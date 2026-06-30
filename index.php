<?php
/**
 * Connect'Academia — Point d'entrée unique
 * CTO : ONA-DAVID LEROY — v2.0
 */
declare(strict_types=1);

// ── Protection inspection du code (côté client) ───────────────────────────
ob_start(function (string $html): string {
    if (stripos($html, '</body>') === false) {
        return $html;
    }
    $script = '<script>(function(){'
        . 'document.addEventListener("contextmenu",function(e){e.preventDefault();});'
        . 'document.addEventListener("keydown",function(e){'
        . 'if(e.key==="F12"'
        . '||(e.ctrlKey&&e.shiftKey&&["I","J","C"].indexOf(e.key.toUpperCase())>-1)'
        . '||(e.ctrlKey&&["U","S"].indexOf(e.key.toUpperCase())>-1))'
        . '{e.preventDefault();e.stopPropagation();return false;}'
        . '});'
        . '}());</script>';
    return str_ireplace('</body>', $script . '</body>', $html);
});

// ── CHARGEMENT DE COMPOSER (PHPMailer, etc.) ──────────────────────────────
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

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
    $path     = ltrim($path, '/');
    $fullPath = __DIR__ . '/public/assets/' . $path;
    $ver      = file_exists($fullPath) ? '?v=' . filemtime($fullPath) : '';
    $base     = BASE_URL === '' ? '/public/assets/' . $path : BASE_URL . '/public/assets/' . $path;
    return $base . $ver;
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

    // persistent=1 : le session handler réutilise les connexions persistantes (pconnect)
    $savePath = 'tcp://' . $redisHost . ':' . $redisPort
        . '?database=' . $redisDb
        . '&lifetime=' . $sessionLifetime
        . '&persistent=1'
        . '&prefix=ca:sess:';

    if (!empty($redisPass)) {
        $savePath .= '&auth=' . rawurlencode($redisPass);
    }

    // Test silencieux via pconnect — la connexion est réutilisée ensuite par le singleton Redis
    try {
        $testRedis = new \Redis();
        if (@$testRedis->pconnect($redisHost, (int)$redisPort, 1.0, 'ca_sess')) {
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

// ── Headers de sécurité HTTP ───────────────────────────────────────────────
if (!headers_sent()) {
    // Protection clickjacking
    header('X-Frame-Options: SAMEORIGIN');
    // Empêche le MIME-sniffing
    header('X-Content-Type-Options: nosniff');
    // Politique de référent stricte
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // Permissions navigateur désactivées
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');
    // HSTS — activer uniquement en HTTPS production
    if (($_ENV['APP_ENV'] ?? 'production') === 'production' && isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
    // Session cookie sécurisé en HTTPS
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_secure', '1');
        }
    }
    // Content-Security-Policy — toutes les ressources tierces préservées
    $csp = implode('; ', [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' https://unpkg.com https://cdn.tailwindcss.com https://code.iconify.design https://www.chatbase.co https://cdnjs.cloudflare.com https://cdn.jsdelivr.net",
        "style-src 'self' 'unsafe-inline' https://cdn.boxicons.com https://unpkg.com https://fonts.googleapis.com https://fonts.cdnfonts.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net",
        "img-src 'self' data: https: blob:",
        "font-src 'self' https://fonts.gstatic.com https://cdn.boxicons.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://fonts.cdnfonts.com",
        "connect-src 'self' https://generativelanguage.googleapis.com https://pay.moneyfusion.net https://www.pay.moneyfusion.net https://unpkg.com https://www.chatbase.co https://api.iconify.design https://*.tile.openstreetmap.org https://cdnjs.cloudflare.com",
        "media-src 'self' blob:",
        "object-src 'none'",
        "frame-src 'self' https://www.chatbase.co https://www.youtube.com https://player.vimeo.com",
        "frame-ancestors 'self'",
        "base-uri 'self'",
        "form-action 'self'",
        "worker-src 'self' blob: https://cdnjs.cloudflare.com",
    ]);
    header('Content-Security-Policy: ' . $csp);
}

// ── Handler d'exceptions global ────────────────────────────────────────────
set_exception_handler(function (\Throwable $e): void {
    error_log('[Uncaught] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    if (!headers_sent()) {
        http_response_code(503);
    }

    $isDbError = str_contains($e->getMessage(), '[Database]')
        || str_contains($e->getMessage(), 'SQLSTATE')
        || str_contains($e->getMessage(), 'Connection refused')
        || str_contains($e->getMessage(), 'PDOException');

    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error'   => ['code' => 'SERVICE_UNAVAILABLE', 'message' => 'Service temporairement indisponible.'],
        ]);
        exit;
    }

    if ($isDbError) {
        require BASE_PATH . '/app/Views/errors/db-down.php';
    } else {
        http_response_code(500);
        if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
            echo '<pre style="background:#1a1a2e;color:#e2e8f0;padding:2rem;font-size:13px;line-height:1.6;">';
            echo '<strong style="color:#f87171">Erreur : </strong>' . htmlspecialchars($e->getMessage()) . "\n\n";
            echo htmlspecialchars($e->getTraceAsString());
            echo '</pre>';
        } else {
            require BASE_PATH . '/app/Views/errors/503.php';
        }
    }
    exit;
});

// ── Dispatcher ─────────────────────────────────────────────────────────────
require BASE_PATH . '/config/routes.php';
$router->dispatch();
