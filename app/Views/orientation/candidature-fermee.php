<?php
$couleur  = e($etab['couleur_principale'] ?? '#8B52FA');
$etabNom  = e($etab['nom']);
$etabSlug = e($etab['slug']);

/* Écran de refus générique. Il sert deux cas : une candidature FERMÉE par
   l'établissement, et une inscription DÉJÀ OUVERTE par ce candidat (une seule
   par école et par année). Le second n'est pas une porte close mais une porte
   déjà franchie : il lui faut son propre titre et un bouton qui mène au dossier
   existant, sinon le candidat se retrouve dans un cul-de-sac. Les trois
   variables sont facultatives — sans elles, l'écran est celui d'avant. */
$titrePage   = trim((string)($titre ?? '')) !== '' ? (string)$titre : 'Candidature fermée';
$lienUrl     = trim((string)($lien ?? ''));
$lienTexte   = trim((string)($lienLibelle ?? '')) !== ''
    ? (string)$lienLibelle
    : "Voir la page de l'établissement";
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($titrePage) ?> — <?= $etabNom ?></title>
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
      <?php /* Cadenas quand la porte est close ; dossier coché quand elle a déjà
               été franchie. La même icône pour les deux cas laisserait croire à
               un refus là où il s'agit d'un renvoi. */ ?>
      <?php if ($lienUrl !== ''): ?>
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="m9 15 2 2 4-4"/></svg>
      <?php else: ?>
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
      <?php endif; ?>
    </div>
    <h1 style="font-size:1.3rem;font-weight:800;margin-bottom:10px"><?= e($titrePage) ?></h1>
    <p style="color:var(--clr-txt-m);font-size:14.5px;max-width:380px;margin:0 auto 24px;line-height:1.7">
      <?= e($message ?? 'Cette section n\'est pas disponible pour cet établissement pour le moment.') ?>
    </p>
    <?php if ($lienUrl !== ''): ?>
      <a href="<?= e($lienUrl) ?>" class="btn-primary"><?= e($lienTexte) ?></a>
      <p style="margin:16px 0 0">
        <a href="<?= url('orientation/' . $etabSlug) ?>"
           style="font-size:13px;color:var(--clr-txt-m);text-decoration:underline">
          Retour à la page de l'établissement
        </a>
      </p>
    <?php else: ?>
      <a href="<?= url('orientation/' . $etabSlug) ?>" class="btn-primary"><?= e($lienTexte) ?></a>
    <?php endif; ?>
  </div>
</main>

</body>
</html>
