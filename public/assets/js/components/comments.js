/**
 * StudyLink — Comments Panel Component
 * Gère le slide panel des commentaires
 */

const Comments = (() => {
    'use strict';

    // ── State ───────────────────────────────────────
    let currentPostId = null;
    let currentPage = 1;
    let isLoading = false;
    let hasMore = true;
    let replyTo = null;
    let sortBy = 'recent';

    // ── DOM ─────────────────────────────────────────
    const elements = {};

    function cacheDOM() {
        elements.overlay = document.getElementById('comments-overlay');
        elements.panel = document.getElementById('comments-panel');
        elements.list = document.getElementById('comments-list');
        elements.title = document.getElementById('comments-title');
        elements.count = document.getElementById('comments-count');
        elements.textarea = document.getElementById('comment-textarea');
        elements.sendBtn = document.getElementById('comment-send-btn');
        elements.closeBtn = document.getElementById('comments-close-btn');
        elements.sortBtns = document.querySelectorAll('.comments-panel__sort-btn');
        elements.replyIndicator = document.getElementById('reply-indicator');
        elements.replyCancelBtn = document.getElementById('reply-cancel-btn');
    }

    // ── Init ────────────────────────────────────────
    function init() {
        cacheDOM();
        if (!elements.panel) return;

        // Close panel
        elements.closeBtn?.addEventListener('click', close);
        elements.overlay?.addEventListener('click', close);

        // Sort
        elements.sortBtns?.forEach(btn => {
            btn.addEventListener('click', () => {
                sortBy = btn.dataset.sort;
                elements.sortBtns.forEach(b => b.classList.remove('comments-panel__sort-btn--active'));
                btn.classList.add('comments-panel__sort-btn--active');
                resetComments();
                loadComments();
            });
        });

        // Textarea auto-resize + send activation
        elements.textarea?.addEventListener('input', () => {
            autoResize(elements.textarea);
            const hasText = elements.textarea.value.trim().length > 0;
            elements.sendBtn?.classList.toggle('comments-panel__send-btn--active', hasText);
        });

        // Send on button click
        elements.sendBtn?.addEventListener('click', submitComment);

        // Send on Ctrl+Enter
        elements.textarea?.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                submitComment();
            }
        });

        // Cancel reply
        elements.replyCancelBtn?.addEventListener('click', cancelReply);

        // Escape to close
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && elements.panel?.classList.contains('comments-panel--open')) {
                close();
            }
        });
    }

    // ── Open Panel ──────────────────────────────────
    function open(postId) {
        currentPostId = postId;
        resetComments();

        elements.overlay?.classList.add('comments-overlay--visible');
        elements.panel?.classList.add('comments-panel--open');
        document.body.style.overflow = 'hidden';

        loadComments();
        elements.textarea?.focus();
    }

    // ── Close Panel ─────────────────────────────────
    function close() {
        elements.overlay?.classList.remove('comments-overlay--visible');
        elements.panel?.classList.remove('comments-panel--open');
        document.body.style.overflow = '';
        currentPostId = null;
        cancelReply();
    }

    // ── Reset ───────────────────────────────────────
    function resetComments() {
        currentPage = 1;
        hasMore = true;
        if (elements.list) elements.list.innerHTML = '';
    }

    // ── Load Comments ───────────────────────────────
    async function loadComments() {
        if (isLoading || !hasMore || !currentPostId) return;
        isLoading = true;
        showLoading(true);

        try {
            const params = new URLSearchParams({
                page: currentPage,
                sort: sortBy
            });

            const response = await API.get(`/api/communaute/posts/${currentPostId}/comments?${params}`);
            const comments = response.data?.comments || [];
            const total = response.data?.total || 0;

            // Update count
            if (elements.count) {
                elements.count.textContent = `(${total})`;
            }

            if (comments.length === 0) {
                hasMore = false;
                if (currentPage === 1) showEmpty();
            } else {
                comments.forEach(comment => {
                    elements.list.appendChild(createCommentElement(comment));
                });
                currentPage++;
            }
        } catch (error) {
            App.toast.error('Erreur lors du chargement des commentaires');
        } finally {
            isLoading = false;
            showLoading(false);
        }
    }

    // ── Create Comment Element ──────────────────────
    function createCommentElement(comment) {
        const div = document.createElement('div');
        const isBest = comment.is_best_answer;
        div.className = `comment ${isBest ? 'comment--best' : ''}`;
        div.dataset.commentId = comment.id;

        const userName = (comment.prenom && comment.nom)
            ? `${comment.prenom} ${comment.nom}`
            : (comment.user_name || 'Utilisateur');
        const baseUrl = window.CA?.baseUrl || '';
        // Utiliser directement le chemin normalisé depuis l'API
        let avatarUrl = baseUrl ? `${baseUrl}/public/assets/images/default-avatar.svg` : '/public/assets/images/default-avatar.svg';
        if (comment.photo_profil && comment.photo_profil.trim() !== '' && comment.photo_profil !== 'null') {
            if (comment.photo_profil.startsWith('http://') || comment.photo_profil.startsWith('https://')) {
                avatarUrl = comment.photo_profil;
            } else if (comment.photo_profil.startsWith('/')) {
                // Le chemin commence par /, il est déjà normalisé avec BASE_URL côté serveur
                avatarUrl = comment.photo_profil;
            } else {
                // Chemin relatif, construire avec BASE_URL
                avatarUrl = baseUrl ? `${baseUrl}/${comment.photo_profil}` : `/${comment.photo_profil}`;
            }
        }
        const timeStr = App.timeAgo(comment.created_at);
        const isLiked = comment.is_liked_by_me ? 'comment__action-btn--liked' : '';

        let badgeHtml = '';
        if (comment.user_role === 'enseignant') {
            badgeHtml = '<span class="comment__badge comment__badge--teacher">Enseignant</span>';
        }
        if (isBest) {
            badgeHtml += '<span class="comment__badge comment__badge--best">✓ Meilleure réponse</span>';
        }

        // Si le commentaire a des réponses chargées en ligne
        let repliesHtml = '';
        if (comment.replies && comment.replies.length > 0) {
            repliesHtml = `
                <button class="comment__show-replies" data-comment-id="${comment.id}">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 12 15 18 9"/>
                    </svg>
                    Voir ${comment.replies.length} réponse${comment.replies.length > 1 ? 's' : ''}
                </button>
                <div class="comment__replies hidden" id="replies-${comment.id}"></div>
            `;
        }

        div.innerHTML = `
            <img class="comment__avatar" src="${App.escapeHtml(avatarUrl)}" alt="${App.escapeHtml(userName)}" loading="lazy">
            <div class="comment__body">
                <div class="comment__bubble">
                    <div class="comment__author">
                        <a href="${baseUrl}/profile/${comment.user_id}" class="comment__name">${App.escapeHtml(userName)}</a>
                        ${badgeHtml}
                    </div>
                    <p class="comment__text">${formatCommentText(comment.contenu)}</p>
                </div>
                <div class="comment__actions">
                    <span class="comment__time" data-time="${comment.created_at}">${timeStr}</span>
                    <button class="comment__action-btn ${isLiked}" onclick="Comments.toggleLike(${comment.id}, this)">
                        ${comment.likes_count > 0 ? `${comment.likes_count} ` : ''}J'aime
                    </button>
                    <button class="comment__reply-btn" onclick="Comments.setReply(${comment.id}, '${App.escapeHtml(userName)}')">
                        Répondre
                    </button>
                </div>
                ${repliesHtml}
            </div>
        `;

        // Show replies toggle — loads from API or shows pre-loaded inline replies
        const showRepliesBtn = div.querySelector('.comment__show-replies');
        if (showRepliesBtn) {
            showRepliesBtn.addEventListener('click', () => {
                const container = document.getElementById(`replies-${comment.id}`);
                if (!container) return;
                const isVisible = !container.classList.contains('hidden');
                if (isVisible) {
                    container.classList.add('hidden');
                    showRepliesBtn.querySelector('svg').style.transform = '';
                    return;
                }
                container.classList.remove('hidden');
                showRepliesBtn.querySelector('svg').style.transform = 'rotate(180deg)';
                // Load pre-fetched inline replies if not yet rendered
                if (container.children.length === 0 && comment.replies) {
                    comment.replies.forEach(reply => {
                        container.appendChild(createCommentElement(reply));
                    });
                } else if (container.children.length === 0) {
                    loadReplies(comment.id);
                }
            });
        }

        return div;
    }

    // ── Format Comment Text ─────────────────────────
    function formatCommentText(text) {
        if (!text) return '';
        let formatted = App.escapeHtml(text);
        const baseUrl = window.CA?.baseUrl || '';
        formatted = formatted.replace(/@(\w+)/g, `<a href="${baseUrl}/profile/$1" class="mention">@$1</a>`);
        formatted = formatted.replace(/\n/g, '<br>');
        return formatted;
    }

    // ── Submit Comment ──────────────────────────────
    async function submitComment() {
        const content = elements.textarea?.value.trim();
        if (!content || !currentPostId) return;

        const data = {
            contenu: content,
            post_id: currentPostId,
            parent_id: replyTo?.commentId || null
        };

        // Disable while sending
        elements.sendBtn?.classList.remove('comments-panel__send-btn--active');
        elements.textarea.disabled = true;

        try {
            const response = await API.post(`/api/communaute/posts/${currentPostId}/comments`, data);
            const newComment = response.data?.comment || response.data;

            if (replyTo && replyTo.commentId) {
                // Ajouter comme réponse
                const repliesContainer = document.getElementById(`replies-${replyTo.commentId}`);
                if (repliesContainer) {
                    repliesContainer.classList.remove('hidden');
                    repliesContainer.appendChild(createCommentElement(newComment));
                }
            } else {
                // Ajouter en haut de la liste
                elements.list?.prepend(createCommentElement(newComment));
            }

            // Update count
            const total = parseInt(elements.count?.textContent?.match(/\d+/)?.[0] || 0) + 1;
            if (elements.count) elements.count.textContent = `(${total})`;

            // Update post card comment count
            const postCard = document.querySelector(`[data-post-id="${currentPostId}"]`);
            if (postCard) {
                const commentStat = postCard.querySelector('.post-card__stats-right span:first-child');
                if (commentStat) {
                    commentStat.textContent = `${total} commentaire${total > 1 ? 's' : ''}`;
                }
            }

            // Reset
            elements.textarea.value = '';
            autoResize(elements.textarea);
            cancelReply();

            // Remove empty state if present
            const empty = elements.list?.querySelector('.comments-panel__empty');
            if (empty) empty.remove();

            App.toast.success('Commentaire publié');
        } catch (error) {
            App.toast.error('Erreur lors de la publication du commentaire');
        } finally {
            elements.textarea.disabled = false;
            elements.textarea?.focus();
        }
    }

    // ── Replies ─────────────────────────────────────
    async function loadReplies(commentId) {
        const container = document.getElementById(`replies-${commentId}`);
        const btn = document.querySelector(`.comment__show-replies[data-comment-id="${commentId}"]`);
        if (!container) return;

        const isVisible = !container.classList.contains('hidden');
        if (isVisible) {
            container.classList.add('hidden');
            if (btn) btn.querySelector('svg').style.transform = '';
            return;
        }

        container.classList.remove('hidden');
        if (btn) btn.querySelector('svg').style.transform = 'rotate(180deg)';

        // Don't reload if already loaded
        if (container.children.length > 0) return;

        try {
            const response = await API.get(`/api/communaute/comments/${commentId}/replies`);
            const replies = response.data || [];
            replies.forEach(reply => {
                container.appendChild(createCommentElement(reply));
            });
        } catch (error) {
            App.toast.error('Erreur lors du chargement des réponses');
        }
    }

    // ── Reply To ────────────────────────────────────
    function setReply(commentId, userName) {
        replyTo = { commentId, userName };
        if (elements.replyIndicator) {
            elements.replyIndicator.classList.remove('hidden');
            elements.replyIndicator.querySelector('span').textContent = `Réponse à ${userName}`;
        }
        elements.textarea?.focus();
    }

    function cancelReply() {
        replyTo = null;
        if (elements.replyIndicator) {
            elements.replyIndicator.classList.add('hidden');
        }
    }

    // ── Like Comment ────────────────────────────────
    async function toggleLike(commentId, btn) {
        const isLiked = btn.classList.contains('comment__action-btn--liked');
        btn.classList.toggle('comment__action-btn--liked');

        try {
            await API.post(`/api/communaute/comments/${commentId}/like`);
        } catch (error) {
            btn.classList.toggle('comment__action-btn--liked');
        }
    }

    // ── Show Loading ────────────────────────────────
    function showLoading(show) {
        const existing = elements.list?.querySelector('.comments-loading');
        if (show && !existing) {
            const loading = document.createElement('div');
            loading.className = 'comments-loading';
            loading.innerHTML = Array(3).fill(`
                <div class="comment-skeleton">
                    <div class="skeleton skeleton--circle" style="width:36px;height:36px"></div>
                    <div class="comment-skeleton__bubble" style="flex:1">
                        <div class="skeleton skeleton--text skeleton--w40"></div>
                        <div class="skeleton skeleton--text skeleton--w80"></div>
                        <div class="skeleton skeleton--text skeleton--w60"></div>
                    </div>
                </div>
            `).join('');
            elements.list?.appendChild(loading);
        } else if (!show && existing) {
            existing.remove();
        }
    }

    // ── Show Empty ──────────────────────────────────
    function showEmpty() {
        if (!elements.list) return;
        elements.list.innerHTML = `
            <div class="comments-panel__empty">
                <div class="comments-panel__empty-icon">💬</div>
                <p class="comments-panel__empty-text">Aucun commentaire pour l'instant.<br>Soyez le premier à commenter !</p>
            </div>
        `;
    }

    // ── Auto Resize Textarea ────────────────────────
    function autoResize(textarea) {
        if (!textarea) return;
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
    }

    // ── Init on load ────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    return {
        init,
        open,
        close,
        toggleLike,
        setReply,
        cancelReply
    };
})();

