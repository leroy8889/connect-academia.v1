<?php
/**
 * Connect'Academia - Dashboard Élève
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$pdo = getDB();
$user_id = $_SESSION['user_id'];
$serie_id = $_SESSION['user_serie'];

// Récupérer les statistiques
$stats = [];

// Cours consultés
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT p.ressource_id) as consultes,
           COUNT(DISTINCT r.id) as total
    FROM progressions p
    RIGHT JOIN ressources r ON r.id = p.ressource_id
    WHERE r.serie_id = ? AND r.is_deleted = 0
");
$stmt->execute([$serie_id]);
$stats['cours'] = $stmt->fetch();

// Temps de révision cette semaine
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(duree_secondes), 0) as temps
    FROM sessions_revision
    WHERE user_id = ? AND debut >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$stmt->execute([$user_id]);
$stats['temps'] = $stmt->fetchColumn();

// Ressources terminées
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM progressions
    WHERE user_id = ? AND statut = 'termine'
");
$stmt->execute([$user_id]);
$stats['terminees'] = $stmt->fetchColumn();

// Matière favorite
$stmt = $pdo->prepare("
    SELECT m.nom, COUNT(*) as nb
    FROM progressions p
    JOIN ressources r ON r.id = p.ressource_id
    JOIN matieres m ON m.id = r.matiere_id
    WHERE p.user_id = ? AND p.temps_passe > 0
    GROUP BY m.id
    ORDER BY nb DESC
    LIMIT 1
");
$stmt->execute([$user_id]);
$stats['matiere_fav'] = $stmt->fetch();

// Ressources en cours (3 dernières)
$stmt = $pdo->prepare("
    SELECT r.id, r.titre, r.type, m.nom as matiere, p.pourcentage, p.derniere_page
    FROM progressions p
    JOIN ressources r ON r.id = p.ressource_id
    JOIN matieres m ON m.id = r.matiere_id
    WHERE p.user_id = ? AND p.statut = 'en_cours'
    ORDER BY p.updated_at DESC
    LIMIT 3
");
$stmt->execute([$user_id]);
$en_cours = $stmt->fetchAll();

// Ressources récentes
$stmt = $pdo->prepare("
    SELECT r.id, r.titre, r.type, r.description, m.nom as matiere, s.nom as serie,
           r.nb_vues, r.created_at
    FROM ressources r
    JOIN matieres m ON m.id = r.matiere_id
    JOIN series s ON s.id = r.serie_id
    WHERE r.is_deleted = 0
    ORDER BY r.created_at DESC
    LIMIT 6
");
$stmt->execute();
$recentes = $stmt->fetchAll();

// Récupérer la série de l'utilisateur
$stmt = $pdo->prepare("SELECT nom FROM series WHERE id = ?");
$stmt->execute([$serie_id]);
$serie_nom = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord — Connect'Academia</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/front.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="app-layout">
        <aside class="sidebar">
            <?php include __DIR__ . '/includes/partials/sidebar.php'; ?>
        </aside>
        
        <main class="main-content">
            <div class="page-container">
                <!-- Bannière de bienvenue -->
                <div style="margin-bottom: 32px;">
                    <h1 style="margin-bottom: 8px; font-family: var(--ff-montserrat); font-weight: 2.5rem;">Bonjour <?= e($_SESSION['user_nom']) ?> 👋</h1>
                    <p style="color: var(--color-text-light); margin-bottom: 12px;">Continuez là où vous vous êtes arrêté</p>
                    <span class="badge badge-serie-<?= $serie_nom ?>">Terminale <?= e($serie_nom) ?></span>
                </div>
                
                <!-- Cartes KPI -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr)); gap: 16px; margin-bottom: 40px;">
                    <div class="kpi-card">
                        <div class="kpi-card__icon">
                            <i data-lucide="book"></i>
                        </div>
                        <div class="kpi-card__value"><?= $stats['cours']['consultes'] ?? 0 ?> / <?= $stats['cours']['total'] ?? 0 ?></div>
                        <div class="kpi-card__label">Cours consultés</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-card__icon">
                            <i data-lucide="clock"></i>
                        </div>
                        <div class="kpi-card__value"><?= formatDuration($stats['temps']) ?></div>
                        <div class="kpi-card__label">Temps de révision (semaine)</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-card__icon">
                            <i data-lucide="check-circle"></i>
                        </div>
                        <div class="kpi-card__value"><?= $stats['terminees'] ?></div>
                        <div class="kpi-card__label">Ressources terminées</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-card__icon">
                            <i data-lucide="flame"></i>
                        </div>
                        <div class="kpi-card__value"><?= e($stats['matiere_fav']['nom'] ?? 'Aucune') ?></div>
                        <div class="kpi-card__label">Matière favorite</div>
                    </div>
                </div>
                
                <!-- Reprendre là où vous êtes -->
                <?php if (!empty($en_cours)): ?>
                <div style="margin-bottom: 40px;">
                    <h2 style="margin-bottom: 20px; font-family: var(--ff-montserrat);">Reprendre là où vous êtes arrêté</h2>
                    <div class="grid-cards">
                        <?php foreach ($en_cours as $ressource): ?>
                        <div class="resource-card">
                            <div class="resource-card__icon">
                                <i data-lucide="file-text"></i>
                            </div>
                            <div class="resource-card__title"><?= e($ressource['titre']) ?></div>
                            <div class="resource-card__meta">
                                <span class="badge badge-<?= $ressource['type'] ?>"><?= ucfirst($ressource['type']) ?></span>
                                <span style="margin-left: 8px;"><?= e($ressource['matiere']) ?></span>
                            </div>
                            <div style="margin-top: 12px;">
                                <div class="progress-bar-container">
                                    <div class="progress-bar-fill" style="width: <?= $ressource['pourcentage'] ?>%"></div>
                                </div>
                                <div style="font-size: 12px; color: var(--color-text-light); margin-top: 4px;">
                                    <?= $ressource['pourcentage'] ?>% complété
                                </div>
                            </div>
                            <div class="resource-card__footer">
                                <a href="viewer.php?ressource=<?= $ressource['id'] ?>" class="btn-primary" style="font-size: 13px; padding: 8px 16px;">
                                    Continuer
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Récemment ajoutés 
                <div>
                    <h2 style="margin-bottom: 20px;">Récemment ajoutés</h2>
                    <div class="grid-cards">
                        <?php foreach ($recentes as $ressource): ?>
                        <div class="resource-card">
                            <div class="resource-card__icon">
                                <i data-lucide="file-text"></i>
                            </div>
                            <div class="resource-card__title"><?= e($ressource['titre']) ?></div>
                            <div class="resource-card__meta">
                                <span class="badge badge-<?= $ressource['type'] ?>"><?= ucfirst($ressource['type']) ?></span>
                                <span style="margin-left: 8px;"><?= e($ressource['matiere']) ?></span>
                            </div>
                            <div class="resource-card__footer">
                                <a href="viewer.php?ressource=<?= $ressource['id'] ?>" class="btn-primary" style="font-size: 13px; padding: 8px 16px;">
                                    Consulter
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>-->
            </div>
        </main>
    </div>
    
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>lucide.createIcons();</script>
</body>
</html>

