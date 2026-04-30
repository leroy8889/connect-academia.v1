<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Module en développement — Connect'Academia</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, 'Inter', sans-serif; background: #F9FAFB; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 2rem; }
    .page { text-align: center; max-width: 480px; }
    .emoji { font-size: 5rem; margin-bottom: 1.5rem; }
    h1 { font-size: 1.75rem; font-weight: 800; color: #1A1A2E; margin-bottom: .75rem; }
    p  { color: #6B7280; font-size: 1rem; line-height: 1.6; margin-bottom: 2rem; }
    .btn { display: inline-flex; align-items: center; gap: .4rem; padding: .75rem 1.5rem; background: #8B52FA; color: white; border-radius: 10px; text-decoration: none; font-weight: 600; }
    .btn:hover { background: #7440E0; }
  </style>
</head>
<body>
  <div class="page">
    <div class="emoji">🚧</div>
    <h1>Module en cours de développement</h1>
    <p>Cette fonctionnalité sera bientôt disponible. Revenez dans quelques instants !</p>
    <a href="<?= defined('BASE_URL') ? BASE_URL . '/hub' : '/hub' ?>" class="btn">← Retour au Hub</a>
  </div>
</body>
</html>
