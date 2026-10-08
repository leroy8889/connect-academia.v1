<?php
declare(strict_types=1);

/**
 * Connect'Academia — Tâches planifiées du module inscription (spec §14)
 *
 * Usage :
 *   php bin/taches-orientation.php <tache> [--dry-run] [--quiet]
 *
 * Tâches disponibles :
 *   expiration     Passe à « expiré » les dossiers 50 % dont le délai est dépassé
 *   rattrapage     Régénère les récépissés et attestations manquants
 *   dormants       Signale aux établissements leurs dossiers non traités
 *   retard-analyse Alerte le super administrateur des dossiers non analysés dans le délai (3 j)
 *   taux-boursiers Journalise les taux de boursiers anormaux
 *   multiples      Journalise les inscriptions à 100 % dans plusieurs établissements
 *   quotidien      = expiration + retard-analyse
 *   hebdomadaire   = dormants + taux-boursiers + multiples
 *
 * Crontab de production :
 *
 *   # Rattrapage des documents — toutes les 15 minutes
 *   *\/15 * * * *  cd /var/www/connectacademia && php bin/taches-orientation.php rattrapage --quiet
 *
 *   # Tâches quotidiennes — 6 h du matin, avant l'ouverture des guichets
 *   0 6 * * *     cd /var/www/connectacademia && php bin/taches-orientation.php quotidien --quiet
 *
 *   # Tâches hebdomadaires — lundi 7 h
 *   0 7 * * 1     cd /var/www/connectacademia && php bin/taches-orientation.php hebdomadaire --quiet
 *
 * `--quiet` n'affiche que les erreurs : sans lui, cron enverrait un e-mail à
 * chaque passage, et personne ne lit un e-mail qui arrive quatre-vingt-seize
 * fois par jour.
 *
 * `--dry-run` liste ce qui SERAIT fait sans rien modifier. À utiliser avant le
 * premier passage en production : une expiration de masse mal calibrée n'est
 * pas rattrapable en un geste.
 */

use Services\Orientation\TachesPlanifiees;
use Services\Orientation\Tracabilite;

// ── Garde CLI stricte : jamais exposable via HTTP ───────────────────────────
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("taches-orientation.php n'est exécutable qu'en ligne de commande.\n");
}

define('BASE_PATH', dirname(__DIR__));

if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require_once BASE_PATH . '/vendor/autoload.php';
}

// Chargement .env : hors webroot d'abord (prod), puis dans le projet (dev).
foreach ([dirname(BASE_PATH) . '/.env', BASE_PATH . '/.env'] as $candidat) {
    if (!is_file($candidat)) {
        continue;
    }
    foreach (file($candidat, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ligne) {
        $ligne = trim($ligne);
        if ($ligne === '' || $ligne[0] === '#') {
            continue;
        }
        $eq = strpos($ligne, '=');
        if ($eq === false) {
            continue;
        }
        $cle    = trim(substr($ligne, 0, $eq));
        $valeur = trim(substr($ligne, $eq + 1));
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

// ── Arguments ───────────────────────────────────────────────────────────────
$options = array_slice($argv, 1);
$tache   = '';
$dryRun  = false;
$quiet   = false;

foreach ($options as $option) {
    if ($option === '--dry-run') {
        $dryRun = true;
    } elseif ($option === '--quiet') {
        $quiet = true;
    } elseif ($tache === '' && !str_starts_with($option, '--')) {
        $tache = $option;
    }
}

$sortie = static function (string $texte, bool $erreur = false) use ($quiet): void {
    // En mode silencieux, seules les erreurs remontent : c'est ce qui rend les
    // e-mails de cron lisibles — quand il en arrive un, il y a un problème.
    if ($erreur) {
        fwrite(STDERR, $texte . PHP_EOL);
    } elseif (!$quiet) {
        fwrite(STDOUT, $texte . PHP_EOL);
    }
};

$titre = static function (string $texte) use ($sortie): void {
    $sortie('');
    $sortie('── ' . $texte . ' ' . str_repeat('─', max(0, 60 - mb_strlen($texte))));
};

// ── Prévisualisation ────────────────────────────────────────────────────────

/**
 * En --dry-run, aucune méthode de TachesPlanifiees n'est appelée : on lit
 * seulement ce qui serait touché. Une prévisualisation qui écrit ne
 * prévisualise rien.
 */
$previsualiser = static function (string $tache) use ($sortie): void {
    $db = \Core\Database::getInstance()->getConnection();

    switch ($tache) {
        case 'expiration':
            $n = (int)$db->query(
                "SELECT COUNT(*) FROM dossiers_inscription
                 WHERE statut IN ('valide_50','accepte') AND expire_le IS NOT NULL AND expire_le < NOW()"
            )->fetchColumn();
            $sortie("  {$n} dossier(s) seraient passés en « expiré ».");
            break;

        case 'rattrapage':
            $r = (int)$db->query(
                "SELECT COUNT(*) FROM dossiers_inscription d
                 LEFT JOIN recepisses rc ON rc.dossier_id = d.id
                 WHERE d.reference IS NOT NULL AND d.statut NOT IN ('brouillon','annule')
                   AND (rc.id IS NULL OR rc.cle_pdf IS NULL OR rc.cle_pdf = '')"
            )->fetchColumn();
            $a = count(\Services\Orientation\AttestationDossierService::enAttente(500));
            $sortie("  {$r} récépissé(s) et {$a} attestation(s) seraient régénérés.");
            break;

        case 'dormants':
            $jours = (int)\Core\Settings::get('relance_dossier_dormant_jours', 7);
            $st = $db->prepare(
                "SELECT COUNT(DISTINCT d.etablissement_id) FROM dossiers_inscription d
                 LEFT JOIN preinscriptions p ON p.dossier_id = d.id
                 WHERE p.id IS NULL AND d.statut IN ('soumis','paye','piece_manquante')
                   AND d.soumis_le < DATE_SUB(NOW(), INTERVAL ? DAY)"
            );
            $st->execute([$jours]);
            $sortie('  ' . (int)$st->fetchColumn() . " établissement(s) seraient notifiés (seuil {$jours} j).");
            break;

        case 'retard-analyse':
            $jours = Tracabilite::delaiAnalyse();
            $n     = count(Tracabilite::analysesEnRetard($jours, 500));
            $sortie("  {$n} dossier(s) dépassent le délai d'analyse de {$jours} jours.");
            $sortie('  (Seuls ceux non signalés au cours des 14 derniers jours seraient journalisés.)');
            break;

        case 'taux-boursiers':
            $analyse = Tracabilite::alerteBoursiers();
            $anormaux = array_filter($analyse['etablissements'], static fn(array $e): bool => $e['anormal']);
            $sortie('  Moyenne plateforme : ' . number_format($analyse['moyenne'], 1, ',', ' ') . ' %.');
            $sortie('  ' . count($anormaux) . ' établissement(s) seraient signalés.');
            break;

        case 'multiples':
            $sortie('  ' . count(Tracabilite::inscriptionsMultiples()) . ' cas seraient signalés.');
            break;
    }
};

// ── Exécution ───────────────────────────────────────────────────────────────

/** @var array<string, callable():array<string,mixed>> */
$taches = [
    'expiration'     => [TachesPlanifiees::class, 'expirerDossiers'],
    'rattrapage'     => [TachesPlanifiees::class, 'rattraperDocuments'],
    'dormants'       => [TachesPlanifiees::class, 'signalerDossiersDormants'],
    'retard-analyse' => [TachesPlanifiees::class, 'alerterRetardAnalyse'],
    'taux-boursiers' => [TachesPlanifiees::class, 'alerterTauxBoursiers'],
    'multiples'      => [TachesPlanifiees::class, 'signalerInscriptionsMultiples'],
];

$groupes = [
    'quotidien'    => ['expiration', 'retard-analyse'],
    'hebdomadaire' => ['dormants', 'taux-boursiers', 'multiples'],
];

if ($tache === '' || (!isset($taches[$tache]) && !isset($groupes[$tache]))) {
    $sortie('Usage : php bin/taches-orientation.php <tache> [--dry-run] [--quiet]', true);
    $sortie('Tâches : ' . implode(', ', array_keys($taches)), true);
    $sortie('Groupes : ' . implode(', ', array_keys($groupes)), true);
    exit(1);
}

$aExecuter = $groupes[$tache] ?? [$tache];
$debut     = microtime(true);
$erreurs   = 0;

// ── Verrou d'exécution : jamais deux fois la même tâche en parallèle ────────
//
// `rattrapage` tourne toutes les QUINZE MINUTES et régénère jusqu'à cinquante
// documents — chacun un rendu dompdf suivi d'un TÉLÉVERSEMENT Backblaze. Quand
// le stockage ralentit, une exécution dépasse son intervalle et cron en lance
// une seconde par-dessus. Les deux interrogent alors la même liste de documents
// manquants — la première n'a pas encore écrit ses lignes — et téléversent les
// mêmes fichiers. Chaque chevauchement double la consommation du quota B2, et
// c'est précisément ainsi qu'on épuise le plafond journalier.
//
// `flock` plutôt qu'une clé Redis : le verrou est tenu par le PROCESSUS. Le
// noyau le relâche à sa mort, quelle qu'en soit la cause — plantage, OOM,
// SIGKILL. Un verrou applicatif, lui, resterait posé et bloquerait la tâche
// jusqu'à une intervention manuelle. Il fonctionne aussi quand Redis est à
// terre, alors que c'est justement là qu'on a besoin des tâches de rattrapage.
$dossierVerrous = BASE_PATH . '/storage/locks';
if (!is_dir($dossierVerrous)) {
    @mkdir($dossierVerrous, 0775, true);
}
$cheminVerrou = $dossierVerrous . '/taches-' . preg_replace('/[^a-z0-9_-]/', '', $tache) . '.lock';
$verrou       = @fopen($cheminVerrou, 'c');

if ($verrou === false) {
    // Verrou impossible à ouvrir : on continue plutôt que d'annuler la tâche.
    // Mieux vaut un chevauchement improbable qu'un rattrapage jamais exécuté.
    $sortie('Verrou inaccessible (' . $cheminVerrou . ') — exécution sans garde.', true);
} elseif (!flock($verrou, LOCK_EX | LOCK_NB)) {
    // Déjà en cours : ce n'est PAS une erreur. Sortie 0, sinon le cron du
    // serveur enverrait un courriel d'alerte à chaque exécution un peu longue.
    $sortie('Tâche « ' . $tache . " » déjà en cours d'exécution — passage ignoré.");
    fclose($verrou);
    exit(0);
} else {
    ftruncate($verrou, 0);
    fwrite($verrou, (string)getmypid());
    fflush($verrou);
    // Le verrou doit vivre jusqu'à la fin du script : on le garde référencé.
    register_shutdown_function(static function () use ($verrou): void {
        @flock($verrou, LOCK_UN);
        @fclose($verrou);
    });
}

$sortie('Connect\'Academia — tâches planifiées · ' . date('d/m/Y H:i:s') . ($dryRun ? ' · PRÉVISUALISATION' : ''));

foreach ($aExecuter as $nom) {
    $titre($nom);

    if ($dryRun) {
        $previsualiser($nom);
        continue;
    }

    try {
        $rapport = ($taches[$nom])();
    } catch (\Throwable $e) {
        $erreurs++;
        $sortie('  ERREUR : ' . $e->getMessage(), true);
        error_log('[Taches] ' . $nom . ' : ' . $e->getMessage());
        continue;
    }

    $details = $rapport['details'] ?? [];
    unset($rapport['details']);

    foreach ($rapport as $cle => $valeur) {
        $sortie('  ' . str_pad((string)$cle, 16) . ' : ' . (is_float($valeur) ? number_format($valeur, 1, ',', ' ') : $valeur));
    }

    foreach ($details as $detail) {
        $sortie('    · ' . $detail);
    }

    $erreurs += (int)($rapport['erreurs'] ?? 0);
}

$duree = round(microtime(true) - $debut, 2);
$sortie('');
$sortie('Terminé en ' . $duree . ' s' . ($erreurs > 0 ? " — {$erreurs} erreur(s)" : ''));

// Code de sortie non nul en cas d'erreur : cron le remonte, et un superviseur
// peut déclencher une alerte sans avoir à analyser la sortie texte.
exit($erreurs > 0 ? 1 : 0);
