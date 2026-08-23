<?php
$couleur = e($etab['couleur_principale'] ?? '#8B52FA');
$etabNom = e($etab['nom']);
$etabSlug = e($etab['slug']);
$o = $old ?? [];
$err = $errors ?? [];

function oldVal(string $key, array $old, string $default = ''): string {
    return isset($old[$key]) ? e($old[$key]) : $default;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Préinscription — <?= $etabNom ?></title>
  <link rel="stylesheet" href="<?= asset('orientation/candidature.css') ?>">
  <style>:root{--brand:<?= $couleur ?>}</style>
</head>
<body>

<!-- Top bar -->
<header class="cand-topbar">
  <a href="<?= url('orientation/' . $etabSlug) ?>" class="cand-back">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
    Retour à la page école
  </a>
  <span class="cand-badge">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
    Préinscription gratuite
  </span>
</header>

<main class="cand-page">

  <!-- Hero -->
  <div class="cand-hero">
    <h1>Préinscription <span class="accent">en ligne</span></h1>
    <p>La préinscription est <strong>gratuite et instantanée</strong>. Remplissez le formulaire, téléchargez votre attestation de préinscription officielle, puis finalisez votre inscription quand vous le souhaitez.</p>
  </div>

  <!-- Error général -->
  <?php if (!empty($err['general'])): ?>
  <div class="alert alert-error" style="margin-bottom:20px">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?= e($err['general']) ?>
  </div>
  <?php endif; ?>

  <form method="POST" action="<?= url("orientation/{$etabSlug}/preinscription") ?>" novalidate>
    <input type="hidden" name="_csrf_token" value="<?= e($csrf) ?>">

    <div class="cand-card">

      <!-- Section Identité -->
      <div class="form-section">
        <div class="section-label">Identité</div>

        <div class="form-row full">
          <div class="form-group">
            <label for="nom_complet">Prénom &amp; Nom <span class="req">*</span></label>
            <input type="text" id="nom_complet" name="nom_complet"
                   value="<?= oldVal('nom_complet', $o) ?>"
                   placeholder="Ex. Ferdy NGUEMA"
                   class="<?= isset($err['nom_complet']) ? 'is-error' : '' ?>"
                   required autocomplete="name">
            <?php if (isset($err['nom_complet'])): ?><span class="field-error"><?= e($err['nom_complet']) ?></span><?php endif; ?>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="genre">Genre <span class="req">*</span></label>
            <select id="genre" name="genre" class="<?= isset($err['genre']) ? 'is-error' : '' ?>" required>
              <option value="">— Choisir —</option>
              <option value="homme" <?= oldVal('genre', $o) === 'homme' ? 'selected' : '' ?>>Homme</option>
              <option value="femme" <?= oldVal('genre', $o) === 'femme' ? 'selected' : '' ?>>Femme</option>
            </select>
            <?php if (isset($err['genre'])): ?><span class="field-error"><?= e($err['genre']) ?></span><?php endif; ?>
          </div>
          <div class="form-group">
            <label for="date_naissance">Date de naissance <span class="req">*</span></label>
            <input type="date" id="date_naissance" name="date_naissance"
                   value="<?= oldVal('date_naissance', $o) ?>"
                   min="1950-01-01" max="<?= date('Y-m-d') ?>"
                   class="<?= isset($err['date_naissance']) ? 'is-error' : '' ?>" required>
            <?php if (isset($err['date_naissance'])): ?><span class="field-error"><?= e($err['date_naissance']) ?></span><?php endif; ?>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="lieu_naissance">Lieu de naissance <span class="req">*</span></label>
            <input type="text" id="lieu_naissance" name="lieu_naissance"
                   value="<?= oldVal('lieu_naissance', $o) ?>"
                   placeholder="Ex. Libreville"
                   class="<?= isset($err['lieu_naissance']) ? 'is-error' : '' ?>" required>
            <?php if (isset($err['lieu_naissance'])): ?><span class="field-error"><?= e($err['lieu_naissance']) ?></span><?php endif; ?>
          </div>
          <div class="form-group">
            <label for="nationalite">Nationalité <span class="req">*</span></label>
            <input type="text" id="nationalite" name="nationalite"
                   value="<?= oldVal('nationalite', $o) ?: 'Gabonaise' ?>"
                   class="<?= isset($err['nationalite']) ? 'is-error' : '' ?>" required>
            <?php if (isset($err['nationalite'])): ?><span class="field-error"><?= e($err['nationalite']) ?></span><?php endif; ?>
          </div>
        </div>
      </div><!-- /Identité -->

      <!-- Section Contact -->
      <div class="form-section">
        <div class="section-label">Contact</div>

        <div class="form-row">
          <div class="form-group">
            <label for="email">E-mail <span class="req">*</span></label>
            <input type="email" id="email" name="email"
                   value="<?= oldVal('email', $o) ?>"
                   placeholder="prenom@exemple.com"
                   class="<?= isset($err['email']) ? 'is-error' : '' ?>"
                   required autocomplete="email">
            <?php if (isset($err['email'])): ?><span class="field-error"><?= e($err['email']) ?></span><?php endif; ?>
          </div>
          <div class="form-group">
            <label for="telephone">Téléphone <span class="req">*</span></label>
            <div class="tel-group">
              <select name="indicatif_tel" class="tel-code" aria-label="Indicatif pays">
                <?php foreach ($indicatifs as $code => $label): ?>
                <option value="<?= e($code) ?>" <?= oldVal('indicatif_tel', $o, '+241') === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <input type="tel" id="telephone" name="telephone"
                     value="<?= oldVal('telephone', $o) ?>"
                     placeholder="07 XX XX XX"
                     class="tel-num <?= isset($err['telephone']) ? 'is-error' : '' ?>" required>
            </div>
            <?php if (isset($err['telephone'])): ?><span class="field-error"><?= e($err['telephone']) ?></span><?php endif; ?>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="pays_provenance">Pays de provenance <span class="req">*</span></label>
            <input type="text" id="pays_provenance" name="pays_provenance"
                   value="<?= oldVal('pays_provenance', $o) ?: 'Gabon' ?>"
                   class="<?= isset($err['pays_provenance']) ? 'is-error' : '' ?>" required>
            <?php if (isset($err['pays_provenance'])): ?><span class="field-error"><?= e($err['pays_provenance']) ?></span><?php endif; ?>
          </div>
          <div class="form-group">
            <label for="ville">Ville <span class="req">*</span></label>
            <input type="text" id="ville" name="ville"
                   value="<?= oldVal('ville', $o) ?>"
                   placeholder="Ex. Libreville"
                   class="<?= isset($err['ville']) ? 'is-error' : '' ?>" required>
            <?php if (isset($err['ville'])): ?><span class="field-error"><?= e($err['ville']) ?></span><?php endif; ?>
          </div>
        </div>

        <div class="form-row full">
          <div class="form-group">
            <label for="adresse">Adresse <span class="req">*</span></label>
            <input type="text" id="adresse" name="adresse"
                   value="<?= oldVal('adresse', $o) ?>"
                   placeholder="Quartier, rue…"
                   class="<?= isset($err['adresse']) ? 'is-error' : '' ?>" required>
            <?php if (isset($err['adresse'])): ?><span class="field-error"><?= e($err['adresse']) ?></span><?php endif; ?>
          </div>
        </div>
      </div><!-- /Contact -->

      <!-- Section Formation souhaitée -->
      <div class="form-section">
        <div class="section-label">Formation souhaitée</div>

        <div class="form-row">
          <div class="form-group">
            <label for="filiere_visee">Filière <span class="req">*</span></label>
            <select id="filiere_visee" name="filiere_visee"
                    class="<?= isset($err['filiere_visee']) ? 'is-error' : '' ?>" required>
              <option value="">— Choisir —</option>
              <?php foreach ($filieres as $val => $lbl): ?>
              <option value="<?= e($val) ?>" <?= oldVal('filiere_visee', $o) === $val ? 'selected' : '' ?>><?= e($lbl) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($err['filiere_visee'])): ?><span class="field-error"><?= e($err['filiere_visee']) ?></span><?php endif; ?>
          </div>
          <div class="form-group" id="specialite-group" style="display:none">
            <label for="specialite_visee">Spécialité <span class="req">*</span></label>
            <select id="specialite_visee" name="specialite_visee"
                    class="<?= isset($err['specialite_visee']) ? 'is-error' : '' ?>">
              <option value="">— Choisir —</option>
            </select>
            <?php if (isset($err['specialite_visee'])): ?><span class="field-error"><?= e($err['specialite_visee']) ?></span><?php endif; ?>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="niveau_entree">Niveau d'entrée <span class="req">*</span></label>
            <input type="text" id="niveau_entree" name="niveau_entree"
                   value="<?= oldVal('niveau_entree', $o) ?>"
                   placeholder="Ex. Licence 3"
                   class="<?= isset($err['niveau_entree']) ? 'is-error' : '' ?>" required>
            <?php if (isset($err['niveau_entree'])): ?><span class="field-error"><?= e($err['niveau_entree']) ?></span><?php endif; ?>
          </div>
        </div>

        <div class="form-row full">
          <div class="form-group">
            <label for="dernier_etablissement">Dernier établissement fréquenté <span class="req">*</span></label>
            <input type="text" id="dernier_etablissement" name="dernier_etablissement"
                   value="<?= oldVal('dernier_etablissement', $o) ?>"
                   placeholder="Ex. Lycée Léon Mba"
                   class="<?= isset($err['dernier_etablissement']) ? 'is-error' : '' ?>" required>
            <?php if (isset($err['dernier_etablissement'])): ?><span class="field-error"><?= e($err['dernier_etablissement']) ?></span><?php endif; ?>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="serie_bac">Série du Bac <span class="req">*</span></label>
            <input type="text" id="serie_bac" name="serie_bac"
                   value="<?= oldVal('serie_bac', $o) ?>"
                   placeholder="Ex. D, C, A4…"
                   class="<?= isset($err['serie_bac']) ? 'is-error' : '' ?>" required>
            <?php if (isset($err['serie_bac'])): ?><span class="field-error"><?= e($err['serie_bac']) ?></span><?php endif; ?>
          </div>
          <div class="form-group">
            <label for="annee_bac">Année d'obtention du Bac <span class="req">*</span></label>
            <input type="number" id="annee_bac" name="annee_bac"
                   value="<?= oldVal('annee_bac', $o) ?>"
                   placeholder="Ex. <?= date('Y') ?>"
                   min="1970" max="<?= date('Y') ?>"
                   class="<?= isset($err['annee_bac']) ? 'is-error' : '' ?>" required>
            <?php if (isset($err['annee_bac'])): ?><span class="field-error"><?= e($err['annee_bac']) ?></span><?php endif; ?>
          </div>
        </div>
      </div><!-- /Formation -->

      <!-- Info banner -->
      <div class="info-banner">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;margin-top:1px"><polyline points="20 6 9 17 4 12"/></svg>
        <span><strong>Préinscription 100 % gratuite</strong> — Aucun paiement n'est requis à cette étape. L'attestation de préinscription sera générée et téléchargeable dès la validation.</span>
      </div>

    </div><!-- /cand-card -->

    <!-- Footer -->
    <div class="cand-footer">
      <?php if ((bool)($etab['inscription_active'] ?? false)): ?>
      <a href="<?= url("orientation/{$etabSlug}/inscription") ?>" class="btn-link">
        Passer à l'inscription payante →
      </a>
      <?php else: ?>
      <span></span>
      <?php endif; ?>
      <button type="submit" class="btn-primary">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        Valider &amp; générer l'attestation
      </button>
    </div>

  </form>
</main>

<script>
(function() {
  // Cascade Filière → Spécialité (configurée par l'établissement)
  var specMap = <?= json_encode($specialites ?? [], JSON_UNESCAPED_UNICODE) ?>;
  var oldSpec = <?= json_encode($o['specialite_visee'] ?? '', JSON_UNESCAPED_UNICODE) ?>;

  var filiereSel = document.getElementById('filiere_visee');
  var specGroup  = document.getElementById('specialite-group');
  var specSel    = document.getElementById('specialite_visee');
  if (!filiereSel || !specGroup || !specSel) return;

  function refreshSpecialites(keepOld) {
    var specs = specMap[filiereSel.value] || [];
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
      if (keepOld && nom === oldSpec) opt.selected = true;
      specSel.appendChild(opt);
    });
    specGroup.style.display = '';
    specSel.required = true;
  }

  filiereSel.addEventListener('change', function() { refreshSpecialites(false); });
  refreshSpecialites(true); // état initial (retour d'erreur de validation)
}());
</script>

</body>
</html>
