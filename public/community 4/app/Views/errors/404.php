<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Page non trouvée | StudyLink</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/main.css') ?>">
    <style>
        .error-page {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2rem;
            background: linear-gradient(135deg, #F3EFFF 0%, #FFFFFF 100%);
        }
        .error-page__code { font-size: 6rem; font-weight: 700; color: var(--color-primary); margin-bottom: 0.5rem; }
        .error-page__title { font-size: 1.5rem; color: var(--color-dark); margin-bottom: 0.5rem; }
        .error-page__text { color: var(--color-gray-600); margin-bottom: 2rem; }
    </style>
</head>
<body>
    <div class="error-page">
        <div class="error-page__code">404</div>
        <h1 class="error-page__title">Page non trouvée</h1>
        <p class="error-page__text">La page que vous recherchez n'existe pas ou a été déplacée.</p>
        <a href="<?= url('/') ?>" class="btn btn--primary">Retour à l'accueil</a>
    </div>
</body>
</html>

