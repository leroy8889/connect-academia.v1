<?php use Core\Session; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= Session::getCsrfToken() ?>">
    <title><?= htmlspecialchars($pageTitle ?? 'StudyLink') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/main.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/feed.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/comments.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/profile.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/auth.css') ?>">
    <script>
        window.STUDYLINK_CONFIG = {
            baseUrl: '<?= BASE_URL ?>',
            csrfToken: '<?= Session::getCsrfToken() ?>',
            userId: <?= Session::userId() ?? 'null' ?>,
            userRole: '<?= Session::userRole() ?? '' ?>',
            feedRefreshInterval: 30000,
        };
    </script>
</head>
<body>
    <!-- ── NAVBAR ──────────────────────────── -->
    <nav class="navbar">
        <div class="navbar__container">
        <div class="navbar__logo-icon">
                    
                </div>

            <div class="navbar__search">
                <svg class="navbar__search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                </svg>
                <input type="text" class="navbar__search-input" placeholder="Rechercher des ressources, sujets..." id="global-search">
            </div>

            <div class="navbar__actions">
                <button class="navbar__btn navbar__notif-btn" id="notif-btn" title="Notifications">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                    <?php if (!empty($unreadNotifs) && $unreadNotifs > 0): ?>
                        <span class="navbar__badge" id="notif-badge"><?= $unreadNotifs ?></span>
                    <?php else: ?>
                        <span class="navbar__badge hidden" id="notif-badge">0</span>
                    <?php endif; ?>
                </button>

                <a href="<?= url('/profile') ?>" class="navbar__avatar-link">
                    <img src="<?= htmlspecialchars(Session::get('user_photo', asset('images/default-avatar.svg'))) ?>"
                         alt="Mon profil"
                         class="navbar__avatar">
                </a>

                <!-- Bouton de déconnexion -->
                <form id="logout-form" action="<?= url('/logout') ?>" method="POST">
                    <input type="hidden" name="_csrf_token" value="<?= Session::getCsrfToken() ?>">
                    <button type="submit" class="navbar__btn navbar__logout-btn" id="logout-btn" title="Se déconnecter">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                            <polyline points="16 17 21 12 16 7"/>
                            <line x1="21" y1="12" x2="9" y2="12"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </nav>

    <!-- ── NOTIFICATIONS DROPDOWN ──────────── -->
    <div class="notif-dropdown hidden" id="notif-dropdown">
        <div class="notif-dropdown__header">
            <h3>Notifications</h3>
            <button class="notif-dropdown__read-all" id="notif-read-all">Tout marquer comme lu</button>
        </div>
        <div class="notif-dropdown__list" id="notif-list">
            <div class="notif-dropdown__empty">Aucune notification</div>
        </div>
    </div>

    <!-- ── MAIN CONTENT ───────────────────── -->
    <main class="main-content">
        <?= $content ?>
    </main>

    <!-- ── COMMENTS PANEL (Slide) ─────────── -->
    <div class="comments-overlay" id="comments-overlay"></div>
    <aside class="comments-panel" id="comments-panel">
        <div class="comments-panel__header">
            <h3 class="comments-panel__title">
                Commentaires
                <span class="comments-panel__count" id="comments-count"></span>
            </h3>
            <button class="comments-panel__close" id="comments-close-btn" aria-label="Fermer">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 6L6 18M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="comments-panel__sort">
            <button class="comments-panel__sort-btn comments-panel__sort-btn--active" data-sort="recent">Plus récents</button>
            <button class="comments-panel__sort-btn" data-sort="popular">Plus populaires</button>
            <button class="comments-panel__sort-btn" data-sort="oldest">Plus anciens</button>
        </div>

        <div class="comments-panel__list" id="comments-list">
            <!-- Commentaires chargés dynamiquement -->
        </div>

        <div class="comments-panel__input">
            <div class="comments-panel__reply-indicator hidden" id="reply-indicator">
                <span></span>
                <button class="comments-panel__reply-cancel" id="reply-cancel-btn" aria-label="Annuler">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 6L6 18M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="comments-panel__input-row">
                <img class="comments-panel__input-avatar"
                     src="<?= htmlspecialchars(Session::get('user_photo', asset('images/default-avatar.svg'))) ?>"
                     alt="Moi">
                <div class="comments-panel__input-wrapper">
                    <textarea class="comments-panel__textarea" id="comment-textarea"
                              placeholder="Écrivez un commentaire..." rows="1"></textarea>
                    <button class="comments-panel__send-btn" id="comment-send-btn" aria-label="Envoyer">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="m22 2-7 20-4-9-9-4z"/><path d="m22 2-10 10"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </aside>

    <!-- ── REPORT MODAL ───────────────────── -->
    <div class="modal-overlay hidden" id="report-modal-overlay">
        <div class="modal" id="report-modal">
            <div class="modal__header">
                <h3>Signaler cette publication</h3>
                <button class="modal__close" id="close-report-modal">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                    </svg>
                </button>
            </div>
            <div class="modal__body">
                <div class="modal__reasons">
                    <label class="modal__reason">
                        <input type="radio" name="report_reason" value="inappropriate">
                        <span>Contenu inapproprié</span>
                    </label>
                    <label class="modal__reason">
                        <input type="radio" name="report_reason" value="spam">
                        <span>Spam</span>
                    </label>
                    <label class="modal__reason">
                        <input type="radio" name="report_reason" value="harassment">
                        <span>Harcèlement</span>
                    </label>
                    <label class="modal__reason">
                        <input type="radio" name="report_reason" value="other">
                        <span>Autre</span>
                    </label>
                </div>
                <textarea class="modal__description" placeholder="Précisez la raison (optionnel)..." id="report-description"></textarea>
            </div>
            <div class="modal__footer">
                <button class="btn btn--ghost" id="cancel-report">Annuler</button>
                <button class="btn btn--primary" id="submit-report">Envoyer le signalement</button>
            </div>
        </div>
    </div>

    <!-- ── DEVELOPMENT MODAL ───────────────────── -->
    <div class="modal-overlay hidden" id="development-modal-overlay">
        <div class="modal" id="development-modal">
            <div class="modal__header">
                <h3>Fonctionnalité en cours de développement</h3>
                <button class="modal__close" id="close-development-modal">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                    </svg>
                </button>
            </div>
            <div class="modal__body">
                <div style="text-align: center; padding: var(--space-4) 0;">
                    <div style="font-size: 48px; margin-bottom: var(--space-4);">🚧</div>
                    <p style="font-size: var(--font-size-base); color: var(--color-gray-700); line-height: 1.6;">
                        Cette fonctionnalité est actuellement en cours de développement.<br>
                        Elle sera bientôt disponible !
                    </p>
                </div>
            </div>
            <div class="modal__footer">
                <button class="btn btn--primary btn--full" id="close-development-modal-btn">Compris</button>
            </div>
        </div>
    </div>

    <!-- ── TOAST ──────────────────────────── -->
    <div class="toast-container" id="toast-container"></div>

    <!-- ── MOBILE BOTTOM NAV ──────────────── -->
    <nav class="bottom-nav">
        <a href="<?= url('/') ?>" class="bottom-nav__item bottom-nav__item--active">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <span>Accueil</span>
        </a>
        <a href="<?= url('/explore') ?>" class="bottom-nav__item">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <span>Explorer</span>
        </a>
        <button class="bottom-nav__fab" id="mobile-create-post">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
        </button>
        <a href="#" class="bottom-nav__item" id="mobile-notif-btn">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <span>Notifs</span>
        </a>
        <a href="<?= url('/profile') ?>" class="bottom-nav__item">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span>Profil</span>
        </a>
    </nav>

    <script src="<?= asset('js/api.js') ?>"></script>
    <script src="<?= asset('js/app.js') ?>"></script>
    <script src="<?= asset('js/components/feed.js') ?>"></script>
    <script src="<?= asset('js/components/post-composer.js') ?>"></script>
    <script src="<?= asset('js/components/comments.js') ?>"></script>
    <script src="<?= asset('js/components/notifications.js') ?>"></script>
    <script src="<?= asset('js/components/follow.js') ?>"></script>
</body>
</html>

