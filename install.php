<?php
/**
 * Connect'Academia — Script d'installation
 * Accès : http://localhost:8888/connect'academia.v1/install.php
 * SUPPRIMER CE FICHIER après installation !
 */
declare(strict_types=1);

define('BASE_PATH', __DIR__);

// Charger .env
$env = parse_ini_file(BASE_PATH . '/.env', false, INI_SCANNER_RAW) ?: [];
foreach ($env as $k => $v) { $_ENV[$k] = $v; }

$step    = $_POST['step'] ?? 'check';
$message = '';
$error   = '';

// ── Connexion PDO ──────────────────────────────────────────────────────────
function getConnection(): \PDO
{
    $host    = $_ENV['DB_HOST']    ?? '127.0.0.1';
    $port    = $_ENV['DB_PORT']    ?? '8889';
    $user    = $_ENV['DB_USER']    ?? 'root';
    $pass    = $_ENV['DB_PASS']    ?? 'root';
    $charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';
    $socket  = $_ENV['DB_SOCKET']  ?? '';

    if (!empty($socket) && file_exists($socket)) {
        $dsn = "mysql:unix_socket={$socket};charset={$charset}";
    } else {
        $dsn = "mysql:host={$host};port={$port};charset={$charset}";
    }

    return new \PDO($dsn, $user, $pass, [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
    ]);
}

// ── Étapes ────────────────────────────────────────────────────────────────
$checks   = [];
$canInstall = false;

// PHP
$phpOk = version_compare(PHP_VERSION, '8.1', '>=');
$checks[] = ['label' => 'PHP >= 8.1', 'ok' => $phpOk, 'detail' => PHP_VERSION];

// Extensions
foreach (['pdo', 'pdo_mysql', 'mbstring', 'json', 'openssl', 'fileinfo'] as $ext) {
    $ok = extension_loaded($ext);
    $checks[] = ['label' => "Extension {$ext}", 'ok' => $ok, 'detail' => $ok ? 'Chargée' : 'MANQUANTE'];
}

// .env
$envOk = file_exists(BASE_PATH . '/.env');
$checks[] = ['label' => 'Fichier .env', 'ok' => $envOk, 'detail' => $envOk ? 'Présent' : 'ABSENT'];

// Répertoires
foreach (['storage/logs', 'public/uploads'] as $dir) {
    $path  = BASE_PATH . '/' . $dir;
    $writable = is_dir($path) && is_writable($path);
    if (!is_dir($path)) {
        @mkdir($path, 0755, true);
        $writable = is_dir($path) && is_writable($path);
    }
    $checks[] = ['label' => "Répertoire {$dir}", 'ok' => $writable, 'detail' => $writable ? 'Accessible' : 'Non accessible'];
}

// MySQL
$mysqlOk = false;
$mysqlDetail = '';
try {
    $pdo = getConnection();
    $mysqlDetail = 'Connexion OK (' . $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION) . ')';
    $mysqlOk = true;
} catch (\Exception $e) {
    $mysqlDetail = 'ERREUR: ' . $e->getMessage();
}
$checks[] = ['label' => 'Connexion MySQL', 'ok' => $mysqlOk, 'detail' => $mysqlDetail];

$canInstall = $mysqlOk && $phpOk && $envOk;

// ── Exécution installation ─────────────────────────────────────────────────
if ($step === 'install' && $canInstall) {
    try {
        $pdo = getConnection();
        $dbName = $_ENV['DB_NAME'] ?? 'connect_academia';

        // Créer la BDD si elle n'existe pas
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$dbName}`");

        // Lire et exécuter le schéma SQL
        $sqlFile = BASE_PATH . '/database/001_schema_unifie.sql';
        if (!file_exists($sqlFile)) {
            throw new \RuntimeException("Fichier SQL introuvable: {$sqlFile}");
        }

        $sql = file_get_contents($sqlFile);
        // Exécuter chaque statement
        $pdo->exec($sql);

        // Créer l'admin par défaut si absent
        $stmt = $pdo->query("SELECT COUNT(*) FROM admins");
        if ((int) $stmt->fetchColumn() === 0) {
            $hash = password_hash('Admin@2026!', PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare("INSERT INTO admins (nom, prenom, email, password_hash, role) VALUES (?, ?, ?, ?, ?)")
                ->execute(['Leroy', 'Ona-David', 'admin@connect-academia.ga', $hash, 'super_admin']);
        }

        $message = "Installation réussie ! Base de données '{$dbName}' créée et configurée.";

    } catch (\Exception $e) {
        $error = 'Erreur installation : ' . $e->getMessage();
    }
}

// Vérifier si BDD existe déjà
$dbExists = false;
if ($mysqlOk) {
    try {
        $pdo = getConnection();
        $pdo->exec("USE `" . ($_ENV['DB_NAME'] ?? 'connect_academia') . "`");
        $pdo->query("SELECT 1 FROM users LIMIT 1");
        $dbExists = true;
    } catch (\Exception) {
        $dbExists = false;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Installation — Connect'Academia</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, 'Inter', sans-serif; background: #F9FAFB; color: #1A1A2E; padding: 2rem 1rem; }
    .wrap { max-width: 680px; margin: 0 auto; }
    .logo { display: flex; align-items: center; gap: .5rem; font-size: 1.3rem; font-weight: 700; margin-bottom: 2rem; }
    .logo span { color: #8B52FA; }
    h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: .5rem; }
    h2 { font-size: 1.1rem; font-weight: 600; margin: 1.5rem 0 .75rem; }
    .card { background: white; border: 1px solid #E5E7EB; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.25rem; }
    .check { display: flex; align-items: center; gap: .75rem; padding: .5rem 0; border-bottom: 1px solid #F3F4F6; }
    .check:last-child { border-bottom: none; }
    .check__status { width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: .75rem; font-weight: 700; }
    .check__status--ok  { background: #ECFDF5; color: #059669; }
    .check__status--err { background: #FEF2F2; color: #DC2626; }
    .check__label { font-size: .9rem; font-weight: 500; flex: 1; }
    .check__detail { font-size: .8rem; color: #6B7280; }
    .alert { padding: 1rem 1.25rem; border-radius: 10px; margin-bottom: 1.25rem; font-size: .9rem; }
    .alert--success { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
    .alert--error   { background: #FEF2F2; color: #7F1D1D; border: 1px solid #FECACA; }
    .alert--warning { background: #FFFBEB; color: #78350F; border: 1px solid #FDE68A; }
    .btn { display: inline-flex; align-items: center; gap: .4rem; padding: .75rem 1.5rem; background: #8B52FA; color: white; border: none; border-radius: 10px; font-size: .95rem; font-weight: 600; cursor: pointer; text-decoration: none; }
    .btn:hover { background: #7440E0; }
    .btn--outline { background: transparent; border: 2px solid #8B52FA; color: #8B52FA; }
    .creds { background: #F0EBFF; padding: 1rem; border-radius: 8px; font-size: .85rem; margin-top: 1rem; }
    .creds p { margin: .25rem 0; }
    .creds code { background: white; padding: .1rem .4rem; border-radius: 4px; font-family: monospace; }
    .delete-warning { background: #FEF2F2; border: 1px solid #FECACA; color: #7F1D1D; padding: .75rem 1rem; border-radius: 8px; font-size: .85rem; margin-top: 1.25rem; font-weight: 500; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="logo">
    <svg width="32" height="32" viewBox="0 0 40 40" fill="none">
      <rect width="40" height="40" rx="10" fill="#8B52FA"/>
      <path d="M8 28L20 10L32 28H8Z" fill="white"/>
      <circle cx="20" cy="20" r="5" fill="white"/>
    </svg>
    Connect<span>'</span>Academia
  </div>

  <h1>Script d'installation</h1>
  <p style="color:#6B7280;margin-bottom:1.5rem">Vérification des prérequis et création de la base de données.</p>

  <?php if ($message): ?>
  <div class="alert alert--success"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <?php if ($error): ?>
  <div class="alert alert--error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if ($dbExists && !$message): ?>
  <div class="alert alert--warning">
    ⚠️ La base de données existe déjà. Réinstaller écrasera les données existantes.
  </div>
  <?php endif; ?>

  <div class="card">
    <h2>Vérification prérequis</h2>
    <?php foreach ($checks as $check): ?>
    <div class="check">
      <div class="check__status check__status--<?= $check['ok'] ? 'ok' : 'err' ?>">
        <?= $check['ok'] ? '✓' : '✗' ?>
      </div>
      <span class="check__label"><?= htmlspecialchars($check['label']) ?></span>
      <span class="check__detail"><?= htmlspecialchars($check['detail']) ?></span>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if ($message): ?>
  <div class="card">
    <h2>✅ Installation terminée !</h2>
    <p style="color:#6B7280;margin-bottom:1rem">La base de données a été créée. Voici vos identifiants admin :</p>
    <div class="creds">
      <p><strong>URL Admin :</strong> <code><?= htmlspecialchars(rtrim($_ENV['APP_URL'] ?? '', '/') . '/admin/login') ?></code></p>
      <p><strong>Email :</strong> <code>admin@connect-academia.ga</code></p>
      <p><strong>Mot de passe :</strong> <code>Admin@2026!</code></p>
    </div>
    <p style="margin-top:1rem">
      <a href="<?= htmlspecialchars(rtrim($_ENV['APP_URL'] ?? '', '/') . '/auth/connexion') ?>" class="btn">Aller à la connexion →</a>
    </p>
    <div class="delete-warning">🚨 Supprimez ce fichier install.php avant de mettre en production !</div>
  </div>
  <?php elseif ($canInstall): ?>
  <form method="POST">
    <input type="hidden" name="step" value="install">
    <button type="submit" class="btn">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
        <polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
      </svg>
      <?= $dbExists ? 'Réinstaller la base de données' : 'Installer la base de données' ?>
    </button>
  </form>
  <?php else: ?>
  <div class="alert alert--error">
    Certains prérequis ne sont pas satisfaits. Vérifiez que MAMP est démarré et que le fichier .env est correctement configuré.
  </div>
  <?php endif; ?>

</div>
</body>
</html>
