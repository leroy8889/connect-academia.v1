<?php
/**
 * Connect'Academia - Dashboard Admin
 */
require_once __DIR__ . '/../includes/admin_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$pdo = getDB();

// KPIs
$stats = [];

// Total élèves
$stats['total_eleves'] = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();

// Ressources publiées
$stats['ressources'] = $pdo->query("SELECT COUNT(*) FROM ressources WHERE is_deleted = 0")->fetchColumn();

// Vues cette semaine
$stats['vues_semaine'] = $pdo->query("
    SELECT COALESCE(SUM(nb_vues), 0) FROM ressources 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND is_deleted = 0
")->fetchColumn();

// Temps de révision total
$stats['temps_revision'] = $pdo->query("
    SELECT COALESCE(SUM(duree_secondes), 0) FROM sessions_revision
")->fetchColumn();

// Dernières inscriptions
$dernieres_inscriptions = $pdo->query("
    SELECT u.id, u.nom, u.prenom, u.email, s.nom as serie, u.created_at, u.is_active
    FROM users u
    JOIN series s ON s.id = u.serie_id
    ORDER BY u.created_at DESC
    LIMIT 5
")->fetchAll();

// Ressources récentes
$ressources_recentes = $pdo->query("
    SELECT r.id, r.titre, r.type, m.nom as matiere, r.nb_vues
    FROM ressources r
    JOIN matieres m ON m.id = r.matiere_id
    WHERE r.is_deleted = 0
    ORDER BY r.created_at DESC
    LIMIT 5
")->fetchAll();

// Nombre d'élèves par série
$eleves_par_serie = $pdo->query("
    SELECT s.nom as serie, s.couleur, COUNT(u.id) as nombre
    FROM series s
    LEFT JOIN users u ON u.serie_id = s.id AND u.is_active = 1
    WHERE s.is_active = 1
    GROUP BY s.id, s.nom, s.couleur
    ORDER BY s.nom
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin — Connect'Academia</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <div class="admin-sidebar__logo">
                <img src="../assets/img/logo.svg" alt="Connect'Academia">
                <span>Connect'Academia</span>
            </div>
            <nav class="admin-sidebar__nav">
                <a href="dashboard.php" class="admin-sidebar__nav-item active">
                    <i data-lucide="layout-dashboard"></i>
                    <span>Dashboard</span>
                </a>
                <a href="users.php" class="admin-sidebar__nav-item">
                    <i data-lucide="users"></i>
                    <span>Users</span>
                </a>
                <a href="series.php" class="admin-sidebar__nav-item">
                    <i data-lucide="compass"></i>
                    <span>Series & Subjects</span>
                </a>
                <a href="ressources.php" class="admin-sidebar__nav-item">
                    <i data-lucide="file-text"></i>
                    <span>Resources</span>
                </a>
                <a href="stats.php" class="admin-sidebar__nav-item">
                    <i data-lucide="bar-chart-2"></i>
                    <span>Statistics</span>
                </a>
                <a href="notifications.php" class="admin-sidebar__nav-item">
                    <i data-lucide="bell"></i>
                    <span>Notifications</span>
                </a>
                <a href="settings.php" class="admin-sidebar__nav-item">
                    <i data-lucide="settings"></i>
                    <span>Settings</span>
                </a>
            </nav>
            <a href="logout.php" class="admin-sidebar__nav-item" style="margin-top: auto;">
                <i data-lucide="log-out"></i>
                <span>Logout</span>
            </a>
        </aside>
        
        <main class="admin-main">
            <div class="admin-topbar">
                <div class="admin-topbar__breadcrumb">Pages / Dashboard</div>
                <div class="admin-topbar__actions">
                    <i data-lucide="bell" style="width: 20px; height: 20px; color: var(--color-text-light); cursor: pointer;"></i>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--color-primary-bg); display: flex; align-items: center; justify-content: center; color: var(--color-primary); font-weight: 600;">A</div>
                        <div>
                            <div style="font-size: 14px; font-weight: 600;">Admin user</div>
                            <div style="font-size: 12px; color: var(--color-text-light);">Super Admin</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="admin-content">
                <h1 style="margin-bottom: 32px;">Dashboard</h1>
                
                <!-- KPI Cards -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 32px;">
                    <div class="kpi-card">
                        <div class="kpi-card__icon">
                            <i data-lucide="users"></i>
                        </div>
                        <div class="kpi-card__value"><?= $stats['total_eleves'] ?></div>
                        <div class="kpi-card__label">Total Students</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-card__icon">
                            <i data-lucide="file-text"></i>
                        </div>
                        <div class="kpi-card__value"><?= $stats['ressources'] ?></div>
                        <div class="kpi-card__label">Resources Published</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-card__icon">
                            <i data-lucide="eye"></i>
                        </div>
                        <div class="kpi-card__value"><?= $stats['vues_semaine'] ?></div>
                        <div class="kpi-card__label">Views this week</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-card__icon">
                            <i data-lucide="clock"></i>
                        </div>
                        <div class="kpi-card__value"><?= formatDuration($stats['temps_revision']) ?></div>
                        <div class="kpi-card__label">Total Revision Time</div>
                    </div>
                </div>
                
                <!-- Graphiques -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 400px), 1fr)); gap: 24px; margin-bottom: 32px;">
                    <!-- Graphique: Nombre d'élèves par série -->
                    <div class="admin-chart-card">
                        <div style="padding: 16px; border-bottom: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center;">
                            <h3 style="font-size: 16px; font-weight: 600;">Élèves par série</h3>
                        </div>
                        <div style="padding: 24px;">
                            <canvas id="chartElevesParSerie" style="max-height: 300px;"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Tableaux -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 400px), 1fr)); gap: 24px;">
                    <div class="admin-table">
                        <div style="padding: 16px; border-bottom: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center;">
                            <h3 style="font-size: 16px; font-weight: 600;">Dernières inscriptions</h3>
                            <a href="users.php" style="font-size: 13px; color: var(--color-primary);">View All</a>
                        </div>
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Series</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dernieres_inscriptions as $user): ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--color-primary-bg); display: flex; align-items: center; justify-content: center; color: var(--color-primary); font-weight: 600; font-size: 12px;">
                                                <?= strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div style="font-weight: 600;"><?= e($user['prenom'] . ' ' . $user['nom']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= e($user['serie']) ?></td>
                                    <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                    <td>
                                        <span style="padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; background: <?= $user['is_active'] ? '#ECFDF5' : '#F3F4F6' ?>; color: <?= $user['is_active'] ? '#059669' : '#6B7280' ?>;">
                                            <?= $user['is_active'] ? 'ACTIVE' : 'PENDING' ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="admin-table">
                        <div style="padding: 16px; border-bottom: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center;">
                            <h3 style="font-size: 16px; font-weight: 600;">Ressources récentes</h3>
                            <a href="ressources.php" style="font-size: 13px; color: var(--color-primary);">Manage All</a>
                        </div>
                        <table>
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Type</th>
                                    <th>Subject</th>
                                    <th>Views</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ressources_recentes as $ressource): ?>
                                <tr>
                                    <td style="font-weight: 600;"><?= e($ressource['titre']) ?></td>
                                    <td>
                                        <span class="badge badge-<?= $ressource['type'] ?>"><?= strtoupper($ressource['type']) ?></span>
                                    </td>
                                    <td><?= e($ressource['matiere']) ?></td>
                                    <td><?= number_format($ressource['nb_vues']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>lucide.createIcons();</script>
    <script>
        // Données pour le graphique des élèves par série
        const elevesParSerieData = <?= json_encode($eleves_par_serie) ?>;
        
        // Vérifier qu'il y a des données
        if (elevesParSerieData && elevesParSerieData.length > 0) {
            // Préparer les données pour Chart.js
            const seriesLabels = elevesParSerieData.map(item => item.serie);
            const seriesCounts = elevesParSerieData.map(item => parseInt(item.nombre) || 0);
            const seriesColors = elevesParSerieData.map(item => item.couleur || '#8B52FA');
            
            // Créer le graphique en barres
            const ctx = document.getElementById('chartElevesParSerie').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: seriesLabels,
                    datasets: [{
                        label: 'Nombre d\'élèves',
                        data: seriesCounts,
                        backgroundColor: seriesColors,
                        borderColor: seriesColors,
                        borderWidth: 1,
                        borderRadius: 6,
                        borderSkipped: false,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            titleFont: {
                                size: 14,
                                weight: '600'
                            },
                            bodyFont: {
                                size: 13
                            },
                            callbacks: {
                                label: function(context) {
                                    return 'Élèves: ' + context.parsed.y;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                font: {
                                    size: 12
                                },
                                color: '#6B7280'
                            },
                            grid: {
                                color: '#E5E7EB',
                                drawBorder: false
                            }
                        },
                        x: {
                            ticks: {
                                font: {
                                    size: 12
                                },
                                color: '#6B7280'
                            },
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        } else {
            // Afficher un message si aucune donnée
            const canvas = document.getElementById('chartElevesParSerie');
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#6B7280';
            ctx.font = '14px Inter';
            ctx.textAlign = 'center';
            ctx.fillText('Aucune donnée disponible', canvas.width / 2, canvas.height / 2);
        }
    </script>
    <script src="../assets/js/admin.js"></script>
</body>
</html>

