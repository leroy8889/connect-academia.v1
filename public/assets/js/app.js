/**
 * Connect'Academia — app.js
 * Fonctions globales : navbar, notifs, toast, utils
 */
(function () {
  'use strict';

  const CA = window.CA || {};

  // ── Helpers ──────────────────────────────────────────────
  function $(selector, ctx = document) { return ctx.querySelector(selector); }
  function $$(selector, ctx = document) { return [...ctx.querySelectorAll(selector)]; }

  function on(el, event, fn) {
    if (el) el.addEventListener(event, fn);
  }

  function url(path) {
    const base = (CA.baseUrl || '').replace(/\/$/, '');
    return base + (path.startsWith('/') ? path : '/' + path);
  }

  function csrfHeaders() {
    return {
      'Content-Type':   'application/json',
      'X-CSRF-Token':   CA.csrfToken || '',
      'X-Requested-With': 'XMLHttpRequest',
    };
  }

  async function apiFetch(path, options = {}) {
    const res = await fetch(url(path), {
      headers: { 'X-Requested-With': 'XMLHttpRequest', ...(options.headers || {}) },
      ...options,
    });
    if (!res.ok && res.status === 401) {
      window.location.href = url('/auth/connexion');
      return null;
    }
    return res.json();
  }

  // ── Toast ─────────────────────────────────────────────────
  window.toast = function (message, type = 'default', duration = 3500) {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const el = document.createElement('div');
    el.className = `toast toast--${type}`;

    const icons = {
      success: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>',
      error:   '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
      default: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
    };

    el.innerHTML = (icons[type] || icons.default) + `<span>${message}</span>`;
    container.appendChild(el);

    setTimeout(() => {
      el.style.opacity = '0';
      el.style.transform = 'translateY(1rem)';
      el.style.transition = 'all .3s';
      setTimeout(() => el.remove(), 300);
    }, duration);
  };

  // ── Navbar ────────────────────────────────────────────────
  function initNavbar() {
    // Hamburger (mobile)
    const hamburger = document.getElementById('hamburger');
    const mobileNav = document.getElementById('mobile-nav');
    const overlay   = document.getElementById('mobile-nav-overlay');

    function openMobileNav() {
      mobileNav?.classList.add('open');
      mobileNav?.classList.remove('hidden');
      overlay?.classList.remove('hidden');
      document.body.style.overflow = 'hidden';
    }

    function closeMobileNav() {
      mobileNav?.classList.remove('open');
      overlay?.classList.add('hidden');
      document.body.style.overflow = '';
    }

    on(hamburger, 'click', openMobileNav);
    on(overlay, 'click', closeMobileNav);

    // Dropdown utilisateur
    const userTrigger  = document.getElementById('user-menu-trigger');
    const userDropdown = document.getElementById('user-dropdown');

    on(userTrigger, 'click', (e) => {
      e.stopPropagation();
      userDropdown?.classList.toggle('hidden');
      userTrigger?.classList.toggle('open');
    });

    on(document, 'click', (e) => {
      if (!userTrigger?.contains(e.target)) {
        userDropdown?.classList.add('hidden');
        userTrigger?.classList.remove('open');
      }
    });

    // Scroll shadow
    const navbar = document.getElementById('navbar');
    if (navbar) {
      window.addEventListener('scroll', () => {
        navbar.style.boxShadow = window.scrollY > 4 ? 'var(--shadow-md)' : '';
      }, { passive: true });
    }
  }

  // ── Notifications ─────────────────────────────────────────
  function initNotifications() {
    const btn      = document.getElementById('notif-btn');
    const dropdown = document.getElementById('notif-dropdown');
    const overlay  = document.getElementById('notif-overlay');
    const badge    = document.getElementById('notif-badge');
    const list     = document.getElementById('notif-list');
    const readAll  = document.getElementById('notif-read-all');

    if (!btn || !dropdown) return;

    let isOpen = false;
    let loaded = false;

    function openDropdown() {
      dropdown.classList.remove('hidden');
      overlay?.classList.remove('hidden');
      isOpen = true;
      if (!loaded) {
        loadNotifications();
        loaded = true;
      }
    }

    function closeDropdown() {
      dropdown.classList.add('hidden');
      overlay?.classList.add('hidden');
      isOpen = false;
    }

    on(btn, 'click', (e) => {
      e.stopPropagation();
      isOpen ? closeDropdown() : openDropdown();
    });

    on(overlay, 'click', closeDropdown);

    async function loadNotifications() {
      if (list) list.innerHTML = '<div class="notif-dropdown__empty">Chargement...</div>';
      const data = await apiFetch('/api/notifications?limit=10');
      if (!data?.success || !list) return;

      if (!data.data?.length) {
        list.innerHTML = '<div class="notif-dropdown__empty">Aucune notification</div>';
        return;
      }

      list.innerHTML = data.data.map(n => `
        <div class="notif-item ${!n.is_read ? 'notif-item--unread' : ''}" data-id="${n.id}">
          <p class="notif-item__text">${escapeHtml(n.message)}</p>
          <span class="notif-item__time">${timeAgo(n.created_at)}</span>
        </div>
      `).join('');
    }

    on(readAll, 'click', async () => {
      const items = $$('.notif-item--unread', list);
      for (const item of items) {
        const id = item.dataset.id;
        apiFetch(`/api/notifications/${id}/read`, {
          method: 'PATCH',
          headers: csrfHeaders(),
        });
        item.classList.remove('notif-item--unread');
      }
      if (badge) badge.classList.add('hidden');
    });

    // Polling compteur (toutes les 30s)
    pollNotifCount();
    setInterval(pollNotifCount, 30000);

    async function pollNotifCount() {
      const data = await apiFetch('/api/notifications/count');
      if (!data?.success || !badge) return;
      const count = data.data?.count || 0;
      if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.classList.remove('hidden');
      } else {
        badge.classList.add('hidden');
      }
    }
  }

  // ── Afficher/masquer mot de passe ──────────────────────────
  function initPasswordToggles() {
    $$('[data-toggle="password"]').forEach(btn => {
      const input = btn.closest('.form__input-wrap')?.querySelector('input[type="password"], input[type="text"]');
      if (!input) return;
      on(btn, 'click', () => {
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        const eye = btn.querySelector('.icon-eye');
        if (eye) {
          eye.innerHTML = isPassword
            ? '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>'
            : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
        }
      });
    });
  }

  // ── Utilitaires ───────────────────────────────────────────
  function escapeHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
  }

  function timeAgo(dateStr) {
    const diff = (Date.now() - new Date(dateStr).getTime()) / 1000;
    if (diff < 60)     return 'à l\'instant';
    if (diff < 3600)   return Math.floor(diff / 60) + ' min';
    if (diff < 86400)  return Math.floor(diff / 3600) + 'h';
    if (diff < 604800) return Math.floor(diff / 86400) + 'j';
    return new Date(dateStr).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
  }

  // ── App API (utilisée par feed.js, post-composer.js, comments.js, follow.js) ──
  window.App = {
    toast: {
      success: (msg) => window.toast(msg, 'success'),
      error:   (msg) => window.toast(msg, 'error'),
      warning: (msg) => window.toast(msg, 'warning'),
      info:    (msg) => window.toast(msg, 'default'),
    },
    timeAgo:    timeAgo,
    escapeHtml: escapeHtml,
    formatNumber: function (n) {
      n = parseInt(n) || 0;
      if (n >= 1000000) return (n / 1000000).toFixed(1) + 'M';
      if (n >= 1000)    return (n / 1000).toFixed(1) + 'k';
      return String(n);
    },
    openModal: function (id) {
      const el = document.getElementById(id);
      if (el) { el.classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
    },
    closeModal: function (id) {
      const el = document.getElementById(id);
      if (el) { el.classList.add('hidden'); document.body.style.overflow = ''; }
    },
  };

  // ── Init ──────────────────────────────────────────────────
  document.addEventListener('DOMContentLoaded', () => {
    initNavbar();
    // N'initialise le système de notif app.js que si le composant Notifications (communauté) n'est pas chargé
    if (typeof Notifications === 'undefined') {
      initNotifications();
    }
    initPasswordToggles();
  });

})();
