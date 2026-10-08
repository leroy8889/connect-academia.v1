<?php
$couleur  = e($etab['couleur_principale'] ?? '#8B52FA');
$etabNom  = e($etab['nom']);
$etabSlug = e($etab['slug']);
$o   = $old ?? [];
$err = $errors ?? [];

// Fil d'étapes calculé par le contrôleur (spec §5).
$etapesLabels = $etapes['labels'] ?? ['Informations', 'Pièces du dossier', 'Confirmation'];
$totalSteps   = count($etapesLabels);

$selectedFiliere = $o['filiere_visee'] ?? ($dossier['filiere_visee'] ?? '');
$selectedNiveau  = $o['niveau_entree'] ?? ($dossier['niveau_entree'] ?? '');
$selectedSpec    = $o['specialite_visee'] ?? ($dossier['specialite_visee'] ?? '');
$specialites     = $specialites ?? [];

// ── Liste des pièces ──
// Elle arrive DÉJÀ filtrée par la situation de bourse déclarée à l'étape 1
// (Dossier::piecesPourBourse). La vue ne décide de rien : elle affiche.
//
// Le filtre par NIVEAU, lui, ne peut pas être fait au rendu — le niveau se
// choisit sur cette page. Chaque ligne porte donc sa portée résolue et le
// script plus bas masque celles qui ne concernent pas le niveau retenu.
$pieces       = $pieces       ?? [];
$deposees     = $deposees     ?? [];   // [piece_requise_id => ligne document]
$piecesBourse = $piecesBourse ?? null; // code interne de la pièce conditionnelle
// Établissement fermé aux boursiers : plus aucun rappel de situation de bourse.
$bourseActive = $bourseActive ?? true;
$tailleMaxTxt = $tailleMaxTxt ?? \Services\Orientation\PieceDossier::formaterTaille(\Services\Orientation\PieceDossier::TAILLE_MAX_OCTETS);
$pieceAccept  = $pieceAccept  ?? \Services\Orientation\PieceDossier::acceptHtml();
$pieceFormats = $pieceFormats ?? \Services\Orientation\PieceDossier::formatsLisibles();

/** Une pièce est-elle obligatoire pour CE dossier ? (mêmes règles que le serveur) */
$estObligatoire = static function (array $piece) use ($piecesBourse, $statutBourse): bool {
    if (($piece['code_interne'] ?? null) !== null && $piece['code_interne'] === $piecesBourse) {
        return $statutBourse === 'boursier';
    }
    return (int)($piece['obligatoire'] ?? 0) === 1;
};

/**
 * Portée d'une pièce en codes de niveau (« L1,L2 »), vide = tous niveaux.
 *
 * C'est la valeur que lit le script de filtrage. Il comparait auparavant le
 * libellé brut de la colonne (« Master M1-M2 ») au code posté par le <select>
 * (« M1 ») : une égalité qui n'arrivait jamais, d'où des pièces de master
 * affichées aux candidats de licence.
 */
$porteeDe = static fn(array $piece): string
    => \Services\Orientation\PorteePiece::attribut($piece['niveau'] ?? null);
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Inscription, étape 2 sur <?= $totalSteps ?> - <?= $etabNom ?></title>
  <link rel="stylesheet" href="<?= asset('orientation/candidature.css') ?>">
  <style>:root{--brand:<?= $couleur ?>}</style>
</head>
<body>

<header class="cand-topbar">
  <a href="<?= url('orientation/' . $etabSlug) ?>" class="cand-back">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
    Retour à la page école
  </a>
  <span class="cand-step-label">Étape 2 / <?= $totalSteps ?></span>
</header>

<main class="cand-page">

  <!-- Chapeau : l'étape 2 ouvrait directement sur son fil de progression,
       sans dire au candidat ce qu'il commençait. Les étapes 1 et 3 avaient
       leur chapeau ; celle-ci n'en avait pas. Même composition partout. -->
  <div class="cand-lede">
    <span class="kicker">Inscription <?= $etabNom ?></span>
    <h1>Votre formation et vos pièces</h1>
    <p>Indiquez la formation visée, puis déposez les justificatifs demandés.
    Vous pouvez revenir modifier ce dossier tant qu'il n'est pas envoyé.</p>
  </div>

  <!-- Fil d'étapes. Les deux premières étaient toutes deux marquées « active » :
       la pastille 1 s'affichait donc pleine alors qu'elle est franchie. -->
  <div class="stepper" aria-label="Progression">
    <?php foreach ($etapesLabels as $i => $label): ?>
      <?php if ($i > 0): ?><div class="step-connector<?= $i <= 1 ? ' done' : '' ?>"></div><?php endif; ?>
      <div class="step<?= $i === 0 ? ' done' : ($i === 1 ? ' active' : '') ?>">
        <div class="step-circle">
          <?php if ($i === 0): ?>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
          <?php else: ?><?= $i + 1 ?><?php endif; ?>
        </div>
        <div class="step-name"><?= e($label) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if (!empty($err['general'])): ?>
  <div class="alert alert-error" style="margin-bottom:20px">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?= e($err['general']) ?>
  </div>
  <?php endif; ?>
  <?php if (!empty($err['pieces'])): ?>
  <div class="alert alert-error" style="margin-bottom:20px">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?= e($err['pieces']) ?>
  </div>
  <?php endif; ?>

  <form method="POST" action="<?= url("orientation/{$etabSlug}/inscription/etape-2") ?>"
        enctype="multipart/form-data" novalidate>
    <input type="hidden" name="_csrf_token" value="<?= e($csrf) ?>">

    <?php /* Une seule carte portait les deux moments de l'étape : le choix de
             la formation et le dépôt des pièces. Elles sont maintenant
             séparées, comme les trois cartes de l'étape 1 — d'autant que la
             seconde dépend de la première (le niveau retenu filtre la liste
             des pièces), et que cet enchaînement se lit mieux entre deux
             blocs qu'à l'intérieur d'un seul. */ ?>
    <div class="cand-card">
      <div class="cand-card-head">
        <h2>Formation visée</h2>
        <p>Le niveau que vous retenez détermine les pièces demandées juste en dessous.</p>
      </div>

      <div class="form-section">
        <div class="section-label">Filière</div>

        <div class="filiere-grid" role="group" aria-label="Filière">
          <?php foreach ($filieres as $val => $lbl): ?>
          <label class="filiere-pill">
            <input type="radio" name="filiere_visee" value="<?= e($val) ?>"
                   <?= $selectedFiliere === $val ? 'checked' : '' ?>
                   id="filiere_<?= e($val) ?>">
            <span><?= e($lbl) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
        <?php if (isset($err['filiere_visee'])): ?>
        <span class="field-error"><?= e($err['filiere_visee']) ?></span>
        <?php endif; ?>

        <div class="form-group" id="specialite-group" style="display:none">
          <label for="specialite_visee">Spécialité <span class="req">*</span></label>
          <select id="specialite_visee" name="specialite_visee"
                  class="<?= isset($err['specialite_visee']) ? 'is-error' : '' ?>">
            <option value="">Choisir</option>
          </select>
          <?php if (isset($err['specialite_visee'])): ?>
          <span class="field-error"><?= e($err['specialite_visee']) ?></span>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label for="niveau_entree">Niveau d'entrée <span class="req">*</span></label>
<?php
            // Liste FERMÉE : le niveau détermine désormais un PRIX (la grille
            // tarifaire de la formation peut distinguer L1, L2 et L3). Un champ
            // libre laissait passer « licence deux », qui ne correspond à aucune
            // ligne de grille — le guichet se retrouvait alors sans tarif.
            //
            // On stocke le LIBELLÉ (« Licence 2 »), pas le code : une dizaine
            // d'écrans, de PDF et d'e-mails affichent `niveau_entree` tel quel.
            // Niveau::code() refait le chemin inverse pour retrouver la valeur
            // d'un dossier ancien, saisi en texte libre.
            $niveauCourant = \Services\Orientation\Niveau::code((string)$selectedNiveau);
            ?>
            <select id="niveau_entree" name="niveau_entree"
                    class="<?= isset($err['niveau_entree']) ? 'is-error' : '' ?>" required>
              <option value="">Choisir</option>
              <?php foreach (\Services\Orientation\Niveau::optionsParCycle() as $cycleLbl => $niveaux): ?>
                <optgroup label="<?= e($cycleLbl) ?>">
                  <?php foreach ($niveaux as $code => $libelle): ?>
                    <option value="<?= e($code) ?>" <?= $niveauCourant === $code ? 'selected' : '' ?>><?= e($libelle) ?></option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endforeach; ?>
            </select>
          <?php if (isset($err['niveau_entree'])): ?><span class="field-error"><?= e($err['niveau_entree']) ?></span><?php endif; ?>
        </div>
      </div>
    </div><!-- /cand-card Formation visée -->

    <!-- ══ Pièces justificatives (spec §5.3) ══
         La liste dépend de la situation de bourse déclarée à l'étape 1.
         Elle est construite côté serveur : rien ici n'est décidé par le
         navigateur, et la même règle est réappliquée à la soumission. -->
    <div class="cand-card">
      <div class="cand-card-head">
        <h2>Pièces du dossier</h2>
        <p>Les pièces marquées « Requis » sont nécessaires pour envoyer votre dossier. Les autres peuvent être fournies plus tard.</p>
      </div>

      <div class="form-section" id="pieces-section">
        <?php /* Ce libellé annonce le PÉRIMÈTRE de la liste, pas son titre :
                 le titre est celui de la carte. Il était piloté par la filière
                 alors que le filtrage se fait sur le NIVEAU, et il portait
                 encore un autre nom (« Pièces justificatives ») une fois
                 réécrit par le script. Il dit désormais une seule chose, la
                 bonne, et c'est le niveau qui le met à jour. */ ?>
        <div class="section-label" id="pieces-label">
          <?= $selectedNiveau ? 'Demandées pour ' . e($selectedNiveau) : 'Demandées pour votre niveau d\'entrée' ?>
        </div>

        <?php if ($statutBourse && $bourseActive): ?>
        <p class="pieces-context">
          <span>Situation déclarée : <strong><?= e($bourseLibelle) ?></strong>.
          <?php if ($piecesBourse !== null): ?>La liste ci-dessous en tient compte.<?php endif; ?></span>
          <a href="<?= url("orientation/{$etabSlug}/inscription") ?>">Modifier</a>
        </p>
        <?php endif; ?>

        <p class="pieces-formats">
          Formats acceptés : <?= e($pieceFormats) ?>. <?= e($tailleMaxTxt) ?> maximum par fichier.
        </p>

        <div class="uploads-list" id="uploads-list">
          <?php foreach ($pieces as $piece):
            $pieceId     = (int)$piece['id'];
            $obligatoire = $estObligatoire($piece);
            $estBourse   = ($piece['code_interne'] ?? null) === $piecesBourse && $piecesBourse !== null;
            $deja        = $deposees[$pieceId] ?? null;
          ?>
          <label class="upload-zone<?= $estBourse ? ' is-bourse' : '' ?><?= $deja ? ' has-file' : '' ?>"
                 data-niveaux="<?= e($porteeDe($piece)) ?>"
                 for="file_<?= $pieceId ?>">
            <?php /* Pastille d'état, pas illustration. Vide, c'est un carré en
                     creux : la même icône de document répétée sur huit lignes
                     ne distinguait rien et meublait tout. Déposée, elle porte
                     la coche — c'est la seule information que le candidat
                     cherche du regard en balayant la liste. */ ?>
            <div class="upload-ico<?= $deja ? ' done' : '' ?>" aria-hidden="true">
              <?php if ($deja): ?>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
              <?php endif; ?>
            </div>
            <div class="upload-meta">
              <div class="upload-name">
                <?php if ($obligatoire): ?><span class="req-dot" title="Obligatoire"></span><?php endif; ?>
                <?= e($piece['libelle']) ?>
              </div>
              <?php if ($deja): ?>
                <span class="upload-existing">Déjà déposée. Déposer un nouveau fichier la remplacera.</span>
                <?php if (($deja['statut'] ?? '') === 'refuse' && !empty($deja['motif_refus'])): ?>
                  <div class="upload-refus">Refusée par l'établissement : <?= e($deja['motif_refus']) ?></div>
                <?php endif; ?>
                <?php /* Réceptacle vide pour le nom du fichier que le script
                         écrit après un nouveau choix. Il manquait sur cette
                         branche : une pièce déjà déposée ne confirmait donc
                         jamais le fichier qui allait la remplacer.
                         `.upload-hint:empty` le replie tant qu'il est vide. */ ?>
                <div class="upload-hint"></div>
              <?php elseif ($estBourse): ?>
                <div class="upload-hint">Demandée parce que vous avez déclaré « <?= e($bourseLibelle) ?> ».</div>
              <?php else: ?>
                <div class="upload-hint">PDF ou image</div>
              <?php endif; ?>
            </div>
            <span class="upload-badge <?= $estBourse ? 'bourse' : ($obligatoire ? 'obligatoire' : 'optionnel') ?>">
              <?= $obligatoire ? 'Requis' : 'Recommandé' ?>
            </span>
            <input type="file" name="piece_<?= $pieceId ?>"
                   id="file_<?= $pieceId ?>"
                   accept="<?= e($pieceAccept) ?>"
                   class="piece-input">
          </label>
          <?php endforeach; ?>
        </div>

        <?php if (empty($pieces)): ?>
        <div class="alert alert-info" style="margin-top:12px">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
          Aucune pièce n'est demandée par cet établissement pour le moment.
        </div>
        <?php else: ?>
        <?php /* Le filtrage par niveau peut vider la liste : l'établissement
                 n'a configuré des pièces que pour d'autres niveaux. Sans ce
                 message, l'écran ressemble à un formulaire cassé. */ ?>
        <div class="alert alert-info" id="pieces-vide" style="margin-top:12px" hidden>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
          Aucune pièce n'est demandée pour ce niveau d'entrée. Vous pouvez envoyer votre dossier.
        </div>
        <?php endif; ?>
      </div>

    </div><!-- /cand-card -->

    <!-- Navigation -->
    <div class="nav-footer">
      <a href="<?= url("orientation/{$etabSlug}/inscription") ?>" class="btn-ghost">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
        Précédent
      </a>
      <button type="submit" class="btn-primary">
        <?= ($etapes['paiement'] ?? false) ? 'Continuer vers le paiement' : 'Envoyer mon dossier' ?>
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
      </button>
    </div>

  </form>
</main>

<script>
(function() {
  // Cascade Filière → Spécialité (configurée par l'établissement)
  var specMap      = <?= json_encode($specialites, JSON_UNESCAPED_UNICODE) ?>;
  var selectedSpec = <?= json_encode($selectedSpec, JSON_UNESCAPED_UNICODE) ?>;
  var specGroup    = document.getElementById('specialite-group');
  var specSel      = document.getElementById('specialite_visee');

  function refreshSpecialites(filiere, keepOld) {
    if (!specGroup || !specSel) return;
    var specs = specMap[filiere] || [];
    specSel.innerHTML = '<option value="">Choisir</option>';

    if (!specs.length) {
      specGroup.style.display = 'none';
      specSel.required = false;
      specSel.value = '';
      return;
    }
    specs.forEach(function(nom) {
      var opt = document.createElement('option');
      opt.value = nom;
      opt.textContent = nom;
      if (keepOld && nom === selectedSpec) opt.selected = true;
      specSel.appendChild(opt);
    });
    specGroup.style.display = '';
    specSel.required = true;
  }

  // Pill auto-fill niveau (niveaux génériques — clés ENUM historiques + libellés)
  var filiereMap = {
    'socle_l1_l2'  : 'L1',
    'licence_l3'   : 'L3',
    'master_m1_m2' : 'M1',
    'Socle L1-L2'  : 'L1',
    'Licence L3'   : 'L3',
    'Master M1-M2' : 'M1'
  };
  // Valeurs = UNIQUEMENT des codes d'option du <select> niveau (L1…M2).
  // Depuis que le niveau est une liste fermée, affecter « Licence 1 » à un
  // <select> ne sélectionne rien du tout : le champ restait vide et le
  // filtrage des pièces n'avait aucun niveau sur quoi s'appuyer.
  // « Certification » a disparu de la carte : aucun niveau de la liste ne lui
  // correspond, mieux vaut ne rien présélectionner que de laisser le champ
  // dans un état invalide.

  var niveauInput = document.getElementById('niveau_entree_field') || document.getElementById('niveau_entree');
  var pills = document.querySelectorAll('.filiere-pill input[type="radio"]');

  pills.forEach(function(radio) {
    radio.addEventListener('change', function() {
      if (niveauInput && !niveauInput.value.trim() && filiereMap[this.value]) {
        niveauInput.value = filiereMap[this.value];
      }
      refreshSpecialites(this.value, false);
      updatePiecesLabel();
      filterPieces();
    });
  });

  // Le niveau d'entrée est ce qui décide RÉELLEMENT des pièces demandées.
  // Il n'était relié à rien : seule la filière déclenchait le filtrage, et
  // uniquement au clic — jamais au chargement. Une pièce de master restait
  // donc affichée à un candidat de licence tant qu'il ne retouchait pas sa
  // filière, et même après.
  if (niveauInput) {
    niveauInput.addEventListener('change', majNiveau);
    niveauInput.addEventListener('input',  majNiveau);
  }

  function majNiveau() { updatePiecesLabel(); filterPieces(); }

  // État initial : filière déjà cochée (retour d'erreur ou dossier existant)
  var checked = document.querySelector('.filiere-pill input[type="radio"]:checked');
  if (checked) refreshSpecialites(checked.value, true);

  /* Périmètre de la liste. Il était calculé à partir de la FILIÈRE, alors que
     le filtrage réel se fait sur le NIVEAU : cliquer « Master M1-M2 » affichait
     donc « Master M1-M2 » au-dessus d'une liste filtrée sur « M1 ». Il suit
     désormais le champ qui décide, et reprend mot pour mot le libellé de
     l'option retenue — donc exactement ce que le serveur a rendu. */
  function updatePiecesLabel() {
    var label = document.getElementById('pieces-label');
    if (!label) return;

    var lbl = '';
    if (niveauInput && niveauInput.options && niveauInput.selectedIndex >= 0) {
      var opt = niveauInput.options[niveauInput.selectedIndex];
      if (opt && opt.value) { lbl = opt.textContent.trim(); }
    } else if (niveauInput) {
      lbl = niveauInput.value.trim();
    }

    label.textContent = lbl
      ? 'Demandées pour ' + lbl
      : 'Demandées pour votre niveau d\'entrée';
  }

  /* ── Filtrage des pièces par niveau d'entrée ─────────────────────────────
     Le <select> « Niveau d'entrée » poste un CODE fermé (L1…M2). Chaque ligne
     de pièce porte sa portée résolue côté serveur dans `data-niveaux` :
     « L1,L2 » pour l'ancien « Socle L1-L2 », « M1,M2 » pour « Master M1-M2 »,
     et une chaîne VIDE pour le tronc commun.

     L'ancien code comparait le libellé de portée au code du <select>
     (« Master M1-M2 » === « M1 ») : l'égalité n'arrivait jamais, aucune pièce
     n'était donc jamais reconnue « concernée », et la clause de repli
     « obligatoire → toujours visible » réaffichait la pièce de master à un
     candidat de licence. C'est le bug corrigé ici.

     On MASQUE les lignes hors périmètre au lieu de reconstruire la liste :
     reconstruire effacerait les fichiers déjà choisis par le candidat, ainsi
     que les repères posés par le serveur (« déjà déposée », pièce liée à la
     bourse).

     Trois règles non négociables :
       - une pièce HORS périmètre n'est pas « obligatoire » pour ce dossier :
         le serveur ne la réclamera pas non plus (piecesManquantesPourDossier),
         on peut donc la masquer sans jamais bloquer la soumission ;
       - une pièce DÉJÀ déposée reste visible même hors périmètre — sinon le
         candidat qui change de niveau ne verrait plus ce qu'il a envoyé ;
       - aucun champ n'est jamais désactivé. Un input `disabled` ne part pas
         dans la requête : la pièce n'arriverait pas au serveur, qui la
         réclamerait sans que le candidat puisse la déposer. */
  function niveauChoisi() {
    return niveauInput ? niveauInput.value.trim().toUpperCase() : '';
  }

  function dansLaPortee(portee, niveau) {
    if (!portee) return true;      // tronc commun
    if (!niveau) return true;      // niveau pas encore choisi : on montre tout
    return portee.split(',').indexOf(niveau) !== -1;
  }

  function filterPieces() {
    var list = document.getElementById('uploads-list');
    if (!list) return;

    var zones = list.querySelectorAll('.upload-zone[data-niveaux]');
    if (!zones.length) return;

    var niveau = niveauChoisi();

    zones.forEach(function (zone) {
      var portee = zone.getAttribute('data-niveaux') || '';
      var champ  = zone.querySelector('.piece-input');
      var rempli = zone.classList.contains('has-file')
                || !!(champ && champ.files && champ.files.length);

      var visible = dansLaPortee(portee, niveau) || rempli;

      zone.hidden = !visible;
      if (champ) {
        champ.disabled = false;
        // Une pièce masquée ne doit pas envoyer un fichier resté sélectionné
        // d'un niveau précédent : le serveur l'ignorerait de toute façon
        // (deposerLot borne le lot au périmètre du dossier), autant que
        // l'écran et la requête disent la même chose.
        if (!visible && !rempli) { champ.value = ''; }
      }
    });

    majCompteur(zones);
  }

  /* Le libellé de section annonce ce que le candidat doit fournir. Vide, il
     laissait croire à un formulaire cassé quand tout était filtré. */
  function majCompteur(zones) {
    var vide = document.getElementById('pieces-vide');
    if (!vide) return;

    var visibles = 0;
    zones.forEach(function (zone) { if (!zone.hidden) { visibles++; } });
    vide.hidden = visibles > 0;
  }

  var TAILLE_MAX = <?= (int)($tailleMax ?? \Services\Orientation\PieceDossier::TAILLE_MAX_OCTETS) ?>;
  var TAILLE_MAX_TXT = <?= json_encode($tailleMaxTxt, JSON_UNESCAPED_UNICODE) ?>;

  /* Retour immédiat sur le fichier choisi. Purement confort : le serveur
     recontrôle la taille ET le type réel du fichier (finfo), qu'on ne peut
     pas connaître ici — le navigateur ne rapporte que ce que l'extension
     laisse deviner. */
  function bindFileEvents(container) {
    if (!container) return;

    container.querySelectorAll('.piece-input').forEach(function (input) {
      input.addEventListener('change', function () {
        var zone = this.closest('.upload-zone');
        if (!zone) return;

        var hint = zone.querySelector('.upload-hint');
        var ico  = zone.querySelector('.upload-ico');

        if (!this.files || !this.files.length) {
          zone.classList.remove('has-file');
          return;
        }

        var fichier = this.files[0];

        if (fichier.size > TAILLE_MAX) {
          this.value = '';
          zone.classList.remove('has-file');
          if (hint) {
            hint.textContent = 'Fichier trop lourd (' + TAILLE_MAX_TXT + ' maximum).';
            hint.style.color = 'var(--clr-error-text)';
          }
          return;
        }

        /* PDF ou n'importe quelle image. Le type annoncé par le navigateur est
           parfois vide (HEIC sur certains Android) : dans ce cas on laisse
           passer, le serveur tranchera sur le type réel. */
        if (fichier.type && fichier.type !== 'application/pdf'
            && fichier.type.indexOf('image/') !== 0) {
          this.value = '';
          zone.classList.remove('has-file');
          if (hint) {
            hint.textContent = 'Format non accepté. Un PDF ou une image uniquement.';
            hint.style.color = 'var(--clr-error-text)';
          }
          return;
        }

        zone.classList.add('has-file');
        if (ico) {
          ico.classList.add('done');
          ico.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';
        }
        if (hint) {
          hint.textContent = fichier.name;
          hint.style.color = '';
        }
      });
    });
  }

  bindFileEvents(document.getElementById('uploads-list'));

  // État initial : le filtrage ne s'appliquait qu'au clic sur une filière.
  // Un candidat qui revient sur l'étape 2 (retour d'erreur, reprise de
  // brouillon) avait donc de nouveau toutes les pièces sous les yeux, celles
  // des autres niveaux comprises.
  updatePiecesLabel();
  filterPieces();
}());
</script>
</body>
</html>
