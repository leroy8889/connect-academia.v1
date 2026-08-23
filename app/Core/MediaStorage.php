<?php
declare(strict_types=1);

namespace Core;

use RuntimeException;

/**
 * Emplacement des médias uploadés par les utilisateurs (images de publications,
 * avatars).
 *
 * PROBLÈME RÉSOLU (prod)
 * ----------------------
 * `public/uploads/` est créé par le déploiement (utilisateur `administrator`)
 * alors que PHP-FPM tourne en `www-data` : `mkdir()` / `move_uploaded_file()`
 * échouent et l'utilisateur voit « Erreur upload image » au moment de publier.
 * Le dossier `storage/`, lui, est écrit en permanence par l'application
 * (journaux, sessions) : il est garanti accessible en écriture.
 *
 * Même stratégie que {@see RessourceStorage} pour les PDF pédagogiques :
 *   1. `public/uploads/{type}/` — emplacement historique servi en direct par le
 *      serveur web (aucune régression pour les fichiers déjà en base) ;
 *   2. `chmod 0775` si le dossier existe sans être ouvert en écriture ;
 *   3. repli `storage/uploads/{type}/`, servi par la route interne
 *      `/media/{type}/{fichier}` (le serveur web interdit `storage/` en direct,
 *      cf. .htaccess et nginx.conf).
 *
 * SÉCURITÉ
 * --------
 * · Les noms de fichiers sont générés (`random_bytes`), jamais fournis par le
 *   client : aucune traversée de chemin possible à l'écriture.
 * · `absolutePath()` n'accepte qu'un type connu et un nom strictement
 *   `[a-f0-9]{32}.ext`, puis vérifie par `realpath()` que le fichier résolu est
 *   bien SOUS la racine autorisée : un chemin corrompu en base ne peut pas
 *   faire lire un fichier arbitraire du serveur.
 */
final class MediaStorage
{
    /** Sous-dossiers autorisés (aucune valeur libre : liste blanche stricte). */
    public const TYPES = ['posts', 'avatars'];

    /** Préfixe stocké en base quand le fichier atterrit dans le repli privé. */
    public const PRIVATE_PREFIX = 'storage/uploads/';

    /**
     * Dossier d'écriture utilisable pour ce type de média.
     *
     * @return array{dir: string, stored: string} `stored` = préfixe à stocker en base
     * @throws RuntimeException si aucun emplacement n'est accessible en écriture
     */
    public static function destination(string $type): array
    {
        $type = self::assertType($type);

        $candidates = [
            '/public/uploads/' . $type . '/' => BASE_PATH . '/public/uploads/' . $type,
            self::PRIVATE_PREFIX . $type . '/' => BASE_PATH . '/' . self::PRIVATE_PREFIX . $type,
        ];

        $diagnostics = [];

        foreach ($candidates as $stored => $dir) {
            if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
                $diagnostics[] = $dir . ' (création impossible)';
                continue;
            }

            if (!is_writable($dir)) {
                @chmod($dir, 0775);           // n'aboutit que si PHP est propriétaire
                clearstatcache(true, $dir);
            }

            if (is_writable($dir)) {
                if (str_starts_with($stored, self::PRIVATE_PREFIX)) {
                    self::hardenPrivateDir($dir);
                }
                return ['dir' => rtrim($dir, '/') . '/', 'stored' => $stored];
            }

            $diagnostics[] = $dir . ' (owner=' . (@fileowner($dir) ?: '?')
                . ' perms=' . decoct(@fileperms($dir) & 0777) . ')';
        }

        throw new RuntimeException(
            'Aucun dossier de médias accessible en écriture : ' . implode(' ; ', $diagnostics)
        );
    }

    /** `true` si le chemin stocké pointe vers le repli privé (non servi en direct). */
    public static function isPrivate(string $storedPath): bool
    {
        return str_starts_with(ltrim($storedPath, '/'), self::PRIVATE_PREFIX);
    }

    /**
     * Chemin absolu d'un média du repli privé, ou null si le nom est invalide,
     * le type inconnu ou le fichier hors de la racine autorisée.
     */
    public static function absolutePath(string $type, string $fichier): ?string
    {
        try {
            $type = self::assertType($type);
        } catch (RuntimeException) {
            return null;
        }

        // Nom généré par l'application uniquement : 32 hex + extension connue.
        if (!preg_match('/^[a-f0-9]{32}\.(jpg|jpeg|png|gif|webp|avif|bmp)$/', $fichier)) {
            return null;
        }

        $base = realpath(BASE_PATH . '/' . self::PRIVATE_PREFIX . $type);
        if ($base === false) {
            return null;
        }

        $abs = realpath($base . '/' . $fichier);
        if ($abs === false || !str_starts_with($abs, $base . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return is_file($abs) ? $abs : null;
    }

    /**
     * URL publique d'un média : lien direct quand le fichier est dans `public/`,
     * route interne contrôlée quand il est tombé dans le repli `storage/`.
     */
    public static function url(string $type, string $storedPath): string
    {
        $storedPath = ltrim(trim($storedPath), '/');

        if (self::isPrivate($storedPath)) {
            return url('/media/' . $type . '/' . basename($storedPath));
        }

        return url('/' . $storedPath);
    }

    private static function assertType(string $type): string
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new RuntimeException('Type de média inconnu : ' . $type);
        }
        return $type;
    }

    /**
     * Défense en profondeur : `storage/` est déjà interdit à la racine
     * (.htaccess + nginx.conf), on repose un refus local au cas où le dossier
     * serait servi par une configuration tierce.
     */
    private static function hardenPrivateDir(string $dir): void
    {
        $htaccess = rtrim($dir, '/') . '/.htaccess';
        if (!is_file($htaccess)) {
            @file_put_contents(
                $htaccess,
                "Require all denied\n"
                . "<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n"
                . "Options -ExecCGI -Indexes\n"
            );
        }
    }
}
