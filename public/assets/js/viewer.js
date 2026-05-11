/**
 * Connect'Academia — Viewer
 * Gère progression + IA chat BACY + mobile drawer
 */
'use strict';

let ressourceId   = null;
let saveProgression = null;

/* Logo officiel plateforme — utilisé dans les avatars IA */
const BACY_LOGO_SVG = `<img src="${(window.CA?.baseUrl || '') + '/public/assets/images/logo-officiel.png'}" alt="BACY" style="width:22px;height:22px;object-fit:contain;">`;

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

function toggleResourceMinimize() {
    const layout      = document.getElementById('viewer-root');
    const panel       = document.getElementById('ia-panel');
    const minBtn      = document.getElementById('btn-minimize-resource');
    const restoreBtn  = document.getElementById('btn-restore-resource');

    const willCollapse = !layout.classList.contains('viewer--resource-collapsed');
    layout.classList.toggle('viewer--resource-collapsed', willCollapse);

    if (willCollapse) {
        // Open IA panel if hidden, then reveal restore button
        if (panel) panel.classList.remove('ia-panel--hidden');
        if (restoreBtn) restoreBtn.style.display = 'flex';
    } else {
        if (restoreBtn) restoreBtn.style.display = 'none';
    }

    // Swap icon on minimize button
    if (minBtn) {
        const icon = minBtn.querySelector('[data-lucide]');
        if (icon) {
            icon.setAttribute('data-lucide', willCollapse ? 'panel-right-open' : 'panel-right-close');
            if (window.lucide) lucide.createIcons({ nodes: [icon] });
        }
        minBtn.title = willCollapse ? 'Restaurer la ressource' : 'Agrandir le chat';
    }
}

// ── IA Chat ───────────────────────────────────────────────────
const IaChat = (() => {
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let isLoading = false;
    let selectedImage = null; // { data: base64string, mime: 'image/jpeg', name: 'filename.jpg' }
    let currentConversationId = null; // UUID de la conversation en cours

    const ALLOWED_IMAGE_TYPES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'image/heic', 'image/heif', 'image/bmp', 'image/tiff',
    ];
    const MAX_IMAGE_BYTES = 5 * 1024 * 1024; // 5 Mo

    function updateSendState() {
        const textarea = document.getElementById('ia-textarea');
        const sendBtn  = document.getElementById('ia-send');
        if (!textarea || !sendBtn) return;
        sendBtn.disabled = textarea.value.trim().length === 0 && !selectedImage;
    }

    function clearImage() {
        selectedImage = null;
        const preview   = document.getElementById('ia-image-preview');
        const thumb     = document.getElementById('ia-image-thumb');
        const fileInput = document.getElementById('ia-image-input');
        if (thumb)     thumb.src = '';
        if (preview)   preview.style.display = 'none';
        if (fileInput) fileInput.value = '';
        updateSendState();
    }

    function clearMessages(welcomeText) {
        const container = document.getElementById('ia-messages');
        if (!container) return;
        container.innerHTML = '';

        const div = document.createElement('div');
        div.className = 'ia-msg ia-msg--ia';

        const avatar = document.createElement('div');
        avatar.className = 'ia-msg__avatar';
        avatar.innerHTML = BACY_LOGO_SVG;

        const bubble = document.createElement('div');
        bubble.className = 'ia-msg__bubble';
        bubble.innerHTML = welcomeText || 'Bonjour ! Je suis l\'assistant Connect\'Academia. Posez-moi vos questions sur cette ressource.';

        div.append(avatar, bubble);
        container.appendChild(div);
    }

    function init() {
        const textarea    = document.getElementById('ia-textarea');
        const sendBtn     = document.getElementById('ia-send');
        const toggleBtn   = document.getElementById('ia-toggle');
        const panel       = document.getElementById('ia-panel');
        const imageBtn    = document.getElementById('ia-image-btn');
        const imageInput  = document.getElementById('ia-image-input');
        const imageRemove = document.getElementById('ia-image-remove');
        const newChatBtn  = document.getElementById('ia-new-chat');

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
            updateSendState();
        });
        sendBtn.addEventListener('click', send);

        // Bouton image → ouvre le sélecteur de fichier
        imageBtn?.addEventListener('click', () => imageInput?.click());

        // Supprimer l'image sélectionnée
        imageRemove?.addEventListener('click', clearImage);

        // Bouton nouvelle conversation
        newChatBtn?.addEventListener('click', startNewConversation);

        // Sélection de fichier
        imageInput?.addEventListener('change', (e) => {
            const file = e.target.files?.[0];
            if (!file) return;

            if (!ALLOWED_IMAGE_TYPES.includes(file.type)) {
                showToast('Format non supporté. Utilisez JPG, PNG, GIF ou WEBP.', 'error');
                imageInput.value = '';
                return;
            }
            if (file.size > MAX_IMAGE_BYTES) {
                showToast('Image trop grande. Maximum 5 Mo.', 'error');
                imageInput.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = (ev) => {
                const dataUrl = ev.target.result;
                // dataUrl = "data:image/jpeg;base64,XXXX"
                const base64 = dataUrl.split(',')[1];
                selectedImage = { data: base64, mime: file.type, name: file.name };

                const preview = document.getElementById('ia-image-preview');
                const thumb   = document.getElementById('ia-image-thumb');
                if (thumb)   thumb.src = dataUrl;
                if (preview) preview.style.display = 'flex';

                updateSendState();
            };
            reader.onerror = () => showToast("Impossible de lire ce fichier.", 'error');
            reader.readAsDataURL(file);
        });

        // Desktop toggle
        toggleBtn?.addEventListener('click', () => {
            const isNowHidden = panel?.classList.toggle('ia-panel--hidden');
            if (isNowHidden) {
                const layout = document.getElementById('viewer-root');
                if (layout?.classList.contains('viewer--resource-collapsed')) {
                    toggleResourceMinimize();
                }
            }
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
                // Stocker le conversation_id de la session en cours
                if (data.data.conversation_id) {
                    currentConversationId = data.data.conversation_id;
                }
                data.data.historique.forEach((entry) => {
                    appendMessage('user', entry.user_message);
                    appendMessage('ia', entry.ia_response);
                });
            }
        } catch (e) { /* silent */ }
    }

    async function startNewConversation() {
        if (isLoading) return;

        const btn = document.getElementById('ia-new-chat');
        if (btn) {
            btn.disabled = true;
            btn.classList.add('ia-new-chat--loading');
        }

        try {
            const res = await fetch(`${window.CA?.baseUrl || ''}/api/apprentissage/ia/nouvelle-conversation`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrf(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ document_id: ressourceId }),
            });

            const data = await res.json();
            if (data.success && data.conversation_id) {
                currentConversationId = data.conversation_id;
                clearImage();
                clearMessages('Nouvelle conversation démarrée ! Posez-moi vos questions sur cette ressource.');

                // Ré-activer l'input si désactivé par quota (quota toujours valide côté session)
                const ta  = document.getElementById('ia-textarea');
                const snd = document.getElementById('ia-send');
                if (ta && ta.dataset.quotaDisabled !== 'true') {
                    ta.disabled = false;
                    ta.placeholder = 'Posez votre question…';
                }
                updateSendState();

                showToast('Nouvelle conversation démarrée ✓');
            } else {
                showToast("Impossible de démarrer une nouvelle conversation.", 'error');
            }
        } catch (e) {
            showToast("Erreur réseau. Vérifiez votre connexion.", 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.classList.remove('ia-new-chat--loading');
            }
        }
    }

    async function send() {
        if (isLoading) return;
        const textarea = document.getElementById('ia-textarea');
        const sendBtn  = document.getElementById('ia-send');
        const message  = textarea?.value.trim();
        const image    = selectedImage;

        if ((!message && !image) || !ressourceId) return;

        isLoading = true;
        if (sendBtn) sendBtn.disabled = true;
        textarea.value = '';
        textarea.style.height = 'auto';

        // Capturer l'image avant clearImage()
        const imageSnapshot = image;
        clearImage();

        appendMessage('user', message, imageSnapshot);
        const loadingId = appendLoading();

        const body = {
            document_id:     ressourceId,
            message:         message || '',
            conversation_id: currentConversationId,
        };
        if (imageSnapshot) {
            body.image_data = imageSnapshot.data;
            body.image_mime = imageSnapshot.mime;
        }

        try {
            const res = await fetch(`${window.CA?.baseUrl || ''}/api/apprentissage/ia/question`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrf(),
                },
                credentials: 'same-origin',
                body: JSON.stringify(body),
            });

            const data = await res.json();
            removeLoading(loadingId);

            if (data.success) {
                // Mettre à jour le conversation_id retourné par le backend
                if (data.conversation_id) {
                    currentConversationId = data.conversation_id;
                }
                appendMessage('ia', data.response);
                if (typeof data.remaining_requests === 'number') {
                    appendQuotaNotice(data.remaining_requests);
                }
            } else {
                appendMessage('ia', '⚠️ ' + (data.error?.message || "Une erreur s'est produite."));
            }
        } catch (e) {
            removeLoading(loadingId);
            appendMessage('ia', '⚠️ Erreur de connexion. Vérifiez votre réseau.');
        } finally {
            isLoading = false;
            updateSendState();
        }
    }

    function appendMessage(role, text, image = null) {
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

        // Afficher l'image jointe dans la bulle utilisateur
        if (image && role === 'user') {
            const imgEl = document.createElement('img');
            imgEl.src = `data:${image.mime};base64,${image.data}`;
            imgEl.alt = image.name || 'Image';
            imgEl.className = 'ia-msg__image';
            bubble.appendChild(imgEl);
        }

        if (text) {
            const textNode = document.createElement('div');
            textNode.innerHTML = renderMarkdown(text);
            bubble.appendChild(textNode);
        }

        if (role === 'ia') {
            // Wrapper body : bulle + bouton PDF
            const body = document.createElement('div');
            body.className = 'ia-msg__body';
            body.appendChild(bubble);

            if (text) {
                const actions = document.createElement('div');
                actions.className = 'ia-msg__actions';

                const dlBtn = document.createElement('button');
                dlBtn.className = 'ia-download-btn';
                dlBtn.type = 'button';
                dlBtn.title = 'Télécharger cette réponse en PDF';
                dlBtn.innerHTML = `<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Télécharger PDF`;
                dlBtn.addEventListener('click', () => downloadResponseAsPdf(bubble));

                actions.appendChild(dlBtn);
                body.appendChild(actions);
            }

            div.append(avatar, body);
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

    function appendQuotaNotice(remaining) {
        const container = document.getElementById('ia-messages');
        if (!container) return;

        let cls  = 'ia-quota-badge';
        let text = '';
        let icon = '';

        const iconClock = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
        const iconWarn  = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
        const iconStop  = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>';

        if (remaining === 0) {
            cls  += ' ia-quota-badge--critical';
            icon  = iconStop;
            text  = 'Limite atteinte. Revenez demain !';
            // Désactiver l'input jusqu'au rechargement
            const ta  = document.getElementById('ia-textarea');
            const btn = document.getElementById('ia-send');
            if (ta)  {
                ta.disabled = true;
                ta.placeholder = 'Limite quotidienne atteinte.';
                ta.dataset.quotaDisabled = 'true';
            }
            if (btn) btn.disabled = true;
        } else if (remaining <= 3) {
            cls  += ' ia-quota-badge--critical';
            icon  = iconWarn;
            text  = `Il vous reste ${remaining} requête${remaining > 1 ? 's' : ''}`;
        } else if (remaining <= 7) {
            cls  += ' ia-quota-badge--warning';
            icon  = iconWarn;
            text  = `Il vous reste ${remaining} requêtes`;
        } else {
            icon  = iconClock;
            text  = `Il vous reste ${remaining} requêtes`;
        }

        const notice = document.createElement('div');
        notice.className = 'ia-quota-notice';
        notice.innerHTML = `<span class="${cls}">${icon} ${text}</span>`;
        container.appendChild(notice);
        container.scrollTop = container.scrollHeight;
    }

    function renderMarkdown(text) {
        const mathSlots = [];
        let slotIdx = 0;

        function saveMath(math, display) {
            const placeholder = '\x00M' + (slotIdx++) + '\x00';
            mathSlots.push({ placeholder, math, display });
            return placeholder;
        }

        // Extract display math BEFORE inline ($$...$$ must precede $...$)
        text = text.replace(/\$\$([\s\S]+?)\$\$/g,   (_, m) => saveMath(m, true));
        text = text.replace(/\\\[([\s\S]+?)\\\]/g,    (_, m) => saveMath(m, true));
        text = text.replace(/\\\((.+?)\\\)/g,         (_, m) => saveMath(m, false));
        text = text.replace(/\$([^\$\n]+?)\$/g,       (_, m) => saveMath(m, false));

        // HTML-escape (math is safely in placeholders)
        let html = text
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

        // Headings — must run before \n → <br> to use multiline anchors
        html = html
            .replace(/^### (.+)$/gm, '<h3 class="ia-md-h3">$1</h3>')
            .replace(/^## (.+)$/gm,  '<h2 class="ia-md-h2">$1</h2>')
            .replace(/^# (.+)$/gm,   '<h1 class="ia-md-h1">$1</h1>');

        // Inline formatting
        html = html
            .replace(/\*\*(.+?)\*\*/g, '<strong class="ia-md-bold">$1</strong>')
            .replace(/\*(.+?)\*/g, '<em>$1</em>')
            .replace(/`(.+?)`/g, '<code style="background:#F3F4F6;padding:1px 4px;border-radius:3px;font-family:monospace;font-size:.9em">$1</code>')
            .replace(/\n/g, '<br>');

        // Restore math blocks with KaTeX (or <code> fallback)
        for (const { placeholder, math, display } of mathSlots) {
            let rendered;
            if (window.katex) {
                try {
                    rendered = window.katex.renderToString(math, {
                        displayMode: display,
                        throwOnError: false,
                        output: 'html',
                        trust: false,
                    });
                } catch (_) {
                    rendered = '<code>' + math.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</code>';
                }
            } else {
                rendered = '<code>' + math.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</code>';
            }
            // split/join évite l'interprétation de $ dans String.replace
            html = html.split(placeholder).join(rendered);
        }

        return html;
    }

    function downloadResponseAsPdf(bubbleEl) {
        if (!window.html2pdf) {
            showToast('Génération PDF non disponible. Rechargez la page.', 'error');
            return;
        }

        const btn = bubbleEl.closest('.ia-msg--ia')?.querySelector('.ia-download-btn');
        if (btn) {
            btn.disabled = true;
            btn.classList.add('ia-download-btn--loading');
        }

        const date = new Date().toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' });
        const ressourceTitre = document.querySelector('.viewer-sidebar__title')?.textContent?.trim() || '';
        const filename = `Connect'Academia-reponse-${new Date().toISOString().slice(0, 10)}.pdf`;

        // Masque plein-écran : cache le wrapper pendant la génération
        const mask = document.createElement('div');
        mask.setAttribute('aria-hidden', 'true');
        mask.style.cssText = [
            'position:fixed', 'inset:0', 'z-index:99999',
            'background:rgba(255,255,255,0.97)',
            'display:flex', 'align-items:center', 'justify-content:center',
            'pointer-events:none',
        ].join(';');
        mask.innerHTML = '<div style="color:#8B52FA;font-family:Arial,sans-serif;font-weight:600;font-size:14px;">Génération du PDF…</div>';

        // Wrapper positionné dans la viewport (position:fixed) pour contourner
        // le overflow:hidden de #main-content qui coupait le rendu html2canvas
        const wrapper = document.createElement('div');
        wrapper.setAttribute('aria-hidden', 'true');
        wrapper.style.cssText = [
            'position:fixed', 'top:0', 'left:0',
            'width:700px', 'background:#ffffff',
            'padding:32px 36px', 'box-sizing:border-box',
            'font-family:Arial,Helvetica,sans-serif',
            'font-size:13px', 'line-height:1.7', 'color:#1A1A1A',
            'z-index:99998', 'pointer-events:none',
        ].join(';');

        wrapper.innerHTML = `
            <div style="border-bottom:2.5px solid #8B52FA;padding-bottom:14px;margin-bottom:20px;display:flex;align-items:center;gap:14px;">
                <div>
                    <div style="font-size:19px;font-weight:700;color:#8B52FA;letter-spacing:-0.3px;">Connect'Academia</div>
                    <div style="font-size:11px;color:#6B7280;margin-top:3px;">Réponse de l'assistant IA · ${date}</div>
                </div>
            </div>
            ${ressourceTitre ? `<div style="font-size:11px;color:#6B7280;margin-bottom:16px;font-style:italic;padding:8px 12px;background:#F3EFFF;border-radius:6px;border-left:3px solid #8B52FA;">Ressource : ${ressourceTitre}</div>` : ''}
            <div id="ia-pdf-content"></div>
            <div style="margin-top:28px;border-top:1px solid #E5E7EB;padding-top:12px;font-size:10px;color:#9CA3AF;text-align:center;">
                Généré par Connect'Academia — La plateforme éducative gabonaise
            </div>`;

        document.body.appendChild(wrapper);
        document.body.appendChild(mask);

        // Clone bubble content et forcer les styles inline pour le PDF
        const pdfContent = wrapper.querySelector('#ia-pdf-content');
        const clone = bubbleEl.cloneNode(true);

        // Reset les classes CSS du clone pour éviter tout conflit avec les vars CSS du thème
        clone.style.cssText = 'display:block;width:100%;box-sizing:border-box;color:#1A1A1A;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.7;background:none;border:none;padding:0;max-width:none;';

        pdfContent.appendChild(clone);

        clone.querySelectorAll('.ia-md-h1').forEach(el => {
            el.style.cssText = 'color:#8B52FA;font-weight:700;font-size:17px;margin:14px 0 7px;display:block;font-family:Arial,sans-serif;';
        });
        clone.querySelectorAll('.ia-md-h2').forEach(el => {
            el.style.cssText = 'color:#8B52FA;font-weight:700;font-size:15px;margin:12px 0 6px;display:block;font-family:Arial,sans-serif;';
        });
        clone.querySelectorAll('.ia-md-h3').forEach(el => {
            el.style.cssText = 'color:#8B52FA;font-weight:700;font-size:14px;margin:10px 0 5px;display:block;font-family:Arial,sans-serif;';
        });
        clone.querySelectorAll('.ia-md-bold, strong').forEach(el => {
            el.style.cssText = 'color:#8B52FA;font-weight:700;';
        });
        clone.querySelectorAll('code').forEach(el => {
            el.style.cssText = 'background:#F3F4F6;padding:1px 5px;border-radius:4px;font-family:monospace;font-size:12px;color:#1A1A1A;';
        });
        clone.querySelectorAll('em').forEach(el => {
            el.style.fontStyle = 'italic';
        });
        // Texte brut dans les div enfants : forcer couleur visible
        clone.querySelectorAll('div, span, p, li, br + *').forEach(el => {
            if (!el.style.color) el.style.color = '#1A1A1A';
        });
        // Supprimer le bouton download qui aurait pu se glisser dans le clone
        clone.querySelectorAll('.ia-msg__actions, .ia-download-btn').forEach(el => el.remove());

        const cleanup = () => {
            if (document.body.contains(wrapper)) document.body.removeChild(wrapper);
            if (document.body.contains(mask))    document.body.removeChild(mask);
            if (btn) { btn.disabled = false; btn.classList.remove('ia-download-btn--loading'); }
        };

        // Laisser le DOM se stabiliser avant la capture html2canvas
        requestAnimationFrame(() => {
            html2pdf().set({
                margin:      [12, 10, 12, 10],
                filename:    filename,
                image:       { type: 'jpeg', quality: 0.97 },
                html2canvas: { scale: 2, useCORS: true, allowTaint: true, logging: false },
                jsPDF:       { unit: 'mm', format: 'a4', orientation: 'portrait' },
            }).from(wrapper).save().then(cleanup).catch(cleanup);
        });
    }

    return { init };
})();

document.addEventListener('DOMContentLoaded', () => {
    IaChat.init();
});
