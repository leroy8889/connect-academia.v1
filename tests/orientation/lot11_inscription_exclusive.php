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
 * LOT 11 — Une inscription ABOUTIE ferme l'année, tous établissements confondus.
 *
 *   §A  Ce qui ferme l'année : valide_100 et son ancêtre `inscrit`
 *   §B  Ce qui ne la ferme pas : états non aboutis, morts, préinscriptions
 *   §C  Bornes : établissement visé exclu, autre année, identité inconnue
 *   §D  Messages : ils nomment l'établissement d'accueil
 *   §E  Session utilisateur : durée maximale de 2 heures
 *   §F  Chiffrement des pièces : enveloppe, scellé, compatibilité ascendante
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
use Core\Crypto;
use Core\Database;
use Core\Session;
use Services\Orientation\UniciteInscription;

$ok = 0; $ko = 0;
function t(string $n, bool $c, string $d = ''): void {
    global $ok, $ko;
    if ($c) { $ok++; echo "  ✓ $n\n"; } else { $ko++; echo "  ✗ $n $d\n"; }
}

$db     = Database::getInstance()->getConnection();
$etabA  = (int)$db->query('SELECT id FROM etablissements ORDER BY id LIMIT 1')->fetchColumn();
$etabB  = (int)$db->query("SELECT id FROM etablissements WHERE id <> {$etabA} ORDER BY id LIMIT 1")->fetchColumn();
$userId = (int)$db->query('SELECT id FROM users WHERE is_deleted = 0 ORDER BY id LIMIT 1')->fetchColumn();
$annee  = AnneeUniversitaire::code();
$MAIL   = 'test.lot11@example.test';

if ($etabB <= 0) {
    echo "\n⚠  Deux établissements sont nécessaires pour ce lot (un seul en base).\n";
    exit(1);
}

register_shutdown_function(static function () use ($db, $MAIL): void {
    $db->exec("DELETE i FROM inscriptions i
                 JOIN dossiers_inscription d ON d.id = i.dossier_id
                WHERE d.email = " . $db->quote($MAIL));
    $db->exec("DELETE FROM dossiers_inscription WHERE email = " . $db->quote($MAIL));
});

/** Même fabrique que le lot 10 : `reference` non nulle ⇒ INSCRIPTION. */
$creer = static function (
    int $etab, ?int $eleve, string $statut, ?string $reference, ?string $anneeDossier = null
) use ($db, $annee, $MAIL): int {
    $db->prepare(
        'INSERT INTO dossiers_inscription
           (etablissement_id, eleve_id, nom_complet, email, genre, date_naissance,
            lieu_naissance, nationalite, telephone, filiere_visee, niveau_entree,
            statut, progression, annee_academique, reference, soumis_le, date_creation)
         VALUES (?, ?, ?, ?, "femme", "2006-01-01", "Libreville", "Gabonaise",
                 "06 00 00 00", "Droit ZZ11", "Licence 1", ?, 0, ?, ?, NOW(), NOW())'
    )->execute([
        $etab, $eleve, 'TEST Lot11', $MAIL,
        $statut, $anneeDossier ?? $annee, $reference,
    ]);

    return (int)$db->lastInsertId();
};

$ref = static fn(string $s): string => sprintf('CA-%s-TL11-%s', date('Y'), $s);

// ══ §A — Une inscription à 100 % ferme l'année ═════════════════════════════
echo "\n── Une inscription aboutie ferme l’année (§A) ──\n";

t('table vide → l’autre établissement est libre',
    UniciteInscription::inscriptionFinaliseeAilleurs($etabB, $userId, $MAIL) === null);

$dossierA = $creer($etabA, $userId, 'valide_100', $ref('000001'));

$bloquant = UniciteInscription::inscriptionFinaliseeAilleurs($etabB, $userId, $MAIL);
t('inscrit à 100 % chez A → B est fermé',      $bloquant !== null);
t('c’est bien le dossier de A qui est rendu',  $bloquant !== null && (int)$bloquant['id'] === $dossierA);
t('la fiche de l’établissement A est jointe',  $bloquant !== null && ($bloquant['etab_nom'] ?? '') !== '');

$db->prepare('UPDATE dossiers_inscription SET statut = "inscrit" WHERE id = ?')->execute([$dossierA]);
t('statut hérité « inscrit » → ferme aussi',
    UniciteInscription::inscriptionFinaliseeAilleurs($etabB, $userId, $MAIL) !== null,
    'des dossiers de production portent encore cet ENUM');

// ══ §B — Ce qui NE ferme PAS l'année ═══════════════════════════════════════
echo "\n── Ce qui ne ferme pas l’année (§B) ──\n";

foreach (['brouillon', 'soumis', 'piece_manquante', 'valide_50'] as $statut) {
    $db->prepare('UPDATE dossiers_inscription SET statut = ? WHERE id = ?')->execute([$statut, $dossierA]);
    t("statut « {$statut} » → n’empêche pas de s’inscrire ailleurs",
        UniciteInscription::inscriptionFinaliseeAilleurs($etabB, $userId, $MAIL) === null,
        'seule une inscription MENÉE À TERME est exclusive');
}

foreach (['rejete', 'expire', 'annule'] as $statut) {
    $db->prepare('UPDATE dossiers_inscription SET statut = ? WHERE id = ?')->execute([$statut, $dossierA]);
    t("statut « {$statut} » → n’empêche pas de s’inscrire ailleurs",
        UniciteInscription::inscriptionFinaliseeAilleurs($etabB, $userId, $MAIL) === null);
}

// Préinscription : référence nulle et statut non brouillon (cf. Dossier::SQL_EST_INSCRIPTION).
$db->prepare('UPDATE dossiers_inscription SET statut = "valide_100", reference = NULL WHERE id = ?')
   ->execute([$dossierA]);
t('préinscription à 100 % → ne ferme rien',
    UniciteInscription::inscriptionFinaliseeAilleurs($etabB, $userId, $MAIL) === null,
    'se préinscrire dans plusieurs écoles reste le fonctionnement normal');

$db->prepare('UPDATE dossiers_inscription SET reference = ? WHERE id = ?')->execute([$ref('000001'), $dossierA]);

// ══ §C — Bornes ════════════════════════════════════════════════════════════
echo "\n── Bornes : établissement visé, année, identité (§C) ──\n";

t('l’établissement visé est EXCLU de la recherche',
    UniciteInscription::inscriptionFinaliseeAilleurs($etabA, $userId, $MAIL) === null,
    'le doublon interne relève de existante(), pas de cette règle');

t('existante() voit toujours le dossier dans son établissement',
    UniciteInscription::existante($etabA, $userId, $MAIL) !== null,
    'les deux contrôles sont complémentaires, pas concurrents');

$anneeSuivante = (string)((int)substr($annee, 0, 4) + 1) . '-' . ((int)substr($annee, 0, 4) + 2);
t('année suivante → libre',
    UniciteInscription::inscriptionFinaliseeAilleurs($etabB, $userId, $MAIL, $anneeSuivante) === null);

t('reconnu par le compte seul',  UniciteInscription::inscriptionFinaliseeAilleurs($etabB, $userId, null) !== null);
t('reconnu par l’e-mail seul',   UniciteInscription::inscriptionFinaliseeAilleurs($etabB, null, $MAIL) !== null);
t('e-mail insensible à la casse et aux espaces',
    UniciteInscription::inscriptionFinaliseeAilleurs($etabB, null, '  ' . strtoupper($MAIL) . ' ') !== null);
t('ni compte ni e-mail → ne bloque pas',
    UniciteInscription::inscriptionFinaliseeAilleurs($etabB, null, '') === null);
t('autre candidat → non concerné',
    UniciteInscription::inscriptionFinaliseeAilleurs($etabB, null, 'inconnu.lot11@example.test') === null);

// ══ §D — Messages ══════════════════════════════════════════════════════════
echo "\n── Messages (§D) ──\n";

$ailleurs = UniciteInscription::inscriptionFinaliseeAilleurs($etabB, $userId, $MAIL);
$msgC = $ailleurs !== null ? UniciteInscription::messageCandidatAutreEtablissement($ailleurs) : '';
$msgG = $ailleurs !== null ? UniciteInscription::messageGuichetAutreEtablissement($ailleurs) : '';

t('message candidat : nomme l’établissement d’accueil',
    $ailleurs !== null && str_contains($msgC, (string)$ailleurs['etab_nom']), $msgC);
t('message candidat : nomme l’année',
    str_contains($msgC, str_replace('-', ' — ', $annee)), $msgC);
t('message candidat : dit quoi faire',
    str_contains($msgC, 'annulation'), $msgC);
t('message guichet : nomme l’établissement d’accueil',
    $ailleurs !== null && str_contains($msgG, (string)$ailleurs['etab_nom']), $msgG);
t('message guichet : rappelle la règle',
    str_contains($msgG, 'tous établissements confondus'), $msgG);

// ══ §E — Durée maximale de la session utilisateur ══════════════════════════
echo "\n── Session utilisateur : 2 heures maximum (§E) ──\n";

$_SESSION = [];
t('durée maximale = 2 heures', Session::DUREE_MAX_UTILISATEUR === 7200);

t('aucune session → rien à expirer', Session::expirerUtilisateurSiBesoin() === false);

$_SESSION = ['user_id' => 42, 'user_name' => 'Test', 'login_at' => time() - 60];
t('session récente → conservée',
    Session::expirerUtilisateurSiBesoin() === false && !empty($_SESSION['user_id']));

$_SESSION = ['user_id' => 42, 'user_name' => 'Test', 'login_at' => time() - 7199];
t('1 h 59 min → encore valide', Session::expirerUtilisateurSiBesoin() === false);

$_SESSION = ['user_id' => 42, 'user_name' => 'Test', 'user_role' => 'eleve', 'login_at' => time() - 7201];
t('2 h 00 min 01 s → session fermée', Session::expirerUtilisateurSiBesoin() === true);
t('toutes les clés utilisateur sont retirées',
    empty($_SESSION['user_id']) && empty($_SESSION['user_name'])
    && empty($_SESSION['user_role']) && empty($_SESSION['login_at']));
// L'expiration ne s'annonce plus sur l'écran de connexion (choix produit) :
// on vérifie qu'aucun message résiduel n'est posé. Ce qui protège le poste
// partagé, c'est la fermeture de session ci-dessus, pas l'avis.
t('aucun message d’expiration n’est posé', empty($_SESSION['_flash']['session_expiree']));

// Cloisonnement : gestionnaire et admin ne doivent pas tomber avec le candidat.
$_SESSION = [
    'user_id' => 42, 'login_at' => time() - 8000,
    'gestionnaire_id' => 7, 'gestionnaire_etab_id' => 3,
    'admin_id' => 1, 'admin_2fa_ok' => true,
];
Session::expirerUtilisateurSiBesoin();
t('session gestionnaire intacte',
    !empty($_SESSION['gestionnaire_id']) && !empty($_SESSION['gestionnaire_etab_id']));
t('session admin intacte', !empty($_SESSION['admin_id']) && !empty($_SESSION['admin_2fa_ok']));

// Repère absent (session ouverte avant le déploiement) : on horodate, on ne déconnecte pas.
$_SESSION = ['user_id' => 42];
t('session sans repère → horodatée, pas fermée',
    Session::expirerUtilisateurSiBesoin() === false && !empty($_SESSION['login_at']));

// Repère dans le futur : jamais éternel.
$_SESSION = ['user_id' => 42, 'login_at' => time() + 999999];
Session::expirerUtilisateurSiBesoin();
t('repère dans le futur → ramené à maintenant',
    (int)$_SESSION['login_at'] <= time());

$_SESSION = ['user_id' => 42, 'login_at' => time() - 3600];
t('décompte restant cohérent',
    abs(Session::secondesRestantesUtilisateur() - 3600) <= 2);

// ══ §F — Chiffrement des pièces ════════════════════════════════════════════
echo "\n── Chiffrement des pièces justificatives (§F) ──\n";

t('AES-256-GCM disponible', Crypto::disponible());

$piece = random_bytes(4096);
$cle   = 'dossiers/1234/pieces/acte_naissance-abcdef.pdf';
$env   = Crypto::chiffrer($piece, $cle);

t('l’enveloppe est reconnaissable',        Crypto::estChiffre($env));
t('le clair n’apparaît pas dans le stockage', !str_contains($env, $piece));
t('aller-retour fidèle',                   Crypto::dechiffrer($env, $cle) === $piece);

$rejete = false;
try { Crypto::dechiffrer($env, 'dossiers/9999/pieces/acte_naissance-abcdef.pdf'); }
catch (\Throwable) { $rejete = true; }
t('pièce déplacée dans le bucket → illisible', $rejete,
    'le contenu est scellé sur sa clé de stockage');

$altere = $env;
$altere[strlen($altere) - 3] = chr(ord($altere[strlen($altere) - 3]) ^ 0x01);
$rejete = false;
try { Crypto::dechiffrer($altere, $cle); } catch (\Throwable) { $rejete = true; }
t('octet modifié → refusé, pas rendu en bouillie', $rejete);

$ancien = "%PDF-1.4 pièce déposée avant le chiffrement";
t('fichier historique en clair → toujours lisible',
    Crypto::dechiffrerSiBesoin($ancien, $cle) === $ancien,
    'aucune migration nécessaire, aucune pièce perdue');

t('deux chiffrements du même contenu diffèrent',
    Crypto::chiffrer('x', $cle) !== Crypto::chiffrer('x', $cle),
    'vecteur d’initialisation aléatoire');

// ══ Nettoyage ══════════════════════════════════════════════════════════════
echo "\n── Nettoyage ──\n";
$db->exec("DELETE i FROM inscriptions i
             JOIN dossiers_inscription d ON d.id = i.dossier_id
            WHERE d.email = " . $db->quote($MAIL));
$db->exec("DELETE FROM dossiers_inscription WHERE email = " . $db->quote($MAIL));
t('nettoyé',
    (int)$db->query("SELECT COUNT(*) FROM dossiers_inscription WHERE email = " . $db->quote($MAIL))->fetchColumn() === 0);

echo "\n══ {$ok} réussis · {$ko} échoués ══\n";
exit($ko === 0 ? 0 : 1);
