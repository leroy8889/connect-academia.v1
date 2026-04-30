/**
 * StudyLink — Main Application
 * Initialisation globale, utilitaires, toast, dropdown, thème
 */

const App = (() => {
    'use strict';

    // ── Toast System ────────────────────────────────
    const toast = {
        _container: null,

        _getContainer() {
            if (!this._container) {
                this._container = document.createElement('div');
                this._container.className = 'toast-container';
                document.body.appendChild(this._container);
            }
            return this._container;
        },

        show(message, type = 'info', duration = 4000) {
            const container = this._getContainer();
            const el = document.createElement('div');
            el.className = `toast toast--${type}`;
            el.innerHTML = `
                <span>${this._escapeHtml(message)}</span>
                <button class="toast__close" aria-label="Fermer">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 6L6 18M6 6l12 12"/>
                    </svg>
                </button>
            `;

            el.querySelector('.toast__close').addEventListener('click', () => this._remove(el));
            container.appendChild(el);

            if (duration > 0) {
                setTimeout(() => this._remove(el), duration);
            }

            return el;
        },

        success(msg, duration)  { return this.show(msg, 'success', duration); },
        error(msg, duration)    { return this.show(msg, 'error', duration); },
        warning(msg, duration)  { return this.show(msg, 'warning', duration); },
        info(msg, duration)     { return this.show(msg, 'info', duration); },

        _remove(el) {
            el.style.opacity = '0';
            el.style.transform = 'translateX(100%)';
            setTimeout(() => el.remove(), 300);
        },

        _escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // ── Dropdown Manager ────────────────────────────
    function initDropdowns() {
        document.addEventListener('click', (e) => {
            // Fermer tous les dropdowns ouverts
            document.querySelectorAll('[data-dropdown].active').forEach(d => {
                if (!d.contains(e.target)) {
                    d.classList.remove('active');
                    const menu = d.querySelector('[data-dropdown-menu]');
                    if (menu) menu.classList.add('hidden');
                }
            });

            // Toggle le dropdown cliqué
            const trigger = e.target.closest('[data-dropdown-toggle]');
            if (trigger) {
                e.stopPropagation();
                const parent = trigger.closest('[data-dropdown]');
                const menu = parent?.querySelector('[data-dropdown-menu]');
                if (parent && menu) {
                    const isOpen = !menu.classList.contains('hidden');
                    // Fermer tous les autres d'abord
                    document.querySelectorAll('[data-dropdown-menu]').forEach(m => m.classList.add('hidden'));
                    if (!isOpen) {
                        menu.classList.remove('hidden');
                        parent.classList.add('active');
                    } else {
                        parent.classList.remove('active');
                    }
                }
            }
        });
    }

    // ── Modal Manager ───────────────────────────────
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';

        // Fermer sur click overlay
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal(modalId);
        });

        // Fermer sur Escape
        const escHandler = (e) => {
            if (e.key === 'Escape') {
                closeModal(modalId);
                document.removeEventListener('keydown', escHandler);
            }
        };
        document.addEventListener('keydown', escHandler);
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }

    // ── Time Ago ────────────────────────────────────
    function timeAgo(dateStr) {
        const date = new Date(dateStr);
        const now = new Date();
        const seconds = Math.floor((now - date) / 1000);

        const intervals = [
            { label: 'an',     seconds: 31536000 },
            { label: 'mois',   seconds: 2592000  },
            { label: 'sem.',   seconds: 604800    },
            { label: 'j',      seconds: 86400     },
            { label: 'h',      seconds: 3600      },
            { label: 'min',    seconds: 60        }
        ];

        for (const interval of intervals) {
            const count = Math.floor(seconds / interval.seconds);
            if (count >= 1) {
                return `il y a ${count}${interval.label}`;
            }
        }
        return "à l'instant";
    }

    // ── Format Number ───────────────────────────────
    function formatNumber(num) {
        if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
        if (num >= 1000) return (num / 1000).toFixed(1) + 'k';
        return num.toString();
    }

    // ── Debounce ────────────────────────────────────
    function debounce(fn, delay = 300) {
        let timer;
        return (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => fn(...args), delay);
        };
    }

    // ── Throttle ────────────────────────────────────
    function throttle(fn, limit = 300) {
        let inThrottle;
        return (...args) => {
            if (!inThrottle) {
                fn(...args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }

    // ── Escape HTML ─────────────────────────────────
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ── Report Modal ────────────────────────────────
    function initReportModal() {
        const closeBtn = document.getElementById('close-report-modal');
        const cancelBtn = document.getElementById('cancel-report');
        const submitBtn = document.getElementById('submit-report');

        closeBtn?.addEventListener('click', () => closeModal('report-modal-overlay'));
        cancelBtn?.addEventListener('click', () => closeModal('report-modal-overlay'));

        submitBtn?.addEventListener('click', async () => {
            const modal = document.getElementById('report-modal');
            const postId = modal?.dataset.postId;
            if (!postId) return;

            const reason = document.querySelector('input[name="report_reason"]:checked')?.value;
            if (!reason) {
                toast.warning('Veuillez sélectionner une raison');
                return;
            }

            const description = document.getElementById('report-description')?.value || '';

            submitBtn.disabled = true;
            submitBtn.textContent = 'Envoi...';

            try {
                await API.post(`/api/posts/${postId}/report`, { reason, description });
                toast.success('Signalement envoyé. Merci de votre vigilance.');
                closeModal('report-modal-overlay');

                // Reset form
                document.querySelectorAll('input[name="report_reason"]').forEach(r => r.checked = false);
                const descEl = document.getElementById('report-description');
                if (descEl) descEl.value = '';
            } catch (error) {
                toast.error(error.message || 'Erreur lors du signalement');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Envoyer le signalement';
            }
        });
    }

    // ── Logout ──────────────────────────────────────
    function initLogout() {
        const logoutForm = document.getElementById('logout-form');
    
        if (!logoutForm) return;
    
        logoutForm.addEventListener('submit', () => {
            const btn = logoutForm.querySelector('button');
            if (btn) {
                btn.disabled = true;
                btn.style.opacity = '0.6';
            }
        });
    }

    
    // ── Search ──────────────────────────────────────
    function initSearch() {
        const searchInput = document.getElementById('global-search');
        if (!searchInput) return;

        let searchTimeout = null;
        let currentSearchQuery = '';

        // Recherche lors de la saisie (debounce)
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.trim();
            clearTimeout(searchTimeout);
            
            if (query.length === 0) {
                currentSearchQuery = '';
                return;
            }

            searchTimeout = setTimeout(async () => {
                if (query.length < 2) {
                    toast.warning('Veuillez saisir au moins 2 caractères');
                    return;
                }

                await performSearch(query);
            }, 500);
        });

        // Recherche sur Enter
        searchInput.addEventListener('keydown', async (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const query = searchInput.value.trim();
                if (query.length < 2) {
                    toast.warning('Veuillez saisir au moins 2 caractères');
                    return;
                }
                await performSearch(query);
            }
        });

        async function performSearch(query) {
            if (query === currentSearchQuery) return;
            currentSearchQuery = query;

            try {
                const response = await API.get(`/api/posts/search?q=${encodeURIComponent(query)}`);
                const posts = response.data?.posts || [];

                if (posts.length === 0) {
                    toast.info('Aucun résultat trouvé');
                    return;
                }

                // Afficher les résultats dans le feed
                if (typeof Feed !== 'undefined' && Feed.loadSearchResults) {
                    Feed.loadSearchResults(posts, query);
                } else {
                    // Fallback : rediriger vers la page explore avec les résultats
                    window.location.href = `${window.STUDYLINK_CONFIG?.baseUrl || ''}/explore?q=${encodeURIComponent(query)}`;
                }
            } catch (error) {
                console.error('Erreur de recherche:', error);
                toast.error('Erreur lors de la recherche');
            }
        }
    }

    // ── Development Modal ───────────────────────────
    function initDevelopmentModal() {
        const closeBtn = document.getElementById('close-development-modal');
        const closeBtnFooter = document.getElementById('close-development-modal-btn');
        const modalId = 'development-modal-overlay';

        closeBtn?.addEventListener('click', () => closeModal(modalId));
        closeBtnFooter?.addEventListener('click', () => closeModal(modalId));
    }

    // ── Init ────────────────────────────────────────
    function init() {
        initDropdowns();
        initReportModal();
        initLogout();
        initSearch();
        initDevelopmentModal();

        // Fermer les modals avec boutons [data-close-modal]
        document.querySelectorAll('[data-close-modal]').forEach(btn => {
            btn.addEventListener('click', () => {
                const modalId = btn.dataset.closeModal || btn.closest('.modal-overlay')?.id;
                if (modalId) closeModal(modalId);
            });
        });

        // Auto-update timestamps
        document.querySelectorAll('[data-time]').forEach(el => {
            el.textContent = timeAgo(el.dataset.time);
        });

        // Mise à jour périodique des timestamps
        setInterval(() => {
            document.querySelectorAll('[data-time]').forEach(el => {
                el.textContent = timeAgo(el.dataset.time);
            });
        }, 60000);
    }

    // DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // ── Public API ──────────────────────────────────
    return {
        toast,
        openModal,
        closeModal,
        timeAgo,
        formatNumber,
        debounce,
        throttle,
        escapeHtml
    };
})();

