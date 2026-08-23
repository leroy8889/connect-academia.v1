<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- 
    - primary meta tag
  -->
  <title>Connect'Academia - Orientation</title>
  <meta name="title" content="Connect'Academia - Orientation">
  <meta name="description" content="Orientation éducative et conseils pour votre parcours académique au Gabon">

  <!-- 
    - favicon
  -->
  <link rel="shortcut icon" href="<?= BASE_URL ?>/assets/images/logo-officiel.png" type="image/svg+xml">

  <!-- 
    - custom css link
  -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css"
        integrity="sha384-42kyIPf7HDYLkGffmxDhSx/3Z/53wGBs3nD6wEFxsbeDc7rMO6mkYbkAcpRsnMU2"
        crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        integrity="sha384-iw3OoTErCYJJB9mCa8LNS2hbsQ7M3C0EpIsO/H5+EGAkPGc6rk+V8i04oW/K5xq0"
        crossorigin="anonymous">

  <!--
    - google font link
  -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,700;9..144,900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Boldonse&family=Montserrat:ital,wght@0,100..900;1,100..900;400;700;800&display=swap" rel="stylesheet">

<style>
    html, body { overflow-x: hidden; }
    :root {
    --primary-color: #8B52FA;
    --light-bg: #F3EFFF;
    --dark-text: #2D2D2D;
    --white: #FFFFFF;
}

.hero-orientation {
    position: relative;
    width: 100%;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    /* Remplace l'URL par une image réelle du Gabon */
    background: url('<?= css_safe(BASE_URL) ?>/assets/images/Backgroud_orienation.PNG') no-repeat center center/cover;
    font-family: 'Montserrat', sans-serif;
    color: var(--white);
    padding: 20px;
    text-align: center;
}

/* Overlay sombre pour faire ressortir le texte */
.hero-overlay {
    position: absolute;
    top: 0; left: 0; width: 100%; height: 100%;
    
    z-index: 1;
}

.hero-content {
    position: relative;
    z-index: 2;
    width: 100%;
    max-width: 1000px;
}

/* Style du Logo */
.brand-container {
    margin-bottom: 20px;
}

.main-title {
    font-weight: 900;
    margin: 0;
    line-height: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 2px;
  font-size: 3.2rem;
}

  .feather-icon {
  transform: rotate(-20deg);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  
}

.feather-icon .brand-icon {
  width: 1.9em;
  height: 1.9em;
  object-fit: contain;
  display: block;
}

@media (max-width: 480px) {
  .feather-icon .brand-icon {
    width: 1.1em;
    height: 1.1em;
  }
}

.bold-text {
    font-weight: 900;
}

.brand-sub {
    font-size: 1.5rem;
    letter-spacing: 1px;
    margin-top: -5px;
}

.tagline {
    font-size: 2.2rem;
    margin-bottom: 40px;
    font-weight: 400;
}

/* Onglets */
.category-tabs {
    display: flex;
    justify-content: center;
    gap: 25px;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.tab {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-weight: 800;
    transition: 0.3s;
    font-size: 1.5rem;
}

.tab i { font-size: 1.1rem; }
.tab:hover, .tab.active { color: var(--primary-color); }

/* Barre de Recherche */
.search-container {
    background: var(--white);
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

.search-box {
    display: flex;
    align-items: center;
    width: 100%;
}

.input-group {
    flex: 1;
    padding: 10px 15px;
}

.divider {
    border-left: 1px solid #ddd;
}

.search-box input, .search-box select {
    width: 100%;
    border: none;
    outline: none;
    font-size: 1.125rem;
    color: var(--dark-text);
    background: transparent;
}

.btn-search {
    background-color: var(--primary-color);
    color: var(--white);
    border: none;
    padding: 15px 35px;
    border-radius: 6px;
    font-weight: bold;
    cursor: pointer;
    transition: background 0.3s;
    font-size: 1rem;
}

.btn-search:hover {
    background-color: #7239e5; /* Version légèrement plus sombre du violet */
}

/* ── Résultats de recherche inline (affichés sous la barre, sans redirection) ── */
.search-results {
    max-width: 1000px;
    margin: 22px auto 0;
    text-align: left;
    display: none;
}
.search-results.is-visible { display: block; }
.sr-head {
    display: flex; align-items: center; justify-content: space-between;
    gap: 12px; margin-bottom: 14px;
    color: var(--white); font-size: 0.92rem; font-weight: 600;
}
.sr-count { opacity: 0.9; }
.sr-clear {
    background: rgba(255,255,255,0.14); color: var(--white);
    border: 1px solid rgba(255,255,255,0.3); border-radius: 999px;
    padding: 6px 14px; font-size: 0.8rem; font-weight: 600; cursor: pointer;
    transition: background 0.2s;
}
.sr-clear:hover { background: rgba(255,255,255,0.26); }
.sr-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
.sr-card {
    background: var(--white); border-radius: 12px; padding: 20px;
    text-align: left; color: var(--dark-text); text-decoration: none;
    display: flex; flex-direction: column;
    border: 1px solid rgba(0,0,0,0.06);
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.sr-card:hover { transform: translateY(-3px); box-shadow: 0 10px 28px rgba(0,0,0,0.22); }
.sr-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; margin-bottom: 14px; }
.sr-logo {
    width: 46px; height: 46px; border-radius: 10px; flex: none;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 18px; overflow: hidden;
}
.sr-logo img { width: 100%; height: 100%; object-fit: cover; }
.sr-badge {
    font-size: 10px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase;
    color: #6b6b6b; border: 1px solid rgba(0,0,0,0.1);
    padding: 4px 9px; border-radius: 999px; white-space: nowrap;
}
.sr-name { font-size: 16px; font-weight: 700; margin: 0 0 4px; line-height: 1.3; }
.sr-loc { font-size: 12.5px; color: #9a9a9a; }
.sr-rating { font-size: 12.5px; color: #5a5a5a; margin-top: 8px; }
.sr-rating .star { color: #F6B100; }
.sr-meta {
    font-size: 12.5px; color: #5a5a5a; margin-top: auto;
    padding-top: 14px; margin-top: 14px; border-top: 1px solid rgba(0,0,0,0.06);
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
}
.sr-price { color: var(--primary-color); font-weight: 700; }
.sr-cta { color: var(--primary-color); font-weight: 700; white-space: nowrap; }
.sr-empty {
    background: var(--white); border-radius: 12px; padding: 44px 24px;
    text-align: center; color: #6b6b6b; font-size: 0.95rem;
}
.sr-empty strong { color: var(--dark-text); display: block; font-size: 1.05rem; margin-bottom: 6px; }
@media (max-width: 900px) { .sr-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 640px) { .sr-grid { grid-template-columns: 1fr; } }

/* Responsivité (Mobile) */
@media (max-width: 768px) {
    .search-box {
        flex-direction: column;
    }
    
    .input-group {
        width: 100%;
        border-left: none;
        border-bottom: 1px solid #eee;
    }

    /* Empêche le zoom auto iOS au focus : 16px = seuil minimum sans zoom */
    .search-box input, .search-box select {
        font-size: 16px;
    }

    .btn-search {
        width: 100%;
        margin-top: 10px;
    }

    .category-tabs {
        gap: 15px;
    }
}


/* Styles pour la section des établissements */
.section-etablissements {
    padding: 80px 20px;
    background-image: url('<?= css_safe(BASE_URL) ?>/assets/images/image-mesh-gradient.png');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    text-align: center;
    font-family: 'Montserrat', sans-serif;
    
}

.section-title {
    color: #2D2D2D;
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 80px; /* Plus d'espace pour le décalage des cartes */
    font-family: 'Montserrat', sans-serif;
}

.cards-container1 {
    display: flex;
    justify-content: center;
    align-items: flex-start;
    gap: 30px;
    max-width: 1100px;
    margin: 0 auto;
    flex-wrap: wrap;
}

/* Base de la carte */
.card1 {
    position: relative;
    width: 280px;
    padding: 60px 20px 40px 20px;
    border-radius: 25px;
    transition: transform 0.3s ease;
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.10);
    box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
    background-color: rgba(45, 45, 45, 0.60);
}

.card1:hover {
    transform: translateY(-5px);
}

/* Couleurs des cartes */
.card-blue { background-color: #BDE4F9; } /* Le bleu de l'image */
.card-green { background-color: #C9E9E7; } /* Le vert de l'image */

/* L'icône flottante au-dessus */
.card-icon1 {
    position: absolute;
    top: -40px;
    left: 50%;
    transform: translateX(-50%);
    width: 80px;
    height: 80px;
    background-color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 15px rgba(0,0,0,0.1);
    font-size: 1.8rem;
    color: #2D2D2D;
}

/* Badge cercle autour de l'icône (effet de bordure claire) */
.card-icon1::before {
    content: '';
    position: absolute;
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background-color: inherit; /* Prend la couleur du parent au hover ou reste blanc */
    opacity: 0.3;
    z-index: -1;
}

.card-blue .card-icon1 { border: 8px solid #BDE4F9; }
.card-green .card-icon1 { border: 8px solid #C9E9E7; }

/* Contenu de la carte */
.card1 h3 {
    font-size: 2rem;
    color: #2D2D2D;
    margin: 0;
    font-weight: 600;
}

.underline {
    width: 80px;
    height: 4px;
    background-color: #000;
    margin: 10px auto 25px auto;
}

.btn-savoir {
    display: inline-block;
    background-color: #FFFFFF;
    color: #2D2D2D;
    text-decoration: none;
    padding: 12px 30px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.9rem;
    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    transition: 0.3s;
}

.btn-savoir:hover {
    background-color: #2D2D2D;
    color: #ffffff;
}

/* Effet de décalage pour la carte du milieu (Identique à l'image) */
.offset-down {
    margin-top: 40px;
}

/* RESPONSIVE */
@media (max-width: 992px) {
    .offset-down {
        margin-top: 0; /* On annule le décalage sur tablette/mobile */
    }
    .cards-container1 {
        gap: 60px; /* Plus d'espace vertical entre les cartes */
    }
    .section-title {
        margin-bottom: 60px;
    }
}

@media (max-width: 480px) {
    .card1 {
        width: 100%;
        max-width: 300px;
    }
}

/* Section Statistiques */
.section-stats {
    padding: 100px 20px;
    background-image: url('<?= css_safe(BASE_URL) ?>/assets/images/image-mesh-gradient.png');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    font-family: 'Montserrat', sans-serif;
    
}

.container-stats {
    max-width: 1400px;
    margin: 0 auto;
}

.stats-cards-wrapper {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 60px;
    flex-wrap: wrap;
}

.stat-card {
    flex: 1;
    min-width: 280px;
    max-width: 350px;
    background: rgba(255, 255, 255, 0.95);
    border-radius: 20px;
    padding: 50px 40px;
    text-align: center;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.stat-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 15px 50px rgba(0, 0, 0, 0.15);
}

.stat-number {
    font-size: 4.5rem;
    font-weight: 800;
    color: var(--primary-color);
    line-height: 1;
    margin-bottom: 20px;
    font-family: 'Montserrat', sans-serif;
}

.stat-label {
    font-size: 1.3rem;
    font-weight: 600;
    color: var(--dark-text);
    line-height: 1.4;
    font-family: 'Montserrat', sans-serif;
}

/* Responsive pour la section stats */
@media (max-width: 992px) {
    .stats-cards-wrapper {
        gap: 40px;
    }
    
    .stat-card {
        min-width: 250px;
        padding: 40px 30px;
    }
    
    .stat-number {
        font-size: 4rem;
    }
    
    .stat-label {
        font-size: 1.2rem;
    }
}

@media (max-width: 768px) {
    .section-stats {
        padding: 60px 20px;
    }
    
    .stats-cards-wrapper {
        flex-direction: column;
        gap: 30px;
    }
    
    .stat-card {
        width: 100%;
        max-width: 400px;
        min-width: auto;
    }
    
    .stat-number {
        font-size: 3.5rem;
    }
    
    .stat-label {
        font-size: 1.1rem;
    }
}

@media (max-width: 480px) {
    .stat-card {
        padding: 35px 25px;
    }
    
    .stat-number {
        font-size: 3rem;
        margin-bottom: 15px;
    }
    
    .stat-label {
        font-size: 1rem;
    }
}
  

  /* Ajustements responsive pour le titre principal */
  @media (max-width: 992px) {
    .main-title { font-size: 2.6rem; gap: 5px; }
  }

  @media (max-width: 480px) {
    .main-title { font-size: 1.8rem; gap: 4px; }
  }

  /* --- CONFIGURATION --- */
.success-page {
    background-color: #0c0c14ee;
    color: white;
    padding: 100px 5%;
    display: flex;
    flex-direction: column;
    align-items: center;
    font-family: 'Inter', sans-serif;
    text-align: center;
}

/* --- HERO --- */
.party-icon {
    width: 70px;
    height: 70px;
    background: rgba(139, 92, 246, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 30px auto;
}

.success-hero h1 {
    font-size: 3.5rem;
    font-weight: 800;
    margin-bottom: 20px;
    letter-spacing: -1px;
}

.success-sub {
    color: #9ca3af;
    max-width: 600px;
    margin: 0 auto;
    line-height: 1.6;
}

/* --- STEPS --- */
.steps-section { margin-top: 80px; width: 100%; max-width: 1100px; }
.steps-label {
    font-size: 11px;
    font-weight: 800;
    color: #8b5cf6;
    letter-spacing: 2px;
    display: block;
    margin-bottom: 40px;
}

.steps-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.step-mini-card {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.05);
    padding: 40px 30px;
    border-radius: 24px;
}

.step-icon-bg {
    width: 35px;
    height: 35px;
    background: rgba(139, 92, 246, 0.2);
    border-radius: 10px;
    margin: 0 auto 20px auto;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #a78bfa;
}

.step-mini-card h4 { font-size: 18px; margin-bottom: 15px; }
.step-mini-card p { color: #6b7280; font-size: 13px; line-height: 1.5; }

/* --- AMBASSADOR BOX --- */
.ambassador-box {
    background: linear-gradient(145deg, #161625 0%, #0c0c14 100%);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 40px;
    width: 100%;
    max-width: 1100px;
    margin: 80px 0;
    padding: 60px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    text-align: left;
    overflow: hidden;
    position: relative;
}

.ambassador-content { flex: 1; z-index: 2; }

.badge-exclu {
    background: rgba(139, 92, 246, 0.15);
    color: #a78bfa;
    padding: 6px 14px;
    border-radius: 100px;
    font-size: 10px;
    font-weight: 800;
    display: inline-block;
    margin-bottom: 25px;
}

.ambassador-content h2 { font-size: 2.5rem; line-height: 1.2; margin-bottom: 20px; }
.purple-text { color: #a78bfa; }
.ambassador-content p { color: #9ca3af; margin-bottom: 35px; max-width: 500px; font-size: 15px; }

.btn-ambassador {
    background: #8b5cf6;
    color: white;
    padding: 16px 35px;
    border-radius: 100px;
    border: none;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 10px 20px rgba(139, 92, 246, 0.3);
}

/* Visuel Cartes Empilées */
.ambassador-visual {
    flex: 1;
    position: relative;
    height: 300px;
    display: flex;
    justify-content: center;
    align-items: center;
}

.stacked-card {
    position: absolute;
    width: 220px;
    height: 220px;
    border-radius: 25px;
}

.card-1 {
    background: #2a2a40;
    transform: rotate(-10deg);
    opacity: 0.3;
}

.card-2 {
    background: #4c3a8e;
    transform: rotate(5deg);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    box-shadow: 0 20px 40px rgba(0,0,0,0.4);
}

.star-circle {
    width: 80px;
    height: 80px;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 40px;
}

.free-badge {
    position: absolute;
    bottom: -10px;
    right: -20px;
    background: #8b5cf6;
    padding: 10px 20px;
    border-radius: 15px;
    font-weight: 800;
    font-size: 14px;
    box-shadow: 0 10px 20px rgba(0,0,0,0.3);
}

/* --- SHARE AREA --- */
.share-area p { color: #6b7280; font-size: 14px; margin-bottom: 25px; }
.share-buttons { display: flex; gap: 15px; justify-content: center; }

.btn-share {
    padding: 14px 28px;
    border-radius: 100px;
    border: none;
    color: white;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
}

.whatsapp { background: #25d366; box-shadow: 0 10px 20px rgba(37, 211, 102, 0.2); }
.facebook { background: #1877f2; box-shadow: 0 10px 20px rgba(24, 119, 242, 0.2); }
.copy-link { background: #1f1f2e; border: 1px solid rgba(255,255,255,0.05); }

/* --- RESPONSIVE --- */
@media (max-width: 900px) {
    .steps-grid { grid-template-columns: 1fr; }
    .ambassador-box { flex-direction: column; padding: 40px 20px; text-align: center; }
    .ambassador-visual { margin-top: 50px; width: 100%; }
    .share-buttons { flex-direction: column; width: 100%; max-width: 300px; }
    .success-hero h1 { font-size: 2.2rem; }
}

  /* ===== POPUP BIENTÔT DISPONIBLE ===== */
  /* popup supprimée — module orientation en ligne */

  /* ==========================================
     SECTION VIDÉO DE PRÉSENTATION
     ========================================== */

  .section-video-pres {
    padding: 80px 24px;
    background: #f7f7f9;
    font-family: 'Montserrat', sans-serif;
  }

  .video-pres-inner {
    max-width: 960px;
    margin: 0 auto;
  }

  /* Outer card blanche — fidèle à l'image */
  .video-pres-card {
    background: #ffffff;
    border-radius: 24px;
    padding: 24px;
    box-shadow:
      0 1px 3px rgba(0,0,0,0.06),
      0 12px 40px rgba(0,0,0,0.10);
    border: 1px solid rgba(0,0,0,0.06);
  }

  /* Player sombre centré dans la card — fidèle à l'image */
  .vp-player-wrap {
    background: #111118;
    border-radius: 14px;
    overflow: hidden;
    aspect-ratio: 16 / 9;
    width: 100%;
    box-shadow:
      0 4px 16px rgba(0,0,0,0.20),
      0 1px 3px rgba(0,0,0,0.12);
  }

  .vp-player-wrap video {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: contain;
  }

  /* Responsive tablette */
  @media (max-width: 768px) {
    .section-video-pres {
      padding: 60px 16px;
    }
    .video-pres-card {
      padding: 16px;
      border-radius: 18px;
    }
    .vp-player-wrap {
      border-radius: 10px;
    }
  }

  /* Responsive mobile */
  @media (max-width: 480px) {
    .section-video-pres {
      padding: 40px 12px;
    }
    .video-pres-card {
      padding: 10px;
      border-radius: 14px;
    }
    .vp-player-wrap {
      border-radius: 8px;
    }
  }

  /* Paysage mobile */
  @media (max-width: 960px) and (orientation: landscape) {
    .vp-player-wrap {
      max-height: 65vh;
      aspect-ratio: unset;
    }
    .vp-player-wrap video {
      max-height: 65vh;
    }
  }

  </style>

</head>

<body id="top">
  </div>

  <!--
    - #HEADER — navbar site unifiée (partial partagé)
  -->
  <?php $navActive = 'orientation'; require dirname(__DIR__) . '/partials/site-navbar.php'; ?>

  <section class="hero-orientation">
    <div class="hero-overlay"></div>
    
    <div class="hero-content">
        <!-- Logo / Titre -->
        <div class="brand-container">
            <h1 class="main-title">
              <span class="feather-icon"><img src="<?= BASE_URL ?>/assets/images/boussole-removebg-preview.png" alt="compass icon" class="brand-icon"></span>
              Mon <span class="bold-text">Orientation</span>
            </h1>
            
        </div>

        <p class="tagline">Le site dédié à l'orientation des étudiants au Gabon</p>

        <!-- Onglets de catégories — seul "Écoles" est actif (phases futures : Formations, Métiers, Pays) -->
        <div class="category-tabs">
            <div class="tab" style="opacity:.45;cursor:not-allowed" title="Bientôt disponible"><i class="fa-solid fa-graduation-cap"></i> Formations</div>
            <div class="tab active"><i class="fa-solid fa-building-columns"></i> Écoles</div>
            <div class="tab" style="opacity:.45;cursor:not-allowed" title="Bientôt disponible"><i class="fa-solid fa-briefcase"></i> Métiers</div>
            <div class="tab" style="opacity:.45;cursor:not-allowed" title="Bientôt disponible"><i class="fa-solid fa-globe"></i> Pays</div>
        </div>

        <!-- Barre de recherche -->
        <div class="search-container">
            <div class="search-box">
                <div class="input-group">
                    <input type="text" placeholder="Mot-clé : filière, spécialité, formation…" aria-label="Recherche par mot-clé">
                </div>
                <div class="input-group divider">
                    <select aria-label="Type d'établissement">
                        <option value="">Tous les établissements</option>
                        <option value="universite">Universités</option>
                        <option value="grande_ecole">Grandes écoles</option>
                    </select>
                </div>
                <button type="button" class="btn-search">
                    <i class="fa-solid fa-magnifying-glass"></i> Rechercher
                </button>
            </div>
        </div>

        <!-- Résultats de recherche affichés en place -->
        <div class="search-results" id="searchResults" aria-live="polite"></div>
    </div>
</section>

<section class="section-etablissements">
    <h2 class="section-title">Différents types d'établissements s'offrent à vous.</h2>

    <div class="cards-container1">
        <!-- Carte Public -->
        <div class="card1 card-blue">
            <div class="card-icon1">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <div class="card-content1">
                <h3>Universités</h3>
                <div class="underline"></div>
                <a href="<?= url('/orientation/universites') ?>" class="btn-savoir">Voir</a>
            </div>
        </div>

        

        <!-- Carte RUP -->
        <div class="card1 card-blue">
            <div class="card-icon1">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <div class="card-content1">
                <h3>Grandes écoles</h3>
                <div class="underline"></div>
                <a href="<?= url('/orientation/grandes-ecoles') ?>" class="btn-savoir">Voir</a>
            </div>
        </div>
    </div>
</section>

<!-- ========================================
     SECTION VIDÉO DE PRÉSENTATION
     ======================================== -->
<section class="section-video-pres">
    <div class="video-pres-inner">
        <div class="video-pres-card">
            <div class="vp-player-wrap">
                <video
                    src="<?= BASE_URL ?>/assets/images/video-orientation.MP4"
                    controls
                    preload="metadata"
                    playsinline
                    aria-label="Vidéo de présentation de Connect'Academia"
                >
                    Votre navigateur ne supporte pas la lecture vidéo.
                </video>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($statsHub) && array_sum($statsHub) > 0): ?>
<section class="section-stats">
    <div class="container-stats">
        <div class="stats-cards-wrapper">
            <div class="stat-card">
                <div class="stat-number"><?= (int)($statsHub['universites'] ?? 0) ?></div>
                <div class="stat-label">Université<?= ($statsHub['universites'] ?? 0) > 1 ? 's' : '' ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= (int)($statsHub['grandes_ecoles'] ?? 0) ?></div>
                <div class="stat-label">Grande<?= ($statsHub['grandes_ecoles'] ?? 0) > 1 ? 's' : '' ?> école<?= ($statsHub['grandes_ecoles'] ?? 0) > 1 ? 's' : '' ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= (int)($statsHub['preinscriptions'] ?? 0) ?></div>
                <div class="stat-label">Préinscription<?= ($statsHub['preinscriptions'] ?? 0) > 1 ? 's' : '' ?></div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="success-page">
  

  <!-- Bannière Ambassadeur -->
  <div class="ambassador-box">
      <div class="ambassador-content">
          <span class="badge-exclu">EXCLUSIVITÉ ÉTUDIANT</span>
          <h2>Deviens Ambassadeur <span class="purple-text">Connect' Academia</span></h2>
          <p>Aide tes camarades à s'orienter et débloque un <strong>Accès Premium GRATUIT</strong> à vie ainsi que des invitations à nos événements VIP.</p>
          <a href="https://chat.whatsapp.com/HMX65hiQcVwHUXA1i6iuv2?mode=gi_t"><button type="button" class="btn-ambassador">Rejoindre le Programme</button></a>
      </div>
      
      <!-- Visuel de droite (Cartes empilées) -->
      <div class="ambassador-visual">
          <div class="stacked-card card-1"></div>
          <div class="stacked-card card-2">
              <div class="star-circle">
                  <i class="fa-solid fa-star"></i>
              </div>
              <div class="free-badge">100% GRATUIT</div>
          </div>
      </div>
  </div>

  <!-- Partage Social -->
  <div class="share-area">
      <p>Partage la fonctionnalité Orientation avec tes camarades :</p>
      <div class="share-buttons">
          <button class="btn-share whatsapp">
              <a href="https://chat.whatsapp.com/BiE25HR0QjQFnPJTfqtDmx"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
          </button>
         
      </div>
  </div>
</section>

  <!-- 
    - #FOOTER
  -->
  <footer class="footer" style="background-image: url('<?= css_safe(BASE_URL) ?>/assets/images/footer-bg.png')">

    <div class="footer-top section">
      <div class="container grid-list">

        <div class="footer-brand">

          <a href="<?= url('/') ?>" class="logo">
            <img src="<?= BASE_URL ?>/assets/images/logo1-removebg-preview.png" width="162" height="50" alt="Connect'Academia logo">
          </a>

          <p class="footer-brand-text">
            La première plateforme dédiée à l'orientation des élèves et étudiants au Gabon.
          </p>

          <div class="wrapper">
            <span class="span">Call:</span>
            <a href="tel:+241077980375" class="footer-link">+241 077 98 03 75</a>
          </div>

          <div class="wrapper">
            <span class="span">Email:</span>
            <a href="mailto:contact@connectacademia.io" class="footer-link">contact@connectacademia.io</a>
          </div>

        </div>

        <ul class="footer-list">

          <li>
            <p class="footer-list-title">Plateforme</p>
          </li>

          <li>
            <a href="<?= url('/') ?>#about" class="footer-link">À propos</a>
          </li>

          <li>
            <a href="<?= url('/auth/inscription') ?>" class="footer-link">Inscription</a>
          </li>

          <li>
            <a href="<?= url('/') ?>" class="footer-link">Accueil</a>
          </li>

          <li>
            <a href="<?= BASE_URL ?>/assets/lib/tarifs.html" class="footer-link">100 % gratuit</a>
          </li>

        </ul>

        <ul class="footer-list">

          <li>
            <p class="footer-list-title">Liens</p>
          </li>

          <li>
            <a href="<?= BASE_URL ?>/contact" class="footer-link">Contact</a>
          </li>

          <li>
            <a href="<?= url('/') ?>#social-media" class="footer-link">Médias sociaux</a>
          </li>

          <li>
            <a href="<?= url('/auth/connexion') ?>" class="footer-link">Connexion</a>
          </li>

          <li>
            <a href="<?= BASE_URL ?>/assets/lib/mentions-legales.html" class="footer-link">Mentions légales</a>
          </li>

        </ul>

        <div class="footer-list">

          <p class="footer-list-title">Contacts</p>

          <p class="footer-list-text">
            Saisissez votre adresse e-mail pour vous inscrire à notre newsletter.
          </p>

          <form action="" class="newsletter-form">
            <input type="email" name="email_address" placeholder="Votre email" required class="input-field">

            <button type="submit" class="btn has-before">
              <span class="span">S'abonner</span>
              <ion-icon name="arrow-forward-outline" aria-hidden="true"></ion-icon>
            </button>
          </form>

          <ul class="social-list">

            <li>
              <a href="https://www.facebook.com/people/Connect-Academia/61568164406172/#" class="social-link">
                <ion-icon name="logo-facebook"></ion-icon>
              </a>
            </li>

            <li>
              <a href="https://www.instagram.com/connectacademia.ga" class="social-link">
                <ion-icon name="logo-instagram"></ion-icon>
              </a>
            </li>

            <li>
              <a href="https://chat.whatsapp.com/BiE25HR0QjQFnPJTfqtDmx" class="social-link">
                <ion-icon name="logo-whatsapp"></ion-icon>
              </a>
            </li>

            <li>
              <a href="https://www.tiktok.com/@connect.academia.ga" class="social-link">
                <ion-icon name="logo-tiktok"></ion-icon>
              </a>
            </li>

            <li>
              <a href="https://www.linkedin.com/company/connect-academia-ga/" class="social-link">
                <ion-icon name="logo-linkedin"></ion-icon>
              </a>
            </li>

          </ul>

        </div>

      </div>
    </div>

    <div class="footer-bottom">
      <div class="container">

        <p class="copyright">
          Copyright 2026 All Rights Reserved by <a href="https://www.instagram.com/connectacademia.ga" class="copyright-link">Connect'Academia</a>
        </p>

      </div>
    </div>

  </footer>


  <!-- 
    - #BACK TO TOP
  -->
  <a href="#top" class="back-top-btn" aria-label="back top top" data-back-top-btn>
    <ion-icon name="chevron-up" aria-hidden="true"></ion-icon>
  </a>


  <!-- 
    - custom js link
  -->
  <script src="<?= BASE_URL ?>/assets/js/script.js" defer></script>

  <script>
  (function () {
    /* Filet de sécurité vidéo
       Problème : MAMP peut servir le .mp4 sans Content-Type correct → le navigateur
       tente de naviguer vers l'URL du fichier, ce qui recharge la page.
       Ce script stoppe toute navigation non intentionnelle pendant la lecture. */
    document.addEventListener('DOMContentLoaded', function () {
      var vid = document.querySelector('.vp-player-wrap video');
      if (!vid) return;

      /* Capture le focus sur la vidéo au lancement
         → empêche les raccourcis clavier (Espace, Entrée) de déclencher
           des liens ou boutons en arrière-plan */
      vid.addEventListener('play', function () { vid.focus(); });

      /* Stoppe la propagation des clics hors du conteneur vidéo */
      var wrap = document.querySelector('.vp-player-wrap');
      if (wrap) {
        wrap.addEventListener('click', function (e) { e.stopPropagation(); });
      }

      /* Bloque toute tentative de navigation pendant la lecture vidéo */
      window.addEventListener('beforeunload', function (e) {
        if (!vid.paused && !vid.ended) {
          e.preventDefault();
          e.returnValue = '';
        }
      });

      /* Si le navigateur essaie de naviguer vers le fichier vidéo lui-même,
         on annule et on revient à la page d'orientation */
      document.addEventListener('visibilitychange', function () {
        if (document.hidden) return;
        if (performance && performance.getEntriesByType) {
          var navEntries = performance.getEntriesByType('navigation');
          if (navEntries.length && navEntries[0].type === 'back_forward') {
            if (!vid.paused) vid.pause();
          }
        }
      });
    });
  })();
  </script>

  <!--
    - ionicon link
  -->
  <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"
          integrity="sha384-EcogLzNzScolFpuLPk/kxJRnmu39K6J1j3+8yrfrebDq7qtbZoB2gfP0rD08JKit"
          crossorigin="anonymous"></script>
  <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"
          integrity="sha384-xYx1P7dxspoifaKXuLoaPybset7M4RzoZPDVwB+CrVVobxN3h90OQGVoHYHtCr9G"
          crossorigin="anonymous"></script>

  <!-- Index de recherche (établissements actifs) embarqué pour la recherche en place -->
  <script>
    window.__ORIENT__ = {
      base:  <?= json_encode(rtrim(url('/orientation'), '/') . '/', JSON_UNESCAPED_SLASHES) ?>,
      items: <?= json_encode($searchIndex ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    };
  </script>

  <script>
    (function () {
      /* Barre de recherche hero → filtre l'index et affiche les résultats en place */
      document.addEventListener('DOMContentLoaded', function () {
        var searchBtn    = document.querySelector('.btn-search');
        var searchInput  = document.querySelector('.search-container input[type="text"]');
        var searchSelect = document.querySelector('.search-container select');
        var resultsBox   = document.getElementById('searchResults');
        var DATA = (window.__ORIENT__ && window.__ORIENT__.items) || [];
        var BASE = (window.__ORIENT__ && window.__ORIENT__.base)  || '';

        function esc(s) {
          return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        function fmt(n) { return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ' '); }

        function stars(note) {
          var full = Math.round(note);
          var s = '';
          for (var i = 1; i <= 5; i++) s += (i <= full ? '★' : '☆');
          return s;
        }

        function priceLine(it) {
          if (it.prixMin > 0)      return '<span class="sr-price">dès ' + fmt(it.prixMin) + ' FCFA/an</span>';
          if (it.prixPreinsc === 0) return '<span class="sr-price">Préinscription gratuite</span>';
          return '<span>Consulter les tarifs</span>';
        }

        function cardHtml(it) {
          var logo = it.logo
            ? '<img src="' + esc(it.logo) + '" alt="' + esc(it.nom) + '">'
            : esc(it.initial);
          var rating = (it.note != null)
            ? '<div class="sr-rating"><span class="star">' + stars(it.note) + '</span> ' +
              String(it.note).replace('.', ',') + ' (' + it.nbAvis + ' avis)</div>'
            : '<div class="sr-rating" style="color:#b5b5b5">Aucun avis</div>';

          return '' +
            '<a class="sr-card" href="' + esc(BASE + it.slug) + '">' +
              '<div class="sr-top">' +
                '<div class="sr-logo" style="background:' + esc(it.couleur) + '1a;color:' + esc(it.couleur) + '">' + logo + '</div>' +
                '<span class="sr-badge">' + esc(it.typeLabel) + '</span>' +
              '</div>' +
              '<h3 class="sr-name">' + esc(it.nom) + '</h3>' +
              '<div class="sr-loc">📍 ' + esc(it.ville) + '</div>' +
              rating +
              '<div class="sr-meta">' +
                priceLine(it) +
                '<span class="sr-cta">Découvrir →</span>' +
              '</div>' +
            '</a>';
        }

        function render(list, q) {
          if (!list.length) {
            resultsBox.innerHTML =
              '<div class="sr-empty"><strong>Aucun résultat' +
              (q ? ' pour « ' + esc(q) + ' »' : '') + '</strong>' +
              'Essaie un autre mot-clé (filière, spécialité, ville…).</div>';
            return;
          }
          var head = '<div class="sr-head"><span class="sr-count">' +
            list.length + ' établissement' + (list.length > 1 ? 's' : '') +
            (q ? ' pour « ' + esc(q) + ' »' : '') +
            '</span><button type="button" class="sr-clear" id="srClear">Effacer</button></div>';
          resultsBox.innerHTML = head + '<div class="sr-grid">' + list.map(cardHtml).join('') + '</div>';
          var clr = document.getElementById('srClear');
          if (clr) clr.addEventListener('click', clearResults);
        }

        function clearResults() {
          resultsBox.classList.remove('is-visible');
          resultsBox.innerHTML = '';
          if (searchInput) { searchInput.value = ''; searchInput.focus(); }
          if (searchSelect) searchSelect.value = '';
        }

        function doSearch() {
          var q    = searchInput  ? searchInput.value.trim().toLowerCase() : '';
          var type = searchSelect ? searchSelect.value : '';

          var list = DATA.filter(function (it) {
            var matchQ    = !q || (it.kw || '').indexOf(q) !== -1;
            var matchType = !type || it.type === type ||
                            (type === 'grande_ecole' && it.type === 'institut');
            return matchQ && matchType;
          });

          render(list, q);
          resultsBox.classList.add('is-visible');
          resultsBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        if (searchBtn)   searchBtn.addEventListener('click', doSearch);
        if (searchInput) searchInput.addEventListener('keydown', function (e) {
          if (e.key === 'Enter') { e.preventDefault(); doSearch(); }
        });

        // Deep-link : /orientation?q=management&type=grande_ecole lance la recherche
        try {
          var params = new URLSearchParams(window.location.search);
          var pq = params.get('q'), pt = params.get('type');
          if ((pq || pt) && searchInput) {
            if (pq) searchInput.value = pq;
            if (pt && searchSelect) searchSelect.value = pt;
            doSearch();
          }
        } catch (e) {}
      });
    })();
  </script>

  

    <script src="https://cdn.botpress.cloud/webchat/v3.7/inject.js"></script>
<script src="https://files.bpcontent.cloud/2026/08/01/17/20260801171922-4PKEFZ4J.js" defer></script>
    
  
    

</body>

</html>
