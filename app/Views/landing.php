<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>Connect'Academia - L'orientation éducative du Gabon</title>
  <meta name="title" content="Connect'Academia - L'orientation éducative du Gabon">
  <meta name="description" content="Apprentissage, Orientation et Entraide : la première plateforme tout-en-un conçue pour les élèves et étudiants du Gabon.">

  <link rel="shortcut icon" href="<?= BASE_URL ?>/assets/images/Logo 1 CA COMPLET.svg" type="image/svg+xml">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <link href='https://cdn.boxicons.com/3.0.6/fonts/basic/boxicons.min.css' rel='stylesheet'>

  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://code.iconify.design/iconify-icon/1.0.7/iconify-icon.min.js"></script>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,700;900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Boldonse&family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">

  <link rel="preload" as="image" href="<?= BASE_URL ?>/assets/images/hero-bg.svg">
  <link rel="preload" as="image" href="<?= BASE_URL ?>/assets/images/hero-banner-1.jpg">
  <link rel="preload" as="image" href="<?= BASE_URL ?>/assets/images/hero-banner-2.jpg">
  <link rel="preload" as="image" href="<?= BASE_URL ?>/assets/images/hero-shape-1.svg">
  <link rel="preload" as="image" href="<?= BASE_URL ?>/assets/images/hero-shape-2.png">

  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>

<body id="top">

  <header class="header" data-header>
    <div class="container">

      <a href="<?= url('') ?>" class="logo">
        <img src="<?= BASE_URL ?>/assets/images/Logo 1 CA COMPLET.svg" width="80" height="10" alt="Connect'Academia logo">
      </a>

      <nav class="navbar" data-navbar>

        <div class="wrapper">
          <a href="<?= url('') ?>" class="logo">
            <img src="<?= BASE_URL ?>/assets/images/Logo 1 CA COMPLET.svg" width="162" height="50" alt="Connect'Academia logo">
          </a>

          <button class="nav-close-btn" aria-label="close menu" data-nav-toggler>
            <ion-icon name="close-outline" aria-hidden="true"></ion-icon>
          </button>
        </div>

        <ul class="navbar-list">

          <li class="navbar-item">
            <a href="#home" class="navbar-link" data-nav-link>Acceuil</a>
          </li>

          <li class="navbar-item">
            <a href="#about" class="navbar-link" data-nav-link>À propos</a>
          </li>

          <li class="navbar-item">
            <a href="<?= BASE_URL ?>/assets/lib/tarifs.html" class="navbar-link" data-nav-link>Tarifs</a>
          </li>

          <li class="navbar-item">
            <a href="<?= BASE_URL ?>/public/orientation/orientation.html" class="navbar-link" data-nav-link>Orientations</a>
          </li>

          <li class="navbar-item">
            <a href="<?= BASE_URL ?>/assets/lib/contact.html" class="navbar-link" data-nav-link>Contact</a>
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
        style="background-image: url('<?= BASE_URL ?>/assets/images/hero-bg.svg')">
        <div class="container">

          <div class="hero-content">

            <h1 class="h1 section-title">
              Votre <span class="span"> résussite </span> ne dépend plus du hasard
            </h1>

            <p class="hero-text">
              Apprentissage, Orientation et Entraide : la première plateforme tout-en-un conçue pour les élèves et étudiants du Gabon.
            </p>

            <a href="<?= url('auth/inscription') ?>" class="btn has-before">
              <span class="span"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"></path>
                <path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"></path>
                <path d="M9 12H4s.5-1 1-4c2 1 3 3 3 3z"></path>
                <path d="M12 15v5s1-.5 4-1c-1-2-3-3-3-3z"></path>
              </svg>Commencer gratuitement</span>
              <ion-icon name="arrow-forward-outline" aria-hidden="true"></ion-icon>
            </a>

          </div>

          <figure class="hero-banner">

            <div class="img-holder one" style="--width: 270; --height: 300;">
              <img src="<?= BASE_URL ?>/assets/images/header1.PNG" width="270" height="300" alt="hero banner" class="img-cover">
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
            <img src="<?= BASE_URL ?>/assets/images/a2ea34ec-c161-4f51-a70c-1d804a323a54.undefined" alt="Étudiant 5">
            <img src="<?= BASE_URL ?>/assets/images/697a000c7cf303ed467c4ccc_icon-hero-h1.avif." alt="Étudiant 6">
          </div>
          <span class="proof-text">Rejoint par +1000 étudiants</span>
        </div>

      </section>

      <section class="lili">
        <div class="max-w-7xl mx-auto px-6 text-center">
          <p class="text-base md:text-lg font-medium text-slate-700 mb-6 tracking-widest uppercase">Une plateforme de référence pour</p>
          <div class="flex flex-wrap justify-center items-center gap-8 md:gap-16 grayscale opacity-60 hover:opacity-100 transition-opacity">
            <span class="text-xl md:text-2xl font-semibold tracking-tight text-slate-800 flex items-center gap-2"><iconify-icon icon="solar:banknote-2-linear"></iconify-icon> Ministère de l'Éducation.</span>
            <span class="text-xl md:text-2xl font-semibold tracking-tight text-slate-800 flex items-center gap-2"><iconify-icon icon="solar:buildings-2-linear"></iconify-icon> Les universités</span>
            <span class="text-xl md:text-2xl font-semibold tracking-tight text-slate-800 flex items-center gap-2"><iconify-icon icon="solar:diploma-linear"></iconify-icon> Office du Bac</span>
            <span class="text-xl md:text-2xl font-semibold tracking-tight text-slate-800 flex items-center gap-2"><iconify-icon icon="solar:users-group-rounded-linear"></iconify-icon> Associations des Parents</span>
          </div>
        </div>
      </section>


      <section class="apprentissage-section">
        <div class="img-appretissage">
          <img src="<?= BASE_URL ?>/assets/images/tableau-apprentissage.png" alt="Tableau apprentissage">
        </div>
      </section>

      <section class="video has-bg-image" aria-label="video"
        style="background-image: url('<?= BASE_URL ?>/assets/images/video-bg.png')">
        <div class="container">

          <div class="video-card">

            <div class="video-banner img-holder has-after" style="--width: 1000; --height: 580;">
              <img src="<?= BASE_URL ?>/assets/images/Images en 5343x3000 - Image Communauté.png" width="970" height="550" loading="lazy" alt="video banner"
                class="img-cover">

              <button class="play-btn" aria-label="play video">
                <ion-icon name="play" aria-hidden="true"></ion-icon>
              </button>

              <video class="video-player" controls style="display: none;">
                <source src="<?= BASE_URL ?>/assets/images/video-presentation.MOV" type="video/mp4">
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
          <h2 class="roadmap-main-title">UN PARCOURS PENSÉ POUR VOTRE RÉUSSITE</h2>
          <h3 class="roadmap-subtitle">AUJOURD'HUI ET POUR DEMAIN</h3>
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
                  <span>L'entraide : Travaillez avec des apprenants venant des quartes coins du Gabon et au delà.<span class="feature-tag tag-nouveau">NOUVEAU</span></span>
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
                <img src="<?= BASE_URL ?>/assets/images/Images en 5343x3000 - Image Apprentissage.png" alt="Les Entreprises">
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
                <img src="<?= BASE_URL ?>/assets/images/Images en 5343x3000 - Image Orientation 1.png" alt="Les Freelances">
              </div>
              <div class="feature-text">
                <h3>Orientation</h3>
                <p>Compare, explore et choisis l'école qui correspond à ton projet de vie.</p>
              </div>
              <a href="<?= url('auth/inscription') ?>" class="btn has-before">
                <span class="span">Commencer</span>
                <ion-icon name="arrow-forward-outline" aria-hidden="true"></ion-icon>
              </a>
            </div>
          </div>

          <div class="feature-card">
            <div class="feature-content">
              <div class="feature-image">
                <img src="<?= BASE_URL ?>/assets/images/Images en 5343x3000 - Image Communauté.png" alt="Les Plateformes">
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
            <h2>Une bibliothèque de savoir à portée de main .</h2>
            <p class="subtitle">Tout ce dont vous avez besoin pour réussir, centralisé au même endroit.</p>
          </div>

          <div class="latency-grid">

            <div class="card-white pulse-card" style="--color: 170, 75%, 41%">
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

            <div class="card-white revival-card" style="--color: 351, 83%, 61%">
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

            <div class="card-white scale-card" style="--color: 229, 75%, 58%">
              <div class="icon-wrap purple-bg">
                <div class="card-icon">
                  <img src="<?= BASE_URL ?>/assets/images/category-3.svg" width="40" height="40" loading="lazy"
                    alt="Off-Campus Programs" class="img">
                </div>
              </div>
              <h3>IA Intégrée</h3>
              <p>Un prof IA qui personnalise votre apprentissage et résume les chapitres complexes pour vous permettre de réviser efficacement.</p>
            </div>

            <div class="card-white recovered-card" style="--color: 42, 94%, 55%">
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

      <section class="integrations-container">
        <div class="integrations-content">
          <h2>Révisez efficacement vos cours avec<span>Connect'Academia</span></h2>
        </div>

        <div class="logos-grid-wrapper">
          <div class="tiles-bg"></div>

          <div class="logos-floating-layer">
            <div class="logo-item pos-1"><img src="https://upload.wikimedia.org/wikipedia/commons/f/f1/Heart_coraz%C3%B3n.svg" alt="Heart"></div>
            <div class="logo-item pos-2"><img src="<?= BASE_URL ?>/assets/images/maths.png" alt="maths"></div>
            <div class="logo-item pos-3"><img src="<?= BASE_URL ?>/assets/images/physiques.png" alt="physiques"></div>
            <div class="logo-item pos-4"><img src="<?= BASE_URL ?>/assets/images/francais.jpg" alt="francais"></div>
            <div class="logo-item pos-5"><img src="<?= BASE_URL ?>/assets/images/svt.png" alt="svt"></div>
            <div class="logo-item pos-6"><img src="<?= BASE_URL ?>/assets/images/philo.png" alt="philo"></div>
            <div class="logo-item pos-7"><img src="<?= BASE_URL ?>/assets/images/anglais.png" alt="anglais"></div>
            <div class="logo-item pos-8"><img src="<?= BASE_URL ?>/assets/images/economie.png" alt="economie"></div>
            <div class="logo-item pos-9"><img src="<?= BASE_URL ?>/assets/images/histoire.png" alt="histoire"></div>
          </div>
        </div>
      </section>

      <section class="section about" id="about" aria-label="about">
        <div class="container">

          <figure class="about-banner">

            <div class="img-holder" style="--width: 520; --height: 370;">
              <img src="<?= BASE_URL ?>/assets/images/IMG_1775.png" width="600" height="400" loading="lazy" alt="about banner"
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

          <div class="cta-action-group">
            <p class="cta-sub-text">3 jours d'essai. Aucune carte requise.</p>
          </div>

          <div class="mockup-clipping-area">
            <img src="<?= BASE_URL ?>/assets/images/communauter.png" alt="Connect'Academia" class="clipped-image">
          </div>
        </div>
      </section>


      <section class="section stats" aria-label="stats">
        <div class="container">

          <ul class="grid-list">

            <li>
              <div class="stats-card" style="--color: 170, 75%, 41%">
                <h3 class="card-title">29.3k</h3>
                <p class="card-text">Student Enrolled</p>
              </div>
            </li>

            <li>
              <div class="stats-card" style="--color: 351, 83%, 61%">
                <h3 class="card-title">32.4K</h3>
                <p class="card-text">Class Completed</p>
              </div>
            </li>

            <li>
              <div class="stats-card" style="--color: 260, 100%, 67%">
                <h3 class="card-title">100%</h3>
                <p class="card-text">Satisfaction Rate</p>
              </div>
            </li>

            <li>
              <div class="stats-card" style="--color: 42, 94%, 55%">
                <h3 class="card-title">354+</h3>
                <p class="card-text">Top</p>
              </div>
            </li>

          </ul>

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
          <h2>BIEN PLUS QU'UNE SIMPLE SOLUTION !</h2>
          <div class="purple-bar"></div>
        </div>

        <div class="benefits-grid">
          <div class="benefit-card">
            <div class="benefit-icon">
              <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 3h8v8H3V3zm0 10h8v8H3v-8zM13 3h8v8h-8V3zm0 10h8v8h-8v-8z"/></svg>
            </div>
            <div class="benefit-text-box">
              <h3>Performance Académique</h3>
              <p>Des outils d'IA pour transformer vos cours en réussite au Bac et aux partiels.</p>
            </div>
          </div>

          <div class="benefit-card">
            <div class="benefit-icon">
              <i class='bx bx-dollar-circle'></i>
            </div>
            <div class="benefit-text-box">
              <h3>Abonnement</h3>
              <p>Une offre adaptée à tous les budgets étudiants, sans engagement caché.</p>
            </div>
          </div>

          <div class="benefit-card">
            <div class="benefit-icon">
              <svg viewBox="0 0 24 24" fill="currentColor"><path d="M4 6h18V4H4c-1.1 0-2 .9-2 2v11H0v3h14v-3H4V6zm19 2h-6c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h6c.55 0 1-.45 1-1V9c0-.55-.45-1-1-1zm-1 9h-4v-7h4v7z"/></svg>
            </div>
            <div class="benefit-text-box">
              <h3>Accessibilité Totale</h3>
              <p>Révisez où vous voulez : dispo 24h/24 sur mobile, tablette et ordinateur.</p>
            </div>
          </div>

          <div class="benefit-card">
            <div class="benefit-icon">
              <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg>
            </div>
            <div class="benefit-text-box active-border">
              <h3>Sérénité Administrative</h3>
              <p>Centralisez vos documents et démarches pour vous concentrer uniquement sur vos études.</p>
            </div>
          </div>

          <div class="benefit-card">
            <div class="benefit-icon">
              <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
            </div>
            <div class="benefit-text-box">
              <h3>Communauté Active</h3>
              <p>Ne bloquez plus jamais seul : posez vos questions et entraidez-vous.</p>
            </div>
          </div>

          <div class="benefit-card">
            <div class="benefit-icon">
              <i class='bx bx-robot'></i>
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
              <path d="M80 40 H280 V150 H300" stroke="#22c55e" stroke-width="1.5" stroke-dasharray="6 6" />
              <path d="M120 90 H250 V150" stroke="#22c55e" stroke-width="1.5" stroke-dasharray="6 6" />
              <path d="M80 150 H300" stroke="#22c55e" stroke-width="1.5" stroke-dasharray="6 6" />
              <path d="M120 210 H250 V150" stroke="#22c55e" stroke-width="1.5" stroke-dasharray="6 6" />
              <path d="M80 270 H280 V150" stroke="#22c55e" stroke-width="1.5" stroke-dasharray="6 6" />
            </svg>

            <div class="tool-icons-stack">
              <div class="tool-icon icon-bitly"><img src="<?= BASE_URL ?>/assets/images/téléchargement-removebg-preview.png" alt="Afram"></div>
              <div class="tool-icon icon-bitly"><img src="<?= BASE_URL ?>/assets/images/BBS-removebg-preview.png" alt="BBS"></div>
              <div class="tool-icon icon-buffer"><img src="<?= BASE_URL ?>/assets/images/ITA.png" alt="ITA"></div>
              <div class="tool-icon icon-generic"><img src="<?= BASE_URL ?>/assets/images/UIL-removebg-preview.png" alt="UIL"></div>
              <div class="tool-icon icon-analytics"><img src="<?= BASE_URL ?>/assets/images/EM-Gabon.png" alt="Em-Gabon"></div>
            </div>

            <div class="target-center">
              <div class="main-glow"></div>
              <div class="target-icon">
                <div class="central-logo-wrap">
                  <img src="<?= BASE_URL ?>/assets/images/Logo 1 CA COMPLET.svg" alt="Connect'Academia">
                </div>
              </div>
            </div>
          </div>

          <div class="consolidation-text">
            <h2><span>Connect'Academia</span> centralise les universités et instituts du Gabon</h2>
            <button class="btn-cta-black">Commencez gratuitement !</button>
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
                <span>Ambassadeurs totales 368</span>
                <span class="pill-stat"></span>
              </div>
              <div class="stat-row">
                <span>Avec coordonnées GPS</span>
                <span class="pill-stat">0</span>
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

        <div class="analytics-display-grid">
          <div class="analytics-item">
            <img src="<?= BASE_URL ?>/assets/images/visite.PNG" alt="Statistiques Visites">
          </div>
          <div class="analytics-item">
            <img src="<?= BASE_URL ?>/assets/images/paiement.PNG" alt="Statistiques Paiements">
          </div>
          <div class="analytics-item">
            <img src="<?= BASE_URL ?>/assets/images/preinscription.png" alt="Statistiques Documents">
          </div>
          <div class="analytics-item">
            <img src="<?= BASE_URL ?>/assets/images/inscription.png" alt="Statistiques Inscriptions">
          </div>
        </div>
      </section>


      <section class="testimonials-container">
        <div class="testimonials-header">
          <h2>Ils ont essayé <span class="product-badge">Connect'Academia</span>, <br> voici leurs impressions</h2>
          <p class="subtitle">Rejoins nos +1000 utilisateurs qui ont déjà essayé Connect'Academia gratuitement.</p>
        </div>

        <div class="testimonials-grid-wrapper">
          <div class="testimonials-grid">

            <div class="testi-card">
              <div class="stars">★★★★★</div>
              <p class="testi-text">"Je trouve la plateforme claire. Et l'idée d'un chatbot IA en direct est une excellente alternative à la question du conseiller d'orientation."</p>
              <div class="user-info">
                <img src="<?= BASE_URL ?>/assets/images/garcon2.png" alt="Lucas">
                <div>
                  <span class="user-name">Lucas Obiang</span>
                </div>
              </div>
            </div>

            <div class="testi-card">
              <div class="stars">★★★★★</div>
              <p class="testi-text">"Merci pour cet outil, ça me donne déjà une idée des débouchés car je suis actuellement en 2nd LE. Peut-on avoir aussi des vidéos explicatives sur chaque filière ?"</p>
              <div class="user-info">
                <img src="https://i.pravatar.cc/100?u=12" alt="Paul">
                <div>
                  <span class="user-name">Rixelle Wora</span>
                </div>
              </div>
            </div>

            <div class="testi-card">
              <div class="stars">★★★★<span class="star-gray">★</span></div>
              <p class="testi-text">"Je veux voir plus d'informations sur les filières scientifiques au Gabon s'il vous plaît. Merci"</p>
              <div class="user-info">
                <img src="<?= BASE_URL ?>/assets/images/fille1.png" alt="Maxime">
                <div>
                  <span class="user-name">Chanice Eyang</span>
                </div>
              </div>
            </div>

            <div class="testi-card">
              <div class="stars">★★★★<span class="star-gray">★</span></div>
              <p class="testi-text">"Bonjour j'ai bien envie de faire des études liées au métier de l'assurance et des mines et pétrole"</p>
              <div class="user-info">
                <img src="<?= BASE_URL ?>/assets/images/garcon1.png" alt="Hugo">
                <div>
                  <span class="user-name">Ferdinand Nguema</span>
                </div>
              </div>
            </div>

            <div class="testi-card">
              <div class="stars">★★★★★</div>
              <p class="testi-text">"Zéro bug en 3 mois d'utilisation. Support réactif en moins de 24h. Les features marchent vraiment. Connect'Academia est stable, fiable, et l'équipe écoute vraiment ses utilisateurs."</p>
              <div class="user-info">
                <img src="<?= BASE_URL ?>/assets/images/fille2.png" alt="Camille">
                <div>
                  <span class="user-name">Lovely Mavioga</span>
                </div>
              </div>
            </div>

            <div class="testi-card">
              <div class="stars">★★★★★</div>
              <p class="testi-text">"Est-ce que vous comptez ajouter une partie d'orientation basée sur les tests de personnalité ?"</p>
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
          <h2>QUESTIONS FRÉQUENTES</h2>
          <div class="title-underline"></div>
        </div>

        <div class="faq-container">

          <div class="faq-item">
            <button class="faq-question">
              <span>1. L'IA est-elle fiable pour mes cours ?</span>
              <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </button>
            <div class="faq-answer">
              <p>○ Réponse : Notre IA "Zéro Hallucination" garantit un alignement parfait avec le contenu des cours du programme national. Elle est incapable de produire des informations hors-sujet.</p>
            </div>
          </div>

          <div class="faq-item">
            <button class="faq-question">
              <span>2. La plateforme couvre-t-elle tous les niveaux ?</span>
              <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </button>
            <div class="faq-answer">
              <p>○ Réponse : Pour l'instant, Connect'Academia est optimisée uniquement pour certaines classes (la Terminale), mais elle a pour objectif de couvrir l'ensemble du cycle secondaire à l'avenir.</p>
            </div>
          </div>

          <div class="faq-item">
            <button class="faq-question">
              <span>3. Comment fonctionne le comparateur d'écoles ?</span>
              <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </button>
            <div class="faq-answer">
              <p>○ Réponse : Nous agrégeons les données officielles (reconnaissance CAMES, frais, localisation) pour vous permettre de comparer les établissements objectivement.</p>
            </div>
          </div>

          <div class="faq-item">
            <button class="faq-question">
              <span>4. Puis-je gérer mes documents administratifs sur le site ?</span>
              <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </button>
            <div class="faq-answer">
              <p>○ Réponse : Oui. Nous déployons progressivement des outils pour simplifier vos démarches (inscriptions, suivi de dossiers, bourses) directement depuis votre espace personnel.</p>
            </div>
          </div>

          <div class="faq-item">
            <button class="faq-question">
              <span>5. Est-ce que l'accès est gratuit ?</span>
              <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </button>
            <div class="faq-answer">
              <p>○ Réponse : L'inscription est gratuite et donne accès aux fonctionnalités de base. Des formules Premium permettent de débloquer l'IA illimitée et les outils avancés.</p>
            </div>
          </div>

        </div>

        <div class="faq-footer">
          <p>Vous n'avez pas trouvé la réponse à vos questions ?</p>
          <p><a href="<?= BASE_URL ?>/assets/lib/contact.html">Cliquez-ici</a></p>
        </div>
      </section>


  </article>
  </main>


  <footer class="footer" style="background-image: url('<?= BASE_URL ?>/assets/images/footer-bg.png')">

    <div class="footer-top section">
      <div class="container grid-list">

        <div class="footer-brand">

          <a href="<?= url('') ?>" class="logo">
            <img src="<?= BASE_URL ?>/assets/images/logo1-removebg-preview.png" width="162" height="50" alt="Connect'Academia logo">
          </a>

          <p class="footer-brand-text">
            Lorem ipsum dolor amet consecto adi pisicing elit sed eiusm tempor incidid unt labore dolore.
          </p>

          <div class="wrapper">
            <span class="span">Add:</span>
            <address class="address">70-80 Upper St Norwich NR2</address>
          </div>

          <div class="wrapper">
            <span class="span">Call:</span>
            <a href="tel:+011234567890" class="footer-link">+01 123 4567 890</a>
          </div>

          <div class="wrapper">
            <span class="span">Email:</span>
            <a href="mailto:info@eduweb.com" class="footer-link">info@eduweb.com</a>
          </div>

        </div>

        <ul class="footer-list">

          <li>
            <p class="footer-list-title">Online Platform</p>
          </li>

          <li>
            <a href="#about" class="footer-link">About</a>
          </li>

          <li>
            <a href="<?= url('auth/inscription') ?>" class="footer-link">Courses</a>
          </li>

          <li>
            <a href="<?= url('auth/inscription') ?>" class="footer-link">Instructor</a>
          </li>

          <li>
            <a href="#" class="footer-link">Events</a>
          </li>

          <li>
            <a href="<?= url('auth/inscription') ?>" class="footer-link">Instructor Profile</a>
          </li>

          <li>
            <a href="<?= BASE_URL ?>/assets/lib/tarifs.html" class="footer-link">Purchase Guide</a>
          </li>

        </ul>

        <ul class="footer-list">

          <li>
            <p class="footer-list-title">Links</p>
          </li>

          <li>
            <a href="<?= BASE_URL ?>/assets/lib/contact.html" class="footer-link">Contact Us</a>
          </li>

          <li>
            <a href="#" class="footer-link">Gallery</a>
          </li>

          <li>
            <a href="#" class="footer-link">News & Articles</a>
          </li>

          <li>
            <a href="#social-media" class="footer-link">FAQ's</a>
          </li>

          <li>
            <a href="<?= url('auth/connexion') ?>" class="footer-link">Sign In/Registration</a>
          </li>

          <li>
            <a href="#" class="footer-link">Coming Soon</a>
          </li>

        </ul>

        <div class="footer-list">

          <p class="footer-list-title">Contacts</p>

          <p class="footer-list-text">
            Enter your email address to register to our newsletter subscription
          </p>

          <form action="" class="newsletter-form">
            <input type="email" name="email_address" placeholder="Your email" required class="input-field">

            <button type="submit" class="btn has-before">
              <span class="span">Subscribe</span>
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

          </ul>

        </div>

      </div>
    </div>

    <div class="footer-bottom">
      <div class="container">
        <p class="copyright">
          Copyright 2026 All Rights Reserved by <a href="https://www.instagram.com/500_vault" class="copyright-link">500_vault</a>
        </p>
      </div>
    </div>

  </footer>


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
  });
</script>

  <script src="<?= BASE_URL ?>/assets/js/script.js" defer></script>

  <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>

  <script>
(function(){if(!window.chatbase||window.chatbase("getState")!=="initialized"){window.chatbase=(...arguments)=>{if(!window.chatbase.q){window.chatbase.q=[]}window.chatbase.q.push(arguments)};window.chatbase=new Proxy(window.chatbase,{get(target,prop){if(prop==="q"){return target.q}return(...args)=>target(prop,...args)}})}const onLoad=function(){const script=document.createElement("script");script.src="https://www.chatbase.co/embed.min.js";script.id="GCRCfFG4ahJN6GlIIbvyC";script.domain="www.chatbase.co";document.body.appendChild(script)};if(document.readyState==="complete"){onLoad()}else{window.addEventListener("load",onLoad)}})();
  </script>

</body>

</html>
