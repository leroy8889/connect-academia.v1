<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= \Core\Session::getCsrfToken() ?>">
  <title><?= e($pageTitle ?? "Admin — Connect'Academia") ?></title>
  <link rel="icon" href="<?= asset('images/logo-officiel.png') ?>" type="image/png">
  <link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/admin-claude.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/loading.css') ?>">
</head>
<body class="admin-auth-body">

  <?= $content ?>

  <script src="<?= asset('js/loading.js') ?>"></script>
</body>
</html>
