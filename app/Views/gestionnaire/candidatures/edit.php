<?php
// Variables: $dossier, $filieres, $errors, $old, $etab
$dossier  = $dossier  ?? [];
$filieres = $filieres ?? [];
$errors   = $errors   ?? [];
$old      = $old      ?? [];

// Valeur affichée : saisie précédente (erreur de validation) sinon valeur en base
function gVal(array $old, array $dossier, string $key, string $default = ''): string {
    $v = $old[$key] ?? $dossier[$key] ?? $default;
    return htmlspecialchars((string)($v ?? $default), ENT_QUOTES, 'UTF-8');
}
function gEditErr(array $errors, string $key): string {
    return isset($errors[$key]) ? '<div class="g-error-msg">' . htmlspecialchars($errors[$key], ENT_QUOTES, 'UTF-8') . '</div>' : '';
}
$dateNaissance = substr((string)($old['date_naissance'] ?? $dossier['date_naissance'] ?? ''), 0, 10);
?>

<div style="margin-bottom:18px;">
  <a href="<?= url('/gestionnaire/candidatures/' . ($dossier['id'] ?? '')) ?>" class="g-back-link">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M5 12l7 7M5 12l7-7"/></svg>
    Retour au dossier
  </a>
</div>

<div class="g-page-header">
  <div>
    <h2>Modifier le dossier #<?= (int)($dossier['id'] ?? 0) ?></h2>
    <p>Corrigez les informations du candidat — le statut et les pièces ne sont pas modifiés ici.</p>
  </div>
</div>

<form action="<?= url('/gestionnaire/candidatures/' . ($dossier['id'] ?? '') . '/modifier') ?>" method="post" style="max-width:760px;">
  <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">

  <!-- Identité -->
  <div class="g-card" style="margin-bottom:20px;">
    <h3 class="g-section-title">Identité</h3>
    <div class="g-form-group">
      <label>Nom complet *</label>
      <input type="text" name="nom_complet" value="<?= gVal($old, $dossier, 'nom_complet') ?>" required>
      <?= gEditErr($errors, 'nom_complet') ?>
    </div>
    <div class="g-form-row">
      <div class="g-form-group">
        <label>Genre *</label>
        <select name="genre" required>
          <option value="">Choisir…</option>
          <option value="homme" <?= gVal($old,$dossier,'genre')==='homme'?'selected':'' ?>>Homme</option>
          <option value="femme" <?= gVal($old,$dossier,'genre')==='femme'?'selected':'' ?>>Femme</option>
        </select>
        <?= gEditErr($errors, 'genre') ?>
      </div>
      <div class="g-form-group">
        <label>Date de naissance *</label>
        <input type="date" name="date_naissance" value="<?= e($dateNaissance) ?>" required>
        <?= gEditErr($errors, 'date_naissance') ?>
      </div>
    </div>
    <div class="g-form-row">
      <div class="g-form-group">
        <label>Lieu de naissance *</label>
        <input type="text" name="lieu_naissance" value="<?= gVal($old, $dossier, 'lieu_naissance') ?>" required>
        <?= gEditErr($errors, 'lieu_naissance') ?>
      </div>
      <div class="g-form-group">
        <label>Nationalité *</label>
        <input type="text" name="nationalite" value="<?= gVal($old, $dossier, 'nationalite') ?>" required>
        <?= gEditErr($errors, 'nationalite') ?>
      </div>
    </div>
  </div>

  <!-- Contact -->
  <div class="g-card" style="margin-bottom:20px;">
    <h3 class="g-section-title">Contact</h3>
    <div class="g-form-group">
      <label>E-mail *</label>
      <input type="email" name="email" value="<?= gVal($old, $dossier, 'email') ?>" required>
      <?= gEditErr($errors, 'email') ?>
    </div>
    <div class="g-form-row">
      <div class="g-form-group">
        <label>Indicatif</label>
        <select name="indicatif_tel">
          <?php foreach (['+241'=>'Gabon','+237'=>'Cameroun','+225'=>'Côte d\'Ivoire','+242'=>'Congo','+243'=>'RDC','+33'=>'France'] as $code => $pays): ?>
            <option value="<?= $code ?>" <?= gVal($old,$dossier,'indicatif_tel','+241')===$code?'selected':'' ?>><?= $code ?> <?= $pays ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="g-form-group">
        <label>Téléphone *</label>
        <input type="tel" name="telephone" value="<?= gVal($old, $dossier, 'telephone') ?>" required>
        <?= gEditErr($errors, 'telephone') ?>
      </div>
    </div>
    <div class="g-form-row">
      <div class="g-form-group">
        <label>Pays de provenance</label>
        <input type="text" name="pays_provenance" value="<?= gVal($old, $dossier, 'pays_provenance') ?>">
      </div>
      <div class="g-form-group">
        <label>Ville</label>
        <input type="text" name="ville" value="<?= gVal($old, $dossier, 'ville') ?>">
      </div>
    </div>
    <div class="g-form-group">
      <label>Adresse</label>
      <input type="text" name="adresse" value="<?= gVal($old, $dossier, 'adresse') ?>">
    </div>
    <div class="g-form-group">
      <label>Dernier établissement fréquenté</label>
      <input type="text" name="dernier_etablissement" value="<?= gVal($old, $dossier, 'dernier_etablissement') ?>">
    </div>
  </div>

  <!-- Formation -->
  <div class="g-card" style="margin-bottom:20px;">
    <h3 class="g-section-title">Formation souhaitée</h3>
    <div class="g-form-row">
      <div class="g-form-group">
        <label>Filière visée</label>
        <select name="filiere_visee" id="filiere_visee">
          <option value="">—</option>
          <?php foreach ($filieres as $val => $lbl): ?>
            <option value="<?= e($val) ?>" <?= gVal($old,$dossier,'filiere_visee')===$val?'selected':'' ?>><?= e($lbl) ?></option>
          <?php endforeach; ?>
        </select>
        <?= gEditErr($errors, 'filiere_visee') ?>
      </div>
      <div class="g-form-group" id="specialite-group" style="display:none;">
        <label>Spécialité *</label>
        <select name="specialite_visee" id="specialite_visee">
          <option value="">—</option>
        </select>
        <?= gEditErr($errors, 'specialite_visee') ?>
      </div>
      <div class="g-form-group">
        <label>Niveau d'entrée</label>
        <input type="text" name="niveau_entree" value="<?= gVal($old, $dossier, 'niveau_entree') ?>" placeholder="Ex. L1, M1…">
      </div>
    </div>
  </div>

  <script>
  (function() {
    // Cascade Filière → Spécialité (config établissement)
    var specMap = <?= json_encode($specialites ?? [], JSON_UNESCAPED_UNICODE) ?>;
    var curSpec = <?= json_encode(is_array($old) && isset($old['specialite_visee']) ? $old['specialite_visee'] : ($dossier['specialite_visee'] ?? ''), JSON_UNESCAPED_UNICODE) ?>;
    var fSel = document.getElementById('filiere_visee');
    var grp  = document.getElementById('specialite-group');
    var sSel = document.getElementById('specialite_visee');
    if (!fSel || !grp || !sSel) return;

    function refresh(keepOld) {
      var specs = specMap[fSel.value] || [];
      sSel.innerHTML = '<option value="">—</option>';
      if (!specs.length) { grp.style.display = 'none'; sSel.required = false; sSel.value = ''; return; }
      specs.forEach(function(nom) {
        var o = document.createElement('option');
        o.value = nom; o.textContent = nom;
        if (keepOld && nom === curSpec) o.selected = true;
        sSel.appendChild(o);
      });
      grp.style.display = ''; sSel.required = true;
    }
    fSel.addEventListener('change', function() { refresh(false); });
    refresh(true);
  }());
  </script>

  <div style="display:flex;gap:10px;">
    <button type="submit" class="g-btn g-btn-primary">Enregistrer les modifications</button>
    <a href="<?= url('/gestionnaire/candidatures/' . ($dossier['id'] ?? '')) ?>" class="g-btn g-btn-ghost">Annuler</a>
  </div>
</form>
