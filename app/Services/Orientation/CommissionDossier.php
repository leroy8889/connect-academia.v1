<?php
declare(strict_types=1);

namespace Services\Orientation;

use Core\Database;
use Core\TarifFormationSchema;
use Models\Orientation\DossierStatut;
use PDO;

/**
 * Commission de la plateforme sur une inscription (spec §10).
 *
 * ── Quatre règles, dans cet ordre d'importance ─────────────────────────────
 *
 *  1. **La commission est due quel que soit le statut de bourse.** Aucune
 *     exception : aucun statut n'exonère. Ce qui change avec la refonte
 *     tarifaire, c'est la BASE sur laquelle elle porte, jamais son existence.
 *
 *  2. **La base dépend de la situation de bourse déclarée** (règle métier
 *     posée par la direction, cf. migration_tarifs_formation.sql §3) :
 *
 *       · boursier confirmé      → les FRAIS D'INSCRIPTION par défaut. La
 *                                  scolarité est prise en charge par l'ANBG ;
 *                                  la commissionner reviendrait à facturer
 *                                  l'établissement sur un flux qu'il ne
 *                                  perçoit pas de l'étudiant.
 *                                  Le super administrateur peut inverser ce
 *                                  seul cas, établissement par établissement
 *                                  (`etablissements.base_commission_boursier`),
 *                                  pour les accords qui prévoient la scolarité.
 *       · non boursier / inconnu → la SCOLARITÉ. Rien n'est pris en charge,
 *                                  l'étudiant reste redevable de la totalité.
 *                                  Comportement historique.
 *
 *     La règle elle-même vit dans TarifCycle::baseCommission() : un seul
 *     endroit, sinon elle diverge. Ici on l'applique, on ne la redéfinit pas.
 *     Et comme le taux, la NATURE retenue est figée dans la commission
 *     (`base_commission`) : changer le réglage d'un établissement n'affecte que
 *     les inscriptions suivantes, jamais les commissions déjà émises.
 *
 *  3. **Le taux est GELÉ à la validation à 100 %.** Il a été copié dans
 *     `dossiers_inscription.taux_commission_applique` par la machine à états.
 *     Ce service lit CETTE valeur, jamais le taux courant de l'établissement.
 *     Sans cela, passer un partenaire de 10 % à 12 % recalculerait toutes les
 *     commissions déjà facturées et réglées : le passé deviendrait mouvant et
 *     la comptabilité indéfendable (spec §10.2, §18).
 *
 *  4. **La base est le montant CONTRACTUEL**, pas le montant encaissé le jour
 *     même. Un premier versement suffit à inscrire ; la commission porte sur
 *     l'engagement, pas sur la trésorerie (spec §17).
 *
 * ── Le statut de bourse peut changer après coup ────────────────────────────
 *
 * L'ANBG répond parfois des mois après l'inscription : un candidat enregistré
 * « non boursier » se déclare alors « boursier », et la base devrait suivre. `reevaluerBase()` s'en
 * charge, mais SEULEMENT tant que rien n'a été encaissé. Dès qu'un franc est
 * rentré, elle refuse et lève `revision_requise` : seul le super administrateur
 * arbitre alors. Recalculer une commission dont une tranche est déjà réglée
 * pourrait produire un total inférieur à ce qui a été perçu, donc une créance
 * négative — un état qu'aucun écran de suivi ne sait représenter honnêtement.
 */
final class CommissionDossier
{
    public const A_FACTURER           = 'a_facturer';
    public const FACTUREE             = 'facturee';
    public const PARTIELLEMENT_REGLEE = 'partiellement_reglee';
    public const REGLEE               = 'reglee';
    public const ANNULEE              = 'annulee';
    public const ABANDONNEE           = 'abandonnee';

    /**
     * Recouvrement présentiel, en DEUX tranches (spec §10.4, révisée).
     *
     * Historiquement 40 / 30 / 30. Trois passages en établissement pour une
     * commission de quelques dizaines de milliers de francs coûtaient plus en
     * déplacements qu'ils ne sécurisaient d'encaissement.
     *
     * SOURCE UNIQUE de la répartition : la modifier ici suffit. La dernière
     * tranche absorbe l'arrondi, si bien que la somme des tranches est toujours
     * exactement égale à la commission, au centime près.
     *
     * ⚠️ Les échéanciers DÉJÀ EN BASE ne sont pas refondus par le code : ceux
     * sur lesquels rien n'a été encaissé le sont une fois pour toutes par
     * `migration_commission_boursier_tranches.sql`, les autres gardent leurs
     * trois lignes — déplacer un montant déjà perçu d'une tranche à l'autre
     * rendrait faux le reçu remis à l'établissement. Tout ce qui lit un
     * échéancier doit donc accepter un nombre de tranches variable.
     */
    public const REPARTITION_TRANCHES = [60.0, 40.0];

    private const LIBELLES = [
        self::A_FACTURER           => 'À facturer',
        self::FACTUREE             => 'Facturée',
        self::PARTIELLEMENT_REGLEE => 'Partiellement réglée',
        self::REGLEE               => 'Réglée',
        self::ANNULEE              => 'Annulée',
        self::ABANDONNEE           => 'Abandon d’année',
    ];

    /**
     * Statuts CLOS : plus aucun encaissement, plus aucun retour en arrière
     * depuis le back-office établissement. Seul le super administrateur peut
     * défaire un abandon, et jamais un règlement.
     */
    private const CLOS = [self::REGLEE, self::ANNULEE, self::ABANDONNEE];

    /**
     * « 2 tranches (60 % / 40 %) » — phrase unique, dérivée de la répartition.
     *
     * Une dizaine d'écrans et de messages annonçaient « 3 tranches
     * (40 % / 30 % / 30 %) » en dur. Recopier une règle métier dans les vues,
     * c'est garantir qu'elle finira fausse à la moitié d'entre elles le jour où
     * elle change — ce qui vient précisément d'arriver.
     */
    public static function libelleRepartition(bool $avecNombre = true): string
    {
        $parts = array_map(
            static fn(float $pct): string => rtrim(rtrim(number_format($pct, 2, ',', ' '), '0'), ',') . ' %',
            self::REPARTITION_TRANCHES
        );

        $detail = implode(' / ', $parts);

        return $avecNombre
            ? count($parts) . ' tranches (' . $detail . ')'
            : $detail;
    }

    /**
     * Crée la ligne de commission d'un dossier passé à 100 %.
     *
     * Idempotente : la contrainte d'unicité sur `dossier_id` garantit qu'un
     * rejeu (double clic, webhook doublon, reprise de tâche) ne facture pas
     * deux fois. Appelée à l'intérieur de la transaction de validation, elle
     * lève en cas d'échec — un dossier inscrit sans sa commission serait une
     * perte silencieuse.
     *
     * @param array<string,mixed> $dossier Ligne du dossier APRÈS la transition.
     * @return int|null Identifiant de la commission, null si non applicable.
     */
    public static function generer(array $dossier, ?int $acteurId = null, ?PDO $db = null): ?int
    {
        $dossierId = (int)($dossier['id'] ?? 0);
        if ($dossierId <= 0) {
            return null;
        }

        if (DossierStatut::normaliser((string)$dossier['statut']) !== DossierStatut::VALIDE_100) {
            return null;
        }

        $db ??= Database::getInstance()->getConnection();

        $existante = self::pourDossier($dossierId, $db);
        if ($existante !== null) {
            return (int)$existante['id'];
        }

        // Nature ET montant de la base, déduits de la situation de bourse du
        // dossier au moment de la validation. Les deux sont figés ci-dessous :
        // la commission doit rester explicable même si le statut change demain.
        $statutBourse = $dossier['statut_bourse'] !== null ? (string)$dossier['statut_bourse'] : null;
        $resolution   = TarifCycle::baseDepuisDossier($dossier, $statutBourse, $db);

        $natureBase = $resolution['base'];
        $base       = $resolution['montant'];

        if ($base === null && $natureBase === TarifCycle::BASE_FRAIS_INSCRIPTION) {
            // Boursier dont l'établissement ne facture aucun frais
            // d'inscription : il n'y a rien à commissionner. Ce n'est pas une
            // anomalie, c'est un cas métier — on n'émet donc pas une commission
            // de 0 F que le suivi présenterait comme une créance à recouvrer.
            Journal::dossier(
                Journal::COMMISSION_GENEREE,
                $dossier,
                [
                    'commission_id' => null,
                    'base_nature'   => $natureBase,
                    'motif'         => 'aucun frais d’inscription enregistré — pas de commission',
                    'statut_bourse' => $statutBourse,
                ],
                ['acteur_id' => $acteurId, 'acteur_role' => 'etablissement']
            );

            return null;
        }

        if ($base === null || $base <= 0) {
            // Ne devrait pas arriver : la machine à états refuse les 100 % sans
            // montant de scolarité. Si on y est quand même, on le dit fort.
            throw new \RuntimeException(
                'Commission impossible : la base de calcul (' . $natureBase
                . ') est absente du dossier #' . $dossierId
            );
        }

        // Taux gelé, lu sur le dossier. Le repli sur le taux courant ne joue
        // que pour un dossier antérieur à la refonte.
        $taux = $dossier['taux_commission_applique'] !== null
            ? (float)$dossier['taux_commission_applique']
            : (float)(new \Models\Orientation\Dossier())->tauxCommissionApplicable((int)$dossier['etablissement_id']);

        $montant  = round($base * $taux / 100, 2);
        $periode  = self::periodeFacturation($dossier);

        // La nature de la base et la situation qui l'a justifiée ne sont
        // écrites que si la migration a été rejouée. Sur une base en retard, la
        // commission reste correcte — seule sa traçabilité est amoindrie, et le
        // garde-fou l'a journalisé.
        $traceBase = TarifFormationSchema::baseCommissionDisponible($db);

        $colonnes = ['dossier_id', 'user_id', 'etablissement_id', 'montant_scolarite',
                     'taux_commission', 'montant_commission', 'statut',
                     'periode_facturation', 'created_by', 'created_at'];
        $valeurs  = [':dossier', ':user', ':etab', ':base', ':taux', ':montant',
                     ':statut', ':periode', ':par', 'NOW()'];
        $params   = [
            ':dossier' => $dossierId,
            ':user'    => (int)($dossier['eleve_id'] ?? 0),
            ':etab'    => (int)$dossier['etablissement_id'],
            ':base'    => number_format($base, 2, '.', ''),
            ':taux'    => number_format($taux, 2, '.', ''),
            ':montant' => number_format($montant, 2, '.', ''),
            ':statut'  => self::A_FACTURER,
            ':periode' => $periode,
            ':par'     => $acteurId,
        ];

        if ($traceBase) {
            $colonnes[] = 'base_commission';
            $valeurs[]  = ':nature';
            $params[':nature'] = $natureBase;

            $colonnes[] = 'statut_bourse_applique';
            $valeurs[]  = ':bourse';
            $params[':bourse'] = $statutBourse;
        }

        try {
            $st = $db->prepare(
                'INSERT INTO commissions_scolarite (' . implode(', ', $colonnes) . ')
                 VALUES (' . implode(', ', $valeurs) . ')'
            );
            $st->execute($params);

            $commissionId = (int)$db->lastInsertId();
        } catch (\PDOException $e) {
            // Course entre deux validations simultanées : l'unicité a joué,
            // la commission existe déjà. Ce n'est pas une erreur.
            if (($e->errorInfo[0] ?? '') === '23000') {
                $existante = self::pourDossier($dossierId, $db);

                return $existante === null ? null : (int)$existante['id'];
            }

            throw $e;
        }

        // L'échéancier naît AVEC la commission, dans la même transaction. Le
        // créer plus tard (écran d'administration, tâche planifiée) laisserait
        // exister des commissions sans échéancier : le super administrateur
        // n'aurait rien à encaisser, et l'établissement rien à suivre.
        self::creerTranches($commissionId, $montant, $db);

        Journal::dossier(
            Journal::COMMISSION_GENEREE,
            $dossier,
            [
                'commission_id' => $commissionId,
                'base_calcul'   => $base,
                // Sans la NATURE, le montant de base ne veut rien dire : 270 000
                // peut être une scolarité comme des frais d'inscription.
                'base_nature'   => $natureBase,
                'taux'          => $taux,
                'montant'       => $montant,
                'periode'       => $periode,
                // Tracé explicitement : c'est la situation qui a déterminé la
                // base, et la preuve qu'aucun statut n'exonère de commission.
                'statut_bourse' => $statutBourse,
            ],
            ['acteur_id' => $acteurId, 'acteur_role' => 'etablissement']
        );

        return $commissionId;
    }

    /**
     * Annule la commission d'un dossier annulé par le super administrateur
     * (spec §10.3). Le montant reste visible : on annule, on n'efface pas.
     */
    public static function annuler(int $dossierId, ?int $acteurId = null): bool
    {
        try {
            $db = Database::getInstance()->getConnection();

            $commission = self::pourDossier($dossierId, $db);
            if ($commission === null) {
                return false;
            }

            // Un encaissement déjà constaté ferme la porte : annuler ici
            // effacerait l'échéancier d'où provient une tranche encaissée, et
            // l'argent reçu n'aurait plus de créance en face.
            if (self::montantEncaisse((int)$commission['id'], $db) > 0) {
                return false;
            }

            $st = $db->prepare(
                'UPDATE commissions_scolarite
                 SET statut = :annulee
                 WHERE dossier_id = :dossier AND statut NOT IN (:reglee, :abandon)'
            );
            $st->execute([
                ':annulee' => self::ANNULEE,
                ':dossier' => $dossierId,
                ':reglee'  => self::REGLEE,
                ':abandon' => self::ABANDONNEE,
            ]);

            if ($st->rowCount() === 0) {
                return false;
            }

            // L'échéancier disparaît avec la créance : le dossier peut repasser
            // à 100 %, et il redemandera alors des tranches neuves, calculées
            // sur les montants du jour.
            $db->prepare('DELETE FROM commission_tranches WHERE commission_id = ?')
               ->execute([(int)$commission['id']]);
        } catch (\Throwable $e) {
            error_log('[CommissionDossier] Annulation échouée #' . $dossierId . ' : ' . $e->getMessage());

            return false;
        }

        Journal::ecrire(Journal::COMMISSION_ANNULEE, [
            'objet_type' => 'dossier',
            'objet_id'   => $dossierId,
            'acteur_id'  => $acteurId,
        ]);

        return true;
    }

    /**
     * Change le statut de facturation d'une commission (écran super admin).
     * Une commission réglée ne redevient jamais « à facturer » : ce serait
     * réécrire une opération comptable close.
     */
    public static function changerStatut(
        int $commissionId,
        string $statut,
        ?int $acteurId = null,
        ?string $reference = null
    ): bool {
        if (!isset(self::LIBELLES[$statut])) {
            return false;
        }

        try {
            $db = Database::getInstance()->getConnection();

            $st = $db->prepare('SELECT * FROM commissions_scolarite WHERE id = ? LIMIT 1');
            $st->execute([$commissionId]);
            $commission = $st->fetch(PDO::FETCH_ASSOC);

            if ($commission === false || in_array((string)$commission['statut'], self::CLOS, true)) {
                return false;
            }

            // « Réglée » à la main reste possible (règlement global hors
            // échéancier), mais elle solde alors TOUTES les tranches restantes :
            // laisser des tranches ouvertes sous une commission réglée ferait
            // afficher à l'établissement un reste à verser inexistant.
            if ($statut === self::REGLEE) {
                $db->prepare(
                    'UPDATE commission_tranches
                     SET est_payee = 1, date_paiement = COALESCE(date_paiement, NOW()),
                         valide_par = COALESCE(valide_par, :par)
                     WHERE commission_id = :id AND est_payee = 0 AND est_abandonnee = 0'
                )->execute([':par' => $acteurId, ':id' => $commissionId]);
            }

            $db->prepare(
                'UPDATE commissions_scolarite
                 SET statut = :statut,
                     regle_le = CASE WHEN :statut2 = :reglee THEN NOW() ELSE regle_le END,
                     reference_reglement = COALESCE(:ref, reference_reglement)
                 WHERE id = :id'
            )->execute([
                ':statut'  => $statut,
                ':statut2' => $statut,
                ':reglee'  => self::REGLEE,
                ':ref'     => $reference !== null && trim($reference) !== '' ? mb_substr(trim($reference), 0, 80) : null,
                ':id'      => $commissionId,
            ]);
        } catch (\Throwable $e) {
            error_log('[CommissionDossier] Changement de statut #' . $commissionId . ' : ' . $e->getMessage());

            return false;
        }

        Journal::ecrire(Journal::COMMISSION_STATUT, [
            'objet_type' => 'commission',
            'objet_id'   => $commissionId,
            'acteur_id'  => $acteurId,
            'donnees'    => ['nouveau_statut' => $statut, 'reference' => $reference],
        ]);

        return true;
    }

    /**
     * Commission d'un dossier, s'il en a une.
     *
     * @return array<string,mixed>|null
     */
    public static function pourDossier(int $dossierId, ?PDO $db = null): ?array
    {
        try {
            $db ??= Database::getInstance()->getConnection();

            $st = $db->prepare('SELECT * FROM commissions_scolarite WHERE dossier_id = ? LIMIT 1');
            $st->execute([$dossierId]);
            $ligne = $st->fetch(PDO::FETCH_ASSOC);

            return $ligne === false ? null : $ligne;
        } catch (\Throwable $e) {
            error_log('[CommissionDossier] Lecture échouée #' . $dossierId . ' : ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Suivi des commissions, filtrable (spec §10.3).
     *
     * @param array{etablissement_id?:int, statut?:string, periode?:string, du?:string, au?:string} $filtres
     * @return list<array<string,mixed>>
     */
    public static function rechercher(array $filtres = [], int $limite = 100, int $offset = 0): array
    {
        [$where, $params] = self::clauses($filtres);

        $limite = max(1, min($limite, 500));
        $offset = max(0, $offset);

        try {
            $st = Database::getInstance()->getConnection()->prepare(
                "SELECT c.*, e.nom AS etab_nom,
                        d.reference, d.nom_complet, d.statut_bourse, d.valide_100_le,
                        d.niveau_entree, d.filiere_visee,
                        d.abandon_le, d.abandon_motif
                 FROM commissions_scolarite c
                 JOIN etablissements e ON e.id = c.etablissement_id
                 LEFT JOIN dossiers_inscription d ON d.id = c.dossier_id
                 {$where}
                 ORDER BY c.created_at DESC
                 LIMIT {$limite} OFFSET {$offset}"
            );
            $st->execute($params);

            // L'échéancier fait partie de la ligne : l'écran de suivi encaisse
            // tranche par tranche, il ne peut pas se contenter d'un total.
            return self::attacherTranches($st->fetchAll(PDO::FETCH_ASSOC));
        } catch (\Throwable $e) {
            error_log('[CommissionDossier] Recherche échouée : ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Totaux par statut pour les mêmes filtres — en-tête de l'écran de suivi.
     *
     * @return array<string,array{nombre:int,montant:float}>
     */
    public static function totaux(array $filtres = []): array
    {
        $filtresSansStatut = $filtres;
        unset($filtresSansStatut['statut']);

        [$where, $params] = self::clauses($filtresSansStatut);

        $totaux = [];
        foreach (array_keys(self::LIBELLES) as $statut) {
            $totaux[$statut] = ['nombre' => 0, 'montant' => 0.0];
        }

        try {
            $st = Database::getInstance()->getConnection()->prepare(
                "SELECT c.statut, COUNT(*) AS n, COALESCE(SUM(c.montant_commission), 0) AS total
                 FROM commissions_scolarite c
                 {$where}
                 GROUP BY c.statut"
            );
            $st->execute($params);

            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
                // Les statuts hérités ('en_cours', 'solde') sont repliés sur
                // leur équivalent actuel.
                $cle = match ((string)$ligne['statut']) {
                    'en_cours' => self::A_FACTURER,
                    'solde'    => self::REGLEE,
                    default    => (string)$ligne['statut'],
                };

                if (!isset($totaux[$cle])) {
                    continue;
                }

                $totaux[$cle]['nombre']  += (int)$ligne['n'];
                $totaux[$cle]['montant'] += (float)$ligne['total'];
            }
        } catch (\Throwable $e) {
            error_log('[CommissionDossier] Totaux échoués : ' . $e->getMessage());
        }

        return $totaux;
    }

    /**
     * Encaissé / restant dû / abandonné, sur les mêmes filtres que la liste.
     *
     * Calculé en base et non sur la page affichée : un total qui ne porterait
     * que sur les 60 lignes visibles serait faux, et faux sur de l'argent.
     *
     * @return array{encaisse:float, restant:float, abandonne:float}
     */
    public static function totauxTranches(array $filtres = []): array
    {
        $filtresSansStatut = $filtres;
        unset($filtresSansStatut['statut']);

        [$where, $params] = self::clauses($filtresSansStatut);

        $jointure = 'JOIN commission_tranches t ON t.commission_id = c.id';
        $clause   = $where === ''
            ? "WHERE c.statut <> '" . self::ANNULEE . "'"
            : $where . " AND c.statut <> '" . self::ANNULEE . "'";

        $totaux = ['encaisse' => 0.0, 'restant' => 0.0, 'abandonne' => 0.0];

        try {
            $st = Database::getInstance()->getConnection()->prepare(
                "SELECT
                    COALESCE(SUM(CASE WHEN t.est_payee = 1 THEN t.montant ELSE 0 END), 0) AS encaisse,
                    COALESCE(SUM(CASE WHEN t.est_payee = 0 AND t.est_abandonnee = 0 THEN t.montant ELSE 0 END), 0) AS restant,
                    COALESCE(SUM(CASE WHEN t.est_payee = 0 AND t.est_abandonnee = 1 THEN t.montant ELSE 0 END), 0) AS abandonne
                 FROM commissions_scolarite c
                 {$jointure}
                 {$clause}"
            );
            $st->execute($params);
            $ligne = $st->fetch(PDO::FETCH_ASSOC);

            if ($ligne !== false) {
                $totaux = [
                    'encaisse'  => (float)$ligne['encaisse'],
                    'restant'   => (float)$ligne['restant'],
                    'abandonne' => (float)$ligne['abandonne'],
                ];
            }
        } catch (\Throwable $e) {
            error_log('[CommissionDossier] Totaux de tranches échoués : ' . $e->getMessage());
        }

        return $totaux;
    }

    /** @return array<string,string> */
    public static function statuts(): array
    {
        return self::LIBELLES;
    }

    public static function libelle(string $statut): string
    {
        return self::LIBELLES[$statut] ?? match ($statut) {
            'en_cours' => self::LIBELLES[self::A_FACTURER],
            'solde'    => self::LIBELLES[self::REGLEE],
            default    => $statut,
        };
    }

    // ── Tranches de recouvrement (spec §10.4) ────────────────────────────────

    /**
     * Crée l'échéancier d'une commission selon REPARTITION_TRANCHES (60 / 40).
     *
     * Idempotente par la contrainte d'unicité (commission_id, numero) : un
     * rejeu ne double jamais l'échéancier. La dernière tranche reçoit le
     * RESTE et non 40 % recalculés — sans cela, des arrondis successifs peuvent
     * faire diverger la somme des tranches de la commission de quelques francs,
     * et un établissement solderait sans jamais atteindre le total.
     *
     * Les tranches EXCÉDENTAIRES sont purgées à la fin : un échéancier hérité
     * de la répartition en trois temps, régénéré ici, laisserait sinon traîner
     * une troisième ligne que plus rien n'alimente et que le suivi présenterait
     * comme un reste à recouvrer. La purge épargne toute tranche encaissée ou
     * close — celles-là sont des faits, pas des lignes de calcul.
     */
    public static function creerTranches(int $commissionId, float $montantCommission, ?PDO $db = null): void
    {
        if ($commissionId <= 0 || $montantCommission <= 0) {
            return;
        }

        $db ??= Database::getInstance()->getConnection();

        try {
            $st = $db->prepare(
                'INSERT INTO commission_tranches (commission_id, numero, pourcentage, montant)
                 VALUES (:commission, :numero, :pct, :montant)
                 ON DUPLICATE KEY UPDATE commission_id = commission_id'
            );

            $restant = round($montantCommission, 2);
            $dernier = count(self::REPARTITION_TRANCHES) - 1;

            foreach (self::REPARTITION_TRANCHES as $i => $pct) {
                $montant = $i === $dernier
                    ? round($restant, 2)
                    : round($montantCommission * $pct / 100, 2);
                $restant = round($restant - $montant, 2);

                $st->execute([
                    ':commission' => $commissionId,
                    ':numero'     => $i + 1,
                    ':pct'        => number_format($pct, 2, '.', ''),
                    ':montant'    => number_format($montant, 2, '.', ''),
                ]);
            }

            $db->prepare(
                'DELETE FROM commission_tranches
                  WHERE commission_id = :commission
                    AND numero > :dernier
                    AND est_payee = 0
                    AND est_abandonnee = 0'
            )->execute([
                ':commission' => $commissionId,
                ':dernier'    => count(self::REPARTITION_TRANCHES),
            ]);
        } catch (\Throwable $e) {
            error_log('[CommissionDossier] Tranches non créées #' . $commissionId . ' : ' . $e->getMessage());

            throw $e; // Appelé dans la transaction de validation : elle doit échouer.
        }
    }

    /**
     * Tranches d'une commission, ordonnées.
     *
     * @return list<array<string,mixed>>
     */
    public static function tranches(int $commissionId, ?PDO $db = null): array
    {
        try {
            $db ??= Database::getInstance()->getConnection();
            $st = $db->prepare(
                'SELECT * FROM commission_tranches WHERE commission_id = ? ORDER BY numero'
            );
            $st->execute([$commissionId]);

            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[CommissionDossier] Tranches illisibles #' . $commissionId . ' : ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Attache les tranches à une liste de commissions, en une seule requête.
     *
     * @param list<array<string,mixed>> $commissions
     * @return list<array<string,mixed>>
     */
    public static function attacherTranches(array $commissions): array
    {
        if ($commissions === []) {
            return [];
        }

        $ids = array_values(array_filter(array_map(
            static fn(array $c): int => (int)($c['id'] ?? 0),
            $commissions
        )));

        if ($ids === []) {
            foreach ($commissions as &$c) { $c['tranches'] = []; }

            return $commissions;
        }

        $parCommission = [];

        try {
            $marques = implode(',', array_fill(0, count($ids), '?'));
            $st = Database::getInstance()->getConnection()->prepare(
                "SELECT * FROM commission_tranches
                 WHERE commission_id IN ({$marques})
                 ORDER BY commission_id, numero"
            );
            $st->execute($ids);

            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $tranche) {
                $parCommission[(int)$tranche['commission_id']][] = $tranche;
            }
        } catch (\Throwable $e) {
            error_log('[CommissionDossier] Attache des tranches échouée : ' . $e->getMessage());
        }

        foreach ($commissions as &$c) {
            $c['tranches'] = $parCommission[(int)($c['id'] ?? 0)] ?? [];
        }
        unset($c);

        return $commissions;
    }

    /**
     * Encaisse (ou dés-encaisse) une tranche — geste du SUPER ADMINISTRATEUR,
     * seul à voir l'argent arriver.
     *
     * Deux règles d'ordre, reprises du recouvrement réel :
     *  - la tranche N ne s'encaisse pas avant la N-1 ;
     *  - la tranche N ne s'annule pas si la N+1 est déjà encaissée.
     * Sans elles, un échéancier « 1 impayée, 2 payée, 3 impayée » deviendrait
     * possible : indéfendable devant un établissement.
     *
     * Une tranche fermée par un abandon d'année n'est plus encaissable : c'est
     * précisément ce que l'abandon acte.
     *
     * @return array{ok:bool, message:string, commission_statut:?string}
     */
    public static function payerTranche(
        int $trancheId,
        bool $payee,
        ?int $acteurId = null,
        ?string $reference = null
    ): array {
        try {
            $db = Database::getInstance()->getConnection();

            $st = $db->prepare(
                'SELECT t.*, c.statut AS commission_statut, c.dossier_id
                 FROM commission_tranches t
                 JOIN commissions_scolarite c ON c.id = t.commission_id
                 WHERE t.id = ? LIMIT 1'
            );
            $st->execute([$trancheId]);
            $tranche = $st->fetch(PDO::FETCH_ASSOC);

            if ($tranche === false) {
                return self::echecTranche('Tranche introuvable.');
            }

            $commissionId = (int)$tranche['commission_id'];
            $numero       = (int)$tranche['numero'];

            if ((string)$tranche['commission_statut'] === self::ANNULEE) {
                return self::echecTranche('Cette commission est annulée : plus rien n’y est encaissable.');
            }
            if ((int)$tranche['est_abandonnee'] === 1) {
                return self::echecTranche(
                    'Cette tranche est close par un abandon d’année. Levez l’abandon avant de l’encaisser.'
                );
            }
            if ((int)$tranche['est_payee'] === ($payee ? 1 : 0)) {
                return self::echecTranche('Cette tranche est déjà dans cet état.');
            }

            $etats = [];
            foreach (self::tranches($commissionId, $db) as $t) {
                $etats[(int)$t['numero']] = (int)$t['est_payee'] === 1;
            }

            if ($payee && $numero > 1 && empty($etats[$numero - 1])) {
                return self::echecTranche(
                    'La tranche ' . ($numero - 1) . ' doit être encaissée avant la tranche ' . $numero . '.'
                );
            }
            if (!$payee && !empty($etats[$numero + 1] ?? false)) {
                return self::echecTranche(
                    'Impossible de revenir sur la tranche ' . $numero . ' : la tranche ' . ($numero + 1)
                    . ' est déjà encaissée.'
                );
            }

            $reference = $reference !== null && trim($reference) !== ''
                ? mb_substr(trim($reference), 0, 80)
                : null;

            // ── Écriture conditionnée à l'état LU plus haut (compare-and-swap) ──
            //
            // Le SELECT du début et cet UPDATE ne sont pas atomiques. Un agent
            // qui double-clique sur « encaisser » lance deux requêtes : les deux
            // lisent `est_payee = 0`, les deux franchissent le garde « déjà dans
            // cet état », et la seconde ÉCRASAIT la première. On perdait alors
            // le validateur et la référence de règlement réellement retenus —
            // sur une écriture financière, dont la traçabilité est justement
            // ce qui la rend opposable — et le journal portait deux entrées
            // pour un seul encaissement.
            //
            // En posant l'état attendu dans le WHERE, une seule des deux
            // requêtes concurrentes touche la ligne ; l'autre voit zéro ligne
            // affectée et se retire sans rien journaliser.
            $maj = $db->prepare(
                'UPDATE commission_tranches
                 SET est_payee = :payee,
                     date_paiement = :date,
                     valide_par = :par,
                     reference_reglement = :ref
                 WHERE id = :id AND est_payee = :attendu'
            );
            $maj->execute([
                ':payee'   => $payee ? 1 : 0,
                ':date'    => $payee ? date('Y-m-d H:i:s') : null,
                ':par'     => $payee ? $acteurId : null,
                ':ref'     => $payee ? $reference : null,
                ':id'      => $trancheId,
                ':attendu' => $payee ? 0 : 1,
            ]);

            if ($maj->rowCount() === 0) {
                return self::echecTranche(
                    'Cette tranche vient d’être modifiée par ailleurs. Rafraîchissez la page avant de réessayer.'
                );
            }

            $statut = self::recalculerStatut($commissionId, $acteurId, $db);
        } catch (\Throwable $e) {
            error_log('[CommissionDossier] Tranche #' . $trancheId . ' : ' . $e->getMessage());

            return self::echecTranche('La tranche n’a pas pu être mise à jour.');
        }

        Journal::ecrire(Journal::COMMISSION_STATUT, [
            'objet_type' => 'commission',
            'objet_id'   => $commissionId,
            'acteur_id'  => $acteurId,
            'donnees'    => [
                'tranche'          => $numero,
                'encaissee'        => $payee,
                'reference'        => $reference,
                'nouveau_statut'   => $statut,
            ],
        ]);

        return [
            'ok'      => true,
            'message' => $payee
                ? 'Tranche ' . $numero . ' encaissée. L’établissement voit la mise à jour immédiatement.'
                : 'Tranche ' . $numero . ' remise en attente.',
            'commission_statut' => $statut,
        ];
    }

    /**
     * Recalcule le statut d'une commission à partir de ses tranches.
     *
     * Les statuts CLOS ne sont jamais recalculés : ils ont été posés par une
     * décision (annulation, abandon), pas déduits d'un compte de cases cochées.
     */
    public static function recalculerStatut(int $commissionId, ?int $acteurId = null, ?PDO $db = null): string
    {
        $db ??= Database::getInstance()->getConnection();

        $st = $db->prepare('SELECT statut FROM commissions_scolarite WHERE id = ? LIMIT 1');
        $st->execute([$commissionId]);
        $courant = (string)($st->fetchColumn() ?: self::A_FACTURER);

        if (in_array($courant, [self::ANNULEE, self::ABANDONNEE], true)) {
            return $courant;
        }

        $tranches = self::tranches($commissionId, $db);
        if ($tranches === []) {
            return $courant;
        }

        $payees  = 0;
        $ouverte = 0;
        foreach ($tranches as $t) {
            if ((int)$t['est_payee'] === 1) {
                $payees++;
            } elseif ((int)$t['est_abandonnee'] === 0) {
                $ouverte++;
            }
        }

        if ($payees > 0 && $ouverte === 0) {
            $nouveau = self::REGLEE;
        } elseif ($payees > 0) {
            $nouveau = self::PARTIELLEMENT_REGLEE;
        } else {
            // Aucune tranche encaissée : on ne rétrograde pas une commission
            // déjà facturée — la facture, elle, a bien été émise. Un
            // encaissement défait ramène donc à « facturée », pas à
            // « à facturer » : on n'encaisse pas ce qu'on n'a pas facturé, et
            // faire retaper la facturation après une correction de saisie
            // finirait par produire des doubles facturations.
            $nouveau = in_array($courant, [self::FACTUREE, self::PARTIELLEMENT_REGLEE, self::REGLEE], true)
                ? self::FACTUREE
                : self::A_FACTURER;
        }

        $db->prepare(
            'UPDATE commissions_scolarite
             SET statut = :statut,
                 regle_le = CASE WHEN :statut2 = :reglee THEN COALESCE(regle_le, NOW()) ELSE NULL END
             WHERE id = :id'
        )->execute([
            ':statut'  => $nouveau,
            ':statut2' => $nouveau,
            ':reglee'  => self::REGLEE,
            ':id'      => $commissionId,
        ]);

        return $nouveau;
    }

    // ── Révision de la base après changement de situation de bourse ──────────

    /**
     * Réévalue la base d'une commission dont le dossier a changé de situation
     * de bourse (l'ANBG a répondu, ou une correction a été apportée).
     *
     * ── Ce qui autorise le recalcul ──────────────────────────────────────────
     *
     * Deux conditions cumulatives, et aucune dérogation :
     *   · la commission n'est pas close (ni réglée, ni annulée, ni abandonnée) ;
     *   · AUCUNE tranche n'a été encaissée.
     *
     * Dès qu'un franc est rentré, recalculer produirait une créance dont le
     * total peut devenir inférieur au perçu — un état qu'aucun écran de suivi
     * ne sait représenter, et qui obligerait à inventer un remboursement. On
     * lève donc `revision_requise` : le super administrateur voit le dossier
     * remonter dans son suivi et tranche lui-même.
     *
     * ── Ce que la méthode ne fait jamais ─────────────────────────────────────
     *
     * Elle ne touche NI au taux (gelé à la validation), NI aux montants figés
     * sur le dossier. Elle change la NATURE de la base retenue — scolarité ou
     * frais d'inscription — et le montant qui en découle, puis régénère
     * l'échéancier, qui n'a jamais été encaissé sinon on ne serait pas là.
     *
     * ── Elle relit le DOSSIER, jamais la configuration du jour ───────────────
     *
     * Conséquence à connaître : un candidat passé « non boursier » puis
     * « boursier » voit sa commission calculée sur les frais d'inscription
     * RÉELLEMENT encaissés au guichet — c'est-à-dire le tarif non boursier
     * qu'il a payé ce jour-là —, et non sur le tarif boursier en vigueur
     * aujourd'hui.
     *
     * C'est délibéré. Recalculer depuis la grille reviendrait à commissionner
     * un montant que l'établissement n'a jamais perçu, et à faire dépendre une
     * commission d'une grille tarifaire que le super administrateur peut
     * modifier après coup. Le remboursement éventuel du trop-perçu au candidat
     * relève de l'établissement, pas de la plateforme.
     *
     * @return array{
     *   ok:bool, action:string, message:string,
     *   ancien_montant:?float, nouveau_montant:?float
     * } action ∈ {aucune, recalculee, signalee}
     */
    public static function reevaluerBase(
        int $dossierId,
        ?int $acteurId = null,
        ?string $acteurRole = null,
        ?PDO $db = null
    ): array {
        $rien = static fn(string $message): array => [
            'ok' => true, 'action' => 'aucune', 'message' => $message,
            'ancien_montant' => null, 'nouveau_montant' => null,
        ];

        try {
            $db ??= Database::getInstance()->getConnection();

            if (!TarifFormationSchema::baseCommissionDisponible($db)) {
                // Sans les colonnes de traçabilité, une révision serait muette :
                // on ne pourrait ni dire pourquoi le montant a changé, ni
                // signaler la commission au super administrateur.
                return $rien('Traçabilité de la base indisponible — révision ignorée.');
            }

            $commission = self::pourDossier($dossierId, $db);
            if ($commission === null) {
                // Cas le plus fréquent, et parfaitement normal : le dossier
                // n'est pas encore à 100 %. La base sera calculée au bon
                // moment, avec la situation du jour.
                return $rien('Aucune commission à réviser.');
            }

            $st = $db->prepare(
                'SELECT id, statut, statut_bourse, etablissement_id,
                        montant_scolarite_annuelle, montant_frais_inscription,
                        taux_commission_applique, reference, nom_complet, eleve_id
                   FROM dossiers_inscription WHERE id = ? LIMIT 1'
            );
            $st->execute([$dossierId]);
            $dossier = $st->fetch(PDO::FETCH_ASSOC);

            if ($dossier === false) {
                return $rien('Dossier introuvable.');
            }

            $statutBourse = $dossier['statut_bourse'] !== null ? (string)$dossier['statut_bourse'] : null;
            $resolution   = TarifCycle::baseDepuisDossier($dossier, $statutBourse, $db);

            $taux            = (float)($commission['taux_commission'] ?? 0);
            $ancienMontant   = (float)$commission['montant_commission'];
            $nouveauMontant  = $resolution['montant'] !== null
                ? round($resolution['montant'] * $taux / 100, 2)
                : null;

            $inchangee = (string)($commission['base_commission'] ?? TarifCycle::BASE_SCOLARITE) === $resolution['base']
                && $nouveauMontant !== null
                && abs($nouveauMontant - $ancienMontant) < 0.01;

            if ($inchangee) {
                return $rien('Base inchangée.');
            }

            $encaisse = self::montantEncaisse((int)$commission['id'], $db);
            $close    = self::estClose($commission);

            if ($close || $encaisse > 0 || $nouveauMontant === null) {
                $motif = $close
                    ? 'Commission close (' . self::libelle((string)$commission['statut']) . ')'
                    : ($encaisse > 0
                        ? 'Tranche(s) déjà encaissée(s) : ' . number_format($encaisse, 0, ',', ' ') . ' XAF'
                        : 'Nouvelle base non renseignée sur le dossier');

                $motif .= ' — situation de bourse passée à « '
                        . SituationBourse::libelleCourt($statutBourse) . ' ».';

                self::signalerRevision((int)$commission['id'], $motif, $db);

                Journal::ecrire(Journal::COMMISSION_STATUT, [
                    'objet_type'       => 'commission',
                    'objet_id'         => (int)$commission['id'],
                    'acteur_id'        => $acteurId,
                    'acteur_role'      => $acteurRole,
                    'etablissement_id' => (int)$dossier['etablissement_id'],
                    'donnees'          => [
                        'revision_requise' => true,
                        'motif'            => $motif,
                        'base_actuelle'    => $commission['base_commission'] ?? null,
                        'base_attendue'    => $resolution['base'],
                        'montant_actuel'   => $ancienMontant,
                        'montant_attendu'  => $nouveauMontant,
                    ],
                ]);

                return [
                    'ok'              => true,
                    'action'          => 'signalee',
                    'message'         => 'Commission signalée au super administrateur : ' . $motif,
                    'ancien_montant'  => $ancienMontant,
                    'nouveau_montant' => $nouveauMontant,
                ];
            }

            // ── Recalcul effectif ────────────────────────────────────────────
            // Transaction : une commission dont le montant a changé mais dont
            // l'échéancier porte encore l'ancien total serait un écart
            // comptable silencieux.
            $externe = $db->inTransaction();
            if (!$externe) {
                $db->beginTransaction();
            }

            try {
                $db->prepare(
                    'UPDATE commissions_scolarite
                        SET montant_scolarite       = :base,
                            montant_commission      = :montant,
                            base_commission         = :nature,
                            statut_bourse_applique  = :bourse,
                            revision_requise        = 0,
                            revision_motif          = NULL,
                            revision_le             = NULL
                      WHERE id = :id'
                )->execute([
                    ':base'    => number_format((float)$resolution['montant'], 2, '.', ''),
                    ':montant' => number_format($nouveauMontant, 2, '.', ''),
                    ':nature'  => $resolution['base'],
                    ':bourse'  => $statutBourse,
                    ':id'      => (int)$commission['id'],
                ]);

                // L'échéancier repart de zéro : aucune tranche n'était
                // encaissée (vérifié plus haut), donc rien n'est perdu.
                $db->prepare('DELETE FROM commission_tranches WHERE commission_id = ?')
                   ->execute([(int)$commission['id']]);

                self::creerTranches((int)$commission['id'], $nouveauMontant, $db);

                if (!$externe) {
                    $db->commit();
                }
            } catch (\Throwable $e) {
                if (!$externe && $db->inTransaction()) {
                    $db->rollBack();
                }
                throw $e;
            }

            Journal::dossier(
                Journal::COMMISSION_GENEREE,
                $dossier,
                [
                    'commission_id'   => (int)$commission['id'],
                    'revision'        => true,
                    'base_nature'     => $resolution['base'],
                    'base_calcul'     => $resolution['montant'],
                    'taux'            => $taux,
                    'ancien_montant'  => $ancienMontant,
                    'montant'         => $nouveauMontant,
                    'statut_bourse'   => $statutBourse,
                ],
                ['acteur_id' => $acteurId, 'acteur_role' => $acteurRole ?? 'admin']
            );

            return [
                'ok'              => true,
                'action'          => 'recalculee',
                'message'         => 'Commission recalculée sur ' . mb_strtolower(TarifCycle::libelleBase($resolution['base'])) . '.',
                'ancien_montant'  => $ancienMontant,
                'nouveau_montant' => $nouveauMontant,
            ];
        } catch (\Throwable $e) {
            error_log('[CommissionDossier] Révision de base échouée #' . $dossierId . ' : ' . $e->getMessage());

            return [
                'ok' => false, 'action' => 'aucune',
                'message' => 'La révision de la commission a échoué.',
                'ancien_montant' => null, 'nouveau_montant' => null,
            ];
        }
    }

    /**
     * Lève le drapeau de révision sur une commission.
     * Idempotent : un motif plus récent remplace le précédent, l'horodatage
     * suit — ce qui compte pour le super administrateur, c'est la dernière
     * raison connue, pas la première.
     */
    private static function signalerRevision(int $commissionId, string $motif, PDO $db): void
    {
        try {
            $db->prepare(
                'UPDATE commissions_scolarite
                    SET revision_requise = 1, revision_motif = :motif, revision_le = NOW()
                  WHERE id = :id'
            )->execute([
                ':motif' => mb_substr($motif, 0, 255),
                ':id'    => $commissionId,
            ]);
        } catch (\Throwable $e) {
            error_log('[CommissionDossier] Signalement impossible #' . $commissionId . ' : ' . $e->getMessage());
        }
    }

    /**
     * Le super administrateur a arbitré : le drapeau retombe.
     * N'exige AUCUN changement de montant — décider qu'il n'y a rien à changer
     * est une décision comme une autre, et elle doit pouvoir se solder.
     */
    public static function cloreRevision(int $commissionId, ?int $acteurId = null): bool
    {
        try {
            $db = Database::getInstance()->getConnection();

            if (!TarifFormationSchema::baseCommissionDisponible($db)) {
                return false;
            }

            $st = $db->prepare(
                'UPDATE commissions_scolarite
                    SET revision_requise = 0, revision_le = NULL
                  WHERE id = ? AND revision_requise = 1'
            );
            $st->execute([$commissionId]);

            if ($st->rowCount() === 0) {
                return false;
            }
        } catch (\Throwable $e) {
            error_log('[CommissionDossier] Clôture de révision #' . $commissionId . ' : ' . $e->getMessage());

            return false;
        }

        Journal::ecrire(Journal::COMMISSION_STATUT, [
            'objet_type' => 'commission',
            'objet_id'   => $commissionId,
            'acteur_id'  => $acteurId,
            'donnees'    => ['revision_close' => true],
        ]);

        return true;
    }

    /** Somme réellement encaissée sur une commission. */
    public static function montantEncaisse(int $commissionId, ?PDO $db = null): float
    {
        try {
            $db ??= Database::getInstance()->getConnection();
            $st = $db->prepare(
                'SELECT COALESCE(SUM(montant), 0) FROM commission_tranches
                 WHERE commission_id = ? AND est_payee = 1'
            );
            $st->execute([$commissionId]);

            return (float)$st->fetchColumn();
        } catch (\Throwable $e) {
            error_log('[CommissionDossier] Encaissé illisible #' . $commissionId . ' : ' . $e->getMessage());

            return 0.0;
        }
    }

    // ── Abandon d'année (spec §10.5) ─────────────────────────────────────────

    /**
     * Constate l'abandon de l'année universitaire par l'étudiant.
     *
     * **Décision du SUPER ADMINISTRATEUR, jamais de l'établissement.** C'est
     * l'établissement qui signale la situation — il perd un étudiant, il a
     * intérêt à ce que la créance s'éteigne. Laisser sa propre console fermer
     * ses tranches reviendrait à lui laisser annuler sa dette.
     *
     * Ce qui se passe :
     *  - le dossier reçoit un abandon daté et motivé — son STATUT ne change pas,
     *    l'inscription a bien eu lieu et l'attestation reste valable ;
     *  - les tranches non encaissées sont closes : elles ne seront jamais dues ;
     *  - les tranches DÉJÀ encaissées restent acquises — l'argent est rentré ;
     *  - la commission passe à « abandon d'année », distincte d'« annulée ».
     *
     * @return array{ok:bool, message:string}
     */
    public static function declarerAbandon(int $dossierId, ?int $acteurId, ?string $motif): array
    {
        $motif = trim((string)$motif);
        if (mb_strlen($motif) < 5) {
            return ['ok' => false, 'message' => 'Indiquez le motif de l’abandon (5 caractères minimum).'];
        }
        $motif = mb_substr($motif, 0, 500);

        $db      = Database::getInstance()->getConnection();
        $externe = $db->inTransaction();

        try {
            $st = $db->prepare('SELECT * FROM dossiers_inscription WHERE id = ? LIMIT 1');
            $st->execute([$dossierId]);
            $dossier = $st->fetch(PDO::FETCH_ASSOC);

            if ($dossier === false) {
                return ['ok' => false, 'message' => 'Dossier introuvable.'];
            }
            if (DossierStatut::normaliser((string)$dossier['statut']) !== DossierStatut::VALIDE_100) {
                return ['ok' => false, 'message' => 'Seul un étudiant inscrit à 100 % peut être déclaré en abandon.'];
            }
            if (!empty($dossier['abandon_le'])) {
                return ['ok' => false, 'message' => 'Cet étudiant est déjà déclaré en abandon.'];
            }

            if (!$externe) {
                $db->beginTransaction();
            }

            $db->prepare(
                'UPDATE dossiers_inscription
                 SET abandon_le = NOW(), abandon_par = :par, abandon_motif = :motif
                 WHERE id = :id'
            )->execute([':par' => $acteurId, ':motif' => $motif, ':id' => $dossierId]);

            $commission = self::pourDossier($dossierId, $db);

            if ($commission !== null) {
                $db->prepare(
                    'UPDATE commission_tranches
                     SET est_abandonnee = 1, abandon_le = NOW()
                     WHERE commission_id = ? AND est_payee = 0'
                )->execute([(int)$commission['id']]);

                $db->prepare(
                    'UPDATE commissions_scolarite
                     SET statut = :statut, abandon_le = NOW(), abandon_par = :par, abandon_motif = :motif
                     WHERE id = :id'
                )->execute([
                    ':statut' => self::ABANDONNEE,
                    ':par'    => $acteurId,
                    ':motif'  => $motif,
                    ':id'     => (int)$commission['id'],
                ]);
            }

            if (!$externe) {
                $db->commit();
            }
        } catch (\Throwable $e) {
            if (!$externe && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[CommissionDossier] Abandon #' . $dossierId . ' : ' . $e->getMessage());

            return ['ok' => false, 'message' => 'L’abandon n’a pas pu être enregistré. Réessayez.'];
        }

        $encaisse = $commission !== null ? self::montantEncaisse((int)$commission['id']) : 0.0;

        Journal::dossier(
            Journal::ABANDON_DECLARE,
            $dossier,
            [
                'motif'            => $motif,
                'commission_id'    => $commission['id'] ?? null,
                'montant_acquis'   => $encaisse,
                'montant_abandonne'=> $commission !== null
                    ? max(0.0, (float)$commission['montant_commission'] - $encaisse)
                    : 0.0,
            ],
            ['acteur_id' => $acteurId, 'acteur_role' => 'super_admin']
        );

        self::notifierEtablissement(
            $dossier,
            'abandon_declare',
            'Abandon d’année constaté',
            trim((string)($dossier['nom_complet'] ?? 'Un étudiant'))
            . ' (' . (string)($dossier['reference'] ?? 'dossier #' . $dossierId) . ') est déclaré en abandon '
            . 'd’année par Connect’Academia. Les tranches de commission non encaissées sont closes : '
            . 'plus rien ne vous sera réclamé sur ce dossier. Motif : ' . $motif
        );

        return ['ok' => true, 'message' => 'Abandon enregistré. Les tranches non encaissées sont closes.'];
    }

    /**
     * Lève un abandon déclaré par erreur — super administrateur uniquement.
     * Les tranches rouvrent exactement là où elles en étaient : celles déjà
     * encaissées ne bougent pas, les closes redeviennent dues.
     *
     * @return array{ok:bool, message:string}
     */
    public static function leverAbandon(int $dossierId, ?int $acteurId): array
    {
        $db      = Database::getInstance()->getConnection();
        $externe = $db->inTransaction();

        try {
            $st = $db->prepare('SELECT * FROM dossiers_inscription WHERE id = ? LIMIT 1');
            $st->execute([$dossierId]);
            $dossier = $st->fetch(PDO::FETCH_ASSOC);

            if ($dossier === false) {
                return ['ok' => false, 'message' => 'Dossier introuvable.'];
            }
            if (empty($dossier['abandon_le'])) {
                return ['ok' => false, 'message' => 'Ce dossier n’est pas en abandon.'];
            }

            if (!$externe) {
                $db->beginTransaction();
            }

            $db->prepare(
                'UPDATE dossiers_inscription
                 SET abandon_le = NULL, abandon_par = NULL, abandon_motif = NULL
                 WHERE id = ?'
            )->execute([$dossierId]);

            $commission = self::pourDossier($dossierId, $db);

            if ($commission !== null) {
                $commissionId = (int)$commission['id'];

                $db->prepare(
                    'UPDATE commission_tranches
                     SET est_abandonnee = 0, abandon_le = NULL
                     WHERE commission_id = ?'
                )->execute([$commissionId]);

                $db->prepare(
                    'UPDATE commissions_scolarite
                     SET statut = :statut, abandon_le = NULL, abandon_par = NULL, abandon_motif = NULL
                     WHERE id = :id'
                )->execute([':statut' => self::A_FACTURER, ':id' => $commissionId]);

                self::recalculerStatut($commissionId, $acteurId, $db);
            }

            if (!$externe) {
                $db->commit();
            }
        } catch (\Throwable $e) {
            if (!$externe && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[CommissionDossier] Levée d’abandon #' . $dossierId . ' : ' . $e->getMessage());

            return ['ok' => false, 'message' => 'La levée d’abandon a échoué. Réessayez.'];
        }

        Journal::dossier(
            Journal::ABANDON_LEVE,
            $dossier,
            ['commission_id' => $commission['id'] ?? null],
            ['acteur_id' => $acteurId, 'acteur_role' => 'super_admin']
        );

        self::notifierEtablissement(
            $dossier,
            'abandon_leve',
            'Abandon d’année levé',
            'L’abandon déclaré sur ' . trim((string)($dossier['nom_complet'] ?? 'un étudiant'))
            . ' (' . (string)($dossier['reference'] ?? 'dossier #' . $dossierId) . ') a été levé. '
            . 'Les tranches de commission redeviennent dues.'
        );

        return ['ok' => true, 'message' => 'Abandon levé. Les tranches redeviennent dues.'];
    }

    /** La commission d'un dossier est-elle close (réglée, annulée, abandon) ? */
    public static function estClose(?array $commission): bool
    {
        return $commission !== null && in_array((string)$commission['statut'], self::CLOS, true);
    }

    // ── Interne ──────────────────────────────────────────────────────────────

    /** @return array{ok:false, message:string, commission_statut:null} */
    private static function echecTranche(string $message): array
    {
        return ['ok' => false, 'message' => $message, 'commission_statut' => null];
    }

    /**
     * Prévient les gestionnaires de l'établissement.
     *
     * Une décision prise par la plateforme sur l'argent d'un partenaire doit
     * arriver dans SON espace, pas seulement dans un journal qu'il ne lit pas.
     */
    private static function notifierEtablissement(array $dossier, string $type, string $titre, string $message): void
    {
        try {
            $etabId        = (int)($dossier['etablissement_id'] ?? 0);
            $notifications = new \Models\Orientation\NotificationOrientation();

            foreach ($notifications->findGestionnaireIds($etabId) as $gestionnaireId) {
                $notifications->create([
                    'user_id'          => (int)$gestionnaireId,
                    'etablissement_id' => $etabId,
                    'type'             => $type,
                    'titre'            => $titre,
                    'message'          => $message,
                ]);
            }
        } catch (\Throwable $e) {
            error_log('[CommissionDossier] Notification établissement : ' . $e->getMessage());
        }
    }


    /** @return array{0:string, 1:array<string,mixed>} */
    private static function clauses(array $filtres): array
    {
        $where  = [];
        $params = [];

        if (!empty($filtres['etablissement_id'])) {
            $where[]         = 'c.etablissement_id = :etab';
            $params[':etab'] = (int)$filtres['etablissement_id'];
        }
        if (!empty($filtres['statut']) && isset(self::LIBELLES[$filtres['statut']])) {
            $herites = [
                self::A_FACTURER => ['en_cours'],
                self::REGLEE     => ['solde'],
            ];
            $valeurs = array_merge([$filtres['statut']], $herites[$filtres['statut']] ?? []);

            $marques = [];
            foreach ($valeurs as $i => $valeur) {
                $marques[]           = ':st' . $i;
                $params[':st' . $i]  = $valeur;
            }
            $where[] = 'c.statut IN (' . implode(', ', $marques) . ')';
        }
        if (!empty($filtres['periode'])) {
            $where[]            = 'c.periode_facturation = :periode';
            $params[':periode'] = (string)$filtres['periode'];
        }
        if (!empty($filtres['du'])) {
            $where[]       = 'c.created_at >= :du';
            $params[':du'] = $filtres['du'] . ' 00:00:00';
        }
        if (!empty($filtres['au'])) {
            $where[]       = 'c.created_at <= :au';
            $params[':au'] = $filtres['au'] . ' 23:59:59';
        }

        return [$where === [] ? '' : 'WHERE ' . implode(' AND ', $where), $params];
    }

    /**
     * Période de facturation « AAAA-MM », déduite de la date de validation.
     * Prise sur la validation et non sur « maintenant » : régénérer une ligne
     * plus tard ne doit pas la déplacer dans un autre mois comptable.
     */
    private static function periodeFacturation(array $dossier): string
    {
        $reference = $dossier['valide_100_le'] ?? null;

        try {
            return (new \DateTimeImmutable($reference !== null ? (string)$reference : 'now'))->format('Y-m');
        } catch (\Throwable) {
            return date('Y-m');
        }
    }
}
