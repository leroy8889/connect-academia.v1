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
 * LOT 12 — Bornes de saisie et cohérence avec le schéma.
 *
 *   §A  CandidatureController::validate() — longueurs, accents, priorités
 *   §B  Les bornes déclarées ne dépassent JAMAIS la colonne qui les reçoit
 *   §C  ContactController — mêmes bornes, mêmes garanties
 *   §D  Routage HEAD — une sonde de supervision ne doit pas voir un 404
 *
 * ── Pourquoi ce lot existe ─────────────────────────────────────────────────
 * MySQL tourne en `STRICT_TRANS_TABLES` : une chaîne trop longue n'est pas
 * tronquée, l'INSERT lève « Data too long ». Côté préinscription le try/catch
 * rattrapait l'exception et rendait « Une erreur est survenue » sans nommer le
 * champ : le candidat resoumettait la même valeur sans fin et ne pouvait plus
 * s'inscrire. Côté formulaire de contact, rien ne rattrapait : page 500.
 *
 * Le vrai piège est la DÉRIVE : le jour où quelqu'un rétrécit une colonne, la
 * borne PHP devient trop permissive et le bug revient, silencieux. §B lit donc
 * les largeurs réelles dans INFORMATION_SCHEMA et les compare aux constantes.
 *
 * Ce lot n'écrit rien en base. Il est rejouable à l'infini.
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

use Controllers\ContactController;
use Controllers\Orientation\CandidatureController;
use Core\Database;

$ok = 0; $ko = 0;
function t(string $n, bool $c, string $d = ''): void {
    global $ok, $ko;
    if ($c) { $ok++; echo "  ✓ $n\n"; } else { $ko++; echo "  ✗ $n $d\n"; }
}

/** Lit une constante privée sans instancier le contrôleur. */
function constante(string $classe, string $nom): mixed {
    return (new ReflectionClass($classe))->getConstant($nom);
}

$db = Database::getInstance()->getConnection();

/**
 * Adresse e-mail SYNTAXIQUEMENT VALIDE d'au moins $longueur caractères.
 *
 * Une simple répétition de « X » n'est pas une adresse : `filter_var` la
 * rejette pour son format et la borne de longueur n'est jamais atteinte. Or la
 * RFC limite la partie locale à 64 caractères et chaque label de domaine à 63 —
 * il faut donc enchaîner plusieurs labels pour dépasser 150 sans sortir du
 * format. C'est la seule façon d'éprouver la borne elle-même.
 */
function emailValideDe(int $longueur): string {
    $adresse = str_repeat('a', 64) . '@';
    // On ajoute des labels de domaine tant que le résultat FINAL — suffixe
    // « test » compris — reste plus court que demandé.
    while (mb_strlen($adresse . 'test') < $longueur) {
        $adresse .= str_repeat('b', 63) . '.';
    }
    return $adresse . 'test';
}

/** Largeur réelle d'une colonne varchar, en caractères. */
function largeur(PDO $db, string $table, string $colonne): ?int {
    $st = $db->prepare(
        'SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $st->execute([$table, $colonne]);
    $v = $st->fetchColumn();
    return $v === false || $v === null ? null : (int)$v;
}

// ══ §A — validate() ════════════════════════════════════════════════════════
echo "\n── Bornes de la préinscription (§A) ──\n";

$ref = new ReflectionMethod(CandidatureController::class, 'validate');
$ref->setAccessible(true);
$ctrl  = (new ReflectionClass(CandidatureController::class))->newInstanceWithoutConstructor();
$appel = static fn(array $f, array $r): array => $ref->invoke($ctrl, $f, $r);

$regles = [
    'nom_complet' => true, 'genre' => true, 'date_naissance' => true,
    'lieu_naissance' => true, 'nationalite' => true, 'email' => true,
    'telephone' => true, 'pays_provenance' => true, 'ville' => true,
    'adresse' => true, 'filiere_visee' => true, 'niveau_entree' => true,
    'dernier_etablissement' => true, 'serie_bac' => true, 'annee_bac' => true,
];
$normal = [
    'nom_complet' => 'Ndong Mba Jean-Baptiste', 'genre' => 'homme',
    'date_naissance' => '2000-01-15', 'lieu_naissance' => 'Libreville',
    'nationalite' => 'Gabonaise', 'email' => 'jb.ndong@example.test',
    'indicatif_tel' => '+241', 'telephone' => '060000000',
    'pays_provenance' => 'Gabon', 'ville' => 'Libreville',
    'adresse' => 'Quartier Louis, rue 12', 'filiere_visee' => 'Droit',
    'specialite_visee' => 'Conseil juridique', 'niveau_entree' => 'L1',
    'dernier_etablissement' => 'Lycée National Léon Mba', 'serie_bac' => 'D',
    'annee_bac' => '2024',
];

t('une saisie normale ne produit aucune erreur', $appel($normal, $regles) === [],
    json_encode($appel($normal, $regles), JSON_UNESCAPED_UNICODE));

// 200 lettres accentuées = 400 octets : `strlen` les refuserait à tort.
t('200 lettres accentuées acceptées',
    !isset($appel(['nom_complet' => str_repeat('é', 200)] + $normal, $regles)['nom_complet']),
    'la borne doit compter des caractères, pas des octets');
t('201 lettres accentuées refusées',
    isset($appel(['nom_complet' => str_repeat('é', 201)] + $normal, $regles)['nom_complet']));

t('un champ vide reste « requis », pas « trop long »',
    ($appel(['nom_complet' => ''] + $normal, $regles)['nom_complet'] ?? '') === 'Ce champ est requis.');

// `indicatif_tel` n'est déclaré dans aucune règle : il partait pourtant
// directement en base, et un POST forgé suffisait à déborder la colonne.
t('un champ hors règles est borné quand même',
    isset($appel(['indicatif_tel' => '+2410000000'] + $normal, $regles)['indicatif_tel']));

// ══ §B — Les bornes collent au schéma ══════════════════════════════════════
echo "\n── Bornes ≤ colonnes réelles (§B) ──\n";

/** @var array<string,array{int,string}> $longueurs */
$longueurs = constante(CandidatureController::class, 'LONGUEURS');
t('la table des longueurs est déclarée', is_array($longueurs) && $longueurs !== []);

foreach ($longueurs as $champ => [$max, $libelle]) {
    $reelle = largeur($db, 'dossiers_inscription', $champ);
    t(sprintf('%-22s borné à %-4d ≤ colonne %s', $champ, $max, $reelle ?? 'absente'),
        $reelle !== null && $max <= $reelle,
        $reelle === null ? 'colonne introuvable' : "colonne={$reelle}");

    // Le message rendu doit nommer la limite, sinon le candidat ne sait pas
    // quoi corriger — c'est tout l'objet du correctif.
    //
    // Cas de l'e-mail : une répétition de « X » n'est pas une adresse, et
    // c'est alors le contrôle de format qui répond — à juste titre. Pour
    // éprouver la BORNE, il faut une adresse trop longue mais bien formée.
    $tropLong = $champ === 'email' ? emailValideDe($max + 1) : str_repeat('X', $max + 1);
    $e = $appel([$champ => $tropLong] + $normal, $regles);
    t(sprintf('%-22s : le message nomme la limite', $champ),
        isset($e[$champ]) && str_contains($e[$champ], (string)$max),
        json_encode($e[$champ] ?? null, JSON_UNESCAPED_UNICODE));
}

// ══ §C — Formulaire de contact ═════════════════════════════════════════════
echo "\n── Formulaire de contact (§C) ──\n";

/** @var array<string,int> $contact */
$contact = constante(ContactController::class, 'LONGUEURS');
t('la table des longueurs de contact est déclarée', is_array($contact) && $contact !== []);

foreach ($contact as $champ => $max) {
    $reelle = largeur($db, 'contact_messages', $champ);
    if ($reelle === null) {
        // `message` est un TEXT : pas de CHARACTER_MAXIMUM_LENGTH en varchar,
        // mais 65 535 OCTETS. Une borne en caractères doit rester bien en deçà,
        // un caractère utf8mb4 pouvant peser quatre octets.
        t(sprintf('%-10s : borne compatible avec un TEXT', $champ), $max * 4 <= 65535,
            "borne={$max} caractères, soit " . ($max * 4) . ' octets au pire');
        continue;
    }
    t(sprintf('%-10s borné à %-4d ≤ colonne %d', $champ, $max, $reelle), $max <= $reelle);
}

t('la limite de débit est déclarée',
    is_int(constante(ContactController::class, 'ENVOIS_MAX'))
    && constante(ContactController::class, 'ENVOIS_MAX') > 0);
t('la fenêtre de limitation est déclarée',
    is_int(constante(ContactController::class, 'FENETRE_SECONDES'))
    && constante(ContactController::class, 'FENETRE_SECONDES') >= 60);

// ══ §D — HEAD est un GET sans corps ════════════════════════════════════════
echo "\n── Routage HEAD (§D) ──\n";

// Aucune route n'est déclarée en HEAD : sans traduction vers GET, toutes les
// pages répondaient 404 et les sondes de supervision voyaient le site mort.
$source = file_get_contents(BASE_PATH . '/app/Core/Router.php');
t('le routeur traduit HEAD en GET',
    str_contains($source, "=== 'HEAD' ? 'GET'"),
    'RFC 9110 §9.3.2 : HEAD répond comme GET, sans corps');
t('la comparaison de route utilise la méthode traduite',
    str_contains($source, "\$route['method'] === \$chercher"),
    'sinon la traduction est calculée mais jamais utilisée');

// ══ §E — Jeton de vérification : pose concurrente ══════════════════════════
echo "\n── Jeton QR posé sous concurrence (§E) ──\n";

// `tokenVerification()` lit le jeton, et n'en pose un que s'il manque. Entre
// la lecture et l'écriture il y a une fenêtre : un candidat qui double-clique
// sur « télécharger » lance deux rendus, les deux voient un jeton vide, les
// deux en tirent un — et le second écrasait le premier. Le document déjà
// imprimé portait alors un QR que /verifier ne reconnaissait plus.
$recepisse = $db->query("SELECT id, dossier_id, token_verification FROM recepisses
                          WHERE dossier_id IS NOT NULL ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

if (!$recepisse) {
    echo "  (aucun récépissé en base — section ignorée)\n";
} else {
    $rid     = (int)$recepisse['id'];
    $did     = (int)$recepisse['dossier_id'];
    $origine = $recepisse['token_verification'];

    register_shutdown_function(static function () use ($db, $rid, $origine): void {
        $db->prepare('UPDATE recepisses SET token_verification = ? WHERE id = ?')
           ->execute([$origine, $rid]);
    });

    $modele = new \Models\Orientation\Dossier();

    $db->prepare('UPDATE recepisses SET token_verification = NULL WHERE id = ?')->execute([$rid]);
    $pose = $modele->tokenVerificationRecepisse($did);
    t('un jeton de 64 hex est posé', is_string($pose) && (bool)preg_match('/^[0-9a-f]{64}$/', $pose));
    t('le jeton rendu est bien celui de la base',
        $pose === $db->query("SELECT token_verification FROM recepisses WHERE id = {$rid}")->fetchColumn());
    t('deux appels rendent le même jeton', $modele->tokenVerificationRecepisse($did) === $pose);

    // On rejoue la course : un concurrent écrit DANS la fenêtre.
    $db->prepare('UPDATE recepisses SET token_verification = NULL WHERE id = ?')->execute([$rid]);
    $gagnant = bin2hex(random_bytes(32));

    $st = $db->prepare('SELECT id, token_verification FROM recepisses WHERE dossier_id = ? LIMIT 1');
    $st->execute([$did]);
    $lu = $st->fetch(PDO::FETCH_ASSOC);            // ← lecture du perdant
    $db->prepare('UPDATE recepisses SET token_verification = ? WHERE id = ?')
       ->execute([$gagnant, $rid]);                 // ← le concurrent gagne
    $perdu = bin2hex(random_bytes(32));
    $db->prepare("UPDATE recepisses SET token_verification = ?
                   WHERE id = ? AND (token_verification IS NULL OR token_verification = '')")
       ->execute([$perdu, $rid]);                   // ← écriture conditionnelle
    $relu = $db->query("SELECT token_verification FROM recepisses WHERE id = {$rid}")->fetchColumn();

    t('le jeton du gagnant n’est pas écrasé', $relu === $gagnant, (string)$relu);
    t('le perdant relit le jeton retenu', $relu !== $perdu,
        'sinon le QR imprimé ne serait plus reconnu par /verifier');

    // Le garde-fou doit être dans le code, pas seulement dans ce test.
    $modeleSrc = file_get_contents(BASE_PATH . '/app/Models/Orientation/Dossier.php');
    t('l’écriture du jeton est conditionnelle dans le modèle',
        str_contains($modeleSrc, "WHERE id = ? AND (token_verification IS NULL OR token_verification = '')"));
    t('le modèle relit le jeton retenu',
        str_contains($modeleSrc, '$retenu !== \'\' ? $retenu : $token'));
}

// ══ §F — Validator : des CARACTÈRES, pas des octets ════════════════════════
echo "\n── Validator : longueurs en caractères (§F) ──\n";

// `strlen` compte les octets. En UTF-8 « éàüñ » en pèse huit : le mot de passe
// satisfaisait `min:8` en ne comptant que QUATRE caractères — la longueur
// minimale des mots de passe se contournait en accentuant. Symétriquement,
// `max:100` refusait un nom de soixante lettres accentuées, ce qui bloquait
// des inscriptions parfaitement légitimes sur un public francophone.
$v = new \Core\Validator();

foreach (['éàüñ' => 4, '密码密码' => 4, 'aç' => 2] as $court => $taille) {
    t(sprintf('« %s » (%d caractères) refusé par min:8', $court, $taille),
        !$v->validate(['password' => $court], ['password' => 'required|min:8']),
        'compté en octets, ce mot de passe passait la longueur minimale');
}
t('un mot de passe de 10 caractères reste accepté',
    $v->validate(['password' => 'motdepasse'], ['password' => 'required|min:8']));

foreach ([60, 100] as $n) {
    t(sprintf('nom de %d lettres accentuées accepté par max:100', $n),
        $v->validate(['nom' => str_repeat('é', $n)], ['nom' => 'required|max:100']),
        'compté en octets, il était refusé à tort');
}
t('nom de 101 lettres accentuées refusé',
    !$v->validate(['nom' => str_repeat('é', 101)], ['nom' => 'required|max:100']));

// Garde-fou de source, en complément des tests de comportement ci-dessus.
// Attention : « mb_strlen(...) » CONTIENT « strlen(...) » — chercher l'absence
// de la seconde chaîne est toujours faux. On teste donc l'appel NU, précédé de
// son opérateur, seule forme qui distingue les deux.
$vSrc = file_get_contents(BASE_PATH . '/app/Core/Validator.php');
t('ruleMin et ruleMax comptent en mb_strlen',
    substr_count($vSrc, 'mb_strlen((string) $value)') === 2
    && !str_contains($vSrc, '&& strlen((string) $value)'));

// ══ §G — Publication : bornes et garde JSON ════════════════════════════════
echo "\n── Publication communauté (§G) ──\n";

$pcSrc = file_get_contents(BASE_PATH . '/app/Controllers/Communaute/PostController.php');

// Le garde « post_max_size dépassé » se lit sur $_POST vide. Or PHP n'alimente
// JAMAIS $_POST pour un corps JSON : appliqué sans distinction, il répondait
// « Requête trop volumineuse » à toute requête JSON, quelle que soit sa taille,
// et la branche de lecture JSON juste en dessous ne s'exécutait jamais.
t('le garde post_max_size épargne les corps JSON',
    str_contains($pcSrc, '!$estJson') && str_contains($pcSrc, "\$estJson     = str_contains(\$contentType, 'application/json')"),
    'sinon la branche JSON du contrôleur est inatteignable');

t('matiere_tag est borné à la colonne',
    str_contains($pcSrc, "'matiere_tag' => 'max:50'"));
t('contenu est borné', str_contains($pcSrc, "'contenu'     => 'required|min:1|max:16000'"));

// `hashtags` est dérivé du contenu : le refuser reviendrait à rejeter un
// message pour un champ que l'auteur n'a pas saisi. On coupe sur une virgule.
t('hashtags est coupé, pas refusé', str_contains($pcSrc, 'HASHTAGS_MAX'));
$maxHashtags = (new ReflectionClass(\Controllers\Communaute\PostController::class))->getConstant('HASHTAGS_MAX');
t('la coupe des hashtags tient dans la colonne',
    is_int($maxHashtags) && $maxHashtags <= (largeur($db, 'posts', 'hashtags') ?? 0),
    'borne=' . var_export($maxHashtags, true) . ' colonne=' . var_export(largeur($db, 'posts', 'hashtags'), true));

// La coupe ne doit jamais laisser un tag tronqué en plein milieu.
$tags  = implode(',', array_map(static fn(int $i): string => sprintf('tag%03d', $i), range(0, 119)));
$coupe = mb_substr($tags, 0, $maxHashtags);
$virg  = mb_strrpos($coupe, ',');
$final = $virg !== false ? mb_substr($coupe, 0, $virg) : $coupe;
t('aucun hashtag tronqué en plein milieu',
    $final !== '' && !str_ends_with($final, ',')
    && (bool)preg_match('/^(tag\d{3})(,tag\d{3})*$/', $final),
    substr($final, -24));
t('la coupe tient dans la colonne', mb_strlen($final) <= $maxHashtags);

// ══ §H — Plafond de concurrence sur la dépendance lente ════════════════════
echo "\n── Plafond d'appels simultanés (§H) ──\n";

// Un appel à l'assistant vaut jusqu'à 3 tentatives de 60 s séparées de deux
// pauses de 2 s : 184 s de worker PHP-FPM immobilisé au pire. Le pool en compte
// 80. Sans plafond, 80 élèves posant une question pendant un ralentissement de
// l'API consomment TOUT le pool — et c'est le site entier qui tombe, tunnel
// d'inscription compris. La limite quotidienne borne les appels par élève et
// par jour, jamais leur simultanéité.
$porte = 'test:lot12:' . bin2hex(random_bytes(4));

$accordees = 0;
for ($i = 1; $i <= 5; $i++) {
    if (\Core\Concurrence::acquerir($porte, 3, 60)) { $accordees++; }
}
t('3 places accordées sur 5 demandes', $accordees === 3, "accordées={$accordees}");
t('le compteur reflète les places tenues', \Core\Concurrence::occupees($porte) === 3);

// Un refus ne doit pas consommer de place, sinon le plafond se referme tout
// seul à la première rafale et la fonctionnalité ne rouvre jamais.
\Core\Concurrence::acquerir($porte, 3, 60);
t('un refus ne consomme pas de place', \Core\Concurrence::occupees($porte) === 3,
    (string)\Core\Concurrence::occupees($porte));

\Core\Concurrence::liberer($porte);
t('une place rendue rouvre une entrée', \Core\Concurrence::occupees($porte) === 2
    && \Core\Concurrence::acquerir($porte, 3, 60));

// Le TTL de sûreté peut remettre le compteur à zéro pendant qu'un appel est
// encore en vol : la libération qui suit ne doit pas le rendre NÉGATIF, sans
// quoi le plafond laisserait tout passer.
for ($i = 0; $i < 10; $i++) { \Core\Concurrence::liberer($porte); }
t('le compteur ne descend jamais sous zéro', \Core\Concurrence::occupees($porte) === 0);
t('le plafond reste opérant après sur-libération',
    \Core\Concurrence::acquerir($porte, 1, 60) && !\Core\Concurrence::acquerir($porte, 1, 60),
    'un compteur négatif aurait laissé passer tout le monde');
\Core\Concurrence::liberer($porte);

// La place doit être rendue même quand la requête sort par exit() :
// Response::json() se termine ainsi, et un bloc `finally` ne s'exécute PAS sur
// exit. C'est register_shutdown_function qui s'en charge.
$porte2 = 'test:lot12:' . bin2hex(random_bytes(4));
$fils   = sys_get_temp_dir() . '/lot12_fils_' . bin2hex(random_bytes(4)) . '.php';
file_put_contents($fils, "<?php\nrequire getenv('BOOT_LOT12');\n"
    . "\\Core\\Concurrence::acquerir(getenv('PORTE_LOT12'), 2, 60);\nexit(0);\n");
$amorce = sys_get_temp_dir() . '/lot12_amorce_' . bin2hex(random_bytes(4)) . '.php';
file_put_contents($amorce,
    "<?php\ndefine('BASE_PATH', " . var_export(BASE_PATH, true) . ");\n"
    . "define('BASE_URL', '');\nrequire BASE_PATH . '/vendor/autoload.php';\n"
    . "foreach (file(BASE_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as \$l) {\n"
    . "    \$l = trim(\$l); if (\$l === '' || \$l[0] === '#' || !str_contains(\$l, '=')) continue;\n"
    . "    [\$k, \$v] = explode('=', \$l, 2); \$_ENV[trim(\$k)] = trim(trim(\$v), '\"\\'');\n}\n"
    . "spl_autoload_register(function (string \$c): void {\n"
    . "    \$f = BASE_PATH . '/app/' . str_replace('\\\\', '/', \$c) . '.php';\n"
    . "    if (file_exists(\$f)) require_once \$f;\n});\n");
exec(sprintf('BOOT_LOT12=%s PORTE_LOT12=%s %s %s 2>&1',
    escapeshellarg($amorce), escapeshellarg($porte2),
    escapeshellarg(PHP_BINARY), escapeshellarg($fils)));
t('la place est rendue même quand le process sort par exit()',
    \Core\Concurrence::occupees($porte2) === 0,
    \Core\Concurrence::occupees($porte2) . ' place(s) fuitée(s) — un finally ne suffit pas sur exit');
@unlink($fils); @unlink($amorce);

// Le garde doit être branché sur l'endpoint, pas seulement exister.
$iaSrc = file_get_contents(BASE_PATH . '/app/Controllers/Apprentissage/IaController.php');
t('l’assistant réserve une place avant d’appeler l’API',
    str_contains($iaSrc, 'Concurrence::acquerir(self::PORTE'));
t('le plafond laisse de la marge au reste du pool',
    (int)(new ReflectionClass(\Controllers\Apprentissage\IaController::class))
        ->getConstant('APPELS_SIMULTANES_MAX') <= 20);

// ══ §I — Contraintes d'unicité dont le CODE dépend ═════════════════════════
echo "\n── Unicité en base : le garde anti-doublon (§I) ──\n";

// Six chemins du code attrapent une PDOException « 23000 » et la traitent comme
// un doublon bénin — c'est ainsi qu'ils survivent à deux requêtes simultanées.
// Ce garde ne vaut QUE si l'index unique existe réellement. S'il manque — une
// migration oubliée en production, le piège récurrent de ce projet — l'INSERT
// passe deux fois sans lever la moindre erreur, et le code croit avoir été
// protégé. Pour `commissions_scolarite`, cela signifie facturer DEUX FOIS un
// établissement pour un seul dossier, en silence.
$unicites = [
    ['commissions_scolarite',   ['dossier_id'],              'double facturation d’un établissement'],
    ['commission_tranches',     ['commission_id', 'numero'], 'tranche de recouvrement en double'],
    ['preinscriptions',         ['dossier_id'],              'acte de préinscription dupliqué dans la frise'],
    ['attestations_inscription',['dossier_id'],              'deux attestations pour un même dossier'],
    ['attestations_inscription',['numero_attestation'],      'deux attestations au même numéro officiel'],
    ['attestations_inscription',['token_verification'],      'deux documents au même jeton de vérification'],
    ['recepisses',              ['dossier_id'],              'deux récépissés pour un même dossier'],
    ['recepisses',              ['numero_recepisse'],        'deux récépissés au même numéro officiel'],
    ['recepisses',              ['token_verification'],      'deux documents au même jeton de vérification'],
    ['recus_orientation',       ['paiement_id'],             'deux reçus pour un même paiement'],
    ['recus_orientation',       ['numero_recu'],             'deux reçus au même numéro'],
    ['jetons_verification',     ['token'],                   'collision de jeton de vérification'],
    ['jetons_verification',     ['code_court'],              'collision de code court'],
    ['inscriptions',            ['dossier_id'],              'inscription enregistrée deux fois'],
    ['etablissements',          ['slug'],                    'deux établissements à la même URL'],
    ['etablissements',          ['code_court'],              'collision de code établissement'],
];

/** Index UNIQUE portant EXACTEMENT ces colonnes, dans cet ordre. */
$aUnicite = static function (PDO $db, string $table, array $colonnes): bool {
    try {
        $index = [];
        foreach ($db->query("SHOW INDEX FROM `{$table}`") as $r) {
            if ((int)$r['Non_unique'] === 0) {
                $index[$r['Key_name']][(int)$r['Seq_in_index']] = $r['Column_name'];
            }
        }
        foreach ($index as $cols) {
            ksort($cols);
            if (array_values($cols) === $colonnes) { return true; }
        }
    } catch (Throwable) {
        return false;
    }

    return false;
};

foreach ($unicites as [$table, $colonnes, $consequence]) {
    t(sprintf('%-26s UNIQUE (%s)', $table, implode(', ', $colonnes)),
        $aUnicite($db, $table, $colonnes),
        'index absent → ' . $consequence);
}

// Et l'invariant se vérifie aussi sur les données : un doublon déjà présent
// signifierait que la contrainte a été ajoutée trop tard, ou jamais.
$doublons = $db->query('SELECT COUNT(*) FROM (
                            SELECT dossier_id FROM commissions_scolarite
                             GROUP BY dossier_id HAVING COUNT(*) > 1
                        ) d')->fetchColumn();
t('aucun dossier ne porte deux commissions', (int)$doublons === 0,
    $doublons . ' dossier(s) doublement facturé(s)');

// ══ §J — Encaissement d'une tranche : compare-and-swap ═════════════════════
echo "\n── Encaissement concurrent d'une tranche (§J) ──\n";

// `payerTranche()` lit la tranche, contrôle son état, puis écrit. Entre les
// deux, un agent qui double-clique lançait une seconde requête : les deux
// lisaient `est_payee = 0`, les deux franchissaient le garde « déjà dans cet
// état », et la seconde ÉCRASAIT le validateur et la référence de règlement de
// la première — sur une écriture financière, dont la traçabilité est ce qui la
// rend opposable — en ajoutant une entrée de journal pour un seul encaissement.
$cdSrc = file_get_contents(BASE_PATH . '/app/Services/Orientation/CommissionDossier.php');
t('l’écriture est conditionnée à l’état lu',
    str_contains($cdSrc, 'WHERE id = :id AND est_payee = :attendu'));
t('une écriture sans effet est signalée, pas journalisée',
    str_contains($cdSrc, '$maj->rowCount() === 0'));

// Vérification par le comportement de la base, pas seulement par le source.
$tr = $db->query("SELECT t.id FROM commission_tranches t
                    JOIN commissions_scolarite c ON c.id = t.commission_id
                   WHERE t.est_payee = 0 AND t.est_abandonnee = 0 AND c.statut <> 'annulee'
                   ORDER BY t.id DESC LIMIT 1")->fetchColumn();
if ($tr === false) {
    echo "  (aucune tranche encaissable — vérification en base ignorée)\n";
} else {
    $tid   = (int)$tr;
    $avant = $db->query("SELECT est_payee, date_paiement, valide_par, reference_reglement
                           FROM commission_tranches WHERE id = {$tid}")->fetch(PDO::FETCH_ASSOC);
    register_shutdown_function(static function () use ($db, $tid, $avant): void {
        $db->prepare('UPDATE commission_tranches
                         SET est_payee = ?, date_paiement = ?, valide_par = ?, reference_reglement = ?
                       WHERE id = ?')
           ->execute([$avant['est_payee'], $avant['date_paiement'],
                      $avant['valide_par'], $avant['reference_reglement'], $tid]);
    });

    $cas = 'UPDATE commission_tranches
               SET est_payee = 1, date_paiement = NOW(), valide_par = ?, reference_reglement = ?
             WHERE id = ? AND est_payee = 0';

    $gagnant = $db->prepare($cas);
    $gagnant->execute([9, 'REF-GAGNANT', $tid]);
    t('le premier encaissement écrit', $gagnant->rowCount() === 1);

    $perdant = $db->prepare($cas);
    $perdant->execute([8, 'REF-PERDANT', $tid]);
    t('le second n’écrase rien', $perdant->rowCount() === 0,
        $perdant->rowCount() . ' ligne(s) écrasée(s) — validateur et référence perdus');

    $fin = $db->query("SELECT valide_par, reference_reglement
                         FROM commission_tranches WHERE id = {$tid}")->fetch(PDO::FETCH_ASSOC);
    t('la référence de règlement retenue est intacte',
        $fin['reference_reglement'] === 'REF-GAGNANT', (string)$fin['reference_reglement']);
    t('le validateur retenu est intact', (int)$fin['valide_par'] === 9, (string)$fin['valide_par']);
}

// ══ §K — Reversement : jamais payer deux fois ══════════════════════════════
echo "\n── Rejeu d'un reversement (§K) ──\n";

// Relancer un payout envoie de l'argent RÉEL. `rejouer()` lisait le statut,
// vérifiait qu'il n'était pas déjà réussi, puis appelait l'API : entre les deux,
// un double-clic de l'agent suffisait pour que les DEUX requêtes franchissent le
// contrôle et déclenchent chacune un virement. L'établissement était payé deux
// fois, sans qu'aucune trace ne signale l'anomalie.
$rsSrc = file_get_contents(BASE_PATH . '/app/Services/Orientation/ReversementService.php');
t('la réservation précède l’appel au payout',
    str_contains($rsSrc, 'reserverPourRejeu($reversementId, self::MAX_TENTATIVES)')
    && strpos($rsSrc, 'reserverPourRejeu') < strpos($rsSrc, '$ok = self::appelPayoutMF'),
    'réserver APRÈS l’appel ne protégerait rien');

$dossier = $db->query('SELECT id, etablissement_id FROM dossiers_inscription
                        ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
if (!$dossier) {
    echo "  (aucun dossier — vérification en base ignorée)\n";
} else {
    // `reversements.paiement_id` porte une clé étrangère : on fabrique un
    // paiement de test, retiré au shutdown (le reversement suit en cascade).
    $db->prepare('INSERT INTO paiements_orientation
                    (dossier_id, etablissement_id, montant, mode, statut, date_paiement)
                  VALUES (?, ?, 100000, "espece", "reussi", NOW())')
       ->execute([(int)$dossier['id'], (int)$dossier['etablissement_id']]);
    $pid = (int)$db->lastInsertId();
    register_shutdown_function(static function () use ($db, $pid): void {
        $db->exec("DELETE FROM paiements_orientation WHERE id = {$pid}");
    });

    $db->prepare("INSERT INTO reversements
        (paiement_id, etablissement_id, montant_brut, commission_plateforme, montant_reverse,
         numero_destination, withdraw_mode, statut, tentatives, date_initiation)
        VALUES (?, ?, 100000, 5000, 95000, '074000000', 'airtel-ga', 'echoue', 0, NOW())")
       ->execute([$pid, (int)$dossier['etablissement_id']]);
    $rid = (int)$db->lastInsertId();

    $rev = new \Models\Orientation\Reversement();
    $MAX = 5;

    t('la première réservation est accordée', $rev->reserverPourRejeu($rid, $MAX));
    $etat = $db->query("SELECT statut, tentatives FROM reversements WHERE id={$rid}")->fetch(PDO::FETCH_ASSOC);
    t('le statut passe à en_cours AVANT l’appel API', $etat['statut'] === 'en_cours', (string)$etat['statut']);
    t('la tentative est comptée', (int)$etat['tentatives'] === 1);

    t('le double-clic est refusé', !$rev->reserverPourRejeu($rid, $MAX),
        'sinon l’établissement serait payé deux fois');
    t('un refus n’incrémente pas les tentatives',
        (int)$db->query("SELECT tentatives FROM reversements WHERE id={$rid}")->fetchColumn() === 1);

    $db->exec("UPDATE reversements SET statut='reussi', tentatives=0 WHERE id={$rid}");
    t('un reversement réussi n’est plus rejouable', !$rev->reserverPourRejeu($rid, $MAX));

    // Le plafond de tentatives doit être appliqué par la MÊME instruction, sinon
    // il se contourne exactement comme le contrôle de statut.
    $db->exec("UPDATE reversements SET statut='echoue', tentatives={$MAX} WHERE id={$rid}");
    t('le plafond de tentatives est atomique', !$rev->reserverPourRejeu($rid, $MAX));
    $db->exec("UPDATE reversements SET statut='echoue', tentatives=" . ($MAX - 1) . " WHERE id={$rid}");
    t('la dernière tentative est accordée', $rev->reserverPourRejeu($rid, $MAX));
    t('puis la porte se ferme', !$rev->reserverPourRejeu($rid, $MAX));

    foreach (['a_rejouer', 'initie'] as $statut) {
        $db->exec("UPDATE reversements SET statut='{$statut}', tentatives=0 WHERE id={$rid}");
        t("statut {$statut} : rejeu toujours possible", $rev->reserverPourRejeu($rid, $MAX));
    }
}

// ══ §L — Tâches planifiées : jamais deux fois en parallèle ═════════════════
echo "\n── Verrou d'exécution des tâches (§L) ──\n";

// `rattrapage` tourne toutes les QUINZE MINUTES et régénère jusqu'à cinquante
// documents, chacun un rendu dompdf suivi d'un TÉLÉVERSEMENT Backblaze. Quand
// le stockage ralentit, une exécution dépasse son intervalle et cron en lance
// une seconde par-dessus : les deux lisent la même liste de documents manquants
// — la première n'a pas fini d'écrire — et téléversent les mêmes fichiers.
// Chaque chevauchement DOUBLE la consommation du quota B2.
$binSrc = file_get_contents(BASE_PATH . '/bin/taches-orientation.php');
t('le script pose un verrou exclusif non bloquant',
    str_contains($binSrc, 'flock($verrou, LOCK_EX | LOCK_NB)'));
t('un passage déjà en cours sort en succès, pas en erreur',
    str_contains($binSrc, 'déjà en cours d’exécution — passage ignoré')
    || str_contains($binSrc, "déjà en cours d'exécution — passage ignoré"),
    'un exit non nul déclencherait un courriel du cron à chaque exécution longue');
t('le verrou porte le nom de la tâche',
    str_contains($binSrc, "'/taches-' . preg_replace"),
    'un verrou global ferait attendre les tâches sans rapport entre elles');
t('un verrou inaccessible ne doit pas annuler la tâche',
    str_contains($binSrc, 'exécution sans garde'),
    'mieux vaut un chevauchement improbable qu’un rattrapage jamais exécuté');

// `flock` est tenu par le PROCESSUS : le noyau le relâche à sa mort, quelle
// qu'en soit la cause. Un verrou applicatif resterait posé après un OOM et
// bloquerait la tâche jusqu'à une intervention manuelle. On le vérifie.
$dossier = BASE_PATH . '/storage/locks';
if (!is_dir($dossier)) { @mkdir($dossier, 0775, true); }
$fichier = $dossier . '/test-lot12-' . bin2hex(random_bytes(4)) . '.lock';

$premier = fopen($fichier, 'c');
t('le premier prend le verrou', $premier !== false && flock($premier, LOCK_EX | LOCK_NB));

$second = fopen($fichier, 'c');
t('le second est refusé pendant ce temps',
    $second !== false && !flock($second, LOCK_EX | LOCK_NB),
    'sans cela, deux rattrapages téléverseraient les mêmes fichiers');
if ($second !== false) { fclose($second); }

// Mort du détenteur, sans déverrouillage explicite : le noyau doit libérer.
if ($premier !== false) { fclose($premier); }
$troisieme = fopen($fichier, 'c');
t('le verrou est libéré à la fermeture du détenteur',
    $troisieme !== false && flock($troisieme, LOCK_EX | LOCK_NB),
    'un verrou qui survit à son processus bloque la tâche indéfiniment');
if ($troisieme !== false) { flock($troisieme, LOCK_UN); fclose($troisieme); }
@unlink($fichier);

t('les verrous ne sont pas versionnés',
    str_contains(file_get_contents(BASE_PATH . '/.gitignore'), 'storage/locks/'),
    'c’est de l’état d’exécution, propre à chaque machine');

// ══ §M — Les tests ne doivent jamais être atteignables par le web ══════════
echo "\n── Exposition des fichiers de test (§M) ──\n";

// Les fichiers de ce dossier écrivent en base, déclenchent des envois d'e-mails
// et génèrent des PDF téléversés sur Backblaze. Servis par un serveur web, ils
// donnaient à n'importe quel visiteur anonyme un droit d'ÉCRITURE sur la
// production et un robinet ouvert sur le quota de stockage — il suffisait de
// deviner `/tests/`, un nom de dossier on ne peut plus courant.
//
// Trois couches, parce qu'une seule finit toujours par sauter : le fichier
// refuse de tourner hors CLI, le serveur refuse de le servir, et le
// déploiement ne l'envoie même pas.
$lots = glob(BASE_PATH . '/tests/orientation/lot*.php') ?: [];
t('des fichiers de test sont bien présents', $lots !== []);

$sansGarde = [];
foreach ($lots as $lot) {
    if (!str_contains((string)file_get_contents($lot), "PHP_SAPI !== 'cli'")) {
        $sansGarde[] = basename($lot);
    }
}
t('chaque test refuse de s’exécuter hors ligne de commande', $sansGarde === [],
    'sans garde : ' . implode(', ', $sansGarde));

$nginx = @file_get_contents(BASE_PATH . '/nginx.conf');
if ($nginx === false) {
    echo "  (nginx.conf absent — vérification ignorée)\n";
} else {
    t('nginx bloque le dossier tests',
        (bool)preg_match('#location ~ \^/\([^)]*\btests\b[^)]*\)#', $nginx),
        'c’est la configuration de PRODUCTION : sans cette entrée, /tests/…php est exécuté');
    // Le dossier s'appelle « Database » avec une majuscule. Sur Linux la casse
    // compte : une règle en minuscules seules ne l'attraperait jamais.
    t('nginx bloque Database ET database',
        str_contains($nginx, 'database|Database'),
        'sur Linux, la casse compte — les migrations SQL seraient servies');
}

$htaccess = @file_get_contents(BASE_PATH . '/.htaccess');
if ($htaccess === false) {
    echo "  (.htaccess absent — vérification ignorée)\n";
} else {
    t('Apache bloque le dossier tests',
        (bool)preg_match('#\^\([^)]*\btests\b[^)]*\)\(/\|\$\)#', $htaccess));
    t('la règle de dossiers ignore la casse',
        (bool)preg_match('#\^\(app\|[^\n]*\[F,L,NC\]#', $htaccess),
        'sans [NC], « Database » passe à travers sur Linux');
    t('la documentation interne n’est pas servie',
        str_contains($htaccess, '^[^/]+\.md$'),
        'les .md portent les spécifications et les règles métier');
}

$deploy = @file_get_contents(BASE_PATH . '/deploy.sh');
if ($deploy === false) {
    echo "  (deploy.sh absent — vérification ignorée)\n";
} else {
    t('le déploiement n’envoie pas les tests en production',
        str_contains($deploy, '--exclude=tests/'),
        'la meilleure protection reste de ne pas déployer le fichier du tout');
}

echo "\n══ {$ok} réussis · {$ko} échoués ══\n";
exit($ko === 0 ? 0 : 1);
