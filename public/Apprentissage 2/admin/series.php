<?php
/**
 * Connect'Academia - Gestion Séries & Matières Admin
 */
require_once __DIR__ . '/../includes/admin_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$pdo = getDB();

// Récupérer les séries
$series = $pdo->query("SELECT * FROM series ORDER BY nom ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Séries & Matières — Connect'Academia</title>
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
                <a href="users.php" class="admin-sidebar__nav-item">
                    <i data-lucide="users"></i>
                    <span>Élèves</span>
                </a>
                <a href="ressources.php" class="admin-sidebar__nav-item">
                    <i data-lucide="file-text"></i>
                    <span>Ressources</span>
                </a>
                <a href="series.php" class="admin-sidebar__nav-item active">
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
                <div class="admin-topbar__breadcrumb">Pages / Séries & Matières</div>
            </div>
            
            <div class="admin-content">
                <h1 style="margin-bottom: 32px;">Gestion des séries et matières</h1>
                
                <div class="admin-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Description</th>
                                <th>Couleur</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($series as $serie): ?>
                            <tr>
                                <td style="font-weight: 600;">Terminale <?= e($serie['nom']) ?></td>
                                <td><?= e($serie['description']) ?></td>
                                <td>
                                    <div style="width: 24px; height: 24px; background: <?= e($serie['couleur']) ?>; border-radius: 4px;"></div>
                                </td>
                                <td>
                                    <span style="padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; background: <?= $serie['is_active'] ? '#ECFDF5' : '#F3F4F6' ?>; color: <?= $serie['is_active'] ? '#059669' : '#6B7280' ?>;">
                                        <?= $serie['is_active'] ? 'ACTIF' : 'INACTIF' ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn-secondary" style="font-size: 12px; padding: 6px 12px;">Modifier</button>
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
    <script src="../assets/js/admin.js"></script>
    <script>lucide.createIcons();</script>
</body>
</html>

