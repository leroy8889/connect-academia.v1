<?php
/**
 * Connect'Academia - Ma Progression
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$pdo = getDB();
$user_id = $_SESSION['user_id'];

// Progression globale
$stmt = $pdo->prepare("
    SELECT AVG(pourcentage) as moyenne
    FROM progressions
    WHERE user_id = ?
");
$stmt->execute([$user_id]);
$progression_globale = round($stmt->fetchColumn() ?? 0);

// Temps total cette semaine
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(duree_secondes), 0) as temps
    FROM sessions_revision
    WHERE user_id = ? AND debut >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$stmt->execute([$user_id]);
$temps_semaine = $stmt->fetchColumn();

// Progression par matière
$stmt = $pdo->prepare("
    SELECT m.id, m.nom, m.icone,
           COUNT(DISTINCT r.id) as total_ressources,
           COUNT(DISTINCT CASE WHEN p.statut = 'termine' THEN r.id END) as terminees,
           COUNT(DISTINCT CASE WHEN p.statut = 'en_cours' THEN r.id END) as en_cours,
           AVG(p.pourcentage) as progression_moyenne
    FROM matieres m
    JOIN ressources r ON r.matiere_id = m.id AND r.is_deleted = 0
    LEFT JOIN progressions p ON p.ressource_id = r.id AND p.user_id = ?
    WHERE m.serie_id = (SELECT serie_id FROM users WHERE id = ?)
    GROUP BY m.id
    ORDER BY m.ordre ASC
");
$stmt->execute([$user_id, $user_id]);
$progression_matieres = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ma Progression — Connect'Academia</title>
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
                <h1 style="margin-bottom: 32px;">Ma Progression</h1>
                
                <!-- Récapitulatif global -->
                <div class="kpi-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr)); gap: 20px; margin-bottom: 40px;">
                    <div class="kpi-card" style="text-align: center;">
                        <div style="width: 120px; height: 120px; margin: 0 auto 16px; border-radius: 50%; background: conic-gradient(var(--color-primary) <?= $progression_globale ?>%, var(--color-primary-bg) 0%); display: flex; align-items: center; justify-content: center;">
                            <div style="font-size: 32px; font-weight: 700; color: #FFF;"><?= $progression_globale ?>%</div>
                        </div>
                        <div class="kpi-card__label">Progression Globale</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-card__icon">
                            <i data-lucide="clock"></i>
                        </div>
                        <div class="kpi-card__value"><?= formatDuration($temps_semaine) ?></div>
                        <div class="kpi-card__label">Temps de révision (semaine)</div>
                    </div>
                </div>
                
                <!-- Progression par matière -->
                <div>
                    <h2 style="margin-bottom: 24px;">Progression par matière</h2>
                    <div style="display: flex; flex-direction: column; gap: 16px;">
                        <?php foreach ($progression_matieres as $matiere): ?>
                        <div class="matiere-progress-card" style="background: var(--color-white); border-radius: var(--radius-lg); padding: 20px; box-shadow: 0 2px 8px var(--color-shadow);">
                            <div class="matiere-progress-header" style="display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; flex-wrap: wrap;">
                                <div style="display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0;">
                                    <div style="width: 40px; height: 40px; flex-shrink: 0; background: var(--color-primary-bg); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--color-primary);">
                                        <i data-lucide="<?= e($matiere['icone']) ?>"></i>
                                    </div>
                                    <div style="min-width: 0;">
                                        <div style="font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= e($matiere['nom']) ?></div>
                                        <div style="font-size: 12px; color: var(--color-text-light);">
                                            <?= $matiere['terminees'] ?> terminées · <?= $matiere['en_cours'] ?> en cours · <?= $matiere['total_ressources'] ?> total
                                        </div>
                                    </div>
                                </div>
                                <div style="font-size: 20px; font-weight: 700; color: var(--color-primary); flex-shrink: 0;">
                                    <?= round($matiere['progression_moyenne'] ?? 0) ?>%
                                </div>
                            </div>
                            <div class="progress-bar-container lg">
                                <div class="progress-bar-fill" style="width: <?= round($matiere['progression_moyenne'] ?? 0) ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>lucide.createIcons();</script>
</body>
</html>

