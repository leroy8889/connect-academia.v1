<?php
// Variables: $etab, $formations, $pieces, $filieres, $errors, $old, $typeDossier
$formations  = $formations  ?? [];
$pieces      = $pieces      ?? [];
$filieres    = $filieres    ?? [];
$errors      = $errors      ?? [];
$old         = $old         ?? [];
$typeDossier = ($typeDossier ?? 'preinscription') === 'inscription' ? 'inscription' : 'preinscription';
$isInscription = $typeDossier === 'inscription';

// Situation de bourse — mêmes options et même note que le formulaire public.
// `$bourseActive` traduit la politique de l'établissement (accepte_boursiers).
$bourseActive  = $bourseActive  ?? true;
$bourseOptions = $bourseOptions ?? \Services\Orientation\SituationBourse::options();
$bourseNote    = $bourseNote    ?? \Services\Orientation\SituationBourse::NOTE_EXPLICATIVE;

function gOld(array $old, string $key, string $default = ''): string {
    return htmlspecialchars((string)($old[$key] ?? $default), ENT_QUOTES, 'UTF-8');
}
function gErr(array $errors, string $key): string {
    return isset($errors[$key]) ? '<div class="g-error-msg">' . htmlspecialchars($errors[$key], ENT_QUOTES, 'UTF-8') . '</div>' : '';
}
?>

<div style="margin-bottom:18px;">
  <a href="<?= url('/gestionnaire/candidatures') ?>" class="g-back-link">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M5 12l7 7M5 12l7-7"/></svg>
    Retour aux dossiers
  </a>
</div>

<div class="g-page-header">
  <div>
    <h2><?= $isInscription ? 'Nouvelle inscription' : 'Nouvelle préinscription' ?></h2>
    <p><?= $isInscription
        ? 'Dossier complet saisi au guichet — pièces et paiement hors ligne inclus.'
        : 'Dossier gratuit saisi au guichet — l\'attestation officielle de préinscription est générée automatiquement.' ?></p>
  </div>
  <div class="g-seg" role="tablist" aria-label="Type de dossier">
    <a href="<?= url('/gestionnaire/candidatures/creer?type=preinscription') ?>" class="<?= !$isInscription ? 'g-seg-active' : '' ?>" role="tab" aria-selected="<?= !$isInscription ? 'true' : 'false' ?>">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
      Préinscription
    </a>
    <a href="<?= url('/gestionnaire/candidatures/creer?type=inscription') ?>" class="<?= $isInscription ? 'g-seg-active' : '' ?>" role="tab" aria-selected="<?= $isInscription ? 'true' : 'false' ?>">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6m3-3h-6"/></svg>
      Inscription
    </a>
  </div>
</div>

<form action="<?= url('/gestionnaire/candidatures/creer') ?>" method="post" enctype="multipart/form-data" style="max-width:760px;">
  <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
  <input type="hidden" name="type_dossier" value="<?= $typeDossier ?>">

  <!-- Identité -->
  <div class="g-card" style="margin-bottom:20px;">
    <h3 class="g-section-title">Identité</h3>
    <div class="g-form-group">
      <label>Nom complet *</label>
      <input type="text" name="nom_complet" value="<?= gOld($old, 'nom_complet') ?>" placeholder="Ex. Christian ONDO" required>
      <?= gErr($errors, 'nom_complet') ?>
    </div>
    <div class="g-form-row">
      <div class="g-form-group">
        <label>Genre *</label>
        <select name="genre" required>
          <option value="">Choisir…</option>
          <option value="homme" <?= gOld($old,'genre')==='homme'?'selected':'' ?>>Homme</option>
          <option value="femme" <?= gOld($old,'genre')==='femme'?'selected':'' ?>>Femme</option>
        </select>
        <?= gErr($errors, 'genre') ?>
      </div>
      <div class="g-form-group">
        <label>Date de naissance *</label>
        <input type="date" name="date_naissance" value="<?= gOld($old, 'date_naissance') ?>" required>
        <?= gErr($errors, 'date_naissance') ?>
      </div>
    </div>
    <div class="g-form-row">
      <div class="g-form-group">
        <label>Lieu de naissance *</label>
        <input type="text" name="lieu_naissance" value="<?= gOld($old, 'lieu_naissance') ?>" placeholder="Ex. Libreville" required>
        <?= gErr($errors, 'lieu_naissance') ?>
      </div>
      <div class="g-form-group">
        <label>Nationalité *</label>
        <input type="text" name="nationalite" value="<?= gOld($old, 'nationalite', 'Gabonaise') ?>" required>
        <?= gErr($errors, 'nationalite') ?>
      </div>
    </div>
  </div>

  <!-- Contact -->
  <div class="g-card" style="margin-bottom:20px;">
    <h3 class="g-section-title">Contact</h3>
    <div class="g-form-group">
      <label>E-mail *</label>
      <input type="email" name="email" value="<?= gOld($old, 'email') ?>" placeholder="candidat@email.com" required>
      <?= gErr($errors, 'email') ?>
    </div>
    <div class="g-form-row">
      <div class="g-form-group">
        <label>Indicatif</label>
        <select name="indicatif_tel">
          <?php foreach (['+241'=>'Gabon','+237'=>'Cameroun','+225'=>'Côte d\'Ivoire','+242'=>'Congo','+243'=>'RDC','+33'=>'France'] as $code => $pays): ?>
            <option value="<?= $code ?>" <?= gOld($old,'indicatif_tel','+241')===$code?'selected':'' ?>><?= $code ?> <?= $pays ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="g-form-group">
        <label>Téléphone *</label>
        <input type="tel" name="telephone" value="<?= gOld($old, 'telephone') ?>" placeholder="06 12 34 56" required>
        <?= gErr($errors, 'telephone') ?>
      </div>
    </div>
    <?php /* Ces quatre champs sont OBLIGATOIRES dans le formulaire public : un
             dossier saisi au guichet sans eux n'est pas le même dossier, et
             c'est le guichet qui produit ensuite le document officiel. */ ?>
    <div class="g-form-row">
      <div class="g-form-group">
        <label>Pays de provenance *</label>
        <input type="text" name="pays_provenance" value="<?= gOld($old, 'pays_provenance', 'Gabon') ?>" required>
        <?= gErr($errors, 'pays_provenance') ?>
      </div>
      <div class="g-form-group">
        <label>Ville *</label>
        <input type="text" name="ville" value="<?= gOld($old, 'ville') ?>" placeholder="Libreville" required>
        <?= gErr($errors, 'ville') ?>
      </div>
    </div>
    <div class="g-form-group">
      <label>Adresse *</label>
      <input type="text" name="adresse" value="<?= gOld($old, 'adresse') ?>" placeholder="Quartier, rue…" required>
      <?= gErr($errors, 'adresse') ?>
    </div>
    <div class="g-form-group">
      <label>Dernier établissement fréquenté *</label>
      <input type="text" name="dernier_etablissement" value="<?= gOld($old, 'dernier_etablissement') ?>" placeholder="Ex. Lycée Léon Mba" required>
      <?= gErr($errors, 'dernier_etablissement') ?>
    </div>
  </div>

  <!-- Formation -->
  <div class="g-card" style="margin-bottom:20px;">
    <h3 class="g-section-title">Formation souhaitée</h3>
    <div class="g-form-row">
      <div class="g-form-group">
        <label>Filière visée <?= $isInscription ? '*' : '' ?></label>
        <select name="filiere_visee" id="filiere_visee" <?= $isInscription ? 'required' : '' ?>>
          <option value="">Choisir…</option>
          <?php foreach ($filieres as $val => $lbl): ?>
            <option value="<?= e($val) ?>" <?= gOld($old,'filiere_visee')===$val?'selected':'' ?>><?= e($lbl) ?></option>
          <?php endforeach; ?>
        </select>
        <?= gErr($errors, 'filiere_visee') ?>
      </div>
      <div class="g-form-group" id="specialite-group" style="display:none;">
        <label>Spécialité *</label>
        <select name="specialite_visee" id="specialite_visee">
          <option value="">Choisir…</option>
        </select>
        <?= gErr($errors, 'specialite_visee') ?>
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
          $niveauCourant = \Services\Orientation\Niveau::code((string)($old['niveau_entree'] ?? ''));
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
        <?= gErr($errors, 'niveau_entree') ?>
      </div>
    </div>

    <?php /* ══ Baccalauréat ══
             Ces deux champs manquaient au guichet alors que le formulaire
             public les exige, et que RecepisseService les imprime sur le
             récépissé comme sur l'attestation : tout dossier saisi ici
             ressortait avec « Série du Bac : — ». */ ?>
    <div class="g-form-row">
      <div class="g-form-group">
        <label>Série du Bac *</label>
        <input type="text" name="serie_bac" value="<?= gOld($old, 'serie_bac') ?>" placeholder="Ex. D, C, A4…" maxlength="20" required>
        <?= gErr($errors, 'serie_bac') ?>
      </div>
      <div class="g-form-group">
        <label>Année d'obtention du Bac *</label>
        <input type="number" name="annee_bac" value="<?= gOld($old, 'annee_bac') ?>"
               placeholder="Ex. <?= date('Y') ?>" min="1970" max="<?= date('Y') ?>" required>
        <?= gErr($errors, 'annee_bac') ?>
      </div>
    </div>
  </div>

  <?php /* ══ Situation de bourse (spec §5.2) ══
           Le formulaire public la demande à l'étape 1 ; le guichet ne la
           demandait nulle part, et tous les dossiers saisis ici restaient
           « Non renseignée » — alors qu'elle détermine les pièces attendues
           ET la base de commission (TarifCycle::baseCommission).

           La déclaration reste celle du CANDIDAT : elle est saisie sous sa
           dictée, mais enregistrée avec le rôle « établissement » et l'identité
           de l'agent, comme l'exige SituationBourse. */ ?>
  <div class="g-card" style="margin-bottom:20px;">
    <h3 class="g-section-title">Situation de bourse<?= $bourseActive ? ' *' : '' ?></h3>
    <?php if (!$bourseActive): ?>
      <input type="hidden" name="statut_bourse" value="non_boursier">
      <p style="font-size:12px;color:var(--ink-3);margin:0;">
        Cet établissement n'accepte pas les candidatures de boursiers : le dossier est enregistré
        comme « non boursier ». Cette porte se rouvre depuis <em>Paramètres</em>.
      </p>
    <?php else: ?>
      <p style="font-size:12px;color:var(--ink-3);margin-bottom:14px;">
        Réponse du candidat. Elle détermine les pièces attendues et n'a aucune incidence sur l'acceptation du dossier.
      </p>
      <div style="display:flex;flex-direction:column;gap:10px;">
        <?php foreach ($bourseOptions as $opt):
              $val = (string)$opt['valeur'];
        ?>
          <label style="display:flex;gap:11px;align-items:flex-start;padding:11px 14px;border:1.5px solid var(--bdr-s);border-radius:var(--r-md);cursor:pointer;">
            <input type="radio" name="statut_bourse" value="<?= e($val) ?>"
                   data-gbourse-choix="<?= e($val) ?>"
                   style="width:auto;margin-top:3px;flex-shrink:0;"
                   <?= gOld($old, 'statut_bourse') === $val ? 'checked' : '' ?> required>
            <span>
              <span style="display:block;font-size:13px;font-weight:600;"><?= e($opt['libelle']) ?></span>
              <span style="display:block;font-size:11.5px;color:var(--ink-3);margin-top:2px;"><?= e($opt['aide']) ?></span>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
      <?= gErr($errors, 'statut_bourse') ?>

      <div data-gbourse-pour="boursier" style="margin-top:14px;" <?= gOld($old,'statut_bourse') === 'boursier' ? '' : 'hidden' ?>>
        <div class="g-form-group">
          <label>Numéro d'attestation ANBG <span style="font-weight:500;color:var(--ink-3);">(facultatif)</span></label>
          <input type="text" name="numero_attestation_anbg" maxlength="50"
                 value="<?= gOld($old, 'numero_attestation_anbg') ?>" placeholder="Tel qu'il figure sur l'attestation">
          <?= gErr($errors, 'numero_attestation_anbg') ?>
        </div>
      </div>

      <p style="font-size:11.5px;color:var(--ink-3);margin:14px 0 0;line-height:1.5;"><?= e($bourseNote) ?></p>

      <script>
      /* Champs conditionnels — confort de saisie uniquement : SituationBourse
         revalide intégralement côté serveur. Les champs masqués sont désactivés
         pour qu'un numéro saisi puis abandonné ne parte pas avec le formulaire. */
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
    var oldSpec = <?= json_encode(is_array($old) ? ($old['specialite_visee'] ?? '') : '', JSON_UNESCAPED_UNICODE) ?>;
    var fSel = document.getElementById('filiere_visee');
    var grp  = document.getElementById('specialite-group');
    var sSel = document.getElementById('specialite_visee');
    if (!fSel || !grp || !sSel) return;

    function refresh(keepOld) {
      var specs = specMap[fSel.value] || [];
      sSel.innerHTML = '<option value="">Choisir…</option>';
      if (!specs.length) { grp.style.display = 'none'; sSel.required = false; sSel.value = ''; return; }
      specs.forEach(function(nom) {
        var o = document.createElement('option');
        o.value = nom; o.textContent = nom;
        if (keepOld && nom === oldSpec) o.selected = true;
        sSel.appendChild(o);
      });
      grp.style.display = ''; sSel.required = true;
    }
    fSel.addEventListener('change', function() { refresh(false); });
    refresh(true);
  }());
  </script>

  <!-- Espace candidat (optionnel) -->
  <div class="g-card" style="margin-bottom:20px;">
    <h3 class="g-section-title">Espace candidat <span style="font-size:11px;font-weight:600;color:var(--ink-3);text-transform:none;letter-spacing:0;">(optionnel)</span></h3>
    <p style="font-size:12px;color:var(--ink-3);margin-bottom:14px;">
      Définis un mot de passe pour créer le compte du candidat : il pourra se connecter avec l'e-mail
      renseigné ci-dessus et suivre son dossier dans son espace. Laisse vide pour ne pas créer de compte.
    </p>
    <div class="g-form-row">
      <div class="g-form-group">
        <label>Mot de passe</label>
        <div style="position:relative;">
          <input type="password" id="compte_password" name="compte_password"
                 minlength="8" maxlength="128" autocomplete="new-password"
                 placeholder="8 caractères minimum" style="padding-right:44px;">
          <button type="button" onclick="gToggleMdp()" title="Afficher / masquer"
                  style="position:absolute;right:6px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--ink-3);padding:6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
        <?= gErr($errors, 'compte_password') ?>
      </div>
      <div class="g-form-group">
        <label>Confirmation</label>
        <input type="password" id="compte_password_confirm" name="compte_password_confirm"
               minlength="8" maxlength="128" autocomplete="new-password"
               placeholder="Retape le mot de passe">
        <?= gErr($errors, 'compte_password_confirm') ?>
      </div>
    </div>
    <button type="button" class="g-btn g-btn-ghost" onclick="gGenererMdp()" style="font-size:12px;">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0 3 3L22 7l-3-3m-3.5 3.5L19 4"/>
      </svg>
      Générer un mot de passe fort
    </button>
  </div>

  <?php if ($isInscription): ?>

  <!-- Pièces justificatives (inscription uniquement) -->
  <?php if (!empty($pieces)): ?>
  <div class="g-card" style="margin-bottom:20px;">
    <h3 class="g-section-title">Pièces justificatives</h3>
    <p style="font-size:12px;color:var(--ink-3);margin-bottom:16px;">
      Dépose les documents remis par le candidat au guichet (<?= e(\Services\Orientation\PieceDossier::consigneDepot()) ?> par pièce). Facultatif — le dossier peut être complété plus tard.
    </p>
    <div style="display:flex;flex-direction:column;gap:12px;">
      <?php foreach ($pieces as $pc): ?>
        <div style="display:flex;align-items:center;gap:14px;padding:12px 16px;background:rgba(255,255,255,.5);border:1.5px dashed var(--bdr-s);border-radius:var(--r-md);">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--ap)" stroke-width="1.8" style="flex-shrink:0;"><path d="M12 16V4m0 0L8 8m4-4l4 4"/><path d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
          <div style="flex:1;min-width:0;">
            <div style="font-size:13px;font-weight:600;">
              <?= e($pc['libelle']) ?>
              <?php if (!empty($pc['obligatoire'])): ?><span style="color:var(--coral);font-size:11px;font-weight:600;margin-left:6px;">attendu</span><?php endif; ?>
            </div>
            <?php if (!empty($pc['niveau'])): ?>
              <div style="font-size:11px;color:var(--ink-3);">Niveau : <?= e($pc['niveau']) ?></div>
            <?php endif; ?>
          </div>
          <input type="file" name="pieces[<?= (int)$pc['id'] ?>]" accept="<?= e(\Services\Orientation\PieceDossier::acceptHtml()) ?>"
                 title="<?= e(\Services\Orientation\PieceDossier::consigneDepot()) ?>"
                 style="width:auto;font-size:12px;padding:6px;background:transparent;border:none;box-shadow:none;">
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Paiement hors ligne (obligatoire pour une inscription) -->
  <div class="g-card" style="margin-bottom:24px;">
    <h3 class="g-section-title">Paiement au guichet *</h3>
    <p style="font-size:12px;color:var(--ink-3);margin-bottom:14px;">
      Une inscription est validée par un paiement hors ligne. Le dossier passera automatiquement au statut « Payé ».
      <?php if (!empty($etab['prix_inscription'])): ?>
        Tarif configuré : <strong style="color:var(--ink);"><?= number_format((int)$etab['prix_inscription'], 0, ',', ' ') ?> XAF</strong>.
      <?php endif; ?>
    </p>
    <div class="g-form-row">
      <div class="g-form-group">
        <label>Montant (XAF) *</label>
        <input type="number" name="montant_paiement" value="<?= gOld($old, 'montant_paiement', (string)(int)($etab['prix_inscription'] ?? 0)) ?>" min="1" required>
        <?= gErr($errors, 'montant_paiement') ?>
      </div>
      <div class="g-form-group">
        <label>Mode de paiement *</label>
        <select name="mode_paiement" required>
          <option value="">Choisir…</option>
          <option value="espece"       <?= gOld($old,'mode_paiement')==='espece'       ?'selected':'' ?>>Espèces</option>
          <option value="virement"     <?= gOld($old,'mode_paiement')==='virement'     ?'selected':'' ?>>Virement</option>
          <option value="mobile_money" <?= gOld($old,'mode_paiement')==='mobile_money' ?'selected':'' ?>>Mobile Money</option>
        </select>
        <?= gErr($errors, 'mode_paiement') ?>
      </div>
    </div>
  </div>

  <?php else: ?>

  <!-- Note préinscription gratuite -->
  <div class="g-card" style="margin-bottom:24px;display:flex;gap:14px;align-items:flex-start;background:rgba(139,82,250,.07);border-color:rgba(139,82,250,.25);">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--ap)" stroke-width="2" style="flex-shrink:0;margin-top:2px;"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
    <div>
      <div style="font-size:13.5px;font-weight:700;color:var(--ap-deep);">Préinscription 100 % gratuite</div>
      <div style="font-size:12.5px;color:var(--ink-2);margin-top:3px;">
        Aucun paiement n'est requis à cette étape.
        <?php if ((int)($etab['genere_recepisse'] ?? 0) === 1): ?>
          L'attestation officielle sera générée et rattachée au dossier dès la validation.
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php endif; ?>

  <div style="display:flex;gap:12px;flex-wrap:wrap;">
    <button type="submit" class="g-btn g-btn-primary">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
      <?= $isInscription ? 'Valider l\'inscription' : 'Valider & générer l\'attestation' ?>
    </button>
    <a href="<?= url('/gestionnaire/candidatures') ?>" class="g-btn g-btn-ghost">Annuler</a>
  </div>
</form>

<script>
function gToggleMdp() {
  var inp = document.getElementById('compte_password');
  var cnf = document.getElementById('compte_password_confirm');
  var t = inp.type === 'password' ? 'text' : 'password';
  inp.type = t; cnf.type = t;
}
function gGenererMdp() {
  var chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%';
  var arr = new Uint32Array(14);
  crypto.getRandomValues(arr);
  var mdp = '';
  for (var i = 0; i < arr.length; i++) mdp += chars[arr[i] % chars.length];
  var inp = document.getElementById('compte_password');
  var cnf = document.getElementById('compte_password_confirm');
  inp.value = mdp; cnf.value = mdp;
  inp.type = 'text'; cnf.type = 'text'; // visible pour que l'agent puisse le communiquer au candidat
}
</script>
