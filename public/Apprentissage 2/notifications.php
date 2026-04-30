<?php
/**
 * Connect'Academia - Notifications
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$pdo = getDB();
$user_id = $_SESSION['user_id'];

// Récupérer les notifications
$stmt = $pdo->prepare("
    SELECT id, titre, message, type, lu, created_at
    FROM notifications
    WHERE (user_id = ? OR user_id IS NULL)
    ORDER BY created_at DESC
    LIMIT 50
");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications — Connect'Academia</title>
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
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; margin-bottom: 32px;">
                    <h1>Notifications</h1>
                    <button class="btn-secondary">Marquer tout comme lu</button>
                </div>
                
                <?php if (empty($notifications)): ?>
                    <div class="empty-state">
                        <div class="empty-state__icon">
                            <i data-lucide="bell"></i>
                        </div>
                        <h3>Aucune notification</h3>
                        <p>Vous n'avez pas encore de notifications.</p>
                    </div>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <?php foreach ($notifications as $notif): ?>
                        <div style="background: var(--color-white); border-radius: var(--radius-lg); padding: 16px; box-shadow: 0 2px 8px var(--color-shadow); <?= !$notif['lu'] ? 'border-left: 4px solid var(--color-primary);' : '' ?>">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; margin-bottom: 4px;"><?= e($notif['titre']) ?></div>
                                    <div style="font-size: 14px; color: var(--color-text-light); margin-bottom: 8px;"><?= e($notif['message']) ?></div>
                                    <div style="font-size: 12px; color: var(--color-text-light);">
                                        <?= timeAgo($notif['created_at']) ?>
                                    </div>
                                </div>
                                <?php if (!$notif['lu']): ?>
                                <span style="width: 8px; height: 8px; background: var(--color-primary); border-radius: 50%; margin-top: 4px;"></span>
                                <?php endif; ?>
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

