<?php
declare(strict_types=1);

namespace Services\Orientation;

use Core\Database;
use PDO;

/**
 * Dépôt et contrôle des pièces justificatives d'un dossier (spec §5.3, §15.2).
 *
 * Ces fichiers sont des actes de naissance et des pièces d'identité : de la
 * donnée personnelle sensible. Les règles appliquées ici ne sont pas
 * négociables et ne dépendent JAMAIS du navigateur :
 *
 *  - taille maximale 3 Mo par fichier ;
 *  - type réel contrôlé par `finfo`, jamais l'extension ni le Content-Type
 *    annoncé par le client ;
 *  - liste blanche d'extensions, jamais de liste noire ;
 *  - renommage systématique : le nom d'origine n'atteint jamais le disque ni
 *    B2 (il n'est conservé qu'en base, pour l'affichage) ;
 *  - empreinte SHA-256 calculée à la réception — preuve d'intégrité et
 *    détection des doublons ;
 *  - bucket B2 privé, aucun accès direct : la consultation passe par une URL
 *    signée à durée courte, émise après contrôle des droits ;
 *  - aucun fichier n'est jamais écrit dans un répertoire servi par nginx.
 *
 * Si B2 est injoignable, le dépôt ÉCHOUE de façon visible. Avaler l'erreur
 * ferait disparaître silencieusement une pièce d'identité — c'est pire qu'un
 * message d'erreur.
 */
final class PieceDossier
{
    /** 3 Mo par fichier — plafond appliqué à TOUS les dépôts de pièces. */
    public const TAILLE_MAX_OCTETS = 3 * 1024 * 1024;

    /**
     * Liste blanche générale : MIME réel => extension canonique.
     * L'extension écrite sur B2 vient de CETTE table, jamais du nom d'origine.
     *
     * Tous les formats image bitmap sont acceptés : un candidat photographie sa
     * pièce d'identité avec son téléphone, et un iPhone produit du HEIC, pas du
     * JPG. Refuser ces formats bloquait des dossiers pour une raison que le
     * candidat ne pouvait ni comprendre ni corriger depuis son appareil.
     *
     * Le SVG reste volontairement absent, et doit le rester : c'est du XML
     * exécutable, il vaudrait un XSS stocké dès qu'on le sert en ligne.
     */
    private const TYPES_ACCEPTES = [
        'application/pdf'          => 'pdf',
        'image/jpeg'               => 'jpg',
        'image/png'                => 'png',
        'image/webp'               => 'webp',
        'image/gif'                => 'gif',
        'image/bmp'                => 'bmp',
        'image/x-ms-bmp'           => 'bmp',
        'image/tiff'               => 'tif',
        'image/avif'               => 'avif',
        'image/heic'               => 'heic',
        'image/heif'               => 'heif',
        'image/x-icon'             => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
    ];

    /**
     * Restrictions par type de pièce. La photo d'identité doit être une image :
     * un PDF y est refusé, il ne s'imprime pas correctement sur le récépissé.
     *
     * La valeur `image` signifie « n'importe quel format image accepté » : la
     * règle ne se réécrit donc pas à chaque format ajouté ci-dessus.
     */
    private const TYPES_PAR_PIECE = [
        'photo_identite' => 'image',
    ];

    /** Durée de vie d'une URL de consultation (spec §15.2 : 5 minutes max). */
    private const URL_SIGNEE_MINUTES = 5;

    /**
     * Dépose une pièce et enregistre sa trace.
     *
     * @param array{name:string,type:string,tmp_name:string,error:int,size:int} $fichier Entrée $_FILES
     * @param array<string,mixed> $dossier Ligne complète du dossier.
     * @param array<string,mixed>|null $piece Ligne de pieces_justificatives_requises.
     *
     * @return array{ok:bool, erreur:?string, document_id:?int, cle:?string, doublon:bool}
     */
    public static function deposer(
        array $fichier,
        array $dossier,
        ?array $piece = null,
        ?int $acteurId = null
    ): array {
        $libellePiece = (string)($piece['libelle'] ?? 'Pièce justificative');

        $erreur = self::verifierTransfert($fichier);
        if ($erreur !== null) {
            return self::echec($erreur);
        }

        // ── Type réel du fichier ────────────────────────────────────────────
        // finfo lit les octets d'en-tête. Un .exe renommé en .pdf est démasqué
        // ici ; le Content-Type envoyé par le navigateur ne l'aurait pas été.
        $mimeReel = self::mimeReel($fichier['tmp_name']);
        if ($mimeReel === null || !isset(self::TYPES_ACCEPTES[$mimeReel])) {
            return self::echec(sprintf(
                '« %s » : format non accepté. Formats autorisés : %s.',
                $libellePiece,
                self::formatsLisibles()
            ));
        }

        $code = $piece['code_interne'] ?? null;
        if ($code !== null && (self::TYPES_PAR_PIECE[$code] ?? null) === 'image'
            && !self::estImage($mimeReel)) {
            return self::echec(sprintf(
                '« %s » : seule une image est acceptée (photo ou scan), pas un PDF.',
                $libellePiece
            ));
        }

        if ((int)$fichier['size'] > self::TAILLE_MAX_OCTETS) {
            return self::echec(sprintf(
                '« %s » : fichier trop lourd (%s). Maximum %s.',
                $libellePiece,
                self::formaterTaille((int)$fichier['size']),
                self::formaterTaille(self::TAILLE_MAX_OCTETS)
            ));
        }

        // ── Empreinte ───────────────────────────────────────────────────────
        $hash = @hash_file('sha256', $fichier['tmp_name']);
        if ($hash === false) {
            return self::echec(sprintf('« %s » : fichier illisible.', $libellePiece));
        }

        // Le même fichier redéposé pour la même pièce ne crée pas de doublon.
        $existant = self::documentIdentique((int)$dossier['id'], $hash, $piece['id'] ?? null);
        if ($existant !== null) {
            return [
                'ok'          => true,
                'erreur'      => null,
                'document_id' => $existant,
                'cle'         => null,
                'doublon'     => true,
            ];
        }

        // ── Nom de destination ──────────────────────────────────────────────
        $cle = self::construireCle($dossier, $code ?? self::codeParDefaut($piece), self::TYPES_ACCEPTES[$mimeReel]);

        try {
            // Chiffré au repos (AES-256-GCM) : un bucket exfiltré ne rend plus
            // aucune pièce d'identité lisible. La consultation passe désormais
            // obligatoirement par l'application (DocumentStorage::getContentClair).
            (new DocumentStorage())->uploadChiffre($fichier['tmp_name'], $cle, $mimeReel);
        } catch (\Throwable $e) {
            error_log('[PieceDossier] Upload B2 échoué (' . $cle . ') : ' . $e->getMessage());

            return self::echec(sprintf(
                '« %s » n’a pas pu être enregistrée. Réessayez dans un instant.',
                $libellePiece
            ));
        }

        // ── Trace en base ───────────────────────────────────────────────────
        try {
            $db = Database::getInstance()->getConnection();
            $st = $db->prepare(
                'INSERT INTO documents_orientation
                   (dossier_id, eleve_id, piece_requise_id, libelle, type_document,
                    nom_original, cle_b2, mime_type, taille_octets, hash_sha256,
                    statut, date_depot)
                 VALUES (:dossier, :eleve, :piece, :libelle, :type,
                         :nom, :cle, :mime, :taille, :hash, \'en_attente\', NOW())'
            );
            $st->execute([
                ':dossier' => (int)$dossier['id'],
                ':eleve'   => !empty($dossier['eleve_id']) ? (int)$dossier['eleve_id'] : null,
                ':piece'   => isset($piece['id']) ? (int)$piece['id'] : null,
                ':libelle' => mb_substr($libellePiece, 0, 180),
                ':type'    => $code,
                ':nom'     => mb_substr(self::nomAffichable((string)$fichier['name']), 0, 255),
                ':cle'     => $cle,
                ':mime'    => $mimeReel,
                ':taille'  => (int)$fichier['size'],
                ':hash'    => $hash,
            ]);

            $documentId = (int)$db->lastInsertId();
        } catch (\Throwable $e) {
            error_log('[PieceDossier] Enregistrement base échoué (' . $cle . ') : ' . $e->getMessage());

            return self::echec(sprintf('« %s » n’a pas pu être enregistrée.', $libellePiece));
        }

        Journal::dossier(
            Journal::PIECE_TELEVERSEE,
            $dossier,
            [
                'document_id'  => $documentId,
                'type'         => $code,
                'libelle'      => $libellePiece,
                'taille'       => (int)$fichier['size'],
                'empreinte'    => substr($hash, 0, 12),
            ],
            ['acteur_id' => $acteurId]
        );

        return ['ok' => true, 'erreur' => null, 'document_id' => $documentId, 'cle' => $cle, 'doublon' => false];
    }

    /**
     * Dépose toutes les pièces présentes dans $_FILES pour ce dossier.
     * Les clés attendues sont « piece_{id} », comme dans le formulaire.
     *
     * @param array<string,mixed> $fichiers $_FILES
     * @param array<string,mixed> $dossier
     * @param list<array<string,mixed>> $piecesAttendues Lignes de pieces_justificatives_requises.
     *
     * @return array{deposees:int, erreurs:list<string>, echecs_obligatoires:bool}
     */
    public static function deposerLot(
        array $fichiers,
        array $dossier,
        array $piecesAttendues,
        ?int $acteurId = null,
        ?string $statutBourse = null
    ): array {
        $parId = [];
        foreach ($piecesAttendues as $piece) {
            $parId[(int)$piece['id']] = $piece;
        }

        $deposees            = 0;
        $erreurs             = [];
        $echecsObligatoires  = false;

        foreach ($fichiers as $champ => $fichier) {
            if (!is_array($fichier) || !str_starts_with($champ, 'piece_')) {
                continue;
            }
            if (($fichier['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $pieceId = (int)substr($champ, 6);

            // Une pièce qui n'appartient pas à la liste attendue pour CE dossier
            // est ignorée : un POST forgé ne peut pas injecter un document hors
            // du périmètre de l'établissement ou de la situation déclarée.
            if (!isset($parId[$pieceId])) {
                continue;
            }

            $resultat = self::deposer($fichier, $dossier, $parId[$pieceId], $acteurId);

            if ($resultat['ok']) {
                $deposees++;
                continue;
            }

            $erreurs[] = (string)$resultat['erreur'];

            $obligatoire = self::estObligatoire($parId[$pieceId], $statutBourse);
            if ($obligatoire) {
                $echecsObligatoires = true;
            }
        }

        return [
            'deposees'            => $deposees,
            'erreurs'             => $erreurs,
            'echecs_obligatoires' => $echecsObligatoires,
        ];
    }

    /**
     * Pièces déjà déposées pour un dossier, indexées par piece_requise_id.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function deposeesParPiece(int $dossierId): array
    {
        try {
            $st = Database::getInstance()->getConnection()->prepare(
                'SELECT * FROM documents_orientation
                 WHERE dossier_id = ? AND statut <> \'refuse\'
                 ORDER BY date_depot DESC, id DESC'
            );
            $st->execute([$dossierId]);
            $lignes = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[PieceDossier] Lecture échouée #' . $dossierId . ' : ' . $e->getMessage());

            return [];
        }

        $parPiece = [];
        foreach ($lignes as $ligne) {
            $cle = (int)($ligne['piece_requise_id'] ?? 0);
            // ORDER BY date DESC : la première rencontrée est la plus récente.
            if (!isset($parPiece[$cle])) {
                $parPiece[$cle] = $ligne;
            }
        }

        return $parPiece;
    }

    /**
     * OBSOLÈTE — ne rend plus d'URL, et ne doit plus en rendre.
     *
     * Cette méthode distribuait un lien B2 signé pointant DIRECTEMENT sur
     * l'objet. Depuis que les pièces sont chiffrées au repos (AES-256-GCM,
     * cf. DocumentStorage::uploadChiffre), un tel lien rendrait au navigateur
     * une enveloppe binaire illisible — et, pire, il resterait valable hors
     * session pendant toute sa durée de vie, alors que le contrôle des droits
     * doit être refait à CHAQUE consultation.
     *
     * Les deux seules portes de consultation sont désormais des points de
     * sortie de l'application, qui vérifient les droits puis déchiffrent :
     *   - candidat     : /eleve/orientation/document/{hash}
     *   - établissement: /gestionnaire/candidatures/{id}/document/{documentId}
     *
     * La journalisation de la consultation est conservée ici pour les appelants
     * historiques ; la valeur de retour est toujours null.
     */
    public static function urlConsultation(array $document, array $dossier, ?int $acteurId = null): ?string
    {
        Journal::dossier(
            'piece_consultee',
            $dossier,
            ['document_id' => (int)($document['id'] ?? 0), 'libelle' => $document['libelle'] ?? null],
            ['acteur_id' => $acteurId]
        );

        return null;
    }

    /**
     * Décision de l'établissement sur une pièce : acceptée, ou refusée avec
     * motif (spec §8.2, zone pièces).
     */
    public static function decider(
        int $documentId,
        int $dossierId,
        string $decision,
        ?string $motif,
        ?int $acteurId
    ): bool {
        if (!in_array($decision, ['accepte', 'refuse'], true)) {
            return false;
        }
        if ($decision === 'refuse' && trim((string)$motif) === '') {
            return false;
        }

        try {
            $db = Database::getInstance()->getConnection();

            // La clause sur dossier_id est la barrière de cloisonnement : elle
            // empêche d'agir sur la pièce d'un autre dossier via un id forgé.
            $st = $db->prepare(
                'UPDATE documents_orientation
                 SET statut = :statut, motif_refus = :motif, verifie_le = NOW(), verifie_par = :par
                 WHERE id = :id AND dossier_id = :dossier'
            );
            $st->execute([
                ':statut'  => $decision,
                ':motif'   => $decision === 'refuse' ? trim((string)$motif) : null,
                ':par'     => $acteurId,
                ':id'      => $documentId,
                ':dossier' => $dossierId,
            ]);

            if ($st->rowCount() === 0) {
                return false;
            }
        } catch (\Throwable $e) {
            error_log('[PieceDossier] Décision échouée #' . $documentId . ' : ' . $e->getMessage());

            return false;
        }

        Journal::ecrire(Journal::PIECE_DECIDEE, [
            'objet_type' => 'dossier',
            'objet_id'   => $dossierId,
            'acteur_id'  => $acteurId,
            'donnees'    => ['document_id' => $documentId, 'decision' => $decision, 'motif' => $motif],
        ]);

        return true;
    }

    /**
     * Supprime une pièce non encore validée (route DELETE de la spec §13.1).
     * Une pièce acceptée par l'établissement n'est jamais supprimable par
     * l'étudiant : elle fait partie du dossier constitué.
     */
    public static function supprimer(int $documentId, int $dossierId, ?int $acteurId = null): bool
    {
        try {
            $db = Database::getInstance()->getConnection();

            $st = $db->prepare(
                'SELECT * FROM documents_orientation
                 WHERE id = ? AND dossier_id = ? AND statut <> \'accepte\' LIMIT 1'
            );
            $st->execute([$documentId, $dossierId]);
            $document = $st->fetch(PDO::FETCH_ASSOC);

            if ($document === false) {
                return false;
            }

            $db->prepare('DELETE FROM documents_orientation WHERE id = ? AND dossier_id = ?')
               ->execute([$documentId, $dossierId]);

            // Le fichier distant part après la ligne : si la suppression B2
            // échoue, on préfère un orphelin sur B2 qu'une ligne pointant vers
            // un fichier disparu.
            try {
                (new DocumentStorage())->delete((string)$document['cle_b2']);
            } catch (\Throwable $e) {
                error_log('[PieceDossier] Suppression B2 échouée : ' . $e->getMessage());
            }
        } catch (\Throwable $e) {
            error_log('[PieceDossier] Suppression échouée #' . $documentId . ' : ' . $e->getMessage());

            return false;
        }

        Journal::ecrire(Journal::PIECE_SUPPRIMEE, [
            'objet_type' => 'dossier',
            'objet_id'   => $dossierId,
            'acteur_id'  => $acteurId,
            'donnees'    => ['document_id' => $documentId],
        ]);

        return true;
    }

    /**
     * MIME acceptés => extension canonique. Source UNIQUE : les autres chemins
     * de dépôt (guichet, étape 2) s'y réfèrent au lieu de recopier la liste.
     *
     * @return array<string,string>
     */
    public static function typesAcceptes(): array
    {
        return self::TYPES_ACCEPTES;
    }

    /** Le type réel est-il déposable ? */
    public static function typeAccepte(string $mime): bool
    {
        return isset(self::TYPES_ACCEPTES[$mime]);
    }

    /** Extension canonique d'un type accepté, `null` sinon. */
    public static function extensionPour(string $mime): ?string
    {
        return self::TYPES_ACCEPTES[$mime] ?? null;
    }

    /** Une image (n'importe quel format bitmap accepté) ? */
    public static function estImage(string $mime): bool
    {
        return isset(self::TYPES_ACCEPTES[$mime]) && str_starts_with($mime, 'image/');
    }

    /**
     * Valeur de l'attribut `accept` d'un <input type="file">.
     *
     * `image/*` plutôt que la liste explicite : les navigateurs mobiles n'ont
     * pas tous le même nom de MIME pour une photo HEIC, et un `accept` trop
     * étroit grise le fichier dans le sélecteur sans expliquer pourquoi.
     * Ce n'est qu'un confort : le serveur revalide le type réel (finfo).
     */
    public static function acceptHtml(): string
    {
        return 'application/pdf,image/*';
    }

    /** Libellé des formats acceptés, affiché à côté du champ. */
    public static function formatsLisibles(): string
    {
        return 'PDF ou image (JPG, PNG, WebP, HEIC, GIF, BMP, TIFF…)';
    }

    /** « PDF ou image — 3 Mo maximum. » Une seule formulation dans tout le produit. */
    public static function consigneDepot(): string
    {
        return self::formatsLisibles() . ' — ' . self::formaterTaille(self::TAILLE_MAX_OCTETS) . ' maximum';
    }

    /** Taille lisible : « 3 Mo », « 820 Ko ». */
    public static function formaterTaille(int $octets): string
    {
        if ($octets >= 1024 * 1024) {
            return rtrim(rtrim(number_format($octets / 1048576, 1, ',', ' '), '0'), ',') . ' Mo';
        }

        return max(1, (int)round($octets / 1024)) . ' Ko';
    }

    /**
     * Une pièce est-elle obligatoire compte tenu de la situation de bourse ?
     *
     * Une pièce CONDITIONNELLE — aujourd'hui la seule attestation de bourse,
     * hier aussi la preuve de dépôt ANBG — ne concerne que la situation qui la
     * déclenche. Sans ce test, un candidat non boursier se voyait réclamer une
     * attestation de bourse qu'il ne peut pas produire — et son dossier restait
     * bloqué sans issue.
     */
    public static function estObligatoire(array $piece, ?string $statutBourse): bool
    {
        $code = $piece['code_interne'] ?? null;

        if ($code !== null && SituationBourse::estPieceConditionnelle($code)) {
            return SituationBourse::pieceAssociee($statutBourse) === $code
                && SituationBourse::pieceObligatoire($statutBourse);
        }

        return (int)($piece['obligatoire'] ?? 0) === 1;
    }

    // ── Interne ──────────────────────────────────────────────────────────────

    /**
     * Chemin B2 : dossiers/{annee}/{etablissement}/{reference}/{type}_{uuid}.{ext}
     * (spec §5.3). Aucun segment ne vient d'une saisie utilisateur.
     */
    private static function construireCle(array $dossier, string $type, string $extension): string
    {
        $annee = (string)($dossier['annee_academique'] ?? '');
        if ($annee === '') {
            $annee = \Core\AnneeUniversitaire::code();
        }

        // Un brouillon n'a pas encore de référence : on utilise son identifiant,
        // qui n'est pas secret et ne circule pas hors de B2.
        $reference = (string)($dossier['reference'] ?? '');
        if ($reference === '') {
            $reference = 'brouillon-' . (int)$dossier['id'];
        }

        return sprintf(
            'dossiers/%s/%d/%s/%s_%s.%s',
            self::segmentSur($annee),
            (int)$dossier['etablissement_id'],
            self::segmentSur($reference),
            self::segmentSur($type !== '' ? $type : 'piece'),
            self::uuid(),
            $extension
        );
    }

    /** Réduit un segment de chemin à un alphabet sûr : jamais de ../ ni d'espace. */
    private static function segmentSur(string $valeur): string
    {
        $propre = preg_replace('/[^A-Za-z0-9_-]/', '-', $valeur) ?? '';
        $propre = trim(preg_replace('/-+/', '-', $propre) ?? '', '-');

        return $propre === '' ? 'x' : mb_substr($propre, 0, 64);
    }

    /** UUID v4 issu d'un générateur cryptographiquement sûr. */
    private static function uuid(): string
    {
        $octets = random_bytes(16);
        $octets[6] = chr((ord($octets[6]) & 0x0f) | 0x40);
        $octets[8] = chr((ord($octets[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($octets), 4));
    }

    /** Traduit les codes d'erreur PHP en message compréhensible. */
    private static function verifierTransfert(array $fichier): ?string
    {
        $code = (int)($fichier['error'] ?? UPLOAD_ERR_NO_FILE);

        $message = match ($code) {
            UPLOAD_ERR_OK        => null,
            UPLOAD_ERR_INI_SIZE,
            UPLOAD_ERR_FORM_SIZE => 'Fichier trop lourd. Maximum ' . self::formaterTaille(self::TAILLE_MAX_OCTETS) . '.',
            UPLOAD_ERR_PARTIAL   => 'Transfert interrompu. Réessayez.',
            UPLOAD_ERR_NO_FILE   => 'Aucun fichier reçu.',
            default              => 'Le transfert a échoué. Réessayez.',
        };

        if ($message !== null) {
            return $message;
        }

        $tmp = (string)($fichier['tmp_name'] ?? '');

        // Garde-fou décisif : sans cette vérification, un chemin forgé dans
        // tmp_name ferait lire au serveur n'importe quel fichier local.
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return 'Fichier invalide.';
        }

        if ((int)($fichier['size'] ?? 0) <= 0) {
            return 'Le fichier est vide.';
        }

        return null;
    }

    private static function mimeReel(string $chemin): ?string
    {
        try {
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($chemin);
        } catch (\Throwable $e) {
            error_log('[PieceDossier] finfo indisponible : ' . $e->getMessage());

            return null;
        }

        return is_string($mime) && $mime !== '' ? $mime : null;
    }

    /** Identifiant d'un document au contenu strictement identique, s'il existe. */
    private static function documentIdentique(int $dossierId, string $hash, mixed $pieceId): ?int
    {
        try {
            $st = Database::getInstance()->getConnection()->prepare(
                'SELECT id FROM documents_orientation
                 WHERE dossier_id = ? AND hash_sha256 = ?
                   AND (piece_requise_id <=> ?) AND statut <> \'refuse\'
                 LIMIT 1'
            );
            $st->execute([$dossierId, $hash, $pieceId !== null ? (int)$pieceId : null]);
            $id = $st->fetchColumn();

            return $id === false ? null : (int)$id;
        } catch (\Throwable $e) {
            error_log('[PieceDossier] Détection doublon échouée : ' . $e->getMessage());

            return null;
        }
    }

    /** Code interne de repli quand la pièce n'en déclare pas. */
    private static function codeParDefaut(?array $piece): string
    {
        if ($piece === null) {
            return 'piece';
        }

        $slug = strtolower(self::segmentSur((string)($piece['libelle'] ?? 'piece')));

        return $slug === 'x' ? 'piece' : $slug;
    }

    /**
     * Nom conservé pour l'affichage. Nettoyé : il sera réaffiché dans le
     * back-office, et un nom de fichier est une chaîne contrôlée par l'auteur
     * du dépôt.
     */
    private static function nomAffichable(string $nom): string
    {
        $nom = basename($nom);
        $nom = preg_replace('/[\x00-\x1F\x7F]/u', '', $nom) ?? '';

        return trim($nom) === '' ? 'document' : trim($nom);
    }

    /** @return array{ok:false, erreur:string, document_id:null, cle:null, doublon:false} */
    private static function echec(string $message): array
    {
        return ['ok' => false, 'erreur' => $message, 'document_id' => null, 'cle' => null, 'doublon' => false];
    }
}
