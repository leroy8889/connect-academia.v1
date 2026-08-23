<?php
$couleur  = e($etab['couleur_principale'] ?? '#8B52FA');
$etabNom  = e($etab['nom']);
$etabSlug = e($etab['slug']);
$o   = $old ?? [];
$err = $errors ?? [];

// Inscription payante → parcours en 3 étapes (paiement), sinon 2 étapes
$payant     = ((int)($etab['prix_inscription'] ?? 0) + (int)($etab['frais_dossier'] ?? 0)) > 0;
$totalSteps = $payant ? 3 : 2;

$selectedFiliere = $o['filiere_visee'] ?? ($dossier['filiere_visee'] ?? '');
$selectedNiveau  = $o['niveau_entree'] ?? ($dossier['niveau_entree'] ?? '');
$selectedSpec    = $o['specialite_visee'] ?? ($dossier['specialite_visee'] ?? '');
$specialites     = $specialites ?? []; // [filière => [spécialité, …]]

// Déterminer si des pièces obligatoires existent → bloc le bouton Passer
$allPiecesFlat = [];
foreach ($pieces as $rows) {
    foreach ($rows as $p) { $allPiecesFlat[] = $p; }
}
$hasMandatory = !empty(array_filter($allPiecesFlat, fn($p) => (bool)$p['obligatoire']));

// Pièces par défaut si aucune configurée dans la DB
$defaultPieces = [
    ['id' => 0, 'libelle' => 'Passeport (ou pièce d\'identité)', 'obligatoire' => 1],
    ['id' => 0, 'libelle' => 'Diplôme du Baccalauréat', 'obligatoire' => 1],
    ['id' => 0, 'libelle' => 'Relevés de notes', 'obligatoire' => 0],
    ['id' => 0, 'libelle' => 'Lettre de motivation', 'obligatoire' => 0],
];

// Fusionner toutes les pièces configurées pour JS
$allPieces = [];
foreach ($pieces as $niveau => $rows) {
    foreach ($rows as $p) {
        $allPieces[] = $p;
    }
}
$piecesForJs = array_values(array_map(fn($p) => [
    'id'          => (int)$p['id'],
    'libelle'     => $p['libelle'],
    'obligatoire' => (bool)$p['obligatoire'],
    'niveau'      => $p['niveau'] ?? null,
], $allPieces ?: $defaultPieces));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Inscription — Étape 2 — <?= $etabNom ?></title>
  <link rel="stylesheet" href="<?= asset('orientation/candidature.css') ?>">
  <style>:root{--brand:<?= $couleur ?>}</style>
</head>
<body>

<header class="cand-topbar">
  <a href="<?= url('orientation/' . $etabSlug) ?>" class="cand-back">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
    Retour à la page école
  </a>
  <span class="cand-step-label">Étape 2 / <?= $totalSteps ?></span>
</header>

<main class="cand-page">

  <!-- Stepper -->
  <div class="stepper" aria-label="Progression">
    <div class="step done">
      <div class="step-circle">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
      </div>
      <div class="step-name">Informations</div>
    </div>
    <div class="step-connector done"></div>
    <div class="step active">
      <div class="step-circle">2</div>
      <div class="step-name">Formation &amp; dossier</div>
    </div>
    <?php if ($payant): ?>
    <div class="step-connector"></div>
    <div class="step">
      <div class="step-circle">3</div>
      <div class="step-name">Paiement</div>
    </div>
    <?php endif; ?>
  </div>

  <?php if (!empty($err['general'])): ?>
  <div class="alert alert-error" style="margin-bottom:20px">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?= e($err['general']) ?>
  </div>
  <?php endif; ?>
  <?php if (!empty($err['pieces'])): ?>
  <div class="alert alert-error" style="margin-bottom:20px">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?= e($err['pieces']) ?>
  </div>
  <?php endif; ?>

  <form method="POST" action="<?= url("orientation/{$etabSlug}/inscription/etape-2") ?>"
        enctype="multipart/form-data" novalidate>
    <input type="hidden" name="_csrf_token" value="<?= e($csrf) ?>">

    <div class="cand-card">
      <div class="cand-card-head">
        <h2>Formation &amp; dossier académique</h2>
        <p>Choisissez votre filière et déposez vos pièces justificatives. Tu peux passer cette étape et compléter plus tard.</p>
      </div>

      <!-- Filière -->
      <div class="form-section">
        <div class="section-label">Filière visée</div>

        <div class="filiere-grid" role="group" aria-label="Filière">
          <?php foreach ($filieres as $val => $lbl): ?>
          <label class="filiere-pill">
            <input type="radio" name="filiere_visee" value="<?= e($val) ?>"
                   <?= $selectedFiliere === $val ? 'checked' : '' ?>
                   id="filiere_<?= e($val) ?>">
            <span><?= e($lbl) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
        <?php if (isset($err['filiere_visee'])): ?>
        <span class="field-error"><?= e($err['filiere_visee']) ?></span>
        <?php endif; ?>

        <div class="form-group" id="specialite-group" style="display:none">
          <label for="specialite_visee">Spécialité <span class="req">*</span></label>
          <select id="specialite_visee" name="specialite_visee"
                  class="<?= isset($err['specialite_visee']) ? 'is-error' : '' ?>">
            <option value="">— Choisir —</option>
          </select>
          <?php if (isset($err['specialite_visee'])): ?>
          <span class="field-error"><?= e($err['specialite_visee']) ?></span>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label for="niveau_entree">Niveau d'entrée <span class="req">*</span></label>
          <input type="text" id="niveau_entree" name="niveau_entree"
                 value="<?= e($selectedNiveau) ?>"
                 placeholder="Ex. Licence 3, Master 1…"
                 id="niveau_entree_field"
                 class="<?= isset($err['niveau_entree']) ? 'is-error' : '' ?>">
          <?php if (isset($err['niveau_entree'])): ?><span class="field-error"><?= e($err['niveau_entree']) ?></span><?php endif; ?>
        </div>
      </div>

      <!-- Pièces justificatives -->
      <div class="form-section" id="pieces-section">
        <div class="section-label" id="pieces-label">
          Pièces justificatives
          <?php if ($selectedNiveau): ?>— <?= e($selectedNiveau) ?><?php endif; ?>
        </div>

        <?php if (!empty($piecesForJs)): ?>
        <p style="font-size:12.5px;color:var(--clr-txt-m);margin-bottom:16px">
          Le passeport (ou pièce d'identité équivalente) est obligatoire pour tous les niveaux.
          Formats acceptés : PDF, JPG, PNG (max 10 Mo par fichier).
        </p>
        <?php endif; ?>

        <div class="uploads-list" id="uploads-list">
          <?php
          $displayPieces = !empty($allPieces) ? $allPieces : $defaultPieces;
          foreach ($displayPieces as $piece):
            $pieceId = (int)($piece['id'] ?? 0);
            $inputName = $pieceId ? "piece_{$pieceId}" : 'piece_extra[]';
          ?>
          <label class="upload-zone" for="file_<?= $pieceId ?: uniqid() ?>">
            <div class="upload-ico">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            </div>
            <div class="upload-meta">
              <div class="upload-name">
                <?php if ($piece['obligatoire']): ?>
                <span class="req-dot" title="Obligatoire"></span>
                <?php endif; ?>
                <?= e($piece['libelle']) ?>
              </div>
              <div class="upload-hint">PDF, image, etc.</div>
            </div>
            <span class="upload-badge <?= $piece['obligatoire'] ? 'obligatoire' : 'optionnel' ?>">
              <?= $piece['obligatoire'] ? 'Requis' : 'Optionnel' ?>
            </span>
            <input type="file" name="<?= e($inputName) ?>"
                   id="file_<?= $pieceId ?>"
                   accept=".pdf,.jpg,.jpeg,.png,.webp"
                   class="piece-input">
          </label>
          <?php endforeach; ?>
        </div>

        <?php if (empty($displayPieces)): ?>
        <div class="alert alert-info" style="margin-top:12px">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
          Aucun document requis configuré pour cet établissement.
        </div>
        <?php endif; ?>
      </div>

    </div><!-- /cand-card -->

    <!-- Navigation -->
    <div class="nav-footer">
      <a href="<?= url("orientation/{$etabSlug}/inscription") ?>" class="btn-ghost">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
        Précédent
      </a>
      <?php if (!$hasMandatory): ?>
      <div class="nav-center">
        <button type="submit" name="passer" value="1" formnovalidate class="btn-link">
          Passer
        </button>
      </div>
      <?php else: ?>
      <div class="nav-center"></div>
      <?php endif; ?>
      <button type="submit" class="btn-primary">
        Soumettre mon dossier
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
      </button>
    </div>

  </form>
</main>

<script>
(function() {
  var piecesData = <?= json_encode($piecesForJs, JSON_UNESCAPED_UNICODE) ?>;

  // Cascade Filière → Spécialité (configurée par l'établissement)
  var specMap      = <?= json_encode($specialites, JSON_UNESCAPED_UNICODE) ?>;
  var selectedSpec = <?= json_encode($selectedSpec, JSON_UNESCAPED_UNICODE) ?>;
  var specGroup    = document.getElementById('specialite-group');
  var specSel      = document.getElementById('specialite_visee');

  function refreshSpecialites(filiere, keepOld) {
    if (!specGroup || !specSel) return;
    var specs = specMap[filiere] || [];
    specSel.innerHTML = '<option value="">— Choisir —</option>';

    if (!specs.length) {
      specGroup.style.display = 'none';
      specSel.required = false;
      specSel.value = '';
      return;
    }
    specs.forEach(function(nom) {
      var opt = document.createElement('option');
      opt.value = nom;
      opt.textContent = nom;
      if (keepOld && nom === selectedSpec) opt.selected = true;
      specSel.appendChild(opt);
    });
    specGroup.style.display = '';
    specSel.required = true;
  }

  // Pill auto-fill niveau (niveaux génériques — clés ENUM historiques + libellés)
  var filiereMap = {
    'socle_l1_l2'  : 'Licence 1',
    'licence_l3'   : 'Licence 3',
    'master_m1_m2' : 'Master 1',
    'certification': 'Certification',
    'Socle L1-L2'  : 'Licence 1',
    'Licence L3'   : 'Licence 3',
    'Master M1-M2' : 'Master 1',
    'Certification': 'Certification'
  };

  var niveauInput = document.getElementById('niveau_entree_field') || document.getElementById('niveau_entree');
  var pills = document.querySelectorAll('.filiere-pill input[type="radio"]');

  pills.forEach(function(radio) {
    radio.addEventListener('change', function() {
      if (niveauInput && !niveauInput.value.trim() && filiereMap[this.value]) {
        niveauInput.value = filiereMap[this.value];
      }
      refreshSpecialites(this.value, false);
      updatePiecesLabel(this.value);
      filterPieces(this.value);
    });
  });

  // État initial : filière déjà cochée (retour d'erreur ou dossier existant)
  var checked = document.querySelector('.filiere-pill input[type="radio"]:checked');
  if (checked) refreshSpecialites(checked.value, true);

  function updatePiecesLabel(filiere) {
    var label = document.getElementById('pieces-label');
    if (!label) return;
    var fL = {
      'socle_l1_l2': 'Socle L1-L2', 'licence_l3': 'Licence L3',
      'master_m1_m2': 'Master M1-M2', 'certification': 'Certification'
    };
    var lbl = fL[filiere] || filiere;
    label.textContent = 'Pièces justificatives' + (lbl ? ' — ' + lbl : '');
  }

  function filterPieces(filiere) {
    if (!piecesData.length) return;

    var niveauTarget = (niveauInput ? niveauInput.value.trim() : '') || (filiereMap[filiere] || '');
    var relevant = piecesData.filter(function(p) {
      return !p.niveau || p.niveau === niveauTarget || p.niveau === null;
    });
    if (!relevant.length) relevant = piecesData.filter(function(p) { return !p.niveau; });
    if (!relevant.length) relevant = piecesData;

    var list = document.getElementById('uploads-list');
    if (!list) return;

    list.innerHTML = relevant.map(function(p) {
      var name = p.id ? 'piece_' + p.id : 'piece_extra[]';
      var uid  = 'file_' + (p.id || Math.random().toString(36).slice(2));
      return '<label class="upload-zone" for="' + uid + '">'
        + '<div class="upload-ico"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>'
        + '<div class="upload-meta"><div class="upload-name">'
        + (p.obligatoire ? '<span class="req-dot"></span>' : '')
        + escHtml(p.libelle) + '</div><div class="upload-hint">PDF, image, etc.</div></div>'
        + '<span class="upload-badge ' + (p.obligatoire ? 'obligatoire' : 'optionnel') + '">'
        + (p.obligatoire ? 'Requis' : 'Optionnel') + '</span>'
        + '<input type="file" name="' + escHtml(name) + '" id="' + uid + '" accept=".pdf,.jpg,.jpeg,.png,.webp" class="piece-input">'
        + '</label>';
    }).join('');

    // Rebind file change events
    bindFileEvents(list);
  }

  function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function bindFileEvents(container) {
    container.querySelectorAll('.piece-input').forEach(function(input) {
      input.addEventListener('change', function() {
        var zone = this.closest('.upload-zone');
        if (!zone) return;
        if (this.files && this.files.length > 0) {
          zone.classList.add('has-file');
          var ico = zone.querySelector('.upload-ico');
          if (ico) {
            ico.classList.add('done');
            ico.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>';
          }
          var hint = zone.querySelector('.upload-hint');
          if (hint) hint.textContent = this.files[0].name;
        } else {
          zone.classList.remove('has-file');
        }
      });
    });
  }

  bindFileEvents(document.getElementById('uploads-list'));
}());
</script>
</body>
</html>
