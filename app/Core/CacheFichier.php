<?php
declare(strict_types=1);

namespace Core;

use Throwable;

/**
 * Cache sur disque — repli de `Core\Cache` quand Redis est indisponible.
 *
 * ── POURQUOI ──────────────────────────────────────────────────────────────
 * `Core\Cache` était exclusivement Redis : sans Redis, `remember()` rappelait
 * son callback À CHAQUE APPEL, sans jamais rien mémoriser. Ce n'était pas un
 * cache dégradé, c'était une absence totale de cache.
 *
 * Conséquence concrète et coûteuse : `LogoDataUri` et `SignatureDataUri`
 * retéléchargeaient le logo et le cachet de l'établissement depuis Backblaze à
 * CHAQUE rendu de récépissé, d'attestation ou de reçu. Une série de générations
 * de documents a suffi à épuiser le plafond de transactions du compte B2, qui
 * a alors refusé TOUTES les lectures — plus une seule image sur la plateforme.
 *
 * Le disque local ne remplace pas Redis (pas de partage entre serveurs, pas de
 * verrou), mais il transforme « aucun cache » en « cache correct sur une
 * machine ». C'est exactement ce qui manquait.
 *
 * ── GARANTIES ─────────────────────────────────────────────────────────────
 *  · Ne lève JAMAIS. Comme la version Redis, un cache en panne se traduit par
 *    un miss, jamais par une erreur remontée à l'appelant.
 *  · Écriture atomique (fichier temporaire + rename) : un lecteur concurrent
 *    ne voit jamais un fichier à moitié écrit.
 *  · `unserialize` avec `allowed_classes: false` : aucune instanciation
 *    d'objet, même si un fichier du répertoire était altéré.
 *  · Les noms de fichiers sont des empreintes hexadécimales : aucune donnée
 *    d'appelant n'atteint le système de fichiers, donc aucune traversée de
 *    chemin possible.
 *  · Le répertoire vit sous `storage/`, bloqué côté web par le `.htaccess`
 *    Apache et par le snippet nginx de production.
 */
final class CacheFichier
{
    /** Répertoire résolu une fois par process, ou null si inutilisable. */
    private static ?string $racine = null;
    private static bool    $resolu = false;

    /**
     * Une entrée sur N déclenche une purge des fichiers périmés. Purger à
     * chaque écriture coûterait plus cher que le cache ne rapporte.
     */
    private const PURGE_SUR = 200;

    /** Au-delà, la valeur n'est pas mise en cache disque (documents massifs). */
    private const TAILLE_MAX = 8_388_608; // 8 Mio

    public static function disponible(): bool
    {
        return self::racine() !== null;
    }

    /**
     * Valeur en cache, ou $defaut si absente ou périmée.
     * Une entrée périmée est supprimée au passage.
     */
    public static function get(string $cle, mixed $defaut = null): mixed
    {
        $fichier = self::fichier($cle);
        if ($fichier === null || !is_file($fichier)) {
            return $defaut;
        }

        try {
            $brut = @file_get_contents($fichier);
            if ($brut === false || $brut === '') {
                return $defaut;
            }

            $entree = @unserialize($brut, ['allowed_classes' => false]);
            if (!is_array($entree) || !array_key_exists('v', $entree)) {
                @unlink($fichier);
                return $defaut;
            }

            $expire = (int)($entree['e'] ?? 0);
            if ($expire > 0 && $expire < time()) {
                @unlink($fichier);
                return $defaut;
            }

            return $entree['v'];
        } catch (Throwable) {
            return $defaut;
        }
    }

    /** L'entrée existe-t-elle et est-elle encore valide ? */
    public static function has(string $cle): bool
    {
        $sentinelle = new \stdClass();

        return self::get($cle, $sentinelle) !== $sentinelle;
    }

    /**
     * Écrit une entrée. $ttl à 0 = sans expiration.
     * Retourne false si le cache disque est inutilisable — jamais d'exception.
     */
    public static function set(string $cle, mixed $valeur, int $ttl = 3600): bool
    {
        $fichier = self::fichier($cle);
        if ($fichier === null) {
            return false;
        }

        try {
            $charge = serialize([
                // La clé d'origine est conservée : le nom de fichier est une
                // empreinte, et flush('prefixe:*') n'aurait sinon aucun moyen
                // de savoir à quoi correspond chaque entrée.
                'k' => $cle,
                'e' => $ttl > 0 ? time() + $ttl : 0,
                'v' => $valeur,
            ]);

            // Un document volumineux n'a rien à faire sur le disque de cache :
            // il s'y accumulerait sans jamais être relu assez souvent.
            if (strlen($charge) > self::TAILLE_MAX) {
                return false;
            }

            $dossier = dirname($fichier);
            if (!is_dir($dossier) && !@mkdir($dossier, 0775, true) && !is_dir($dossier)) {
                return false;
            }

            // Écriture atomique : un lecteur concurrent voit l'ancienne version
            // ou la nouvelle, jamais un fichier tronqué.
            $temp = $fichier . '.' . bin2hex(random_bytes(4)) . '.tmp';
            if (@file_put_contents($temp, $charge, LOCK_EX) === false) {
                @unlink($temp);
                return false;
            }
            if (!@rename($temp, $fichier)) {
                @unlink($temp);
                return false;
            }

            if (random_int(1, self::PURGE_SUR) === 1) {
                self::purger();
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public static function forget(string $cle): bool
    {
        $fichier = self::fichier($cle);

        return $fichier !== null && is_file($fichier) && @unlink($fichier);
    }

    /**
     * Supprime les entrées dont la clé correspond au motif (`prefixe:*`).
     *
     * Le nom de fichier étant une empreinte, le motif ne peut pas être appliqué
     * au système de fichiers : on relit l'en-tête de chaque entrée, qui
     * conserve sa clé d'origine. Opération rare (invalidation de section), sur
     * un cache local de taille modeste.
     */
    public static function flush(string $motif = '*'): void
    {
        $racine = self::racine();
        if ($racine === null) {
            return;
        }

        $regex = '/^' . str_replace('\*', '.*', preg_quote($motif, '/')) . '$/';

        foreach (self::fichiers() as $fichier) {
            try {
                $brut = @file_get_contents($fichier);
                if ($brut === false) {
                    continue;
                }
                $entree = @unserialize($brut, ['allowed_classes' => false]);
                $cle    = is_array($entree) ? (string)($entree['k'] ?? '') : '';

                if ($motif === '*' || ($cle !== '' && preg_match($regex, $cle) === 1)) {
                    @unlink($fichier);
                }
            } catch (Throwable) {
                // Entrée illisible : on la retire, elle ne sert plus à rien.
                @unlink($fichier);
            }
        }
    }

    /** Secondes restantes : -2 absente, -1 sans expiration, sinon le reste. */
    public static function ttl(string $cle): int
    {
        $fichier = self::fichier($cle);
        if ($fichier === null || !is_file($fichier)) {
            return -2;
        }

        try {
            $entree = @unserialize((string)@file_get_contents($fichier), ['allowed_classes' => false]);
            if (!is_array($entree)) {
                return -2;
            }

            $expire = (int)($entree['e'] ?? 0);
            if ($expire === 0) {
                return -1;
            }

            return max(-2, $expire - time());
        } catch (Throwable) {
            return -2;
        }
    }

    // ── Interne ──────────────────────────────────────────────────────────────

    /** Répertoire de cache, créé au besoin. Null s'il est inutilisable. */
    private static function racine(): ?string
    {
        if (self::$resolu) {
            return self::$racine;
        }
        self::$resolu = true;

        $base = defined('BASE_PATH') ? (string)BASE_PATH : dirname(__DIR__, 2);
        $chemin = $base . '/storage/cache';

        try {
            if (!is_dir($chemin) && !@mkdir($chemin, 0775, true) && !is_dir($chemin)) {
                self::$racine = null;
                return null;
            }

            if (!is_writable($chemin)) {
                error_log('[CacheFichier] Répertoire non inscriptible : ' . $chemin);
                self::$racine = null;
                return null;
            }

            self::$racine = $chemin;
        } catch (Throwable $e) {
            error_log('[CacheFichier] Répertoire indisponible : ' . $e->getMessage());
            self::$racine = null;
        }

        return self::$racine;
    }

    /**
     * Chemin du fichier d'une clé. Deux niveaux de sous-répertoires : un
     * dossier plat à dizaines de milliers d'entrées ralentit chaque ouverture.
     */
    private static function fichier(string $cle): ?string
    {
        $racine = self::racine();
        if ($racine === null) {
            return null;
        }

        $empreinte = sha1($cle);

        return $racine . '/' . substr($empreinte, 0, 2) . '/' . substr($empreinte, 2) . '.cache';
    }

    /** @return list<string> */
    private static function fichiers(): array
    {
        $racine = self::racine();
        if ($racine === null) {
            return [];
        }

        $trouves = @glob($racine . '/*/*.cache');

        return $trouves === false ? [] : $trouves;
    }

    /** Retire les entrées expirées. Appelée au hasard, jamais bloquante. */
    private static function purger(): void
    {
        $maintenant = time();

        foreach (self::fichiers() as $fichier) {
            try {
                $entree = @unserialize((string)@file_get_contents($fichier), ['allowed_classes' => false]);
                if (!is_array($entree)) {
                    @unlink($fichier);
                    continue;
                }
                $expire = (int)($entree['e'] ?? 0);
                if ($expire > 0 && $expire < $maintenant) {
                    @unlink($fichier);
                }
            } catch (Throwable) {
                @unlink($fichier);
            }
        }
    }
}
