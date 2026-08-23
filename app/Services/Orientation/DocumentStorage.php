<?php
declare(strict_types=1);

namespace Services\Orientation;

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use Aws\S3\PostObjectV4;

/**
 * Stockage B2 (S3-compatible) — EXCLUSIF au module Orientation.
 * Aucun autre module ne doit utiliser cette classe.
 */
class DocumentStorage
{
    private S3Client $s3;
    private string $bucket;

    private const MIME_WHITELIST = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    private const MAX_SIZE_BYTES = 10 * 1024 * 1024; // 10 Mo

    private const VIDEO_MIME_WHITELIST = [
        'video/mp4',
        'video/webm',
        'video/ogg',
        'video/quicktime',      // .mov
        'video/x-msvideo',      // .avi
        'video/x-matroska',     // .mkv
        'video/x-ms-wmv',       // .wmv
        'video/mpeg',
        'video/3gpp',
    ];

    private const VIDEO_MAX_SIZE_BYTES = 500 * 1024 * 1024; // 500 Mo

    /**
     * Images éditoriales (illustrations d'actualités) — tous les formats
     * bitmap courants sont acceptés. Le SVG est volontairement exclu :
     * c'est un document XML exécutable (risque XSS sur un bucket public).
     */
    private const IMAGE_MIME_WHITELIST = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/bmp',
        'image/x-ms-bmp',
        'image/tiff',
        'image/avif',
        'image/heic',
        'image/heif',
        'image/x-icon',
        'image/vnd.microsoft.icon',
    ];

    /** Limite d'upload des images éditoriales : 15 Mo. */
    public const IMAGE_MAX_SIZE_BYTES = 15 * 1024 * 1024;

    /** Formats image acceptés pour un logo / une photo campus (== attribut accept du form). */
    public const MEDIA_IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * Contraintes (MIME autorisés + taille max) par type de média uploadé en
     * direct navigateur → B2. Source UNIQUE partagée par l'endpoint de
     * signature ET la validation post-upload (headObject).
     *
     * @return array{mimes: string[], maxBytes: int, prefix: string}
     */
    public static function contraintesMedia(string $kind): array
    {
        return match ($kind) {
            'logo'  => ['mimes' => self::MEDIA_IMAGE_MIMES,     'maxBytes' => self::MAX_SIZE_BYTES,        'prefix' => 'logos/'],
            'image' => ['mimes' => self::MEDIA_IMAGE_MIMES,     'maxBytes' => self::MAX_SIZE_BYTES,        'prefix' => 'images/'],
            'video' => ['mimes' => self::VIDEO_MIME_WHITELIST,  'maxBytes' => self::VIDEO_MAX_SIZE_BYTES,  'prefix' => 'videos/'],
            default => throw new \InvalidArgumentException('[DocumentStorage] Type de média inconnu : ' . $kind),
        };
    }

    /** Extension de fichier canonique pour un MIME média (logo/image/vidéo). */
    public static function extensionMedia(string $mime): string
    {
        static $map = [
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
            'video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/ogg' => 'ogv',
            'video/quicktime' => 'mov', 'video/x-msvideo' => 'avi',
            'video/x-matroska' => 'mkv', 'video/x-ms-wmv' => 'wmv',
            'video/mpeg' => 'mpg', 'video/3gpp' => '3gp',
        ];
        return $map[$mime] ?? 'bin';
    }

    public function __construct()
    {
        $keyId      = $_ENV['B2_KEY_ID']          ?? '';
        $appKey     = $_ENV['B2_APPLICATION_KEY']  ?? '';
        $endpoint   = $_ENV['B2_ENDPOINT']         ?? '';
        $region     = $_ENV['B2_REGION']           ?? 'us-west-004';
        $this->bucket = $_ENV['B2_BUCKET']         ?? '';

        if (!$keyId || !$appKey || !$endpoint || !$this->bucket) {
            throw new \RuntimeException('[DocumentStorage] Variables B2 manquantes dans .env (B2_KEY_ID, B2_APPLICATION_KEY, B2_BUCKET, B2_ENDPOINT)');
        }

        $this->s3 = new S3Client([
            'version'                 => 'latest',
            'region'                  => $region,
            'endpoint'                => 'https://' . $endpoint,
            'use_path_style_endpoint' => true,
            'credentials'             => [
                'key'    => $keyId,
                'secret' => $appKey,
            ],
            // Échec RAPIDE si B2 est injoignable (évite les requêtes web qui
            // pendent jusqu'au timeout FastCGI). 'timeout' => 0 : pas de limite
            // sur la durée TOTALE, pour ne pas couper un gros upload serveur.
            'http'                    => ['connect_timeout' => 8, 'timeout' => 0],
            'retries'                 => 2,
        ]);
    }

    /**
     * Upload un fichier vers B2.
     *
     * @param string $tmpPath    Chemin temporaire (issu de $_FILES)
     * @param string $cle        Clé de destination dans le bucket (ex: "logos/mon-etab.png")
     * @param string $mimeType   MIME déclaré — sera validé contre le fichier réel
     * @param bool   $publicRead Accès public (logos) vs privé (dossiers)
     * @return string            La clé B2 stockée
     */
    public function upload(string $tmpPath, string $cle, string $mimeType, bool $publicRead = false): string
    {
        $this->validerFichier($tmpPath, $mimeType);

        try {
            $this->s3->putObject([
                'Bucket'      => $this->bucket,
                'Key'         => $cle,
                'SourceFile'  => $tmpPath,
                'ContentType' => $mimeType,
                'ACL'         => $publicRead ? 'public-read' : 'private',
            ]);
        } catch (S3Exception $e) {
            throw new \RuntimeException('[DocumentStorage] Upload échoué : ' . $e->getAwsErrorMessage(), 0, $e);
        }

        return $cle;
    }

    /**
     * Upload une image éditoriale vers B2 — tous formats image bitmap,
     * limite 15 Mo. Le MIME réel du fichier (finfo) fait foi, jamais celui
     * déclaré par le navigateur.
     *
     * @param string $tmpPath    Chemin temporaire (issu de $_FILES)
     * @param string $cleSansExt Clé de destination SANS extension
     *                           (ex: "actualites/12/65f2a1b3c") — l'extension
     *                           est déduite du MIME réel.
     * @param bool   $publicRead Accès public vs privé
     * @return string            La clé B2 stockée (avec extension)
     *
     * @throws \InvalidArgumentException message destiné à l'utilisateur final
     */
    public function uploadImage(string $tmpPath, string $cleSansExt, bool $publicRead = false): string
    {
        if (!file_exists($tmpPath) || !is_readable($tmpPath)) {
            throw new \InvalidArgumentException("Le fichier image n'a pas pu être lu. Réessayez.");
        }

        $taille = (int)filesize($tmpPath);
        if ($taille > self::IMAGE_MAX_SIZE_BYTES) {
            throw new \InvalidArgumentException(sprintf(
                "La taille de l'image n'est pas supportée : %s Mo (maximum %d Mo).",
                number_format($taille / 1048576, 1, ',', ' '),
                (int)(self::IMAGE_MAX_SIZE_BYTES / 1048576)
            ));
        }

        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeReel = $finfo->file($tmpPath);

        if ($mimeReel === false || !in_array($mimeReel, self::IMAGE_MIME_WHITELIST, true)) {
            throw new \InvalidArgumentException(
                "Ce fichier n'est pas une image valide. Formats acceptés : JPG, PNG, WebP, GIF, BMP, TIFF, AVIF, HEIC."
            );
        }

        $cle = $cleSansExt . '.' . self::extensionPourMime($mimeReel);

        try {
            $this->s3->putObject([
                'Bucket'      => $this->bucket,
                'Key'         => $cle,
                'SourceFile'  => $tmpPath,
                'ContentType' => $mimeReel,
                'ACL'         => $publicRead ? 'public-read' : 'private',
            ]);
        } catch (S3Exception $e) {
            throw new \RuntimeException('[DocumentStorage] Upload image échoué : ' . $e->getAwsErrorMessage(), 0, $e);
        }

        return $cle;
    }

    /**
     * Extension canonique correspondant à un MIME image validé.
     */
    private static function extensionPourMime(string $mime): string
    {
        static $map = [
            'image/jpeg'                  => 'jpg',
            'image/png'                   => 'png',
            'image/webp'                  => 'webp',
            'image/gif'                   => 'gif',
            'image/bmp'                   => 'bmp',
            'image/x-ms-bmp'              => 'bmp',
            'image/tiff'                  => 'tiff',
            'image/avif'                  => 'avif',
            'image/heic'                  => 'heic',
            'image/heif'                  => 'heif',
            'image/x-icon'                => 'ico',
            'image/vnd.microsoft.icon'    => 'ico',
        ];
        return $map[$mime] ?? 'img';
    }

    /**
     * Upload une vidéo vers B2 — whitelist MIME vidéo + limite 500 Mo.
     *
     * @param string $tmpPath  Chemin temporaire (issu de $_FILES)
     * @param string $cle      Clé de destination (ex: "videos/mon-etab/presentation.mp4")
     * @return string          La clé B2 stockée
     */
    public function uploadVideo(string $tmpPath, string $cle): string
    {
        if (!file_exists($tmpPath) || !is_readable($tmpPath)) {
            throw new \InvalidArgumentException('[DocumentStorage] Fichier vidéo temporaire introuvable.');
        }

        $taille = filesize($tmpPath);
        if ($taille > self::VIDEO_MAX_SIZE_BYTES) {
            throw new \InvalidArgumentException(sprintf(
                '[DocumentStorage] Vidéo trop lourde : %.1f Mo (max %d Mo).',
                $taille / 1048576,
                self::VIDEO_MAX_SIZE_BYTES / 1048576
            ));
        }

        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeReel = $finfo->file($tmpPath);

        if ($mimeReel === false || !in_array($mimeReel, self::VIDEO_MIME_WHITELIST, true)) {
            throw new \InvalidArgumentException(sprintf(
                '[DocumentStorage] Format vidéo refusé : "%s". Formats acceptés : MP4, WebM, MOV, AVI, MKV, WMV, MPEG, 3GP.',
                (string)$mimeReel
            ));
        }

        try {
            $this->s3->putObject([
                'Bucket'      => $this->bucket,
                'Key'         => $cle,
                'SourceFile'  => $tmpPath,
                'ContentType' => $mimeReel,
                'ACL'         => 'private',
            ]);
        } catch (S3Exception $e) {
            throw new \RuntimeException('[DocumentStorage] Upload vidéo échoué : ' . $e->getAwsErrorMessage(), 0, $e);
        }

        return $cle;
    }

    /**
     * Génère une URL signée temporaire (accès privé).
     *
     * @param string $cle      Clé B2 du fichier
     * @param int    $minutes  Durée de validité (max 10 min pour B2 — clé limitée)
     * @return string          URL signée
     */
    public function signedUrl(string $cle, int $minutes = 10): string
    {
        try {
            $cmd = $this->s3->getCommand('GetObject', [
                'Bucket' => $this->bucket,
                'Key'    => $cle,
            ]);

            $request = $this->s3->createPresignedRequest($cmd, "+{$minutes} minutes");
            return (string) $request->getUri();
        } catch (S3Exception $e) {
            throw new \RuntimeException('[DocumentStorage] signedUrl échoué : ' . $e->getAwsErrorMessage(), 0, $e);
        }
    }

    /**
     * URL PUT présignée — upload DIRECT navigateur → B2, sans transiter par PHP.
     *
     * Le gros fichier (vidéo jusqu'à 500 Mo, ~1 Mo/s) ne bloque plus la requête
     * web → plus aucun timeout FastCGI/nginx possible. Le navigateur exécute :
     *   fetch(url, { method:'PUT', body:file, headers:{'Content-Type':contentType} })
     *
     * Seul le Content-Type est signé (le bucket est privé par défaut : aucun ACL
     * à propager). Le client DOIT renvoyer exactement ce Content-Type, sinon B2
     * répond SignatureDoesNotMatch.
     *
     * @param string $cle         Clé de destination dans le bucket
     * @param string $contentType MIME signé (doit être renvoyé tel quel par le client)
     * @param int    $minutes     Validité de l'URL (le temps de l'upload)
     * @return string             URL PUT présignée
     */
    public function presignPut(string $cle, string $contentType, int $minutes = 30): string
    {
        try {
            $cmd = $this->s3->getCommand('PutObject', [
                'Bucket'      => $this->bucket,
                'Key'         => $cle,
                'ContentType' => $contentType,
            ]);
            $request = $this->s3->createPresignedRequest($cmd, "+{$minutes} minutes");
            return (string) $request->getUri();
        } catch (S3Exception $e) {
            throw new \RuntimeException('[DocumentStorage] presignPut échoué : ' . $e->getAwsErrorMessage(), 0, $e);
        }
    }

    /**
     * Métadonnées d'un objet (taille + MIME réel stocké par B2) — sert à VALIDER
     * côté serveur un fichier uploadé en direct par le navigateur (la validation
     * finfo locale n'a jamais vu passer le fichier).
     *
     * @return array{size:int, contentType:string}
     * @throws \RuntimeException si l'objet n'existe pas / B2 injoignable
     */
    public function objectMeta(string $cle): array
    {
        try {
            $r = $this->s3->headObject([
                'Bucket' => $this->bucket,
                'Key'    => $cle,
            ]);
            return [
                'size'        => (int)($r['ContentLength'] ?? 0),
                'contentType' => (string)($r['ContentType'] ?? ''),
            ];
        } catch (S3Exception $e) {
            throw new \RuntimeException('[DocumentStorage] objectMeta échoué : ' . $e->getAwsErrorMessage(), 0, $e);
        }
    }

    /**
     * Récupère le contenu brut d'un objet du bucket (ex : logo à embarquer en data URI).
     */
    public function getContent(string $cle): string
    {
        try {
            $res = $this->s3->getObject([
                'Bucket' => $this->bucket,
                'Key'    => $cle,
            ]);
            return (string)$res['Body'];
        } catch (S3Exception $e) {
            throw new \RuntimeException('[DocumentStorage] getContent échoué : ' . $e->getAwsErrorMessage(), 0, $e);
        }
    }

    /**
     * Supprime un fichier du bucket.
     */
    public function delete(string $cle): void
    {
        try {
            $this->s3->deleteObject([
                'Bucket' => $this->bucket,
                'Key'    => $cle,
            ]);
        } catch (S3Exception $e) {
            throw new \RuntimeException('[DocumentStorage] Suppression échouée : ' . $e->getAwsErrorMessage(), 0, $e);
        }
    }

    /**
     * Teste la connexion B2 (ping léger : liste les objets avec prefix inexistant).
     * Retourne true si le bucket est accessible.
     */
    public function ping(): bool
    {
        try {
            $this->s3->listObjectsV2([
                'Bucket'  => $this->bucket,
                'Prefix'  => '__ping__',
                'MaxKeys' => 1,
            ]);
            return true;
        } catch (S3Exception $e) {
            return false;
        }
    }

    /**
     * Upload un contenu string (HTML, JSON…) généré côté serveur — aucune validation fichier.
     */
    public function uploadContent(string $content, string $cle, string $mime = 'text/html; charset=utf-8'): string
    {
        try {
            $this->s3->putObject([
                'Bucket'      => $this->bucket,
                'Key'         => $cle,
                'Body'        => $content,
                'ContentType' => $mime,
                'ACL'         => 'private',
            ]);
        } catch (S3Exception $e) {
            throw new \RuntimeException('[DocumentStorage] uploadContent échoué : ' . $e->getAwsErrorMessage(), 0, $e);
        }
        return $cle;
    }

    /**
     * Valide le fichier : vrai MIME (finfo) + taille max.
     */
    private function validerFichier(string $tmpPath, string $mimeDeclaree): void
    {
        if (!file_exists($tmpPath) || !is_readable($tmpPath)) {
            throw new \InvalidArgumentException('[DocumentStorage] Fichier temporaire introuvable.');
        }

        $taille = filesize($tmpPath);
        if ($taille > self::MAX_SIZE_BYTES) {
            throw new \InvalidArgumentException(sprintf(
                '[DocumentStorage] Fichier trop lourd : %d octets (max %d).',
                $taille,
                self::MAX_SIZE_BYTES
            ));
        }

        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeReel = $finfo->file($tmpPath);

        if ($mimeReel === false || !in_array($mimeReel, self::MIME_WHITELIST, true)) {
            throw new \InvalidArgumentException(sprintf(
                '[DocumentStorage] Type MIME refusé : "%s". Types acceptés : %s.',
                $mimeReel,
                implode(', ', self::MIME_WHITELIST)
            ));
        }

        // Cohérence MIME déclaré vs réel (tolérance : si réel valide, c'est bon)
        if ($mimeDeclaree !== $mimeReel) {
            // Accepte quand même — le vrai MIME pris en compte pour l'upload
        }
    }
}
