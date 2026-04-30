<?php use Core\Session; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= Session::getCsrfToken() ?>">
    <title><?= htmlspecialchars($pageTitle ?? 'StudyLink') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/main.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/auth.css') ?>">
</head>
<body class="auth-body">
    <?= $content ?>

    <footer class="auth-footer">
        <div class="auth-footer__links">
            <a href="#">À propos</a>
            <a href="#">Support</a>
            <a href="#">Confidentialité</a>
        </div>
        <span class="auth-footer__copy">&copy; <?= date('Y') ?> StudyLink. Tous droits réservés.</span>
    </footer>

    <script src="<?= asset('js/components/auth.js') ?>"></script>
</body>
</html>
