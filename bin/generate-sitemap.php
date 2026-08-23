<?php
declare(strict_types=1);

/**
 * Régénère le fichier physique /sitemap.xml à partir de la base.
 *
 * Usage :  php bin/generate-sitemap.php
 *
 * À lancer après un déploiement, ou à planifier (cron) si l'on souhaite un
 * rafraîchissement périodique. En fonctionnement normal le fichier est déjà
 * réécrit automatiquement à chaque création/modification d'établissement par
 * l'administration (cf. Services\SitemapGenerator).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script s'exécute uniquement en ligne de commande.\n");
}

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

// Chargement du .env (même logique que index.php)
foreach ([dirname(BASE_PATH) . '/.env', BASE_PATH . '/.env'] as $candidate) {
    if (is_file($candidate)) {
        foreach (file($candidate, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);
            if (strlen($value) >= 2
                && (($value[0] === '"' && $value[-1] === '"') || ($value[0] === "'" && $value[-1] === "'"))) {
                $value = substr($value, 1, -1);
            }
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
        break;
    }
}

// Autoloader PSR-4 du projet (namespaces → app/)
spl_autoload_register(static function (string $class): void {
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', $class) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$gen = new \Services\SitemapGenerator();

if ($gen->writeToDisk()) {
    fwrite(STDOUT, "Sitemap régénéré : " . $gen->filePath() . "\n");
    exit(0);
}

fwrite(STDERR, "Échec de la génération du sitemap.\n");
exit(1);
