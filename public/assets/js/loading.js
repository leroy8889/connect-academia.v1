/**
 * Connect'Academia — loading.js
 * Barre de progression de navigation (état de chargement de page).
 * Autonome, sans dépendance : chargé par TOUS les layouts.
 * Expose window.CALoading = { start, done }.
 *
 * MPA : sur une navigation classique la page se décharge ; la barre
 * se remplit pendant l'attente réseau et sert d'indice honnête que
 * quelque chose se charge. Réutilisable aussi pour des appels AJAX.
 */
(function () {
  'use strict';

  var bar = null;
  var timer = null;
  var width = 0;

  function ensureBar() {
    if (bar && document.body.contains(bar)) return bar;
    bar = document.getElementById('ca-progress');
    if (!bar) {
      bar = document.createElement('div');
      bar.id = 'ca-progress';
      bar.setAttribute('role', 'progressbar');
      bar.setAttribute('aria-hidden', 'true');
      document.body.appendChild(bar);
    }
    return bar;
  }

  function set(pct) {
    width = pct;
    ensureBar().style.width = pct + '%';
  }

  function start() {
    if (timer) return;               // déjà en cours
    ensureBar().classList.add('active');
    set(8);
    timer = setInterval(function () {
      // Approche asymptotique de 90 % : ne se termine jamais seule.
      var remaining = 90 - width;
      set(width + Math.max(0.5, remaining * 0.12));
      if (width >= 90) { clearInterval(timer); timer = null; }
    }, 240);
  }

  function done() {
    if (timer) { clearInterval(timer); timer = null; }
    if (!bar) return;
    set(100);
    setTimeout(function () {
      if (!bar) return;
      bar.classList.remove('active');
      setTimeout(function () { set(0); }, 300);
    }, 200);
  }

  // ── Déclencheurs de navigation ──────────────────────────────
  function isInternalNav(a) {
    if (!a || !a.href) return false;
    if (a.target && a.target !== '_self') return false;
    if (a.hasAttribute('download')) return false;
    var href = a.getAttribute('href') || '';
    if (href.charAt(0) === '#' || href.indexOf('mailto:') === 0 ||
        href.indexOf('tel:') === 0 || href.indexOf('javascript:') === 0) return false;
    if (a.dataset && a.dataset.noProgress !== undefined) return false;
    var dest;
    try { dest = new URL(a.href, location.href); } catch (e) { return false; }
    if (dest.origin !== location.origin) return false;
    // Même page + ancre => pas de navigation réelle
    if (dest.pathname === location.pathname && dest.hash && dest.search === location.search) return false;
    return true;
  }

  // Phase bulle + garde defaultPrevented : si un handler applicatif a
  // intercepté l'action (AJAX, e.preventDefault), on ne lance PAS la barre
  // — sinon elle resterait bloquée sans navigation réelle.
  document.addEventListener('click', function (e) {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    var a = e.target.closest ? e.target.closest('a') : null;
    if (isInternalNav(a)) start();
  });

  document.addEventListener('submit', function (e) {
    if (e.defaultPrevented) return;
    var form = e.target;
    if (!form || (form.dataset && form.dataset.noProgress !== undefined)) return;
    if ((form.getAttribute('target') || '_self') !== '_self') return;
    start();
  });

  // Fin : retour depuis le cache (bfcache) ou chargement terminé.
  window.addEventListener('pageshow', done);
  window.addEventListener('load', done);

  window.CALoading = { start: start, done: done };
})();
