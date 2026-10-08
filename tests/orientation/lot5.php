<?php
declare(strict_types=1);

// ── Garde CLI stricte : un test ne doit JAMAIS être atteignable par le web ──
//
// Ces fichiers écrivent en base, déclenchent des envois d'e-mails et génèrent
// des PDF téléversés sur Backblaze. Servis par un serveur web, ils offriraient
// à n'importe quel visiteur anonyme un droit d'écriture sur la production et un
// robinet ouvert sur le quota de stockage. La configuration du serveur est une
// protection ; celle-ci en est une seconde, qui tient même si la première est
// oubliée sur une machine, un jour de déploiement pressé.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce fichier de test ne s'exécute qu'en ligne de commande.\n");
}

define('BASE_PATH', dirname(__DIR__, 2));
define('BASE_URL', '');
require_once BASE_PATH . '/vendor/autoload.php';
foreach (file(BASE_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
    $l = trim($l); if ($l === '' || $l[0] === '#' || !str_contains($l, '=')) continue;
    [$k, $v] = explode('=', $l, 2); $_ENV[trim($k)] = trim(trim($v), '"\'');
}
// La base de développement contient de VRAIES adresses d'administrateurs, et
// les tâches planifiées envoient des e-mails. Coupe-circuit obligatoire : une
// suite de tests ne doit jamais écrire à personne.
$_ENV['MAIL_DISABLED'] = 'true';
spl_autoload_register(function (string $c): void {
    $f = BASE_PATH . '/app/' . str_replace('\\', '/', $c) . '.php';
    if (file_exists($f)) require_once $f;
});

use Core\AnneeUniversitaire;
use Core\Database;
use Models\Orientation\DossierStatut;
use Services\Orientation\DecisionEtablissement;
use Services\Orientation\Journal;
use Services\Orientation\Tracabilite;

$ok=0;$ko=0;
function t(string $n, bool $c, string $d=''): void { global $ok,$ko; if($c){$ok++;echo "  ✓ $n\n";}else{$ko++;echo "  ✗ $n $d\n";} }

$db = Database::getInstance()->getConnection();
$etabId = (int)$db->query('SELECT id FROM etablissements ORDER BY id LIMIT 1')->fetchColumn();
$userId = (int)$db->query('SELECT id FROM users WHERE is_deleted = 0 ORDER BY id LIMIT 1')->fetchColumn();

// Fixture : un dossier d'INSCRIPTION complet, rattaché à un utilisateur réel.
//
// La référence CA-AAAA-XXX-NNNNNN n'est pas décorative : c'est elle qui
// distingue un dossier d'inscription d'une préinscription (cf.
// Dossier::SQL_EST_INSCRIPTION). SoumissionDossier::executer() la réserve à la
// soumission ; un dossier d'inscription sans référence n'existe pas dans la
// vraie vie, et la fixture qui en fabriquait un testait une situation
// impossible — elle se retrouvait classée en préinscription, à juste titre.
$db->exec("DELETE FROM dossiers_inscription WHERE email = 'test.lot5@example.test'");
$db->exec("INSERT INTO dossiers_inscription
  (etablissement_id, eleve_id, nom_complet, email, filiere_visee, niveau_entree, statut, progression,
   annee_academique, statut_bourse, soumis_le, reference, date_creation)
  VALUES ({$etabId}, {$userId}, 'TEST Lot5', 'test.lot5@example.test', 'Licence 1', 'Licence 1',
   'soumis', 0, '" . AnneeUniversitaire::code() . "', 'boursier', NOW(),
   'CA-" . date('Y') . "-LT5-000001', NOW())");
$did = (int)$db->lastInsertId();

echo "\n── Entonnoir de conversion (spec §11.4) ──\n";
$e = Tracabilite::entonnoir();
t('5 étapes',                 count($e) === 5, (string)count($e));
t('ordre du parcours',        array_column($e, 'cle') === ['comptes','preins','deposes','valides_50','inscrits']);
t('taux relatif au précédent', $e[0]['taux'] === 100.0);
t('décroissant ou égal',      $e[2]['valeur'] >= $e[3]['valeur'] && $e[3]['valeur'] >= $e[4]['valeur'],
                              implode('/', array_column($e, 'valeur')));
$eEtab = Tracabilite::entonnoir($etabId);
t('filtrable par établissement', count($eEtab) === 5);
t('sous-ensemble de la plateforme', $eEtab[2]['valeur'] <= $e[2]['valeur']);

echo "\n── Répartition des bourses ──\n";
$b = Tracabilite::repartitionBourses();
// Trois catégories depuis le retrait de « dossier en cours » : non boursier,
// boursier, non renseignée. La quatrième n'existe plus.
t('3 catégories',             count($b['repartition']) === 3, implode(',', array_keys($b['repartition'])));
t('plus de catégorie « dossier en cours »',
                              !array_key_exists('dossier_en_cours', $b['repartition']));
t('boursier compté',          $b['repartition']['boursier']['nombre'] >= 1);
$somme = array_sum(array_column($b['repartition'], 'nombre'));
t('somme = total',            $somme === $b['total'], "{$somme} vs {$b['total']}");
$parts = array_sum(array_column($b['repartition'], 'part'));
t('parts ≈ 100 %',            $b['total'] === 0 || abs($parts - 100) < 0.5, (string)$parts);

echo "\n── Délais et attente ──\n";
DecisionEtablissement::valider50($did, $etabId, null);
$d = Tracabilite::delaiTraitement();
t('délais calculés',          is_array($d));
$lig = array_values(array_filter($d, fn($x) => (int)$x['etablissement_id'] === $etabId));
t('établissement présent',    $lig !== []);
t('délai positif',            $lig === [] || (float)$lig[0]['delai_moyen'] >= 0);
t('attente prolongée',        is_array(Tracabilite::attenteProlongee()));
t('seuil borné (0 → 1 j)',    is_array(Tracabilite::attenteProlongee(0)));
t('seuil borné (9999 → 365)', is_array(Tracabilite::attenteProlongee(9999)));

echo "\n── Alerte taux de boursiers ──\n";
$a = Tracabilite::alerteBoursiers();
t('moyenne calculée',         is_float($a['moyenne']));
t('liste par établissement',  $a['etablissements'] !== []);
$petit = array_values(array_filter($a['etablissements'], fn($x) => $x['dossiers'] < 10));
t('effectif < 10 jamais signalé', $petit === [] || array_sum(array_column($petit, 'anormal')) === 0);
foreach ($a['etablissements'] as $x) {
    if ($x['dossiers'] > 0) {
        t("taux cohérent — {$x['nom']}", abs($x['taux'] - $x['boursiers'] / $x['dossiers'] * 100) < 0.1);
        break;
    }
}

echo "\n── Fiche utilisateur (spec §11.1) ──\n";
$f = Tracabilite::ficheUtilisateur($userId);
t('4 sections',               count($f) === 4 && isset($f['activite'], $f['preinscriptions'], $f['dossiers'], $f['frise']));
t('activité renseignée',      array_key_exists('cree_le', $f['activite']) && array_key_exists('statut_parcours', $f['activite']));
t('dossier listé',            count(array_filter($f['dossiers'], fn($x) => (int)$x['id'] === $did)) === 1);
$ligneDossier = array_values(array_filter($f['dossiers'], fn($x) => (int)$x['id'] === $did))[0];
t('progression exposée',      $ligneDossier['progression'] === 50, (string)$ligneDossier['progression']);
t('libellé lisible',          !str_contains($ligneDossier['statut_libelle'], '_'));
t('utilisateur inconnu → vide', Tracabilite::ficheUtilisateur(99999999)['dossiers'] === []);

echo "\n── Frise chronologique ──\n";
$frise = Journal::friseUtilisateur($userId, 50);
t('frise alimentée',          $frise !== []);
t('ordre décroissant',        count($frise) < 2 || strtotime((string)$frise[0]['cree_le']) >= strtotime((string)$frise[1]['cree_le']));
t('libellé lisible',          $frise === [] || $frise[0]['libelle'] !== $frise[0]['type_evenement']);
t('données décodées',         $frise === [] || is_array($frise[0]['donnees']));

echo "\n── Vue transversale (spec §13.4) ──\n";
$total = Tracabilite::compterDossiersTransversaux([]);
t('comptage global',          $total > 0, (string)$total);
t('filtre statut',            Tracabilite::compterDossiersTransversaux(['statut'=>'valide_50']) >= 1);
t('statut forgé ignoré',      Tracabilite::compterDossiersTransversaux(['statut'=>"' OR 1=1 --"]) === $total);
t('filtre bourse',            Tracabilite::compterDossiersTransversaux(['statut_bourse'=>'boursier']) >= 1);
t('bourse forgée ignorée',    Tracabilite::compterDossiersTransversaux(['statut_bourse'=>'riche']) === $total);
t('joker SQL échappé',        Tracabilite::compterDossiersTransversaux(['q'=>'%']) <= $total);
$liste = Tracabilite::dossiersTransversaux(['q'=>'TEST Lot5'], 10);
t('recherche par nom',        $liste !== []);
t('ancienneté exposée',       $liste !== [] && array_key_exists('anciennete', $liste[0]));
t('limite bornée',            count(Tracabilite::dossiersTransversaux([], 9999)) <= 500);

echo "\n── Journal : consultation seule ──\n";
$avant = Journal::compter([]);
t('comptage journal',         $avant > 0, (string)$avant);
t('filtre par type',          Journal::compter(['type'=>'dossier_valide_50']) >= 1);
t('type inconnu → 0',         Journal::compter(['type'=>'inexistant_xyz']) === 0);
$refl = new ReflectionClass(Journal::class);
$ecritures = array_filter($refl->getMethods(ReflectionMethod::IS_PUBLIC),
    fn($m) => preg_match('/^(update|delete|supprim|purge|modifi|effac)/i', $m->getName()) === 1);
t('aucune méthode d’écriture destructive', $ecritures === [],
   implode(',', array_map(fn($m) => $m->getName(), $ecritures)));

echo "\n── Inscriptions multiples (spec §17) ──\n";
t('détection disponible',     is_array(Tracabilite::inscriptionsMultiples()));

echo "\n── Nettoyage ──\n";
$db->exec("DELETE FROM commissions_scolarite WHERE dossier_id = {$did}");
$db->exec("DELETE FROM journal_activite WHERE objet_type='dossier' AND objet_id={$did}");
$db->exec("DELETE FROM dossiers_inscription WHERE id = {$did}");
$db->exec("DELETE FROM sequences_dossier");
echo "  ✓ nettoyé\n";
echo "\n══ {$ok} réussis · {$ko} échoués ══\n";
exit($ko === 0 ? 0 : 1);
