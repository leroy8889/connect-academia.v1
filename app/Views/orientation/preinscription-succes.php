<?php
$couleur  = e($etab['couleur_principale'] ?? '#8B52FA');
$etabNom  = e($etab['nom']);
$etabSlug = e($etab['slug']);
$filiereLibelle = $filieres[$dossier['filiere_visee'] ?? ''] ?? ($dossier['filiere_visee'] ?? '—');
if (!empty($dossier['specialite_visee'])) {
    $filiereLibelle .= ' — ' . $dossier['specialite_visee'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Préinscription confirmée — <?= $etabNom ?></title>
  <link rel="stylesheet" href="<?= asset('orientation/candidature.css') ?>">
  <style>:root{--brand:<?= $couleur ?>}</style>
</head>
<body>

<header class="cand-topbar">
  <a href="<?= url('orientation/' . $etabSlug) ?>" class="cand-back">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
    Retour à la page école
  </a>
  <span class="cand-badge">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
    Préinscription validée
  </span>
</header>

<main class="cand-page">

  <div class="cand-card" style="padding:40px 28px;text-align:center">

    <div class="success-icon">
      <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
    </div>

    <h2 style="font-size:1.4rem;font-weight:800;margin-bottom:8px">Votre attestation est prête !</h2>
    <p style="color:var(--clr-txt-m,#63596E);font-size:14px;margin-bottom:28px;max-width:420px;margin-left:auto;margin-right:auto">
      Votre préinscription à <strong><?= $etabNom ?></strong> a bien été enregistrée.
      <?php if ($recepisseUrl): ?>
      Téléchargez votre attestation de préinscription officielle et conservez-la.
      <?php endif; ?>
    </p>

    <!-- Résumé dossier -->
    <dl class="dossier-summary" style="text-align:left">
      <dt>Référence dossier</dt>
      <dd><?= e($recepisse['numero_recepisse'] ?? ('DOS-' . hid((int)$dossier['id']))) ?></dd>

      <dt>Candidat</dt>
      <dd><?= e($dossier['nom_complet'] ?? '') ?></dd>

      <dt>Formation visée</dt>
      <dd><?= e($filiereLibelle) ?><?= !empty($dossier['niveau_entree']) ? ' — ' . e($dossier['niveau_entree']) : '' ?></dd>

      <dt>Date de préinscription</dt>
      <dd><?= e(isset($recepisse['date_emission']) ? (new DateTime($recepisse['date_emission']))->format('d/m/Y à H:i') : date('d/m/Y')) ?></dd>
    </dl>

    <!-- Boutons CTA -->
    <div style="display:flex;flex-direction:column;gap:12px;align-items:center">
      <?php if ($recepisseUrl): ?>
      <a href="<?= e($recepisseUrl) ?>" target="_blank" rel="noopener" class="btn-primary" style="width:100%;max-width:360px;justify-content:center">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Télécharger l'attestation
      </a>
      <?php endif; ?>

      <?php if ((bool)($etab['inscription_active'] ?? false)): ?>
      <a href="<?= url("orientation/{$etabSlug}/inscription") ?>" class="btn-ghost" style="width:100%;max-width:360px;justify-content:center">
        Finaliser mon inscription →
      </a>
      <?php endif; ?>

      <a href="<?= url("orientation/{$etabSlug}") ?>" class="btn-link" style="margin-top:4px">
        Retour à la page de l'établissement
      </a>
    </div>

  </div>

  <!-- Suivi du dossier dans l'espace personnel -->
  <?php if (\Core\Session::isLoggedIn()): ?>
  <div class="cand-card" style="margin-top:16px;padding:22px 26px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
    <div style="min-width:0">
      <p style="font-weight:800;font-size:15px;margin-bottom:4px">Dossier rattaché à votre compte ✓</p>
      <p style="font-size:13px;color:var(--clr-txt-m,#63596E)">Suivez son évolution à tout moment depuis votre espace personnel.</p>
    </div>
    <a href="<?= url('/eleve/orientation') ?>" class="btn-primary" style="flex-shrink:0">Voir dans mon espace</a>
  </div>
  <?php else: ?>
  <div class="cand-card" style="margin-top:16px;padding:22px 26px">
    <p style="font-weight:800;font-size:15px;margin-bottom:4px">Suivez votre dossier en ligne</p>
    <p style="font-size:13px;color:var(--clr-txt-m,#63596E);margin-bottom:16px">
      Créez votre espace gratuit avec l'adresse <strong><?= e($dossier['email'] ?? '') ?></strong> :
      votre dossier y sera automatiquement rattaché et vous pourrez suivre son évolution (validation, paiement, documents).
    </p>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <a href="<?= url('/orientation/compte') ?>?email=<?= urlencode($dossier['email'] ?? '') ?>" class="btn-primary">Créer mon espace</a>
      <a href="<?= url('/auth/connexion') ?>?redirect=<?= urlencode('/eleve/orientation') ?>" class="btn-ghost">J'ai déjà un compte</a>
    </div>
  </div>
  <?php endif; ?>

  <!-- Info note -->
  <div class="alert alert-info" style="margin-top:16px">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
    <span>Cette attestation confirme votre préinscription. Il ne constitue pas une inscription définitive. Vous pouvez revenir finaliser votre inscription à tout moment.</span>
  </div>

</main>

</body>
</html>
