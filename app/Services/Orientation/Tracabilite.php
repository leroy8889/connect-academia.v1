<?php
declare(strict_types=1);

namespace Services\Orientation;

use Core\Database;
use Models\Orientation\Dossier;
use Models\Orientation\DossierStatut;
use PDO;

/**
 * Traçabilité et indicateurs du super administrateur (spec §11).
 *
 * « La plateforme ne collecte plus l'argent ; elle doit donc être irréprochable
 * sur la donnée. » Tout ce qui est ici sert à répondre à une question posée par
 * un partenaire ou un candidat : qu'est-ce qui s'est passé, quand, et par qui.
 *
 * Toutes les lectures sont en SELECT seul. Aucune méthode de cette classe ne
 * modifie quoi que ce soit — le journal est en ajout seul (spec §11.3), et une
 * classe d'analyse n'a rien à écrire.
 */
final class Tracabilite
{
    /** Au-delà, un dossier validé à 50 % est signalé comme en souffrance. */
    private const ATTENTE_50_JOURS = 15;

    /**
     * Écart relatif au-delà duquel le taux de boursiers d'un établissement est
     * jugé anormal par rapport à la moyenne de la plateforme (spec §11.4).
     * 0,5 = 50 % d'écart, dans un sens ou dans l'autre.
     */
    private const ECART_BOURSIERS = 0.5;

    /** Effectif minimal pour qu'un taux ait un sens statistique. */
    private const EFFECTIF_MINIMAL = 10;

    /**
     * Discriminant préinscription / dossier d'inscription.
     *
     * La règle vit dans Models\Orientation\Dossier : elle est partagée mot
     * pour mot avec l'espace élève, sans quoi les deux espaces racontent deux
     * histoires différentes du même dossier. Voir Dossier::SQL_EST_INSCRIPTION
     * pour le détail des trois marqueurs et le piège de la table
     * `preinscriptions`, qui porte une ligne pour CHAQUE dossier et ne
     * discrimine donc rien.
     */
    private const EST_INSCRIPTION = Dossier::SQL_EST_INSCRIPTION;

    // ── §11.1 — Fiche utilisateur enrichie ──────────────────────────────────

    /**
     * Tout ce que la fiche d'un utilisateur doit montrer.
     *
     * @return array{
     *   activite:array<string,mixed>,
     *   preinscriptions:list<array<string,mixed>>,
     *   dossiers:list<array<string,mixed>>,
     *   frise:list<array<string,mixed>>
     * }
     */
    public static function ficheUtilisateur(int $utilisateurId): array
    {
        return [
            'activite'        => self::activite($utilisateurId),
            'preinscriptions' => self::preinscriptions($utilisateurId),
            'dossiers'        => self::dossiers($utilisateurId),
            'frise'           => Journal::friseUtilisateur($utilisateurId, 300),
        ];
    }

    /**
     * Bloc « Activité sur la plateforme » (spec §11.1).
     *
     * @return array<string,mixed>
     */
    public static function activite(int $utilisateurId): array
    {
        $vide = [
            'cree_le'            => null,
            'canal'              => null,
            'derniere_connexion' => null,
            'nb_preinscriptions' => 0,
            'nb_dossiers'        => 0,
            'statut_parcours'    => 'visiteur',
        ];

        try {
            $db = Database::getInstance()->getConnection();

            $st = $db->prepare(
                'SELECT u.created_at, u.last_login, u.role, u.statut_parcours
                 FROM users u WHERE u.id = ? LIMIT 1'
            );
            $st->execute([$utilisateurId]);
            $user = $st->fetch(PDO::FETCH_ASSOC);

            if ($user === false) {
                return $vide;
            }

            // Deux comptages distincts : une préinscription est un acte léger
            // (table `preinscriptions`), un dossier d'inscription porte le
            // cycle de vie complet.
            $st = $db->prepare(
                'SELECT
                   SUM(CASE WHEN ' . self::EST_INSCRIPTION . ' THEN 0 ELSE 1 END) AS preins,
                   SUM(CASE WHEN ' . self::EST_INSCRIPTION . ' THEN 1 ELSE 0 END) AS dossiers
                 FROM dossiers_inscription d
                 WHERE d.eleve_id = ?'
            );
            $st->execute([$utilisateurId]);
            $compte = $st->fetch(PDO::FETCH_ASSOC) ?: [];

            return [
                'cree_le'            => $user['created_at'],
                // Le canal d'inscription se déduit du rôle : un compte
                // « visiteur » est né d'une candidature, pas d'une inscription
                // volontaire à la plateforme.
                'canal'              => ($user['role'] ?? '') === 'visiteur'
                    ? 'Créé lors d’une candidature'
                    : 'Inscription directe',
                'derniere_connexion' => $user['last_login'],
                'nb_preinscriptions' => (int)($compte['preins']   ?? 0),
                'nb_dossiers'        => (int)($compte['dossiers'] ?? 0),
                'statut_parcours'    => (string)($user['statut_parcours'] ?? 'visiteur'),
            ];
        } catch (\Throwable $e) {
            error_log('[Tracabilite] Activité illisible #' . $utilisateurId . ' : ' . $e->getMessage());

            return $vide;
        }
    }

    /**
     * Préinscriptions de l'utilisateur, avec la colonne décisive : ont-elles
     * donné lieu à un dossier d'inscription ? (spec §11.1)
     *
     * @return list<array<string,mixed>>
     */
    public static function preinscriptions(int $utilisateurId): array
    {
        try {
            $st = Database::getInstance()->getConnection()->prepare(
                'SELECT d.id, d.reference, d.statut, d.filiere_visee, d.specialite_visee,
                        d.date_creation, d.soumis_le,
                        e.nom AS etab_nom, e.id AS etablissement_id,
                        EXISTS (
                          SELECT 1 FROM inscriptions i2
                          JOIN dossiers_inscription d2 ON d2.id = i2.dossier_id
                          WHERE d2.eleve_id = d.eleve_id
                            AND d2.etablissement_id = d.etablissement_id
                        ) AS a_donne_dossier
                 FROM dossiers_inscription d
                 JOIN etablissements e ON e.id = d.etablissement_id
                 WHERE d.eleve_id = ? AND NOT ' . self::EST_INSCRIPTION . '
                 ORDER BY d.date_creation DESC'
            );
            $st->execute([$utilisateurId]);

            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[Tracabilite] Préinscriptions illisibles #' . $utilisateurId . ' : ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Dossiers d'inscription de l'utilisateur, avec leur progression et leurs
     * trois dates de jalon (spec §11.1).
     *
     * @return list<array<string,mixed>>
     */
    public static function dossiers(int $utilisateurId): array
    {
        try {
            $st = Database::getInstance()->getConnection()->prepare(
                'SELECT d.*, e.nom AS etab_nom,
                        c.montant_commission, c.statut AS commission_statut
                 FROM dossiers_inscription d
                 JOIN etablissements e ON e.id = d.etablissement_id
                 LEFT JOIN commissions_scolarite c ON c.dossier_id = d.id
                 WHERE d.eleve_id = ? AND ' . self::EST_INSCRIPTION . '
                 ORDER BY d.date_creation DESC'
            );
            $st->execute([$utilisateurId]);
            $lignes = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[Tracabilite] Dossiers illisibles #' . $utilisateurId . ' : ' . $e->getMessage());

            return [];
        }

        foreach ($lignes as &$ligne) {
            $etat                     = DossierStatut::normaliser((string)$ligne['statut']);
            $ligne['statut_normalise'] = $etat;
            $ligne['statut_libelle']   = DossierStatut::libelle($etat);
            $ligne['progression']      = DossierStatut::progression($etat);
            $ligne['bourse_libelle']   = SituationBourse::libelleCourt($ligne['statut_bourse'] ?? null);
        }

        return $lignes;
    }

    // ── §11.4 — Tableaux de bord ────────────────────────────────────────────

    /**
     * Entonnoir de conversion : comptes créés → préinscriptions → dossiers
     * déposés → validés à 50 % → validés à 100 %.
     *
     * @return list<array{cle:string,libelle:string,valeur:int,taux:float}>
     */
    public static function entonnoir(?int $etablissementId = null): array
    {
        $etapes = [
            'comptes'    => 'Comptes créés',
            'preins'     => 'Dossiers ouverts',
            'deposes'    => 'Préinscriptions déposées',
            'valides_50' => 'Validés à 50 %',
            'inscrits'   => 'Inscrits (100 %)',
        ];

        $valeurs = array_fill_keys(array_keys($etapes), 0);

        try {
            $db = Database::getInstance()->getConnection();

            // Un entonnoir cloisonné sur un établissement ne peut pas compter
            // les comptes de la plateforme entière : on compte alors les
            // candidats distincts de cet établissement.
            if ($etablissementId === null) {
                $valeurs['comptes'] = (int)$db->query(
                    'SELECT COUNT(*) FROM users WHERE is_deleted = 0'
                )->fetchColumn();
            } else {
                $st = $db->prepare(
                    'SELECT COUNT(DISTINCT COALESCE(eleve_id, id))
                     FROM dossiers_inscription WHERE etablissement_id = ?'
                );
                $st->execute([$etablissementId]);
                $valeurs['comptes'] = (int)$st->fetchColumn();
            }

            $filtre = $etablissementId !== null ? ' AND d.etablissement_id = :etab' : '';
            $params = $etablissementId !== null ? [':etab' => $etablissementId] : [];

            // Entonnoir monotone : chaque étape est un sous-ensemble strict de
            // la précédente, sinon le taux de conversion ne veut rien dire.
            $st = $db->prepare(
                "SELECT
                   COUNT(*) AS preins,
                   SUM(CASE WHEN d.statut <> 'brouillon' THEN 1 ELSE 0 END) AS deposes,
                   SUM(CASE WHEN d.statut IN ('valide_50','accepte','valide_100','inscrit') THEN 1 ELSE 0 END) AS valides_50,
                   SUM(CASE WHEN d.statut IN ('valide_100','inscrit') THEN 1 ELSE 0 END) AS inscrits
                 FROM dossiers_inscription d
                 WHERE 1 = 1{$filtre}"
            );
            $st->execute($params);
            $ligne = $st->fetch(PDO::FETCH_ASSOC) ?: [];

            foreach (['preins', 'deposes', 'valides_50', 'inscrits'] as $cle) {
                $valeurs[$cle] = (int)($ligne[$cle] ?? 0);
            }
        } catch (\Throwable $e) {
            error_log('[Tracabilite] Entonnoir échoué : ' . $e->getMessage());
        }

        // Le taux est relatif à l'étape PRÉCÉDENTE, pas au sommet : c'est ce
        // qui montre où le parcours perd du monde.
        $resultat  = [];
        $precedent = null;

        foreach ($etapes as $cle => $libelle) {
            $valeur = $valeurs[$cle];
            $taux   = $precedent === null || $precedent === 0
                ? 100.0
                : round($valeur / $precedent * 100, 1);

            $resultat[] = ['cle' => $cle, 'libelle' => $libelle, 'valeur' => $valeur, 'taux' => $taux];
            $precedent  = $valeur;
        }

        return $resultat;
    }

    /**
     * Répartition des situations de bourse (spec §11.4).
     *
     * @return array{total:int, repartition:array<string,array{nombre:int,part:float}>}
     */
    public static function repartitionBourses(?int $etablissementId = null): array
    {
        $cles        = array_merge(SituationBourse::valeurs(), ['non_renseigne']);
        $repartition = array_fill_keys($cles, ['nombre' => 0, 'part' => 0.0]);
        $total       = 0;

        try {
            $db     = Database::getInstance()->getConnection();
            $filtre = $etablissementId !== null ? ' AND d.etablissement_id = :etab' : '';
            $params = $etablissementId !== null ? [':etab' => $etablissementId] : [];

            $st = $db->prepare(
                "SELECT COALESCE(d.statut_bourse, 'non_renseigne') AS cle, COUNT(*) AS n
                 FROM dossiers_inscription d
                 WHERE d.statut <> 'brouillon'{$filtre}
                 GROUP BY cle"
            );
            $st->execute($params);

            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
                $cle = (string)$ligne['cle'];
                if (!isset($repartition[$cle])) {
                    continue;
                }
                $repartition[$cle]['nombre'] = (int)$ligne['n'];
                $total                      += (int)$ligne['n'];
            }

            if ($total > 0) {
                foreach ($repartition as $cle => $valeur) {
                    $repartition[$cle]['part'] = round($valeur['nombre'] / $total * 100, 1);
                }
            }
        } catch (\Throwable $e) {
            error_log('[Tracabilite] Répartition des bourses échouée : ' . $e->getMessage());
        }

        return ['total' => $total, 'repartition' => $repartition];
    }

    /**
     * Délai moyen de traitement d'un dossier, du dépôt à la validation à 50 %,
     * par établissement (spec §11.4).
     *
     * @return list<array{etablissement_id:int,nom:string,dossiers:int,delai_moyen:float,delai_median:?float}>
     */
    public static function delaiTraitement(): array
    {
        try {
            $st = Database::getInstance()->getConnection()->query(
                'SELECT e.id AS etablissement_id, e.nom,
                        COUNT(*) AS dossiers,
                        AVG(TIMESTAMPDIFF(HOUR, d.soumis_le, d.valide_50_le) / 24) AS delai_moyen
                 FROM dossiers_inscription d
                 JOIN etablissements e ON e.id = d.etablissement_id
                 WHERE d.soumis_le IS NOT NULL
                   AND d.valide_50_le IS NOT NULL
                   AND d.valide_50_le >= d.soumis_le
                 GROUP BY e.id, e.nom
                 ORDER BY delai_moyen DESC'
            );
            $lignes = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[Tracabilite] Délai de traitement échoué : ' . $e->getMessage());

            return [];
        }

        foreach ($lignes as &$ligne) {
            $ligne['dossiers']    = (int)$ligne['dossiers'];
            $ligne['delai_moyen'] = round((float)$ligne['delai_moyen'], 1);
        }

        return $lignes;
    }

    /**
     * Dossiers validés à 50 % qui attendent depuis plus de quinze jours
     * (spec §11.4). Ce sont les candidats qui risquent l'expiration.
     *
     * @return list<array<string,mixed>>
     */
    public static function attenteProlongee(int $jours = self::ATTENTE_50_JOURS, int $limite = 100): array
    {
        $jours  = max(1, min($jours, 365));
        $limite = max(1, min($limite, 500));

        try {
            $st = Database::getInstance()->getConnection()->prepare(
                "SELECT d.id, d.reference, d.nom_complet, d.valide_50_le, d.expire_le,
                        e.nom AS etab_nom,
                        DATEDIFF(NOW(), d.valide_50_le) AS jours_attente
                 FROM dossiers_inscription d
                 JOIN etablissements e ON e.id = d.etablissement_id
                 WHERE d.statut IN ('valide_50', 'accepte')
                   AND d.valide_50_le IS NOT NULL
                   AND d.valide_50_le < DATE_SUB(NOW(), INTERVAL :jours DAY)
                 ORDER BY d.valide_50_le ASC
                 LIMIT {$limite}"
            );
            $st->execute([':jours' => $jours]);

            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[Tracabilite] Attente prolongée échouée : ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Délai d'analyse par défaut accordé à un établissement, en jours.
     * Surchargeable par le paramètre `delai_analyse_dossier_jours`.
     */
    public const DELAI_ANALYSE_JOURS = 3;

    /**
     * Dossiers qu'un établissement laisse sans décision au-delà du délai
     * d'analyse (défaut : 3 jours). C'est l'engagement de service de la
     * plateforme envers le candidat, et le super administrateur est le seul
     * à pouvoir le faire respecter.
     *
     * Le compteur ne part PAS de `soumis_le`, mais de la dernière entrée en
     * état « soumis » : un dossier renvoyé après une demande de pièce redonne
     * trois jours pleins à l'établissement. Sans cela, tout dossier ayant
     * connu un aller-retour serait signalé à tort dès sa réémission.
     *
     * Les états `piece_manquante` et `valide_50` ne sont jamais comptés : la
     * balle est alors dans le camp du candidat, pas de l'établissement.
     *
     * `$exclureSignalesDepuisJours` écarte, EN BASE et avant la limite, les
     * dossiers déjà journalisés en `alerte_retard_analyse` sur la fenêtre
     * donnée. Laissé à `null` — le défaut — la liste est complète : c'est ce
     * que veut le tableau de bord, qui montre l'arriéré tel qu'il est.
     *
     * @return list<array{
     *   id:int, reference:?string, nom_complet:?string, etablissement_id:int,
     *   etab_nom:string, depuis_le:string, jours_attente:int
     * }>
     */
    public static function analysesEnRetard(
        ?int $jours = null,
        int $limite = 200,
        ?int $exclureSignalesDepuisJours = null
    ): array {
        $jours  = max(1, min($jours ?? self::delaiAnalyse(), 365));
        $limite = max(1, min($limite, 500));

        // Exclusion des dossiers DÉJÀ signalés, faite en base et AVANT la
        // limite. Filtrer en PHP après coup laissait un arriéré affamer les
        // nouveaux : trois cents dossiers en retard, deux cents remontés par
        // la limite, tous déjà signalés — et le dossier oublié d'hier ne
        // sortait jamais du lot tant que les anciens n'avaient pas vieilli.
        $antiRepetition = $exclureSignalesDepuisJours !== null
            ? max(1, min($exclureSignalesDepuisJours, 365))
            : null;
        $filtreSignales = $antiRepetition !== null
            ? "AND NOT EXISTS (
                   SELECT 1 FROM journal_activite j
                    WHERE j.objet_type = 'dossier'
                      AND j.objet_id   = d.id
                      AND j.type_evenement = :evenement
                      AND j.cree_le > DATE_SUB(NOW(), INTERVAL {$antiRepetition} DAY)
               )"
            : '';

        try {
            // Table dérivée plutôt que HAVING sur alias : le calcul du point de
            // départ est fait une fois, puis filtré et trié, sans dépendre des
            // extensions MySQL au comportement variable selon le sql_mode.
            // DATEDIFF est calculé côté base, pas en PHP : l'horloge de MySQL
            // et celle de PHP ne partagent pas toujours le même fuseau, et un
            // écart de deux heures suffisait à rendre « 10 jours » en « 9 ».
            $st = Database::getInstance()->getConnection()->prepare(
                "SELECT t.*, DATEDIFF(NOW(), t.depuis_le) AS jours_attente FROM (
                     SELECT d.id, d.reference, d.nom_complet, d.etablissement_id,
                            e.nom AS etab_nom,
                            COALESCE(
                                (SELECT MAX(h.cree_le)
                                   FROM historique_statut_dossier h
                                  WHERE h.dossier_id = d.id
                                    AND h.nouveau_statut = 'soumis'),
                                d.soumis_le
                            ) AS depuis_le
                       FROM dossiers_inscription d
                       JOIN etablissements e ON e.id = d.etablissement_id
                      WHERE d.statut IN ('soumis', 'paye')
                        {$filtreSignales}
                 ) AS t
                 WHERE t.depuis_le IS NOT NULL
                   AND t.depuis_le < DATE_SUB(NOW(), INTERVAL :jours DAY)
                 ORDER BY t.depuis_le ASC
                 LIMIT {$limite}"
            );
            $params = [':jours' => $jours];
            if ($antiRepetition !== null) {
                $params[':evenement'] = Journal::ALERTE_RETARD_ANALYSE;
            }
            $st->execute($params);
            $lignes = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[Tracabilite] Retards d’analyse illisibles : ' . $e->getMessage());

            return [];
        }

        foreach ($lignes as &$ligne) {
            $ligne['jours_attente'] = (int)$ligne['jours_attente'];
        }

        return $lignes;
    }

    /** Délai d'analyse en vigueur, borné à des valeurs praticables. */
    public static function delaiAnalyse(): int
    {
        $jours = (int)\Core\Settings::get('delai_analyse_dossier_jours', self::DELAI_ANALYSE_JOURS);

        return max(1, min($jours, 90));
    }

    /**
     * Établissements dont le taux de boursiers déclarés s'écarte fortement de
     * la moyenne de la plateforme (spec §11.4).
     *
     * Un écart n'est pas une accusation : il peut refléter un recrutement
     * réellement différent. C'est un signal à vérifier, et c'est pour cela que
     * la méthode retourne aussi l'effectif — un taux de 100 % sur trois
     * dossiers ne veut rien dire, d'où le seuil d'effectif minimal.
     *
     * @return array{
     *   moyenne:float,
     *   etablissements:list<array{etablissement_id:int,nom:string,dossiers:int,boursiers:int,taux:float,ecart:float,anormal:bool}>
     * }
     */
    public static function alerteBoursiers(): array
    {
        try {
            $db = Database::getInstance()->getConnection();

            $st = $db->query(
                "SELECT e.id AS etablissement_id, e.nom,
                        COUNT(*) AS dossiers,
                        SUM(CASE WHEN d.statut_bourse = 'boursier' THEN 1 ELSE 0 END) AS boursiers
                 FROM dossiers_inscription d
                 JOIN etablissements e ON e.id = d.etablissement_id
                 WHERE d.statut <> 'brouillon'
                   AND d.statut_bourse IS NOT NULL
                 GROUP BY e.id, e.nom
                 ORDER BY e.nom"
            );
            $lignes = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[Tracabilite] Alerte boursiers échouée : ' . $e->getMessage());

            return ['moyenne' => 0.0, 'etablissements' => []];
        }

        $totalDossiers  = 0;
        $totalBoursiers = 0;

        foreach ($lignes as $ligne) {
            $totalDossiers  += (int)$ligne['dossiers'];
            $totalBoursiers += (int)$ligne['boursiers'];
        }

        $moyenne = $totalDossiers > 0 ? $totalBoursiers / $totalDossiers * 100 : 0.0;

        $resultat = [];
        foreach ($lignes as $ligne) {
            $dossiers = (int)$ligne['dossiers'];
            $taux     = $dossiers > 0 ? (int)$ligne['boursiers'] / $dossiers * 100 : 0.0;

            $ecart = $moyenne > 0 ? ($taux - $moyenne) / $moyenne : 0.0;

            $resultat[] = [
                'etablissement_id' => (int)$ligne['etablissement_id'],
                'nom'              => (string)$ligne['nom'],
                'dossiers'         => $dossiers,
                'boursiers'        => (int)$ligne['boursiers'],
                'taux'             => round($taux, 1),
                'ecart'            => round($ecart * 100, 1),
                'anormal'          => $dossiers >= self::EFFECTIF_MINIMAL
                    && abs($ecart) > self::ECART_BOURSIERS,
            ];
        }

        return ['moyenne' => round($moyenne, 1), 'etablissements' => $resultat];
    }

    /**
     * Étudiants ayant atteint 100 % dans DEUX établissements pour la même
     * année académique (spec §17, « inscriptions multiples »).
     *
     * Ce n'est pas nécessairement une fraude : c'est un indicateur de
     * traçabilité, et il vaut mieux le voir que l'ignorer.
     *
     * @return list<array<string,mixed>>
     */
    public static function inscriptionsMultiples(): array
    {
        try {
            $st = Database::getInstance()->getConnection()->query(
                "SELECT d.eleve_id, d.annee_academique,
                        COUNT(DISTINCT d.etablissement_id) AS nb_etablissements,
                        GROUP_CONCAT(DISTINCT e.nom ORDER BY e.nom SEPARATOR ' · ') AS etablissements,
                        MAX(d.nom_complet) AS nom_complet
                 FROM dossiers_inscription d
                 JOIN etablissements e ON e.id = d.etablissement_id
                 WHERE d.statut IN ('valide_100', 'inscrit')
                   AND d.eleve_id IS NOT NULL
                   AND d.annee_academique IS NOT NULL
                 GROUP BY d.eleve_id, d.annee_academique
                 HAVING nb_etablissements > 1
                 ORDER BY nb_etablissements DESC"
            );

            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[Tracabilite] Inscriptions multiples échouées : ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Vue transversale des dossiers, tous établissements confondus (spec §13.4).
     *
     * @param array{statut?:string, etablissement_id?:int, statut_bourse?:string, q?:string, annee?:string} $filtres
     * @return list<array<string,mixed>>
     */
    public static function dossiersTransversaux(array $filtres = [], int $limite = 100, int $offset = 0): array
    {
        [$where, $params] = self::clausesDossiers($filtres);

        $limite = max(1, min($limite, 500));
        $offset = max(0, $offset);

        try {
            $st = Database::getInstance()->getConnection()->prepare(
                "SELECT d.*, e.nom AS etab_nom,
                        c.montant_commission, c.statut AS commission_statut,
                        DATEDIFF(NOW(), COALESCE(d.soumis_le, d.date_creation)) AS anciennete,
                        " . self::EST_INSCRIPTION . " AS est_inscription
                 FROM dossiers_inscription d
                 JOIN etablissements e ON e.id = d.etablissement_id
                 LEFT JOIN commissions_scolarite c ON c.dossier_id = d.id
                 WHERE {$where}
                 ORDER BY COALESCE(d.soumis_le, d.date_creation) DESC
                 LIMIT {$limite} OFFSET {$offset}"
            );
            $st->execute($params);
            $lignes = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[Tracabilite] Vue transversale échouée : ' . $e->getMessage());

            return [];
        }

        foreach ($lignes as &$ligne) {
            $etat                      = DossierStatut::normaliser((string)$ligne['statut']);
            $ligne['statut_normalise'] = $etat;
            $ligne['statut_libelle']   = DossierStatut::libelle($etat);
            $ligne['progression']      = DossierStatut::progression($etat);
            $ligne['est_inscription']  = (int)($ligne['est_inscription'] ?? 0) === 1;
            $ligne['type']             = $ligne['est_inscription'] ? 'inscription' : 'preinscription';
            $ligne['type_libelle']     = $ligne['est_inscription'] ? 'Inscription' : 'Préinscription';
            $ligne['bourse_libelle']   = SituationBourse::libelleCourt($ligne['statut_bourse'] ?? null);
        }

        return $lignes;
    }

    public static function compterDossiersTransversaux(array $filtres = []): int
    {
        [$where, $params] = self::clausesDossiers($filtres);

        try {
            $st = Database::getInstance()->getConnection()->prepare(
                "SELECT COUNT(*)
                 FROM dossiers_inscription d
                 WHERE {$where}"
            );
            $st->execute($params);

            return (int)$st->fetchColumn();
        } catch (\Throwable $e) {
            error_log('[Tracabilite] Comptage transversal échoué : ' . $e->getMessage());

            return 0;
        }
    }

    // ── Interne ──────────────────────────────────────────────────────────────

    /** @return array{0:string, 1:array<string,mixed>} */
    private static function clausesDossiers(array $filtres): array
    {
        $where  = ['1 = 1'];
        $params = [];

        $statut = (string)($filtres['statut'] ?? '');
        if ($statut !== '' && DossierStatut::existe($statut)) {
            $herites = [
                DossierStatut::SOUMIS     => ['paye'],
                DossierStatut::VALIDE_50  => ['accepte'],
                DossierStatut::VALIDE_100 => ['inscrit'],
                DossierStatut::REJETE     => ['refuse'],
            ];
            $valeurs = array_merge([$statut], $herites[$statut] ?? []);

            $marques = [];
            foreach ($valeurs as $i => $valeur) {
                $marques[]          = ':st' . $i;
                $params[':st' . $i] = $valeur;
            }
            $where[] = 'd.statut IN (' . implode(', ', $marques) . ')';
        }

        if (!empty($filtres['etablissement_id'])) {
            $where[]         = 'd.etablissement_id = :etab';
            $params[':etab'] = (int)$filtres['etablissement_id'];
        }

        // Préinscription (dépôt en ligne) ou dossier d'inscription (validé à
        // 100 %, ou brouillon en cours) — cf. self::EST_INSCRIPTION.
        $type = (string)($filtres['type'] ?? '');
        if ($type === 'inscription') {
            $where[] = self::EST_INSCRIPTION;
        } elseif ($type === 'preinscription') {
            $where[] = 'NOT ' . self::EST_INSCRIPTION;
        }

        $bourse = (string)($filtres['statut_bourse'] ?? '');
        if ($bourse === 'non_renseigne') {
            $where[] = 'd.statut_bourse IS NULL';
        } elseif ($bourse !== '' && SituationBourse::estValide($bourse)) {
            $where[]           = 'd.statut_bourse = :bourse';
            $params[':bourse'] = $bourse;
        }

        if (!empty($filtres['annee'])) {
            $where[]          = 'd.annee_academique = :annee';
            $params[':annee'] = (string)$filtres['annee'];
        }

        $q = trim((string)($filtres['q'] ?? ''));
        if ($q !== '') {
            $motif         = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
            $where[]       = '(d.nom_complet LIKE :q1 OR d.reference LIKE :q2 OR d.email LIKE :q3)';
            $params[':q1'] = $motif;
            $params[':q2'] = $motif;
            $params[':q3'] = $motif;
        }

        return [implode(' AND ', $where), $params];
    }
}
