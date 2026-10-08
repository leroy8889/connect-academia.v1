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

/**
 * LOT 9 — Tarification par formation / niveau / bourse, et base de commission.
 *
 *   §A  Niveau : liste fermée, lecture du texte libre historique
 *   §B  Grille tarifaire : les deux pratiques du terrain, et l'ordre des replis
 *   §C  Frais d'inscription par cycle et par situation de bourse
 *   §D  Base de commission selon la bourse (bout en bout, via le guichet)
 *   §E  Changement de situation de bourse : recalcul, refus, signalement
 *   §F  Facture proforma : tarif boursier
 *
 * Comme les autres lots : crée ses données, les nettoie, rejouable à l'infini.
 * Tourne sur la base de DÉVELOPPEMENT — jamais sur la production.
 */
define('BASE_PATH', dirname(__DIR__, 2));
define('BASE_URL', '');
require_once BASE_PATH . '/vendor/autoload.php';
foreach (file(BASE_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
    $l = trim($l); if ($l === '' || $l[0] === '#' || !str_contains($l, '=')) continue;
    [$k, $v] = explode('=', $l, 2); $_ENV[trim($k)] = trim(trim($v), '"\'');
}
$_ENV['MAIL_DISABLED'] = 'true';
spl_autoload_register(function (string $c): void {
    $f = BASE_PATH . '/app/' . str_replace('\\', '/', $c) . '.php';
    if (file_exists($f)) require_once $f;
});

use Core\AnneeUniversitaire;
use Core\Database;
use Models\Orientation\Dossier;
use Models\Orientation\TarifFormation;
use Services\Orientation\CommissionDossier;
use Services\Orientation\DecisionEtablissement;
use Services\Orientation\FactureProformaService;
use Services\Orientation\Niveau;
use Services\Orientation\RecepisseService;
use Services\Orientation\SituationBourse;
use Services\Orientation\TarifCycle;

$ok = 0; $ko = 0;

/**
 * Nombre de pages d'un PDF produit par dompdf.
 *
 * Le catalogue /Pages porte /Count : on le lit plutôt que de compter les
 * objets /Type /Page, dont la graphie varie selon la version du moteur.
 */
function nbPagesPdf(string $pdf): int {
    if (preg_match_all('#/Count\s+(\d+)#', $pdf, $m)) {
        return max(array_map('intval', $m[1]));
    }
    return substr_count($pdf, '/Type /Page') - substr_count($pdf, '/Type /Pages');
}

function t(string $n, bool $c, string $d = ''): void {
    global $ok, $ko;
    if ($c) { $ok++; echo "  ✓ $n\n"; } else { $ko++; echo "  ✗ $n" . ($d !== '' ? "  → $d" : '') . "\n"; }
}

$db     = Database::getInstance()->getConnection();
$modele = new Dossier();
$annee  = AnneeUniversitaire::code();
$MAIL   = 'test.lot9@example.test';
$SLUG   = 'zz-lot9';

// ── Établissement de test, entièrement à nous : aucun tarif réel n'est touché,
//    contrairement au lot 8 qui emprunte le premier établissement de la base.
$db->exec("DELETE FROM etablissements WHERE slug = " . $db->quote($SLUG));
$db->exec(
    "INSERT INTO etablissements
       (slug, nom, type, ville, statut, taux_commission_scolarite,
        prix_inscription, frais_dossier,
        frais_inscription_licence, frais_inscription_licence_boursier,
        frais_inscription_master,  frais_inscription_master_boursier,
        frais_scolarite, frais_scolarite_boursier,
        frais_scolarite_master, frais_scolarite_master_boursier)
     VALUES (" . $db->quote($SLUG) . ", 'ZZ Lot9', 'institut', 'Libreville', 'brouillon', 10.00,
             25000, 5000,
             60000, 30000,
             80000, 40000,
             500000, 400000,
             900000, 700000)"
);
$etabId = (int)$db->lastInsertId();

$db->prepare("INSERT INTO filieres_etablissement (etablissement_id, nom) VALUES (?, 'Droit ZZ9')")->execute([$etabId]);
$filiereId = (int)$db->lastInsertId();

$userId = (int)$db->query('SELECT id FROM users WHERE is_deleted = 0 ORDER BY id LIMIT 1')->fetchColumn();

register_shutdown_function(static function () use ($db, $etabId, $MAIL): void {
    // Les dossiers partent d'abord : commissions et tranches suivent par cascade.
    $db->exec("DELETE FROM journal_activite WHERE objet_type = 'dossier' AND objet_id IN
               (SELECT id FROM dossiers_inscription WHERE email = " . $db->quote($MAIL) . ")");
    $db->exec("DELETE FROM notifications_orientation WHERE message LIKE '%TEST Lot9%'");
    $db->exec("DELETE FROM dossiers_inscription WHERE email = " . $db->quote($MAIL));
    // L'établissement emporte filière, grille et pièces (ON DELETE CASCADE).
    $db->exec("DELETE FROM etablissements WHERE id = {$etabId}");
    echo "\n── Nettoyage ──\n  ✓ nettoyé\n";
});

$etab = TarifCycle::etablissement($etabId);

/**
 * Dossier d'INSCRIPTION « soumis » de test.
 *
 * La référence est ce qui le distingue d'une préinscription
 * (Dossier::estInscription) : sans elle, la validation à 100 % est refusée,
 * en test comme en production.
 */
$creer = static function (string $niveau, ?string $bourse) use ($db, $etabId, $userId, $annee, $MAIL): int {
    static $seq = 0;
    $db->prepare(
        'INSERT INTO dossiers_inscription
           (etablissement_id, eleve_id, nom_complet, email, filiere_visee, niveau_entree,
            statut, progression, annee_academique, statut_bourse, reference, soumis_le, date_creation)
         VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, NOW(), NOW())'
    )->execute([
        $etabId, $userId, 'TEST Lot9', $MAIL, 'Droit ZZ9', $niveau, 'soumis', $annee, $bourse,
        sprintf('CA-%s-TL9-%06d', date('Y'), ++$seq),
    ]);

    return (int)$db->lastInsertId();
};

$reglement = [
    'montant_encaisse_initial' => '50000',
    'mode_paiement'            => 'especes',
    'date_paiement'            => date('Y-m-d'),
];

$vue = static fn(string $niveau, string $bourse): array => [
    'etablissement_id' => $etabId, 'filiere_visee' => 'Droit ZZ9',
    'niveau_entree' => $niveau, 'statut_bourse' => $bourse,
];


// ══ §A — Niveau : liste fermée ═════════════════════════════════════════════
echo "\n── Niveau d'entrée : liste fermée, lecture du legacy (§A) ──\n";

t('cinq niveaux, dans l’ordre du cursus',
    Niveau::codes() === ['L1', 'L2', 'L3', 'M1', 'M2']);
t('groupés par cycle pour le <optgroup>',
    array_keys(Niveau::optionsParCycle()) === ['Licence', 'Master']);
t('code → libellé stocké', Niveau::libellePourCode('L2') === 'Licence 2');
t('code inconnu refusé', Niveau::libellePourCode('L9') === null);
t('code minuscule toléré', Niveau::libellePourCode('m1') === 'Master 1');

t('« Licence 2 » relu',      Niveau::code('Licence 2') === 'L2');
t('« licence 3 » relu',      Niveau::code('licence 3') === 'L3');
t('« L1 » relu',             Niveau::code('L1') === 'L1');
t('« M2 » relu',             Niveau::code('M2') === 'M2');
t('« Master » seul → M1',    Niveau::code('Master') === 'M1',
    'un candidat n’entre pas en M2 sans le préciser');
t('« 2ème année de licence »', Niveau::code('2ème année de licence') === 'L2',
    (string)Niveau::code('2ème année de licence'));
t('« Bac+3 » → L3',          Niveau::code('Bac+3') === 'L3', (string)Niveau::code('Bac+3'));
t('« Bac+5 » → M2',          Niveau::code('Bac+5') === 'M2', (string)Niveau::code('Bac+5'));
t('« Mastère » → M1',        Niveau::code('Mastère spécialisé') === 'M1');
t('« BTSM1 » n’est pas M1',  Niveau::code('BTSM1') !== 'M1', (string)Niveau::code('BTSM1'));
t('illisible → null',        Niveau::code('Année préparatoire') === null,
    (string)Niveau::code('Année préparatoire'));
t('vide → null',             Niveau::code('') === null);

t('cycle de L3 = licence', Niveau::cycle('L3') === TarifCycle::LICENCE);
t('cycle de M1 = master',  Niveau::cycle('M1') === TarifCycle::MASTER);


// ══ §B — Grille tarifaire ══════════════════════════════════════════════════
echo "\n── Grille : sans elle, RIEN ne change (§B) ──\n";

t('L2 non boursier → fiche établissement',
    TarifCycle::scolariteApplicable($vue('Licence 2', 'non_boursier'), $etab) === 500000.0);
t('L2 boursier → tarif boursier de la fiche',
    TarifCycle::scolariteApplicable($vue('Licence 2', 'boursier'), $etab) === 400000.0);
t('M1 → tarif master de la fiche',
    TarifCycle::scolariteApplicable($vue('Master 1', 'non_boursier'), $etab) === 900000.0);

echo "\n── Grille : établissement à TARIF UNIQUE par cycle (§B) ──\n";
(new TarifFormation())->remplacerGrille($filiereId, $etabId, [
    'licence' => ['' => ['non_boursier' => '1 200 000', 'boursier' => '650000']],
    'master'  => ['' => ['non_boursier' => '1600000',   'boursier' => '900000']],
]);
t('L1, L2 et L3 au même prix',
    TarifCycle::scolariteApplicable($vue('Licence 1', 'non_boursier'), $etab) === 1200000.0
 && TarifCycle::scolariteApplicable($vue('Licence 2', 'non_boursier'), $etab) === 1200000.0
 && TarifCycle::scolariteApplicable($vue('Licence 3', 'non_boursier'), $etab) === 1200000.0);
t('boursiers de licence au même prix',
    TarifCycle::scolariteApplicable($vue('Licence 1', 'boursier'), $etab) === 650000.0
 && TarifCycle::scolariteApplicable($vue('Licence 3', 'boursier'), $etab) === 650000.0);
t('montant saisi « 1 200 000 » avec espaces bien lu',
    TarifCycle::scolariteApplicable($vue('L1', 'non_boursier'), $etab) === 1200000.0);
t('la grille l’emporte sur la fiche établissement',
    TarifCycle::detail($vue('Licence 2', 'non_boursier'), $etab)['source'] === 'grille_cycle');

echo "\n── Grille : établissement à tarif PAR ANNÉE (§B) ──\n";
(new TarifFormation())->remplacerGrille($filiereId, $etabId, [
    'licence' => [
        ''   => ['non_boursier' => '',        'boursier' => '650000'],
        'L1' => ['non_boursier' => '1000000', 'boursier' => ''],
        'L2' => ['non_boursier' => '1100000', 'boursier' => ''],
        'L3' => ['non_boursier' => '1250000', 'boursier' => ''],
    ],
    'master' => [
        ''   => ['non_boursier' => '1600000', 'boursier' => '900000'],
        'M1' => ['non_boursier' => '1500000', 'boursier' => ''],
    ],
]);
t('L1 → 1 000 000', TarifCycle::scolariteApplicable($vue('Licence 1', 'non_boursier'), $etab) === 1000000.0);
t('L2 → 1 100 000', TarifCycle::scolariteApplicable($vue('Licence 2', 'non_boursier'), $etab) === 1100000.0);
t('L3 → 1 250 000', TarifCycle::scolariteApplicable($vue('Licence 3', 'non_boursier'), $etab) === 1250000.0);
t('boursiers : une seule ligne de cycle suffit',
    TarifCycle::scolariteApplicable($vue('Licence 1', 'boursier'), $etab) === 650000.0
 && TarifCycle::scolariteApplicable($vue('Licence 3', 'boursier'), $etab) === 650000.0);
t('M1 : le niveau l’emporte sur le cycle',
    TarifCycle::scolariteApplicable($vue('Master 1', 'non_boursier'), $etab) === 1500000.0
 && TarifCycle::detail($vue('Master 1', 'non_boursier'), $etab)['source'] === 'grille_niveau');
t('M2 : pas de ligne propre → tarif du cycle',
    TarifCycle::scolariteApplicable($vue('Master 2', 'non_boursier'), $etab) === 1600000.0);

echo "\n── Grille : effacement et cloisonnement (§B) ──\n";
(new TarifFormation())->remplacerGrille($filiereId, $etabId, [
    'licence' => [
        ''   => ['non_boursier' => '1200000', 'boursier' => '650000'],
        'L2' => ['non_boursier' => '', 'boursier' => ''],
    ],
    'master' => [],
]);
t('case vidée → la ligne disparaît de la base',
    (int)$db->query("SELECT COUNT(*) FROM tarifs_formation WHERE filiere_id={$filiereId} AND niveau='L2'")->fetchColumn() === 0);
t('L2 retombe sur le tarif du cycle',
    TarifCycle::scolariteApplicable($vue('Licence 2', 'non_boursier'), $etab) === 1200000.0);
t('master vidé → repli sur la fiche établissement',
    TarifCycle::scolariteApplicable($vue('Master 1', 'non_boursier'), $etab) === 900000.0);
t('écriture refusée pour un autre établissement',
    (new TarifFormation())->remplacerGrille($filiereId, $etabId + 99999,
        ['licence' => ['' => ['non_boursier' => '1']]]) === false);
t('le refus n’a rien écrit',
    TarifCycle::scolariteApplicable($vue('Licence 2', 'non_boursier'), $etab) === 1200000.0);


// ══ §C — Frais d'inscription ═══════════════════════════════════════════════
echo "\n── Frais d'inscription par cycle et par bourse (§C) ──\n";

t('L2 non boursier → 60 000',
    TarifCycle::detail($vue('Licence 2', 'non_boursier'), $etab)['frais_inscription'] === 60000.0);
t('L2 boursier → 30 000',
    TarifCycle::detail($vue('Licence 2', 'boursier'), $etab)['frais_inscription'] === 30000.0);
t('M1 non boursier → 80 000',
    TarifCycle::detail($vue('Master 1', 'non_boursier'), $etab)['frais_inscription'] === 80000.0);
t('M1 boursier → 40 000',
    TarifCycle::detail($vue('Master 1', 'boursier'), $etab)['frais_inscription'] === 40000.0);

// Colonnes remises à 0 : on doit retrouver EXACTEMENT le comportement d'avant
// la migration, c'est-à-dire prix_inscription + frais_dossier.
$db->exec("UPDATE etablissements SET frais_inscription_licence = 0, frais_inscription_licence_boursier = 0
           WHERE id = {$etabId}");
$etabSansFrais = TarifCycle::etablissement($etabId);
t('colonnes à 0 → repli sur le tarif public (30 000)',
    TarifCycle::detail($vue('Licence 2', 'non_boursier'), $etabSansFrais)['frais_inscription'] === 30000.0);
t('boursier aussi',
    TarifCycle::detail($vue('Licence 2', 'boursier'), $etabSansFrais)['frais_inscription'] === 30000.0);
t('signature historique préservée',
    TarifCycle::fraisInscription($etabSansFrais) === 30000.0,
    'fraisInscription($etab) seul doit continuer de répondre');

$db->exec("UPDATE etablissements SET frais_inscription_licence = 60000, frais_inscription_licence_boursier = 30000
           WHERE id = {$etabId}");
$etab = TarifCycle::etablissement($etabId);

// Un tarif boursier absent retombe sur le non boursier : c'est ce qui permet à
// un établissement à tarif unique de ne rien saisir de plus.
$db->exec("UPDATE etablissements SET frais_inscription_master_boursier = 0 WHERE id = {$etabId}");
$etabPartiel = TarifCycle::etablissement($etabId);
t('frais boursier absent → tarif non boursier du cycle',
    TarifCycle::detail($vue('Master 1', 'boursier'), $etabPartiel)['frais_inscription'] === 80000.0);
$db->exec("UPDATE etablissements SET frais_inscription_master_boursier = 40000 WHERE id = {$etabId}");
$etab = TarifCycle::etablissement($etabId);


// ══ §D — Base de commission, de bout en bout ═══════════════════════════════
echo "\n── Base de commission selon la situation de bourse (§D) ──\n";

t('boursier → frais d’inscription',
    TarifCycle::baseCommission('boursier') === TarifCycle::BASE_FRAIS_INSCRIPTION);
t('situation retirée ou inconnue → scolarité',
    TarifCycle::baseCommission('dossier_en_cours') === TarifCycle::BASE_SCOLARITE);
t('non boursier → scolarité',
    TarifCycle::baseCommission('non_boursier') === TarifCycle::BASE_SCOLARITE);
t('situation non renseignée → scolarité',
    TarifCycle::baseCommission(null) === TarifCycle::BASE_SCOLARITE,
    'les dossiers antérieurs ont bien été facturés sur la scolarité');

/** Valide un dossier à 100 % et rend sa commission. */
$valider = static function (int $id) use ($etabId, $reglement): ?array {
    DecisionEtablissement::valider50($id, $etabId, null);
    $r = DecisionEtablissement::valider100($id, $etabId, null, $reglement);
    if (!$r['ok']) { return ['__erreur' => $r['message']]; }
    return CommissionDossier::pourDossier($id);
};

// Non boursier : commission sur la scolarité (1 200 000 × 10 %).
$dN = $creer('Licence 2', 'non_boursier');
$cN = $valider($dN);
t('non boursier : base = scolarité 1 200 000',
    $cN !== null && (float)($cN['montant_scolarite'] ?? 0) === 1200000.0,
    json_encode($cN['__erreur'] ?? ($cN['montant_scolarite'] ?? null)));
t('non boursier : commission = 120 000',
    $cN !== null && (float)($cN['montant_commission'] ?? 0) === 120000.0);
t('non boursier : nature tracée',
    $cN !== null && (string)($cN['base_commission'] ?? '') === TarifCycle::BASE_SCOLARITE);

// Situation de bourse ABSENTE (dossier hérité, préinscription convertie) :
// même base que le non boursier. C'est le repli qui protège la facturation
// d'un dossier dont la déclaration n'a jamais été faite.
$dC = $creer('Licence 2', null);
$cC = $valider($dC);
t('situation non renseignée : base = scolarité',
    $cC !== null && (float)($cC['montant_scolarite'] ?? 0) === 1200000.0,
    json_encode($cC['__erreur'] ?? null));
t('situation non renseignée : commission = 120 000',
    $cC !== null && (float)($cC['montant_commission'] ?? 0) === 120000.0);

// Boursier : commission sur les FRAIS D'INSCRIPTION (60 000 boursier = 30 000).
$dB = $creer('Licence 2', 'boursier');
$cB = $valider($dB);
t('boursier : base = frais d’inscription 30 000',
    $cB !== null && (float)($cB['montant_scolarite'] ?? 0) === 30000.0,
    json_encode($cB['__erreur'] ?? ($cB['montant_scolarite'] ?? null)));
t('boursier : commission = 3 000',
    $cB !== null && (float)($cB['montant_commission'] ?? 0) === 3000.0);
t('boursier : nature tracée',
    $cB !== null && (string)($cB['base_commission'] ?? '') === TarifCycle::BASE_FRAIS_INSCRIPTION);
t('boursier : situation gelée sur la commission',
    $cB !== null && (string)($cB['statut_bourse_applique'] ?? '') === 'boursier');

$aB = (new Dossier())->findById($dB);
t('boursier : scolarité du dossier = tarif boursier (650 000)',
    (float)$aB['montant_scolarite_annuelle'] === 650000.0,
    (string)$aB['montant_scolarite_annuelle']);
t('boursier : frais d’inscription du dossier = 30 000',
    (float)$aB['montant_frais_inscription'] === 30000.0,
    (string)$aB['montant_frais_inscription']);
t('la commission ne porte PAS sur la scolarité du boursier',
    $cB !== null && (float)($cB['montant_scolarite'] ?? 0) !== 650000.0);

t('les trois tranches suivent la nouvelle base',
    abs(array_sum(array_map(static fn($x) => (float)$x['montant'], CommissionDossier::tranches((int)$cB['id']))) - 3000.0) < 0.001);


// ══ §E — Changement de situation de bourse ═════════════════════════════════
echo "\n── Changement de bourse après validation (§E) ──\n";

// L'ANBG répond : « dossier en cours » devient « boursier ». Rien n'est
// encaissé → la commission doit basculer sur les frais d'inscription.
SituationBourse::enregistrer($dC, ['statut_bourse' => 'boursier'], SituationBourse::PAR_ADMIN, null);
$cC2 = CommissionDossier::pourDossier($dC);
t('bourse accordée → base recalculée sur les frais d’inscription',
    $cC2 !== null && (string)$cC2['base_commission'] === TarifCycle::BASE_FRAIS_INSCRIPTION,
    (string)($cC2['base_commission'] ?? 'null'));
// 6 000 et non 3 000, et c'est VOULU : la base est le montant FIGÉ sur le
// dossier au guichet — 60 000 F de frais d'inscription réellement encaissés
// à l'époque où le candidat était encore « dossier en cours ». La révision
// change la NATURE de la base (scolarité → frais d'inscription), jamais les
// montants constatés.
//
// Recalculer depuis la configuration du jour (30 000, tarif boursier) aurait
// commissionné un montant que l'établissement n'a jamais perçu, et aurait fait
// dépendre une commission d'une grille tarifaire modifiable après coup.
t('montant recalculé sur les frais RÉELLEMENT encaissés : 6 000',
    $cC2 !== null && (float)$cC2['montant_commission'] === 6000.0,
    (string)($cC2['montant_commission'] ?? 'null'));
t('base = frais figés au guichet (60 000), pas le tarif boursier du jour',
    $cC2 !== null && (float)$cC2['montant_scolarite'] === 60000.0,
    (string)($cC2['montant_scolarite'] ?? 'null'));
t('échéancier régénéré sur le nouveau total',
    abs(array_sum(array_map(static fn($x) => (float)$x['montant'], CommissionDossier::tranches((int)$cC2['id']))) - 6000.0) < 0.001);
t('aucun signalement : le recalcul a eu lieu',
    $cC2 !== null && (int)$cC2['revision_requise'] === 0);
t('situation gelée mise à jour',
    $cC2 !== null && (string)$cC2['statut_bourse_applique'] === 'boursier');

// Retour en arrière : la bourse est retirée, rien n'est encore encaissé.
SituationBourse::enregistrer($dC, ['statut_bourse' => 'non_boursier'], SituationBourse::PAR_ADMIN, null);
$cC3 = CommissionDossier::pourDossier($dC);
t('bourse retirée → retour sur la scolarité',
    $cC3 !== null && (string)$cC3['base_commission'] === TarifCycle::BASE_SCOLARITE
 && (float)$cC3['montant_commission'] === 120000.0,
    (string)($cC3['montant_commission'] ?? 'null'));

// Redéclarer la MÊME situation ne doit rien déclencher.
$avant = CommissionDossier::pourDossier($dC)['updated_at'] ?? null;
t('redéclaration identique sans effet',
    SituationBourse::enregistrer($dC, ['statut_bourse' => 'non_boursier'], SituationBourse::PAR_ADMIN, null) === false);

echo "\n── Une tranche encaissée ferme la révision automatique (§E) ──\n";

// On encaisse la première tranche du dossier NON boursier, puis on lui accorde
// une bourse. Le recalcul doit être REFUSÉ au profit d'un signalement.
$trN = CommissionDossier::tranches((int)$cN['id']);
$rP  = CommissionDossier::payerTranche((int)$trN[0]['id'], true, null, 'VIR-L9');
t('tranche 1 encaissée', $rP['ok'] === true, (string)$rP['message']);

$montantAvant = (float)CommissionDossier::pourDossier($dN)['montant_commission'];
SituationBourse::enregistrer($dN, ['statut_bourse' => 'boursier'], SituationBourse::PAR_ADMIN, null);
$cN2 = CommissionDossier::pourDossier($dN);

t('montant INCHANGÉ malgré le changement de bourse',
    $cN2 !== null && (float)$cN2['montant_commission'] === $montantAvant,
    (string)($cN2['montant_commission'] ?? 'null'));
t('base inchangée',
    $cN2 !== null && (string)$cN2['base_commission'] === TarifCycle::BASE_SCOLARITE);
t('commission signalée au super administrateur',
    $cN2 !== null && (int)$cN2['revision_requise'] === 1);
t('le motif nomme l’encaissement',
    $cN2 !== null && str_contains((string)$cN2['revision_motif'], 'encaiss'),
    (string)($cN2['revision_motif'] ?? ''));
t('encaissement préservé',
    abs(CommissionDossier::montantEncaisse((int)$cN['id']) - (float)$trN[0]['montant']) < 0.001);
t('le statut du DOSSIER n’a pas bougé',
    (string)(new Dossier())->findById($dN)['statut'] === 'valide_100');
t('la déclaration de bourse est bien enregistrée malgré le refus',
    (string)(new Dossier())->findById($dN)['statut_bourse'] === 'boursier',
    'refuser de recalculer ne doit pas refuser la déclaration');

t('le super administrateur peut lever le signalement',
    CommissionDossier::cloreRevision((int)$cN['id'], null) === true);
t('signalement levé',
    (int)CommissionDossier::pourDossier($dN)['revision_requise'] === 0);
t('lever deux fois ne fait rien',
    CommissionDossier::cloreRevision((int)$cN['id'], null) === false);
t('les montants n’ont pas bougé après la levée',
    (float)CommissionDossier::pourDossier($dN)['montant_commission'] === $montantAvant);

echo "\n── Dossier sans commission : la révision est un non-événement (§E) ──\n";
$dSans = $creer('Licence 1', 'non_boursier');
$r = CommissionDossier::reevaluerBase($dSans, null, 'admin');
t('dossier non validé → aucune action', $r['ok'] === true && $r['action'] === 'aucune', $r['action']);


// ══ §F — Facture proforma ══════════════════════════════════════════════════
echo "\n── Facture proforma : le tarif BOURSIER (§F) ──\n";

// La proforma est la pièce déposée à l'ANBG : elle doit montrer ce que
// l'agence financerait, donc le tarif boursier — même pour un dossier dont
// la situation de bourse n'est pas (encore) déclarée.
$dossierProforma = [
    'id' => 0, 'etablissement_id' => $etabId, 'nom_complet' => 'TEST Lot9',
    'filiere_visee' => 'Droit ZZ9', 'niveau_entree' => 'Licence 2',
];

$html = (new FactureProformaService())->render($dossierProforma, $etab, 'PRO-LOT9-001');

t('scolarité boursière affichée (650 000)', str_contains($html, '650 000'), 'montant boursier absent');
t('scolarité non boursière ABSENTE', !str_contains($html, '1 200 000'),
    'la proforma ne doit pas montrer le tarif non boursier');
t('frais d’inscription boursiers affichés (30 000)', str_contains($html, '30 000'));
t('total = 680 000', str_contains($html, '680 000'), 'total boursier attendu');
t('le niveau figure sur la facture', str_contains($html, 'Licence 2'));
// La formation n'est pas assujettie à la TVA : le vocabulaire fiscal a été
// retiré de la proforma (« HTVA », « TVA 18 % », « Montant TTC »), de même que
// le paragraphe « Arrêté la présente facture… » qui répétait le total déjà
// porté par le tableau. Un seul total subsiste : « Net à payer ».
t('aucune mention de TVA', !str_contains($html, 'TVA'), 'le vocabulaire fiscal est revenu');
t('aucune mention de TTC', !str_contains($html, 'TTC'), 'le vocabulaire fiscal est revenu');
t('pas de paragraphe « Arrêté la présente »', !str_contains($html, 'Arrêté la présente'));
t('un seul total, « Net à payer »',
    substr_count($html, 'Net à payer') === 1, 'le total doit apparaître une fois et une seule');

t('des tarifs sont bien détectés',
    FactureProformaService::etabAConfigureTarifs($etab, $dossierProforma) === true);

// ── Composition du dossier d'inscription, restreinte au cycle ───────────────
// La liste vient de la fiche établissement. Un candidat de licence ne doit
// jamais lire les pièces du master — c'est la seule chose qui rende la liste
// utilisable au guichet.
$etabCompo = $etab;
$etabCompo['composition_dossier'] = json_encode([
    'commun'  => ['Acte de naissance légalisé'],
    'licence' => ['Relevé de notes du baccalauréat'],
    'master'  => ['Diplôme de licence'],
], JSON_UNESCAPED_UNICODE);

$htmlCompo = (new FactureProformaService())->render($dossierProforma, $etabCompo, 'PRO-LOT9-002');
t('proforma : pièces communes imprimées', str_contains($htmlCompo, 'Acte de naissance légalisé'));
t('proforma : pièces du cycle demandé imprimées', str_contains($htmlCompo, 'Relevé de notes du baccalauréat'));
t('proforma : pièces de l’AUTRE cycle absentes', !str_contains($htmlCompo, 'Diplôme de licence'),
    'un candidat de licence ne doit pas réunir les pièces du master');

$dossierMaster = $dossierProforma;
$dossierMaster['niveau_entree'] = 'Master 1';
$htmlMaster = (new FactureProformaService())->render($dossierMaster, $etabCompo, 'PRO-LOT9-003');
t('proforma master : pièces du master imprimées', str_contains($htmlMaster, 'Diplôme de licence'));
t('proforma master : pièces de la licence absentes',
    !str_contains($htmlMaster, 'Relevé de notes du baccalauréat'));
t('proforma master : le commun reste imprimé', str_contains($htmlMaster, 'Acte de naissance légalisé'));

// Fiche sans composition : aucun bloc, aucun titre orphelin.
// On teste le TITRE du bloc, pas sa classe : les règles CSS `.fp-pieces` sont
// dans le <style> du fragment et sont donc toujours émises, que le bloc soit
// rendu ou non.
// Titre + « — cycle » : la chaîne telle qu'elle est IMPRIMÉE, jamais un
// fragment qui pourrait aussi vivre dans un commentaire du gabarit.
$TITRE_PIECES = "Composition du dossier d'inscription — cycle";
t('proforma sans composition : aucun bloc de pièces',
    !str_contains($html, $TITRE_PIECES),
    'le bloc ne doit apparaître que si l’établissement a saisi quelque chose');
t('proforma avec composition : le bloc est titré « Composition du dossier d’inscription »',
    str_contains($htmlCompo, $TITRE_PIECES), 'titre du bloc de pièces introuvable');


// ══ §G — Où vit la composition du dossier ══════════════════════════════════
// Décision produit : la liste des pièces appartient à la FACTURE PROFORMA,
// pas à l'attestation. C'est la proforma que le candidat dépose au service des
// admissions et à l'ANBG ; dupliquer la liste sur la page 1 faisait deux
// sources de vérité pour un même document.
echo "\n── Composition du dossier : proforma uniquement (§G) ──\n";

$svcRec = new RecepisseService();

$attestation = $svcRec->render($dossierProforma, $etabCompo, 'PRE-LOT9-0001');
t('attestation : aucun bloc « Composition du dossier d’inscription »',
    !str_contains($attestation, $TITRE_PIECES),
    'la liste des pièces ne doit plus figurer sur la page 1');
t('attestation : aucune pièce imprimée',
    !str_contains($attestation, 'Acte de naissance légalisé')
    && !str_contains($attestation, 'Relevé de notes du baccalauréat'),
    'aucun libellé de pièce ne doit apparaître sur l’attestation');

// L'attestation garde son objet : constater l'enregistrement, rien de plus.
// Le document doit tenir en DEUX pages — une par pièce officielle —, et chaque
// ligne ajoutée à la page 1 se paie en risque de débordement.
//
// On vérifie la PHRASE DE CONSTAT, pas l'ancien paragraphe sur les pièces à
// fournir : celui-ci a été retiré, la règle voulant qu'aucune mention des
// pièces ne figure sur l'attestation, pas même une allusion.
t('attestation : phrase de constat conservée',
    str_contains($attestation, 'valeur d')
    && str_contains($attestation, 'enregistrement du dossier'),
    'le constat d’enregistrement a disparu de la page 1');
t('attestation : aucun paragraphe de renvoi vers la proforma',
    !str_contains($attestation, 'facture proforma jointe'),
    'la page 1 ne doit pas gagner de texte');

// Document complet : exactement deux pages, la liste des pièces sur la seconde.
$complet = $svcRec->renderPdfComplet($dossierProforma, $etabCompo, 'PRE-LOT9-0002', 'FAC-LOT9-0002');
t('document complet : un PDF est produit', $complet !== null, 'renderPdfComplet a rendu null');
t('document complet : exactement 2 pages',
    $complet !== null && nbPagesPdf($complet) === 2,
    'pages comptées : ' . ($complet !== null ? (string)nbPagesPdf($complet) : 'aucun PDF'));

// Un seul saut de page dans le document assemblé : l'attestation tient sur son
// recto, la proforma sur le sien.
$fragment = (new FactureProformaService())->render($dossierProforma, $etabCompo, 'FAC-LOT9-0005');
t('proforma : un seul saut de page déclaré',
    substr_count($fragment, 'page-break-before: always') === 1);

// Cas défavorable : douze pièces, le maximum qu'un établissement saisit en
// pratique. C'est là que la proforma déborderait sur une troisième feuille.
$etabDouze = $etab;
$etabDouze['composition_dossier'] = json_encode([
    'commun'  => [
        'Acte de naissance légalisé de moins de trois mois',
        'Copie légalisée de la carte nationale d’identité',
        'Quatre photos d’identité récentes sur fond blanc',
        'Certificat de résidence délivré par la mairie',
        'Certificat médical d’aptitude de moins de trois mois',
        'Fiche de renseignements dûment remplie et signée',
        'Copie du relevé de notes du baccalauréat',
        'Copie légalisée du diplôme du baccalauréat',
    ],
    'licence' => [
        'Relevés de notes des années universitaires antérieures',
        'Attestation de réussite ou certificat de scolarité',
        'Autorisation parentale pour les candidats mineurs',
        'Justificatif de prise en charge financière',
    ],
], JSON_UNESCAPED_UNICODE);

$pdfDouze = $svcRec->renderPdfComplet($dossierProforma, $etabDouze, 'PRE-LOT9-0006', 'FAC-LOT9-0006');
t('douze pièces : le document tient toujours en 2 pages',
    $pdfDouze !== null && nbPagesPdf($pdfDouze) === 2,
    'pages comptées : ' . ($pdfDouze !== null ? (string)nbPagesPdf($pdfDouze) : 'aucun PDF'));


echo "\n══ $ok réussis · $ko échoués ══\n";
exit($ko === 0 ? 0 : 1);
