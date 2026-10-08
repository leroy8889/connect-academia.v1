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
use Models\Orientation\Dossier;
use Models\Orientation\DossierStatut;
use Services\Orientation\TachesPlanifiees;

$ok=0;$ko=0;
function t(string $n, bool $c, string $d=''): void { global $ok,$ko; if($c){$ok++;echo "  ✓ $n\n";}else{$ko++;echo "  ✗ $n $d\n";} }

$db = Database::getInstance()->getConnection();
$etabId = (int)$db->query('SELECT id FROM etablissements ORDER BY id LIMIT 1')->fetchColumn();
$userId = (int)$db->query('SELECT id FROM users WHERE is_deleted = 0 ORDER BY id LIMIT 1')->fetchColumn();
$m = new Dossier();
$annee = AnneeUniversitaire::code();
$ids = [];

function creer(PDO $db, int $etabId, int $userId, string $annee, array $champs): int {
    $base = [
        'nom_complet' => 'TEST Lot6', 'email' => 'test.lot6@example.test',
        'filiere_visee' => 'Licence 1', 'niveau_entree' => 'Licence 1',
        'statut' => 'soumis', 'progression' => 0, 'statut_bourse' => 'non_boursier',
        'soumis_le' => 'NOW()', 'expire_le' => 'NULL', 'bourse_declaree_le' => 'NULL',
        'reference' => 'NULL',
    ];
    $c = array_merge($base, $champs);
    $sql = "INSERT INTO dossiers_inscription
      (etablissement_id, eleve_id, nom_complet, email, filiere_visee, niveau_entree, statut,
       progression, annee_academique, statut_bourse, soumis_le, expire_le, bourse_declaree_le,
       reference, date_creation)
      VALUES ({$etabId}, {$userId}, " . $db->quote($c['nom_complet']) . ", " . $db->quote($c['email']) . ",
       " . $db->quote($c['filiere_visee']) . ", " . $db->quote($c['niveau_entree']) . ",
       " . $db->quote($c['statut']) . ", {$c['progression']}, " . $db->quote($annee) . ",
       " . $db->quote($c['statut_bourse']) . ", {$c['soumis_le']}, {$c['expire_le']},
       {$c['bourse_declaree_le']}, {$c['reference']}, NOW())";
    $db->exec($sql);
    return (int)$db->lastInsertId();
}

$db->exec("DELETE FROM dossiers_inscription WHERE email = 'test.lot6@example.test'");

echo "\n── Expiration (spec §14) ──\n";
// Un dossier 50 % dont le délai est dépassé, un autre encore dans les temps
$expire  = creer($db, $etabId, $userId, $annee, ['statut'=>'valide_50','progression'=>50,'expire_le'=>"DATE_SUB(NOW(), INTERVAL 3 DAY)"]);
$aTemps  = creer($db, $etabId, $userId, $annee, ['statut'=>'valide_50','progression'=>50,'expire_le'=>"DATE_ADD(NOW(), INTERVAL 10 DAY)"]);
$ids[] = $expire; $ids[] = $aTemps;

$r = TachesPlanifiees::expirerDossiers();
t('au moins 1 dossier expiré',   $r['traites'] >= 1, json_encode($r));
t('aucune erreur',               $r['erreurs'] === 0, implode(' | ', $r['details']));
t('dépassé → expire',            DossierStatut::normaliser($m->findById($expire)['statut']) === DossierStatut::EXPIRE);
t('dans les temps → intact',     DossierStatut::normaliser($m->findById($aTemps)['statut']) === DossierStatut::VALIDE_50);
t('progression remise à 0',      (int)$m->findById($expire)['progression'] === 0);
$h = $db->query("SELECT acteur_role FROM historique_statut_dossier WHERE dossier_id = {$expire} ORDER BY id DESC LIMIT 1")->fetchColumn();
t('acteur = systeme',            $h === 'systeme', (string)$h);
$n = (int)$db->query("SELECT COUNT(*) FROM notifications_orientation WHERE user_id = {$userId} AND type = 'dossier_expire'")->fetchColumn();
t('candidat notifié',            $n >= 1);

$r2 = TachesPlanifiees::expirerDossiers();
t('rejeu : rien à refaire',      $r2['traites'] === 0, json_encode($r2));

// ── Relance bourse ANBG : tâche SUPPRIMÉE ────────────────────────────────────
// Elle relançait les « dossier en cours de traitement à l'ANBG » de plus de
// 90 jours. La situation ayant été retirée du parcours, la tâche n'a plus
// d'objet : elle est retirée de TachesPlanifiees et du lanceur.
echo "\n── Relance bourse ANBG : tâche retirée ──\n";
t('méthode retirée du service',
   !method_exists(TachesPlanifiees::class, 'relancerBoursesEnCours'));
t('tâche retirée du lanceur',
   !str_contains((string)file_get_contents(__DIR__ . '/../../bin/taches-orientation.php'), 'relance-bourse'));

echo "\n── Dossiers dormants (>7 j) ──\n";
$dormant = creer($db, $etabId, $userId, $annee, ['soumis_le'=>"DATE_SUB(NOW(), INTERVAL 20 DAY)"]);
$frais   = creer($db, $etabId, $userId, $annee, ['soumis_le'=>"NOW()"]);
$ids = array_merge($ids, [$dormant, $frais]);

$r = TachesPlanifiees::signalerDossiersDormants();
t('établissement notifié',       $r['etablissements'] >= 1, json_encode($r));
t('dossier dormant compté',      $r['dossiers'] >= 1);
t('aucune erreur',               $r['erreurs'] === 0);
$n = (int)$db->query("SELECT COUNT(*) FROM notifications_orientation WHERE type = 'dossiers_dormants'")->fetchColumn();
t('une notif par établissement, pas par dossier', $n <= $r['etablissements'] * 5, (string)$n);

echo "\n── Alerte taux de boursiers ──\n";
$r = TachesPlanifiees::alerterTauxBoursiers();
t('rapport produit',             isset($r['signales'], $r['moyenne']));
t('moyenne numérique',           is_float($r['moyenne']));
t('signalés = entrées journal',  $r['signales'] === (int)$db->query("SELECT COUNT(*) FROM journal_activite WHERE type_evenement = 'alerte_taux_boursiers'")->fetchColumn());

echo "\n── Inscriptions multiples ──\n";
$r = TachesPlanifiees::signalerInscriptionsMultiples();
t('rapport produit',             isset($r['signales']));

echo "\n── Rattrapage des documents ──\n";
$r = TachesPlanifiees::rattraperDocuments(3);
t('rapport complet',             isset($r['recepisses'], $r['attestations'], $r['erreurs']));
t('lot borné',                   $r['recepisses'] <= 3);

echo "\n── Bornes du lot ──\n";
$refl = new ReflectionClass(TachesPlanifiees::class);
$lot  = $refl->getConstant('LOT');
t('constante LOT définie',       is_int($lot) && $lot > 0 && $lot <= 1000, (string)$lot);
t('anti-répétition définie',     is_int($refl->getConstant('ANTI_REPETITION_JOURS')));
$sql = file_get_contents(BASE_PATH . '/app/Services/Orientation/TachesPlanifiees.php');
t('toutes les requêtes bornées', substr_count($sql, 'LIMIT') >= 3, (string)substr_count($sql, 'LIMIT'));

echo "\n── CLI ──\n";
$php = '/Applications/MAMP/bin/php/php8.3.30/bin/php';
exec("cd " . escapeshellarg(BASE_PATH) . " && {$php} bin/taches-orientation.php 2>&1", $out, $code);
t('sans argument → code 1',      $code === 1);
exec("cd " . escapeshellarg(BASE_PATH) . " && {$php} bin/taches-orientation.php inexistante 2>&1", $o2, $c2);
t('tâche inconnue → code 1',     $c2 === 1);
exec("cd " . escapeshellarg(BASE_PATH) . " && {$php} bin/taches-orientation.php quotidien --dry-run 2>&1", $o3, $c3);
t('dry-run → code 0',            $c3 === 0, implode("\n", $o3));
t('dry-run marqué',              str_contains(implode(' ', $o3), 'PRÉVISUALISATION'));
$avantDry = (int)$db->query("SELECT COUNT(*) FROM journal_activite")->fetchColumn();
exec("cd " . escapeshellarg(BASE_PATH) . " && {$php} bin/taches-orientation.php hebdomadaire --dry-run 2>&1", $o4, $c4);
t('dry-run n’écrit rien',        (int)$db->query("SELECT COUNT(*) FROM journal_activite")->fetchColumn() === $avantDry);
exec("cd " . escapeshellarg(BASE_PATH) . " && {$php} bin/taches-orientation.php rattrapage --quiet 2>&1", $o5, $c5);
t('--quiet silencieux si OK',    trim(implode('', $o5)) === '', implode("\n", $o5));

echo "\n── Nettoyage ──\n";
foreach ($ids as $id) {
    $db->exec("DELETE FROM commissions_scolarite WHERE dossier_id = {$id}");
    $db->exec("DELETE FROM journal_activite WHERE objet_type='dossier' AND objet_id={$id}");
    $db->exec("DELETE FROM recepisses WHERE dossier_id = {$id}");
    $db->exec("DELETE FROM dossiers_inscription WHERE id = {$id}");
}
$db->exec("DELETE FROM notifications_orientation WHERE type IN ('dossier_expire','dossiers_dormants','relance_bourse')");
$db->exec("DELETE FROM journal_activite WHERE type_evenement IN ('alerte_taux_boursiers','alerte_inscriptions_multiples','relance_bourse')");
$db->exec("DELETE FROM sequences_dossier");
echo "  ✓ nettoyé\n";
echo "\n══ {$ok} réussis · {$ko} échoués ══\n";
exit($ko === 0 ? 0 : 1);
