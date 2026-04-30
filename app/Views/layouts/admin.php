<?php
use Core\Session;

$adminName    = $_SESSION['admin_name']  ?? 'Admin';
$adminRole    = $_SESSION['admin_role']  ?? 'admin';
$adminInitial = strtoupper(mb_substr($adminName, 0, 1));
$currentUri   = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$baseUrl      = defined('BASE_URL') ? BASE_URL : '';

// Badge signalements en attente
$pendingReports = 0;
try {
    $pendingReports = (new \Models\Report())->count("status = 'pending'");
} catch (\Throwable $e) {}

function adminNavActive(string $path, string $current, string $base): string {
    $full = $base . $path;
    return ($current === $full || str_starts_with($current, $full . '/')) ? ' active' : '';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= Session::getCsrfToken() ?>">
  <title><?= e($pageTitle ?? "Admin — Connect'Academia") ?></title>
  <link rel="icon" href="<?= asset('images/logo-officiel.png') ?>" type="image/png">
  <link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
  <script>
    window.CA_ADMIN = {
      baseUrl: <?= json_encode($baseUrl) ?>,
      csrfToken: <?= json_encode(Session::getCsrfToken()) ?>,
      adminId: <?= Session::adminId() ?? 'null' ?>,
      adminRole: <?= json_encode($adminRole) ?>,
    };
  </script>
</head>
<body class="admin-body">

<!-- Overlay mobile -->
<div id="sidebar-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:150;"
     onclick="document.getElementById('admin-sidebar').classList.remove('open');this.style.display='none'"></div>

<div class="admin-layout">

  <!-- ── SIDEBAR ──────────────────────────────────────────── -->
  <aside class="admin-sidebar" id="admin-sidebar">

    <!-- Logo -->
    <div class="sidebar-logo">
      <div class="sidebar-logo-icon">
        <svg width="20" height="20" viewBox="0 0 40 40" fill="none">
          <path d="M8 28L20 10L32 28H8Z" fill="white" fill-opacity="0.9"/>
          <circle cx="20" cy="20" r="5" fill="white"/>
        </svg>
      </div>
      <div class="sidebar-logo-text">
        <strong>Connect'Academia</strong>
        <span>Admin console</span>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">

      <!-- PILOTAGE -->
      <div class="sidebar-section">
        <div class="sidebar-section-label">Pilotage</div>
        <a href="<?= url('/admin') ?>" class="sidebar-link<?= adminNavActive('/admin', $currentUri, $baseUrl) && !str_contains($currentUri, '/admin/') ? ' active' : (str_ends_with($currentUri, '/admin') ? ' active' : '') ?>">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
            <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
          </svg>
          <span>Dashboard</span>
        </a>
      </div>

      <!-- APPRENTISSAGE -->
      <div class="sidebar-section">
        <div class="sidebar-section-label">Apprentissage</div>
        <a href="<?= url('/admin/utilisateurs') ?>" class="sidebar-link<?= str_contains($currentUri, '/admin/utilisateurs') ? ' active' : '' ?>">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
          </svg>
          <span>Utilisateurs</span>
        </a>
        <a href="<?= url('/admin/series-matieres') ?>" class="sidebar-link<?= str_contains($currentUri, '/admin/series-matieres') ? ' active' : '' ?>">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <circle cx="12" cy="12" r="10"/>
            <line x1="2" y1="12" x2="22" y2="12"/>
            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
          </svg>
          <span>Séries &amp; Matières</span>
        </a>
        <a href="<?= url('/admin/contenu') ?>" class="sidebar-link<?= str_contains($currentUri, '/admin/contenu') ? ' active' : '' ?>">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
            <line x1="16" y1="13" x2="8" y2="13"/>
            <line x1="16" y1="17" x2="8" y2="17"/>
            <polyline points="10 9 9 9 8 9"/>
          </svg>
          <span>Ressources</span>
        </a>
      </div>

      <!-- COMMUNAUTÉ -->
      <div class="sidebar-section">
        <div class="sidebar-section-label">Communauté</div>
        <a href="<?= url('/admin/communaute') ?>" class="sidebar-link<?= str_contains($currentUri, '/admin/communaute') ? ' active' : '' ?>">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
          </svg>
          <span>Communauté</span>
        </a>
        <a href="<?= url('/admin/signalements') ?>" class="sidebar-link<?= str_contains($currentUri, '/admin/signalements') ? ' active' : '' ?>">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/>
            <line x1="4" y1="22" x2="4" y2="15"/>
          </svg>
          <span>Signalements</span>
          <?php if ($pendingReports > 0): ?>
            <span class="sidebar-badge"><?= $pendingReports ?></span>
          <?php endif; ?>
        </a>
      </div>

      <!-- SYSTÈME -->
      <div class="sidebar-section">
        <div class="sidebar-section-label">Système</div>
        <a href="<?= url('/admin/analytics') ?>" class="sidebar-link<?= str_contains($currentUri, '/admin/analytics') ? ' active' : '' ?>">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <line x1="18" y1="20" x2="18" y2="10"/>
            <line x1="12" y1="20" x2="12" y2="4"/>
            <line x1="6" y1="20" x2="6" y2="14"/>
          </svg>
          <span>Analytics</span>
        </a>
        <a href="<?= url('/admin/notifications') ?>" class="sidebar-link<?= str_contains($currentUri, '/admin/notifications') ? ' active' : '' ?>">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
          </svg>
          <span>Notifications</span>
        </a>
        <a href="<?= url('/admin/parametres') ?>" class="sidebar-link<?= str_contains($currentUri, '/admin/parametres') ? ' active' : '' ?>">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <circle cx="12" cy="12" r="3"/>
            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
          </svg>
          <span>Paramètres</span>
        </a>
      </div>

    </nav>

    <!-- Admin info -->
    <div class="sidebar-admin">
      <div class="sidebar-admin-avatar"><?= $adminInitial ?></div>
      <div class="sidebar-admin-info">
        <strong><?= e($adminName) ?></strong>
        <span><?= e(ucfirst(str_replace('_', ' ', $adminRole))) ?></span>
      </div>
    </div>

  </aside>

  <!-- ── MAIN ──────────────────────────────────────────────── -->
  <div class="admin-main">

    <!-- Topbar -->
    <header class="admin-topbar">

      <!-- Hamburger mobile -->
      <button id="sidebar-toggle" style="display:none;background:none;border:none;cursor:pointer;padding:4px;color:var(--txt-m);"
              onclick="document.getElementById('admin-sidebar').classList.toggle('open');document.getElementById('sidebar-overlay').style.display='block'">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>
        </svg>
      </button>

      <!-- Breadcrumb -->
      <div class="topbar-breadcrumb">
        <span class="bc-section"><?= e($breadcrumbSection ?? 'Admin') ?></span>
        <span class="bc-sep">·</span>
        <span class="bc-page"><?= e($breadcrumbPage ?? ($pageTitle ?? '')) ?></span>
      </div>

      <!-- Recherche -->
      <div class="topbar-search">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <input type="text" placeholder="Rechercher un élève, un cours, un post…" id="topbar-global-search">
      </div>

      <!-- Actions -->
      <div class="topbar-actions">
        <button class="btn-topbar-outline" onclick="window.print()">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="8 17 3 17 3 7 21 7 21 17 16 17"/>
            <polyline points="8 2 8 22 16 22 16 2"/>
          </svg>
          Exporter
        </button>
        <a href="<?= url('/admin/notifications') ?>" class="topbar-icon-btn" title="Notifications">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
          </svg>
        </a>
        <div class="topbar-avatar" title="<?= e($adminName) ?>"><?= $adminInitial ?></div>

        <!-- Logout rapide -->
        <form action="<?= url('/admin/logout') ?>" method="POST" style="margin:0;">
          <input type="hidden" name="_csrf_token" value="<?= Session::getCsrfToken() ?>">
          <button type="submit" class="topbar-icon-btn" title="Déconnexion" style="color:var(--red);">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
              <polyline points="16 17 21 12 16 7"/>
              <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
          </button>
        </form>
      </div>
    </header>

    <!-- Contenu -->
    <main class="admin-content">
      <?= $content ?>
    </main>

  </div><!-- .admin-main -->
</div><!-- .admin-layout -->

<script src="<?= asset('js/admin.js') ?>"></script>
<?php if (!empty($extraJs)): foreach ($extraJs as $js): ?>
<script src="<?= $js ?>"></script>
<?php endforeach; endif; ?>

<style>
@media (max-width: 768px) {
  #sidebar-toggle { display: block !important; }
}
</style>
</body>
</html>
