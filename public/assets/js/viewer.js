/**
 * Connect'Academia — Viewer PDF
 * Gère l'affichage iframe + progression + IA chat
 */
'use strict';

let ressourceId   = null;
let saveProgression = null;

function initViewer(ressId, lastPage, pdfUrl, progressionPct) {
    ressourceId     = ressId;
    saveProgression = initProgressionAuto(ressourceId);

    // Mettre à jour la barre de progression si déjà en cours
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
        });
        sendBtn.addEventListener('click', send);

        toggleBtn?.addEventListener('click', () => {
            panel?.classList.toggle('ia-panel--hidden');
        });

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
        avatar.textContent = role === 'ia' ? '✦' : '👤';

        const bubble = document.createElement('div');
        bubble.className = 'ia-msg__bubble';
        // Rendu Markdown basique
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
        div.innerHTML = `<div class="ia-msg__avatar">✦</div>
            <div class="ia-msg__bubble" style="display:flex;gap:4px;align-items:center">
                <span style="animation:blink 1s infinite">●</span>
                <span style="animation:blink 1s .3s infinite">●</span>
                <span style="animation:blink 1s .6s infinite">●</span>
            </div>`;
        container.appendChild(div);
        container.scrollTop = container.scrollHeight;

        const style = document.createElement('style');
        style.textContent = '@keyframes blink{0%,100%{opacity:.2}50%{opacity:1}}';
        document.head.appendChild(style);

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
