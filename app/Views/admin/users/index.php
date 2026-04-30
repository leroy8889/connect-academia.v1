<?php
// Variables: $users (array), $counts (array), $page, $totalPages, $filters, $series (array)
$users      = $users ?? [];
$counts     = $counts ?? ['total'=>0,'eleve'=>0,'enseignant'=>0,'admin'=>0,'actifs'=>0,'suspendus'=>0];
$page       = (int)($page ?? 1);
$totalPages = (int)($totalPages ?? 1);
$filters    = $filters ?? [];
$series     = $series ?? [];
$activeTab  = $filters['role'] ?? 'all';

if (!function_exists('humanTimeDiff')) {
    function humanTimeDiff(string $datetime): string {
        $diff = time() - strtotime($datetime);
        if ($diff < 60)     return $diff . 's';
        if ($diff < 3600)   return floor($diff/60) . 'min';
        if ($diff < 86400)  return floor($diff/3600) . 'h';
        if ($diff < 604800) return floor($diff/86400) . 'j';
        return floor($diff/604800) . 'sem';
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
    $href = url('/admin/utilisateurs') . ($key !== 'all' ? '?role=' . $key : '');
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
    <input type="text" placeholder="Rechercher par nom, email…"
           data-search-table="users-table"
           value="<?= e($filters['q'] ?? '') ?>">
  </div>

  <table class="admin-table" id="users-table">
    <thead>
      <tr>
        <th>Utilisateur</th>
        <th>Rôle</th>
        <th>Série</th>
        <th>Statut</th>
        <th>Inscrit le</th>
        <th>Dernier login</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($users)): ?>
      <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--txt-m);">Aucun utilisateur trouvé</td></tr>
      <?php endif; ?>
      <?php foreach ($users as $u): ?>
      <tr data-user-id="<?= $u['id'] ?>">
        <td>
          <div class="user-cell">
            <div class="user-avatar" style="background:<?= $u['is_active'] ? 'var(--ap)' : 'var(--txt-l)' ?>;">
              <?php if (!empty($u['photo_profil'])): ?>
                <img src="<?= e($u['photo_profil']) ?>" alt="">
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
        <td style="font-size:12px;color:var(--txt-m);"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
        <td style="font-size:12px;color:var(--txt-m);">
          <?= $u['last_login'] ? 'il y a ' . humanTimeDiff($u['last_login']) : '—' ?>
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

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
  <div class="admin-pagination">
    <p>Affichage <?= (($page-1)*20)+1 ?>–<?= min($page*20, $counts['total']) ?> sur <?= number_format($counts['total']) ?></p>
    <div class="pagination-btns">
      <?php if ($page > 1): ?>
        <a href="<?= url('/admin/utilisateurs?page='.($page-1)) ?>" class="pagination-btn">← Précédent</a>
      <?php endif; ?>
      <?php for ($p = max(1, $page-2); $p <= min($totalPages, $page+2); $p++): ?>
        <a href="<?= url('/admin/utilisateurs?page='.$p) ?>"
           class="pagination-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
      <?php endfor; ?>
      <?php if ($page < $totalPages): ?>
        <a href="<?= url('/admin/utilisateurs?page='.($page+1)) ?>" class="pagination-btn">Suivant →</a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

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
