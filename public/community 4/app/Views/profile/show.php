<?php use Core\Session; ?>
<?php
// Calculer l'URL complète de la photo de profil avec BASE_URL
$profilePhotoUrl = !empty($profileUser['photo_profil'])
    ? url($profileUser['photo_profil'])
    : asset('images/default-avatar.svg');
?>

<div class="profile-page">
    <!-- ── Profile Hero ──────────────────────── -->
    <div class="profile-hero">
        <div class="profile-hero__banner">
            <?php if ($isOwner): ?>
                <button class="profile-hero__banner-edit" id="edit-profile-btn-banner">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
                    </svg>
                    Modifier
                </button>
            <?php endif; ?>
        </div>

        <div class="profile-hero__content">
            <div class="profile-hero__avatar-wrapper">
                <img class="profile-hero__avatar" id="profile-avatar-img"
                     src="<?= htmlspecialchars($profilePhotoUrl) ?>"
                     alt="<?= htmlspecialchars($profileUser['prenom']) ?>">
                <?php if ($isOwner): ?>
                    <button class="profile-hero__avatar-edit" id="change-avatar-btn" title="Changer la photo">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
                        </svg>
                    </button>
                    <input type="file" id="avatar-input" accept="image/jpeg,image/png,image/gif,image/webp" class="hidden">
                <?php endif; ?>
            </div>

            <div class="profile-hero__top">
                <div class="profile-hero__identity">
                    <h1 class="profile-hero__name">
                        <?= htmlspecialchars($profileUser['prenom'] . ' ' . $profileUser['nom']) ?>
                        <?php if ($profileUser['role'] === 'enseignant'): ?>
                            <svg class="profile-hero__verified" width="20" height="20" viewBox="0 0 24 24" fill="#8B52FA" stroke="white" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                            </svg>
                        <?php endif; ?>
                    </h1>

                    <span class="profile-hero__role-badge profile-hero__role-badge--<?= $profileUser['role'] === 'enseignant' ? 'teacher' : 'student' ?>">
                        <?= $profileUser['role'] === 'enseignant' ? '👨‍🏫 Enseignant' : '🎓 Élève' ?>
                    </span>

                    <?php if (!empty($profileUser['bio'])): ?>
                        <p class="profile-hero__bio"><?= htmlspecialchars($profileUser['bio']) ?></p>
                    <?php endif; ?>

                    <div class="profile-hero__details">
                        <?php if ($profileUser['role'] === 'enseignant' && !empty($profileUser['matiere'])): ?>
                            <span class="profile-hero__detail">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                                <?= htmlspecialchars($profileUser['matiere']) ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($profileUser['classe'])): ?>
                            <span class="profile-hero__detail">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c0 2 4 3 6 3s6-1 6-3v-5"/></svg>
                                <?= htmlspecialchars($profileUser['classe']) ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($profileUser['etablissement'])): ?>
                            <span class="profile-hero__detail">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/></svg>
                                <?= htmlspecialchars($profileUser['etablissement']) ?>
                            </span>
                        <?php endif; ?>
                        <span class="profile-hero__detail">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            Membre depuis <?= date('M Y', strtotime($profileUser['created_at'])) ?>
                        </span>
                    </div>
                </div>

                <div class="profile-hero__actions">
                    <?php if ($isOwner): ?>
                        <button class="btn btn--outline" id="edit-profile-btn">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/>
                            </svg>
                            Modifier
                        </button>
                    <?php else: ?>
                        <button class="btn <?= !empty($isFollowing) ? 'btn--outline' : 'btn--primary' ?> follow-btn" data-user-id="<?= $profileUser['id'] ?>">
                            <?= !empty($isFollowing) ? 'Abonné' : 'Suivre' ?>
                        </button>
                        <button class="btn btn--ghost">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                            </svg>
                            Message
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="profile-hero__stats">
                <div class="profile-stat">
                    <span class="profile-stat__value"><?= $profileUser['posts_count'] ?? 0 ?></span>
                    <span class="profile-stat__label">Publications</span>
                </div>
                <div class="profile-stat">
                    <span class="profile-stat__value"><?= $profileUser['followers_count'] ?? 0 ?></span>
                    <span class="profile-stat__label">Abonnés</span>
                </div>
                <div class="profile-stat">
                    <span class="profile-stat__value"><?= $profileUser['following_count'] ?? 0 ?></span>
                    <span class="profile-stat__label">Abonnements</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Tabs ──────────────────────────────── -->
    <div class="profile-tabs">
        <button class="profile-tabs__tab profile-tabs__tab--active" data-tab="posts">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
            Publications
            <span class="profile-tabs__tab-count"><?= $profileUser['posts_count'] ?? 0 ?></span>
        </button>
        <button class="profile-tabs__tab" data-tab="resolved">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            Résolus
        </button>
        <button class="profile-tabs__tab" data-tab="comments">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            Commentaires
        </button>
        <?php if ($isOwner): ?>
        <button class="profile-tabs__tab" data-tab="bookmarks">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m19 21-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
            Enregistrés
        </button>
        <?php endif; ?>
    </div>

    <!-- ── Tab Content ───────────────────────── -->
    <div class="profile-content">
        <!-- Posts -->
        <div id="profile-posts-tab">
            <?php if (!empty($posts)): ?>
                <?php foreach ($posts as $post): ?>
                    <?php
                        // La photo_profil est déjà normalisée avec BASE_URL par le modèle
                        $postPhotoUrl = !empty($post['photo_profil'])
                            ? $post['photo_profil']
                            : asset('images/default-avatar.svg');
                    ?>
                    <article class="post-card" data-post-id="<?= $post['id'] ?>">
                        <div class="post-card__header">
                            <img class="post-card__avatar"
                                 src="<?= htmlspecialchars($postPhotoUrl) ?>" alt="">
                            <div class="post-card__info">
                                <div class="post-card__author">
                                    <a href="<?= url('/profile/' . ($post['user_id'] ?? $profileUser['id'])) ?>" class="post-card__name">
                                        <?= htmlspecialchars(($post['prenom'] ?? '') . ' ' . ($post['nom'] ?? '')) ?>
                                    </a>
                                    <?php if (!empty($post['type']) && $post['type'] !== 'partage'): ?>
                                        <span class="post-card__badge post-card__badge--teacher"><?= htmlspecialchars(ucfirst($post['type'])) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="post-card__meta">
                                    <span data-time="<?= $post['created_at'] ?>"></span>
                                </div>
                            </div>
                            <?php if ($isOwner): ?>
                            <div style="position:relative" data-dropdown>
                                <button class="post-card__more-btn" data-dropdown-toggle aria-label="Plus d'options">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                        <circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/>
                                    </svg>
                                </button>
                                <div class="post-card__dropdown hidden" data-dropdown-menu>
                                    <button class="post-card__dropdown-item post-card__dropdown-item--danger" onclick="ProfileActions.confirmDeletePost(<?= $post['id'] ?>)">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                        Supprimer
                                    </button>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="post-card__content">
                            <p class="post-card__text"><?= nl2br(htmlspecialchars($post['contenu'])) ?></p>
                        </div>
                        <?php if (!empty($post['image'])): ?>
                            <div class="post-card__media">
                                <img src="<?= htmlspecialchars($post['image']) ?>" alt="" loading="lazy">
                            </div>
                        <?php endif; ?>
                        <div class="post-card__stats">
                            <div class="post-card__stats-left">
                                <span><?= $post['likes_count'] ?? 0 ?> j'aime</span>
                            </div>
                            <div class="post-card__stats-right">
                                <span><?= $post['comments_count'] ?? 0 ?> commentaires</span>
                            </div>
                        </div>
                        <div class="post-card__actions">
                            <button class="post-card__action" onclick="Feed.toggleLike(<?= $post['id'] ?>, this)">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                                J'aime
                            </button>
                            <button class="post-card__action" onclick="Comments.open(<?= $post['id'] ?>)">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                Commenter
                            </button>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="feed-empty">
                    <div class="feed-empty__icon">📝</div>
                    <h3 class="feed-empty__title">Aucune publication</h3>
                    <p class="feed-empty__text">
                        <?= $isOwner ? 'Partagez votre première publication !' : 'Cet utilisateur n\'a pas encore publié.' ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Resolved (hidden by default) -->
        <div id="profile-resolved-tab" class="hidden">
            <div class="feed-empty">
                <div class="feed-empty__icon">✅</div>
                <h3 class="feed-empty__title">Questions résolues</h3>
                <p class="feed-empty__text">Les questions avec une meilleure réponse apparaîtront ici.</p>
            </div>
        </div>

        <!-- Comments (hidden by default) -->
        <div id="profile-comments-tab" class="hidden">
            <?php if (!empty($comments)): ?>
                <?php foreach ($comments as $comment): ?>
                    <div class="post-card" style="padding: var(--space-4)">
                        <p class="post-card__text"><?= htmlspecialchars($comment['contenu']) ?></p>
                        <div class="post-card__meta" style="margin-top:var(--space-2)">
                            <span data-time="<?= $comment['created_at'] ?>"></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="feed-empty">
                    <div class="feed-empty__icon">💬</div>
                    <h3 class="feed-empty__title">Aucun commentaire</h3>
                    <p class="feed-empty__text">Les commentaires postés apparaîtront ici.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Bookmarks (hidden by default) -->
        <?php if ($isOwner): ?>
        <div id="profile-bookmarks-tab" class="hidden">
            <?php if (!empty($bookmarks)): ?>
                <?php foreach ($bookmarks as $post): ?>
                    <?php
                        // La photo_profil est déjà normalisée avec BASE_URL par le modèle
                        $bmPhotoUrl = !empty($post['photo_profil'])
                            ? $post['photo_profil']
                            : asset('images/default-avatar.svg');
                    ?>
                    <article class="post-card" data-post-id="<?= $post['id'] ?>">
                        <div class="post-card__header">
                            <img class="post-card__avatar"
                                 src="<?= htmlspecialchars($bmPhotoUrl) ?>" alt="">
                            <div class="post-card__info">
                                <div class="post-card__author">
                                    <span class="post-card__name"><?= htmlspecialchars(($post['prenom'] ?? '') . ' ' . ($post['nom'] ?? '')) ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="post-card__content">
                            <p class="post-card__text"><?= nl2br(htmlspecialchars(mb_substr($post['contenu'], 0, 200))) ?>...</p>
                        </div>
                        <?php if (!empty($post['image'])): ?>
                            <div class="post-card__media">
                                <img src="<?= htmlspecialchars($post['image']) ?>" alt="" loading="lazy">
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="feed-empty">
                    <div class="feed-empty__icon">🔖</div>
                    <h3 class="feed-empty__title">Aucun post enregistré</h3>
                    <p class="feed-empty__text">Les publications que vous enregistrez apparaîtront ici.</p>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── MODAL : Confirmer la suppression d'un post ────── -->
<div class="modal-overlay hidden" id="delete-post-modal-overlay">
    <div class="modal" style="max-width: 420px;">
        <div class="modal__header">
            <h3>Supprimer la publication</h3>
            <button class="modal__close" data-close-modal="delete-post-modal-overlay">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                </svg>
            </button>
        </div>
        <div class="modal__body">
            <p style="color: var(--color-gray-700); font-size: var(--font-size-sm); line-height: 1.6;">
                Êtes-vous sûr de vouloir supprimer cette publication ? Cette action est irréversible.
            </p>
        </div>
        <div class="modal__footer">
            <button class="btn btn--ghost" data-close-modal="delete-post-modal-overlay">Annuler</button>
            <button class="btn btn--danger" id="confirm-delete-post-btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                </svg>
                Supprimer
            </button>
        </div>
    </div>
</div>

<?php if ($isOwner): ?>
<!-- ── MODAL : Modifier le profil ──────────────────────── -->
<div class="modal-overlay hidden" id="edit-profile-modal-overlay">
    <div class="modal" style="max-width: 560px;">
        <div class="modal__header">
            <h3>Modifier le profil</h3>
            <button class="modal__close" data-close-modal="edit-profile-modal-overlay">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                </svg>
            </button>
        </div>
        <div class="modal__body">
            <form id="edit-profile-form" autocomplete="off">
                <div class="form-group form-group--row">
                    <div>
                        <label class="form-label" for="edit-prenom">Prénom</label>
                        <input type="text" class="form-input" name="prenom" id="edit-prenom"
                               value="<?= htmlspecialchars($profileUser['prenom']) ?>" required>
                    </div>
                    <div>
                        <label class="form-label" for="edit-nom">Nom</label>
                        <input type="text" class="form-input" name="nom" id="edit-nom"
                               value="<?= htmlspecialchars($profileUser['nom']) ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit-bio">Bio</label>
                    <textarea class="form-input" name="bio" id="edit-bio" rows="3"
                              maxlength="160" style="resize: vertical;"
                              placeholder="Parlez-nous de vous..."><?= htmlspecialchars($profileUser['bio'] ?? '') ?></textarea>
                    <small style="color: var(--color-gray-500); font-size: 11px;">
                        <span id="bio-char-count"><?= mb_strlen($profileUser['bio'] ?? '') ?></span>/160 caractères
                    </small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit-etablissement">Établissement</label>
                    <input type="text" class="form-input" name="etablissement" id="edit-etablissement"
                           value="<?= htmlspecialchars($profileUser['etablissement'] ?? '') ?>"
                           placeholder="Nom de votre établissement">
                </div>

                <?php if ($profileUser['role'] === 'enseignant'): ?>
                    <div class="form-group">
                        <label class="form-label" for="edit-matiere">Matière(s)</label>
                        <input type="text" class="form-input" name="matiere" id="edit-matiere"
                               value="<?= htmlspecialchars($profileUser['matiere'] ?? '') ?>"
                               placeholder="Ex : Mathématiques, Physique">
                    </div>
                <?php else: ?>
                    <div class="form-group form-group--row">
                        <div>
                            <label class="form-label" for="edit-classe">Classe</label>
                            <input type="text" class="form-input" name="classe" id="edit-classe"
                                   value="<?= htmlspecialchars($profileUser['classe'] ?? '') ?>"
                                   placeholder="Ex : Terminale S">
                        </div>
                        <div>
                            <label class="form-label" for="edit-niveau">Niveau</label>
                            <input type="text" class="form-input" name="niveau" id="edit-niveau"
                                   value="<?= htmlspecialchars($profileUser['niveau'] ?? '') ?>"
                                   placeholder="Ex : Lycée">
                        </div>
                    </div>
                <?php endif; ?>
            </form>
        </div>
        <div class="modal__footer">
            <button class="btn btn--ghost" data-close-modal="edit-profile-modal-overlay">Annuler</button>
            <button class="btn btn--primary" id="save-profile-btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                    <polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
                </svg>
                Enregistrer
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// ── ProfileActions : Suppression de publication ─────
const ProfileActions = (() => {
    'use strict';

    let postIdToDelete = null;

    function confirmDeletePost(postId) {
        postIdToDelete = postId;
        App.openModal('delete-post-modal-overlay');
    }

    async function executeDelete() {
        if (!postIdToDelete) return;

        const btn = document.getElementById('confirm-delete-post-btn');
        if (!btn) return;

        btn.disabled = true;
        const originalText = btn.innerHTML;
        btn.innerHTML = 'Suppression...';

        try {
            await API.delete('/api/posts/' + postIdToDelete);
            App.toast.success('Publication supprimée avec succès');
            App.closeModal('delete-post-modal-overlay');

            // Retirer la carte du post du DOM avec animation
            const postCard = document.querySelector('.post-card[data-post-id="' + postIdToDelete + '"]');
            if (postCard) {
                postCard.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                postCard.style.opacity = '0';
                postCard.style.transform = 'translateY(-10px)';
                setTimeout(() => postCard.remove(), 300);
            }

            // Mettre à jour le compteur de publications
            const postCountEls = document.querySelectorAll('.profile-stat__value');
            if (postCountEls.length > 0) {
                let currentCount = parseInt(postCountEls[0].textContent) || 0;
                postCountEls[0].textContent = Math.max(0, currentCount - 1);
            }
            const tabCount = document.querySelector('.profile-tabs__tab-count');
            if (tabCount) {
                let currentTabCount = parseInt(tabCount.textContent) || 0;
                tabCount.textContent = Math.max(0, currentTabCount - 1);
            }

            postIdToDelete = null;
        } catch (err) {
            App.toast.error(err.message || 'Erreur lors de la suppression');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }

    // Initialiser le bouton de confirmation
    document.addEventListener('DOMContentLoaded', () => {
        const confirmBtn = document.getElementById('confirm-delete-post-btn');
        if (confirmBtn) {
            confirmBtn.addEventListener('click', executeDelete);
        }
    });

    return { confirmDeletePost };
})();

(function() {
    'use strict';

    // ── Profile Tabs ────────────────────────────────
    document.querySelectorAll('.profile-tabs__tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.profile-tabs__tab').forEach(t => t.classList.remove('profile-tabs__tab--active'));
            tab.classList.add('profile-tabs__tab--active');

            const tabName = tab.dataset.tab;
            document.querySelectorAll('.profile-content > div[id]').forEach(p => p.classList.add('hidden'));
            const target = document.getElementById(`profile-${tabName}-tab`);
            if (target) target.classList.remove('hidden');
        });
    });

    // ── Edit Profile (ouvrir la modale) ─────────────
    const editBtn = document.getElementById('edit-profile-btn');
    const editBtnBanner = document.getElementById('edit-profile-btn-banner');

    function openEditModal() {
        App.openModal('edit-profile-modal-overlay');
    }

    if (editBtn) editBtn.addEventListener('click', openEditModal);
    if (editBtnBanner) editBtnBanner.addEventListener('click', openEditModal);

    // ── Bio : compteur de caractères ────────────────
    const bioTextarea = document.getElementById('edit-bio');
    const bioCount = document.getElementById('bio-char-count');
    if (bioTextarea && bioCount) {
        bioTextarea.addEventListener('input', () => {
            bioCount.textContent = bioTextarea.value.length;
        });
    }

    // ── Enregistrer le profil ───────────────────────
    const saveBtn = document.getElementById('save-profile-btn');
    if (saveBtn) {
        saveBtn.addEventListener('click', async () => {
            const form = document.getElementById('edit-profile-form');
            if (!form) return;

            // Récupérer les données du formulaire en objet JSON
            const formData = new FormData(form);
            const data = {};
            formData.forEach((value, key) => { data[key] = value; });

            // Validation basique côté client
            if (!data.prenom || !data.prenom.trim()) {
                App.toast.warning('Le prénom est obligatoire');
                return;
            }
            if (!data.nom || !data.nom.trim()) {
                App.toast.warning('Le nom est obligatoire');
                return;
            }

            saveBtn.disabled = true;
            const originalText = saveBtn.innerHTML;
            saveBtn.innerHTML = 'Enregistrement...';

            try {
                const userId = window.STUDYLINK_CONFIG.userId;
                await API.put('/api/users/' + userId, data);
                App.toast.success('Profil mis à jour avec succès !');
                App.closeModal('edit-profile-modal-overlay');
                setTimeout(() => location.reload(), 800);
            } catch (err) {
                App.toast.error(err.message || 'Erreur lors de la mise à jour du profil');
            } finally {
                saveBtn.disabled = false;
                saveBtn.innerHTML = originalText;
            }
        });
    }

    // ── Changer la photo de profil (avatar) ─────────
    const changeAvatarBtn = document.getElementById('change-avatar-btn');
    const avatarInput = document.getElementById('avatar-input');

    if (changeAvatarBtn && avatarInput) {
        changeAvatarBtn.addEventListener('click', () => avatarInput.click());

        avatarInput.addEventListener('change', async () => {
            const file = avatarInput.files[0];
            if (!file) return;

            // Validation taille (max 10 Mo)
            if (file.size > 10 * 1024 * 1024) {
                App.toast.error('Image trop volumineuse (max 10 Mo)');
                avatarInput.value = '';
                return;
            }

            // Validation type
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                App.toast.error('Format non autorisé. Utilisez JPG, PNG, GIF ou WEBP.');
                avatarInput.value = '';
                return;
            }

            const formData = new FormData();
            formData.append('avatar', file);

            // Feedback visuel : aperçu immédiat
            const avatarImg = document.getElementById('profile-avatar-img');
            let oldSrc = '';
            if (avatarImg) {
                oldSrc = avatarImg.src;
                const reader = new FileReader();
                reader.onload = (e) => { avatarImg.src = e.target.result; };
                reader.readAsDataURL(file);
            }

            try {
                const userId = window.STUDYLINK_CONFIG.userId;
                const result = await API.post('/api/users/' + userId + '/avatar', formData);
                App.toast.success('Photo de profil mise à jour !');

                // Mettre à jour l'avatar dans le header et la navbar
                if (result && result.data && result.data.photo_url) {
                    const newUrl = result.data.photo_url;
                    if (avatarImg) avatarImg.src = newUrl;
                    // Navbar avatar
                    const navAvatar = document.querySelector('.navbar__avatar');
                    if (navAvatar) navAvatar.src = newUrl;
                }

                setTimeout(() => location.reload(), 1200);
            } catch (err) {
                App.toast.error(err.message || 'Erreur lors de la mise à jour de la photo');
                // Rétablir l'ancienne image en cas d'erreur
                if (avatarImg && oldSrc) avatarImg.src = oldSrc;
            }

            // Reset l'input pour permettre de re-sélectionner le même fichier
            avatarInput.value = '';
        });
    }
})();
</script>
