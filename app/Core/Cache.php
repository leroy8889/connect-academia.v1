<?php
declare(strict_types=1);

namespace Core;

/**
 * Service de cache : Redis en premier, disque en repli.
 *
 * Toutes les méthodes échouent silencieusement — un cache en panne se traduit
 * par un miss, jamais par une erreur remontée à l'appelant. Clés préfixées par
 * 'cache:' (en plus du prefix global Redis).
 *
 * ── Le repli disque, et pourquoi il existe ────────────────────────────────
 * Cette classe était exclusivement Redis. Sans Redis, `remember()` rappelait
 * son callback à chaque appel sans jamais rien mémoriser : ce n'était pas un
 * cache dégradé, c'était une absence totale de cache, invisible depuis les
 * appelants qui croyaient en avoir un.
 *
 * Le prix a été payé sur `LogoDataUri` et `SignatureDataUri`, qui
 * retéléchargeaient le logo et le cachet depuis Backblaze à chaque rendu de
 * document. Une série de générations a épuisé le plafond de transactions du
 * compte B2, qui a refusé toutes les lectures — plus une seule image sur la
 * plateforme.
 *
 * Redis reste le chemin normal : quand il répond, le comportement est
 * strictement identique à avant, y compris pour les compteurs atomiques que le
 * disque ne sait pas rendre. Le disque n'entre en jeu QUE lorsque Redis est
 * absent, et vaut infiniment mieux que rien.
 */
class Cache
{
    private const NS = 'cache:';

    // ── Lecture ─────────────────────────────────────────────────────────────

    public static function get(string $key, mixed $default = null): mixed
    {
        $redis = Redis::getInstance();
        if (!$redis->isAvailable()) {
            return CacheFichier::get(self::NS . $key, $default);
        }
        $value = $redis->get(self::NS . $key);
        // false = clé absente en Redis ; toute autre valeur (y compris null) = hit
        return $value !== false ? $value : $default;
    }

    public static function has(string $key): bool
    {
        $redis = Redis::getInstance();
        if (!$redis->isAvailable()) {
            return CacheFichier::has(self::NS . $key);
        }
        return $redis->exists(self::NS . $key);
    }

    // ── Écriture ─────────────────────────────────────────────────────────────

    public static function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        $redis = Redis::getInstance();
        if (!$redis->isAvailable()) {
            return CacheFichier::set(self::NS . $key, $value, $ttl);
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
        } else {
            // Même sentinelle que côté Redis : une valeur null légitimement
            // mise en cache doit compter comme un hit, sans quoi le callback
            // repart à chaque appel — précisément le défaut corrigé ici.
            $absent = new \stdClass();
            $value  = CacheFichier::get(self::NS . $key, $absent);
            if ($value !== $absent) {
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
        } else {
            $absent = new \stdClass();
            $value  = CacheFichier::get(self::NS . $key, $absent);
            if ($value !== $absent) {
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
        if (empty($keys)) {
            return [];
        }
        if (!$redis->isAvailable()) {
            $resultat = [];
            foreach ($keys as $k) {
                $resultat[$k] = CacheFichier::get(self::NS . $k, $default);
            }
            return $resultat;
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
            return CacheFichier::forget(self::NS . $key);
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
            CacheFichier::flush(self::NS . $pattern);
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
        return $redis->isAvailable()
            ? $redis->ttl(self::NS . $key)
            : CacheFichier::ttl(self::NS . $key);
    }
}
