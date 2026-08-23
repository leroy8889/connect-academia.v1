<?php
// Variables: $users (array), $counts (array), $page, $totalPages, $filters, $series (array)
$users      = $users ?? [];
$counts     = $counts ?? ['total'=>0,'eleve'=>0,'gestionnaire'=>0,'admin'=>0,'visiteur'=>0,'actifs'=>0,'suspendus'=>0];
$page       = (int)($page ?? 1);
$totalPages = (int)($totalPages ?? 1);
$total      = (int)($total ?? 0);
$filters    = $filters ?? [];
$series     = $series ?? [];
$isReadOnly = ($_SESSION['admin_role'] ?? '') === 'moderateur_1';

// Active tab: si un filtre status=* est présent → onglet "suspended"
if (!empty($filters['status'])) {
    $activeTab = 'suspended';
} else {
    $activeTab = !empty($filters['role']) ? $filters['role'] : 'all';
}

if (!function_exists('humanTimeDiff')) {
    // Accepts Unix timestamp (int) — no timezone ambiguity
    function humanTimeDiff(int $ts): string {
        $diff = time() - $ts;
        if ($diff < 0)      return 'à l\'instant';
        if ($diff < 60)     return $diff . 's';
        if ($diff < 3600)   return floor($diff/60) . 'min';
        if ($diff < 86400)  return floor($diff/3600) . 'h';
        if ($diff < 604800) return floor($diff/86400) . 'j';
        return floor($diff/604800) . 'sem';
    }
}

if (!function_exists('isOnline')) {
    function isOnline(?int $lastActivityTs, int $threshold = 300): bool {
        return $lastActivityTs !== null && $lastActivityTs > 0 && (time() - $lastActivityTs) < $threshold;
    }
}

/**
 * Construit une URL pour la pagination/tabs en préservant tous les filtres actifs.
 * $tabKey = null   → préserve role/status courant
 * $tabKey = 'all'  → supprime role et status
 * $tabKey = string → applique le filtre correspondant
 */
if (!function_exists('pgUrl')) {
    function pgUrl(array $filters, int $page, ?string $tabKey = null): string {
        $params = [];
        // Paramètre de recherche
        if (!empty($filters['q'])) $params['q'] = $filters['q'];

        if ($tabKey !== null) {
            // On construit l'URL pour un onglet → reset page à 1
            if ($tabKey === 'suspended') {
                $params['status'] = 'suspended';
            } elseif ($tabKey !== '' && $tabKey !== 'all') {
                $params['role'] = $tabKey;
            }
            // tabKey='all' → pas de role/status
        } else {
            // Préserver role/status courant
            if (!empty($filters['role'])) $params['role'] = $filters['role'];
            if (!empty($filters['status'])) $params['status'] = $filters['status'];
            if ($page > 1) $params['page'] = $page;
        }

        $qs = $params ? '?' . http_build_query($params) : '';
        return url('/admin/utilisateurs') . $qs;
    }
}
?>

<!-- Header -->
<div class="admin-page-header-row">
  <div>
    <h1>Gestion des utilisateurs</h1>
    <p>
      <?= number_format($counts['total']) ?> utilisateurs au total —
      <?= number_format($counts['actifs'] ?? 0) ?> actifs,
      <?= number_format($counts['suspendus']) ?> suspendus
    </p>
  </div>
  <div style="display:flex;gap:10px;align-items:center;">
    <a href="<?= url('/admin/utilisateurs?export=csv') ?>" class="btn-outline">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      Exporter CSV
    </a>
    <?php if (!$isReadOnly): ?>
    <button class="btn-primary" data-modal-open="modal-create-user">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Nouvel utilisateur
    </button>
    <?php endif; ?>
  </div>
</div>

<!-- Tabs -->
<div class="admin-tabs" style="border-radius:var(--r);margin-bottom:14px;">
  <?php
  $tabs = [
    'all'        => ['label' => 'Tous',        'count' => $counts['total']],
    'eleve'        => ['label' => 'Élèves',        'count' => $counts['eleve'] ?? 0],
    'gestionnaire' => ['label' => 'Gestionnaires', 'count' => $counts['gestionnaire'] ?? 0],
    'suspended'    => ['label' => 'Suspendus',     'count' => $counts['suspendus']],
  ];
  foreach ($tabs as $key => $tab):
    $href     = pgUrl($filters, 1, $key);
    $isActive = ($activeTab === $key || ($key === 'all' && $activeTab === ''));
  ?>
  <a href="<?= $href ?>" class="admin-tab <?= $isActive ? 'active' : '' ?>">
    <?= $tab['label'] ?>
    <span class="tab-count"><?= number_format($tab['count']) ?></span>
  </a>
  <?php endforeach; ?>
</div>

<?php
// ── Colonnes kanban utilisateurs — groupées par rôle (comme la partie
//    Orientation groupe les établissements par statut). Le statut du compte
//    (Actif / Suspendu) reste porté par le badge + la barre d'accent rouge :
//    un toggle ne déplace donc jamais la carte de colonne.
$roleCols = [
    'eleve'        => ['label' => 'Élèves',        'dot' => 'var(--ap)',           'accent' => '#8B52FA', 'items' => []],
    'gestionnaire' => ['label' => 'Gestionnaires', 'dot' => 'var(--blue,#2563EB)', 'accent' => '#2563EB', 'items' => []],
];
$autresRoles = [];
foreach ($users as $u) {
    if (isset($roleCols[$u['role']])) {
        $roleCols[$u['role']]['items'][] = $u;
    } else {
        $autresRoles[] = $u;
    }
}
if (!empty($autresRoles)) {
    $roleCols['autre'] = ['label' => 'Autres rôles', 'dot' => 'var(--txt-l)', 'accent' => '#64748B', 'items' => $autresRoles];
}
$nbCols = count($roleCols);
?>

<!-- Recherche -->
<div class="uk-toolbar">
  <input type="text" id="users-search-input" class="uk-search"
         placeholder="Rechercher par nom, email… (Entrée pour chercher)"
         value="<?= e($filters['q'] ?? '') ?>"
         data-base-url="<?= e(pgUrl(array_merge($filters, ['q' => '']), 1)) ?>">
</div>

<!-- Board kanban utilisateurs -->
<?php if (empty($users)): ?>
<div class="uk-glass" style="padding:48px 20px;text-align:center;color:var(--txt-m);font-size:14px;margin-bottom:20px;">
  Aucun utilisateur trouvé
</div>
<?php else: ?>
<div class="uk-board cols-<?= $nbCols ?>" id="users-board">
<?php foreach ($roleCols as $roleKey => $col): ?>
  <section class="uk-col" aria-label="<?= e($col['label']) ?>">
    <div class="uk-col-head">
      <span class="uk-col-dot" style="background:<?= $col['dot'] ?>;"></span>
      <?= e($col['label']) ?>
      <span class="uk-col-count"><?= count($col['items']) ?></span>
    </div>
    <div class="uk-col-body">
    <?php if (empty($col['items'])): ?>
      <div class="uk-col-empty">Aucun utilisateur dans cette colonne.</div>
    <?php endif; ?>
    <?php foreach ($col['items'] as $u): ?>
    <?php
      $accent = $u['is_active'] ? $col['accent'] : 'var(--red,#EF4444)';
      $online = isOnline(!empty($u['last_activity_ts']) ? (int)$u['last_activity_ts'] : null);
      $abStatut = $u['ab_statut'] ?? null;
      $abFin    = $u['ab_fin']    ?? null;
      $abPlan   = $u['ab_plan']   ?? null;
      $telFmt   = \Core\Phone::formatGabon($u['telephone'] ?? null);
      $etabsInscrit = trim((string)($u['etabs_inscrit'] ?? ''));
    ?>
      <article class="kb-card uk-glass<?= $u['is_active'] ? '' : ' is-suspended' ?>"
               style="--kb-accent:<?= $accent ?>;"
               data-accent="<?= $col['accent'] ?>"
               data-user-id="<?= $u['id'] ?>"
               data-search="<?= e(strtolower($u['prenom'] . ' ' . $u['nom'] . ' ' . $u['email'] . ' ' . $u['role'] . ' ' . ($u['serie'] ?? '') . ' ' . ($u['telephone'] ?? '') . ' ' . $etabsInscrit)) ?>">

        <div class="kb-head">
          <div class="user-avatar" style="background:<?= $u['is_active'] ? 'var(--ap)' : 'var(--txt-l)' ?>;">
            <?php if (!empty($u['photo_profil_url'])): ?>
              <img src="<?= e($u['photo_profil_url']) ?>" alt="">
            <?php else: ?>
              <?= strtoupper(mb_substr($u['prenom'], 0, 1) . mb_substr($u['nom'], 0, 1)) ?>
            <?php endif; ?>
          </div>
          <div class="kb-id">
            <h3 class="kb-name"><?= e($u['prenom'] . ' ' . $u['nom']) ?></h3>
            <div class="kb-mail"><?= e($u['email']) ?></div>
          </div>
          <span class="presence-badge <?= $online ? 'presence-online' : 'presence-offline' ?>"
                data-uid="<?= $u['id'] ?>">
            <span class="presence-dot"></span>
            <?= $online ? 'En ligne' : 'Déconnecté' ?>
          </span>
        </div>

        <div class="kb-badges">
          <span class="badge badge-<?= e($u['role']) ?>"><?= e(ucfirst($u['role'])) ?></span>
          <span class="badge <?= $u['is_active'] ? 'badge-actif' : 'badge-suspendu' ?> status-badge">
            <?= $u['is_active'] ? 'Actif' : 'Suspendu' ?>
          </span>
          <?php if ($abStatut === 'actif' && $abFin): ?>
            <span class="kb-chip kb-chip-ab" title="Expire le <?= date('d/m/Y', strtotime($abFin)) ?>">
              Abo · <?= e(ucfirst((string)$abPlan)) ?> — exp. <?= date('d/m/Y', strtotime($abFin)) ?>
            </span>
          <?php else: ?>
            <span class="kb-chip kb-chip-mute">Aucun abonnement</span>
          <?php endif; ?>
          <?php if ($etabsInscrit !== ''): ?>
            <span class="kb-chip kb-chip-etab" title="Inscription validée par l'établissement">
              🎓 Élève · <?= e($etabsInscrit) ?>
            </span>
          <?php endif; ?>
        </div>

        <div class="kb-meta">
          <span title="Téléphone">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            Tél : <?= $telFmt !== '' ? e($telFmt) : '—' ?>
          </span>
          <span title="Série">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10 12 5 2 10l10 5 10-5z"/><path d="M6 12v5c0 1 2.7 2.5 6 2.5s6-1.5 6-2.5v-5"/></svg>
            Série : <?= e($u['serie'] ?? '—') ?>
          </span>
          <span title="Date d'inscription">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            Inscrit le <?= date('d M Y', strtotime($u['created_at'])) ?>
          </span>
          <span title="Dernier login">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <?php if (!empty($u['last_login_ts']) && (int)$u['last_login_ts'] > 0): ?>
              <span title="<?= date('d/m/Y à H:i:s', (int)$u['last_login_ts']) ?>">Login il y a <?= humanTimeDiff((int)$u['last_login_ts']) ?></span>
            <?php else: ?>
              Jamais connecté
            <?php endif; ?>
          </span>
        </div>

        <?php if (!$isReadOnly): ?>
        <div class="kb-foot">
          <label class="toggle-switch" title="<?= $u['is_active'] ? 'Suspendre' : 'Activer' ?>">
            <input type="checkbox" class="user-toggle"
                   data-user-id="<?= $u['id'] ?>"
                   <?= $u['is_active'] ? 'checked' : '' ?>>
            <span class="toggle-slider"></span>
          </label>
          <div class="kb-actions">
            <?php if ($etabsInscrit !== ''): ?>
            <button class="kb-act btn-commission-user" title="Scolarité & commission 10 %"
                    data-user-id="<?= $u['id'] ?>"
                    data-name="<?= e($u['prenom'] . ' ' . $u['nom']) ?>">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/></svg>
            </button>
            <?php endif; ?>
            <button class="kb-act btn-message-user" title="Envoyer un message"
                    data-user-id="<?= $u['id'] ?>"
                    data-name="<?= e($u['prenom'] . ' ' . $u['nom']) ?>">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            </button>
            <button class="kb-act btn-edit-user" title="Modifier"
                    data-user-id="<?= $u['id'] ?>"
                    data-prenom="<?= e($u['prenom']) ?>"
                    data-nom="<?= e($u['nom']) ?>"
                    data-email="<?= e($u['email']) ?>"
                    data-telephone="<?= e($u['telephone'] ?? '') ?>"
                    data-role="<?= e($u['role']) ?>"
                    data-serie-id="<?= (int)($u['serie_id'] ?? 0) ?>">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button class="kb-act kb-act-danger btn-delete-user" title="Supprimer"
                    data-user-id="<?= $u['id'] ?>"
                    data-name="<?= e($u['prenom'] . ' ' . $u['nom']) ?>">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
            </button>
          </div>
        </div>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Pagination — toujours affichée, boutons de page uniquement si > 1 page -->
<div class="uk-glass uk-pagecard">
  <?php
    $perPage  = 20;
    $firstRow = $total > 0 ? ($page - 1) * $perPage + 1 : 0;
    $lastRow  = min($page * $perPage, $total);
  ?>
  <div class="admin-pagination">
    <p>
      <?php if ($total === 0): ?>
        Aucun utilisateur trouvé
      <?php elseif ($totalPages === 1): ?>
        <strong><?= number_format($total) ?></strong> utilisateur<?= $total > 1 ? 's' : '' ?>
      <?php else: ?>
        Affichage <strong><?= number_format($firstRow) ?>–<?= number_format($lastRow) ?></strong>
        sur <strong><?= number_format($total) ?></strong> utilisateur<?= $total > 1 ? 's' : '' ?>
        &nbsp;·&nbsp; Page <?= $page ?> / <?= $totalPages ?>
      <?php endif; ?>
    </p>

    <?php if ($totalPages > 1): ?>
    <div class="pagination-btns">

      <?php if ($page > 1): ?>
        <a href="<?= pgUrl($filters, $page - 1) ?>"
           class="pagination-btn pagination-prev" title="Page précédente">← Préc.</a>
      <?php else: ?>
        <span class="pagination-btn pagination-prev" style="opacity:.35;cursor:default;">← Préc.</span>
      <?php endif; ?>

      <?php
        $window  = 2;
        $pages   = [1];
        for ($p = max(2, $page - $window); $p <= min($totalPages - 1, $page + $window); $p++) {
            $pages[] = $p;
        }
        $pages[] = $totalPages;
        $pages = array_unique($pages);
        sort($pages);

        $prev = null;
        foreach ($pages as $p):
          if ($prev !== null && $p - $prev > 1): ?>
            <span class="pagination-btn" style="border:none;cursor:default;color:var(--txt-l);">…</span>
          <?php endif; ?>
          <a href="<?= pgUrl($filters, $p) ?>"
             class="pagination-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
        <?php $prev = $p; endforeach; ?>

      <?php if ($page < $totalPages): ?>
        <a href="<?= pgUrl($filters, $page + 1) ?>"
           class="pagination-btn pagination-next" title="Page suivante">Suiv. →</a>
      <?php else: ?>
        <span class="pagination-btn pagination-next" style="opacity:.35;cursor:default;">Suiv. →</span>
      <?php endif; ?>

    </div>
    <?php endif; ?>
  </div>
</div>

<style>
/* ══════════════════════════════════════════════════════════════════
   Utilisateurs admin — board kanban (même langage que Orientation)
   ══════════════════════════════════════════════════════════════════ */
.uk-glass {
  background: rgba(255,255,255,.62);
  -webkit-backdrop-filter: blur(16px) saturate(170%);
  backdrop-filter: blur(16px) saturate(170%);
  border: 1px solid rgba(255,255,255,.55);
  border-radius: 16px;
  box-shadow: 0 8px 32px rgba(26,26,26,.07), 0 1px 2px rgba(26,26,26,.04),
              inset 0 1px 0 rgba(255,255,255,.72);
}
@supports not (backdrop-filter: blur(1px)) {
  .uk-glass { background: rgba(255,255,255,.92); }
}

.uk-toolbar { display: flex; justify-content: flex-end; margin-bottom: 16px; }
.uk-search {
  padding: 10px 16px 10px 38px; min-width: 320px;
  font-family: inherit; font-size: 13px; font-weight: 500; color: var(--txt);
  background: rgba(255,255,255,.7) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='%239E9E9E' stroke-width='2'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='M21 21l-4.35-4.35'/%3E%3C/svg%3E") no-repeat 13px center;
  border: 1px solid var(--border-strong, #E0E0E6); border-radius: 999px;
  outline: none; transition: border-color .16s, box-shadow .16s;
  -webkit-backdrop-filter: blur(10px); backdrop-filter: blur(10px);
}
.uk-search:focus { border-color: var(--ap); box-shadow: 0 0 0 3px rgba(139,82,250,.2); }

.uk-board { display: grid; gap: 18px; align-items: start; margin-bottom: 20px; }
.uk-board.cols-2 { grid-template-columns: repeat(2,1fr); }
.uk-board.cols-3 { grid-template-columns: repeat(3,1fr); }
.uk-col-head {
  display: flex; align-items: center; gap: 9px;
  padding: 6px 4px 12px; font-size: 11.5px; font-weight: 700;
  letter-spacing: .12em; text-transform: uppercase; color: var(--txt-m);
}
.uk-col-dot   { width: 8px; height: 8px; border-radius: 999px; flex: none; }
.uk-col-count { margin-left: auto; font-size: 12px; font-weight: 700; color: var(--txt-l); letter-spacing: 0; }
.uk-col-body  { display: flex; flex-direction: column; gap: 12px; min-height: 40px; }
.uk-col-empty {
  font-size: 12px; color: var(--txt-l); text-align: center;
  padding: 22px 12px; border: 1.5px dashed var(--border-strong, #E0E0E6); border-radius: 16px;
}

/* ── Carte utilisateur ────────────────────────────────────────────── */
.kb-card {
  position: relative; overflow: hidden;
  border-radius: 16px; padding: 16px 18px 14px 21px;
  display: flex; flex-direction: column; gap: 11px;
  transition: transform .22s cubic-bezier(.2,.8,.2,1), box-shadow .22s cubic-bezier(.2,.8,.2,1);
}
.kb-card::before {
  content: ''; position: absolute; top: 0; left: 0; bottom: 0; width: 4px;
  background: var(--kb-accent, var(--ap)); border-radius: 4px 0 0 4px;
}
.kb-card:hover { transform: translateY(-2px); box-shadow: 0 14px 40px rgba(26,26,26,.11), inset 0 1px 0 rgba(255,255,255,.72); }
.kb-card.is-suspended { opacity: .82; }

.kb-head { display: flex; align-items: center; gap: 10px; min-width: 0; }
.kb-head .user-avatar { flex: none; }
.kb-id   { flex: 1; min-width: 0; }
.kb-name {
  font-size: 14.5px; font-weight: 700; color: var(--txt);
  letter-spacing: -.01em; line-height: 1.3; margin: 0;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.kb-mail { font-size: 12px; color: var(--txt-m); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.kb-head .presence-badge { flex: none; }

.kb-badges { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.kb-chip {
  font-size: 10.5px; font-weight: 700; padding: 3px 9px;
  border-radius: 999px; letter-spacing: .02em;
}
.kb-chip-ab   { background: rgba(16,185,129,.12); color: #059669; }
.kb-chip-mute { background: rgba(26,26,26,.05); color: var(--txt-m); }
.kb-chip-etab { background: rgba(139,82,250,.12); color: #7440D9; }

/* ── Modal commission scolarité ───────────────────────────────────── */
.cm-block {
  border: 1px solid var(--border-strong, #E0E0E6);
  border-radius: 12px;
  padding: 14px 16px;
  margin-bottom: 12px;
}
.cm-block-head {
  display: flex; align-items: baseline; justify-content: space-between;
  gap: 10px; flex-wrap: wrap; margin-bottom: 10px;
}
.cm-etab { font-size: 13.5px; font-weight: 700; color: var(--txt); }
.cm-sub  { font-size: 11.5px; color: var(--txt-m); }
.cm-amounts {
  display: flex; gap: 18px; flex-wrap: wrap;
  font-size: 12.5px; color: var(--txt-m); margin-bottom: 12px;
}
.cm-amounts strong { color: var(--txt); font-variant-numeric: tabular-nums; }
.cm-tranche {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 10px; border-radius: 9px;
  border: 1px solid var(--border, #EBEBF5); margin-bottom: 6px;
  font-size: 12.5px;
}
.cm-tranche.is-paid { background: rgba(16,185,129,.07); border-color: rgba(16,185,129,.3); }
.cm-tranche input[type="checkbox"] { width: 16px; height: 16px; accent-color: #059669; cursor: pointer; flex: none; }
.cm-tranche input[type="checkbox"]:disabled { cursor: not-allowed; }
.cm-tranche-label { flex: 1; min-width: 0; font-weight: 600; color: var(--txt); }
.cm-tranche-montant { font-weight: 700; font-variant-numeric: tabular-nums; color: var(--txt); }
.cm-tranche-date { font-size: 11px; color: #059669; white-space: nowrap; }
.cm-create-row { display: flex; gap: 8px; align-items: center; }
.cm-create-row input { flex: 1; }
.cm-solde-badge {
  font-size: 10.5px; font-weight: 700; padding: 3px 9px; border-radius: 999px;
  background: rgba(16,185,129,.12); color: #059669;
}
.cm-encours-badge {
  font-size: 10.5px; font-weight: 700; padding: 3px 9px; border-radius: 999px;
  background: rgba(245,158,11,.13); color: #B45309;
}
.cm-empty { font-size: 12.5px; color: var(--txt-m); text-align: center; padding: 18px 8px; }

.kb-meta { display: flex; flex-direction: column; gap: 5px; font-size: 12px; color: var(--txt-m); }
.kb-meta span { display: inline-flex; align-items: center; gap: 6px; min-width: 0; }
.kb-meta svg  { width: 13px; height: 13px; color: var(--txt-l); flex: none; }

.kb-foot {
  display: flex; align-items: center; gap: 8px;
  padding-top: 10px; border-top: 1px solid var(--border);
}
.kb-actions { margin-left: auto; display: flex; align-items: center; gap: 2px; }
.kb-act {
  display: inline-flex; align-items: center; justify-content: center;
  width: 28px; height: 28px; border-radius: 9px; border: none; cursor: pointer;
  background: transparent; color: var(--txt-m);
  transition: background .14s, color .14s;
}
.kb-act:hover { background: var(--ap-lt); color: var(--ap); }
.kb-act-danger:hover { background: var(--red-bg, #FEF2F2); color: var(--red, #EF4444); }
.kb-act svg { width: 14px; height: 14px; }

.uk-pagecard { padding: 0 6px; }
.uk-pagecard .admin-pagination { border-top: none; }

@media (max-width: 1100px) {
  .uk-board.cols-2, .uk-board.cols-3 { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
  .uk-toolbar { justify-content: stretch; }
  .uk-search { min-width: 0; width: 100%; }
}

.presence-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 11px;
  font-weight: 500;
  padding: 3px 8px;
  border-radius: 20px;
  white-space: nowrap;
}
.presence-badge .presence-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  flex-shrink: 0;
}
.presence-online {
  background: rgba(34,197,94,.12);
  color: #22c55e;
  border: 1px solid rgba(34,197,94,.25);
}
.presence-online .presence-dot {
  background: #22c55e;
  box-shadow: 0 0 0 2px rgba(34,197,94,.25);
  animation: pulse-green 2s infinite;
}
.presence-offline {
  background: rgba(148,163,184,.08);
  color: var(--txt-l, #94a3b8);
  border: 1px solid rgba(148,163,184,.18);
}
.presence-offline .presence-dot {
  background: #94a3b8;
}
@keyframes pulse-green {
  0%, 100% { box-shadow: 0 0 0 2px rgba(34,197,94,.25); }
  50%       { box-shadow: 0 0 0 4px rgba(34,197,94,.08); }
}
</style>

<script>
(function () {
  // ── Polling statuts présence (toutes les 30s) ──────────────────────
  const POLL_MS = 30000;
  const STATUTS_URL = '<?= e(url('/admin/api/utilisateurs/statuts')) ?>';

  function refreshPresence() {
    fetch(STATUTS_URL, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(r => r.ok ? r.json() : null)
      .then(data => {
        if (!data || !data.success) return;
        Object.entries(data.statuts).forEach(([uid, s]) => {
          const badge = document.querySelector(`.presence-badge[data-uid="${uid}"]`);
          if (!badge) return;
          const online = s.is_online;
          badge.className = 'presence-badge ' + (online ? 'presence-online' : 'presence-offline');
          // Update text node (last child after the dot span)
          const dot = badge.querySelector('.presence-dot');
          badge.innerHTML = '';
          badge.appendChild(dot);
          badge.appendChild(document.createTextNode(online ? ' En ligne' : ' Déconnecté'));
        });
      })
      .catch(() => {});
  }

  setInterval(refreshPresence, POLL_MS);

  // ── Recherche serveur depuis le champ de recherche ─────────────────
  const input = document.getElementById('users-search-input');
  if (!input) return;

  let debounce;
  const baseUrl = input.dataset.baseUrl || '<?= e(url('/admin/utilisateurs')) ?>';

  function serverSearch(q) {
    const url = new URL(baseUrl, window.location.origin);
    const params = new URLSearchParams(url.search);
    params.delete('page'); // reset page
    if (q.trim()) {
      params.set('q', q.trim());
    } else {
      params.delete('q');
    }
    window.location.href = url.pathname + (params.toString() ? '?' + params.toString() : '');
  }

  // Entrée → recherche immédiate côté serveur
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      clearTimeout(debounce);
      serverSearch(input.value);
    }
  });

  // Debounce 700ms → recherche serveur automatique (seulement si ≥2 chars ou vide)
  input.addEventListener('input', (e) => {
    clearTimeout(debounce);
    const q = e.target.value;
    // Filtre client-side immédiat sur les cartes de la page courante
    filterUserCards(q);
    // Déclenchement serveur en différé
    if (q.length === 0 || q.length >= 2) {
      debounce = setTimeout(() => serverSearch(q), 700);
    }
  });

  function filterUserCards(q) {
    q = q.toLowerCase();
    const board = document.getElementById('users-board');
    if (!board) return;
    board.querySelectorAll('.kb-card').forEach(card => {
      card.style.display = (card.dataset.search || '').includes(q) ? '' : 'none';
    });
    // Compteurs de colonnes mis à jour selon les cartes visibles
    board.querySelectorAll('.uk-col').forEach(col => {
      const visible = col.querySelectorAll('.kb-card:not([style*="display: none"])').length;
      const count = col.querySelector('.uk-col-count');
      if (count) count.textContent = visible;
      const empty = col.querySelector('.uk-col-empty');
      if (empty) empty.style.display = visible === 0 ? '' : 'none';
    });
  }
})();
</script>

<!-- ── Modal Créer utilisateur ─────────────────────────────── -->
<div class="admin-modal-overlay" id="modal-create-user">
  <div class="admin-modal">
    <div class="admin-modal-header">
      <h2>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--ap)" stroke-width="2">
          <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
          <circle cx="8.5" cy="7" r="4"/>
          <line x1="20" y1="8" x2="20" y2="14"/>
          <line x1="23" y1="11" x2="17" y2="11"/>
        </svg>
        Créer un utilisateur
      </h2>
      <button class="modal-close" data-modal-close="modal-create-user">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="admin-modal-body">
      <form id="form-create-user">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div class="form-group">
            <label class="form-label">Prénom *</label>
            <input type="text" name="prenom" class="form-input" placeholder="Ex: Emma" required>
          </div>
          <div class="form-group">
            <label class="form-label">Nom *</label>
            <input type="text" name="nom" class="form-input" placeholder="Ex: Leroy" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Email *</label>
          <input type="email" name="email" class="form-input" placeholder="Ex: emma@connect-academia.ga" required>
        </div>
        <div class="form-group">
          <label class="form-label">Téléphone (Gabon)</label>
          <input type="tel" name="telephone" class="form-input" placeholder="Ex: 074 12 34 56" inputmode="numeric" maxlength="20">
          <span style="font-size:11px;color:var(--txt-m);margin-top:4px;display:block;">Numéro gabonais — commence par 01, 06 ou 07.</span>
        </div>
        <div class="form-group">
          <label class="form-label">Mot de passe temporaire *</label>
          <input type="password" name="password" class="form-input" placeholder="Min. 8 caractères" minlength="8" required>
          <span style="font-size:11px;color:var(--txt-m);margin-top:4px;display:block;">L'utilisateur pourra le modifier après connexion.</span>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div class="form-group">
            <label class="form-label">Rôle *</label>
            <select name="role" class="form-select" required>
              <option value="eleve">Élève</option>
              <option value="gestionnaire">Gestionnaire</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Série</label>
            <select name="serie_id" class="form-select">
              <option value="">Sélectionner…</option>
              <?php foreach ($series as $s): ?>
              <option value="<?= $s['id'] ?>">Terminale <?= e($s['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </form>
    </div>
    <div class="admin-modal-footer">
      <button type="button" class="btn-ghost" data-modal-close="modal-create-user">Annuler</button>
      <button type="button" class="btn-primary" id="btn-submit-create-user">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
        Créer l'utilisateur
      </button>
    </div>
  </div>
</div>

<!-- ── Modal Envoyer un message ───────────────────────────── -->
<div class="admin-modal-overlay" id="modal-message-user">
  <div class="admin-modal">
    <div class="admin-modal-header">
      <h2>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--ap)" stroke-width="2">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
        </svg>
        Envoyer un message
      </h2>
      <button class="modal-close" data-modal-close="modal-message-user">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="admin-modal-body">
      <form id="form-message-user">
        <input type="hidden" id="message-user-id">
        <p style="font-size:13px;color:var(--txt-m);margin:0 0 12px;">
          Destinataire : <strong id="message-user-name" style="color:var(--txt);"></strong>
        </p>
        <div class="form-group">
          <label class="form-label">Message *</label>
          <textarea name="message" id="message-user-text" class="form-input" rows="5"
                    maxlength="500" required
                    placeholder="Ex : Votre relevé de notes est illisible, merci de le renvoyer depuis votre espace."></textarea>
          <span style="font-size:11px;color:var(--txt-m);margin-top:4px;display:block;">
            <span id="message-char-count">0</span>/500 caractères — arrivera dans les notifications du compte de l'utilisateur.
          </span>
        </div>
      </form>
    </div>
    <div class="admin-modal-footer">
      <button type="button" class="btn-ghost" data-modal-close="modal-message-user">Annuler</button>
      <button type="button" class="btn-primary" id="btn-submit-message-user">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        Envoyer
      </button>
    </div>
  </div>
</div>

<!-- ── Modal Éditer utilisateur ───────────────────────────── -->
<div class="admin-modal-overlay" id="modal-edit-user">
  <div class="admin-modal">
    <div class="admin-modal-header">
      <h2>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--ap)" stroke-width="2">
          <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
          <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
        </svg>
        Modifier l'utilisateur
      </h2>
      <button class="modal-close" data-modal-close="modal-edit-user">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="admin-modal-body">
      <form id="form-edit-user">
        <input type="hidden" name="_edit_user_id" id="edit-user-id">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div class="form-group">
            <label class="form-label">Prénom *</label>
            <input type="text" name="prenom" id="edit-prenom" class="form-input" required>
          </div>
          <div class="form-group">
            <label class="form-label">Nom *</label>
            <input type="text" name="nom" id="edit-nom" class="form-input" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Email *</label>
          <input type="email" name="email" id="edit-email" class="form-input" required>
        </div>
        <div class="form-group">
          <label class="form-label">Téléphone (Gabon)</label>
          <input type="tel" name="telephone" id="edit-telephone" class="form-input" placeholder="Ex: 074 12 34 56" inputmode="numeric" maxlength="20">
          <span style="font-size:11px;color:var(--txt-m);margin-top:4px;display:block;">Numéro gabonais — commence par 01, 06 ou 07.</span>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div class="form-group">
            <label class="form-label">Rôle *</label>
            <select name="role" id="edit-role" class="form-select" required>
              <option value="eleve">Élève</option>
              <option value="gestionnaire">Gestionnaire</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Série</label>
            <select name="serie_id" id="edit-serie-id" class="form-select">
              <option value="">Sélectionner…</option>
              <?php foreach ($series as $s): ?>
              <option value="<?= $s['id'] ?>">Terminale <?= e($s['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </form>
    </div>
    <div class="admin-modal-footer">
      <button type="button" class="btn-ghost" data-modal-close="modal-edit-user">Annuler</button>
      <button type="button" class="btn-primary" id="btn-submit-edit-user">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        Enregistrer
      </button>
    </div>
  </div>
</div>

<!-- ── Modal Scolarité & commission 10 % ──────────────────────── -->
<div class="admin-modal-overlay" id="modal-commission-user">
  <div class="admin-modal">
    <div class="admin-modal-header">
      <h2>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--ap)" stroke-width="2">
          <rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/>
        </svg>
        Scolarité &amp; commission (10 %)
      </h2>
      <button class="modal-close" data-modal-close="modal-commission-user">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="admin-modal-body">
      <p style="font-size:13px;color:var(--txt-m);margin:0 0 12px;">
        Élève : <strong id="commission-user-name" style="color:var(--txt);"></strong>
      </p>
      <p style="font-size:11.5px;color:var(--txt-m);margin:0 0 14px;line-height:1.55;">
        La plateforme perçoit un <strong>pourcentage de la scolarité</strong> de chaque élève apporté à un
        établissement (rien sur l'inscription) — taux défini par établissement dans son formulaire de
        création/modification — recouvré en présentiel en <strong>3 tranches</strong> :
        40 % puis 30 % puis 30 % de la commission. Cochez chaque tranche encaissée —
        l'établissement voit l'état des paiements sur son dashboard.
      </p>
      <div id="commission-body">
        <div class="cm-empty">Chargement…</div>
      </div>
    </div>
    <div class="admin-modal-footer">
      <button type="button" class="btn-ghost" data-modal-close="modal-commission-user">Fermer</button>
    </div>
  </div>
</div>

<script>
(function () {
  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
  const base = () => window.CA_ADMIN?.baseUrl ?? '';
  const fmt  = (n) => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 2 }).format(Number(n)) + ' XAF';
  const esc  = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

  const body = document.getElementById('commission-body');
  let currentUserId = null;

  function openCommissionModal(userId, name) {
    currentUserId = userId;
    document.getElementById('commission-user-name').textContent = name;
    body.innerHTML = '<div class="cm-empty">Chargement…</div>';
    document.getElementById('modal-commission-user').classList.add('open');
    load();
  }

  async function load() {
    try {
      const res  = await fetch(`${base()}/admin/api/utilisateurs/${currentUserId}/commissions`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });
      const data = await res.json();
      if (!data.success) {
        body.innerHTML = `<div class="cm-empty">${esc(data.message || 'Erreur de chargement.')}</div>`;
        return;
      }
      render(data);
    } catch {
      body.innerHTML = '<div class="cm-empty">Erreur réseau.</div>';
    }
  }

  function render(data) {
    const readOnly    = !!data.read_only;
    const commissions = data.commissions || [];
    const sansCommission = (data.dossiers || []).filter(d => !d.commission_id);

    let html = '';

    commissions.forEach(c => {
      const solde = c.statut === 'solde';
      html += `
        <div class="cm-block">
          <div class="cm-block-head">
            <div>
              <div class="cm-etab">${esc(c.etablissement_nom)}</div>
              <div class="cm-sub">${esc(c.filiere_visee || '')} — dossier #${c.dossier_id}</div>
            </div>
            <span class="${solde ? 'cm-solde-badge' : 'cm-encours-badge'}">${solde ? 'Soldée ✓' : 'En cours'}</span>
          </div>
          <div class="cm-amounts">
            <span>Scolarité : <strong>${fmt(c.montant_scolarite)}</strong></span>
            <span>Commission (${Number(c.taux_commission)} %) : <strong>${fmt(c.montant_commission)}</strong></span>
          </div>
          ${(c.tranches || []).map(t => {
            const paid = Number(t.est_payee) === 1;
            return `
            <div class="cm-tranche ${paid ? 'is-paid' : ''}">
              <input type="checkbox" class="cm-toggle" data-tranche-id="${t.id}"
                     ${paid ? 'checked' : ''} ${readOnly ? 'disabled' : ''}>
              <span class="cm-tranche-label">Tranche ${t.numero} — ${Number(t.pourcentage)} % de la commission</span>
              ${paid && t.date_paiement ? `<span class="cm-tranche-date">Encaissée le ${new Date(t.date_paiement.replace(' ', 'T')).toLocaleDateString('fr-FR')}</span>` : ''}
              <span class="cm-tranche-montant">${fmt(t.montant)}</span>
            </div>`;
          }).join('')}
        </div>`;
    });

    sansCommission.forEach(d => {
      const taux = Number(d.taux_commission ?? 10);
      html += `
        <div class="cm-block">
          <div class="cm-block-head">
            <div>
              <div class="cm-etab">${esc(d.etablissement_nom)}</div>
              <div class="cm-sub">${esc(d.filiere_visee || '')} — dossier #${d.dossier_id} · inscription validée · taux établissement : ${taux} %</div>
            </div>
            <span class="cm-encours-badge">Commission à créer</span>
          </div>
          ${readOnly ? '<div class="cm-sub">Lecture seule — création réservée aux administrateurs.</div>' : `
          <div class="cm-create-row">
            <input type="number" class="form-input cm-montant" data-dossier-id="${d.dossier_id}"
                   min="1" step="1" placeholder="Montant de la scolarité (XAF)">
            <button type="button" class="btn-primary cm-create" data-dossier-id="${d.dossier_id}">Créer la commission</button>
          </div>
          <div class="cm-sub" style="margin-top:6px;">La commission de ${taux} % (taux configuré sur l'établissement) et ses 3 tranches (40 / 30 / 30) seront calculées automatiquement.</div>`}
        </div>`;
    });

    if (html === '') {
      html = '<div class="cm-empty">Aucun dossier « inscrit » pour cet utilisateur.</div>';
    }

    body.innerHTML = html;

    body.querySelectorAll('.cm-create').forEach(btn => {
      btn.addEventListener('click', async () => {
        const dossierId = btn.dataset.dossierId;
        const input     = body.querySelector(`.cm-montant[data-dossier-id="${dossierId}"]`);
        const montant   = parseFloat(input?.value || '0');
        if (!montant || montant <= 0) {
          window.showToast?.('Saisissez le montant de la scolarité.', 'warning');
          input?.focus();
          return;
        }
        btn.disabled = true;
        try {
          const res  = await fetch(`${base()}/admin/api/commissions`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': csrf(),
              'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ dossier_id: dossierId, montant_scolarite: montant, _csrf_token: csrf() }),
          });
          const data = await res.json();
          if (data.success) {
            window.showToast?.(data.message, 'success');
            load();
          } else {
            window.showToast?.(data.message || 'Erreur', 'error');
            btn.disabled = false;
          }
        } catch {
          window.showToast?.('Erreur réseau', 'error');
          btn.disabled = false;
        }
      });
    });

    body.querySelectorAll('.cm-toggle').forEach(cb => {
      cb.addEventListener('change', async () => {
        cb.disabled = true;
        try {
          const res  = await fetch(`${base()}/admin/api/commissions/tranches/${cb.dataset.trancheId}`, {
            method: 'PATCH',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': csrf(),
              'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ paye: cb.checked, _csrf_token: csrf() }),
          });
          const data = await res.json();
          if (data.success) {
            window.showToast?.(data.message, 'success');
          } else {
            window.showToast?.(data.message || 'Erreur', 'error');
          }
        } catch {
          window.showToast?.('Erreur réseau', 'error');
        }
        load();
      });
    });
  }

  document.querySelectorAll('.btn-commission-user').forEach(btn => {
    btn.addEventListener('click', () =>
      openCommissionModal(btn.dataset.userId, btn.dataset.name)
    );
  });
})();
</script>
