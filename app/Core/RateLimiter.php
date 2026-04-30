<?php
declare(strict_types=1);

namespace Core;

/**
 * Rate limiter basé sur Redis (compteur avec TTL = fenêtre glissante).
 *
 * Fonctionnement :
 *   - Chaque "action" (ex: login) est identifiée par une clé unique (ip + action).
 *   - INCR incrémente le compteur. Sur le premier hit, un TTL est posé (= durée de la fenêtre).
 *   - Si le compteur >= maxAttempts avant expiration du TTL → bloqué.
 *   - Fail-open : si Redis est indisponible, aucune tentative n'est comptée.
 *
 * Namespace Redis : 'rl:' (en plus du prefix global).
 */
class RateLimiter
{
    private const NS = 'rl:';

    // ── API principale ───────────────────────────────────────────────────────

    /**
     * Vérifie si l'action est bloquée. Retourne true si l'action est autorisée.
     * NE consomme PAS une tentative — appeler hit() séparément.
     */
    public static function allow(string $key, int $maxAttempts): bool
    {
        return self::attempts($key) < $maxAttempts;
    }

    /**
     * Alias sémantique négatif.
     */
    public static function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return self::attempts($key) >= $maxAttempts;
    }

    /**
     * Enregistre une tentative. Retourne le nombre total de tentatives.
     * Définit le TTL uniquement au premier hit (fenêtre glissante ancrée au début).
     */
    public static function hit(string $key, int $decaySeconds): int
    {
        $redis = Redis::getInstance();
        if (!$redis->isAvailable()) {
            return 0;
        }

        $fullKey = self::NS . $key;
        $count   = $redis->incr($fullKey);

        if ($count === 1 || $count === false) {
            // Premier hit ou incr a échoué — on pose/repose le TTL
            $redis->expire($fullKey, $decaySeconds);
            $count = max(1, (int)$count);
        }

        return $count;
    }

    /**
     * Réinitialise le compteur (ex: après une connexion réussie).
     */
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

    /**
     * Secondes restantes avant réinitialisation de la fenêtre.
     */
    public static function availableIn(string $key): int
    {
        $redis = Redis::getInstance();
        if (!$redis->isAvailable()) {
            return 0;
        }
        return max(0, $redis->ttl(self::NS . $key));
    }

    // ── Générateurs de clés standardisés ────────────────────────────────────

    /**
     * Clé par IP pour une action donnée.
     * Ex: RateLimiter::ipKey('login') → 'login:a1b2c3...'
     */
    public static function ipKey(string $action, ?string $ip = null): string
    {
        $ip = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        return $action . ':ip:' . md5($ip);
    }

    /**
     * Clé par email pour une action donnée (utile pour brute-force ciblé).
     */
    public static function emailKey(string $action, string $email): string
    {
        return $action . ':email:' . md5(strtolower(trim($email)));
    }
}
