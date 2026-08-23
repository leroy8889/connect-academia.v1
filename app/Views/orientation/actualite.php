<?php
/**
 * Détail d'une actualité d'établissement — page publique.
 * Variables injectées par PublicController::actualite() :
 *   $etab (array), $actu (array), $autres (array),
 *   $logoUrl (string), $imageUrl (string)
 *
 * Page autonome (layout ''), cohérente avec le microsite établissement
 * (orientation/site.css). Accent = couleur de marque de l'établissement.
 */

$couleur = preg_match('/^#[0-9A-Fa-f]{6}$/', $etab['couleur_principale'] ?? '') ? $etab['couleur_principale'] : '#1F4E79';
$nom     = $etab['nom']   ?? '';
$ville   = $etab['ville'] ?? '';
$slug    = $etab['slug']  ?? '';
$annee   = date('Y');

$typeLabels = ['universite' => 'Université', 'grande_ecole' => 'Grande école', 'institut' => 'Institut'];
$type = $typeLabels[$etab['type'] ?? ''] ?? ($etab['type'] ?? '');

$etabUrl = url('orientation/' . rawurlencode($slug));
$blogUrl = $etabUrl . '#blog';

$titre    = $actu['titre'] ?? '';
$categ    = trim((string)($actu['categorie'] ?? ''));
$extrait  = trim((string)($actu['extrait'] ?? ''));
$contenu  = trim((string)($actu['contenu'] ?? ''));
$datePub  = !empty($actu['date_publication']) ? strtotime($actu['date_publication']) : null;

// ── Temps de lecture (≈ 200 mots/min) ────────────────────────────────
$nbMots   = str_word_count(strip_tags($contenu));
$lecture  = max(1, (int)ceil($nbMots / 200));

// ── Slug d'ancre pour le sommaire ────────────────────────────────────
$anchor = static function (string $txt): string {
    $s = strtolower(trim($txt));
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim((string)$s, '-') ?: 'section';
};

// ── Mise en forme inline : gras + retours à la ligne ─────────────────
$inline = static function (string $txt): string {
    $h = e($txt);
    $h = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $h);
    return nl2br($h, false);
};

// ── Parse du contenu en blocs (markdown-lite : ## titre, ### sous-titre) ──
$blocks = $contenu !== '' ? preg_split("/\n\s*\n/", $contenu) : [];
$toc    = [];
$body   = [];
foreach ($blocks as $b) {
    $b = rtrim($b);
    if ($b === '') continue;
    if (preg_match('/^###\s+(.+)$/s', $b, $m)) {
        $id = $anchor($m[1]);
        $toc[]  = ['level' => 3, 'id' => $id, 'text' => trim($m[1])];
        $body[] = ['tag' => 'h3', 'id' => $id, 'html' => $inline(trim($m[1]))];
    } elseif (preg_match('/^##\s+(.+)$/s', $b, $m)) {
        $id = $anchor($m[1]);
        $toc[]  = ['level' => 2, 'id' => $id, 'text' => trim($m[1])];
        $body[] = ['tag' => 'h2', 'id' => $id, 'html' => $inline(trim($m[1]))];
    } else {
        $body[] = ['tag' => 'p', 'id' => '', 'html' => $inline($b)];
    }
}
$hasToc = count($toc) >= 2;

$moisFr = [1=>'janv.',2=>'févr.',3=>'mars',4=>'avr.',5=>'mai',6=>'juin',7=>'juil.',8=>'août',9=>'sept.',10=>'oct.',11=>'nov.',12=>'déc.'];
$dateTxt = $datePub ? ((int)date('j', $datePub) . ' ' . $moisFr[(int)date('n', $datePub)] . ' ' . date('Y', $datePub)) : '';
?>
<!doctype html>
<html lang="fr" data-theme="light">
<head>
<meta charset="utf-8">
<title><?= e($titre) ?> — <?= e($nom) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="<?= e($extrait !== '' ? $extrait : ('Actualité de ' . $nom . ' sur Connect\'Academia.')) ?>">
<link rel="stylesheet" href="<?= asset('orientation/site.css') ?>">
<style>
  :root { --brand: <?= e($couleur) ?>; }

  /* ── Page article ─────────────────────────────────────────── */
  .art-main { padding: 96px 0 40px; }
  .art-head { max-width: 760px; margin: 0 auto; text-align: center; }
  .art-breadcrumb {
    display: inline-flex; align-items: center; gap: 8px;
    font-size: 13px; font-weight: 600; color: var(--ink-3);
    margin-bottom: 22px;
  }
  .art-breadcrumb a { color: var(--ink-3); transition: color 180ms var(--ease); }
  .art-breadcrumb a:hover { color: var(--brand); }
  .art-breadcrumb svg { width: 14px; height: 14px; }

  .art-cat {
    display: inline-block; font-size: 12px; font-weight: 700;
    letter-spacing: 0.16em; text-transform: uppercase; color: var(--brand);
    margin-bottom: 18px;
  }
  .art-title {
    font-size: clamp(34px, 5vw, 56px); font-weight: 800;
    letter-spacing: -0.035em; line-height: 1.06; color: var(--ink);
    text-wrap: balance; margin: 0;
  }
  .art-meta {
    display: flex; flex-wrap: wrap; align-items: center; justify-content: center;
    gap: 10px 16px; margin-top: 26px;
    font-size: 14px; color: var(--ink-2);
  }
  .art-meta .am { display: inline-flex; align-items: center; gap: 7px; }
  .art-meta .am svg { width: 15px; height: 15px; opacity: 0.75; }
  .art-meta .sep { width: 3px; height: 3px; border-radius: 999px; background: var(--border-strong); }
  .art-author { display: inline-flex; align-items: center; gap: 9px; font-weight: 600; color: var(--ink); }
  .art-author .av {
    width: 30px; height: 30px; border-radius: 999px; overflow: hidden; flex: none;
    display: grid; place-items: center; background: var(--brand-tint);
    color: var(--brand); font-weight: 700; font-size: 13px;
    border: 1px solid var(--brand-line);
  }
  .art-author .av img { width: 100%; height: 100%; object-fit: cover; }

  .art-hero {
    max-width: 1000px; margin: 40px auto 0; border-radius: var(--radius-xl);
    overflow: hidden; box-shadow: var(--shadow-lg); border: 1px solid var(--border-subtle);
    background: var(--brand-tint);
  }
  .art-hero img { width: 100%; height: auto; display: block; }
  .art-hero-ph { aspect-ratio: 16/8; display: grid; place-items: center; }
  .art-hero-ph svg { width: 56px; height: 56px; color: var(--brand); opacity: 0.28; }

  /* ── Corps + sommaire ─────────────────────────────────────── */
  .art-layout {
    max-width: 1000px; margin: 56px auto 0;
    display: grid; grid-template-columns: minmax(0,1fr) 300px; gap: 56px; align-items: start;
  }
  .art-layout.no-toc { grid-template-columns: minmax(0, 760px); justify-content: center; }

  .art-callout {
    background: var(--brand-tint); border-left: 3px solid var(--brand);
    border-radius: 0 var(--radius-lg) var(--radius-lg) 0;
    padding: 22px 26px; margin-bottom: 40px;
  }
  .art-callout .lbl {
    font-size: 11px; font-weight: 700; letter-spacing: 0.16em; text-transform: uppercase;
    color: var(--brand); margin-bottom: 9px;
  }
  .art-callout p { margin: 0; font-size: 16px; line-height: 1.6; font-style: italic; color: var(--ink); }

  .art-body { font-size: 17px; line-height: 1.75; color: var(--ink-2); }
  .art-body h2 {
    font-size: clamp(23px, 2.6vw, 30px); font-weight: 800; letter-spacing: -0.02em;
    line-height: 1.2; color: var(--ink); margin: 44px 0 16px; scroll-margin-top: 96px;
  }
  .art-body h3 {
    font-size: 20px; font-weight: 700; letter-spacing: -0.01em;
    color: var(--ink); margin: 32px 0 12px; scroll-margin-top: 96px;
  }
  .art-body h2:first-child, .art-body h3:first-child { margin-top: 0; }
  .art-body p { margin: 0 0 20px; }
  .art-body strong { color: var(--ink); font-weight: 700; }

  /* ── Sommaire ─────────────────────────────────────────────── */
  .art-toc { position: sticky; top: 96px; }
  .art-toc-card {
    background: var(--card); border: 1px solid var(--border-subtle);
    border-radius: var(--radius-lg); padding: 24px 22px; box-shadow: var(--shadow-sm);
  }
  .art-toc-card h4 {
    margin: 0 0 16px; font-size: 12px; font-weight: 700;
    letter-spacing: 0.16em; text-transform: uppercase; color: var(--ink-3);
  }
  .art-toc-card ul { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 2px; }
  .art-toc-card a {
    display: block; padding: 7px 12px; border-radius: var(--radius-sm);
    font-size: 13.5px; line-height: 1.45; color: var(--ink-2);
    border-left: 2px solid transparent; transition: all 180ms var(--ease);
  }
  .art-toc-card a.lvl-3 { padding-left: 24px; font-size: 13px; }
  .art-toc-card a:hover { color: var(--ink); background: var(--brand-tint); }
  .art-toc-card a.active {
    color: var(--brand); font-weight: 600; background: var(--brand-tint);
    border-left-color: var(--brand);
  }

  /* ── Pied d'article : retour + à lire aussi ───────────────── */
  .art-foot { max-width: 1000px; margin: 64px auto 0; }
  .art-back {
    display: inline-flex; align-items: center; gap: 9px;
    font-size: 14px; font-weight: 600; color: var(--brand);
    padding: 12px 20px; border: 1px solid var(--brand-line); border-radius: 999px;
    background: var(--brand-tint); transition: all 200ms var(--ease);
  }
  .art-back:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
  .art-back svg { width: 16px; height: 16px; }

  .art-related { margin-top: 72px; }
  .art-related > .rh { margin-bottom: 26px; }
  .art-related .section-tag { margin-bottom: 6px; }
  .art-related h2 { font-size: clamp(24px, 3vw, 32px); font-weight: 800; letter-spacing: -0.03em; margin: 6px 0 0; }
  .art-related-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }

  @media (max-width: 900px) {
    .art-layout, .art-layout.no-toc { grid-template-columns: 1fr; gap: 40px; }
    .art-toc { display: none; }
    .art-related-grid { grid-template-columns: 1fr; }
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
      <a class="btn btn-primary btn-sm" href="<?= e($blogUrl) ?>">Toutes les actualités
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
      </a>
    </div>
  </nav>
</header>

<!-- ════════════════ ARTICLE ════════════════ -->
<main class="art-main">
  <div class="wrap">
    <header class="art-head">
      <div class="art-breadcrumb">
        <a href="<?= e($etabUrl) ?>"><?= e($nom) ?></a>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>
        <a href="<?= e($blogUrl) ?>">Actualités</a>
      </div>

      <?php if ($categ !== ''): ?>
      <span class="art-cat"><?= e($categ) ?></span>
      <?php endif; ?>

      <h1 class="art-title"><?= e($titre) ?></h1>

      <div class="art-meta">
        <span class="art-author">
          <span class="av">
            <?php if ($logoUrl): ?><img src="<?= e($logoUrl) ?>" alt=""><?php else: ?><?= e(strtoupper(mb_substr($nom, 0, 1, 'UTF-8'))) ?><?php endif; ?>
          </span>
          <?= e($nom) ?>
        </span>
        <?php if ($dateTxt !== ''): ?>
        <span class="sep"></span>
        <span class="am">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
          <?= e($dateTxt) ?>
        </span>
        <?php endif; ?>
        <span class="sep"></span>
        <span class="am">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
          <?= (int)$lecture ?> min de lecture
        </span>
      </div>
    </header>

    <div class="art-hero">
      <?php if ($imageUrl): ?>
      <img src="<?= e($imageUrl) ?>" alt="<?= e($titre) ?>">
      <?php else: ?>
      <div class="art-hero-ph">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.6"/><path d="M21 15l-5-5L5 21"/></svg>
      </div>
      <?php endif; ?>
    </div>

    <div class="art-layout<?= $hasToc ? '' : ' no-toc' ?>">
      <div class="art-content">
        <?php if ($extrait !== ''): ?>
        <div class="art-callout">
          <div class="lbl">En résumé</div>
          <p><?= e($extrait) ?></p>
        </div>
        <?php endif; ?>

        <article class="art-body">
          <?php if (!empty($body)): ?>
            <?php foreach ($body as $blk): ?>
              <?php if ($blk['tag'] === 'h2'): ?>
              <h2 id="<?= e($blk['id']) ?>"><?= $blk['html'] ?></h2>
              <?php elseif ($blk['tag'] === 'h3'): ?>
              <h3 id="<?= e($blk['id']) ?>"><?= $blk['html'] ?></h3>
              <?php else: ?>
              <p><?= $blk['html'] ?></p>
              <?php endif; ?>
            <?php endforeach; ?>
          <?php elseif ($extrait !== ''): ?>
            <p><?= e($extrait) ?></p>
          <?php else: ?>
            <p>Le contenu de cette actualité sera bientôt disponible.</p>
          <?php endif; ?>
        </article>
      </div>

      <?php if ($hasToc): ?>
      <aside class="art-toc">
        <div class="art-toc-card">
          <h4>Sommaire</h4>
          <ul>
            <?php foreach ($toc as $t): ?>
            <li><a class="lvl-<?= (int)$t['level'] ?>" href="#<?= e($t['id']) ?>"><?= e($t['text']) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </aside>
      <?php endif; ?>
    </div>

    <div class="art-foot">
      <a class="art-back" href="<?= e($blogUrl) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 19l-7-7 7-7"/></svg>
        Retour aux actualités
      </a>

      <?php if (!empty($autres)): ?>
      <section class="art-related">
        <div class="rh">
          <span class="section-tag">À lire aussi</span>
          <h2 class="display">D'autres actualités.</h2>
        </div>
        <div class="art-related-grid">
          <?php foreach ($autres as $a): ?>
          <?php $aUrl = url('orientation/' . rawurlencode($slug) . '/actualite/' . (int)$a['id']); ?>
          <a class="post" href="<?= e($aUrl) ?>">
            <div class="post-media">
              <?php if (!empty($a['categorie'])): ?><span class="post-cat"><?= e($a['categorie']) ?></span><?php endif; ?>
              <?php if (!empty($a['image_url'])): ?>
              <img src="<?= e($a['image_url']) ?>" alt="<?= e($a['titre'] ?? '') ?>" loading="lazy">
              <?php else: ?>
              <div class="post-media-placeholder">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 3v18"/></svg>
              </div>
              <?php endif; ?>
            </div>
            <div class="post-body">
              <?php if (!empty($a['date_publication'])): ?>
              <span class="post-date">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                <?= e(date('j M Y', strtotime($a['date_publication']))) ?>
              </span>
              <?php endif; ?>
              <h3><?= e($a['titre'] ?? '') ?></h3>
              <?php if (!empty($a['extrait'])): ?><p><?= e($a['extrait']) ?></p><?php endif; ?>
              <span class="post-link">Lire
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
              </span>
            </div>
          </a>
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
        <a href="<?= e($blogUrl) ?>">Le blog</a>
        <a href="<?= e($etabUrl) ?>#avis">Avis des étudiants</a>
        <a href="<?= e($etabUrl) ?>#contact">Nous contacter</a>
      </div>
      <div class="foot-col">
        <h4>Candidature</h4>
        <?php if (!empty($etab['preinscription_active'])): ?>
        <a href="<?= e(url('orientation/' . rawurlencode($slug) . '/preinscription')) ?>">Préinscription en ligne</a>
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

<?php if ($hasToc): ?>
<script>
(function () {
  var links = Array.prototype.slice.call(document.querySelectorAll('.art-toc-card a'));
  var targets = links.map(function (a) {
    return document.getElementById(a.getAttribute('href').slice(1));
  });
  function onScroll() {
    var pos = window.scrollY + 120, current = -1;
    for (var i = 0; i < targets.length; i++) {
      if (targets[i] && targets[i].offsetTop <= pos) current = i;
    }
    links.forEach(function (a, i) { a.classList.toggle('active', i === current); });
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
})();
</script>
<?php endif; ?>

</body>
</html>
