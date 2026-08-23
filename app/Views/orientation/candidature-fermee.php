<?php
$couleur  = e($etab['couleur_principale'] ?? '#8B52FA');
$etabNom  = e($etab['nom']);
$etabSlug = e($etab['slug']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Candidature fermée — <?= $etabNom ?></title>
  <link rel="stylesheet" href="<?= asset('orientation/candidature.css') ?>">
  <style>:root{--brand:<?= $couleur ?>}</style>
</head>
<body>

<header class="cand-topbar">
  <a href="<?= url('orientation/' . $etabSlug) ?>" class="cand-back">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
    Retour à la page école
  </a>
</header>

<main class="cand-page">
  <div class="cand-card" style="padding:48px 28px;text-align:center">
    <div class="pay-lock" style="margin-bottom:20px">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
    </div>
    <h1 style="font-size:1.3rem;font-weight:800;margin-bottom:10px">Candidature fermée</h1>
    <p style="color:var(--clr-txt-m);font-size:14.5px;max-width:380px;margin:0 auto 24px;line-height:1.7">
      <?= e($message ?? 'Cette section n\'est pas disponible pour cet établissement pour le moment.') ?>
    </p>
    <a href="<?= url('orientation/' . $etabSlug) ?>" class="btn-primary">
      Voir la page de l'établissement
    </a>
  </div>
</main>

</body>
</html>
