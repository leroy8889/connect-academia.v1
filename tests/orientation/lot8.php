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
 * LOT 8 — Tarifs par cycle, tranches de commission, abandon d'année,
 *          complément de dossier par le candidat.
 *
 *   §A  TarifCycle : détection du cycle, tarif NON boursier, replis
 *   §B  Validation à 100 % : les montants viennent de la configuration
 *   §C  Tranches de recouvrement : création, somme exacte, ordre d'encaissement
 *   §D  Abandon d'année : clôture des tranches, verrous, levée
 *   §E  ComplementDossier : pièce refusée, blocages, renvoi à l'analyse
 *
 * Comme les autres lots : crée ses données, les nettoie, rejouable à l'infini.
 * Tourne sur la base de DÉVELOPPEMENT.
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
use Models\Orientation\DossierStatut;
use Services\Orientation\CommissionDossier;
use Services\Orientation\ComplementDossier;
use Services\Orientation\DecisionEtablissement;
use Services\Orientation\TarifCycle;

$ok = 0; $ko = 0;
function t(string $n, bool $c, string $d = ''): void {
    global $ok, $ko;
    if ($c) { $ok++; echo "  ✓ $n\n"; } else { $ko++; echo "  ✗ $n $d\n"; }
}

$db     = Database::getInstance()->getConnection();
$etabId = (int)$db->query('SELECT id FROM etablissements ORDER BY id LIMIT 1')->fetchColumn();

// La grille par formation et les montants portés par la filière priment sur
// la fiche que ce test écrit : on les met en sommeil, puis on les restaure.
require_once __DIR__ . '/_tarifs_isolation.php';
isolerTarifsEtablissement($db, $etabId);
$userId = (int)$db->query('SELECT id FROM users WHERE is_deleted = 0 ORDER BY id LIMIT 1')->fetchColumn();
$modele = new Dossier();
$annee  = AnneeUniversitaire::code();
$MAIL   = 'test.lot8@example.test';

// ── Tarifs de l'établissement de test, restaurés quoi qu'il arrive ──────────
$tarifsOrigine = $db->query(
    "SELECT frais_scolarite, frais_scolarite_boursier, frais_scolarite_master,
            frais_scolarite_master_boursier, prix_inscription, frais_dossier,
            taux_commission_scolarite
     FROM etablissements WHERE id = {$etabId}"
)->fetch(PDO::FETCH_ASSOC) ?: [];

register_shutdown_function(static function () use ($db, $etabId, $tarifsOrigine, $MAIL): void {
    // Les dossiers de test partent d'abord : leurs commissions et tranches
    // suivent par cascade (fk_commission_dossier ON DELETE CASCADE).
    $db->exec("DELETE FROM journal_activite WHERE objet_type = 'dossier' AND objet_id IN
               (SELECT id FROM dossiers_inscription WHERE email = " . $db->quote($MAIL) . ")");
    // Les décisions notifient l'établissement : ces messages sont du bruit de
    // test, ils n'ont rien à faire dans la boîte d'un gestionnaire réel.
    $db->exec("DELETE FROM notifications_orientation WHERE message LIKE '%TEST Lot8%'");
    $db->exec("DELETE FROM dossiers_inscription WHERE email = " . $db->quote($MAIL));

    if ($tarifsOrigine === []) { return; }
    $db->prepare(
        'UPDATE etablissements
         SET frais_scolarite = ?, frais_scolarite_boursier = ?, frais_scolarite_master = ?,
             frais_scolarite_master_boursier = ?, prix_inscription = ?, frais_dossier = ?,
             taux_commission_scolarite = ?
         WHERE id = ?'
    )->execute([
        (int)$tarifsOrigine['frais_scolarite'],
        (int)$tarifsOrigine['frais_scolarite_boursier'],
        (int)$tarifsOrigine['frais_scolarite_master'],
        (int)$tarifsOrigine['frais_scolarite_master_boursier'],
        (int)$tarifsOrigine['prix_inscription'],
        (int)$tarifsOrigine['frais_dossier'],
        $tarifsOrigine['taux_commission_scolarite'],
        $etabId,
    ]);
});

$db->exec("DELETE FROM dossiers_inscription WHERE email = " . $db->quote($MAIL));
$db->prepare(
    'UPDATE etablissements
     SET frais_scolarite = 800000, frais_scolarite_boursier = 300000,
         frais_scolarite_master = 1200000, frais_scolarite_master_boursier = 500000,
         prix_inscription = 25000, frais_dossier = 5000, taux_commission_scolarite = 10.00
     WHERE id = ?'
)->execute([$etabId]);

/** Crée un dossier « soumis » de test. */
$creer = static function (string $niveau = 'Licence 1', string $bourse = 'non_boursier', ?string $ref = null)
                        use ($db, $etabId, $userId, $annee, $MAIL): int {
    $db->prepare(
        'INSERT INTO dossiers_inscription
           (etablissement_id, eleve_id, nom_complet, email, filiere_visee, niveau_entree,
            statut, progression, annee_academique, statut_bourse, soumis_le, reference, date_creation)
         VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, NOW(), ?, NOW())'
    )->execute([
        $etabId, $userId, 'TEST Lot8', $MAIL, $niveau, $niveau,
        'soumis', $annee, $bourse, $ref,
    ]);

    return (int)$db->lastInsertId();
};

$reglement = [
    'montant_encaisse_initial' => '100000',
    'mode_paiement'            => 'especes',
    'date_paiement'            => date('Y-m-d'),
];

// ══ §A — TarifCycle ════════════════════════════════════════════════════════
echo "\n── Cycle et tarif applicable (spec §10.1) ──\n";

t('Licence 1 → licence',  TarifCycle::detecter(['niveau_entree' => 'Licence 1']) === TarifCycle::LICENCE);
t('Licence 3 → licence',  TarifCycle::detecter(['niveau_entree' => 'Licence 3']) === TarifCycle::LICENCE);
t('Master 1 → master',    TarifCycle::detecter(['niveau_entree' => 'Master 1']) === TarifCycle::MASTER);
t('M2 → master',          TarifCycle::detecter(['niveau_entree' => 'M2']) === TarifCycle::MASTER);
t('« Mastère » → master', TarifCycle::detecter(['niveau_entree' => 'Mastère spécialisé']) === TarifCycle::MASTER);
t('filière master_m1_m2 → master',
    TarifCycle::detecter(['niveau_entree' => '', 'filiere_visee' => 'master_m1_m2']) === TarifCycle::MASTER);
t('niveau inconnu → licence (tarif le plus bas)',
    TarifCycle::detecter(['niveau_entree' => 'Année préparatoire']) === TarifCycle::LICENCE,
    'une erreur de détection ne doit jamais surfacturer');
t('« BTSM1 » n’est pas un master',
    TarifCycle::detecter(['niveau_entree' => 'BTSM1']) === TarifCycle::LICENCE);

$etab = TarifCycle::etablissement($etabId);
$dL   = ['etablissement_id' => $etabId, 'niveau_entree' => 'Licence 2', 'filiere_visee' => 'Droit'];
$dM   = ['etablissement_id' => $etabId, 'niveau_entree' => 'Master 1',  'filiere_visee' => 'Droit'];

t('tarif licence appliqué', TarifCycle::scolariteApplicable($dL, $etab) === 800000.0);
t('tarif master appliqué',  TarifCycle::scolariteApplicable($dM, $etab) === 1200000.0);
// RÈGLE RÉVISÉE (migration_tarifs_formation.sql §3) : la situation de bourse
// détermine désormais le tarif retenu ET la base de la commission. Un boursier
// est tarifé au tarif boursier du cycle.
t('tarif boursier appliqué à un boursier',
    TarifCycle::scolariteApplicable($dL + ['statut_bourse' => 'boursier'], $etab) === 300000.0,
    (string)TarifCycle::scolariteApplicable($dL + ['statut_bourse' => 'boursier'], $etab));
// Toute situation autre que « boursier » — y compris une valeur retirée ou
// forgée — suit le tarif NON boursier. C'est ce repli qui garantit qu'un
// dossier hérité ne se retrouve jamais tarifé comme un boursier.
t('tarif non boursier appliqué à une situation retirée',
    TarifCycle::scolariteApplicable($dL + ['statut_bourse' => 'dossier_en_cours'], $etab) === 800000.0);

$detail = TarifCycle::detail($dM, $etab);
t('détail : cycle master',       $detail['cycle'] === TarifCycle::MASTER);
t('détail : source fiche étab',  $detail['source'] === 'etablissement', (string)$detail['source']);
t('détail : tarif boursier exposé pour information', $detail['tarif_boursier'] === 500000.0);
t('frais d’inscription = inscription + dossier', TarifCycle::fraisInscription($etab) === 30000.0);

// ══ §B — Validation à 100 % : la configuration prime sur la saisie ══════════
echo "\n── La saisie du guichet ne fixe plus les montants (spec §8.4) ──\n";

$d1 = $creer('Licence 2', 'boursier', 'CA-' . date('Y') . '-TST-000810');
DecisionEtablissement::valider50($d1, $etabId, null);

// L'agent tente d'imposer un montant : il doit être écrasé par la fiche.
$r = DecisionEtablissement::valider100($d1, $etabId, null,
    $reglement + ['montant_scolarite_annuelle' => '1', 'montant_frais_inscription' => '999999']);
t('validation à 100 % acceptée', $r['ok'] === true, (string)$r['message']);

$a1 = $modele->findById($d1);
// Dossier BOURSIER : la scolarité retenue est le tarif boursier du cycle.
t('montant saisi ÉCRASÉ par la fiche', (float)$a1['montant_scolarite_annuelle'] === 300000.0,
    (string)$a1['montant_scolarite_annuelle']);
t('frais d’inscription issus de la fiche', (float)$a1['montant_frais_inscription'] === 30000.0,
    (string)$a1['montant_frais_inscription']);
t('versement du jour conservé', (float)$a1['montant_encaisse_initial'] === 100000.0);

$c1 = CommissionDossier::pourDossier($d1);
t('commission créée', $c1 !== null);
// Boursier confirmé → la commission porte sur les FRAIS D'INSCRIPTION
// (30 000 = prix_inscription 25 000 + frais_dossier 5 000), pas sur la scolarité.
t('base boursier = frais d’inscription',
    $c1 !== null && (float)$c1['montant_scolarite'] === 30000.0,
    $c1 === null ? 'commission absente' : (string)$c1['montant_scolarite']);
t('nature de base tracée',
    $c1 !== null && (string)$c1['base_commission'] === TarifCycle::BASE_FRAIS_INSCRIPTION,
    $c1 === null ? '' : (string)($c1['base_commission'] ?? 'null'));
t('situation de bourse gelée sur la commission',
    $c1 !== null && (string)$c1['statut_bourse_applique'] === 'boursier');
t('montant = 10 % de 30 000', $c1 !== null && (float)$c1['montant_commission'] === 3000.0,
    $c1 === null ? '' : (string)$c1['montant_commission']);

// Un dossier de master sur le même établissement doit prendre l'autre tarif.
$dm = $creer('Master 1', 'non_boursier', 'CA-' . date('Y') . '-TST-000811');
DecisionEtablissement::valider50($dm, $etabId, null);
DecisionEtablissement::valider100($dm, $etabId, null, $reglement);
$cm = CommissionDossier::pourDossier($dm);
t('cycle master → base 1 200 000', $cm !== null && (float)$cm['montant_scolarite'] === 1200000.0);

// Repli : aucun tarif configuré pour le cycle → le guichet n'est PAS bloqué,
// la saisie de l'agent reprend la main. Une fiche incomplète côté plateforme ne
// doit jamais empêcher une inscription devant un candidat qui attend.
$db->prepare('UPDATE etablissements SET frais_scolarite = 0, frais_scolarite_master = 0 WHERE id = ?')
   ->execute([$etabId]);

// Filière volontairement introuvable dans `filieres_etablissement` : le repli
// par filière ne doit pas jouer, sinon on ne teste pas le bon chemin. On ne
// SUPPRIME rien du référentiel réel pour obtenir ce résultat.
$dRepli = $creer('TEST filière sans tarif', 'non_boursier', 'CA-' . date('Y') . '-TST-000815');
t('tarif non configuré détecté',
    TarifCycle::detail($modele->findById($dRepli))['configure'] === false);

DecisionEtablissement::valider50($dRepli, $etabId, null);
$r = DecisionEtablissement::valider100($dRepli, $etabId, null,
    $reglement + ['montant_scolarite_annuelle' => '640000']);
t('repli : saisie de l’agent acceptée', $r['ok'] === true, (string)$r['message']);
t('repli : montant saisi conservé',
    (float)$modele->findById($dRepli)['montant_scolarite_annuelle'] === 640000.0);
t('repli : sans montant, la validation est refusée',
    DecisionEtablissement::valider100(
        $creer('TEST filière sans tarif', 'non_boursier', 'CA-' . date('Y') . '-TST-000816'),
        $etabId, null, $reglement
    )['ok'] === false);

$db->prepare('UPDATE etablissements SET frais_scolarite = 800000, frais_scolarite_master = 1200000 WHERE id = ?')
   ->execute([$etabId]);

// ══ §C — Tranches de recouvrement ══════════════════════════════════════════
// La répartition est LUE sur la constante, jamais réécrite ici : elle est
// passée de 40/30/30 à 60/40, et un test qui la recopie devient faux le jour du
// changement — exactement ce qui s'est produit.
$repartition = CommissionDossier::REPARTITION_TRANCHES;
$nbTranches  = count($repartition);
echo "\n── Recouvrement en " . $nbTranches . " tranches (spec §10.4) ──\n";

$tr = CommissionDossier::tranches((int)$c1['id']);
t($nbTranches . ' tranches créées', count($tr) === $nbTranches, (string)count($tr));
t('répartition ' . CommissionDossier::libelleRepartition(false),
    array_map(static fn($x) => (float)$x['pourcentage'], $tr) === $repartition,
    implode(' / ', array_map(static fn($x) => (string)(float)$x['pourcentage'], $tr)));
t('la répartition totalise 100 %', abs(array_sum($repartition) - 100.0) < 0.001);
// Montants DÉRIVÉS de la commission réellement émise, pas codés en dur : la
// base dépend maintenant de la situation de bourse, et figer « 80 000 » ici
// rendrait le test faux au premier changement de règle tarifaire.
$totalCom = (float)$c1['montant_commission'];
t('somme des tranches = commission, au centime',
    abs(array_sum(array_map(static fn($x) => (float)$x['montant'], $tr)) - $totalCom) < 0.001,
    (string)$totalCom);
t('tranche 1 = ' . $repartition[0] . ' % de la commission',
    abs((float)$tr[0]['montant'] - round($totalCom * $repartition[0] / 100, 2)) < 0.001,
    (string)$tr[0]['montant']);
// La DERNIÈRE tranche absorbe l'arrondi : elle vaut le reste, pas le
// pourcentage recalculé. C'est ce qui garantit la somme exacte ci-dessus.
$sommeAvantDerniere = 0.0;
for ($i = 0; $i < $nbTranches - 1; $i++) { $sommeAvantDerniere += (float)$tr[$i]['montant']; }
t('dernière tranche = reste exact',
    abs((float)$tr[$nbTranches - 1]['montant'] - round($totalCom - $sommeAvantDerniere, 2)) < 0.001,
    (string)$tr[$nbTranches - 1]['montant']);
t('aucune tranche encaissée au départ',
    array_sum(array_map(static fn($x) => (int)$x['est_payee'], $tr)) === 0);

// Ordre imposé : la tranche 2 ne passe pas avant la 1.
$r = CommissionDossier::payerTranche((int)$tr[1]['id'], true, null, null);
t('tranche 2 refusée avant la tranche 1', $r['ok'] === false, (string)$r['message']);

$r = CommissionDossier::payerTranche((int)$tr[0]['id'], true, null, 'VIR-001');
t('tranche 1 encaissée', $r['ok'] === true, (string)$r['message']);
t('statut → partiellement réglée',
    $r['commission_statut'] === CommissionDossier::PARTIELLEMENT_REGLEE, (string)$r['commission_statut']);
t('encaissé = tranche 1', abs(CommissionDossier::montantEncaisse((int)$c1['id']) - (float)$tr[0]['montant']) < 0.001,
    (string)CommissionDossier::montantEncaisse((int)$c1['id']));
t('référence de règlement conservée',
    (string)CommissionDossier::tranches((int)$c1['id'])[0]['reference_reglement'] === 'VIR-001');

$r = CommissionDossier::payerTranche((int)$tr[0]['id'], true, null, null);
t('double encaissement refusé', $r['ok'] === false);

CommissionDossier::payerTranche((int)$tr[1]['id'], true, null, null);
$r = CommissionDossier::payerTranche((int)$tr[0]['id'], false, null, null);
t('retour arrière refusé si la suivante est encaissée', $r['ok'] === false, (string)$r['message']);

// Solder l'échéancier, quel que soit le nombre de tranches : le statut ne passe
// à « réglée » qu'une fois la DERNIÈRE encaissée, jamais avant.
for ($i = 2; $i < $nbTranches; $i++) {
    $r = CommissionDossier::payerTranche((int)$tr[$i]['id'], true, null, null);
    t('tranche ' . ($i + 1) . ' encaissée', $r['ok'] === true, (string)$r['message']);
}
$r = ['commission_statut' => CommissionDossier::recalculerStatut((int)$c1['id'])];
t('statut → réglée', $r['commission_statut'] === CommissionDossier::REGLEE, (string)$r['commission_statut']);
t('date de règlement posée',
    !empty(CommissionDossier::pourDossier($d1)['regle_le']));

// Une commission réglée est close : plus de changement de statut.
t('commission réglée : statut verrouillé',
    CommissionDossier::changerStatut((int)$c1['id'], CommissionDossier::A_FACTURER, null) === false);

// L'établissement ne peut plus défaire une inscription dont l'argent est rentré.
$r = DecisionEtablissement::annuler($d1, $etabId, null, 'Tentative');
t('annulation refusée : commission encaissée', $r['ok'] === false, (string)$r['message']);

// ══ §D — Abandon d'année ═══════════════════════════════════════════════════
echo "\n── Abandon d’année (spec §10.5) ──\n";

$d2 = $creer('Licence 1', 'non_boursier', 'CA-' . date('Y') . '-TST-000812');
DecisionEtablissement::valider50($d2, $etabId, null);
DecisionEtablissement::valider100($d2, $etabId, null, $reglement);
$c2  = CommissionDossier::pourDossier($d2);
$tr2 = CommissionDossier::tranches((int)$c2['id']);
CommissionDossier::payerTranche((int)$tr2[0]['id'], true, null, null);

t('motif trop court refusé',
    CommissionDossier::declarerAbandon($d2, null, 'ok')['ok'] === false);

$r = CommissionDossier::declarerAbandon($d2, null, 'Départ à l’étranger, signalé par l’établissement.');
t('abandon enregistré', $r['ok'] === true, (string)$r['message']);

$a2 = $modele->findById($d2);
t('dossier daté en abandon',      !empty($a2['abandon_le']));
t('statut du dossier INCHANGÉ',   DossierStatut::normaliser((string)$a2['statut']) === DossierStatut::VALIDE_100,
    'l’inscription a bien eu lieu : l’attestation reste valable');

$c2 = CommissionDossier::pourDossier($d2);
t('commission en abandon d’année', (string)$c2['statut'] === CommissionDossier::ABANDONNEE);

$tr2 = CommissionDossier::tranches((int)$c2['id']);
t('tranche encaissée conservée',   (int)$tr2[0]['est_payee'] === 1 && (int)$tr2[0]['est_abandonnee'] === 0);
$restantes = array_slice($tr2, 1);
t('tranches restantes closes',
    $restantes !== [] && array_sum(array_map(static fn($x) => (int)$x['est_abandonnee'], $restantes)) === count($restantes),
    implode(',', array_map(static fn($x) => (string)(int)$x['est_abandonnee'], $restantes)));
t('encaissé inchangé',             CommissionDossier::montantEncaisse((int)$c2['id']) > 0);

$r = CommissionDossier::payerTranche((int)$tr2[1]['id'], true, null, null);
t('tranche close : plus encaissable', $r['ok'] === false, (string)$r['message']);

t('deuxième abandon refusé',
    CommissionDossier::declarerAbandon($d2, null, 'Nouvelle tentative de constat.')['ok'] === false);

$r = DecisionEtablissement::annuler($d2, $etabId, null, 'Tentative');
t('annulation refusée sur un abandon', $r['ok'] === false, (string)$r['message']);

$r = CommissionDossier::leverAbandon($d2, null);
t('abandon levé', $r['ok'] === true, (string)$r['message']);
$tr2 = CommissionDossier::tranches((int)$c2['id']);
t('tranches rouvertes',      (int)$tr2[1]['est_abandonnee'] === 0);
t('encaissement préservé',   (int)$tr2[0]['est_payee'] === 1);
t('statut recalculé',
    (string)CommissionDossier::pourDossier($d2)['statut'] === CommissionDossier::PARTIELLEMENT_REGLEE,
    (string)CommissionDossier::pourDossier($d2)['statut']);
t('dossier sans marque d’abandon', empty($modele->findById($d2)['abandon_le']));

t('abandon impossible hors 100 %',
    CommissionDossier::declarerAbandon($creer('Licence 1'), null, 'Motif suffisamment long.')['ok'] === false);

// ══ §E — Complément de dossier par le candidat ═════════════════════════════
echo "\n── Pièce refusée, remplacement et renvoi (spec §9.3) ──\n";

$d3 = $creer('Licence 1', 'non_boursier', 'CA-' . date('Y') . '-TST-000813');
$dossier3 = $modele->findById($d3);

t('dossier soumis : dépôt ouvert', ComplementDossier::ouvertAuDepot($dossier3) === true);

// Un dossier soumis porte déjà ses pièces obligatoires : la soumission les
// exige. On reproduit cet état, sinon on testerait un dossier impossible.
$obligatoires = $modele->piecesObligatoiresPourDossier($dossier3);
$insPiece = $db->prepare(
    "INSERT INTO documents_orientation
       (dossier_id, eleve_id, piece_requise_id, libelle, cle_b2, mime_type,
        taille_octets, hash_sha256, statut, date_depot)
     VALUES (?, ?, ?, 'Pièce obligatoire', ?, 'application/pdf', 512, ?, 'en_attente', NOW())"
);
foreach ($obligatoires as $pieceId) {
    $insPiece->execute([$d3, $userId, $pieceId, 'test/lot8-ob-' . $pieceId . '.pdf', hash('sha256', 'ob' . $pieceId)]);
}
t('pièces obligatoires déposées', ComplementDossier::blocages($dossier3) === [],
    implode(', ', ComplementDossier::blocages($dossier3)));

// La pièce conditionnelle de bourse ne concerne pas un non-boursier : elle ne
// doit jamais figurer parmi les blocages.
$dBoursier = $modele->findById($d3);
$dBoursier['statut_bourse'] = 'non_boursier';
t('attestation de bourse non réclamée à un non-boursier',
    !in_array('Attestation de bourse ANBG', ComplementDossier::blocages($dBoursier), true));

// Une pièce déposée puis refusée par l'établissement.
$db->prepare(
    "INSERT INTO documents_orientation
       (dossier_id, eleve_id, libelle, cle_b2, mime_type, taille_octets, hash_sha256,
        statut, motif_refus, date_depot)
     VALUES (?, ?, 'Acte de naissance', 'test/lot8-v1.pdf', 'application/pdf', 1024, ?, 'refuse', 'Document illisible.', NOW())"
)->execute([$d3, $userId, str_repeat('a', 64)]);
$doc1 = (int)$db->lastInsertId();

t('pièce refusée détectée', count(ComplementDossier::documentsRefuses($d3)) === 1);
t('blocage nommé',
    in_array('Acte de naissance', ComplementDossier::blocages($dossier3), true));

// L'établissement passe le dossier en « pièce manquante ».
$modele->transition($d3, DossierStatut::PIECE_MANQUANTE, 'etablissement', [
    'pieces_attendues' => 'Acte de naissance lisible',
]);
$dossier3 = $modele->findById($d3);

$r = ComplementDossier::renvoyer($dossier3, $userId);
t('renvoi refusé tant que la pièce est refusée', $r['ok'] === false, (string)$r['message']);
t('le message nomme la pièce', str_contains($r['message'], 'Acte de naissance'));

// Le candidat dépose une nouvelle version, chaînée à la refusée.
$db->prepare(
    "INSERT INTO documents_orientation
       (dossier_id, eleve_id, libelle, cle_b2, mime_type, taille_octets, hash_sha256,
        statut, remplace_document_id, date_depot)
     VALUES (?, ?, 'Acte de naissance', 'test/lot8-v2.pdf', 'application/pdf', 2048, ?, 'en_attente', ?, NOW())"
)->execute([$d3, $userId, str_repeat('b', 64), $doc1]);

t('pièce refusée soldée par sa remplaçante', ComplementDossier::documentsRefuses($d3) === []);
t('version périmée repérée', in_array($doc1, ComplementDossier::documentsRemplaces($d3), true));
t('plus aucun blocage', ComplementDossier::blocages($dossier3) === []);

$r = ComplementDossier::renvoyer($dossier3, $userId);
t('renvoi accepté', $r['ok'] === true, (string)$r['message']);
t('dossier de nouveau soumis',
    DossierStatut::normaliser((string)$modele->findById($d3)['statut']) === DossierStatut::SOUMIS);
t('date de dépôt d’origine préservée', !empty($modele->findById($d3)['soumis_le']));
t('pièces attendues effacées', empty($modele->findById($d3)['pieces_attendues']));

$r = ComplementDossier::renvoyer($modele->findById($d3), $userId);
t('second renvoi sans objet refusé', $r['ok'] === false);

// Un dossier rejeté n'est plus ouvert au dépôt.
$d4 = $creer('Licence 1', 'non_boursier', 'CA-' . date('Y') . '-TST-000814');
$modele->transition($d4, DossierStatut::REJETE, 'etablissement', ['motif' => 'Dossier hors délai.']);
t('dossier rejeté : dépôt fermé', ComplementDossier::ouvertAuDepot($modele->findById($d4)) === false);

// ══ §F — Assiette de commission des boursiers, négociée par accord ═════════
// Seul terme négociable de la règle : le super administrateur choisit, pour un
// établissement donné, si la commission d'un BOURSIER porte sur ses frais
// d'inscription (défaut) ou sur sa scolarité (accord particulier). Les autres
// situations de bourse ne bougent jamais.
echo "\n── Assiette de commission des boursiers (accords) ──\n";

if (!\Core\TarifFormationSchema::baseBoursierDisponible($db)) {
    t('colonne base_commission_boursier présente', false,
        'rejouer Database/migration_commission_boursier_tranches.sql');
} else {
    $reglageOrigine = (string)$db->query(
        "SELECT base_commission_boursier FROM etablissements WHERE id = {$etabId}"
    )->fetchColumn();

    $poserReglage = static function (string $valeur) use ($db, $etabId): void {
        $db->prepare('UPDATE etablissements SET base_commission_boursier = ? WHERE id = ?')
           ->execute([$valeur, $etabId]);
        // Le service mémorise le réglage pour la durée du process : sans purge,
        // le test lirait la valeur de l'itération précédente.
        TarifCycle::oublierReglages();
        \Core\TarifFormationSchema::oublier();
    };

    register_shutdown_function(static function () use ($db, $etabId, $reglageOrigine): void {
        $db->prepare('UPDATE etablissements SET base_commission_boursier = ? WHERE id = ?')
           ->execute([$reglageOrigine !== '' ? $reglageOrigine : 'frais_inscription', $etabId]);
    });

    // La règle pure, sans base de données.
    t('règle : boursier + accord « scolarité » → scolarité',
        TarifCycle::baseCommission('boursier', TarifCycle::BASE_SCOLARITE) === TarifCycle::BASE_SCOLARITE);
    t('règle : boursier + accord « frais » → frais',
        TarifCycle::baseCommission('boursier', TarifCycle::BASE_FRAIS_INSCRIPTION) === TarifCycle::BASE_FRAIS_INSCRIPTION);
    t('règle : le réglage NE touche PAS un non boursier',
        TarifCycle::baseCommission('non_boursier', TarifCycle::BASE_FRAIS_INSCRIPTION) === TarifCycle::BASE_SCOLARITE);
    t('règle : le réglage NE touche PAS une situation retirée ou inconnue',
        TarifCycle::baseCommission('dossier_en_cours', TarifCycle::BASE_FRAIS_INSCRIPTION) === TarifCycle::BASE_SCOLARITE);
    t('règle : valeur illisible → défaut historique',
        TarifCycle::baseCommission('boursier', 'n_importe_quoi') === TarifCycle::BASE_FRAIS_INSCRIPTION);

    // Bout en bout : l'accord « scolarité ».
    $poserReglage(TarifCycle::BASE_SCOLARITE);
    t('réglage relu depuis la fiche',
        TarifCycle::baseBoursierEtablissement($etabId, $db) === TarifCycle::BASE_SCOLARITE);

    $dSco = $creer('Licence 1', 'boursier', 'CA-' . date('Y') . '-TST-000820');
    DecisionEtablissement::valider50($dSco, $etabId, null);
    DecisionEtablissement::valider100($dSco, $etabId, null, $reglement);
    $fSco = $modele->findById($dSco);
    $cSco = CommissionDossier::pourDossier($dSco);

    t('accord « scolarité » : commission émise', $cSco !== null);
    t('accord « scolarité » : nature = scolarité',
        $cSco !== null && (string)$cSco['base_commission'] === TarifCycle::BASE_SCOLARITE,
        (string)($cSco['base_commission'] ?? '—'));
    t('accord « scolarité » : base = scolarité du dossier',
        $cSco !== null && abs((float)$cSco['montant_scolarite'] - (float)$fSco['montant_scolarite_annuelle']) < 0.001,
        (string)($cSco['montant_scolarite'] ?? '—'));
    t('accord « scolarité » : échéancier au format courant',
        $cSco !== null && count(CommissionDossier::tranches((int)$cSco['id'])) === $nbTranches);

    // Bout en bout : le défaut historique.
    $poserReglage(TarifCycle::BASE_FRAIS_INSCRIPTION);
    $dFra = $creer('Licence 1', 'boursier', 'CA-' . date('Y') . '-TST-000821');
    DecisionEtablissement::valider50($dFra, $etabId, null);
    DecisionEtablissement::valider100($dFra, $etabId, null, $reglement);
    $fFra = $modele->findById($dFra);
    $cFra = CommissionDossier::pourDossier($dFra);

    t('défaut : nature = frais d’inscription',
        $cFra !== null && (string)$cFra['base_commission'] === TarifCycle::BASE_FRAIS_INSCRIPTION,
        (string)($cFra['base_commission'] ?? '—'));
    t('défaut : base = frais figés au guichet',
        $cFra !== null && abs((float)$cFra['montant_scolarite'] - (float)$fFra['montant_frais_inscription']) < 0.001,
        (string)($cFra['montant_scolarite'] ?? '—'));

    // Un NON boursier reste sur la scolarité, réglage « frais » ou non.
    $dNon = $creer('Licence 1', 'non_boursier', 'CA-' . date('Y') . '-TST-000822');
    DecisionEtablissement::valider50($dNon, $etabId, null);
    DecisionEtablissement::valider100($dNon, $etabId, null, $reglement);
    $cNon = CommissionDossier::pourDossier($dNon);
    t('non boursier : toujours la scolarité',
        $cNon !== null && (string)$cNon['base_commission'] === TarifCycle::BASE_SCOLARITE,
        (string)($cNon['base_commission'] ?? '—'));

    // Le passé ne bouge pas : rebasculer l'accord ne recalcule pas une
    // commission déjà émise — c'est la même garantie que pour le taux.
    $poserReglage(TarifCycle::BASE_SCOLARITE);
    $cFraApres = CommissionDossier::pourDossier($dFra);
    t('commission déjà émise NON recalculée par le changement d’accord',
        $cFraApres !== null && (string)$cFraApres['base_commission'] === TarifCycle::BASE_FRAIS_INSCRIPTION
        && abs((float)$cFraApres['montant_commission'] - (float)$cFra['montant_commission']) < 0.001);

    $poserReglage($reglageOrigine !== '' ? $reglageOrigine : TarifCycle::BASE_FRAIS_INSCRIPTION);
}

echo "\n── Nettoyage ──\n";
$db->exec("DELETE FROM journal_activite WHERE objet_type = 'dossier' AND objet_id IN
           (SELECT id FROM dossiers_inscription WHERE email = " . $db->quote($MAIL) . ")");
$db->exec("DELETE FROM notifications_orientation WHERE message LIKE '%TEST Lot8%'");
$db->exec("DELETE FROM dossiers_inscription WHERE email = " . $db->quote($MAIL));
t('nettoyé', (int)$db->query(
    "SELECT COUNT(*) FROM dossiers_inscription WHERE email = " . $db->quote($MAIL)
)->fetchColumn() === 0);

echo "\n══ {$ok} réussis · {$ko} échoués ══\n";
exit($ko === 0 ? 0 : 1);
