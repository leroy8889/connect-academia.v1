<?php
declare(strict_types=1);

namespace Models\Orientation;

use Core\Database;
use Services\Orientation\Journal;
use PDO;

class Dossier
{
    /**
     * ── Règle unique : ce dossier relève-t-il du parcours d'INSCRIPTION ? ──
     *
     * L'inscription est un PROCESSUS, pas un état final. Un candidat qui a
     * déposé son dossier d'inscription est dans le parcours d'inscription dès
     * la première étape, même si l'établissement ne l'a pas encore validé, et
     * même si le dossier finit rejeté. Le classer ailleurs en attendant n'a
     * aucun sens pour lui : il a rempli un formulaire d'inscription, il veut
     * le retrouver dans « Mes inscriptions ».
     *
     * Trois marqueurs, du plus tardif au plus précoce :
     *
     *  1. une ligne dans `inscriptions` — posée à la validation à 100 %, à la
     *     saisie au guichet, et par l'ancien parcours de paiement en ligne ;
     *  2. `reference` non nulle — la référence CA-AAAA-XXX-NNNNNN est réservée
     *     par SoumissionDossier::executer(), donc à la soumission du dossier
     *     d'inscription. Une préinscription n'en reçoit JAMAIS : elle n'a qu'un
     *     numéro de récépissé, dans la table `recepisses` ;
     *  3. `statut = 'brouillon'` — l'étape 1 est enregistrée, le dossier
     *     existe déjà mais n'a encore ni référence ni ligne d'inscription.
     *
     * ── Pourquoi une constante SQL ET une méthode PHP ──────────────────────
     * La règle était réécrite à la main dans sept endroits (deux contrôleurs,
     * trois vues, deux requêtes de traçabilité), et le critère n° 2 manquait
     * partout. Une seule définition, deux formes d'emploi : les requêtes
     * consomment la constante, le code qui tient déjà la ligne en mémoire
     * appelle la méthode. Les deux disent la même chose, par construction.
     *
     * La constante suppose la table `dossiers_inscription` aliasée `d`.
     */
    public const SQL_EST_INSCRIPTION =
        "(EXISTS (SELECT 1 FROM inscriptions i WHERE i.dossier_id = d.id)
          OR d.reference IS NOT NULL
          OR d.statut = 'brouillon')";

    /** Nature d'un dossier, telle que la liste du back-office la filtre. */
    public const TYPE_PREINSCRIPTION = 'preinscription';
    public const TYPE_INSCRIPTION    = 'inscription';

    /**
     * Pendant PHP de self::SQL_EST_INSCRIPTION.
     *
     * @param array<string,mixed> $dossier Ligne de `dossiers_inscription`,
     *        éventuellement enrichie de `inscription_id` par une jointure.
     */
    public static function estInscription(array $dossier): bool
    {
        return !empty($dossier['inscription_id'])
            || trim((string)($dossier['reference'] ?? '')) !== ''
            || ($dossier['statut'] ?? '') === 'brouillon';
    }

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create(array $d): int
    {
        $st = $this->db->prepare('
            INSERT INTO dossiers_inscription
              (etablissement_id, eleve_id, filiere_visee, specialite_visee, niveau_entree,
               nom_complet, genre, date_naissance, lieu_naissance, nationalite,
               email, indicatif_tel, telephone, adresse, ville, pays_provenance,
               dernier_etablissement, serie_bac, annee_bac, annee_academique, origine, statut)
            VALUES
              (:etablissement_id, :eleve_id, :filiere_visee, :specialite_visee, :niveau_entree,
               :nom_complet, :genre, :date_naissance, :lieu_naissance, :nationalite,
               :email, :indicatif_tel, :telephone, :adresse, :ville, :pays_provenance,
               :dernier_etablissement, :serie_bac, :annee_bac, :annee_academique, "en_ligne", :statut)
        ');

        $st->execute([
            ':etablissement_id'      => $d['etablissement_id'],
            ':eleve_id'              => !empty($d['eleve_id']) ? (int)$d['eleve_id'] : null,
            ':filiere_visee'         => $d['filiere_visee'] ?? null,
            ':specialite_visee'      => $d['specialite_visee'] ?? null,
            ':niveau_entree'         => $d['niveau_entree'] ?? null,
            ':nom_complet'           => $d['nom_complet'],
            ':genre'                 => $d['genre'],
            ':date_naissance'        => $d['date_naissance'],
            ':lieu_naissance'        => $d['lieu_naissance'],
            ':nationalite'           => $d['nationalite'],
            ':email'                 => $d['email'],
            ':indicatif_tel'         => $d['indicatif_tel'] ?? '+241',
            ':telephone'             => $d['telephone'],
            ':adresse'               => $d['adresse'] ?? null,
            ':ville'                 => $d['ville'] ?? null,
            ':pays_provenance'       => $d['pays_provenance'] ?? null,
            ':dernier_etablissement' => $d['dernier_etablissement'] ?? null,
            ':serie_bac'             => $d['serie_bac'] ?? null,
            ':annee_bac'             => !empty($d['annee_bac']) ? (int)$d['annee_bac'] : null,
            ':annee_academique'      => $d['annee_academique'] ?? \Core\AnneeUniversitaire::code(),
            ':statut'                => $d['statut'] ?? 'brouillon',
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Fiche complète d'un dossier.
     *
     * `inscription_id` est le marqueur le plus tardif du parcours d'inscription
     * (cf. self::SQL_EST_INSCRIPTION). Sans lui, self::estInscription() classait
     * une inscription saisie au guichet parmi les préinscriptions : elle n'a ni
     * `reference` ni statut « brouillon », seulement sa ligne dans
     * `inscriptions`. Sous-requête plutôt que jointure : aucune ligne dupliquée
     * si le dossier en porte deux.
     */
    public function findById(int $id): ?array
    {
        $st = $this->db->prepare('
            SELECT d.*,
                   e.nom               AS etab_nom,
                   e.slug              AS etab_slug,
                   e.couleur_principale,
                   e.ville             AS etab_ville,
                   e.genere_recepisse,
                   e.genere_attestation,
                   e.preinscription_active,
                   e.inscription_active,
                   (SELECT i.id FROM inscriptions i WHERE i.dossier_id = d.id LIMIT 1) AS inscription_id,
                   TRIM(CONCAT(COALESCE(cu.prenom, ""), " ", COALESCE(cu.nom, ""))) AS cree_par_nom
            FROM dossiers_inscription d
            JOIN etablissements e ON e.id = d.etablissement_id
            LEFT JOIN users cu ON cu.id = d.cree_par_user_id
            WHERE d.id = ?
            LIMIT 1
        ');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateFiliere(int $id, string $filiere, string $niveau, ?string $specialite = null): void
    {
        $this->db->prepare(
            'UPDATE dossiers_inscription SET filiere_visee = ?, specialite_visee = ?, niveau_entree = ? WHERE id = ?'
        )->execute([$filiere, $specialite, $niveau, $id]);
    }

    /**
     * Met à jour les informations du candidat — verrouillé sur l'établissement
     * du gestionnaire (isolation multi-tenant). Retourne false si le dossier
     * n'appartient pas à cet établissement.
     */
    public function updateInfosByEtab(int $id, int $etabId, array $d): bool
    {
        $st = $this->db->prepare('
            UPDATE dossiers_inscription SET
              nom_complet           = :nom_complet,
              genre                 = :genre,
              date_naissance        = :date_naissance,
              lieu_naissance        = :lieu_naissance,
              nationalite           = :nationalite,
              email                 = :email,
              indicatif_tel         = :indicatif_tel,
              telephone             = :telephone,
              adresse               = :adresse,
              ville                 = :ville,
              pays_provenance       = :pays_provenance,
              dernier_etablissement = :dernier_etablissement,
              filiere_visee         = :filiere_visee,
              specialite_visee      = :specialite_visee,
              niveau_entree         = :niveau_entree,
              serie_bac             = :serie_bac,
              annee_bac             = :annee_bac
            WHERE id = :id AND etablissement_id = :etab
        ');
        $st->execute([
            ':nom_complet'           => $d['nom_complet'],
            ':genre'                 => $d['genre'],
            ':date_naissance'        => $d['date_naissance'],
            ':lieu_naissance'        => $d['lieu_naissance'],
            ':nationalite'           => $d['nationalite'],
            ':email'                 => $d['email'],
            ':indicatif_tel'         => $d['indicatif_tel'] ?? '+241',
            ':telephone'             => $d['telephone'],
            ':adresse'               => $d['adresse'] ?? null,
            ':ville'                 => $d['ville'] ?? null,
            ':pays_provenance'       => $d['pays_provenance'] ?? null,
            ':dernier_etablissement' => $d['dernier_etablissement'] ?? null,
            ':filiere_visee'         => $d['filiere_visee'] ?? null,
            ':specialite_visee'      => $d['specialite_visee'] ?? null,
            ':niveau_entree'         => $d['niveau_entree'] ?? null,
            // Bac : mêmes colonnes que le formulaire public. Une valeur vide
            // devient NULL plutôt que '' — le récépissé imprime « — » sur une
            // colonne nulle, et une chaîne vide y ferait un trou blanc.
            ':serie_bac'             => ($d['serie_bac'] ?? '') !== '' ? $d['serie_bac'] : null,
            ':annee_bac'             => !empty($d['annee_bac']) ? (int)$d['annee_bac'] : null,
            ':id'                    => $id,
            ':etab'                  => $etabId,
        ]);
        return $st->rowCount() > 0 || $this->belongsToEtab($id, $etabId);
    }

    private function belongsToEtab(int $id, int $etabId): bool
    {
        $st = $this->db->prepare(
            'SELECT COUNT(*) FROM dossiers_inscription WHERE id = ? AND etablissement_id = ?'
        );
        $st->execute([$id, $etabId]);
        return (int)$st->fetchColumn() > 0;
    }

    /**
     * Cherche un dossier portant déjà cet e-mail DANS le même établissement.
     * Volontairement borné à l'établissement : un candidat a le droit de
     * postuler dans plusieurs écoles avec le même e-mail, et un gestionnaire ne
     * doit rien apprendre des dossiers d'un autre établissement (multi-tenant).
     *
     * La comparaison est insensible à la casse et aux espaces de bord — les
     * anciens dossiers ont pu être saisis « Jean@Mail.com » au guichet.
     *
     * @param int|null $excludeId Dossier à ignorer (cas de la modification).
     * @return array|null ['id','nom_complet','statut'] du dossier en conflit.
     */
    public function findByEmailInEtab(string $email, int $etabId, ?int $excludeId = null): ?array
    {
        $email = strtolower(trim($email));
        if ($email === '' || $etabId <= 0) {
            return null;
        }

        $sql = 'SELECT id, nom_complet, statut
                FROM dossiers_inscription
                WHERE etablissement_id = :etab
                  AND LOWER(TRIM(email)) = :email';
        $params = [':etab' => $etabId, ':email' => $email];

        if ($excludeId !== null && $excludeId > 0) {
            $sql .= ' AND id <> :exclude';
            $params[':exclude'] = $excludeId;
        }
        $sql .= ' ORDER BY id ASC LIMIT 1';

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @deprecated Écriture directe de `statut` — proscrite par la spec §18.
     *             Utiliser transition() : elle seule vérifie la machine à états
     *             et alimente historique_statut_dossier + journal_activite.
     *             Conservée pour les rares appels hérités non encore migrés.
     */
    public function updateStatut(int $id, string $statut): void
    {
        $this->db->prepare(
            'UPDATE dossiers_inscription SET statut = ?, progression = ? WHERE id = ?'
        )->execute([$statut, DossierStatut::progression($statut), $id]);
    }

    // ── MACHINE À ÉTATS (spec §4) ────────────────────────────────────────────

    /**
     * Point d'entrée UNIQUE de tout changement de statut (spec §4.2).
     *
     * Aucune autre écriture de la colonne `statut` ne doit exister dans le code.
     * La méthode :
     *   1. verrouille la ligne du dossier (SELECT … FOR UPDATE) ;
     *   2. vérifie que la transition est permise à cet acteur ;
     *   3. applique les garde-fous propres à l'état visé ;
     *   4. écrit les jalons, la progression et le statut ;
     *   5. trace dans historique_statut_dossier ET dans journal_activite.
     *
     * Elle est ré-entrante : si une transaction est déjà ouverte par l'appelant
     * (soumission complète, validation 100 % avec commission), elle s'y greffe
     * au lieu d'en ouvrir une seconde.
     *
     * @param string $vers   Statut cible (constante DossierStatut).
     * @param string $acteur 'etudiant' | 'etablissement' | 'systeme' | 'super_admin'
     * @param array{
     *   acteur_id?:int|null, commentaire?:string|null, motif?:string|null,
     *   pieces_attendues?:string|null, expire_le?:string|null,
     *   finances?:array{montant_scolarite_annuelle:float,montant_encaisse_initial:float,
     *                   mode_paiement:string,reference_paiement?:string|null,date_paiement:string},
     *   evenement?:string|null, donnees?:array
     * } $options
     *
     * @return array{ok:bool, erreur:?string, dossier:?array, ancien:?string}
     */
    public function transition(int $dossierId, string $vers, string $acteur, array $options = []): array
    {
        $externe = $this->db->inTransaction();
        if (!$externe) {
            $this->db->beginTransaction();
        }

        try {
            $st = $this->db->prepare(
                'SELECT * FROM dossiers_inscription WHERE id = ? FOR UPDATE'
            );
            $st->execute([$dossierId]);
            $dossier = $st->fetch(PDO::FETCH_ASSOC);

            if ($dossier === false) {
                return $this->echecTransition($externe, 'Dossier introuvable.');
            }

            $ancien = DossierStatut::normaliser((string)$dossier['statut']);

            // Rejouer la même transition n'est pas une erreur : la soumission
            // peut être relancée par un rafraîchissement ou un webhook doublon.
            if ($ancien === $vers) {
                if (!$externe) {
                    $this->db->commit();
                }

                return ['ok' => true, 'erreur' => null, 'dossier' => $dossier, 'ancien' => $ancien];
            }

            $retour = ($options['retour'] ?? false) === true;

            if (!DossierStatut::peut($ancien, $vers, $acteur, $retour)) {
                return $this->echecTransition(
                    $externe,
                    $retour
                        ? 'Cette décision n’est plus annulable : le dossier a changé d’état depuis.'
                        : sprintf(
                            'Transition refusée : %s → %s n’est pas permise à « %s ».',
                            $ancien,
                            $vers,
                            $acteur
                        )
                );
            }

            // Garde-fou financier : sans montant ni mode de paiement, la
            // commission ne pourra jamais être calculée (spec §8.4 / §18).
            // Un retour arrière VERS 100 % n'existe pas, la garde ne s'applique
            // donc jamais à une annulation.
            if ($vers === DossierStatut::VALIDE_100) {
                $erreurFinance = $this->validerFinances($options['finances'] ?? [], $dossier);
                if ($erreurFinance !== null) {
                    return $this->echecTransition($externe, $erreurFinance);
                }
            }

            [$sql, $params] = $this->construireMiseAJour($dossierId, $vers, $acteur, $options, $dossier);
            $this->db->prepare($sql)->execute($params);

            $this->tracerTransition($dossierId, $ancien, $vers, $acteur, $options);

            $st = $this->db->prepare('SELECT * FROM dossiers_inscription WHERE id = ?');
            $st->execute([$dossierId]);
            $apres = $st->fetch(PDO::FETCH_ASSOC) ?: $dossier;

            $this->synchroniserStatutParcours($apres);

            if (!$externe) {
                $this->db->commit();
            }

            $this->journaliserTransition($apres, $ancien, $vers, $acteur, $options);

            return ['ok' => true, 'erreur' => null, 'dossier' => $apres, 'ancien' => $ancien];
        } catch (\Throwable $e) {
            if (!$externe && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[Dossier::transition] #' . $dossierId . ' → ' . $vers . ' : ' . $e->getMessage());

            if ($externe) {
                throw $e; // L'appelant pilote sa propre transaction : il doit savoir.
            }

            return ['ok' => false, 'erreur' => 'Erreur serveur pendant le changement de statut.', 'dossier' => null, 'ancien' => null];
        }
    }

    /**
     * Transitions proposables à un acteur pour ce dossier — alimente les
     * boutons de la zone décision du back-office (spec §8.2).
     *
     * @return list<string>
     */
    public function transitionsPossibles(array $dossier, string $acteur): array
    {
        return DossierStatut::transitionsPossibles((string)($dossier['statut'] ?? ''), $acteur);
    }

    // ── Interne : machine à états ────────────────────────────────────────────

    /** @return array{ok:false, erreur:string, dossier:null, ancien:null} */
    private function echecTransition(bool $externe, string $message): array
    {
        if (!$externe && $this->db->inTransaction()) {
            $this->db->rollBack();
        }

        return ['ok' => false, 'erreur' => $message, 'dossier' => null, 'ancien' => null];
    }

    /**
     * Vérifie que les champs financiers obligatoires sont présents et sains.
     * Retourne le message d'erreur, ou null si tout est bon.
     */
    private function validerFinances(array $finances, array $dossier): ?string
    {
        // Un dossier déjà pourvu (rejeu d'une validation) n'a pas à refournir.
        $dejaRenseigne = $dossier['montant_scolarite_annuelle'] !== null
            && $dossier['mode_paiement'] !== null;

        if ($finances === [] && $dejaRenseigne) {
            return null;
        }

        $scolarite = (float)($finances['montant_scolarite_annuelle'] ?? 0);
        $encaisse  = (float)($finances['montant_encaisse_initial']   ?? 0);
        $mode      = (string)($finances['mode_paiement']             ?? '');
        $date      = (string)($finances['date_paiement']             ?? '');

        if ($scolarite <= 0) {
            return 'Le montant de la scolarité annuelle est obligatoire.';
        }
        if ($encaisse < 0) {
            return 'Le montant encaissé ne peut pas être négatif.';
        }
        if (!in_array($mode, ['especes', 'mobile_money', 'virement', 'cheque', 'autre'], true)) {
            return 'Le mode de paiement est obligatoire.';
        }
        if (\DateTime::createFromFormat('Y-m-d', $date) === false) {
            return 'La date de paiement est obligatoire.';
        }

        return null;
    }

    /**
     * Construit l'UPDATE correspondant à l'état visé : statut, progression et
     * jalons datés. Les colonnes sont des littéraux du code, jamais des entrées
     * utilisateur ; seules les valeurs sont liées.
     *
     * @return array{0:string, 1:array<string,mixed>}
     */
    private function construireMiseAJour(
        int $dossierId,
        string $vers,
        string $acteur,
        array $options,
        array $dossier
    ): array {
        if (($options['retour'] ?? false) === true) {
            return $this->construireRetour($dossierId, $vers, $dossier);
        }

        $colonnes = ['statut = :statut', 'progression = :progression'];
        $params   = [
            ':statut'      => $vers,
            ':progression' => DossierStatut::progression($vers),
            ':id'          => $dossierId,
        ];

        $acteurId = isset($options['acteur_id']) ? (int)$options['acteur_id'] : null;

        switch ($vers) {
            case DossierStatut::SOUMIS:
                // Première soumission uniquement : un retour depuis
                // piece_manquante ne réécrit pas la date de dépôt d'origine.
                if (empty($dossier['soumis_le'])) {
                    $colonnes[] = 'soumis_le = NOW()';
                }
                $colonnes[] = 'pieces_attendues = NULL';
                break;

            case DossierStatut::PIECE_MANQUANTE:
                $colonnes[]                 = 'pieces_attendues = :pieces';
                $params[':pieces']          = $options['pieces_attendues'] ?? null;
                break;

            case DossierStatut::VALIDE_50:
                $colonnes[]            = 'valide_50_le = NOW()';
                $colonnes[]            = 'valide_50_par = :agent';
                $colonnes[]            = 'expire_le = :expire';
                $params[':agent']      = $acteurId;
                $params[':expire']     = $options['expire_le'] ?? $this->calculerExpiration($dossier);
                break;

            case DossierStatut::VALIDE_100:
                $colonnes[]        = 'valide_100_le = NOW()';
                $colonnes[]        = 'valide_100_par = :agent';
                $params[':agent']  = $acteurId;

                $finances = $options['finances'] ?? [];
                if ($finances !== []) {
                    $colonnes[] = 'montant_scolarite_annuelle = :scolarite';
                    $colonnes[] = 'montant_encaisse_initial   = :encaisse';
                    $colonnes[] = 'mode_paiement              = :mode';
                    $colonnes[] = 'reference_paiement         = :refpaie';
                    $colonnes[] = 'date_paiement              = :datepaie';

                    $params[':scolarite'] = number_format((float)$finances['montant_scolarite_annuelle'], 2, '.', '');
                    $params[':encaisse']  = number_format((float)($finances['montant_encaisse_initial'] ?? 0), 2, '.', '');
                    $params[':mode']      = (string)$finances['mode_paiement'];
                    $params[':refpaie']   = $finances['reference_paiement'] ?? null;
                    $params[':datepaie']  = (string)$finances['date_paiement'];

                    // Frais d'inscription : colonne ajoutée après coup. Sur une
                    // base non migrée, la mentionner ferait échouer TOUTE la
                    // validation à 100 % — donc plus aucune inscription au
                    // guichet. On la saute plutôt que de bloquer l'agent.
                    if (\Core\DossierFinanceSchema::fraisInscriptionDisponible($this->db)) {
                        $frais = $finances['montant_frais_inscription'] ?? null;
                        $colonnes[]      = 'montant_frais_inscription = :fraisinsc';
                        $params[':fraisinsc'] = $frais === null
                            ? null
                            : number_format((float)$frais, 2, '.', '');
                    }
                }

                // Gel du taux de commission (spec §10.2). Copié une seule fois :
                // une fois figé, il ne bouge plus jamais, même si le taux de
                // l'établissement change ensuite.
                if ($dossier['taux_commission_applique'] === null) {
                    $colonnes[]     = 'taux_commission_applique = :taux';
                    $params[':taux'] = $this->tauxCommissionApplicable((int)$dossier['etablissement_id']);
                }
                break;

            case DossierStatut::REJETE:
                $colonnes[]       = 'motif_rejet = :motif';
                $params[':motif'] = $options['motif'] ?? $options['commentaire'] ?? null;
                break;

            case DossierStatut::EXPIRE:
            case DossierStatut::ANNULE:
                // Rien de plus : la date est portée par l'historique.
                break;
        }

        $sql = 'UPDATE dossiers_inscription SET ' . implode(', ', $colonnes) . ' WHERE id = :id';

        return [$sql, $params];
    }

    /**
     * Construit l'UPDATE d'un RETOUR ARRIÈRE (annulation d'une décision).
     *
     * Volontairement séparé de construireMiseAJour() : une annulation efface
     * les jalons de la décision annulée, là où une transition normale les pose.
     * Les mélanger dans un même `switch` produirait des UPDATE qui écrivent et
     * effacent la même colonne — par exemple `valide_50_le = NOW()` en revenant
     * de 100 %, ce qui réécrirait la date réelle de la validation à 50 %.
     *
     * Ce qui n'est JAMAIS effacé :
     *  - `taux_commission_applique`, gelé une fois pour toutes (spec §10.2) :
     *    si le dossier repasse à 100 %, il doit retrouver son taux d'origine,
     *    pas celui du jour de la correction ;
     *  - `soumis_le` et `reference`, qui datent et nomment le dépôt : un
     *    récépissé déjà remis au candidat porte cette référence.
     *
     * @return array{0:string, 1:array<string,mixed>}
     */
    private function construireRetour(int $dossierId, string $vers, array $dossier): array
    {
        $colonnes = ['statut = :statut', 'progression = :progression'];
        $params   = [
            ':statut'      => $vers,
            ':progression' => DossierStatut::progression($vers),
            ':id'          => $dossierId,
        ];

        switch (DossierStatut::normaliser((string)$dossier['statut'])) {
            case DossierStatut::VALIDE_50:
                // Retour à « soumis » : le dossier n'est plus recevable, la
                // date limite de présentation n'a donc plus lieu d'être. La
                // laisser ferait expirer un dossier qui n'attend plus personne.
                $colonnes[] = 'valide_50_le  = NULL';
                $colonnes[] = 'valide_50_par = NULL';
                $colonnes[] = 'expire_le     = NULL';
                break;

            case DossierStatut::PIECE_MANQUANTE:
                $colonnes[] = 'pieces_attendues = NULL';
                break;

            case DossierStatut::REJETE:
                $colonnes[] = 'motif_rejet = NULL';
                break;

            case DossierStatut::VALIDE_100:
                $colonnes[] = 'valide_100_le  = NULL';
                $colonnes[] = 'valide_100_par = NULL';
                // Les montants repartent : ils seront ressaisis à la prochaine
                // validation. Les laisser afficherait un règlement encaissé sur
                // un dossier qui n'est plus inscrit.
                $colonnes[] = 'montant_scolarite_annuelle = NULL';
                $colonnes[] = 'montant_encaisse_initial   = NULL';
                $colonnes[] = 'mode_paiement              = NULL';
                $colonnes[] = 'reference_paiement         = NULL';
                $colonnes[] = 'date_paiement              = NULL';

                if (\Core\DossierFinanceSchema::fraisInscriptionDisponible($this->db)) {
                    $colonnes[] = 'montant_frais_inscription = NULL';
                }
                // Le délai de présentation repart de zéro : l'ancienne date
                // limite est presque toujours dépassée au moment où l'on
                // corrige, et la tâche d'expiration ferait disparaître le
                // dossier dès la nuit suivante.
                $colonnes[]        = 'expire_le = :expire';
                $params[':expire'] = $this->calculerExpiration($dossier);
                break;
        }

        // Un dossier qui redevient « soumis » sans date de dépôt serait invisible
        // des tableaux de suivi, qui trient tous sur soumis_le.
        if ($vers === DossierStatut::SOUMIS && empty($dossier['soumis_le'])) {
            $colonnes[] = 'soumis_le = NOW()';
        }

        $sql = 'UPDATE dossiers_inscription SET ' . implode(', ', $colonnes) . ' WHERE id = :id';

        return [$sql, $params];
    }

    /**
     * Date limite de passage en présentiel = aujourd'hui + délai de
     * l'établissement, avec repli sur le paramètre global (spec §8.3).
     */
    private function calculerExpiration(array $dossier): string
    {
        $jours = 0;

        try {
            $st = $this->db->prepare(
                'SELECT delai_expiration_dossier_jours FROM etablissements WHERE id = ?'
            );
            $st->execute([(int)$dossier['etablissement_id']]);
            $jours = (int)$st->fetchColumn();
        } catch (\Throwable $e) {
            error_log('[Dossier] Délai d’expiration illisible : ' . $e->getMessage());
        }

        if ($jours <= 0) {
            $jours = (int)\Core\Settings::get('delai_expiration_defaut_jours', 30);
        }

        return (new \DateTimeImmutable('+' . max(1, $jours) . ' days'))->format('Y-m-d H:i:s');
    }

    /**
     * Taux applicable au moment T, du plus spécifique au plus général
     * (spec §10.1) : établissement, puis paramètre global de la plateforme.
     */
    public function tauxCommissionApplicable(int $etablissementId): string
    {
        try {
            $st = $this->db->prepare(
                'SELECT taux_commission_scolarite FROM etablissements WHERE id = ?'
            );
            $st->execute([$etablissementId]);
            $taux = $st->fetchColumn();

            if ($taux !== false && $taux !== null && (float)$taux > 0) {
                return number_format((float)$taux, 2, '.', '');
            }
        } catch (\Throwable $e) {
            error_log('[Dossier] Taux établissement illisible : ' . $e->getMessage());
        }

        return number_format((float)\Core\Settings::get('taux_commission_defaut', 10.00), 2, '.', '');
    }

    /** Ligne d'historique — obligatoire à chaque transition (spec §4.2). */
    private function tracerTransition(
        int $dossierId,
        string $ancien,
        string $vers,
        string $acteur,
        array $options
    ): void {
        $this->db->prepare(
            'INSERT INTO historique_statut_dossier
               (dossier_id, ancien_statut, nouveau_statut, acteur_id, acteur_role, commentaire, cree_le)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        )->execute([
            $dossierId,
            $ancien,
            $vers,
            isset($options['acteur_id']) ? (int)$options['acteur_id'] : null,
            $acteur,
            $options['commentaire'] ?? $options['motif'] ?? null,
        ]);
    }

    /**
     * Journal d'activité — écrit APRÈS commit : une trace ne doit jamais
     * pouvoir faire échouer la transition qu'elle décrit.
     */
    private function journaliserTransition(
        array $dossier,
        string $ancien,
        string $vers,
        string $acteur,
        array $options
    ): void {
        $evenement = $options['evenement'] ?? match ($vers) {
            DossierStatut::SOUMIS     => Journal::DOSSIER_SOUMIS,
            DossierStatut::VALIDE_50  => Journal::DOSSIER_VALIDE_50,
            DossierStatut::VALIDE_100 => Journal::DOSSIER_VALIDE_100,
            DossierStatut::REJETE     => Journal::DOSSIER_REJETE,
            DossierStatut::EXPIRE     => Journal::DOSSIER_EXPIRE,
            DossierStatut::ANNULE     => Journal::DOSSIER_ANNULE,
            default                   => 'dossier_statut_modifie',
        };

        Journal::dossier(
            $evenement,
            $dossier,
            ($options['donnees'] ?? []) + array_filter([
                'ancien_statut'  => $ancien,
                'nouveau_statut' => $vers,
                'reference'      => $dossier['reference'] ?? null,
                'motif'          => $options['motif'] ?? null,
                'commentaire'    => $options['commentaire'] ?? null,
            ], static fn($v): bool => $v !== null),
            ['acteur_role' => $acteur, 'acteur_id' => $options['acteur_id'] ?? null]
        );
    }

    /**
     * Tient à jour users.statut_parcours (spec §12.1) : visiteur → candidat →
     * inscrit. Ne redescend jamais : « inscrit » est un acquis.
     */
    private function synchroniserStatutParcours(array $dossier): void
    {
        $eleveId = (int)($dossier['eleve_id'] ?? 0);
        if ($eleveId === 0) {
            return;
        }

        $statut = DossierStatut::normaliser((string)$dossier['statut']);

        $cible = match (true) {
            $statut === DossierStatut::VALIDE_100    => 'inscrit',
            $statut === DossierStatut::BROUILLON     => null,
            default                                   => 'candidat',
        };

        if ($cible === null) {
            return;
        }

        try {
            // ORDER FIELD garantit la monotonie : on ne repasse jamais
            // « inscrit » à « candidat » en une seule requête atomique.
            $this->db->prepare(
                "UPDATE users
                 SET statut_parcours = ?
                 WHERE id = ?
                   AND FIELD(statut_parcours, 'visiteur', 'candidat', 'inscrit') < FIELD(?, 'visiteur', 'candidat', 'inscrit')"
            )->execute([$cible, $eleveId, $cible]);
        } catch (\Throwable $e) {
            error_log('[Dossier] statut_parcours non mis à jour : ' . $e->getMessage());
        }
    }

    /**
     * Réserve un numéro de récépissé unique et crée la ligne (cle_pdf vide,
     * complétée ensuite par updateRecepisseCle). Numérotation atomique et
     * anti-collision : la contrainte numero_recepisse est unique sur toute la
     * table — on part du plus grand numéro de l'année, puis on réessaie en
     * incrémentant tant que l'INSERT bute sur un doublon (course concurrente ou
     * numéro déjà pris par un autre établissement). Idempotent : renvoie le
     * numéro existant si le dossier possède déjà un récépissé.
     */
    public function reserveRecepisse(int $dossierId): string
    {
        $existing = $this->getRecepisse($dossierId);
        if ($existing && !empty($existing['numero_recepisse'])) {
            return (string)$existing['numero_recepisse'];
        }

        $year = date('Y');
        $base = $this->nextSequence('recepisses', 'numero_recepisse', 'PRE', $year);

        for ($i = 0; $i < 50; $i++) {
            $numero = sprintf('PRE-%s-%04d', $year, $base + $i);
            try {
                $this->db->prepare('
                    INSERT INTO recepisses (dossier_id, numero_recepisse, cle_pdf, date_emission, token_verification)
                    VALUES (?, ?, "", NOW(), ?)
                ')->execute([$dossierId, $numero, bin2hex(random_bytes(32))]);

                try {
                    // Une ligne dans `preinscriptions` ATTESTE d'une
                    // préinscription : c'est sur elle que comptent le bilan de
                    // l'établissement, la courbe du tableau de bord et la fiche
                    // candidat de la console d'administration.
                    //
                    // Or ce numéro sert AUSSI au récépissé de dépôt d'un dossier
                    // d'inscription (RecepisseDossierService::generer) : en
                    // écrire une systématiquement faisait compter chaque
                    // inscription comme une préinscription de plus, et
                    // disparaître le même dossier du compte des inscriptions.
                    // La nature du dossier décide, pas le document imprimé.
                    $ctxDossier = $this->findById($dossierId);

                    if ($ctxDossier !== null && !self::estInscription($ctxDossier)) {
                        $this->db->prepare('
                            INSERT INTO preinscriptions (dossier_id, date_preinscription)
                            VALUES (?, NOW())
                        ')->execute([$dossierId]);

                        // Traçabilité §11.2 — l'acte de préinscription, c'est
                        // précisément cette ligne : elle n'est écrite qu'une fois
                        // par dossier, donc pas de doublon dans la frise.
                        Journal::dossier(Journal::PREINSCRIPTION_CREEE, $ctxDossier, [
                            'numero_recepisse' => $numero,
                            'filiere'          => $ctxDossier['filiere_visee'] ?? null,
                        ]);
                    }
                } catch (\Throwable) {
                    // Doublon éventuel (rejeu) — ignoré
                }
                return $numero;
            } catch (\PDOException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
                // Collision : dossier déjà pourvu (course) → on renvoie l'existant,
                // sinon numéro pris ailleurs → on réessaie avec +1.
                $existing = $this->getRecepisse($dossierId);
                if ($existing && !empty($existing['numero_recepisse'])) {
                    return (string)$existing['numero_recepisse'];
                }
            }
        }
        throw new \RuntimeException('Récépissé : impossible de réserver un numéro unique.');
    }

    public function updateRecepisseCle(int $dossierId, string $cle): void
    {
        $this->db->prepare(
            'UPDATE recepisses SET cle_pdf = ? WHERE dossier_id = ?'
        )->execute([$cle, $dossierId]);
    }

    /**
     * Prochain numéro séquentiel (global, par année) pour une colonne au format
     * "PREFIX-AAAA-NNNN". Renvoie MAX(NNNN)+1 sur l'année, ou 1 si aucun.
     * $table/$col sont des identifiants internes fixes (jamais des entrées
     * utilisateur) — interpolation sûre.
     */
    private function nextSequence(string $table, string $col, string $prefix, string $year): int
    {
        $st = $this->db->prepare(
            "SELECT MAX(CAST(SUBSTRING_INDEX($col, '-', -1) AS UNSIGNED))
             FROM $table
             WHERE $col LIKE :like"
        );
        $st->execute([':like' => $prefix . '-' . $year . '-%']);
        return (int)$st->fetchColumn() + 1;
    }

    public function getRecepisse(int $dossierId): ?array
    {
        $st = $this->db->prepare('SELECT * FROM recepisses WHERE dossier_id = ? LIMIT 1');
        $st->execute([$dossierId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getPiecesObligatoires(int $etablissementId): array
    {
        $st = $this->db->prepare(
            'SELECT id FROM pieces_justificatives_requises
             WHERE etablissement_id = ? AND obligatoire = 1 AND id > 0'
        );
        $st->execute([$etablissementId]);
        return $st->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    /**
     * Liste des pièces à demander, adaptée à la situation de bourse déclarée
     * (spec §5.3). La réponse de l'étape 1 fait apparaître ou disparaître la
     * pièce `attestation_bourse` — la liste n'est plus figée.
     *
     * Une pièce dont `condition_bourse` vaut « tous » est toujours demandée.
     * Toute autre valeur ne s'affiche que si elle correspond exactement à la
     * déclaration. Tant qu'aucune situation n'est déclarée, seules les pièces
     * communes sont retournées.
     *
     * @return list<array<string,mixed>>
     */
    public function piecesPourBourse(int $etablissementId, ?string $statutBourse): array
    {
        $st = $this->db->prepare(
            'SELECT * FROM pieces_justificatives_requises
             WHERE etablissement_id = :etab
               AND (condition_bourse = \'tous\' OR condition_bourse = :bourse)
             ORDER BY obligatoire DESC, ordre ASC, id ASC'
        );
        $st->execute([':etab' => $etablissementId, ':bourse' => (string)$statutBourse]);

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Pièces attendues pour UN dossier précis : filtrées à la fois par le
     * niveau d'entrée et par la situation de bourse déclarée.
     *
     * Les deux filtres existaient séparément — `getPiecesGroupedByNiveau` d'un
     * côté, `piecesPourBourse` de l'autre — et chaque écran n'en appliquait
     * qu'un. L'espace élève réclamait donc une attestation de bourse ANBG à des
     * candidats non boursiers, qui n'avaient aucun moyen de la produire.
     *
     * @param array<string,mixed> $dossier
     * @return list<array<string,mixed>>
     */
    public function piecesAttenduesPourDossier(array $dossier): array
    {
        $niveau = trim((string)($dossier['niveau_entree'] ?? ''));

        $lignes = $this->piecesPourBourse(
            (int)($dossier['etablissement_id'] ?? 0),
            $dossier['statut_bourse'] ?? null
        );

        // La comparaison passe par PorteePiece, JAMAIS par l'égalité de deux
        // chaînes : la colonne de la pièce porte un cycle (« Master M1-M2 »)
        // et celle du dossier une année (« Master 1 »). Comparées telles
        // quelles, elles n'étaient jamais égales — une pièce de master
        // disparaissait donc de l'espace d'un candidat de master, et restait
        // affichée à un candidat de licence.
        return array_values(array_filter(
            $lignes,
            static fn(array $piece): bool => \Services\Orientation\PorteePiece::concerne(
                $piece['niveau'] ?? null,
                $niveau
            )
        ));
    }

    /**
     * Identifiants des pièces obligatoires pour CE dossier, niveau d'entrée et
     * situation de bourse compris. Version « dossier » de
     * piecesObligatoiresPourBourse(), qui n'applique que le second filtre.
     *
     * @param array<string,mixed> $dossier
     * @return list<int>
     */
    public function piecesObligatoiresPourDossier(array $dossier): array
    {
        $obligatoires = [];

        foreach ($this->piecesAttenduesPourDossier($dossier) as $piece) {
            if (\Services\Orientation\PieceDossier::estObligatoire($piece, $dossier['statut_bourse'] ?? null)) {
                $obligatoires[] = (int)$piece['id'];
            }
        }

        return $obligatoires;
    }

    /**
     * Identifiants des pièces réellement obligatoires pour CE dossier :
     * les pièces communes obligatoires, plus la pièce de bourse quand la
     * situation déclarée l'impose (boursier confirmé).
     *
     * C'est cette liste que le serveur oppose à la soumission — jamais celle
     * calculée par le navigateur (spec §5.2 : ne jamais faire confiance au
     * client).
     *
     * @return list<int>
     */
    public function piecesObligatoiresPourBourse(int $etablissementId, ?string $statutBourse): array
    {
        $obligatoires = [];

        foreach ($this->piecesPourBourse($etablissementId, $statutBourse) as $piece) {
            $code = $piece['code_interne'] ?? null;

            // La pièce liée à la bourse porte sa propre règle : obligatoire
            // pour un boursier confirmé, seulement recommandée pour un dossier
            // en cours de traitement.
            if ($code !== null && \Services\Orientation\SituationBourse::pieceAssociee($statutBourse) === $code) {
                if (\Services\Orientation\SituationBourse::pieceObligatoire($statutBourse)) {
                    $obligatoires[] = (int)$piece['id'];
                }
                continue;
            }

            if ((int)($piece['obligatoire'] ?? 0) === 1) {
                $obligatoires[] = (int)$piece['id'];
            }
        }

        return $obligatoires;
    }

    /**
     * Pièces manquantes parmi les obligatoires, en tenant compte de ce qui est
     * DÉJÀ en base (reprise d'un brouillon, ajout après demande de complément)
     * et de ce qui arrive dans la requête courante.
     *
     * @param array<string,mixed> $fichiers $_FILES
     * @return list<array<string,mixed>> Les lignes de pièces encore absentes.
     */
    public function piecesManquantes(
        int $dossierId,
        int $etablissementId,
        ?string $statutBourse,
        array $fichiers = []
    ): array {
        return $this->manquantesParmi(
            $dossierId,
            $this->piecesObligatoiresPourBourse($etablissementId, $statutBourse),
            $fichiers
        );
    }

    /**
     * Pièces manquantes pour CE dossier : niveau d'entrée ET situation de
     * bourse compris.
     *
     * Version « dossier » de piecesManquantes(), qui n'applique que le second
     * filtre. C'est celle qu'oppose la soumission de l'étape 2 : sans elle, un
     * candidat de licence restait bloqué sur une pièce obligatoire réservée au
     * master — une pièce qu'il n'avait aucun moyen de fournir, et que le
     * formulaire ne lui montrait même pas.
     *
     * @param array<string,mixed> $dossier  Dossier À JOUR (niveau d'entrée enregistré).
     * @param array<string,mixed> $fichiers $_FILES
     * @return list<array<string,mixed>> Les lignes de pièces encore absentes.
     */
    public function piecesManquantesPourDossier(array $dossier, array $fichiers = []): array
    {
        return $this->manquantesParmi(
            (int)($dossier['id'] ?? 0),
            $this->piecesObligatoiresPourDossier($dossier),
            $fichiers
        );
    }

    /**
     * Cœur commun aux deux méthodes ci-dessus : parmi une liste d'obligatoires
     * déjà arrêtée, lesquelles ne sont ni en base ni dans la requête courante ?
     *
     * @param list<int>           $obligatoires
     * @param array<string,mixed> $fichiers $_FILES
     * @return list<array<string,mixed>>
     */
    private function manquantesParmi(int $dossierId, array $obligatoires, array $fichiers): array
    {
        if ($obligatoires === []) {
            return [];
        }

        $deposees = [];
        $st = $this->db->prepare(
            'SELECT DISTINCT piece_requise_id FROM documents_orientation
             WHERE dossier_id = ? AND piece_requise_id IS NOT NULL AND statut <> \'refuse\''
        );
        $st->execute([$dossierId]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN, 0) as $id) {
            $deposees[(int)$id] = true;
        }

        $manquantes = [];
        foreach ($obligatoires as $pieceId) {
            if (isset($deposees[$pieceId])) {
                continue;
            }
            $cle = 'piece_' . $pieceId;
            if (isset($fichiers[$cle]) && ($fichiers[$cle]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                continue;
            }
            $manquantes[] = $pieceId;
        }

        if ($manquantes === []) {
            return [];
        }

        // Renvoie les lignes complètes : l'appelant a besoin des libellés pour
        // le message d'erreur, pas seulement des identifiants.
        $place = implode(',', array_fill(0, count($manquantes), '?'));
        $st    = $this->db->prepare(
            "SELECT * FROM pieces_justificatives_requises WHERE id IN ({$place}) ORDER BY ordre ASC, id ASC"
        );
        $st->execute($manquantes);

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPiecesGroupedByNiveau(int $etablissementId): array
    {
        $st = $this->db->prepare('
            SELECT * FROM pieces_justificatives_requises
            WHERE etablissement_id = ?
            ORDER BY obligatoire DESC, ordre ASC, id ASC
        ');
        $st->execute([$etablissementId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($rows as $row) {
            $key = $row['niveau'] ?? '__tous';
            $grouped[$key][] = $row;
        }
        return $grouped;
    }

    public function addDocument(array $d): int
    {
        $st = $this->db->prepare('
            INSERT INTO documents_orientation
              (dossier_id, eleve_id, piece_requise_id, libelle, cle_b2, mime_type, taille_octets)
            VALUES
              (:dossier_id, :eleve_id, :piece_requise_id, :libelle, :cle_b2, :mime_type, :taille)
        ');
        $st->execute([
            ':dossier_id'       => $d['dossier_id'],
            ':eleve_id'         => $d['eleve_id'] ?? 0,
            ':piece_requise_id' => $d['piece_requise_id'] ?? null,
            ':libelle'          => $d['libelle'],
            ':cle_b2'           => $d['cle_b2'],
            ':mime_type'        => $d['mime_type'],
            ':taille'           => $d['taille_octets'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    // ── Attestation d'inscription (délivrée après validation) ────────────────

    /**
     * Réserve un numéro d'attestation unique et crée la ligne (cle_pdf vide).
     * Même stratégie atomique anti-collision que reserveRecepisse (contrainte
     * uq_attestation_numero unique sur toute la table). Idempotent.
     */
    public function reserveAttestation(int $dossierId): string
    {
        $existing = $this->getAttestation($dossierId);
        if ($existing && !empty($existing['numero_attestation'])) {
            return (string)$existing['numero_attestation'];
        }

        $year = date('Y');
        $base = $this->nextSequence('attestations_inscription', 'numero_attestation', 'ATT', $year);

        for ($i = 0; $i < 50; $i++) {
            $numero = sprintf('ATT-%s-%04d', $year, $base + $i);
            try {
                $this->db->prepare('
                    INSERT INTO attestations_inscription (dossier_id, numero_attestation, cle_pdf, date_emission, token_verification)
                    VALUES (?, ?, "", NOW(), ?)
                ')->execute([$dossierId, $numero, bin2hex(random_bytes(32))]);
                return $numero;
            } catch (\PDOException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
                $existing = $this->getAttestation($dossierId);
                if ($existing && !empty($existing['numero_attestation'])) {
                    return (string)$existing['numero_attestation'];
                }
            }
        }
        throw new \RuntimeException('Attestation : impossible de réserver un numéro unique.');
    }

    public function updateAttestationCle(int $dossierId, string $cle): void
    {
        $this->db->prepare(
            'UPDATE attestations_inscription SET cle_pdf = ? WHERE dossier_id = ?'
        )->execute([$cle, $dossierId]);
    }

    public function getAttestation(int $dossierId): ?array
    {
        $st = $this->db->prepare('SELECT * FROM attestations_inscription WHERE dossier_id = ? LIMIT 1');
        $st->execute([$dossierId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ── Vérification d'authenticité (QR code) ────────────────────────────────

    /**
     * Token de vérification du récépissé (64 hex, 256 bits aléatoires),
     * encodé dans le QR code imprimé sur le document. Backfill paresseux :
     * les lignes créées avant la migration reçoivent leur token au premier
     * accès. Renvoie null si le dossier n'a pas encore de récépissé.
     */
    public function tokenVerificationRecepisse(int $dossierId): ?string
    {
        return $this->tokenVerification('recepisses', $dossierId);
    }

    /** Idem pour l'attestation d'inscription. */
    public function tokenVerificationAttestation(int $dossierId): ?string
    {
        return $this->tokenVerification('attestations_inscription', $dossierId);
    }

    /** $table est un identifiant interne fixe (jamais une entrée utilisateur). */
    private function tokenVerification(string $table, int $dossierId): ?string
    {
        $st = $this->db->prepare("SELECT id, token_verification FROM $table WHERE dossier_id = ? LIMIT 1");
        $st->execute([$dossierId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        if (!empty($row['token_verification'])) {
            return (string)$row['token_verification'];
        }

        // ── Pose du jeton : le premier écrivain gagne, et tous relisent ────
        //
        // Le SELECT ci-dessus et l'UPDATE ci-dessous ne sont pas atomiques. Un
        // candidat qui double-clique sur « télécharger » lance deux rendus :
        // les deux lisaient un jeton vide, les deux en tiraient un, et le
        // second écrasait le premier. Le document DÉJÀ rendu et imprimé
        // portait alors un QR code que /verifier ne reconnaissait plus — un
        // document officiel devenu invérifiable, sans le moindre message.
        //
        // La clause `WHERE token_verification IS NULL OR = ''` rend l'écriture
        // conditionnelle : un seul concurrent la remporte. Chacun relit ensuite
        // la valeur retenue, et tous les rendus concurrents portent le même
        // jeton — celui qui est réellement en base.
        $token = bin2hex(random_bytes(32));
        $this->db->prepare(
            "UPDATE $table SET token_verification = ?
              WHERE id = ? AND (token_verification IS NULL OR token_verification = '')"
        )->execute([$token, (int)$row['id']]);

        $relu = $this->db->prepare("SELECT token_verification FROM $table WHERE id = ? LIMIT 1");
        $relu->execute([(int)$row['id']]);
        $retenu = (string)($relu->fetchColumn() ?: '');

        return $retenu !== '' ? $retenu : $token;
    }

    /**
     * Recherche un récépissé par son token de vérification (scan du QR code).
     * Renvoie les données officielles à afficher publiquement : document,
     * dossier, établissement et compte élève lié — ou null si token inconnu.
     */
    public function findRecepisseByToken(string $token): ?array
    {
        $st = $this->db->prepare('
            SELECT r.numero_recepisse AS numero, r.date_emission, r.token_verification,
                   d.id AS dossier_id, d.nom_complet, d.genre, d.date_naissance, d.lieu_naissance,
                   d.nationalite, d.pays_provenance, d.email, d.indicatif_tel, d.telephone,
                   d.filiere_visee, d.specialite_visee, d.niveau_entree, d.dernier_etablissement,
                   d.statut, d.origine, d.date_creation, d.eleve_id,
                   e.nom AS etab_nom, e.ville AS etab_ville, e.slug AS etab_slug,
                   e.couleur_principale, e.logo_cle,
                   u.nom AS compte_nom, u.prenom AS compte_prenom, u.email AS compte_email,
                   u.created_at AS compte_cree_le, u.is_verified AS compte_verifie
            FROM recepisses r
            JOIN dossiers_inscription d ON d.id = r.dossier_id
            JOIN etablissements e ON e.id = d.etablissement_id
            LEFT JOIN users u ON u.id = d.eleve_id
            WHERE r.token_verification = ?
            LIMIT 1
        ');
        $st->execute([$token]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Idem pour l'attestation d'inscription. */
    public function findAttestationByToken(string $token): ?array
    {
        $st = $this->db->prepare('
            SELECT a.numero_attestation AS numero, a.date_emission, a.token_verification,
                   d.id AS dossier_id, d.nom_complet, d.genre, d.date_naissance, d.lieu_naissance,
                   d.nationalite, d.pays_provenance, d.email, d.indicatif_tel, d.telephone,
                   d.adresse, d.ville,
                   d.filiere_visee, d.specialite_visee, d.niveau_entree, d.dernier_etablissement,
                   d.statut, d.origine, d.date_creation, d.eleve_id,
                   e.nom AS etab_nom, e.ville AS etab_ville, e.slug AS etab_slug,
                   e.couleur_principale, e.logo_cle,
                   u.nom AS compte_nom, u.prenom AS compte_prenom, u.email AS compte_email,
                   u.created_at AS compte_cree_le, u.is_verified AS compte_verifie
            FROM attestations_inscription a
            JOIN dossiers_inscription d ON d.id = a.dossier_id
            JOIN etablissements e ON e.id = d.etablissement_id
            LEFT JOIN users u ON u.id = d.eleve_id
            WHERE a.token_verification = ?
            LIMIT 1
        ');
        $st->execute([$token]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ── Paiement ─────────────────────────────────────────────────────────────

    public function createPaiement(array $d): int
    {
        $st = $this->db->prepare('
            INSERT INTO paiements_orientation
              (dossier_id, etablissement_id, montant, mode, token_pay, statut)
            VALUES
              (:dossier_id, :etablissement_id, :montant, :mode, :token_pay, :statut)
        ');
        $st->execute([
            ':dossier_id'       => $d['dossier_id'],
            ':etablissement_id' => $d['etablissement_id'],
            ':montant'          => $d['montant'],
            ':mode'             => $d['mode'] ?? 'en_ligne',
            ':token_pay'        => $d['token_pay'] ?? null,
            ':statut'           => $d['statut'] ?? 'initie',
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function findPaiementById(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM paiements_orientation WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findPaiementByToken(string $token): ?array
    {
        $st = $this->db->prepare('SELECT * FROM paiements_orientation WHERE token_pay = ? LIMIT 1');
        $st->execute([$token]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findLastPaiementByDossier(int $dossierId): ?array
    {
        $st = $this->db->prepare(
            'SELECT * FROM paiements_orientation WHERE dossier_id = ? ORDER BY id DESC LIMIT 1'
        );
        $st->execute([$dossierId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Paiements d'un établissement, joints au dossier, au reçu et au
     * reversement — pour la page Paiements de l'espace gestionnaire.
     */
    public function findPaiementsByEtab(int $etabId, int $limit = 300): array
    {
        $st = $this->db->prepare('
            SELECT p.*,
                   d.nom_complet, d.email, d.filiere_visee, d.specialite_visee,
                   d.niveau_entree, d.statut AS dossier_statut,
                   rec.numero_recu, rec.cle_pdf AS recu_cle, rec.date_emission AS recu_date,
                   rv.statut AS reversement_statut, rv.montant_reverse,
                   rv.commission_plateforme, rv.date_confirmation AS reversement_date
            FROM paiements_orientation p
            JOIN dossiers_inscription d ON d.id = p.dossier_id
            LEFT JOIN recus_orientation rec ON rec.paiement_id = p.id
            LEFT JOIN reversements rv ON rv.paiement_id = p.id
            WHERE p.etablissement_id = :etab
            ORDER BY p.date_paiement DESC
            LIMIT ' . max(1, $limit)
        );
        $st->execute([':etab' => $etabId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Totaux paiements d'un établissement (paiements réussis uniquement).
     */
    public function statsPaiementsEtab(int $etabId): array
    {
        $st = $this->db->prepare("
            SELECT COUNT(*)                                        AS nb_reussis,
                   COALESCE(SUM(p.montant), 0)                     AS montant_total,
                   COALESCE(SUM(rv.montant_reverse), 0)            AS montant_reverse,
                   COALESCE(SUM(rv.commission_plateforme), 0)      AS commission_totale,
                   COALESCE(SUM(rv.statut = 'reussi'), 0)          AS reversements_reussis
            FROM paiements_orientation p
            LEFT JOIN reversements rv ON rv.paiement_id = p.id
            WHERE p.etablissement_id = ? AND p.statut = 'reussi'
        ");
        $st->execute([$etabId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Tous les paiements de la plateforme (vue super admin), filtrables par
     * établissement et statut.
     */
    public function findAllPaiements(?int $etabId = null, ?string $statut = null, int $limit = 500): array
    {
        $where  = [];
        $params = [];
        if ($etabId) {
            $where[]         = 'p.etablissement_id = :etab';
            $params[':etab'] = $etabId;
        }
        if ($statut !== null && $statut !== '') {
            $where[]           = 'p.statut = :statut';
            $params[':statut'] = $statut;
        }
        $sql = '
            SELECT p.*,
                   d.nom_complet, d.email, d.filiere_visee, d.specialite_visee,
                   e.nom AS etab_nom, e.slug AS etab_slug, e.couleur_principale,
                   rec.numero_recu, rec.cle_pdf AS recu_cle,
                   rv.statut AS reversement_statut, rv.montant_reverse,
                   rv.commission_plateforme, rv.numero_destination
            FROM paiements_orientation p
            JOIN dossiers_inscription d ON d.id = p.dossier_id
            JOIN etablissements e ON e.id = p.etablissement_id
            LEFT JOIN recus_orientation rec ON rec.paiement_id = p.id
            LEFT JOIN reversements rv ON rv.paiement_id = p.id
            ' . (!empty($where) ? 'WHERE ' . implode(' AND ', $where) : '') . '
            ORDER BY p.date_paiement DESC
            LIMIT ' . max(1, $limit);
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Totaux plateforme (vue super admin) — paiements réussis uniquement.
     */
    public function statsPaiementsGlobal(): array
    {
        $st = $this->db->query("
            SELECT COUNT(*)                                        AS nb_reussis,
                   COALESCE(SUM(p.montant), 0)                     AS montant_total,
                   COALESCE(SUM(rv.montant_reverse), 0)            AS montant_reverse,
                   COALESCE(SUM(rv.commission_plateforme), 0)      AS commission_totale,
                   COALESCE(SUM(rv.statut = 'reussi'), 0)          AS reversements_reussis,
                   COALESCE(SUM(rv.statut IN ('a_rejouer','echoue')), 0) AS reversements_en_echec
            FROM paiements_orientation p
            LEFT JOIN reversements rv ON rv.paiement_id = p.id
            WHERE p.statut = 'reussi'
        ");
        return $st->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function updatePaiementStatut(int $id, string $statut): void
    {
        $sql    = 'UPDATE paiements_orientation SET statut = ?';
        $params = [$statut];
        if ($statut === 'reussi') {
            $sql .= ', date_confirmation = NOW()';
        }
        $sql .= ' WHERE id = ?';
        $params[] = $id;
        $this->db->prepare($sql)->execute($params);
    }

    public function updatePaiementToken(int $id, string $token): void
    {
        $this->db->prepare('UPDATE paiements_orientation SET token_pay = ? WHERE id = ?')
            ->execute([$token, $id]);
    }

    // ── Inscription ───────────────────────────────────────────────────────────

    public function hasInscription(int $dossierId): bool
    {
        $st = $this->db->prepare('SELECT COUNT(*) FROM inscriptions WHERE dossier_id = ?');
        $st->execute([$dossierId]);
        return (int)$st->fetchColumn() > 0;
    }

    public function createInscription(int $dossierId, int $etabId): void
    {
        try {
            $this->db->prepare('
                INSERT INTO inscriptions (dossier_id, etablissement_id)
                VALUES (?, ?)
            ')->execute([$dossierId, $etabId]);
        } catch (\Throwable) {
            // Ignore duplicate (idempotence)
        }
    }

    // ── Reçu ─────────────────────────────────────────────────────────────────

    /**
     * Réserve un numéro de reçu unique et crée la ligne (cle_pdf vide).
     * Même stratégie atomique anti-collision (numero_recu unique sur toute la
     * table). Idempotent par paiement. Renvoie le numéro attribué.
     */
    public function reserveRecu(int $paiementId): string
    {
        $existing = $this->getRecu($paiementId);
        if ($existing && !empty($existing['numero_recu'])) {
            return (string)$existing['numero_recu'];
        }

        $year = date('Y');
        $base = $this->nextSequence('recus_orientation', 'numero_recu', 'REC', $year);

        for ($i = 0; $i < 50; $i++) {
            $numero = sprintf('REC-%s-%04d', $year, $base + $i);
            try {
                $this->db->prepare('
                    INSERT INTO recus_orientation (paiement_id, numero_recu, cle_pdf, date_emission)
                    VALUES (?, ?, "", NOW())
                ')->execute([$paiementId, $numero]);
                return $numero;
            } catch (\PDOException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
                $existing = $this->getRecu($paiementId);
                if ($existing && !empty($existing['numero_recu'])) {
                    return (string)$existing['numero_recu'];
                }
            }
        }
        throw new \RuntimeException('Reçu : impossible de réserver un numéro unique.');
    }

    public function updateRecuCle(int $paiementId, string $cle): void
    {
        $this->db->prepare(
            'UPDATE recus_orientation SET cle_pdf = ? WHERE paiement_id = ?'
        )->execute([$cle, $paiementId]);
    }

    public function getRecu(int $paiementId): ?array
    {
        $st = $this->db->prepare('SELECT * FROM recus_orientation WHERE paiement_id = ? LIMIT 1');
        $st->execute([$paiementId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ── Gestionnaire ─────────────────────────────────────────────────────────

    // ── Back-office établissement : liste filtrable (spec §8.1) ──────────────

    /**
     * Vue unique des dossiers d'un établissement, avec filtres cumulables.
     *
     * L'ancienneté en jours est calculée par MySQL plutôt qu'en PHP : c'est
     * elle qui sert au tri et au repérage visuel des dossiers qui dorment, et
     * la trier en PHP obligerait à tout charger avant de paginer.
     *
     * @param array{
     *   statut?:string, filiere?:string, statut_bourse?:string,
     *   du?:string, au?:string, q?:string, tri?:string
     * } $filtres
     * @return list<array<string,mixed>>
     */
    public function findByEtabFiltre(int $etabId, array $filtres = [], int $limite = 50, int $offset = 0): array
    {
        [$where, $params] = $this->clausesFiltre($etabId, $filtres);

        // LIMIT/OFFSET interpolés après cast entier borné : avec
        // EMULATE_PREPARES=false, MySQL refuse un placeholder sur LIMIT.
        $limite = max(1, min($limite, 200));
        $offset = max(0, $offset);

        $tri = match ($filtres['tri'] ?? '') {
            'anciennete' => 'anciennete DESC',
            'nom'        => 'd.nom_complet ASC',
            'reference'  => 'd.reference DESC',
            default      => 'COALESCE(d.soumis_le, d.date_creation) DESC',
        };

        $sql = "SELECT d.*,
                       r.numero_recepisse, r.cle_pdf AS recepisse_cle,
                       a.numero_attestation,
                       (SELECT i.id FROM inscriptions i WHERE i.dossier_id = d.id LIMIT 1) AS inscription_id,
                       DATEDIFF(NOW(), COALESCE(d.soumis_le, d.date_creation)) AS anciennete,
                       (SELECT COUNT(*) FROM documents_orientation doc
                         WHERE doc.dossier_id = d.id AND doc.statut <> 'refuse') AS nb_pieces
                FROM dossiers_inscription d
                LEFT JOIN recepisses r ON r.dossier_id = d.id
                LEFT JOIN attestations_inscription a ON a.dossier_id = d.id
                WHERE {$where}
                ORDER BY {$tri}
                LIMIT {$limite} OFFSET {$offset}";

        $st = $this->db->prepare($sql);
        $st->execute($params);

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Nombre total de dossiers correspondant aux mêmes filtres (pagination). */
    public function countByEtabFiltre(int $etabId, array $filtres = []): int
    {
        [$where, $params] = $this->clausesFiltre($etabId, $filtres);

        $st = $this->db->prepare("SELECT COUNT(*) FROM dossiers_inscription d WHERE {$where}");
        $st->execute($params);

        return (int)$st->fetchColumn();
    }

    /**
     * Compteurs par statut, cliquables en haut de la liste (spec §8.1).
     *
     * Les filtres autres que le statut sont appliqués : les compteurs doivent
     * refléter ce que l'agent regarde, pas l'établissement entier — sinon ils
     * mentent dès qu'un filtre de filière est posé.
     *
     * @return array<string,int> statut => nombre, tous les états présents
     */
    public function compteursParStatut(int $etabId, array $filtres = []): array
    {
        unset($filtres['statut']);
        [$where, $params] = $this->clausesFiltre($etabId, $filtres);

        $compteurs = array_fill_keys(DossierStatut::tous(), 0);

        try {
            $st = $this->db->prepare(
                "SELECT d.statut, COUNT(*) AS n
                 FROM dossiers_inscription d
                 WHERE {$where}
                 GROUP BY d.statut"
            );
            $st->execute($params);

            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
                // Les statuts hérités sont repliés sur leur équivalent actuel :
                // deux lignes MySQL peuvent donc alimenter le même compteur.
                $cle = DossierStatut::normaliser((string)$ligne['statut']);
                $compteurs[$cle] = ($compteurs[$cle] ?? 0) + (int)$ligne['n'];
            }
        } catch (\Throwable $e) {
            error_log('[Dossier] Compteurs échoués : ' . $e->getMessage());
        }

        return $compteurs;
    }

    /**
     * Combien de préinscriptions, combien d'inscriptions — à filtres égaux.
     *
     * Alimente les deux onglets de la liste : l'agent doit savoir ce qu'il ne
     * regarde pas. Le filtre de type est retiré du calcul, sinon l'onglet
     * inactif afficherait toujours zéro.
     *
     * @return array{preinscription:int, inscription:int}
     */
    public function compteursParType(int $etabId, array $filtres = []): array
    {
        unset($filtres['type'], $filtres['statut']);
        [$where, $params] = $this->clausesFiltre($etabId, $filtres);

        $compteurs = [self::TYPE_PREINSCRIPTION => 0, self::TYPE_INSCRIPTION => 0];

        try {
            $st = $this->db->prepare(
                'SELECT ' . self::SQL_EST_INSCRIPTION . " AS est_inscription, COUNT(*) AS n
                 FROM dossiers_inscription d
                 WHERE {$where}
                 GROUP BY est_inscription"
            );
            $st->execute($params);

            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
                $cle = (int)$ligne['est_inscription'] === 1
                    ? self::TYPE_INSCRIPTION
                    : self::TYPE_PREINSCRIPTION;
                $compteurs[$cle] += (int)$ligne['n'];
            }
        } catch (\Throwable $e) {
            error_log('[Dossier] Compteurs par type échoués : ' . $e->getMessage());
        }

        return $compteurs;
    }

    /**
     * Filières distinctes réellement présentes dans les dossiers — alimente le
     * menu de filtre sans proposer d'entrée qui ne ramènerait rien.
     *
     * @return list<string>
     */
    public function filieresPresentes(int $etabId): array
    {
        try {
            $st = $this->db->prepare(
                'SELECT DISTINCT filiere_visee FROM dossiers_inscription
                 WHERE etablissement_id = ? AND filiere_visee IS NOT NULL AND filiere_visee <> \'\'
                 ORDER BY filiere_visee'
            );
            $st->execute([$etabId]);

            return $st->fetchAll(PDO::FETCH_COLUMN, 0);
        } catch (\Throwable $e) {
            error_log('[Dossier] Filières illisibles : ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Construit le WHERE commun à la liste, au comptage et aux compteurs :
     * une seule définition des filtres, donc pas de dérive entre les trois.
     *
     * @return array{0:string, 1:array<string,mixed>}
     */
    private function clausesFiltre(int $etabId, array $filtres): array
    {
        // Le cloisonnement par établissement est en tête et n'est jamais
        // optionnel : c'est la barrière du multi-tenant (spec §15.1).
        $where  = ['d.etablissement_id = :etab'];
        $params = [':etab' => $etabId];

        $statut = (string)($filtres['statut'] ?? '');
        if ($statut !== '' && DossierStatut::existe($statut)) {
            // Un dossier hérité ('accepte') doit ressortir sous son statut
            // actuel ('valide_50') : on interroge donc les deux écritures.
            $equivalents = self::equivalentsStatut($statut);
            $marques     = [];

            foreach ($equivalents as $i => $valeur) {
                $marque          = ':st' . $i;
                $marques[]       = $marque;
                $params[$marque] = $valeur;
            }
            $where[] = 'd.statut IN (' . implode(', ', $marques) . ')';
        }

        // Nature du dossier : une préinscription et une inscription ne suivent
        // pas le même parcours et ne se traitent pas sur le même écran. Le
        // critère est celui du modèle (self::SQL_EST_INSCRIPTION), jamais une
        // règle réécrite ici.
        $type = (string)($filtres['type'] ?? '');
        if ($type === self::TYPE_INSCRIPTION) {
            $where[] = self::SQL_EST_INSCRIPTION;
        } elseif ($type === self::TYPE_PREINSCRIPTION) {
            $where[] = 'NOT ' . self::SQL_EST_INSCRIPTION;
        }

        if (!empty($filtres['filiere'])) {
            $where[]            = 'd.filiere_visee = :filiere';
            $params[':filiere'] = (string)$filtres['filiere'];
        }

        $bourse = (string)($filtres['statut_bourse'] ?? '');
        if ($bourse !== '') {
            if ($bourse === 'non_renseigne') {
                $where[] = 'd.statut_bourse IS NULL';
            } elseif (\Services\Orientation\SituationBourse::estValide($bourse)) {
                $where[]           = 'd.statut_bourse = :bourse';
                $params[':bourse'] = $bourse;
            }
        }

        if (!empty($filtres['du'])) {
            $where[]       = 'COALESCE(d.soumis_le, d.date_creation) >= :du';
            $params[':du'] = $filtres['du'] . ' 00:00:00';
        }
        if (!empty($filtres['au'])) {
            $where[]       = 'COALESCE(d.soumis_le, d.date_creation) <= :au';
            $params[':au'] = $filtres['au'] . ' 23:59:59';
        }

        $q = trim((string)($filtres['q'] ?? ''));
        if ($q !== '') {
            // EMULATE_PREPARES=false interdit de réutiliser un même placeholder.
            $where[]      = '(d.nom_complet LIKE :q1 OR d.reference LIKE :q2 OR d.email LIKE :q3)';
            $motif        = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
            $params[':q1'] = $motif;
            $params[':q2'] = $motif;
            $params[':q3'] = $motif;
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * Écritures possibles d'un même état : la valeur actuelle, plus l'ancienne
     * si le dossier n'a pas encore été touché depuis la migration.
     *
     * @return list<string>
     */
    private static function equivalentsStatut(string $statut): array
    {
        $herites = [
            DossierStatut::SOUMIS     => ['paye'],
            DossierStatut::VALIDE_50  => ['accepte'],
            DossierStatut::VALIDE_100 => ['inscrit'],
            DossierStatut::REJETE     => ['refuse'],
        ];

        return array_merge([$statut], $herites[$statut] ?? []);
    }

    public function findByEtab(int $etabId, array $filters = []): array
    {
        $where  = ['d.etablissement_id = :etab'];
        $params = [':etab' => $etabId];

        if (!empty($filters['statut'])) {
            $where[]           = 'd.statut = :statut';
            $params[':statut'] = $filters['statut'];
        }
        if (!empty($filters['origine'])) {
            $where[]            = 'd.origine = :origine';
            $params[':origine'] = $filters['origine'];
        }
        if (!empty($filters['q'])) {
            // ATTR_EMULATE_PREPARES=false interdit de réutiliser le même placeholder
            $where[]       = '(d.nom_complet LIKE :q1 OR d.email LIKE :q2)';
            $params[':q1'] = '%' . $filters['q'] . '%';
            $params[':q2'] = '%' . $filters['q'] . '%';
        }

        $sql = 'SELECT d.*,
                       r.numero_recepisse,
                       i.id AS inscription_id,
                       p.statut AS paiement_statut, p.montant AS paiement_montant
                FROM dossiers_inscription d
                LEFT JOIN recepisses r ON r.dossier_id = d.id
                LEFT JOIN inscriptions i ON i.dossier_id = d.id
                LEFT JOIN paiements_orientation p ON p.dossier_id = d.id AND p.statut = "reussi"
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY d.date_creation DESC
                LIMIT 200';

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Bilan statistique d'une période : candidats ayant effectué une
     * préinscription (récépissé délivré) ou une inscription validée entre
     * deux dates incluses. Alimente l'export PDF du tableau de bord.
     *
     * @param string $type 'preinscription' | 'inscription'
     * @param string $du   Date de début (Y-m-d, incluse)
     * @param string $au   Date de fin   (Y-m-d, incluse)
     * @return array<int, array<string, mixed>> nom_complet, email,
     *         indicatif_tel, telephone, filiere_visee, niveau_entree, ville,
     *         origine, date_evenement — triés chronologiquement
     */
    public function bilanPeriode(int $etabId, string $type, string $du, string $au): array
    {
        if ($type === 'inscription') {
            $sql = 'SELECT d.nom_complet, d.email, d.indicatif_tel, d.telephone,
                           d.filiere_visee, d.niveau_entree, d.ville, d.origine,
                           i.date_inscription AS date_evenement
                    FROM inscriptions i
                    JOIN dossiers_inscription d ON d.id = i.dossier_id
                    WHERE i.etablissement_id = :etab
                      AND i.date_inscription >= :du
                      AND i.date_inscription < DATE_ADD(:au, INTERVAL 1 DAY)
                    ORDER BY i.date_inscription ASC
                    LIMIT 5000';
        } else {
            $sql = 'SELECT d.nom_complet, d.email, d.indicatif_tel, d.telephone,
                           d.filiere_visee, d.niveau_entree, d.ville, d.origine,
                           p.date_preinscription AS date_evenement
                    FROM preinscriptions p
                    JOIN dossiers_inscription d ON d.id = p.dossier_id
                    WHERE d.etablissement_id = :etab
                      AND p.date_preinscription >= :du
                      AND p.date_preinscription < DATE_ADD(:au, INTERVAL 1 DAY)
                    ORDER BY p.date_preinscription ASC
                    LIMIT 5000';
        }

        $st = $this->db->prepare($sql);
        $st->execute([':etab' => $etabId, ':du' => $du, ':au' => $au]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function statsForEtab(int $etabId): array
    {
        // « Préinscriptions » comptait les dossiers au statut `soumis`. Depuis
        // la machine à états, un dossier d'INSCRIPTION déposé porte lui aussi
        // ce statut en attendant la décision de l'établissement : la carte du
        // tableau de bord annonçait donc des préinscriptions qui n'en étaient
        // pas. Le critère est celui du modèle, et lui seul.
        $st = $this->db->prepare('
            SELECT
              COUNT(*) AS nb_total,
              SUM(CASE WHEN d.statut != "brouillon" AND NOT ' . self::SQL_EST_INSCRIPTION . ' THEN 1 ELSE 0 END) AS nb_preinscriptions,
              SUM(CASE WHEN d.statut = "paye" THEN 1 ELSE 0 END)   AS nb_payes,
              SUM(CASE WHEN d.statut != "brouillon" AND d.origine = "etablissement" THEN 1 ELSE 0 END) AS nb_guichet,
              SUM(CASE WHEN d.statut != "brouillon" AND d.origine = "en_ligne" THEN 1 ELSE 0 END) AS nb_enligne
            FROM dossiers_inscription d
            WHERE d.etablissement_id = ?
        ');
        $st->execute([$etabId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        $st2 = $this->db->prepare('
            SELECT COALESCE(SUM(po.montant),0)
            FROM paiements_orientation po
            WHERE po.etablissement_id = ? AND po.statut = "reussi"
        ');
        $st2->execute([$etabId]);
        $row['revenus'] = (int)$st2->fetchColumn();

        $st3 = $this->db->prepare(
            'SELECT COUNT(*) FROM inscriptions WHERE etablissement_id = ?'
        );
        $st3->execute([$etabId]);
        $row['nb_inscriptions'] = (int)$st3->fetchColumn();

        $st4 = $this->db->prepare(
            'SELECT COUNT(*) FROM visites_etablissement WHERE etablissement_id = ?'
        );
        $st4->execute([$etabId]);
        $row['nb_visites'] = (int)$st4->fetchColumn();

        return $row;
    }

    /**
     * Candidatures déposées (statut ≠ brouillon) groupées par ville du candidat.
     * $etabId = null → toute la plateforme (dashboard super-admin),
     * sinon périmètre d'un établissement (dashboard gestionnaire).
     *
     * @return array<int, array{ville:string, n:int}> triées par volume décroissant
     */
    public function statsVilles(?int $etabId = null): array
    {
        $sql = '
            SELECT COALESCE(NULLIF(TRIM(ville), ""), "Non renseignée") AS ville,
                   COUNT(*) AS n
            FROM dossiers_inscription
            WHERE statut != "brouillon"';
        $params = [];
        if ($etabId !== null) {
            $sql     .= ' AND etablissement_id = ?';
            $params[] = $etabId;
        }
        $sql .= ' GROUP BY COALESCE(NULLIF(TRIM(ville), ""), "Non renseignée")
                  ORDER BY n DESC, ville ASC';

        $st = $this->db->prepare($sql);
        $st->execute($params);

        return array_map(
            static fn(array $r): array => ['ville' => (string)$r['ville'], 'n' => (int)$r['n']],
            $st->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /**
     * Croissance mensuelle : préinscriptions et inscriptions de l'établissement
     * sur les $mois derniers mois (bornes incluses), pour le graphique à deux
     * courbes du tableau de bord. Les mois sans activité sont remplis à 0 côté
     * PHP afin d'obtenir une série continue.
     *
     * @return array<int, array{mois:string, label:string, preinscriptions:int, inscriptions:int}>
     */
    public function croissanceMensuelle(int $etabId, int $mois = 12): array
    {
        $mois = max(2, min(24, $mois));

        // Squelette : un seau par mois, du plus ancien au mois courant.
        $moisFr = ['', 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin',
                   'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
        $buckets = [];
        $cursor  = new \DateTime('first day of this month 00:00:00');
        $cursor->modify('-' . ($mois - 1) . ' months');
        for ($i = 0; $i < $mois; $i++) {
            $key = $cursor->format('Y-m');
            $buckets[$key] = [
                'mois'            => $key,
                'label'          => $moisFr[(int)$cursor->format('n')] . ' ' . $cursor->format('y'),
                'preinscriptions' => 0,
                'inscriptions'    => 0,
            ];
            $cursor->modify('+1 month');
        }
        $depuis = array_key_first($buckets) . '-01';

        // Préinscriptions par mois
        $stPre = $this->db->prepare('
            SELECT DATE_FORMAT(p.date_preinscription, "%Y-%m") AS ym, COUNT(*) AS n
            FROM preinscriptions p
            JOIN dossiers_inscription d ON d.id = p.dossier_id
            WHERE d.etablissement_id = :etab
              AND p.date_preinscription >= :depuis
            GROUP BY ym
        ');
        $stPre->execute([':etab' => $etabId, ':depuis' => $depuis]);
        foreach ($stPre->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if (isset($buckets[$r['ym']])) $buckets[$r['ym']]['preinscriptions'] = (int)$r['n'];
        }

        // Inscriptions par mois
        $stIns = $this->db->prepare('
            SELECT DATE_FORMAT(date_inscription, "%Y-%m") AS ym, COUNT(*) AS n
            FROM inscriptions
            WHERE etablissement_id = :etab
              AND date_inscription >= :depuis
            GROUP BY ym
        ');
        $stIns->execute([':etab' => $etabId, ':depuis' => $depuis]);
        foreach ($stIns->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if (isset($buckets[$r['ym']])) $buckets[$r['ym']]['inscriptions'] = (int)$r['n'];
        }

        return array_values($buckets);
    }

    public function getDocumentsByDossier(int $dossierId): array
    {
        $st = $this->db->prepare('
            SELECT doc.*, pj.libelle AS piece_libelle, pj.obligatoire
            FROM documents_orientation doc
            LEFT JOIN pieces_justificatives_requises pj ON pj.id = doc.piece_requise_id
            WHERE doc.dossier_id = ?
            ORDER BY doc.id ASC
        ');
        $st->execute([$dossierId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Une pièce justificative par son identifiant, avec le dossier auquel elle
     * appartient — l'appelant vérifie ensuite la propriété du dossier avant de
     * servir le fichier (jamais l'inverse).
     */
    public function findDocumentById(int $docId): ?array
    {
        $st = $this->db->prepare('
            SELECT doc.*, d.etablissement_id, d.eleve_id, d.email AS dossier_email
            FROM documents_orientation doc
            JOIN dossiers_inscription d ON d.id = doc.dossier_id
            WHERE doc.id = ?
            LIMIT 1
        ');
        $st->execute([$docId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateStatutByEtab(int $id, int $etabId, string $statut): bool
    {
        $allowed = ['soumis','paye','accepte','inscrit','refuse'];
        if (!in_array($statut, $allowed, true)) return false;
        $this->db->prepare(
            'UPDATE dossiers_inscription SET statut = ? WHERE id = ? AND etablissement_id = ?'
        )->execute([$statut, $id, $etabId]);
        return true;
    }

    public function createGuichet(array $d, int $etabId, int $agentId): int
    {
        $st = $this->db->prepare('
            INSERT INTO dossiers_inscription
              (etablissement_id, filiere_visee, specialite_visee, niveau_entree,
               nom_complet, genre, date_naissance, lieu_naissance, nationalite,
               email, indicatif_tel, telephone, adresse, ville, pays_provenance,
               dernier_etablissement, serie_bac, annee_bac, annee_academique,
               origine, cree_par_user_id, statut)
            VALUES
              (:etab, :filiere, :specialite, :niveau,
               :nom, :genre, :naissance, :lieu, :nationalite,
               :email, :indicatif, :tel, :adresse, :ville, :pays,
               :dernier, :serie_bac, :annee_bac, :annee_academique,
               "etablissement", :agent, :statut)
        ');
        $st->execute([
            ':etab'        => $etabId,
            ':filiere'     => $d['filiere_visee'] ?? null,
            ':specialite'  => $d['specialite_visee'] ?? null,
            ':niveau'      => $d['niveau_entree'] ?? null,
            ':nom'         => $d['nom_complet'],
            ':genre'       => $d['genre'],
            ':naissance'   => $d['date_naissance'],
            ':lieu'        => $d['lieu_naissance'],
            ':nationalite' => $d['nationalite'],
            ':email'       => $d['email'],
            ':indicatif'   => $d['indicatif_tel'] ?? '+241',
            ':tel'         => $d['telephone'],
            ':adresse'     => $d['adresse'] ?? null,
            ':ville'       => $d['ville'] ?? null,
            ':pays'        => $d['pays_provenance'] ?? null,
            ':dernier'     => $d['dernier_etablissement'] ?? null,
            // Série et année du bac : le récépissé et l'attestation les
            // impriment (RecepisseService). Un dossier saisi au guichet sans
            // ces colonnes sortait un document officiel portant « — » là où le
            // même dossier déposé en ligne portait la vraie mention.
            ':serie_bac'   => ($d['serie_bac'] ?? '') !== '' ? $d['serie_bac'] : null,
            ':annee_bac'   => !empty($d['annee_bac']) ? (int)$d['annee_bac'] : null,
            // Année académique : la colonne restait NULL au guichet, alors que
            // le parcours public la renseignait depuis toujours. Un dossier
            // sans année n'appartient à aucune campagne — il échappait aux
            // bilans, aux filtres par année, et à la règle « une seule
            // inscription par établissement et par année » (UniciteInscription).
            ':annee_academique' => $d['annee_academique'] ?? \Core\AnneeUniversitaire::code(),
            ':agent'       => $agentId,
            ':statut'      => 'soumis',
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function createPaiementHorsligne(int $dossierId, int $etabId, int $montant, string $mode, int $agentId): int
    {
        $st = $this->db->prepare('
            INSERT INTO paiements_orientation
              (dossier_id, etablissement_id, montant, mode, statut, enregistre_par_user_id, date_confirmation)
            VALUES
              (:dossier, :etab, :montant, :mode, "reussi", :agent, NOW())
        ');
        $st->execute([
            ':dossier'  => $dossierId,
            ':etab'     => $etabId,
            ':montant'  => $montant,
            ':mode'     => $mode,
            ':agent'    => $agentId,
        ]);
        return (int)$this->db->lastInsertId();
    }

    // ── Espace élève ──────────────────────────────────────────────────────────

    /**
     * Rattache au compte $userId tous les dossiers créés avec son email
     * avant l'existence du compte (préinscriptions anonymes).
     * Idempotent — appelé à la création de compte et à chaque connexion.
     */
    public function claimByEmail(int $userId, string $email): int
    {
        if ($userId <= 0 || $email === '') {
            return 0;
        }
        $st = $this->db->prepare('
            UPDATE dossiers_inscription
            SET eleve_id = :uid
            WHERE email = :email AND (eleve_id IS NULL OR eleve_id = 0)
        ');
        $st->execute([':uid' => $userId, ':email' => $email]);
        return $st->rowCount();
    }

    public function countByUserContext(int $userId, string $email): array
    {
        $st = $this->db->prepare('
            SELECT
              COUNT(*) AS total,
              SUM(CASE WHEN statut = "soumis" THEN 1 ELSE 0 END)            AS en_attente,
              SUM(CASE WHEN statut IN ("paye","accepte") THEN 1 ELSE 0 END) AS confirmes,
              SUM(CASE WHEN statut = "refuse" THEN 1 ELSE 0 END)            AS refuses
            FROM dossiers_inscription
            WHERE (eleve_id = :uid OR email = :email) AND statut != "brouillon"
        ');
        $st->execute([':uid' => $userId, ':email' => $email]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'total'      => (int)($row['total'] ?? 0),
            'en_attente' => (int)($row['en_attente'] ?? 0),
            'confirmes'  => (int)($row['confirmes'] ?? 0),
            'refuses'    => (int)($row['refuses'] ?? 0),
        ];
    }

    public function findByUserContext(int $userId, string $email): array
    {
        $st = $this->db->prepare('
            SELECT d.*,
                   e.nom AS etab_nom, e.slug AS etab_slug, e.couleur_principale, e.logo_cle,
                   r.numero_recepisse, r.cle_pdf AS recepisse_cle, r.date_emission AS recepisse_date,
                   rec.numero_recu, rec.cle_pdf AS recu_cle,
                   i.id AS inscription_id,
                   a.numero_attestation, a.cle_pdf AS attestation_cle, a.date_emission AS attestation_date,
                   p.statut AS paiement_statut, p.montant AS paiement_montant, p.date_confirmation AS paiement_date
            FROM dossiers_inscription d
            JOIN etablissements e ON e.id = d.etablissement_id
            LEFT JOIN recepisses r ON r.dossier_id = d.id
            LEFT JOIN inscriptions i ON i.dossier_id = d.id
            LEFT JOIN attestations_inscription a ON a.dossier_id = d.id
            LEFT JOIN paiements_orientation p ON p.dossier_id = d.id AND p.statut = "reussi"
            LEFT JOIN recus_orientation rec ON rec.paiement_id = p.id
            WHERE (d.eleve_id = :uid OR d.email = :email)
            ORDER BY d.date_creation DESC
        ');
        $st->execute([':uid' => $userId, ':email' => $email]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Tous les paiements (tous statuts) des dossiers de l'utilisateur,
     * avec le reçu éventuel — pour les onglets Paiements / Reçus de l'espace élève.
     */
    public function findPaiementsByUserContext(int $userId, string $email): array
    {
        $st = $this->db->prepare('
            SELECT p.*,
                   d.id AS dossier_id, d.filiere_visee, d.niveau_entree, d.statut AS dossier_statut,
                   e.nom AS etab_nom, e.slug AS etab_slug, e.couleur_principale,
                   i.id AS inscription_id,
                   rec.numero_recu, rec.cle_pdf AS recu_cle, rec.date_emission AS recu_date
            FROM paiements_orientation p
            JOIN dossiers_inscription d ON d.id = p.dossier_id
            JOIN etablissements e ON e.id = d.etablissement_id
            LEFT JOIN inscriptions i ON i.dossier_id = d.id
            LEFT JOIN recus_orientation rec ON rec.paiement_id = p.id
            WHERE (d.eleve_id = :uid OR d.email = :email)
            ORDER BY p.date_paiement DESC
        ');
        $st->execute([':uid' => $userId, ':email' => $email]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Toutes les pièces déposées sur les dossiers de l'utilisateur,
     * groupables par dossier — pour l'onglet Documents de l'espace élève.
     */
    public function findDocumentsByUserContext(int $userId, string $email): array
    {
        $st = $this->db->prepare('
            SELECT doc.*,
                   pj.libelle AS piece_libelle, pj.obligatoire,
                   d.id AS dossier_id, d.statut AS dossier_statut,
                   e.nom AS etab_nom, e.couleur_principale
            FROM documents_orientation doc
            JOIN dossiers_inscription d ON d.id = doc.dossier_id
            JOIN etablissements e ON e.id = d.etablissement_id
            LEFT JOIN pieces_justificatives_requises pj ON pj.id = doc.piece_requise_id
            WHERE (d.eleve_id = :uid OR d.email = :email)
            ORDER BY doc.date_depot DESC, doc.id DESC
        ');
        $st->execute([':uid' => $userId, ':email' => $email]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByIdWithOwnership(int $id, int $userId, string $email): ?array
    {
        $st = $this->db->prepare('
            SELECT d.*,
                   e.nom AS etab_nom, e.slug AS etab_slug, e.couleur_principale, e.logo_cle,
                   r.numero_recepisse, r.cle_pdf AS recepisse_cle,
                   rec.numero_recu, rec.cle_pdf AS recu_cle,
                   i.id AS inscription_id,
                   a.numero_attestation, a.cle_pdf AS attestation_cle,
                   p.statut AS paiement_statut, p.montant AS paiement_montant,
                   f.intitule AS formation_intitule
            FROM dossiers_inscription d
            JOIN etablissements e ON e.id = d.etablissement_id
            LEFT JOIN recepisses r ON r.dossier_id = d.id
            LEFT JOIN inscriptions i ON i.dossier_id = d.id
            LEFT JOIN attestations_inscription a ON a.dossier_id = d.id
            LEFT JOIN paiements_orientation p ON p.dossier_id = d.id AND p.statut = "reussi"
            LEFT JOIN recus_orientation rec ON rec.paiement_id = p.id
            LEFT JOIN formations f ON f.id = d.formation_id
            WHERE d.id = :id AND (d.eleve_id = :uid OR d.email = :email)
            LIMIT 1
        ');
        $st->execute([':id' => $id, ':uid' => $userId, ':email' => $email]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
