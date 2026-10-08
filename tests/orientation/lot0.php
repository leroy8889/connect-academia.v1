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


/** Smoke test Lot 0 — machine à états, référence, jeton, journal. */

define('BASE_PATH', dirname(__DIR__, 2));
require_once BASE_PATH . '/vendor/autoload.php';

foreach (file(BASE_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
    $l = trim($l);
    if ($l === '' || $l[0] === '#' || !str_contains($l, '=')) continue;
    [$k, $v] = explode('=', $l, 2);
    $_ENV[trim($k)] = trim(trim($v), '"\'');
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
use Services\Orientation\CodeEtablissement;
use Services\Orientation\JetonDossier;
use Services\Orientation\Journal;
use Services\Orientation\ReferenceDossier;

$ok = 0; $ko = 0;
function t(string $nom, bool $cond, string $detail = ''): void {
    global $ok, $ko;
    if ($cond) { $ok++; echo "  ✓ {$nom}\n"; }
    else { $ko++; echo "  ✗ {$nom} {$detail}\n"; }
}

echo "\n── Année académique ──\n";
t('debut() = int 4 chiffres', AnneeUniversitaire::debut() >= 2020);
t('code() = AAAA-AAAA', (bool)preg_match('/^\d{4}-\d{4}$/', AnneeUniversitaire::code()), AnneeUniversitaire::code());

echo "\n── Machine à états ──\n";
t('brouillon→soumis (étudiant)',       DossierStatut::peut('brouillon', 'soumis', 'etudiant'));
t('brouillon→soumis REFUSÉ à l’étab',  !DossierStatut::peut('brouillon', 'soumis', 'etablissement'));
t('soumis→valide_100 REFUSÉ (saut)',   !DossierStatut::peut('soumis', 'valide_100', 'etablissement'));
t('valide_50→valide_100 (étab)',       DossierStatut::peut('valide_50', 'valide_100', 'etablissement'));
t('valide_50→expire (système only)',   DossierStatut::peut('valide_50', 'expire', 'systeme') && !DossierStatut::peut('valide_50', 'expire', 'etablissement'));
t('valide_100 terminal',               DossierStatut::estTerminal('valide_100') && DossierStatut::transitionsPossibles('valide_100', 'etablissement') === []);
t('super_admin force un terminal',     DossierStatut::peut('valide_100', 'annule', 'super_admin'));
t('hérité "inscrit" → valide_100',     DossierStatut::normaliser('inscrit') === 'valide_100');
t('hérité "accepte" → valide_50',      DossierStatut::normaliser('accepte') === 'valide_50');
t('statut forgé → brouillon',          DossierStatut::normaliser('root') === 'brouillon');
t('progression 50',                    DossierStatut::progression('valide_50') === 50);
t('message étudiant sans nom tech',    !str_contains(DossierStatut::messageEtudiant('valide_50'), 'valide_50'));
t('motif substitué',                   str_contains(DossierStatut::messageEtudiant('rejete', 'Dossier incomplet'), 'Dossier incomplet'));
t('paliers = 3',                       count(DossierStatut::paliers('valide_50')) === 3);
t('palier 50 atteint, 100 non',        DossierStatut::paliers('valide_50')[1]['atteint'] && !DossierStatut::paliers('valide_50')[2]['atteint']);

echo "\n── Code établissement ──\n";
$db = Database::getInstance()->getConnection();
$etabId = (int)$db->query('SELECT id FROM etablissements ORDER BY id LIMIT 1')->fetchColumn();
$code = CodeEtablissement::pour($etabId);
t('code 3-5 majuscules', (bool)preg_match('/^[A-Z0-9]{3,5}$/', $code), $code);
t('idempotent', CodeEtablissement::pour($etabId) === $code, $code);
echo "    → établissement #{$etabId} = {$code}\n";

echo "\n── Référence (concurrence) ──\n";
$db->beginTransaction();
$r1 = ReferenceDossier::reserver($db, $etabId);
$db->commit();
$db->beginTransaction();
$r2 = ReferenceDossier::reserver($db, $etabId);
$db->commit();
t('format CA-AAAA-CODE-NNNNNN', ReferenceDossier::estValide($r1), $r1);
t('séquence strictement croissante', $r1 !== $r2, "{$r1} / {$r2}");
echo "    → {$r1} puis {$r2}\n";
$db->beginTransaction();
$db->rollBack();
try { ReferenceDossier::reserver($db, $etabId); t('exige une transaction', false); }
catch (\RuntimeException) { t('exige une transaction', true); }

echo "\n── Jeton de vérification ──\n";
$dossierId = (int)$db->query('SELECT id FROM dossiers_inscription ORDER BY id DESC LIMIT 1')->fetchColumn();
$j = JetonDossier::pour($dossierId);
t('token 48 hex', $j !== null && preg_match('/^[0-9a-f]{48}$/', $j['token']) === 1);
t('code court 8, alphabet sûr', $j !== null && preg_match('/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{8}$/', $j['code_court']) === 1, $j['code_court'] ?? '');
t('idempotent', JetonDossier::pour($dossierId)['token'] === $j['token']);
t('résolution par token', (JetonDossier::resoudre($j['token'])['dossier_id'] ?? 0) === $dossierId);
t('résolution par code court', (JetonDossier::resoudre($j['code_court'])['dossier_id'] ?? 0) === $dossierId);
t('résolution code formaté', (JetonDossier::resoudre(JetonDossier::formaterCodeCourt($j['code_court']))['dossier_id'] ?? 0) === $dossierId);
t('token inconnu → null', JetonDossier::resoudre(str_repeat('a', 48)) === null);
t('saisie vide → null', JetonDossier::resoudre('') === null);
$nouveau = JetonDossier::revoquerEtRegenerer($dossierId, null);
t('révocation + régénération', $nouveau !== null && $nouveau['token'] !== $j['token']);
t('ancien jeton marqué inactif', JetonDossier::resoudre($j['token'])['actif'] === false);
t('nouveau jeton actif', JetonDossier::actif($dossierId)['token'] === $nouveau['token']);

echo "\n── Transitions réelles en base ──\n";
$m = new Dossier();
$db->exec("INSERT INTO dossiers_inscription (etablissement_id, nom_complet, email, statut, annee_academique, date_creation)
           VALUES ({$etabId}, 'TEST Lot0', 'test.lot0@example.test', 'brouillon', '" . AnneeUniversitaire::code() . "', NOW())");
$testId = (int)$db->lastInsertId();

$r = $m->transition($testId, DossierStatut::VALIDE_100, 'etablissement');
t('saut brouillon→100 refusé', !$r['ok'], (string)$r['erreur']);

$r = $m->transition($testId, DossierStatut::SOUMIS, 'etudiant');
t('brouillon→soumis accepté', $r['ok'], (string)$r['erreur']);
t('soumis_le posé', !empty($r['dossier']['soumis_le']));
t('progression 0', (int)$r['dossier']['progression'] === 0);

$r = $m->transition($testId, DossierStatut::SOUMIS, 'etudiant');
t('rejeu idempotent', $r['ok']);

$r = $m->transition($testId, DossierStatut::VALIDE_50, 'etablissement', ['acteur_id' => null, 'commentaire' => 'Pièces conformes']);
t('soumis→valide_50', $r['ok'], (string)$r['erreur']);
t('progression 50', (int)$r['dossier']['progression'] === 50);
t('expire_le calculé', !empty($r['dossier']['expire_le']), (string)($r['dossier']['expire_le'] ?? ''));

$r = $m->transition($testId, DossierStatut::VALIDE_100, 'etablissement');
t('100 % SANS finances refusé', !$r['ok'], (string)$r['erreur']);

$r = $m->transition($testId, DossierStatut::VALIDE_100, 'etablissement', [
    'finances' => ['montant_scolarite_annuelle' => 0, 'montant_encaisse_initial' => 100, 'mode_paiement' => 'especes', 'date_paiement' => date('Y-m-d')],
]);
t('montant 0 refusé', !$r['ok'], (string)$r['erreur']);

$r = $m->transition($testId, DossierStatut::VALIDE_100, 'etablissement', [
    'finances' => ['montant_scolarite_annuelle' => 750000, 'montant_encaisse_initial' => 250000, 'mode_paiement' => 'root', 'date_paiement' => date('Y-m-d')],
]);
t('mode de paiement forgé refusé', !$r['ok'], (string)$r['erreur']);

$r = $m->transition($testId, DossierStatut::VALIDE_100, 'etablissement', [
    'finances' => ['montant_scolarite_annuelle' => 750000, 'montant_encaisse_initial' => 250000, 'mode_paiement' => 'mobile_money', 'reference_paiement' => 'REC-42', 'date_paiement' => date('Y-m-d')],
]);
t('valide_50→100 avec finances', $r['ok'], (string)$r['erreur']);
t('progression 100', (int)$r['dossier']['progression'] === 100);
t('taux gelé copié', $r['dossier']['taux_commission_applique'] !== null, (string)($r['dossier']['taux_commission_applique'] ?? ''));
t('montant enregistré', (float)$r['dossier']['montant_scolarite_annuelle'] === 750000.0);

// Le gel doit résister à un changement de taux ultérieur (spec §10.2)
$tauxAvant = $r['dossier']['taux_commission_applique'];
$db->exec("UPDATE etablissements SET taux_commission_scolarite = 25.00 WHERE id = {$etabId}");
$relu = $m->findById($testId);
t('taux gelé insensible au changement', $relu['taux_commission_applique'] === $tauxAvant, "{$tauxAvant} → {$relu['taux_commission_applique']}");
$db->exec("UPDATE etablissements SET taux_commission_scolarite = 10.00 WHERE id = {$etabId}");

$r = $m->transition($testId, DossierStatut::VALIDE_50, 'etablissement');
t('sortie d’état terminal refusée', !$r['ok'], (string)$r['erreur']);
$r = $m->transition($testId, DossierStatut::ANNULE, 'super_admin', ['acteur_id' => 1]);
t('super_admin sort du terminal', $r['ok'], (string)$r['erreur']);

echo "\n── Historique & journal ──\n";
$h = $db->query("SELECT ancien_statut, nouveau_statut, acteur_role FROM historique_statut_dossier WHERE dossier_id = {$testId} ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
t('4 lignes d’historique', count($h) === 4, (string)count($h));
t('chaîne cohérente', ($h[0]['nouveau_statut'] ?? '') === 'soumis' && ($h[2]['nouveau_statut'] ?? '') === 'valide_100');
$je = Journal::friseObjet('dossier', $testId);
t('journal alimenté', count($je) >= 4, (string)count($je));
t('libellés lisibles', ($je[0]['libelle'] ?? '') !== ($je[0]['type_evenement'] ?? ''));

Journal::ecrire(Journal::CONNEXION, ['utilisateur_id' => null, 'donnees' => ['test' => true]]);
t('journal tolère un contexte vide', true);

echo "\n── Nettoyage ──\n";
$db->exec("DELETE FROM journal_activite WHERE objet_type='dossier' AND objet_id={$testId}");
$db->exec("DELETE FROM dossiers_inscription WHERE id={$testId}");
$db->exec("DELETE FROM jetons_verification WHERE dossier_id={$dossierId}");
echo "  ✓ dossier de test supprimé\n";

echo "\n══ {$ok} réussis · {$ko} échoués ══\n";
exit($ko === 0 ? 0 : 1);
