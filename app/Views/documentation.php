<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title><?= e($pageTitle ?? "Documentation — Connect'Academia") ?></title>
  <meta name="description" content="Guide complet Connect'Academia : créer ton compte élève, utiliser les modules Apprentissage, Communauté et Orientation, faire une préinscription ou une inscription dans un établissement au Gabon.">

  <link rel="shortcut icon" href="<?= BASE_URL ?>/assets/images/Logo 1 CA COMPLET.svg" type="image/svg+xml">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="<?= asset('css/colors_and_type.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/documentation.css') ?>">
</head>
<body class="doc-page">

<?php
// Icônes maison (SVG inline, trait encre + accent violet) — pas de lib externe.
$docIcon = function (string $name, int $size = 24): string {
  static $glyphs = [
    'graduation-cap' => '<path d="M2.5 9.3 12 4.8l9.5 4.5L12 13.8z"/><path d="M6.8 11.7v4.1c0 1.5 2.3 2.9 5.2 2.9s5.2-1.4 5.2-2.9v-4.1"/><path d="M21.5 9.3v5.2"/><circle class="doc-ic-accent" cx="21.5" cy="16.6" r="1.5"/>',
    'compass'        => '<circle cx="12" cy="12" r="9"/><path d="m15.7 8.3-2.1 5.3-5.3 2.1 2.1-5.3z"/><circle class="doc-ic-accent" cx="12" cy="12" r="1.3"/>',
    'book-open'      => '<path d="M12 6.8C10 5.1 7.3 4.5 4 4.7c-.6 0-1 .5-1 1v11.5c0 .6.5 1 1.1 1 3 0 5.6.7 7.9 2.3 2.3-1.6 4.9-2.3 7.9-2.3.6 0 1.1-.4 1.1-1V5.7c0-.5-.4-1-1-1-3.3-.2-6 .4-8 2.1z"/><path d="M12 6.8v13.7"/>',
    'users'          => '<circle cx="9" cy="8.2" r="3.4"/><path d="M3.4 19.4c.4-3 2.7-4.8 5.6-4.8s5.2 1.8 5.6 4.8"/><path d="M15.7 5.2a3.4 3.4 0 0 1 0 6"/><path d="M17.9 14.9c1.9.8 3 2.4 3.3 4.5"/>',
    'key-round'      => '<circle cx="8" cy="15.5" r="4.3"/><path d="m11.2 12.4 8-8"/><path d="m15.7 7.8 2.2 2.2"/><path d="m18.6 4.9 2 2"/><circle class="doc-ic-accent" cx="8" cy="15.5" r="1.4"/>',
    'info'           => '<circle cx="12" cy="12" r="9"/><path d="M12 11.2v5.4"/><circle class="doc-ic-accent" cx="12" cy="7.8" r="1.4"/>',
    'shield-check'   => '<path d="M12 2.8 19.5 6v5.4c0 4.6-3 8.2-7.5 9.8-4.5-1.6-7.5-5.2-7.5-9.8V6z"/><path class="doc-ic-check" d="m8.7 11.8 2.4 2.4 4.4-4.4"/>',
    'star'           => '<path class="doc-ic-tint" d="m12 3.6 2.6 5.2 5.7.8-4.1 4 1 5.7-5.2-2.7-5.2 2.7 1-5.7-4.1-4 5.7-.8z"/>',
    'qr-code'        => '<rect x="3.5" y="3.5" width="6.5" height="6.5" rx="1.2"/><rect x="14" y="3.5" width="6.5" height="6.5" rx="1.2"/><rect x="3.5" y="14" width="6.5" height="6.5" rx="1.2"/><path class="doc-ic-accent" d="M14 14h2.6v2.6H14zM17.9 17.9h2.6v2.6h-2.6z"/><path d="M20.5 14v.01M14 20.5v.01"/>',
    'mail-check'     => '<path d="M20 11.3V7.5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v9c0 1.1.9 2 2 2h7.5"/><path d="m3.4 8 8.6 5.4L20.6 8"/><path class="doc-ic-check" d="m15.5 17.3 2.1 2.1 3.9-3.9"/>',
    'arrow-right'    => '<path d="M5 12h14"/><path d="m13 5 7 7-7 7"/>',
  ];
  return '<svg class="doc-ic" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($glyphs[$name] ?? '') . '</svg>';
};
?>

<?php $navActive = 'documentation'; require BASE_PATH . '/app/Views/partials/site-navbar.php'; ?>

<!-- ── Hero ─────────────────────────────────────────────── -->
<header class="doc-hero">
  <div class="doc-hero-bg" aria-hidden="true"></div>
  <div class="doc-container">
    <span class="doc-eyebrow">Documentation</span>
    <h1>Apprendre à utiliser Connect'Academia</h1>
    <p class="doc-lead">
      Tout ce qu'il faut savoir pour démarrer&nbsp;: créer un compte, réviser le Bac,
      échanger avec la communauté et candidater dans l'établissement de ton choix.
      Choisis ton profil pour suivre le guide adapté.
    </p>

    <div class="doc-personas">
      <a class="doc-persona" href="#parcours-eleve">
        <span class="doc-persona-icon"><?= $docIcon('graduation-cap') ?></span>
        <h2>Je suis élève</h2>
        <p>Tu prépares le Bac au Gabon. Accède aux trois modules&nbsp;: Apprentissage, Communauté et Orientation.</p>
        <span class="doc-persona-go">Suivre le guide élève <?= $docIcon('arrow-right', 16) ?></span>
      </a>
      <a class="doc-persona" href="#parcours-visiteur">
        <span class="doc-persona-icon"><?= $docIcon('compass') ?></span>
        <h2>Je suis visiteur</h2>
        <p>Parent, étudiant ou candidat libre&nbsp;: vous utilisez uniquement le module Orientation pour candidater dans un établissement.</p>
        <span class="doc-persona-go">Suivre le guide visiteur <?= $docIcon('arrow-right', 16) ?></span>
      </a>
    </div>
  </div>
</header>

<!-- ── Corps ────────────────────────────────────────────── -->
<div class="doc-container doc-body">

  <!-- Sommaire -->
  <aside class="doc-toc" aria-label="Sommaire">
    <p class="doc-toc-title">Parcours élève</p>
    <ul>
      <li><a href="#eleve-compte">1. Créer ton compte</a></li>
      <li><a href="#eleve-hub">2. Le hub, ton point de départ</a></li>
      <li><a href="#eleve-abonnement">3. Un accès 100&nbsp;% gratuit</a></li>
      <li><a href="#eleve-apprentissage">4. Module Apprentissage</a></li>
      <li><a href="#eleve-communaute">5. Module Communauté</a></li>
      <li><a href="#eleve-orientation">6. Module Orientation</a></li>
      <li><a class="doc-toc-sub" href="#orientation-explorer">Explorer les établissements</a></li>
      <li><a class="doc-toc-sub" href="#orientation-preinscription">La préinscription</a></li>
      <li><a class="doc-toc-sub" href="#orientation-inscription">L'inscription</a></li>
      <li><a class="doc-toc-sub" href="#orientation-espace">Ton espace orientation</a></li>
    </ul>
    <p class="doc-toc-title">Parcours visiteur</p>
    <ul>
      <li><a href="#visiteur-qui">1. À qui s'adresse ce parcours</a></li>
      <li><a href="#visiteur-explorer">2. Explorer sans compte</a></li>
      <li><a href="#visiteur-candidater">3. Candidater : votre espace au départ</a></li>
      <li><a href="#visiteur-compte">4. Le compte express</a></li>
      <li><a href="#visiteur-suivi">5. Suivre vos dossiers</a></li>
    </ul>
    <p class="doc-toc-title">Pour finir</p>
    <ul>
      <li><a href="#comparatif">Élève ou visiteur&nbsp;?</a></li>
      <li><a href="#faq">Questions fréquentes</a></li>
    </ul>
  </aside>

  <!-- Contenu -->
  <main class="doc-content">

    <!-- ════════════════ PARCOURS ÉLÈVE ════════════════ -->
    <div class="doc-part" id="parcours-eleve">
      <div class="doc-part-head">
        <span class="doc-eyebrow">Parcours élève</span>
        <h2>Ton guide complet, de l'inscription au dossier accepté</h2>
        <p>
          En tant qu'élève, tu disposes d'un compte complet&nbsp;: un hub central et trois
          modules — Apprentissage pour réviser, Communauté pour échanger, Orientation
          pour trouver ton établissement et y candidater.
        </p>
      </div>

      <section id="eleve-compte">
        <h3>1. Créer ton compte</h3>
        <p>
          La création de compte est gratuite et prend moins de deux minutes.
        </p>
        <ol class="doc-steps">
          <li class="doc-step">
            <h5>Ouvre la page d'inscription</h5>
            <p>Depuis la page d'accueil, clique sur <strong>«&nbsp;S'inscrire&nbsp;»</strong> ou rends-toi directement sur <a href="<?= url('auth/inscription') ?>">la page d'inscription</a>.</p>
          </li>
          <li class="doc-step">
            <h5>Remplis le formulaire</h5>
            <ul>
              <li><strong>Prénom et nom</strong> — tels qu'ils figurent sur tes documents scolaires.</li>
              <li><strong>Adresse email</strong> — elle sert à te connecter et à recevoir les notifications importantes.</li>
              <li><strong>Mot de passe</strong> — au moins 8 caractères, à confirmer une seconde fois.</li>
              <li><strong>Ta série de Bac</strong> (A, B, C, D…) — elle personnalise les matières et ressources proposées. Tu peux la renseigner plus tard si tu hésites.</li>
            </ul>
          </li>
          <li class="doc-step">
            <h5>Valide et connecte-toi</h5>
            <p>Une fois le compte créé, tu es redirigé vers <a href="<?= url('auth/connexion') ?>">la page de connexion</a>. Saisis ton email et ton mot de passe&nbsp;: tu arrives sur ton hub.</p>
          </li>
        </ol>
        <div class="doc-callout">
          <?= $docIcon('key-round') ?>
          <p>Mot de passe oublié&nbsp;? Depuis la page de connexion, clique sur «&nbsp;Mot de passe oublié&nbsp;» et saisis ton email&nbsp;: un lien de réinitialisation valable 1&nbsp;heure t'est envoyé.</p>
        </div>
      </section>

      <section id="eleve-hub">
        <h3>2. Le hub, ton point de départ</h3>
        <p>
          Après chaque connexion, tu atterris sur le <strong>hub</strong>&nbsp;: ton tableau
          de bord personnel. Il rassemble tes statistiques (cours consultés, progression
          globale, publications, abonnés), tes cours en cours, les annonces de la
          plateforme et surtout l'accès aux trois modules.
        </p>
        <div class="doc-cards">
          <div class="doc-card">
            <span class="doc-card-icon"><?= $docIcon('book-open') ?></span>
            <h5>Apprentissage</h5>
            <p>Cours, fiches et anciens sujets par matière, avec un tuteur IA pour t'expliquer ce que tu ne comprends pas.</p>
          </div>
          <div class="doc-card">
            <span class="doc-card-icon"><?= $docIcon('users') ?></span>
            <h5>Communauté</h5>
            <p>Un fil académique où poser tes questions, répondre aux autres et discuter dans des salons par thème.</p>
          </div>
          <div class="doc-card">
            <span class="doc-card-icon"><?= $docIcon('compass') ?></span>
            <h5>Orientation</h5>
            <p>Découvre les universités et grandes écoles du Gabon, compare-les et candidate directement en ligne.</p>
          </div>
        </div>
      </section>

      <section id="eleve-abonnement">
        <h3>3. Un accès 100&nbsp;% gratuit</h3>
        <p>
          Connect'Academia est <strong>entièrement gratuit</strong>&nbsp;: dès la création
          de ton compte, tu accèdes sans limite aux modules Apprentissage, Communauté et
          Orientation. Il n'y a ni abonnement, ni période d'essai, ni frais cachés.
        </p>
        <div class="doc-callout">
          <?= $docIcon('info') ?>
          <p>Aucun paiement n'est demandé pour utiliser la plateforme. Seuls d'éventuels <strong>frais d'inscription des établissements</strong> (module Orientation) peuvent s'appliquer, et ils sont fixés par les écoles elles-mêmes.</p>
        </div>
      </section>

      <section id="eleve-apprentissage">
        <h3>4. Module Apprentissage</h3>
        <p>
          C'est ton espace de révision pour le Bac, organisé autour des matières de ta série.
        </p>
        <ol class="doc-steps">
          <li class="doc-step">
            <h5>Parcours tes matières</h5>
            <p>Le tableau de bord Apprentissage liste les matières de ta série. Chaque matière regroupe des ressources&nbsp;: cours, fiches de révision, exercices et anciens sujets.</p>
          </li>
          <li class="doc-step">
            <h5>Ouvre une ressource dans le lecteur</h5>
            <p>Chaque document s'ouvre dans un lecteur intégré, directement dans ton navigateur — rien à télécharger. Ajoute les ressources utiles à tes <strong>favoris</strong> pour les retrouver en un clic.</p>
          </li>
          <li class="doc-step">
            <h5>Pose tes questions au tuteur IA</h5>
            <p>Un point de cours pas clair&nbsp;? Ouvre le tuteur IA, pose ta question en français et obtiens une explication adaptée à ton niveau. Ton historique de conversations est conservé pour y revenir.</p>
          </li>
          <li class="doc-step">
            <h5>Suis ta progression</h5>
            <p>Marque les cours terminés au fil de tes révisions&nbsp;: la page <strong>Progression</strong> calcule ton avancement global et par matière, aussi visible sur le hub.</p>
          </li>
        </ol>
      </section>

      <section id="eleve-communaute">
        <h3>5. Module Communauté</h3>
        <p>
          La communauté est un fil d'actualité académique entre élèves&nbsp;: on y pose des
          questions, on y répond, on s'entraide.
        </p>
        <ol class="doc-steps">
          <li class="doc-step">
            <h5>Publie une question ou une astuce</h5>
            <p>Depuis le fil, rédige ta publication (texte, éventuellement image). Les autres élèves la voient, l'aiment et y répondent en commentaires.</p>
          </li>
          <li class="doc-step">
            <h5>Réponds et fais valider la meilleure réponse</h5>
            <p>Sous chaque question, l'auteur peut marquer un commentaire comme <strong>meilleure réponse</strong> — pratique pour repérer la bonne explication d'un coup d'œil.</p>
          </li>
          <li class="doc-step">
            <h5>Explore et abonne-toi</h5>
            <p>La page <strong>Explorer</strong> te fait découvrir les publications populaires et de nouveaux profils. Abonne-toi aux élèves pertinents pour voir leurs publications dans ton fil.</p>
          </li>
          <li class="doc-step">
            <h5>Discute dans les salons</h5>
            <p>Des <strong>salons de discussion</strong> par thème permettent d'échanger en direct. Tes notifications (réponses, mentions, abonnements) sont regroupées dans la cloche en haut de l'écran.</p>
          </li>
        </ol>
        <div class="doc-callout">
          <?= $docIcon('shield-check') ?>
          <p>Un contenu déplacé&nbsp;? Utilise le bouton <strong>Signaler</strong> sur la publication&nbsp;: l'équipe de modération l'examine rapidement.</p>
        </div>
      </section>

      <section id="eleve-orientation">
        <h3>6. Module Orientation</h3>

        <div class="doc-highlight">
          <span class="doc-eyebrow">Le cœur de la plateforme</span>
          <h3>De la découverte d'un établissement au dossier d'inscription validé</h3>
          <p>
            Le module Orientation te permet de découvrir les universités et grandes écoles
            du Gabon, puis de déposer une <strong>préinscription</strong> ou une
            <strong>inscription</strong> entièrement en ligne — avec des documents officiels
            (attestations de préinscription et d'inscription) vérifiables par QR code.
          </p>
        </div>

        <section id="orientation-explorer">
          <h4>Explorer les établissements</h4>
          <ol class="doc-steps">
            <li class="doc-step">
              <h5>Ouvre la marketplace Orientation</h5>
              <p>Depuis le hub ou le menu du site, clique sur <a href="<?= url('orientation') ?>">Orientations</a>. Tu peux filtrer par type&nbsp;: <a href="<?= url('orientation/universites') ?>">universités</a> ou <a href="<?= url('orientation/grandes-ecoles') ?>">grandes écoles</a>.</p>
            </li>
            <li class="doc-step">
              <h5>Consulte la fiche d'un établissement</h5>
              <p>Chaque fiche présente l'essentiel pour choisir&nbsp;:</p>
              <ul>
                <li>la présentation, la localisation et les contacts&nbsp;;</li>
                <li>les <strong>formations, filières et spécialités</strong> proposées&nbsp;;</li>
                <li>les <strong>actualités</strong> publiées par l'établissement (concours, dates, portes ouvertes)&nbsp;;</li>
                <li>les <strong>avis 5 étoiles</strong> laissés par d'autres élèves.</li>
              </ul>
            </li>
            <li class="doc-step">
              <h5>Compare, puis candidate</h5>
              <p>Sur la fiche, deux boutons peuvent apparaître selon ce que l'établissement a activé&nbsp;: <strong>Préinscription</strong> et <strong>Inscription</strong>. Connecté à ton compte, tes informations sont préremplies automatiquement dans les formulaires.</p>
            </li>
          </ol>
          <div class="doc-callout">
            <?= $docIcon('star') ?>
            <p>Tu peux toi-même noter un établissement (1 à 5 étoiles) et laisser un avis, à condition d'être connecté à ton compte.</p>
          </div>
        </section>

        <section id="orientation-preinscription">
          <h4>La préinscription, pas à pas</h4>
          <p>
            La préinscription est une <strong>candidature simple en une étape</strong>&nbsp;:
            tu manifestes ton intérêt auprès de l'établissement, qui te recontacte ensuite.
          </p>
          <ol class="doc-steps">
            <li class="doc-step">
              <h5>Lance la préinscription</h5>
              <p>Sur la fiche de l'établissement, clique sur <strong>«&nbsp;Préinscription&nbsp;»</strong>. Le bouton n'apparaît que si l'établissement a ouvert ses préinscriptions.</p>
            </li>
            <li class="doc-step">
              <h5>Remplis le formulaire unique</h5>
              <ul>
                <li><strong>État civil</strong>&nbsp;: nom complet, genre, date et lieu de naissance, nationalité.</li>
                <li><strong>Contact</strong>&nbsp;: email, téléphone (indicatif +241 par défaut), adresse, ville, pays de provenance.</li>
                <li><strong>Projet d'études</strong>&nbsp;: la <strong>filière visée</strong> (et sa <strong>spécialité</strong> si la filière en propose), le <strong>niveau d'entrée</strong> et ton <strong>dernier établissement fréquenté</strong>.</li>
              </ul>
              <p>Si tu es connecté, la plupart des champs sont déjà préremplis avec les informations de ton profil.</p>
            </li>
            <li class="doc-step">
              <h5>Envoie ton dossier</h5>
              <p>À l'envoi, ton dossier passe immédiatement au statut <span class="doc-badge doc-badge--info">soumis</span>&nbsp;: il est visible par l'établissement.</p>
            </li>
            <li class="doc-step">
              <h5>Télécharge ton attestation</h5>
              <p>Si l'établissement délivre des attestations, une <strong>attestation officielle de préinscription</strong> (PDF numéroté avec QR code d'authenticité) est générée sur-le-champ&nbsp;: télécharge-le depuis la page de confirmation, il reste aussi disponible dans ton espace.</p>
            </li>
            <li class="doc-step">
              <h5>Attends le retour de l'établissement</h5>
              <p>L'établissement examine les préinscriptions et te contacte (notification, email ou téléphone) pour la suite&nbsp;: entretien, concours ou passage à l'inscription.</p>
            </li>
          </ol>
        </section>

        <section id="orientation-inscription">
          <h4>L'inscription, pas à pas</h4>
          <p>
            L'inscription est la <strong>candidature complète</strong>&nbsp;: un assistant en
            deux étapes qui constitue ton dossier officiel, pièces justificatives comprises.
          </p>
          <ol class="doc-steps">
            <li class="doc-step">
              <h5>Lance l'inscription</h5>
              <p>Sur la fiche de l'établissement, clique sur <strong>«&nbsp;Inscription&nbsp;»</strong> (visible si l'établissement a ouvert ses inscriptions en ligne).</p>
            </li>
            <li class="doc-step">
              <h5>Étape 1 — ton identité</h5>
              <p>Renseigne ton état civil et tes coordonnées (les mêmes informations que pour une préinscription). À la validation, un dossier est créé au statut <span class="doc-badge doc-badge--neutral">brouillon</span>&nbsp;: tu peux revenir plus tard sans rien perdre.</p>
            </li>
            <li class="doc-step">
              <h5>Étape 2 — les pièces justificatives</h5>
              <p>Dépose les documents demandés par l'établissement pour ton niveau d'entrée (photo d'identité, relevés de notes, diplôme, acte de naissance…).</p>
              <ul>
                <li>Les pièces marquées <strong>«&nbsp;Requis&nbsp;»</strong> sont obligatoires&nbsp;: l'envoi est bloqué tant qu'elles ne sont pas toutes déposées.</li>
                <li>Formats acceptés&nbsp;: images ou PDF, directement depuis ton téléphone ou ton ordinateur.</li>
              </ul>
            </li>
            <li class="doc-step">
              <h5>Envoie ton dossier complet</h5>
              <p>À la validation de l'étape 2, ton dossier passe au statut <span class="doc-badge doc-badge--info">soumis</span>. Les frais d'inscription éventuels se règlent directement auprès de l'établissement, qui t'indique la marche à suivre.</p>
            </li>
            <li class="doc-step">
              <h5>Suis le traitement de ton dossier</h5>
              <p>L'établissement examine ton dossier et met son statut à jour&nbsp;— tu es notifié à chaque changement&nbsp;:</p>
              <ul>
                <li><span class="doc-badge doc-badge--warning">accepté</span> — ton dossier est retenu, l'établissement finalise ton admission&nbsp;;</li>
                <li><span class="doc-badge doc-badge--success">inscrit</span> — ton inscription est validée&nbsp;;</li>
                <li><span class="doc-badge doc-badge--error">refusé</span> — le dossier n'est pas retenu&nbsp;; le motif t'est communiqué quand il est renseigné.</li>
              </ul>
            </li>
            <li class="doc-step">
              <h5>Récupère ton attestation d'inscription</h5>
              <p>Dès que ton inscription est validée, une <strong>attestation officielle d'inscription</strong> (PDF avec QR code d'authenticité) est délivrée&nbsp;: télécharge-la depuis ton espace, rubrique Documents.</p>
            </li>
          </ol>
          <div class="doc-callout">
            <?= $docIcon('qr-code') ?>
            <p><strong>Documents infalsifiables.</strong> Chaque attestation porte un QR code unique. N'importe qui (établissement, administration, employeur) peut le scanner pour vérifier l'authenticité du document sur une page publique de vérification.</p>
          </div>
        </section>

        <section id="orientation-espace">
          <h4>Ton espace orientation</h4>
          <p>
            Toutes tes candidatures vivent dans <strong>ton espace orientation</strong>,
            accessible depuis le hub ou directement via <a href="<?= url('eleve/orientation') ?>">Mon espace</a>.
            Tu y retrouves&nbsp;:
          </p>
          <ul class="doc-list">
            <li><strong>Mes préinscriptions</strong> et <strong>mes inscriptions</strong> — chaque dossier avec son statut en temps réel et son détail complet&nbsp;;</li>
            <li><strong>Documents</strong> — toutes tes attestations, téléchargeables à tout moment&nbsp;;</li>
            <li><strong>Paiements et reçus</strong> — l'historique de tes règlements liés à l'orientation&nbsp;;</li>
            <li><strong>Notifications</strong> — chaque changement de statut ou message d'un établissement&nbsp;;</li>
            <li><strong>Mon profil</strong> — tes informations personnelles (elles servent au préremplissage des formulaires) et ton mot de passe.</li>
          </ul>
        </section>
      </section>
    </div>

    <!-- ════════════════ PARCOURS VISITEUR ════════════════ -->
    <div class="doc-part" id="parcours-visiteur">
      <div class="doc-part-head">
        <span class="doc-eyebrow">Parcours visiteur</span>
        <h2>Candidater dans un établissement, sans être élève de la plateforme</h2>
        <p>
          Parent qui inscrit son enfant, étudiant déjà bachelier, candidat libre&nbsp;:
          le module Orientation vous est entièrement ouvert, gratuitement et sans abonnement.
          Voici votre parcours de bout en bout.
        </p>
      </div>

      <section id="visiteur-qui">
        <h3>1. À qui s'adresse ce parcours</h3>
        <p>
          Le compte visiteur est pensé pour le <strong>grand public</strong>&nbsp;: toute
          personne qui souhaite uniquement utiliser l'orientation — trouver un établissement
          au Gabon et y déposer un dossier. Il ne donne pas accès aux modules de révision
          (Apprentissage) ni à la communauté, réservés aux comptes élèves.
        </p>
      </section>

      <section id="visiteur-explorer">
        <h3>2. Explorer sans compte</h3>
        <p>
          Aucun compte n'est nécessaire pour découvrir les établissements. Depuis la page
          d'accueil, cliquez sur <strong>«&nbsp;Orientations&nbsp;»</strong> dans le menu ou
          rendez-vous sur <a href="<?= url('orientation') ?>">la marketplace Orientation</a>&nbsp;:
          parcourez les universités et grandes écoles, ouvrez leurs fiches, consultez
          formations, actualités et avis librement.
        </p>
      </section>

      <section id="visiteur-candidater">
        <h3>3. Candidater&nbsp;: votre espace se crée au départ</h3>
        <p>
          La préinscription comme l'inscription restent <strong>ouvertes à tous</strong>, sans
          être élève de la plateforme. Une seule évolution&nbsp;: votre espace se configure
          désormais <strong>au tout début</strong> de la démarche — et non plus à la fin. Dès
          que vous cliquez sur <strong>Préinscription</strong> ou <strong>Inscription</strong>,
          si vous n'êtes pas connecté, on vous invite à créer votre
          <a href="#visiteur-compte">compte express</a> (ou à vous connecter). Vous êtes ensuite
          <strong>renvoyé automatiquement à votre démarche</strong>, avec vos informations déjà
          préremplies et le dossier rattaché à votre espace dès sa création.
        </p>
        <ol class="doc-steps">
          <li class="doc-step">
            <h5>Choisissez l'établissement et le type de candidature</h5>
            <p>Sur la fiche de l'établissement, cliquez sur <strong>Préinscription</strong> ou <strong>Inscription</strong> selon ce qui est proposé.</p>
          </li>
          <li class="doc-step">
            <h5>Créez votre espace express, ou connectez-vous</h5>
            <p><strong>C'est le point de départ.</strong> Prénom, nom, email et mot de passe suffisent. L'email choisi devient la clé de votre espace&nbsp;: il sert à vous notifier et à réunir tous vos dossiers au même endroit. Un compte existe déjà&nbsp;? Connectez-vous, la démarche reprend aussitôt.</p>
          </li>
          <li class="doc-step">
            <h5>Remplissez le dossier — pré-rempli — et envoyez-le</h5>
            <p>De retour sur le formulaire, vos coordonnées sont déjà là. Le dossier est transmis à l'établissement et rattaché à votre espace ; l'attestation de préinscription est téléchargeable immédiatement depuis la page de confirmation comme depuis votre espace — conservez-le.</p>
          </li>
        </ol>
      </section>

      <section id="visiteur-compte">
        <h3>4. Le compte express</h3>
        <p>
          Le compte express est <strong>une version allégée du compte</strong>, sans série ni
          établissement — juste ce qu'il faut pour candidater puis suivre vos dossiers
          (statuts, documents, notifications). C'est lui qui s'ouvre au démarrage d'une
          préinscription ou d'une inscription.
        </p>
        <ol class="doc-steps">
          <li class="doc-step">
            <h5>La page s'ouvre au lancement de votre démarche</h5>
            <p>Quand vous lancez une préinscription ou une inscription sans être connecté, la page de création de l'espace apparaît d'elle-même, puis vous renvoie à votre candidature. Vous pouvez aussi y accéder à tout moment via <a href="<?= url('orientation/compte') ?>">Créer mon espace</a>.</p>
          </li>
          <li class="doc-step">
            <h5>Quatre champs, pas plus</h5>
            <p>Prénom, nom, adresse email et mot de passe (8 caractères minimum, à confirmer). <strong>Retenez bien cet email&nbsp;: c'est votre identifiant de connexion et la clé de tous vos dossiers.</strong></p>
          </li>
          <li class="doc-step">
            <h5>Vous êtes connecté, vos dossiers vous rejoignent</h5>
            <p>À la création, vous êtes connecté dans la foulée et renvoyé à votre démarche. Tout dossier déjà déposé avec cette adresse email est <strong>rattaché automatiquement</strong> à votre espace.</p>
          </li>
        </ol>
        <div class="doc-callout">
          <?= $docIcon('mail-check') ?>
          <p>Un compte existe déjà avec votre email&nbsp;? Inutile d'en recréer un&nbsp;: <a href="<?= url('auth/connexion') ?>">connectez-vous</a>, vos dossiers y sont déjà rattachés.</p>
        </div>
      </section>

      <section id="visiteur-suivi">
        <h3>5. Suivre vos dossiers</h3>
        <p>
          Une fois connecté, vous arrivez directement dans <strong>votre espace
          orientation</strong> — le même que celui des élèves, centré sur vos candidatures&nbsp;:
        </p>
        <ul class="doc-list">
          <li>l'état de chaque dossier en temps réel&nbsp;
            (<span class="doc-badge doc-badge--info">soumis</span>
            <span class="doc-badge doc-badge--warning">accepté</span>
            <span class="doc-badge doc-badge--success">inscrit</span>
            <span class="doc-badge doc-badge--error">refusé</span>)&nbsp;;</li>
          <li>vos <strong>attestations</strong> à télécharger, avec leur QR code de vérification&nbsp;;</li>
          <li>vos <strong>notifications</strong>&nbsp;: chaque changement de statut ou message d'un établissement&nbsp;;</li>
          <li>votre <strong>profil</strong>, pour mettre à jour vos informations — elles prérempliront vos prochaines candidatures.</li>
        </ul>
        <p>
          Pour vos connexions suivantes, utilisez simplement la
          <a href="<?= url('auth/connexion') ?>">page de connexion</a> avec votre email et
          votre mot de passe.
        </p>
      </section>
    </div>

    <!-- ════════════════ COMPARATIF + FAQ ════════════════ -->
    <div class="doc-part" id="doc-fin">
      <section id="comparatif">
        <div class="doc-part-head">
          <span class="doc-eyebrow">En résumé</span>
          <h2>Élève ou visiteur&nbsp;: quel compte pour quel usage&nbsp;?</h2>
        </div>
        <div class="doc-table-wrap">
          <table class="doc-table">
            <thead>
              <tr>
                <th>Fonctionnalité</th>
                <th>Compte élève</th>
                <th>Compte visiteur</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>Explorer les établissements</td>
                <td>Oui</td>
                <td>Oui (même sans compte)</td>
              </tr>
              <tr>
                <td>Préinscription / inscription en ligne</td>
                <td>Oui, formulaires préremplis</td>
                <td>Oui</td>
              </tr>
              <tr>
                <td>Suivi des dossiers, documents, notifications</td>
                <td>Oui</td>
                <td>Oui</td>
              </tr>
              <tr>
                <td>Module Apprentissage (cours, tuteur IA)</td>
                <td>Oui, gratuitement</td>
                <td>Non</td>
              </tr>
              <tr>
                <td>Module Communauté (fil, salons)</td>
                <td>Oui, gratuitement</td>
                <td>Non</td>
              </tr>
              <tr>
                <td>Laisser un avis sur un établissement</td>
                <td>Oui</td>
                <td>Oui, une fois connecté</td>
              </tr>
              <tr>
                <td>Création du compte</td>
                <td><a href="<?= url('auth/inscription') ?>">Inscription complète</a></td>
                <td><a href="<?= url('orientation/compte') ?>">Compte express (4 champs)</a></td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section id="faq">
        <h3>Questions fréquentes</h3>
        <div class="doc-faq">
          <details>
            <summary>Puis-je candidater dans plusieurs établissements&nbsp;?</summary>
            <div class="doc-faq-body">
              <p>Oui. Chaque candidature crée un dossier indépendant&nbsp;: tu peux déposer autant de préinscriptions et d'inscriptions que tu le souhaites, dans des établissements différents. Tous les dossiers sont suivis côte à côte dans ton espace.</p>
            </div>
          </details>
          <details>
            <summary>J'ai déposé un dossier avant de créer mon compte. Est-il perdu&nbsp;?</summary>
            <div class="doc-faq-body">
              <p>Non. Crée ton compte (élève ou express) avec <strong>la même adresse email</strong> que celle du formulaire de candidature&nbsp;: le dossier est rattaché automatiquement à ton espace dès la première connexion.</p>
            </div>
          </details>
          <details>
            <summary>Comment vérifier qu'une attestation est authentique&nbsp;?</summary>
            <div class="doc-faq-body">
              <p>Chaque document officiel porte un QR code unique. Scanne-le avec n'importe quel téléphone&nbsp;: il ouvre une page publique de vérification qui confirme (ou non) l'authenticité du document et affiche les informations du dossier correspondant.</p>
            </div>
          </details>
          <details>
            <summary>Le bouton Préinscription ou Inscription n'apparaît pas sur une fiche. Pourquoi&nbsp;?</summary>
            <div class="doc-faq-body">
              <p>Chaque établissement active lui-même ses candidatures en ligne. Si le bouton est absent, c'est que la campagne n'est pas ouverte pour le moment&nbsp;: consulte les actualités de la fiche ou contacte l'établissement directement.</p>
            </div>
          </details>
          <details>
            <summary>Mon inscription est validée. Où trouver mon attestation&nbsp;?</summary>
            <div class="doc-faq-body">
              <p>Dans ton espace orientation, rubrique <strong>Documents</strong> (ou depuis le détail du dossier). L'attestation est délivrée par l'établissement au moment où il valide ton inscription&nbsp;— tu reçois une notification dès qu'elle est disponible.</p>
            </div>
          </details>
          <details>
            <summary>L'orientation est-elle payante&nbsp;?</summary>
            <div class="doc-faq-body">
              <p>L'exploration des établissements et le dépôt de candidatures sur la plateforme sont gratuits et sans abonnement. Les éventuels frais de dossier ou d'inscription sont fixés par chaque établissement et se règlent directement auprès de lui.</p>
            </div>
          </details>
        </div>
      </section>
    </div>
  </main>
</div>

<!-- ── CTA final ────────────────────────────────────────── -->
<section class="doc-cta">
  <div class="doc-container">
    <div>
      <h2>Prêt à commencer&nbsp;?</h2>
      <p>Crée ton compte élève pour tout débloquer, ou explore directement les établissements du Gabon.</p>
    </div>
    <div class="doc-cta-actions">
      <a class="doc-btn doc-btn--primary" href="<?= url('auth/inscription') ?>">
        Créer mon compte
        <?= $docIcon('arrow-right', 18) ?>
      </a>
      <a class="doc-btn doc-btn--ghost" href="<?= url('orientation') ?>">Explorer l'orientation</a>
    </div>
  </div>
</section>

<footer class="doc-footer">
  <div class="doc-container">
    <p>© <?= date('Y') ?> Connect'Academia — Votre passerelle vers l'avenir.</p>
    <nav aria-label="Liens du pied de page">
      <a href="<?= url('') ?>">Accueil</a>
      <a href="<?= url('orientation') ?>">Orientations</a>
      <a href="<?= BASE_URL ?>/assets/lib/tarifs.html">100&nbsp;% gratuit</a>
      <a href="<?= BASE_URL ?>/contact">Contact</a>
    </nav>
  </div>
</footer>

<script>
(function () {
  // Sommaire : surligner la section visible
  var links = document.querySelectorAll('.doc-toc a[href^="#"]');
  if (!links.length || !('IntersectionObserver' in window)) return;

  var map = {};
  links.forEach(function (a) { map[a.getAttribute('href').slice(1)] = a; });

  var current = null;
  var observer = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (!entry.isIntersecting) return;
      var link = map[entry.target.id];
      if (!link) return;
      if (current) current.classList.remove('is-active');
      link.classList.add('is-active');
      current = link;
    });
  }, { rootMargin: '-30% 0px -60% 0px' });

  Object.keys(map).forEach(function (id) {
    var el = document.getElementById(id);
    if (el) observer.observe(el);
  });
})();
</script>

</body>
</html>
