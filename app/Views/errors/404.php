<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Page introuvable — Connect'Academia</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Inter', sans-serif;
      background: #F9FAFB;
      display: flex; align-items: center; justify-content: center;
      min-height: 100vh; padding: 2rem;
    }
    .error-page { text-align: center; max-width: 480px; }
    .error-page__code {
      font-size: 8rem; font-weight: 800; color: #8B52FA;
      line-height: 1; opacity: 0.15;
    }
    .error-page__title { font-size: 1.75rem; font-weight: 700; color: #1A1A2E; margin-top: -2rem; }
    .error-page__desc  { color: #6B7280; margin: 1rem 0 2rem; font-size: 1rem; line-height: 1.6; }
    .btn {
      display: inline-flex; align-items: center; gap: .5rem;
      padding: .75rem 1.5rem; border-radius: 10px; text-decoration: none;
      font-weight: 600; font-size: .95rem; transition: all .2s;
      background: #8B52FA; color: white; border: none; cursor: pointer;
    }
    .btn:hover { background: #7440E0; transform: translateY(-1px); }
  </style>
</head>
<body>
  <div class="error-page">
    <div class="error-page__code">404</div>
    <h1 class="error-page__title">Page introuvable</h1>
    <p class="error-page__desc">
      La page que vous recherchez n'existe pas ou a été déplacée.
    </p>
    <a href="<?= defined('BASE_URL') ? BASE_URL . '/hub' : '/hub' ?>" class="btn">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
      </svg>
      Retour à l'accueil
    </a>
  </div>
</body>
</html>
