<?php
$couleur  = e($etab['couleur_principale'] ?? '#8B52FA');
$etabNom  = e($etab['nom']);
$etabSlug = e($etab['slug']);
$o   = $old ?? [];
$err = $errors ?? [];

// Adresse du compte connecté. Non nulle, le champ e-mail est verrouillé : elle
// rattache le dossier à l'espace candidat, et le serveur refuse toute autre
// valeur (CandidatureController::erreurEmailCompte).
$emailVerrouille = $emailVerrouille ?? null;

// L'établissement accepte-t-il les candidatures de boursiers ? Faux, tout le
// bloc « situation de bourse » disparaît de l'écran et le dossier part en
// « non boursier » — valeur que le serveur réimpose de toute façon
// (PolitiqueBoursiers::normaliserPost).
$bourseActive = $bourseActive ?? true;

// Fil d'étapes fourni par le contrôleur : le parcours compte toujours trois
// temps (spec §5) ; seul le libellé du troisième change selon que
// l'établissement encaisse encore en ligne ou renvoie le règlement au guichet.
$etapesLabels = $etapes['labels'] ?? ['Informations', 'Pièces du dossier', 'Confirmation'];
$totalSteps   = count($etapesLabels);

function oldE1(string $key, array $old, string $default = ''): string {
    return isset($old[$key]) ? e($old[$key]) : $default;
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Inscription, étape 1 sur <?= $totalSteps ?> - <?= $etabNom ?></title>
  <link rel="stylesheet" href="<?= asset('orientation/candidature.css') ?>">
  <style>:root{--brand:<?= $couleur ?>}</style>
</head>
<body>

<header class="cand-topbar">
  <a href="<?= url('orientation/' . $etabSlug) ?>" class="cand-back">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
    Retour à la page école
  </a>
  <span class="cand-step-label">Étape 1 / <?= $totalSteps ?></span>
</header>

<main class="cand-page">

  <!-- Chapeau : le fil d'étapes seul n'apprend rien au candidat sur ce qu'il
       commence. Trois lignes situent la démarche et son exigence. -->
  <div class="cand-lede">
    <span class="kicker">Inscription <?= $etabNom ?></span>
    <h1>Vos informations</h1>
    <p>Elles constituent votre dossier candidat et seront vérifiées sur pièces.
    Saisissez-les telles qu'elles figurent sur vos documents d'état civil.</p>
  </div>


  <!-- Stepper -->
  <div class="stepper" aria-label="Progression">
    <?php foreach ($etapesLabels as $i => $label): ?>
      <?php if ($i > 0): ?><div class="step-connector"></div><?php endif; ?>
      <div class="step<?= $i === 0 ? ' active' : '' ?>">
        <div class="step-circle"><?= $i + 1 ?></div>
        <div class="step-name"><?= e($label) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if (!empty($err['general'])): ?>
  <div class="alert alert-error" style="margin-bottom:20px">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?= e($err['general']) ?>
  </div>
  <?php endif; ?>

  <form method="POST" action="<?= url("orientation/{$etabSlug}/inscription/etape-1") ?>" novalidate>
    <input type="hidden" name="_csrf_token" value="<?= e($csrf) ?>">

    <!-- Le formulaire tenait dans une seule carte de dix-huit champs. Il est
         découpé selon ce que le candidat va chercher dans ses papiers :
         son état civil, ses coordonnées, puis son parcours. Trois cartes, trois
         moments — et un formulaire interrompu se reprend plus facilement. -->
    <div class="cand-card">
      <div class="cand-card-head">
        <h2>Identité</h2>
        <p>Telle qu'elle figure sur votre pièce d'identité ou votre acte de naissance.</p>
      </div>

      <div class="form-section">

        <div class="form-row full">
          <div class="form-group">
            <label for="nom_complet">Nom complet <span class="req">*</span></label>
            <input type="text" id="nom_complet" name="nom_complet"
                   value="<?= oldE1('nom_complet', $o) ?>"
                   placeholder="Ex. Christian ONDO"
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
              <option value="homme" <?= oldE1('genre', $o) === 'homme' ? 'selected' : '' ?>>Homme</option>
              <option value="femme" <?= oldE1('genre', $o) === 'femme' ? 'selected' : '' ?>>Femme</option>
            </select>
            <?php if (isset($err['genre'])): ?><span class="field-error"><?= e($err['genre']) ?></span><?php endif; ?>
          </div>
          <div class="form-group">
            <label for="date_naissance">Date de naissance <span class="req">*</span></label>
            <input type="date" id="date_naissance" name="date_naissance" autocomplete="bday"
                   value="<?= oldE1('date_naissance', $o) ?>"
                   min="1950-01-01" max="<?= date('Y-m-d') ?>"
                   class="<?= isset($err['date_naissance']) ? 'is-error' : '' ?>" required>
            <?php if (isset($err['date_naissance'])): ?><span class="field-error"><?= e($err['date_naissance']) ?></span><?php endif; ?>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="lieu_naissance">Lieu de naissance <span class="req">*</span></label>
            <input type="text" id="lieu_naissance" name="lieu_naissance" autocomplete="off"
                   value="<?= oldE1('lieu_naissance', $o) ?>"
                   placeholder="Ex. Libreville"
                   class="<?= isset($err['lieu_naissance']) ? 'is-error' : '' ?>" required>
            <?php if (isset($err['lieu_naissance'])): ?><span class="field-error"><?= e($err['lieu_naissance']) ?></span><?php endif; ?>
          </div>
          <div class="form-group">
            <label for="nationalite">Nationalité <span class="req">*</span></label>
            <input type="text" id="nationalite" name="nationalite" autocomplete="off"
                   value="<?= oldE1('nationalite', $o) ?: 'Gabonaise' ?>"
                   class="<?= isset($err['nationalite']) ? 'is-error' : '' ?>" required>
            <?php if (isset($err['nationalite'])): ?><span class="field-error"><?= e($err['nationalite']) ?></span><?php endif; ?>
          </div>
        </div>

      </div><!-- /form-section -->
    </div><!-- /cand-card Identité -->

    <div class="cand-card">
      <div class="cand-card-head">
        <h2>Coordonnées</h2>
        <p>C'est par là que l'établissement vous joindra pour la suite de votre dossier.</p>
      </div>

      <div class="form-section">

        <div class="form-row">
          <div class="form-group">
            <label for="email">Adresse e-mail <span class="req">*</span></label>
            <?php if ($emailVerrouille !== null): ?>
              <!-- Champ verrouillé : l'adresse du compte est la clé qui relie
                   le dossier à l'espace candidat. Le `readonly` n'est qu'un
                   confort ; la barrière est côté serveur. -->
              <div class="field-lock">
                <input type="email" id="email" name="email"
                       value="<?= e($emailVerrouille) ?>"
                       readonly aria-readonly="true" autocomplete="email" required
                       class="is-locked <?= isset($err['email']) ? 'is-error' : '' ?>">
                <svg class="field-lock-ic" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><rect x="4" y="11" width="16" height="9" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
              </div>
              <span class="field-note">Adresse de votre compte. C'est elle qui rattache ce dossier à votre espace, et c'est à elle que vos documents seront envoyés.</span>
            <?php else: ?>
              <input type="email" id="email" name="email"
                     value="<?= oldE1('email', $o) ?>"
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
                <option value="<?= e($code) ?>" <?= oldE1('indicatif_tel', $o, '+241') === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <input type="tel" id="telephone" name="telephone" autocomplete="tel-national"
                     value="<?= oldE1('telephone', $o) ?>"
                     placeholder="07 XX XX XX"
                     class="tel-num <?= isset($err['telephone']) ? 'is-error' : '' ?>" required>
            </div>
            <?php if (isset($err['telephone'])): ?><span class="field-error"><?= e($err['telephone']) ?></span><?php endif; ?>
          </div>
        </div>

        <div class="form-row full">
          <div class="form-group">
            <label for="adresse">Adresse complète <span class="req">*</span></label>
            <input type="text" id="adresse" name="adresse" autocomplete="street-address"
                   value="<?= oldE1('adresse', $o) ?>"
                   placeholder="Quartier, rue, n°…"
                   class="<?= isset($err['adresse']) ? 'is-error' : '' ?>" required>
            <?php if (isset($err['adresse'])): ?><span class="field-error"><?= e($err['adresse']) ?></span><?php endif; ?>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="ville">Ville <span class="req">*</span></label>
            <input type="text" id="ville" name="ville" autocomplete="address-level2"
                   value="<?= oldE1('ville', $o) ?>"
                   placeholder="Ex. Libreville"
                   class="<?= isset($err['ville']) ? 'is-error' : '' ?>" required>
            <?php if (isset($err['ville'])): ?><span class="field-error"><?= e($err['ville']) ?></span><?php endif; ?>
          </div>
          <div class="form-group">
            <label for="pays_provenance">Pays de provenance <span class="req">*</span></label>
            <input type="text" id="pays_provenance" name="pays_provenance" autocomplete="country-name"
                   value="<?= oldE1('pays_provenance', $o) ?: 'Gabon' ?>"
                   class="<?= isset($err['pays_provenance']) ? 'is-error' : '' ?>" required>
            <?php if (isset($err['pays_provenance'])): ?><span class="field-error"><?= e($err['pays_provenance']) ?></span><?php endif; ?>
          </div>
        </div>

      </div><!-- /form-section -->
    </div><!-- /cand-card Coordonnées -->

    <div class="cand-card">
      <div class="cand-card-head">
        <h2><?= $bourseActive ? 'Parcours et bourse' : 'Parcours' ?></h2>
        <p><?= $bourseActive
              ? 'Votre réponse sur la bourse détermine les pièces demandées à l\'étape suivante.'
              : 'Ces informations complètent votre dossier de candidature.' ?></p>
      </div>

      <div class="form-section">

        <div class="form-row full">
          <div class="form-group">
            <label for="dernier_etablissement">Dernier établissement fréquenté <span class="req">*</span></label>
            <input type="text" id="dernier_etablissement" name="dernier_etablissement"
                   value="<?= oldE1('dernier_etablissement', $o) ?>"
                   placeholder="Ex. Lycée Léon Mba"
                   class="<?= isset($err['dernier_etablissement']) ? 'is-error' : '' ?>" required>
            <?php if (isset($err['dernier_etablissement'])): ?><span class="field-error"><?= e($err['dernier_etablissement']) ?></span><?php endif; ?>
          </div>
        </div>

        <!-- ══ Situation de bourse (spec §5.2) ══
             La question appartient à l'étape 1 parce que c'est une déclaration,
             au même titre que les autres informations saisies ici. Sa réponse
             détermine la liste des pièces demandées à l'étape suivante. -->
        <?php
        // Établissement fermé aux boursiers : le bloc entier disparaît — la
        // question n'a pas de réponse utile ici — et un champ caché part en
        // « non boursier ». Ce champ n'est PAS la garantie : le serveur
        // réimpose la valeur quoi qu'il reçoive (PolitiqueBoursiers).
        ?>
        <?php if (!$bourseActive): ?>
          <input type="hidden" name="statut_bourse" value="non_boursier">
        <?php else: ?>
        <div class="bourse-block">
          <h3 id="bourse-titre">Votre situation de bourse <span class="req">*</span></h3>
          <p class="bourse-lead">Une seule réponse. Elle détermine les pièces qui vous seront demandées à l'étape suivante.</p>

          <div class="bourse-options" role="radiogroup" aria-labelledby="bourse-titre">
            <?php foreach ($bourseOptions as $opt):
                $val     = $opt['valeur'];
                $checked = ($o['statut_bourse'] ?? '') === $val;
            ?>
            <label class="bourse-option">
              <input type="radio" name="statut_bourse" value="<?= e($val) ?>"
                     data-bourse-choix="<?= e($val) ?>"
                     <?= $checked ? 'checked' : '' ?> required>
              <span class="bourse-option-body">
                <span class="bourse-radio" aria-hidden="true"></span>
                <span class="bourse-option-text">
                  <span class="bourse-option-title"><?= e($opt['libelle']) ?></span>
                  <span class="bourse-option-hint"><?= e($opt['aide']) ?></span>
                </span>
              </span>
            </label>
            <?php endforeach; ?>
          </div>

          <?php if (isset($err['statut_bourse'])): ?>
            <span class="field-error" style="margin-top:8px"><?= e($err['statut_bourse']) ?></span>
          <?php endif; ?>

          <!-- Champs conditionnels. Le `hidden` initial est piloté par le JS ;
               si le JS ne s'exécute pas, tout reste visible et la validation
               serveur fait foi de toute façon. -->
          <div class="bourse-conditional" id="bourse-champ-boursier"
               data-bourse-pour="boursier"
               <?= ($o['statut_bourse'] ?? '') === 'boursier' ? '' : 'hidden' ?>>
            <div class="form-group">
              <label for="numero_attestation_anbg">Numéro d'attestation ANBG <span class="upload-badge optionnel">Facultatif</span></label>
              <input type="text" id="numero_attestation_anbg" name="numero_attestation_anbg"
                     value="<?= oldE1('numero_attestation_anbg', $o) ?>"
                     placeholder="Ex. ANBG/2026/014782" maxlength="50"
                     class="<?= isset($err['numero_attestation_anbg']) ? 'is-error' : '' ?>">
              <?php if (isset($err['numero_attestation_anbg'])): ?>
                <span class="field-error"><?= e($err['numero_attestation_anbg']) ?></span>
              <?php else: ?>
                <span class="upload-hint">Si vous ne l'avez pas sous la main, laissez vide : l'attestation elle-même sera demandée à l'étape suivante.</span>
              <?php endif; ?>
            </div>
          </div>

          <?php /* La note est portée par un filet à gauche, plus par une
                   pastille d'information : une icône par bloc de texte finit
                   par meubler l'écran sans rien y ajouter. */ ?>
          <p class="bourse-note"><?= e($bourseNote) ?></p>
        </div>
        <?php endif; ?>

      </div><!-- /form-section -->
    </div><!-- /cand-card -->

    <!-- Navigation
         Le bouton « Précédent » désactivé occupait la moitié du pied de page
         pour ne rien faire : à la première étape il n'y a pas d'arrière, et le
         retour à la page école est déjà dans la barre du haut. Le pied ne
         porte donc que l'action réelle. -->
    <div class="nav-footer nav-end">
      <button type="submit" class="btn-primary">
        Continuer
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
      </button>
    </div>

  </form>
</main>

<script>
/* Champs conditionnels du bloc bourse.
   Confort de saisie uniquement : la validation qui compte est celle du
   serveur (SituationBourse::valider). Retirer ce script, ou trafiquer le
   DOM, ne permet donc rien — au pire le formulaire redevient entièrement
   visible. */
(function () {
    var choix   = document.querySelectorAll('[data-bourse-choix]');
    var blocs   = document.querySelectorAll('[data-bourse-pour]');
    if (!choix.length || !blocs.length) { return; }

    function appliquer(valeur) {
        blocs.forEach(function (bloc) {
            var concerne = bloc.getAttribute('data-bourse-pour') === valeur;
            bloc.hidden  = !concerne;

            // Un champ masqué ne doit pas rester "required" : le navigateur
            // refuserait de soumettre en pointant un champ invisible.
            bloc.querySelectorAll('input, select, textarea').forEach(function (champ) {
                if (champ.dataset.requis === undefined) {
                    champ.dataset.requis = champ.required ? '1' : '0';
                }
                champ.required = concerne && champ.dataset.requis === '1';
                if (!concerne) { champ.value = ''; }
            });
        });
    }

    choix.forEach(function (radio) {
        radio.addEventListener('change', function () { appliquer(this.value); });
    });

    var coche = document.querySelector('[data-bourse-choix]:checked');
    appliquer(coche ? coche.value : null);

    // Deux situations seulement — « je ne suis pas boursier » et « je suis
    // boursier ». Le seul champ conditionnel est le numéro d'attestation, et
    // il reste facultatif : ni ici, ni dans le HTML, ni côté serveur
    // (SituationBourse::valider). Un champ vide laisse la colonne à NULL.
}());
</script>

</body>
</html>
