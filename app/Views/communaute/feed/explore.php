<?php
$unreadNotifs = (int) ($unreadNotifs ?? 0);
$userId       = \Core\Session::userId();
$userRole     = \Core\Session::userRole() ?? 'eleve';
?>

<div class="feed-layout">

  <!-- ══ SIDEBAR GAUCHE ═══════════════════════════════════════ -->
  <aside class="sidebar--left">
    <nav class="sidebar__nav">
      <a href="<?= url('/communaute') ?>" class="sidebar__nav-item">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
        </svg>
        Mon fil
      </a>
      <a href="<?= url('/communaute/explorer') ?>" class="sidebar__nav-item sidebar__nav-item--active">
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

  <!-- ══ EXPLORE CENTRAL ═════════════════════════════════════ -->
  <main class="feed-main">

    <!-- Barre de recherche -->
    <div style="background:#fff;border-radius:16px;padding:16px 20px;box-shadow:0 2px 12px rgba(0,0,0,.06);margin-bottom:20px;border:1px solid var(--color-gray-200)">
      <div style="display:flex;gap:10px;align-items:center">
        <div style="flex:1;position:relative">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
               style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--color-gray-500)">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
          </svg>
          <input type="text" id="explore-search"
                 placeholder="Rechercher des posts, des questions, des utilisateurs…"
                 style="width:100%;padding:10px 12px 10px 36px;border:1px solid var(--color-gray-200);border-radius:10px;font-size:14px;font-family:inherit;outline:none;transition:border-color .2s"
                 onfocus="this.style.borderColor='var(--color-primary)'"
                 onblur="this.style.borderColor='var(--color-gray-200)'">
        </div>
        <button onclick="ExploreSearch.run()" style="padding:10px 20px;background:var(--color-primary);color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;white-space:nowrap;transition:background .2s"
                onmouseover="this.style.background='#7540E0'" onmouseout="this.style.background='var(--color-primary)'">
          Rechercher
        </button>
      </div>
    </div>

    <!-- Filtres explore -->
    <div class="feed-filters" style="margin-bottom:20px">
      <button class="feed-filters__btn feed-filters__btn--active feed__filter" data-filter="" id="explore-filter-all">
        Tout
      </button>
      <button class="feed-filters__btn feed__filter" data-filter="question" id="explore-filter-question">
        Questions
      </button>
      <button class="feed-filters__btn feed__filter" data-filter="ressource" id="explore-filter-resource">
        Ressources
      </button>
      <button class="feed-filters__btn feed__filter" data-filter="partage" id="explore-filter-discussion">
        Discussions
      </button>
    </div>

    <!-- Nouveaux posts banner -->
    <div id="new-posts-banner" class="new-posts-banner hidden" role="alert">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 5 5 12"/></svg>
      Nouveaux posts
    </div>

    <!-- Feed container -->
    <div id="feed-container" aria-live="polite"></div>

    <!-- Loading -->
    <div id="feed-loading" class="feed-loading" style="display:none">
      <div class="feed-loading__spinner"></div>
    </div>

    <div id="feed-sentinel" style="height:1px"></div>

  </main>

  <!-- ══ SIDEBAR DROITE ════════════════════════════════════ -->
  <aside style="min-width:0">

    <!-- Recherche utilisateurs -->
    <div style="background:var(--glass-bg);border:var(--glass-border);border-radius:var(--radius-lg);padding:var(--space-5);box-shadow:var(--glass-shadow);margin-bottom:var(--space-4)">
      <div style="font-size:var(--font-size-base);font-weight:600;color:var(--color-dark);margin-bottom:var(--space-4)">Chercher un profil</div>
      <div style="position:relative">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--color-gray-500)">
          <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <input type="text" id="user-search-input"
               placeholder="Nom, prénom…"
               style="width:100%;padding:8px 10px 8px 30px;border:1px solid var(--color-gray-200);border-radius:10px;font-size:13px;font-family:inherit;outline:none"
               onfocus="this.style.borderColor='var(--color-primary)'"
               onblur="this.style.borderColor='var(--color-gray-200)'">
      </div>
      <div id="user-search-results" style="margin-top:10px"></div>
    </div>

  </aside>

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
      <button class="comments-panel__reply-cancel" id="reply-cancel-btn" type="button" aria-label="Annuler">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <div class="comments-panel__input-row">
      <img src="<?= e(\Core\Session::get('user_photo') ? url(\Core\Session::get('user_photo')) : asset('images/default-avatar.svg')) ?>"
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

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Init feed avec mode explore (tous les posts, pas seulement le réseau)
    if (typeof Feed !== 'undefined') {
        Feed.init({ explore: true });
    }
    if (typeof Notifications !== 'undefined') Notifications.init(<?= $unreadNotifs ?>);

    // Recherche live utilisateurs
    const userInput = document.getElementById('user-search-input');
    const userResults = document.getElementById('user-search-results');
    let searchTimer = null;

    userInput?.addEventListener('input', function () {
        clearTimeout(searchTimer);
        const q = this.value.trim();
        if (q.length < 2) { userResults.innerHTML = ''; return; }
        searchTimer = setTimeout(async () => {
            try {
                const data = await API.get('/api/users/search?q=' + encodeURIComponent(q));
                const users = data.data?.users || [];
                if (!users.length) { userResults.innerHTML = '<p style="font-size:13px;color:var(--color-gray-500)">Aucun résultat</p>'; return; }
                userResults.innerHTML = users.map(u => `
                    <a href="${window.CA.baseUrl}/communaute/profil/${u.id}" style="display:flex;align-items:center;gap:8px;padding:6px 0;text-decoration:none">
                        <img src="${u.photo_profil ? window.CA.baseUrl + u.photo_profil : window.CA.baseUrl + '/public/assets/images/default-avatar.svg'}"
                             style="width:32px;height:32px;border-radius:50%;object-fit:cover" onerror="this.onerror=null;this.src=window.CA.baseUrl+'/public/assets/images/default-avatar.svg'">
                        <div>
                            <div style="font-size:13px;font-weight:600;color:var(--color-dark)">${esc(u.prenom + ' ' + u.nom)}</div>
                            <div style="font-size:11px;color:var(--color-gray-500)">${esc(u.role)}</div>
                        </div>
                    </a>
                `).join('');
            } catch (e) {}
        }, 300);
    });

    // ExploreSearch pour la barre principale
    window.ExploreSearch = {
        async run() {
            const q = document.getElementById('explore-search')?.value.trim();
            if (!q) return;
            try {
                const data = await API.get('/api/communaute/posts?q=' + encodeURIComponent(q) + '&limit=20');
                const posts = data.data?.posts || [];
                if (typeof Feed !== 'undefined') Feed.loadSearchResults(posts, q);
            } catch (e) {
                console.error(e);
            }
        }
    };

    document.getElementById('explore-search')?.addEventListener('keydown', e => {
        if (e.key === 'Enter') ExploreSearch.run();
    });

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = String(s || '');
        return d.innerHTML;
    }
});
</script>
