<?php use Core\Session; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= Session::getCsrfToken() ?>">
    <title><?= htmlspecialchars($pageTitle ?? 'Admin — StudyLink') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
    <script>
        window.STUDYLINK_ADMIN = {
            baseUrl: '<?= BASE_URL ?>',
            csrfToken: '<?= Session::getCsrfToken() ?>',
            userId: <?= Session::userId() ?? 'null' ?>,
            statsEndpoint: '<?= url('/admin/api/stats') ?>',
            chartData: <?= json_encode($chartData ?? null, JSON_UNESCAPED_UNICODE) ?>,
            subjectActivity: <?= json_encode($subjectActivity ?? null, JSON_UNESCAPED_UNICODE) ?>,
        };
    </script>
</head>
<body class="admin-body">
    <!-- ── SIDEBAR ──────────────────────────── -->
    <aside class="admin-sidebar" id="admin-sidebar">
        <div class="admin-sidebar__header">
            <div class="admin-sidebar__logo">
                <div class="admin-sidebar__logo-icon">
                    <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                        <rect width="32" height="32" rx="8" fill="#8B52FA"/>
                        <path d="M9 16L14 21L23 11" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="admin-sidebar__logo-text">
                    <span class="admin-sidebar__brand">StudyLink</span>
                    <span class="admin-sidebar__subtitle">ADMIN CONSOLE</span>
                </div>
            </div>
        </div>

        <nav class="admin-sidebar__nav">
            <div class="admin-sidebar__section">
                <span class="admin-sidebar__section-title">MAIN MENU</span>
                <a href="<?= url('/admin') ?>" class="admin-sidebar__link <?= ($activePage ?? '') === 'dashboard' ? 'admin-sidebar__link--active' : '' ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <rect x="3" y="3" width="7" height="7" rx="1.5"/>
                        <rect x="14" y="3" width="7" height="7" rx="1.5"/>
                        <rect x="3" y="14" width="7" height="7" rx="1.5"/>
                        <rect x="14" y="14" width="7" height="7" rx="1.5"/>
                    </svg>
                    <span>Dashboard</span>
                </a>
                <a href="<?= url('/admin/users') ?>" class="admin-sidebar__link <?= ($activePage ?? '') === 'users' ? 'admin-sidebar__link--active' : '' ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    <span>Users</span>
                </a>
                <a href="<?= url('/admin/subjects') ?>" class="admin-sidebar__link <?= ($activePage ?? '') === 'subjects' ? 'admin-sidebar__link--active' : '' ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                        <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                    </svg>
                    <span>Subjects</span>
                </a>
                <a href="<?= url('/admin/reports') ?>" class="admin-sidebar__link <?= ($activePage ?? '') === 'reports' ? 'admin-sidebar__link--active' : '' ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M21.21 15.89A10 10 0 1 1 8 2.83"/>
                        <path d="M22 12A10 10 0 0 0 12 2v10z"/>
                    </svg>
                    <span>Reports</span>
                </a>
            </div>

            <div class="admin-sidebar__section">
                <span class="admin-sidebar__section-title">SYSTEM</span>
                <a href="<?= url('/admin/settings') ?>" class="admin-sidebar__link <?= ($activePage ?? '') === 'settings' ? 'admin-sidebar__link--active' : '' ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <circle cx="12" cy="12" r="3"/>
                        <path d="M12 1v2m0 18v2M4.22 4.22l1.42 1.42m12.72 12.72l1.42 1.42M1 12h2m18 0h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>
                    </svg>
                    <span>Settings</span>
                </a>
                <a href="<?= url('/admin/support') ?>" class="admin-sidebar__link <?= ($activePage ?? '') === 'support' ? 'admin-sidebar__link--active' : '' ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                    <span>Support</span>
                </a>
            </div>
        </nav>

        <div class="admin-sidebar__footer">
            <span class="admin-sidebar__footer-label">Logged in as</span>
            <div class="admin-sidebar__user">
                <img src="<?= htmlspecialchars(Session::get('user_photo', asset('images/default-avatar.svg'))) ?>"
                     alt="Admin" class="admin-sidebar__user-avatar">
                <div class="admin-sidebar__user-info">
                    <span class="admin-sidebar__user-name"><?= htmlspecialchars(Session::get('user_name', 'Admin')) ?></span>
                    <span class="admin-sidebar__user-role">Super Admin</span>
                </div>
                <form action="<?= url('/admin/logout') ?>" method="POST" style="margin-left:auto;">
                    <input type="hidden" name="_csrf_token" value="<?= Session::getCsrfToken() ?>">
                    <button type="submit" class="admin-sidebar__logout-btn" title="Se déconnecter">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                            <polyline points="16 17 21 12 16 7"/>
                            <line x1="21" y1="12" x2="9" y2="12"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <!-- ── MAIN AREA ────────────────────────── -->
    <div class="admin-main">
        <!-- ── TOP HEADER ───────────────────── -->
        <header class="admin-header">
            <div class="admin-header__left">
                <button class="admin-header__menu-toggle" id="sidebar-toggle">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="3" y1="12" x2="21" y2="12"/>
                        <line x1="3" y1="6" x2="21" y2="6"/>
                        <line x1="3" y1="18" x2="21" y2="18"/>
                    </svg>
                </button>
                <h1 class="admin-header__title"><?= htmlspecialchars($headerTitle ?? 'Dashboard Overview') ?></h1>
                <span class="admin-header__separator">|</span>
                <div class="admin-header__date">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                    <span id="header-date-range"><?= date('M 1') ?> - <?= date('M t, Y') ?></span>
                </div>
            </div>
            <div class="admin-header__right">
                <div class="admin-header__search">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="m21 21-4.35-4.35"/>
                    </svg>
                    <input type="text" placeholder="Search analytics..." class="admin-header__search-input">
                </div>
                <button class="admin-header__notif-btn" id="admin-notif-btn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                    <?php if (!empty($stats['pending_reports']) && $stats['pending_reports'] > 0): ?>
                        <span class="admin-header__notif-badge"><?= $stats['pending_reports'] ?></span>
                    <?php endif; ?>
                </button>
                <button class="admin-header__export-btn" id="export-report-btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="7 10 12 15 17 10"/>
                        <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                    Export Report
                </button>
            </div>
        </header>

        <!-- ── CONTENT ──────────────────────── -->
        <main class="admin-content">
            <?= $content ?>
        </main>
    </div>

    <!-- ── TOAST ────────────────────────────── -->
    <div class="admin-toast-container" id="admin-toast-container"></div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="<?= asset('js/admin.js') ?>"></script>
</body>
</html>

