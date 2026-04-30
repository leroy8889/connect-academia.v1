<?php
$profileUser  = $profileUser  ?? [];
$isOwner      = $isOwner      ?? false;
$isFollowing  = $isFollowing  ?? false;
$posts        = $posts        ?? [];
$bookmarks    = $bookmarks    ?? [];
$comments     = $comments     ?? [];
$unreadNotifs = (int) ($unreadNotifs ?? 0);

$profileId  = (int) ($profileUser['id'] ?? 0);
$fullName   = e(($profileUser['prenom'] ?? '') . ' ' . ($profileUser['nom'] ?? ''));
$role       = $profileUser['role'] ?? 'eleve';
$photo      = $profileUser['photo_profil'] ?? null;
$bio        = $profileUser['bio'] ?? null;

function relativeDate(string $date): string {
    $diff = time() - strtotime($date);
    if ($diff < 3600)    return floor($diff / 60) . 'min';
    if ($diff < 86400)   return floor($diff / 3600) . 'h';
    if ($diff < 604800)  return floor($diff / 86400) . 'j';
    return date('d/m/Y', strtotime($date));
}
?>

<div class="profile-page">

  <!-- ══ HERO ══════════════════════════════════════════════════ -->
  <div class="profile-hero">

    <!-- Bannière -->
    <div class="profile-hero__banner">
      <?php if ($isOwner): ?>
        <button class="profile-hero__banner-edit">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
          </svg>
          Modifier
        </button>
      <?php endif; ?>
    </div>

    <!-- Contenu profil -->
    <div class="profile-hero__content">

      <!-- Avatar -->
      <div class="profile-hero__avatar-wrapper">
        <img
          src="<?= e($photo ?: asset('images/default-avatar.svg')) ?>"
          alt="<?= $fullName ?>"
          class="profile-hero__avatar"
          onerror="this.onerror=null;this.src=<?= htmlspecialchars(json_encode(asset('images/default-avatar.svg')), ENT_QUOTES) ?>">
        <?php if ($isOwner): ?>
          <button class="profile-hero__avatar-edit" title="Changer l'avatar" aria-label="Modifier l'avatar">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
              <circle cx="12" cy="13" r="4"/>
            </svg>
          </button>
        <?php endif; ?>
      </div>

      <!-- Infos + Actions -->
      <div class="profile-hero__top">
        <div class="profile-hero__identity">
          <h1 class="profile-hero__name">
            <?= $fullName ?>
            <?php if ($role === 'professeur'): ?>
              <svg class="profile-hero__verified" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
              </svg>
            <?php endif; ?>
          </h1>
          <span class="profile-hero__role-badge profile-hero__role-badge--<?= $role === 'professeur' ? 'teacher' : 'student' ?>">
            <?php if ($role === 'professeur'): ?>
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>
              </svg>
              Professeur
            <?php else: ?>
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
              </svg>
              Élève / Étudiant
            <?php endif; ?>
          </span>

          <?php if (!empty($bio)): ?>
            <p class="profile-hero__bio"><?= e($bio) ?></p>
          <?php endif; ?>

          <div class="profile-hero__details">
            <?php if (!empty($profileUser['created_at'])): ?>
              <span class="profile-hero__detail">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
                Membre depuis <?= date('F Y', strtotime($profileUser['created_at'])) ?>
              </span>
            <?php endif; ?>
          </div>
        </div>

        <!-- Boutons action -->
        <div class="profile-hero__actions">
          <?php if ($isOwner): ?>
            <button
              onclick="document.getElementById('edit-profile-section').classList.toggle('hidden')"
              style="padding:10px 20px;background:#F3F4F6;color:#374151;border:none;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;transition:background .2s"
              onmouseover="this.style.background='#E5E7EB'" onmouseout="this.style.background='#F3F4F6'">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
              </svg>
              Modifier le profil
            </button>
          <?php else: ?>
            <button
              class="follow-btn"
              data-user-id="<?= $profileId ?>"
              data-following="<?= $isFollowing ? '1' : '0' ?>"
              style="padding:10px 22px;background:<?= $isFollowing ? '#F3EFFF' : 'var(--color-primary)' ?>;color:<?= $isFollowing ? 'var(--color-primary)' : '#fff' ?>;border:<?= $isFollowing ? '1.5px solid var(--color-primary)' : 'none' ?>;border-radius:var(--radius-full);font-size:14px;font-weight:600;cursor:pointer;transition:all .2s">
              <?= $isFollowing ? 'Abonné' : 'Suivre' ?>
            </button>
            <a href="<?= url('/communaute/chat') ?>"
               style="padding:10px 20px;background:#F3F4F6;color:#374151;border:none;border-radius:var(--radius-full);font-size:14px;font-weight:600;cursor:pointer;text-decoration:none;display:flex;align-items:center;gap:6px">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
              </svg>
              Chat
            </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Stats -->
      <div class="profile-hero__stats">
        <div class="profile-stat" onclick="switchTab('posts')">
          <span class="profile-stat__value"><?= count($posts) ?></span>
          <span class="profile-stat__label">Posts</span>
        </div>
        <div class="profile-stat">
          <span class="profile-stat__value"><?= (int)($profileUser['followers_count'] ?? 0) ?></span>
          <span class="profile-stat__label">Abonnés</span>
        </div>
        <div class="profile-stat">
          <span class="profile-stat__value"><?= (int)($profileUser['following_count'] ?? 0) ?></span>
          <span class="profile-stat__label">Abonnements</span>
        </div>
      </div>

    </div><!-- /.profile-hero__content -->
  </div><!-- /.profile-hero -->

  <!-- Input file caché pour changer l'avatar (owner only) -->
  <?php if ($isOwner): ?>
    <input type="file" id="photo-profil-input" accept="image/jpeg,image/png,image/webp" style="display:none">
  <?php endif; ?>

  <!-- ══ FORMULAIRE MODIFICATION (owner only) ═══════════════ -->
  <?php if ($isOwner): ?>
    <div id="edit-profile-section" class="hidden">
      <form class="edit-profile-form" method="POST" action="<?= url('/api/communaute/profil/update') ?>" enctype="multipart/form-data" id="profile-edit-form">
        <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
        <input type="file" name="photo_profil" id="profile-form-photo-input" accept="image/jpeg,image/png,image/webp" style="display:none">
        <h2 class="edit-profile-form__title">Modifier mon profil</h2>
        <div class="edit-profile-form__row">
          <div>
            <label style="font-size:13px;font-weight:600;display:block;margin-bottom:6px">Prénom</label>
            <input type="text" name="prenom" value="<?= e($profileUser['prenom'] ?? '') ?>"
                   style="width:100%;padding:10px 14px;border:1px solid var(--color-gray-200);border-radius:10px;font-size:14px;font-family:inherit;outline:none">
          </div>
          <div>
            <label style="font-size:13px;font-weight:600;display:block;margin-bottom:6px">Nom</label>
            <input type="text" name="nom" value="<?= e($profileUser['nom'] ?? '') ?>"
                   style="width:100%;padding:10px 14px;border:1px solid var(--color-gray-200);border-radius:10px;font-size:14px;font-family:inherit;outline:none">
          </div>
        </div>
        <div style="margin-top:16px">
          <label style="font-size:13px;font-weight:600;display:block;margin-bottom:6px">Bio</label>
          <textarea name="bio" rows="3"
                    style="width:100%;padding:10px 14px;border:1px solid var(--color-gray-200);border-radius:10px;font-size:14px;font-family:inherit;outline:none;resize:vertical"
                    placeholder="Parlez un peu de vous…"><?= e($bio ?? '') ?></textarea>
        </div>
        <div class="edit-profile-form__actions">
          <button type="button" onclick="document.getElementById('edit-profile-section').classList.add('hidden')"
                  style="padding:10px 20px;background:#F3F4F6;border:none;border-radius:10px;font-weight:600;cursor:pointer">Annuler</button>
          <button type="submit"
                  style="padding:10px 24px;background:var(--color-primary);color:#fff;border:none;border-radius:10px;font-weight:600;cursor:pointer">Enregistrer</button>
        </div>
      </form>
    </div>
  <?php endif; ?>

  <!-- ══ ONGLETS ════════════════════════════════════════════ -->
  <div class="profile-tabs" role="tablist">
    <button class="profile-tabs__tab profile-tabs__tab--active" data-tab="posts" onclick="switchTab('posts')" role="tab">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
      </svg>
      Posts
      <span class="profile-tabs__tab-count"><?= count($posts) ?></span>
    </button>
    <button class="profile-tabs__tab" data-tab="comments" onclick="switchTab('comments')" role="tab">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
      </svg>
      Réponses
      <span class="profile-tabs__tab-count"><?= count($comments) ?></span>
    </button>
    <?php if ($isOwner): ?>
      <button class="profile-tabs__tab" data-tab="bookmarks" onclick="switchTab('bookmarks')" role="tab">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
        </svg>
        Enregistrés
        <span class="profile-tabs__tab-count"><?= count($bookmarks) ?></span>
      </button>
    <?php endif; ?>
  </div>

  <!-- ══ CONTENU ONGLETS ════════════════════════════════════ -->
  <div class="profile-content">

    <!-- ── Posts ── -->
    <div id="tab-posts" class="tab-panel">
      <?php if (empty($posts)): ?>
        <div style="text-align:center;padding:48px 20px;color:var(--color-gray-500)">
          <div style="font-size:40px;margin-bottom:12px">📝</div>
          <p style="font-size:15px"><?= $isOwner ? 'Vous n\'avez pas encore publié de post.' : 'Aucun post pour l\'instant.' ?></p>
          <?php if ($isOwner): ?>
            <a href="<?= url('/communaute') ?>" style="display:inline-block;margin-top:16px;padding:10px 20px;background:var(--color-primary);color:#fff;border-radius:10px;text-decoration:none;font-weight:600;font-size:14px">
              Publier un post
            </a>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <?php foreach ($posts as $p):
          $pAuthor = e(($profileUser['prenom'] ?? '') . ' ' . ($profileUser['nom'] ?? ''));
          $pPhoto  = $photo;
          $pTime   = relativeDate($p['created_at'] ?? '');
          $pLikes  = (int)($p['likes_count'] ?? 0);
          $pComm   = (int)($p['comments_count'] ?? 0);
        ?>
          <div class="post-card">
            <div class="post-card__header">
              <img src="<?= e($pPhoto ?: asset('images/default-avatar.svg')) ?>"
                   alt="<?= $pAuthor ?>" class="post-card__avatar"
                   onerror="this.onerror=null;this.src=<?= htmlspecialchars(json_encode(asset('images/default-avatar.svg')), ENT_QUOTES) ?>">
              <div class="post-card__info">
                <div class="post-card__author">
                  <a href="<?= url('/communaute/profil/' . $profileId) ?>" class="post-card__name"><?= $pAuthor ?></a>
                  <?php if ($role === 'professeur'): ?>
                    <span class="post-card__badge post-card__badge--teacher">Professeur</span>
                  <?php endif; ?>
                  <?php if (!empty($p['categorie']) && $p['categorie'] === 'question'): ?>
                    <span class="post-card__badge" style="background:#EFF6FF;color:#3B82F6">Question</span>
                  <?php endif; ?>
                </div>
                <div class="post-card__meta">
                  <span data-time="<?= e($p['created_at'] ?? '') ?>"><?= $pTime ?></span>
                </div>
              </div>
            </div>

            <div class="post-card__content">
              <div class="post-card__text"><?= nl2br(htmlspecialchars($p['contenu'] ?? '', ENT_QUOTES)) ?></div>
            </div>

            <div class="post-card__actions">
              <button class="post-card__action <?= (int)($p['user_liked'] ?? 0) ? 'post-card__action--liked' : '' ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                </svg>
                <?= $pLikes ?>
              </button>
              <button class="post-card__action">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
                <?= $pComm ?>
              </button>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- ── Réponses ── -->
    <div id="tab-comments" class="tab-panel hidden">
      <?php if (empty($comments)): ?>
        <div style="text-align:center;padding:48px 20px;color:var(--color-gray-500)">
          <div style="font-size:40px;margin-bottom:12px">💬</div>
          <p style="font-size:15px">Aucune réponse publiée.</p>
        </div>
      <?php else: ?>
        <?php foreach ($comments as $c): ?>
          <div style="background:#fff;border-radius:14px;padding:16px;margin-bottom:12px;border:1px solid var(--color-gray-200);box-shadow:0 2px 8px rgba(0,0,0,.04)">
            <div style="font-size:12px;color:var(--color-gray-500);margin-bottom:8px">
              En réponse à un post · <?= relativeDate($c['created_at'] ?? '') ?>
            </div>
            <div style="font-size:14px;color:var(--color-dark);line-height:1.6">
              <?= nl2br(htmlspecialchars($c['contenu'] ?? '', ENT_QUOTES)) ?>
            </div>
            <?php if (!empty($c['post_id'])): ?>
              <a href="<?= url('/communaute') ?>#post-<?= (int)$c['post_id'] ?>"
                 style="font-size:12px;color:var(--color-primary);text-decoration:none;margin-top:8px;display:inline-block">
                Voir le post →
              </a>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- ── Enregistrés (owner only) ── -->
    <?php if ($isOwner): ?>
      <div id="tab-bookmarks" class="tab-panel hidden">
        <?php if (empty($bookmarks)): ?>
          <div style="text-align:center;padding:48px 20px;color:var(--color-gray-500)">
            <div style="font-size:40px;margin-bottom:12px">🔖</div>
            <p style="font-size:15px">Aucun post enregistré pour l'instant.</p>
          </div>
        <?php else: ?>
          <?php foreach ($bookmarks as $b):
            $bPhoto = $b['photo_profil'] ?? null;
            $bName  = e(($b['prenom'] ?? '') . ' ' . ($b['nom'] ?? ''));
          ?>
            <div class="post-card">
              <div class="post-card__header">
                <img src="<?= e($bPhoto ?: asset('images/default-avatar.svg')) ?>"
                     alt="<?= $bName ?>" class="post-card__avatar"
                     onerror="this.onerror=null;this.src=<?= htmlspecialchars(json_encode(asset('images/default-avatar.svg')), ENT_QUOTES) ?>">
                <div class="post-card__info">
                  <a href="<?= url('/communaute/profil/' . (int)($b['user_id'] ?? 0)) ?>" class="post-card__name"><?= $bName ?></a>
                  <div class="post-card__meta">
                    <span><?= relativeDate($b['created_at'] ?? '') ?></span>
                  </div>
                </div>
              </div>
              <div class="post-card__content">
                <div class="post-card__text"><?= nl2br(htmlspecialchars($b['contenu'] ?? '', ENT_QUOTES)) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  </div><!-- /.profile-content -->

</div><!-- /.profile-page -->

<script>
function switchTab(name) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.add('hidden'));
    document.querySelectorAll('.profile-tabs__tab').forEach(t => t.classList.remove('profile-tabs__tab--active'));
    const panel = document.getElementById('tab-' + name);
    if (panel) panel.classList.remove('hidden');
    document.querySelector(`[data-tab="${name}"]`)?.classList.add('profile-tabs__tab--active');
}

document.addEventListener('DOMContentLoaded', function () {
    if (typeof Follow !== 'undefined') Follow.init();
    if (typeof Notifications !== 'undefined') Notifications.init(<?= $unreadNotifs ?>);

    // ── Upload photo avatar (clic sur bouton caméra) ──────────
    const avatarInput      = document.getElementById('photo-profil-input');
    const avatarEditBtn    = document.querySelector('.profile-hero__avatar-edit');
    const avatarImg        = document.querySelector('.profile-hero__avatar');
    const navbarAvatar     = document.querySelector('.navbar__avatar');
    const profileFormPhoto = document.getElementById('profile-form-photo-input');

    if (avatarEditBtn && avatarInput) {
        avatarEditBtn.addEventListener('click', function () {
            avatarInput.click();
        });

        avatarInput.addEventListener('change', async function () {
            const file = this.files[0];
            if (!file) return;

            // URL de secours (ancienne photo déjà normalisée)
            const fallbackSrc = <?= json_encode($photo ?? asset('images/default-avatar.svg')) ?>;

            // Aperçu local immédiat via FileReader
            const reader = new FileReader();
            reader.onload = function (ev) {
                if (avatarImg)    avatarImg.src    = ev.target.result;
                if (navbarAvatar) navbarAvatar.src = ev.target.result;
            };
            reader.readAsDataURL(file);

            // Upload vers le serveur
            const fd = new FormData();
            fd.append('photo_profil', file);
            fd.append('nom',    <?= json_encode($profileUser['nom']    ?? '') ?>);
            fd.append('prenom', <?= json_encode($profileUser['prenom'] ?? '') ?>);
            fd.append('bio',    <?= json_encode($profileUser['bio']    ?? '') ?>);
            fd.append('_csrf_token', document.querySelector('#profile-edit-form input[name="_csrf_token"]')?.value || '');

            try {
                const result = await API.post('/api/communaute/profil/update', fd);
                // Remplacer le blob par l'URL serveur définitive
                if (result && result.photo_url) {
                    if (avatarImg)    avatarImg.src    = result.photo_url;
                    if (navbarAvatar) navbarAvatar.src = result.photo_url;
                }
                if (typeof App !== 'undefined') App.toast.success('Photo de profil mise à jour');
                else window.toast('Photo de profil mise à jour', 'success');
            } catch (err) {
                if (typeof App !== 'undefined') App.toast.error(err.message || 'Erreur lors de l\'upload');
                else alert(err.message || 'Erreur lors de l\'upload');
                // Rétablir l'ancienne photo
                if (avatarImg)    avatarImg.src    = fallbackSrc;
                if (navbarAvatar) navbarAvatar.src = fallbackSrc;
            }

            // Synchroniser avec le formulaire d'édition
            if (profileFormPhoto) {
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                profileFormPhoto.files = dataTransfer.files;
            }
        });
    }

    // ── Formulaire édition profil (nom/prénom/bio) AJAX ──────
    const form = document.getElementById('profile-edit-form');
    form?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const submitBtn = form.querySelector('[type="submit"]');
        if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Enregistrement…'; }

        const fd = new FormData(form);
        try {
            const result = await API.post('/api/communaute/profil/update', fd);
            if (typeof App !== 'undefined') App.toast.success('Profil mis à jour avec succès');
            else window.toast('Profil mis à jour avec succès', 'success');
            document.getElementById('edit-profile-section')?.classList.add('hidden');
            // Mettre à jour le nom affiché sans recharger
            const newPrenom = form.querySelector('[name="prenom"]')?.value.trim();
            const newNom    = form.querySelector('[name="nom"]')?.value.trim();
            if (newPrenom && newNom) {
                document.querySelector('.profile-hero__name')?.childNodes.forEach(node => {
                    if (node.nodeType === Node.TEXT_NODE) node.textContent = newPrenom + ' ' + newNom;
                });
            }
            // Mettre à jour l'avatar si une nouvelle photo a été envoyée via le formulaire
            if (result && result.photo_url) {
                if (avatarImg)    avatarImg.src    = result.photo_url;
                if (navbarAvatar) navbarAvatar.src = result.photo_url;
            }
        } catch (err) {
            if (typeof App !== 'undefined') App.toast.error(err.message || 'Erreur lors de la mise à jour du profil');
            else alert(err.message || 'Erreur lors de la mise à jour du profil.');
        } finally {
            if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Enregistrer'; }
        }
    });
});
</script>
