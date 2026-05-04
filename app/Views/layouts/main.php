<?php use Core\Session; ?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= Session::getCsrfToken() ?>">
  <title><?= e($pageTitle ?? "Connect'Academia") ?></title>
  <link rel="icon" href="<?= asset('images/logo-officiel.png') ?>" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="<?= asset('css/global.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/colors_and_type.css') ?>">
  <?php if (!empty($extraCss)): foreach ($extraCss as $css): ?>
  <?php if ($css === 'colors_and_type.css') continue; ?>
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
          <svg width="22" height="22" viewBox="0 0 40 40" fill="none">
            <path d="M8 28L20 10L32 28H8Z" fill="#8B52FA" fill-opacity="0.9"/>
            <circle cx="20" cy="20" r="5" fill="#8B52FA"/>
          </svg>
        </div>
        <span class="navbar__logo-text">Connect<span class="navbar__logo-accent">'</span>Academia</span>
      </a>

      <!-- Navigation centrale -->
      <div class="navbar__nav">
        <a href="<?= url('/hub') ?>" class="navbar__nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/hub') ? 'active' : '' ?>">
          <i data-lucide="home"></i>
          Hub
        </a>
        <a href="<?= url('/apprentissage') ?>" class="navbar__nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/apprentissage') ? 'active' : '' ?>">
          <i data-lucide="book-open"></i>
          Apprentissage
        </a>
        <a href="<?= url('/communaute') ?>" class="navbar__nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/communaute') ? 'active' : '' ?>">
          <i data-lucide="users"></i>
          Communauté
        </a>
        <a href="<?= BASE_URL ?>/public/orientation/orientation.html" class="navbar__nav-link">
          <i data-lucide="compass"></i>
          Orientation
        </a>
      </div>

      <!-- Actions droite -->
      <div class="navbar__actions">
        <!-- Notifications -->
        <button class="navbar__action-btn" id="notif-btn" aria-label="Notifications">
          <i data-lucide="bell"></i>
          <span class="navbar__badge hidden" id="notif-badge">0</span>
        </button>

        <!-- Avatar -->
        <div class="navbar__user" id="user-menu-trigger">
          <img src="<?= e(Session::get('user_photo') ? url(Session::get('user_photo')) : asset('images/default-avatar.svg')) ?>"
               alt="<?= e(Session::get('user_name', 'Mon profil')) ?>"
               class="navbar__avatar"
               onerror="this.onerror=null;this.src=<?= htmlspecialchars(json_encode(asset('images/default-avatar.svg')), ENT_QUOTES) ?>">
          <i data-lucide="chevron-down" class="navbar__chevron"></i>
        </div>

        <!-- Dropdown utilisateur -->
        <div class="navbar__user-dropdown hidden" id="user-dropdown">
          <div class="navbar__user-dropdown-header">
            <strong><?= e(Session::get('user_name', '')) ?></strong>
            <span><?= e(Session::userRole() ?? '') ?></span>
          </div>
          <a href="<?= url('/communaute/profil/' . Session::userId()) ?>" class="navbar__user-dropdown-item">
            <i data-lucide="user"></i>
            Mon profil
          </a>
          <a href="<?= url('/abonnement/choisir') ?>" class="navbar__user-dropdown-item">
            <i data-lucide="credit-card"></i>
            Abonnement
          </a>
          <div class="navbar__user-dropdown-divider"></div>
          <form action="<?= url('/auth/deconnexion') ?>" method="POST" class="navbar__logout-form">
            <input type="hidden" name="_csrf_token" value="<?= Session::getCsrfToken() ?>">
            <button type="submit" class="navbar__user-dropdown-item navbar__user-dropdown-item--danger">
              <i data-lucide="log-out"></i>
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
    <a href="<?= url('/hub') ?>" class="mobile-nav__link">
      <i data-lucide="home"></i> Hub
    </a>
    <a href="<?= url('/apprentissage') ?>" class="mobile-nav__link">
      <i data-lucide="book-open"></i> Apprentissage
    </a>
    <a href="<?= url('/communaute') ?>" class="mobile-nav__link">
      <i data-lucide="users"></i> Communauté
    </a>
    <a href="<?= BASE_URL ?>/public/orientation/orientation.html" class="mobile-nav__link">
      <i data-lucide="compass"></i> Orientation
    </a>
    <div class="mobile-nav__divider"></div>
    <a href="<?= url('/communaute/profil/' . Session::userId()) ?>" class="mobile-nav__link">
      <i data-lucide="user"></i> Mon profil
    </a>
    <form action="<?= url('/auth/deconnexion') ?>" method="POST">
      <input type="hidden" name="_csrf_token" value="<?= Session::getCsrfToken() ?>">
      <button type="submit" class="mobile-nav__link mobile-nav__link--danger">
        <i data-lucide="log-out"></i> Se déconnecter
      </button>
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
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
  <script>lucide.createIcons();</script>
</body>
</html>
