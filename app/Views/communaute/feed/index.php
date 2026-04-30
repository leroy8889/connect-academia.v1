<?php
$currentUser      = $currentUser      ?? [];
$topQuestions     = $topQuestions     ?? [];
$suggestions      = $suggestions      ?? [];
$trendingHashtags = $trendingHashtags ?? [];
$unreadNotifs     = (int) ($unreadNotifs ?? 0);

$userName = e(($currentUser['prenom'] ?? '') . ' ' . ($currentUser['nom'] ?? ''));
$userPhoto = $currentUser['photo_profil'] ?? null;
$userRole  = $currentUser['role'] ?? 'eleve';
$userId    = \Core\Session::userId();
?>

<div class="feed-layout">

  <!-- ══ SIDEBAR GAUCHE ═══════════════════════════════════════ -->
  <aside class="sidebar--left">

    <!-- Carte utilisateur -->
    <div class="sidebar__user-card">
      <a href="<?= url('/communaute/profil/' . $userId) ?>">
        <img
          src="<?= e($userPhoto ? url($userPhoto) : asset('images/default-avatar.svg')) ?>"
          alt="<?= $userName ?>"
          class="sidebar__user-avatar"
          onerror="this.onerror=null;this.src=<?= htmlspecialchars(json_encode(asset('images/default-avatar.svg')), ENT_QUOTES) ?>">
      </a>
      <div class="sidebar__user-name"><?= $userName ?></div>
      <div class="sidebar__user-role"><?= e(ucfirst($userRole)) ?></div>
      <div class="sidebar__user-stats">
        <div class="sidebar__stat">
          <div class="sidebar__stat-value" id="stat-posts"><?= (int)($currentUser['posts_count'] ?? 0) ?></div>
          <div class="sidebar__stat-label">Posts</div>
        </div>
        <div class="sidebar__stat">
          <div class="sidebar__stat-value" id="stat-followers"><?= (int)($currentUser['followers_count'] ?? 0) ?></div>
          <div class="sidebar__stat-label">Abonnés</div>
        </div>
        <div class="sidebar__stat">
          <div class="sidebar__stat-value" id="stat-following"><?= (int)($currentUser['following_count'] ?? 0) ?></div>
          <div class="sidebar__stat-label">Abonnements</div>
        </div>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="sidebar__nav">
      <a href="<?= url('/communaute') ?>" class="sidebar__nav-item sidebar__nav-item--active">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
        </svg>
        Mon fil
      </a>
      <a href="<?= url('/communaute/explorer') ?>" class="sidebar__nav-item">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        Explorer
      </a>
      <a href="<?= url('/communaute/profil/' . $userId) ?>" class="sidebar__nav-item">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
        </svg>
        Mon profil
      </a>
      <a href="<?= url('/communaute/chat') ?>" class="sidebar__nav-item">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
        </svg>
        Chat
      </a>
    </nav>

  </aside>

  <!-- ══ FEED CENTRAL ════════════════════════════════════════ -->
  <main class="feed-main">

    <!-- Bannière nouveaux posts -->
    <div id="new-posts-banner" class="new-posts-banner hidden" role="alert">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 5 5 12"/></svg>
      Nouveaux posts
    </div>

    <!-- Filtres -->
    <div class="feed-filters" role="tablist">
      <button class="feed-filters__btn feed-filters__btn--active feed__filter" data-filter="" role="tab">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
        </svg>
        Mon fil
      </button>
      <button class="feed-filters__btn feed__filter" data-filter="question" role="tab">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
        Questions
      </button>
      <button class="feed-filters__btn feed__filter" data-filter="ressource" role="tab">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
        </svg>
        Ressources
      </button>
      <button class="feed-filters__btn feed__filter" data-filter="partage" role="tab">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
        </svg>
        Discussions
      </button>
    </div>

    <!-- Composer -->
    <div class="post-composer" id="post-composer-trigger">
      <div class="post-composer__top">
        <img
          src="<?= e($userPhoto ? url($userPhoto) : asset('images/default-avatar.svg')) ?>"
          alt="<?= $userName ?>"
          class="post-composer__avatar"
          onerror="this.onerror=null;this.src=<?= htmlspecialchars(json_encode(asset('images/default-avatar.svg')), ENT_QUOTES) ?>">
        <button class="post-composer__input" id="create-post-trigger" type="button">
          Partagez quelque chose, posez une question…
        </button>
      </div>
      <div class="post-composer__actions">
        <div class="post-composer__attachment-btns">
          <button class="post-composer__attach-btn" type="button" id="open-composer-question">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            Question
          </button>
          <button class="post-composer__attach-btn" type="button" id="open-composer-resource">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
            </svg>
            Ressource
          </button>
        </div>
      </div>
    </div>

    <!-- Modal composer -->
    <style>
      #create-post-modal{position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);z-index:1000;display:flex;align-items:center;justify-content:center;padding:1rem}
      .post-composer-modal{display:flex;flex-direction:column;max-height:calc(100vh - 2rem)}
      .post-composer-modal .post-composer-modal__body{overflow-y:auto;flex:1;min-height:0}
      #submit-post:not(:disabled){opacity:1;cursor:pointer}
      #submit-post:disabled{opacity:.5;cursor:not-allowed}
    </style>
    <div id="create-post-modal" class="hidden" role="dialog" aria-modal="true" aria-label="Nouvelle publication">
      <div class="post-composer-modal">

        <div class="post-composer-modal__header">
          <h3>Nouvelle publication</h3>
          <button class="post-composer-modal__close" id="close-create-modal" aria-label="Fermer">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
          </button>
        </div>

        <div class="post-composer-modal__body">

          <!-- Sélecteur de type -->
          <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
            <button class="create-post-modal__type create-post-modal__type--active" data-type="partage"
                    style="padding:6px 16px;border:1.5px solid var(--color-primary);background:var(--color-lavender);color:var(--color-primary);border-radius:var(--radius-full);font-size:13px;font-weight:600;cursor:pointer">Partage</button>
            <button class="create-post-modal__type" data-type="question"
                    style="padding:6px 16px;border:1.5px solid var(--color-gray-300);background:transparent;color:var(--color-gray-600);border-radius:var(--radius-full);font-size:13px;font-weight:600;cursor:pointer">Question</button>
            <button class="create-post-modal__type" data-type="ressource"
                    style="padding:6px 16px;border:1.5px solid var(--color-gray-300);background:transparent;color:var(--color-gray-600);border-radius:var(--radius-full);font-size:13px;font-weight:600;cursor:pointer">Ressource</button>
          </div>

          <!-- Zone de texte -->
          <textarea id="post-content" class="post-composer-modal__textarea"
                    placeholder="Partagez quelque chose, posez une question…"
                    maxlength="2000"></textarea>
          <div style="text-align:right;font-size:11px;color:var(--color-gray-500);margin-top:4px">
            <span id="composer-char-count">0/2000</span>
          </div>

          <!-- Aperçu image -->
          <div id="image-preview-container" class="hidden" style="margin-top:12px;position:relative">
            <img id="image-preview" src="" alt="Aperçu"
                 style="max-width:100%;max-height:220px;border-radius:8px;display:block;object-fit:contain;background:#f3f4f6">
            <button id="remove-image" type="button" aria-label="Supprimer l'image"
                    style="position:absolute;top:6px;right:6px;width:28px;height:28px;border-radius:50%;background:rgba(0,0,0,.55);color:#fff;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
              </svg>
            </button>
          </div>

        </div><!-- /.body -->

        <div class="post-composer-modal__footer">
          <div class="post-composer-modal__attach-list">
            <button class="post-composer-modal__attach-btn" id="attach-image-btn" type="button" title="Joindre une image">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
              </svg>
            </button>
            <input type="file" id="post-image-input" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none">
          </div>
          <button id="submit-post" type="button" disabled
                  style="padding:10px 24px;background:var(--color-primary);color:#fff;border:none;border-radius:var(--radius-full);font-size:14px;font-weight:600;transition:opacity .2s">
            Publier
          </button>
        </div>

      </div><!-- /.post-composer-modal -->
    </div><!-- /#create-post-modal -->

    <!-- Posts container -->
    <div id="feed-container" aria-live="polite" aria-label="Fil d'actualité"></div>

    <!-- État vide -->
    <div id="feed-empty" class="feed-empty hidden">
      <div class="feed-empty__icon">📝</div>
      <h3 class="feed-empty__title">Aucune publication pour l'instant</h3>
      <p class="feed-empty__text">Soyez le premier à partager quelque chose avec la communauté !</p>
    </div>

    <!-- Loading -->
    <div id="feed-loading" class="feed-loading" style="display:none">
      <div class="feed-loading__spinner"></div>
    </div>

    <!-- Sentinel infinite scroll -->
    <div id="feed-sentinel" aria-hidden="true" style="height:1px"></div>

  </main>

  <!-- ══ SIDEBAR DROITE ════════════════════════════════════ -->
  <aside style="min-width:0">

    <!-- Top questions -->
    <?php if (!empty($topQuestions)): ?>
      <div style="background:var(--glass-bg);border:var(--glass-border);border-radius:var(--radius-lg);padding:var(--space-5);margin-bottom:var(--space-4);box-shadow:var(--glass-shadow)">
        <div style="font-size:var(--font-size-base);font-weight:600;color:var(--color-dark);margin-bottom:var(--space-4)">
          Questions populaires
        </div>
        <?php foreach ($topQuestions as $q): ?>
          <div style="display:flex;gap:var(--space-3);padding:var(--space-2) 0;border-bottom:1px solid var(--color-gray-200);cursor:pointer"
               onclick="window.location.href='<?= url('/communaute') ?>#post-<?= (int)($q['id'] ?? 0) ?>'" role="button" style="cursor:pointer">
            <div style="width:28px;height:28px;background:var(--color-lavender);border-radius:var(--radius-full);display:flex;align-items:center;justify-content:center;flex-shrink:0">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#8B52FA" stroke-width="2.5">
                <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>
              </svg>
            </div>
            <div style="min-width:0">
              <div style="font-size:var(--font-size-sm);font-weight:500;color:var(--color-dark);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden"><?= e($q['contenu'] ?? '') ?></div>
              <div style="font-size:var(--font-size-xs);color:var(--color-gray-500);margin-top:2px"><?= (int)($q['comments_count'] ?? 0) ?> réponse<?= (int)($q['comments_count'] ?? 0) > 1 ? 's' : '' ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Suggestions abonnements -->
    <?php if (!empty($suggestions)): ?>
      <div class="suggested-card">
        <div class="suggested-card__title">Personnes à suivre</div>
        <?php foreach ($suggestions as $s): ?>
          <div class="suggested-user" data-user-id="<?= (int)$s['id'] ?>">
            <a href="<?= url('/communaute/profil/' . (int)$s['id']) ?>">
              <img
                src="<?= e($s['photo_profil'] ? url($s['photo_profil']) : asset('images/default-avatar.svg')) ?>"
                alt="<?= e($s['prenom'] . ' ' . $s['nom']) ?>"
                class="suggested-user__avatar"
                onerror="this.onerror=null;this.src=<?= htmlspecialchars(json_encode(asset('images/default-avatar.svg')), ENT_QUOTES) ?>">
            </a>
            <div class="suggested-user__info">
              <a href="<?= url('/communaute/profil/' . (int)$s['id']) ?>" class="suggested-user__name">
                <?= e($s['prenom'] . ' ' . $s['nom']) ?>
              </a>
              <div class="suggested-user__role"><?= e(ucfirst($s['role'] ?? '')) ?></div>
            </div>
            <button
              class="suggested-user__follow-btn follow-btn"
              data-user-id="<?= (int)$s['id'] ?>"
              data-following="0">
              Suivre
            </button>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Hashtags tendance -->
    <?php if (!empty($trendingHashtags)): ?>
      <div style="background:var(--glass-bg);border:var(--glass-border);border-radius:var(--radius-lg);padding:var(--space-5);margin-top:var(--space-4);box-shadow:var(--glass-shadow)">
        <div class="trending-card__title">Tendances</div>
        <?php foreach ($trendingHashtags as $i => $h): ?>
          <div class="trending-item" onclick="window.location.href='<?= url('/communaute/explorer') ?>?q=%23<?= rawurlencode($h['tag'] ?? '') ?>'"
               style="cursor:pointer">
            <div class="trending-item__rank"><?= $i + 1 ?></div>
            <div>
              <div class="trending-item__text">#<?= e($h['tag'] ?? '') ?></div>
              <span class="trending-item__count"><?= (int)($h['count'] ?? 0) ?> post<?= (int)($h['count'] ?? 0) > 1 ? 's' : '' ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </aside>

</div>

<!-- FAB mobile -->
<button class="fab" id="fab-create-post" aria-label="Nouveau post">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
  </svg>
</button>

<!-- Modal signalement -->
<div id="report-modal-overlay" class="hidden"
     style="position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);z-index:1000;display:flex;align-items:center;justify-content:center;padding:1rem">
  <div id="report-modal" style="background:#fff;border-radius:16px;width:90%;max-width:440px;box-shadow:0 20px 60px rgba(0,0,0,.2)">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:20px 24px;border-bottom:1px solid var(--color-gray-200)">
      <h3 style="font-size:16px;font-weight:600">Signaler cette publication</h3>
      <button onclick="App.closeModal('report-modal-overlay')" style="border:none;background:none;cursor:pointer;color:var(--color-gray-500);width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:50%">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <div style="padding:20px 24px">
      <p style="font-size:13px;color:var(--color-gray-600);margin-bottom:16px">Pourquoi signalez-vous cette publication ?</p>
      <div style="display:flex;flex-direction:column;gap:8px" id="report-reasons">
        <?php foreach(['spam' => 'Spam ou publicité', 'inappropriate' => 'Contenu inapproprié', 'harassment' => 'Harcèlement', 'misinformation' => 'Désinformation', 'other' => 'Autre'] as $val => $label): ?>
          <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1.5px solid var(--color-gray-200);border-radius:10px;cursor:pointer;transition:border-color .15s"
                 onmouseover="this.style.borderColor='var(--color-primary)'" onmouseout="if(!this.querySelector('input').checked)this.style.borderColor='var(--color-gray-200)'">
            <input type="radio" name="report_reason" value="<?= $val ?>" style="accent-color:var(--color-primary)">
            <span style="font-size:14px;font-weight:500"><?= $label ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
    <div style="display:flex;gap:10px;padding:16px 24px;border-top:1px solid var(--color-gray-200);justify-content:flex-end">
      <button onclick="App.closeModal('report-modal-overlay')"
              style="padding:9px 20px;background:var(--color-gray-100);border:none;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer">
        Annuler
      </button>
      <button id="report-submit-btn"
              style="padding:9px 20px;background:var(--color-primary);color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer"
              onclick="(async()=>{
                const reason=document.querySelector('[name=report_reason]:checked')?.value;
                if(!reason){App.toast.warning('Choisissez un motif');return;}
                const postId=document.getElementById('report-modal').dataset.postId;
                try{
                  await API.post('/api/communaute/posts/'+postId+'/report',{reason});
                  App.closeModal('report-modal-overlay');
                  document.querySelectorAll('[name=report_reason]').forEach(r=>r.checked=false);
                  App.toast.success('Signalement envoyé. Merci de votre vigilance.');
                }catch(e){App.toast.error(e.message||'Erreur');}
              })()">
        Signaler
      </button>
    </div>
  </div>
</div>

<!-- Panel commentaires -->
<div class="comments-overlay" id="comments-overlay"></div>
<div class="comments-panel" id="comments-panel" role="dialog" aria-label="Commentaires">

  <div class="comments-panel__header">
    <div>
      <span class="comments-panel__title" id="comments-title">Commentaires</span>
      <span class="comments-panel__count" id="comments-count"></span>
    </div>
    <button class="comments-panel__close" id="comments-close-btn" aria-label="Fermer">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
      </svg>
    </button>
  </div>

  <div class="comments-panel__sort">
    <button class="comments-panel__sort-btn comments-panel__sort-btn--active" data-sort="recent">Récents</button>
    <button class="comments-panel__sort-btn" data-sort="best">Meilleurs</button>
  </div>

  <div class="comments-panel__list" id="comments-list"></div>

  <div class="comments-panel__input">
    <div class="comments-panel__reply-indicator hidden" id="reply-indicator">
      <span></span>
      <button class="comments-panel__reply-cancel" id="reply-cancel-btn" type="button" aria-label="Annuler la réponse">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <div class="comments-panel__input-row">
      <img src="<?= e($userPhoto ? url($userPhoto) : asset('images/default-avatar.svg')) ?>"
           alt="" class="comments-panel__input-avatar"
           onerror="this.onerror=null;this.src=<?= htmlspecialchars(json_encode(asset('images/default-avatar.svg')), ENT_QUOTES) ?>">
      <div class="comments-panel__input-wrapper">
        <textarea id="comment-textarea" class="comments-panel__textarea"
                  placeholder="Écrire un commentaire…" rows="1"></textarea>
        <button id="comment-send-btn" class="comments-panel__send-btn" type="button" aria-label="Envoyer">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
            <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
          </svg>
        </button>
      </div>
    </div>
  </div>

</div><!-- /#comments-panel -->

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Feed !== 'undefined') Feed.init();
    if (typeof Notifications !== 'undefined') Notifications.init(<?= $unreadNotifs ?>);

    // Boutons "Question" et "Ressource" ouvrent le modal avec le bon type
    document.getElementById('open-composer-question')?.addEventListener('click', function () {
        if (typeof PostComposer !== 'undefined') {
            PostComposer.openModal();
            setTimeout(function () {
                document.querySelector('[data-type="question"]')?.click();
            }, 50);
        }
    });
    document.getElementById('open-composer-resource')?.addEventListener('click', function () {
        if (typeof PostComposer !== 'undefined') {
            PostComposer.openModal();
            setTimeout(function () {
                document.querySelector('[data-type="ressource"]')?.click();
            }, 50);
        }
    });
});
</script>
