<?php
declare(strict_types=1);

namespace Core;

use RuntimeException;

/**
 * Emplacement des PDF de la bibliothèque pédagogique.
 *
 * PROBLÈME RÉSOLU (prod, juil. 2026)
 * ----------------------------------
 * `public/uploads/ressources/` est créé par le déploiement (utilisateur
 * `administrator`) alors que PHP tourne en `www-data` : `is_writable()` renvoie
 * false et l'admin voit « Dossier de destination non accessible ». Le dossier
 * `storage/`, lui, est écrit en permanence par l'application (journaux,
 * sessions) : il est donc garanti accessible en écriture.
 *
 * Stratégie, dans l'ordre :
 *   1. `public/uploads/ressources/` — emplacement historique, servi en direct
 *      par le serveur web (aucune régression pour les fichiers déjà en base) ;
 *   2. tentative de `chmod 0775` si le dossier existe mais n'est pas ouvert en
 *      écriture (fonctionne quand PHP est propriétaire) ;
 *   3. repli sur `storage/uploads/ressources/`, servi par la route interne
 *      `/ressources/fichier/{id}` (le serveur web bloque `storage/` en direct,
 *      cf. .htaccess et nginx.conf — le contrôle d'accès reste donc entier).
 *
 * SÉCURITÉ
 * --------
 * · Les noms de fichiers sont générés (`random_bytes`), jamais fournis par le
 *   client : aucune traversée de chemin à l'écriture.
 * · `absolutePath()` résout via `realpath()` puis VÉRIFIE que le résultat est
 *   bien contenu dans l'une des deux racines autorisées : un `fichier_path`
 *   corrompu en base ne peut pas faire lire /etc/passwd.
 * · Le repli `storage/` n'est jamais atteignable en direct depuis le web.
 */
final class RessourceStorage
{
    /** Chemin relatif (à la racine projet) stocké en base pour l'emplacement public. */
    public const PUBLIC_PREFIX = 'uploads/ressources/';

    /** Chemin relatif stocké en base pour le repli privé. */
    public const PRIVATE_PREFIX = 'storage/uploads/ressources/';

    /**
     * Dossier d'écriture utilisable, avec le préfixe à stocker en base.
     *
     * @return array{dir: string, prefix: string}
     * @throws RuntimeException si aucun emplacement n'est accessible en écriture
     */
    public static function destination(): array
    {
        $candidates = [
            self::PUBLIC_PREFIX  => BASE_PATH . '/public/' . self::PUBLIC_PREFIX,
            self::PRIVATE_PREFIX => BASE_PATH . '/' . self::PRIVATE_PREFIX,
        ];

        $diagnostics = [];

        foreach ($candidates as $prefix => $dir) {
            if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
                $diagnostics[] = $dir . ' (création impossible)';
                continue;
            }

            // Le dossier existe mais n'est pas ouvert en écriture : tentative de
            // correction (n'aboutit que si PHP en est propriétaire).
            if (!is_writable($dir)) {
                @chmod($dir, 0775);
                clearstatcache(true, $dir);
            }

            if (is_writable($dir)) {
                if ($prefix === self::PRIVATE_PREFIX) {
                    self::hardenPrivateDir($dir);
                }
                return ['dir' => rtrim($dir, '/') . '/', 'prefix' => $prefix];
            }

            $diagnostics[] = $dir . ' (owner=' . (@fileowner($dir) ?: '?')
                . ' perms=' . decoct(@fileperms($dir) & 0777) . ')';
        }

        throw new RuntimeException(
            'Aucun dossier de ressources accessible en écriture : ' . implode(' ; ', $diagnostics)
        );
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

    /**
     * `true` si le chemin stocké pointe vers le repli privé (non servi en direct).
     */
    public static function isPrivate(string $storedPath): bool
    {
        return str_starts_with(ltrim($storedPath, '/'), self::PRIVATE_PREFIX);
    }

    /**
     * Chemin absolu sur disque d'un `fichier_path` de la table `ressources`,
     * ou null si le fichier est introuvable / hors des racines autorisées.
     */
    public static function absolutePath(string $storedPath): ?string
    {
        $storedPath = ltrim(trim($storedPath), '/');
        if ($storedPath === '' || preg_match('#^https?://#i', $storedPath)) {
            return null;
        }

        if (self::isPrivate($storedPath)) {
            $base      = realpath(BASE_PATH . '/' . self::PRIVATE_PREFIX);
            $candidate = BASE_PATH . '/' . $storedPath;
        } else {
            $base      = realpath(BASE_PATH . '/public');
            $candidate = BASE_PATH . '/public/' . $storedPath;
        }

        $abs = realpath($candidate);
        if ($abs === false || $base === false) {
            return null;
        }

        // Confinement strict : le chemin résolu doit être SOUS la racine autorisée.
        if (!str_starts_with($abs, $base . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return is_file($abs) ? $abs : null;
    }

    /**
     * URL de consultation d'une ressource : lien direct quand le fichier est
     * dans `public/`, route interne contrôlée quand il est dans `storage/`.
     */
    public static function url(int $ressourceId, string $storedPath): string
    {
        $storedPath = ltrim(trim($storedPath), '/');
        if ($storedPath === '') {
            return '#';
        }
        if (preg_match('#^https?://#i', $storedPath)) {
            return $storedPath; // ressource externe (YouTube, Vimeo…)
        }
        if (self::isPrivate($storedPath)) {
            return url('/ressources/fichier/' . $ressourceId);
        }

        return url('/public/' . $storedPath);
    }
}
