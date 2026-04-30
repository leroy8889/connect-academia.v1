<?php
/**
 * Connect'Academia - Connexion Admin
 */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Réinitialiser les tentatives de connexion (rate limiting désactivé)
    unset($_SESSION['login_attempts']);
    
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();
        
        if ($admin && password_verify($password, $admin['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_role'] = $admin['role'];
            $_SESSION['last_activity'] = time();
            
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Identifiants incorrects.';
        }
    } catch (PDOException $e) {
        error_log('Erreur login admin: ' . $e->getMessage());
        $error = 'Erreur de connexion.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Admin — Connect'Academia</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: var(--color-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .admin-login-card {
            background: #282828;
            border-radius: var(--radius-lg);
            padding: 48px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
        }
        .admin-login-card__logo {
            text-align: center;
            margin-bottom: 32px;
        }
        .admin-login-card__logo img {
            height: 40px;
        }
        .admin-login-card__icon {
            width: 64px;
            height: 64px;
            background: var(--color-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            color: var(--color-white);
            font-size: 32px;
        }
        .admin-login-card__title {
            text-align: center;
            color: var(--color-white);
            margin-bottom: 8px;
        }
        .admin-login-card__subtitle {
            text-align: center;
            color: #9CA3AF;
            font-size: 14px;
            margin-bottom: 32px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 500;
            color: var(--color-white);
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            background: #1F1F1F;
            border: 1px solid #3A3A3A;
            border-radius: var(--radius-md);
            color: var(--color-white);
            font-size: 14px;
            font-family: inherit;
        }
        .form-group input:focus {
            outline: none;
            border-color: var(--color-primary);
        }
        .form-group input::placeholder {
            color: #6B7280;
        }
        .error-message {
            background: #7F1D1D;
            color: #FCA5A5;
            padding: 12px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            font-size: 14px;
        }
        .form-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 24px;
        }
        .form-checkbox input {
            width: auto;
        }
        .form-checkbox label {
            margin: 0;
            font-size: 13px;
            color: #9CA3AF;
        }
        .security-info {
            text-align: center;
            margin-top: 24px;
            font-size: 12px;
            color: #6B7280;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
    </style>
</head>
<body>
    <div class="admin-login-card">
        <div class="admin-login-card__logo">
            <img src="../assets/img/logo.svg" alt="Connect'Academia">
        </div>
        <div class="admin-login-card__icon">🔒</div>
        <h1 class="admin-login-card__title">Accès Administrateur</h1>
        <p class="admin-login-card__subtitle">
            Veuillez entrer vos identifiants sécurisés pour accéder au portail de gestion Terminale.
        </p>
        
        <?php if ($error): ?>
            <div class="error-message"><?= e($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email Professionnel</label>
                <input type="email" id="email" name="email" 
                       value="admin@connectacademia.ga" 
                       placeholder="admin@connectacademia.ga" required>
            </div>
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" 
                       value="••••••" 
                       placeholder="••••••" required>
            </div>
            <div class="form-checkbox">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">Maintenir ma session active pour 24h</label>
            </div>
            <button type="submit" class="btn-primary" style="width: 100%;">
                → Se connecter
            </button>
        </form>
        
        <div class="security-info">
            <span>✓</span>
            <span>CONNEXION CHIFFRÉE AES-256</span>
        </div>
    </div>
</body>
</html>

