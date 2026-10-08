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
use Services\Orientation\SituationBourse;
use Services\Orientation\SoumissionDossier;

$ok=0;$ko=0;
function t(string $n, bool $c, string $d=''): void { global $ok,$ko; if($c){$ok++;echo "  ✓ $n\n";}else{$ko++;echo "  ✗ $n $d\n";} }

echo "\n── Validation bourse (anti-falsification client) ──\n";
$r = SituationBourse::valider([]);
t('sans réponse → refusé', isset($r['erreurs']['statut_bourse']));
$r = SituationBourse::valider(['statut_bourse' => 'boursier_special']);
t('valeur forgée → refusée', isset($r['erreurs']['statut_bourse']));
t('valeur forgée non réfléchie', $r['valeurs']['statut_bourse'] === null);
// « Mon dossier est en cours de traitement à l'ANBG » a été RETIRÉ du parcours
// (Database/migration_suppression_dossier_en_cours.sql). Un POST qui le
// présente encore — formulaire resté ouvert, script, cache navigateur — doit
// être refusé comme n'importe quelle valeur forgée, sans être réfléchi.
$r = SituationBourse::valider(['statut_bourse' => 'dossier_en_cours']);
t('statut retiré → refusé', isset($r['erreurs']['statut_bourse']));
t('statut retiré non réfléchi', $r['valeurs']['statut_bourse'] === null);
t('deux situations seulement', SituationBourse::valeurs() === ['non_boursier', 'boursier'], implode(',', SituationBourse::valeurs()));
t('deux options proposées', count(SituationBourse::options()) === 2);
t('libellés attendus',
   SituationBourse::libelle('non_boursier') === 'Je ne suis pas boursier'
   && SituationBourse::libelle('boursier') === 'Je suis boursier');
t('libellé du statut retiré encore lisible (historique)',
   SituationBourse::libelleCourt('dossier_en_cours') !== 'Non renseignée');
$r = SituationBourse::valider(['statut_bourse' => 'dossier_en_cours', 'date_depot_anbg' => date('Y-m-d')]);
t('date de dépôt ANBG jamais reprise', $r['valeurs']['date_depot_anbg'] === null);
$r = SituationBourse::valider(['statut_bourse' => 'boursier', 'numero_attestation_anbg' => "<script>alert(1)</script>"]);
t('numéro ANBG nettoyé', !str_contains((string)$r['valeurs']['numero_attestation_anbg'], '<'), (string)$r['valeurs']['numero_attestation_anbg']);
$r = SituationBourse::valider(['statut_bourse' => 'boursier', 'numero_attestation_anbg' => str_repeat('A', 80)]);
t('numéro trop long refusé', isset($r['erreurs']['numero_attestation_anbg']));
$r = SituationBourse::valider(['statut_bourse' => 'boursier']);
t('boursier sans numéro → OK (facultatif)', $r['erreurs'] === []);
$r = SituationBourse::valider(['statut_bourse' => 'non_boursier', 'date_depot_anbg' => '2026-01-01', 'numero_attestation_anbg' => 'X']);
t('champs hors contexte ignorés', $r['erreurs'] === [] && $r['valeurs']['date_depot_anbg'] === null && $r['valeurs']['numero_attestation_anbg'] === null);

echo "\n── Pièce conditionnelle ──\n";
t('boursier → attestation obligatoire', SituationBourse::pieceAssociee('boursier') === 'attestation_bourse' && SituationBourse::pieceObligatoire('boursier'));
t('statut retiré → plus aucune pièce associée', SituationBourse::pieceAssociee('dossier_en_cours') === null);
t('preuve de dépôt reste reconnue comme conditionnelle',
   SituationBourse::estPieceConditionnelle('preuve_depot_anbg'));
t('non_boursier → aucune pièce', SituationBourse::pieceAssociee('non_boursier') === null);

echo "\n── Liste dynamique en base ──\n";
$db = Database::getInstance()->getConnection();
$etabId = (int)$db->query('SELECT id FROM etablissements ORDER BY id LIMIT 1')->fetchColumn();
$m = new Dossier();
$pNon = $m->piecesPourBourse($etabId, 'non_boursier');
$pBou = $m->piecesPourBourse($etabId, 'boursier');
$codes = fn(array $l): array => array_values(array_filter(array_column($l, 'code_interne')));
t('non_boursier : pas de pièce ANBG', !in_array('attestation_bourse', $codes($pNon), true) && !in_array('preuve_depot_anbg', $codes($pNon), true));
t('boursier : attestation présente', in_array('attestation_bourse', $codes($pBou), true));
t('boursier : pas la preuve de dépôt', !in_array('preuve_depot_anbg', $codes($pBou), true));
// La ligne de référentiel « Preuve de dépôt du dossier ANBG » a été supprimée
// par la migration : elle n'est plus proposée à personne, quelle que soit la
// situation demandée — y compris l'ancienne, si elle est encore forgée.
$pEnc = $m->piecesPourBourse($etabId, 'dossier_en_cours');
t('statut retiré : plus aucune preuve de dépôt', !in_array('preuve_depot_anbg', $codes($pEnc), true));
t('statut retiré : liste ramenée au tronc commun', count($pEnc) === count($pNon), count($pEnc) . ' vs ' . count($pNon));
t('liste boursier > liste non-boursier', count($pBou) === count($pNon) + 1, count($pBou) . ' vs ' . count($pNon));

$oblNon = $m->piecesObligatoiresPourBourse($etabId, 'non_boursier');
$oblBou = $m->piecesObligatoiresPourBourse($etabId, 'boursier');
t('attestation dans les obligatoires du boursier', count($oblBou) === count($oblNon) + 1, count($oblBou) . ' vs ' . count($oblNon));

echo "\n── Déclaration + historique ──\n";
$db->exec("INSERT INTO dossiers_inscription (etablissement_id, nom_complet, email, statut, annee_academique, date_creation)
           VALUES ({$etabId}, 'TEST Lot1', 'test.lot1@example.test', 'brouillon', '" . AnneeUniversitaire::code() . "', NOW())");
$did = (int)$db->lastInsertId();

t('statut retiré → écriture refusée', !SituationBourse::enregistrer($did, ['statut_bourse' => 'dossier_en_cours']));
t('déclaration enregistrée', SituationBourse::enregistrer($did, ['statut_bourse' => 'non_boursier']));
$d = $m->findById($did);
t('statut stocké', $d['statut_bourse'] === 'non_boursier');
t('horodatage posé par le serveur', !empty($d['bourse_declaree_le']));
t('declare_par = etudiant', $d['bourse_declaree_par'] === 'etudiant');
t('rejeu identique = pas d’historique en double', !SituationBourse::enregistrer($did, ['statut_bourse' => 'non_boursier']));
t('bascule vers boursier', SituationBourse::enregistrer($did, ['statut_bourse' => 'boursier', 'numero_attestation_anbg' => 'ANBG/2026/1'], SituationBourse::PAR_ETUDIANT));
$d = $m->findById($did);
t('date ANBG effacée hors contexte', $d['date_depot_anbg'] === null);
t('numéro conservé', $d['numero_attestation_anbg'] === 'ANBG/2026/1');
$h = SituationBourse::historique($did);
t('2 lignes d’historique', count($h) === 2, (string)count($h));
t('chaînage ancien→nouveau', $h[1]['ancien_statut'] === 'non_boursier' && $h[1]['nouveau_statut'] === 'boursier');
t('statut du dossier inchangé par la bourse', DossierStatut::normaliser($d['statut']) === DossierStatut::BROUILLON);
t('phrase document', str_contains(SituationBourse::phrase($d), 'Boursier') && str_contains(SituationBourse::phrase($d), 'ANBG/2026/1'), SituationBourse::phrase($d));

echo "\n── Pièces manquantes (contrôle serveur) ──\n";
$d = $m->findById($did);
$manq = $m->piecesManquantes($did, $etabId, 'boursier', []);
t('boursier sans pièces → manquantes détectées', $manq !== []);
$libelles = array_column($manq, 'libelle');
t('attestation de bourse listée', in_array('Attestation de bourse ANBG', $libelles, true), implode(' | ', $libelles));

echo "\n── Soumission ──\n";
$etab = $db->query("SELECT * FROM etablissements WHERE id = {$etabId}")->fetch(PDO::FETCH_ASSOC);
$r = SoumissionDossier::executer($m->findById($did), $etab, null);
t('soumission OK', $r['ok'], (string)$r['erreur']);
t('référence attribuée', $r['reference'] !== null && str_starts_with((string)$r['reference'], 'CA-'), (string)$r['reference']);
t('jeton émis', $r['jeton'] !== null && strlen((string)$r['jeton']['token']) === 48);
t('statut = soumis', DossierStatut::normaliser($r['dossier']['statut']) === DossierStatut::SOUMIS);
t('soumis_le posé', !empty($r['dossier']['soumis_le']));
$ref1 = $r['reference'];
$r2 = SoumissionDossier::executer($m->findById($did), $etab, null);
t('rejeu : même référence', $r2['reference'] === $ref1, "{$ref1} / {$r2['reference']}");
t('rejeu : même jeton', $r2['jeton']['token'] === $r['jeton']['token']);
t('rejeu signalé deja_soumis', $r2['deja_soumis'] === true);

echo "\n── Mode de paiement ──\n";
t('défaut = presentiel', ($etab['mode_paiement_inscription'] ?? '') === 'presentiel', (string)($etab['mode_paiement_inscription'] ?? ''));

echo "\n── Nettoyage ──\n";
$db->exec("DELETE FROM journal_activite WHERE objet_type='dossier' AND objet_id={$did}");
$db->exec("DELETE FROM dossiers_inscription WHERE id={$did}");
$db->exec("DELETE FROM sequences_dossier");
echo "  ✓ nettoyé\n";
echo "\n══ {$ok} réussis · {$ko} échoués ══\n";
exit($ko === 0 ? 0 : 1);
