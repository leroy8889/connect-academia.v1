<?php
// Variables: $ressources (array), $series (array), $matieresBySerie (array), $totalRessources, $totalVues, $totalPdf
$ressources      = $ressources ?? [];
$series          = $series ?? [];
$matieresBySerie = $matieresBySerie ?? [];
$totalRessources = $totalRessources ?? 0;
$totalVues       = $totalVues ?? 0;
$totalPdf        = $totalPdf ?? 0;
$filtres         = ($filtres ?? []) + ['serie_id' => 0, 'matiere_id' => 0, 'type' => '', 'q' => ''];
$pagination      = ($pagination ?? []) + [
    'page' => 1, 'par_page' => 48, 'total' => count($ressources),
    'total_pages' => 1, 'debut' => 1, 'fin' => count($ressources), 'options' => [24, 48, 96, 200],
];
$filtresActifs   = $filtres['serie_id'] > 0 || $filtres['matiere_id'] > 0 || $filtres['type'] !== '' || $filtres['q'] !== '';

// URL de la bibliothèque en conservant les filtres courants
$ctnUrl = static function (array $over = []) use ($filtres, $pagination): string {
    $p = [
        'serie_id'   => $filtres['serie_id'] ?: null,
        'matiere_id' => $filtres['matiere_id'] ?: null,
        'type'       => $filtres['type'] ?: null,
        'q'          => $filtres['q'] ?: null,
        'par_page'   => $pagination['par_page'] === 48 ? null : $pagination['par_page'],
        'page'       => $pagination['page'] > 1 ? $pagination['page'] : null,
    ];
    foreach ($over as $k => $v) $p[$k] = $v;
    if (($p['par_page'] ?? null) === 0) $p['par_page'] = '0'; // « tout afficher » explicite
    $p = array_filter($p, static fn ($v): bool => $v !== null && $v !== '' && $v !== 0);
    return url('/admin/contenu') . ($p ? '?' . http_build_query($p) : '');
};

// Matières du filtre : celles de la série sélectionnée, sinon toutes (groupées)
$serieNoms = [];
foreach ($series as $s) $serieNoms[(int)$s['id']] = $s['nom'];

$typeBg = [
    'cours'            => ['bg' => '#F0FDF4', 'color' => '#16A34A', 'label' => 'COURS'],
    'td'               => ['bg' => '#EFF6FF', 'color' => '#1D4ED8', 'label' => 'TD'],
    'ancienne_epreuve' => ['bg' => '#FFF7ED', 'color' => '#C2410C', 'label' => 'SUJET'],
    'corrige'          => ['bg' => '#FFF7ED', 'color' => '#B45309', 'label' => 'MÉTHODOLOGIE'],
];
?>

<!-- Header -->
<div class="admin-page-header-row">
  <div>
    <h1>Bibliothèque des ressources</h1>
    <p><?= number_format($totalRessources) ?> PDF publiés · <?= number_format($totalVues) ?> vues cumulées ce mois</p>
  </div>
  <div style="display:flex;gap:10px;">
    <button class="btn-ghost">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
      Filtres
    </button>
    <button class="btn-primary" data-modal-open="modal-upload">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Nouvelle ressource
    </button>
  </div>
</div>

<!-- Filtres — soumis au serveur : la recherche porte sur TOUTE la bibliothèque,
     pas seulement sur les cartes de la page affichée -->
<div class="admin-card mb-16">
  <form method="GET" action="<?= url('/admin/contenu') ?>"
        style="padding:14px 18px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
    <input type="text" name="q" value="<?= e($filtres['q']) ?>" placeholder="Rechercher un cours, un sujet…"
           data-search-table="ressources-grid-container"
           style="flex:1;min-width:180px;padding:9px 14px;border:1.5px solid var(--border);border-radius:8px;font-family:var(--font-body);font-size:13px;background:var(--bg);outline:none;">
    <select class="form-select" style="width:auto;padding:9px 14px;" id="filter-serie" name="serie_id">
      <option value="">Toutes les séries ▾</option>
      <?php foreach ($series as $s): ?>
      <option value="<?= $s['id'] ?>" <?= $filtres['serie_id'] === (int)$s['id'] ? 'selected' : '' ?>>Terminale <?= e($s['nom']) ?></option>
      <?php endforeach; ?>
    </select>
    <select class="form-select" style="width:auto;padding:9px 14px;" id="filter-matiere" name="matiere_id">
      <option value="">Toutes les matières ▾</option>
      <?php foreach ($matieresBySerie as $sid => $mats): ?>
        <?php if ($filtres['serie_id'] > 0 && $filtres['serie_id'] !== (int)$sid) continue; ?>
        <optgroup label="Terminale <?= e($serieNoms[(int)$sid] ?? '—') ?>">
          <?php foreach ($mats as $m): ?>
          <option value="<?= (int)$m['id'] ?>" <?= $filtres['matiere_id'] === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['nom']) ?></option>
          <?php endforeach; ?>
        </optgroup>
      <?php endforeach; ?>
    </select>
    <select class="form-select" style="width:auto;padding:9px 14px;" id="filter-type" name="type">
      <option value="">Tous les types ▾</option>
      <option value="cours"            <?= $filtres['type'] === 'cours'            ? 'selected' : '' ?>>Cours</option>
      <option value="td"               <?= $filtres['type'] === 'td'               ? 'selected' : '' ?>>TD</option>
      <option value="ancienne_epreuve" <?= $filtres['type'] === 'ancienne_epreuve' ? 'selected' : '' ?>>Sujets</option>
      <option value="corrige"          <?= $filtres['type'] === 'corrige'          ? 'selected' : '' ?>>Méthodologies</option>
    </select>
    <select class="form-select" style="width:auto;padding:9px 14px;" name="par_page"
            onchange="this.form.querySelector('input[name=page]')?.remove(); this.form.submit();">
      <?php foreach ($pagination['options'] as $opt): ?>
      <option value="<?= $opt ?>" <?= (int)$pagination['par_page'] === $opt ? 'selected' : '' ?>><?= $opt ?> / page</option>
      <?php endforeach; ?>
      <option value="0" <?= (int)$pagination['par_page'] === 0 ? 'selected' : '' ?>>Tout afficher</option>
    </select>
    <button type="submit" class="btn-primary">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
      Filtrer
    </button>
    <?php if ($filtresActifs): ?>
    <a href="<?= url('/admin/contenu') ?>" class="btn-ghost" style="text-decoration:none;">Réinitialiser</a>
    <?php endif; ?>
  </form>
</div>

<!-- Compteur d'affichage — vérité sur ce qui est visible vs total en base -->
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px;">
  <div style="font-size:12.5px;color:var(--txt-m);">
    <?php if ($pagination['total'] === 0): ?>
      Aucune ressource ne correspond aux filtres.
    <?php else: ?>
      Affichage <strong style="color:var(--txt);"><?= number_format($pagination['debut']) ?>–<?= number_format($pagination['fin']) ?></strong>
      sur <strong style="color:var(--txt);"><?= number_format($pagination['total']) ?></strong>
      ressource<?= $pagination['total'] > 1 ? 's' : '' ?><?= $filtresActifs ? ' filtrée' . ($pagination['total'] > 1 ? 's' : '') : '' ?>
      <?php if ($filtresActifs): ?><span style="color:var(--txt-l);">· <?= number_format($totalRessources) ?> au total</span><?php endif; ?>
    <?php endif; ?>
  </div>
  <?php if ($pagination['total_pages'] > 1): ?>
  <div style="font-size:12.5px;color:var(--txt-l);">Page <?= $pagination['page'] ?> / <?= $pagination['total_pages'] ?></div>
  <?php endif; ?>
</div>

<!-- Grille ressources -->
<div class="ressources-grid" id="ressources-grid-container">
  <?php foreach ($ressources as $r):
    $typeInfo = $typeBg[$r['type']] ?? $typeBg['cours'];
  ?>
  <div class="ressource-card" style="position:relative;">
    <div class="ressource-card-thumb" style="background:<?= $typeInfo['bg'] ?>;">
      <span class="type-badge badge" style="background:<?= $typeInfo['bg'] ?>;color:<?= $typeInfo['color'] ?>;border:1px solid <?= $typeInfo['color'] ?>33;font-size:10px;">
        <?= $typeInfo['label'] ?>
      </span>
      <div class="ressource-card-thumb-icon">
        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="<?= $typeInfo['color'] ?>" stroke-width="1.5">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
          <polyline points="14 2 14 8 20 8"/>
          <line x1="16" y1="13" x2="8" y2="13"/>
          <line x1="16" y1="17" x2="8" y2="17"/>
        </svg>
      </div>
      <!-- Actions overlay -->
      <div style="position:absolute;top:8px;right:8px;display:flex;gap:4px;opacity:0;transition:opacity 0.15s;" class="card-actions">
        <button class="action-btn btn-view-ressource"
                data-url="<?= e($r['file_url'] ?? '#') ?>"
                data-titre="<?= e($r['titre']) ?>"
                style="background:white;width:28px;height:28px;" title="Visualiser">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#0369A1" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
        <button class="action-btn btn-edit-ressource"
                data-id="<?= $r['id'] ?>"
                data-titre="<?= e($r['titre']) ?>"
                data-type="<?= e($r['type']) ?>"
                data-annee="<?= e($r['annee'] ?? '') ?>"
                data-desc="<?= e($r['description'] ?? '') ?>"
                style="background:white;width:28px;height:28px;" title="Modifier">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--ap)" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        </button>
        <button class="action-btn btn-delete-ressource"
                data-id="<?= $r['id'] ?>"
                data-titre="<?= e(addslashes($r['titre'])) ?>"
                style="background:white;width:28px;height:28px;" title="Supprimer">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--red)" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
        </button>
      </div>
    </div>
    <div class="ressource-card-body">
      <div class="ressource-card-title"><?= e($r['titre']) ?></div>
      <div class="ressource-card-tags">
        <span class="ressource-tag tag-matiere"><?= e($r['matiere'] ?? '—') ?></span>
        <span class="ressource-tag tag-serie">Terminale <?= e($r['serie'] ?? '—') ?></span>
      </div>
      <div class="ressource-card-meta">
        <span>
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          <?= number_format((int)($r['nb_vues'] ?? 0)) ?>
        </span>
        <span>
          <?= $r['created_at'] ? date('d M', strtotime($r['created_at'])) : '' ?>
        </span>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (empty($ressources)): ?>
  <div style="grid-column:1/-1;" class="empty-state">
    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
    <?php if ($filtresActifs): ?>
    <h3>Aucun résultat</h3>
    <p>Aucune ressource ne correspond à ces filtres — <a href="<?= url('/admin/contenu') ?>" style="color:var(--ap);font-weight:600;">tout afficher</a>.</p>
    <?php else: ?>
    <h3>Aucune ressource publiée</h3>
    <p>Cliquez sur <strong>Nouvelle ressource</strong> pour commencer.</p>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Pagination serveur — aucune ressource n'est plus masquée par un LIMIT -->
<?php if ($pagination['total_pages'] > 1): ?>
<nav class="ctn-pager" aria-label="Pagination des ressources">
  <?php if ($pagination['page'] > 1): ?>
  <a class="ctn-pager-btn" href="<?= e($ctnUrl(['page' => $pagination['page'] - 1])) ?>" rel="prev">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="15 18 9 12 15 6"/></svg>
    Précédent
  </a>
  <?php else: ?>
  <span class="ctn-pager-btn is-off">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="15 18 9 12 15 6"/></svg>
    Précédent
  </span>
  <?php endif; ?>

  <?php
    // Fenêtre de pages : 1 … p-2 p-1 [p] p+1 p+2 … N
    $tp = (int)$pagination['total_pages'];
    $cp = (int)$pagination['page'];
    $pages = array_unique(array_filter(
        array_merge([1, 2], range(max(1, $cp - 2), min($tp, $cp + 2)), [$tp - 1, $tp]),
        static fn (int $n): bool => $n >= 1 && $n <= $tp
    ));
    sort($pages);
    $prev = 0;
  ?>
  <?php foreach ($pages as $n): ?>
    <?php if ($prev && $n > $prev + 1): ?><span class="ctn-pager-gap">…</span><?php endif; ?>
    <?php if ($n === $cp): ?>
    <span class="ctn-pager-num is-current" aria-current="page"><?= $n ?></span>
    <?php else: ?>
    <a class="ctn-pager-num" href="<?= e($ctnUrl(['page' => $n])) ?>"><?= $n ?></a>
    <?php endif; ?>
    <?php $prev = $n; ?>
  <?php endforeach; ?>

  <?php if ($pagination['page'] < $tp): ?>
  <a class="ctn-pager-btn" href="<?= e($ctnUrl(['page' => $pagination['page'] + 1])) ?>" rel="next">
    Suivant
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="9 18 15 12 9 6"/></svg>
  </a>
  <?php else: ?>
  <span class="ctn-pager-btn is-off">
    Suivant
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="9 18 15 12 9 6"/></svg>
  </span>
  <?php endif; ?>

  <a class="ctn-pager-all" href="<?= e($ctnUrl(['par_page' => 0, 'page' => null])) ?>">Tout afficher (<?= number_format($pagination['total']) ?>)</a>
</nav>
<?php endif; ?>

<!-- ── MODAL UPLOAD ───────────────────────────────────────── -->
<div class="admin-modal-overlay" id="modal-upload">
  <div class="admin-modal">
    <div class="admin-modal-header">
      <h2>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--ap)" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        Ajouter une ressource
      </h2>
      <button class="modal-close" data-modal-close="modal-upload">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="admin-modal-body">
      <form id="upload-ressource-form" enctype="multipart/form-data">
        <div class="form-group">
          <label class="form-label">Titre du document *</label>
          <input type="text" name="titre" class="form-input" placeholder="Ex: Devoir de Mathématiques n°1" required>
        </div>
        <div class="form-group">
          <label class="form-label">Description courte</label>
          <textarea name="description" class="form-textarea" placeholder="Présentation rapide du contenu…"></textarea>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div class="form-group">
            <label class="form-label">Type *</label>
            <select name="type" class="form-select" required>
              <option value="">Sélectionner un type</option>
              <option value="cours">Cours</option>
              <option value="td">Travail Dirigé (TD)</option>
              <option value="ancienne_epreuve">Ancienne Épreuve / Sujet</option>
              <option value="corrige">Méthodologie</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Série *</label>
            <select name="serie_id" class="form-select" id="modal-serie-select" required>
              <option value="">Toutes</option>
              <?php foreach ($series as $s): ?>
              <option value="<?= $s['id'] ?>">Terminale <?= e($s['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div class="form-group">
            <label class="form-label">Matière *</label>
            <select name="matiere_id" class="form-select" id="modal-matiere-select" required disabled>
              <option value="">← Sélectionnez d'abord une série</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Année (pour sujets)</label>
            <input type="number" name="annee" class="form-input" placeholder="Ex: 2024" min="2000" max="2030">
          </div>
        </div>
        <!-- Zone upload -->
        <div class="upload-zone" id="drop-zone" onclick="document.getElementById('fichier-input').click()">
          <div class="upload-zone-icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>
          </div>
          <div class="upload-zone-title">Cliquez ou glissez-déposez votre fichier PDF</div>
          <div class="upload-zone-hint">PDF uniquement, taille max 20 Mo</div>
          <div id="file-name" style="margin-top:8px;font-size:12px;color:var(--ap);font-weight:600;"></div>
          <input type="file" id="fichier-input" name="fichier" accept=".pdf" required style="display:none;">
        </div>
        <div class="upload-progress" id="upload-progress-wrap">
          <div class="upload-progress-fill" id="upload-progress-fill" style="width:0%"></div>
        </div>
      </form>
    </div>
    <div class="admin-modal-footer">
      <button type="button" class="btn-ghost" data-modal-close="modal-upload">Annuler</button>
      <button type="submit" form="upload-ressource-form" class="btn-primary">Mettre en ligne</button>
    </div>
  </div>
</div>

<!-- ── MODAL ÉDITION RESSOURCE ────────────────────────────── -->
<div class="admin-modal-overlay" id="modal-edit-ressource">
  <div class="admin-modal">
    <div class="admin-modal-header">
      <h2>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--ap)" stroke-width="2">
          <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
          <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
        </svg>
        Modifier la ressource
      </h2>
      <button class="modal-close" data-modal-close="modal-edit-ressource">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="admin-modal-body">
      <form id="form-edit-ressource">
        <input type="hidden" name="_edit_ressource_id" id="edit-ressource-id">
        <div class="form-group">
          <label class="form-label">Titre *</label>
          <input type="text" name="titre" id="edit-ressource-titre" class="form-input" required>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" id="edit-ressource-desc" class="form-textarea" rows="3" placeholder="Présentation du contenu…"></textarea>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div class="form-group">
            <label class="form-label">Type *</label>
            <select name="type" id="edit-ressource-type" class="form-select" required>
              <option value="cours">Cours</option>
              <option value="td">Travail Dirigé (TD)</option>
              <option value="ancienne_epreuve">Ancienne Épreuve / Sujet</option>
              <option value="corrige">Méthodologie</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Année (sujets)</label>
            <input type="number" name="annee" id="edit-ressource-annee" class="form-input" placeholder="Ex: 2024" min="2000" max="2030">
          </div>
        </div>
      </form>
    </div>
    <div class="admin-modal-footer">
      <button type="button" class="btn-ghost" data-modal-close="modal-edit-ressource">Annuler</button>
      <button type="button" class="btn-primary" id="btn-submit-edit-ressource">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        Enregistrer
      </button>
    </div>
  </div>
</div>

<!-- ── MODAL VISUALISATION PDF ─────────────────────────────── -->
<div class="admin-modal-overlay" id="modal-view-ressource" style="align-items:flex-start;padding:20px;">
  <div class="admin-modal" style="max-width:860px;width:100%;max-height:calc(100vh - 40px);display:flex;flex-direction:column;">
    <div class="admin-modal-header" style="flex-shrink:0;">
      <h2 id="view-ressource-titre" style="font-size:14px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:680px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--ap)" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        Visualisation
      </h2>
      <div style="display:flex;align-items:center;gap:8px;">
        <a id="view-ressource-open-link" href="#" target="_blank" class="btn-ghost" style="font-size:11px;padding:5px 10px;">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
          Ouvrir dans onglet
        </a>
        <button class="modal-close" onclick="closeViewModal()">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
    </div>
    <div style="flex:1;min-height:0;padding:0;">
      <iframe id="view-ressource-iframe" src="" style="width:100%;height:72vh;border:none;display:block;"></iframe>
    </div>
  </div>
</div>

<style>
.ressource-card:hover .card-actions { opacity: 1 !important; }

/* Pagination bibliothèque — hairlines, pilules, tabular-nums */
.ctn-pager { display:flex; align-items:center; justify-content:center; gap:6px; flex-wrap:wrap; margin:24px 0 8px; }
.ctn-pager-btn, .ctn-pager-num, .ctn-pager-all {
  display:inline-flex; align-items:center; gap:5px;
  height:34px; padding:0 12px; border-radius:999px;
  border:1px solid var(--border-strong); background:var(--card);
  font-family:var(--font-body); font-size:12.5px; font-weight:600;
  color:var(--txt-m); text-decoration:none;
  transition:border-color .13s, color .13s, background .13s;
}
.ctn-pager-num { min-width:34px; justify-content:center; padding:0 10px; font-variant-numeric:tabular-nums; }
.ctn-pager-btn:hover, .ctn-pager-num:hover, .ctn-pager-all:hover { border-color:var(--ap); color:var(--ap); background:var(--ap-lt); }
.ctn-pager-num.is-current { background:var(--ap); border-color:var(--ap); color:#fff; }
.ctn-pager-btn.is-off { opacity:.4; pointer-events:none; }
.ctn-pager-gap { padding:0 2px; color:var(--txt-l); font-size:12.5px; }
.ctn-pager-all { margin-left:8px; border-style:dashed; }
@media (max-width:640px) {
  .ctn-pager-all { margin-left:0; width:100%; justify-content:center; }
}
</style>

<script>
// Matières pré-chargées par série (évite AJAX, fiable même si baseUrl cassée)
window.CA_MATIERES_BY_SERIE = <?= json_encode($matieresBySerie) ?>;

function openViewModal(url, titre) {
  const modal = document.getElementById('modal-view-ressource');
  document.getElementById('view-ressource-titre').childNodes[1].textContent = ' ' + titre;
  document.getElementById('view-ressource-iframe').src = url;
  document.getElementById('view-ressource-open-link').href = url;
  modal.classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeViewModal() {
  const modal = document.getElementById('modal-view-ressource');
  modal.classList.remove('open');
  document.getElementById('view-ressource-iframe').src = '';
  document.body.style.overflow = '';
}
document.getElementById('modal-view-ressource').addEventListener('click', function(e) {
  if (e.target === this) closeViewModal();
});
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.btn-view-ressource').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.stopPropagation();
      openViewModal(this.dataset.url, this.dataset.titre);
    });
  });
});
</script>
