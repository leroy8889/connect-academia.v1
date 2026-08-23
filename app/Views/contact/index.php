<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!--
    - primary meta tag
  -->
  <title><?= e($pageTitle ?? "Contact - Connect'Academia") ?></title>
  <meta name="title" content="Contact - Connect'Academia">
  <meta name="description" content="Contactez Connect'Academia pour toute question ou assistance">

  <!--
    - favicon
  -->
  <link rel="shortcut icon" href="<?= BASE_URL ?>/assets/images/Logo 1 CA COMPLET.svg" type="image/svg+xml">

  <!--
    - custom css link
  -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/lib/contact-form.css">
  <link href='https://cdn.boxicons.com/3.0.6/fonts/basic/boxicons.min.css' rel='stylesheet'
        integrity="sha384-peOMXcPtuUEv048Rvrgr5GP4ry1QYdMnsEo5p72CxcbGkV2rlwXGnP1SWJMclp2u"
        crossorigin="anonymous">

  <!--
    - google font link
  -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,700;9..144,900&display=swap" rel="stylesheet">


  <!--
    - preload images
  -->
  <!-- Préchargements retirés : hero-banner-1/2.jpg et hero-shape-1.svg
       n'existent plus (404), et hero-bg.svg / hero-shape-2.png appartiennent au
       hero de la page d'accueil — la page contact ne les affiche pas. -->

  <style>
    /*-----------------------------------*\
  #CONTACT FORM STYLES
\*-----------------------------------*/

/* Fix pour le header - Empêche le décalage lors du scroll */
html {
  overflow-x: hidden;
  width: 100%;
}

body {
  overflow-x: hidden;
  width: 100%;
  position: relative;
}

/* Correction du header pour éviter le décalage lors du scroll */
.header {
  position: absolute !important;
  top: 20px !important;
  left: 50% !important;
  right: auto !important;
  transform: translateX(-50%) !important;
  width: calc(100% - 40px) !important;
  max-width: 1100px !important;
  margin-left: 0 !important;
  margin-right: 0 !important;
  margin-top: 0 !important;
  margin-bottom: 0 !important;
  box-sizing: border-box !important;
  will-change: transform;
}

.header.active {
  position: fixed !important;
  top: 20px !important;
  left: 50% !important;
  right: auto !important;
  transform: translateX(-50%) !important;
  width: calc(100% - 40px) !important;
  max-width: 1200px !important;
  margin-left: 0 !important;
  margin-right: 0 !important;
  margin-top: 0 !important;
  margin-bottom: 0 !important;
  box-sizing: border-box !important;
  will-change: transform;
}

.header .container {
  margin: 0 !important;
  padding-inline: 0 !important;
}

@media (min-width: 575px) {
  .header {
    width: calc(100% - 20px) !important;
  }

  .header.active {
    width: calc(100% - 20px) !important;
  }
}

@media (min-width: 768px) {
  .header {
    width: calc(100% - 40px) !important;
  }

  .header.active {
    width: calc(100% - 40px) !important;
  }
}

.contact-form {
  max-width: 500px;
  margin: 60px auto 0;
  padding: 0 20px;
  background: transparent;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  margin-bottom: 20px;
}

.form-group {
  margin-bottom: 20px;
}

.form-label {
  display: block;
  font-size: 14px;
  font-weight: 500;
  color: #1F2937;
  margin-bottom: 8px;
  font-family: var(--ff-poppins, 'Poppins', sans-serif);
}

.form-input,
.form-textarea {
  width: 100%;
  padding: 12px 16px;
  font-size: 14px;
  font-family: var(--ff-poppins, 'Poppins', sans-serif);
  color: #1F2937;
  background-color: #F9FAFB;
  border: 1px solid #E5E7EB;
  border-radius: 8px;
  transition: all 0.3s ease;
  box-sizing: border-box;
}

.form-input:focus,
.form-textarea:focus {
  outline: none;
  border-color: #8C52FF;
  background-color: #FFFFFF;
  box-shadow: 0 0 0 3px rgba(140, 82, 255, 0.1);
}

.form-input::placeholder,
.form-textarea::placeholder {
  color: #9CA3AF;
  font-size: 14px;
}

.form-textarea {
  resize: vertical;
  min-height: 120px;
  line-height: 1.5;
}

.form-success {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px;
  margin-bottom: 20px;
  background-color: #ECFDF5;
  border: 1px solid #D1FAE5;
  border-radius: 8px;
}

.success-content {
  display: flex;
  align-items: center;
  gap: 10px;
}

.success-icon {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
}

.success-text {
  font-size: 14px;
  font-weight: 500;
  color: #10b981;
  font-family: var(--ff-poppins, 'Poppins', sans-serif);
}

.form-submit-btn {
  width: 100%;
  padding: 14px 24px;
  font-size: 16px;
  font-weight: 600;
  font-family: var(--ff-poppins, 'Poppins', sans-serif);
  color: #FFFFFF;
  background-color: #8C52FF;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.3s ease;
  margin-top: 10px;
}

.form-submit-btn:hover {
  background-color: #7C42EF;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(140, 82, 255, 0.3);
}

.form-submit-btn:active {
  transform: translateY(0);
}

.form-submit-btn:focus {
  outline: none;
  box-shadow: 0 0 0 3px rgba(140, 82, 255, 0.3);
}

.section-subtitle {
  margin-top: 40px;
}

/* Responsive Design */
@media (max-width: 768px) {
  .contact-form {
    margin-top: 40px;
    padding: 0 10px;
  }

  .form-row {
    grid-template-columns: 1fr;
    gap: 0;
  }

  .form-group {
    margin-bottom: 18px;
  }

  .form-input,
  .form-textarea {
    padding: 10px 14px;
    font-size: 14px;
  }

  .form-submit-btn {
    padding: 12px 20px;
    font-size: 15px;
  }
}

@media (max-width: 480px) {
  .contact-form {
    margin-top: 30px;
  }

  .form-label {
    font-size: 13px;
    margin-bottom: 6px;
  }

  .form-input,
  .form-textarea {
    padding: 10px 12px;
    font-size: 13px;
  }

  .form-submit-btn {
    padding: 12px 18px;
    font-size: 14px;
  }
}

/* Animation pour le message de succès */
@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.form-success[style*="display: block"],
.form-success[style*="display: flex"] {
  animation: slideIn 0.3s ease;
}

/* ── SUCCESS OVERLAY ── */
.contact-success-overlay {
  display: none;
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: rgba(15, 10, 40, 0.6);
  backdrop-filter: blur(6px);
  align-items: center;
  justify-content: center;
  padding: 24px;
}
.contact-success-overlay.show {
  display: flex;
  animation: overlayIn 0.35s ease;
}
@keyframes overlayIn {
  from { opacity: 0; }
  to   { opacity: 1; }
}
.contact-success-card {
  background: #fff;
  border-radius: 20px;
  padding: 48px 40px;
  max-width: 460px;
  width: 100%;
  text-align: center;
  box-shadow: 0 24px 64px rgba(20,20,43,0.18);
  animation: cardPop 0.45s cubic-bezier(0.34,1.56,0.64,1);
}
@keyframes cardPop {
  from { transform: scale(0.7) translateY(30px); opacity: 0; }
  to   { transform: scale(1) translateY(0);       opacity: 1; }
}
.success-circle {
  width: 88px; height: 88px;
  border-radius: 50%;
  background: linear-gradient(135deg, #8C52FF, #6B32DA);
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 28px;
  animation: successPulse 2s ease-in-out infinite;
  box-shadow: 0 0 0 0 rgba(140,82,255,0.4);
}
@keyframes successPulse {
  0%   { box-shadow: 0 0 0 0 rgba(140,82,255,0.4); }
  50%  { box-shadow: 0 0 0 16px rgba(140,82,255,0); }
  100% { box-shadow: 0 0 0 0 rgba(140,82,255,0); }
}
.success-checkmark {
  display: block;
  animation: drawCheck 0.5s 0.1s ease both;
}
@keyframes drawCheck {
  from { stroke-dashoffset: 50; opacity: 0; }
  to   { stroke-dashoffset: 0;  opacity: 1; }
}
.contact-success-card h3 {
  font-family: 'Poppins', sans-serif;
  font-size: 22px;
  font-weight: 700;
  color: #1A1A2E;
  margin-bottom: 12px;
}
.contact-success-card p {
  font-family: 'Poppins', sans-serif;
  font-size: 14px;
  color: #6B6B8A;
  line-height: 1.6;
  margin-bottom: 28px;
}
.contact-success-close {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 28px;
  background: linear-gradient(135deg, #8C52FF, #6B32DA);
  color: #fff;
  border: none;
  border-radius: 10px;
  font-family: 'Poppins', sans-serif;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: transform 0.2s, box-shadow 0.2s;
}
.contact-success-close:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(140,82,255,0.35);
}

/* Loading state bouton */
.form-submit-btn.loading {
  opacity: 0.75;
  cursor: not-allowed;
  pointer-events: none;
}
.form-submit-btn .spinner {
  display: none;
  width: 16px; height: 16px;
  border: 2px solid rgba(255,255,255,0.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
  margin-right: 6px;
}
@keyframes spin {
  to { transform: rotate(360deg); }
}
.form-submit-btn.loading .spinner { display: inline-block; }

/* Error messages */
.field-error {
  font-size: 12px;
  color: #EF4444;
  margin-top: 4px;
  display: none;
  font-family: 'Poppins', sans-serif;
}
.form-input.error, .form-textarea.error {
  border-color: #EF4444;
  background: #FEF2F2;
}
.form-input.error:focus, .form-textarea.error:focus {
  box-shadow: 0 0 0 3px rgba(239,68,68,0.12);
}
.form-error-banner {
  display: none;
  padding: 12px 16px;
  background: #FEF2F2;
  border: 1px solid #FECACA;
  border-radius: 8px;
  color: #DC2626;
  font-size: 13px;
  font-family: 'Poppins', sans-serif;
  margin-bottom: 16px;
  animation: slideIn 0.3s ease;
}


  </style>

</head>

<body id="top">

  <!--
    - #HEADER
  -->

  <header class="header" data-header>
    <div class="container">

      <a href="<?= BASE_URL ?>/" class="logo">
        <img src="<?= BASE_URL ?>/assets/images/logo-officiel.png" width="80" height="15" alt="Connect'Academia logo">
      </a>

      <nav class="navbar" data-navbar>

        <div class="wrapper">
          <a href="<?= BASE_URL ?>/" class="logo">
            <img src="<?= BASE_URL ?>/assets/images/logo-officiel.png" width="162" height="50" alt="Connect'Academia logo">
          </a>

          <button class="nav-close-btn" aria-label="close menu" data-nav-toggler>
            <ion-icon name="close-outline" aria-hidden="true"></ion-icon>
          </button>
        </div>

        <ul class="navbar-list">

          <li class="navbar-item">
            <a href="<?= BASE_URL ?>/#home" class="navbar-link" data-nav-link>Accueil</a>
          </li>

          <li class="navbar-item">
            <a href="<?= BASE_URL ?>/#about" class="navbar-link" data-nav-link>À propos</a>
          </li>

          <li class="navbar-item">
            <a href="<?= BASE_URL ?>/orientation" class="navbar-link" data-nav-link>Orientations</a>
          </li>

          <li class="navbar-item">
            <a href="<?= BASE_URL ?>/contact" class="navbar-link" data-nav-link>Contact</a>
          </li>

        </ul>

      </nav>

      <div class="header-actions">

        <a href="<?= BASE_URL ?>/auth/connexion" class="btn has-before">
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

      <!--
        - #CONTACT SECTION
      -->

      <section class="section contact" id="contact" aria-label="contact">
        <div class="container">

          <p class="section-subtitle">Contactez-nous</p>

          <h2 class="h2 section-title">
            Nous sommes là pour <span class="span">vous aider</span>
          </h2>

          <p class="section-text">
            Avez-vous des questions ? N'hésitez pas à nous contacter. Notre équipe est prête à vous aider.
          </p>

          <!-- Formulaire de contact -->
          <form class="contact-form" id="contactForm" novalidate>

            <div class="form-error-banner" id="formErrorBanner"></div>

            <div class="form-row">
              <div class="form-group">
                <label for="nom" class="form-label">Nom *</label>
                <input
                  type="text"
                  id="nom"
                  name="nom"
                  class="form-input"
                  placeholder="Entrez votre nom"
                  autocomplete="family-name"
                >
                <span class="field-error" id="err-nom">Le nom est requis.</span>
              </div>

              <div class="form-group">
                <label for="prenom" class="form-label">Prénom *</label>
                <input
                  type="text"
                  id="prenom"
                  name="prenom"
                  class="form-input"
                  placeholder="Entrez votre prénom"
                  autocomplete="given-name"
                >
                <span class="field-error" id="err-prenom">Le prénom est requis.</span>
              </div>
            </div>

            <div class="form-group">
              <label for="email" class="form-label">Adresse mail *</label>
              <input
                type="email"
                id="email"
                name="email"
                class="form-input"
                placeholder="Entrez votre adresse mail"
                autocomplete="email"
              >
              <span class="field-error" id="err-email">Adresse email invalide.</span>
            </div>

            <div class="form-group">
              <label for="telephone" class="form-label">Téléphone *</label>
              <input
                type="tel"
                id="telephone"
                name="telephone"
                class="form-input"
                placeholder="Ex: +241 01 23 45 67 numéro whatsapp"
                autocomplete="tel"
              >
              <span class="field-error" id="err-telephone">Le numéro de téléphone est requis.</span>
            </div>

            <div class="form-group">
              <label for="message" class="form-label">Message *</label>
              <textarea
                id="message"
                name="message"
                class="form-textarea"
                rows="6"
                placeholder="Expliquez-nous votre besoin en quelques mots..."
              ></textarea>
              <span class="field-error" id="err-message">Le message doit contenir au moins 10 caractères.</span>
            </div>

            <button type="submit" class="form-submit-btn" id="submitBtn">
              <span class="spinner"></span>
              <span class="btn-text">Envoyer un message</span>
            </button>
          </form>

          <!-- Success Overlay -->
          <div class="contact-success-overlay" id="successOverlay">
            <div class="contact-success-card">
              <div class="success-circle">
                <svg width="42" height="42" viewBox="0 0 42 42" fill="none">
                  <polyline class="success-checkmark" points="10,22 18,30 32,14"
                    stroke="white" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"
                    stroke-dasharray="50" stroke-dashoffset="50"/>
                </svg>
              </div>
              <h3>Message envoyé !</h3>
              <p>Votre message a bien été reçu. Notre équipe vous répondra dans les plus brefs délais. Merci de nous avoir contactés !</p>
              <button class="contact-success-close" onclick="document.getElementById('successOverlay').classList.remove('show')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <polyline points="20 6 9 17 4 12"/>
                </svg>
                Parfait, merci !
              </button>
            </div>
          </div>

        </div>
      </section>

    </article>
  </main>


  <!--
    - #FOOTER
  -->

  <footer class="footer" style="background-image: url('<?= BASE_URL ?>/assets/images/footer-bg.png')">

    <div class="footer-top section">
      <div class="container grid-list">

        <div class="footer-brand">

          <a href="<?= BASE_URL ?>/" class="logo">
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
            <a href="<?= BASE_URL ?>/#about" class="footer-link">À propos</a>
          </li>

          <li>
            <a href="<?= BASE_URL ?>/auth/inscription" class="footer-link">Inscription</a>
          </li>

          <li>
            <a href="<?= BASE_URL ?>/" class="footer-link">Accueil</a>
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
            <a href="<?= BASE_URL ?>/#social-media" class="footer-link">Médias sociaux</a>
          </li>

          <li>
            <a href="<?= BASE_URL ?>/auth/connexion" class="footer-link">Connexion</a>
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

  <!--
    - ionicon link
  -->
  <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"
          integrity="sha384-EcogLzNzScolFpuLPk/kxJRnmu39K6J1j3+8yrfrebDq7qtbZoB2gfP0rD08JKit"
          crossorigin="anonymous"></script>
  <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"
          integrity="sha384-xYx1P7dxspoifaKXuLoaPybset7M4RzoZPDVwB+CrVVobxN3h90OQGVoHYHtCr9G"
          crossorigin="anonymous"></script>

  <!--
    - Contact Form Script
  -->
  <script>
  (function () {
    const form        = document.getElementById('contactForm');
    const submitBtn   = document.getElementById('submitBtn');
    const btnText     = submitBtn?.querySelector('.btn-text');
    const errorBanner = document.getElementById('formErrorBanner');
    const overlay     = document.getElementById('successOverlay');

    const fields = {
      nom:       { el: document.getElementById('nom'),       err: document.getElementById('err-nom') },
      prenom:    { el: document.getElementById('prenom'),    err: document.getElementById('err-prenom') },
      email:     { el: document.getElementById('email'),     err: document.getElementById('err-email') },
      telephone: { el: document.getElementById('telephone'), err: document.getElementById('err-telephone') },
      message:   { el: document.getElementById('message'),   err: document.getElementById('err-message') },
    };

    function clearErrors() {
      errorBanner.style.display = 'none';
      Object.values(fields).forEach(({ el, err }) => {
        el.classList.remove('error');
        err.style.display = 'none';
      });
    }

    function showFieldError(name, msg) {
      const f = fields[name];
      if (!f) return;
      f.el.classList.add('error');
      f.err.textContent = msg;
      f.err.style.display = 'block';
    }

    function setLoading(on) {
      if (on) {
        submitBtn.classList.add('loading');
        if (btnText) btnText.textContent = 'Envoi en cours…';
      } else {
        submitBtn.classList.remove('loading');
        if (btnText) btnText.textContent = 'Envoyer un message';
      }
    }

    /* Réinitialise l'état d'erreur d'un champ à la frappe */
    Object.values(fields).forEach(({ el, err }) => {
      el.addEventListener('input', () => {
        el.classList.remove('error');
        err.style.display = 'none';
      });
    });

    if (!form) return;

    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      clearErrors();

      const body = {
        nom:       fields.nom.el.value.trim(),
        prenom:    fields.prenom.el.value.trim(),
        email:     fields.email.el.value.trim(),
        telephone: fields.telephone.el.value.trim(),
        message:   fields.message.el.value.trim(),
      };

      /* Validation client légère */
      let hasError = false;
      if (!body.nom)       { showFieldError('nom', 'Le nom est requis.');         hasError = true; }
      if (!body.prenom)    { showFieldError('prenom', 'Le prénom est requis.');   hasError = true; }
      if (!body.email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(body.email)) {
        showFieldError('email', 'Adresse email invalide.');                        hasError = true;
      }
      if (!body.telephone) { showFieldError('telephone', 'Le téléphone est requis.'); hasError = true; }
      if (body.message.length < 10) {
        showFieldError('message', 'Le message doit contenir au moins 10 caractères.'); hasError = true;
      }
      if (hasError) {
        const firstErr = form.querySelector('.form-input.error, .form-textarea.error');
        firstErr?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
      }

      setLoading(true);

      try {
        // json_encode : BASE_URL peut contenir une apostrophe (dossier
        // « connect'academia » en local) — un littéral '…' casserait le script.
        const res  = await fetch(<?= json_encode(BASE_URL . '/api/contact', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(body),
        });
        const data = await res.json();

        if (data.success) {
          form.reset();
          overlay.classList.add('show');
        } else if (data.errors && Array.isArray(data.errors)) {
          errorBanner.textContent = data.errors.join(' — ');
          errorBanner.style.display = 'block';
          errorBanner.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else {
          throw new Error(data.message || 'Erreur serveur.');
        }
      } catch (err) {
        errorBanner.textContent = err.message || 'Une erreur est survenue. Veuillez réessayer.';
        errorBanner.style.display = 'block';
      } finally {
        setLoading(false);
      }
    });

    /* Fermer overlay avec Escape */
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') overlay?.classList.remove('show');
    });
    overlay?.addEventListener('click', e => {
      if (e.target === overlay) overlay.classList.remove('show');
    });
  })();
  </script>

</body>

</html>
