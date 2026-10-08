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

// Situation de bourse — mêmes options que le parcours public.
$bourseActive  = $bourseActive  ?? true;
$bourseOptions = $bourseOptions ?? \Services\Orientation\SituationBourse::options();
$bourseNote    = $bourseNote    ?? \Services\Orientation\SituationBourse::NOTE_EXPLICATIVE;
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
        <label>Pays de provenance *</label>
        <input type="text" name="pays_provenance" value="<?= gVal($old, $dossier, 'pays_provenance', 'Gabon') ?>" required>
        <?= gEditErr($errors, 'pays_provenance') ?>
      </div>
      <div class="g-form-group">
        <label>Ville *</label>
        <input type="text" name="ville" value="<?= gVal($old, $dossier, 'ville') ?>" required>
        <?= gEditErr($errors, 'ville') ?>
      </div>
    </div>
    <div class="g-form-group">
      <label>Adresse *</label>
      <input type="text" name="adresse" value="<?= gVal($old, $dossier, 'adresse') ?>" required>
      <?= gEditErr($errors, 'adresse') ?>
    </div>
    <div class="g-form-group">
      <label>Dernier établissement fréquenté *</label>
      <input type="text" name="dernier_etablissement" value="<?= gVal($old, $dossier, 'dernier_etablissement') ?>" required>
      <?= gEditErr($errors, 'dernier_etablissement') ?>
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
        <?php
          // Liste fermée, comme dans les formulaires publics : le niveau
          // détermine le tarif appliqué au dossier (la grille de la formation
          // peut distinguer L1, L2 et L3). Un champ libre au guichet aurait
          // rouvert par la porte de service ce qu'on vient de fermer côté
          // candidat. Un dossier ancien en texte libre est retrouvé par
          // Niveau::code() et reste présélectionné.
          $niveauCourant = \Services\Orientation\Niveau::code((string)($old['niveau_entree'] ?? ($dossier['niveau_entree'] ?? '')));
        ?>
        <select name="niveau_entree" required style="width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:var(--font);font-size:13px;">
          <option value="">— Choisir —</option>
          <?php foreach (\Services\Orientation\Niveau::optionsParCycle() as $cycleLbl => $niveaux): ?>
            <optgroup label="<?= e($cycleLbl) ?>">
              <?php foreach ($niveaux as $code => $libelle): ?>
                <option value="<?= e($code) ?>" <?= $niveauCourant === $code ? 'selected' : '' ?>><?= e($libelle) ?></option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
        <?= gEditErr($errors, 'niveau_entree') ?>
      </div>
    </div>

    <?php /* Série et année du bac : présentes dans le formulaire public,
             imprimées sur le récépissé — elles doivent être corrigeables ici
             sinon un dossier guichet reste définitivement incomplet. */ ?>
    <div class="g-form-row">
      <div class="g-form-group">
        <label>Série du Bac *</label>
        <input type="text" name="serie_bac" value="<?= gVal($old, $dossier, 'serie_bac') ?>" placeholder="Ex. D, C, A4…" maxlength="20" required>
        <?= gEditErr($errors, 'serie_bac') ?>
      </div>
      <div class="g-form-group">
        <label>Année d'obtention du Bac *</label>
        <input type="number" name="annee_bac" value="<?= gVal($old, $dossier, 'annee_bac') ?>"
               placeholder="Ex. <?= date('Y') ?>" min="1970" max="<?= date('Y') ?>" required>
        <?= gEditErr($errors, 'annee_bac') ?>
      </div>
    </div>
  </div>

  <?php /* ══ Situation de bourse ══
           Corrigeable au guichet (le candidat se présente pour la rectifier),
           jamais en silence : SituationBourse trace l'auteur et l'horodatage,
           et refuse de réviser une commission déjà encaissée. */ ?>
  <div class="g-card" style="margin-bottom:20px;">
    <h3 class="g-section-title">Situation de bourse<?= $bourseActive ? ' *' : '' ?></h3>
    <?php if (!$bourseActive): ?>
      <input type="hidden" name="statut_bourse" value="non_boursier">
      <p style="font-size:12px;color:var(--ink-3);margin:0;">
        Cet établissement n'accepte pas les candidatures de boursiers : le dossier reste « non boursier ».
      </p>
    <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:10px;">
        <?php foreach ($bourseOptions as $opt):
              $val = (string)$opt['valeur'];
        ?>
          <label style="display:flex;gap:11px;align-items:flex-start;padding:11px 14px;border:1.5px solid var(--bdr-s);border-radius:var(--r-md);cursor:pointer;">
            <input type="radio" name="statut_bourse" value="<?= e($val) ?>"
                   data-gbourse-choix="<?= e($val) ?>"
                   style="width:auto;margin-top:3px;flex-shrink:0;"
                   <?= gVal($old, $dossier, 'statut_bourse') === $val ? 'checked' : '' ?> required>
            <span>
              <span style="display:block;font-size:13px;font-weight:600;"><?= e($opt['libelle']) ?></span>
              <span style="display:block;font-size:11.5px;color:var(--ink-3);margin-top:2px;"><?= e($opt['aide']) ?></span>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
      <?= gEditErr($errors, 'statut_bourse') ?>

      <div data-gbourse-pour="boursier" style="margin-top:14px;" <?= gVal($old,$dossier,'statut_bourse') === 'boursier' ? '' : 'hidden' ?>>
        <div class="g-form-group">
          <label>Numéro d'attestation ANBG <span style="font-weight:500;color:var(--ink-3);">(facultatif)</span></label>
          <input type="text" name="numero_attestation_anbg" maxlength="50" value="<?= gVal($old, $dossier, 'numero_attestation_anbg') ?>">
          <?= gEditErr($errors, 'numero_attestation_anbg') ?>
        </div>
      </div>

      <p style="font-size:11.5px;color:var(--ink-3);margin:14px 0 0;line-height:1.5;"><?= e($bourseNote) ?></p>

      <script>
      (function () {
          var choix = document.querySelectorAll('[data-gbourse-choix]');
          var blocs = document.querySelectorAll('[data-gbourse-pour]');
          if (!choix.length) { return; }

          function appliquer(valeur) {
              blocs.forEach(function (bloc) {
                  var concerne = bloc.getAttribute('data-gbourse-pour') === valeur;
                  bloc.hidden = !concerne;
                  bloc.querySelectorAll('input').forEach(function (champ) {
                      champ.disabled = !concerne;
                  });
              });
          }
          choix.forEach(function (radio) {
              radio.addEventListener('change', function () { appliquer(this.value); });
          });
          var coche = document.querySelector('[data-gbourse-choix]:checked');
          appliquer(coche ? coche.value : null);
      }());
      </script>
    <?php endif; ?>
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
