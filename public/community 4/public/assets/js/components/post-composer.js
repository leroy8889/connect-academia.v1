/**
 * StudyLink — Post Composer Component
 * Gère la création de nouvelles publications
 * Aligné avec les IDs HTML de feed/index.php
 */

const PostComposer = (() => {
    'use strict';

    // ── State ───────────────────────────────────────
    let selectedType = 'partage';
    let isSubmitting = false;

    // ── DOM ─────────────────────────────────────────
    const elements = {};

    function cacheDOM() {
        // Compact composer (feed page)
        elements.createPostTrigger = document.getElementById('create-post-trigger');
        elements.createPostBtn = document.getElementById('create-post-btn');
        elements.fabBtn = document.getElementById('fab-create-post');
        elements.mobileCreateBtn = document.getElementById('mobile-create-post');
        elements.emptyCreateBtn = document.getElementById('empty-create-post');
        elements.quickPostBtn = document.getElementById('quick-post-btn');

        // Modal
        elements.modal = document.getElementById('create-post-modal');
        elements.closeModalBtn = document.getElementById('close-create-modal');
        elements.textarea = document.getElementById('post-content');
        elements.submitBtn = document.getElementById('submit-post');

        // Type selector
        elements.typeBtns = document.querySelectorAll('.create-post-modal__type');

        // Image upload
        elements.imageInput = document.getElementById('post-image-input');
        elements.attachImageBtn = document.getElementById('attach-image-btn');
        elements.createPostImgBtn = document.getElementById('create-post-img-btn');
        elements.imagePreviewContainer = document.getElementById('image-preview-container');
        elements.imagePreview = document.getElementById('image-preview');
        elements.removeImageBtn = document.getElementById('remove-image');

        // Selects
        elements.matiereSelect = document.getElementById('post-matiere');
        elements.classeSelect = document.getElementById('post-classe');
    }

    // ── Init ────────────────────────────────────────
    function init() {
        cacheDOM();

        // Ouvrir le modal
        elements.createPostTrigger?.addEventListener('click', openModal);
        elements.createPostBtn?.addEventListener('click', openModal);
        elements.fabBtn?.addEventListener('click', openModal);
        elements.mobileCreateBtn?.addEventListener('click', openModal);
        elements.emptyCreateBtn?.addEventListener('click', openModal);
        elements.quickPostBtn?.addEventListener('click', openModal);

        // Fermer le modal
        elements.closeModalBtn?.addEventListener('click', closeModal);
        initOverlayClose();

        // Type selector
        elements.typeBtns?.forEach(btn => {
            btn.addEventListener('click', () => {
                elements.typeBtns.forEach(b => {
                    b.classList.remove('create-post-modal__type--active');
                    b.style.borderColor = 'var(--color-gray-300)';
                    b.style.background = 'transparent';
                    b.style.color = 'var(--color-gray-600)';
                });
                btn.classList.add('create-post-modal__type--active');
                btn.style.borderColor = 'var(--color-primary)';
                btn.style.background = 'var(--color-lavender)';
                btn.style.color = 'var(--color-primary)';
                selectedType = btn.dataset.type;
            });
        });

        // Textarea auto-resize + char count + enable submit
        elements.textarea?.addEventListener('input', () => {
            autoResize(elements.textarea);
            updateCharCount();
            updateSubmitState();
        });

        // Image upload triggers
        elements.attachImageBtn?.addEventListener('click', () => elements.imageInput?.click());
        elements.createPostImgBtn?.addEventListener('click', () => elements.imageInput?.click());

        // Image file change
        elements.imageInput?.addEventListener('change', handleImageSelect);

        // Remove image
        elements.removeImageBtn?.addEventListener('click', removeImage);

        // Submit
        elements.submitBtn?.addEventListener('click', submitPost);

        // Drag & drop on textarea
        elements.textarea?.addEventListener('dragover', (e) => {
            e.preventDefault();
            elements.textarea.style.borderColor = 'var(--color-primary)';
        });
        elements.textarea?.addEventListener('dragleave', () => {
            elements.textarea.style.borderColor = '';
        });
        elements.textarea?.addEventListener('drop', (e) => {
            e.preventDefault();
            elements.textarea.style.borderColor = '';
            const files = Array.from(e.dataTransfer.files).filter(f => f.type.startsWith('image/'));
            if (files.length > 0) {
                showImagePreview(files[0]);
            }
        });

        // Ctrl+Enter to submit
        elements.textarea?.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                submitPost();
            }
        });
    }

    // ── Modal Controls ──────────────────────────────
    function openModal() {
        if (elements.modal) {
            elements.modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            setTimeout(() => elements.textarea?.focus(), 100);
        }
    }

    function closeModal() {
        if (elements.modal) {
            elements.modal.classList.add('hidden');
            document.body.style.overflow = '';
            resetComposer();
        }
    }

    // Fermer le modal en cliquant sur l'overlay (en dehors du contenu)
    function initOverlayClose() {
        elements.modal?.addEventListener('click', (e) => {
            if (e.target === elements.modal) {
                closeModal();
            }
        });
        // Fermer avec Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && elements.modal && !elements.modal.classList.contains('hidden')) {
                closeModal();
            }
        });
    }

    // ── Image Handling ──────────────────────────────
    function handleImageSelect(e) {
        const file = e.target.files[0];
        if (!file) return;

        const maxSize = 10 * 1024 * 1024; // 10MB
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if (!allowedTypes.includes(file.type)) {
            App.toast.warning('Format non supporté. Utilisez JPEG, PNG, GIF ou WebP');
            return;
        }
        if (file.size > maxSize) {
            App.toast.warning('Image trop lourde (max 10 Mo)');
            return;
        }

        showImagePreview(file);
    }

    function showImagePreview(file) {
        // Stocker le fichier AVANT les vérifications DOM pour ne jamais le perdre
        if (elements.imageInput) {
            elements.imageInput._selectedFile = file;
        }

        if (!elements.imagePreview || !elements.imagePreviewContainer) return;

        const reader = new FileReader();
        reader.onload = (e) => {
            elements.imagePreview.src = e.target.result;
            elements.imagePreviewContainer.classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    }

    function removeImage() {
        if (elements.imagePreviewContainer) {
            elements.imagePreviewContainer.classList.add('hidden');
        }
        if (elements.imagePreview) {
            elements.imagePreview.src = '';
        }
        if (elements.imageInput) {
            elements.imageInput.value = '';
            elements.imageInput._selectedFile = null;
        }
    }

    // ── Submit Post ─────────────────────────────────
    async function submitPost() {
        if (isSubmitting) return;
        const content = elements.textarea?.value.trim();
        if (!content) {
            App.toast.warning('Veuillez écrire quelque chose');
            return;
        }

        isSubmitting = true;
        if (elements.submitBtn) {
            elements.submitBtn.disabled = true;
            elements.submitBtn.textContent = 'Publication...';
        }

        const formData = new FormData();
        formData.append('contenu', content);
        formData.append('type', selectedType);

        // Image — on tente _selectedFile (drag&drop ou input) en priorité,
        // puis files[0] comme fallback si le fichier est toujours dans l'input
        const imageFile = elements.imageInput?._selectedFile
            || (elements.imageInput?.files?.length > 0 ? elements.imageInput.files[0] : null);
        if (imageFile) {
            formData.append('image', imageFile);
        }

        // Optional tags
        const matiere = elements.matiereSelect?.value;
        const classe = elements.classeSelect?.value;
        if (matiere) formData.append('matiere_tag', matiere);
        if (classe) formData.append('classe_tag', classe);

        // Extract hashtags from content
        const hashtagMatches = content.match(/#(\w+)/gu);
        if (hashtagMatches) {
            formData.append('hashtags', hashtagMatches.map(h => h.substring(1)).join(','));
        }

        try {
            const response = await API.post('/api/posts', formData);
            const newPost = response.data?.post;

            // Ajouter le post en haut du feed
            const feedContainer = document.getElementById('feed-container');
            if (feedContainer && newPost && typeof Feed !== 'undefined' && Feed.createPostCard) {
                // Hide empty state and loading
                const emptyEl = document.getElementById('feed-empty');
                if (emptyEl) emptyEl.classList.add('hidden');
                const loadingEl = document.getElementById('feed-loading');
                if (loadingEl) loadingEl.classList.add('hidden');

                const postEl = Feed.createPostCard(newPost);
                if (postEl) {
                    feedContainer.prepend(postEl);
                }
            }

            closeModal();
            App.toast.success('Publication créée avec succès !');
        } catch (error) {
            App.toast.error(error.message || 'Erreur lors de la publication');
        } finally {
            isSubmitting = false;
            if (elements.submitBtn) {
                elements.submitBtn.disabled = false;
                elements.submitBtn.textContent = 'Publier';
            }
        }
    }

    // ── Char Count ──────────────────────────────────
    function updateCharCount() {
        const charCountEl = document.getElementById('composer-char-count');
        if (charCountEl && elements.textarea) {
            const len = elements.textarea.value.length;
            charCountEl.textContent = `${len}/2000`;
        }
    }

    // ── Submit State ────────────────────────────────
    function updateSubmitState() {
        if (elements.submitBtn && elements.textarea) {
            const hasContent = elements.textarea.value.trim().length > 0;
            elements.submitBtn.disabled = !hasContent;
        }
    }

    // ── Auto Resize ─────────────────────────────────
    function autoResize(textarea) {
        if (!textarea) return;
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 300) + 'px';
    }

    // ── Reset ───────────────────────────────────────
    function resetComposer() {
        if (elements.textarea) {
            elements.textarea.value = '';
            elements.textarea.style.height = '';
        }
        selectedType = 'partage';
        elements.typeBtns?.forEach(btn => {
            const isPartage = btn.dataset.type === 'partage';
            btn.classList.toggle('create-post-modal__type--active', isPartage);
            btn.style.borderColor = isPartage ? 'var(--color-primary)' : 'var(--color-gray-300)';
            btn.style.background = isPartage ? 'var(--color-lavender)' : 'transparent';
            btn.style.color = isPartage ? 'var(--color-primary)' : 'var(--color-gray-600)';
        });
        removeImage();
        updateCharCount();
        if (elements.submitBtn) elements.submitBtn.disabled = true;
        if (elements.matiereSelect) elements.matiereSelect.value = '';
        if (elements.classeSelect) elements.classeSelect.value = '';
    }

    // ── Init on load ────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    return {
        init,
        openModal,
        closeModal,
        submitPost
    };
})();
