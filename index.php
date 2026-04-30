<?php
/**
 * Connect'Academia — Point d'entrée unique
 * CTO : ONA-DAVID LEROY — v2.0
 */
declare(strict_types=1);

define('BASE_PATH', __DIR__);
define('APP_START', microtime(true));

// ── Détection automatique du BASE_URL ──────────────────────────────────────
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
define('BASE_URL', ($scriptDir === '.' || $scriptDir === '') ? '' : $scriptDir);

// ── Chargement des variables d'environnement ───────────────────────────────
$envFile = BASE_PATH . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $eqPos = strpos($line, '=');
        if ($eqPos === false) {
            continue;
        }
        $key   = trim(substr($line, 0, $eqPos));
        $value = trim(substr($line, $eqPos + 1));
        // Retirer les guillemets entourants
        if (strlen($value) >= 2
            && (($value[0] === '"'  && $value[-1] === '"')
             || ($value[0] === "'"  && $value[-1] === "'"))
        ) {
            $value = substr($value, 1, -1);
        }
        $_ENV[$key] = $value;
        putenv("{$key}={$value}");
    }
}

// ── Mode erreur selon l'environnement ──────────────────────────────────────
if (($_ENV['APP_DEBUG'] ?? 'true') === 'true') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/app.log');

// ── Helpers globaux ────────────────────────────────────────────────────────
function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    return BASE_URL === '' ? '/' . $path : BASE_URL . '/' . $path;
}

function asset(string $path): string
{
    $path = ltrim($path, '/');
    return BASE_URL === '' ? '/public/assets/' . $path : BASE_URL . '/public/assets/' . $path;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function config(string $key, $default = null)
{
    return $_ENV[$key] ?? $default;
}

// ── Autoloader PSR-4 ───────────────────────────────────────────────────────
spl_autoload_register(function (string $class): void {
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// ── Session sécurisée ──────────────────────────────────────────────────────
$sessionLifetime = (int)($_ENV['SESSION_LIFETIME'] ?? 7200);

ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.gc_maxlifetime', (string)$sessionLifetime);

// Tenter de stocker les sessions dans Redis (handler natif de l'extension PHP)
// Si Redis est indisponible, on conserve le handler fichiers par défaut.
if (extension_loaded('redis')) {
    $redisHost = $_ENV['REDIS_HOST']     ?? '127.0.0.1';
    $redisPort = $_ENV['REDIS_PORT']     ?? '6379';
    $redisPass = $_ENV['REDIS_PASSWORD'] ?? '';
    $redisDb   = (int)($_ENV['REDIS_DB'] ?? 0);

    $savePath = 'tcp://' . $redisHost . ':' . $redisPort
        . '?database=' . $redisDb
        . '&lifetime=' . $sessionLifetime
        . '&persistent=0'
        . '&prefix=ca:sess:';

    if (!empty($redisPass)) {
        $savePath .= '&auth=' . rawurlencode($redisPass);
    }

    // Test silencieux : on ne bascule que si Redis répond
    try {
        $testRedis = new \Redis();
        if (@$testRedis->connect($redisHost, (int)$redisPort, 1.0)) {
            if (!empty($redisPass)) {
                $testRedis->auth($redisPass);
            }
            $testRedis->ping();
            ini_set('session.save_handler', 'redis');
            ini_set('session.save_path',    $savePath);
            unset($testRedis);
        }
    } catch (\Exception $e) {
        // Redis indisponible — on garde le handler fichiers
        error_log('[Session] Fallback fichiers : ' . $e->getMessage());
    }
}

$sessionName = $_ENV['SESSION_NAME'] ?? 'ca_session';
session_name($sessionName);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Générer le token CSRF si absent
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Dispatcher ─────────────────────────────────────────────────────────────
require BASE_PATH . '/config/routes.php';
$router->dispatch();
