<?php
/**
 * Connect'Academia - Gestion Utilisateurs Admin
 */
require_once __DIR__ . '/../includes/admin_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$pdo = getDB();

// Récupérer les utilisateurs
$users = $pdo->query("
    SELECT u.id, u.nom, u.prenom, u.email, u.created_at, u.last_login, u.is_active,
           s.nom as serie
    FROM users u
    JOIN series s ON s.id = u.serie_id
    ORDER BY u.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Utilisateurs — Connect'Academia</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <div class="admin-sidebar__logo">
                <img src="../assets/img/logo.svg" alt="Connect'Academia">
                <span>Gabon Terminale Admin</span>
            </div>
            <nav class="admin-sidebar__nav">
                <a href="dashboard.php" class="admin-sidebar__nav-item">
                    <i data-lucide="layout-dashboard"></i>
                    <span>Dashboard</span>
                </a>
                <a href="users.php" class="admin-sidebar__nav-item active">
                    <i data-lucide="users"></i>
                    <span>Élèves</span>
                </a>
                <a href="ressources.php" class="admin-sidebar__nav-item">
                    <i data-lucide="file-text"></i>
                    <span>Ressources</span>
                </a>
                <a href="series.php" class="admin-sidebar__nav-item">
                    <i data-lucide="compass"></i>
                    <span>Séries & Matières</span>
                </a>
            </nav>
            <a href="logout.php" class="admin-sidebar__nav-item" style="margin-top: auto;">
                <i data-lucide="log-out"></i>
                <span>Déconnexion</span>
            </a>
        </aside>
        
        <main class="admin-main">
            <div class="admin-topbar">
                <div class="admin-topbar__breadcrumb">Pages / Élèves</div>
            </div>
            
            <div class="admin-content">
                <h1 style="margin-bottom: 32px;">Gestion des utilisateurs</h1>
                
                <div class="admin-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Email</th>
                                <th>Série</th>
                                <th>Date inscription</th>
                                <th>Dernière connexion</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td style="font-weight: 600;"><?= e($user['prenom'] . ' ' . $user['nom']) ?></td>
                                <td><?= e($user['email']) ?></td>
                                <td><?= e($user['serie']) ?></td>
                                <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                                <td><?= $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Jamais' ?></td>
                                <td>
                                    <span style="padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; background: <?= $user['is_active'] ? '#ECFDF5' : '#F3F4F6' ?>; color: <?= $user['is_active'] ? '#059669' : '#6B7280' ?>;">
                                        <?= $user['is_active'] ? 'ACTIF' : 'INACTIF' ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn-secondary" style="font-size: 12px; padding: 6px 12px;">Voir</button>
                                    <button class="btn-danger" style="font-size: 12px; padding: 6px 12px; margin-left: 8px;">Désactiver</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script>lucide.createIcons();</script>
    <script src="../assets/js/admin.js"></script>
</body>
</html>

