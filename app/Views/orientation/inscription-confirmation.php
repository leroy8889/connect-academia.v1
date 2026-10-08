<?php
/**
 * Étape 3 — Confirmation « Dossier envoyé » (spec §5.4).
 *
 * Écran sans saisie. Son rôle est double : rassurer (le dossier existe, voici
 * sa référence) et détromper (le candidat n'est PAS encore inscrit). Le second
 * point n'est pas négociable — c'est lui qui évite les litiges avec les
 * établissements (spec §2).
 */
$couleur  = e($etab['couleur_principale'] ?? '#8B52FA');
$etabNom  = e($etab['nom']);
$etabSlug = e($etab['slug']);

$etapesLabels = $etapes['labels'] ?? ['Informations', 'Pièces du dossier', 'Confirmation'];
$reference    = (string)($reference ?? '');
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Dossier envoyé - <?= $etabNom ?></title>
  <link rel="stylesheet" href="<?= asset('orientation/candidature.css') ?>">
  <style>:root{--brand:<?= $couleur ?>}</style>
</head>
<body>

<header class="cand-topbar">
  <a href="<?= url('orientation/' . $etabSlug) ?>" class="cand-back">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
    <?= $etabNom ?>
  </a>
  <span class="cand-step-label">Étape <?= count($etapesLabels) ?> / <?= count($etapesLabels) ?></span>
</header>

<main class="cand-page">

  <?php $derniere = count($etapesLabels) - 1; ?>
  <div class="stepper" aria-label="Progression">
    <?php foreach ($etapesLabels as $i => $label): ?>
      <?php if ($i > 0): ?><div class="step-connector done"></div><?php endif; ?>
      <?php // Les étapes précédentes sont franchies, pas « en cours » : tout
            // marquer `active` peignait les trois pastilles à l'identique. ?>
      <div class="step <?= $i === $derniere ? 'active' : 'done' ?>">
        <div class="step-circle">
          <?php if ($i === $derniere): ?><?= $i + 1 ?><?php else: ?>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
          <?php endif; ?>
        </div>
        <div class="step-name"><?= e($label) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="confirm-card">

    <div class="confirm-mark" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
    </div>

    <h1>Votre dossier a bien été envoyé</h1>
    <p class="confirm-lead">
      <?php $filiereTxt = trim((string)($filiere ?? '')); ?>
      <?= $etabNom ?> a reçu votre candidature<?= $filiereTxt !== '' ? ' en ' . e($filiereTxt) : '' ?>
      pour l'année académique <?= e($anneeLibelle ?? '') ?>. Vous serez prévenu
      par notification dès que l'établissement l'aura analysé.
    </p>

    <!-- Référence : l'information que le candidat va noter ou photographier -->
    <div class="confirm-reference">
      <span class="label">Référence de votre dossier, à conserver</span>
      <?php if ($reference !== ''): ?>
        <span class="value"><?= e($reference) ?></span>
      <?php else: ?>
        <span class="value">En cours d'attribution</span>
      <?php endif; ?>
      <?php if (!empty($codeCourt)): ?>
        <span class="code-court">Code de vérification : <b><?= e($codeCourt) ?></b></span>
      <?php endif; ?>
    </div>

    <!-- Mention indispensable : un dossier déposé n'est pas une inscription -->
    <p class="confirm-warning">
      <strong>Votre dossier est déposé auprès de l'établissement.</strong> Il est enregistré sous la référence ci-dessus et transmis à l’établissement.
       Vous n’avez plus aucune démarche à faire pour l’instant. Ne vous déplacez pas et ne recommencez pas d’inscription ailleurs. Tout se suit depuis votre espace. 
    </p>

    <ol class="confirm-steps">
      <?php foreach (($prochaines ?? []) as $etape): ?>
        <li><span><?= e($etape) ?></span></li>
      <?php endforeach; ?>
    </ol>

    <div class="confirm-actions">
      <a href="<?= url('eleve/orientation/inscriptions') ?>" class="btn-primary">
        Accéder à mon espace
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
      </a>
    </div>

    <p class="confirm-meta">
      Votre récépissé de dépôt, avec son QR code de vérification, est disponible
      dans votre espace. Présentez-le à l'établissement lors de votre passage.
    </p>

  </div>
</main>

</body>
</html>
