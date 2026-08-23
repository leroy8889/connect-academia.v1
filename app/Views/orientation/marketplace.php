<?php
// Variables: $etablissements (array), $logoUrls (array<int,string>), $villes (array),
//            $avisAgg (array<int, array{nb_avis:int, note_moyenne:float}>)
// Optionnelles: $initialType (string), $heroTitre (string), $initialQ (string)
$avisAgg = $avisAgg ?? [];
$typeLabels = [
    'universite'   => 'Université',
    'grande_ecole' => 'Grande école',
    'institut'     => 'Institut',
];
$total       = count($etablissements);
$initialType = $initialType ?? '';
$heroTitre   = $heroTitre   ?? '';
$initialQ    = $initialQ    ?? '';
$isRecherche = $isRecherche ?? false;
$totalFormations = array_sum(array_map(
    static fn(array $e): int => (int)($e['nb_formations'] ?? 0),
    $etablissements
));
?>
<!doctype html>
<html lang="fr" data-theme="light">
<head>
<meta charset="utf-8">
<title>Orientation — Écoles &amp; universités du Gabon · Connect'Academia</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Découvrez les établissements d'enseignement supérieur au Gabon. Comparez les formations, les frais et candidatez en ligne sur Connect'Academia.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ── Tokens — langage Claude, couleurs & typo Connect'Academia ─────────── */
:root {
  --ap:        #8B52FA;
  --ap-hover:  #9D6EFB;
  --ap-press:  #7440D9;
  --ap-tint:   #F3EFFF;

  --ink:    #1A1A1A;
  --ink-2:  #4A4A4A;
  --ink-3:  #9E9E9E;
  --paper:  #FAF9F5;
  --surface:#F5F5F7;
  --card:   #FFFFFF;
  --charcoal:      #1A1A1A;
  --charcoal-card: #242424;

  --hairline:        rgba(26,26,26,0.10);
  --hairline-strong: rgba(26,26,26,0.22);
  --shadow-hover: 0 1px 3px rgba(26,26,26,0.08);

  --r-sm: 8px;  --r-md: 12px;  --r-lg: 16px;  --r-pill: 999px;
  --ease: cubic-bezier(.2,.8,.2,1);
  --maxw: 1200px;
  --font: 'Montserrat', system-ui, -apple-system, sans-serif;
}

/* ── Reset ────────────────────────────────────────────────────────────── */
*,*::before,*::after { box-sizing: border-box; }
body { margin:0; font-family:var(--font); color:var(--ink); background:var(--paper);
       -webkit-font-smoothing:antialiased; text-rendering:optimizeLegibility; }
img  { max-width:100%; display:block; }
a    { color:inherit; text-decoration:none; }
button { font-family:inherit; }
.wrap{ max-width:var(--maxw); margin:0 auto; padding:0 32px; }
:focus-visible { outline:2px solid var(--ap); outline-offset:3px; border-radius:var(--r-sm); }

/* ── Boutons — aplats, radius 8, zéro glow ───────────────────────────── */
.btn {
  font-weight:600; font-size:15px; border:0; cursor:pointer;
  padding:13px 24px; border-radius:var(--r-sm);
  display:inline-flex; align-items:center; justify-content:center; gap:8px;
  transition:background 200ms var(--ease), border-color 200ms var(--ease),
             color 200ms var(--ease), transform 120ms var(--ease);
  white-space:nowrap; text-decoration:none;
}
.btn svg { width:16px; height:16px; }
.btn:active { transform:scale(0.98); }
.btn-primary { background:var(--ap); color:#fff; }
.btn-primary:hover  { background:var(--ap-hover); }
.btn-primary:active { background:var(--ap-press); }
.btn-ghost { background:transparent; color:var(--ink); border:1px solid var(--hairline-strong); }
.btn-ghost:hover { border-color:var(--ink); }
.btn-ghost-inverse { background:transparent; color:#fff; border:1px solid rgba(255,255,255,0.28); }
.btn-ghost-inverse:hover { border-color:rgba(255,255,255,0.65); }

/* ── Hero — nu sur le canvas, la typo porte tout ─────────────────────── */
.mkt-hero {
  padding:164px 0 56px;
  position:relative; overflow:hidden;
  text-align:center;
}
.mkt-hero::before {
  content:""; position:absolute; inset:0; z-index:0;
  background:url("<?= str_replace(['"', "'"], ['%22', '%27'], asset('images/backgroud-etablissements-web.jpg')) ?>") center top / cover no-repeat;
  opacity:0.12;
  -webkit-mask-image:linear-gradient(to bottom, #000 0%, transparent 88%);
          mask-image:linear-gradient(to bottom, #000 0%, transparent 88%);
  pointer-events:none;
}
.mkt-hero-inner { position:relative; z-index:2; max-width:800px; margin:0 auto; }
.mkt-eyebrow {
  display:block;
  font-size:11px; font-weight:700; letter-spacing:0.14em; text-transform:uppercase;
  color:var(--ap); margin-bottom:20px;
}
.mkt-hero h1 {
  font-size:clamp(36px,5.4vw,58px); font-weight:400;
  letter-spacing:-0.03em; line-height:1.08;
  margin:0 0 20px; color:var(--ink);
  text-wrap:balance;
}
.mkt-hero h1 .hl { color:var(--ap); }
.mkt-hero p {
  font-size:clamp(16px,1.4vw,19px); color:var(--ink-2);
  line-height:1.65; margin:0 auto 36px; font-weight:400; max-width:620px;
}

/* Recherche — le CTA principal de la page */
.mkt-search-wrap { position:relative; max-width:600px; margin:0 auto; }
.mkt-search-icon { position:absolute; left:20px; top:50%; transform:translateY(-50%); color:var(--ink-3); pointer-events:none; }
.mkt-search-icon svg { width:19px; height:19px; }
.mkt-search {
  width:100%; padding:18px 20px 18px 54px;
  font-family:inherit; font-size:16px; font-weight:500;
  background:var(--card); color:var(--ink);
  border:1px solid var(--hairline-strong);
  border-radius:var(--r-md);
  outline:none;
  transition:border-color 180ms var(--ease), box-shadow 180ms var(--ease);
}
.mkt-search:hover { border-color:var(--ink-3); }
.mkt-search:focus { border-color:var(--ap); box-shadow:0 0 0 3px rgba(139,82,250,0.14); }
.mkt-search::placeholder { color:var(--ink-3); }

/* ── Dalle stats — une seule surface blanche, hairlines internes ─────── */
.mkt-stats { position:relative; z-index:2; margin:48px auto 0; max-width:760px; }
.mkt-stats-slab {
  display:grid; grid-template-columns:repeat(3,1fr);
  background:var(--card); border:1px solid var(--hairline);
  border-radius:var(--r-lg); overflow:hidden;
}
.mkt-stat {
  padding:24px 20px; text-align:center;
}
.mkt-stat + .mkt-stat { border-left:1px solid var(--hairline); }
.mkt-stat-num {
  display:block; font-size:34px; font-weight:300; letter-spacing:-0.02em;
  color:var(--ink); line-height:1.1; font-variant-numeric:tabular-nums;
}
.mkt-stat-num .unit { font-size:18px; font-weight:400; color:var(--ap); }
.mkt-stat-label {
  display:block; margin-top:6px;
  font-size:12px; font-weight:600; color:var(--ink-3);
  letter-spacing:0.02em;
}

/* ── Barre de filtres — hairlines sur le canvas ──────────────────────── */
.mkt-filters-bar {
  border-top:1px solid var(--hairline);
  border-bottom:1px solid var(--hairline);
  background:var(--paper);
  margin-top:56px;
}
.mkt-filters-inner {
  display:flex; align-items:center; justify-content:space-between;
  gap:16px; padding:14px 0; flex-wrap:wrap;
}
.mkt-tabs { display:flex; gap:4px; flex-wrap:wrap; }
.mkt-tab {
  padding:9px 16px; border-radius:var(--r-sm);
  font-size:13px; font-weight:600; cursor:pointer; border:none;
  background:transparent; color:var(--ink-2);
  transition:background 160ms var(--ease), color 160ms var(--ease);
}
.mkt-tab:hover  { background:var(--ap-tint); color:var(--ap); }
.mkt-tab.active { background:var(--ap); color:#fff; }
.mkt-filters-right { display:flex; align-items:center; gap:14px; }
.mkt-select {
  padding:9px 34px 9px 14px; border-radius:var(--r-sm);
  font-family:inherit; font-size:13px; font-weight:500; color:var(--ink);
  background:var(--card) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%239E9E9E' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E") no-repeat right 10px center;
  border:1px solid var(--hairline);
  -webkit-appearance:none; appearance:none; cursor:pointer;
  transition:border-color 160ms var(--ease);
}
.mkt-select:hover { border-color:var(--hairline-strong); }
.mkt-select:focus { outline:none; border-color:var(--ap); }
.mkt-count-label { font-size:13px; font-weight:600; color:var(--ink-3); white-space:nowrap; font-variant-numeric:tabular-nums; }

/* ── Grille ──────────────────────────────────────────────────────────── */
.mkt-main { padding:48px 0 96px; }
.mkt-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:20px; }

/* ── Carte établissement — aplat blanc, hairline, radius 12 ──────────── */
.etab-card {
  background:var(--card); border:1px solid var(--hairline);
  border-radius:var(--r-md); padding:26px;
  display:flex; flex-direction:column;
  transition:border-color 200ms var(--ease), box-shadow 200ms var(--ease), transform 120ms var(--ease);
  position:relative; isolation:isolate;
  text-decoration:none; color:inherit; cursor:pointer;
}
.etab-card:hover {
  border-color:var(--hairline-strong);
  box-shadow:var(--shadow-hover);
}
.etab-card:active { transform:scale(0.99); }
.etab-card:focus-visible { outline:2px solid var(--ap); outline-offset:3px; }

.etab-card-top { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:18px; }
.etab-logo {
  width:52px; height:52px; border-radius:10px; flex:none;
  overflow:hidden; background:var(--ap-tint);
  display:flex; align-items:center; justify-content:center;
  font-size:21px; font-weight:700; color:var(--ap);
  border:1px solid var(--hairline);
}
.etab-logo img { width:100%; height:100%; object-fit:cover; }
.etab-type-badge {
  display:inline-flex; align-items:center; gap:6px;
  font-size:10px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase;
  color:var(--ink-2); background:transparent;
  padding:5px 11px; border-radius:var(--r-pill);
  border:1px solid var(--hairline); white-space:nowrap;
}
.etab-type-badge .tint { width:7px; height:7px; border-radius:999px; flex:none; }

.etab-card-body { flex:1; }
.etab-card-name {
  font-size:17px; font-weight:600; letter-spacing:-0.01em; line-height:1.3;
  color:var(--ink); margin:0 0 6px;
}
.etab-card-loc {
  display:inline-flex; align-items:center; gap:6px;
  font-size:13px; font-weight:500; color:var(--ink-3);
}
.etab-card-loc svg { width:13px; height:13px; }

/* Note moyenne 5 étoiles */
.etab-rating { display:flex; align-items:center; gap:7px; margin-top:8px; }
.etab-rating-val { font-size:13px; font-weight:700; color:var(--ink); font-variant-numeric:tabular-nums; }
.etab-stars { position:relative; display:inline-block; font-size:14px; line-height:1; letter-spacing:1px; color:rgba(26,26,26,0.16); }
.etab-stars::before { content:'★★★★★'; }
.etab-stars-fill { position:absolute; top:0; left:0; overflow:hidden; white-space:nowrap; color:#F6B100; }
.etab-stars-fill::before { content:'★★★★★'; }
.etab-rating-count { font-size:12px; font-weight:500; color:var(--ink-3); }

.etab-card-divider { height:1px; background:var(--hairline); margin:18px 0; }

.etab-card-meta { display:flex; flex-direction:column; gap:10px; margin-bottom:22px; }
.etab-card-meta-row {
  display:flex; align-items:center; gap:10px;
  font-size:13px; color:var(--ink-2);
}
.etab-card-meta-row svg { width:15px; height:15px; color:var(--ink-3); flex:none; }
.etab-card-price { font-weight:700; color:var(--ap); }

.etab-card-foot { margin-top:auto; }
.etab-btn {
  width:100%; display:flex; align-items:center; justify-content:center; gap:8px;
  padding:12px 18px; border-radius:var(--r-sm);
  font-size:14px; font-weight:600;
  background:var(--ap); color:#fff;
  transition:background 200ms var(--ease), transform 120ms var(--ease);
  border:none; cursor:pointer;
}
.etab-btn svg { width:15px; height:15px; transition:transform 200ms var(--ease); }
.etab-card:hover .etab-btn { background:var(--ap-hover); }
.etab-card:hover .etab-btn svg { transform:translateX(3px); }
.etab-card:active .etab-btn { background:var(--ap-press); }

/* ── États vides ─────────────────────────────────────────────────────── */
.mkt-empty {
  grid-column:1/-1; text-align:center;
  padding:80px 24px; color:var(--ink-3);
}
.mkt-empty svg { margin:0 auto 20px; opacity:0.25; }
.mkt-empty h3 { font-size:20px; font-weight:600; color:var(--ink); margin:0 0 10px; }
.mkt-empty p  { font-size:15px; margin:0 0 28px; }

#no-results {
  display:none; grid-column:1/-1; text-align:center;
  padding:60px 24px; color:var(--ink-3);
}
#no-results svg { margin:0 auto 16px; opacity:0.2; }
#no-results p { font-size:15px; margin:0 0 20px; }

/* ── Bande charbon — CTA finale ──────────────────────────────────────── */
.mkt-band {
  background:var(--charcoal); color:#fff;
  padding:72px 0;
}
.mkt-band-inner { max-width:680px; margin:0 auto; text-align:center; }
.mkt-band .mkt-eyebrow { color:var(--ap-hover); }
.mkt-band h2 {
  font-size:clamp(26px,3.4vw,38px); font-weight:400;
  letter-spacing:-0.025em; line-height:1.15;
  margin:0 0 16px; text-wrap:balance;
}
.mkt-band p {
  font-size:16px; color:rgba(255,255,255,0.62); line-height:1.65;
  margin:0 0 32px;
}
.mkt-band-actions { display:flex; align-items:center; justify-content:center; gap:12px; flex-wrap:wrap; }

/* ── Footer ─────────────────────────────────────────────────────────── */
.mkt-footer {
  background:var(--charcoal); color:rgba(255,255,255,0.6);
  border-top:1px solid rgba(255,255,255,0.10);
  padding:36px 0; font-size:13px;
}
.mkt-foot-inner { display:flex; align-items:center; justify-content:space-between; gap:20px; flex-wrap:wrap; }
.mkt-foot-brand { display:inline-flex; align-items:center; gap:10px; }
.mkt-foot-brand img { width:28px; height:28px; flex:none; }
.mkt-foot-brand strong { color:#fff; font-weight:700; font-size:14px; }
.mkt-foot-links { display:flex; gap:20px; }
.mkt-foot-links a { color:rgba(255,255,255,0.5); transition:color 150ms; }
.mkt-foot-links a:hover { color:#fff; }

/* ── Responsive ─────────────────────────────────────────────────────── */
@media (max-width:960px) {
  .mkt-grid { grid-template-columns:repeat(2,1fr); }
}
@media (max-width:640px) {
  .wrap { padding:0 20px; }
  .mkt-grid { grid-template-columns:1fr; }
  .mkt-hero { padding:128px 0 40px; }
  .mkt-stats-slab { grid-template-columns:1fr; }
  .mkt-stat + .mkt-stat { border-left:none; border-top:1px solid var(--hairline); }
  .mkt-filters-inner { flex-direction:column; align-items:flex-start; }
  .mkt-filters-right { width:100%; }
  .mkt-select { flex:1; }
}

@media (prefers-reduced-motion: reduce) {
  *,*::before,*::after { transition-duration:0.01ms !important; animation:none !important; }
}
</style>
</head>
<body>

<!-- ════════════ HEADER — navbar site unifiée (partial partagé) ════════════ -->
<?php $navActive = 'orientation'; require dirname(__DIR__) . '/partials/site-navbar.php'; ?>

<!-- ════════════ HERO ════════════ -->
<section class="mkt-hero">
  <div class="mkt-hero-inner wrap">
    <span class="mkt-eyebrow">Orientation · Gabon 2026</span>
    <?php if ($heroTitre !== ''): ?>
    <h1><?= e($heroTitre) ?> — <span class="hl">lance ta carrière</span>.</h1>
    <?php else: ?>
    <h1>Trouve ton école, <span class="hl">lance ta carrière</span>.</h1>
    <?php endif; ?>
    <p>Compare les établissements, découvre leurs formations et dépose ta candidature en quelques minutes — tout en ligne, gratuitement.</p>

    <div class="mkt-search-wrap">
      <span class="mkt-search-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
        </svg>
      </span>
      <input id="mktSearch" type="text" class="mkt-search"
             placeholder="Rechercher un établissement, une filière, une ville…"
             value="<?= e($initialQ) ?>"
             autocomplete="off" spellcheck="false"
             aria-label="Rechercher un établissement ou une ville">
    </div>

    <?php if ($total > 0): ?>
    <div class="mkt-stats">
      <div class="mkt-stats-slab">
        <div class="mkt-stat">
          <span class="mkt-stat-num"><?= $total ?></span>
          <span class="mkt-stat-label">Établissement<?= $total > 1 ? 's' : '' ?> partenaire<?= $total > 1 ? 's' : '' ?></span>
        </div>
        <?php if ($totalFormations > 0): ?>
        <div class="mkt-stat">
          <span class="mkt-stat-num"><?= $totalFormations ?></span>
          <span class="mkt-stat-label">Formation<?= $totalFormations > 1 ? 's' : '' ?> disponible<?= $totalFormations > 1 ? 's' : '' ?></span>
        </div>
        <?php endif; ?>
        <div class="mkt-stat">
          <span class="mkt-stat-num">100<span class="unit">&nbsp;%</span></span>
          <span class="mkt-stat-label">Candidature en ligne</span>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- ════════════ FILTRES ════════════ -->
<div class="mkt-filters-bar">
  <div class="wrap mkt-filters-inner">
    <nav class="mkt-tabs" aria-label="Type d'établissement">
      <a class="mkt-tab<?= $initialType === '' ? ' active' : '' ?>" href="<?= url('/orientation') ?>">Accueil orientation</a>
      <a class="mkt-tab<?= $initialType === 'universite' ? ' active' : '' ?>" href="<?= url('/orientation/universites') ?>">Universités</a>
      <a class="mkt-tab<?= in_array($initialType, ['grande_ecole', 'institut'], true) ? ' active' : '' ?>" href="<?= url('/orientation/grandes-ecoles') ?>">Grandes écoles</a>
    </nav>
    <div class="mkt-filters-right">
      <?php if (!empty($villes)): ?>
      <select id="mktVille" class="mkt-select" aria-label="Filtrer par ville">
        <option value="">Toutes les villes</option>
        <?php foreach ($villes as $v): ?>
        <option value="<?= e(strtolower($v)) ?>"><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
      <?php endif; ?>
      <span class="mkt-count-label" id="mktCount" aria-live="polite">
        <?= $total ?> établissement<?= $total > 1 ? 's' : '' ?>
      </span>
    </div>
  </div>
</div>

<!-- ════════════ GRILLE ════════════ -->
<main class="mkt-main">
  <div class="wrap">
    <?php if (empty($etablissements) && $isRecherche): ?>
    <!-- Recherche sans résultat : mot-clé inconnu ou aucun établissement du type -->
    <div class="mkt-grid">
      <div class="mkt-empty">
        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
          <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
          <path d="M8 11h6M11 8v6" opacity=".4"/>
        </svg>
        <h3>Aucun résultat<?= $initialQ !== '' ? ' pour « ' . e($initialQ) . ' »' : '' ?></h3>
        <p>Aucun établissement ne propose de formation correspondante.<br>Essaie un autre mot-clé (filière, spécialité…) ou explore la liste complète.</p>
        <a class="btn btn-primary" href="<?= url('/orientation/recherche') ?>">Voir tous les établissements</a>
      </div>
    </div>
    <?php elseif (empty($etablissements)): ?>
    <!-- État vide global (aucun établissement actif) -->
    <div class="mkt-grid">
      <div class="mkt-empty">
        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
          <path d="M22 10 12 5 2 10l10 5 10-5z"/>
          <path d="M6 12v5c0 1 2.7 2.5 6 2.5s6-1.5 6-2.5v-5M22 10v6"/>
        </svg>
        <h3>Aucun établissement disponible</h3>
        <p>Les établissements partenaires seront bientôt disponibles.</p>
        <a class="btn btn-primary" href="<?= url('/') ?>">Retour à l'accueil</a>
      </div>
    </div>
    <?php else: ?>

    <div class="mkt-grid" id="mktGrid">
    <?php foreach ($etablissements as $etab): ?>
      <?php
        $id         = (int)$etab['id'];
        $logoUrl    = $logoUrls[$id] ?? '';
        $initial    = strtoupper(mb_substr($etab['nom'], 0, 1, 'UTF-8'));
        $typeLabel  = $typeLabels[$etab['type'] ?? ''] ?? ($etab['type'] ?? '');
        $nbForm     = (int)($etab['nb_formations'] ?? 0);
        $prixMin    = (int)($etab['prix_min'] ?? 0);
        $couleur    = preg_match('/^#[0-9A-Fa-f]{6}$/', $etab['couleur_principale'] ?? '') ? $etab['couleur_principale'] : '#8B52FA';
        $slug       = $etab['slug'];
        // Index de recherche : nom + ville + type + intitulés de formations +
        // filières + spécialités proposées (PAS la description libre → évite les
        // faux positifs). « management » retrouve l'établissement qui la propose.
        $searchData = mb_strtolower(trim(implode(' ', array_filter([
            $etab['nom']            ?? '',
            $etab['ville']          ?? '',
            $typeLabel,
            $etab['kw_formations']  ?? '',
            $etab['kw_filieres']    ?? '',
            $etab['kw_specialites'] ?? '',
        ]))), 'UTF-8');
      ?>
      <a class="etab-card"
         href="<?= url('/orientation/' . e($slug)) ?>"
         data-type="<?= e($etab['type'] ?? '') ?>"
         data-ville="<?= e(strtolower($etab['ville'] ?? '')) ?>"
         data-search="<?= e($searchData) ?>">

        <div class="etab-card-top">
          <div class="etab-logo" style="background:<?= e($couleur) ?>14;color:<?= e($couleur) ?>;">
            <?php if ($logoUrl): ?>
            <img src="<?= e($logoUrl) ?>" alt="Logo <?= e($etab['nom']) ?>">
            <?php else: ?>
            <?= $initial ?>
            <?php endif; ?>
          </div>
          <span class="etab-type-badge">
            <span class="tint" style="background:<?= e($couleur) ?>;"></span>
            <?= e($typeLabel) ?>
          </span>
        </div>

        <div class="etab-card-body">
          <h2 class="etab-card-name"><?= e($etab['nom']) ?></h2>
          <span class="etab-card-loc">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <?= e($etab['ville'] ?? 'Gabon') ?>
          </span>
          <?php $agg = $avisAgg[$id] ?? null; ?>
          <?php if ($agg && $agg['nb_avis'] > 0): ?>
          <div class="etab-rating" aria-label="Note moyenne <?= number_format($agg['note_moyenne'], 1, ',', ' ') ?> sur 5 (<?= $agg['nb_avis'] ?> avis)">
            <span class="etab-rating-val"><?= number_format($agg['note_moyenne'], 1, ',', ' ') ?></span>
            <span class="etab-stars"><span class="etab-stars-fill" style="width:<?= round($agg['note_moyenne'] / 5 * 100, 1) ?>%;"></span></span>
            <span class="etab-rating-count">(<?= $agg['nb_avis'] ?> avis)</span>
          </div>
          <?php else: ?>
          <div class="etab-rating">
            <span class="etab-stars" aria-hidden="true"><span class="etab-stars-fill" style="width:0;"></span></span>
            <span class="etab-rating-count">Aucun avis pour l'instant</span>
          </div>
          <?php endif; ?>
        </div>

        <div class="etab-card-divider"></div>

        <div class="etab-card-meta">
          <div class="etab-card-meta-row">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10 12 5 2 10l10 5 10-5z"/><path d="M6 12v5c0 1 2.7 2.5 6 2.5s6-1.5 6-2.5v-5"/></svg>
            <span><?= $nbForm ?> formation<?= $nbForm > 1 ? 's' : '' ?> disponible<?= $nbForm > 1 ? 's' : '' ?></span>
          </div>
          <div class="etab-card-meta-row">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            <?php if ($prixMin > 0): ?>
            <span>À partir de <span class="etab-card-price"><?= number_format($prixMin, 0, ',', ' ') ?> FCFA/an</span></span>
            <?php elseif ((int)($etab['prix_preinscription'] ?? 0) === 0): ?>
            <span class="etab-card-price">Préinscription gratuite</span>
            <?php else: ?>
            <span>Consulter les tarifs</span>
            <?php endif; ?>
          </div>
          <?php if (!empty($etab['date_limite_dossier'])): ?>
          <div class="etab-card-meta-row">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
            <span>Date limite : <?= e(date('j M Y', strtotime($etab['date_limite_dossier']))) ?></span>
          </div>
          <?php endif; ?>
        </div>

        <div class="etab-card-foot">
          <span class="etab-btn">
            Découvrir
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
          </span>
        </div>
      </a>
    <?php endforeach; ?>

      <!-- Aucun résultat après filtre -->
      <div id="no-results">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
          <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
          <path d="M8 11h6M11 8v6" opacity=".4"/>
        </svg>
        <p>Aucun établissement ne correspond à ta recherche.<br>Essaie un autre nom ou une autre ville.</p>
        <button type="button" class="btn btn-ghost" id="mktReset">Réinitialiser les filtres</button>
      </div>
    </div>
    <?php endif; ?>
  </div>
</main>

<!-- ════════════ BANDE CHARBON — CTA ════════════ -->
<section class="mkt-band">
  <div class="wrap mkt-band-inner">
    <span class="mkt-eyebrow">Ta passerelle vers l'avenir</span>
    <h2>Tu hésites encore sur ton orientation&nbsp;?</h2>
    <p>Explore l'espace orientation pour comparer les filières, poser tes questions et préparer ton dossier — Connect'Academia t'accompagne à chaque étape, gratuitement.</p>
    <div class="mkt-band-actions">
      <a class="btn btn-primary" href="<?= url('/orientation') ?>">
        Explorer l'orientation
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
      </a>
      <a class="btn btn-ghost-inverse" href="<?= url('/auth/choisir') ?>">Créer un compte</a>
    </div>
  </div>
</section>

<!-- ════════════ FOOTER ════════════ -->
<footer class="mkt-footer">
  <div class="wrap mkt-foot-inner">
    <div class="mkt-foot-brand">
      <img src="<?= BASE_URL ?>/assets/images/logo-officiel.png" width="28" height="28" alt="">
      <strong>Connect'Academia</strong>
      <span>· Orientation Gabon 2026</span>
    </div>
    <nav class="mkt-foot-links">
      <a href="<?= url('/auth/connexion') ?>">Connexion</a>
      <a href="<?= url('/auth/inscription') ?>">Inscription</a>
    </nav>
  </div>
</footer>

<script>
(function () {
  var cards      = document.querySelectorAll('.etab-card');
  var noResults  = document.getElementById('no-results');
  var countLabel = document.getElementById('mktCount');
  var searchInput= document.getElementById('mktSearch');
  var villeSelect= document.getElementById('mktVille');
  var resetBtn   = document.getElementById('mktReset');

  var state = { q: (searchInput && searchInput.value) || '', type: '', ville: '' };

  function applyFilters() {
    var q     = state.q.toLowerCase().trim();
    var type  = state.type;
    var ville = state.ville;
    var visible = 0;

    cards.forEach(function (card) {
      var matchQ     = !q     || card.dataset.search.includes(q);
      var matchType  = !type  || card.dataset.type  === type;
      var matchVille = !ville || card.dataset.ville === ville;
      var show = matchQ && matchType && matchVille;
      card.style.display = show ? '' : 'none';
      if (show) visible++;
    });

    if (noResults) noResults.style.display = visible === 0 ? 'block' : 'none';
    if (countLabel) countLabel.textContent = visible + ' établissement' + (visible > 1 ? 's' : '');
  }

  if (searchInput) {
    searchInput.addEventListener('input', function () {
      state.q = this.value;
      applyFilters();
    });
  }

  // Les onglets de type sont des liens serveur (marketplaces dédiées) —
  // aucun filtrage client par type nécessaire.

  if (villeSelect) {
    villeSelect.addEventListener('change', function () {
      state.ville = this.value;
      applyFilters();
    });
  }

  if (resetBtn) {
    resetBtn.addEventListener('click', function () {
      state.q = ''; state.ville = '';
      if (searchInput) searchInput.value = '';
      if (villeSelect) villeSelect.value = '';
      applyFilters();
      if (searchInput) searchInput.focus();
    });
  }

  // Applique la recherche pré-remplie (?q=…) dès le chargement
  if (state.q) applyFilters();

  // Apparition douce des cartes (désactivée si reduced-motion)
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (!reduced && 'IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) {
          e.target.style.opacity = '1';
          e.target.style.transform = 'none';
          io.unobserve(e.target);
        }
      });
    }, { threshold: 0.08 });
    cards.forEach(function (card, i) {
      card.style.opacity = '0';
      card.style.transform = 'translateY(12px)';
      card.style.transition = 'opacity 0.4s ease ' + (i % 3 * 70) + 'ms, transform 0.4s ease ' + (i % 3 * 70) + 'ms';
      io.observe(card);
    });
  }
}());
</script>
</body>
</html>
