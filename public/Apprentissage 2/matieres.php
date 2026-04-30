<?php
/**
 * Connect'Academia - Liste des Matières
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$serie_id = intval($_GET['serie'] ?? $_SESSION['user_serie']);
$pdo = getDB();
$user_id = $_SESSION['user_id'];

// Récupérer les matières de la série avec progression
$stmt = $pdo->prepare("
    SELECT m.id, m.nom, m.icone, m.ordre,
           COUNT(DISTINCT r.id) AS nb_ressources,
           COALESCE(AVG(p.pourcentage), 0) AS progression_moyenne
    FROM matieres m
    LEFT JOIN ressources r ON r.matiere_id = m.id AND r.is_deleted = 0
    LEFT JOIN progressions p ON p.ressource_id = r.id AND p.user_id = ?
    WHERE m.serie_id = ? AND m.is_active = 1
    GROUP BY m.id
    ORDER BY m.ordre ASC
");
$stmt->execute([$user_id, $serie_id]);
$matieres = $stmt->fetchAll();

// Récupérer la série
$stmt = $pdo->prepare("SELECT nom, description FROM series WHERE id = ?");
$stmt->execute([$serie_id]);
$serie = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Matières — Connect'Academia</title>
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
                <div style="margin-bottom: 32px;">
                    <h1 style="margin-bottom: 8px;">Matières — Terminale <?= e($serie['nom']) ?></h1>
                    <p style="color: var(--color-text-light);"><?= e($serie['description']) ?></p>
                </div>
                
                <div class="grid-cards">
                    <?php foreach ($matieres as $matiere): ?>
                    <a href="ressources.php?matiere=<?= $matiere['id'] ?>" class="resource-card" style="text-decoration: none;">
                        <div class="resource-card__icon">
                            <i data-lucide="<?= e($matiere['icone']) ?>"></i>
                        </div>
                        <div class="resource-card__title"><?= e($matiere['nom']) ?></div>
                        <div class="resource-card__meta">
                            <?= $matiere['nb_ressources'] ?> ressources disponibles
                        </div>
                        <div style="margin-top: 12px;">
                            <div class="progress-bar-container">
                                <div class="progress-bar-fill" style="width: <?= round($matiere['progression_moyenne']) ?>%"></div>
                            </div>
                            <div style="font-size: 12px; color: var(--color-text-light); margin-top: 4px;">
                                <?= round($matiere['progression_moyenne']) ?>% de progression
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>lucide.createIcons();</script>
</body>
</html>

