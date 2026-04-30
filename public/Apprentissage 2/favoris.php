<?php
/**
 * Connect'Academia - Mes Favoris
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$pdo = getDB();
$user_id = $_SESSION['user_id'];

// Récupérer les favoris
$stmt = $pdo->prepare("
    SELECT r.id, r.titre, r.type, r.description, r.nb_vues,
           m.nom as matiere, s.nom as serie,
           p.statut, p.pourcentage
    FROM favoris f
    JOIN ressources r ON r.id = f.ressource_id
    JOIN matieres m ON m.id = r.matiere_id
    JOIN series s ON s.id = r.serie_id
    LEFT JOIN progressions p ON p.ressource_id = r.id AND p.user_id = ?
    WHERE f.user_id = ? AND r.is_deleted = 0
    ORDER BY f.created_at DESC
");
$stmt->execute([$user_id, $user_id]);
$favoris = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Favoris — Connect'Academia</title>
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
                <h1 style="margin-bottom: 32px;">Mes Favoris</h1>
                
                <?php if (empty($favoris)): ?>
                    <div class="empty-state">
                        <div class="empty-state__icon">
                            <i data-lucide="star"></i>
                        </div>
                        <h3>Aucun favori</h3>
                        <p>Vous n'avez pas encore de favoris. Commencez à réviser !</p>
                    </div>
                <?php else: ?>
                    <div class="grid-cards">
                        <?php foreach ($favoris as $ressource): ?>
                        <div class="resource-card">
                            <div class="resource-card__icon">
                                <i data-lucide="file-text"></i>
                            </div>
                            <div class="resource-card__title"><?= e($ressource['titre']) ?></div>
                            <div class="resource-card__meta">
                                <span class="badge badge-<?= $ressource['type'] ?>"><?= ucfirst(str_replace('_', ' ', $ressource['type'])) ?></span>
                                <span style="margin-left: 8px;"><?= e($ressource['matiere']) ?></span>
                            </div>
                            <?php if ($ressource['statut']): ?>
                            <div style="margin-top: 12px;">
                                <div class="progress-bar-container">
                                    <div class="progress-bar-fill" style="width: <?= $ressource['pourcentage'] ?>%"></div>
                                </div>
                                <div style="font-size: 12px; color: var(--color-text-light); margin-top: 4px;">
                                    <?= $ressource['pourcentage'] ?>% complété
                                </div>
                            </div>
                            <?php endif; ?>
                            <div class="resource-card__footer">
                                <button onclick="toggleFavori(<?= $ressource['id'] ?>, this)" 
                                        class="active"
                                        style="background: none; border: none; cursor: pointer; color: #FCD34D;"
                                        title="Retirer des favoris">
                                    <i data-lucide="star" style="width: 20px; height: 20px;"></i>
                                </button>
                                <a href="viewer.php?ressource=<?= $ressource['id'] ?>" class="btn-primary" style="font-size: 13px; padding: 8px 16px;">
                                    Consulter
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>lucide.createIcons();</script>
</body>
</html>

