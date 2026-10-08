<?php
$couleur = e($etab['couleur_principale'] ?? '#8B52FA');
$etabNom = e($etab['nom']);
$etabSlug = e($etab['slug']);
$o = $old ?? [];
$err = $errors ?? [];

// Adresse du compte connecté. Non nulle, le champ e-mail est verrouillé :
// c'est elle qui rattache le dossier à l'espace candidat, et le serveur
// refuse toute autre valeur (CandidatureController::erreurEmailCompte).
$emailVerrouille = $emailVerrouille ?? null;

function oldVal(string $key, array $old, string $default = ''): string {
    return isset($old[$key]) ? e($old[$key]) : $default;
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Préinscription - <?= $etabNom ?></title>
  <link rel="stylesheet" href="<?= asset('orientation/candidature.css') ?>">
  <style>:root{--brand:<?= $couleur ?>}</style>
</head>
<body>

<header class="cand-topbar">
  <a href="<?= url('orientation/' . $etabSlug) ?>" class="cand-back">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
    Retour à la page école
  </a>
  <?php /* La coche du badge illustrait le mot « gratuite » écrit à côté. */ ?>
  <span class="cand-badge">Préinscription gratuite</span>
</header>

<main class="cand-page">

  <?php /* Le titre était centré, en deux teintes, au-dessus d'un paragraphe
           de quarante mots lui aussi centré : la seule page du parcours à
           être composée ainsi. Elle adopte le chapeau des trois étapes. */ ?>
  <div class="cand-lede">
    <span class="kicker">Préinscription <?= $etabNom ?></span>
    <h1>Votre préinscription</h1>
    <p>Elle est gratuite, immédiate, et ne vous engage à rien. À la validation,
    votre attestation de préinscription est générée et téléchargeable aussitôt.</p>
  </div>

  <!-- Error général -->
  <?php if (!empty($err['general'])): ?>
  <div class="alert alert-error" style="margin-bottom:20px">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
    <?= e($err['general']) ?>
  </div>
  <?php endif; ?>

  <form method="POST" action="<?= url("orientation/{$etabSlug}/preinscription") ?>" novalidate>
    <input type="hidden" name="_csrf_token" value="<?= e($csrf) ?>">

    <?php /* Le formulaire tenait dans une seule carte de dix-huit champs,
             découpée par trois intertitres. Il passe à trois cartes, comme
             l'étape 1 de l'inscription : état civil, coordonnées, parcours.
             Un formulaire interrompu se reprend plus facilement, et les deux
             écrans se ressemblent enfin. */ ?>
    <div class="cand-card">
      <div class="cand-card-head">
        <h2>Identité</h2>
        <p>Telle qu'elle figure sur votre pièce d'identité ou votre acte de naissance.</p>
      </div>

      <div class="form-section">

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
              <option value="">Choisir</option>
              <option value="homme" <?= oldVal('genre', $o) === 'homme' ? 'selected' : '' ?>>Homme</option>
              <option value="femme" <?= oldVal('genre', $o) === 'femme' ? 'selected' : '' ?>>Femme</option>
            </select>
            <?php if (isset($err['genre'])): ?><span class="field-error"><?= e($err['genre']) ?></span><?php endif; ?>
          </div>
          <div class="form-group">
            <label for="date_naissance">Date de naissance <span class="req">*</span></label>
            <input type="date" id="date_naissance" name="date_naissance" autocomplete="bday"
                   value="<?= oldVal('date_naissance', $o) ?>"
                   min="1950-01-01" max="<?= date('Y-m-d') ?>"
                   class="<?= isset($err['date_naissance']) ? 'is-error' : '' ?>" required>
            <?php if (isset($err['date_naissance'])): ?><span class="field-error"><?= e($err['date_naissance']) ?></span><?php endif; ?>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="lieu_naissance">Lieu de naissance <span class="req">*</span></label>
            <input type="text" id="lieu_naissance" name="lieu_naissance" autocomplete="off"
                   value="<?= oldVal('lieu_naissance', $o) ?>"
                   placeholder="Ex. Libreville"
                   class="<?= isset($err['lieu_naissance']) ? 'is-error' : '' ?>" required>
            <?php if (isset($err['lieu_naissance'])): ?><span class="field-error"><?= e($err['lieu_naissance']) ?></span><?php endif; ?>
          </div>
          <div class="form-group">
            <label for="nationalite">Nationalité <span class="req">*</span></label>
            <input type="text" id="nationalite" name="nationalite" autocomplete="off"
                   value="<?= oldVal('nationalite', $o) ?: 'Gabonaise' ?>"
                   class="<?= isset($err['nationalite']) ? 'is-error' : '' ?>" required>
            <?php if (isset($err['nationalite'])): ?><span class="field-error"><?= e($err['nationalite']) ?></span><?php endif; ?>
          </div>
        </div>
      </div>
    </div><!-- /cand-card Identité -->

    <div class="cand-card">
      <div class="cand-card-head">
        <h2>Coordonnées</h2>
        <p>C'est par là que l'établissement vous joindra pour la suite de votre dossier.</p>
      </div>

      <div class="form-section">

        <div class="form-row">
          <div class="form-group">
            <label for="email">E-mail <span class="req">*</span></label>
            <?php if ($emailVerrouille !== null): ?>
              <div class="field-lock">
                <input type="email" id="email" name="email"
                       value="<?= e($emailVerrouille) ?>"
                       readonly aria-readonly="true" autocomplete="email" required
                       class="is-locked <?= isset($err['email']) ? 'is-error' : '' ?>">
                <svg class="field-lock-ic" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="4" y="11" width="16" height="9" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
              </div>
              <span class="field-note">Adresse de votre compte. C'est elle qui rattache ce dossier à votre espace.</span>
            <?php else: ?>
              <input type="email" id="email" name="email"
                     value="<?= oldVal('email', $o) ?>"
                     placeholder="prenom@exemple.com"
                     class="<?= isset($err['email']) ? 'is-error' : '' ?>"
                     required autocomplete="email">
            <?php endif; ?>
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
              <input type="tel" id="telephone" name="telephone" autocomplete="tel-national"
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
            <input type="text" id="pays_provenance" name="pays_provenance" autocomplete="country-name"
                   value="<?= oldVal('pays_provenance', $o) ?: 'Gabon' ?>"
                   class="<?= isset($err['pays_provenance']) ? 'is-error' : '' ?>" required>
            <?php if (isset($err['pays_provenance'])): ?><span class="field-error"><?= e($err['pays_provenance']) ?></span><?php endif; ?>
          </div>
          <div class="form-group">
            <label for="ville">Ville <span class="req">*</span></label>
            <input type="text" id="ville" name="ville" autocomplete="address-level2"
                   value="<?= oldVal('ville', $o) ?>"
                   placeholder="Ex. Libreville"
                   class="<?= isset($err['ville']) ? 'is-error' : '' ?>" required>
            <?php if (isset($err['ville'])): ?><span class="field-error"><?= e($err['ville']) ?></span><?php endif; ?>
          </div>
        </div>

        <div class="form-row full">
          <div class="form-group">
            <label for="adresse">Adresse <span class="req">*</span></label>
            <input type="text" id="adresse" name="adresse" autocomplete="street-address"
                   value="<?= oldVal('adresse', $o) ?>"
                   placeholder="Quartier, rue…"
                   class="<?= isset($err['adresse']) ? 'is-error' : '' ?>" required>
            <?php if (isset($err['adresse'])): ?><span class="field-error"><?= e($err['adresse']) ?></span><?php endif; ?>
          </div>
        </div>
      </div>
    </div><!-- /cand-card Coordonnées -->

    <div class="cand-card">
      <div class="cand-card-head">
        <h2>Formation souhaitée</h2>
        <p>Ces informations orientent l'étude de votre dossier par l'établissement.</p>
      </div>

      <div class="form-section">

        <div class="form-row">
          <div class="form-group">
            <label for="filiere_visee">Filière <span class="req">*</span></label>
            <select id="filiere_visee" name="filiere_visee"
                    class="<?= isset($err['filiere_visee']) ? 'is-error' : '' ?>" required>
              <option value="">Choisir</option>
              <?php foreach ($filieres as $val => $lbl): ?>
              <option value="<?= e($val) ?>" <?= oldVal('filiere_visee', $o) === $val ? 'selected' : '' ?>><?= e($lbl) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($err['filiere_visee'])): ?><span class="field-error"><?= e($err['filiere_visee']) ?></span><?php endif; ?>
          </div>
          <div class="form-group">
            <label for="niveau_entree">Niveau d'entrée <span class="req">*</span></label>
<?php
            // Liste FERMÉE : le niveau détermine désormais un PRIX (la grille
            // tarifaire de la formation peut distinguer L1, L2 et L3). Un champ
            // libre laissait passer « licence deux », qui ne correspond à aucune
            // ligne de grille — le guichet se retrouvait alors sans tarif.
            //
            // On stocke le LIBELLÉ (« Licence 2 »), pas le code : une dizaine
            // d'écrans, de PDF et d'e-mails affichent `niveau_entree` tel quel.
            // Niveau::code() refait le chemin inverse pour retrouver la valeur
            // d'un dossier ancien, saisi en texte libre.
            $niveauCourant = \Services\Orientation\Niveau::code((string)($o['niveau_entree'] ?? ''));
            ?>
            <select id="niveau_entree" name="niveau_entree"
                    class="<?= isset($err['niveau_entree']) ? 'is-error' : '' ?>" required>
              <option value="">Choisir</option>
              <?php foreach (\Services\Orientation\Niveau::optionsParCycle() as $cycleLbl => $niveaux): ?>
                <optgroup label="<?= e($cycleLbl) ?>">
                  <?php foreach ($niveaux as $code => $libelle): ?>
                    <option value="<?= e($code) ?>" <?= $niveauCourant === $code ? 'selected' : '' ?>><?= e($libelle) ?></option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endforeach; ?>
            </select>
            <?php if (isset($err['niveau_entree'])): ?><span class="field-error"><?= e($err['niveau_entree']) ?></span><?php endif; ?>
          </div>
        </div>

        <?php /* Rangée révélée par le script quand la filière choisie propose
                 des spécialités. L'identifiant est porté par la RANGÉE : le
                 script ne manipule que `style.display`, et `display:''` rend
                 alors la grille de `.form-row.full`. */ ?>
        <div class="form-row full" id="specialite-group" style="display:none">
          <div class="form-group">
            <label for="specialite_visee">Spécialité <span class="req">*</span></label>
            <select id="specialite_visee" name="specialite_visee"
                    class="<?= isset($err['specialite_visee']) ? 'is-error' : '' ?>">
              <option value="">Choisir</option>
            </select>
            <?php if (isset($err['specialite_visee'])): ?><span class="field-error"><?= e($err['specialite_visee']) ?></span><?php endif; ?>
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
      </div>
    </div><!-- /cand-card Formation souhaitée -->

    <?php /* Le bandeau vert de bas de carte répétait mot pour mot le chapeau
             de la page (« gratuite », « attestation téléchargeable »), à la
             même distance de l'œil. La réassurance descend là où la question
             se pose vraiment : juste au-dessus du bouton d'envoi. */ ?>
    <p class="cand-footer-note">Aucun paiement n'est demandé à cette étape.</p>

    <div class="cand-footer">
      <?php if ((bool)($etab['inscription_active'] ?? false)): ?>
      <?php /* La flèche était un caractère collé au libellé, et « payante »
               opposait deux parcours qui ne s'excluent pas : on préinscrit
               d'abord, on s'inscrit ensuite. */ ?>
      <a href="<?= url("orientation/{$etabSlug}/inscription") ?>" class="btn-link">
        Aller directement à l'inscription
      </a>
      <?php endif; ?>
      <button type="submit" class="btn-primary">
        Valider et générer l'attestation
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
    specSel.innerHTML = '<option value="">Choisir</option>';

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
