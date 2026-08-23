/**
 * Sidebar liquid-glass hover — Connect'Academia
 *
 * UNE pastille de verre qui glisse (ressort) sous le lien survolé, façon
 * sélection de barre latérale macOS / visionOS. Appliqué à l'espace
 * gestionnaire ET à la console super admin (même effet, mêmes règles).
 *
 *  - WebGL + liquid-glass-js (Container) dispo : la pastille EST un élément
 *    verre WebGL (réfraction réelle du fond crème + texte), partage le
 *    snapshot html2canvas.
 *  - Sinon : pastille en verre CSS (backdrop-filter). Look Apple garanti.
 *  - Sinon : la surbrillance CSS `:hover` d'origine reste active.
 *
 * Une seule instance verre par sidebar = conforme au budget WebGL (1–4
 * contextes) et à la règle « jamais un élément verre par ligne de liste ».
 */
(function () {
  'use strict';

  var SPRING =
    'transform .38s cubic-bezier(.34,1.4,.5,1), width .28s cubic-bezier(.34,1.4,.5,1), ' +
    'height .2s ease, opacity .2s ease';

  // Sidebars gérées : { conteneur, zone défilante, liens, classe pastille }
  //
  // `cssOnly:true` = pastille en verre CSS uniquement, jamais la variante WebGL.
  // Console admin : les pages sont longues (tableaux, graphes) et le snapshot
  // html2canvas plein document se remappait de travers dans le shader → la
  // pastille échantillonnait des zones sombres et le survol « noircissait ».
  // Le verre CSS donne exactement le même rendu qu'ailleurs, de façon
  // déterministe, et supprime la rasterisation plein écran au chargement.
  var CONFIGS = [
    { sidebar: '.g-sidebar',     nav: '.g-nav',         link: '.g-nav-link',   pill: 'g-nav-glass' },
    { sidebar: '.admin-sidebar', nav: '.sidebar-nav',   link: '.sidebar-link', pill: 'sidebar-nav-glass', cssOnly: true }
  ];

  function webglAvailable() {
    try {
      var c = document.createElement('canvas');
      return !!(c.getContext('webgl') || c.getContext('experimental-webgl'));
    } catch (e) {
      return false;
    }
  }

  /**
   * Machines qui ne tiennent pas le budget compositing : on ne tente même pas
   * le verre WebGL (snapshot html2canvas + contexte GL), la pastille CSS suffit.
   * Symptôme évité : contexte GL perdu en boucle → verre qui clignote et
   * curseur système qui vire au noir le temps que le GPU se recompose.
   */
  function deviceCanAffordWebGL() {
    try {
      // Mêmes signaux que le garde-fou CSS d'admin-claude.css, pour que JS et
      // CSS retombent sur le verre CSS exactement dans les mêmes conditions.
      if (window.matchMedia) {
        if (window.matchMedia('(prefers-reduced-transparency: reduce)').matches) return false;
        if (window.matchMedia('(update: slow)').matches) return false;
      }
      if (typeof navigator.deviceMemory === 'number' && navigator.deviceMemory < 4) return false;
      if (typeof navigator.hardwareConcurrency === 'number' && navigator.hardwareConcurrency < 4) return false;
    } catch (e) { /* API absente : on laisse passer */ }
    return true;
  }

  /**
   * Repli définitif en verre CSS : on retire la variante WebGL (la règle
   * `.…-webgl` neutralisait le backdrop-filter) et on jette le canvas mort.
   * Appelé sur `webglcontextlost` — sans ça la pastille restait invisible
   * jusqu'au rechargement de la page.
   */
  function fallbackToCss(pill, cfg, canvas) {
    pill.classList.remove(cfg.pill + '-webgl');
    if (canvas && canvas.parentNode) canvas.parentNode.removeChild(canvas);
    document.body.classList.add('no-glass');
  }

  function mount(cfg, canWebGL) {
    var sidebar = document.querySelector(cfg.sidebar);
    var nav = sidebar && sidebar.querySelector(cfg.nav);
    if (!nav) return;

    var links = [].slice.call(nav.querySelectorAll(cfg.link));
    if (!links.length) return;

    var inst = null;
    var pill;

    if (canWebGL && !cfg.cssOnly) {
      try {
        inst = new Container({ type: 'rounded', borderRadius: 12, tintOpacity: 0.14 });
        pill = inst.element;
        pill.classList.add(cfg.pill, cfg.pill + '-webgl');
        if (inst.canvas) {
          inst.canvas.style.boxShadow = 'none';
          var lostCanvas = inst.canvas;
          // preventDefault() = on accepte une éventuelle restauration, mais on
          // ne l'attend pas : le verre CSS reprend la main immédiatement.
          lostCanvas.addEventListener('webglcontextlost', function (ev) {
            ev.preventDefault();
            fallbackToCss(pill, cfg, lostCanvas);
          }, false);
        }
      } catch (e) {
        inst = null;
      }
    }

    if (!inst) {
      pill = document.createElement('div');
      pill.className = cfg.pill;
    }

    pill.style.position = 'absolute';
    pill.style.pointerEvents = 'none';
    pill.style.opacity = '0';
    pill.style.transition = SPRING;
    nav.insertBefore(pill, nav.firstChild);
    sidebar.classList.add('g-glass-ready');

    var hideTimer;

    function place(link) {
      clearTimeout(hideTimer);
      pill.style.height = link.offsetHeight + 'px';
      pill.style.width = link.offsetWidth + 'px';
      pill.style.left = link.offsetLeft + 'px';
      pill.style.transform = 'translateY(' + link.offsetTop + 'px)';
      pill.classList.add('on');
      pill.style.opacity = '1';
      if (inst && inst.updateSizeFromDOM) {
        try { inst.updateSizeFromDOM(); } catch (e) {}
      }
    }

    function rest() {
      var active = nav.querySelector(cfg.link + '.active, ' + cfg.link + '.g-nav-active');
      if (active) {
        place(active);
      } else {
        pill.classList.remove('on');
        pill.style.opacity = '0';
      }
    }

    links.forEach(function (l) {
      l.addEventListener('mouseenter', function () { place(l); });
      l.addEventListener('focus', function () { place(l); });
    });

    nav.addEventListener('mouseleave', function () {
      hideTimer = setTimeout(rest, 70);
    });

    requestAnimationFrame(rest);
    window.addEventListener('resize', function () { requestAnimationFrame(rest); });
  }

  function init() {
    var canWebGL =
      deviceCanAffordWebGL() &&
      webglAvailable() &&
      typeof Container !== 'undefined' &&
      typeof html2canvas !== 'undefined';

    CONFIGS.forEach(function (cfg) {
      try { mount(cfg, canWebGL); } catch (e) { /* silencieux : hover CSS conservé */ }
    });
  }

  function boot() {
    var ready = document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve();
    ready.then(function () {
      requestAnimationFrame(function () {
        try { init(); } catch (e) {}
      });
    });
  }

  if (document.readyState === 'complete') {
    boot();
  } else {
    window.addEventListener('load', boot);
  }
})();
