<?php
$couleur          = e($etab['couleur_principale'] ?? '#8B52FA');
$etabNom          = e($etab['nom']);
$etabSlug         = e($etab['slug']);
// Libellé de la formation. Les morceaux étaient assemblés au tiret cadratin
// (« Licence L3 — Réseaux — Licence 3 »), ce qui donnait une ligne de trois
// segments impossible à lire d'un coup d'œil. Ils sont désormais séparés par
// des virgules, et l'absence de valeur ne remplit plus la case d'un tiret.
$filiereLibelle   = $filieres[$dossier['filiere_visee'] ?? ''] ?? ($dossier['filiere_visee'] ?: '');
if (!empty($dossier['specialite_visee'])) {
    $filiereLibelle = trim($filiereLibelle . ', ' . $dossier['specialite_visee'], ', ');
}
$niveauEntree     = trim((string)($dossier['niveau_entree'] ?? ''));
$formationLigne   = trim(implode(', ', array_filter([$filiereLibelle, $niveauEntree])), ', ');
if ($formationLigne === '') { $formationLigne = 'Non renseignée'; }
$nomComplet       = e($dossier['nom_complet'] ?? '');
$emailCandidat    = e($dossier['email'] ?? '');
$prixInscription  = (int)($etab['prix_inscription'] ?? 0);
$fraisDossier     = (int)($etab['frais_dossier']    ?? 0);
$total            = $prixInscription + $fraisDossier;
$err              = $errors ?? [];

// Fil d'étapes fourni par le contrôleur, comme sur les autres écrans du
// parcours. Le repli reproduit la forme « paiement en ligne actif », qui est
// le seul cas où cet écran est rendu.
$etapesLabels     = $etapes['labels'] ?? ['Informations', 'Pièces du dossier', 'Paiement'];
$totalSteps       = count($etapesLabels);
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Inscription, étape <?= $totalSteps ?> sur <?= $totalSteps ?> - <?= $etabNom ?></title>
  <link rel="stylesheet" href="<?= asset('orientation/candidature.css') ?>">
  <style>:root{--brand:<?= $couleur ?>}</style>
</head>
<body>

<header class="cand-topbar">
  <a href="<?= url('orientation/' . $etabSlug) ?>" class="cand-back">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
    Retour à la page école
  </a>
  <span class="cand-step-label">Étape <?= $totalSteps ?> / <?= $totalSteps ?></span>
</header>

<main class="cand-page">

  <!-- Chapeau : le montant est la seule inconnue qui reste au candidat.
       Il est annoncé ici, pas découvert au bas de la page. -->
  <div class="cand-lede">
    <span class="kicker">Inscription <?= $etabNom ?></span>
    <h1>Régler votre inscription</h1>
    <p>Dernière étape. Votre dossier part à l'établissement dès la confirmation
    du paiement, et votre reçu est disponible immédiatement après.</p>
  </div>

  <!-- Fil d'étapes. Les trois pastilles étaient écrites en dur, et la
       deuxième s'appelait « Formation & dossier » alors que les étapes 1, 2 et
       la confirmation affichent toutes « Pièces du dossier », libellé fourni
       par le contrôleur. Un même parcours ne peut pas nommer son étape 2 de
       deux façons selon l'écran où on se trouve. -->
  <div class="stepper" aria-label="Progression">
    <?php foreach ($etapesLabels as $i => $label): ?>
      <?php if ($i > 0): ?><div class="step-connector done"></div><?php endif; ?>
      <div class="step<?= $i < 2 ? ' done' : ' active' ?>">
        <div class="step-circle">
          <?php if ($i < 2): ?>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
          <?php else: ?><?= $i + 1 ?><?php endif; ?>
        </div>
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

  <?php if (!empty($err['paiement'])): ?>
  <div class="alert alert-info" style="margin-bottom:20px">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
    <?= e($err['paiement']) ?>
  </div>
  <?php endif; ?>

  <div class="cand-card">
    <div class="cand-card-head">
      <h2>Récapitulatif</h2>
      <p>Vérifiez ces informations avant de payer : elles seront celles de votre dossier.</p>
    </div>

    <!-- Résumé dossier -->
    <div class="form-section">
      <div class="section-label">Votre dossier</div>
      <dl class="dossier-summary">
        <dt>Candidat</dt>
        <dd><?= $nomComplet ?></dd>
        <dt>Formation visée</dt>
        <dd><?= e($formationLigne) ?></dd>
        <dt>Établissement</dt>
        <dd><?= $etabNom ?></dd>
      </dl>
    </div>

    <!-- Détail du montant -->
    <?php if ($total > 0): ?>
    <div class="form-section">
      <div class="section-label">Montant à régler</div>
      <div class="pay-breakdown">
        <?php if ($prixInscription > 0): ?>
        <div class="pay-line">
          <span>Frais d'inscription</span>
          <strong><?= number_format($prixInscription, 0, ',', ' ') ?> XAF</strong>
        </div>
        <?php endif; ?>
        <?php if ($fraisDossier > 0): ?>
        <div class="pay-line">
          <span>Frais de dossier</span>
          <strong><?= number_format($fraisDossier, 0, ',', ' ') ?> XAF</strong>
        </div>
        <?php endif; ?>
        <div class="pay-total">
          <span>Total</span>
          <span class="pay-total-amount"><?= number_format($total, 0, ',', ' ') ?> XAF</span>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Formulaire de paiement -->
    <div class="form-section">
      <div class="section-label">Méthode de paiement</div>

      <?php if ($total > 0): ?>

      <?php /* Les deux icônes (carte bancaire, bouclier) illustraient en
               image ce que les libellés disent en toutes lettres, et la carte
               bancaire annonçait un moyen de paiement qui n'existe pas ici. */ ?>
      <div class="pay-methods">
        <div class="pay-method-badge">Airtel Money</div>
        <div class="pay-method-badge">Moov Money</div>
        <div class="pay-method-badge">Paiement sécurisé par MoneyFusion</div>
      </div>

      <form method="POST" action="<?= url("orientation/{$etabSlug}/inscription/etape-3/payer") ?>" id="pay-form">
        <input type="hidden" name="_csrf_token" value="<?= e($csrf) ?>">

        <div class="pay-cta">
          <button type="submit" class="btn-pay" id="pay-btn">
            Payer <?= number_format($total, 0, ',', ' ') ?> XAF
          </button>
          <p class="pay-disclaimer">
            Vous serez redirigé vers la plateforme sécurisée MoneyFusion.
            Votre reçu sera disponible immédiatement après confirmation du paiement.
          </p>
        </div>
      </form>

      <?php else: ?>

      <!-- Gratuit -->
      <form method="POST" action="<?= url("orientation/{$etabSlug}/inscription/etape-3/payer") ?>">
        <input type="hidden" name="_csrf_token" value="<?= e($csrf) ?>">
        <div class="pay-cta">
          <button type="submit" class="btn-pay">
            Confirmer mon inscription
          </button>
        </div>
      </form>

      <?php endif; ?>
    </div>

  </div><!-- /cand-card -->

  <div class="alert alert-info" style="margin-top:0">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
    <span>Un reçu sera envoyé à <strong><?= $emailCandidat ?></strong> après confirmation du paiement.</span>
  </div>

  <!-- Navigation -->
  <div class="nav-footer">
    <a href="<?= url("orientation/{$etabSlug}/inscription/etape-2") ?>" class="btn-ghost">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
      Revenir aux pièces
    </a>
  </div>

</main>

<script>
(function() {
  var form = document.getElementById('pay-form');
  var btn  = document.getElementById('pay-btn');
  if (!form || !btn) return;

  form.addEventListener('submit', function() {
    btn.disabled = true;
    btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" class="spin"><path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0"/></svg> Redirection en cours…';
    btn.style.opacity = '0.75';
  });
}());
</script>

<?php /* Les styles de cet écran vivaient ici, dans un <style> de fin de
         page. Ils sont passés dans candidature.css (section « ÉTAPE 3 »)
         pour que le parcours n'ait qu'une seule échelle de rayons et de
         filets à maintenir. */ ?>

</body>
</html>
