<?php
/**
 * Détail d'une formation d'établissement — page publique.
 * Variables injectées par PublicController::formationDetail() :
 *   $etab (array), $formation (array), $autres (array), $logoUrl (string)
 *
 * Page autonome (layout ''), cohérente avec le microsite établissement
 * (orientation/site.css), même structure que orientation/actualite.php.
 */

$couleur = preg_match('/^#[0-9A-Fa-f]{6}$/', $etab['couleur_principale'] ?? '') ? $etab['couleur_principale'] : '#1F4E79';
$nom     = $etab['nom']   ?? '';
$ville   = $etab['ville'] ?? '';
$slug    = $etab['slug']  ?? '';
$annee   = date('Y');

$typeLabels = ['universite' => 'Université', 'grande_ecole' => 'Grande école', 'institut' => 'Institut'];
$type = $typeLabels[$etab['type'] ?? ''] ?? ($etab['type'] ?? '');

$etabUrl          = url('orientation/' . rawurlencode($slug));
$formationsUrl     = $etabUrl . '#formations';
$preinscriptionUrl = url('orientation/' . rawurlencode($slug) . '/preinscription');

$intitule = $formation['intitule'] ?? '';
$niveau   = trim((string)($formation['niveau'] ?? ''));
$duree    = trim((string)($formation['duree_texte'] ?? ''));
$places   = trim((string)($formation['places_texte'] ?? ''));
$prix     = (int)($formation['prix'] ?? 0);
$prixUnite= $formation['prix_unite'] ?: 'FCFA/an';
$statut   = $formation['statut'] ?? 'ouverte';
$isOpen   = $statut === 'ouverte';

$statutLabels = ['ouverte' => 'Ouverte', 'bientot' => 'Bientôt', 'fermee' => 'Fermée'];
$statutLabel  = $statutLabels[$statut] ?? $statut;

// ── Rendu simple des zones débouchés/avantages : lignes "- " en liste, sinon paragraphe ──
$renderBlocs = static function (string $texte): string {
    $texte = trim($texte);
    if ($texte === '') return '';
    $lignes = preg_split('/\r\n|\r|\n/', $texte);
    $html = '';
    $enListe = false;
    foreach ($lignes as $ligne) {
        $ligne = trim($ligne);
        if ($ligne === '') continue;
        $estPuce = (bool)preg_match('/^[-•*]\s+/', $ligne);
        $texteLigne = $estPuce ? preg_replace('/^[-•*]\s+/', '', $ligne) : $ligne;
        if ($estPuce) {
            if (!$enListe) { $html .= '<ul class="fd-list">'; $enListe = true; }
            $html .= '<li>' . e($texteLigne) . '</li>';
        } else {
            if ($enListe) { $html .= '</ul>'; $enListe = false; }
            $html .= '<p>' . e($texteLigne) . '</p>';
        }
    }
    if ($enListe) $html .= '</ul>';
    return $html;
};

$debouchesHtml = $renderBlocs((string)($formation['debouches'] ?? ''));
$avantagesHtml = $renderBlocs((string)($formation['avantages'] ?? ''));
$aDuContenu    = $debouchesHtml !== '' || $avantagesHtml !== '';
$description   = trim((string)($formation['description'] ?? ''));
?>
<!doctype html>
<html lang="fr" data-theme="light">
<head>
<meta charset="utf-8">
<title><?= e($intitule) ?> — <?= e($nom) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="<?= e($intitule . ' — ' . $nom . ($niveau !== '' ? ' (' . $niveau . ')' : '') . '. Préinscription en ligne sur Connect\'Academia.') ?>">
<link rel="stylesheet" href="<?= asset('orientation/site.css') ?>">
<style>
  :root { --brand: <?= e($couleur) ?>; }

  .fd-main { padding: 96px 0 40px; }
  .fd-head { max-width: 760px; margin: 0 auto; text-align: center; }
  .fd-breadcrumb {
    display: inline-flex; align-items: center; gap: 8px;
    font-size: 13px; font-weight: 600; color: var(--ink-3);
    margin-bottom: 22px;
  }
  .fd-breadcrumb a { color: var(--ink-3); transition: color 180ms var(--ease); }
  .fd-breadcrumb a:hover { color: var(--brand); }
  .fd-breadcrumb svg { width: 14px; height: 14px; }

  .fd-badges { display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 18px; }
  .fd-niveau {
    display: inline-block; font-size: 12px; font-weight: 700;
    letter-spacing: 0.1em; text-transform: uppercase; color: var(--brand);
    background: var(--brand-tint); border: 1px solid var(--brand-line);
    padding: 5px 12px; border-radius: 999px;
  }
  .fd-statut { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; color: var(--ink-3); }
  .fd-statut .dot { width: 7px; height: 7px; border-radius: 999px; background: #22c55e; }
  .fd-statut.closed .dot { background: #9E9E9E; }

  .fd-title {
    font-size: clamp(30px, 4.4vw, 48px); font-weight: 800;
    letter-spacing: -0.03em; line-height: 1.1; color: var(--ink);
    text-wrap: balance; margin: 0;
  }
  .fd-meta {
    display: flex; flex-wrap: wrap; align-items: center; justify-content: center;
    gap: 10px 16px; margin-top: 22px; font-size: 14px; color: var(--ink-2);
  }
  .fd-meta .am { display: inline-flex; align-items: center; gap: 7px; }
  .fd-meta .am svg { width: 15px; height: 15px; opacity: 0.75; }
  .fd-meta .sep { width: 3px; height: 3px; border-radius: 999px; background: var(--border-strong); }

  .fd-hero {
    max-width: 1000px; margin: 40px auto 0; border-radius: var(--radius-xl);
    overflow: hidden; box-shadow: var(--shadow-lg); border: 1px solid var(--border-subtle);
    background: var(--brand-tint); aspect-ratio: 16/6; display: grid; place-items: center;
  }
  .fd-hero svg { width: 64px; height: 64px; color: var(--brand); opacity: 0.32; }

  .fd-layout {
    max-width: 1000px; margin: 56px auto 0;
    display: grid; grid-template-columns: minmax(0,1fr) 300px; gap: 56px; align-items: start;
  }

  .fd-block { margin-bottom: 40px; }
  .fd-block-head { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
  .fd-block-icon {
    width: 38px; height: 38px; border-radius: var(--radius-md); flex: none;
    display: grid; place-items: center; background: var(--brand-tint); color: var(--brand);
    border: 1px solid var(--brand-line);
  }
  .fd-block-icon svg { width: 19px; height: 19px; }
  .fd-block h2 { font-size: 21px; font-weight: 800; letter-spacing: -0.01em; color: var(--ink); margin: 0; }
  .fd-block .fd-body { font-size: 16px; line-height: 1.7; color: var(--ink-2); }
  .fd-body p { margin: 0 0 14px; }
  .fd-list { list-style: none; margin: 0 0 14px; padding: 0; display: flex; flex-direction: column; gap: 10px; }
  .fd-list li { position: relative; padding-left: 26px; }
  .fd-list li::before {
    content: ""; position: absolute; left: 0; top: 8px; width: 8px; height: 8px;
    border-radius: 999px; background: var(--brand);
  }
  .fd-empty { font-size: 15px; color: var(--ink-3); font-style: italic; }

  .fd-side { position: sticky; top: 96px; }
  .fd-card {
    background: var(--card); border: 1px solid var(--border-subtle);
    border-radius: var(--radius-lg); padding: 26px 24px; box-shadow: var(--shadow-sm);
  }
  .fd-card h4 { margin: 0 0 18px; font-size: 12px; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: var(--ink-3); }
  .fd-facts { display: flex; flex-direction: column; gap: 14px; margin-bottom: 22px; }
  .fd-fact { display: flex; align-items: center; gap: 10px; font-size: 14px; color: var(--ink-2); }
  .fd-fact svg { width: 17px; height: 17px; color: var(--brand); flex: none; }
  .fd-fact b { color: var(--ink); }
  .fd-price { border-top: 1px solid var(--border-subtle); padding-top: 18px; margin-bottom: 20px; }
  .fd-price small { display: block; font-size: 12px; color: var(--ink-3); margin-bottom: 4px; }
  .fd-price b { font-size: 24px; font-weight: 800; color: var(--ink); letter-spacing: -0.02em; }
  .fd-price b span { font-size: 13px; font-weight: 600; color: var(--ink-3); }
  .fd-cta { width: 100%; justify-content: center; }

  .fd-foot { max-width: 1000px; margin: 64px auto 0; }
  .fd-back {
    display: inline-flex; align-items: center; gap: 9px;
    font-size: 14px; font-weight: 600; color: var(--brand);
    padding: 12px 20px; border: 1px solid var(--brand-line); border-radius: 999px;
    background: var(--brand-tint); transition: all 200ms var(--ease);
  }
  .fd-back:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
  .fd-back svg { width: 16px; height: 16px; }

  .fd-related { margin-top: 72px; }
  .fd-related > .rh { margin-bottom: 26px; }
  .fd-related h2 { font-size: clamp(24px, 3vw, 32px); font-weight: 800; letter-spacing: -0.03em; margin: 6px 0 0; }

  @media (max-width: 900px) {
    .fd-layout { grid-template-columns: 1fr; gap: 40px; }
    .fd-side { position: static; }
  }
</style>
</head>
<body>

<!-- ════════════════ HEADER ════════════════ -->
<header class="site-header">
  <nav class="nav wrap">
    <a class="brand-id" href="<?= e($etabUrl) ?>" aria-label="Accueil <?= e($nom) ?>">
      <div class="brand-logo">
        <?php if ($logoUrl): ?>
        <img src="<?= e($logoUrl) ?>" alt="Logo <?= e($nom) ?>">
        <?php else: ?>
        <div class="brand-initial"><?= e(strtoupper(mb_substr($nom, 0, 1, 'UTF-8'))) ?></div>
        <?php endif; ?>
      </div>
      <span class="brand-text">
        <strong><?= e($nom) ?></strong>
        <span><?= e($type) ?><?php if ($ville): ?> · <?= e($ville) ?><?php endif; ?></span>
      </span>
    </a>
    <div class="nav-cta">
      <a class="btn btn-primary btn-sm" href="<?= e($formationsUrl) ?>">Toutes les formations
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
      </a>
    </div>
  </nav>
</header>

<!-- ════════════════ FORMATION ════════════════ -->
<main class="fd-main">
  <div class="wrap">
    <header class="fd-head">
      <div class="fd-breadcrumb">
        <a href="<?= e($etabUrl) ?>"><?= e($nom) ?></a>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>
        <a href="<?= e($formationsUrl) ?>">Formations</a>
      </div>

      <div class="fd-badges">
        <?php if ($niveau !== ''): ?><span class="fd-niveau"><?= e($niveau) ?></span><?php endif; ?>
        <span class="fd-statut<?= $isOpen ? '' : ' closed' ?>"><span class="dot"></span><?= e($statutLabel) ?></span>
      </div>

      <h1 class="fd-title"><?= e($intitule) ?></h1>

      <div class="fd-meta">
        <?php if ($duree !== ''): ?>
        <span class="am">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
          <?= e($duree) ?>
        </span>
        <?php endif; ?>
        <?php if ($places !== ''): ?>
        <span class="sep"></span>
        <span class="am">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/></svg>
          <?= e($places) ?>
        </span>
        <?php endif; ?>
      </div>
    </header>

    <div class="fd-hero">
      <?php if (!empty($formationImageUrl)): ?>
      <img src="<?= e($formationImageUrl) ?>" alt="<?= e($intitule) ?>" style="width:100%;height:100%;object-fit:cover;">
      <?php else: ?>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path d="M22 10 12 5 2 10l10 5 10-5z"/>
        <path d="M6 12v5c0 1 2.7 2.5 6 2.5s6-1.5 6-2.5v-5M22 10v6"/>
      </svg>
      <?php endif; ?>
    </div>

    <div class="fd-layout">
      <div class="fd-content">
        <?php if ($description !== ''): ?>
        <p style="font-size:18px;line-height:1.65;color:var(--ink);margin:0 0 36px;"><?= nl2br(e($description)) ?></p>
        <?php endif; ?>
        <?php if ($debouchesHtml !== ''): ?>
        <section class="fd-block">
          <div class="fd-block-head">
            <span class="fd-block-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
            </span>
            <h2>Débouchés &amp; opportunités</h2>
          </div>
          <div class="fd-body"><?= $debouchesHtml ?></div>
        </section>
        <?php endif; ?>

        <?php if ($avantagesHtml !== ''): ?>
        <section class="fd-block">
          <div class="fd-block-head">
            <span class="fd-block-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l2.9 6.4 6.8.6-5.1 4.6 1.6 6.7L12 16.9 5.8 20.3l1.6-6.7-5.1-4.6 6.8-.6L12 2z"/></svg>
            </span>
            <h2>Avantages du programme</h2>
          </div>
          <div class="fd-body"><?= $avantagesHtml ?></div>
        </section>
        <?php endif; ?>

        <?php if (!$aDuContenu): ?>
        <p class="fd-empty">Les informations détaillées (débouchés, avantages) de cette formation seront bientôt disponibles. Contacte l'établissement pour en savoir plus.</p>
        <?php endif; ?>
      </div>

      <aside class="fd-side">
        <div class="fd-card">
          <h4>En bref</h4>
          <div class="fd-facts">
            <?php if ($niveau !== ''): ?>
            <div class="fd-fact">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10 12 5 2 10l10 5 10-5z"/></svg>
              Niveau <b><?= e($niveau) ?></b>
            </div>
            <?php endif; ?>
            <?php if ($duree !== ''): ?>
            <div class="fd-fact">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
              Durée <b><?= e($duree) ?></b>
            </div>
            <?php endif; ?>
            <?php if ($places !== ''): ?>
            <div class="fd-fact">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
              <b><?= e($places) ?></b>
            </div>
            <?php endif; ?>
          </div>

          <?php if ($prix > 0): ?>
          <div class="fd-price">
            <small>À partir de</small>
            <b><?= number_format($prix, 0, ',', ' ') ?> <span><?= e($prixUnite) ?></span></b>
          </div>
          <?php endif; ?>

          <?php if ($isOpen): ?>
          <a class="btn btn-primary fd-cta" href="<?= !empty($etab['preinscription_active']) ? e($preinscriptionUrl) : e($etabUrl) . '#tarifs' ?>">
            Préinscription
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
          </a>
          <?php else: ?>
          <a class="btn btn-ghost fd-cta" href="<?= e($etabUrl) ?>#contact">M'avertir de l'ouverture</a>
          <?php endif; ?>
        </div>
      </aside>
    </div>

    <div class="fd-foot">
      <a class="fd-back" href="<?= e($formationsUrl) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 19l-7-7 7-7"/></svg>
        Retour aux formations
      </a>

      <?php if (!empty($autres)): ?>
      <section class="fd-related">
        <div class="rh">
          <span class="section-tag">Aussi chez <?= e($nom) ?></span>
          <h2 class="display">D'autres formations.</h2>
        </div>
        <div class="filiere-grid">
          <?php foreach ($autres as $f): ?>
          <?php $fUrl = url('orientation/' . rawurlencode($slug) . '/formations/' . (int)$f['id']); ?>
          <article class="filiere">
            <div class="filiere-top">
              <?php if (!empty($f['niveau'])): ?><span class="filiere-niveau"><?= e($f['niveau']) ?></span><?php endif; ?>
            </div>
            <div class="filiere-head"><h3><?= e($f['intitule']) ?></h3></div>
            <div class="filiere-foot">
              <?php if ((int)$f['prix'] > 0): ?>
              <div class="filiere-price">
                <small>À partir de</small>
                <b><?= number_format((int)$f['prix'], 0, ',', ' ') ?><span> <?= e($f['prix_unite'] ?: 'FCFA/an') ?></span></b>
              </div>
              <?php else: ?><div></div><?php endif; ?>
              <a class="btn btn-primary btn-sm" href="<?= e($fUrl) ?>">Voir
                <svg class="filiere-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
              </a>
            </div>
          </article>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>
    </div>
  </div>
</main>

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
        <p class="foot-about"><?= e($type) ?><?php if ($ville): ?> à <?= e($ville) ?><?php endif ?>.</p>
      </div>
      <div class="foot-col">
        <h4>Établissement</h4>
        <a href="<?= e($etabUrl) ?>">Retour au site</a>
        <a href="<?= e($formationsUrl) ?>">Nos formations</a>
        <a href="<?= e($etabUrl) ?>#avis">Avis des étudiants</a>
        <a href="<?= e($etabUrl) ?>#contact">Nous contacter</a>
      </div>
      <div class="foot-col">
        <h4>Candidature</h4>
        <?php if (!empty($etab['preinscription_active'])): ?>
        <a href="<?= e($preinscriptionUrl) ?>">Préinscription en ligne</a>
        <?php endif; ?>
        <?php if (!empty($etab['inscription_active'])): ?>
        <a href="<?= e(url('orientation/' . rawurlencode($slug) . '/inscription')) ?>">Inscription en ligne</a>
        <?php endif; ?>
        <a href="<?= e($etabUrl) ?>#tarifs">Frais de scolarité</a>
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

</body>
</html>
