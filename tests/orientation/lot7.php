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
 * LOT 7 — Retour arrière sur décision, délai d'analyse, reçu de scolarité.
 *
 * Couvre les trois ajouts métier de la passe « corrections guichet » :
 *   §A  machine à états : table des retours, cloisonnement des acteurs
 *   §B  Dossier::transition en mode retour : jalons effacés, taux gelé
 *   §C  DecisionEtablissement::annuler : commission, attestation, refus
 *   §D  Tracabilite::analysesEnRetard : point de départ, bornes
 *   §E  TachesPlanifiees::alerterRetardAnalyse : journal, idempotence
 *   §F  RecuInscriptionService : éligibilité, numérotation, lettres, PDF
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
use Services\Orientation\RecuInscriptionService;
use Services\Orientation\TachesPlanifiees;
use Services\Orientation\Tracabilite;

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
$MAIL   = 'test.lot7@example.test';

$db->exec("DELETE FROM dossiers_inscription WHERE email = " . $db->quote($MAIL));

// ── Tarifs de l'établissement de test ───────────────────────────────────────
// La scolarité et les frais d'inscription ne se saisissent plus au guichet :
// ils viennent de la fiche établissement (Services\Orientation\TarifCycle).
// On fixe donc ici ce que les assertions attendent, et on rend la fiche à son
// état d'origine quoi qu'il arrive — y compris si la suite s'interrompt.
$tarifsOrigine = $db->query(
    "SELECT frais_scolarite, frais_scolarite_master, frais_scolarite_boursier,
            prix_inscription, frais_dossier
     FROM etablissements WHERE id = {$etabId}"
)->fetch(PDO::FETCH_ASSOC) ?: [];
register_shutdown_function(static function () use ($db, $etabId, $tarifsOrigine): void {
    if ($tarifsOrigine === []) { return; }
    $db->prepare(
        'UPDATE etablissements
         SET frais_scolarite = ?, frais_scolarite_master = ?, frais_scolarite_boursier = ?,
             prix_inscription = ?, frais_dossier = ?
         WHERE id = ?'
    )->execute([
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
$fixerTarifs(750000, 30000, 0);

/**
 * Crée un dossier de test au statut « soumis ».
 *
 * Avec une référence : c'est un dossier d'INSCRIPTION, celui que les décisions
 * du parcours (50 %, 100 %, pièce, prolongation) acceptent. Sans référence :
 * c'est une PRÉINSCRIPTION — la distinction est celle de la plateforme
 * (Dossier::estInscription), vérifiée plus bas dans ce même lot.
 */
$creer = static function (?string $reference = null) use ($db, $etabId, $userId, $annee, $MAIL): int {
    $db->prepare(
        'INSERT INTO dossiers_inscription
           (etablissement_id, eleve_id, nom_complet, email, filiere_visee, niveau_entree,
            statut, progression, annee_academique, statut_bourse, soumis_le, reference, date_creation)
         VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, NOW(), ?, NOW())'
    )->execute([
        $etabId, $userId, 'TEST Lot7', $MAIL, 'Licence 1', 'Licence 1',
        'soumis', $annee, 'non_boursier', $reference,
    ]);

    return (int)$db->lastInsertId();
};

$finances = [
    'montant_scolarite_annuelle' => '750000',
    'montant_encaisse_initial'   => '250000',
    'mode_paiement'              => 'especes',
    'reference_paiement'         => 'REC-GUICHET-001',
    'date_paiement'              => date('Y-m-d'),
];

// ══ §A — Machine à états ═══════════════════════════════════════════════════
echo "\n── Table des retours (machine à états) ──\n";

t('50 % revient à « soumis »',       DossierStatut::retour(DossierStatut::VALIDE_50) === DossierStatut::SOUMIS);
t('pièce manquante revient à « soumis »', DossierStatut::retour(DossierStatut::PIECE_MANQUANTE) === DossierStatut::SOUMIS);
t('rejet revient à « soumis »',      DossierStatut::retour(DossierStatut::REJETE) === DossierStatut::SOUMIS);
t('100 % revient à 50 %',            DossierStatut::retour(DossierStatut::VALIDE_100) === DossierStatut::VALIDE_50);
t('un brouillon n’a rien à annuler', DossierStatut::retour(DossierStatut::BROUILLON) === null);
t('un dossier soumis n’a rien à annuler', DossierStatut::retour(DossierStatut::SOUMIS) === null);
t('un dossier expiré n’a rien à annuler', DossierStatut::retour(DossierStatut::EXPIRE) === null);

t('libellé de bouton fourni',        DossierStatut::retourLibelle(DossierStatut::VALIDE_100) !== null);
t('conséquence annoncée',            str_contains((string)DossierStatut::retourEffet(DossierStatut::VALIDE_100), 'commission'));
t('aucun libellé sans retour',       DossierStatut::retourLibelle(DossierStatut::SOUMIS) === null);

t('retour autorisé à l’établissement',
    DossierStatut::peut(DossierStatut::VALIDE_100, DossierStatut::VALIDE_50, 'etablissement', true));
t('retour REFUSÉ à l’étudiant',
    !DossierStatut::peut(DossierStatut::VALIDE_100, DossierStatut::VALIDE_50, 'etudiant', true));
t('retour REFUSÉ au système',
    !DossierStatut::peut(DossierStatut::VALIDE_100, DossierStatut::VALIDE_50, 'systeme', true));
t('seul le chemin inverse exact passe',
    !DossierStatut::peut(DossierStatut::REJETE, DossierStatut::VALIDE_100, 'etablissement', true));
t('sans le drapeau, la sortie d’état terminal reste refusée',
    !DossierStatut::peut(DossierStatut::VALIDE_100, DossierStatut::VALIDE_50, 'etablissement'));

// La zone décision se construit sur TRANSITIONS : un retour ne doit pas y
// apparaître comme une décision de plus.
t('les retours ne polluent pas la zone décision',
    DossierStatut::transitionsPossibles(DossierStatut::VALIDE_100, 'etablissement') === []);
t('50 % ne propose pas « soumis » comme décision',
    !in_array(DossierStatut::SOUMIS, DossierStatut::transitionsPossibles(DossierStatut::VALIDE_50, 'etablissement'), true));

// ══ §B — Transition en mode retour ═════════════════════════════════════════
echo "\n── Annulation d’une validation à 50 % ──\n";

$id = $creer('CA-' . date('Y') . '-TST-000710');
DecisionEtablissement::valider50($id, $etabId, null);
$avant = $modele->findById($id);
t('validé à 50 %',                   DossierStatut::normaliser((string)$avant['statut']) === DossierStatut::VALIDE_50);
t('date limite posée',               !empty($avant['expire_le']));

$r = DecisionEtablissement::annuler($id, $etabId, null, 'Erreur de saisie au guichet.');
$apres = $modele->findById($id);
t('annulation acceptée',             $r['ok'] === true, (string)($r['message'] ?? ''));
t('retour à « soumis »',             DossierStatut::normaliser((string)$apres['statut']) === DossierStatut::SOUMIS);
t('progression remise à 0',          (int)$apres['progression'] === 0);
t('jalon valide_50_le effacé',       $apres['valide_50_le'] === null);
t('date limite effacée',             $apres['expire_le'] === null, 'un dossier non validé ne doit pas pouvoir expirer');
t('date de dépôt conservée',         !empty($apres['soumis_le']));
t('trace en historique',
    (int)$db->query("SELECT COUNT(*) FROM historique_statut_dossier
                      WHERE dossier_id = {$id} AND ancien_statut = 'valide_50' AND nouveau_statut = 'soumis'")->fetchColumn() === 1);
t('journalisé comme annulation, pas comme décision',
    (int)$db->query("SELECT COUNT(*) FROM journal_activite
                      WHERE objet_type = 'dossier' AND objet_id = {$id}
                        AND type_evenement = 'decision_annulee'")->fetchColumn() >= 1);
t('candidat notifié',
    (int)$db->query("SELECT COUNT(*) FROM notifications_orientation
                      WHERE user_id = {$userId} AND type = 'decision_annulee'")->fetchColumn() >= 1);

$r2 = DecisionEtablissement::annuler($id, $etabId, null);
t('rien à annuler deux fois de suite', $r2['ok'] === false);

echo "\n── Annulation d’une demande de pièce ──\n";
$idP = $creer('CA-' . date('Y') . '-TST-000711');
DecisionEtablissement::demanderPiece($idP, $etabId, null, 'Relevé de notes illisible.');
t('pièce demandée', DossierStatut::normaliser((string)$modele->findById($idP)['statut']) === DossierStatut::PIECE_MANQUANTE);
DecisionEtablissement::annuler($idP, $etabId, null);
$apresP = $modele->findById($idP);
t('retour à « soumis »',        DossierStatut::normaliser((string)$apresP['statut']) === DossierStatut::SOUMIS);
t('pièces attendues effacées',  empty($apresP['pieces_attendues']));

echo "\n── Annulation d’un rejet ──\n";
$idR = $creer('CA-' . date('Y') . '-TST-000712');
DecisionEtablissement::rejeter($idR, $etabId, null, 'Diplôme non conforme au niveau demandé.');
t('rejeté', DossierStatut::normaliser((string)$modele->findById($idR)['statut']) === DossierStatut::REJETE);
DecisionEtablissement::annuler($idR, $etabId, null);
$apresR = $modele->findById($idR);
t('retour à « soumis »',   DossierStatut::normaliser((string)$apresR['statut']) === DossierStatut::SOUMIS);
t('motif de rejet effacé', $apresR['motif_rejet'] === null);

// ══ §C — Annulation d’une inscription à 100 % ══════════════════════════════
echo "\n── Annulation d’une inscription à 100 % ──\n";

$id100 = $creer('CA-' . date('Y') . '-TST-000777');
DecisionEtablissement::valider50($id100, $etabId, null);
$v = DecisionEtablissement::valider100($id100, $etabId, null, $finances, null);
t('validé à 100 %', $v['ok'] === true, (string)($v['message'] ?? ''));

$cent = $modele->findById($id100);
$tauxGele = $cent['taux_commission_applique'];
t('taux gelé au moment des 100 %', $tauxGele !== null);
t('commission générée',            CommissionDossier::pourDossier($id100) !== null);

$rA = DecisionEtablissement::annuler($id100, $etabId, null, 'Règlement enregistré sur le mauvais dossier.');
$apres100 = $modele->findById($id100);
t('annulation acceptée',          $rA['ok'] === true, (string)($rA['message'] ?? ''));
t('retour à 50 %',                DossierStatut::normaliser((string)$apres100['statut']) === DossierStatut::VALIDE_50);
t('progression revenue à 50',     (int)$apres100['progression'] === 50);
t('jalon valide_100_le effacé',   $apres100['valide_100_le'] === null);
t('montants effacés',             $apres100['montant_scolarite_annuelle'] === null && $apres100['mode_paiement'] === null);
t('taux de commission TOUJOURS gelé', (string)$apres100['taux_commission_applique'] === (string)$tauxGele,
    'le taux du jour de la première validation doit survivre à la correction');
t('nouvelle date limite calculée',
    !empty($apres100['expire_le']) && strtotime((string)$apres100['expire_le']) > time(),
    'sans cela la tâche d’expiration ferait disparaître le dossier la nuit suivante');

$com = CommissionDossier::pourDossier($id100);
t('commission annulée',           $com !== null && (string)$com['statut'] === CommissionDossier::ANNULEE);
t('attestation retirée',          $modele->getAttestation($id100) === null,
    'une attestation qui certifie une inscription annulée est un faux en circulation');
t('ligne inscription retirée',    $modele->hasInscription($id100) === false);
t('date de dépôt conservée',      !empty($apres100['soumis_le']));

// Commission réglée : le back-office ne défait pas une opération comptable close.
$id100b = $creer('CA-' . date('Y') . '-TST-000778');
DecisionEtablissement::valider50($id100b, $etabId, null);
DecisionEtablissement::valider100($id100b, $etabId, null, $finances, null);
$db->prepare('UPDATE commissions_scolarite SET statut = ? WHERE dossier_id = ?')
   ->execute([CommissionDossier::REGLEE, $id100b]);
$rBloc = DecisionEtablissement::annuler($id100b, $etabId, null);
t('commission réglée → annulation refusée', $rBloc['ok'] === false);
t('dossier laissé intact',
    DossierStatut::normaliser((string)$modele->findById($id100b)['statut']) === DossierStatut::VALIDE_100);

// Cloisonnement : un autre établissement ne défait pas la décision d'un voisin.
$autreEtab = (int)$db->query("SELECT id FROM etablissements WHERE id <> {$etabId} ORDER BY id LIMIT 1")->fetchColumn();
if ($autreEtab > 0) {
    $rTenant = DecisionEtablissement::annuler($id100, $autreEtab, null);
    t('cloisonnement : établissement tiers refusé', $rTenant['ok'] === false);
}

// ══ §D — Délai d'analyse ═══════════════════════════════════════════════════
echo "\n── Délai d’analyse (spec §14) ──\n";

t('délai par défaut = 3 jours',  Tracabilite::DELAI_ANALYSE_JOURS === 3);
t('délai borné (jamais 0)',      Tracabilite::delaiAnalyse() >= 1);

$idVieux = $creer('CA-' . date('Y') . '-TST-000713');
$db->exec("UPDATE dossiers_inscription SET soumis_le = DATE_SUB(NOW(), INTERVAL 10 DAY) WHERE id = {$idVieux}");
$idFrais = $creer('CA-' . date('Y') . '-TST-000714');

$retards = Tracabilite::analysesEnRetard(3, 500);
$idsRetard = array_map('intval', array_column($retards, 'id'));
t('dossier ancien signalé',      in_array($idVieux, $idsRetard, true));
t('dossier du jour non signalé', !in_array($idFrais, $idsRetard, true));

$ligne = array_values(array_filter($retards, static fn(array $r): bool => (int)$r['id'] === $idVieux));
t('jours d’attente calculés',    $ligne !== [] && (int)$ligne[0]['jours_attente'] >= 10);
t('établissement nommé',         $ligne !== [] && trim((string)$ligne[0]['etab_nom']) !== '');

// Le compteur repart au dernier retour en « soumis » : un dossier renvoyé
// après une demande de pièce redonne un délai plein à l'établissement.
DecisionEtablissement::demanderPiece($idVieux, $etabId, null, 'Photo d’identité manquante.');
t('en attente du candidat → hors du retard',
    !in_array($idVieux, array_map('intval', array_column(Tracabilite::analysesEnRetard(3, 500), 'id')), true));

$modele->transition($idVieux, DossierStatut::SOUMIS, 'etudiant', ['acteur_id' => $userId]);
$idsApresRenvoi = array_map('intval', array_column(Tracabilite::analysesEnRetard(3, 500), 'id'));
t('délai remis à zéro au renvoi', !in_array($idVieux, $idsApresRenvoi, true),
    'sinon tout dossier ayant connu un aller-retour serait signalé à tort');

t('validé à 50 % → hors du retard',
    !in_array($id100, array_map('intval', array_column(Tracabilite::analysesEnRetard(3, 500), 'id')), true));

// ══ §E — Tâche d'alerte ════════════════════════════════════════════════════
echo "\n── Alerte super administrateur ──\n";

$db->exec("DELETE FROM journal_activite WHERE objet_type = 'dossier' AND objet_id = {$idVieux}
             AND type_evenement = 'alerte_retard_analyse'");
$db->exec("UPDATE dossiers_inscription SET soumis_le = DATE_SUB(NOW(), INTERVAL 12 DAY) WHERE id = {$idVieux}");
$db->exec("DELETE FROM historique_statut_dossier WHERE dossier_id = {$idVieux}");

$rapport = TachesPlanifiees::alerterRetardAnalyse();
t('rapport produit',        isset($rapport['dossiers'], $rapport['etablissements'], $rapport['erreurs']));
t('au moins un dossier signalé', $rapport['dossiers'] >= 1, json_encode($rapport));
t('entrée de journal écrite',
    (int)$db->query("SELECT COUNT(*) FROM journal_activite
                      WHERE objet_type = 'dossier' AND objet_id = {$idVieux}
                        AND type_evenement = 'alerte_retard_analyse'")->fetchColumn() === 1);

$rejeu = TachesPlanifiees::alerterRetardAnalyse();
t('rejeu : aucun doublon',
    (int)$db->query("SELECT COUNT(*) FROM journal_activite
                      WHERE objet_type = 'dossier' AND objet_id = {$idVieux}
                        AND type_evenement = 'alerte_retard_analyse'")->fetchColumn() === 1,
    'un dossier oublié deux mois produirait sinon soixante entrées identiques');
t('rejeu : rapport vide',   $rejeu['dossiers'] === 0);

// ══ §F — Reçu de scolarité ═════════════════════════════════════════════════
echo "\n── Reçu de règlement de scolarité ──\n";

$idRecu = $creer('CA-' . date('Y') . '-TST-000779');
t('reçu indisponible avant validation',
    RecuInscriptionService::disponible($modele->findById($idRecu)) === false);

DecisionEtablissement::valider50($idRecu, $etabId, null);
t('reçu indisponible à 50 %',
    RecuInscriptionService::disponible($modele->findById($idRecu)) === false);

DecisionEtablissement::valider100($idRecu, $etabId, null, $finances, null);
$dRecu = $modele->findById($idRecu);
t('reçu disponible à 100 %', RecuInscriptionService::disponible($dRecu) === true);

$numero = RecuInscriptionService::numero($dRecu);
t('numéro dérivé de la référence', $numero === 'REC-' . date('Y') . '-TST-000779', $numero);
t('numéro stable entre deux appels', $numero === RecuInscriptionService::numero($dRecu));

t('montant en lettres',        RecuInscriptionService::enLettres(250000) === 'deux cent cinquante mille francs CFA',
    RecuInscriptionService::enLettres(250000));
t('lettres — un franc',        RecuInscriptionService::enLettres(1) === 'un franc CFA', RecuInscriptionService::enLettres(1));
t('lettres — quatre-vingts',   RecuInscriptionService::enLettres(80) === 'quatre-vingts francs CFA', RecuInscriptionService::enLettres(80));
t('lettres — soixante et onze', RecuInscriptionService::enLettres(71) === 'soixante et onze francs CFA', RecuInscriptionService::enLettres(71));
t('lettres — quatre-vingt-dix-neuf', RecuInscriptionService::enLettres(99) === 'quatre-vingt-dix-neuf francs CFA', RecuInscriptionService::enLettres(99));
t('lettres — un million',      RecuInscriptionService::enLettres(1000000) === 'un million de francs CFA',
                               RecuInscriptionService::enLettres(1000000));
t('lettres — deux millions',   RecuInscriptionService::enLettres(2000000) === 'deux millions de francs CFA',
                               RecuInscriptionService::enLettres(2000000));
t('lettres — « de » seulement si la phrase finit par million',
                               RecuInscriptionService::enLettres(1250500) === 'un million deux cent cinquante mille cinq cents francs CFA',
                               RecuInscriptionService::enLettres(1250500));
t('lettres — dix-sept',        RecuInscriptionService::enLettres(17) === 'dix-sept francs CFA', RecuInscriptionService::enLettres(17));
t('lettres — zéro',            RecuInscriptionService::enLettres(0) === 'zéro franc CFA');

$pdf = RecuInscriptionService::rendre($idRecu);
t('PDF produit',               $pdf !== null && str_starts_with((string)$pdf, '%PDF'),
                               $pdf === null ? 'null' : substr((string)$pdf, 0, 8));
if ($pdf !== null) {
    preg_match_all('/\/Type\s*\/Page[^s]/', $pdf, $pages);
    t('tient sur une page A4',  count($pages[0]) === 1, count($pages[0]) . ' page(s)');
    t('poids raisonnable',      strlen($pdf) > 2000 && strlen($pdf) < 3_000_000, strlen($pdf) . ' octets');
}

// Une inscription annulée ne doit plus produire de reçu.
DecisionEtablissement::annuler($idRecu, $etabId, null);
t('reçu retiré après annulation',
    RecuInscriptionService::disponible($modele->findById($idRecu)) === false,
    'un reçu sur un règlement effacé serait un faux');
t('rendu refusé après annulation', RecuInscriptionService::rendre($idRecu) === null);

// ══ §H — Inscription vs préinscription : une seule règle ═══════════════════
// L'inscription est un PROCESSUS. Un dossier d'inscription appartient à
// « Mes inscriptions » dès l'étape 1, et y reste quelle que soit l'issue.
echo "\n── Classement inscription / préinscription ──\n";

t('brouillon = inscription en cours',
    Dossier::estInscription(['statut' => 'brouillon', 'reference' => null]));
t('référence CA- = inscription',
    Dossier::estInscription(['statut' => 'soumis', 'reference' => 'CA-2026-HAV-000001']));
t('inscription rejetée reste une inscription',
    Dossier::estInscription(['statut' => 'rejete', 'reference' => 'CA-2026-HAV-000001']),
    'un dossier refusé ne redevient pas une préinscription');
t('ligne inscriptions = inscription',
    Dossier::estInscription(['statut' => 'valide_100', 'reference' => null, 'inscription_id' => 12]));
t('préinscription reste une préinscription',
    !Dossier::estInscription(['statut' => 'soumis', 'reference' => null]));
t('référence vide ne compte pas',
    !Dossier::estInscription(['statut' => 'soumis', 'reference' => '   ']));

// Les deux formes de la règle doivent trancher à l'identique, sinon l'espace
// élève et la console d'administration racontent deux histoires du même dossier.
$idInsc = $creer('CA-' . date('Y') . '-TST-000801');
$idPre  = $creer();  // sans référence : préinscription

$sql = 'SELECT d.id, ' . Dossier::SQL_EST_INSCRIPTION . ' AS est_insc
          FROM dossiers_inscription d WHERE d.id IN (?, ?)';
$st = $db->prepare($sql);
$st->execute([$idInsc, $idPre]);
$verdictSql = [];
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
    $verdictSql[(int)$ligne['id']] = (int)$ligne['est_insc'] === 1;
}

t('SQL : dossier avec référence = inscription',   $verdictSql[$idInsc] === true);
t('SQL : dossier sans référence = préinscription', $verdictSql[$idPre] === false);
t('SQL et PHP concordent (inscription)',
    $verdictSql[$idInsc] === Dossier::estInscription($modele->findById($idInsc)));
t('SQL et PHP concordent (préinscription)',
    $verdictSql[$idPre] === Dossier::estInscription($modele->findById($idPre)));

// La console d'administration doit voir le dossier d'inscription AVANT les 100 %.
$fiche = Tracabilite::ficheUtilisateur($userId);
$idsDossiers = array_map('intval', array_column($fiche['dossiers'], 'id'));
$idsPreins   = array_map('intval', array_column($fiche['preinscriptions'], 'id'));
t('admin : inscription en cours listée dans les dossiers', in_array($idInsc, $idsDossiers, true));
t('admin : inscription en cours absente des préinscriptions', !in_array($idInsc, $idsPreins, true));
t('admin : préinscription listée du bon côté',
    in_array($idPre, $idsPreins, true) && !in_array($idPre, $idsDossiers, true));

// ══ §I — Frais d'inscription distincts de la scolarité ═════════════════════
echo "\n── Frais d’inscription séparés de la scolarité ──\n";

$colonneFrais = \Core\DossierFinanceSchema::fraisInscriptionDisponible($db);
t('colonne montant_frais_inscription présente', $colonneFrais,
    'rejouer Database/migration_frais_inscription_dossier.sql');

$financesFrais = $finances + ['montant_frais_inscription' => '30000'];

$ctrl100 = DecisionEtablissement::validerFinances($financesFrais);
t('frais acceptés',            $ctrl100['erreurs'] === [], json_encode($ctrl100['erreurs']));
t('frais isolés dans les valeurs', (float)$ctrl100['valeurs']['montant_frais_inscription'] === 30000.0);
t('frais NON ajoutés à la scolarité',
    (float)$ctrl100['valeurs']['montant_scolarite_annuelle'] === 750000.0);
t('frais NON ajoutés au versement',
    (float)$ctrl100['valeurs']['montant_encaisse_initial'] === 250000.0);

t('frais absents → null, pas zéro',
    DecisionEtablissement::validerFinances($finances)['valeurs']['montant_frais_inscription'] === null,
    'zéro affirmerait que l’établissement ne prend pas de frais');
t('frais négatifs refusés',
    isset(DecisionEtablissement::validerFinances($finances + ['montant_frais_inscription' => '-5'])['erreurs']['montant_frais_inscription']));
t('frais illisibles refusés',
    isset(DecisionEtablissement::validerFinances($finances + ['montant_frais_inscription' => 'trente mille'])['erreurs']['montant_frais_inscription']));
t('frais supérieurs à la scolarité acceptés',
    DecisionEtablissement::validerFinances($finances + ['montant_frais_inscription' => '9000000'])['erreurs'] === [],
    'ils ne sont pas bornés par la scolarité : ils n’en font pas partie');

$idFrais = $creer('CA-' . date('Y') . '-TST-000802');
DecisionEtablissement::valider50($idFrais, $etabId, null);
$vF = DecisionEtablissement::valider100($idFrais, $etabId, null, $financesFrais, null);
t('validation à 100 % avec frais', $vF['ok'] === true, (string)($vF['message'] ?? ''));

$dF = $modele->findById($idFrais);
t('frais enregistrés en base',        (float)$dF['montant_frais_inscription'] === 30000.0);
t('scolarité intacte',                (float)$dF['montant_scolarite_annuelle'] === 750000.0);
t('versement intact',                 (float)$dF['montant_encaisse_initial'] === 250000.0);

$comF = CommissionDossier::pourDossier($idFrais);
t('commission calculée sur la SEULE scolarité',
    $comF !== null && (float)$comF['montant_scolarite'] === 750000.0,
    'les frais d’inscription ne sont pas une base de commission');

// Le reçu doit présenter les deux natures séparément.
$refl  = new ReflectionMethod(RecuInscriptionService::class, 'rassembler');
$refl->setAccessible(true);
$vue = $refl->invoke(null, $idFrais);
t('reçu : bloc frais présent',        $vue['a_frais'] === true);
t('reçu : frais affichés à part',     str_starts_with($vue['frais'], '30 000'), $vue['frais']);
t('reçu : scolarité affichée',        str_starts_with($vue['scolarite'], '750 000'), $vue['scolarite']);
t('reçu : reste = scolarité - versement, SANS les frais',
    str_starts_with($vue['reste'], '500 000'), $vue['reste']);
t('reçu : total du jour = frais + versement',
    str_starts_with($vue['total_jour'], '280 000'), $vue['total_jour']);
t('reçu : lettres portent sur le total remis',
    $vue['lettres'] === 'deux cent quatre-vingt mille francs CFA', $vue['lettres']);

$pdfF = RecuInscriptionService::rendre($idFrais);
t('reçu avec frais : PDF produit', $pdfF !== null && str_starts_with((string)$pdfF, '%PDF'));
if ($pdfF !== null) {
    preg_match_all('/\/Type\s*\/Page[^s]/', $pdfF, $pagesF);
    t('reçu avec frais : une seule page', count($pagesF[0]) === 1, count($pagesF[0]) . ' page(s)');
}

// ── Le reçu tient sur une page, même avec toutes les entrées au maximum ──
// Le gabarit a un budget de hauteur fixe alors que ces chaînes viennent de la
// saisie d'un établissement. Sans bornes, un nom d'école un peu long suffisait
// à produire un reçu de deux pages dont la seconde ne portait que le pied.
$idLong = $creer('CA-' . date('Y') . '-TST-000804');
$db->prepare('UPDATE dossiers_inscription
                 SET nom_complet = ?, filiere_visee = ?, specialite_visee = ?
               WHERE id = ?')
   ->execute([
       'MBOUMBA NZE OYONO ONDO OBAME NGUEMA Jean-Baptiste Aristide',
       'Licence professionnelle en gestion des ressources humaines et communication',
       'Management des organisations et conduite du changement',
       $idLong,
   ]);
DecisionEtablissement::valider50($idLong, $etabId, null);
// Les montants viennent maintenant de la fiche établissement : pour éprouver la
// mise en page avec les nombres les plus larges possibles, c'est la FICHE qu'on
// pousse au maximum, le temps de cette validation.
$fixerTarifs(95000000, 1500000, 0);
DecisionEtablissement::valider100($idLong, $etabId, null, [
    'montant_encaisse_initial'   => '60000000',
    'mode_paiement'              => 'especes',
    'reference_paiement'         => 'RECU-GUICHET-2026-000999-BIS',
    'date_paiement'              => date('Y-m-d'),
], null);
$fixerTarifs(750000, 30000, 0);

$vueLong = $refl->invoke(null, $idLong);
t('dossier long validé à 100 %', $vueLong !== null,
    'validerFinances a refusé la saisie : le cas de mise en page n’est pas testé');
$vueLong ??= ['formation' => '', 'etab_ligne' => '', 'candidat' => '', 'candidat_long' => false];
t('formation bornée',    mb_strlen($vueLong['formation']) <= 61, $vueLong['formation']);
t('adresse bornée',      mb_strlen($vueLong['etab_ligne']) <= 77, $vueLong['etab_ligne']);
t('nom repéré comme long', $vueLong['candidat_long'] === true);
t('nom JAMAIS tronqué',
    str_contains($vueLong['candidat'], 'Aristide') && !str_contains($vueLong['candidat'], '…'),
    'un nom coupé sur un justificatif est inacceptable : il rétrécit, il ne se coupe pas');

$pdfLong = RecuInscriptionService::rendre($idLong);
t('entrées au maximum : PDF produit', $pdfLong !== null && str_starts_with((string)$pdfLong, '%PDF'));
if ($pdfLong !== null) {
    preg_match_all('/\/Type\s*\/Page[^s]/', $pdfLong, $pagesL);
    t('entrées au maximum : une seule page', count($pagesL[0]) === 1, count($pagesL[0]) . ' page(s)');
}

// Établissement qui ne prend PAS de frais d'inscription : le bloc disparaît du
// reçu au lieu d'afficher « 0 XAF », qui laisserait croire à un oubli de saisie.
$idSansFrais = $creer('CA-' . date('Y') . '-TST-000803');
DecisionEtablissement::valider50($idSansFrais, $etabId, null);
$fixerTarifs(750000, 0, 0);
DecisionEtablissement::valider100($idSansFrais, $etabId, null, $finances, null);
$fixerTarifs(750000, 30000, 0);
$vueSans = $refl->invoke(null, $idSansFrais);
t('reçu sans frais : bloc masqué',      $vueSans['a_frais'] === false);
t('reçu sans frais : total = versement', str_starts_with($vueSans['total_jour'], '250 000'), $vueSans['total_jour']);

// L'annulation efface aussi les frais : plus de règlement, plus de trace.
DecisionEtablissement::annuler($idFrais, $etabId, null);
t('annulation : frais effacés', $modele->findById($idFrais)['montant_frais_inscription'] === null);

// ══ §G — Verrou de l'adresse e-mail (front-office) ═════════════════════════
// La barrière qui compte est côté serveur : le `readonly` du formulaire se
// retire en deux clics dans l'inspecteur du navigateur. On appelle donc
// directement les méthodes du contrôleur, session forgée à l'appui.
echo "\n── Verrou de l’adresse e-mail ──\n";

$_SESSION = ['user_id' => $userId];
$emailCompte = strtolower(trim((string)$db->query("SELECT email FROM users WHERE id = {$userId}")->fetchColumn()));

$ctrl = new \Controllers\Orientation\CandidatureController();
$refl = new ReflectionClass($ctrl);

$appeler = static function (string $methode, array $args) use ($ctrl, $refl) {
    $m = $refl->getMethod($methode);
    $m->setAccessible(true);
    return $m->invokeArgs($ctrl, $args);
};

t('adresse du compte lue',       $appeler('emailCompte', []) === $emailCompte, (string)$appeler('emailCompte', []));
t('adresse identique acceptée',  $appeler('erreurEmailCompte', [['email' => $emailCompte]]) === null);
t('casse et espaces tolérés',
    $appeler('erreurEmailCompte', [['email' => '  ' . strtoupper($emailCompte) . ' ']]) === null,
    'une majuscule ne doit pas bloquer une candidature');
t('champ vide non bloquant ici', $appeler('erreurEmailCompte', [['email' => '']]) === null,
    'le champ requis est traité par validate(), pas par le verrou');

$err = $appeler('erreurEmailCompte', [['email' => 'autre.adresse@example.test']]);
t('adresse différente REFUSÉE',  is_string($err) && $err !== '');
t('le message nomme l’adresse du compte', is_string($err) && str_contains($err, $emailCompte));

$pre = $appeler('prefillFromUser', [[]]);
t('e-mail prérempli',            ($pre['email'] ?? null) === $emailCompte);
t('nom prérempli',               trim((string)($pre['nom_complet'] ?? '')) !== '');

// Après une erreur de validation, la saisie précédente revient par le flash.
// Le nom saisi doit survivre ; l'adresse, elle, est réimposée.
$pre2 = $appeler('prefillFromUser', [['nom_complet' => 'NOM SAISI', 'email' => 'forge@example.test', 'ville' => 'Oyem']]);
t('nom saisi conservé',          $pre2['nom_complet'] === 'NOM SAISI');
t('autres champs conservés',     $pre2['ville'] === 'Oyem');
t('adresse forgée écrasée',      $pre2['email'] === $emailCompte,
    'le champ affiché doit correspondre à ce que le serveur retiendra');

// Visiteur non connecté : aucun verrou, aucun préremplissage.
$_SESSION = [];
$ctrl2 = new \Controllers\Orientation\CandidatureController();
$r2 = new ReflectionClass($ctrl2);
$app2 = static function (string $methode, array $args) use ($ctrl2, $r2) {
    $m = $r2->getMethod($methode); $m->setAccessible(true); return $m->invokeArgs($ctrl2, $args);
};
t('visiteur : pas d’adresse de compte', $app2('emailCompte', []) === null);
t('visiteur : aucune erreur de verrou', $app2('erreurEmailCompte', [['email' => 'libre@example.test']]) === null);
t('visiteur : aucun préremplissage',    $app2('prefillFromUser', [[]]) === []);

$_SESSION = [];

// ══ Nettoyage ══════════════════════════════════════════════════════════════
echo "\n── Nettoyage ──\n";
$db->exec("DELETE FROM notifications_orientation WHERE user_id = {$userId} AND type = 'decision_annulee'");
$db->exec("DELETE FROM dossiers_inscription WHERE email = " . $db->quote($MAIL));
t('nettoyé',
    (int)$db->query("SELECT COUNT(*) FROM dossiers_inscription WHERE email = " . $db->quote($MAIL))->fetchColumn() === 0);

echo "\n══ {$ok} réussis · {$ko} échoués ══\n";
exit($ko === 0 ? 0 : 1);
