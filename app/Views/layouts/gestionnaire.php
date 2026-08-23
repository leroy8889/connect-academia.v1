<?php
use Core\Session;

$userId      = Session::gestionnaireId();
$userName    = $_SESSION['gestionnaire_name'] ?? 'Gestionnaire';
$userInitial = strtoupper(mb_substr($userName, 0, 1));
$etabId      = (int)Session::get('gestionnaire_etab_id');

$currentUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$baseUrl    = defined('BASE_URL') ? BASE_URL : '';

function gNavActive(string $path, string $current, string $base): string {
    $full = $base . $path;
    return ($current === $full || str_starts_with($current, $full . '/')) ? ' g-nav-active' : '';
}

// Lien guichet actif selon ?type=
$currentType = $_GET['type'] ?? '';

// Notifications non lues (messages de l'administration + suivi dossiers)
$gNotifUnread = 0;
if ($userId) {
    try {
        $gNotifUnread = (new \Models\Orientation\NotificationOrientation())->countUnread((int)$userId);
    } catch (\Throwable) {
        $gNotifUnread = 0;
    }
}

// Récupérer nom établissement depuis session ou fallback
$etabNom = $_SESSION['gestionnaire_etab_nom'] ?? null;
if (!$etabNom && $etabId) {
    try {
        $etabRow = (new \Models\Orientation\Etablissement())->findById($etabId);
        $etabNom = $etabRow['nom'] ?? 'Mon établissement';
        $_SESSION['gestionnaire_etab_nom'] = $etabNom;
    } catch (\Throwable) {
        $etabNom = 'Mon établissement';
    }
}
?>
<!doctype html>
<html lang="fr" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<!-- Restaure l'état replié de la sidebar avant rendu (anti-flash) -->
<script>try{if(localStorage.getItem('gSidebarCollapsed')==='1'){document.documentElement.classList.add('g-collapsed');}}catch(e){}</script>
<meta name="csrf-token" content="<?= Session::getCsrfToken() ?>">
<title><?= e($pageTitle ?? "Gestionnaire — Connect'Academia") ?></title>
<link rel="icon" href="<?= asset('images/logo-officiel.png') ?>" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script>
window.CA_GEST = {
  baseUrl: <?= json_encode($baseUrl) ?>,
  csrfToken: <?= json_encode(Session::getCsrfToken()) ?>,
  etabId: <?= $etabId ?>,
};
</script>
<style>
/* ════════════════════════════════════════════════════════════════
   Espace gestionnaire — Claude design system × Liquid Glass
   Canvas crème, verre translucide, reflets spéculaires, serif display
   ════════════════════════════════════════════════════════════════ */
:root {
  /* Accent Connect'Academia */
  --ap:        #8B52FA;
  --ap-deep:   #7440D9;
  --ap-tint:   rgba(139,82,250,.10);
  --ap-glow:   rgba(139,82,250,.22);
  /* Coral Claude — rare, points de chaleur */
  --coral:     #D97757;
  /* Encres */
  --ink:       #1F1E1D;
  --ink-2:     #52504B;
  --ink-3:     #8D8A83;
  /* Canvas crème Claude */
  --paper:     #FAF9F5;
  --surface:   rgba(255,255,255,.42);
  --card:      rgba(255,255,255,.58);
  --border:    rgba(31,30,29,.08);
  --bdr-s:     rgba(31,30,29,.16);
  /* Verre */
  --glass-hi:      rgba(255,255,255,.65);
  --glass-border:  rgba(255,255,255,.55);
  --glass-shadow:  0 8px 32px rgba(31,30,29,.08), 0 1px 2px rgba(31,30,29,.04);
  --glass-inset:   inset 0 1px 0 rgba(255,255,255,.72);
  /* Sidebar blanc cassé — canvas crème « style Claude », encres sombres */
  --sidebar-bg:    #FAF9F5;
  --sidebar-bg-2:  #F2F0E9;
  --sidebar-ink:   #1F1E1D;
  --sidebar-ink-2: #605D57;
  --sidebar-ink-3: #97938B;
  --sidebar-line:  rgba(31,30,29,.08);
  --sidebar-w:  266px;
  --shadow-sm: 0 1px 3px rgba(31,30,29,.05);
  --shadow-md: 0 8px 32px rgba(31,30,29,.09), 0 1px 3px rgba(31,30,29,.04);
  --r-sm:10px; --r-md:14px; --r-lg:18px; --r-xl:24px; --r-pill:999px;
  --ease: cubic-bezier(.2,.8,.2,1);
  --font:  'Montserrat', system-ui, -apple-system, 'Segoe UI', sans-serif;
  --serif: 'Montserrat', system-ui, -apple-system, 'Segoe UI', sans-serif;
}
*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
html { scroll-behavior:smooth; }
body {
  font-family:var(--font); color:var(--ink); display:flex; min-height:100vh;
  -webkit-font-smoothing:antialiased; text-rendering:optimizeLegibility;
  background:var(--paper);
}
/* Fond ambiant : le verre a besoin de matière derrière lui */
body::before {
  content:''; position:fixed; inset:0; z-index:-1; pointer-events:none;
  background:
    radial-gradient(ellipse 800px 520px at 8% -6%,  rgba(139,82,250,.13), transparent 60%),
    radial-gradient(ellipse 700px 460px at 104% 12%, rgba(217,119,87,.10), transparent 55%),
    radial-gradient(ellipse 900px 600px at 50% 115%, rgba(139,82,250,.08), transparent 60%),
    var(--paper);
}
a { color:inherit; text-decoration:none; }
img { max-width:100%; display:block; }

/* ── Sidebar — blanc cassé « Claude », head + foot figés, nav scrollable ── */
.g-sidebar {
  width:var(--sidebar-w); height:100vh;
  background:linear-gradient(180deg, var(--sidebar-bg), var(--sidebar-bg-2));
  border-right:1px solid var(--sidebar-line);
  box-shadow:1px 0 0 rgba(255,255,255,.55), 6px 0 30px rgba(31,30,29,.05);
  display:flex; flex-direction:column; flex-shrink:0;
  position:fixed; top:0; left:0; z-index:100; overflow:hidden;
}
.g-sidebar::before { /* matière derrière le verre : léger halo ambiant */
  content:''; position:absolute; inset:0; z-index:0; pointer-events:none;
  background:
    radial-gradient(420px 300px at 18% -6%, rgba(139,82,250,.08), transparent 70%),
    radial-gradient(360px 320px at 96% 10%, rgba(217,119,87,.05), transparent 72%);
}
.g-sidebar-head { padding:22px 18px 16px; border-bottom:1px solid var(--sidebar-line); position:relative; z-index:2; }
.g-sidebar-logo { display:flex; align-items:center; gap:10px; margin-bottom:14px; }
.g-sidebar-logo img { width:30px; height:30px; border-radius:8px; box-shadow:0 2px 8px rgba(31,30,29,.14); }
.g-sidebar-logo-txt { font-size:13.5px; font-weight:700; color:var(--sidebar-ink); letter-spacing:-.01em; }
.g-etab-badge {
  background:var(--ap-tint);
  border:1px solid rgba(139,82,250,.22);
  border-radius:14px; padding:10px 13px;
  box-shadow:inset 0 1px 0 rgba(255,255,255,.7);
}
.g-etab-badge span { font-size:9.5px; text-transform:uppercase; letter-spacing:.08em; color:var(--ap-deep); font-weight:700; display:block; }
.g-etab-badge strong { font-size:12.5px; color:var(--sidebar-ink); font-weight:600; display:block; margin-top:3px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

.g-nav {
  flex:1; min-height:0; overflow-y:auto; overflow-x:hidden;
  padding:12px 12px 16px; position:relative; z-index:2;
  overscroll-behavior:contain;
  scrollbar-width:thin; scrollbar-color:rgba(31,30,29,.22) transparent;
}
.g-nav::-webkit-scrollbar { width:9px; }
.g-nav::-webkit-scrollbar-thumb { background:rgba(31,30,29,.14); border-radius:8px; border:2px solid transparent; background-clip:padding-box; }
.g-nav:hover::-webkit-scrollbar-thumb { background:rgba(31,30,29,.24); background-clip:padding-box; }
.g-nav-section { padding:14px 10px 6px; font-size:10px; text-transform:uppercase; letter-spacing:.09em; color:var(--sidebar-ink-3); font-weight:700; }
.g-nav-link {
  position:relative; z-index:1;
  display:flex; align-items:center; gap:11px;
  padding:9px 12px; margin:1px 0;
  font-size:13px; font-weight:500; color:var(--sidebar-ink-2);
  border-radius:12px; border:1px solid transparent;
  transition:color 180ms var(--ease), background 180ms var(--ease); cursor:pointer; text-decoration:none;
}
.g-nav-link svg { width:16px; height:16px; flex-shrink:0; opacity:.6; transition:opacity 180ms, color 180ms; }
/* Fallback (JS/verre indisponible) : surbrillance CSS classique */
.g-nav-link:hover { color:var(--sidebar-ink); background:rgba(31,30,29,.05); }
.g-nav-link:hover svg { opacity:1; color:var(--ap); }
/* Verre actif : la pastille liquide gère la surbrillance, on retire le fond CSS */
.g-sidebar.g-glass-ready .g-nav-link:hover { background:transparent; }
.g-nav-link.g-nav-active {
  color:var(--ap-deep);
  background:rgba(139,82,250,.10);
  border-color:rgba(139,82,250,.20);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.6);
}
.g-nav-link.g-nav-active svg { opacity:1; color:var(--ap); }

/* ── Pastille de verre liquide (hover, style Apple) ────────────────── */
.g-nav-glass {
  position:absolute; left:12px; top:0; height:38px; width:auto;
  border-radius:14px; opacity:0; pointer-events:none; z-index:0;
  background:linear-gradient(180deg, rgba(255,255,255,.72), rgba(255,255,255,.44));
  -webkit-backdrop-filter:blur(11px) saturate(185%);
  backdrop-filter:blur(11px) saturate(185%);
  border:1px solid rgba(255,255,255,.9);
  box-shadow:
    0 8px 22px rgba(31,30,29,.13), 0 1px 3px rgba(31,30,29,.08),
    inset 0 1px 0 rgba(255,255,255,.95), inset 0 -8px 16px rgba(139,82,250,.07);
}
.g-nav-glass::before { /* reflet spéculaire haut */
  content:''; position:absolute; left:0; right:0; top:0; height:55%;
  border-radius:inherit;
  background:linear-gradient(180deg, rgba(255,255,255,.55), transparent);
  pointer-events:none;
}
.g-nav-glass.on { opacity:1; }
/* Variante WebGL (liquid-glass-js) : le canvas réfracte, on neutralise le fond CSS */
.g-nav-glass-webgl { background:transparent; -webkit-backdrop-filter:none; backdrop-filter:none; }
.g-nav-glass-webgl::before { opacity:.7; }
.g-nav-glass-webgl canvas { box-shadow:none !important; }

.g-sidebar-foot { padding:12px; border-top:1px solid var(--sidebar-line); position:relative; z-index:2; }
.g-user-card {
  display:flex; align-items:center; gap:10px; padding:9px 10px;
  border-radius:14px; transition:background 180ms var(--ease);
  border:1px solid transparent;
}
.g-user-card:hover { background:rgba(31,30,29,.045); border-color:var(--sidebar-line); }
.g-avatar {
  width:34px; height:34px; border-radius:50%;
  background:linear-gradient(135deg, var(--ap), var(--ap-deep));
  display:flex; align-items:center; justify-content:center;
  font-size:13.5px; font-weight:700; color:#fff; flex-shrink:0;
  box-shadow:0 2px 10px rgba(139,82,250,.4), inset 0 1px 0 rgba(255,255,255,.3);
}
.g-user-meta { flex:1; min-width:0; }
.g-user-name { font-size:12.5px; color:var(--sidebar-ink); font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; display:block; }
.g-user-hint { font-size:10.5px; color:var(--sidebar-ink-3); display:block; margin-top:1px; }
.g-logout {
  font-size:11px; color:var(--sidebar-ink-3); cursor:pointer; background:none; border:none;
  padding:6px; border-radius:8px; transition:all 160ms; display:flex; align-items:center;
}
.g-logout:hover { color:#dc2626; background:rgba(239,68,68,.10); }

/* ── Main ─────────────────────────────────────────────────────────── */
.g-main { margin-left:var(--sidebar-w); flex:1; display:flex; flex-direction:column; min-height:100vh; }

/* ── Topbar — barre de verre flottante ───────────────────────────── */
.g-topbar {
  height:60px;
  background:rgba(250,249,245,.62);
  -webkit-backdrop-filter:blur(20px) saturate(180%);
  backdrop-filter:blur(20px) saturate(180%);
  border-bottom:1px solid var(--glass-border);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.6), 0 1px 12px rgba(31,30,29,.04);
  display:flex; align-items:center; padding:0 28px; gap:16px;
  position:sticky; top:0; z-index:50;
}
.g-topbar h1 { font-family:var(--serif); font-size:17px; font-weight:500; letter-spacing:-.01em; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.g-topbar-btns { display:flex; align-items:center; gap:10px; }

/* Cloche notifications */
.g-bell {
  position:relative; display:inline-flex; align-items:center; justify-content:center;
  width:38px; height:38px; border-radius:12px; color:var(--ink-2);
  background:rgba(255,255,255,.45); border:1px solid var(--glass-border);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.6);
  transition:color 160ms var(--ease), background 160ms var(--ease), transform 160ms var(--ease);
}
.g-bell:hover { color:var(--ap-deep); background:rgba(255,255,255,.75); transform:translateY(-1px); }
.g-bell-active { color:var(--ap-deep); background:var(--ap-tint); border-color:rgba(139,82,250,.28); }
.g-bell-badge {
  position:absolute; top:-5px; right:-5px; min-width:18px; height:18px; padding:0 5px;
  border-radius:999px; background:var(--coral); color:#fff; font-size:10.5px; font-weight:700;
  display:flex; align-items:center; justify-content:center; line-height:1;
  box-shadow:0 2px 6px rgba(217,119,87,.5), 0 0 0 2px var(--paper);
}
/* Compteur dans la nav latérale */
.g-nav-count {
  margin-left:auto; min-width:19px; height:19px; padding:0 6px;
  border-radius:999px; background:var(--coral); color:#fff; font-size:10.5px; font-weight:700;
  display:inline-flex; align-items:center; justify-content:center; line-height:1;
  box-shadow:0 1px 4px rgba(217,119,87,.4);
}

/* ── Content ──────────────────────────────────────────────────────── */
.g-content { padding:30px 32px 48px; flex:1; max-width:1280px; width:100%; }

/* ── Cartes de verre ──────────────────────────────────────────────── */
.g-card {
  background:var(--card);
  -webkit-backdrop-filter:blur(20px) saturate(180%);
  backdrop-filter:blur(20px) saturate(180%);
  border-radius:var(--r-xl);
  border:1px solid var(--glass-border);
  box-shadow:var(--glass-shadow), var(--glass-inset);
  padding:26px;
  transition:box-shadow 240ms var(--ease), transform 240ms var(--ease), background 240ms var(--ease);
}
a.g-card:hover { transform:translateY(-2px); background:var(--glass-hi); box-shadow:0 14px 44px rgba(31,30,29,.11), var(--glass-inset); }

.g-kpi-row { display:grid; grid-template-columns:repeat(auto-fill,minmax(185px,1fr)); gap:16px; margin-bottom:26px; }
.g-kpi {
  background:var(--card);
  -webkit-backdrop-filter:blur(18px) saturate(170%);
  backdrop-filter:blur(18px) saturate(170%);
  border-radius:var(--r-lg);
  border:1px solid var(--glass-border);
  box-shadow:var(--glass-shadow), var(--glass-inset);
  padding:20px 22px;
  transition:transform 220ms var(--ease), box-shadow 220ms var(--ease);
}
.g-kpi:hover { transform:translateY(-2px); box-shadow:0 12px 36px rgba(31,30,29,.10), var(--glass-inset); }
.g-kpi-val { font-family:var(--serif); font-size:30px; font-weight:500; letter-spacing:-.02em; color:var(--ink); line-height:1.1; }
.g-kpi-val.purple { color:var(--ap); }
.g-kpi-val.coral  { color:var(--coral); }
.g-kpi-label { font-size:11.5px; color:var(--ink-3); margin-top:6px; font-weight:500; }

/* ── Table ────────────────────────────────────────────────────────── */
.g-table { width:100%; border-collapse:collapse; font-size:13px; }
.g-table th { text-align:left; padding:12px 16px; font-size:10.5px; text-transform:uppercase; letter-spacing:.06em; color:var(--ink-3); font-weight:600; border-bottom:1px solid var(--border); }
.g-table td { padding:13px 16px; border-bottom:1px solid var(--border); vertical-align:middle; }
.g-table tr:last-child td { border-bottom:none; }
.g-table tbody tr { transition:background 160ms; }
.g-table tbody tr:hover td { background:rgba(255,255,255,.5); }

/* ── Badges ───────────────────────────────────────────────────────── */
.g-badge {
  display:inline-flex; align-items:center; gap:5px; padding:3.5px 11px;
  border-radius:var(--r-pill); font-size:11px; font-weight:600;
  border:1px solid transparent; box-shadow:inset 0 1px 0 rgba(255,255,255,.4);
}
.g-badge-soumis    { background:rgba(59,130,246,.12);  color:#1d4ed8; border-color:rgba(59,130,246,.22); }
.g-badge-paye      { background:rgba(34,197,94,.12);   color:#15803d; border-color:rgba(34,197,94,.22); }
.g-badge-accepte   { background:rgba(34,197,94,.12);   color:#15803d; border-color:rgba(34,197,94,.22); }
.g-badge-inscrit   { background:rgba(34,197,94,.16);   color:#15803d; border-color:rgba(34,197,94,.30); }
.g-badge-refuse    { background:rgba(239,68,68,.10);   color:#b91c1c; border-color:rgba(239,68,68,.20); }
.g-badge-brouillon { background:rgba(31,30,29,.06);    color:var(--ink-2); border-color:rgba(31,30,29,.10); }
.g-badge-enligne   { background:rgba(139,82,250,.12);  color:var(--ap-deep); border-color:rgba(139,82,250,.24); }
.g-badge-guichet   { background:rgba(217,119,87,.13);  color:#B45309; border-color:rgba(217,119,87,.26); }
.g-badge-ouverte   { background:rgba(34,197,94,.12);   color:#15803d; border-color:rgba(34,197,94,.22); }
.g-badge-bientot   { background:rgba(217,119,87,.13);  color:#B45309; border-color:rgba(217,119,87,.26); }
.g-badge-fermee    { background:rgba(31,30,29,.06);    color:var(--ink-3); border-color:rgba(31,30,29,.10); }

/* ── Boutons — pilules de verre ───────────────────────────────────── */
.g-btn {
  display:inline-flex; align-items:center; gap:7px; font-family:var(--font); font-weight:600;
  font-size:13px; padding:9.5px 19px; border-radius:var(--r-pill); border:1px solid transparent;
  cursor:pointer; transition:all 200ms var(--ease); text-decoration:none; white-space:nowrap;
}
.g-btn svg { width:15px; height:15px; }
.g-btn:active { transform:scale(.97); }
.g-btn-primary {
  background:linear-gradient(180deg, #9663FB, var(--ap) 55%, var(--ap-deep));
  color:#fff;
  box-shadow:0 6px 20px var(--ap-glow), inset 0 1px 0 rgba(255,255,255,.35);
}
.g-btn-primary:hover { filter:brightness(1.07); transform:translateY(-1px); box-shadow:0 10px 26px var(--ap-glow), inset 0 1px 0 rgba(255,255,255,.35); }
.g-btn-outline {
  background:rgba(139,82,250,.06); color:var(--ap-deep); border-color:rgba(139,82,250,.45);
  -webkit-backdrop-filter:blur(10px); backdrop-filter:blur(10px);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.5);
}
.g-btn-outline:hover { background:var(--ap-tint); border-color:var(--ap); }
.g-btn-ghost {
  background:rgba(255,255,255,.45); color:var(--ink-2); border-color:var(--glass-border);
  -webkit-backdrop-filter:blur(12px) saturate(160%); backdrop-filter:blur(12px) saturate(160%);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.6), 0 1px 4px rgba(31,30,29,.04);
}
.g-btn-ghost:hover { background:rgba(255,255,255,.72); border-color:var(--bdr-s); transform:translateY(-1px); }
.g-btn-danger { background:rgba(239,68,68,.05); color:#dc2626; border-color:rgba(239,68,68,.4); box-shadow:inset 0 1px 0 rgba(255,255,255,.5); }
.g-btn-danger:hover { background:rgba(239,68,68,.10); border-color:#dc2626; }
.g-btn-sm { padding:6.5px 14px; font-size:12px; }

/* ── Formulaires — champs de verre ────────────────────────────────── */
.g-form-group { margin-bottom:16px; }
.g-form-group label { display:block; font-size:12px; font-weight:600; margin-bottom:7px; color:var(--ink-2); }
.g-form-group input,
.g-form-group select,
.g-form-group textarea,
.g-input {
  width:100%; padding:10.5px 14px;
  border:1px solid var(--bdr-s); border-radius:var(--r-md);
  font-family:var(--font); font-size:13px; color:var(--ink);
  background:rgba(255,255,255,.75);
  -webkit-backdrop-filter:blur(8px); backdrop-filter:blur(8px);
  box-shadow:inset 0 1px 2px rgba(31,30,29,.04);
  transition:border-color 180ms, box-shadow 180ms, background 180ms;
}
.g-form-group input:focus,
.g-form-group select:focus,
.g-form-group textarea:focus,
.g-input:focus {
  outline:none; border-color:var(--ap); background:#fff;
  box-shadow:0 0 0 3px var(--ap-glow), inset 0 1px 2px rgba(31,30,29,.02);
}
.g-form-group input::placeholder, .g-form-group textarea::placeholder { color:var(--ink-3); }
.g-form-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.g-error-msg { font-size:11.5px; color:#dc2626; margin-top:5px; font-weight:500; }
.g-form-hint { font-size:11.5px; color:var(--ink-3); margin-top:5px; }

/* Interrupteurs (paramètres) */
.g-switch { position:relative; display:inline-flex; align-items:center; cursor:pointer; }
.g-switch input { position:absolute; opacity:0; width:0; height:0; }
.g-switch-track {
  width:42px; height:24px; border-radius:var(--r-pill);
  background:rgba(31,30,29,.14); border:1px solid rgba(31,30,29,.08);
  transition:background 200ms var(--ease); flex-shrink:0;
  box-shadow:inset 0 1px 3px rgba(31,30,29,.12);
}
.g-switch-track::after {
  content:''; position:absolute; top:3px; left:3px; width:18px; height:18px;
  border-radius:50%; background:#fff;
  box-shadow:0 1px 4px rgba(31,30,29,.25), inset 0 1px 0 rgba(255,255,255,.9);
  transition:transform 200ms var(--ease);
}
.g-switch input:checked + .g-switch-track { background:var(--ap); border-color:var(--ap-deep); }
.g-switch input:checked + .g-switch-track::after { transform:translateX(18px); }
.g-switch input:focus-visible + .g-switch-track { box-shadow:0 0 0 3px var(--ap-glow); }

/* ── Flash ────────────────────────────────────────────────────────── */
.g-flash {
  border-radius:var(--r-lg); padding:13px 18px; margin-bottom:22px;
  font-size:13px; font-weight:500; display:flex; align-items:center; justify-content:space-between; gap:12px;
  -webkit-backdrop-filter:blur(14px) saturate(160%); backdrop-filter:blur(14px) saturate(160%);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.5), 0 4px 16px rgba(31,30,29,.05);
}
.g-flash-ok  { background:rgba(34,197,94,.13);  color:#15803d; border:1px solid rgba(34,197,94,.30); }
.g-flash-err { background:rgba(239,68,68,.11);  color:#b91c1c; border:1px solid rgba(239,68,68,.28); }

/* ── Modales — verre profond ──────────────────────────────────────── */
.g-modal-overlay {
  display:none; position:fixed; inset:0; z-index:200;
  background:rgba(22,19,42,.38);
  -webkit-backdrop-filter:blur(6px); backdrop-filter:blur(6px);
  align-items:center; justify-content:center; padding:20px;
}
.g-modal-overlay.open { display:flex; }
.g-modal {
  background:rgba(255,255,255,.82);
  -webkit-backdrop-filter:blur(30px) saturate(180%); backdrop-filter:blur(30px) saturate(180%);
  border:1px solid rgba(255,255,255,.7);
  border-radius:var(--r-xl); padding:28px; width:100%; max-width:540px;
  box-shadow:0 28px 80px rgba(22,19,42,.28), inset 0 1px 0 rgba(255,255,255,.8);
  max-height:90vh; overflow-y:auto;
}
.g-modal-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; }
.g-modal-head h2 { font-family:var(--serif); font-size:19px; font-weight:500; letter-spacing:-.01em; }
.g-modal-close { background:none; border:none; cursor:pointer; color:var(--ink-3); font-size:20px; line-height:1; padding:4px 8px; border-radius:8px; transition:all 160ms; }
.g-modal-close:hover { color:var(--ink); background:rgba(31,30,29,.06); }

/* ── Titres & structure ───────────────────────────────────────────── */
.g-section-title { font-size:14px; font-weight:700; margin-bottom:14px; letter-spacing:-.005em; }
.g-page-header { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:26px; gap:16px; flex-wrap:wrap; }
.g-page-header h2 { font-family:var(--serif); font-size:26px; font-weight:500; letter-spacing:-.02em; line-height:1.15; }
.g-page-header p  { font-size:13px; color:var(--ink-2); margin-top:5px; }
.g-empty { text-align:center; padding:56px 20px; color:var(--ink-3); }
.g-empty svg { opacity:.28; margin:0 auto 14px; }
.g-empty p { font-size:14px; }
.g-back-link {
  font-size:13px; color:var(--ap-deep); display:inline-flex; align-items:center; gap:6px;
  font-weight:600; padding:6px 12px 6px 8px; border-radius:var(--r-pill);
  transition:background 160ms; margin-left:-8px;
}
.g-back-link:hover { background:var(--ap-tint); }

/* ── Segmented control (préinscription / inscription) ─────────────── */
.g-seg {
  display:inline-flex; padding:4px; gap:2px;
  background:rgba(31,30,29,.05); border:1px solid var(--border);
  border-radius:var(--r-pill);
  -webkit-backdrop-filter:blur(10px); backdrop-filter:blur(10px);
  box-shadow:inset 0 1px 3px rgba(31,30,29,.05);
}
.g-seg a, .g-seg button {
  padding:8px 20px; border-radius:var(--r-pill); border:none; background:transparent;
  font-family:var(--font); font-size:13px; font-weight:600; color:var(--ink-2);
  cursor:pointer; transition:all 200ms var(--ease); text-decoration:none;
  display:inline-flex; align-items:center; gap:7px;
}
.g-seg .g-seg-active {
  background:rgba(255,255,255,.92); color:var(--ink);
  box-shadow:0 2px 10px rgba(31,30,29,.10), inset 0 1px 0 rgba(255,255,255,.9);
}

/* ── Fallback sans backdrop-filter ────────────────────────────────── */
@supports not (backdrop-filter: blur(1px)) {
  .g-sidebar { background:var(--sidebar-bg); }
  .g-nav-glass { background:rgba(255,255,255,.9); }
  .g-topbar { background:#FBFAF7; }
  .g-card, .g-kpi { background:#FFFFFF; }
  .g-modal { background:#FFFFFF; }
  .g-flash-ok  { background:#EBF9EF; }
  .g-flash-err { background:#FDEEEE; }
  .g-btn-ghost { background:#FFFFFF; }
  .g-form-group input, .g-form-group select, .g-form-group textarea, .g-input { background:#FFFFFF; }
}

/* ── Bouton menu (mobile + repli desktop) ──────────────────────────── */
.g-hamburger { display:flex; align-items:center; justify-content:center; background:none; border:none; cursor:pointer; color:var(--ink); padding:6px; border-radius:8px; transition:background .15s; }
.g-hamburger:hover { background:rgba(31,30,29,.06); }

/* Repli / dépli desktop (état persisté sur <html>) */
.g-sidebar { transition:transform 240ms var(--ease); }
.g-main    { transition:margin-left 240ms var(--ease); }
@media(min-width:769px) {
  html.g-collapsed .g-sidebar { transform:translateX(-100%); }
  html.g-collapsed .g-main    { margin-left:0; }
}
.g-sidebar-overlay {
  display:none; position:fixed; inset:0; z-index:99;
  background:rgba(22,19,42,.4);
  -webkit-backdrop-filter:blur(4px); backdrop-filter:blur(4px);
}

@media(max-width:768px) {
  .g-sidebar { transform:translateX(-100%); transition:transform 240ms var(--ease); }
  .g-sidebar.open { transform:translateX(0); }
  .g-sidebar-overlay.open { display:block; }
  .g-main { margin-left:0; }
  .g-hamburger { display:flex; }
  .g-topbar { padding:0 16px; }
  .g-content { padding:18px 16px 40px; }
  .g-form-row { grid-template-columns:1fr; }
  .g-page-header h2 { font-size:22px; }
}
</style>
<link rel="stylesheet" href="<?= asset('css/loading.css') ?>">
</head>
<body>

<!-- Overlay sidebar mobile -->
<div class="g-sidebar-overlay" id="gOverlay" onclick="closeGSidebar()"></div>

<!-- Sidebar -->
<nav class="g-sidebar" id="gSidebar">
  <div class="g-sidebar-head">
    <div class="g-sidebar-logo">
      <img src="<?= asset('images/logo-officiel.png') ?>" alt="Connect'Academia">
      <span class="g-sidebar-logo-txt">Connect'Academia</span>
    </div>
    <div class="g-etab-badge">
      <span>Portail gestionnaire</span>
      <strong><?= e($etabNom) ?></strong>
    </div>
  </div>

  <div class="g-nav">
    <div class="g-nav-section">Pilotage</div>
    <a href="<?= url('/gestionnaire') ?>" class="g-nav-link<?= $currentUri === $baseUrl . '/gestionnaire' ? ' g-nav-active' : '' ?>">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
      Vue d'ensemble
    </a>
    <a href="<?= url('/gestionnaire/notifications') ?>" class="g-nav-link<?= gNavActive('/gestionnaire/notifications', $currentUri, $baseUrl) ?>">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      Notifications
      <?php if ($gNotifUnread > 0): ?><span class="g-nav-count"><?= $gNotifUnread > 9 ? '9+' : $gNotifUnread ?></span><?php endif; ?>
    </a>
    <a href="<?= url('/gestionnaire/paiements') ?>" class="g-nav-link<?= gNavActive('/gestionnaire/paiements', $currentUri, $baseUrl) ?>">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
      Paiements
    </a>
    <a href="<?= url('/gestionnaire/historique') ?>" class="g-nav-link<?= gNavActive('/gestionnaire/historique', $currentUri, $baseUrl) ?>">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
      Historique
    </a>
    <a href="<?= url('/gestionnaire/commissions') ?>" class="g-nav-link<?= gNavActive('/gestionnaire/commissions', $currentUri, $baseUrl) ?>">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
      Commissions
    </a>

    <div class="g-nav-section">Candidatures</div>
    <a href="<?= url('/gestionnaire/candidatures') ?>" class="g-nav-link<?= gNavActive('/gestionnaire/candidatures', $currentUri, $baseUrl) === ' g-nav-active' && !str_contains($currentUri, '/creer') ? ' g-nav-active' : '' ?>">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
      Dossiers reçus
    </a>
    <a href="<?= url('/gestionnaire/candidatures/creer?type=preinscription') ?>" class="g-nav-link<?= str_contains($currentUri, '/creer') && $currentType !== 'inscription' ? ' g-nav-active' : '' ?>">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M12 4v16m8-8H4"/></svg>
      Préinscription guichet
    </a>
    <a href="<?= url('/gestionnaire/candidatures/creer?type=inscription') ?>" class="g-nav-link<?= str_contains($currentUri, '/creer') && $currentType === 'inscription' ? ' g-nav-active' : '' ?>">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6m3-3h-6"/></svg>
      Inscription guichet
    </a>

    <div class="g-nav-section">Mon établissement</div>
    <a href="<?= url('/gestionnaire/formations') ?>" class="g-nav-link<?= gNavActive('/gestionnaire/formations', $currentUri, $baseUrl) ?>">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M12 14l9-5-9-5-9 5 9 5z"/><path d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
      Formations
    </a>
    <a href="<?= url('/gestionnaire/actualites') ?>" class="g-nav-link<?= gNavActive('/gestionnaire/actualites', $currentUri, $baseUrl) ?>">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2"/></svg>
      Actualités
    </a>

    <div class="g-nav-section">Configuration</div>
    <a href="<?= url('/gestionnaire/filieres') ?>" class="g-nav-link<?= gNavActive('/gestionnaire/filieres', $currentUri, $baseUrl) ?>">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16M4 12h10M4 18h6"/></svg>
      Filières &amp; spécialités
    </a>
    <a href="<?= url('/gestionnaire/formulaires') ?>" class="g-nav-link<?= gNavActive('/gestionnaire/formulaires', $currentUri, $baseUrl) ?>">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
      Formulaires
    </a>
    <a href="<?= url('/gestionnaire/pieces') ?>" class="g-nav-link<?= gNavActive('/gestionnaire/pieces', $currentUri, $baseUrl) ?>">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
      Pièces requises
    </a>
    <a href="<?= url('/gestionnaire/parametres') ?>" class="g-nav-link<?= gNavActive('/gestionnaire/parametres', $currentUri, $baseUrl) ?>">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 01-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09a1.65 1.65 0 00-1-1.51 1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09a1.65 1.65 0 001.51-1 1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06a1.65 1.65 0 001.82.33h0a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51h0a1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82v0a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
      Paramètres
    </a>
    <a href="<?= url('/gestionnaire/profil') ?>" class="g-nav-link<?= gNavActive('/gestionnaire/profil', $currentUri, $baseUrl) ?>">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      Mon profil
    </a>
  </div>

  <div class="g-sidebar-foot">
    <div class="g-user-card">
      <a href="<?= url('/gestionnaire/profil') ?>" style="display:flex;align-items:center;gap:10px;flex:1;min-width:0;">
        <div class="g-avatar"><?= e($userInitial) ?></div>
        <span class="g-user-meta">
          <span class="g-user-name"><?= e($userName) ?></span>
          <span class="g-user-hint">Voir mon profil</span>
        </span>
      </a>
      <form action="<?= url('/gestionnaire/logout') ?>" method="post" style="margin:0">
        <input type="hidden" name="_csrf_token" value="<?= Session::getCsrfToken() ?>">
        <button type="submit" class="g-logout" title="Déconnexion">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
        </button>
      </form>
    </div>
  </div>
</nav>

<!-- Main -->
<div class="g-main">
  <header class="g-topbar">
    <button class="g-hamburger" onclick="toggleGSidebar()" aria-label="Ouvrir ou fermer le menu" title="Ouvrir / fermer le menu">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
    </button>
    <h1><?= e($pageTitle ?? "Gestionnaire") ?></h1>
    <div class="g-topbar-btns">
      <a href="<?= url('/gestionnaire/notifications') ?>" class="g-bell<?= gNavActive('/gestionnaire/notifications', $currentUri, $baseUrl) ? ' g-bell-active' : '' ?>" title="Notifications" aria-label="Notifications<?= $gNotifUnread > 0 ? ' (' . $gNotifUnread . ' non lues)' : '' ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <?php if ($gNotifUnread > 0): ?><span class="g-bell-badge"><?= $gNotifUnread > 9 ? '9+' : $gNotifUnread ?></span><?php endif; ?>
      </a>
      <a href="<?= url('/orientation/' . ($etab['slug'] ?? '')) ?>" target="_blank" class="g-btn g-btn-ghost g-btn-sm" style="font-size:12px;">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6M15 3h6v6M10 14L21 3"/></svg>
        Voir le site
      </a>
    </div>
  </header>

  <main class="g-content">
    <?php if (!empty($flash)): ?>
    <div class="g-flash g-flash-ok" role="alert">
      <span><?= e($flash) ?></span>
      <button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;font-size:18px;line-height:1;color:inherit;">&times;</button>
    </div>
    <?php endif; ?>
    <?php if (!empty($flashError)): ?>
    <div class="g-flash g-flash-err" role="alert">
      <span><?= e($flashError) ?></span>
      <button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;font-size:18px;line-height:1;color:inherit;">&times;</button>
    </div>
    <?php endif; ?>

    <?= $content ?>
  </main>
</div>

<script>
function openGSidebar()  { document.getElementById('gSidebar').classList.add('open'); document.getElementById('gOverlay').classList.add('open'); }
function closeGSidebar() { document.getElementById('gSidebar').classList.remove('open'); document.getElementById('gOverlay').classList.remove('open'); }

// Bouton menu unique : overlay sur mobile, repli persistant sur desktop.
function toggleGSidebar() {
  if (window.matchMedia('(max-width:768px)').matches) {
    document.getElementById('gSidebar').classList.contains('open') ? closeGSidebar() : openGSidebar();
  } else {
    var collapsed = document.documentElement.classList.toggle('g-collapsed');
    try { localStorage.setItem('gSidebarCollapsed', collapsed ? '1' : '0'); } catch (e) {}
  }
}

// Modal helpers
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.g-modal-overlay').forEach(el => {
  el.addEventListener('click', e => { if (e.target === el) el.classList.remove('open'); });
});
</script>

<!-- Liquid Glass WebGL (liquid-glass-js) — enhancement progressif des boutons .g-btn.
     Sans WebGL/JS, les boutons gardent leur style CSS : aucune régression possible. -->
<link rel="stylesheet" href="<?= asset('liquid-glass/glass-adapter.css') ?>">
<script src="<?= asset('liquid-glass/html2canvas.min.js') ?>" defer></script>
<script src="<?= asset('liquid-glass/container.js') ?>" defer></script>
<script src="<?= asset('liquid-glass/glass-adapter.js') ?>" defer></script>
<!-- Pastille de hover en verre liquide (sidebar) — WebGL si dispo, sinon verre CSS. -->
<script src="<?= asset('liquid-glass/sidebar-glass.js') ?>" defer></script>
<script src="<?= asset('js/loading.js') ?>"></script>
</body>
</html>
