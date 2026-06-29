<?php
declare(strict_types=1);

namespace Core;

use Redis as PhpRedis;

/**
 * Singleton Redis avec connexions persistantes et fallback gracieux.
 * Connexion persistante (pconnect) = réutilisation dans le même worker PHP-FPM.
 * Toutes les méthodes échouent silencieusement si Redis est indisponible.
 */
class Redis
{
    private static ?self $instance = null;
    private ?PhpRedis $client     = null;
    private bool $available       = false;
    private string $prefix;

    // Lua : INCR atomique + EXPIRE seulement au premier hit (élimine la race condition)
    private const LUA_INCR_EXPIRE = <<<'LUA'
local c = redis.call('INCR', KEYS[1])
if c == 1 then redis.call('EXPIRE', KEYS[1], ARGV[1]) end
return c
LUA;

    private function __construct()
    {
        if (!extension_loaded('redis')) {
            error_log('[Redis] Extension PHP redis non chargée.');
            return;
        }

        $host     = $_ENV['REDIS_HOST']     ?? '127.0.0.1';
        $port     = (int)($_ENV['REDIS_PORT']     ?? 6379);
        $password = $_ENV['REDIS_PASSWORD'] ?? '';
        $db       = (int)($_ENV['REDIS_DB']       ?? 0);
        $timeout  = (float)($_ENV['REDIS_TIMEOUT'] ?? 1.5);

        $this->prefix = ($_ENV['REDIS_PREFIX'] ?? 'ca') . ':';

        try {
            $this->client = new PhpRedis();

            // pconnect = connexion persistante réutilisée entre requêtes PHP-FPM sur le même worker
            $persistId = 'ca_db' . $db;
            if (!$this->client->pconnect($host, $port, $timeout, $persistId)) {
                $this->client = null;
                error_log('[Redis] Impossible de se connecter (pconnect) à ' . $host . ':' . $port);
                return;
            }

            if (!empty($password)) {
                $this->client->auth($password);
            }

            if ($db !== 0) {
                $this->client->select($db);
            }

            // Sérialisation : igbinary si disponible (plus rapide + compact), sinon PHP natif
            if (extension_loaded('igbinary')) {
                $this->client->setOption(PhpRedis::OPT_SERIALIZER, PhpRedis::SERIALIZER_IGBINARY);
            } else {
                $this->client->setOption(PhpRedis::OPT_SERIALIZER, PhpRedis::SERIALIZER_PHP);
            }

            // Compression LZF si disponible (~30-50% réduction mémoire Redis)
            if (defined('Redis::COMPRESSION_LZF')) {
                $this->client->setOption(PhpRedis::OPT_COMPRESSION, PhpRedis::COMPRESSION_LZF);
            }

            $this->client->ping();
            $this->available = true;

        } catch (\Exception $e) {
            error_log('[Redis] Erreur de connexion : ' . $e->getMessage());
            $this->client    = null;
            $this->available = false;
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function isAvailable(): bool { return $this->available; }
    public function prefix(): string    { return $this->prefix; }

    // ── Opérations de base ──────────────────────────────────────────────────

    public function get(string $key): mixed
    {
        if (!$this->available) return false;
        try {
            return $this->client->get($this->prefix . $key);
        } catch (\Exception $e) {
            $this->markUnavailable($e);
            return false;
        }
    }

    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        if (!$this->available) return false;
        try {
            return $ttl > 0
                ? $this->client->setex($this->prefix . $key, $ttl, $value)
                : (bool)$this->client->set($this->prefix . $key, $value);
        } catch (\Exception $e) {
            $this->markUnavailable($e);
            return false;
        }
    }

    public function del(string ...$keys): int
    {
        if (!$this->available) return 0;
        try {
            $prefixed = array_map(fn(string $k) => $this->prefix . $k, $keys);
            return (int)$this->client->del($prefixed);
        } catch (\Exception $e) {
            $this->markUnavailable($e);
            return 0;
        }
    }

    public function exists(string $key): bool
    {
        if (!$this->available) return false;
        try {
            return (bool)$this->client->exists($this->prefix . $key);
        } catch (\Exception $e) {
            $this->markUnavailable($e);
            return false;
        }
    }

    public function expire(string $key, int $ttl): bool
    {
        if (!$this->available) return false;
        try {
            return $this->client->expire($this->prefix . $key, $ttl);
        } catch (\Exception $e) {
            $this->markUnavailable($e);
            return false;
        }
    }

    public function ttl(string $key): int
    {
        if (!$this->available) return -2;
        try {
            return (int)$this->client->ttl($this->prefix . $key);
        } catch (\Exception $e) {
            return -2;
        }
    }

    // ── Compteurs ───────────────────────────────────────────────────────────

    public function incr(string $key): int|false
    {
        if (!$this->available) return false;
        try {
            return $this->client->incr($this->prefix . $key);
        } catch (\Exception $e) {
            $this->markUnavailable($e);
            return false;
        }
    }

    public function incrBy(string $key, int $value): int|false
    {
        if (!$this->available) return false;
        try {
            return $this->client->incrBy($this->prefix . $key, $value);
        } catch (\Exception $e) {
            $this->markUnavailable($e);
            return false;
        }
    }

    public function decrBy(string $key, int $value): int|false
    {
        if (!$this->available) return false;
        try {
            return $this->client->decrBy($this->prefix . $key, $value);
        } catch (\Exception $e) {
            $this->markUnavailable($e);
            return false;
        }
    }

    /**
     * INCR + EXPIRE atomique via Lua. Élimine la race condition du duo incr()+expire().
     * L'EXPIRE n'est posé qu'au premier hit (count == 1), la fenêtre ne se réinitialise pas.
     */
    public function incrWithExpire(string $key, int $ttl): int|false
    {
        if (!$this->available) return false;
        try {
            $result = $this->client->eval(
                self::LUA_INCR_EXPIRE,
                [$this->prefix . $key, (string)$ttl],
                1
            );
            return ($result !== false) ? (int)$result : false;
        } catch (\Exception $e) {
            $this->markUnavailable($e);
            return false;
        }
    }

    /**
     * Lit un entier écrit par INCR (bypass sérialiseur — INCR écrit des entiers bruts).
     */
    public function getInt(string $key): int
    {
        if (!$this->available) return 0;
        try {
            $raw = $this->client->rawCommand('GET', $this->prefix . $key);
            return $raw !== false ? (int)$raw : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    // ── Multi-get (un seul round-trip) ──────────────────────────────────────

    /**
     * Récupère plusieurs clés en un seul round-trip.
     * Retourne [key => value|false] (false = clé absente).
     */
    public function mGet(array $keys): array
    {
        if (!$this->available || empty($keys)) return [];
        try {
            $prefixed = array_map(fn(string $k) => $this->prefix . $k, $keys);
            $values   = $this->client->mGet($prefixed);
            $result   = [];
            foreach ($keys as $i => $key) {
                $result[$key] = $values[$i] ?? false;
            }
            return $result;
        } catch (\Exception $e) {
            $this->markUnavailable($e);
            return [];
        }
    }

    // ── Hash operations ─────────────────────────────────────────────────────

    public function hSet(string $key, string $field, mixed $value): int|false
    {
        if (!$this->available) return false;
        try {
            return $this->client->hSet($this->prefix . $key, $field, $value);
        } catch (\Exception $e) {
            $this->markUnavailable($e);
            return false;
        }
    }

    public function hGet(string $key, string $field): mixed
    {
        if (!$this->available) return false;
        try {
            return $this->client->hGet($this->prefix . $key, $field);
        } catch (\Exception $e) {
            $this->markUnavailable($e);
            return false;
        }
    }

    public function hMSet(string $key, array $data): bool
    {
        if (!$this->available) return false;
        try {
            return $this->client->hMSet($this->prefix . $key, $data);
        } catch (\Exception $e) {
            $this->markUnavailable($e);
            return false;
        }
    }

    public function hMGet(string $key, array $fields): array
    {
        if (!$this->available) return [];
        try {
            return $this->client->hMGet($this->prefix . $key, $fields) ?: [];
        } catch (\Exception $e) {
            $this->markUnavailable($e);
            return [];
        }
    }

    public function hGetAll(string $key): array
    {
        if (!$this->available) return [];
        try {
            return $this->client->hGetAll($this->prefix . $key) ?: [];
        } catch (\Exception $e) {
            $this->markUnavailable($e);
            return [];
        }
    }

    public function hIncrBy(string $key, string $field, int $value): int|false
    {
        if (!$this->available) return false;
        try {
            return $this->client->hIncrBy($this->prefix . $key, $field, $value);
        } catch (\Exception $e) {
            $this->markUnavailable($e);
            return false;
        }
    }

    public function hDel(string $key, string ...$fields): int
    {
        if (!$this->available) return 0;
        try {
            return (int)$this->client->hDel($this->prefix . $key, ...$fields);
        } catch (\Exception $e) {
            return 0;
        }
    }

    // ── Sorted set operations ───────────────────────────────────────────────

    public function zAdd(string $key, float $score, string $member): int|false
    {
        if (!$this->available) return false;
        try {
            return (int)$this->client->zAdd($this->prefix . $key, $score, $member);
        } catch (\Exception $e) {
            $this->markUnavailable($e);
            return false;
        }
    }

    public function zIncrBy(string $key, float $increment, string $member): float|false
    {
        if (!$this->available) return false;
        try {
            return $this->client->zIncrBy($this->prefix . $key, $increment, $member);
        } catch (\Exception $e) {
            $this->markUnavailable($e);
            return false;
        }
    }

    public function zRevRange(string $key, int $start, int $end, bool $withScores = false): array
    {
        if (!$this->available) return [];
        try {
            $res = $this->client->zRevRange($this->prefix . $key, $start, $end, $withScores);
            return $res ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function zScore(string $key, string $member): float|false
    {
        if (!$this->available) return false;
        try {
            return $this->client->zScore($this->prefix . $key, $member);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function zCard(string $key): int
    {
        if (!$this->available) return 0;
        try {
            return (int)$this->client->zCard($this->prefix . $key);
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function zRem(string $key, string ...$members): int
    {
        if (!$this->available) return 0;
        try {
            return (int)$this->client->zRem($this->prefix . $key, ...$members);
        } catch (\Exception $e) {
            return 0;
        }
    }

    // ── Set operations ──────────────────────────────────────────────────────

    public function sAdd(string $key, string ...$members): int|false
    {
        if (!$this->available) return false;
        try {
            return (int)$this->client->sAdd($this->prefix . $key, ...$members);
        } catch (\Exception $e) {
            $this->markUnavailable($e);
            return false;
        }
    }

    public function sIsMember(string $key, string $member): bool
    {
        if (!$this->available) return false;
        try {
            return (bool)$this->client->sIsMember($this->prefix . $key, $member);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function sRem(string $key, string ...$members): int
    {
        if (!$this->available) return 0;
        try {
            return (int)$this->client->sRem($this->prefix . $key, ...$members);
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function sMembers(string $key): array
    {
        if (!$this->available) return [];
        try {
            return $this->client->sMembers($this->prefix . $key) ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function sCard(string $key): int
    {
        if (!$this->available) return 0;
        try {
            return (int)$this->client->sCard($this->prefix . $key);
        } catch (\Exception $e) {
            return 0;
        }
    }

    // ── Pipeline ────────────────────────────────────────────────────────────

    /**
     * Exécute un lot de commandes en un seul round-trip.
     * Le callback reçoit le client Redis en mode pipeline (sans prefix automatique).
     *
     * Exemple :
     *   $results = Redis::getInstance()->pipeline(function($pipe) {
     *       $pipe->del(['ca:cache:key1', 'ca:cache:key2']);
     *   });
     */
    public function pipeline(callable $callback): array
    {
        if (!$this->available) return [];
        try {
            $pipe = $this->client->multi(PhpRedis::PIPELINE);
            $callback($pipe);
            return $pipe->exec() ?: [];
        } catch (\Exception $e) {
            $this->markUnavailable($e);
            return [];
        }
    }

    // ── SCAN-based keys (non-bloquant en production) ────────────────────────

    public function keys(string $pattern): array
    {
        if (!$this->available) return [];
        try {
            $result  = [];
            $cursor  = null;
            $fullPat = $this->prefix . $pattern;
            do {
                [$cursor, $batch] = $this->client->scan($cursor, ['match' => $fullPat, 'count' => 200]);
                if (is_array($batch)) {
                    $result = array_merge($result, $batch);
                }
            } while ($cursor !== 0);
            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    // ── Client natif (helpers avancés) ──────────────────────────────────────

    public function client(): ?PhpRedis
    {
        return $this->client;
    }

    // ── Opérations sans prefix (session handler) ────────────────────────────

    public function getRaw(string $key): mixed
    {
        if (!$this->available) return false;
        try {
            return $this->client->get($key);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function setRaw(string $key, string $value, int $ttl = 0): bool
    {
        if (!$this->available) return false;
        try {
            return $ttl > 0
                ? $this->client->setex($key, $ttl, $value)
                : (bool)$this->client->set($key, $value);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function delRaw(string $key): void
    {
        if (!$this->available) return;
        try {
            $this->client->del([$key]);
        } catch (\Exception $e) {
            // silence
        }
    }

    // ── Internal ────────────────────────────────────────────────────────────

    private function markUnavailable(\Exception $e): void
    {
        error_log('[Redis] Erreur mid-request : ' . $e->getMessage());
        $this->available = false;
    }
}
