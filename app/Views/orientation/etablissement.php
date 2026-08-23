<?php
// Variables injectées par PublicController::show()
// $etab (array), $logoUrl (string), $imageUrls (array),
// $formations (array), $filieres (array), $actualites (array),
// $badges (array), $atouts (array), $statistiques (array), $infosPaiement (array),
// $avisAgg (array), $avisDist (array), $avisList (array), $monAvis (?array)

// Filières publiques (avec leurs spécialités) — menu déroulant Filière → Spécialités.
$filieres = $filieres ?? [];

// ── Avis 5 étoiles (façon Google Reviews) ─────────────────────────────
$avisAgg  = $avisAgg  ?? ['nb_avis' => 0, 'note_moyenne' => 0.0];
$avisDist = $avisDist ?? [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
$avisList = $avisList ?? [];
$monAvis  = $monAvis  ?? null;

$avisLogged = \Core\Session::isLoggedIn();
$avisRole   = \Core\Session::userRole();
// Les comptes gestionnaire/admin ne notent pas (même règle que Google Business)
$peutNoter  = $avisLogged && !in_array($avisRole, ['gestionnaire', 'admin'], true);
$avisCsrf   = \Core\Session::getCsrfToken();

$avisRelatif = static function (?string $dt): string {
    if (!$dt) return '';
    $diff = time() - strtotime($dt);
    if ($diff < 3600)     return 'il y a ' . max(1, (int)floor($diff / 60)) . ' min';
    if ($diff < 86400)    return 'il y a ' . (int)floor($diff / 3600) . ' h';
    if ($diff < 2592000)  { $j = (int)floor($diff / 86400);    return "il y a {$j} jour" . ($j > 1 ? 's' : ''); }
    if ($diff < 31536000) { $m = (int)floor($diff / 2592000);  return "il y a {$m} mois"; }
    $a = (int)floor($diff / 31536000);
    return "il y a {$a} an" . ($a > 1 ? 's' : '');
};
$avisAuteur = static function (array $a): string {
    return trim(($a['prenom'] ?? '') . ' ' . mb_strtoupper(mb_substr((string)($a['nom'] ?? ''), 0, 1, 'UTF-8')) . '.');
};

$typeLabels = [
    'universite'   => 'Université',
    'grande_ecole' => 'Grande école',
    'institut'     => 'Institut',
];

$statutFormation = [
    'ouverte' => ['label' => 'Ouverte',  'class' => ''],
    'bientot' => ['label' => 'Bientôt',  'class' => 'closed'],
    'fermee'  => ['label' => 'Fermée',   'class' => 'closed'],
];

// Vidéo : fichier uploadé (URL signée B2) prioritaire, sinon YouTube legacy
$videoFileUrl = $videoFileUrl ?? '';
$ytId = null;
$videoUrl = $etab['video_url'] ?? '';
if ($videoUrl) {
    if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $videoUrl, $m)) {
        $ytId = $m[1];
    }
}
$hasVideo = $videoFileUrl !== '' || $ytId !== null;

$couleur = preg_match('/^#[0-9A-Fa-f]{6}$/', $etab['couleur_principale'] ?? '') ? $etab['couleur_principale'] : '#1F4E79';
$nom     = $etab['nom'] ?? '';
$ville   = $etab['ville'] ?? '';
$type    = $typeLabels[$etab['type'] ?? ''] ?? ($etab['type'] ?? '');
$annee   = date('Y');

// URLs candidature (routes : Orientation\CandidatureController)
$slug              = $etab['slug'] ?? '';
$preinsActive      = !empty($etab['preinscription_active']);
$insActive         = !empty($etab['inscription_active']);
$preinscriptionUrl = url('orientation/' . rawurlencode($slug) . '/preinscription');
$inscriptionUrl    = url('orientation/' . rawurlencode($slug) . '/inscription');

// Carte : embed Google Maps configuré par l'admin prioritaire,
// sinon fallback GPS legacy, sinon recherche par adresse
$mapSrc   = '';
$mapHref  = '';
if (!empty($etab['map_embed_url'])) {
    $mapSrc  = $etab['map_embed_url'];
    $q       = urlencode(trim(($etab['adresse'] ?? '') . ' ' . $nom . ' ' . $ville));
    $mapHref = "https://maps.google.com/?q={$q}";
} elseif (!empty($etab['latitude']) && !empty($etab['longitude'])) {
    $lat = (float)$etab['latitude'];
    $lng = (float)$etab['longitude'];
    $mapSrc  = "https://maps.google.com/maps?q={$lat},{$lng}&output=embed";
    $mapHref = "https://maps.google.com/?q={$lat},{$lng}";
} elseif (!empty($etab['adresse'])) {
    $q = urlencode($etab['adresse'] . ' ' . $ville);
    $mapSrc  = "https://maps.google.com/maps?q={$q}&output=embed";
    $mapHref = "https://maps.google.com/?q={$q}";
}

// Prix à afficher dans section tarifs (min des formations ouvertes, ou prix_preinscription)
$prixMin = 0;
foreach ($formations as $f) {
    if ($f['statut'] === 'ouverte' && $f['prix'] > 0) {
        if ($prixMin === 0 || $f['prix'] < $prixMin) $prixMin = (int)$f['prix'];
    }
}
$prixInscription    = (int)($etab['prix_inscription'] ?? 0);
$prixPreinscription = (int)($etab['prix_preinscription'] ?? 0);
$fraisDossier       = (int)($etab['frais_dossier'] ?? 0);
// Frais de scolarité annuels par cycle × statut boursier.
// 0 = non renseigné : un cycle sans tarif plein n'est pas affiché, et un
// établissement à tarif unique (boursier laissé à 0) garde l'affichage
// historique — un seul montant, sans mention de statut.
$scolariteLicence           = (int)($etab['frais_scolarite'] ?? 0);
$scolariteLicenceBoursier   = (int)($etab['frais_scolarite_boursier'] ?? 0);
$scolariteMaster            = (int)($etab['frais_scolarite_master'] ?? 0);
$scolariteMasterBoursier    = (int)($etab['frais_scolarite_master_boursier'] ?? 0);
$fraisUniforme              = (int)($etab['frais_uniforme'] ?? 0);
$aScolarite                 = ($scolariteLicence > 0 || $scolariteMaster > 0);
?>
<!doctype html>
<html lang="fr" data-theme="light">
<head>
<meta charset="utf-8">
<title><?= e($nom) ?> — Connect'Academia · Orientation</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="<?= e($etab['sous_titre'] ?? "Découvrez {$nom} — formations, admission et candidature en ligne sur Connect'Academia.") ?>">
<link rel="stylesheet" href="<?= asset('orientation/site.css') ?>">
<style>
  :root { --brand: <?= e($couleur) ?>; }
</style>
</head>
<body>

<!-- ════════════════ HEADER ════════════════ -->
<header class="site-header">
  <nav class="nav wrap">
    <a class="brand-id" href="#top" aria-label="Accueil <?= e($nom) ?>">
      <div class="brand-logo">
        <?php if ($logoUrl): ?>
        <img src="<?= e($logoUrl) ?>" alt="Logo <?= e($nom) ?>">
        <?php else: ?>
        <div class="brand-initial"><?= e(strtoupper(mb_substr($nom, 0, 1, 'UTF-8'))) ?></div>
        <?php endif; ?>
      </div>
      <span class="brand-text">
        <strong><?= e($nom) ?></strong>
        <span><?= e($type) ?> · <?= e($ville) ?></span>
      </span>
    </a>

    <div class="nav-menu">
      <?php if (!empty($formations) || !empty($filieres)): ?><a href="#filieres">Formations</a><?php endif; ?>
      <a href="#tarifs">Admission</a>
      <?php if (!empty($actualites)): ?><a href="#blog">Actualités</a><?php endif; ?>
      <a href="#avis">Avis</a>
      <a href="#contact">Contact</a>
      <a class="nav-market" href="<?= url('/orientation') ?>" title="Explorer toutes les écoles sur Connect'Academia">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
        Toutes les écoles
      </a>
    </div>

    <div class="nav-cta">
      <a class="btn btn-primary btn-sm" href="<?= $preinsActive ? e($preinscriptionUrl) : ($insActive ? e($inscriptionUrl) : '#tarifs') ?>">Candidature
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
      </a>
      <button class="burger" aria-label="Menu">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
      </button>
    </div>
  </nav>
</header>

<!-- ════════════════ HERO ════════════════ -->
<section class="hero" id="top">
  <?php if (!empty($etab['date_limite_dossier'])): ?>
  <div class="float float-deadline">
    <small>Date limite dossier</small>
    <b><?= e(date('j M Y', strtotime($etab['date_limite_dossier']))) ?></b>
  </div>
  <?php endif; ?>

  <?php if ($logoUrl): ?>
  <div class="float float-logo">
    <img src="<?= e($logoUrl) ?>" alt="Logo <?= e($nom) ?>">
  </div>
  <?php endif; ?>

  <div class="hero-inner wrap">
    <?php if (!empty($etab['label_campagne'])): ?>
    <span class="eyebrow"><span class="pulse"></span><?= e($etab['label_campagne']) ?></span>
    <?php endif; ?>

    <h1 class="display" style="font-size:clamp(46px,8vw,80px)">
      <?php if (!empty($etab['titre_accroche'])): ?>
        <?= e($etab['titre_accroche']) ?>
      <?php else: ?>
        Bienvenue à <span class="hl"><?= e($nom) ?></span>.
      <?php endif; ?>
    </h1>

    <?php if (!empty($etab['sous_titre'])): ?>
    <p class="hero-sub"><?= nl2br(e($etab['sous_titre'])) ?></p>
    <?php endif; ?>

    <div class="hero-actions">
      <?php if (!empty($etab['preinscription_active'])): ?>
      <a class="btn btn-primary" href="<?= e($preinscriptionUrl) ?>">Préinscription gratuite
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
      </a>
      <?php endif; ?>
      <?php if (!empty($etab['inscription_active'])): ?>
      <a class="btn btn-outline" href="<?= e($inscriptionUrl) ?>">Inscription en ligne
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
      </a>
      <?php endif; ?>
      <a class="btn btn-market" href="<?= url('/orientation') ?>" title="Explorer toutes les écoles sur Connect'Academia">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
        Toutes les écoles
      </a>
      <?php if ($hasVideo): ?>
      <a class="btn btn-ghost" href="#video">
        <span class="play"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></span>
        Voir la présentation
      </a>
      <?php endif; ?>
    </div>

    <?php if (!empty($badges)): ?>
    <div class="trust">
      <?php foreach ($badges as $i => $badge): ?>
        <?php if ($i > 0): ?><span class="trust-sep"></span><?php endif; ?>
        <span class="trust-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4"/><path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/></svg>
          <?= e($badge) ?>
        </span>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- ════════════════ FILIÈRES ════════════════ -->
<?php if (!empty($formations) || !empty($filieres)): ?>
<section class="section filieres" id="filieres">
  <div class="wrap">
    <div class="section-head center">
      <span class="section-tag">Nos formations</span>
      <h2 class="display">Des parcours <span class="hl">d'excellence</span>.</h2>
      <?php if (!empty($formations)): ?>
      <p>Chaque formation est ouverte à la préinscription en ligne. Lance ta candidature en quelques minutes.</p>
      <?php else: ?>
      <p>Découvre les filières proposées et déplie chacune pour voir ses spécialités.</p>
      <?php endif; ?>
    </div>

    <?php if (!empty($formations)): ?>
    <div class="filiere-grid">
    <?php foreach ($formations as $f): ?>
      <?php
        $sf    = $statutFormation[$f['statut']] ?? ['label' => $f['statut'], 'class' => ''];
        $isOpen = $f['statut'] === 'ouverte';
      ?>
      <article class="filiere">
        <div class="filiere-top">
          <?php if (!empty($f['niveau'])): ?>
          <span class="filiere-niveau"><?= e($f['niveau']) ?></span>
          <?php endif; ?>
          <span class="filiere-statut <?= $sf['class'] ?>">
            <span class="dot"></span><?= $sf['label'] ?>
          </span>
        </div>
        <div class="filiere-ic">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 10 12 5 2 10l10 5 10-5z"/>
            <path d="M6 12v5c0 1 2.7 2.5 6 2.5s6-1.5 6-2.5v-5M22 10v6"/>
          </svg>
        </div>
        <?php $fDetailUrl = url('orientation/' . rawurlencode($etab['slug']) . '/formations/' . (int)$f['id']); ?>
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;">
          <h3><a href="<?= e($fDetailUrl) ?>" style="color:inherit;text-decoration:none;"><?= e($f['intitule']) ?></a></h3>
          <a href="<?= e($fDetailUrl) ?>" title="Voir le détail de cette formation" aria-label="Voir le détail de cette formation"
             style="display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:999px;background:var(--brand-tint);color:var(--brand);border:1px solid var(--brand-line);flex:none;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </a>
        </div>
        <div class="filiere-meta">
          <?php if (!empty($f['duree_texte'])): ?>
          <div class="row">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            <?= e($f['duree_texte']) ?>
          </div>
          <?php endif; ?>
          <?php if (!empty($f['places_texte'])): ?>
          <div class="row">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/></svg>
            <?= e($f['places_texte']) ?>
          </div>
          <?php endif; ?>
        </div>
        <div class="filiere-foot">
          <?php if ((int)$f['prix'] > 0): ?>
          <div class="filiere-price">
            <small>À partir de</small>
            <b><?= number_format((int)$f['prix'], 0, ',', ' ') ?><span> <?= e($f['prix_unite'] ?: 'FCFA/an') ?></span></b>
          </div>
          <?php else: ?>
          <div></div>
          <?php endif; ?>
          <?php if ($isOpen): ?>
          <a class="btn btn-primary btn-sm" href="<?= $preinsActive ? e($preinscriptionUrl) : '#tarifs' ?>">
            Préinscription
            <svg class="filiere-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
          </a>
          <?php else: ?>
          <a class="btn btn-ghost btn-sm" href="#contact">M'avertir</a>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($filieres)): ?>
    <div class="fil-spec"<?= !empty($formations) ? ' data-has-formations="1"' : '' ?>>
      <div class="fil-spec-head">
        <h3>Filières &amp; spécialités</h3>
        <p>Clique sur une filière pour dérouler ses spécialités.</p>
      </div>
      <div class="fil-acc">
        <?php foreach ($filieres as $fi => $fil): ?>
          <?php
            $specs   = $fil['specialites'] ?? [];
            $hasSpec = !empty($specs);
            $nbSpec  = count($specs);
            $panelId = 'fil-panel-' . $fi;
          ?>
          <div class="fil-item<?= $hasSpec ? '' : ' is-solo' ?>">
            <button type="button" class="fil-trigger" aria-expanded="false"
                    <?= $hasSpec ? 'aria-controls="' . $panelId . '"' : 'disabled' ?>>
              <span class="fil-name"><?= e($fil['nom']) ?></span>
              <span class="fil-aside">
                <?php if ($hasSpec): ?>
                  <span class="fil-count"><?= $nbSpec ?> spécialité<?= $nbSpec > 1 ? 's' : '' ?></span>
                  <svg class="fil-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                       stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M6 9l6 6 6-6"/>
                  </svg>
                <?php else: ?>
                  <span class="fil-count fil-count-solo">Sans spécialité</span>
                <?php endif; ?>
              </span>
            </button>
            <?php if ($hasSpec): ?>
            <div class="fil-panel" id="<?= $panelId ?>" hidden>
              <ul class="fil-list">
                <?php foreach ($specs as $spec): ?>
                <li>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
                       stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                  <span><?= e($spec) ?></span>
                </li>
                <?php endforeach; ?>
              </ul>
            </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<!-- ════════════════ VIDÉO ════════════════ -->
<?php if ($hasVideo || !empty($imageUrls)): ?>
<section class="section video-sec" id="video">
  <div class="wrap">
    <div class="section-head center">
      <span class="section-tag">Visite guidée</span>
      <h2 class="display">Découvrez <span class="hl"><?= e($nom) ?></span>.</h2>
      <?php if (!empty($etab['description'])): ?>
      <p>Une plongée immersive dans notre établissement.</p>
      <?php endif; ?>
    </div>
    <div class="video-stage" id="videoStage">
      <?php if ($videoFileUrl): ?>
        <video controls preload="metadata" playsinline
               <?= !empty($imageUrls[0]) ? 'poster="' . e($imageUrls[0]) . '"' : '' ?>
               style="width:100%;height:100%;object-fit:contain;background:#000;">
          <source src="<?= e($videoFileUrl) ?>">
          Ton navigateur ne supporte pas la lecture vidéo.
        </video>
      <?php elseif ($ytId): ?>
        <iframe src="https://www.youtube.com/embed/<?= e($ytId) ?>"
                title="Présentation <?= e($nom) ?>"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen></iframe>
      <?php elseif (!empty($imageUrls[0])): ?>
        <img src="<?= e($imageUrls[0]) ?>" alt="Campus <?= e($nom) ?>">
        <div class="video-play"><span>
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
        </span></div>
      <?php else: ?>
        <div class="video-placeholder"></div>
        <div class="video-play"><span>
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
        </span></div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ════════════════ GALERIE CAMPUS ════════════════ -->
<?php if (count($imageUrls) > 0): ?>
<section class="section galerie-sec" id="galerie">
  <div class="wrap">
    <div class="section-head center">
      <span class="section-tag">Galerie</span>
      <h2 class="display">Le campus <span class="hl">en images</span>.</h2>
    </div>
    <div class="galerie-grid galerie-n<?= min(count($imageUrls), 10) ?>">
      <?php foreach (array_slice($imageUrls, 0, 10) as $i => $img): ?>
      <button type="button" class="galerie-item" onclick="ouvrirLightbox(<?= $i ?>)" aria-label="Agrandir la photo <?= $i + 1 ?>">
        <img src="<?= e($img) ?>" alt="Campus <?= e($nom) ?> — photo <?= $i + 1 ?>" loading="lazy">
      </button>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Lightbox -->
<div class="galerie-lightbox" id="galerieLightbox" role="dialog" aria-modal="true" aria-label="Galerie photos">
  <button type="button" class="lb-close" onclick="fermerLightbox()" aria-label="Fermer">&times;</button>
  <button type="button" class="lb-nav lb-prev" onclick="naviguerLightbox(-1)" aria-label="Photo précédente">&#8249;</button>
  <img id="lightboxImg" src="" alt="Photo campus agrandie">
  <button type="button" class="lb-nav lb-next" onclick="naviguerLightbox(1)" aria-label="Photo suivante">&#8250;</button>
</div>
<?php endif; ?>

<!-- ════════════════ QUI SOMMES-NOUS ════════════════ -->
<section class="section about" id="about">
  <div class="wrap about-grid">
    <div class="about-visual">
      <div class="about-img-wrap">
        <?php if (!empty($imageUrls[0])): ?>
        <img src="<?= e($imageUrls[0]) ?>" alt="Campus <?= e($nom) ?>">
        <?php elseif (!empty($imageUrls[1])): ?>
        <img src="<?= e($imageUrls[1]) ?>" alt="Campus <?= e($nom) ?>">
        <?php endif; ?>
      </div>
      <?php if (!empty($etab['citation_texte'])): ?>
      <div class="about-quote">
        <div class="mark">"</div>
        <p><?= e($etab['citation_texte']) ?></p>
        <?php if (!empty($etab['citation_auteur'])): ?>
        <div class="who">
          <strong><?= e($etab['citation_auteur']) ?></strong>
          <?php if (!empty($etab['citation_fonction'])): ?>
          <span><?= e($etab['citation_fonction']) ?></span>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <div class="about-body">
      <span class="section-tag">Qui sommes-nous ?</span>
      <h2 class="display" style="font-size:clamp(30px,3.4vw,46px);margin:14px 0 22px;">
        <?= e($nom) ?> <span class="hl">à <?= e($ville) ?></span>.
      </h2>
      <?php if (!empty($etab['description'])): ?>
      <p><?= nl2br(e($etab['description'])) ?></p>
      <?php endif; ?>

      <?php if (!empty($atouts)): ?>
      <ul class="about-list">
        <?php foreach ($atouts as $atout): ?>
        <li>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="10"/></svg>
          <?= e($atout) ?>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>

      <?php if (!empty($statistiques)): ?>
      <div class="about-stats">
        <?php foreach ($statistiques as $stat): ?>
        <div class="about-stat">
          <b><?= e($stat['valeur'] ?? '') ?></b>
          <span><?= e($stat['label'] ?? '') ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ════════════════ BLOG / ACTUALITÉS ════════════════ -->
<?php if (!empty($actualites)): ?>
<section class="section blog" id="blog">
  <div class="wrap">
    <div class="blog-head">
      <div class="section-head">
        <span class="section-tag">Le blog</span>
        <h2 class="display">La vie du campus, <span class="hl">en direct</span>.</h2>
        <p>Actualités, événements et temps forts de l'établissement.</p>
      </div>
    </div>

    <div class="blog-grid">
    <?php foreach ($actualites as $idx => $actu): ?>
      <?php
        $featured = ($idx === 0 && count($actualites) > 1);
        $actuUrl  = url('orientation/' . rawurlencode($slug) . '/actualite/' . (int)$actu['id']);
      ?>
      <a class="post<?= $featured ? ' featured' : '' ?>" href="<?= e($actuUrl) ?>">
        <div class="post-media">
          <?php if (!empty($actu['categorie'])): ?>
          <span class="post-cat"><?= e($actu['categorie']) ?></span>
          <?php endif; ?>
          <?php if (!empty($actu['image_url'])): ?>
          <img src="<?= e($actu['image_url']) ?>" alt="<?= e($actu['titre'] ?? '') ?>" loading="lazy">
          <?php else: ?>
          <div class="post-media-placeholder">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 3v18"/></svg>
          </div>
          <?php endif; ?>
        </div>
        <div class="post-body">
          <?php if ($featured): ?>
          <span class="post-flag">Article à la une</span>
          <?php endif; ?>
          <?php if (!empty($actu['date_publication'])): ?>
          <span class="post-date">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
            <?= e(date('j M Y', strtotime($actu['date_publication']))) ?>
          </span>
          <?php endif; ?>
          <h3><?= e($actu['titre'] ?? '') ?></h3>
          <?php if (!empty($actu['extrait'])): ?>
          <p><?= e($actu['extrait']) ?></p>
          <?php endif; ?>
          <span class="post-link">Lire
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
          </span>
        </div>
      </a>
    <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ════════════════ AVIS (5 étoiles, façon Google Reviews) ════════════════ -->
<style>
/* ── Section avis ─────────────────────────────────────────────────── */
.avis-sec { background: var(--paper-2); }
.avis-layout { display: grid; grid-template-columns: 340px 1fr; gap: 28px; align-items: start; margin-top: 12px; }

.av-star-color { color: #F6B100; }
.av-stars { position: relative; display: inline-block; font-size: 20px; line-height: 1; letter-spacing: 2px; color: rgba(54,44,32,0.18); }
.av-stars::before { content: '★★★★★'; }
.av-stars-fill { position: absolute; top: 0; left: 0; overflow: hidden; white-space: nowrap; color: #F6B100; }
.av-stars-fill::before { content: '★★★★★'; }
.av-stars.small { font-size: 15px; letter-spacing: 1.5px; }

.av-resume {
  background: var(--card); border: 1px solid var(--border-subtle);
  border-radius: 24px; padding: 28px; box-shadow: var(--shadow-md);
  position: sticky; top: calc(var(--header-h) + 16px);
}
.av-resume-note { font-size: 54px; font-weight: 800; letter-spacing: -0.04em; color: var(--ink); line-height: 1; }
.av-resume-note .sur { font-size: 20px; font-weight: 600; color: var(--ink-3); }
.av-resume-count { font-size: 13px; color: var(--ink-2); margin-top: 10px; font-weight: 500; }
.av-dist { margin-top: 22px; display: flex; flex-direction: column; gap: 8px; }
.av-dist-row { display: flex; align-items: center; gap: 10px; font-size: 12.5px; color: var(--ink-2); }
.av-dist-row .n-etoiles { width: 34px; font-weight: 600; white-space: nowrap; }
.av-dist-bar { flex: 1; height: 8px; border-radius: 999px; background: rgba(54,44,32,0.09); overflow: hidden; }
.av-dist-fill { height: 100%; border-radius: 999px; background: #F6B100; }
.av-dist-row .n-avis { width: 26px; text-align: right; font-weight: 600; color: var(--ink-3); font-variant-numeric: tabular-nums; }

/* ── Widget "noter" ───────────────────────────────────────────────── */
.av-form-card {
  background: var(--card); border: 1px solid var(--border-subtle);
  border-radius: 24px; padding: 26px 28px; box-shadow: var(--shadow-sm);
  margin-bottom: 22px;
}
.av-form-card h3 { font-size: 17px; font-weight: 700; color: var(--ink); margin: 0 0 4px; }
.av-form-card .sous { font-size: 13px; color: var(--ink-3); margin: 0 0 16px; }
.av-picker { display: flex; gap: 4px; margin-bottom: 14px; }
.av-picker button {
  background: none; border: none; cursor: pointer; padding: 2px;
  font-size: 34px; line-height: 1; color: rgba(54,44,32,0.20);
  transition: color 120ms var(--ease), transform 120ms var(--ease);
}
.av-picker button.lit { color: #F6B100; }
.av-picker button:hover { transform: scale(1.15); }
.av-picker button:focus-visible { outline: 2px solid var(--brand); outline-offset: 3px; border-radius: 6px; }
.av-textarea {
  width: 100%; min-height: 88px; resize: vertical;
  font-family: inherit; font-size: 14px; color: var(--ink);
  padding: 13px 15px; border-radius: 14px;
  border: 1.5px solid var(--border-strong); background: var(--paper);
  outline: none; transition: border-color 150ms var(--ease);
  box-sizing: border-box;
}
.av-textarea:focus { border-color: var(--brand); }
.av-form-foot { display: flex; align-items: center; gap: 14px; margin-top: 14px; flex-wrap: wrap; }
.av-submit {
  font-family: inherit; font-weight: 700; font-size: 14px; cursor: pointer;
  padding: 12px 24px; border: none; border-radius: 999px;
  background: var(--brand); color: #fff; box-shadow: 0 6px 18px var(--brand-glow);
  transition: transform 160ms var(--ease), box-shadow 160ms var(--ease), opacity 160ms;
}
.av-submit:hover { transform: translateY(-2px); }
.av-submit:disabled { opacity: .5; cursor: not-allowed; transform: none; }
.av-delete {
  background: none; border: none; cursor: pointer; font-family: inherit;
  font-size: 13px; font-weight: 600; color: #b91c1c; text-decoration: underline;
}
.av-feedback { font-size: 13px; font-weight: 600; margin-top: 10px; display: none; }
.av-feedback.ok  { color: #15803d; display: block; }
.av-feedback.err { color: #b91c1c; display: block; }
.av-login-cta {
  display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;
}
.av-login-cta p { font-size: 14px; color: var(--ink-2); margin: 0; }
.av-login-cta .actions { display: flex; gap: 10px; flex-wrap: wrap; }

/* ── Liste des avis ───────────────────────────────────────────────── */
.av-list { display: flex; flex-direction: column; gap: 14px; }
.av-item {
  background: var(--card); border: 1px solid var(--border-subtle);
  border-radius: 20px; padding: 20px 22px; box-shadow: var(--shadow-sm);
}
.av-item-head { display: flex; align-items: center; gap: 12px; margin-bottom: 8px; }
.av-avatar {
  width: 38px; height: 38px; border-radius: 999px; flex: none;
  display: flex; align-items: center; justify-content: center;
  background: var(--brand-tint-2); color: var(--brand);
  font-size: 15px; font-weight: 800;
}
.av-item-auteur { font-size: 14px; font-weight: 700; color: var(--ink); }
.av-item-date { font-size: 12px; color: var(--ink-3); }
.av-item-txt { font-size: 14px; color: var(--ink-2); line-height: 1.65; margin: 8px 0 0; white-space: pre-line; overflow-wrap: anywhere; }
.av-more {
  margin-top: 18px; width: 100%; cursor: pointer;
  font-family: inherit; font-size: 14px; font-weight: 700;
  padding: 13px; border-radius: 999px; color: var(--brand);
  background: var(--card); border: 1.5px solid var(--brand);
  transition: background 150ms var(--ease);
}
.av-more:hover { background: var(--brand-tint); }
.av-vide {
  text-align: center; padding: 36px 20px; color: var(--ink-3); font-size: 14px;
  background: var(--card); border: 1.5px dashed var(--border-strong); border-radius: 20px;
}

/* ── Commentaires longs : troncature + « Lire la suite » ──────────── */
.av-item-txt.clamped {
  display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical;
  overflow: hidden;
}
.av-lire-suite {
  background: none; border: none; cursor: pointer; padding: 0; margin-top: 6px;
  font-family: inherit; font-size: 13px; font-weight: 700; color: var(--brand);
}
.av-lire-suite:hover { text-decoration: underline; }

/* ── Modal « Tous les avis » ──────────────────────────────────────── */
.av-modal-overlay {
  position: fixed; inset: 0; z-index: 1200;
  background: rgba(20, 16, 10, 0.55); backdrop-filter: blur(4px);
  display: flex; align-items: center; justify-content: center; padding: 20px;
}
.av-modal-overlay[hidden] { display: none; }
.av-modal {
  background: var(--paper); border-radius: 24px; box-shadow: var(--shadow-md);
  width: min(680px, 100%); max-height: min(82vh, 820px);
  display: flex; flex-direction: column; overflow: hidden;
}
.av-modal-head {
  display: flex; align-items: flex-start; justify-content: space-between; gap: 16px;
  padding: 22px 26px 18px; border-bottom: 1px solid var(--border-subtle); flex: none;
}
.av-modal-head h3 { font-size: 18px; font-weight: 800; color: var(--ink); margin: 0 0 6px; }
.av-modal-agg { display: flex; align-items: center; gap: 10px; }
.av-modal-note { font-size: 22px; font-weight: 800; color: var(--ink); }
.av-modal-count { font-size: 13px; color: var(--ink-3); font-weight: 600; }
.av-modal-close {
  background: var(--card); border: 1px solid var(--border-subtle); cursor: pointer;
  width: 38px; height: 38px; border-radius: 999px; flex: none;
  font-size: 22px; line-height: 1; color: var(--ink-2);
  display: flex; align-items: center; justify-content: center;
  transition: background 150ms var(--ease);
}
.av-modal-close:hover { background: var(--brand-tint); color: var(--brand); }
.av-modal-body { overflow-y: auto; padding: 20px 26px 26px; overscroll-behavior: contain; }
.av-modal-loader, .av-modal-fin {
  text-align: center; padding: 18px; font-size: 13px; font-weight: 600; color: var(--ink-3);
}
body.av-modal-open { overflow: hidden; }
@media (max-width: 560px) {
  .av-modal-overlay { padding: 0; align-items: flex-end; }
  .av-modal { width: 100%; max-height: 92vh; border-radius: 24px 24px 0 0; }
}

@media (max-width: 880px) {
  .avis-layout { grid-template-columns: 1fr; }
  .av-resume { position: static; }
}
</style>
<section class="section avis-sec" id="avis">
  <div class="wrap">
    <div class="section-head center">
      <span class="section-tag">Avis des étudiants</span>
      <h2 class="display">Ce que la communauté <span class="hl">en pense</span>.</h2>
      <p>Note <?= e($nom) ?> de 1 à 5 étoiles — ton avis aide les futurs candidats à choisir.</p>
    </div>

    <div class="avis-layout">
      <!-- Résumé : moyenne + répartition (façon fiche Google) -->
      <aside class="av-resume" aria-label="Résumé des avis">
        <div class="av-resume-note" id="avNoteMoyenne"><?= number_format((float)$avisAgg['note_moyenne'], 1, ',', ' ') ?><span class="sur">/5</span></div>
        <div style="margin-top:10px;">
          <span class="av-stars" aria-hidden="true"><span class="av-stars-fill" id="avStarsFill" style="width:<?= round((float)$avisAgg['note_moyenne'] / 5 * 100, 1) ?>%;"></span></span>
        </div>
        <div class="av-resume-count" id="avCount">
          <?= (int)$avisAgg['nb_avis'] ?> avis publié<?= (int)$avisAgg['nb_avis'] > 1 ? 's' : '' ?>
        </div>
        <div class="av-dist">
          <?php foreach ($avisDist as $etoiles => $n): ?>
          <div class="av-dist-row">
            <span class="n-etoiles"><?= (int)$etoiles ?> <span class="av-star-color">★</span></span>
            <div class="av-dist-bar">
              <div class="av-dist-fill" style="width:<?= (int)$avisAgg['nb_avis'] > 0 ? round($n / (int)$avisAgg['nb_avis'] * 100, 1) : 0 ?>%;"></div>
            </div>
            <span class="n-avis"><?= (int)$n ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </aside>

      <div>
        <!-- Déposer / modifier son avis -->
        <?php if ($peutNoter): ?>
        <div class="av-form-card">
          <h3><?= $monAvis ? 'Ton avis sur cet établissement' : 'Donne ton avis' ?></h3>
          <p class="sous"><?= $monAvis ? 'Tu peux modifier ta note ou ton commentaire à tout moment.' : 'Sélectionne un nombre d\'étoiles, le commentaire est facultatif.' ?></p>
          <div class="av-picker" id="avPicker" role="radiogroup" aria-label="Ta note sur 5 étoiles">
            <?php for ($i = 1; $i <= 5; $i++): ?>
            <button type="button" data-note="<?= $i ?>" role="radio"
                    aria-checked="<?= $monAvis && (int)$monAvis['note'] === $i ? 'true' : 'false' ?>"
                    aria-label="<?= $i ?> étoile<?= $i > 1 ? 's' : '' ?>">★</button>
            <?php endfor; ?>
          </div>
          <textarea id="avCommentaire" class="av-textarea" maxlength="2000"
                    placeholder="Partage ton expérience : enseignement, campus, ambiance… (facultatif)"><?= e($monAvis['commentaire'] ?? '') ?></textarea>
          <div class="av-form-foot">
            <button type="button" class="av-submit" id="avSubmit" disabled>
              <?= $monAvis ? 'Mettre à jour mon avis' : 'Publier mon avis' ?>
            </button>
            <?php if ($monAvis): ?>
            <button type="button" class="av-delete" id="avDelete">Supprimer mon avis</button>
            <?php endif; ?>
          </div>
          <div class="av-feedback" id="avFeedback" role="status"></div>
        </div>
        <?php elseif ($avisLogged): ?>
        <div class="av-form-card">
          <p style="font-size:14px;color:var(--ink-2);margin:0;">Les comptes gestionnaire et administrateur ne peuvent pas déposer d'avis.</p>
        </div>
        <?php else: ?>
        <div class="av-form-card av-login-cta">
          <p><strong>Connecte-toi pour noter cet établissement.</strong><br>
             Ta note (1 à 5 étoiles) est publiée avec ton prénom, comme sur Google.</p>
          <div class="actions">
            <a class="btn btn-primary btn-sm" href="<?= url('/auth/connexion') ?>">Se connecter</a>
            <a class="btn btn-ghost btn-sm" href="<?= url('/orientation/compte') ?>">Créer un compte express</a>
          </div>
        </div>
        <?php endif; ?>

        <!-- Commentaires repliés par défaut : bouton → modal paginée -->
        <div class="av-list" id="avList" hidden></div>
        <?php if ((int)$avisAgg['nb_avis'] === 0): ?>
        <div class="av-vide" id="avListEmpty">
          Aucun avis pour le moment — sois le premier à partager ton expérience !
        </div>
        <?php else: ?>
        <button type="button" class="av-more" id="avMore">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;margin-right:6px;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          Voir les commentaires (<?= (int)$avisAgg['nb_avis'] ?>)
        </button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<!-- Modal « Tous les avis » — liste complète paginée, façon fiche Google -->
<div class="av-modal-overlay" id="avModalOverlay" hidden>
  <div class="av-modal" role="dialog" aria-modal="true" aria-labelledby="avModalTitle">
    <div class="av-modal-head">
      <div>
        <h3 id="avModalTitle">Avis sur <?= e($nom) ?></h3>
        <div class="av-modal-agg">
          <span class="av-modal-note"><?= number_format((float)$avisAgg['note_moyenne'], 1, ',', ' ') ?></span>
          <span class="av-stars small" aria-hidden="true"><span class="av-stars-fill" style="width:<?= round((float)$avisAgg['note_moyenne'] / 5 * 100, 1) ?>%;"></span></span>
          <span class="av-modal-count"><?= (int)$avisAgg['nb_avis'] ?> avis</span>
        </div>
      </div>
      <button type="button" class="av-modal-close" id="avModalClose" aria-label="Fermer">&times;</button>
    </div>
    <div class="av-modal-body" id="avModalBody">
      <div class="av-list" id="avModalList"></div>
      <div class="av-modal-loader" id="avModalLoader" hidden>Chargement…</div>
      <div class="av-modal-fin" id="avModalFin" hidden>Vous avez lu tous les avis.</div>
    </div>
  </div>
</div>

<!-- ════════════════ TARIFS ════════════════ -->
<section class="section tarifs" id="tarifs">
  <div class="wrap">
    <div class="section-head center">
      <span class="section-tag">Scolarité &amp; admission</span>
      <h2 class="display">Des frais clairs, <span class="hl">un paiement simple</span>.</h2>
    </div>

    <div class="tarif-card">
      <div class="tarif-left">
        <span class="badge">Scolarité <?= $annee ?> — <?= $annee + 1 ?></span>
        <?php if ($prixMin > 0): ?>
        <div class="tarif-price">
          <div class="small">À partir de</div>
          <span class="big"><?= number_format($prixMin, 0, ',', ' ') ?><span class="unit"> FCFA / an</span></span>
        </div>
        <?php endif; ?>

        <?php if ($aScolarite): ?>
        <div class="tarif-cycles">
          <div class="tarif-cycles-title">Frais de scolarité annuels</div>
          <div class="cycle-grid">
            <?php if ($scolariteLicence > 0): ?>
            <div class="cycle-card">
              <div class="cy-label"><span class="cy-dot"></span>Cycle Licence</div>
              <?php if ($scolariteLicenceBoursier > 0): ?>
              <div class="cy-tiers">
                <div class="cy-tier">
                  <span class="cy-tier-label">Non boursier</span>
                  <span class="cy-amount"><?= number_format($scolariteLicence, 0, ',', ' ') ?>
                    <span class="cy-unit">FCFA / an</span>
                  </span>
                </div>
                <div class="cy-tier">
                  <span class="cy-tier-label">Boursier</span>
                  <span class="cy-amount"><?= number_format($scolariteLicenceBoursier, 0, ',', ' ') ?>
                    <span class="cy-unit">FCFA / an</span>
                  </span>
                </div>
              </div>
              <?php else: ?>
              <span class="cy-amount"><?= number_format($scolariteLicence, 0, ',', ' ') ?>
                <span class="cy-unit">FCFA / an</span>
              </span>
              <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if ($scolariteMaster > 0): ?>
            <div class="cycle-card">
              <div class="cy-label"><span class="cy-dot cy-dot-2"></span>Cycle Master</div>
              <?php if ($scolariteMasterBoursier > 0): ?>
              <div class="cy-tiers">
                <div class="cy-tier">
                  <span class="cy-tier-label">Non boursier</span>
                  <span class="cy-amount"><?= number_format($scolariteMaster, 0, ',', ' ') ?>
                    <span class="cy-unit">FCFA / an</span>
                  </span>
                </div>
                <div class="cy-tier">
                  <span class="cy-tier-label">Boursier</span>
                  <span class="cy-amount"><?= number_format($scolariteMasterBoursier, 0, ',', ' ') ?>
                    <span class="cy-unit">FCFA / an</span>
                  </span>
                </div>
              </div>
              <?php else: ?>
              <span class="cy-amount"><?= number_format($scolariteMaster, 0, ',', ' ') ?>
                <span class="cy-unit">FCFA / an</span>
              </span>
              <?php endif; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <ul class="tarif-check">
          <?php if ($prixPreinscription > 0): ?>
          <li>
            <span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg></span>
            Préinscription : <?= number_format($prixPreinscription, 0, ',', ' ') ?> FCFA
          </li>
          <?php else: ?>
          <li>
            <span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg></span>
            Préinscription gratuite
          </li>
          <?php endif; ?>
          <?php if ($prixInscription > 0): ?>
          <li>
            <span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg></span>
            Frais d'inscription : <?= number_format($prixInscription, 0, ',', ' ') ?> FCFA
          </li>
          <?php endif; ?>
          <?php if ($fraisDossier > 0): ?>
          <li>
            <span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg></span>
            Frais administratifs : <?= number_format($fraisDossier, 0, ',', ' ') ?> FCFA
          </li>
          <?php endif; ?>
          <?php if ($fraisUniforme > 0): ?>
          <li>
            <span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg></span>
            Frais d'uniforme : <?= number_format($fraisUniforme, 0, ',', ' ') ?> FCFA
          </li>
          <?php endif; ?>
          <?php if (!empty($etab['genere_recepisse'])): ?>
          <li>
            <span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg></span>
            Attestation de préinscription générée automatiquement
          </li>
          <?php endif; ?>
          <li>
            <span class="ck"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg></span>
            Paiement sécurisé en ligne via Connect'Academia
          </li>
        </ul>
        <div class="tarif-actions">
          <?php if (!empty($etab['preinscription_active'])): ?>
          <a class="btn btn-primary" href="<?= e($preinscriptionUrl) ?>">Préinscription en ligne
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
          </a>
          <?php endif; ?>
          <?php if (!empty($etab['inscription_active'])): ?>
          <a class="btn <?= !empty($etab['preinscription_active']) ? 'btn-outline' : 'btn-primary' ?>" href="<?= e($inscriptionUrl) ?>">Inscription en ligne
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
          </a>
          <?php endif; ?>
          <a class="btn btn-ghost" href="#contact">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            Nous contacter
          </a>
        </div>
      </div>

      <div class="tarif-right">
        <div class="pay-title">Modes de paiement</div>
        <div class="pay-list">
          <?php if (!empty($infosPaiement)): ?>
            <?php foreach ($infosPaiement as $mode): ?>
            <div class="pay">
              <div class="pay-ic">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/></svg>
              </div>
              <div class="pay-txt"><strong><?= e($mode) ?></strong></div>
            </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="pay">
              <div class="pay-ic">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/></svg>
              </div>
              <div class="pay-txt"><strong>Espèces</strong><span>À la comptabilité de l'établissement</span></div>
            </div>
            <div class="pay">
              <div class="pay-ic">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="2" width="12" height="20" rx="3"/><path d="M11 18h2"/></svg>
              </div>
              <div class="pay-txt"><strong>Mobile Money</strong><span>Airtel Money · Moov Money</span></div>
            </div>
            <div class="pay">
              <div class="pay-ic">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.5 9a2.5 2.5 0 0 1 5 0c0 1.7-2.5 2-2.5 4M12 17h.01"/></svg>
              </div>
              <div class="pay-txt"><strong>Paiement en ligne sécurisé</strong><span>Via Connect'Academia (MoneyFusion)</span></div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ════════════════ LOCALISATION / CONTACT ════════════════ -->
<section class="contact" id="contact">
  <div class="contact-grid">
    <?php if ($mapSrc): ?>
    <div class="contact-map">
      <a class="map-open" href="<?= e($mapHref) ?>" target="_blank" rel="noopener">
        Ouvrir dans Maps
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14 21 3"/></svg>
      </a>
      <iframe title="Localisation <?= e($nom) ?>" loading="lazy" src="<?= e($mapSrc) ?>"></iframe>
    </div>
    <?php else: ?>
    <div class="contact-map" style="background:var(--brand-tint);display:flex;align-items:center;justify-content:center;">
      <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" style="color:var(--brand);opacity:0.3"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
    </div>
    <?php endif; ?>

    <div class="contact-info">
      <h2 class="display">Nous contacter</h2>
      <?php if (!empty($etab['adresse'])): ?>
      <div class="contact-row">
        <div class="contact-ic">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
        </div>
        <div class="ct">
          <small>Adresse</small>
          <span><?= e($etab['adresse']) ?>, <?= e($ville) ?></span>
        </div>
      </div>
      <?php endif; ?>
      <?php if (!empty($etab['email_contact'])): ?>
      <div class="contact-row">
        <div class="contact-ic">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 6 10-6"/></svg>
        </div>
        <div class="ct">
          <small>Email</small>
          <a href="mailto:<?= e($etab['email_contact']) ?>"><?= e($etab['email_contact']) ?></a>
        </div>
      </div>
      <?php endif; ?>
      <?php if (!empty($etab['telephone'])): ?>
      <div class="contact-row">
        <div class="contact-ic">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
        </div>
        <div class="ct">
          <small>Téléphone</small>
          <a href="tel:<?= e($etab['telephone']) ?>"><?= e($etab['telephone']) ?></a>
        </div>
      </div>
      <?php endif; ?>
      <?php if (!empty($etab['whatsapp'])): ?>
      <div class="contact-row">
        <div class="contact-ic">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
        </div>
        <div class="ct">
          <small>WhatsApp</small>
          <a href="https://wa.me/<?= e(preg_replace('/\D/', '', $etab['whatsapp'])) ?>"><?= e($etab['whatsapp']) ?></a>
        </div>
      </div>
      <?php endif; ?>
      <?php if (empty($etab['adresse']) && empty($etab['email_contact']) && empty($etab['telephone'])): ?>
      <div class="contact-row">
        <div class="contact-ic">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
        </div>
        <div class="ct"><small>Localisation</small><span><?= e($ville) ?>, Gabon</span></div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ════════════════ FOOTER ════════════════ -->
<footer class="site-footer">
  <div class="wrap">
    <div class="foot-grid">
      <div>
        <div class="foot-brand">
          <?php if ($logoUrl): ?>
          <img src="<?= e($logoUrl) ?>" alt="Logo <?= e($nom) ?>">
          <?php else: ?>
          <div class="foot-initial"><?= e(strtoupper(mb_substr($nom, 0, 1, 'UTF-8'))) ?></div>
          <?php endif; ?>
          <strong><?= e($nom) ?></strong>
        </div>
        <p class="foot-about">
          <?= e($type) ?> <?php if ($ville): ?>à <?= e($ville) ?><?php endif ?>.
          <?php if (!empty($etab['preinscription_active'])): ?>Candidatures <?= $annee ?> — <?= $annee + 1 ?> ouvertes en ligne.<?php endif; ?>
        </p>
      </div>
      <div class="foot-col">
        <h4>Établissement</h4>
        <?php if (!empty($formations) || !empty($filieres)): ?><a href="#filieres">Nos formations</a><?php endif; ?>
        <a href="#about">Qui sommes-nous</a>
        <a href="#tarifs">Admission &amp; tarifs</a>
        <a href="#avis">Avis des étudiants</a>
        <?php if (!empty($actualites)): ?><a href="#blog">Le blog</a><?php endif; ?>
        <?php if ($hasVideo): ?><a href="#video">Présentation</a><?php endif; ?>
        <?php if (!empty($imageUrls)): ?><a href="#galerie">Galerie</a><?php endif; ?>
      </div>
      <div class="foot-col">
        <h4>Candidature</h4>
        <?php if (!empty($etab['preinscription_active'])): ?>
        <a href="<?= e($preinscriptionUrl) ?>">Préinscription en ligne</a>
        <?php endif; ?>
        <?php if (!empty($etab['inscription_active'])): ?>
        <a href="<?= e($inscriptionUrl) ?>">Inscription en ligne</a>
        <?php endif; ?>
        <a href="#tarifs">Frais de scolarité</a>
        <a href="#contact">Nous contacter</a>
      </div>
    </div>
    <div class="foot-bottom">
      <span>© <?= $annee ?> <?= e($nom) ?> — Tous droits réservés.</span>
      <span class="powered">
        <span class="dot"></span>
        Propulsé par <b>Connect'Academia</b> · Orientation
      </span>
    </div>
  </div>
</footer>

<script src="<?= asset('orientation/site.js') ?>"></script>
<script>
/* ── Accordéon Filières → Spécialités ──────────────────────────────── */
(function () {
  var acc = document.querySelector('.fil-acc');
  if (!acc) return;

  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function close(item) {
    var btn   = item.querySelector('.fil-trigger');
    var panel = item.querySelector('.fil-panel');
    if (!btn || !panel) return;
    btn.setAttribute('aria-expanded', 'false');
    if (reduce) {
      panel.hidden = true;
      panel.style.height = '';
    } else {
      panel.style.height = panel.scrollHeight + 'px';
      requestAnimationFrame(function () { panel.style.height = '0px'; });
      panel.addEventListener('transitionend', function te(e) {
        if (e.propertyName !== 'height') return;
        panel.hidden = true;
        panel.style.height = '';
        panel.removeEventListener('transitionend', te);
      });
    }
    item.classList.remove('is-open');
  }

  function open(item) {
    var btn   = item.querySelector('.fil-trigger');
    var panel = item.querySelector('.fil-panel');
    if (!btn || !panel) return;
    btn.setAttribute('aria-expanded', 'true');
    item.classList.add('is-open');
    panel.hidden = false;
    if (reduce) { panel.style.height = 'auto'; return; }
    panel.style.height = '0px';
    requestAnimationFrame(function () { panel.style.height = panel.scrollHeight + 'px'; });
    panel.addEventListener('transitionend', function te(e) {
      if (e.propertyName !== 'height') return;
      panel.style.height = 'auto';
      panel.removeEventListener('transitionend', te);
    });
  }

  acc.addEventListener('click', function (e) {
    var btn = e.target.closest('.fil-trigger');
    if (!btn || btn.disabled || !acc.contains(btn)) return;
    var item = btn.closest('.fil-item');
    if (!item) return;
    if (item.classList.contains('is-open')) {
      close(item);
    } else {
      // Un seul panneau ouvert à la fois
      acc.querySelectorAll('.fil-item.is-open').forEach(close);
      open(item);
    }
  });
})();
</script>
<script>
/* ── Avis 5 étoiles (façon Google Reviews) ─────────────────────────── */
(function () {
  var cfg = {
    etabId:   <?= (int)$etab['id'] ?>,
    apiUrl:   <?= json_encode(url('/api/orientation/avis'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
    csrf:     <?= json_encode($avisCsrf, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
    note:     <?= $monAvis ? (int)$monAvis['note'] : 0 ?>,
    offset:   <?= count($avisList) ?>,
    total:    <?= (int)$avisAgg['nb_avis'] ?>
  };

  /* — Sélecteur d'étoiles — */
  var picker   = document.getElementById('avPicker');
  var submit   = document.getElementById('avSubmit');
  var feedback = document.getElementById('avFeedback');
  var delBtn   = document.getElementById('avDelete');
  var note     = cfg.note;

  function paint(n) {
    if (!picker) return;
    picker.querySelectorAll('button').forEach(function (b) {
      b.classList.toggle('lit', parseInt(b.dataset.note, 10) <= n);
    });
  }

  if (picker) {
    paint(note);
    if (submit) submit.disabled = note < 1;

    picker.querySelectorAll('button').forEach(function (b) {
      var v = parseInt(b.dataset.note, 10);
      b.addEventListener('mouseenter', function () { paint(v); });
      b.addEventListener('mouseleave', function () { paint(note); });
      b.addEventListener('click', function () {
        note = v;
        paint(note);
        picker.querySelectorAll('button').forEach(function (x) {
          x.setAttribute('aria-checked', x === b ? 'true' : 'false');
        });
        if (submit) submit.disabled = false;
      });
    });
  }

  function showFeedback(msg, ok) {
    if (!feedback) return;
    feedback.textContent = msg;
    feedback.className = 'av-feedback ' + (ok ? 'ok' : 'err');
  }

  function apiHeaders() {
    return {
      'Content-Type': 'application/json',
      'X-CSRF-Token': cfg.csrf,
      'X-Requested-With': 'XMLHttpRequest'
    };
  }

  if (submit) {
    submit.addEventListener('click', function () {
      if (note < 1) return;
      submit.disabled = true;
      fetch(cfg.apiUrl, {
        method: 'POST',
        headers: apiHeaders(),
        credentials: 'same-origin',
        body: JSON.stringify({
          etablissement_id: cfg.etabId,
          note: note,
          commentaire: (document.getElementById('avCommentaire') || {}).value || ''
        })
      })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (res) {
        if (res.ok && res.j.success) {
          showFeedback('Merci ! Ton avis a bien été enregistré.', true);
          setTimeout(function () { window.location.reload(); }, 900);
        } else {
          submit.disabled = false;
          showFeedback((res.j.error && res.j.error.message) || 'Impossible d\'enregistrer ton avis. Réessaie.', false);
        }
      })
      .catch(function () {
        submit.disabled = false;
        showFeedback('Erreur réseau — vérifie ta connexion et réessaie.', false);
      });
    });
  }

  if (delBtn) {
    delBtn.addEventListener('click', function () {
      if (!confirm('Supprimer définitivement ton avis ?')) return;
      fetch(cfg.apiUrl + '/' + cfg.etabId, {
        method: 'DELETE',
        headers: apiHeaders(),
        credentials: 'same-origin'
      })
      .then(function (r) { return r.json(); })
      .then(function () { window.location.reload(); })
      .catch(function () { showFeedback('Erreur réseau — réessaie.', false); });
    });
  }

  /* — Voir plus d'avis (pagination) — */
  var moreBtn = document.getElementById('avMore');
  var list    = document.getElementById('avList');

  function dateRelative(str) {
    var diff = (Date.now() - new Date(str.replace(' ', 'T'))) / 1000;
    if (diff < 3600)     return 'il y a ' + Math.max(1, Math.floor(diff / 60)) + ' min';
    if (diff < 86400)    return 'il y a ' + Math.floor(diff / 3600) + ' h';
    if (diff < 2592000)  { var j = Math.floor(diff / 86400);   return 'il y a ' + j + ' jour' + (j > 1 ? 's' : ''); }
    if (diff < 31536000) { return 'il y a ' + Math.floor(diff / 2592000) + ' mois'; }
    var a = Math.floor(diff / 31536000);
    return 'il y a ' + a + ' an' + (a > 1 ? 's' : '');
  }

  function renderAvis(a) {
    var art = document.createElement('article');
    art.className = 'av-item';

    var head = document.createElement('div');
    head.className = 'av-item-head';
    var avatar = document.createElement('span');
    avatar.className = 'av-avatar';
    avatar.textContent = (a.auteur || '?').charAt(0).toUpperCase();
    var idBox = document.createElement('div');
    var nomEl = document.createElement('div');
    nomEl.className = 'av-item-auteur';
    nomEl.textContent = a.auteur || 'Utilisateur';
    var dateEl = document.createElement('div');
    dateEl.className = 'av-item-date';
    dateEl.textContent = a.date ? dateRelative(a.date) : '';
    idBox.appendChild(nomEl); idBox.appendChild(dateEl);
    head.appendChild(avatar); head.appendChild(idBox);
    art.appendChild(head);

    var stars = document.createElement('span');
    stars.className = 'av-stars small';
    stars.setAttribute('aria-label', a.note + ' étoiles sur 5');
    var fill = document.createElement('span');
    fill.className = 'av-stars-fill';
    fill.style.width = (a.note * 20) + '%';
    stars.appendChild(fill);
    art.appendChild(stars);

    if (a.commentaire) {
      var txt = document.createElement('p');
      txt.className = 'av-item-txt';
      txt.textContent = a.commentaire;
      art.appendChild(txt);
      if (a.commentaire.length > 280) {
        txt.classList.add('clamped');
        var lire = document.createElement('button');
        lire.type = 'button';
        lire.className = 'av-lire-suite';
        lire.setAttribute('data-toggle-clamp', '');
        lire.textContent = 'Lire la suite';
        art.appendChild(lire);
      }
    }
    return art;
  }

  /* — « Lire la suite » (délégation : items serveur + items modal) — */
  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-toggle-clamp]');
    if (!btn) return;
    var txt = btn.previousElementSibling;
    if (!txt || !txt.classList.contains('av-item-txt')) return;
    var clamped = txt.classList.toggle('clamped');
    btn.textContent = clamped ? 'Lire la suite' : 'Réduire';
  });

  /* — Modal « Tous les avis » : liste complète chargée par pages de 10 — */
  var overlay   = document.getElementById('avModalOverlay');
  var modalList = document.getElementById('avModalList');
  var modalBody = document.getElementById('avModalBody');
  var loader    = document.getElementById('avModalLoader');
  var finMsg    = document.getElementById('avModalFin');
  var closeBtn  = document.getElementById('avModalClose');

  var modal = { offset: 0, total: cfg.total, loading: false, done: false };

  function chargerPage() {
    if (modal.loading || modal.done) return;
    modal.loading = true;
    loader.hidden = false;
    fetch(cfg.apiUrl + '/' + cfg.etabId + '?offset=' + modal.offset + '&limit=10', {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    })
    .then(function (r) { return r.json(); })
    .then(function (j) {
      if (j.success && j.data.avis.length) {
        j.data.avis.forEach(function (a) { modalList.appendChild(renderAvis(a)); });
        modal.offset += j.data.avis.length;
        modal.total   = j.data.aggregat.nb_avis;
      }
      if (!j.success || !j.data.avis.length || modal.offset >= modal.total) {
        modal.done = true;
        finMsg.hidden = modal.offset === 0;
      }
    })
    .catch(function () { /* réseau : l'utilisateur peut re-scroller pour réessayer */ })
    .finally(function () {
      modal.loading = false;
      loader.hidden = true;
      // Page trop courte pour scroller : compléter jusqu'à remplir la vue
      if (!modal.done && modalBody.scrollHeight <= modalBody.clientHeight) {
        chargerPage();
      }
    });
  }

  function ouvrirModal() {
    modal.offset  = 0;
    modal.done    = false;
    modal.loading = false;
    modalList.textContent = '';
    finMsg.hidden = true;
    overlay.hidden = false;
    document.body.classList.add('av-modal-open');
    modalBody.scrollTop = 0;
    chargerPage();
  }

  function fermerModal() {
    overlay.hidden = true;
    document.body.classList.remove('av-modal-open');
  }

  if (moreBtn && overlay) {
    moreBtn.addEventListener('click', ouvrirModal);
    closeBtn.addEventListener('click', fermerModal);
    overlay.addEventListener('click', function (ev) {
      if (ev.target === overlay) fermerModal();
    });
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && !overlay.hidden) fermerModal();
    });
    // Scroll infini dans la modal : page suivante à 200px du bas
    modalBody.addEventListener('scroll', function () {
      if (modalBody.scrollTop + modalBody.clientHeight >= modalBody.scrollHeight - 200) {
        chargerPage();
      }
    });
  }
})();
</script>
<?php if (!empty($imageUrls)): ?>
<script>
(function () {
  var photos = <?= json_encode(array_slice($imageUrls, 0, 10), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  var courant = 0;
  var lb  = document.getElementById('galerieLightbox');
  var img = document.getElementById('lightboxImg');

  window.ouvrirLightbox = function (i) {
    courant = i;
    img.src = photos[i];
    lb.classList.add('open');
    document.body.style.overflow = 'hidden';
  };
  window.fermerLightbox = function () {
    lb.classList.remove('open');
    document.body.style.overflow = '';
  };
  window.naviguerLightbox = function (dir) {
    courant = (courant + dir + photos.length) % photos.length;
    img.src = photos[courant];
  };

  lb.addEventListener('click', function (e) { if (e.target === lb) fermerLightbox(); });
  document.addEventListener('keydown', function (e) {
    if (!lb.classList.contains('open')) return;
    if (e.key === 'Escape')     fermerLightbox();
    if (e.key === 'ArrowLeft')  naviguerLightbox(-1);
    if (e.key === 'ArrowRight') naviguerLightbox(1);
  });
})();
</script>
<?php endif; ?>
</body>
</html>
