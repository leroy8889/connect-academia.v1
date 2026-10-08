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
$autreEtab = (int)$db->query("SELECT id FROM etablissements WHERE id <> {$etabId} ORDER BY id LIMIT 1")->fetchColumn();
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
$fixerTarifs(750000, 0, 0);

/**
 * Dossier d'INSCRIPTION de test.
 *
 * La référence n'est pas décorative : c'est elle qui distingue un dossier
 * d'inscription d'une préinscription (Dossier::estInscription). Sans elle, le
 * dossier fabriqué ici est une préinscription — et les décisions du parcours
 * d'inscription (50 %, 100 %, pièce, prolongation) lui sont refusées, comme
 * elles le sont en production.
 */
function nouveauDossier(PDO $db, int $etabId, string $statut = 'soumis', ?string $bourse = 'non_boursier'): int {
    static $seq = 0;
    $reference = sprintf('CA-%s-TL3-%06d', date('Y'), ++$seq);
    $db->exec("INSERT INTO dossiers_inscription
      (etablissement_id, nom_complet, email, filiere_visee, niveau_entree, statut, progression,
       annee_academique, statut_bourse, reference, soumis_le, date_creation)
      VALUES ({$etabId}, 'TEST Lot3', 'test.lot3@example.test', 'Licence 1', 'Licence 1',
       '{$statut}', 0, '" . AnneeUniversitaire::code() . "', " . ($bourse ? "'{$bourse}'" : 'NULL') . ", '{$reference}', NOW(), NOW())");
    return (int)$db->lastInsertId();
}

echo "\n── Validation des données financières ──\n";
$base = ['montant_scolarite_annuelle'=>'750000','montant_encaisse_initial'=>'250000','mode_paiement'=>'especes','date_paiement'=>date('Y-m-d')];
t('jeu complet accepté',       DecisionEtablissement::validerFinances($base)['erreurs'] === []);
t('montant absent refusé',     isset(DecisionEtablissement::validerFinances(['mode_paiement'=>'especes','date_paiement'=>date('Y-m-d')])['erreurs']['montant_scolarite_annuelle']));
t('montant 0 refusé',          isset(DecisionEtablissement::validerFinances(['montant_scolarite_annuelle'=>'0']+$base)['erreurs']['montant_scolarite_annuelle']));
t('montant négatif refusé',    isset(DecisionEtablissement::validerFinances(['montant_scolarite_annuelle'=>'-5000']+$base)['erreurs']['montant_scolarite_annuelle']));
t('montant non numérique refusé', isset(DecisionEtablissement::validerFinances(['montant_scolarite_annuelle'=>'750000; DROP']+$base)['erreurs']['montant_scolarite_annuelle']));
t('espaces tolérés',           DecisionEtablissement::validerFinances(['montant_scolarite_annuelle'=>'750 000']+$base)['erreurs'] === []);
t('virgule décimale tolérée',  DecisionEtablissement::validerFinances(['montant_scolarite_annuelle'=>'750000,50']+$base)['erreurs'] === []);
t('encaissé > scolarité signalé', isset(DecisionEtablissement::validerFinances(['montant_encaisse_initial'=>'900000']+$base)['erreurs']['montant_encaisse_initial']));
t('mode forgé refusé',         isset(DecisionEtablissement::validerFinances(['mode_paiement'=>'bitcoin']+$base)['erreurs']['mode_paiement']));
t('date future refusée',       isset(DecisionEtablissement::validerFinances(['date_paiement'=>date('Y-m-d',strtotime('+2 days'))]+$base)['erreurs']['date_paiement']));
t('date invalide refusée',     isset(DecisionEtablissement::validerFinances(['date_paiement'=>'2026-02-31']+$base)['erreurs']['date_paiement']));

echo "\n── Validation à 50 % ──\n";
$d1 = nouveauDossier($db, $etabId);
$r = DecisionEtablissement::valider50($d1, $etabId, null, 'Pièces conformes');
t('valider 50 OK',             $r['ok'], (string)$r['message']);
t('statut = valide_50',        DossierStatut::normaliser($r['dossier']['statut']) === DossierStatut::VALIDE_50);
t('expire_le posé',            !empty($r['dossier']['expire_le']));
t('progression 50',            (int)$r['dossier']['progression'] === 50);

echo "\n── Cloisonnement multi-établissement ──\n";
if ($autreEtab > 0) {
    $r = DecisionEtablissement::valider50($d1, $autreEtab, null);
    t('autre établissement refusé', !$r['ok'], (string)$r['message']);
} else { t('autre établissement refusé', true, '(un seul établissement en base)'); }
$r = DecisionEtablissement::valider50(999999, $etabId, null);
t('dossier inexistant refusé', !$r['ok']);

echo "\n── Validation à 100 % ──\n";
$r = DecisionEtablissement::valider100($d1, $etabId, null, []);
t('100 % sans finances refusé', !$r['ok'] && $r['erreurs'] !== []);
$r = DecisionEtablissement::valider100($d1, $etabId, null, ['montant_scolarite_annuelle'=>'750000','montant_encaisse_initial'=>'250000','mode_paiement'=>'mobile_money','reference_paiement'=>'REC-77','date_paiement'=>date('Y-m-d')]);
t('100 % avec finances OK',    $r['ok'], (string)$r['message']);
$apres = $m->findById($d1);
t('statut = valide_100',       DossierStatut::normaliser($apres['statut']) === DossierStatut::VALIDE_100);
t('montant enregistré',        (float)$apres['montant_scolarite_annuelle'] === 750000.0);
t('mode enregistré',           $apres['mode_paiement'] === 'mobile_money');
t('taux gelé',                 $apres['taux_commission_applique'] !== null);

echo "\n── Commission ──\n";
$c = CommissionDossier::pourDossier($d1);
t('commission créée',          $c !== null);
t('base = scolarité annuelle', $c !== null && (float)$c['montant_scolarite'] === 750000.0);
t('montant = base × taux',     $c !== null && abs((float)$c['montant_commission'] - 750000.0 * (float)$c['taux_commission'] / 100) < 0.01);
t('statut a_facturer',         $c !== null && $c['statut'] === 'a_facturer');
t('période AAAA-MM',           $c !== null && preg_match('/^\d{4}-\d{2}$/', (string)$c['periode_facturation']) === 1);
$avant = $c['id'];
CommissionDossier::generer($m->findById($d1), null);
t('pas de double facturation', CommissionDossier::pourDossier($d1)['id'] === $avant);

// Le gel doit résister à un changement de taux (spec §10.2)
$tauxGele = (float)$c['taux_commission'];
$db->exec("UPDATE etablissements SET taux_commission_scolarite = 22.00 WHERE id = {$etabId}");
t('commission insensible au nouveau taux', (float)CommissionDossier::pourDossier($d1)['taux_commission'] === $tauxGele);
$db->exec("UPDATE etablissements SET taux_commission_scolarite = 10.00 WHERE id = {$etabId}");

echo "\n── Commission due quel que soit le statut de bourse (spec §10.1) ──\n";

// RÈGLE RÉVISÉE (migration_tarifs_formation.sql §3) : aucun statut n'exonère de
// commission, mais la BASE dépend de la situation — les frais d'inscription
// pour un boursier confirmé, la scolarité pour les deux autres.
//
// Les frais d'inscription doivent donc être renseignés ici, sinon un boursier
// n'aurait littéralement aucune base à commissionner. Ce n'est pas un artifice
// de test : c'est exactement la situation d'un établissement partenaire qui ne
// facture pas l'inscription — il ne verse alors rien sur ses boursiers.
$fixerTarifs(750000, 40000, 5000);

foreach (['non_boursier', 'boursier'] as $bourse) {
    $dx = nouveauDossier($db, $etabId, 'soumis', $bourse);
    DecisionEtablissement::valider50($dx, $etabId, null);
    DecisionEtablissement::valider100($dx, $etabId, null, ['montant_scolarite_annuelle'=>'500000','montant_encaisse_initial'=>'100000','mode_paiement'=>'especes','date_paiement'=>date('Y-m-d')]);
    $cx = CommissionDossier::pourDossier($dx);
    t("commission générée — {$bourse}", $cx !== null && (float)$cx['montant_commission'] > 0,
        $cx === null ? 'aucune commission' : (string)$cx['montant_commission']);
    // La base attendue diffère selon le statut : 45 000 de frais d'inscription
    // (40 000 + 5 000) pour le boursier, la scolarité de la fiche pour les
    // deux autres — 750 000, et non les 500 000 postés : depuis la refonte
    // tarifaire, le guichet ne fixe plus les montants, il les constate.
    $baseAttendue = $bourse === 'boursier' ? 45000.0 : 750000.0;
    t("base cohérente avec le statut — {$bourse}",
        $cx !== null && (float)$cx['montant_scolarite'] === $baseAttendue,
        $cx === null ? '' : (string)$cx['montant_scolarite']);
    $db->exec("DELETE FROM commissions_scolarite WHERE dossier_id = {$dx}");
    $db->exec("DELETE FROM journal_activite WHERE objet_type='dossier' AND objet_id={$dx}");
    $db->exec("DELETE FROM dossiers_inscription WHERE id = {$dx}");
}

echo "\n── Rejet et demande de pièce ──\n";
$d2 = nouveauDossier($db, $etabId);
$r = DecisionEtablissement::rejeter($d2, $etabId, null, 'court');
t('motif trop court refusé',   !$r['ok']);
$r = DecisionEtablissement::rejeter($d2, $etabId, null, '');
t('motif vide refusé',         !$r['ok']);
$r = DecisionEtablissement::demanderPiece($d2, $etabId, null, '   ');
t('pièces attendues vides refusées', !$r['ok']);
$r = DecisionEtablissement::demanderPiece($d2, $etabId, null, 'Relevé de notes lisible');
t('demande de pièce OK',       $r['ok'], (string)$r['message']);
t('statut piece_manquante',    DossierStatut::normaliser($r['dossier']['statut']) === DossierStatut::PIECE_MANQUANTE);
t('pièces attendues stockées', str_contains((string)$r['dossier']['pieces_attendues'], 'Relevé'));
$r = DecisionEtablissement::rejeter($d2, $etabId, null, 'Le diplôme fourni ne correspond pas au niveau requis.');
t('rejet depuis piece_manquante', $r['ok'], (string)$r['message']);
t('motif enregistré',          str_contains((string)$r['dossier']['motif_rejet'], 'diplôme'));
$r = DecisionEtablissement::valider50($d2, $etabId, null);
t('état terminal verrouillé',  !$r['ok']);

echo "\n── Prolongation ──\n";
$d3 = nouveauDossier($db, $etabId);
DecisionEtablissement::valider50($d3, $etabId, null);
$avantDate = $m->findById($d3)['expire_le'];
$r = DecisionEtablissement::prolonger($d3, $etabId, null, 15);
t('prolongation OK',           $r['ok'], (string)$r['message']);
t('date repoussée',            strtotime((string)$r['dossier']['expire_le']) > strtotime((string)$avantDate));
t('0 jour refusé',             !DecisionEtablissement::prolonger($d3, $etabId, null, 0)['ok']);
t('999 jours refusé',          !DecisionEtablissement::prolonger($d3, $etabId, null, 999)['ok']);

echo "\n── Liste filtrable ──\n";
$tous = $m->countByEtabFiltre($etabId, []);
t('comptage global',           $tous > 0, (string)$tous);
$c50 = $m->countByEtabFiltre($etabId, ['statut' => 'valide_50']);
t('filtre par statut',         $c50 >= 1, (string)$c50);
t('statut forgé ignoré',       $m->countByEtabFiltre($etabId, ['statut' => "' OR 1=1 --"]) === $tous);
t('recherche par référence',   is_array($m->findByEtabFiltre($etabId, ['q' => 'CA-2026'])));
t('joker SQL échappé',         $m->countByEtabFiltre($etabId, ['q' => '%']) <= $tous);
$comp = $m->compteursParStatut($etabId);
t('compteurs complets',        count($comp) === count(DossierStatut::tous()));
t('somme = total',             array_sum($comp) === $tous, array_sum($comp) . ' vs ' . $tous);
$liste = $m->findByEtabFiltre($etabId, [], 5);
t('ancienneté calculée',       $liste !== [] && array_key_exists('anciennete', $liste[0]));
t('limite respectée',          count($liste) <= 5);

echo "\n── Nettoyage ──\n";
foreach ([$d1, $d2, $d3] as $id) {
    $db->exec("DELETE FROM commissions_scolarite WHERE dossier_id = {$id}");
    $db->exec("DELETE FROM journal_activite WHERE objet_type='dossier' AND objet_id={$id}");
    $db->exec("DELETE FROM dossiers_inscription WHERE id = {$id}");
}
$db->exec("DELETE FROM sequences_dossier");
echo "  ✓ nettoyé\n";
echo "\n══ {$ok} réussis · {$ko} échoués ══\n";
exit($ko === 0 ? 0 : 1);
