<?php
/**
 * Connect'Academia - Inscription Élève
 */
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$error = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prenom = trim($_POST['prenom'] ?? '');
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $serie_id = intval($_POST['serie_id'] ?? 0);
    
    // Validation
    if (strlen($prenom) < 2 || strlen($prenom) > 50) {
        $errors['prenom'] = 'Le prénom doit contenir entre 2 et 50 caractères.';
    }
    if (strlen($nom) < 2 || strlen($nom) > 50) {
        $errors['nom'] = 'Le nom doit contenir entre 2 et 50 caractères.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Email invalide.';
    }
    if (strlen($password) < 8) {
        $errors['password'] = 'Le mot de passe doit contenir au moins 8 caractères.';
    }
    if ($password !== $password_confirm) {
        $errors['password_confirm'] = 'Les mots de passe ne correspondent pas.';
    }
    if ($serie_id < 1 || $serie_id > 5) {
        $errors['serie_id'] = 'Veuillez sélectionner une série.';
    }
    
    if (empty($errors)) {
        try {
            $pdo = getDB();
            
            // Vérifier si l'email existe déjà
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors['email'] = 'Cet email est déjà utilisé.';
            } else {
                // Hash du mot de passe
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                
                // Insertion
                $stmt = $pdo->prepare("
                    INSERT INTO users (nom, prenom, email, password, serie_id)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$nom, $prenom, $email, $hash, $serie_id]);
                
                // Créer la session
                session_regenerate_id(true);
                $_SESSION['user_id'] = $pdo->lastInsertId();
                $_SESSION['user_serie'] = $serie_id;
                $_SESSION['user_nom'] = $prenom;
                $_SESSION['last_activity'] = time();
                
                header('Location: dashboard.php');
                exit;
            }
        } catch (PDOException $e) {
            error_log('Erreur inscription: ' . $e->getMessage());
            $error = 'Erreur lors de l\'inscription. Réessayez.';
        }
    }
}

// Récupérer les séries pour le select
$pdo = getDB();
$series = $pdo->query("SELECT id, nom, description FROM series WHERE is_active = 1 ORDER BY nom ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription — Connect'Academia</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    <style>
        .register-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #F9FAFB;
            padding: 20px;
        }
        .register-card {
            background: var(--color-white);
            border-radius: var(--radius-lg);
            box-shadow: 0 4px 16px var(--color-shadow);
            padding: 40px;
            width: 100%;
            max-width: 450px;
        }
        .register-card__logo {
            text-align: center;
            margin-bottom: 32px;
        }
        .register-card__logo img {
            height: 48px;
        }
        .register-card__title {
            text-align: center;
            margin-bottom: 8px;
        }
        .register-card__subtitle {
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
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            font-size: 14px;
            font-family: inherit;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--color-primary);
        }
        .form-group input.error,
        .form-group select.error {
            border-color: #DC2626;
        }
        .error-message {
            background: #FEF2F2;
            color: #DC2626;
            padding: 12px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            font-size: 14px;
        }
        .field-error {
            color: #DC2626;
            font-size: 12px;
            margin-top: 4px;
        }
        .register-card__footer {
            text-align: center;
            margin-top: 24px;
            font-size: 14px;
            color: var(--color-text-light);
        }
        .register-card__footer a {
            color: var(--color-primary);
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="register-card__logo">
                <img src="assets/img/logo.svg" alt="Connect'Academia">
            </div>
            <h1 class="register-card__title">Inscription</h1>
            <p class="register-card__subtitle">Créez votre compte élève</p>
            
            <?php if ($error): ?>
                <div class="error-message"><?= e($error) ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" id="registerForm">
                <div class="form-group">
                    <label for="prenom">Prénom *</label>
                    <input type="text" id="prenom" name="prenom" required 
                           value="<?= e($_POST['prenom'] ?? '') ?>"
                           class="<?= isset($errors['prenom']) ? 'error' : '' ?>">
                    <?php if (isset($errors['prenom'])): ?>
                        <div class="field-error"><?= e($errors['prenom']) ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="nom">Nom *</label>
                    <input type="text" id="nom" name="nom" required
                           value="<?= e($_POST['nom'] ?? '') ?>"
                           class="<?= isset($errors['nom']) ? 'error' : '' ?>">
                    <?php if (isset($errors['nom'])): ?>
                        <div class="field-error"><?= e($errors['nom']) ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" required
                           value="<?= e($_POST['email'] ?? '') ?>"
                           class="<?= isset($errors['email']) ? 'error' : '' ?>">
                    <?php if (isset($errors['email'])): ?>
                        <div class="field-error"><?= e($errors['email']) ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="password">Mot de passe *</label>
                    <input type="password" id="password" name="password" required
                           class="<?= isset($errors['password']) ? 'error' : '' ?>">
                    <?php if (isset($errors['password'])): ?>
                        <div class="field-error"><?= e($errors['password']) ?></div>
                    <?php endif; ?>
                    <small style="color: var(--color-text-light); font-size: 12px;">Minimum 8 caractères</small>
                </div>
                
                <div class="form-group">
                    <label for="password_confirm">Confirmer le mot de passe *</label>
                    <input type="password" id="password_confirm" name="password_confirm" required
                           class="<?= isset($errors['password_confirm']) ? 'error' : '' ?>">
                    <?php if (isset($errors['password_confirm'])): ?>
                        <div class="field-error"><?= e($errors['password_confirm']) ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="serie_id">Série *</label>
                    <select id="serie_id" name="serie_id" required
                            class="<?= isset($errors['serie_id']) ? 'error' : '' ?>">
                        <option value="">-- Sélectionner une série --</option>
                        <?php foreach ($series as $serie): ?>
                            <option value="<?= $serie['id'] ?>" 
                                    <?= (isset($_POST['serie_id']) && $_POST['serie_id'] == $serie['id']) ? 'selected' : '' ?>>
                                Terminale <?= e($serie['nom']) ?> — <?= e($serie['description']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['serie_id'])): ?>
                        <div class="field-error"><?= e($errors['serie_id']) ?></div>
                    <?php endif; ?>
                </div>
                
                <button type="submit" class="btn-primary" style="width: 100%;">S'inscrire</button>
            </form>
            
            <div class="register-card__footer">
                Déjà inscrit ? <a href="login.php">Se connecter</a>
            </div>
        </div>
    </div>
    
    <script>
        // Validation côté client
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const passwordConfirm = document.getElementById('password_confirm').value;
            
            if (password !== passwordConfirm) {
                e.preventDefault();
                alert('Les mots de passe ne correspondent pas.');
                return false;
            }
            
            if (password.length < 8) {
                e.preventDefault();
                alert('Le mot de passe doit contenir au moins 8 caractères.');
                return false;
            }
        });
    </script>
</body>
</html>

