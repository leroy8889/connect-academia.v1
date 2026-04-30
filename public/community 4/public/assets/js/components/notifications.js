/**
 * StudyLink — Notifications Component
 * Gère le dropdown de notifications et le polling
 */

const Notifications = (() => {
    'use strict';

    // ── State ───────────────────────────────────────
    let isOpen = false;
    let pollInterval = null;
    let unreadCount = 0;

    // ── DOM ─────────────────────────────────────────
    const elements = {};

    function cacheDOM() {
        elements.btn = document.getElementById('notif-btn');
        elements.badge = document.getElementById('notif-badge');
        elements.dropdown = document.getElementById('notif-dropdown');
        elements.list = document.getElementById('notif-list');
        elements.readAllBtn = document.getElementById('notif-read-all');
    }

    // ── Init ────────────────────────────────────────
    function init() {
        cacheDOM();
        if (!elements.btn) return;

        // Toggle dropdown
        elements.btn.addEventListener('click', (e) => {
            e.stopPropagation();
            toggle();
        });

        // Read all
        elements.readAllBtn?.addEventListener('click', markAllRead);

        // Close on click outside
        document.addEventListener('click', (e) => {
            if (isOpen && !elements.dropdown?.contains(e.target)) {
                close();
            }
        });

        // Start polling
        startPolling();

        // Charger initial
        fetchNotifications();
    }

    // ── Toggle ──────────────────────────────────────
    function toggle() {
        if (isOpen) {
            close();
        } else {
            open();
        }
    }

    function open() {
        if (!elements.dropdown) return;
        elements.dropdown.classList.remove('hidden');
        isOpen = true;
        fetchNotifications();
    }

    function close() {
        if (!elements.dropdown) return;
        elements.dropdown.classList.add('hidden');
        isOpen = false;
    }

    // ── Fetch Notifications ─────────────────────────
    async function fetchNotifications() {
        try {
            const response = await API.get('/api/notifications');
            const notifications = response.data?.notifications || [];
            unreadCount = response.data?.unread_count || 0;

            updateBadge();
            renderNotifications(notifications);
        } catch (error) {
            console.error('Error fetching notifications:', error);
        }
    }

    // ── Render ──────────────────────────────────────
    function renderNotifications(notifications) {
        if (!elements.list) return;

        if (notifications.length === 0) {
            elements.list.innerHTML = `
                <div class="notif-dropdown__empty">
                    <p>Aucune notification</p>
                </div>
            `;
            return;
        }

        elements.list.innerHTML = notifications.map(notif => {
            const baseUrl = window.STUDYLINK_CONFIG?.baseUrl || '';
            const avatar = notif.actor_photo
                ? (notif.actor_photo.startsWith('http') ? notif.actor_photo : `${baseUrl}${notif.actor_photo}`)
                : `${baseUrl}/public/assets/images/default-avatar.svg`;
            const time = App.timeAgo(notif.created_at);
            const unreadClass = !notif.is_read ? 'notif-item--unread' : '';

            return `
                <div class="notif-item ${unreadClass}" data-notif-id="${notif.id}" onclick="Notifications.handleClick(${notif.id}, '${App.escapeHtml(notif.link || '')}')">
                    <img class="notif-item__avatar" src="${App.escapeHtml(avatar)}" alt="" loading="lazy">
                    <div>
                        <p class="notif-item__text">${formatNotifMessage(notif)}</p>
                        <span class="notif-item__time">${time}</span>
                    </div>
                </div>
            `;
        }).join('');
    }

    // ── Format Notification Message ─────────────────
    function formatNotifMessage(notif) {
        const actorName = (notif.actor_prenom && notif.actor_nom)
            ? `${notif.actor_prenom} ${notif.actor_nom}`
            : 'Quelqu\'un';
        const name = `<strong>${App.escapeHtml(actorName)}</strong>`;

        switch (notif.type) {
            case 'like':
                return `${name} a aimé votre publication`;
            case 'comment':
                return `${name} a commenté votre publication`;
            case 'follow':
                return `${name} a commencé à vous suivre`;
            case 'mention':
                return `${name} vous a mentionné dans un commentaire`;
            case 'reply':
                return `${name} a répondu à votre commentaire`;
            case 'announcement':
                return `${name} a publié une annonce`;
            default:
                return notif.message ? App.escapeHtml(notif.message) : `${name} a interagi avec vous`;
        }
    }

    // ── Handle Click ────────────────────────────────
    async function handleClick(notifId, link) {
        try {
            await API.patch(`/api/notifications/${notifId}/read`);

            // Update UI
            const item = document.querySelector(`[data-notif-id="${notifId}"]`);
            if (item) item.classList.remove('notif-item--unread');

            unreadCount = Math.max(0, unreadCount - 1);
            updateBadge();

            // Ne pas rediriger - l'utilisateur a demandé que les notifications ne renvoient nulle part
        } catch (error) {
            console.error('Erreur lors de la mise à jour de la notification:', error);
        }
    }

    // ── Mark All Read ───────────────────────────────
    async function markAllRead() {
        try {
            await API.patch('/api/notifications/read-all');
            unreadCount = 0;
            updateBadge();

            // Update UI
            elements.list?.querySelectorAll('.notif-item--unread').forEach(item => {
                item.classList.remove('notif-item--unread');
            });

            App.toast.success('Toutes les notifications marquées comme lues');
        } catch (error) {
            App.toast.error('Erreur');
        }
    }

    // ── Badge ───────────────────────────────────────
    function updateBadge() {
        if (!elements.badge) return;
        if (unreadCount > 0) {
            elements.badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
            elements.badge.classList.remove('hidden');
        } else {
            elements.badge.classList.add('hidden');
        }
    }

    // ── Polling ─────────────────────────────────────
    function startPolling() {
        pollInterval = setInterval(async () => {
            try {
                const response = await API.get('/api/notifications/count');
                const newCount = response.data?.unread_count || 0;
                if (newCount !== unreadCount) {
                    unreadCount = newCount;
                    updateBadge();
                }
            } catch (e) {
                // Silently fail
            }
        }, 30000);
    }

    // ── Init on load ────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    return {
        init,
        toggle,
        open,
        close,
        handleClick,
        markAllRead
    };
})();

