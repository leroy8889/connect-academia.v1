<?php
/**
 * Navbar site unifiée — pilule flottante translucide (style hub Orientation).
 * Usage : $navActive = 'accueil'|'orientation'|'documentation'|'contact' (optionnel)
 *         puis require ce fichier dans le <body>.
 * Autonome : charge sa propre CSS + JS, aucune dépendance à style.css/script.js.
 */
$navActive = $navActive ?? '';
$caNavLinks = [
    ['label' => 'Accueil',      'href' => url('/'),                              'key' => 'accueil'],
    ['label' => 'À propos',     'href' => url('/') . '#about',                   'key' => 'apropos'],
    ['label' => 'Orientations', 'href' => url('/orientation'),                   'key' => 'orientation'],
    ['label' => 'Documentation','href' => url('/documentation'),                 'key' => 'documentation'],
    ['label' => 'Contact',      'href' => BASE_URL . '/contact', 'key' => 'contact'],
];
?>
<link rel="stylesheet" href="<?= asset('css/site-navbar.css') ?>">

<header class="ca-nav" data-ca-nav>
  <div class="ca-nav__inner">

    <a href="<?= url('/') ?>" class="ca-nav__logo" aria-label="Connect'Academia — accueil">
      <img src="<?= BASE_URL ?>/assets/images/logo-officiel.png" width="56" height="56" alt="Connect'Academia logo">
    </a>

    <nav class="ca-nav__menu" data-ca-nav-menu aria-label="Navigation principale">
      <div class="ca-nav__menu-head">
        <a href="<?= url('/') ?>" class="ca-nav__logo">
          <img src="<?= BASE_URL ?>/assets/images/logo-officiel.png" width="64" height="64" alt="Connect'Academia logo">
        </a>
        <button class="ca-nav__close" type="button" aria-label="Fermer le menu" data-ca-nav-toggle>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
      </div>

      <ul class="ca-nav__list">
        <?php foreach ($caNavLinks as $l): ?>
        <li>
          <a href="<?= $l['href'] ?>" class="ca-nav__link<?= $navActive === $l['key'] ? ' is-active' : '' ?>" data-ca-nav-link><?= $l['label'] ?></a>
        </li>
        <?php endforeach; ?>
      </ul>
    </nav>

    <div class="ca-nav__actions">
      <?php if (\Core\Session::isLoggedIn()): ?>
      <a href="<?= url(\Core\Session::isVisiteur() ? '/eleve/orientation' : '/hub') ?>" class="ca-nav__cta">
        <span>Mon espace</span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
      </a>
      <?php else: ?>
      <a href="<?= url('/auth/connexion') ?>" class="ca-nav__cta">
        <span>Connexion</span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
      </a>
      <?php endif; ?>
      <button class="ca-nav__burger" type="button" aria-label="Ouvrir le menu" data-ca-nav-toggle>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>
    </div>

  </div>

  <div class="ca-nav__overlay" data-ca-nav-toggle data-ca-nav-overlay></div>
</header>

<script>
(function () {
  if (window.__caNavInit) return;
  window.__caNavInit = true;

  var nav     = document.querySelector('[data-ca-nav]');
  var menu    = document.querySelector('[data-ca-nav-menu]');
  var overlay = document.querySelector('[data-ca-nav-overlay]');
  if (!nav || !menu) return;

  function toggleMenu() {
    menu.classList.toggle('is-open');
    if (overlay) overlay.classList.toggle('is-open');
  }
  function closeMenu() {
    menu.classList.remove('is-open');
    if (overlay) overlay.classList.remove('is-open');
  }

  document.querySelectorAll('[data-ca-nav-toggle]').forEach(function (el) {
    el.addEventListener('click', toggleMenu);
  });
  document.querySelectorAll('[data-ca-nav-link]').forEach(function (el) {
    el.addEventListener('click', closeMenu);
  });

  var ticking = false;
  window.addEventListener('scroll', function () {
    if (ticking) return;
    ticking = true;
    window.requestAnimationFrame(function () {
      nav.classList.toggle('is-fixed', window.scrollY > 100);
      ticking = false;
    });
  }, { passive: true });
})();
</script>
