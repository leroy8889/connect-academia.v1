<?php
declare(strict_types=1);

/**
 * Connect'Academia — purge du cache applicatif (Redis et/ou disque)
 *
 * Usage :
 *   php bin/cache-vider.php               Vide tout le cache
 *   php bin/cache-vider.php medias        Vide seulement logos, cachets, photos
 *   php bin/cache-vider.php "prefixe:*"   Vide les clés correspondant au motif
 *   php bin/cache-vider.php --etat        N'efface rien, affiche l'état
 *
 * ── Quand s'en servir ──────────────────────────────────────────────────────
 * Après avoir remplacé un logo ou un cachet hors de l'application, et surtout
 * après le rétablissement du stockage : un fichier illisible est mémorisé
 * comme tel pendant une minute, et cette commande raccourcit encore l'attente.
 *
 * Vider le cache ne perd aucune donnée : tout y est reconstructible. Le seul
 * effet est un premier affichage plus lent, le temps que les entrées se
 * reforment.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("cache-vider.php n'est exécutable qu'en ligne de commande.\n");
}

define('BASE_PATH', dirname(__DIR__));

if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require_once BASE_PATH . '/vendor/autoload.php';
}

foreach ([dirname(BASE_PATH) . '/.env', BASE_PATH . '/.env'] as $candidat) {
    if (!is_file($candidat)) {
        continue;
    }
    foreach (file($candidat, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ligne) {
        $ligne = trim($ligne);
        if ($ligne === '' || $ligne[0] === '#' || !str_contains($ligne, '=')) {
            continue;
        }
        [$cle, $valeur] = explode('=', $ligne, 2);
        $cle    = trim($cle);
        $valeur = trim($valeur);
        if (strlen($valeur) >= 2 && ($valeur[0] === '"' || $valeur[0] === "'") && $valeur[-1] === $valeur[0]) {
            $valeur = substr($valeur, 1, -1);
        }
        $_ENV[$cle] = $valeur;
        putenv("{$cle}={$valeur}");
    }
    break;
}

spl_autoload_register(static function (string $classe): void {
    $fichier = BASE_PATH . '/app/' . str_replace('\\', '/', $classe) . '.php';
    if (is_file($fichier)) {
        require_once $fichier;
    }
});

/** Motifs des médias téléchargés depuis le stockage objet. */
$motifsMedias = [
    'orientation:logo_datauri:*',
    'orientation:signature_datauri:*',
    'orientation:photo_datauri:*',
];

$argument = $argv[1] ?? '';

$redisDispo  = \Core\Redis::getInstance()->isAvailable();
$disqueDispo = \Core\CacheFichier::disponible();
$entrees     = count(glob(BASE_PATH . '/storage/cache/*/*.cache') ?: []);

echo "Cache Connect'Academia\n";
echo '  Redis        : ' . ($redisDispo  ? 'disponible' : 'INDISPONIBLE (repli disque actif)') . "\n";
echo '  Disque       : ' . ($disqueDispo ? 'disponible' : 'INDISPONIBLE') . "\n";
echo "  Entrées disque : {$entrees}\n";

if ($argument === '--etat') {
    exit(0);
}

if ($argument === 'medias') {
    foreach ($motifsMedias as $motif) {
        \Core\Cache::flush($motif);
        echo "  vidé : {$motif}\n";
    }
} elseif ($argument !== '') {
    \Core\Cache::flush($argument);
    echo "  vidé : {$argument}\n";
} else {
    \Core\Cache::flush('*');
    echo "  vidé : tout le cache\n";
}

clearstatcache();
$restant = count(glob(BASE_PATH . '/storage/cache/*/*.cache') ?: []);
echo "  Entrées disque restantes : {$restant}\n";

exit(0);
