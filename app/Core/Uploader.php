<?php
declare(strict_types=1);

namespace Core;

class Uploader
{
    /**
     * Formats d'image acceptés à la publication.
     *
     * Volontairement large (photos de téléphone incluses : HEIC/HEIF, AVIF) —
     * plus aucune image légitime n'est refusée. SVG reste EXCLU : un SVG est un
     * document XML pouvant embarquer du JavaScript, servi depuis notre domaine
     * ce serait un XSS stocké.
     */
    private const ALLOWED_IMAGE_MIME = [
        'image/jpeg', 'image/pjpeg', 'image/png', 'image/gif', 'image/webp',
        'image/avif', 'image/bmp', 'image/x-ms-bmp', 'image/tiff',
        'image/heic', 'image/heif',
    ];
    private const ALLOWED_DOC_MIME   = ['application/pdf'];

    // Dimensions max pour les avatars (px)
    private const AVATAR_MAX_PX = 800;
    // Qualité JPEG pour les avatars (0-100)
    private const AVATAR_QUALITY = 85;

    /**
     * @param int|null $maxBytes null = pas de limite applicative (seules les limites PHP serveur s'appliquent)
     */
    public function handleImage(array $file, string $dir, ?int $maxBytes = null): string
    {
        return $this->handle($file, $dir, self::ALLOWED_IMAGE_MIME, $maxBytes);
    }

    public function handleDocument(array $file, string $dir): string
    {
        $maxMb = (int) ($_ENV['MAX_UPLOAD_MB'] ?? 50);
        return $this->handle($file, $dir, self::ALLOWED_DOC_MIME, $maxMb * 1024 * 1024);
    }

    /**
     * Upload + redimensionnement GD pour les avatars.
     * - Accepte JPEG, PNG, GIF, WebP
     * - Redimensionne à max 800×800 en conservant le ratio
     * - Corrige l'orientation EXIF (photos de téléphone)
     * - Toujours enregistré en JPEG pour uniformité
     */
    public function handleAvatar(array $file): string
    {
        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException($this->uploadErrorMessage((int) $file['error']));
        }

        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new \RuntimeException("Fichier temporaire introuvable.");
        }

        $maxBytes = 15 * 1024 * 1024;
        if ((int) $file['size'] > $maxBytes) {
            throw new \RuntimeException("Cette image est trop lourde, veuillez en mettre une autre (max 15 Mo).");
        }

        // Vérification du type MIME réel
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, self::ALLOWED_IMAGE_MIME, true)) {
            throw new \RuntimeException("Format non autorisé. Utilisez une image JPEG, PNG, GIF ou WebP.");
        }

        // Chargement de l'image en mémoire avec GD
        $srcImage = match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($file['tmp_name']),
            'image/png'  => @imagecreatefrompng($file['tmp_name']),
            'image/gif'  => @imagecreatefromgif($file['tmp_name']),
            'image/webp' => @imagecreatefromwebp($file['tmp_name']),
            default      => false,
        };

        if ($srcImage === false) {
            throw new \RuntimeException("Impossible de traiter l'image. Vérifiez qu'elle n'est pas corrompue.");
        }

        // Correction orientation EXIF (photos prises avec un téléphone)
        if ($mimeType === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($file['tmp_name']);
            if (!empty($exif['Orientation']) && $exif['Orientation'] !== 1) {
                $srcImage = $this->fixExifOrientation($srcImage, (int) $exif['Orientation']);
            }
        }

        $srcW = imagesx($srcImage);
        $srcH = imagesy($srcImage);

        // Calcul des nouvelles dimensions (ratio conservé)
        $max = self::AVATAR_MAX_PX;
        if ($srcW > $max || $srcH > $max) {
            if ($srcW >= $srcH) {
                $newW = $max;
                $newH = (int) round($srcH * $max / $srcW);
            } else {
                $newH = $max;
                $newW = (int) round($srcW * $max / $srcH);
            }
        } else {
            $newW = $srcW;
            $newH = $srcH;
        }

        // Création du canvas de destination (fond blanc pour transparence PNG/WebP)
        $dstImage = imagecreatetruecolor($newW, $newH);
        $white    = imagecolorallocate($dstImage, 255, 255, 255);
        imagefill($dstImage, 0, 0, $white);

        imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
        imagedestroy($srcImage);

        // Dossier de destination (repli storage/ si public/uploads n'est pas
        // accessible en écriture — cf. MediaStorage).
        try {
            $dest = MediaStorage::destination('avatars');
        } catch (\RuntimeException $e) {
            imagedestroy($dstImage);
            error_log('[Uploader] ' . $e->getMessage());
            throw new \RuntimeException("Le serveur ne peut pas enregistrer l'image. Réessayez plus tard.");
        }

        $filename = bin2hex(random_bytes(16)) . '.jpg';
        $destPath = $dest['dir'] . $filename;

        $saved = @imagejpeg($dstImage, $destPath, self::AVATAR_QUALITY);
        imagedestroy($dstImage);

        if (!$saved) {
            error_log('[Uploader] Écriture avatar impossible dans ' . $dest['dir']);
            throw new \RuntimeException("Échec de l'enregistrement de l'image.");
        }
        @chmod($destPath, 0644);

        return $dest['stored'] . $filename;
    }

    private function handle(array $file, string $dir, array $allowedMimes, ?int $maxBytes = null): string
    {
        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException($this->uploadErrorMessage((int) $file['error']));
        }

        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new \RuntimeException("Fichier temporaire introuvable.");
        }

        if ($maxBytes !== null && (int) $file['size'] > $maxBytes) {
            $maxMb = (int) round($maxBytes / 1024 / 1024);
            throw new \RuntimeException("Fichier trop volumineux (max {$maxMb} Mo)");
        }

        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, $allowedMimes, true)) {
            throw new \RuntimeException(
                "Format non pris en charge (" . ($mimeType ?: 'inconnu') . ")."
            );
        }

        $extensions = [
            'image/jpeg'      => 'jpg',
            'image/pjpeg'     => 'jpg',
            'image/png'       => 'png',
            'image/gif'       => 'gif',
            'image/webp'      => 'webp',
            'image/avif'      => 'avif',
            'image/bmp'       => 'bmp',
            'image/x-ms-bmp'  => 'bmp',
            'image/tiff'      => 'jpg',   // converti en JPEG ci-dessous
            'image/heic'      => 'jpg',   // idem (photos iPhone)
            'image/heif'      => 'jpg',
            'application/pdf' => 'pdf',
        ];

        $ext      = $extensions[$mimeType] ?? 'bin';
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;

        // public/uploads/ appartient au compte de déploiement alors que PHP tourne
        // en www-data : MediaStorage bascule sur storage/uploads/ le cas échéant.
        $type = trim($dir, '/');
        try {
            $dest = MediaStorage::destination($type);
        } catch (\RuntimeException $e) {
            error_log('[Uploader] ' . $e->getMessage());
            throw new \RuntimeException("Le serveur ne peut pas enregistrer le fichier. Réessayez plus tard.");
        }

        $destPath = $dest['dir'] . $filename;

        // move_uploaded_file peut échouer (partition, ACL) là où copy() passe :
        // repli explicite avant d'abandonner.
        if (!@move_uploaded_file($file['tmp_name'], $destPath)
            && !(@copy($file['tmp_name'], $destPath) && @unlink($file['tmp_name']))) {
            $err = error_get_last()['message'] ?? 'raison inconnue';
            error_log('[Uploader] Écriture impossible dans ' . $dest['dir'] . ' : ' . $err);
            throw new \RuntimeException("Le serveur n'a pas pu enregistrer le fichier. Réessayez plus tard.");
        }

        @chmod($destPath, 0644);

        // Formats qu'aucun navigateur n'affiche (TIFF, HEIC/HEIF) : on les
        // réécrit en JPEG. Si GD ne sait pas les décoder, mieux vaut refuser
        // franchement que stocker une image qui ne s'affichera nulle part.
        if (!self::convertirSiNecessaire($destPath, (string)$mimeType)) {
            @unlink($destPath);
            throw new \RuntimeException(
                "Ce format de photo n'est pas pris en charge par le serveur. "
                . "Enregistrez-la en JPEG ou PNG puis réessayez."
            );
        }

        return $dest['stored'] . $filename;
    }

    /**
     * Réécrit en JPEG les formats qu'aucun navigateur n'affiche (TIFF,
     * HEIC/HEIF). Retourne false si GD ne sait pas décoder le fichier —
     * l'appelant refuse alors l'upload plutôt que de stocker une image morte.
     * Les formats directement affichables passent sans traitement.
     */
    private static function convertirSiNecessaire(string $path, string $mimeType): bool
    {
        if (!in_array($mimeType, ['image/tiff', 'image/heic', 'image/heif'], true)) {
            return true;
        }
        $data = @file_get_contents($path);
        if ($data === false) {
            return false;
        }
        $img = @imagecreatefromstring($data);
        if ($img === false) {
            return false;
        }
        $ok = @imagejpeg($img, $path, 88);
        imagedestroy($img);
        return (bool)$ok;
    }

    /**
     * Corrige l'orientation d'une image GD selon la valeur EXIF.
     */
    private function fixExifOrientation(\GdImage $image, int $orientation): \GdImage
    {
        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle !== 0) {
            $rotated = imagerotate($image, $angle, 0);
            if ($rotated !== false) {
                imagedestroy($image);
                return $rotated;
            }
        }

        return $image;
    }

    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE   => "Le fichier dépasse la limite serveur (" . ini_get('upload_max_filesize') . ")",
            UPLOAD_ERR_FORM_SIZE  => "Le fichier dépasse la limite du formulaire",
            UPLOAD_ERR_PARTIAL    => "Transfert partiel, veuillez réessayer",
            UPLOAD_ERR_NO_FILE    => "Aucun fichier reçu",
            UPLOAD_ERR_NO_TMP_DIR => "Dossier temporaire manquant",
            UPLOAD_ERR_CANT_WRITE => "Erreur d'écriture sur le serveur",
            default               => "Erreur d'upload (code: {$code})",
        };
    }
}
