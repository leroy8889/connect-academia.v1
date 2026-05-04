/**
 * Connect'Academia — Viewer
 * Gère progression + IA chat BACY + mobile drawer
 */
'use strict';

let ressourceId   = null;
let saveProgression = null;

/* Logo SVG plateforme — utilisé dans les avatars IA */
const BACY_LOGO_SVG = `<svg width="18" height="18" viewBox="0 0 40 40" fill="none" aria-hidden="true"><path d="M8 28L20 10L32 28H8Z" fill="white" fill-opacity="0.92"/><circle cx="20" cy="20" r="5" fill="white"/></svg>`;

function initViewer(ressId, lastPage, pdfUrl, progressionPct) {
    ressourceId     = ressId;
    saveProgression = initProgressionAuto(ressourceId);
    updateProgressBar(progressionPct || 0);
}

function updateProgressBar(pct) {
    const bar  = document.getElementById('progressBar');
    const text = document.getElementById('progressText');
    if (bar)  bar.style.width = pct + '%';
    if (text) text.textContent = pct + '%';
}

function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen?.();
    } else {
        document.exitFullscreen?.();
    }
}

// ── IA Chat ───────────────────────────────────────────────────
const IaChat = (() => {
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let isLoading = false;

    function init() {
        const textarea  = document.getElementById('ia-textarea');
        const sendBtn   = document.getElementById('ia-send');
        const toggleBtn = document.getElementById('ia-toggle');
        const panel     = document.getElementById('ia-panel');

        if (!textarea || !sendBtn) return;

        textarea.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                send();
            }
        });
        textarea.addEventListener('input', () => {
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 100) + 'px';
            sendBtn.disabled = textarea.value.trim().length === 0;
        });
        sendBtn.addEventListener('click', send);

        // Desktop toggle
        toggleBtn?.addEventListener('click', () => {
            panel?.classList.toggle('ia-panel--hidden');
        });

        // Mobile FAB — ouvre le drawer
        const mobileFab     = document.getElementById('ia-mobile-fab');
        const mobileOverlay = document.getElementById('ia-mobile-overlay');
        const closeBtn      = document.getElementById('ia-close-mobile');
        const handle        = document.getElementById('ia-panel-handle');

        function openMobile() {
            if (!panel) return;
            panel.classList.remove('ia-panel--hidden');
            panel.classList.add('mobile-open');
            if (mobileOverlay) mobileOverlay.style.display = 'block';
            if (closeBtn) closeBtn.style.display = 'flex';
            // Focus textarea pour UX mobile
            setTimeout(() => textarea?.focus(), 300);
        }

        function closeMobile() {
            if (!panel) return;
            panel.classList.remove('mobile-open');
            if (mobileOverlay) mobileOverlay.style.display = 'none';
            if (closeBtn) closeBtn.style.display = 'none';
        }

        mobileFab?.addEventListener('click', openMobile);
        mobileOverlay?.addEventListener('click', closeMobile);
        closeBtn?.addEventListener('click', closeMobile);
        handle?.addEventListener('click', closeMobile);

        loadHistorique();
    }

    async function loadHistorique() {
        if (!ressourceId) return;
        try {
            const res  = await fetch(`${window.CA?.baseUrl || ''}/api/apprentissage/ia/historique/${ressourceId}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const data = await res.json();
            if (data.success && data.data?.historique) {
                data.data.historique.forEach((entry) => {
                    appendMessage('user', entry.user_message);
                    appendMessage('ia', entry.ia_response);
                });
            }
        } catch (e) { /* silent */ }
    }

    async function send() {
        if (isLoading) return;
        const textarea = document.getElementById('ia-textarea');
        const sendBtn  = document.getElementById('ia-send');
        const message  = textarea?.value.trim();

        if (!message || !ressourceId) return;

        isLoading = true;
        if (sendBtn) sendBtn.disabled = true;
        textarea.value = '';
        textarea.style.height = 'auto';

        appendMessage('user', message);
        const loadingId = appendLoading();

        try {
            const res  = await fetch(`${window.CA?.baseUrl || ''}/api/apprentissage/ia/question`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrf(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ document_id: ressourceId, message }),
            });

            const data = await res.json();
            removeLoading(loadingId);

            if (data.success) {
                appendMessage('ia', data.response);
            } else {
                appendMessage('ia', '⚠️ ' + (data.error?.message || "Une erreur s'est produite."));
            }
        } catch (e) {
            removeLoading(loadingId);
            appendMessage('ia', '⚠️ Erreur de connexion. Vérifiez votre réseau.');
        } finally {
            isLoading = false;
            if (sendBtn) sendBtn.disabled = false;
        }
    }

    function appendMessage(role, text) {
        const container = document.getElementById('ia-messages');
        if (!container) return;

        const div = document.createElement('div');
        div.className = `ia-msg ia-msg--${role === 'ia' ? 'ia' : 'user'}`;

        const avatar = document.createElement('div');
        avatar.className = 'ia-msg__avatar';

        if (role === 'ia') {
            avatar.innerHTML = BACY_LOGO_SVG;
        } else {
            avatar.textContent = '👤';
        }

        const bubble = document.createElement('div');
        bubble.className = 'ia-msg__bubble';
        bubble.innerHTML = renderMarkdown(text);

        if (role === 'ia') {
            div.append(avatar, bubble);
        } else {
            div.append(bubble, avatar);
        }

        container.appendChild(div);
        container.scrollTop = container.scrollHeight;
        return div;
    }

    function appendLoading() {
        const container = document.getElementById('ia-messages');
        const id = 'ia-loading-' + Date.now();
        const div = document.createElement('div');
        div.id = id;
        div.className = 'ia-msg ia-msg--ia';

        const avatar = document.createElement('div');
        avatar.className = 'ia-msg__avatar';
        avatar.innerHTML = BACY_LOGO_SVG;

        const bubble = document.createElement('div');
        bubble.className = 'ia-msg__bubble';
        bubble.innerHTML = `<div class="ia-loading"><span></span><span></span><span></span></div>`;

        div.append(avatar, bubble);
        container.appendChild(div);
        container.scrollTop = container.scrollHeight;
        return id;
    }

    function removeLoading(id) {
        document.getElementById(id)?.remove();
    }

    function renderMarkdown(text) {
        return text
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.+?)\*/g, '<em>$1</em>')
            .replace(/`(.+?)`/g, '<code style="background:#F3F4F6;padding:1px 4px;border-radius:3px;font-family:monospace;font-size:.9em">$1</code>')
            .replace(/\n/g, '<br>');
    }

    return { init };
})();

document.addEventListener('DOMContentLoaded', () => {
    IaChat.init();
});
