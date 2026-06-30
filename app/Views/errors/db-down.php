<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Maintenance — Connect'Academia</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, 'Inter', sans-serif; background: #0f0a1e; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 2rem; }
    .card { text-align: center; max-width: 480px; background: #1c1435; border: 1px solid rgba(139,82,250,.25); border-radius: 20px; padding: 3rem 2rem; }
    .icon { width: 64px; height: 64px; background: rgba(139,82,250,.15); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; }
    h1 { font-size: 1.5rem; font-weight: 800; color: #f1f0ff; margin-bottom: .75rem; }
    p  { color: #9ca3af; font-size: .95rem; line-height: 1.6; margin-bottom: 1.5rem; }
    .badge { display: inline-flex; align-items: center; gap: .4rem; padding: .35rem .85rem; background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.3); border-radius: 20px; color: #fca5a5; font-size: .8rem; font-weight: 600; margin-bottom: 2rem; }
    .btn { display: inline-flex; align-items: center; gap: .4rem; padding: .7rem 1.4rem; background: #8B52FA; color: white; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: .9rem; cursor: pointer; border: none; }
    .btn:hover { background: #7440e0; }
    .hint { margin-top: 1.5rem; font-size: .78rem; color: #6b7280; }
  </style>
</head>
<body>
  <div class="card">
    <div class="icon">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#8B52FA" stroke-width="2">
        <ellipse cx="12" cy="5" rx="9" ry="3"/>
        <path d="M3 5v6c0 1.66 4 3 9 3s9-1.34 9-3V5"/>
        <path d="M3 11v6c0 1.66 4 3 9 3s9-1.34 9-3v-6"/>
      </svg>
    </div>
    <span class="badge">
      <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/></svg>
      Base de données indisponible
    </span>
    <h1>Maintenance en cours</h1>
    <p>Le serveur de base de données est temporairement inaccessible. L'équipe technique est notifiée.</p>
    <button class="btn" onclick="window.location.reload()">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
      Réessayer
    </button>
    <p class="hint">Si le problème persiste : démarrez MySQL dans MAMP puis rechargez.</p>
  </div>
</body>
</html>
