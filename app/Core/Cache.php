<?php
declare(strict_types=1);

namespace Core;

/**
 * Service de cache Redis.
 * Toutes les méthodes échouent silencieusement si Redis est indisponible.
 * Clés préfixées par 'cache:' (en plus du prefix global Redis).
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
        // false = clé absente en Redis ; toute autre valeur (y compris null) = hit
        return $value !== false ? $value : $default;
    }

    public static function has(string $key): bool
    {
        $redis = Redis::getInstance();
        return $redis->isAvailable() && $redis->exists(self::NS . $key);
    }

    // ── Écriture ─────────────────────────────────────────────────────────────

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
     *
     * CORRECTION : utilise false (clé absente en Redis) comme sentinelle de miss,
     * pas null — permet de cacher légitimement une valeur null sans relancer le callback.
     */
    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        $redis = Redis::getInstance();
        if ($redis->isAvailable()) {
            $value = $redis->get(self::NS . $key);
            if ($value !== false) {
                return $value;
            }
        }
        $value = $callback();
        self::set($key, $value, $ttl);
        return $value;
    }

    /**
     * Cache sans expiration. Même logique false-as-miss que remember().
     */
    public static function rememberForever(string $key, callable $callback): mixed
    {
        $redis = Redis::getInstance();
        if ($redis->isAvailable()) {
            $value = $redis->get(self::NS . $key);
            if ($value !== false) {
                return $value;
            }
        }
        $value = $callback();
        self::set($key, $value, 0);
        return $value;
    }

    // ── Multi-get (un seul round-trip) ────────────────────────────────────────

    /**
     * Récupère plusieurs clés en une seule commande MGET.
     * Retourne [key => value] — $default pour les clés absentes.
     */
    public static function getMultiple(array $keys, mixed $default = null): array
    {
        $redis = Redis::getInstance();
        if (!$redis->isAvailable() || empty($keys)) {
            return array_fill_keys($keys, $default);
        }

        $nsKeys = array_map(fn(string $k) => self::NS . $k, $keys);
        $raw    = $redis->mGet($nsKeys); // [nsKey => value|false]

        $result = [];
        foreach ($keys as $k) {
            $v          = $raw[self::NS . $k] ?? false;
            $result[$k] = $v !== false ? $v : $default;
        }
        return $result;
    }

    // ── Compteurs ────────────────────────────────────────────────────────────

    public static function increment(string $key, int $by = 1): int
    {
        $redis = Redis::getInstance();
        if (!$redis->isAvailable()) {
            return 0;
        }
        $result = $redis->incrBy(self::NS . $key, $by);
        return $result !== false ? (int)$result : 0;
    }

    public static function decrement(string $key, int $by = 1): int
    {
        $redis = Redis::getInstance();
        if (!$redis->isAvailable()) {
            return 0;
        }
        $result = $redis->decrBy(self::NS . $key, $by);
        return $result !== false ? (int)$result : 0;
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
     * Supprime toutes les clés correspondant au pattern (ex: 'admin:dashboard:*').
     * Utilise SCAN (non-bloquant) + DEL batch via le client natif.
     */
    public static function flush(string $pattern = '*'): void
    {
        $redis = Redis::getInstance();
        if (!$redis->isAvailable()) {
            return;
        }

        $keys = $redis->keys(self::NS . $pattern);
        if (empty($keys)) {
            return;
        }

        $client = $redis->client();
        if ($client === null) {
            return;
        }

        // DEL accepte plusieurs clés — suppression en batches de 500 (un round-trip par batch)
        foreach (array_chunk($keys, 500) as $chunk) {
            $client->del($chunk);
        }
    }

    // ── Helpers TTL ──────────────────────────────────────────────────────────

    public static function ttl(string $key): int
    {
        $redis = Redis::getInstance();
        return $redis->isAvailable() ? $redis->ttl(self::NS . $key) : -2;
    }
}
