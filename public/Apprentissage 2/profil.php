<?php
/**
 * Connect'Academia - Mon Profil
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$pdo = getDB();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Récupérer les infos utilisateur
$stmt = $pdo->prepare("
    SELECT u.*, s.nom as serie_nom
    FROM users u
    JOIN series s ON s.id = u.serie_id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Statistiques
$stmt = $pdo->prepare("SELECT COUNT(*) FROM progressions WHERE user_id = ?");
$stmt->execute([$user_id]);
$nb_ressources_consultees = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(duree_secondes), 0) FROM sessions_revision WHERE user_id = ?");
$stmt->execute([$user_id]);
$temps_total = $stmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prenom = trim($_POST['prenom'] ?? '');
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    if (empty($prenom) || empty($nom) || empty($email)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        try {
            // Vérifier si l'email est déjà utilisé par un autre utilisateur
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) {
                $error = 'Cet email est déjà utilisé.';
            } else {
                $stmt = $pdo->prepare("UPDATE users SET prenom = ?, nom = ?, email = ? WHERE id = ?");
                $stmt->execute([$prenom, $nom, $email, $user_id]);
                $success = 'Profil mis à jour avec succès.';
                $_SESSION['user_nom'] = $prenom;
                header('Location: profil.php');
                exit;
            }
        } catch (PDOException $e) {
            error_log('Erreur update profil: ' . $e->getMessage());
            $error = 'Erreur lors de la mise à jour.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil — Connect'Academia</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/front.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    <style>
        /* Styles spécifiques pour la page profil */
        .profil-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 40px;
            padding-bottom: 24px;
            border-bottom: 2px solid var(--color-border);
        }
        
        .profil-header__title {
            font-size: 32px;
            font-weight: 700;
            color: var(--color-dark);
            margin: 0;
        }
        
        .profil-header__subtitle {
            color: var(--color-text-light);
            font-size: 15px;
            margin-top: 6px;
        }
        
        /* Messages d'alerte améliorés */
        .alert {
            padding: 16px 20px;
            border-radius: var(--radius-lg);
            margin-bottom: 32px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            font-weight: 500;
            animation: slideDown 0.3s ease-out;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .alert-error {
            background: linear-gradient(135deg, #FEF2F2 0%, #FEE2E2 100%);
            color: #DC2626;
            border-left: 4px solid #DC2626;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%);
            color: #059669;
            border-left: 4px solid #059669;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Layout principal */
        .profil-layout {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 40px;
            margin-top: 32px;
        }
        
        /* Section informations personnelles */
        .profil-section {
            background: var(--color-white);
            border-radius: var(--radius-lg);
            padding: 32px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            border: 1px solid var(--color-border);
        }
        
        .profil-section__header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--color-border);
        }
        
        .profil-section__icon {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, var(--color-primary) 0%, #7540E0 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            box-shadow: 0 4px 12px rgba(139, 82, 250, 0.25);
        }
        
        .profil-section__title {
            font-size: 22px;
            font-weight: 600;
            color: var(--color-dark);
            margin: 0;
        }
        
        /* Formulaire moderne */
        .form-modern {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        
        .form-field {
            position: relative;
        }
        
        .form-field__label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 600;
            color: var(--color-dark);
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-field__label-icon {
            width: 16px;
            height: 16px;
            color: var(--color-primary);
        }
        
        .form-field__input-wrapper {
            position: relative;
        }
        
        .form-field__input {
            width: 100%;
            padding: 14px 16px 14px 48px;
            font-size: 15px;
            font-family: var(--font-main);
            color: var(--color-dark);
            background: #FAFBFC;
            border: 2px solid var(--color-border);
            border-radius: var(--radius-md);
            transition: all 0.2s ease;
            outline: none;
        }
        
        .form-field__input:focus {
            background: var(--color-white);
            border-color: var(--color-primary);
            box-shadow: 0 0 0 4px rgba(139, 82, 250, 0.1);
        }
        
        .form-field__input:disabled {
            background: #F3F4F6;
            color: var(--color-text-light);
            cursor: not-allowed;
            border-color: #E5E7EB;
        }
        
        .form-field__input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            color: var(--color-text-light);
            pointer-events: none;
            transition: color 0.2s ease;
        }
        
        .form-field__input:focus + .form-field__input-icon {
            color: var(--color-primary);
        }
        
        .form-field__help {
            font-size: 12px;
            color: var(--color-text-light);
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        /* Bouton d'action */
        .form-actions {
            margin-top: 8px;
            display: flex;
            gap: 12px;
        }
        
        .btn-save {
            background: linear-gradient(135deg, var(--color-primary) 0%, #7540E0 100%);
            color: var(--color-white);
            border: none;
            border-radius: var(--radius-md);
            padding: 14px 28px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(139, 82, 250, 0.3);
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(139, 82, 250, 0.4);
        }
        
        .btn-save:active {
            transform: translateY(0);
        }
        
        /* Section statistiques */
        .stats-grid {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .stat-card {
            background: var(--color-white);
            border-radius: var(--radius-lg);
            padding: 24px;
            border: 1px solid var(--color-border);
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, var(--color-primary) 0%, #7540E0 100%);
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        }
        
        .stat-card__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        
        .stat-card__label {
            font-size: 13px;
            font-weight: 600;
            color: var(--color-text-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-card__icon {
            width: 36px;
            height: 36px;
            background: var(--color-primary-bg);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--color-primary);
        }
        
        .stat-card__value {
            font-size: 24px;
            font-weight: 700;
            color: var(--color-dark);
            margin-top: 4px;
        }
        
        /* Section titre statistiques */
        .stats-section__header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
        }
        
        .stats-section__icon {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, #4A90D9 0%, #357ABD 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            box-shadow: 0 4px 12px rgba(74, 144, 217, 0.25);
        }
        
        .stats-section__title {
            font-size: 22px;
            font-weight: 600;
            color: var(--color-dark);
            margin: 0;
        }
        
        /* Responsive */
        @media (max-width: 968px) {
            .profil-layout {
                grid-template-columns: 1fr;
                gap: 24px;
            }

            .profil-header {
                flex-direction: column;
                align-items: flex-start;
                margin-bottom: 28px;
            }
        }

        @media (max-width: 768px) {
            .profil-section {
                padding: 20px 16px;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn-save {
                width: 100%;
                justify-content: center;
                padding: 12px 20px;
            }

            .profil-header__title {
                font-size: 24px;
            }

            .form-field__input {
                font-size: 14px;
                padding: 12px 12px 12px 44px;
            }

            .stat-card {
                padding: 16px 14px;
            }
        }

        @media (max-width: 480px) {
            .profil-section {
                padding: 16px 12px;
            }

            .profil-header__title {
                font-size: 20px;
            }

            .profil-section__title,
            .stats-section__title {
                font-size: 18px;
            }
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <aside class="sidebar">
            <?php include __DIR__ . '/includes/partials/sidebar.php'; ?>
        </aside>
        
        <main class="main-content">
            <div class="page-container">
                <!-- En-tête de la page -->
                <div class="profil-header">
                    <div>
                        <h1 class="profil-header__title">Mon Profil</h1>
                        <p class="profil-header__subtitle">Gérez vos informations personnelles et consultez vos statistiques</p>
                    </div>
                </div>
                
                <!-- Messages d'alerte -->
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i data-lucide="alert-circle" style="width: 20px; height: 20px;"></i>
                        <span><?= e($error) ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i data-lucide="check-circle" style="width: 20px; height: 20px;"></i>
                        <span><?= e($success) ?></span>
                    </div>
                <?php endif; ?>
                
                <!-- Layout principal -->
                <div class="profil-layout">
                    <!-- Section Informations personnelles -->
                    <div class="profil-section">
                        <div class="profil-section__header">
                            <div class="profil-section__icon">
                                <i data-lucide="user"></i>
                            </div>
                            <h2 class="profil-section__title">Informations personnelles</h2>
                        </div>
                        
                        <form method="POST" action="" class="form-modern">
                            <div class="form-field">
                                <label for="prenom" class="form-field__label">
                                    <i data-lucide="user" class="form-field__label-icon"></i>
                                    Prénom
                                </label>
                                <div class="form-field__input-wrapper">
                                    <input 
                                        type="text" 
                                        id="prenom" 
                                        name="prenom" 
                                        value="<?= e($user['prenom']) ?>" 
                                        required
                                        class="form-field__input"
                                        placeholder="Votre prénom"
                                    >
                                    <i data-lucide="user" class="form-field__input-icon"></i>
                                </div>
                            </div>
                            
                            <div class="form-field">
                                <label for="nom" class="form-field__label">
                                    <i data-lucide="user-circle" class="form-field__label-icon"></i>
                                    Nom
                                </label>
                                <div class="form-field__input-wrapper">
                                    <input 
                                        type="text" 
                                        id="nom" 
                                        name="nom" 
                                        value="<?= e($user['nom']) ?>" 
                                        required
                                        class="form-field__input"
                                        placeholder="Votre nom"
                                    >
                                    <i data-lucide="user-circle" class="form-field__input-icon"></i>
                                </div>
                            </div>
                            
                            <div class="form-field">
                                <label for="email" class="form-field__label">
                                    <i data-lucide="mail" class="form-field__label-icon"></i>
                                    Adresse email
                                </label>
                                <div class="form-field__input-wrapper">
                                    <input 
                                        type="email" 
                                        id="email" 
                                        name="email" 
                                        value="<?= e($user['email']) ?>" 
                                        required
                                        class="form-field__input"
                                        placeholder="votre.email@exemple.com"
                                    >
                                    <i data-lucide="mail" class="form-field__input-icon"></i>
                                </div>
                                <div class="form-field__help">
                                    <i data-lucide="info" style="width: 14px; height: 14px;"></i>
                                    Cette adresse sera utilisée pour vous contacter
                                </div>
                            </div>
                            
                            <div class="form-field">
                                <label class="form-field__label">
                                    <i data-lucide="graduation-cap" class="form-field__label-icon"></i>
                                    Série
                                </label>
                                <div class="form-field__input-wrapper">
                                    <input 
                                        type="text" 
                                        value="Terminale <?= e($user['serie_nom']) ?>" 
                                        disabled
                                        class="form-field__input"
                                    >
                                    <i data-lucide="graduation-cap" class="form-field__input-icon"></i>
                                </div>
                                <div class="form-field__help">
                                    <i data-lucide="lock" style="width: 14px; height: 14px;"></i>
                                    La série ne peut pas être modifiée
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn-save">
                                    <i data-lucide="save"></i>
                                    Enregistrer les modifications
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Section Statistiques -->
                    <div>
                        <div class="stats-section__header">
                            <div class="stats-section__icon">
                                <i data-lucide="bar-chart-3"></i>
                            </div>
                            <h2 class="stats-section__title">Statistiques</h2>
                        </div>
                        
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-card__header">
                                    <div class="stat-card__label">Date d'inscription</div>
                                    <div class="stat-card__icon">
                                        <i data-lucide="calendar"></i>
                                    </div>
                                </div>
                                <div class="stat-card__value">
                                    <?= date('d/m/Y', strtotime($user['created_at'])) ?>
                                </div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-card__header">
                                    <div class="stat-card__label">Ressources consultées</div>
                                    <div class="stat-card__icon">
                                        <i data-lucide="book-open"></i>
                                    </div>
                                </div>
                                <div class="stat-card__value">
                                    <?= $nb_ressources_consultees ?>
                                </div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-card__header">
                                    <div class="stat-card__label">Temps total de révision</div>
                                    <div class="stat-card__icon">
                                        <i data-lucide="clock"></i>
                                    </div>
                                </div>
                                <div class="stat-card__value">
                                    <?= formatDuration($temps_total) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        lucide.createIcons();
        
        // Réinitialiser les icônes après un délai pour s'assurer qu'elles sont bien chargées
        setTimeout(() => {
            lucide.createIcons();
        }, 100);
    </script>
</body>
</html>

