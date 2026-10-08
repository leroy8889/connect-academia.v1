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
use Services\Orientation\CommissionDossier;
use Services\Orientation\DecisionEtablissement;
use Services\Orientation\SituationBourse;

$ok=0;$ko=0;
function t(string $n, bool $c, string $d=''): void { global $ok,$ko; if($c){$ok++;echo "  ✓ $n\n";}else{$ko++;echo "  ✗ $n $d\n";} }

$db = Database::getInstance()->getConnection();
$etabId = (int)$db->query('SELECT id FROM etablissements ORDER BY id LIMIT 1')->fetchColumn();

// La grille par formation et les montants portés par la filière priment sur
// la fiche que ce test écrit : on les met en sommeil, puis on les restaure.
require_once __DIR__ . '/_tarifs_isolation.php';
isolerTarifsEtablissement($db, $etabId);
$m = new Dossier();

// ── Tarifs de l'établissement de test ───────────────────────────────────────
// La scolarité ne se saisit plus au guichet : elle vient de la fiche
// établissement (Services\Orientation\TarifCycle), tarif NON boursier du cycle.
// On fixe donc ici ce que les assertions attendent, et on rend la fiche à son
// état d'origine quoi qu'il arrive — y compris si la suite s'interrompt.
$tarifsOrigine = $db->query(
    "SELECT frais_scolarite, frais_scolarite_master, frais_scolarite_boursier,
            prix_inscription, frais_dossier
     FROM etablissements WHERE id = {$etabId}"
)->fetch(PDO::FETCH_ASSOC) ?: [];
register_shutdown_function(static function () use ($db, $etabId, $tarifsOrigine): void {
    if ($tarifsOrigine === []) { return; }
    $st = $db->prepare(
        'UPDATE etablissements
         SET frais_scolarite = ?, frais_scolarite_master = ?, frais_scolarite_boursier = ?,
             prix_inscription = ?, frais_dossier = ?
         WHERE id = ?'
    );
    $st->execute([
        (int)$tarifsOrigine['frais_scolarite'],
        (int)$tarifsOrigine['frais_scolarite_master'],
        (int)$tarifsOrigine['frais_scolarite_boursier'],
        (int)$tarifsOrigine['prix_inscription'],
        (int)$tarifsOrigine['frais_dossier'],
        $etabId,
    ]);
});
/** Fixe les tarifs de la fiche établissement pour la suite des assertions. */
$fixerTarifs = static function (int $licence, int $inscription = 0, int $dossier = 0)
                              use ($db, $etabId): void {
    $db->prepare(
        'UPDATE etablissements
         SET frais_scolarite = ?, frais_scolarite_master = 0, frais_scolarite_boursier = ?,
             prix_inscription = ?, frais_dossier = ?
         WHERE id = ?'
    )->execute([$licence, max(0, (int)round($licence * 0.6)), $inscription, $dossier, $etabId]);
};
$fixerTarifs(600000, 0, 0);

echo "\n── Messages étudiants : jamais de nom technique (spec §9.2) ──\n";
foreach (DossierStatut::tous() as $st) {
    $msg = DossierStatut::messageEtudiant($st, 'Motif de test');
    t("« {$st} » traduit", $msg !== '' && !str_contains($msg, $st) && !str_contains($msg, '_'), $msg);
}
t('motif substitué',      str_contains(DossierStatut::messageEtudiant('rejete', 'Diplôme non conforme'), 'Diplôme non conforme'));
t('motif absent → mention', str_contains(DossierStatut::messageEtudiant('rejete', null), 'non précisé'));

echo "\n── Paliers (spec §9.1) ──\n";
foreach ([['brouillon',0],['soumis',1],['valide_50',2],['valide_100',3]] as [$st,$attendus]) {
    $p = DossierStatut::paliers($st);
    $n = count(array_filter($p, fn($x) => $x['atteint']));
    t("« {$st} » → {$attendus} palier(s) atteint(s)", $n === $attendus, (string)$n);
}
t('toujours 3 paliers', count(DossierStatut::paliers('rejete')) === 3);

echo "\n── Action attendue de l’étudiant ──\n";
t('piece_manquante → action',  DossierStatut::actionEtudiant('piece_manquante') !== null);
t('valide_50 → récépissé',     str_contains((string)DossierStatut::actionEtudiant('valide_50')['url_suffixe'], 'recepisse'));
t('valide_100 → attestation',  str_contains((string)DossierStatut::actionEtudiant('valide_100')['url_suffixe'], 'attestation'));
t('soumis → aucune action',    DossierStatut::actionEtudiant('soumis') === null);
t('rejete → aucune action',    DossierStatut::actionEtudiant('rejete') === null);

echo "\n── Mise à jour de la bourse après inscription (spec §9.4) ──\n";
// La référence fait de ce dossier une INSCRIPTION : sans elle, la plateforme
// y voit une préinscription et lui refuse la validation à 100 %.
$db->exec("INSERT INTO dossiers_inscription
  (etablissement_id, nom_complet, email, filiere_visee, niveau_entree, statut, progression,
   annee_academique, statut_bourse, reference, soumis_le, date_creation)
  VALUES ({$etabId}, 'TEST Lot4', 'test.lot4@example.test', 'Licence 1', 'Licence 1',
   'soumis', 0, '" . AnneeUniversitaire::code() . "', 'non_boursier', 'CA-" . date('Y') . "-TL4-000001', NOW(), NOW())");
$did = (int)$db->lastInsertId();

DecisionEtablissement::valider50($did, $etabId, null);
DecisionEtablissement::valider100($did, $etabId, null, ['montant_scolarite_annuelle'=>'600000','montant_encaisse_initial'=>'200000','mode_paiement'=>'especes','date_paiement'=>date('Y-m-d')]);

$avant = CommissionDossier::pourDossier($did);
t('inscrit à 100 %',            DossierStatut::normaliser($m->findById($did)['statut']) === DossierStatut::VALIDE_100);
t('commission présente',        $avant !== null);

// La bascule de bourse APRÈS les 100 % ne doit toucher ni le statut, ni la commission
$change = SituationBourse::enregistrer($did, ['statut_bourse' => 'boursier', 'numero_attestation_anbg' => 'ANBG/2026/99']);
t('bascule acceptée après 100 %', $change);
$apres = $m->findById($did);
t('statut du dossier inchangé', DossierStatut::normaliser($apres['statut']) === DossierStatut::VALIDE_100);
t('progression inchangée',      (int)$apres['progression'] === 100);
$c2 = CommissionDossier::pourDossier($did);
t('montant de commission inchangé', (float)$c2['montant_commission'] === (float)$avant['montant_commission']);
t('taux de commission inchangé',    (float)$c2['taux_commission'] === (float)$avant['taux_commission']);
t('statut de commission inchangé',  $c2['statut'] === $avant['statut']);
t('finances inchangées',        (float)$apres['montant_scolarite_annuelle'] === 600000.0);
t('historique de bourse écrit', count(SituationBourse::historique($did)) >= 1);

echo "\n── Suivi des commissions (spec §10.3) ──\n";
$totaux = CommissionDossier::totaux([]);
t('tous les statuts dans les totaux',
    count($totaux) === count(CommissionDossier::statuts()), implode(',', array_keys($totaux)));
t('a_facturer alimenté',        $totaux['a_facturer']['nombre'] >= 1);
$liste = CommissionDossier::rechercher(['etablissement_id' => $etabId], 10);
t('recherche filtrée',          $liste !== []);
t('jointure dossier',           array_key_exists('reference', $liste[0]) && array_key_exists('statut_bourse', $liste[0]));

$cid = (int)$avant['id'];
t('a_facturer → facturee',      CommissionDossier::changerStatut($cid, 'facturee', null));
t('statut forgé refusé',        !CommissionDossier::changerStatut($cid, 'gratuite', null));
t('facturee → reglee',          CommissionDossier::changerStatut($cid, 'reglee', null, 'VIR-2026-01'));
$c3 = CommissionDossier::pourDossier($did);
t('regle_le horodaté',          !empty($c3['regle_le']));
t('référence enregistrée',      $c3['reference_reglement'] === 'VIR-2026-01');
t('réglée → verrouillée',       !CommissionDossier::changerStatut($cid, 'a_facturer', null));
t('réglée non annulable',       !CommissionDossier::annuler($did, null));

echo "\n── Libellés hérités ──\n";
t('en_cours → À facturer',      CommissionDossier::libelle('en_cours') === 'À facturer');
t('solde → Réglée',             CommissionDossier::libelle('solde') === 'Réglée');

echo "\n── Nettoyage ──\n";
$db->exec("DELETE FROM commissions_scolarite WHERE dossier_id = {$did}");
$db->exec("DELETE FROM journal_activite WHERE objet_type='dossier' AND objet_id={$did}");
$db->exec("DELETE FROM dossiers_inscription WHERE id = {$did}");
$db->exec("DELETE FROM sequences_dossier");
echo "  ✓ nettoyé\n";
echo "\n══ {$ok} réussis · {$ko} échoués ══\n";
exit($ko === 0 ? 0 : 1);
