<?php
// Variables: $users (array), $counts (array), $page, $totalPages, $filters, $series (array)
$users      = $users ?? [];
$counts     = $counts ?? ['total'=>0,'eleve'=>0,'enseignant'=>0,'admin'=>0,'actifs'=>0,'suspendus'=>0];
$page       = (int)($page ?? 1);
$totalPages = (int)($totalPages ?? 1);
$total      = (int)($total ?? 0);
$filters    = $filters ?? [];
$series     = $series ?? [];

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
    <button class="btn-primary" data-modal-open="modal-create-user">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Nouvel utilisateur
    </button>
  </div>
</div>

<!-- Tabs -->
<div class="admin-tabs" style="border-radius:var(--r) var(--r) 0 0;">
  <?php
  $tabs = [
    'all'        => ['label' => 'Tous',        'count' => $counts['total']],
    'eleve'      => ['label' => 'Élèves',      'count' => $counts['eleve'] ?? 0],
    'enseignant' => ['label' => 'Enseignants', 'count' => $counts['enseignant'] ?? 0],
    'suspended'  => ['label' => 'Suspendus',   'count' => $counts['suspendus']],
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

<!-- Table -->
<div class="admin-table-wrap" style="border-radius:0 0 var(--r) var(--r);">
  <div class="admin-table-filters">
    <input type="text" id="users-search-input"
           placeholder="Rechercher par nom, email… (Entrée pour chercher)"
           data-search-table="users-table"
           value="<?= e($filters['q'] ?? '') ?>"
           data-base-url="<?= e(pgUrl(array_merge($filters, ['q' => '']), 1)) ?>">
  </div>

  <table class="admin-table" id="users-table">
    <thead>
      <tr>
        <th>Utilisateur</th>
        <th>Rôle</th>
        <th>Série</th>
        <th>Statut</th>
        <th>Abonnement</th>
        <th>Présence</th>
        <th>Inscrit le</th>
        <th>Dernier login</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($users)): ?>
      <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--txt-m);">Aucun utilisateur trouvé</td></tr>
      <?php endif; ?>
      <?php foreach ($users as $u): ?>
      <tr data-user-id="<?= $u['id'] ?>">
        <td>
          <div class="user-cell">
            <div class="user-avatar" style="background:<?= $u['is_active'] ? 'var(--ap)' : 'var(--txt-l)' ?>;">
              <?php if (!empty($u['photo_profil_url'])): ?>
                <img src="<?= e($u['photo_profil_url']) ?>" alt="">
              <?php else: ?>
                <?= strtoupper(mb_substr($u['prenom'], 0, 1) . mb_substr($u['nom'], 0, 1)) ?>
              <?php endif; ?>
            </div>
            <div class="user-info">
              <strong><?= e($u['prenom'] . ' ' . $u['nom']) ?></strong>
              <span><?= e($u['email']) ?></span>
            </div>
          </div>
        </td>
        <td><span class="badge badge-<?= e($u['role']) ?>"><?= e(ucfirst($u['role'])) ?></span></td>
        <td style="color:var(--txt-m);font-size:12px;"><?= e($u['serie'] ?? '—') ?></td>
        <td>
          <span class="badge <?= $u['is_active'] ? 'badge-actif' : 'badge-suspendu' ?> status-badge">
            <?= $u['is_active'] ? 'Actif' : 'Suspendu' ?>
          </span>
        </td>
        <td>
          <?php
            $abStatut = $u['ab_statut'] ?? null;
            $abFin    = $u['ab_fin']    ?? null;
            $abPlan   = $u['ab_plan']   ?? null;
            if ($abStatut === 'actif' && $abFin):
              $joursAb = max(0, (int) ceil((strtotime($abFin) - time()) / 86400));
          ?>
            <span style="display:inline-flex;flex-direction:column;gap:2px;">
              <span style="display:inline-block;background:rgba(16,185,129,0.12);color:#059669;font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px;">
                Actif · <?= ucfirst($abPlan) ?>
              </span>
              <span style="font-size:10px;color:var(--txt-m);">exp. <?= date('d/m/Y', strtotime($abFin)) ?></span>
            </span>
          <?php else: ?>
            <span style="background:#f3f4f6;color:#9ca3af;font-size:10px;font-weight:600;padding:2px 8px;border-radius:999px;display:inline-block;">Aucun</span>
          <?php endif; ?>
        </td>
        <td>
          <?php
            $online = isOnline(!empty($u['last_activity_ts']) ? (int)$u['last_activity_ts'] : null);
          ?>
          <span class="presence-badge <?= $online ? 'presence-online' : 'presence-offline' ?>"
                data-uid="<?= $u['id'] ?>">
            <span class="presence-dot"></span>
            <?= $online ? 'En ligne' : 'Déconnecté' ?>
          </span>
        </td>
        <td style="font-size:12px;color:var(--txt-m);"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
        <td style="font-size:12px;color:var(--txt-m);">
          <?php if (!empty($u['last_login_ts']) && (int)$u['last_login_ts'] > 0): ?>
            <span title="<?= date('d/m/Y à H:i:s', (int)$u['last_login_ts']) ?>">
              il y a <?= humanTimeDiff((int)$u['last_login_ts']) ?>
            </span>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td>
          <div class="table-actions" style="opacity:1;gap:4px;">
            <label class="toggle-switch" title="<?= $u['is_active'] ? 'Suspendre' : 'Activer' ?>">
              <input type="checkbox" class="user-toggle"
                     data-user-id="<?= $u['id'] ?>"
                     <?= $u['is_active'] ? 'checked' : '' ?>>
              <span class="toggle-slider"></span>
            </label>
            <button class="action-btn btn-edit-user" title="Modifier"
                    data-user-id="<?= $u['id'] ?>"
                    data-prenom="<?= e($u['prenom']) ?>"
                    data-nom="<?= e($u['nom']) ?>"
                    data-email="<?= e($u['email']) ?>"
                    data-role="<?= e($u['role']) ?>"
                    data-serie-id="<?= (int)($u['serie_id'] ?? 0) ?>">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button class="action-btn btn-delete-user" title="Supprimer"
                    data-user-id="<?= $u['id'] ?>"
                    data-name="<?= e($u['prenom'] . ' ' . $u['nom']) ?>"
                    style="color:var(--red,#EF4444);">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
            </button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Pagination — toujours affichée, boutons de page uniquement si > 1 page -->
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
    // Filtre client-side immédiat (current page)
    const tableBody = document.querySelector('#users-table tbody');
    if (tableBody) {
      tableBody.querySelectorAll('tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q.toLowerCase()) ? '' : 'none';
      });
    }
    // Déclenchement serveur en différé
    if (q.length === 0 || q.length >= 2) {
      debounce = setTimeout(() => serverSearch(q), 700);
    }
  });
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
          <label class="form-label">Mot de passe temporaire *</label>
          <input type="password" name="password" class="form-input" placeholder="Min. 8 caractères" minlength="8" required>
          <span style="font-size:11px;color:var(--txt-m);margin-top:4px;display:block;">L'utilisateur pourra le modifier après connexion.</span>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div class="form-group">
            <label class="form-label">Rôle *</label>
            <select name="role" class="form-select" required>
              <option value="eleve">Élève</option>
              <option value="enseignant">Enseignant</option>
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
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div class="form-group">
            <label class="form-label">Rôle *</label>
            <select name="role" id="edit-role" class="form-select" required>
              <option value="eleve">Élève</option>
              <option value="enseignant">Enseignant</option>
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
