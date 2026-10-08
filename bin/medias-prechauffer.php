<?php
declare(strict_types=1);

/**
 * Connect'Academia — préchauffage des médias d'établissement
 *
 * Usage :
 *   php bin/medias-prechauffer.php            Archive logos et cachets manquants
 *   php bin/medias-prechauffer.php --forcer   Re-télécharge même ceux déjà archivés
 *   php bin/medias-prechauffer.php --etat     N'écrit rien, montre ce qui manque
 *
 * ── À quoi ça sert ─────────────────────────────────────────────────────────
 * Les logos et cachets sont peu nombreux, minuscules, et ne changent presque
 * jamais. Cette commande les télécharge UNE fois depuis le stockage objet et en
 * archive le dérivé localement (cf. Services\Orientation\MiroirMedia).
 *
 * Une fois passée, la plateforme n'a plus besoin du stockage distant pour
 * afficher ces images : un plafond atteint, une panne réseau ou un cache vidé
 * ne les font plus disparaître.
 *
 * À lancer :
 *   · après un premier déploiement ;
 *   · après le rétablissement du stockage, pour repartir sur des bases saines ;
 *   · après l'ajout d'un établissement.
 *
 * Chaque média manquant coûte UNE transaction de téléchargement. Avec neuf
 * établissements, le préchauffage complet en coûte moins de vingt.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("medias-prechauffer.php n'est exécutable qu'en ligne de commande.\n");
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

use Services\Orientation\LogoDataUri;
use Services\Orientation\MiroirMedia;
use Services\Orientation\SignatureDataUri;

$options = array_slice($argv, 1);
$forcer  = in_array('--forcer', $options, true);
$etat    = in_array('--etat', $options, true);

try {
    $db = \Core\Database::getInstance()->getConnection();
    $etablissements = $db->query(
        'SELECT id, nom, logo_cle, signature_cle FROM etablissements ORDER BY id'
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    fwrite(STDERR, 'Base de données injoignable : ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Préchauffage des médias d'établissement" . ($etat ? ' — ÉTAT SEULEMENT' : '') . "\n";
echo '  Médias déjà archivés : ' . MiroirMedia::compter() . "\n\n";

$archives = 0;
$dejaLa   = 0;
$echecs   = 0;

foreach ($etablissements as $etab) {
    $nom = (string)$etab['nom'];

    foreach ([['logo', (string)($etab['logo_cle'] ?? ''), LogoDataUri::class],
              ['cachet', (string)($etab['signature_cle'] ?? ''), SignatureDataUri::class]] as [$type, $cle, $classe]) {

        if ($cle === '') {
            continue;
        }

        if (!$forcer && MiroirMedia::lire($cle) !== null) {
            $dejaLa++;
            printf("  · %-38s %-7s déjà archivé\n", mb_substr($nom, 0, 38), $type);
            continue;
        }

        if ($etat) {
            printf("  ! %-38s %-7s À TÉLÉCHARGER\n", mb_substr($nom, 0, 38), $type);
            continue;
        }

        // L'appel remplit le cache ET l'archive locale (cf. MiroirMedia).
        if ($forcer) {
            $classe::invalider($cle);
        }
        $valeur = $classe::pour($cle);

        if ($valeur === '') {
            $echecs++;
            printf("  ✗ %-38s %-7s illisible\n", mb_substr($nom, 0, 38), $type);
            continue;
        }

        $archives++;
        printf("  ✓ %-38s %-7s archivé (%d Kio)\n", mb_substr($nom, 0, 38), $type, (int)round(strlen($valeur) / 1024));
    }
}

echo "\n";
printf("  archivés : %d · déjà présents : %d · illisibles : %d\n", $archives, $dejaLa, $echecs);
echo '  Total archivé : ' . MiroirMedia::compter() . "\n";

if ($echecs > 0) {
    echo "\n  Des médias restent illisibles. Causes fréquentes :\n";
    echo "   · plafond Backblaze atteint (console → Caps & Alerts) ;\n";
    echo "   · fichier absent du bucket ;\n";
    echo "   · identifiants B2 invalides.\n";
    echo "  Le détail exact figure dans storage/logs/app.log.\n";
    exit(1);
}

exit(0);
