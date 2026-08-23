<?php
// Choix du profil avant inscription — page autonome (layout '').
// Cartes en verre "Liquid Glass" (WebGL, skill liquid-glass-js) sur fond blanc
// cassé (crème) de la marque : quelques halos violets très doux derrière les
// cartes donnent au verre de quoi réfracter (sinon il paraît gris sur fond uni),
// façon Apple light-mode. Repli CSS backdrop-filter si WebGL indisponible /
// prefers-reduced-motion. Aucune icône. Page statique — liens via url(). On tutoie.
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle ?? "S'inscrire — Connect'Academia") ?></title>
  <link rel="icon" href="<?= asset('images/logo-officiel.png') ?>" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset('css/glass.css') ?>">
  <style>
    :root {
      --canvas:#faf9f5; --hairline:#e6dfd8;
      --ink:#141413; --body:#3d3d3a; --muted:#6c6a64; --faint:#8e8b82;
      --purple:#8B52FA; --purple-press:#7440D9; --purple-tint:#F3EFFF;
      --sans:'Montserrat',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;
      --ease:cubic-bezier(.2,.8,.2,1);
    }
    * { margin:0; padding:0; box-sizing:border-box; }
    html { -webkit-text-size-adjust:100%; }
    body {
      font-family:var(--sans); color:var(--body); background:var(--canvas);
      min-height:100vh; display:flex; flex-direction:column; position:relative;
      -webkit-font-smoothing:antialiased; text-rendering:optimizeLegibility; overflow-x:hidden;
    }

    /* ── Fond blanc cassé + halos violets doux (réfractés par le verre) ── */
    /* Blanc cassé "Claude" : crèmes chaudes superposées, aucune couleur d'accent.
       Modulation très douce — le verre a de quoi réfracter sans quitter le blanc cassé. */
    .ch-bg {
      position:fixed; inset:0; z-index:0; pointer-events:none;
      background:
        radial-gradient(58% 52% at 50% 8%,  #f7f2e9, transparent 70%),
        radial-gradient(66% 58% at 12% 92%, #f1ebdf, transparent 72%),
        radial-gradient(60% 54% at 92% 82%, #f3ede2, transparent 70%),
        radial-gradient(48% 44% at 84% 10%, #f6f0e6, transparent 68%),
        var(--canvas);
    }

    /* ── En-tête ── */
    .ch-top { position:relative; z-index:2; display:flex; align-items:center; justify-content:space-between; padding:24px 34px; }
    .ch-brand { display:inline-flex; align-items:center; gap:11px; text-decoration:none; color:var(--ink); }
    .ch-brand img { width:36px; height:36px; object-fit:contain; }
    .ch-brand b { font-weight:600; font-size:16px; letter-spacing:-.01em; }
    .ch-back {
      display:inline-flex; align-items:center; gap:7px; font-size:14px; font-weight:500;
      color:var(--muted); text-decoration:none; transition:color 150ms var(--ease);
    }
    .ch-back:hover { color:var(--ink); }
    .ch-back svg { width:15px; height:15px; }

    /* ── Corps ── */
    .ch-main { position:relative; z-index:2; flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:14px 24px 72px; }
    .ch-head { text-align:center; max-width:640px; margin:0 auto 44px; }
    .ch-eyebrow { font-size:12px; font-weight:600; letter-spacing:2px; text-transform:uppercase; color:var(--purple); margin-bottom:16px; }
    .ch-title { font-weight:700; letter-spacing:-.8px; font-size:42px; line-height:1.1; color:var(--ink); margin-bottom:16px; }
    .ch-lede { font-size:16.5px; line-height:1.6; color:var(--muted); max-width:46ch; margin:0 auto; }

    /* ── Grille de cartes ── */
    .ch-grid { width:100%; max-width:920px; display:grid; grid-template-columns:1fr 1fr; gap:26px; align-items:stretch; }
    .ch-card {
      position:relative; display:block; text-decoration:none; color:var(--ink);
      min-height:322px; border-radius:28px; isolation:isolate; overflow:hidden;
      background:linear-gradient(150deg, rgba(255,255,255,.66), rgba(255,255,255,.42));
      border:1px solid rgba(255,255,255,.7);
      backdrop-filter:blur(20px) saturate(150%); -webkit-backdrop-filter:blur(20px) saturate(150%);
      box-shadow:0 26px 60px -24px rgba(90,60,150,.28), inset 0 1px 0 rgba(255,255,255,.8);
      transition:transform 260ms var(--ease);
    }
    /* Une fois le verre WebGL monté : le canvas fournit le rendu, on retire le frosting CSS */
    .ch-card.is-glass {
      background:transparent; border-color:transparent;
      backdrop-filter:none; -webkit-backdrop-filter:none;
      box-shadow:0 26px 60px -24px rgba(90,60,150,.3);
    }
    .ch-card:hover { transform:translateY(-6px); }
    .ch-card:focus-visible { outline:2px solid var(--purple); outline-offset:4px; border-radius:28px; }
    @media (prefers-reduced-transparency:reduce) {
      .ch-card:not(.is-glass) { background:#fff; backdrop-filter:none; -webkit-backdrop-filter:none; border-color:var(--hairline); }
    }

    /* Corps de contenu (au-dessus du verre) */
    .ch-body {
      position:relative; z-index:1; height:100%; min-height:inherit;
      display:flex; flex-direction:column; align-items:flex-start; padding:32px 30px; gap:0;
    }
    .ch-kicker {
      font-size:11.5px; font-weight:700; letter-spacing:1.4px; text-transform:uppercase;
      color:var(--purple-press); padding:6px 12px; border-radius:999px;
      background:var(--purple-tint); border:1px solid rgba(139,82,250,.22);
    }
    .ch-body h2 { margin-top:auto; font-size:25px; font-weight:700; letter-spacing:-.3px; line-height:1.15; color:var(--ink); }
    .ch-body p { margin-top:11px; font-size:14.5px; line-height:1.55; color:var(--muted); max-width:30ch; }
    .ch-cue { margin-top:20px; display:inline-flex; align-items:center; gap:8px; font-size:14.5px; font-weight:600; color:var(--purple-press); }
    .ch-cue svg { width:18px; height:18px; transition:transform 240ms var(--ease); }
    .ch-card:hover .ch-cue svg { transform:translateX(5px); }

    /* Le verre WebGL occupe toute la carte, derrière le contenu */
    .ch-card .glass-container.ch-glass {
      position:absolute !important; inset:0; width:100%; height:100%;
      padding:0; gap:0; display:block; border-radius:28px;
    }
    .ch-card .glass-container.ch-glass canvas { border-radius:28px !important; }

    /* ── Pied ── */
    .ch-foot { position:relative; z-index:2; text-align:center; margin-top:38px; font-size:14.5px; color:var(--muted); }
    .ch-foot a { color:var(--purple-press); font-weight:600; text-decoration:none; border-bottom:1px solid rgba(116,64,217,.35); padding-bottom:1px; }
    .ch-foot a:hover { border-color:var(--purple-press); }

    /* ── Responsive ── */
    @media (max-width:760px) {
      .ch-grid { grid-template-columns:1fr; gap:18px; max-width:440px; }
      .ch-card { min-height:230px; }
      .ch-title { font-size:32px; }
      .ch-lede { font-size:15px; }
    }
    @media (max-width:520px) {
      .ch-top { padding:16px 18px; }
      .ch-back span { display:none; }
      .ch-main { padding:6px 16px 48px; }
      .ch-head { margin-bottom:32px; }
    }
    @media (prefers-reduced-motion:reduce) { * { transition:none !important; } }
  </style>
</head>
<body>

  <div class="ch-bg" aria-hidden="true"></div>

  <header class="ch-top">
    <a href="<?= url('/') ?>" class="ch-brand" aria-label="Connect'Academia, accueil">
      <img src="<?= asset('images/logo-officiel.png') ?>" alt="">
      <b>Connect'Academia</b>
    </a>
    <a href="<?= url('/auth/connexion') ?>" class="ch-back">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
      <span>Connexion</span>
    </a>
  </header>

  <main class="ch-main">
    <div class="ch-head">
      <div class="ch-eyebrow">Rejoindre Connect'Academia</div>
      <h1 class="ch-title">Bienvenue. Choisis ton profil.</h1>
      <p class="ch-lede">Deux façons d'utiliser la plateforme. Sélectionne celle qui te correspond pour créer ton compte en quelques secondes.</p>
    </div>

    <div class="ch-grid">
      <a class="ch-card" href="<?= url('/auth/inscription') ?>" data-glass>
        <div class="ch-body" data-html2canvas-ignore>
          <span class="ch-kicker">Élève</span>
          <h2>Je suis élève</h2>
          <p>Cours, apprentissage et communauté. Ton compte est lié à ta série du Bac.</p>
          <span class="ch-cue">Créer mon compte élève
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
          </span>
        </div>
      </a>

      <a class="ch-card" href="<?= url('/orientation/compte') ?>" data-glass>
        <div class="ch-body" data-html2canvas-ignore>
          <span class="ch-kicker">Visiteur</span>
          <h2>Je suis visiteur</h2>
          <p>Parents, candidats libres et étudiants : orientation, préinscriptions et suivi de tes dossiers.</p>
          <span class="ch-cue">Créer mon espace orientation
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
          </span>
        </div>
      </a>
    </div>

    <p class="ch-foot">Tu as déjà un compte ? <a href="<?= url('/auth/connexion') ?>">Se connecter</a></p>
  </main>

  <?php // ── Liquid Glass : html2canvas (CDN autorisé par la CSP) → container.js → build ── ?>
  <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
  <script src="<?= asset('js/glass/container.js') ?>"></script>
  <script src="<?= asset('js/glass/button.js') ?>"></script>
  <script>
  (function () {
    // Support : WebGL + pas de motion/transparence réduite + libs chargées
    var webglOK = (function () {
      try { var c = document.createElement('canvas'); return !!(c.getContext('webgl') || c.getContext('experimental-webgl')); }
      catch (e) { return false; }
    })();
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var canGlass = webglOK && !reduce && typeof html2canvas === 'function' && typeof Container === 'function';

    if (!canGlass) { return; } // les cartes gardent leur verre CSS par défaut

    // Verre clair façon Apple light-mode : teinte douce, réfraction visible mais discrète
    window.glassControls = {
      edgeIntensity: 0.02, rimIntensity: 0.1, baseIntensity: 0.01,
      edgeDistance: 0.15, rimDistance: 0.8, baseDistance: 0.1,
      cornerBoost: 0.035, rippleEffect: 0.1, blurRadius: 7.0, tintOpacity: 0.18
    };

    function buildGlass() {
      var cards = document.querySelectorAll('.ch-card[data-glass]');
      cards.forEach(function (card) {
        try {
          var glass = new Container({ type: 'rounded', borderRadius: 28, tintOpacity: 0.18 });
          glass.element.classList.add('ch-glass');
          var body = card.querySelector('.ch-body');
          card.insertBefore(glass.element, body); // verre derrière, contenu au-dessus (z-index)
          card.classList.add('is-glass');          // retire le frosting CSS, laisse le canvas
          glass.updateSizeFromDOM();
        } catch (e) {
          /* la carte conserve son verre CSS par défaut */
        }
      });
    }

    function start() {
      var ready = (document.fonts && document.fonts.ready) ? document.fonts.ready : Promise.resolve();
      ready.then(function () { requestAnimationFrame(function () { setTimeout(buildGlass, 60); }); });
    }

    if (document.readyState === 'complete') start();
    else window.addEventListener('load', start);

    // Resize : resync la taille des canvases
    var t;
    window.addEventListener('resize', function () {
      clearTimeout(t);
      t = setTimeout(function () {
        Container.instances.forEach(function (inst) { inst.updateSizeFromDOM && inst.updateSizeFromDOM(); inst.render && inst.render(); });
      }, 250);
    });
  })();
  </script>
</body>
</html>
