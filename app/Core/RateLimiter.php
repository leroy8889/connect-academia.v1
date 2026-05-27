<?php
declare(strict_types=1);

namespace Core;

/**
 * Rate limiter basé sur Redis.
 *
 * Fonctionnement :
 *   - Chaque "action" (ex: login) est identifiée par une clé unique (ip + action).
 *   - hit() utilise un script Lua atomique INCR + EXPIRE → élimine la race condition.
 *     L'EXPIRE n'est posé qu'au 1er hit, donc la fenêtre ne se réinitialise pas en cours.
 *   - Si le compteur >= maxAttempts avant expiration du TTL → bloqué.
 *   - Fail-open : si Redis est indisponible, aucune tentative n'est comptée.
 *
 * Namespace Redis : 'rl:' (en plus du prefix global).
 */
class RateLimiter
{
    private const NS = 'rl:';

    // ── API principale ───────────────────────────────────────────────────────

    public static function allow(string $key, int $maxAttempts): bool
    {
        return self::attempts($key) < $maxAttempts;
    }

    public static function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return self::attempts($key) >= $maxAttempts;
    }

    /**
     * Enregistre une tentative de façon atomique (Lua INCR + EXPIRE).
     * Le TTL n'est posé qu'au 1er hit — la fenêtre ne glisse pas.
     */
    public static function hit(string $key, int $decaySeconds): int
    {
        $redis   = Redis::getInstance();
        if (!$redis->isAvailable()) {
            return 0;
        }

        $fullKey = self::NS . $key;
        $count   = $redis->incrWithExpire($fullKey, $decaySeconds);

        return $count !== false ? (int)$count : 0;
    }

    public static function clear(string $key): void
    {
        $redis = Redis::getInstance();
        if ($redis->isAvailable()) {
            $redis->del(self::NS . $key);
        }
    }

    // ── Informations ─────────────────────────────────────────────────────────

    public static function attempts(string $key): int
    {
        $redis = Redis::getInstance();
        if (!$redis->isAvailable()) {
            return 0;
        }
        return $redis->getInt(self::NS . $key);
    }

    public static function retriesLeft(string $key, int $maxAttempts): int
    {
        return max(0, $maxAttempts - self::attempts($key));
    }

    public static function availableIn(string $key): int
    {
        $redis = Redis::getInstance();
        if (!$redis->isAvailable()) {
            return 0;
        }
        return max(0, $redis->ttl(self::NS . $key));
    }

    // ── Générateurs de clés standardisés ────────────────────────────────────

    public static function ipKey(string $action, ?string $ip = null): string
    {
        $ip = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        return $action . ':ip:' . md5($ip);
    }

    public static function emailKey(string $action, string $email): string
    {
        return $action . ':email:' . md5(strtolower(trim($email)));
    }
}
