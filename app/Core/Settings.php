<?php
declare(strict_types=1);

namespace Core;

/**
 * Lit les paramètres applicatifs depuis la table `settings` (DB),
 * avec cache Redis (TTL 1 h). Fallback sur $_ENV si la clé n'existe pas en DB.
 *
 * Priorité : DB settings → $_ENV → valeur par défaut fournie.
 */
class Settings
{
    private const CACHE_TTL = 3600;
    private const CACHE_NS  = 'setting:';

    private static array $static = [];

    // ── Lecture ──────────────────────────────────────────────────────────────

    public static function get(string $key, mixed $default = null): mixed
    {
        // 1. Cache statique PHP (durée de la requête)
        if (array_key_exists($key, self::$static)) {
            return self::$static[$key];
        }

        // 2. Cache Redis
        $cacheKey = self::CACHE_NS . $key;
        $cached   = Cache::get($cacheKey);
        if ($cached !== null) {
            self::$static[$key] = $cached;
            return $cached;
        }

        // 3. Base de données
        try {
            $db   = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
            $stmt->execute([$key]);
            $val  = $stmt->fetchColumn();

            if ($val !== false) {
                Cache::set($cacheKey, (string) $val, self::CACHE_TTL);
                self::$static[$key] = (string) $val;
                return (string) $val;
            }
        } catch (\Exception $e) {
            // DB inaccessible — on continue vers le fallback
        }

        // 4. $_ENV fallback
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        return $default;
    }

    // ── Helpers métier ────────────────────────────────────────────────────────

    /**
     * Durée de la période gratuite en secondes.
     * Source primaire : settings.periode_gratuite_jours (en jours).
     * Fallback : $_ENV['PERIODE_GRATUITE_HEURES'] (en heures).
     */
    public static function getPeriodeGratuiteSecondes(): int
    {
        $jours = self::get('periode_gratuite_jours');
        if ($jours !== null) {
            return (int) $jours * 86400;
        }
        $heures = (int) ($_ENV['PERIODE_GRATUITE_HEURES'] ?? 24);
        return $heures * 3600;
    }

    /**
     * Prix de l'abonnement mensuel en XAF.
     */
    public static function getPrixMensuel(): float
    {
        $prix = self::get('prix_mensuel_xaf');
        if ($prix !== null) {
            return (float) $prix;
        }
        return (float) ($_ENV['PRIX_MENSUEL_XAF'] ?? 2000);
    }

    /**
     * Prix de l'abonnement annuel en XAF.
     */
    public static function getPrixAnnuel(): float
    {
        $prix = self::get('prix_annuel_xaf');
        if ($prix !== null) {
            return (float) $prix;
        }
        return (float) ($_ENV['PRIX_ANNUEL_XAF'] ?? 15000);
    }

    // ── Invalidation cache ────────────────────────────────────────────────────

    public static function forget(string $key): void
    {
        Cache::forget(self::CACHE_NS . $key);
        unset(self::$static[$key]);
    }

    public static function forgetAll(): void
    {
        Cache::flush(self::CACHE_NS . '*');
        self::$static = [];
    }
}
