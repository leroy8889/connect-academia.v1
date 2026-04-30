<?php
declare(strict_types=1);

namespace Core;

class Uploader
{
    private const ALLOWED_IMAGE_MIME = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private const ALLOWED_DOC_MIME   = ['application/pdf'];

    // Dimensions max pour les avatars (px)
    private const AVATAR_MAX_PX = 800;
    // Qualité JPEG pour les avatars (0-100)
    private const AVATAR_QUALITY = 85;

    public function handleImage(array $file, string $dir): string
    {
        return $this->handle($file, $dir, self::ALLOWED_IMAGE_MIME);
    }

    public function handleDocument(array $file, string $dir): string
    {
        return $this->handle($file, $dir, self::ALLOWED_DOC_MIME);
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

        // Limite pratique : 20 Mo pour un avatar (largement suffisant)
        $maxBytes = 20 * 1024 * 1024;
        if ((int) $file['size'] > $maxBytes) {
            throw new \RuntimeException("Photo trop volumineuse (max 20 Mo). Compressez-la avant l'envoi.");
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

        // Dossier de destination
        $destDir = BASE_PATH . '/public/uploads/avatars';
        if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
            imagedestroy($dstImage);
            throw new \RuntimeException("Impossible de créer le dossier de destination.");
        }

        $filename = bin2hex(random_bytes(16)) . '.jpg';
        $destPath = $destDir . '/' . $filename;

        $saved = imagejpeg($dstImage, $destPath, self::AVATAR_QUALITY);
        imagedestroy($dstImage);

        if (!$saved) {
            throw new \RuntimeException("Échec de l'enregistrement de l'image.");
        }

        return '/public/uploads/avatars/' . $filename;
    }

    private function handle(array $file, string $dir, array $allowedMimes): string
    {
        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException($this->uploadErrorMessage((int) $file['error']));
        }

        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new \RuntimeException("Fichier temporaire introuvable.");
        }

        $maxMb = (int) ($_ENV['MAX_UPLOAD_MB'] ?? 50);
        if ((int) $file['size'] > $maxMb * 1024 * 1024) {
            throw new \RuntimeException("Fichier trop volumineux (max {$maxMb} Mo)");
        }

        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, $allowedMimes, true)) {
            throw new \RuntimeException("Format non autorisé.");
        }

        $extensions = [
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
            'image/gif'       => 'gif',
            'image/webp'      => 'webp',
            'application/pdf' => 'pdf',
        ];

        $ext      = $extensions[$mimeType] ?? 'bin';
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $destDir  = BASE_PATH . '/public/uploads/' . trim($dir, '/');

        if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
            throw new \RuntimeException("Impossible de créer le dossier de destination.");
        }

        if (!move_uploaded_file($file['tmp_name'], $destDir . '/' . $filename)) {
            throw new \RuntimeException("Échec du déplacement du fichier.");
        }

        return '/public/uploads/' . trim($dir, '/') . '/' . $filename;
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
