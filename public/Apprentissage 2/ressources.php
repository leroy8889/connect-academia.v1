<?php
/**
 * Connect'Academia - Ressources d'une matière
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$matiere_id = intval($_GET['matiere'] ?? 0);
$type_filter = $_GET['type'] ?? 'tous';
$pdo = getDB();
$user_id = $_SESSION['user_id'];

if ($matiere_id < 1) {
    header('Location: matieres.php');
    exit;
}

// Récupérer la matière
$stmt = $pdo->prepare("
    SELECT m.id, m.nom, m.icone, s.nom as serie_nom
    FROM matieres m
    JOIN series s ON s.id = m.serie_id
    WHERE m.id = ? AND m.is_active = 1
");
$stmt->execute([$matiere_id]);
$matiere = $stmt->fetch();

if (!$matiere) {
    header('Location: matieres.php');
    exit;
}

// Construire la requête des ressources
$sql = "
    SELECT r.id, r.titre, r.type, r.description, r.nb_vues, r.created_at,
           ch.titre as chapitre,
           p.statut, p.pourcentage, p.derniere_page,
           (SELECT COUNT(*) FROM favoris f WHERE f.user_id = ? AND f.ressource_id = r.id) as est_favori
    FROM ressources r
    LEFT JOIN chapitres ch ON ch.id = r.chapitre_id
    LEFT JOIN progressions p ON p.ressource_id = r.id AND p.user_id = ?
    WHERE r.matiere_id = ? AND r.is_deleted = 0
";

$params = [$user_id, $user_id, $matiere_id];

if ($type_filter !== 'tous') {
    $sql .= " AND r.type = ?";
    $params[] = $type_filter;
}

$sql .= " ORDER BY r.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ressources = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($matiere['nom']) ?> — Connect'Academia</title>
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
                    <h1 style="margin-bottom: 8px;"><?= e($matiere['nom']) ?></h1>
                    <p style="color: var(--color-text-light);">
                        <span class="badge badge-serie-<?= $matiere['serie_nom'] ?>">Terminale <?= e($matiere['serie_nom']) ?></span>
                        <span style="margin-left: 12px;"><?= count($ressources) ?> ressources disponibles</span>
                    </p>
                </div>
                
                <!-- Onglets filtres -->
                <div class="filter-tabs" style="display: flex; gap: 8px; margin-bottom: 24px; border-bottom: 1px solid var(--color-border); overflow-x: auto; -webkit-overflow-scrolling: touch;">
                    <a href="?matiere=<?= $matiere_id ?>&type=tous" 
                       class="<?= $type_filter === 'tous' ? 'active' : '' ?>"
                       style="padding: 12px 20px; border-bottom: 2px solid <?= $type_filter === 'tous' ? 'var(--color-primary)' : 'transparent' ?>; color: <?= $type_filter === 'tous' ? 'var(--color-primary)' : 'var(--color-text-light)' ?>; font-weight: <?= $type_filter === 'tous' ? '600' : '400' ?>; text-decoration: none;">
                        Tous
                    </a>
                    <a href="?matiere=<?= $matiere_id ?>&type=cours"
                       style="padding: 12px 20px; border-bottom: 2px solid <?= $type_filter === 'cours' ? 'var(--color-primary)' : 'transparent' ?>; color: <?= $type_filter === 'cours' ? 'var(--color-primary)' : 'var(--color-text-light)' ?>; font-weight: <?= $type_filter === 'cours' ? '600' : '400' ?>; text-decoration: none;">
                        Cours
                    </a>
                    <a href="?matiere=<?= $matiere_id ?>&type=td"
                       style="padding: 12px 20px; border-bottom: 2px solid <?= $type_filter === 'td' ? 'var(--color-primary)' : 'transparent' ?>; color: <?= $type_filter === 'td' ? 'var(--color-primary)' : 'var(--color-text-light)' ?>; font-weight: <?= $type_filter === 'td' ? '600' : '400' ?>; text-decoration: none;">
                        Travaux Dirigés
                    </a>
                    <a href="?matiere=<?= $matiere_id ?>&type=ancienne_epreuve"
                       style="padding: 12px 20px; border-bottom: 2px solid <?= $type_filter === 'ancienne_epreuve' ? 'var(--color-primary)' : 'transparent' ?>; color: <?= $type_filter === 'ancienne_epreuve' ? 'var(--color-primary)' : 'var(--color-text-light)' ?>; font-weight: <?= $type_filter === 'ancienne_epreuve' ? '600' : '400' ?>; text-decoration: none;">
                        Anciennes Épreuves
                    </a>
                </div>
                
                <!-- Grille de ressources -->
                <div class="grid-cards">
                    <?php foreach ($ressources as $ressource): ?>
                    <div class="resource-card">
                        <div class="resource-card__icon">
                            <i data-lucide="file-text"></i>
                        </div>
                        <div class="resource-card__title"><?= e($ressource['titre']) ?></div>
                        <div class="resource-card__meta">
                            <span class="badge badge-<?= $ressource['type'] ?>"><?= ucfirst(str_replace('_', ' ', $ressource['type'])) ?></span>
                            <?php if ($ressource['chapitre']): ?>
                                <span style="margin-left: 8px;"><?= e($ressource['chapitre']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($ressource['statut']): ?>
                        <div style="margin-top: 12px;">
                            <div class="progress-bar-container">
                                <div class="progress-bar-fill" style="width: <?= $ressource['pourcentage'] ?>%"></div>
                            </div>
                            <div style="font-size: 12px; color: var(--color-text-light); margin-top: 4px;">
                                <?php if ($ressource['statut'] === 'termine'): ?>
                                    ✅ Terminé
                                <?php else: ?>
                                    En cours — <?= $ressource['pourcentage'] ?>%
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="resource-card__footer">
                            <button onclick="toggleFavori(<?= $ressource['id'] ?>, this)" 
                                    class="<?= $ressource['est_favori'] ? 'active' : '' ?>"
                                    style="background: none; border: none; cursor: pointer; color: <?= $ressource['est_favori'] ? '#FCD34D' : 'var(--color-text-light)' ?>;"
                                    title="<?= $ressource['est_favori'] ? 'Retirer des favoris' : 'Ajouter aux favoris' ?>">
                                <i data-lucide="star" style="width: 20px; height: 20px;"></i>
                            </button>
                            <a href="viewer.php?ressource=<?= $ressource['id'] ?>" class="btn-primary" style="font-size: 13px; padding: 8px 16px;">
                                Consulter
                            </a>
                        </div>
                    </div>
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

