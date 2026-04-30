<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= \Core\Session::getCsrfToken() ?>">
  <title><?= e($pageTitle ?? "Connect'Academia") ?></title>
   <link rel="icon" href="<?= asset('images/logo-officiel.png') ?>" type="image/png">
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset('css/auth.css') ?>?v=2">
</head>
<body class="auth-page">

  <?= $content ?>

  <script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
