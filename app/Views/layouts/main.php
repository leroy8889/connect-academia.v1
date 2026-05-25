<?php
use Core\Session;
$__navAcces = \Middleware\AbonneMiddleware::hasAccess(Session::userId());
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= Session::getCsrfToken() ?>">
  <title><?= e($pageTitle ?? "Connect'Academia") ?></title>
  <link rel="icon" href="<?= asset('images/logo-officiel.png') ?>" type="image/png">

  <!-- PWA -->
  <link rel="manifest" href="<?= BASE_URL ?>/manifest.json">
  <meta name="theme-color" content="#8B52FA">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="Connect'Academia">
  <meta name="application-name" content="Connect'Academia">
  <meta name="msapplication-TileColor" content="#8B52FA">
  <meta name="msapplication-TileImage" content="<?= asset('images/icons/icon-144x144.png') ?>">
  <link rel="apple-touch-icon" href="<?= asset('images/icons/apple-touch-icon.png') ?>">
  <link rel="apple-touch-icon" sizes="152x152" href="<?= asset('images/icons/icon-152x152.png') ?>">
  <link rel="apple-touch-icon" sizes="192x192" href="<?= asset('images/icons/icon-192x192.png') ?>">

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
      userUuid:   <?= json_encode(Session::userUuid() ?? '') ?>,
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
        <img src="<?= asset('images/logo-officiel.png') ?>" alt="Connect'Academia" class="navbar__logo-img">
      </a>

      <!-- Navigation centrale -->
      <div class="navbar__nav">
        <a href="<?= url('/hub') ?>" class="navbar__nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/hub') ? 'active' : '' ?>">
          <i data-lucide="home"></i>
          Hub
        </a>
        <?php if ($__navAcces): ?>
        <a href="<?= url('/apprentissage') ?>" class="navbar__nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/apprentissage') ? 'active' : '' ?>">
          <i data-lucide="book-open"></i>
          Apprentissage
        </a>
        <?php else: ?>
        <a href="<?= url('/abonnement/choisir') ?>" class="navbar__nav-link navbar__nav-link--locked" title="Abonnement requis pour accéder à l'apprentissage">
          <i data-lucide="lock"></i>
          Apprentissage
        </a>
        <?php endif; ?>
        <?php if ($__navAcces): ?>
        <a href="<?= url('/communaute') ?>" class="navbar__nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/communaute') ? 'active' : '' ?>">
          <i data-lucide="users"></i>
          Communauté
        </a>
        <?php else: ?>
        <a href="<?= url('/abonnement/choisir') ?>" class="navbar__nav-link navbar__nav-link--locked" title="Abonnement requis pour accéder à la communauté">
          <i data-lucide="lock"></i>
          Communauté
        </a>
        <?php endif; ?>
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
          <?php if ($__navAcces): ?>
          <a href="<?= url('/communaute/mon-profil') ?>" class="navbar__user-dropdown-item">
            <i data-lucide="user"></i>
            Mon profil
          </a>
          <?php else: ?>
          <a href="<?= url('/abonnement/choisir') ?>" class="navbar__user-dropdown-item navbar__user-dropdown-item--locked" title="Abonnement requis">
            <i data-lucide="lock"></i>
            Mon profil
          </a>
          <?php endif; ?>
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
    <?php if ($__navAcces): ?>
    <a href="<?= url('/apprentissage') ?>" class="mobile-nav__link">
      <i data-lucide="book-open"></i> Apprentissage
    </a>
    <?php else: ?>
    <a href="<?= url('/abonnement/choisir') ?>" class="mobile-nav__link mobile-nav__link--locked">
      <i data-lucide="lock"></i> Apprentissage
    </a>
    <?php endif; ?>
    <?php if ($__navAcces): ?>
    <a href="<?= url('/communaute') ?>" class="mobile-nav__link">
      <i data-lucide="users"></i> Communauté
    </a>
    <?php else: ?>
    <a href="<?= url('/abonnement/choisir') ?>" class="mobile-nav__link mobile-nav__link--locked">
      <i data-lucide="lock"></i> Communauté
    </a>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>/public/orientation/orientation.html" class="mobile-nav__link">
      <i data-lucide="compass"></i> Orientation
    </a>
    <div class="mobile-nav__divider"></div>
    <?php if ($__navAcces): ?>
    <a href="<?= url('/communaute/mon-profil') ?>" class="mobile-nav__link">
      <i data-lucide="user"></i> Mon profil
    </a>
    <?php else: ?>
    <a href="<?= url('/abonnement/choisir') ?>" class="mobile-nav__link mobile-nav__link--locked">
      <i data-lucide="lock"></i> Mon profil
    </a>
    <?php endif; ?>
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

  <!-- ── FOOTER ────────────────────────────────────────────────────────────── -->
  <?php if (empty($hideFooter)): ?>
  <footer class="hub-footer" style="background-image: url('<?= BASE_URL ?>/assets/images/footer-bg.png')">
    <div class="hub-footer__top">
      <div class="hub-footer__container">

        <!-- Brand -->
        <div class="hub-footer__brand">
          <a href="<?= url('/hub') ?>">
            <img src="<?= BASE_URL ?>/assets/images/logo-officiel.png" width="162" height="50" alt="Connect'Academia logo" class="hub-footer__logo">
          </a>
          <p class="hub-footer__brand-text">
            La première plateforme dédiée à l'orientation des élèves et étudiants au Gabon.
          </p>
          <div class="hub-footer__contact-row">
            <span class="hub-footer__contact-label">Call:</span>
            <a href="tel:+241077980375" class="hub-footer__link">+241 077 98 03 75</a>
          </div>
          <div class="hub-footer__contact-row">
            <span class="hub-footer__contact-label">Email:</span>
            <a href="mailto:contact@connectacademia.io" class="hub-footer__link">contact@connectacademia.io</a>
          </div>
        </div>

        <!-- Plateforme -->
        <ul class="hub-footer__list">
          <li><p class="hub-footer__list-title">Plateforme</p></li>
          <li><a href="<?= url('/hub') ?>" class="hub-footer__link">Accueil</a></li>
          <li><a href="<?= url($__navAcces ? '/apprentissage' : '/abonnement/choisir') ?>" class="hub-footer__link">Apprentissage</a></li>
          <li><a href="<?= url($__navAcces ? '/communaute' : '/abonnement/choisir') ?>" class="hub-footer__link">Communauté</a></li>
          <li><a href="<?= url('/abonnement/choisir') ?>" class="hub-footer__link">Abonnement</a></li>
        </ul>

        <!-- Liens -->
        <ul class="hub-footer__list">
          <li><p class="hub-footer__list-title">Liens</p></li>
          <li><a href="<?= BASE_URL ?>/assets/lib/contact.html" class="hub-footer__link">Contact</a></li>
          <li><a href="<?= url('') ?>" class="hub-footer__link">À propos</a></li>
          <li><a href="<?= url($__navAcces ? '/communaute/mon-profil' : '/abonnement/choisir') ?>" class="hub-footer__link">Mon profil</a></li>
          <li><a href="<?= BASE_URL ?>/assets/lib/mentions-legales.html" class="hub-footer__link">Mentions légales</a></li>
        </ul>

        <!-- Newsletter + Réseaux sociaux -->
        <div class="hub-footer__newsletter-col">
          <p class="hub-footer__list-title">Contacts</p>
          <p class="hub-footer__list-text">
            Saisissez votre adresse e-mail pour vous inscrire à notre newsletter.
          </p>
          <form action="" class="hub-footer__newsletter">
            <input type="email" name="email_address" placeholder="Votre email" required class="hub-footer__input">
            <button type="submit" class="hub-footer__btn">
              <span>S'abonner</span>
              <ion-icon name="arrow-forward-outline" aria-hidden="true"></ion-icon>
            </button>
          </form>
          <ul class="hub-footer__social">
            <li><a href="https://www.facebook.com/people/Connect-Academia/61568164406172/#" class="hub-footer__social-link" target="_blank" rel="noopener"><ion-icon name="logo-facebook"></ion-icon></a></li>
            <li><a href="https://www.instagram.com/connectacademia.ga" class="hub-footer__social-link" target="_blank" rel="noopener"><ion-icon name="logo-instagram"></ion-icon></a></li>
            <li><a href="https://chat.whatsapp.com/BiE25HR0QjQFnPJTfqtDmx" class="hub-footer__social-link" target="_blank" rel="noopener"><ion-icon name="logo-whatsapp"></ion-icon></a></li>
            <li><a href="https://www.tiktok.com/@connect.academia.ga" class="hub-footer__social-link" target="_blank" rel="noopener"><ion-icon name="logo-tiktok"></ion-icon></a></li>
            <li><a href="https://www.linkedin.com/company/connect-academia-ga/" class="hub-footer__social-link" target="_blank" rel="noopener"><ion-icon name="logo-linkedin"></ion-icon></a></li>
          </ul>
        </div>

      </div>
    </div>

    <div class="hub-footer__bottom">
      <div class="hub-footer__bottom-inner">
        <p class="hub-footer__copyright">
          Copyright 2026 All Rights Reserved by
          <a href="https://www.instagram.com/connectacademia.ga" class="hub-footer__copyright-link">Connect'Academia</a>
        </p>
      </div>
    </div>
  </footer>
  <?php endif; ?>

  <!--── TOAST ──────────────────────────────────────────────────────────── -->
  <div class="toast-container" id="toast-container"></div>

  <script src="<?= asset('js/app.js') ?>"></script>
  <?php if (!empty($extraJs)): foreach ($extraJs as $js): ?>
  <script src="<?= asset('js/' . $js) ?>"></script>
  <?php endforeach; endif; ?>
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
  <script>lucide.createIcons();</script>
  <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>

  <style>
  /* ── Hub Footer ──────────────────────────────────────────────────────── */
  .hub-footer {
    background-repeat: no-repeat;
    background-size: cover;
    background-position: center top;
    background-color: #1C1C2E;
    color: #BCBCBC;
    font-size: 0.9rem;
    margin-top: 60px;
  }
  .hub-footer__top { padding: 60px 20px; }
  .hub-footer__container {
    max-width: 1200px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 1fr;
    gap: 40px;
  }
  .hub-footer__logo { max-width: 162px; height: auto; }
  .hub-footer__brand-text { margin: 20px 0; line-height: 1.6; }
  .hub-footer__contact-row { display: flex; gap: 6px; margin-top: 8px; }
  .hub-footer__contact-label { font-weight: 500; color: #fff; }
  .hub-footer__link { color: #BCBCBC; text-decoration: none; transition: color 0.2s; }
  .hub-footer__link:hover { color: #45C1A0; }
  .hub-footer__list { list-style: none; padding: 0; margin: 0; }
  .hub-footer__list-title {
    color: #fff;
    font-size: 1rem;
    font-weight: 600;
    margin: 0 0 14px;
    font-family: Montserrat, sans-serif;
  }
  .hub-footer__list li { padding: 5px 0; }
  .hub-footer__list-text { line-height: 1.6; margin-bottom: 16px; }
  .hub-footer__newsletter {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 28px;
  }
  .hub-footer__input {
    background: #fff;
    border: none;
    border-radius: 8px;
    padding: 12px;
    font-size: 0.9rem;
    color: #1C1C2E;
    width: 100%;
    box-sizing: border-box;
  }
  .hub-footer__btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: #8B52FA;
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 12px 20px;
    font-weight: 600;
    cursor: pointer;
    font-size: 0.9rem;
    transition: background 0.2s;
  }
  .hub-footer__btn:hover { background: #7C3AED; }
  .hub-footer__social { display: flex; gap: 22px; list-style: none; padding: 0; margin: 0; }
  .hub-footer__social-link {
    color: #BCBCBC;
    font-size: 22px;
    text-decoration: none;
    transition: color 0.2s;
    display: flex;
    align-items: center;
  }
  .hub-footer__social-link:hover { color: #45C1A0; }
  .hub-footer__bottom {
    border-top: 1px solid #2A2A3E;
    padding: 24px 20px;
  }
  .hub-footer__bottom-inner { max-width: 1200px; margin: 0 auto; }
  .hub-footer__copyright { text-align: center; font-size: 0.875rem; }
  .hub-footer__copyright-link { color: #45C1A0; text-decoration: none; }
  .hub-footer__copyright-link:hover { text-decoration: underline; }

  /* Responsive */
  @media (min-width: 640px) {
    .hub-footer__container { grid-template-columns: 1fr 1fr; }
    .hub-footer__brand,
    .hub-footer__newsletter-col { grid-column: 1 / 3; }
  }
  @media (min-width: 1024px) {
    .hub-footer__container { grid-template-columns: 1fr 0.6fr 0.6fr 1.2fr; }
    .hub-footer__brand,
    .hub-footer__newsletter-col { grid-column: auto; }
  }
  </style>

  <!-- PWA : Service Worker + Install Prompt -->
  <div id="pwa-banner" style="display:none;position:fixed;bottom:0;left:0;right:0;z-index:9999;padding:12px 16px;background:#fff;border-top:1px solid #E5E7EB;box-shadow:0 -4px 16px rgba(0,0,0,0.08);display:none;align-items:center;gap:12px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;" aria-live="polite">
    <img src="<?= asset('images/icons/icon-72x72.png') ?>" width="40" height="40" style="border-radius:10px;flex-shrink:0;" alt="">
    <div style="flex:1;min-width:0;">
      <div style="font-weight:600;font-size:0.875rem;color:#1A1A2E;">Installer Connect'Academia</div>
      <div style="font-size:0.8125rem;color:#6B7280;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">Accès rapide depuis votre écran d'accueil</div>
    </div>
    <button id="pwa-install-btn" style="flex-shrink:0;background:#8B52FA;color:#fff;border:none;border-radius:8px;padding:8px 16px;font-size:0.875rem;font-weight:600;cursor:pointer;">Installer</button>
    <button id="pwa-dismiss-btn" style="flex-shrink:0;background:none;border:none;color:#9CA3AF;cursor:pointer;padding:4px;line-height:1;" aria-label="Fermer">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
  </div>

  <script>
  (function () {
    'use strict';

    // ── Service Worker ─────────────────────────────────────
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', function () {
        navigator.serviceWorker.register('<?= BASE_URL ?>/sw.js', {
          scope: '<?= BASE_URL ?>/'
        }).then(function (reg) {
          reg.addEventListener('updatefound', function () {
            var newWorker = reg.installing;
            if (!newWorker) return;
            newWorker.addEventListener('statechange', function () {
              if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                if (window.toast) {
                  window.toast('Mise à jour disponible — rechargez la page', 'info', 6000);
                }
              }
            });
          });
        }).catch(function (err) {
          console.warn('[PWA] SW registration failed:', err);
        });
      });
    }

    // ── Install Prompt ──────────────────────────────────────
    var deferredPrompt = null;
    var banner = document.getElementById('pwa-banner');
    var installBtn = document.getElementById('pwa-install-btn');
    var dismissBtn = document.getElementById('pwa-dismiss-btn');

    var DISMISS_KEY = 'ca_pwa_dismissed';
    var INSTALL_KEY = 'ca_pwa_installed';

    function showBanner() {
      if (!banner) return;
      if (localStorage.getItem(DISMISS_KEY)) return;
      if (localStorage.getItem(INSTALL_KEY)) return;
      banner.style.display = 'flex';
    }

    window.addEventListener('beforeinstallprompt', function (e) {
      e.preventDefault();
      deferredPrompt = e;
      setTimeout(showBanner, 3000);
    });

    window.addEventListener('appinstalled', function () {
      localStorage.setItem(INSTALL_KEY, '1');
      if (banner) banner.style.display = 'none';
      deferredPrompt = null;
    });

    if (installBtn) {
      installBtn.addEventListener('click', function () {
        if (!deferredPrompt) return;
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then(function (result) {
          if (result.outcome === 'accepted') {
            localStorage.setItem(INSTALL_KEY, '1');
          }
          deferredPrompt = null;
          banner.style.display = 'none';
        });
      });
    }

    if (dismissBtn) {
      dismissBtn.addEventListener('click', function () {
        if (banner) banner.style.display = 'none';
        localStorage.setItem(DISMISS_KEY, Date.now().toString());
      });
    }
  }());
  </script>
</body>
</html>
