<?php
/**
 * Succès inscription — deux états :
 *  - "soumis"       : dossier déposé en 2 étapes, en attente de validation
 *                     par l'établissement (l'attestation viendra après).
 *  - "paye/accepte" : paiement confirmé (guichet, ou futur paiement en ligne).
 */
$couleur        = e($etab['couleur_principale'] ?? '#8B52FA');
$etabNom        = e($etab['nom']);
$etabSlug       = e($etab['slug']);
$filiereLibelle = $filieres[$dossier['filiere_visee'] ?? ''] ?? ($dossier['filiere_visee'] ?? '—');
if (!empty($dossier['specialite_visee'])) {
    $filiereLibelle .= ' — ' . $dossier['specialite_visee'];
}
$nomComplet     = e($dossier['nom_complet'] ?? '');
$emailCandidat  = e($dossier['email'] ?? '');
$montant        = (int)($paiement['montant'] ?? 0);
$estPaye        = in_array($dossier['statut'] ?? '', ['paye', 'accepte'], true) && $montant > 0;
$titre          = $estPaye ? 'Inscription confirmée !' : 'Dossier d\'inscription envoyé !';
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $estPaye ? 'Inscription confirmée' : 'Dossier envoyé' ?> — <?= $etabNom ?></title>
  <link rel="stylesheet" href="<?= asset('orientation/candidature.css') ?>">
  <style>:root{--brand:<?= $couleur ?>}</style>
</head>
<body>

<header class="cand-topbar">
  <a href="<?= url('orientation/' . $etabSlug) ?>" class="cand-back">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
    Retour à la page école
  </a>
  <span class="cand-badge" style="background:#dcfce7;color:#166534;border-color:#86efac">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
    <?= $estPaye ? 'Inscription validée' : 'Dossier soumis' ?>
  </span>
</header>

<main class="cand-page">

  <div class="cand-card" style="padding:40px 28px;text-align:center">

    <div class="success-icon" style="background:#dcfce7">
      <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
    </div>

    <h2 style="font-size:1.4rem;font-weight:800;margin-bottom:8px"><?= $titre ?></h2>
    <p style="color:var(--clr-txt-m,#63596E);font-size:14px;margin-bottom:28px;max-width:440px;margin-left:auto;margin-right:auto">
      <?php if ($estPaye): ?>
        Votre paiement est passé et a bien été reçu par <strong><?= $etabNom ?></strong>. Votre dossier complet est maintenant à l'étude — l'attestation d'inscription vous sera délivrée après validation.
        <?php if ($recuUrl): ?>Téléchargez votre reçu officiel et conservez-le.<?php endif; ?>
      <?php else: ?>
        Votre dossier d'inscription à <strong><?= $etabNom ?></strong> a bien été déposé.
        L'établissement va l'examiner : dès validation, votre <strong>attestation d'inscription</strong>
        sera disponible dans votre espace personnel.
      <?php endif; ?>
    </p>

    <!-- Résumé dossier -->
    <dl class="dossier-summary" style="text-align:left">
      <dt>Candidat</dt>
      <dd><?= $nomComplet ?></dd>

      <dt>Formation visée</dt>
      <dd>
        <?= e($filiereLibelle) ?>
        <?= !empty($dossier['niveau_entree']) ? ' — ' . e($dossier['niveau_entree']) : '' ?>
      </dd>

      <dt>Établissement</dt>
      <dd><?= $etabNom ?></dd>

      <?php if ($estPaye): ?>
      <dt>Montant réglé</dt>
      <dd><?= number_format($montant, 0, ',', ' ') ?> XAF</dd>
      <?php else: ?>
      <dt>Statut</dt>
      <dd style="color:#b45309;font-weight:700">En attente de validation</dd>
      <?php endif; ?>

      <dt>Date de dépôt</dt>
      <dd><?= date('d/m/Y à H:i') ?></dd>
    </dl>

    <!-- Boutons CTA -->
    <div style="display:flex;flex-direction:column;gap:12px;align-items:center;margin-top:24px">
      <?php if ($estPaye && $recuUrl): ?>
      <a href="<?= e($recuUrl) ?>" target="_blank" rel="noopener" class="btn-primary"
         style="width:100%;max-width:360px;justify-content:center">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Télécharger le reçu
      </a>
      <?php endif; ?>

      <a href="<?= url("orientation/{$etabSlug}") ?>" class="btn-ghost"
         style="width:100%;max-width:360px;justify-content:center">
        Retour à la page de l'établissement
      </a>
    </div>

  </div>

  <!-- Suivi du dossier dans l'espace personnel -->
  <?php if (\Core\Session::isLoggedIn()): ?>
  <div class="cand-card" style="margin-top:16px;padding:22px 26px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
    <div style="min-width:0">
      <p style="font-weight:800;font-size:15px;margin-bottom:4px">Dossier rattaché à votre compte ✓</p>
      <p style="font-size:13px;color:var(--clr-txt-m,#63596E)">Suivez la validation de votre dossier et récupérez votre attestation depuis votre espace personnel.</p>
    </div>
    <a href="<?= url('/eleve/orientation') ?>" class="btn-primary" style="flex-shrink:0">Voir dans mon espace</a>
  </div>
  <?php else: ?>
  <div class="cand-card" style="margin-top:16px;padding:22px 26px">
    <p style="font-weight:800;font-size:15px;margin-bottom:4px">Suivez votre dossier en ligne</p>
    <p style="font-size:13px;color:var(--clr-txt-m,#63596E);margin-bottom:16px">
      Créez votre espace gratuit avec l'adresse <strong><?= $emailCandidat ?></strong> :
      votre dossier y sera automatiquement rattaché — validation, attestation et documents au même endroit.
    </p>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <a href="<?= url('/orientation/compte') ?>?email=<?= urlencode($dossier['email'] ?? '') ?>" class="btn-primary">Créer mon espace</a>
      <a href="<?= url('/auth/connexion') ?>?redirect=<?= urlencode('/eleve/orientation') ?>" class="btn-ghost">J'ai déjà un compte</a>
    </div>
  </div>
  <?php endif; ?>

  <!-- Note confirmation -->
  <div class="alert alert-info" style="margin-top:16px">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
    <span>
      Un e-mail de confirmation a été envoyé à <strong><?= $emailCandidat ?></strong>.
      <?= $estPaye
          ? 'L\'établissement traitera votre dossier et vous contactera pour la suite du processus.'
          : 'Vous serez informé(e) dès que l\'établissement aura validé votre dossier.' ?>
    </span>
  </div>

</main>

</body>
</html>
