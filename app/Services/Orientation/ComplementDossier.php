<?php
declare(strict_types=1);

namespace Services\Orientation;

use Core\Database;
use Models\Orientation\Dossier;
use Models\Orientation\DossierStatut;
use Models\Orientation\NotificationOrientation;
use PDO;

/**
 * Complément de dossier par le candidat : remplacer une pièce refusée,
 * déposer une pièce manquante, renvoyer le dossier à l'analyse (spec §9.3).
 *
 * ── Le problème que ce service résout ────────────────────────────────────────
 *
 * L'établissement pouvait refuser une pièce et le dire au candidat… sans lui
 * donner le moindre moyen de la corriger. Le formulaire d'inscription n'est
 * accessible qu'en brouillon, et l'espace élève n'affichait les pièces qu'en
 * lecture. Résultat : une notification « votre acte de naissance est illisible »
 * qui ne mène nulle part, et un dossier bloqué jusqu'à un appel téléphonique.
 *
 * ── Les partis pris ──────────────────────────────────────────────────────────
 *
 *  1. **On ne remplace pas, on SUPERPOSE.** La pièce refusée n'est jamais
 *     effacée : elle reste en base, chaînée à sa remplaçante par
 *     `remplace_document_id`. Un refus contesté doit rester démontrable, des
 *     deux côtés. Seul l'affichage ne montre que la dernière version.
 *
 *  2. **Le candidat ne décide pas de l'état de son dossier.** Il dépose ; c'est
 *     l'état RÉEL des pièces qui décide du renvoi à l'analyse. Un bouton
 *     « renvoyer » cliquable avec une pièce encore refusée renverrait un dossier
 *     que l'établissement refuserait à nouveau — deux allers-retours pour rien.
 *
 *  3. **Le renvoi est AUTOMATIQUE dès qu'il n'y a plus rien à corriger.** Le
 *     candidat qui vient de téléverser la dernière pièce attendue a fini son
 *     travail : lui demander un clic de plus, c'est fabriquer des dossiers
 *     oubliés à mi-chemin.
 *
 *  4. **Rien après la validation à 50 %… sauf refus explicite.** Un dossier jugé
 *     recevable ne se rouvre pas au dépôt libre. Mais si le guichet a refusé une
 *     pièce précise après coup, le candidat doit pouvoir la corriger : c'est
 *     exactement le cas où il n'a aucun autre recours.
 */
final class ComplementDossier
{
    /** États dans lesquels le candidat peut compléter son dossier librement. */
    private const ETATS_OUVERTS = [
        DossierStatut::SOUMIS,
        DossierStatut::PIECE_MANQUANTE,
    ];

    /**
     * États où le dossier est CLOS : même une pièce refusée n'y rouvre pas le
     * dépôt. Rien de ce que le candidat téléverserait ne serait relu — lui
     * ouvrir le formulaire lui ferait perdre son temps, et donnerait au dossier
     * une apparence d'activité qu'il n'a plus.
     *
     * `brouillon` en fait partie pour une autre raison : ce dossier-là se
     * complète dans le formulaire d'inscription, pas dans l'espace de suivi.
     */
    private const ETATS_CLOS = [
        DossierStatut::BROUILLON,
        DossierStatut::REJETE,
        DossierStatut::EXPIRE,
        DossierStatut::ANNULE,
    ];

    /**
     * Le candidat peut-il agir sur les pièces de ce dossier ?
     *
     * Deux portes distinctes :
     *
     *  · les états OUVERTS (soumis, pièce manquante) : dépôt libre, c'est le
     *    parcours normal de constitution du dossier ;
     *  · TOUT autre état, dès lors que l'établissement a refusé une pièce sans
     *    lui donner de remplaçante. Un refus est une DEMANDE adressée au
     *    candidat : la lui adresser sans lui laisser de bouton pour y répondre
     *    n'a jamais de sens, quel que soit l'avancement du dossier.
     *
     * Le second cas ne couvrait que VALIDE_50. Une pièce refusée après une
     * validation à 100 % — le cas d'un document jugé non conforme au moment de
     * l'archivage — laissait donc le candidat devant une carte « Refusée » sans
     * la moindre action possible : l'écran paraissait vide, et le dossier ne
     * pouvait plus avancer que par téléphone.
     */
    public static function ouvertAuDepot(array $dossier): bool
    {
        $etat = DossierStatut::normaliser((string)($dossier['statut'] ?? ''));

        if (in_array($etat, self::ETATS_OUVERTS, true)) {
            return true;
        }

        if (in_array($etat, self::ETATS_CLOS, true)) {
            return false;
        }

        return self::documentsRefuses((int)$dossier['id']) !== [];
    }

    /**
     * Dépose une pièce en remplacement d'une pièce refusée, ou en réponse à une
     * demande de complément.
     *
     * @param array<string,mixed> $dossier    Dossier DÉJÀ vérifié comme appartenant au candidat.
     * @param array<string,mixed> $fichier    Entrée de $_FILES.
     * @param int|null            $pieceId    pieces_justificatives_requises.id visé.
     * @param int|null            $documentId Document précis à remplacer (pièce hors référentiel).
     *
     * @return array{ok:bool, message:string, renvoye:bool, blocages:list<string>}
     */
    public static function deposer(
        array $dossier,
        array $fichier,
        ?int $pieceId,
        ?int $documentId,
        ?int $acteurId
    ): array {
        $dossierId = (int)$dossier['id'];

        if (!self::ouvertAuDepot($dossier)) {
            return self::echec(
                'Votre dossier n’est plus ouvert au dépôt de pièces. Contactez l’établissement.'
            );
        }

        // ── Pièce visée ─────────────────────────────────────────────────────
        // L'identifiant vient du formulaire : il est revérifié contre CE dossier
        // et CET établissement. Un identifiant forgé ne peut donc ni viser le
        // dossier d'un autre, ni greffer une pièce hors du référentiel de
        // l'établissement.
        $ancien = null;

        if ($documentId !== null) {
            $ancien = self::documentDuDossier($documentId, $dossierId);

            if ($ancien === null) {
                return self::echec('Cette pièce n’appartient pas à votre dossier.');
            }
            if ((string)$ancien['statut'] === 'accepte') {
                return self::echec(
                    'Cette pièce a déjà été acceptée par l’établissement : elle ne peut plus être remplacée.'
                );
            }

            $pieceId ??= $ancien['piece_requise_id'] !== null ? (int)$ancien['piece_requise_id'] : null;
        }

        $piece = $pieceId !== null
            ? self::pieceRequise($pieceId, (int)$dossier['etablissement_id'])
            : null;

        if ($pieceId !== null && $piece === null) {
            return self::echec('Pièce inconnue pour cet établissement.');
        }

        if ($piece === null && $ancien === null) {
            return self::echec('Indiquez la pièce que vous souhaitez déposer.');
        }

        // La pièce déjà en place pour ce référentiel, si l'appelant n'a pas
        // désigné de document précis.
        if ($ancien === null && $piece !== null) {
            $ancien = self::documentCourant($dossierId, (int)$piece['id']);

            if ($ancien !== null && (string)$ancien['statut'] === 'accepte') {
                return self::echec(
                    'Cette pièce a déjà été acceptée par l’établissement : elle ne peut plus être remplacée.'
                );
            }
        }

        // ── Le même fichier ne corrige rien ─────────────────────────────────
        // Redéposer à l'identique le document refusé est l'erreur la plus
        // fréquente : le candidat croit avoir agi, l'établissement refuse une
        // seconde fois, et deux jours sont perdus. On l'arrête ici, en disant
        // quoi faire — PieceDossier ne peut pas le voir : sa détection de
        // doublon ignore volontairement les pièces refusées.
        if ($ancien !== null
            && (string)$ancien['statut'] === 'refuse'
            && !empty($ancien['hash_sha256'])
            && ($fichier['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
            && is_uploaded_file((string)($fichier['tmp_name'] ?? ''))
            && @hash_file('sha256', (string)$fichier['tmp_name']) === (string)$ancien['hash_sha256']) {
            return self::echec(
                'C’est exactement le fichier déjà refusé. Téléversez une nouvelle version : '
                . 'un document plus lisible, complet, ou au bon format.'
                . (trim((string)($ancien['motif_refus'] ?? '')) !== ''
                    ? ' Motif du refus : ' . trim((string)$ancien['motif_refus'])
                    : '')
            );
        }

        // ── Dépôt ───────────────────────────────────────────────────────────
        $depot = PieceDossier::deposer($fichier, $dossier, $piece, $acteurId);

        if (!$depot['ok']) {
            return self::echec((string)$depot['erreur']);
        }

        $nouveauId = (int)$depot['document_id'];

        // Doublon strict d'une pièce NON refusée : rien de neuf n'est arrivé.
        // Le dire vaut mieux qu'un « c'est enregistré » qui laisserait le
        // candidat attendre une décision qui ne viendra pas.
        if ($depot['doublon'] && $ancien !== null && $nouveauId === (int)$ancien['id']) {
            return self::echec(
                'Ce document est déjà dans votre dossier, à l’identique. '
                . 'Téléversez une version différente si vous vouliez le corriger.'
            );
        }

        if ($ancien !== null && $nouveauId !== (int)$ancien['id']) {
            self::chainer($nouveauId, (int)$ancien['id']);

            Journal::dossier(
                Journal::PIECE_REMPLACEE,
                $dossier,
                [
                    'document_id'  => $nouveauId,
                    'remplace'     => (int)$ancien['id'],
                    'libelle'      => $ancien['libelle'] ?? null,
                    'motif_refus'  => $ancien['motif_refus'] ?? null,
                ],
                ['acteur_id' => $acteurId, 'acteur_role' => 'etudiant']
            );
        }

        // ── Suite du parcours ───────────────────────────────────────────────
        $blocages = self::blocages($dossier);
        $renvoye  = false;

        $etat = DossierStatut::normaliser((string)$dossier['statut']);

        if ($etat === DossierStatut::PIECE_MANQUANTE && $blocages === []) {
            $renvoye = self::renvoyer($dossier, $acteurId)['ok'];
        } else {
            // Pas de renvoi : l'établissement doit tout de même savoir qu'une
            // pièce est arrivée, sinon elle attend qu'on pense à la regarder.
            self::notifierEtablissement(
                $dossier,
                'piece_remplacee',
                'Nouvelle pièce déposée',
                trim((string)($dossier['nom_complet'] ?? 'Un candidat'))
                . ' (' . self::reference($dossier) . ') a déposé '
                . ($piece !== null ? '« ' . (string)$piece['libelle'] . ' »' : 'une pièce complémentaire')
                . ' en remplacement. À vérifier dans la zone pièces du dossier.'
            );
        }

        return [
            'ok'       => true,
            'message'  => $renvoye
                ? 'Pièce enregistrée. Votre dossier est reparti en analyse : plus rien ne manque.'
                : 'Pièce enregistrée. L’établissement va la vérifier.',
            'renvoye'  => $renvoye,
            'blocages' => $blocages,
        ];
    }

    /**
     * Renvoie le dossier à l'analyse (piece_manquante → soumis).
     *
     * Refuse tant qu'il reste une pièce refusée ou une pièce obligatoire
     * absente : un renvoi prématuré fait perdre un aller-retour au candidat et
     * une analyse à l'établissement.
     *
     * @return array{ok:bool, message:string, blocages:list<string>}
     */
    public static function renvoyer(array $dossier, ?int $acteurId): array
    {
        $dossierId = (int)$dossier['id'];
        $etat      = DossierStatut::normaliser((string)$dossier['statut']);

        if ($etat !== DossierStatut::PIECE_MANQUANTE) {
            return [
                'ok'       => false,
                'message'  => 'Votre dossier n’est pas en attente de pièce complémentaire.',
                'blocages' => [],
            ];
        }

        $blocages = self::blocages($dossier);
        if ($blocages !== []) {
            return [
                'ok'       => false,
                'message'  => 'Il reste des pièces à fournir : ' . implode(', ', $blocages) . '.',
                'blocages' => $blocages,
            ];
        }

        $resultat = (new Dossier())->transition($dossierId, DossierStatut::SOUMIS, 'etudiant', [
            'acteur_id'   => $acteurId,
            'commentaire' => 'Pièces complémentaires déposées par le candidat.',
            'evenement'   => Journal::DOSSIER_RENVOYE,
        ]);

        if (!$resultat['ok']) {
            return ['ok' => false, 'message' => (string)$resultat['erreur'], 'blocages' => []];
        }

        self::notifierEtablissement(
            $dossier,
            'dossier_renvoye',
            'Dossier complété par le candidat',
            trim((string)($dossier['nom_complet'] ?? 'Un candidat'))
            . ' (' . self::reference($dossier) . ') a fourni les pièces demandées. '
            . 'Le dossier est de nouveau à analyser.'
        );

        return [
            'ok'       => true,
            'message'  => 'Votre dossier est reparti en analyse. Vous serez notifié de la décision.',
            'blocages' => [],
        ];
    }

    /**
     * Ce qui empêche encore le dossier de repartir en analyse.
     *
     * @return list<string> Libellés lisibles par le candidat.
     */
    public static function blocages(array $dossier): array
    {
        $dossierId = (int)$dossier['id'];
        $blocages  = [];

        foreach (self::documentsRefuses($dossierId) as $doc) {
            $blocages[] = (string)($doc['libelle'] ?? 'Pièce refusée');
        }

        // Pièces obligatoires jamais déposées : le candidat ne peut pas
        // « compléter » un dossier auquel il manque une pièce d'entrée.
        foreach (self::piecesManquantes($dossier) as $libelle) {
            $blocages[] = $libelle;
        }

        return array_values(array_unique($blocages));
    }

    /**
     * Documents refusés qui n'ont pas encore de remplaçant.
     *
     * @return list<array<string,mixed>>
     */
    public static function documentsRefuses(int $dossierId): array
    {
        try {
            $st = Database::getInstance()->getConnection()->prepare(
                'SELECT d.*
                 FROM documents_orientation d
                 WHERE d.dossier_id = :dossier
                   AND d.statut = \'refuse\'
                   AND NOT EXISTS (
                       SELECT 1 FROM documents_orientation r
                       WHERE r.remplace_document_id = d.id
                   )
                 ORDER BY d.id'
            );
            $st->execute([':dossier' => $dossierId]);

            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[ComplementDossier] Pièces refusées illisibles #' . $dossierId . ' : ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Identifiants des documents rendus obsolètes par une version plus récente.
     * L'espace élève s'en sert pour n'afficher que la dernière version.
     *
     * @return list<int>
     */
    public static function documentsRemplaces(int $dossierId): array
    {
        try {
            $st = Database::getInstance()->getConnection()->prepare(
                'SELECT remplace_document_id
                 FROM documents_orientation
                 WHERE dossier_id = ? AND remplace_document_id IS NOT NULL'
            );
            $st->execute([$dossierId]);

            return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN, 0));
        } catch (\Throwable $e) {
            error_log('[ComplementDossier] Chaînage illisible #' . $dossierId . ' : ' . $e->getMessage());

            return [];
        }
    }

    // ── Interne ──────────────────────────────────────────────────────────────

    /**
     * Libellés des pièces obligatoires non déposées, compte tenu du niveau
     * d'entrée et de la situation de bourse déclarée.
     *
     * @return list<string>
     */
    private static function piecesManquantes(array $dossier): array
    {
        try {
            $modele   = new Dossier();
            // Filtrée par niveau ET par situation de bourse : réclamer une
            // attestation de bourse à un non-boursier bloquerait son dossier
            // pour une pièce qu'il ne peut pas produire.
            $requises = $modele->piecesAttenduesPourDossier($dossier);

            $deposees = PieceDossier::deposeesParPiece((int)$dossier['id']);
            $bourse   = $dossier['statut_bourse'] ?? null;

            $manquantes = [];
            foreach ($requises as $piece) {
                if (!PieceDossier::estObligatoire($piece, $bourse)) {
                    continue;
                }
                if (!isset($deposees[(int)$piece['id']])) {
                    $manquantes[] = (string)$piece['libelle'];
                }
            }

            return $manquantes;
        } catch (\Throwable $e) {
            error_log('[ComplementDossier] Pièces attendues illisibles : ' . $e->getMessage());

            return [];
        }
    }

    /** @return array<string,mixed>|null */
    private static function documentDuDossier(int $documentId, int $dossierId): ?array
    {
        try {
            $st = Database::getInstance()->getConnection()->prepare(
                'SELECT * FROM documents_orientation WHERE id = ? AND dossier_id = ? LIMIT 1'
            );
            $st->execute([$documentId, $dossierId]);
            $ligne = $st->fetch(PDO::FETCH_ASSOC);

            return $ligne === false ? null : $ligne;
        } catch (\Throwable $e) {
            error_log('[ComplementDossier] Document illisible #' . $documentId . ' : ' . $e->getMessage());

            return null;
        }
    }

    /** Dernière version déposée pour une pièce du référentiel. */
    private static function documentCourant(int $dossierId, int $pieceId): ?array
    {
        try {
            $st = Database::getInstance()->getConnection()->prepare(
                'SELECT * FROM documents_orientation
                 WHERE dossier_id = ? AND piece_requise_id = ?
                 ORDER BY id DESC LIMIT 1'
            );
            $st->execute([$dossierId, $pieceId]);
            $ligne = $st->fetch(PDO::FETCH_ASSOC);

            return $ligne === false ? null : $ligne;
        } catch (\Throwable $e) {
            error_log('[ComplementDossier] Version courante illisible : ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Ligne du référentiel, bornée à l'établissement du dossier : c'est la
     * barrière qui empêche un identifiant forgé de rattacher un fichier au
     * référentiel d'un autre établissement.
     *
     * @return array<string,mixed>|null
     */
    private static function pieceRequise(int $pieceId, int $etablissementId): ?array
    {
        try {
            $st = Database::getInstance()->getConnection()->prepare(
                'SELECT * FROM pieces_justificatives_requises
                 WHERE id = ? AND etablissement_id = ? LIMIT 1'
            );
            $st->execute([$pieceId, $etablissementId]);
            $ligne = $st->fetch(PDO::FETCH_ASSOC);

            return $ligne === false ? null : $ligne;
        } catch (\Throwable $e) {
            error_log('[ComplementDossier] Pièce requise illisible #' . $pieceId . ' : ' . $e->getMessage());

            return null;
        }
    }

    private static function chainer(int $nouveauId, int $ancienId): void
    {
        try {
            Database::getInstance()->getConnection()->prepare(
                'UPDATE documents_orientation SET remplace_document_id = ? WHERE id = ?'
            )->execute([$ancienId, $nouveauId]);
        } catch (\Throwable $e) {
            error_log('[ComplementDossier] Chaînage impossible #' . $nouveauId . ' : ' . $e->getMessage());
        }
    }

    private static function reference(array $dossier): string
    {
        $reference = trim((string)($dossier['reference'] ?? ''));

        return $reference !== '' ? $reference : 'dossier #' . (int)$dossier['id'];
    }

    private static function notifierEtablissement(array $dossier, string $type, string $titre, string $message): void
    {
        try {
            $etabId        = (int)$dossier['etablissement_id'];
            $notifications = new NotificationOrientation();

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
            error_log('[ComplementDossier] Notification établissement : ' . $e->getMessage());
        }
    }

    /** @return array{ok:false, message:string, renvoye:false, blocages:list<string>} */
    private static function echec(string $message): array
    {
        return ['ok' => false, 'message' => $message, 'renvoye' => false, 'blocages' => []];
    }
}
