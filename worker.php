<?php
declare(strict_types=1);

/**
 * Connect'Academia — Worker de file de jobs (Core\Queue)
 *
 * Traite en arrière-plan les tâches lentes empilées par l'application (e-mails
 * SMTP, génération de reçus PDF + upload B2) pour libérer les workers PHP-FPM.
 *
 * Lancement :
 *   php worker.php            # boucle infinie (production, via systemd/supervisor)
 *   php worker.php once       # vide la file puis s'arrête (cron de secours / tests)
 *
 * Le worker s'arrête proprement sur SIGTERM/SIGINT (termine le job en cours),
 * et se termine après MAX_JOBS ou MAX_MEMORY pour que le superviseur le
 * redémarre (protection contre les fuites mémoire des libs tierces : dompdf…).
 *
 * Exemple d'unité systemd :
 *   [Service]
 *   ExecStart=/usr/bin/php /var/www/connectacademia/worker.php
 *   Restart=always
 *   User=www-data
 */

use Core\Queue;

// ── Garde CLI stricte : jamais exposable via HTTP ───────────────────────────
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("worker.php n'est exécutable qu'en ligne de commande.\n");
}

// ── Bootstrap (miroir d'index.php, sans session/headers/output-buffer) ──────
declare(ticks=1); // requis pour la livraison des signaux pcntl

define('BASE_PATH', __DIR__);

if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Chargement .env : hors webroot d'abord (prod), puis dans le projet (dev).
foreach ([dirname(BASE_PATH) . '/.env', BASE_PATH . '/.env'] as $candidate) {
    if (is_file($candidate)) {
        foreach (file($candidate, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }
            $key = trim(substr($line, 0, $eq));
            $val = trim(substr($line, $eq + 1));
            if (strlen($val) >= 2 && ($val[0] === '"' || $val[0] === "'") && $val[-1] === $val[0]) {
                $val = substr($val, 1, -1);
            }
            $_ENV[$key] = $val;
            putenv("{$key}={$val}");
        }
        break;
    }
}

// Autoloader PSR-4 (identique à index.php : Namespace\Classe → app/Namespace/Classe.php)
spl_autoload_register(function (string $class): void {
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', $class) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

// ── Paramètres du runner ────────────────────────────────────────────────────
const IDLE_SLEEP  = 1;                 // secondes de pause quand la file est vide
const MAX_JOBS    = 500;               // redémarrage après N jobs (anti-fuite mémoire)
const MAX_MEMORY  = 256 * 1024 * 1024; // redémarrage au-delà de 256 Mo
$runOnce = (($argv[1] ?? '') === 'once');

// ── Arrêt gracieux ──────────────────────────────────────────────────────────
$running = true;
if (function_exists('pcntl_signal')) {
    $stop = function (int $sig) use (&$running): void {
        $running = false;
        fwrite(STDOUT, "\n[worker] Signal {$sig} reçu — arrêt après le job en cours…\n");
    };
    pcntl_signal(SIGTERM, $stop);
    pcntl_signal(SIGINT,  $stop);
}

// ── Vérification Redis ──────────────────────────────────────────────────────
if (!\Core\Redis::getInstance()->isAvailable()) {
    fwrite(STDERR, "[worker] Redis indisponible — worker inutile (les jobs s'exécutent en repli synchrone côté web). Arrêt.\n");
    exit(1);
}

fwrite(STDOUT, sprintf(
    "[worker] Démarré (pid %d, mode %s). Ctrl+C pour arrêter.\n",
    getmypid(),
    $runOnce ? 'once' : 'boucle'
));

// ── Boucle principale ───────────────────────────────────────────────────────
$processed = 0;
while ($running) {
    $job = Queue::reserve();

    if ($job === null) {
        if ($runOnce) {
            break; // file vidée → fin en mode once
        }
        sleep(IDLE_SLEEP);
        continue;
    }

    $type = (string)($job['type'] ?? '?');
    $id   = (string)($job['id'] ?? '?');

    try {
        Queue::handle($type, (array)($job['payload'] ?? []));
        $processed++;
        fwrite(STDOUT, "[worker] OK   {$type} #{$id}\n");
    } catch (\Throwable $e) {
        fwrite(STDERR, "[worker] FAIL {$type} #{$id} : {$e->getMessage()}\n");
        Queue::release($job, $e->getMessage());
    }

    // Garde-fous : rendre la main au superviseur pour un process neuf.
    if ($processed >= MAX_JOBS) {
        fwrite(STDOUT, "[worker] MAX_JOBS atteint ({$processed}) — redémarrage propre.\n");
        break;
    }
    if (memory_get_usage(true) >= MAX_MEMORY) {
        fwrite(STDOUT, "[worker] Seuil mémoire atteint — redémarrage propre.\n");
        break;
    }
}

fwrite(STDOUT, "[worker] Arrêté ({$processed} jobs traités).\n");
exit(0);
