/**
 * StudyLink — Feed Component
 * Gère le fil d'actualité : infinite scroll, filtres, polling, posts
 */

const Feed = (() => {
    'use strict';

    // ── State ───────────────────────────────────────
    let isLoading = false;
    let hasMore = true;
    let currentFilter = '';
    let pollInterval = null;
    let lastPostId = 0;       // ID du dernier post (pour polling "after")
    let oldestPostId = null;  // ID du plus ancien post chargé (pour pagination "before")
    let observer = null;

    // ── DOM References ──────────────────────────────
    const elements = {};

    function cacheDOM() {
        elements.feedContainer = document.getElementById('feed-container');
        elements.feedLoading = document.getElementById('feed-loading');
        elements.feedSentinel = document.getElementById('feed-sentinel');
        elements.filterBtns = document.querySelectorAll('.feed__filter');
        elements.newPostsBanner = document.getElementById('new-posts-banner');
    }

    // ── Init ────────────────────────────────────────
    function init() {
        cacheDOM();
        if (!elements.feedContainer) return;

        initFilters();
        initInfiniteScroll();
        startPolling();
        initDevelopmentModalHandlers();

        // New posts banner click
        elements.newPostsBanner?.addEventListener('click', loadNewPosts);

        // Load initial posts
        loadPosts();
    }

    // ── Ouvrir le modal de développement ──────────
    function openDevelopmentModal() {
        const modal = document.getElementById('development-modal-overlay');
        if (!modal) {
            console.error('Modal de développement introuvable');
            // Fallback : utiliser un alert si le modal n'existe pas
            alert('Fonctionnalité en cours de développement');
            return;
        }
        
        // Ouvrir le modal directement
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        
        // Gérer la fermeture sur clic overlay
        const overlayClickHandler = (e) => {
            if (e.target === modal) {
                closeDevelopmentModal();
                modal.removeEventListener('click', overlayClickHandler);
            }
        };
        modal.addEventListener('click', overlayClickHandler);
        
        // Gérer la fermeture sur Escape
        const escHandler = (e) => {
            if (e.key === 'Escape') {
                closeDevelopmentModal();
                document.removeEventListener('keydown', escHandler);
            }
        };
        document.addEventListener('keydown', escHandler);
    }
    
    // ── Fermer le modal de développement ──────────
    function closeDevelopmentModal() {
        const modal = document.getElementById('development-modal-overlay');
        if (modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }
    }
    
    // ── Initialiser les boutons de fermeture ──────
    function initDevelopmentModalHandlers() {
        const closeBtn = document.getElementById('close-development-modal');
        const closeBtnFooter = document.getElementById('close-development-modal-btn');
        
        if (closeBtn) {
            closeBtn.addEventListener('click', closeDevelopmentModal);
        }
        if (closeBtnFooter) {
            closeBtnFooter.addEventListener('click', closeDevelopmentModal);
        }
    }

    // ── Filtres ─────────────────────────────────────
    function initFilters() {
        elements.filterBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                const filterValue = btn.dataset.type ?? '';
                
                // Afficher un popup modal pour Questions, Ressources, Annonces, Abonnements
                if (filterValue === 'question' || filterValue === 'ressource' || filterValue === 'annonce' || filterValue === 'following') {
                    openDevelopmentModal();
                    return;
                }
                
                if (filterValue === currentFilter) return;

                elements.filterBtns.forEach(b => {
                    b.classList.remove('feed__filter--active');
                    b.classList.remove('feed-filters__btn--active');
                });
                btn.classList.add('feed__filter--active');
                btn.classList.add('feed-filters__btn--active');

                currentFilter = filterValue;
                resetFeed();
                loadPosts();
            });
        });
    }

    // ── Infinite Scroll ─────────────────────────────
    function initInfiniteScroll() {
        if (!elements.feedSentinel) return;

        observer = new IntersectionObserver(entries => {
            if (entries[0].isIntersecting && !isLoading && hasMore) {
                loadPosts();
            }
        }, { rootMargin: '200px' });

        observer.observe(elements.feedSentinel);
    }

    // ── Load Posts ──────────────────────────────────
    async function loadPosts() {
        if (isLoading || !hasMore) return;
        isLoading = true;
        showLoading(true);

        try {
            const params = new URLSearchParams();
            if (currentFilter) params.set('type', currentFilter);
            // Cursor-based pagination : envoyer l'ID du plus ancien post pour la page suivante
            if (oldestPostId) params.set('before', oldestPostId);

            const response = await API.get(`/api/posts?${params}`);
            const posts = response.data?.posts || [];
            const serverHasMore = response.data?.has_more ?? true;

            if (posts.length === 0) {
                hasMore = false;
                if (!oldestPostId) showEmptyState(); // Premier chargement vide
            } else {
                posts.forEach(post => {
                    const el = createPostCard(post);
                    elements.feedContainer.appendChild(el);

                    // Suivre le plus récent pour le polling
                    if (post.id > lastPostId) lastPostId = post.id;
                    // Suivre le plus ancien pour la pagination
                    if (!oldestPostId || post.id < oldestPostId) oldestPostId = post.id;
                });
                hasMore = serverHasMore;
            }
        } catch (error) {
            App.toast.error('Erreur lors du chargement des publications');
            console.error('Feed load error:', error);
        } finally {
            isLoading = false;
            showLoading(false);
        }
    }

    // ── Reset Feed ──────────────────────────────────
    function resetFeed() {
        hasMore = true;
        lastPostId = 0;
        oldestPostId = null;
        if (elements.feedContainer) {
            elements.feedContainer.innerHTML = '';
        }
    }

    // ── Polling ─────────────────────────────────────
    function startPolling() {
        pollInterval = setInterval(checkNewPosts, 30000);
    }

    async function checkNewPosts() {
        if (!lastPostId) return;

        try {
            const params = new URLSearchParams({ after: lastPostId });
            if (currentFilter) params.set('type', currentFilter);
            const response = await API.get(`/api/posts?${params}`);
            const count = response.data?.count || (response.data?.new_posts?.length ?? 0);

            if (count > 0 && elements.newPostsBanner) {
                elements.newPostsBanner.classList.remove('hidden');
                const text = elements.newPostsBanner.querySelector('#new-posts-text');
                if (text) text.textContent = `${count} nouvelle${count > 1 ? 's' : ''} publication${count > 1 ? 's' : ''}`;
            }
        } catch (e) {
            // Silently fail
        }
    }

    function loadNewPosts() {
        if (elements.newPostsBanner) {
            elements.newPostsBanner.classList.add('hidden');
        }
        resetFeed();
        loadPosts();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // ── Helper: Build URL (reproduit url() PHP) ───────
    /**
     * Construit une URL de la même manière que la fonction url() PHP
     * url() PHP fait: BASE_URL . '/' . ltrim($path, '/')
     * Exemple: url('/public/uploads/posts/xxx.jpg') → '/communityv2/public/uploads/posts/xxx.jpg'
     */
    function buildUrl(path) {
        const baseUrl = window.STUDYLINK_CONFIG?.baseUrl || '';
        if (!path) return baseUrl || '/';
        
        // Si c'est une URL absolue (http/https), la retourner telle quelle
        if (path.startsWith('http://') || path.startsWith('https://')) {
            return path;
        }
        
        // Reproduire exactement le comportement de url() PHP
        // ltrim($path, '/') enlève tous les slashes au début
        const cleanPath = path.replace(/^\/+/, '');
        
        // BASE_URL . '/' . cleanPath
        if (baseUrl) {
            return baseUrl + '/' + cleanPath;
        }
        return '/' + cleanPath;
    }

    // ── Create Post Card HTML ───────────────────────
    function createPostCard(post) {
        const div = document.createElement('article');
        div.className = 'post-card animate-slide-in';
        div.dataset.postId = post.id;

        // Adapter les noms de champs PHP → JS
        const userName = post.prenom && post.nom ? `${post.prenom} ${post.nom}` : (post.user_name || 'Utilisateur');
        const baseUrl = window.STUDYLINK_CONFIG?.baseUrl || '';
        
        // Construire l'URL de l'avatar - utiliser directement le chemin normalisé depuis l'API
        let avatarUrl = buildUrl('/public/assets/images/default-avatar.svg');
        if (post.photo_profil && post.photo_profil.trim() !== '' && post.photo_profil !== 'null') {
            // Le chemin est déjà normalisé côté serveur avec BASE_URL, utiliser directement
            if (post.photo_profil.startsWith('http://') || post.photo_profil.startsWith('https://')) {
                avatarUrl = post.photo_profil;
            } else if (post.photo_profil.startsWith('/')) {
                // Le chemin commence par /, il est déjà normalisé avec BASE_URL côté serveur
                avatarUrl = post.photo_profil;
            } else {
                // Chemin relatif, utiliser buildUrl
                avatarUrl = buildUrl(post.photo_profil);
            }
        }
        
        const timeStr = App.timeAgo(post.created_at);
        const isLiked = post.is_liked_by_me ? 'post-card__action--liked' : '';
        const isBookmarked = post.is_bookmarked_by_me ? 'post-card__action--bookmarked' : '';
        const likeIcon = post.is_liked_by_me ?
            '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>' :
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>';

        // Construire le HTML de l'image - utiliser directement le chemin normalisé depuis l'API
        let mediaHtml = '';
        if ('image' in post && post.image !== null && post.image !== undefined) {
            let postImage = String(post.image).trim();
            if (postImage !== '' && postImage !== 'null') {
                try {
                    // Le chemin est déjà normalisé côté serveur avec BASE_URL, utiliser directement
                    let imageUrl;
                    if (postImage.startsWith('http://') || postImage.startsWith('https://')) {
                        imageUrl = postImage;
                    } else if (postImage.startsWith('/')) {
                        // Le chemin commence par /, il est déjà normalisé avec BASE_URL côté serveur
                        imageUrl = postImage;
                    } else {
                        // Chemin relatif simple (juste le nom du fichier), construire le chemin complet
                        imageUrl = buildUrl('/public/uploads/posts/' + postImage);
                    }
                    mediaHtml = `
                        <div class="post-card__media">
                            <img src="${App.escapeHtml(imageUrl)}" alt="Image du post" loading="lazy" style="max-width: 100%; height: auto; display: block;">
                        </div>`;
                } catch (e) {
                    console.error('Erreur lors de la construction de l\'URL de l\'image:', e, postImage);
                }
            }
        }

        let tagsHtml = '';
        if (post.hashtags) {
            const tags = post.hashtags.split(',').filter(t => t.trim());
            if (tags.length > 0) {
                tagsHtml = `<div class="post-card__tags">${tags.map(t =>
                    `<span class="post-card__tag">#${App.escapeHtml(t.trim())}</span>`
                ).join('')}</div>`;
            }
        }

        let badgeHtml = '';
        if (post.user_role === 'enseignant') {
            badgeHtml = '<span class="post-card__badge post-card__badge--teacher">Enseignant</span>';
        }
        if (post.is_resolved) {
            badgeHtml += '<span class="post-card__badge post-card__badge--resolved">✓ Résolu</span>';
        }

        let typeBadge = '';
        if (post.type && post.type !== 'partage') {
            const typeLabels = { question: 'Question', ressource: 'Ressource', annonce: 'Annonce' };
            typeBadge = `<span class="post-card__type-badge post-card__type-badge--${post.type}">${typeLabels[post.type] || post.type}</span>`;
        }

        const matiereTag = post.matiere_tag ? `<span class="post-card__meta-dot"></span><span>${App.escapeHtml(post.matiere_tag)}</span>` : '';

        div.innerHTML = `
            <div class="post-card__header">
                <img class="post-card__avatar" src="${App.escapeHtml(avatarUrl)}" alt="${App.escapeHtml(userName)}" loading="lazy">
                <div class="post-card__info">
                    <div class="post-card__author">
                        <a href="${baseUrl}/profile/${post.user_id}" class="post-card__name">${App.escapeHtml(userName)}</a>
                        ${badgeHtml}
                        ${typeBadge}
                    </div>
                    <div class="post-card__meta">
                        <span data-time="${post.created_at}">${timeStr}</span>
                        ${matiereTag}
                    </div>
                </div>
                <div style="position:relative" data-dropdown>
                    <button class="post-card__more-btn" data-dropdown-toggle aria-label="Plus d'options">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/>
                        </svg>
                    </button>
                    <div class="post-card__dropdown hidden" data-dropdown-menu>
                        <button class="post-card__dropdown-item" onclick="Feed.bookmarkPost(${post.id})">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
                            Enregistrer
                        </button>
                        <button class="post-card__dropdown-item" onclick="Feed.reportPost(${post.id})">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
                            Signaler
                        </button>
                    </div>
                </div>
            </div>

            <div class="post-card__content">
                <p class="post-card__text">${formatPostContent(post.contenu)}</p>
                ${tagsHtml}
            </div>

            ${mediaHtml}

            <div class="post-card__stats">
                <div class="post-card__stats-left">
                    <div class="post-card__like-icons">
                        <span>👍</span><span>❤️</span>
                    </div>
                    <span>${App.formatNumber(post.likes_count || 0)}</span>
                </div>
                <div class="post-card__stats-right">
                    <span>${post.comments_count || 0} commentaire${(post.comments_count || 0) > 1 ? 's' : ''}</span>
                </div>
            </div>

            <div class="post-card__actions">
                <button class="post-card__action ${isLiked}" onclick="Feed.toggleLike(${post.id}, this)" aria-label="J'aime">
                    ${likeIcon}
                    <span>J'aime</span>
                </button>
                <button class="post-card__action" onclick="Comments.open(${post.id})" aria-label="Commenter">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                    <span>Commenter</span>
                </button>
                <button class="post-card__action ${isBookmarked}" onclick="Feed.bookmarkPost(${post.id}, this)" aria-label="Enregistrer">
                    <svg viewBox="0 0 24 24" fill="${post.is_bookmarked_by_me ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="2">
                        <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
                    </svg>
                    <span>Enregistrer</span>
                </button>
            </div>
        `;

        return div;
    }

    // ── Format Post Content ─────────────────────────
    function formatPostContent(content) {
        if (!content) return '';
        let text = App.escapeHtml(content);
        // Links
        text = text.replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>');
        // Mentions
        const baseUrl = window.STUDYLINK_CONFIG?.baseUrl || '';
        text = text.replace(/@(\w+)/g, `<a href="${baseUrl}/profile/$1" class="mention">@$1</a>`);
        // Hashtags
        text = text.replace(/#(\w+)/g, `<a href="${baseUrl}/search?q=%23$1" class="hashtag">#$1</a>`);
        // Newlines
        text = text.replace(/\n/g, '<br>');
        return text;
    }

    // ── Like Toggle ─────────────────────────────────
    async function toggleLike(postId, btn) {
        const card = btn.closest('.post-card');
        const isLiked = btn.classList.contains('post-card__action--liked');

        // Optimistic UI
        btn.classList.toggle('post-card__action--liked');
        const icon = btn.querySelector('svg');
        if (!isLiked) {
            icon.setAttribute('fill', 'currentColor');
            icon.removeAttribute('stroke');
            btn.querySelector('svg').classList.add('animate-heart');
            setTimeout(() => icon.classList.remove('animate-heart'), 400);
        } else {
            icon.setAttribute('fill', 'none');
            icon.setAttribute('stroke', 'currentColor');
        }

        // Update like count
        const statsLeft = card.querySelector('.post-card__stats-left span:last-child');
        if (statsLeft) {
            let count = parseInt(statsLeft.textContent) || 0;
            count = isLiked ? Math.max(0, count - 1) : count + 1;
            statsLeft.textContent = App.formatNumber(count);
        }

        try {
            await API.post(`/api/posts/${postId}/like`);
        } catch (error) {
            // Rollback
            btn.classList.toggle('post-card__action--liked');
            if (isLiked) {
                icon.setAttribute('fill', 'currentColor');
            } else {
                icon.setAttribute('fill', 'none');
                icon.setAttribute('stroke', 'currentColor');
            }
            App.toast.error("Erreur lors de l'action");
        }
    }

    // ── Bookmark ────────────────────────────────────
    async function bookmarkPost(postId, btn) {
        const wasBookmarked = btn ? btn.classList.contains('post-card__action--bookmarked') : false;

        if (btn) {
            btn.classList.toggle('post-card__action--bookmarked');
            const icon = btn.querySelector('svg');
            icon.setAttribute('fill', wasBookmarked ? 'none' : 'currentColor');
        }

        try {
            const result = await API.post(`/api/posts/${postId}/bookmark`);
            const isNowBookmarked = result.data?.bookmarked ?? !wasBookmarked;
            App.toast.success(isNowBookmarked ? 'Publication enregistrée' : 'Publication retirée des favoris');
        } catch (error) {
            // Rollback
            if (btn) {
                btn.classList.toggle('post-card__action--bookmarked');
                const icon = btn.querySelector('svg');
                icon.setAttribute('fill', wasBookmarked ? 'currentColor' : 'none');
            }
            App.toast.error('Erreur lors de la sauvegarde');
        }
    }

    // ── Report Post ─────────────────────────────────
    function reportPost(postId) {
        const reportModal = document.getElementById('report-modal');
        if (reportModal) {
            reportModal.dataset.postId = postId;
            App.openModal('report-modal-overlay');
        }
    }

    // ── Show Loading ────────────────────────────────
    function showLoading(show) {
        if (elements.feedLoading) {
            elements.feedLoading.classList.toggle('hidden', !show);
        }
    }

    // ── Show Empty State ────────────────────────────
    function showEmptyState() {
        if (!elements.feedContainer) return;
        // Hide skeleton
        if (elements.feedLoading) elements.feedLoading.classList.add('hidden');
        const emptyEl = document.getElementById('feed-empty');
        if (emptyEl) emptyEl.classList.remove('hidden');
    }

    // ── Destroy ─────────────────────────────────────
    function destroy() {
        if (pollInterval) clearInterval(pollInterval);
        if (observer) observer.disconnect();
    }

    // ── Init on load ────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // ── Load Search Results ────────────────────────
    function loadSearchResults(posts, query) {
        if (!elements.feedContainer) return;
        
        // Afficher un message de recherche
        elements.feedContainer.innerHTML = `
            <div style="padding: var(--space-4); background: var(--color-gray-100); border-radius: var(--radius-md); margin-bottom: var(--space-4);">
                <p style="font-size: var(--font-size-sm); color: var(--color-gray-600);">
                    Résultats de recherche pour "<strong>${App.escapeHtml(query)}</strong>" (${posts.length} résultat${posts.length > 1 ? 's' : ''})
                </p>
            </div>
        `;
        
        // Afficher les posts trouvés
        if (posts.length > 0) {
            posts.forEach(post => {
                const el = createPostCard(post);
                elements.feedContainer.appendChild(el);
            });
        } else {
            elements.feedContainer.innerHTML += `
                <div class="feed-empty">
                    <div class="feed-empty__icon">🔍</div>
                    <h3 class="feed-empty__title">Aucun résultat</h3>
                    <p class="feed-empty__text">Aucune publication ne correspond à votre recherche.</p>
                </div>
            `;
        }
        
        // Masquer le loading
        showLoading(false);
        
        // Mettre à jour les timestamps
        document.querySelectorAll('[data-time]').forEach(el => {
            el.textContent = App.timeAgo(el.dataset.time);
        });
    }

    return {
        init,
        loadPosts,
        loadNewPosts,
        createPostCard,
        toggleLike,
        bookmarkPost,
        reportPost,
        loadSearchResults,
        destroy
    };
})();

