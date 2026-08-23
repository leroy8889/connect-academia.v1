<?php
/**
 * Layout — Espace élève Orientation (app-shell sidebar, maquette compte/Compte.html)
 * Variables attendues : $pageTitle, $activeTab, $user, $navCounts, $content
 * Optionnelles : $flash, $errors
 */

require_once BASE_PATH . '/app/Views/orientation/espace_eleve/_helpers.php';

$activeTab = $activeTab ?? 'overview';
$navCounts = $navCounts ?? [];
$user      = $user      ?? [];
$errors    = $errors    ?? [];

$prenom   = trim($user['prenom'] ?? '');
$nom      = trim($user['nom'] ?? '');
$fullName = trim($prenom . ' ' . $nom) ?: 'Mon compte';
$initiales = mb_strtoupper(mb_substr($prenom !== '' ? $prenom : ($nom !== '' ? $nom : '?'), 0, 1))
           . ($nom !== '' && $prenom !== '' ? mb_strtoupper(mb_substr($nom, 0, 1)) : '');
$roleLabel = (($user['role'] ?? '') === 'eleve') ? 'Élève' : 'Candidat';
$homeUrl   = \Core\Session::isVisiteur() ? url('/') : url('/hub');

$navMonCompte = [
    ['id' => 'overview', 'label' => "Vue d'ensemble",  'icon' => 'grid',   'url' => url('/eleve/orientation')],
    ['id' => 'profil',   'label' => 'Mon profil',      'icon' => 'user',   'url' => url('/eleve/orientation/profil')],
];
$navMonDossier = [
    ['id' => 'preinscriptions', 'label' => 'Préinscriptions', 'icon' => 'fileText',  'url' => url('/eleve/orientation/preinscriptions'), 'count' => $navCounts['preinscriptions'] ?? 0],
    ['id' => 'inscriptions',    'label' => 'Inscriptions',    'icon' => 'briefcase', 'url' => url('/eleve/orientation/inscriptions'),    'count' => $navCounts['inscriptions'] ?? 0],
    ['id' => 'paiements',       'label' => 'Paiements',       'icon' => 'card',      'url' => url('/eleve/orientation/paiements'),       'count' => 0],
    ['id' => 'recus',           'label' => 'Reçus',           'icon' => 'receipt',   'url' => url('/eleve/orientation/recus'),           'count' => 0],
    ['id' => 'documents',       'label' => 'Documents',       'icon' => 'folder',    'url' => url('/eleve/orientation/documents'),       'count' => $navCounts['documents'] ?? 0],
    ['id' => 'notifications',   'label' => 'Notifications',   'icon' => 'bell',      'url' => url('/eleve/orientation/notifications'),   'count' => $navCounts['notifications'] ?? 0],
];
$allNav = array_merge($navMonCompte, $navMonDossier);
$pageLabel = 'Mon compte';
foreach ($allNav as $n) {
    if ($n['id'] === $activeTab) { $pageLabel = $n['label']; break; }
}
$unread = (int)($navCounts['notifications'] ?? 0);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e($pageTitle ?? "Mon compte — Connect'Academia") ?></title>
  <link rel="icon" href="<?= asset('images/logo-officiel.png') ?>" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset('css/espace-compte.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/loading.css') ?>">
  <style>html,body{margin:0;padding:0;background:#FAF9F5}</style>
</head>
<body>
<div class="acct" id="acctShell">

  <div class="acct-side-overlay" data-acct-close aria-hidden="true"></div>

  <!-- ===== Sidebar ===== -->
  <aside class="acct-side">
    <div class="acct-brand">
      <div class="acct-brand-mk">
        <img src="<?= asset('images/logo-officiel.png') ?>" alt="Connect'Academia">
      </div>
      <div>
        <div class="acct-brand-name">Connect'Academia</div>
        <div class="acct-brand-sub">Espace élève · Orientation</div>
      </div>
    </div>

    <div class="acct-nav-label">Mon compte</div>
    <?php foreach ($navMonCompte as $n): ?>
      <a href="<?= $n['url'] ?>" class="acct-nav-item <?= $activeTab === $n['id'] ? 'on' : '' ?>">
        <span class="acct-nav-ic"><?= acct_icon($n['icon'], 16) ?></span>
        <span><?= e($n['label']) ?></span>
      </a>
    <?php endforeach; ?>

    <div class="acct-nav-label">Mon dossier</div>
    <?php foreach ($navMonDossier as $n): ?>
      <a href="<?= $n['url'] ?>" class="acct-nav-item <?= $activeTab === $n['id'] ? 'on' : '' ?>">
        <span class="acct-nav-ic"><?= acct_icon($n['icon'], 16) ?></span>
        <span><?= e($n['label']) ?></span>
        <?php if (!empty($n['count'])): ?><span class="acct-nav-count"><?= (int)$n['count'] ?></span><?php endif; ?>
      </a>
    <?php endforeach; ?>

    <div class="acct-side-foot">
      <a href="<?= url('/orientation') ?>" class="acct-back"><?= acct_icon('compass', 15) ?> Explorer les établissements</a>
      <a href="<?= $homeUrl ?>" class="acct-back"><?= acct_icon('home', 15) ?> Retour à l'accueil</a>
      <form method="POST" action="<?= url('/auth/deconnexion') ?>" style="margin:0;">
        <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
        <button type="submit" class="acct-back" style="width:100%;background:none;border:none;font-family:inherit;text-align:left;">
          <?= acct_icon('logout', 15) ?> Déconnexion
        </button>
      </form>
      <div class="acct-side-me">
        <div class="acct-side-me-av"><?= e($initiales) ?></div>
        <div class="acct-side-me-info">
          <div class="acct-side-me-name"><?= e($fullName) ?></div>
          <div class="acct-side-me-role"><?= e($roleLabel) ?></div>
        </div>
      </div>
    </div>
  </aside>

  <!-- ===== Main ===== -->
  <div class="acct-main">
    <div class="acct-top">
      <div class="acct-burger" data-acct-burger role="button" aria-label="Menu" tabindex="0"><?= acct_icon('menu', 17) ?></div>
      <div>
        <div class="acct-crumb">Orientation · <b>Mon compte</b></div>
        <div class="acct-page-title">
          <?= e($pageLabel) ?>
          <?php if (!empty($navCounts['dossier_actif'])): ?>
            <span class="acct-live"><span class="dot"></span> Dossier actif</span>
          <?php endif; ?>
        </div>
      </div>
      <div class="acct-top-actions">
        <a href="<?= url('/eleve/orientation/notifications') ?>" class="acct-icon-btn" aria-label="Notifications">
          <?= acct_icon('bell', 16) ?>
          <?php if ($unread > 0): ?><span class="dot-r"></span><?php endif; ?>
        </a>
        <a href="<?= url('/eleve/orientation/profil') ?>" class="acct-top-avatar" aria-label="Mon profil"><?= e($initiales) ?></a>
      </div>
    </div>

    <div class="acct-content">
      <?php if (!empty($flash)): ?>
        <div class="acct-flash ok"><?= acct_icon('checkCircle', 16) ?> <?= e($flash) ?></div>
      <?php endif; ?>
      <?php if (!empty($errors['general'])): ?>
        <div class="acct-flash err"><?= acct_icon('alert', 16) ?> <?= e($errors['general']) ?></div>
      <?php endif; ?>

      <?= $content ?>
    </div>
  </div>
</div>


<script>
(function () {
  var shell = document.getElementById('acctShell');
  var burger = document.querySelector('[data-acct-burger]');
  var overlay = document.querySelector('[data-acct-close]');
  if (!shell || !burger) return;
  burger.addEventListener('click', function () { shell.classList.toggle('side-open'); });
  burger.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); shell.classList.toggle('side-open'); }
  });
  if (overlay) overlay.addEventListener('click', function () { shell.classList.remove('side-open'); });
})();
</script>
<script src="<?= asset('js/loading.js') ?>"></script>
</body>
</html>
