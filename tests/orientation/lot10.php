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
 * LOT 10 — Une seule inscription par candidat, par établissement et par année.
 *
 *   §A  UniciteInscription : ce qui bloque, ce qui ne bloque pas
 *   §B  Identité : compte OU e-mail
 *   §C  Périmètre : inscriptions seulement, préinscriptions libres
 *   §D  Bornes : autre établissement, autre année, dossier exclu
 *   §E  Guichet : l'année académique est bien posée à la création
 *   §F  Messages : ils nomment le dossier existant
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
use Services\Orientation\UniciteInscription;

$ok = 0; $ko = 0;
function t(string $n, bool $c, string $d = ''): void {
    global $ok, $ko;
    if ($c) { $ok++; echo "  ✓ $n\n"; } else { $ko++; echo "  ✗ $n $d\n"; }
}

$db     = Database::getInstance()->getConnection();
$etabId = (int)$db->query('SELECT id FROM etablissements ORDER BY id LIMIT 1')->fetchColumn();
$autre  = (int)$db->query("SELECT id FROM etablissements WHERE id <> {$etabId} ORDER BY id LIMIT 1")->fetchColumn();
$userId = (int)$db->query('SELECT id FROM users WHERE is_deleted = 0 ORDER BY id LIMIT 1')->fetchColumn();
$annee  = AnneeUniversitaire::code();
$MAIL   = 'test.lot10@example.test';

$ids = [];

// Tout part, même en cas d'interruption : ces dossiers portent un e-mail
// dédié, aucun dossier réel ne peut être touché.
register_shutdown_function(static function () use ($db, $MAIL): void {
    $db->exec("DELETE i FROM inscriptions i
                 JOIN dossiers_inscription d ON d.id = i.dossier_id
                WHERE d.email = " . $db->quote($MAIL));
    $db->exec("DELETE FROM dossiers_inscription WHERE email = " . $db->quote($MAIL));
});

/**
 * Crée un dossier de test. `reference` non nulle = INSCRIPTION (critère du
 * modèle, Dossier::SQL_EST_INSCRIPTION) ; référence nulle et statut autre que
 * « brouillon » = PRÉINSCRIPTION.
 */
$creer = static function (
    int $etab, ?int $eleve, string $statut, ?string $reference,
    ?string $anneeDossier = null, ?string $email = null
) use ($db, $annee, $MAIL): int {
    $db->prepare(
        'INSERT INTO dossiers_inscription
           (etablissement_id, eleve_id, nom_complet, email, genre, date_naissance,
            lieu_naissance, nationalite, telephone, filiere_visee, niveau_entree,
            statut, progression, annee_academique, reference, soumis_le, date_creation)
         VALUES (?, ?, ?, ?, "femme", "2006-01-01", "Libreville", "Gabonaise",
                 "06 00 00 00", "Droit ZZ10", "Licence 1", ?, 0, ?, ?, NOW(), NOW())'
    )->execute([
        $etab, $eleve, 'TEST Lot10', $email ?? $MAIL,
        $statut, $anneeDossier ?? $annee, $reference,
    ]);

    return (int)$db->lastInsertId();
};

$ref = static fn(string $suffixe): string => sprintf('CA-%s-TL10-%s', date('Y'), $suffixe);

// ══ §A — Ce qui bloque ══════════════════════════════════════════════════════
echo "\n── Une inscription vivante occupe la place (§A) ──\n";

t('table vide → rien ne bloque',
    UniciteInscription::existante($etabId, $userId, $MAIL) === null);

$vivant = $creer($etabId, $userId, 'soumis', $ref('000001'));
$ids[]  = $vivant;

$bloquant = UniciteInscription::existante($etabId, $userId, $MAIL);
t('inscription soumise → bloque',     $bloquant !== null);
t('c’est bien LE dossier existant',   $bloquant !== null && (int)$bloquant['id'] === $vivant);
t('la fiche établissement est jointe', $bloquant !== null && ($bloquant['etab_nom'] ?? '') !== '');
t('dejaInscrit() dit la même chose',  UniciteInscription::dejaInscrit($etabId, $userId, $MAIL) === true);

// Un brouillon compte : c'est l'étape 1 déjà enregistrée. Sans lui, un candidat
// qui abandonne à l'étape 2 rouvrait un dossier neuf à chaque passage.
$db->exec("UPDATE dossiers_inscription SET statut = 'brouillon', reference = NULL WHERE id = {$vivant}");
t('brouillon (étape 1 enregistrée) → bloque',
    UniciteInscription::dejaInscrit($etabId, $userId, $MAIL));

foreach (['soumis', 'piece_manquante', 'valide_50', 'valide_100', 'paye', 'accepte', 'inscrit'] as $statut) {
    $db->prepare('UPDATE dossiers_inscription SET statut = ?, reference = ? WHERE id = ?')
       ->execute([$statut, $ref('000001'), $vivant]);
    t("statut « {$statut} » → bloque", UniciteInscription::dejaInscrit($etabId, $userId, $MAIL));
}

echo "\n── Un dossier mort ne bloque personne (§A) ──\n";
foreach (['rejete', 'expire', 'annule', 'refuse'] as $statut) {
    $db->prepare('UPDATE dossiers_inscription SET statut = ? WHERE id = ?')->execute([$statut, $vivant]);
    t("statut « {$statut} » → ne bloque pas",
        !UniciteInscription::dejaInscrit($etabId, $userId, $MAIL),
        'un refus ne doit pas enfermer le candidat pour l’année');
}

// ══ §B — Identité : le compte OU l'e-mail ══════════════════════════════════
echo "\n── Identité : compte OU e-mail (§B) ──\n";

$db->prepare('UPDATE dossiers_inscription SET statut = "soumis" WHERE id = ?')->execute([$vivant]);

t('reconnu par le compte seul',  UniciteInscription::dejaInscrit($etabId, $userId, null));
t('reconnu par l’e-mail seul',   UniciteInscription::dejaInscrit($etabId, null, $MAIL));
t('e-mail insensible à la casse et aux espaces',
    UniciteInscription::dejaInscrit($etabId, null, '  ' . strtoupper($MAIL) . ' '));
t('ni compte ni e-mail → ne bloque pas',
    !UniciteInscription::dejaInscrit($etabId, null, ''),
    'un contrôle qui ne sait pas qui il regarde ne doit rien refuser');
t('autre candidat → ne bloque pas',
    !UniciteInscription::dejaInscrit($etabId, null, 'quelquun.dautre.lot10@example.test'));

// Dossier ouvert au guichet, sans compte rattaché : c'est le doublon le plus
// fréquent — le candidat s'inscrit ensuite en ligne avec le même e-mail.
$db->prepare('UPDATE dossiers_inscription SET eleve_id = NULL WHERE id = ?')->execute([$vivant]);
t('dossier guichet sans compte → reconnu par l’e-mail',
    UniciteInscription::dejaInscrit($etabId, $userId, $MAIL));
$db->prepare('UPDATE dossiers_inscription SET eleve_id = ? WHERE id = ?')->execute([$userId, $vivant]);

// ══ §C — Périmètre : les préinscriptions restent libres ════════════════════
echo "\n── Préinscription ≠ inscription (§C) ──\n";

$db->prepare('UPDATE dossiers_inscription SET statut = "annule" WHERE id = ?')->execute([$vivant]);

// Ni référence, ni statut brouillon, ni ligne dans `inscriptions` : c'est une
// préinscription au sens de Dossier::SQL_EST_INSCRIPTION.
$preins = $creer($etabId, $userId, 'soumis', null);
$ids[]  = $preins;
t('préinscription → ne bloque pas',
    !UniciteInscription::dejaInscrit($etabId, $userId, $MAIL),
    'un candidat garde le droit de préinscrire plusieurs filières');

// La même ligne devient une inscription dès qu'une référence est posée
// (SoumissionDossier) — la règle suit ce marqueur, pas un statut.
$db->prepare('UPDATE dossiers_inscription SET reference = ? WHERE id = ?')->execute([$ref('000002'), $preins]);
t('référence posée → devient une inscription bloquante',
    UniciteInscription::dejaInscrit($etabId, $userId, $MAIL));
$db->prepare('UPDATE dossiers_inscription SET reference = NULL WHERE id = ?')->execute([$preins]);

// Troisième marqueur : la ligne dans `inscriptions`, posée au guichet.
$db->prepare('INSERT INTO inscriptions (dossier_id, etablissement_id, date_inscription) VALUES (?, ?, NOW())')
   ->execute([$preins, $etabId]);
t('ligne d’inscription → bloque aussi',
    UniciteInscription::dejaInscrit($etabId, $userId, $MAIL),
    'un dossier saisi au guichet n’a ni référence ni brouillon');

// ══ §D — Bornes ════════════════════════════════════════════════════════════
echo "\n── Autre établissement, autre année, dossier exclu (§D) ──\n";

t('autre établissement → libre',
    $autre > 0 && !UniciteInscription::dejaInscrit($autre, $userId, $MAIL),
    'la règle est par établissement, pas globale');

$anneeSuivante = (string)((int)substr($annee, 0, 4) + 1) . '-' . ((int)substr($annee, 0, 4) + 2);
t('année suivante → libre',
    !UniciteInscription::dejaInscrit($etabId, $userId, $MAIL, $anneeSuivante),
    'la règle est par année académique : il faut pouvoir se réinscrire l’an prochain');

t('le dossier lui-même peut être exclu (modification)',
    !UniciteInscription::dejaInscrit($etabId, $userId, $MAIL, null, $preins));

t('établissement inconnu → ne bloque pas',
    !UniciteInscription::dejaInscrit(0, $userId, $MAIL));

// Le dossier le plus récent est retourné : c'est celui vers lequel renvoyer.
$second = $creer($etabId, $userId, 'soumis', $ref('000003'));
$ids[]  = $second;
$trouve = UniciteInscription::existante($etabId, $userId, $MAIL);
t('le plus récent est retourné', $trouve !== null && (int)$trouve['id'] === $second,
    $trouve === null ? 'aucun' : (string)$trouve['id']);

// ══ §E — Le guichet pose bien l'année académique ═══════════════════════════
echo "\n── Guichet : année académique renseignée (§E) ──\n";

$modele  = new Dossier();
$guichet = $modele->createGuichet([
    'nom_complet'    => 'TEST Lot10 Guichet',
    'email'          => $MAIL,
    'telephone'      => '06 00 00 01',
    'genre'          => 'homme',
    'date_naissance' => '2006-02-02',
    'lieu_naissance' => 'Port-Gentil',
    'nationalite'    => 'Gabonaise',
    'niveau_entree'  => 'Licence 1',
    'filiere_visee'  => 'Droit ZZ10',
], $etabId, $userId);
$ids[] = $guichet;

$dG = $modele->findById($guichet);
t('dossier guichet créé',            $dG !== null);
t('année académique renseignée',     $dG !== null && (string)$dG['annee_academique'] === $annee,
    $dG === null ? '' : (string)($dG['annee_academique'] ?? 'NULL'));

// ══ §F — Messages ══════════════════════════════════════════════════════════
echo "\n── Messages (§F) ──\n";

$existante = UniciteInscription::existante($etabId, $userId, $MAIL);
$msgC      = $existante !== null ? UniciteInscription::messageCandidat($existante) : '';
$msgG      = $existante !== null ? UniciteInscription::messageGuichet($existante) : '';

t('message candidat : nomme l’établissement',
    $existante !== null && str_contains($msgC, (string)$existante['etab_nom']));
t('message candidat : nomme l’année',
    str_contains($msgC, str_replace('-', ' — ', $annee)), $msgC);
t('message candidat : dit quoi faire',
    str_contains($msgC, 'espace candidat'));
t('message guichet : nomme le dossier',
    $existante !== null && str_contains($msgG, '#' . (int)$existante['id']), $msgG);
t('message guichet : porte le statut du dossier',
    $existante !== null && str_contains(
        $msgG,
        mb_strtolower(\Models\Orientation\DossierStatut::libelle($existante['statut'], true))
    ), $msgG);
t('message guichet : rappelle la règle',
    str_contains($msgG, 'seule inscription'));

// ══ Nettoyage ══════════════════════════════════════════════════════════════
echo "\n── Nettoyage ──\n";
$db->exec("DELETE i FROM inscriptions i
             JOIN dossiers_inscription d ON d.id = i.dossier_id
            WHERE d.email = " . $db->quote($MAIL));
$db->exec("DELETE FROM dossiers_inscription WHERE email = " . $db->quote($MAIL));
$restants = (int)$db->query(
    "SELECT COUNT(*) FROM dossiers_inscription WHERE email = " . $db->quote($MAIL)
)->fetchColumn();
t('nettoyé', $restants === 0, (string)$restants);

echo "\n══ {$ok} réussis · {$ko} échoués ══\n";
exit($ko === 0 ? 0 : 1);
