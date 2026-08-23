<?php
declare(strict_types=1);

namespace Controllers;

use Core\MediaStorage;

/**
 * Service des médias tombés dans le repli privé `storage/uploads/{type}/`.
 *
 * En production, `public/uploads/` appartient au compte de déploiement alors
 * que PHP tourne en `www-data` : les images y sont alors écrites dans
 * `storage/`, que le serveur web refuse de servir en direct. Ce contrôleur les
 * restitue, avec exactement la même visibilité qu'un fichier de `public/`
 * (aucune régression fonctionnelle) mais sans jamais exposer `storage/`.
 *
 * Sécurité : `MediaStorage::absolutePath()` n'accepte qu'un type connu et un
 * nom de fichier `[a-f0-9]{32}.ext` généré par l'application, puis confine le
 * chemin résolu sous la racine autorisée — pas de traversée possible.
 */
class MediaController
{
    private const MIMES = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'bmp'  => 'image/bmp',
    ];

    public function show(string $type, string $fichier): void
    {
        $abs = MediaStorage::absolutePath($type, $fichier);

        if ($abs === null) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Introuvable';
            return;
        }

        $ext  = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        $mime = self::MIMES[$ext] ?? 'application/octet-stream';

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string)filesize($abs));
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: inline');
        // Nom aléatoire et contenu immuable : cache long côté navigateur.
        header('Cache-Control: public, max-age=604800, immutable');
        readfile($abs);
    }
}
