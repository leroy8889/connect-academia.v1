<?php use Core\Session; ?>

<div class="feed-layout">
    <!-- ══════════════════════════════════════════ -->
    <!-- LEFT SIDEBAR                               -->
    <!-- ══════════════════════════════════════════ -->
    <aside class="sidebar sidebar--left">
        <!-- User Card -->
        <div class="sidebar__user-card">
            <img src="<?= htmlspecialchars(!empty($currentUser['photo_profil']) ? url($currentUser['photo_profil']) : asset('images/default-avatar.svg')) ?>"
                 alt="Avatar" class="sidebar__user-avatar">
            <div class="sidebar__user-name"><?= htmlspecialchars(($currentUser['prenom'] ?? '') . ' ' . ($currentUser['nom'] ?? '')) ?></div>
            <div class="sidebar__user-role">
                <?= htmlspecialchars(($currentUser['role'] ?? '') === 'enseignant'
                    ? ($currentUser['matiere'] ?? 'Enseignant')
                    : ($currentUser['classe'] ?? 'Élève')) ?>
            </div>
            <div class="sidebar__user-stats">
                <div class="sidebar__stat">
                    <span class="sidebar__stat-value"><?= $currentUser['posts_count'] ?? 0 ?></span>
                    <span class="sidebar__stat-label">Posts</span>
                </div>
                <div class="sidebar__stat">
                    <span class="sidebar__stat-value"><?= $currentUser['followers_count'] ?? 0 ?></span>
                    <span class="sidebar__stat-label">Abonnés</span>
                </div>
                <div class="sidebar__stat">
                    <span class="sidebar__stat-value"><?= $currentUser['following_count'] ?? 0 ?></span>
                    <span class="sidebar__stat-label">Abonnements</span>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="sidebar__nav">
            <a href="<?= url('/') ?>" class="sidebar__nav-item sidebar__nav-item--active">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
                <span>Accueil</span>
            </a>
            <a href="<?= url('/explore') ?>" class="sidebar__nav-item">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/>
                </svg>
                <span>Explorer</span>
            </a>
            <a href="#" class="sidebar__nav-item">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
                <span>Messages</span>
            </a>
            <a href="#" class="sidebar__nav-item">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="m19 21-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
                </svg>
                <span>Enregistrés</span>
            </a>
            <a href="<?= url('/profile') ?>" class="sidebar__nav-item">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                </svg>
                <span>Mon Profil</span>
            </a>
        </nav>
    </aside>

    <!-- ══════════════════════════════════════════ -->
    <!-- MAIN FEED                                  -->
    <!-- ══════════════════════════════════════════ -->
    <section class="feed-main">
        <!-- Filter Tabs -->
        <div class="feed-filters">
            <button class="feed-filters__btn feed-filters__btn--active feed__filter feed__filter--active" data-type="">Tout</button>
            <button class="feed-filters__btn feed__filter" data-type="question">Questions</button>
            <button class="feed-filters__btn feed__filter" data-type="ressource">Ressources</button>
            <button class="feed-filters__btn feed__filter" data-type="annonce">Annonces</button>
            <button class="feed-filters__btn feed__filter" data-type="following">Abonnements</button>
        </div>

        <!-- Post Composer -->
        <div class="post-composer">
            <div class="post-composer__top">
                <img src="<?= htmlspecialchars(!empty($currentUser['photo_profil']) ? url($currentUser['photo_profil']) : asset('images/default-avatar.svg')) ?>"
                     alt="" class="post-composer__avatar">
                <input type="text" class="post-composer__input" id="create-post-trigger"
                       placeholder="Partagez une ressource ou posez une question..." readonly>
            </div>
            <div class="post-composer__actions">
                <div class="post-composer__attachment-btns">
                    <button class="post-composer__attach-btn" onclick="PostComposer.openModal()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
                        </svg>
                        Photo
                    </button>
                    <button class="post-composer__attach-btn" onclick="PostComposer.openModal()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                        </svg>
                        Fichier
                    </button>
                    <button class="post-composer__attach-btn" onclick="PostComposer.openModal()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="4" y1="9" x2="20" y2="9"/><line x1="4" y1="15" x2="20" y2="15"/>
                            <line x1="10" y1="3" x2="8" y2="21"/><line x1="16" y1="3" x2="14" y2="21"/>
                        </svg>
                        Hashtag
                    </button>
                </div>
                <button class="btn btn--primary btn--sm" onclick="PostComposer.openModal()">Publier</button>
            </div>
        </div>

        <!-- New Posts Indicator -->
        <div class="new-posts-indicator hidden" id="new-posts-banner">
            <button class="new-posts-indicator__btn" onclick="Feed.loadNewPosts()">
                <span id="new-posts-text">Nouvelles publications</span>
            </button>
        </div>

        <!-- Posts Container -->
        <div id="feed-container">
            <!-- Posts chargés dynamiquement via JS -->
        </div>

        <!-- Empty State -->
        <div id="feed-empty" class="feed-empty hidden">
            <div class="feed-empty__icon">📝</div>
            <h3 class="feed-empty__title">Aucune publication pour le moment</h3>
            <p class="feed-empty__text">Soyez le premier à partager quelque chose avec la communauté !</p>
            <button class="btn btn--primary" id="empty-create-post">Créer une publication</button>
        </div>

        <!-- Loading Skeletons -->
        <div id="feed-loading">
            <div class="skeleton-card">
                <div class="skeleton-card__header">
                    <div class="skeleton skeleton--circle"></div>
                    <div class="skeleton-card__meta">
                        <div class="skeleton skeleton--text skeleton--w60"></div>
                        <div class="skeleton skeleton--text skeleton--w40"></div>
                    </div>
                </div>
                <div class="skeleton skeleton--text skeleton--w100"></div>
                <div class="skeleton skeleton--text skeleton--w80"></div>
                <div class="skeleton skeleton--text skeleton--w60"></div>
            </div>
            <div class="skeleton-card">
                <div class="skeleton-card__header">
                    <div class="skeleton skeleton--circle"></div>
                    <div class="skeleton-card__meta">
                        <div class="skeleton skeleton--text skeleton--w60"></div>
                        <div class="skeleton skeleton--text skeleton--w40"></div>
                    </div>
                </div>
                <div class="skeleton skeleton--text skeleton--w100"></div>
                <div class="skeleton skeleton--text skeleton--w90"></div>
            </div>
            <div class="skeleton-card">
                <div class="skeleton-card__header">
                    <div class="skeleton skeleton--circle"></div>
                    <div class="skeleton-card__meta">
                        <div class="skeleton skeleton--text skeleton--w60"></div>
                        <div class="skeleton skeleton--text skeleton--w40"></div>
                    </div>
                </div>
                <div class="skeleton skeleton--text skeleton--w100"></div>
                <div class="skeleton skeleton--text skeleton--w70"></div>
            </div>
        </div>

        <!-- Sentinel pour infinite scroll -->
        <div id="feed-sentinel" style="height:1px"></div>
    </section>

    <!-- ══════════════════════════════════════════ -->
    <!-- RIGHT SIDEBAR                              -->
    <!-- ══════════════════════════════════════════ -->
    <aside class="sidebar sidebar--right">
        <!-- Trending Topics -->
        <div class="trending-card">
            <h3 class="trending-card__title">🔥 Tendances</h3>
            <?php if (!empty($topQuestions)): ?>
                <?php foreach ($topQuestions as $i => $q): ?>
                    <div class="trending-item">
                        <span class="trending-item__rank"><?= $i + 1 ?></span>
                        <div>
                            <span class="trending-item__text"><?= htmlspecialchars(mb_substr($q['contenu'], 0, 60)) ?>...</span>
                            <span class="trending-item__count"><?= $q['likes_count'] ?? 0 ?> interactions</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="trending-item">
                    <span class="trending-item__rank">1</span>
                    <div>
                        <span class="trending-item__text">#CalculIntégral</span>
                        <span class="trending-item__count">128 publications</span>
                    </div>
                </div>
                <div class="trending-item">
                    <span class="trending-item__rank">2</span>
                    <div>
                        <span class="trending-item__text">#RévisionsBac</span>
                        <span class="trending-item__count">96 publications</span>
                    </div>
                </div>
                <div class="trending-item">
                    <span class="trending-item__rank">3</span>
                    <div>
                        <span class="trending-item__text">#PythonDébutant</span>
                        <span class="trending-item__count">74 publications</span>
                    </div>
                </div>
                <div class="trending-item">
                    <span class="trending-item__rank">4</span>
                    <div>
                        <span class="trending-item__text">#Méthodologie</span>
                        <span class="trending-item__count">52 publications</span>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Suggested Users -->
        <div class="suggested-card">
            <h3 class="suggested-card__title">Suggestions</h3>
            <?php if (!empty($suggestions)): ?>
                <?php foreach ($suggestions as $user): ?>
                    <div class="suggested-user">
                        <img src="<?= htmlspecialchars(!empty($user['photo_profil']) ? url($user['photo_profil']) : asset('images/default-avatar.svg')) ?>"
                             alt="" class="suggested-user__avatar">
                        <div class="suggested-user__info">
                            <a href="<?= url('/profile/' . $user['id']) ?>" class="suggested-user__name">
                                <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?>
                            </a>
                            <span class="suggested-user__role">
                                <?= htmlspecialchars($user['role'] === 'enseignant' ? ($user['matiere'] ?? 'Enseignant') : ($user['classe'] ?? 'Élève')) ?>
                            </span>
                        </div>
                        <button class="suggested-user__follow-btn" data-user-id="<?= $user['id'] ?>">Suivre</button>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="suggested-user">
                    <img src="<?= asset('images/default-avatar.svg') ?>" alt="" class="suggested-user__avatar">
                    <div class="suggested-user__info">
                        <span class="suggested-user__name">Marie Dupont</span>
                        <span class="suggested-user__role">Enseignante · Maths</span>
                    </div>
                    <button class="suggested-user__follow-btn">Suivre</button>
                </div>
                <div class="suggested-user">
                    <img src="<?= asset('images/default-avatar.svg') ?>" alt="" class="suggested-user__avatar">
                    <div class="suggested-user__info">
                        <span class="suggested-user__name">Lucas Martin</span>
                        <span class="suggested-user__role">Terminale S</span>
                    </div>
                    <button class="suggested-user__follow-btn">Suivre</button>
                </div>
                <div class="suggested-user">
                    <img src="<?= asset('images/default-avatar.svg') ?>" alt="" class="suggested-user__avatar">
                    <div class="suggested-user__info">
                        <span class="suggested-user__name">Sophie Bernard</span>
                        <span class="suggested-user__role">Enseignante · Physique</span>
                    </div>
                    <button class="suggested-user__follow-btn">Suivre</button>
                </div>
            <?php endif; ?>
        </div>
    </aside>
</div>

<!-- Post Composer Modal -->
<div class="modal-overlay hidden" id="create-post-modal">
    <div class="post-composer-modal">
        <div class="post-composer-modal__header">
            <h3>Créer une publication</h3>
            <button class="post-composer-modal__close" id="close-create-modal">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 6L6 18M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="post-composer-modal__body">
            <div class="post-composer-modal__user">
                <img src="<?= htmlspecialchars(!empty($currentUser['photo_profil']) ? url($currentUser['photo_profil']) : asset('images/default-avatar.svg')) ?>"
                     alt="" class="post-composer-modal__avatar">
                <div>
                    <div class="post-composer-modal__name"><?= htmlspecialchars(($currentUser['prenom'] ?? '') . ' ' . ($currentUser['nom'] ?? '')) ?></div>
                    <button class="post-composer-modal__visibility">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                        </svg>
                        Public
                    </button>
                </div>
            </div>

            <!-- Type de publication -->
            <div class="post-composer-modal__types" style="display:flex;gap:8px;margin-bottom:12px;">
                <button class="create-post-modal__type create-post-modal__type--active" data-type="partage" style="padding:6px 14px;border:1.5px solid var(--color-primary);border-radius:9999px;background:var(--color-lavender);color:var(--color-primary);font-size:0.75rem;font-weight:600;cursor:pointer;transition:all .15s ease;">Partage</button>
                <button class="create-post-modal__type" data-type="question" style="padding:6px 14px;border:1.5px solid var(--color-gray-300);border-radius:9999px;background:transparent;color:var(--color-gray-600);font-size:0.75rem;font-weight:600;cursor:pointer;transition:all .15s ease;">Question</button>
                <button class="create-post-modal__type" data-type="ressource" style="padding:6px 14px;border:1.5px solid var(--color-gray-300);border-radius:9999px;background:transparent;color:var(--color-gray-600);font-size:0.75rem;font-weight:600;cursor:pointer;transition:all .15s ease;">Ressource</button>
                <button class="create-post-modal__type" data-type="annonce" style="padding:6px 14px;border:1.5px solid var(--color-gray-300);border-radius:9999px;background:transparent;color:var(--color-gray-600);font-size:0.75rem;font-weight:600;cursor:pointer;transition:all .15s ease;">Annonce</button>
            </div>

            <textarea class="post-composer-modal__textarea" id="post-content"
                      placeholder="Que souhaitez-vous partager avec la communauté ?" rows="5"></textarea>

            <!-- Image Preview -->
            <div class="post-composer-modal__media-preview hidden" id="image-preview-container" style="position:relative;margin-top:12px;">
                <img id="image-preview" src="" alt="Aperçu" style="width:100%;max-height:300px;object-fit:cover;border-radius:8px;">
                <button id="remove-image" class="media-preview-item__remove" style="position:absolute;top:8px;right:8px;width:28px;height:28px;border-radius:50%;background:rgba(0,0,0,0.6);color:white;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;" title="Supprimer l'image">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 6L6 18M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <input type="file" id="post-image-input" accept="image/*" class="hidden">
        </div>
        <div class="post-composer-modal__footer">
            <div class="post-composer-modal__attach-list">
                <button class="post-composer-modal__attach-btn" id="attach-image-btn" title="Ajouter une image">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
                    </svg>
                </button>
                <button class="post-composer-modal__attach-btn" title="Hashtag">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="4" y1="9" x2="20" y2="9"/><line x1="4" y1="15" x2="20" y2="15"/>
                        <line x1="10" y1="3" x2="8" y2="21"/><line x1="16" y1="3" x2="14" y2="21"/>
                    </svg>
                </button>
            </div>
            <span style="font-size:12px;color:var(--color-gray-500)" id="composer-char-count">0/2000</span>
            <button class="btn btn--primary" id="submit-post">Publier</button>
        </div>
    </div>
</div>

<!-- FAB Button (Desktop) -->
<button class="fab" id="fab-create-post" onclick="PostComposer.openModal()" title="Créer une publication">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5">
        <path d="M12 5v14"/><path d="M5 12h14"/>
    </svg>
</button>
