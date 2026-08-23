<?php
/**
 * Page de maintenance globale — Connect'Academia.
 * Rendue par Core\Maintenance quand le mode maintenance est actif.
 * Quasi-autonome : seule dépendance externe = le logo (asset statique servi
 * directement par Apache, hors périmètre du guard, donc dispo en maintenance).
 *
 * Direction : éditorial minimal, thème CLAIR unique = canvas « blanc cassé »
 * chaud style Claude. Choix délibéré du mono-thème (page système). Typo
 * Montserrat, structure en filets, violet de marque #8B52FA en accent unique.
 *
 * $maintenanceMessage : message personnalisé facultatif (super-admin).
 */
$maintenanceMessage = isset($maintenanceMessage) ? trim((string) $maintenanceMessage) : '';
$defaultMessage = "On améliore quelques réglages en coulisses. Reviens dans un petit moment, tout sera de nouveau accessible.";
?>
<!DOCTYPE html>
<html lang="fr" data-page="maintenance">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<meta name="robots" content="noindex, nofollow">
<title>Maintenance - Connect'Academia</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --accent:#8B52FA;
    --accent-soft:rgba(139,82,250,.12);
    --bg:#FAF9F5;            /* blanc cassé chaud, style Claude */
    --ink:#1B1A17;
    --ink-soft:#57544D;
    --ink-faint:#8B887F;
    --line:rgba(27,26,23,.10);
    --line-soft:rgba(27,26,23,.06);
    --font:'Montserrat',system-ui,-apple-system,'Segoe UI',sans-serif;
  }

  *{box-sizing:border-box;margin:0;padding:0}
  html,body{height:100%}
  body{
    font-family:var(--font);
    background:var(--bg);
    color:var(--ink);
    min-height:100dvh;
    display:flex;align-items:center;justify-content:center;
    padding:32px 24px;
    -webkit-font-smoothing:antialiased;
    text-rendering:optimizeLegibility;
  }

  /* Fine barre de progression indéterminée : signale un travail en cours. */
  .progress{
    position:fixed;top:0;left:0;right:0;height:3px;
    background:var(--line-soft);overflow:hidden;z-index:10;
  }
  .progress::after{
    content:"";position:absolute;top:0;left:0;height:100%;width:34%;
    background:var(--accent);border-radius:0 3px 3px 0;
    animation:slide 1.9s cubic-bezier(.65,0,.35,1) infinite;
  }
  @keyframes slide{
    0%{transform:translateX(-110%)}
    60%{transform:translateX(210%)}
    100%{transform:translateX(210%)}
  }

  .stage{
    width:100%;max-width:520px;
    text-align:left;
    animation:rise .55s cubic-bezier(.16,1,.3,1) both;
  }
  @keyframes rise{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}

  /* Logo officiel de la plateforme. */
  .logo{
    display:block;height:84px;width:auto;max-width:240px;
    margin-bottom:26px;
  }

  .status{
    display:inline-flex;align-items:center;gap:8px;
    font-size:12.5px;font-weight:600;letter-spacing:.01em;
    color:var(--accent);background:var(--accent-soft);
    padding:6px 13px;border-radius:999px;margin-bottom:22px;
  }
  .status .dot{
    width:7px;height:7px;border-radius:50%;background:var(--accent);
    box-shadow:0 0 0 0 rgba(139,82,250,.45);
    animation:beat 2.2s ease-in-out infinite;
  }
  @keyframes beat{
    0%,100%{box-shadow:0 0 0 0 rgba(139,82,250,.45)}
    50%{box-shadow:0 0 0 5px rgba(139,82,250,0)}
  }

  h1{
    font-size:clamp(28px,6vw,40px);font-weight:800;
    line-height:1.08;letter-spacing:-.025em;color:var(--ink);
    margin-bottom:16px;max-width:14ch;
  }
  .lead{
    font-size:16px;font-weight:400;line-height:1.62;
    color:var(--ink-soft);max-width:46ch;
  }

  /* Faits en filets — trois infos honnêtes, séparées par des lignes fines. */
  .facts{
    margin-top:36px;border-top:1px solid var(--line);
  }
  .facts .row{
    display:flex;align-items:baseline;justify-content:space-between;
    gap:16px;padding:14px 0;border-bottom:1px solid var(--line-soft);
  }
  .facts .k{
    font-size:12px;font-weight:500;letter-spacing:.04em;
    text-transform:uppercase;color:var(--ink-faint);
  }
  .facts .v{
    font-size:14px;font-weight:600;color:var(--ink);text-align:right;
  }

  .foot{
    margin-top:30px;font-size:13px;font-weight:400;color:var(--ink-faint);
  }

  @media (max-width:480px){
    .facts .row{flex-direction:column;gap:4px}
    .facts .v{text-align:left}
    .logo{height:72px}
  }

  @media (prefers-reduced-motion:reduce){
    .stage{animation:none}
    .progress::after{animation:none;width:34%}
    .status .dot{animation:none}
  }
</style>
</head>
<body>
  <div class="progress" aria-hidden="true"></div>

  <main class="stage" role="alert" aria-live="polite">
    <img class="logo" src="<?= asset('images/logo-removebg.png') ?>" alt="Connect'Academia" width="240" height="84">

    <span class="status"><span class="dot"></span> Maintenance en cours</span>

    <h1>La plateforme se met à jour.</h1>
    <p class="lead"><?= e($maintenanceMessage !== '' ? $maintenanceMessage : $defaultMessage) ?></p>

    <div class="facts">
      <div class="row">
        <span class="k">État</span>
        <span class="v">Maintenance planifiée</span>
      </div>
      <div class="row">
        <span class="k">Accès</span>
        <span class="v">Réservé à l'équipe</span>
      </div>
      <div class="row">
        <span class="k">Retour</span>
        <span class="v">Très bientôt</span>
      </div>
    </div>

    <p class="foot">Merci de ta patience.</p>
  </main>
</body>
</html>
