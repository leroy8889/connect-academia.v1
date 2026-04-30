<?php
// Variables: $series (array avec nb_eleves, nb_ressources), $matieres, $activeSerie
$series      = $series ?? [];
$matieres    = $matieres ?? [];
$activeSerie = $activeSerie ?? null;

$serieColors = [
    'A'   => '#8B52FA',
    'C'   => '#F59E0B',
    'D'   => '#3B82F6',
    'SES' => '#10B981',
    'STI' => '#EF4444',
    'B'   => '#EC4899',
];
?>

<!-- Header -->
<div class="admin-page-header-row">
  <div>
    <h1>Séries &amp; Matières</h1>
    <p>Gérez les filières Terminale disponibles sur la plateforme</p>
  </div>
  <button class="btn-primary" data-modal-open="modal-new-serie">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Nouvelle série
  </button>
</div>

<!-- Cartes séries -->
<div class="series-grid mb-24">
  <?php foreach ($series as $s):
    $initial = mb_strtoupper(mb_substr($s['nom'], 0, 3));
    $color   = $serieColors[$s['nom']] ?? '#8B52FA';
    $isActive = $activeSerie && (int)$activeSerie['id'] === (int)$s['id'];
  ?>
  <div class="serie-card <?= $isActive ? 'active-serie' : '' ?>" style="position:relative;"
       onclick="window.location='<?= url('/admin/series-matieres?serie=' . $s['id']) ?>'">
    <div class="serie-initial" style="background:<?= e($color) ?>;"><?= e($initial) ?></div>
    <h3>Terminale <?= e($s['nom']) ?></h3>
    <p><?= e($s['description'] ?? 'Filière Terminale') ?></p>
    <div class="serie-stats">
      <div>
        <div class="serie-stat-value"><?= number_format((int)($s['nb_users'] ?? 0)) ?></div>
        <div class="serie-stat-label">Élèves</div>
      </div>
      <div>
        <div class="serie-stat-value"><?= number_format((int)($s['nb_ressources'] ?? 0)) ?></div>
        <div class="serie-stat-label">Ressources</div>
      </div>
    </div>
    <button class="action-btn" style="position:absolute;top:14px;right:14px;"
            onclick="event.stopPropagation()">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/>
      </svg>
    </button>
  </div>
  <?php endforeach; ?>
  <?php if (empty($series)): ?>
  <div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--txt-m);">
    Aucune série configurée. Cliquez sur <strong>Nouvelle série</strong> pour commencer.
  </div>
  <?php endif; ?>
</div>

<!-- Matières de la série active -->
<?php if ($activeSerie && !empty($matieres)): ?>
<div class="admin-table-wrap">
  <div class="admin-table-header">
    <div>
      <h2>Matières — Terminale <?= e($activeSerie['nom']) ?></h2>
      <p><?= count($matieres) ?> matières enregistrées · Glissez-déposez pour réordonner</p>
    </div>
    <button class="btn-primary btn-sm" data-modal-open="modal-new-matiere">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Ajouter
    </button>
  </div>
  <table class="admin-table">
    <thead>
      <tr>
        <th>Matière</th>
        <th>Coefficient</th>
        <th>Enseignants</th>
        <th>Ressources</th>
        <th>Activité</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php
      $maxRes = max(1, max(array_column($matieres, 'nb_ressources') ?: [1]));
      foreach ($matieres as $m):
        $pct = $maxRes > 0 ? round(($m['nb_ressources'] / $maxRes) * 100) : 0;
      ?>
      <tr>
        <td style="font-weight:600;"><?= e($m['nom']) ?></td>
        <td style="color:var(--txt-m);font-size:12px;">coef. <?= e($m['coef'] ?? '—') ?></td>
        <td style="color:var(--txt-m);font-size:12px;"><?= number_format((int)($m['nb_enseignants'] ?? 0)) ?></td>
        <td style="color:var(--txt-m);font-size:12px;"><?= number_format((int)($m['nb_ressources'] ?? 0)) ?></td>
        <td style="width:160px;">
          <div class="matiere-progress-bar" style="height:4px;">
            <div class="matiere-progress-fill" style="width:<?= $pct ?>%;"></div>
          </div>
        </td>
        <td>
          <div class="table-actions" style="opacity:1;">
            <button class="action-btn" title="Modifier" data-modal-open="modal-edit-matiere-<?= $m['id'] ?>">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php elseif (!$activeSerie): ?>
<div class="admin-card">
  <div class="admin-card-body empty-state">
    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
    <h3>Sélectionnez une série</h3>
    <p>Cliquez sur une carte de série ci-dessus pour voir ses matières.</p>
  </div>
</div>
<?php endif; ?>

<!-- Modal nouvelle matière -->
<div class="admin-modal-overlay" id="modal-new-matiere">
  <div class="admin-modal">
    <div class="admin-modal-header">
      <h2>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--ap)" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
        Nouvelle matière
      </h2>
      <button class="modal-close" data-modal-close="modal-new-matiere">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="admin-modal-body">
      <form action="<?= url('/admin/api/series/matiere') ?>" method="POST" id="form-new-matiere">
        <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
        <input type="hidden" name="serie_id" value="<?= (int)($activeSerie['id'] ?? 0) ?>">
        <div class="form-group">
          <label class="form-label">Nom de la matière *</label>
          <input type="text" name="nom" class="form-input" placeholder="Ex: Mathématiques" required>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div class="form-group">
            <label class="form-label">Coefficient *</label>
            <input type="number" name="coef" class="form-input" value="2" min="1" max="10" required>
          </div>
          <div class="form-group">
            <label class="form-label">Icône (emoji ou code)</label>
            <input type="text" name="icone" class="form-input" placeholder="Ex: 📐 ou math">
          </div>
        </div>
        <div class="admin-modal-footer" style="padding:0;border:none;margin-top:20px;">
          <button type="button" class="btn-ghost" data-modal-close="modal-new-matiere">Annuler</button>
          <button type="submit" class="btn-primary">Ajouter la matière</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal nouvelle série -->
<div class="admin-modal-overlay" id="modal-new-serie">
  <div class="admin-modal">
    <div class="admin-modal-header">
      <h2>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--ap)" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
        Nouvelle série
      </h2>
      <button class="modal-close" data-modal-close="modal-new-serie">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="admin-modal-body">
      <form action="<?= url('/admin/api/series/serie') ?>" method="POST">
        <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
        <div class="form-group">
          <label class="form-label">Nom de la série *</label>
          <input type="text" name="nom" class="form-input" placeholder="Ex: D" required>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <input type="text" name="description" class="form-input" placeholder="Ex: Sciences expérimentales">
        </div>
        <div class="form-group">
          <label class="form-label">Couleur</label>
          <input type="color" name="couleur" class="form-input" value="#8B52FA" style="height:42px;padding:4px 8px;">
        </div>
        <div class="admin-modal-footer" style="padding:0;border:none;margin-top:20px;">
          <button type="button" class="btn-ghost" data-modal-close="modal-new-serie">Annuler</button>
          <button type="submit" class="btn-primary">Créer la série</button>
        </div>
      </form>
    </div>
  </div>
</div>
