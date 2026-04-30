<?php
declare(strict_types=1);

namespace Core;

class Uploader
{
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private const MAX_SIZE_MB  = 10;

    public function handle(array $file, string $dir): string
    {
        // Vérifier les erreurs d'upload PHP
        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException($this->getUploadErrorMessage((int) $file['error']));
        }

        // Vérifier que le fichier temporaire existe vraiment
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new \RuntimeException("Fichier temporaire introuvable. Veuillez réessayer.");
        }

        // Vérifier la taille
        $maxMb   = (int) ($_ENV['MAX_UPLOAD_MB'] ?? self::MAX_SIZE_MB);
        $maxSize = $maxMb * 1024 * 1024;
        if ((int) $file['size'] > $maxSize) {
            throw new \RuntimeException("Image trop volumineuse (max {$maxMb} Mo)");
        }

        // Vérification MIME réelle (via le contenu du fichier, pas le Content-Type)
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, self::ALLOWED_MIME, true)) {
            throw new \RuntimeException("Format non autorisé. Utilisez JPG, PNG, GIF ou WEBP.");
        }

        // Nom de fichier sécurisé et aléatoire
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        ];
        $ext      = $extensions[$mimeType];
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;

        // Créer le répertoire de destination si nécessaire
        $destDir = BASE_PATH . '/public/uploads/' . $dir;
        if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
            throw new \RuntimeException("Impossible de créer le dossier de destination.");
        }

        $destPath = $destDir . '/' . $filename;

        // Déplacer le fichier uploadé vers sa destination finale
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new \RuntimeException("Échec du déplacement de l'image. Vérifiez les permissions du dossier uploads/.");
        }

        return '/public/uploads/' . $dir . '/' . $filename;
    }

    /**
     * Retourne un message d'erreur lisible selon le code d'erreur PHP.
     */
    private function getUploadErrorMessage(int $code): string
    {
        $messages = [
            UPLOAD_ERR_INI_SIZE   => 'L\'image dépasse la limite autorisée par le serveur (' . ini_get('upload_max_filesize') . ').',
            UPLOAD_ERR_FORM_SIZE  => 'L\'image dépasse la limite autorisée par le formulaire.',
            UPLOAD_ERR_PARTIAL    => 'L\'image n\'a été que partiellement transférée. Veuillez réessayer.',
            UPLOAD_ERR_NO_FILE    => 'Aucun fichier reçu.',
            UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant sur le serveur.',
            UPLOAD_ERR_CANT_WRITE => 'Impossible d\'écrire le fichier sur le serveur (permissions).',
            UPLOAD_ERR_EXTENSION  => 'Upload bloqué par une extension PHP.',
        ];
        return $messages[$code] ?? "Erreur inconnue lors de l'upload (code : {$code}).";
    }
}

