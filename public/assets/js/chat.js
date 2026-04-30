/**
 * Connect'Academia — Chat Long Polling
 */
'use strict';

const Chat = (() => {
    const csrf    = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const baseUrl = () => window.CA?.baseUrl || '';

    let salonId   = null;
    let lastId    = 0;
    let polling   = false;
    let pollTimer = null;

    function init(sid, initialLastId = 0) {
        salonId = sid;
        lastId  = initialLastId;

        const form    = document.getElementById('chat-form');
        const input   = document.getElementById('chat-input');
        const sendBtn = document.getElementById('chat-send');

        if (!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const msg = input?.value.trim();
            if (!msg) return;

            sendBtn && (sendBtn.disabled = true);
            try {
                await API.post(`/api/communaute/salons/${salonId}/messages`, { contenu: msg });
                if (input) { input.value = ''; input.style.height = 'auto'; }
            } catch (err) {
                showChatError(err.message || 'Erreur envoi');
            } finally {
                sendBtn && (sendBtn.disabled = false);
                input?.focus();
            }
        });

        input?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); }
        });
        input?.addEventListener('input', () => {
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 120) + 'px';
        });

        scrollToBottom();
        startPolling();
    }

    async function startPolling() {
        if (polling) return;
        polling = true;

        while (polling) {
            try {
                const url = `${baseUrl()}/api/communaute/salons/${salonId}/messages/poll?last_id=${lastId}&timeout=20`;
                const res = await fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    signal: AbortSignal.timeout(25000),
                });
                const data = await res.json();

                if (data.success && data.data?.messages?.length) {
                    data.data.messages.forEach((msg) => {
                        appendMessage(msg);
                        lastId = Math.max(lastId, msg.id);
                    });
                    scrollToBottom();
                }
            } catch (e) {
                await sleep(3000);
            }
        }
    }

    function appendMessage(msg) {
        const container = document.getElementById('chat-messages');
        if (!container) return;

        const isMe   = msg.is_me || (msg.user_id === window.CA?.userId);
        const photo  = msg.photo_profil || (baseUrl() + '/public/assets/images/default-avatar.svg');
        const name   = msg.prenom + ' ' + msg.nom;
        const time   = new Date(msg.created_at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });

        const div = document.createElement('div');
        div.className = `chat-msg ${isMe ? 'chat-msg--me' : ''}`;
        div.dataset.id = msg.id;
        div.innerHTML = `
            ${!isMe ? `<img class="chat-msg__avatar" src="${esc(photo)}" alt="${esc(name)}" onerror="this.src='${baseUrl()}/public/assets/images/default-avatar.svg'">` : ''}
            <div class="chat-msg__body">
                ${!isMe ? `<span class="chat-msg__name">${esc(name)}</span>` : ''}
                <div class="chat-msg__bubble">${esc(msg.contenu)}</div>
                <span class="chat-msg__time">${time}</span>
            </div>
            ${isMe ? `<img class="chat-msg__avatar" src="${esc(photo)}" alt="${esc(name)}" onerror="this.src='${baseUrl()}/public/assets/images/default-avatar.svg'">` : ''}
        `;
        container.appendChild(div);
    }

    function scrollToBottom() {
        const container = document.getElementById('chat-messages');
        if (container) container.scrollTop = container.scrollHeight;
    }

    function showChatError(msg) {
        const container = document.getElementById('chat-messages');
        if (!container) return;
        const div = document.createElement('div');
        div.className = 'chat-msg chat-msg--error';
        div.innerHTML = `<div class="chat-msg__bubble" style="background:#FEE2E2;color:#DC2626">${esc(msg)}</div>`;
        container.appendChild(div);
        setTimeout(() => div.remove(), 5000);
    }

    function esc(str) {
        const div = document.createElement('div');
        div.textContent = String(str || '');
        return div.innerHTML;
    }

    function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

    function stop() { polling = false; }

    return { init, stop };
})();
