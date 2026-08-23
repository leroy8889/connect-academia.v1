<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>Connect'Academia - L'orientation éducative du Gabon</title>
  <meta name="title" content="Connect'Academia - L'orientation éducative du Gabon">
  <meta name="description" content="Apprentissage, Orientation et Entraide : la première plateforme tout-en-un conçue pour les élèves et étudiants du Gabon.">
  <meta name="description" content="Connect'Academia, l'orientation éducative au Gabon ">
  <meta name="google-site-verification" content="fmg3ovN0W_D8zpCW2jRSdQEyneO4Psc9H5rDLs05lPw" />

  <link rel="shortcut icon" href="<?= BASE_URL ?>/assets/images/Logo 1 CA COMPLET.svg" type="image/svg+xml">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

  <!-- Botpress Webchat v3 — ouverture uniquement au clic sur la bulle de chat
       (aucune ouverture automatique). -->
  <script src="https://cdn.botpress.cloud/webchat/v3.7/inject.js"></script>
  <script src="https://files.bpcontent.cloud/2026/08/01/17/20260801171922-4PKEFZ4J.js" defer></script>

  <?php $__cssBase = dirname(__DIR__, 2) . '/assets/css'; ?>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= @filemtime($__cssBase . '/style.css') ?: time() ?>">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/landing-redesign.css?v=<?= @filemtime($__cssBase . '/landing-redesign.css') ?: time() ?>">
  <link href='https://cdn.boxicons.com/3.0.6/fonts/basic/boxicons.min.css' rel='stylesheet'>

  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://code.iconify.design/iconify-icon/1.0.7/iconify-icon.min.js"></script>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,700;9..144,900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Boldonse&family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">

  <!-- Seuls les visuels réellement affichés sont préchargés. hero-banner-1/2.jpg
       et hero-shape-1.svg n'existent plus dans assets/images : ils ne
       produisaient que des 404 et un avertissement « preloaded but not used ». -->
  <link rel="preload" as="image" href="<?= BASE_URL ?>/assets/images/hero-bg.svg">
  <link rel="preload" as="image" href="<?= BASE_URL ?>/assets/images/hero-shape-2.png">

  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

  <!-- PWA -->
  <link rel="manifest" href="<?= BASE_URL ?>/manifest.json">
  <meta name="theme-color" content="#8a52fa">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="Connect'Academia">
  <link rel="apple-touch-icon" href="<?= BASE_URL ?>/public/assets/images/icons/apple-touch-icon.png">
</head>

<body id="top">

  <style>
  /* Cible la navbar entière ET la liste des liens en mode mobile pour tout peindre en blanc */
  @media (max-width: 991px) {
    .navbar,
    [data-navbar],
    .navbar-list {
      background-color: #ffffff !important;
    }

    /* Assure que tous les liens et éléments de texte restent bien sombres et visibles */
    .navbar-link,
    .navbar-item {
      color: #1a1a1a !important;
      background-color: #ffffff !important;
    }

    /* Optionnel : ajoute une bordure légère sous chaque lien si tu veux mieux les séparer */
    .navbar-item {
      border-bottom: 1px solid #f0f0f0;
    }
  }
</style>

  <header class="header" data-header>
    <div class="container">

      <a href="<?= url('') ?>" class="logo">
        <img src="<?= BASE_URL ?>/assets/images/logo-officiel.png" width="80" height="10" alt="Connect'Academia logo">
      </a>

      <nav class="navbar" data-navbar>

        <div class="wrapper">
          <a href="<?= url('') ?>" class="logo">
            <img src="<?= BASE_URL ?>/assets/images/logo-officiel.png" width="162" height="50" alt="Connect'Academia logo">
          </a>

          <button class="nav-close-btn" aria-label="close menu" data-nav-toggler>
            <ion-icon name="close-outline" aria-hidden="true"></ion-icon>
          </button>
        </div>

        <ul class="navbar-list">

          <li class="navbar-item">
            <a href="#home" class="navbar-link" data-nav-link>Accueil</a>
          </li>

          <li class="navbar-item">
            <a href="#about" class="navbar-link" data-nav-link>À propos</a>
          </li>

          <li class="navbar-item">
            <a href="<?= url('/orientation') ?>" class="navbar-link" data-nav-link>Orientations</a>
          </li>

          <li class="navbar-item">
            <a href="<?= url('/documentation') ?>" class="navbar-link" data-nav-link>Documentation</a>
          </li>

          <li class="navbar-item">
            <a href="<?= BASE_URL ?>/contact" class="navbar-link" data-nav-link>Contact</a>
          </li>

        </ul>

      </nav>

      <div class="header-actions">

        <a href="<?= url('auth/connexion') ?>" class="btn has-before">
          <span class="span">Connexion</span>
          <ion-icon name="arrow-forward-outline" aria-hidden="true"></ion-icon>
        </a>

        <button class="header-action-btn" aria-label="open menu" data-nav-toggler>
          <ion-icon name="menu-outline" aria-hidden="true"></ion-icon>
        </button>

      </div>

      <div class="overlay" data-nav-toggler data-overlay></div>

    </div>
  </header>


  <main>
    <article>

      <section class="section hero has-bg-image" id="home" aria-label="home"
        style="background-image: url(&quot;<?= BASE_URL ?>/assets/images/hero-bg.svg&quot;)">
        <div class="container">

        
          <div class="hero-content">

            <h1 class="h1 section-title">
              Trouvez et <span class="span"> intégrez </span> votre future école, <span class="span-clics">en quelques clics</span>
            </h1>

            <p class="hero-text">
              Comparez les universités, grandes écoles et instituts. Préinscrivez-vous et payez en ligne en toute confiance.
            </p>

            <div class="hero-actions">

              <a href="<?= url('auth/connexion') ?>" class="btn has-before">
                <span class="span"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"></path>
                  <path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"></path>
                  <path d="M9 12H4s.5-1 1-4c2 1 3 3 3 3z"></path>
                  <path d="M12 15v5s1-.5 4-1c-1-2-3-3-3-3z"></path>
                </svg>Commencer gratuitement</span>
                <ion-icon name="arrow-forward-outline" aria-hidden="true"></ion-icon>
              </a>

              <a href="<?= url('/orientation') ?>" class="btn btn-hero-secondary has-before">
                <span class="span"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                  <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
                </svg>Explorer les écoles</span>
                <ion-icon name="arrow-forward-outline" aria-hidden="true"></ion-icon>
              </a>

            </div>

          </div>

          <figure class="hero-banner">

            <div class="img-holder one" style="--width: 270; --height: 340;">
              <img src="<?= BASE_URL ?>/assets/images/header1.PNG" width="270" height="340" alt="hero banner" class="img-cover">
            </div>

            <div class="img-holder two" style="--width: 240; --height: 370;">
              <img src="<?= BASE_URL ?>/assets/images/header2.PNG" width="240" height="370" alt="hero banner" class="img-cover">
            </div>

            <img src="<?= BASE_URL ?>/assets/images/hero-shape-2.png" width="622" height="551" alt="" class="shape hero-shape-2">

          </figure>

        </div>

        <div class="social-proof">
          <div class="avatars">
            <img src="<?= BASE_URL ?>/assets/images/fille1.png" alt="Étudiant 1">
            <img src="<?= BASE_URL ?>/assets/images/garcon1.png" alt="Étudiant 2">
            <img src="<?= BASE_URL ?>/assets/images/garcon2.png" alt="Étudiant 3">
            <img src="<?= BASE_URL ?>/assets/images/fille2.png" alt="Étudiant 4">
            <img src="<?= BASE_URL ?>/assets/images/fille3.png" alt="Étudiant 5">
            
          </div>
          <span class="proof-text">Rejoint par +2000 étudiants</span>
        </div>

      </section>

      <section class="lili">
        <div class="max-w-7xl mx-auto px-6 text-center">
          <p class="text-base md:text-lg font-medium text-slate-700 mb-6 tracking-widest uppercase">Une plateforme de référence pour</p>
          <div class="flex flex-wrap justify-center items-center gap-8 md:gap-16">
            <span class="text-xl md:text-2xl font-semibold tracking-tight text-slate-800 flex items-center gap-2"><iconify-icon icon="solar:banknote-2-linear"></iconify-icon> Ministère de l'Éducation.</span>
            <span class="text-xl md:text-2xl font-semibold tracking-tight text-slate-800 flex items-center gap-2"><iconify-icon icon="solar:buildings-2-linear"></iconify-icon> Les universités</span>
            <span class="text-xl md:text-2xl font-semibold tracking-tight text-slate-800 flex items-center gap-2"><iconify-icon icon="solar:diploma-linear"></iconify-icon> Office du Bac</span>
            <span class="text-xl md:text-2xl font-semibold tracking-tight text-slate-800 flex items-center gap-2"><iconify-icon icon="solar:users-group-rounded-linear"></iconify-icon> Associations des Parents</span>
          </div>
        </div>
      </section>


      <section class="apprentissage-section">
        <div class="img-appretissage">
          <img src="<?= BASE_URL ?>/assets/images/img-apprentissage.PNG" alt="Tableau apprentissage">
        </div>
      </section>

      <section class="video has-bg-image" aria-label="video"
        style="background-image: url(&quot;<?= BASE_URL ?>/assets/images/video-bg.png&quot;)">
        <div class="container">

          <div class="video-card">

            <div class="video-banner img-holder has-after" style="--width: 1000; --height: 580;">
              <img src="<?= BASE_URL ?>/assets/images/image-communate2.png" width="970" height="550" loading="lazy" alt="video banner"
                class="img-cover">

              <button class="play-btn" aria-label="play video">
                <ion-icon name="play" aria-hidden="true"></ion-icon>
              </button>

              <!-- preload="none" : le fichier pèse 27 Mo et reste masqué jusqu'au
                   clic sur .play-btn (assets/js/script.js) — inutile de le
                   télécharger à chaque affichage de la page d'accueil. -->
              <video class="video-player" controls preload="none" style="display: none;">
                <source src="<?= BASE_URL ?>/assets/images/video-presentation.mp4" type="video/mp4">
                Votre navigateur ne supporte pas la lecture de vidéos.
              </video>
            </div>

            <img src="<?= BASE_URL ?>/assets/images/video-shape-1.png" width="1089" height="605" loading="lazy" alt=""
              class="shape video-shape-1">

            <img src="<?= BASE_URL ?>/assets/images/video-shape-2.png" width="158" height="174" loading="lazy" alt=""
              class="shape video-shape-2">

          </div>

        </div>
      </section>

      <section class="roadmap-section">
        <div class="roadmap-header">
          <h2 class="roadmap-main-title">Un parcours pensé pour votre réussite</h2>
          <h3 class="roadmap-subtitle">Aujourd'hui et pour demain</h3>
          <p class="roadmap-description">Découvrez les fonctionnalités clés qui construisent progressivement une expérience éducative complète, moderne et adaptée au Gabon.</p>
        </div>

        <div class="roadmap-timeline">
          <div class="timeline-line"></div>

          <div class="timeline-phase phase-left phase-completed">
            <div class="phase-checkmark">
              <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" fill="#10b981"/>
              </svg>
            </div>
            <div class="phase-card">
              <div class="phase-status status-terminé">TERMINÉ</div>
              <div class="phase-date">Apprentissage Intelligent*</div>
              <div class="phase-subtitle">Cours, révisions et IA éducative</div>
              <ul class="phase-features">
                <li class="feature-completed">
                  <svg class="check-icon" viewBox="0 0 20 20" fill="none">
                    <path d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" fill="#10b981"/>
                  </svg>
                  <span>Synthèses : Obtenez un résumé texte ou audio de n'importe quel chapitre.</span>
                </li>
                <li class="feature-completed">
                  <svg class="check-icon" viewBox="0 0 20 20" fill="none">
                    <path d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" fill="#10b981"/>
                  </svg>
                  <span>Quiz : L'IA crée des tests sur mesure pour vérifier que vous avez compris.</span>
                </li>
                <li class="feature-completed">
                  <svg class="check-icon" viewBox="0 0 20 20" fill="none">
                    <path d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" fill="#10b981"/>
                  </svg>
                  <span>Cartes Mentales : Visualisez les liens entre les idées grâce aux Mind Maps.
                    <span class="feature-tag tag-core">CORE</span></span>
                </li>
                <li class="feature-completed">
                  <svg class="check-icon" viewBox="0 0 20 20" fill="none">
                    <path d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" fill="#10b981"/>
                  </svg>
                  <span>Assistant de Cours : Chattez directement avec vos PDF pour une réponse précise.</span>
                </li>
                <li class="feature-completed">
                  <svg class="check-icon" viewBox="0 0 20 20" fill="none">
                    <path d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" fill="#10b981"/>
                  </svg>
                  <span>Garantie Fiabilité : IA "Zéro Hallucination" basée uniquement sur vos documents.</span>
                </li>
              </ul>
            </div>
          </div>

          <div class="timeline-phase phase-right phase-validated">
            <div class="phase-checkmark">
              <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" fill="#10b981"/>
              </svg>
            </div>
            <div class="phase-card">
              <div class="phase-status status-validé">VALIDÉ</div>
              <div class="phase-date">Orientation & Avenir*</div>
              <div class="phase-subtitle">Trouvez votre voie et l'école qui vous correspond.</div>
              <ul class="phase-features">
                <li class="feature-completed">
                  <svg class="check-icon" viewBox="0 0 20 20" fill="none">
                    <path d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" fill="#10b981"/>
                  </svg>
                  <span>Immersion Campus : Explorez les écoles (photos, vidéos, conditions d'accès).</span>
                </li>
                <li class="feature-completed">
                  <svg class="check-icon" viewBox="0 0 20 20" fill="none">
                    <path d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" fill="#10b981"/>
                  </svg>
                  <span>Le Comparateur : Comparaison directe (Frais, Reconnaissance CAMES, Débouchés). <span class="feature-tag tag-vital">VITAL</span></span>
                </li>
                <li class="feature-completed">
                  <svg class="check-icon" viewBox="0 0 20 20" fill="none">
                    <path d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" fill="#10b981"/>
                  </svg>
                  <span>Conseiller Virtuel : Un guide interactif pour vos questions sur le marché local.</span>
                </li>
                <li class="feature-completed">
                  <svg class="check-icon" viewBox="0 0 20 20" fill="none">
                    <path d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" fill="#10b981"/>
                  </svg>
                  <span>Choix éclairés : Toutes les données pour décider sans stress.</span>
                </li>
              </ul>
            </div>
          </div>

          <div class="timeline-phase phase-left phase-current">
            <div class="phase-rocket">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"></path>
                <path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"></path>
                <path d="M9 12H4s.5-1 1-4c2 1 3 3 3 3z"></path>
                <path d="M12 15v5s1-.5 4-1c-1-2-3-3-3-3z"></path>
              </svg>
            </div>
            <div class="phase-card">
              <div class="phase-date">COMMUNAUTÉ*</div>
              <div class="phase-subtitle">Réseau d'Entraide.</div>
              <ul class="phase-features">
                <li class="feature-completed">
                  <svg class="check-icon" viewBox="0 0 20 20" fill="none">
                    <path d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" fill="#10b981"/>
                  </svg>
                  <span>Fil d'Actualité Académique : Un espace de chat où vous pourrez poser vos questions et obtenir des réponses. <span class="feature-tag tag-lancement">LANCEMENT</span></span>
                </li>
                <li class="feature-completed">
                  <svg class="check-icon" viewBox="0 0 20 20" fill="none">
                    <path d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" fill="#10b981"/>
                  </svg>
                  <span>L'entraide : Travaillez avec des apprenants venant des quatre coins du Gabon et au-delà.<span class="feature-tag tag-nouveau">NOUVEAU</span></span>
                </li>
                <li class="feature-pending">
                  <div class="empty-checkbox"></div>
                  <span>Mémoire collective : Retrouvez facilement les échanges pertinents archivés. <span class="feature-tag tag-dev">EN DEV</span></span>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </section>


      <section class="features-section">
        <div class="features-container">

          <div class="feature-card">
            <div class="feature-content">
              <div class="feature-image">
                <img src="<?= BASE_URL ?>/assets/images/image-communauter.png" alt="Les Entreprises">
              </div>
              <div class="feature-text">
                <h3>Apprentissage</h3>
                <p>Transforme tes cours en résumés, quiz et explications intelligentes : ton apprentissage devient plus simple, plus rapide et plus éfficace.</p>
              </div>
              <a href="<?= url('auth/inscription') ?>" class="btn has-before">
                <span class="span">Commencer</span>
                <ion-icon name="arrow-forward-outline" aria-hidden="true"></ion-icon>
              </a>
            </div>
          </div>

          <div class="feature-card">
            <div class="feature-content reverse">
              <div class="feature-image">
                <img src="<?= BASE_URL ?>/assets/images/image-orientation1.png" alt="Les Freelances">
              </div>
              <div class="feature-text">
                <h3>Orientation</h3>
                <p>Compare, explore et choisis l'école qui correspond à ton projet de vie.</p>
              </div>
              <a href="<?= url('/orientation') ?>" class="btn has-before">
                <span class="span">Commencer</span>
                <ion-icon name="arrow-forward-outline" aria-hidden="true"></ion-icon>
              </a>
            </div>
          </div>

          <div class="feature-card">
            <div class="feature-content">
              <div class="feature-image">
                <img src="<?= BASE_URL ?>/assets/images/image-communate2.png" alt="Les Plateformes">
              </div>
              <div class="feature-text">
                <h3>Rejoindre le réseau</h3>
                <p>Le réseau social structuré où élèves et professeurs collaborent pour la réussite</p>
              </div>
              <a href="<?= url('auth/inscription') ?>" class="btn has-before">
                <span class="span">Commencer</span>
                <ion-icon name="arrow-forward-outline" aria-hidden="true"></ion-icon>
              </a>
            </div>
          </div>

        </div>
      </section>


      <section class="latency-container">
        <div class="latency-card-outer">
          <div class="browser-header">
            <div class="dot red"></div>
            <div class="dot yellow"></div>
            <div class="dot green"></div>
          </div>

          <div class="latency-header">
            <div class="system-pill">
              <span class="dot-blue"></span> Catégories
            </div>
            <h2>Une bibliothèque de savoir à portée de main </h2>
            <p class="subtitle">Tout ce dont vous avez besoin pour réussir, centralisé au même endroit.</p>
          </div>

          <div class="latency-grid">

            <div class="card-white pulse-card" style="--color: 262, 84%, 60%">
              <div class="icon-wrap green-bg">
                <div class="card-icon">
                  <img src="<?= BASE_URL ?>/assets/images/category-1.svg" width="40" height="40" loading="lazy"
                    alt="Online Degree Programs" class="img">
                </div>
              </div>
              <h3>+1000 Cours</h3>
              <p>Accédez à des milliers de chapitres conformes au programme national.</p>
              <div class="radar-visual">
                <div class="circle c1"></div>
                <div class="circle c2"></div>
                <div class="circle c3"></div>
                <div class="radar-sweep"></div>
                <div class="radar-dot"></div>
              </div>
            </div>

            <div class="card-white revival-card" style="--color: 262, 62%, 52%">
              <div class="revival-content">
                <div class="icon-wrap blue-bg">
                  <div class="card-icon">
                    <img src="<?= BASE_URL ?>/assets/images/category-2.svg" width="40" height="40" loading="lazy"
                      alt="Non-Degree Programs" class="img">
                  </div>
                </div>
                <h3>+1500 Quiz</h3>
                <p>Exercices interactifs pour valider vos acquis. Testez vos connaissances et progressez à votre rythme.</p>
              </div>
            </div>

            <div class="card-white scale-card" style="--color: 262, 92%, 66%">
              <div class="icon-wrap purple-bg">
                <div class="card-icon">
                  <img src="<?= BASE_URL ?>/assets/images/category-3.svg" width="40" height="40" loading="lazy"
                    alt="Off-Campus Programs" class="img">
                </div>
              </div>
              <h3>IA Intégrée</h3>
              <p>Un prof IA qui personnalise votre apprentissage et résume les chapitres complexes pour vous permettre de réviser efficacement.</p>
            </div>

            <div class="card-white recovered-card" style="--color: 262, 45%, 56%">
              <div class="icon-wrap orange-bg">
                <div class="card-icon">
                  <img src="<?= BASE_URL ?>/assets/images/category-4.svg" width="40" height="40" loading="lazy"
                    alt="Hybrid Distance Programs" class="img">
                </div>
              </div>
              <h3>+5000 Annales</h3>
              <p>Entraînez-vous sur les vrais sujets d'examens des années précédentes.</p>
            </div>

          </div>
        </div>
      </section>

      
      <section class="section about" id="about" aria-label="about">
        <div class="container">

          <figure class="about-banner">

            <div class="img-holder" style="--width: 520; --height: 450;">
              <img src="<?= BASE_URL ?>/assets/images/IMG_1775.png" width="600" height="450" loading="lazy" alt="about banner"
                class="img-cover">
            </div>

            <img src="<?= BASE_URL ?>/assets/images/about-shape-3.png" width="722" height="528" loading="lazy" alt=""
              class="shape about-shape-3">

          </figure>

          <div class="about-content">

            <p class="section-subtitle">Notre Mission</p>

            <h2 class="h2 section-title">
              Concentrez-vous sur l'essentiel : <span class="span">vos études</span>
            </h2>

            <p class="section-text">
              Connect'Academia lève les barrières entre vous et votre avenir. Nous simplifions chaque étape de votre parcours, que ce soit pour approfondir les cours abordés en classe, explorer la filière qui vous convient le mieux ou vous préparer à plonger dans l'univers des études supérieures.
            </p>

            <ul class="about-list">

              <li class="about-item">
                <ion-icon name="checkmark-done-outline" aria-hidden="true"></ion-icon>
                <span class="span">Révisions Assistées</span>
              </li>

              <li class="about-item">
                <ion-icon name="checkmark-done-outline" aria-hidden="true"></ion-icon>
                <span class="span">Meilleurs choix d'Etablissements</span>
              </li>

              <li class="about-item">
                <ion-icon name="checkmark-done-outline" aria-hidden="true"></ion-icon>
                <span class="span">Meilleurs Filières</span>
              </li>

            </ul>

            <img src="<?= BASE_URL ?>/assets/images/about-shape-4.svg" width="100" height="100" loading="lazy" alt=""
              class="shape about-shape-4">

          </div>

        </div>
      </section>


      <section class="final-cta-container">
        <div class="cta-inner-box">
          <h1 class="cta-title">
            Rejoins
            <span class="logo-inline-box">
              <img src="<?= BASE_URL ?>/assets/images/36fc982a-f7f9-46e8-8476-5a96bfc73495.undefined" alt="logo">
            </span>
            <span class="logo-inline-box">
              <img src="<?= BASE_URL ?>/assets/images/55bdfc77-7960-41dc-937f-f2079f3c1568.undefined" alt="logo">
            </span>
            <span class="logo-inline-box">
              <img src="<?= BASE_URL ?>/assets/images/b17b0887-3f88-4234-86e0-ca12c9cf9386.undefined" alt="logo">
            </span>
            la grande communauté Connect'Academia <br> dès aujourd'hui
          </h1>

          

          <div class="mockup-clipping-area">
            <img src="<?= BASE_URL ?>/assets/images/communauter.png" alt="Connect'Academia" class="clipped-image">
          </div>
        </div>
      </section>


      


      <section class="social-media-section" id="social-media">
        <div class="social-media-container">

          <div class="section-intro">
            <div class="intro-left">
              <span class="about-pill">Suivre</span>
              <h2>Médias Sociaux</h2>
            </div>
          </div>

          <div class="posts-grid">

            <div class="post-card">
              <div class="post-header">
                <img src="<?= BASE_URL ?>/assets/images/logo-whatsapp.JPG" alt="whatsapp logo">
                <span>whatsapp</span>
              </div>
              <div class="post-image">
                <img src="<?= BASE_URL ?>/assets/images/whatsap2.PNG" alt="Social media post whatsapp">
              </div>
              <div class="post-footer">
                <div class="action-icons-left">
                  <a href="https://chat.whatsapp.com/BiE25HR0QjQFnPJTfqtDmx"><i class='bx bx-send-alt-2'></i></a>
                </div>
                <div class="action-icon-right">
                  <i class='bx bx-bookmark-alt'></i>
                </div>
              </div>
            </div>

            <div class="post-card">
              <div class="post-header">
                <img src="<?= BASE_URL ?>/assets/images/logo-insta.JPG" alt="instagram logo">
                <span>Instagram</span>
              </div>
              <div class="post-image">
                <img src="<?= BASE_URL ?>/assets/images/insta2.PNG" alt="Social media post instagram">
              </div>
              <div class="post-footer">
                <div class="action-icons-left">
                  <a href="https://www.instagram.com/connectacademia.ga?igsh=MXB6OHZxMmNnYjhuMg%3D%3D&utm_source=qr"><i class='bx bx-send-alt-2'></i></a>
                </div>
                <div class="action-icon-right">
                  <i class='bx bx-bookmark-alt'></i>
                </div>
              </div>
            </div>

            <div class="post-card">
              <div class="post-header">
                <img src="<?= BASE_URL ?>/assets/images/linktree-logo-icon.webp" alt="linktree logo">
                <span>Linktree</span>
              </div>
              <div class="post-image">
                <img src="<?= BASE_URL ?>/assets/images/lien.PNG" alt="Linktree">
              </div>
              <div class="post-footer">
                <div class="action-icons-left">
                  <a href="https://linktr.ee/ConnectAcademia_GA"><i class='bx bx-send-alt-2'></i></a>
                </div>
                <div class="action-icon-right">
                  <i class='bx bx-bookmark-alt'></i>
                </div>
              </div>
            </div>

          </div>
        </div>
      </section>


      <section class="benefits-section">
        <div class="benefits-header">
          <h2>Bien plus qu'une simple solution</h2>
          <div class="purple-bar"></div>
        </div>

        <div class="benefits-grid">
          <div class="benefit-card">
            <div class="benefit-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
            </div>
            <div class="benefit-text-box">
              <h3>Performance Académique</h3>
              <p>Des outils d'IA pour transformer vos cours en réussite au Bac et aux partiels.</p>
            </div>
          </div>

          <div class="benefit-card">
            <div class="benefit-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg>
            </div>
            <div class="benefit-text-box">
              <h3>100&nbsp;% Gratuit</h3>
              <p>Tous les modules sont accessibles gratuitement, sans abonnement ni frais cachés.</p>
            </div>
          </div>

          <div class="benefit-card">
            <div class="benefit-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h8"/><path d="M10 19v-3.96 3.15"/><path d="M7 19h5"/><rect width="6" height="10" x="16" y="12" rx="2"/></svg>
            </div>
            <div class="benefit-text-box">
              <h3>Accessibilité Totale</h3>
              <p>Révisez où vous voulez : dispo 24h/24 sur mobile, tablette et ordinateur.</p>
            </div>
          </div>

          <div class="benefit-card">
            <div class="benefit-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/><path d="m9 13 2 2 4-4"/></svg>
            </div>
            <div class="benefit-text-box active-border">
              <h3>Sérénité Administrative</h3>
              <p>Centralisez vos documents et démarches pour vous concentrer uniquement sur vos études.</p>
            </div>
          </div>

          <div class="benefit-card">
            <div class="benefit-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div class="benefit-text-box">
              <h3>Communauté Active</h3>
              <p>Ne bloquez plus jamais seul : posez vos questions et entraidez-vous.</p>
            </div>
          </div>

          <div class="benefit-card">
            <div class="benefit-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>
            </div>
            <div class="benefit-text-box">
              <h3>Orientation Stratégique</h3>
              <p>Une IA qui analyse votre profil pour vous guider vers les meilleures écoles.</p>
            </div>
          </div>
        </div>
      </section>


      <section class="consolidation-container">
        <div class="consolidation-card">

          <div class="visual-flow">
            <svg class="lines-svg" viewBox="0 0 400 300" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M120 27 H280 V150 H300" stroke="#8B52FA" stroke-width="1.5" stroke-dasharray="6 6" />
              <path d="M120 109 H250 V150 H300" stroke="#8B52FA" stroke-width="1.5" stroke-dasharray="6 6" />
              <path d="M80 191 H250 V150 H300" stroke="#8B52FA" stroke-width="1.5" stroke-dasharray="6 6" />
              <path d="M120 273 H280 V150 H300" stroke="#8B52FA" stroke-width="1.5" stroke-dasharray="6 6" />
            </svg>

            <div class="tool-icons-stack">
              <div class="tool-icon icon-bitly"><img src="<?= BASE_URL ?>/assets/images/téléchargement-removebg-preview.png" alt="Afram"></div>
              <div class="tool-icon icon-bitly"><img src="<?= BASE_URL ?>/assets/images/Icones-Univ.png" alt="Université partenaire"></div>
              <div class="tool-icon icon-buffer"><img src="<?= BASE_URL ?>/assets/images/Icones-Univ-2.png" alt="Institut partenaire"></div>
              <div class="tool-icon icon-generic"><img src="<?= BASE_URL ?>/assets/images/Icones-Univ-3.png" alt="École partenaire"></div>
            </div>

            <div class="target-center">
              <div class="main-glow"></div>
              <div class="target-icon">
                <div class="central-logo-wrap">
                  <img src="<?= BASE_URL ?>/assets/images/logo-officiel.png" alt="Connect'Academia">
                </div>
              </div>
            </div>
          </div>

          <div class="consolidation-text">
            <h2><span>Connect'Academia</span> centralise les universités et instituts du Gabon</h2>
            
          </div>

        </div>
      </section>


      <section class="map-dashboard-section">

        <div class="testimonials-header">
          <h2>Nos Différents <span class="product-badge">Ambassadeurs</span>, <br> dans l'ensemble du Gabon</h2>
          <p class="subtitle">Rejoins nos +100 ambassadeurs, qui ont déjà aidé leurs camarades à s'orienter.</p>
        </div>

        <div class="dashboard-card">

          <div class="map-view-container">
            <div id="real-map-gabon"></div>
            <div class="map-watermark">
              <i class="fa-solid fa-earth-africa"></i> Leaflet | © OpenStreetMap
            </div>
          </div>

          <aside class="dashboard-sidebar">
            <div class="sidebar-top">
              <div class="brand-icon"><i class="fa-solid fa-map-location-dot"></i></div>
              <h3>Provinces</h3>
            </div>

            <div class="provinces-scroll-area">
              <div class="prov-card active">
                <div class="prov-main">
                  <span class="status-indicator green"></span>
                  <div class="prov-text">
                    <strong>Estuaire</strong>
                    <small>Libreville</small>
                  </div>
                </div>
                <div class="badge-count">150</div>
              </div>

              <div class="provinces-mini-grid">
                <div class="prov-card"><div class="prov-main"><span class="status-indicator yellow"></span><div class="prov-text"><strong>55 Ambassadeurs</strong><small>Franceville</small></div></div><div class="badge-count">55</div></div>
                <div class="prov-card"><div class="prov-main"><span class="status-indicator blue"></span><div class="prov-text"><strong>15 Ambassadeurs</strong><small>Lambaréné</small></div></div><div class="badge-count">15</div></div>
                <div class="prov-card"><div class="prov-main"><span class="status-indicator red"></span><div class="prov-text"><strong>10 Ambassadeurs</strong><small>Mouila</small></div></div><div class="badge-count">10</div></div>
                <div class="prov-card"><div class="prov-main"><span class="status-indicator purple"></span><div class="prov-text"><strong>30 Ambassadeurs</strong><small>Tchibanga</small></div></div><div class="badge-count">30</div></div>
                <div class="prov-card"><div class="prov-main"><span class="status-indicator cyan"></span><div class="prov-text"><strong>95 Ambassadeurs</strong><small>Port-Gentil</small></div></div><div class="badge-count">95</div></div>
                <div class="prov-card"><div class="prov-main"><span class="status-indicator orange"></span><div class="prov-text"><strong>68 Ambassadeurs</strong><small>Oyem</small></div></div><div class="badge-count">68</div></div>
              </div>
            </div>

            <div class="sidebar-footer">
              <div class="stat-row">
                <span>Total ambassadeurs</span>
                <span class="pill-stat">368</span>
              </div>
              <div class="stat-row">
                <span>Provinces couvertes</span>
                <span class="pill-stat">7</span>
              </div>
            </div>
          </aside>
        </div>
      </section>

      <section class="analytics-grid-section">
        <div class="analytics-header">
          <span class="badge-purple-tag">ANALYTICS</span>
          <h2>Pour les universités, <br>Suivez vos statistiques en temps réel</h2>
          <p>Créer des liens, c'est bien. Mais savoir qui clique, où et pourquoi, c'est encore mieux.</p>
        </div>

        <div class="analytics-carousel" id="analyticsCarousel">
          <div class="analytics-tabs" role="tablist" aria-label="Statistiques disponibles">
            <button type="button" class="analytics-tab is-active" role="tab" aria-selected="true" data-index="0">Visites</button>
            <button type="button" class="analytics-tab" role="tab" aria-selected="false" data-index="1">Paiements</button>
            <button type="button" class="analytics-tab" role="tab" aria-selected="false" data-index="2">Préinscriptions</button>
            <button type="button" class="analytics-tab" role="tab" aria-selected="false" data-index="3">Inscriptions</button>
          </div>

          <div class="analytics-stage">
            <div class="analytics-frame">
              <div class="analytics-viewport">
                <div class="analytics-track">
                <figure class="analytics-slide">
                  <img src="<?= BASE_URL ?>/assets/images/affiche1.png" alt="Statistiques des visites" loading="lazy">
                </figure>
                <figure class="analytics-slide">
                  <img src="<?= BASE_URL ?>/assets/images/affiche-pay.png" alt="Statistiques des paiements" loading="lazy">
                </figure>
                <figure class="analytics-slide">
                  <img src="<?= BASE_URL ?>/assets/images/affiche-preins.png" alt="Statistiques des préinscriptions" loading="lazy">
                </figure>
                <figure class="analytics-slide">
                  <img src="<?= BASE_URL ?>/assets/images/affiche-inscr.png" alt="Statistiques des inscriptions" loading="lazy">
                </figure>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>


      <section class="testimonials-container">
        <div class="testimonials-header">
          <h2>Ils ont essayé <span class="product-badge">Connect'Academia</span> <br> voici leurs impressions</h2>
          <p class="subtitle">Rejoins nos +1000 utilisateurs qui ont déjà essayé Connect'Academia gratuitement.</p>
        </div>

        <div class="testimonials-grid-wrapper">
          <div class="testimonials-grid">

            <div class="testi-card">
              <div class="stars">★★★★★</div>
              <p class="testi-text">"J'ai comparé trois écoles côte à côte : filières, frais, reconnaissance. En dix minutes j'ai compris ce que je n'avais pas compris en deux mois de
discussions autour de moi.
"</p>
              <div class="user-info">
                <img src="<?= BASE_URL ?>/assets/images/garcon2.png" alt="Lucas">
                <div>
                  <span class="user-name">Lucas Obiang</span>
                </div>
              </div>
            </div>

            <div class="testi-card">
              <div class="stars">★★★★★</div>
              <p class="testi-text">"Je suis encore en 2nd LE et je viens déjà regarder les filières qui existent. Est-ce que vous comptez ajouter des fiches métiers, pour savoir
concrètement où mène chaque filière ?"</p>
              <div class="user-info">
                <img src="https://i.pravatar.cc/100?u=12" alt="Paul">
                <div>
                  <span class="user-name">Rixelle Wora</span>
                </div>
              </div>
            </div>

            <div class="testi-card">
              <div class="stars">★★★★<span class="star-gray">★</span></div>
              <p class="testi-text">"Je cherche des écoles scientifiques et je ne suis pas dans la capitale. Est-ce qu'on pourra bientôt filtrer par ville pour voir ce qui existe près de chez
moi ?"</p>
              <div class="user-info">
                <img src="<?= BASE_URL ?>/assets/images/fille1.png" alt="Maxime">
                <div>
                  <span class="user-name">Chanice Eyang</span>
                </div>
              </div>
            </div>

            <div class="testi-card">
              <div class="stars">★★★★<span class="star-gray">★</span></div>
              <p class="testi-text">"Je vise l'assurance et les métiers des mines et du pétrole. J'ai trouvé deux écoles qui forment là-dessus, je ne savais même pas qu'elles existaient.
"</p>
              <div class="user-info">
                <img src="<?= BASE_URL ?>/assets/images/garcon1.png" alt="Hugo">
                <div>
                  <span class="user-name">Ferdinand Nguema</span>
                </div>
              </div>
            </div>

            <div class="testi-card">
              <div class="stars">★★★★★</div>
              <p class="testi-text">"J'ai fait ma pré-inscription en ligne et reçu mon attestation tout de suite. Ensuite j'ai réglé mon inscription depuis mon téléphone, sans me déplacer
une seule fois. Je ne pensais pas que c'était possible.
"</p>
              <div class="user-info">
                <img src="<?= BASE_URL ?>/assets/images/fille2.png" alt="Camille">
                <div>
                  <span class="user-name">Lovely Mavioga</span>
                </div>
              </div>
            </div>

            <div class="testi-card">
              <div class="stars">★★★★★</div>
              <p class="testi-text">"Est-ce que vous prévoyez un test pour aider à choisir quand on hésite encore ? Le conseiller est parfait quand on sait ce qu'on veut, moi je suis entre
deux filières.
"</p>
              <div class="user-info">
                <img src="https://i.pravatar.cc/100?u=16" alt="Mathieu">
                <div>
                  <span class="user-name">Clair Andréa</span>
                </div>
              </div>
            </div>

          </div>
          <div class="bottom-fade-overlay"></div>
        </div>
      </section>

      <section class="faq-section">
        <div class="faq-header">
          <h2>Questions fréquentes</h2>
          <div class="title-underline"></div>
        </div>

        <div class="faq-container">

          <div class="faq-item">
            <button class="faq-question">
              <span>1. Concrètement, que permet la plateforme ?</span>
              <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </button>
            <div class="faq-answer">
              <p>Trois choses. Découvrir les établissements supérieurs, vous pré-inscrire en ligne dans plusieurs écoles et finaliser votre inscription sans vous déplacer.
Réviser votre programme scolaire avec des cours résumés et un assistant qui répond à vos questions. Et échanger avec d'autres élèves et étudiants
dans un espace pensé pour les études, pas pour la dispersion.</p>
            </div>
          </div>

          <div class="faq-item">
            <button class="faq-question">
              <span>2. Comment se passe une inscription sur la plateforme ?</span>
              <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </button>
            <div class="faq-answer">
              <p>En deux temps. Vous cliquez sur inscription, vous remplissez le formulaire et vous recevez immédiatement votre attestation de pré-inscription. C'est
gratuit, automatique, sans validation préalable — et ce document vous sert notamment pour vos démarches de demande de bourse. Ensuite vient
l'inscription définitive : vous réglez les frais depuis votre téléphone et l'établissement valide votre dossier. Vous pouvez vous pré-inscrire dans
plusieurs écoles avant de choisir.</p>
            </div>
          </div>

          <div class="faq-item">
            <button class="faq-question">
              <span>3. Les cours correspondent-ils au programme officiel ?</span>
              <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </button>
            <div class="faq-answer">
              <p>Oui. Nous avons repris les progressions officielles auprès de l'Institut Pédagogique National et numérisé le programme de terminale par série.
Chaque cours est résumé et reformulé pour être plus clair que vos notes de classe. À côté du cours, un assistant pédagogique répond à vos questions
sur le chapitre que vous êtes en train de réviser — comme un professeur particulier disponible à toute heure.</p>
            </div>
          </div>

          <div class="faq-item">
            <button class="faq-question">
              <span>4. Comment savoir quelle filière ou quelle école choisir ?</span>
              <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </button>
            <div class="faq-answer">
              <p>Un conseiller d'orientation est intégré à la plateforme. Vous pouvez lui poser vos questions sur les filières, les débouchés, la charge de travail, les
métiers et les perspectives d'emploi. Il connaît aussi tous les établissements référencés et peut vous aider à les mettre en regard avant de décider.
Une fonction de comparaison dédiée, pour placer deux écoles côte à côte, arrive prochainement.</p>
            </div>
          </div>

          <div class="faq-item">
            <button class="faq-question">
              <span>5. Est-ce que l'accès est gratuit ?</span>
              <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </button>
            <div class="faq-answer">
              <p>Oui pour tout ce qui précède le choix de votre école : découverte des établissements, conseil d'orientation, pré-inscription. Seule l'inscription
définitive est payante, aux frais fixés par l'établissement lui-même — Connect'Academia ne fixe pas ces montants. La partie révision est gratuite pour
l'instant ; elle passera à un abonnement mensuel à partir de la prochaine rentrée scolaire, avec un tarif communiqué à l'avance.</p>
            </div>
          </div>

        </div>

        <div class="faq-footer">
          <p>Vous n'avez pas trouvé la réponse à vos questions ?</p>
          <p><a href="<?= BASE_URL ?>/contact">Cliquez-ici</a></p>
        </div>
      </section>


  </article>
  </main>


  <footer class="footer" style="background-image: url(&quot;<?= BASE_URL ?>/assets/images/footer-bg.png&quot;)">

    <div class="footer-top section">
      <div class="container grid-list">

        <div class="footer-brand">

          <a href="<?= url('') ?>" class="logo">
            <img src="<?= BASE_URL ?>/assets/images/logo1-removebg-preview.png" width="162" height="50" alt="Connect'Academia logo">
          </a>

          <p class="footer-brand-text">
            La première plateforme dédiée à l'orientation des élèves et étudiants au Gabon.
          </p>

          

          <div class="wrapper">
            <span class="span">Tél :</span>
            <a href="tel:+241077980375" class="footer-link">+241 077 98 03 75</a>
          </div>

          <div class="wrapper">
            <span class="span">Email:</span>
            <a href="mailto:contact@connectacademia.io" class="footer-link">contact@connectacademia.io</a>
          </div>

        </div>

        <ul class="footer-list">

          <li>
            <p class="footer-list-title"> Platforme</p>
          </li>

          <li>
            <a href="#about" class="footer-link">À propos</a>
          </li>

          <li>
            <a href="<?= url('auth/inscription') ?>" class="footer-link">Inscription</a>
          </li>

          

          <li>
            <a href="#" class="footer-link">Accueil</a>
          </li>


          <li>
            <a href="<?= url('/documentation') ?>" class="footer-link">Documentation</a>
          </li>

        </ul>

        <ul class="footer-list">

          <li>
            <p class="footer-list-title">Liens</p>
          </li>

          <li>
            <a href="<?= BASE_URL ?>/contact" class="footer-link">Contact </a>
          </li>

          

          <li>
            <a href="#social-media" class="footer-link">Médias sociaux</a>
          </li>

          <li>
            <a href="<?= url('auth/connexion') ?>" class="footer-link">Connexion</a>
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
            <input type="email" name="email_address" placeholder="Ton adresse e-mail" required class="input-field">

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

<style>
    /* Design Pro Cookie Card */
    #cookie-card {
      position: fixed;
      bottom: 20px;
      left: 20px;
      max-width: 350px;
      background: rgba(255, 255, 255, 0.98);
      backdrop-filter: blur(10px);
      border-radius: 16px;
      padding: 20px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
      z-index: 10001;
      display: none;
      font-family: sans-serif;
      border: 1px solid #eee;
      animation: slideInUp 0.6s ease-out;
    }
    @keyframes slideInUp {
      from { transform: translateY(50px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }
    .cookie-title { font-weight: bold; font-size: 18px; display: flex; align-items: center; gap: 8px; margin-bottom: 10px; color: #333; }
    .cookie-text { font-size: 14px; color: #666; line-height: 1.4; margin-bottom: 15px; }
    .btn-accept-pro { background: #8B52FA; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; font-weight: bold; cursor: pointer; width: 100%; transition: 0.3s; }
    .btn-accept-pro:hover { background: #9D6EFB; }

    /* WhatsApp Float */
    .whatsapp-pro {
      position: fixed;
      bottom: 90px;
      right: 20px;
      background: #25d366;
      color: white;
      width: 55px;
      height: 55px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 30px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
      z-index: 9999;
      transition: 0.3s;
    }
    .whatsapp-pro:hover { transform: scale(1.1); color: white; }

    /* Footer Responsive Patch */
    @media (max-width: 768px) {
      .footer-list { width: 100% !important; text-align: center !important; }
      .footer-top .container { flex-direction: column !important; }
      #cookie-card { left: 10px; right: 10px; max-width: none; }
    }

    /* Scroll Reveal */
    [data-reveal] {
      opacity: 0;
      transform: translateY(50px);
      transition: opacity 0.65s cubic-bezier(0.22, 1, 0.36, 1),
                  transform 0.65s cubic-bezier(0.22, 1, 0.36, 1);
    }
    [data-reveal].revealed {
      opacity: 1;
      transform: translateY(0);
    }
  </style>

  <div id="cookie-card">
    <!-- « cookie-outline » n'existe pas dans ionicons 5.5.2 : la récupération du
         SVG partait en 404 sans en-tête CORS, ce qui levait un « Failed to
         fetch » en console. shield-checkmark-outline existe et colle au consentement. -->
    <div class="cookie-title"><ion-icon name="shield-checkmark-outline" style="color: #8B52FA;"></ion-icon> Cookies</div>
    <p class="cookie-text">Nous utilisons des cookies pour améliorer votre expérience sur Connect'Academia.</p>
    <button class="btn-accept-pro" onclick="acceptCookies()">Accepter & Fermer</button>
  </div>

  <a href="https://wa.me/24107980375" class="whatsapp-pro" target="_blank">
    <ion-icon name="logo-whatsapp"></ion-icon>
  </a>

  <a href="#top" class="back-top-btn" aria-label="back top top" data-back-top-btn>
    <ion-icon name="chevron-up" aria-hidden="true"></ion-icon>
  </a>

<script>
  document.addEventListener("DOMContentLoaded", function() {
    var map = L.map('real-map-gabon').setView([-0.8037, 11.6094], 6);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 18,
    }).addTo(map);

    function createCustomIcon(color) {
      return L.divIcon({
        html: `<div class="custom-pin" style="background:${color}"><i class="fa-solid fa-star"></i></div>`,
        className: 'custom-div-icon',
        iconSize: [30, 30],
        iconAnchor: [15, 15]
      });
    }

    L.marker([0.4162, 9.4673], {icon: createCustomIcon('#2ecc71')}).addTo(map).bindPopup("Libreville (Estuaire)");
    L.marker([-1.6333, 13.5833], {icon: createCustomIcon('#f1c40f')}).addTo(map).bindPopup("Franceville (Haut-Ogooué)");
    L.marker([-0.7167, 8.7833], {icon: createCustomIcon('#1abc9c')}).addTo(map).bindPopup("Port-Gentil (Ogooué-Maritime)");
    L.marker([-0.7000, 10.2333], {icon: createCustomIcon('#3b82f6')}).addTo(map).bindPopup("Lambaréné (Moyen-Ogooué)");
    L.marker([-2.8833, 10.2167], {icon: createCustomIcon('#a855f7')}).addTo(map).bindPopup("Tchibanga (Nyanga)");
    L.marker([-1.4094, 11.0178], {icon: createCustomIcon('#f97316')}).addTo(map).bindPopup("Mouila (Ngounié)");
    L.marker([2.0167, 11.5000], {icon: createCustomIcon('#14b8a6')}).addTo(map).bindPopup("Oyem (Woleu-Ntem)");

    setTimeout(function() {
      map.invalidateSize();
    }, 100);

    window.addEventListener("resize", function() {
      map.invalidateSize();
    });

    // --- Logique Cookies ---
    document.getElementById('cookie-card').style.display = 'block';
  });

  function acceptCookies() {
    localStorage.setItem('cookies_accepted', 'true');
    document.getElementById('cookie-card').style.display = 'none';
  }
</script>

  <script>
  (function () {
    var carousel = document.getElementById('analyticsCarousel');
    if (!carousel) return;

    var track = carousel.querySelector('.analytics-track');
    var slides = carousel.querySelectorAll('.analytics-slide');
    var tabs = carousel.querySelectorAll('.analytics-tab');
    if (!track || slides.length === 0 || tabs.length === 0) return;

    var count = slides.length;
    var current = 0; /* peut valoir `count` : le clone du 1er slide */
    var timer = null;
    var INTERVAL = 4000;
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    /* Boucle infinie sans rembobinage : le 1er slide est cloné en
       fin de piste. On glisse toujours vers l'avant ; une fois sur
       le clone, on saute sans animation sur le vrai 1er slide. */
    var clone = slides[0].cloneNode(true);
    clone.setAttribute('aria-hidden', 'true');
    track.appendChild(clone);

    function apply() {
      track.style.transform = 'translateX(-' + (current * 100) + '%)';
    }

    function jump(index) { /* repositionnement invisible */
      current = index;
      track.style.transition = 'none';
      apply();
      void track.offsetWidth; /* force le reflow avant de réanimer */
      track.style.transition = '';
    }

    function setActiveTab(i) {
      Array.prototype.forEach.call(tabs, function (tab, j) {
        tab.classList.toggle('is-active', j === i);
        tab.setAttribute('aria-selected', j === i ? 'true' : 'false');
      });
    }

    function goTo(index) {
      current = index;
      apply();
      setActiveTab(current % count);
    }

    function next() {
      if (current >= count) jump(0);
      goTo(current + 1);
    }

    function prev() {
      if (current <= 0) jump(count);
      goTo(current - 1);
    }

    track.addEventListener('transitionend', function (e) {
      if (e.target === track && current === count) jump(0);
    });

    function stop() {
      if (timer) { clearInterval(timer); timer = null; }
    }

    function start() {
      /* Le défilement reste automatique même avec "réduire les animations" :
         la transition devient instantanée via CSS, mais l'avance continue. */
      if (timer || document.hidden) return;
      timer = setInterval(next, INTERVAL);
    }

    Array.prototype.forEach.call(tabs, function (tab) {
      tab.addEventListener('click', function () {
        if (current >= count) jump(0);
        goTo(parseInt(tab.getAttribute('data-index'), 10) || 0);
        stop();
        start();
      });
    });

    carousel.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowRight') { next(); stop(); start(); }
      if (e.key === 'ArrowLeft') { prev(); stop(); start(); }
    });

    /* Aucune pause au survol : le défilement ne s'arrête jamais.
       Seuls les onglets/flèches relancent le compteur, et l'onglet
       caché ou hors écran suspend le timer (invisible, économise
       les ressources). */
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) { stop(); } else { start(); }
    });

    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) { start(); } else { stop(); }
        });
      }, { threshold: 0.25 }).observe(carousel);
    } else {
      start();
    }
  })();
  </script>

  <script src="<?= BASE_URL ?>/assets/js/script.js" defer></script>

  <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>

  <script>
  (function () {
    var lili = document.querySelector('.lili');
    if (!lili) return;

    var targets = [];
    var el = lili;
    while (el) {
      targets.push(el);
      el = el.nextElementSibling;
    }

    targets.forEach(function (t) { t.setAttribute('data-reveal', ''); });

    if (!('IntersectionObserver' in window)) {
      targets.forEach(function (t) { t.classList.add('revealed'); });
      return;
    }

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('revealed');
        if (entry.target.querySelector('#real-map-gabon')) {
          setTimeout(function () { window.dispatchEvent(new Event('resize')); }, 700);
        }
        observer.unobserve(entry.target);
      });
    }, { threshold: 0.08 });

    targets.forEach(function (t) { observer.observe(t); });
  })();
  </script>

  
    
    
    

  <script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      // json_encode fournit ses propres guillemets ET échappe l'apostrophe :
      // BASE_URL contient « connect'academia » en local, ce qui cassait le
      // littéral JS et tuait tout ce bloc (service worker jamais enregistré).
      navigator.serviceWorker.register(<?= json_encode(BASE_URL . '/sw.js', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>, {
        scope: <?= json_encode(BASE_URL . '/', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>
      }).catch(function (err) {
        console.warn('[PWA] SW registration failed:', err);
      });
    });
  }
  </script>

</body>

</html>
