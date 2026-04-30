<?php
/**
 * Connect'Academia - Connexion Élève
 */
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_serie'] = $user['serie_id'];
                $_SESSION['user_nom'] = $user['prenom'];
                $_SESSION['last_activity'] = time();
                
                // Mettre à jour last_login
                $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")
                    ->execute([$user['id']]);
                
                $redirect = $_GET['redirect'] ?? 'dashboard.php';
                header('Location: ' . $redirect);
                exit;
            } else {
                $error = 'Email ou mot de passe incorrect.';
            }
        } catch (PDOException $e) {
            error_log('Erreur login: ' . $e->getMessage());
            $error = 'Erreur de connexion. Réessayez.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — Connect'Academia</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #F9FAFB;
            padding: 20px;
        }
        .login-card {
            background: var(--color-white);
            border-radius: var(--radius-lg);
            box-shadow: 0 4px 16px var(--color-shadow);
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }
        .login-card__logo {
            text-align: center;
            margin-bottom: 32px;
        }
        .login-card__logo img {
            height: 48px;
        }
        .login-card__title {
            text-align: center;
            margin-bottom: 8px;
        }
        .login-card__subtitle {
            text-align: center;
            color: var(--color-text-light);
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
            color: var(--color-dark);
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            font-size: 14px;
            font-family: inherit;
        }
        .form-group input:focus {
            outline: none;
            border-color: var(--color-primary);
        }
        .error-message {
            background: #FEF2F2;
            color: #DC2626;
            padding: 12px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            font-size: 14px;
        }
        .login-card__footer {
            text-align: center;
            margin-top: 24px;
            font-size: 14px;
            color: var(--color-text-light);
        }
        .login-card__footer a {
            color: var(--color-primary);
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-card__logo">
                <img src="assets/img/logo.svg" alt="Connect'Academia">
            </div>
            <h1 class="login-card__title">Connexion</h1>
            <p class="login-card__subtitle">Accédez à votre espace élève</p>
            
            <?php if ($error): ?>
                <div class="error-message"><?= e($error) ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn-primary" style="width: 100%;">Se connecter</button>
            </form>
            
            <div class="login-card__footer">
                Pas encore inscrit ? <a href="register.php">S'inscrire</a>
            </div>
        </div>
    </div>
</body>
</html>

