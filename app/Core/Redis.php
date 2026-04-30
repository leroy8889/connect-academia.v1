<?php
declare(strict_types=1);

namespace Core;

use Redis as PhpRedis;

/**
 * Singleton Redis avec fallback gracieux.
 * Si Redis est indisponible, toutes les méthodes échouent silencieusement
 * (isAvailable() retourne false) — l'application continue sans cache/rate limit.
 */
class Redis
{
    private static ?self $instance = null;
    private ?PhpRedis $client     = null;
    private bool $available       = false;
    private string $prefix;

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

            if (!$this->client->connect($host, $port, $timeout)) {
                $this->client = null;
                error_log('[Redis] Impossible de se connecter à ' . $host . ':' . $port);
                return;
            }

            if (!empty($password)) {
                $this->client->auth($password);
            }

            if ($db !== 0) {
                $this->client->select($db);
            }

            // Sérialisation igbinary si disponible, sinon PHP natif
            if (extension_loaded('igbinary')) {
                $this->client->setOption(PhpRedis::OPT_SERIALIZER, PhpRedis::SERIALIZER_IGBINARY);
            } else {
                $this->client->setOption(PhpRedis::OPT_SERIALIZER, PhpRedis::SERIALIZER_PHP);
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

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    // ── Opérations de base ──────────────────────────────────────────────────

    public function get(string $key): mixed
    {
        if (!$this->available) {
            return false;
        }
        try {
            return $this->client->get($this->prefix . $key);
        } catch (\Exception $e) {
            $this->markUnavailable($e);
            return false;
        }
    }

    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        if (!$this->available) {
            return false;
        }
        try {
            if ($ttl > 0) {
                return $this->client->setex($this->prefix . $key, $ttl, $value);
            }
            return (bool)$this->client->set($this->prefix . $key, $value);
        } catch (\Exception $e) {
            $this->markUnavailable($e);
            return false;
        }
    }

    public function del(string ...$keys): int
    {
        if (!$this->available) {
            return 0;
        }
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
        if (!$this->available) {
            return false;
        }
        try {
            return (bool)$this->client->exists($this->prefix . $key);
        } catch (\Exception $e) {
            $this->markUnavailable($e);
            return false;
        }
    }

    public function expire(string $key, int $ttl): bool
    {
        if (!$this->available) {
            return false;
        }
        try {
            return $this->client->expire($this->prefix . $key, $ttl);
        } catch (\Exception $e) {
            $this->markUnavailable($e);
            return false;
        }
    }

    public function ttl(string $key): int
    {
        if (!$this->available) {
            return -2;
        }
        try {
            return (int)$this->client->ttl($this->prefix . $key);
        } catch (\Exception $e) {
            return -2;
        }
    }

    public function incr(string $key): int|false
    {
        if (!$this->available) {
            return false;
        }
        try {
            return $this->client->incr($this->prefix . $key);
        } catch (\Exception $e) {
            $this->markUnavailable($e);
            return false;
        }
    }

    /**
     * Lit une valeur entière stockée par INCR (sans sérialiseur pour éviter le conflit igbinary/PHP).
     * INCR écrit des entiers bruts dans Redis — get() avec sérialiseur échouerait à désérialiser.
     */
    public function getInt(string $key): int
    {
        if (!$this->available) {
            return 0;
        }
        try {
            $raw = $this->client->rawCommand('GET', $this->prefix . $key);
            return $raw !== false ? (int)$raw : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Scan-based keys() pour éviter KEYS bloquant en production.
     * Acceptable en développement ; en prod on préférerait des sets dédiés.
     */
    public function keys(string $pattern): array
    {
        if (!$this->available) {
            return [];
        }
        try {
            $result  = [];
            $cursor  = null;
            $fullPat = $this->prefix . $pattern;
            do {
                [$cursor, $batch] = $this->client->scan($cursor, ['match' => $fullPat, 'count' => 100]);
                if (is_array($batch)) {
                    $result = array_merge($result, $batch);
                }
            } while ($cursor !== 0);
            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    // ── Accès au client natif (pour les helpers avancés) ───────────────────

    public function client(): ?PhpRedis
    {
        return $this->client;
    }

    // ── Opérations sans prefix (pour le session handler interne) ───────────

    public function getRaw(string $key): mixed
    {
        if (!$this->available) {
            return false;
        }
        try {
            return $this->client->get($key);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function setRaw(string $key, string $value, int $ttl = 0): bool
    {
        if (!$this->available) {
            return false;
        }
        try {
            if ($ttl > 0) {
                return $this->client->setex($key, $ttl, $value);
            }
            return (bool)$this->client->set($key, $value);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function delRaw(string $key): void
    {
        if (!$this->available) {
            return;
        }
        try {
            $this->client->del([$key]);
        } catch (\Exception $e) {
            // silence
        }
    }

    // ───────────────────────────────────────────────────────────────────────

    private function markUnavailable(\Exception $e): void
    {
        error_log('[Redis] Erreur mid-request : ' . $e->getMessage());
        $this->available = false;
    }
}
