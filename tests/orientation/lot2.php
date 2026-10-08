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

use Core\Database;
use Core\Texte;
use Models\Orientation\DossierStatut;
use Services\Orientation\JetonDossier;
use Services\Orientation\QrCodeVerification;
use Services\Orientation\RecepisseDossierService;
use Services\Orientation\ScanQr;

$ok=0;$ko=0;
function t(string $n, bool $c, string $d=''): void { global $ok,$ko; if($c){$ok++;echo "  ✓ $n\n";}else{$ko++;echo "  ✗ $n $d\n";} }

echo "\n── Translittération déterministe (Core\\Texte) ──\n";
t('é → e (pas L\'ea)',      Texte::sansAccents('Ariane Léa') === 'Ariane Lea', Texte::sansAccents('Ariane Léa'));
t('ô ç à û',                Texte::sansAccents('Côte çà û') === 'Cote ca u', Texte::sansAccents('Côte çà û'));
t('œ / æ',                  Texte::sansAccents('cœur æther') === 'coeur aether', Texte::sansAccents('cœur æther'));
t('apostrophe courbe',      Texte::sansAccents("l’École") === "l'Ecole", Texte::sansAccents("l’École"));
t('MRZ propre',             Texte::mrz('NZENGUE MBADINGA Ariane Léa') === 'NZENGUE<MBADINGA<ARIANE<LEA', Texte::mrz('NZENGUE MBADINGA Ariane Léa'));
t('MRZ sans < en bord',     !str_starts_with(Texte::mrz('  Léa  '), '<') && !str_ends_with(Texte::mrz('  Léa  '), '<'));
t('clé de comparaison',     Texte::cle("Côte d'Ivoire") === Texte::cle('cote d ivoire'));
t('ASCII inchangé',         Texte::sansAccents('CA-2026-AFRAM-000147') === 'CA-2026-AFRAM-000147');

echo "\n── QR code ──\n";
$tok = str_repeat('ab', 24);
$png = QrCodeVerification::dataUriPng($tok, 8, QrCodeVerification::CHEMIN_DOSSIER);
t('PNG produit',            str_starts_with($png, 'data:image/png;base64,'));
t('QR non vide',            strlen($png) > 1500, (string)strlen($png));
t('URL dossier = /v/',      str_contains(QrCodeVerification::urlDossier($tok), '/v/' . $tok));
t('URL document = /verifier/', str_contains(QrCodeVerification::url($tok), '/verifier/' . $tok));
t('token vide → chaîne vide', QrCodeVerification::dataUriPng(null) === '');

echo "\n── Guilloché ──\n";
$m = Services\Orientation\MotifSecurite::tuile();
t('tuile PNG',              str_starts_with($m, 'data:image/png;base64,'));
t('tuile légère (< 1 Ko)',  strlen($m) < 1400, (string)strlen($m));
t('mémorisée',              Services\Orientation\MotifSecurite::tuile() === $m);

echo "\n── Récépissé PDF ──\n";
$db = Database::getInstance()->getConnection();

// Fixture autonome : la suite ne doit dépendre d'aucun autre script.
$db->exec("DELETE FROM dossiers_inscription WHERE email = 'ariane.nzengue@example.ga'");
$etabId = (int)$db->query('SELECT id FROM etablissements ORDER BY id LIMIT 1')->fetchColumn();
$db->exec("INSERT INTO dossiers_inscription
  (etablissement_id, reference, nom_complet, genre, date_naissance, lieu_naissance,
   nationalite, pays_provenance, email, indicatif_tel, telephone,
   filiere_visee, specialite_visee, niveau_entree, statut, progression, annee_academique,
   statut_bourse, date_depot_anbg, bourse_declaree_le, soumis_le, date_creation)
  VALUES ({$etabId}, 'CA-2026-TEST-000147', 'NZENGUE MBADINGA Ariane Lea', 'femme',
   '2007-03-14', 'Libreville', 'Gabonaise', 'Gabon',
   'ariane.nzengue@example.ga', '+241', '06 12 34 56',
   'Licence 1', 'Genie logiciel', 'Licence 1', 'soumis', 0, '2026-2027',
   'non_boursier', NULL, '2026-08-18 14:30:00',
   '2026-08-18 14:30:00', '2026-08-18 14:30:00')");
$did = (int)$db->lastInsertId();
JetonDossier::pour($did);

foreach ([
  ['Acte de naissance', 'acte_naissance'],
  ['Releve de notes du baccalaureat', 'releve_notes'],
  ['Preuve de depot du dossier ANBG', 'preuve_depot_anbg'],
] as $i => [$lib, $code]) {
    $db->prepare("INSERT INTO documents_orientation
      (dossier_id, eleve_id, libelle, type_document, nom_original, cle_b2, mime_type,
       taille_octets, hash_sha256, statut, date_depot)
      VALUES (?, NULL, ?, ?, 'demo.pdf', ?, 'application/pdf', 1024, ?, 'en_attente', '2026-08-18 14:30:00')")
      ->execute([$did, $lib, $code, "demo/{$did}/{$code}", str_repeat((string)$i, 64)]);
}

{
    $pdf = RecepisseDossierService::rendreDirect($did);
    t('PDF rendu',           $pdf !== null);
    t('en-tête PDF valide',  $pdf !== null && str_starts_with($pdf, '%PDF-'));
    t('poids raisonnable',   $pdf !== null && strlen($pdf) > 40000 && strlen($pdf) < 400000, (string)strlen((string)$pdf));
    preg_match_all("/\/Type\s*\/Page[^s]/", (string)$pdf, $pg);
    t('tient sur une page',  count($pg[0]) === 1, count($pg[0]) . ' pages');
    // Déterminisme : deux rendus successifs donnent la même empreinte de contenu
    $a = RecepisseDossierService::rendreDirect($did);
    t('rendu reproductible', $a !== null && strlen($a) === strlen((string)$pdf), strlen((string)$a) . ' vs ' . strlen((string)$pdf));
}

echo "\n── Jeton : résolution stricte ──\n";
$j = JetonDossier::actif($did);
t('jeton présent',          $j !== null);
if ($j !== null) {
    t('token tronqué refusé',    JetonDossier::resoudre(substr($j['token'], 0, 47)) === null);
    t('token + suffixe refusé',  JetonDossier::resoudre($j['token'] . 'a') === null);
    t('injection SQL refusée',   JetonDossier::resoudre("' OR 1=1 --") === null);
    t('code court minuscules',   (JetonDossier::resoudre(strtolower($j['code_court']))['dossier_id'] ?? 0) === $did);
    t('code court avec espaces', (JetonDossier::resoudre(' ' . $j['code_court'] . ' ')['dossier_id'] ?? 0) === $did);
    t('I/O/0/1 corrigés',        JetonDossier::normaliserCodeCourt('IOOI2345') === 'JQQJ2345', (string)JetonDossier::normaliserCodeCourt('IOOI2345'));
    t('code 7 car. → null',      JetonDossier::normaliserCodeCourt('ABC2345') === null);
}

echo "\n── Journal des scans ──\n";
ScanQr::enregistrer(['dossier_id' => $did, 'resultat' => 'valide', 'acteur_role' => 'gestionnaire']);
ScanQr::enregistrer(['token_tente' => "<script>x</script>", 'resultat' => 'forge_inconnu']);
$n = (int)$db->query("SELECT COUNT(*) FROM scans_qr WHERE dossier_id = {$did}")->fetchColumn();
t('scan réussi tracé',      $n >= 1);
$forge = $db->query("SELECT token_tente, resultat FROM scans_qr ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
t('résultat forgé normalisé', $forge['resultat'] === 'introuvable', (string)$forge['resultat']);
t('token tenté assaini',    !str_contains((string)$forge['token_tente'], '<'), (string)$forge['token_tente']);
t('résultat par statut 50',  ScanQr::resultatPourStatut(DossierStatut::VALIDE_50) === 'valide');
t('résultat par statut 100', ScanQr::resultatPourStatut(DossierStatut::VALIDE_100) === 'deja_inscrit');
t('résultat par statut soumis', ScanQr::resultatPourStatut(DossierStatut::SOUMIS) === 'non_valide_50');

echo "\n── Nettoyage ──\n";
$db->exec("DELETE FROM scans_qr WHERE dossier_id = {$did} OR (token_tente IS NOT NULL AND dossier_id IS NULL)");
$db->exec("DELETE FROM journal_activite WHERE objet_type='dossier' AND objet_id={$did}");
$db->exec("DELETE FROM dossiers_inscription WHERE id = {$did}");
echo "  ✓ nettoyé\n";
echo "\n══ {$ok} réussis · {$ko} échoués ══\n";
exit($ko === 0 ? 0 : 1);
