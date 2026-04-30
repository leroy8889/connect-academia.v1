<?php
// Variables: $ressources (array), $series (array), $matieresBySerie (array), $totalRessources, $totalVues, $totalPdf
$ressources      = $ressources ?? [];
$series          = $series ?? [];
$matieresBySerie = $matieresBySerie ?? [];
$totalRessources = $totalRessources ?? 0;
$totalVues       = $totalVues ?? 0;
$totalPdf        = $totalPdf ?? 0;

$typeBg = [
    'cours'            => ['bg' => '#F0FDF4', 'color' => '#16A34A', 'label' => 'COURS'],
    'td'               => ['bg' => '#EFF6FF', 'color' => '#1D4ED8', 'label' => 'TD'],
    'ancienne_epreuve' => ['bg' => '#FFF7ED', 'color' => '#C2410C', 'label' => 'SUJET'],
    'corrige'          => ['bg' => '#FFF7ED', 'color' => '#B45309', 'label' => 'CORRIGÉ'],
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

<!-- Filtres -->
<div class="admin-card mb-16">
  <div style="padding:14px 18px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
    <input type="text" placeholder="Rechercher un cours, un sujet…"
           data-search-table="ressources-grid-container"
           style="flex:1;min-width:180px;padding:9px 14px;border:1.5px solid var(--border);border-radius:8px;font-family:var(--font-body);font-size:13px;background:var(--bg);outline:none;">
    <select class="form-select" style="width:auto;padding:9px 14px;" id="filter-serie">
      <option value="">Toutes les séries ▾</option>
      <?php foreach ($series as $s): ?>
      <option value="<?= $s['id'] ?>">Terminale <?= e($s['nom']) ?></option>
      <?php endforeach; ?>
    </select>
    <select class="form-select" style="width:auto;padding:9px 14px;" id="filter-matiere">
      <option value="">Toutes les matières ▾</option>
      <option value="Mathématiques">Mathématiques</option>
      <option value="Physique-Chimie">Physique-Chimie</option>
      <option value="SVT">SVT</option>
      <option value="Français">Français</option>
      <option value="Anglais">Anglais</option>
      <option value="Historique-Géographie">Historique-Géographie</option>
      <option value="Philosophie">Philosophie</option>
      <option value="Espagnol">Espagnol</option>
      <option value="Economie">Economie</option>


    </select>
    <select class="form-select" style="width:auto;padding:9px 14px;" id="filter-type">
      <option value="">Tous les types ▾</option>
      <option value="cours">Cours</option>
      <option value="td">TD</option>
      <option value="ancienne_epreuve">Sujets</option>
      <option value="corrige">Corrigés</option>
    </select>
    <button class="btn-ghost">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="21" y1="10" x2="7" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="21" y1="18" x2="7" y2="18"/></svg>
      Trier · Plus vue
    </button>
  </div>
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
    <h3>Aucune ressource publiée</h3>
    <p>Cliquez sur <strong>Nouvelle ressource</strong> pour commencer.</p>
  </div>
  <?php endif; ?>
</div>

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
              <option value="corrige">Corrigé</option>
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
            <select name="matiere_id" class="form-select" required>
              <option value="">Choisir la matière</option>
              <option value="Mathématiques">Mathématiques</option>
              <option value="Physique-Chimie">Physique-Chimie</option>
              <option value="SVT">SVT</option>
              <option value="Français">Français</option>
              <option value="Anglais">Anglais</option>
              <option value="Historique-Géographie">Historique-Géographie</option>
              <option value="Philosophie">Philosophie</option>
              <option value="Espagnol">Espagnol</option>
              <option value="Economie">Economie</option>

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
              <option value="corrige">Corrigé</option>
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

<style>
.ressource-card:hover .card-actions { opacity: 1 !important; }
</style>

<script>
// Matières pré-chargées par série (évite AJAX, fiable même si baseUrl cassée)
window.CA_MATIERES_BY_SERIE = <?= json_encode($matieresBySerie) ?>;
</script>
