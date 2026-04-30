<?php
declare(strict_types=1);

namespace Core;

/**
 * Service de cache Redis.
 * Toutes les méthodes échouent silencieusement si Redis est indisponible.
 * Les clés sont préfixées par 'cache:' (en plus du prefix global Redis).
 */
class Cache
{
    private const NS = 'cache:';

    // ── Lecture ─────────────────────────────────────────────────────────────

    public static function get(string $key, mixed $default = null): mixed
    {
        $redis = Redis::getInstance();
        if (!$redis->isAvailable()) {
            return $default;
        }

        $value = $redis->get(self::NS . $key);
        return $value !== false ? $value : $default;
    }

    public static function has(string $key): bool
    {
        $redis = Redis::getInstance();
        return $redis->isAvailable() && $redis->exists(self::NS . $key);
    }

    // ── Écriture ─────────────────────────────────────────────────────────────

    /**
     * @param int $ttl Durée en secondes (0 = pas d'expiration)
     */
    public static function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        $redis = Redis::getInstance();
        if (!$redis->isAvailable()) {
            return false;
        }
        return $redis->set(self::NS . $key, $value, $ttl);
    }

    // ── Remember ─────────────────────────────────────────────────────────────

    /**
     * Retourne la valeur en cache ou l'hydrate via $callback puis la met en cache.
     */
    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        $cached = self::get($key);
        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();
        self::set($key, $value, $ttl);
        return $value;
    }

    /**
     * Même comportement que remember() mais invalide le cache si $callback
     * retourne false ou null.
     */
    public static function rememberForever(string $key, callable $callback): mixed
    {
        $cached = self::get($key);
        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();
        if ($value !== null && $value !== false) {
            self::set($key, $value, 0);
        }
        return $value;
    }

    // ── Suppression ──────────────────────────────────────────────────────────

    public static function forget(string $key): bool
    {
        $redis = Redis::getInstance();
        if (!$redis->isAvailable()) {
            return false;
        }
        return $redis->del(self::NS . $key) > 0;
    }

    /**
     * Supprime toutes les clés correspondant au pattern (ex: 'dashboard:*').
     */
    public static function flush(string $pattern = '*'): void
    {
        $redis = Redis::getInstance();
        if (!$redis->isAvailable()) {
            return;
        }

        $keys = $redis->keys(self::NS . $pattern);
        foreach ($keys as $fullKey) {
            // Les clés retournées par keys() sont déjà préfixées par Redis::prefix()
            // On utilise delRaw() car le prefix global est déjà présent
            $redis->delRaw($fullKey);
        }
    }

    // ── Helpers TTL standards ────────────────────────────────────────────────

    public static function ttl(string $key): int
    {
        $redis = Redis::getInstance();
        return $redis->isAvailable() ? $redis->ttl(self::NS . $key) : -2;
    }
}
