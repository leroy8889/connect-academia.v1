<?php
/**
 * Connect'Academia - Lecteur PDF
 */
require_once __DIR__ . '/includes/config.php';
session_start();

// Permettre l'accès aux élèves ET aux admins
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    $redirect = urlencode($_SERVER['REQUEST_URI']);
    header('Location: login.php?redirect=' . $redirect);
    exit;
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$ressource_id = intval($_GET['ressource'] ?? 0);
$pdo = getDB();
// Utiliser user_id si élève, sinon null pour admin (pas de progression)
$user_id = $_SESSION['user_id'] ?? null;

if ($ressource_id < 1) {
    header('Location: dashboard.php');
    exit;
}

// Récupérer la ressource
if ($user_id) {
    // Pour les élèves : récupérer avec progression
    $stmt = $pdo->prepare("
        SELECT r.id, r.titre, r.type, r.description, r.fichier_path, r.nb_vues,
               m.nom as matiere, s.nom as serie,
               p.statut, p.pourcentage, p.derniere_page, p.temps_passe
        FROM ressources r
        JOIN matieres m ON m.id = r.matiere_id
        JOIN series s ON s.id = r.serie_id
        LEFT JOIN progressions p ON p.ressource_id = r.id AND p.user_id = ?
        WHERE r.id = ? AND r.is_deleted = 0
    ");
    $stmt->execute([$user_id, $ressource_id]);
} else {
    // Pour les admins : récupérer sans progression
    $stmt = $pdo->prepare("
        SELECT r.id, r.titre, r.type, r.description, r.fichier_path, r.nb_vues,
               m.nom as matiere, s.nom as serie,
               NULL as statut, NULL as pourcentage, NULL as derniere_page, NULL as temps_passe
        FROM ressources r
        JOIN matieres m ON m.id = r.matiere_id
        JOIN series s ON s.id = r.serie_id
        WHERE r.id = ? AND r.is_deleted = 0
    ");
    $stmt->execute([$ressource_id]);
}
$ressource = $stmt->fetch();

if (!$ressource) {
    header('Location: dashboard.php');
    exit;
}

// Récupérer les autres ressources de la matière
$stmt = $pdo->prepare("
    SELECT r.id, r.titre, r.type
    FROM ressources r
    WHERE r.matiere_id = (SELECT matiere_id FROM ressources WHERE id = ?)
    AND r.id != ? AND r.is_deleted = 0
    ORDER BY r.created_at DESC
    LIMIT 5
");
$stmt->execute([$ressource_id, $ressource_id]);
$autres_ressources = $stmt->fetchAll();

$derniere_page = $ressource['derniere_page'] ?? 1;
$progression = $ressource['pourcentage'] ?? 0;
$est_favori = false;

// Vérifier si favori (uniquement pour les élèves)
if ($user_id) {
    $stmt = $pdo->prepare("SELECT id FROM favoris WHERE user_id = ? AND ressource_id = ?");
    $stmt->execute([$user_id, $ressource_id]);
    $est_favori = (bool)$stmt->fetch();
}

// Construire l'URL du PDF via l'endpoint API sécurisé
$basePath = getBasePath();
$fichier_url = ($basePath ? $basePath . '/' : '') . 'api/pdf.php?id=' . $ressource_id;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($ressource['titre']) ?> — Connect'Academia</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/front.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    <style>
        .viewer-layout {
            display: flex;
            height: calc(100vh - 64px);
        }
        .viewer-sidebar {
            width: 280px;
            min-width: 220px;
            flex-shrink: 0;
            background: var(--color-white);
            border-right: 1px solid var(--color-border);
            padding: 24px;
            overflow-y: auto;
            transition: width 0.3s ease, padding 0.3s ease, min-width 0.3s ease;
        }
        .viewer-sidebar.sidebar-collapsed {
            width: 0;
            min-width: 0;
            padding: 0;
            overflow: hidden;
            border-right: none;
        }
        .viewer-main {
            flex: 1;
            min-width: 0;
            background: #525252;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .viewer-controls {
            background: var(--color-white);
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--color-border);
        }
        .viewer-controls__nav {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .viewer-controls__page {
            font-size: 14px;
            color: var(--color-text-light);
        }
        .viewer-controls__actions {
            display: flex;
            gap: 8px;
        }
        #pdf-canvas {
            flex: 1;
            min-height: 0;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        #pdf-viewer {
            flex: 1;
            width: 100%;
            min-height: 0;
            border: none;
            display: block;
        }
        .viewer-info {
            margin-bottom: 24px;
        }
        .viewer-info__title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .viewer-info__meta {
            font-size: 13px;
            color: var(--color-text-light);
            margin-bottom: 16px;
        }
        .viewer-progress {
            margin-bottom: 24px;
        }
        .viewer-timer {
            background: var(--color-primary-bg);
            padding: 12px;
            border-radius: var(--radius-md);
            text-align: center;
            margin-bottom: 24px;
        }
        .viewer-timer__label {
            font-size: 12px;
            color: var(--color-text-light);
            margin-bottom: 4px;
        }
        .viewer-timer__value {
            font-size: 20px;
            font-weight: 600;
            color: var(--color-primary);
        }
        .sidebar.hidden {
            transform: translateX(-100%);
        }
        .sidebar {
            transition: transform 0.3s ease;
        }
        .main-content {
            transition: margin-left 0.3s ease;
        }

        /* Annuler le padding-top sur la page viewer pour éviter double espace */
        @media (max-width: 768px) {
            .viewer-page {
                padding-top: 0 !important;
            }
            .viewer-page .viewer-breadcrumb {
                padding-left: 64px;
            }
        }

        /* Responsive viewer — tablette */
        @media (max-width: 1024px) and (min-width: 769px) {
            .viewer-sidebar {
                width: 240px;
                min-width: 200px;
                padding: 18px;
            }
        }

        /* Responsive viewer — mobile */
        @media (max-width: 768px) {
            .viewer-layout {
                flex-direction: column;
                height: calc(100vh - 64px);
            }
            /* Panneau d'infos : collapsed par défaut sur mobile */
            .viewer-sidebar {
                width: 100% !important;
                min-width: unset !important;
                flex-shrink: 0;
                max-height: 0;
                overflow: hidden;
                border-right: none;
                border-bottom: none;
                padding: 0 16px !important;
                transition: max-height 0.35s ease, padding 0.35s ease, border-bottom 0.35s ease;
            }
            /* Classe ouverte depuis JS */
            .viewer-sidebar.mobile-open {
                max-height: 260px;
                padding: 14px 16px !important;
                overflow-y: auto;
                border-bottom: 1px solid var(--color-border);
            }
            /* Annule la classe desktop sidebar-collapsed sur mobile */
            .viewer-sidebar.sidebar-collapsed {
                width: 100% !important;
            }
            /* Le PDF prend tout l'espace restant */
            .viewer-main {
                flex: 1;
                min-height: 0;
            }
            .viewer-controls {
                flex-wrap: wrap;
                gap: 6px;
                padding: 8px 12px;
            }
            .viewer-controls__nav,
            .viewer-controls__actions {
                gap: 6px;
            }
            .viewer-controls__actions .btn-primary span {
                display: none;
            }
            .viewer-info__title {
                font-size: 15px;
            }
            .viewer-timer {
                padding: 8px;
                margin-bottom: 12px;
            }
            .viewer-timer__value {
                font-size: 16px;
            }
            /* Bouton toggle infos : visible seulement sur mobile */
            #viewerInfoToggleBtn {
                display: inline-flex;
            }
        }

        @media (min-width: 769px) {
            /* Bouton toggle infos : caché sur desktop (sidebar toujours visible) */
            #viewerInfoToggleBtn {
                display: none;
            }
        }

        /* Interface de Chat IA */
        .chat-panel {
            position: fixed;
            bottom: 0;
            right: 0;
            width: 420px;
            height: 600px;
            background: var(--color-white);
            border-top-left-radius: var(--radius-lg);
            border-top-right-radius: var(--radius-lg);
            box-shadow: 0 -4px 24px rgba(0, 0, 0, 0.15);
            display: none;
            flex-direction: column;
            z-index: 1000;
            transform: translateY(100%);
            transition: transform 0.3s ease;
        }
        
        .chat-panel.open {
            display: flex;
            transform: translateY(0);
        }
        
        .chat-panel__header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--color-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: linear-gradient(135deg, var(--color-primary) 0%, #7540E0 100%);
            border-top-left-radius: var(--radius-lg);
            border-top-right-radius: var(--radius-lg);
        }
        
        .chat-panel__header-content {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .chat-panel__avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--color-white);
        }
        
        .chat-panel__header-text {
            flex: 1;
        }
        
        .chat-panel__title {
            font-size: 15px;
            font-weight: 600;
            color: var(--color-white);
            margin-bottom: 2px;
        }
        
        .chat-panel__subtitle {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.9);
        }
        
        .chat-panel__close {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--color-white);
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .chat-panel__close:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .chat-panel__messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            background: #F9FAFB;
        }
        
        .chat-message {
            display: flex;
            gap: 12px;
            animation: messageSlideIn 0.3s ease;
        }
        
        @keyframes messageSlideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .chat-message.user {
            flex-direction: row-reverse;
        }
        
        .chat-message__avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .chat-message.user .chat-message__avatar {
            background: var(--color-primary);
            color: var(--color-white);
        }
        
        .chat-message.assistant .chat-message__avatar {
            background: var(--color-primary-bg);
            color: var(--color-primary);
        }
        
        .chat-message__content {
            max-width: 75%;
            padding: 12px 16px;
            border-radius: var(--radius-md);
            font-size: 14px;
            line-height: 1.5;
        }
        
        .chat-message.user .chat-message__content {
            background: var(--color-primary);
            color: var(--color-white);
            border-bottom-right-radius: 4px;
        }
        
        .chat-message.assistant .chat-message__content {
            background: var(--color-white);
            color: var(--color-text);
            border: 1px solid var(--color-border);
            border-bottom-left-radius: 4px;
        }
        
        .chat-message__content p {
            margin: 0 0 8px 0;
        }
        
        .chat-message__content p:last-child {
            margin-bottom: 0;
        }
        
        .chat-message__content code {
            background: rgba(0, 0, 0, 0.05);
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        
        .chat-message.user .chat-message__content code {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .chat-message__loading {
            display: flex;
            gap: 4px;
            padding: 12px 16px;
            background: var(--color-white);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            border-bottom-left-radius: 4px;
        }
        
        .chat-message__loading-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--color-primary);
            animation: loadingDot 1.4s infinite;
        }
        
        .chat-message__loading-dot:nth-child(2) {
            animation-delay: 0.2s;
        }
        
        .chat-message__loading-dot:nth-child(3) {
            animation-delay: 0.4s;
        }
        
        @keyframes loadingDot {
            0%, 60%, 100% {
                opacity: 0.3;
                transform: scale(0.8);
            }
            30% {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        .chat-panel__input-container {
            padding: 16px;
            border-top: 1px solid var(--color-border);
            background: var(--color-white);
        }
        
        .chat-panel__form {
            display: flex;
            gap: 8px;
            align-items: flex-end;
        }
        
        .chat-panel__input {
            flex: 1;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            padding: 10px 14px;
            font-size: 14px;
            font-family: inherit;
            resize: none;
            max-height: 120px;
            overflow-y: auto;
            transition: border-color 0.2s;
        }
        
        .chat-panel__input:focus {
            outline: none;
            border-color: var(--color-primary);
        }
        
        .chat-panel__send {
            background: var(--color-primary);
            color: var(--color-white);
            border: none;
            border-radius: var(--radius-md);
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s;
            flex-shrink: 0;
        }
        
        .chat-panel__send:hover:not(:disabled) {
            background: #7540E0;
        }
        
        .chat-panel__send:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        @media (max-width: 768px) {
            .chat-panel {
                width: 100%;
                height: 100vh;
                border-radius: 0;
            }
            
            .chat-panel__header {
                border-radius: 0;
            }
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <aside class="sidebar">
            <?php include __DIR__ . '/includes/partials/sidebar.php'; ?>
        </aside>
        
        <main class="main-content viewer-page" style="height: 100vh;">
            <div class="viewer-breadcrumb" style="background: var(--color-white); border-bottom: 1px solid var(--color-border); padding: 12px 24px;">
                <div style="font-size: 13px; color: var(--color-text-light);">
                    Dashboard > <?= e($ressource['matiere']) ?> > <?= e($ressource['titre']) ?>
                </div>
            </div>
            
            <div class="viewer-layout">
                <div class="viewer-sidebar">
                    <div class="viewer-info">
                        <div class="viewer-info__title"><?= e($ressource['titre']) ?></div>
                        <div class="viewer-info__meta">
                            <span class="badge badge-<?= $ressource['type'] ?>"><?= ucfirst(str_replace('_', ' ', $ressource['type'])) ?></span>
                            <span style="margin-left: 8px;"><?= e($ressource['matiere']) ?></span>
                        </div>
                    </div>
                    
                    <?php if ($user_id): // Afficher progression et actions uniquement pour les élèves ?>
                    <div class="viewer-progress">
                        <div style="font-size: 13px; font-weight: 600; margin-bottom: 8px;">Progression</div>
                        <div class="progress-bar-container lg">
                            <div class="progress-bar-fill" id="progressBar" style="width: <?= $progression ?>%"></div>
                        </div>
                        <div class="viewer-timer">
                        <div class="viewer-timer__label">⏱️ Temps de révision</div>
                        <div class="viewer-timer__value" id="timerDisplay">00:00</div>
                    </div>
                        <div style="font-size: 12px; color: var(--color-text-light); margin-top: 4px;" id="progressText">
                            <?= $progression ?>%
                        </div>
                    </div>
                    
                    
                    <div style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 24px;">
                        <button onclick="markAsComplete()" class="btn-primary" id="completeBtn" style="width: 100%;">
                            <i data-lucide="check-circle"></i>
                            Marquer comme terminé
                        </button>
                        <button onclick="toggleFavori(<?= $ressource_id ?>, this)" 
                                class="btn-secondary" 
                                style="width: 100%; <?= $est_favori ? 'background: #FEF3C7; color: #D97706;' : '' ?>">
                            <i data-lucide="star"></i>
                            <?= $est_favori ? 'Retirer des favoris' : 'Ajouter aux favoris' ?>
                        </button>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($autres_ressources)): ?>
                    <div>
                        <div style="font-size: 13px; font-weight: 600; margin-bottom: 12px;">Autres ressources</div>
                        <?php foreach ($autres_ressources as $autre): ?>
                        <a href="viewer.php?ressource=<?= $autre['id'] ?>" 
                           style="display: block; padding: 8px; border-radius: var(--radius-md); margin-bottom: 8px; background: #F9FAFB; text-decoration: none; color: var(--color-text);">
                            <div style="font-size: 13px; font-weight: 600;"><?= e($autre['titre']) ?></div>
                            <div style="font-size: 11px; color: var(--color-text-light);">
                                <span class="badge badge-<?= $autre['type'] ?>"><?= ucfirst(str_replace('_', ' ', $autre['type'])) ?></span>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="viewer-main">
                    <div class="viewer-controls">
                        <div class="viewer-controls__nav">
                            <button onclick="toggleSidebar()" class="btn-secondary" style="padding: 6px 12px;" id="sidebarToggleBtn" title="Masquer/Afficher la barre latérale">
                                <i data-lucide="panel-left-close" id="sidebarToggleIcon"></i>
                            </button>
                            <button onclick="toggleViewerSidebar()" class="btn-secondary" style="padding: 6px 12px;" id="viewerInfoToggleBtn" title="Infos de la ressource">
                                <i data-lucide="info" id="viewerInfoToggleIcon"></i>
                            </button>
                            <span class="viewer-controls__page">PDF Viewer</span>
                        </div>
                        <div class="viewer-controls__actions">
                            <?php if ($user_id): // Afficher le bouton chat uniquement pour les élèves ?>
                            <button onclick="toggleChat()" class="btn-primary" style="padding: 6px 12px;" id="chatToggleBtn" title="Ouvrir l'assistant IA">
                                <i data-lucide="message-circle" id="chatToggleIcon"></i>
                                <span style="margin-left: 6px;">Assistant</span>
                            </button>
                            <?php endif; ?>
                            <button onclick="toggleFullscreen()" class="btn-secondary" style="padding: 6px 12px;">
                                <i data-lucide="maximize"></i>
                            </button>
                        </div>
                    </div>
                    <iframe id="pdf-viewer" src="<?= e($fichier_url) ?>"></iframe>
                </div>
            </div>
            
            <?php if ($user_id): // Afficher le chat uniquement pour les élèves ?>
            <!-- Interface de Chat IA -->
            <div id="chatPanel" class="chat-panel">
                <div class="chat-panel__header">
                    <div class="chat-panel__header-content">
                        <div class="chat-panel__avatar">
                            <i data-lucide="sparkles"></i>
                        </div>
                        <div class="chat-panel__header-text">
                            <div class="chat-panel__title">Assistant Connect'Acadrmia</div>
                            <div class="chat-panel__subtitle">Ton assistant pédagogique 🇬🇦</div>
                        </div>
                    </div>
                    <button onclick="toggleChat()" class="chat-panel__close" title="Fermer">
                        <i data-lucide="x"></i>
                    </button>
                </div>
                <div class="chat-panel__messages" id="chatMessages">
                    <!-- Les messages seront injectés ici par JavaScript -->
                </div>
                <div class="chat-panel__input-container">
                    <form id="chatForm" class="chat-panel__form">
                        <textarea 
                            id="chatInput" 
                            class="chat-panel__input" 
                            placeholder="Pose ta question sur ce document..."
                            rows="1"
                            maxlength="2000"></textarea>
                        <button type="submit" class="chat-panel__send" id="chatSendBtn" title="Envoyer">
                            <i data-lucide="send"></i>
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="assets/js/main.js"></script>
    <?php if ($user_id): ?>
    <script src="assets/js/chat.js"></script>
    <?php endif; ?>
    <script>
    lucide.createIcons();

    // Fonction pour toggle la sidebar
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        const toggleIcon = document.getElementById('sidebarToggleIcon');
        const isMobile = window.innerWidth <= 768;

        if (sidebar && mainContent) {
            const isHidden = sidebar.classList.contains('hidden');

            if (isHidden) {
                sidebar.classList.remove('hidden');
                if (!isMobile) mainContent.style.marginLeft = '240px';
                if (toggleIcon) toggleIcon.setAttribute('data-lucide', 'panel-left-close');
            } else {
                sidebar.classList.add('hidden');
                mainContent.style.marginLeft = '0';
                if (toggleIcon) toggleIcon.setAttribute('data-lucide', 'panel-left-open');
            }

            lucide.createIcons();
        }
    }

    // Toggle panneau d'infos de la ressource (viewer-sidebar)
    function toggleViewerSidebar() {
        const vSidebar = document.querySelector('.viewer-sidebar');
        const icon = document.getElementById('viewerInfoToggleIcon');
        const isMobile = window.innerWidth <= 768;

        if (!vSidebar) return;

        if (isMobile) {
            const isOpen = vSidebar.classList.contains('mobile-open');
            vSidebar.classList.toggle('mobile-open', !isOpen);
            if (icon) icon.setAttribute('data-lucide', isOpen ? 'info' : 'chevron-up');
        } else {
            const isCollapsed = vSidebar.classList.contains('sidebar-collapsed');
            vSidebar.classList.toggle('sidebar-collapsed', !isCollapsed);
            if (icon) icon.setAttribute('data-lucide', isCollapsed ? 'info' : 'panel-right-open');
        }
        lucide.createIcons();
    }

    // Fonctions de navigation PDF (désactivées car on utilise iframe)
    function previousPage() {
        // Navigation désactivée avec iframe
    }

    function nextPage() {
        // Navigation désactivée avec iframe
    }

    function zoomIn() {
        // Zoom désactivé avec iframe
    }

    function zoomOut() {
        // Zoom désactivé avec iframe
    }

    function toggleFullscreen() {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen();
        } else {
            document.exitFullscreen();
        }
    }

    async function markAsComplete() {
        <?php if (!$user_id): ?>
        return; // Seulement pour les élèves
        <?php endif; ?>
        
        try {
            const response = await fetch('api/progression.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'complete', ressource_id: <?= $ressource_id ?> })
            });
            
            const data = await response.json();
            if (data.success) {
                const progressBar = document.getElementById('progressBar');
                const progressText = document.getElementById('progressText');
                const completeBtn = document.getElementById('completeBtn');
                
                if (progressBar) progressBar.style.width = '100%';
                if (progressText) progressText.textContent = '100%';
                if (completeBtn) {
                    completeBtn.textContent = '✅ Terminé !';
                    completeBtn.disabled = true;
                }
                
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Succès', 'Ressource marquée comme terminée !', 'success');
                }
            }
        } catch (error) {
            console.error('Erreur complete:', error);
            if (typeof Swal !== 'undefined') {
                Swal.fire('Erreur', 'Impossible de marquer comme terminé', 'error');
            }
        }
    }

    <?php if ($user_id): ?>
    // Initialiser le chat
    initChat(<?= $ressource_id ?>, <?= json_encode($ressource['type'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, <?= json_encode(generateCsrfToken(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
    
    // ============================================================
    // GESTION DU CHRONOMÈTRE
    // ============================================================
    let startTime = null;
    let timerInterval = null;
    let heartbeatInterval = null;
    let tempsPasseInitial = <?= intval($ressource['temps_passe'] ?? 0) ?>;
    let dernierHeartbeatTime = 0;
    
    // Démarrer le chronomètre au chargement de la page
    async function initTimer() {
        try {
            // Démarrer une session de révision
            const response = await fetch('api/progression.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'start', ressource_id: <?= $ressource_id ?> })
            });
            
            const data = await response.json();
            if (data.success) {
                // Initialiser le temps de départ
                startTime = Date.now();
                dernierHeartbeatTime = 0;
                
                // Mettre à jour l'affichage du chronomètre toutes les secondes
                timerInterval = setInterval(updateTimerDisplay, 1000);
                updateTimerDisplay(); // Première mise à jour immédiate
                
                // Envoyer un heartbeat toutes les 30 secondes
                heartbeatInterval = setInterval(sendHeartbeat, 30000);
            }
        } catch (error) {
            console.error('Erreur initialisation timer:', error);
        }
    }
    
    // Mettre à jour l'affichage du chronomètre
    function updateTimerDisplay() {
        if (!startTime) return;
        
        const tempsEcouleSession = Math.floor((Date.now() - startTime) / 1000);
        const tempsTotal = tempsPasseInitial + tempsEcouleSession;
        
        const h = Math.floor(tempsTotal / 3600);
        const m = Math.floor((tempsTotal % 3600) / 60);
        const s = tempsTotal % 60;
        
        const timerDisplay = document.getElementById('timerDisplay');
        if (timerDisplay) {
            timerDisplay.textContent = 
                `${h > 0 ? h + 'h ' : ''}${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
        }
    }
    
    // Envoyer un heartbeat pour sauvegarder le temps
    async function sendHeartbeat() {
        if (!startTime) return;
        
        try {
            const tempsEcouleSession = Math.floor((Date.now() - startTime) / 1000);
            // Envoyer seulement le temps écoulé depuis le dernier heartbeat pour éviter la double comptabilisation
            const tempsDepuisDernierHeartbeat = tempsEcouleSession - dernierHeartbeatTime;
            
            if (tempsDepuisDernierHeartbeat > 0) {
                await fetch('api/progression.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'heartbeat',
                        ressource_id: <?= $ressource_id ?>,
                        temps: tempsDepuisDernierHeartbeat,
                        page_actuelle: 1,
                        total_pages: 1
                    })
                });
                
                // Mettre à jour le temps initial pour refléter le temps sauvegardé
                tempsPasseInitial += tempsDepuisDernierHeartbeat;
                dernierHeartbeatTime = tempsEcouleSession;
                startTime = Date.now(); // Réinitialiser le temps de départ pour la prochaine période
            }
        } catch (error) {
            console.error('Erreur heartbeat:', error);
        }
    }
    
    // Sauvegarder le temps à la fermeture de la page
    window.addEventListener('beforeunload', () => {
        if (startTime) {
            const tempsEcouleSession = Math.floor((Date.now() - startTime) / 1000);
            const tempsDepuisDernierHeartbeat = tempsEcouleSession - dernierHeartbeatTime;
            
            if (tempsDepuisDernierHeartbeat > 0) {
                // Utiliser sendBeacon pour fiabilité à la fermeture
                navigator.sendBeacon('api/progression.php', JSON.stringify({
                    action: 'end',
                    ressource_id: <?= $ressource_id ?>,
                    temps: tempsDepuisDernierHeartbeat,
                    page_actuelle: 1,
                    total_pages: 1
                }));
            }
        }
    });
    
    // Nettoyer les intervalles si nécessaire
    window.addEventListener('unload', () => {
        if (timerInterval) clearInterval(timerInterval);
        if (heartbeatInterval) clearInterval(heartbeatInterval);
    });
    
    // Initialiser le chronomètre au chargement
    initTimer();
    <?php endif; ?>
     </script>
</body>
</html>

