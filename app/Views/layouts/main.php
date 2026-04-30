<?php use Core\Session; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= Session::getCsrfToken() ?>">
  <title><?= e($pageTitle ?? "Connect'Academia") ?></title>
  <link rel="icon" href="<?= asset('images/logo-officiel.png') ?>" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset('css/global.css') ?>">
  <?php if (!empty($extraCss)): foreach ($extraCss as $css): ?>
  <link rel="stylesheet" href="<?= asset('css/' . $css) ?>">
  <?php endforeach; endif; ?>
  <script>
    window.CA = {
      baseUrl:    <?= json_encode(BASE_URL) ?>,
      csrfToken:  <?= json_encode(Session::getCsrfToken()) ?>,
      userId:     <?= Session::userId() ?? 'null' ?>,
      userRole:   <?= json_encode(Session::userRole() ?? '') ?>,
      userPhoto:  <?= json_encode(Session::get('user_photo', null) ?? '') ?>,
    };
  </script>
</head>
<body>

  <!-- ── NAVBAR ────────────────────────────────────────────────────────── -->
  <nav class="navbar" id="navbar">
    <div class="navbar__container">

      <!-- Logo -->
      <a href="<?= url('/hub') ?>" class="navbar__logo">
        <div class="navbar__logo-icon">
          <svg width="28" height="28" viewBox="0 0 40 40" fill="none">
            <path d="M8 28L20 10L32 28H8Z" fill="#8B52FA" fill-opacity="0.9"/>
            <circle cx="20" cy="20" r="5" fill="#8B52FA"/>
          </svg>
        </div>
        <span class="navbar__logo-text">Connect<span class="navbar__logo-accent">'</span>Academia</span>
      </a>

      <!-- Navigation centrale -->
      <div class="navbar__nav">
        <a href="<?= url('/hub') ?>" class="navbar__nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/hub') ? 'active' : '' ?>">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
          </svg>
          Hub
        </a>
        <a href="<?= url('/apprentissage') ?>" class="navbar__nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/apprentissage') ? 'active' : '' ?>">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
          </svg>
          Apprentissage
        </a>
        <a href="<?= url('/communaute') ?>" class="navbar__nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/communaute') ? 'active' : '' ?>">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
          </svg>
          Communauté
        </a>
        <a href="<?= BASE_URL ?>/public/orientation/orientation.html" class="navbar__nav-link">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/>
            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
          </svg>
          Orientation
        </a>
      </div>

      <!-- Actions droite -->
      <div class="navbar__actions">
        <!-- Notifications -->
        <button class="navbar__action-btn" id="notif-btn" aria-label="Notifications">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
          </svg>
          <span class="navbar__badge hidden" id="notif-badge">0</span>
        </button>

        <!-- Avatar -->
        <div class="navbar__user" id="user-menu-trigger">
          <img src="<?= e(Session::get('user_photo') ? url(Session::get('user_photo')) : asset('images/default-avatar.svg')) ?>"
               alt="<?= e(Session::get('user_name', 'Mon profil')) ?>"
               class="navbar__avatar"
               onerror="this.onerror=null;this.src=<?= htmlspecialchars(json_encode(asset('images/default-avatar.svg')), ENT_QUOTES) ?>">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="navbar__chevron">
            <polyline points="6 9 12 15 18 9"/>
          </svg>
        </div>

        <!-- Dropdown utilisateur -->
        <div class="navbar__user-dropdown hidden" id="user-dropdown">
          <div class="navbar__user-dropdown-header">
            <strong><?= e(Session::get('user_name', '')) ?></strong>
            <span><?= e(Session::userRole() ?? '') ?></span>
          </div>
          <a href="<?= url('/communaute/profil/' . Session::userId()) ?>" class="navbar__user-dropdown-item">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
            </svg>
            Mon profil
          </a>
          <a href="<?= url('/abonnement/choisir') ?>" class="navbar__user-dropdown-item">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>
            </svg>
            Abonnement
          </a>
          <div class="navbar__user-dropdown-divider"></div>
          <form action="<?= url('/auth/deconnexion') ?>" method="POST" class="navbar__logout-form">
            <input type="hidden" name="_csrf_token" value="<?= Session::getCsrfToken() ?>">
            <button type="submit" class="navbar__user-dropdown-item navbar__user-dropdown-item--danger">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
              </svg>
              Se déconnecter
            </button>
          </form>
        </div>
      </div>

      <!-- Bouton menu mobile -->
      <button class="navbar__hamburger" id="hamburger" aria-label="Menu">
        <span></span><span></span><span></span>
      </button>
    </div>
  </nav>

  <!-- Menu mobile -->
  <div class="mobile-nav hidden" id="mobile-nav">
    <a href="<?= url('/hub') ?>" class="mobile-nav__link">Hub</a>
    <a href="<?= url('/apprentissage') ?>" class="mobile-nav__link">Apprentissage</a>
    <a href="<?= url('/communaute') ?>" class="mobile-nav__link">Communauté</a>
    <a href="<?= BASE_URL ?>/public/orientation/orientation.html" class="mobile-nav__link">Orientation</a>
    <div class="mobile-nav__divider"></div>
    <a href="<?= url('/communaute/profil/' . Session::userId()) ?>" class="mobile-nav__link">Mon profil</a>
    <form action="<?= url('/auth/deconnexion') ?>" method="POST">
      <input type="hidden" name="_csrf_token" value="<?= Session::getCsrfToken() ?>">
      <button type="submit" class="mobile-nav__link mobile-nav__link--danger">Se déconnecter</button>
    </form>
  </div>
  <div class="mobile-nav-overlay hidden" id="mobile-nav-overlay"></div>

  <!-- ── NOTIFICATIONS DROPDOWN ──────────────────────────────────────────── -->
  <div class="notif-dropdown hidden" id="notif-dropdown">
    <div class="notif-dropdown__header">
      <h3>Notifications</h3>
      <button class="notif-dropdown__read-all" id="notif-read-all">Tout lire</button>
    </div>
    <div class="notif-dropdown__list" id="notif-list">
      <div class="notif-dropdown__empty">Aucune notification</div>
    </div>
  </div>
  <div class="notif-overlay hidden" id="notif-overlay"></div>

  <!-- ── CONTENU ─────────────────────────────────────────────────────────── -->
  <main class="main-content" id="main-content">
    <?= $content ?>
  </main>

  <!-- ── TOAST ──────────────────────────────────────────────────────────── -->
  <div class="toast-container" id="toast-container"></div>

  <script src="<?= asset('js/app.js') ?>"></script>
  <?php if (!empty($extraJs)): foreach ($extraJs as $js): ?>
  <script src="<?= asset('js/' . $js) ?>"></script>
  <?php endforeach; endif; ?>
</body>
</html>
