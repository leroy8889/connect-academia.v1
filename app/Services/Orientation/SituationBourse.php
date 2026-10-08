<?php
declare(strict_types=1);

namespace Services\Orientation;

use Core\Database;
use PDO;

/**
 * Situation de bourse déclarée par l'étudiant (spec §5.2 et §9.4).
 *
 * DEUX SITUATIONS, PAS TROIS
 * --------------------------
 * La plateforme a longtemps proposé un troisième choix — « Mon dossier est en
 * cours de traitement à l'ANBG ». Les établissements partenaires l'ont fait
 * retirer : il décrivait une attente, pas une situation, et personne ne savait
 * quoi en faire au guichet. Un candidat dont la demande de bourse n'a pas
 * abouti est, aujourd'hui, non boursier ; le jour où elle aboutit, il bascule
 * lui-même en « boursier » depuis son espace, à tout moment, y compris après
 * l'inscription définitive. La bascule est tracée et la commission est
 * réévaluée (cf. `enregistrer()`).
 *
 * Le retrait n'a AUCUN effet financier : `dossier_en_cours` était tarifé et
 * commissionné exactement comme `non_boursier` (TarifCycle::baseCommission ne
 * distingue que le boursier confirmé). Les dossiers qui portaient l'ancienne
 * valeur ont été basculés en `non_boursier` par
 * Database/migration_suppression_dossier_en_cours.sql.
 *
 * Trois principes non négociables :
 *
 *  1. C'est une DÉCLARATION DE L'ÉTUDIANT. L'établissement ne la fixe jamais
 *     seul — c'est le conflit d'intérêts que la spec §18 interdit. Un
 *     établissement ou un administrateur peut la corriger, mais la correction
 *     est tracée avec son identité.
 *
 *  2. L'HORODATAGE VIENT DU SERVEUR. `bourse_declaree_le` est posé par NOW(),
 *     jamais par une valeur transmise dans le formulaire.
 *
 *  3. ELLE NE TOUCHE JAMAIS AU STATUT DU DOSSIER. Déclarer une bourse
 *     n'accepte pas une candidature, ne la refuse pas, ne la fait pas
 *     progresser. L'instruction du dossier et la situation de bourse sont deux
 *     chaînes séparées.
 *
 * ── Ce qui a changé : l'effet financier ──────────────────────────────────────
 *
 * Cette classe posait un quatrième principe — « aucun effet financier » — qui
 * n'est PLUS vrai, et il faut le dire clairement plutôt que de laisser un
 * commentaire mentir. La direction a arbitré une règle de facturation qui
 * dépend de la situation déclarée (cf. TarifCycle::baseCommission) :
 *
 *   · boursier confirmé → commission sur les frais d'inscription (réglable
 *     par accord, cf. `etablissements.base_commission_boursier`) ;
 *   · non boursier      → commission sur la scolarité.
 *
 * Le risque que l'ancien principe écartait — déclarer une bourse pour alléger
 * la facture — est désormais couvert autrement, par trois verrous :
 *
 *   a. se déclarer boursier EXIGE l'attestation ANBG : `pieceObligatoire()` ;
 *   b. chaque changement écrit une ligne dans `historique_statut_bourse` avec
 *      son auteur, son rôle et son horodatage serveur : rien n'est réécrit en
 *      silence ;
 *   c. la révision d'une commission déjà encaissée est REFUSÉE — elle est
 *      signalée au super administrateur, seul habilité à trancher
 *      (CommissionDossier::reevaluerBase).
 *
 * Toute déclaration écrit une ligne dans `historique_statut_bourse` : l'état
 * courant est dans le dossier, la vérité chronologique est dans l'historique.
 */
final class SituationBourse
{
    public const NON_BOURSIER = 'non_boursier';
    public const BOURSIER     = 'boursier';

    /**
     * Ancienne troisième situation, RETIRÉE du parcours (voir l'en-tête).
     *
     * La constante survit pour une seule raison : `historique_statut_bourse`
     * est en ajout seul et ses colonnes sont des VARCHAR — les lignes écrites
     * avant le retrait portent encore cette valeur, et l'espace candidat les
     * réaffiche. Elle n'est JAMAIS proposable, jamais valide en saisie, jamais
     * écrite : `valeurs()`, `options()` et `estValide()` l'ignorent.
     *
     * @deprecated Affichage des lignes d'historique uniquement.
     */
    public const DOSSIER_EN_COURS = 'dossier_en_cours';

    /** Auteurs possibles d'une déclaration (colonne role_modificateur). */
    public const PAR_ETUDIANT      = 'etudiant';
    public const PAR_ETABLISSEMENT = 'etablissement';
    public const PAR_ADMIN         = 'admin';

    /** Libellés affichés dans le formulaire (spec §5.2). */
    private const LIBELLES = [
        self::NON_BOURSIER => 'Je ne suis pas boursier',
        self::BOURSIER     => 'Je suis boursier',
    ];

    /** Libellés courts pour les tableaux et les filtres du back-office. */
    private const LIBELLES_COURTS = [
        self::NON_BOURSIER => 'Non boursier',
        self::BOURSIER     => 'Boursier',
    ];

    /**
     * Libellés « en toutes lettres » des documents officiels (spec §6.3).
     * Formulés à la troisième personne : le récépissé parle du candidat, il ne
     * reprend pas la phrase que le candidat a cochée.
     */
    private const LIBELLES_DOCUMENT = [
        self::NON_BOURSIER => 'Non boursier',
        self::BOURSIER     => 'Boursier',
    ];

    /**
     * Libellés des valeurs RETIRÉES, pour la seule relecture de l'existant.
     *
     * Sans ce repli, une ligne d'historique antérieure au retrait s'affichait
     * « Non renseignée » : le candidat aurait vu son propre parcours réécrit.
     * Et tant que la migration n'a pas été rejouée sur un environnement, un
     * dossier resté en `dossier_en_cours` continue de s'y lire correctement
     * au lieu de sortir un document officiel amputé de sa mention.
     */
    private const LIBELLES_RETIRES = [
        self::DOSSIER_EN_COURS => 'Dossier ANBG en cours (situation retirée)',
    ];

    /** Aide affichée sous chaque option. */
    private const AIDES = [
        self::NON_BOURSIER => 'Vous financez votre scolarité par vos propres moyens.',
        self::BOURSIER     => 'Vous bénéficiez déjà d’une bourse de l’ANBG.',
    ];

    /** Code interne de la pièce exigée par chaque situation, s'il y en a une. */
    private const PIECES = [
        self::BOURSIER => 'attestation_bourse',
    ];

    /**
     * Codes de pièces conditionnelles hérités : plus aucune situation ne les
     * déclenche, mais des documents déjà déposés les portent. Les reconnaître
     * évite qu'une pièce `preuve_depot_anbg` restée en base ne soit reclassée
     * « pièce du tronc commun » et réclamée à tout le monde.
     */
    private const PIECES_RETIREES = ['preuve_depot_anbg'];

    /** Texte explicatif du bloc, repris tel quel de la spec §5.2. */
    public const NOTE_EXPLICATIVE =
        'Cette information nous permet d’adapter votre dossier. Elle n’a aucune '
        . 'incidence sur l’acceptation de votre candidature. Vous pourrez la modifier '
        . 'à tout moment depuis votre espace si votre situation évolue.';

    /** @return list<string> */
    public static function valeurs(): array
    {
        return array_keys(self::LIBELLES);
    }

    public static function estValide(?string $statut): bool
    {
        return $statut !== null && isset(self::LIBELLES[$statut]);
    }

    public static function libelle(?string $statut): string
    {
        return self::LIBELLES[(string)$statut] ?? '';
    }

    /**
     * Libellé court. Une valeur RETIRÉE garde le sien : ces libellés sortent
     * dans l'historique du candidat et dans les tableaux de traçabilité, où
     * afficher « Non renseignée » à la place d'une déclaration réellement
     * faite serait un mensonge.
     */
    public static function libelleCourt(?string $statut): string
    {
        $statut = (string)$statut;

        return self::LIBELLES_COURTS[$statut]
            ?? self::LIBELLES_RETIRES[$statut]
            ?? 'Non renseignée';
    }

    public static function aide(?string $statut): string
    {
        return self::AIDES[(string)$statut] ?? '';
    }

    /**
     * Options du bloc radio, prêtes pour la vue.
     *
     * @return list<array{valeur:string,libelle:string,aide:string,piece:?string}>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::LIBELLES as $valeur => $libelle) {
            $options[] = [
                'valeur'  => $valeur,
                'libelle' => $libelle,
                'aide'    => self::AIDES[$valeur],
                'piece'   => self::PIECES[$valeur] ?? null,
            ];
        }

        return $options;
    }

    /** Code interne de la pièce conditionnelle, ou null. */
    public static function pieceAssociee(?string $statut): ?string
    {
        return self::PIECES[(string)$statut] ?? null;
    }

    /**
     * Ce code interne désigne-t-il une pièce liée à une situation de bourse ?
     *
     * Sert à distinguer « pièce du tronc commun » de « pièce conditionnelle » :
     * la seconde n'est jamais exigée d'un candidat dont la situation ne la
     * déclenche pas, quel que soit le drapeau `obligatoire` du référentiel.
     */
    public static function estPieceConditionnelle(?string $code): bool
    {
        if ($code === null) {
            return false;
        }

        return in_array($code, self::PIECES, true)
            || in_array($code, self::PIECES_RETIREES, true);
    }

    /**
     * La pièce conditionnelle est-elle obligatoire ? (spec §5.3)
     * Seul le boursier confirmé doit une pièce : son attestation ANBG.
     */
    public static function pieceObligatoire(?string $statut): bool
    {
        return (string)$statut === self::BOURSIER;
    }

    /**
     * Situation « en toutes lettres » pour le récépissé et l'attestation
     * (spec §6.3) : « Boursier — déclarée le 18/08/2026, attestation n° … ».
     *
     * Une valeur RETIRÉE reste imprimable : sur un environnement où la
     * migration n'a pas encore été rejouée, un document officiel doit dire ce
     * que le dossier porte, pas « Non renseignée ».
     */
    public static function phrase(array $dossier): string
    {
        $statut  = (string)($dossier['statut_bourse'] ?? '');
        $libelle = self::LIBELLES_DOCUMENT[$statut] ?? self::LIBELLES_RETIRES[$statut] ?? null;

        if ($libelle === null) {
            return 'Non renseignée';
        }

        $details = [];

        if (!empty($dossier['bourse_declaree_le'])) {
            $details[] = 'déclarée le ' . self::formaterDate((string)$dossier['bourse_declaree_le']);
        }
        if ($statut === self::BOURSIER && !empty($dossier['numero_attestation_anbg'])) {
            $details[] = 'attestation n° ' . $dossier['numero_attestation_anbg'];
        }

        return $libelle . ($details === [] ? '' : ' — ' . implode(', ', $details));
    }

    // ── Validation ───────────────────────────────────────────────────────────

    /**
     * Valide une saisie de formulaire. Le client masque et affiche les champs
     * conditionnels en JavaScript ; ici on refait TOUT, sans lui faire confiance
     * (spec §5.2).
     *
     * @param array<string,mixed> $post
     * @return array{
     *   erreurs: array<string,string>,
     *   valeurs: array{statut_bourse:?string,numero_attestation_anbg:?string,date_depot_anbg:?string}
     * }
     */
    public static function valider(array $post): array
    {
        $erreurs = [];
        $statut  = is_string($post['statut_bourse'] ?? null) ? trim($post['statut_bourse']) : '';

        if ($statut === '') {
            $erreurs['statut_bourse'] = 'Merci d’indiquer votre situation de bourse.';

            return ['erreurs' => $erreurs, 'valeurs' => self::valeursVides()];
        }

        if (!self::estValide($statut)) {
            // Valeur forgée — ou l'ancienne « dossier en cours », retirée du
            // parcours : on ne la renvoie pas à l'utilisateur pour ne pas
            // réfléchir une chaîne arbitraire dans le formulaire.
            $erreurs['statut_bourse'] = 'Situation de bourse invalide.';

            return ['erreurs' => $erreurs, 'valeurs' => self::valeursVides()];
        }

        $numero = null;

        if ($statut === self::BOURSIER) {
            // Numéro d'attestation ANBG : facultatif (spec §5.2), mais borné et
            // nettoyé — il finit imprimé sur un document officiel.
            $brut = is_string($post['numero_attestation_anbg'] ?? null)
                ? trim($post['numero_attestation_anbg'])
                : '';

            if ($brut !== '') {
                $propre = preg_replace('/[^A-Za-z0-9\/\-\. ]/', '', $brut) ?? '';
                $propre = trim(preg_replace('/\s+/', ' ', $propre) ?? '');

                if ($propre === '') {
                    $erreurs['numero_attestation_anbg'] = 'Numéro d’attestation invalide.';
                } elseif (mb_strlen($propre) > 50) {
                    $erreurs['numero_attestation_anbg'] = 'Numéro d’attestation trop long (50 caractères maximum).';
                } else {
                    $numero = $propre;
                }
            }
        }

        // `date_depot_anbg` n'est plus jamais lue : elle n'appartenait qu'à la
        // situation retirée. La clé reste dans le contrat de retour — les
        // appelants la passent à `enregistrer()`, qui écrit alors NULL — pour
        // ne pas casser une signature utilisée par cinq points d'entrée.
        return [
            'erreurs' => $erreurs,
            'valeurs' => [
                'statut_bourse'           => $statut,
                'numero_attestation_anbg' => $numero,
                'date_depot_anbg'         => null,
            ],
        ];
    }

    // ── Persistance ──────────────────────────────────────────────────────────

    /**
     * Enregistre la déclaration sur le dossier et trace l'historique.
     *
     * Ne modifie JAMAIS `statut`, `progression`, ni quoi que ce soit de
     * Le statut du dossier n'est jamais touché. La commission, elle, est
     * réévaluée en fin de méthode. Idempotente : redéclarer la même situation
     * avec les mêmes champs n'écrit pas de ligne d'historique — et ne
     * déclenche donc aucune révision inutile.
     *
     * @param array{statut_bourse:string,numero_attestation_anbg?:?string,date_depot_anbg?:?string} $valeurs
     * @return bool true si quelque chose a changé.
     */
    public static function enregistrer(
        int $dossierId,
        array $valeurs,
        string $role = self::PAR_ETUDIANT,
        ?int $acteurId = null,
        ?string $justificatifCle = null,
        ?string $commentaire = null,
        ?PDO $db = null
    ): bool {
        $statut = (string)($valeurs['statut_bourse'] ?? '');
        if (!self::estValide($statut)) {
            return false;
        }
        if (!in_array($role, [self::PAR_ETUDIANT, self::PAR_ETABLISSEMENT, self::PAR_ADMIN], true)) {
            $role = self::PAR_ETUDIANT;
        }

        try {
            $db ??= Database::getInstance()->getConnection();

            $st = $db->prepare(
                'SELECT id, eleve_id, etablissement_id, reference, statut_bourse,
                        numero_attestation_anbg, date_depot_anbg
                 FROM dossiers_inscription WHERE id = ? LIMIT 1'
            );
            $st->execute([$dossierId]);
            $dossier = $st->fetch(PDO::FETCH_ASSOC);

            if ($dossier === false) {
                return false;
            }

            $ancien = $dossier['statut_bourse'] !== null ? (string)$dossier['statut_bourse'] : null;
            $numero = $statut === self::BOURSIER ? ($valeurs['numero_attestation_anbg'] ?? null) : null;

            // La date de dépôt ANBG n'appartenait qu'à la situation retirée :
            // toute nouvelle déclaration remet la colonne à NULL. Elle entre
            // dans la comparaison d'idempotence pour qu'un dossier hérité qui
            // en porte encore une soit bien nettoyé au premier changement.
            $inchange = $ancien === $statut
                && (string)($dossier['numero_attestation_anbg'] ?? '') === (string)($numero ?? '')
                && (string)($dossier['date_depot_anbg'] ?? '') === '';

            if ($inchange) {
                return false;
            }

            // bourse_declaree_le = NOW() : l'horodatage est posé par le serveur,
            // jamais transmis par le formulaire (spec §5.2).
            $db->prepare(
                'UPDATE dossiers_inscription
                 SET statut_bourse           = :statut,
                     numero_attestation_anbg = :numero,
                     date_depot_anbg         = NULL,
                     bourse_declaree_le      = NOW(),
                     bourse_declaree_par     = :role
                 WHERE id = :id'
            )->execute([
                ':statut' => $statut,
                ':numero' => $numero,
                ':role'   => $role,
                ':id'     => $dossierId,
            ]);

            $db->prepare(
                'INSERT INTO historique_statut_bourse
                   (dossier_id, ancien_statut, nouveau_statut, justificatif_cle,
                    modifie_par, role_modificateur, commentaire, modifie_le)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
            )->execute([
                $dossierId,
                $ancien,
                $statut,
                $justificatifCle,
                $acteurId,
                $role,
                $commentaire,
            ]);

            Journal::dossier(
                Journal::STATUT_BOURSE_MODIFIE,
                $dossier,
                array_filter([
                    'ancien_statut'  => $ancien,
                    'nouveau_statut' => $statut,
                    'numero_anbg'    => $numero,
                    'declare_par'    => $role,
                ], static fn($v): bool => $v !== null),
                ['acteur_role' => $role, 'acteur_id' => $acteurId]
            );

            // ── Effet financier, désormais assumé ────────────────────────────
            // La base de la commission dépend de la situation de bourse. Un
            // non boursier qui devient « boursier » — parce que sa demande
            // ANBG a fini par aboutir — doit donc voir sa commission passer de
            // la scolarité aux frais d'inscription.
            //
            // Appelé ICI, et nulle part ailleurs : `enregistrer()` est le seul
            // point d'écriture de `statut_bourse`. Le brancher sur les
            // contrôleurs aurait laissé passer toute nouvelle porte d'entrée.
            //
            // Ne s'applique qu'aux dossiers déjà validés à 100 % — pour tous
            // les autres, il n'existe aucune commission et l'appel est un
            // simple SELECT qui ne trouve rien. La méthode refuse d'elle-même
            // de toucher à une commission encaissée : elle la signale au super
            // administrateur au lieu de la réécrire.
            CommissionDossier::reevaluerBase($dossierId, $acteurId, $role, $db);

            return true;
        } catch (\Throwable $e) {
            error_log('[SituationBourse] Enregistrement échoué #' . $dossierId . ' : ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Historique des changements pour un dossier, du plus ancien au plus récent
     * (zone identité du back-office, spec §8.2).
     *
     * @return list<array<string,mixed>>
     */
    public static function historique(int $dossierId): array
    {
        try {
            $st = Database::getInstance()->getConnection()->prepare(
                'SELECT h.*, u.nom AS acteur_nom, u.prenom AS acteur_prenom
                 FROM historique_statut_bourse h
                 LEFT JOIN users u ON u.id = h.modifie_par
                 WHERE h.dossier_id = ?
                 ORDER BY h.modifie_le ASC, h.id ASC'
            );
            $st->execute([$dossierId]);
            $lignes = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[SituationBourse] Historique illisible #' . $dossierId . ' : ' . $e->getMessage());

            return [];
        }

        foreach ($lignes as &$ligne) {
            $ligne['ancien_libelle']   = self::libelleCourt($ligne['ancien_statut'] ?? null);
            $ligne['nouveau_libelle']  = self::libelleCourt($ligne['nouveau_statut'] ?? null);
        }

        return $lignes;
    }

    // ── Interne ──────────────────────────────────────────────────────────────

    /** @return array{statut_bourse:null,numero_attestation_anbg:null,date_depot_anbg:null} */
    private static function valeursVides(): array
    {
        return [
            'statut_bourse'           => null,
            'numero_attestation_anbg' => null,
            'date_depot_anbg'         => null,
        ];
    }

    private static function formaterDate(string $valeur): string
    {
        try {
            return (new \DateTimeImmutable($valeur))->format('d/m/Y');
        } catch (\Throwable) {
            return $valeur;
        }
    }
}
