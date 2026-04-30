<?php
/**
 * StudyLink — Point d'entrée principal
 * Réseau Social Scolaire
 */
declare(strict_types=1);

// Définir le chemin de base du projet
define('BASE_PATH', __DIR__);
define('APP_START', microtime(true));

// Déterminer l'URL de base (sous-répertoire) automatiquement
// Utiliser REQUEST_URI et SCRIPT_NAME pour déterminer le chemin de base
$scriptName = $_SERVER['SCRIPT_NAME'];
$requestUri = $_SERVER['REQUEST_URI'] ?? '';

// Extraire le chemin du script (sans le nom du fichier)
$scriptDir = dirname($scriptName);
$scriptDir = str_replace('\\', '/', $scriptDir);
$scriptDir = rtrim($scriptDir, '/');

// Si le script est à la racine du serveur, BASE_URL est vide
// Sinon, BASE_URL est le chemin du dossier contenant index.php
if ($scriptDir === '.' || $scriptDir === '/' || $scriptDir === '') {
    $baseUrl = '';
} else {
    $baseUrl = $scriptDir;
}

define('BASE_URL', $baseUrl);

// Charger les variables d'environnement
$envFile = BASE_PATH . '/.env';
if (file_exists($envFile)) {
    $env = parse_ini_file($envFile, false, INI_SCANNER_RAW);
    if ($env !== false) {
        foreach ($env as $key => $value) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

/**
 * Helper : génère une URL complète avec le préfixe du sous-répertoire.
 * Utilisation : url('/login') → '/communityv2/login'
 */
function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    if (BASE_URL === '') {
        return '/' . $path;
    }
    return BASE_URL . '/' . $path;
}

/**
 * Helper : génère un chemin vers un asset.
 * Utilisation : asset('css/main.css') → '/communityv2/public/assets/css/main.css'
 */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    if (BASE_URL === '') {
        return '/public/assets/' . $path;
    }
    return BASE_URL . '/public/assets/' . $path;
}

// Mode erreur selon l'environnement
$appEnv = $_ENV['APP_ENV'] ?? 'local';
if ($appEnv === 'production') {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
} else {
    // Mode développement : afficher toutes les erreurs
    error_reporting(E_ALL | E_STRICT);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    ini_set('log_errors', '1');
    ini_set('error_log', BASE_PATH . '/error.log');
}

// Autoloader
spl_autoload_register(function (string $class): void {
    // Convertir namespace en chemin de fichier
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Session sécurisée
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Strict');
$sessionName = $_ENV['SESSION_NAME'] ?? 'studylink_sess';
session_name($sessionName);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Générer le token CSRF si absent
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Charger les routes et dispatcher
require BASE_PATH . '/config/routes.php';
$router->dispatch();

