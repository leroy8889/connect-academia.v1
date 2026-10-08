<?php
// Variables attendues du DashboardController:
// $kpi, $croissance, $allCroissance, $parSerie, $dernieres, $activiteRecente, $activiteMatieres
$kpi              = $kpi ?? [];
$croissance       = $croissance ?? [];
$allCroissance    = $allCroissance ?? [3 => [], 6 => $croissance, 12 => []];
$parSerie         = $parSerie ?? [];
$dernieres        = array_slice($dernieres ?? [], 0, 5);
$activiteRecente  = array_slice($activiteRecente ?? [], 0, 5);
$activiteMatieres = $activiteMatieres ?? [];
$suivi            = $suivi ?? null;

$totalVues = array_sum(array_column($parSerie, 'nb'));

// Prépare les datasets pour les 3 périodes (3M, 6M, 12M) — utilisés par Chart.js
function _buildPeriodDataset(array $rows): array {
    $vals     = array_map('intval', array_column($rows, 'nb'));
    $labels   = array_column($rows, 'mois');
    $total    = array_sum($vals);
    $bestIdx  = !empty($vals) ? (int) array_search(max($vals), $vals) : null;
    $bestMois = $bestIdx !== null ? ($rows[$bestIdx]['mois'] ?? '—') : '—';
    $bestVal  = !empty($vals) ? max($vals) : 0;
    $avg      = !empty($vals) ? round(array_sum($vals) / count($vals), 1) : 0;
    $taux     = (count($vals) >= 2 && $vals[0] > 0)
        ? round((($vals[count($vals)-1] - $vals[0]) / $vals[0]) * 100, 1)
        : null;
    return compact('labels', 'vals', 'total', 'bestMois', 'bestVal', 'avg', 'taux');
}

$periodsDatasets = [
    3  => _buildPeriodDataset($allCroissance[3]  ?? []),
    6  => _buildPeriodDataset($allCroissance[6]  ?? $croissance),
    12 => _buildPeriodDataset($allCroissance[12] ?? []),
];
$datasetsJSON = json_encode($periodsDatasets);
$def          = $periodsDatasets[6]; // default period for initial HTML render

$serieLabels   = json_encode(array_column($parSerie, 'serie'));
$serieData     = json_encode(array_map('intval', array_column($parSerie, 'nb')));
$serieCouleurs = json_encode(array_column($parSerie, 'couleur'));

$maxConsult = max(1, max(array_column($activiteMatieres, 'nb_consultations') ?: [1]));

// KPI — accent violet réservé à la métrique principale, le reste reste en encre neutre
$kpiCards = [
  [
    'label'     => 'Membres actifs',
    'value'     => number_format((int)($kpi['totalUsers'] ?? 0)),
    'trend'     => '+18.5%',
    'up'        => true,
    'icon'      => 'users',
    'accent'    => true,
    'sparkline' => 'M0,38 L18,30 L36,22 L54,14 L72,7 L90,4',
    'type'      => 'line',
  ],
  [
    'label'     => 'Ressources publiées',
    'value'     => number_format((int)($kpi['totalRessources'] ?? 0)),
    'trend'     => 'stable',
    'up'        => null,
    'icon'      => 'book-open',
    'type'      => 'bar',
  ],
  [
    'label'     => 'Actifs ce mois',
    'value'     => number_format((int)($kpi['activesMonth'] ?? 0)),
    'trend'     => '-1.7%',
    'up'        => false,
    'icon'      => 'calendar',
    'sparkline' => 'M0,32 L18,26 L36,29 L54,16 L72,21 L90,10',
    'type'      => 'line',
  ],
  [
    'label'     => 'Engagement',
    'value'     => number_format((float)($kpi['engagement'] ?? 0), 1) . '%',
    'trend'     => '+4.7%',
    'up'        => true,
    'icon'      => 'activity',
    'sparkline' => 'M0,36 L18,31 L36,23 L54,26 L72,15 L90,9',
    'type'      => 'line',
  ],
];

// Icônes ligne (Lucide, stroke 1.75) — même langage visuel qu'Anthropic / Claude
$kpiIcons = [
  'users'      => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
  'book-open'  => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',
  'calendar'   => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
  'activity'   => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
];
?>

<div class="cd-dash">

  <!-- ── En-tête page ──────────────────────────────────────── -->
  <header class="cd-head">
    <div>
      <span class="cd-eyebrow">Vue d'ensemble</span>
      <h1 class="cd-title">Tableau de bord</h1>
      <p class="cd-sub">Activité de la plateforme, en temps réel.</p>
    </div>
    <div class="cd-head-actions">
      <span class="cd-live"><span class="cd-live-dot"></span>En direct</span>
      <button class="cd-btn-ghost" onclick="window.print()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
        </svg>
        Exporter
      </button>
    </div>
  </header>

  <!-- ── KPI ───────────────────────────────────────────────── -->
  <section class="cd-kpi-grid">
    <?php foreach ($kpiCards as $card): ?>
    <article class="cd-kpi<?= !empty($card['accent']) ? ' cd-kpi-accent' : '' ?>">
      <div class="cd-kpi-top">
        <span class="cd-kpi-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><?= $kpiIcons[$card['icon']] ?></svg>
        </span>
        <?php if ($card['up'] === true): ?>
        <span class="cd-kpi-trend cd-trend-up">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/></svg>
          <?= $card['trend'] ?>
        </span>
        <?php elseif ($card['up'] === false): ?>
        <span class="cd-kpi-trend cd-trend-down">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="7" y1="7" x2="17" y2="17"/><polyline points="17 7 17 17 7 17"/></svg>
          <?= $card['trend'] ?>
        </span>
        <?php else: ?>
        <span class="cd-kpi-trend cd-trend-neutral">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="5" y1="12" x2="19" y2="12"/></svg>
          <?= $card['trend'] ?>
        </span>
        <?php endif; ?>
      </div>

      <div class="cd-kpi-label"><?= $card['label'] ?></div>
      <div class="cd-kpi-value"><?= $card['value'] ?></div>

      <?php if ($card['type'] === 'line' && isset($card['sparkline'])): ?>
      <svg class="cd-kpi-spark" width="96" height="46" viewBox="0 0 90 46" preserveAspectRatio="none">
        <defs>
          <linearGradient id="cdgrad-<?= md5($card['label']) ?>" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="var(--ap)" stop-opacity="0.16"/>
            <stop offset="100%" stop-color="var(--ap)" stop-opacity="0"/>
          </linearGradient>
        </defs>
        <path d="<?= $card['sparkline'] ?> L90,46 L0,46 Z" fill="url(#cdgrad-<?= md5($card['label']) ?>)"/>
        <polyline points="<?= str_replace(['M','L',','], ['',' ',' '], $card['sparkline']) ?>" stroke="var(--ap)" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <?php elseif ($card['type'] === 'bar'): ?>
      <svg class="cd-kpi-spark" width="96" height="46" viewBox="0 0 90 46">
        <rect x="6"  y="28" width="13" height="18" rx="2" fill="var(--ap)" fill-opacity="0.5"/>
        <rect x="27" y="20" width="13" height="26" rx="2" fill="var(--ap)" fill-opacity="0.65"/>
        <rect x="48" y="13" width="13" height="33" rx="2" fill="var(--ap)" fill-opacity="0.8"/>
        <rect x="69" y="7"  width="13" height="39" rx="2" fill="var(--ap)"/>
      </svg>
      <?php endif; ?>
    </article>
    <?php endforeach; ?>
  </section>

  <!-- ── Graphique vedette : croissance ────────────────────── -->
  <section class="cd-chart cd-chart-hero">
    <div class="cd-chart-head">
      <div>
        <h3 class="cd-chart-title">Croissance des inscriptions</h3>
        <p class="cd-chart-desc">Nouvelles inscriptions — <span id="gc-period-label">6 derniers mois</span></p>
      </div>
      <div class="cd-chart-controls">
        <div class="cd-legend">
          <span><i class="cd-lg-line"></i>Inscriptions</span>
          <span><i class="cd-lg-dash"></i>Moyenne</span>
        </div>
        <div class="cd-seg" role="tablist" aria-label="Période d'analyse">
          <button class="period-tab" data-period="3" role="tab" aria-selected="false">3M</button>
          <button class="period-tab active" data-period="6" role="tab" aria-selected="true">6M</button>
          <button class="period-tab" data-period="12" role="tab" aria-selected="false">12M</button>
        </div>
      </div>
    </div>

    <!-- Bandeau statistique — éditorial, séparé par filets -->
    <div class="cd-stats">
      <div class="cd-stat">
        <div class="cd-stat-k">Total période</div>
        <div class="cd-stat-v" id="gc-stat-total"><?= number_format($def['total']) ?></div>
        <div class="cd-stat-s">inscriptions</div>
      </div>
      <div class="cd-stat">
        <div class="cd-stat-k">Meilleur mois</div>
        <div class="cd-stat-v" id="gc-stat-best-val"><?= number_format($def['bestVal']) ?></div>
        <div class="cd-stat-s" id="gc-stat-best-mois"><?= e($def['bestMois']) ?></div>
      </div>
      <div class="cd-stat">
        <div class="cd-stat-k">Moyenne / mois</div>
        <div class="cd-stat-v" id="gc-stat-avg"><?= number_format($def['avg'], 1) ?></div>
        <div class="cd-stat-s">inscriptions</div>
      </div>
      <?php
        $defTaux   = $def['taux'];
        $tauxColor = $defTaux === null ? 'var(--txt-l)' : ($defTaux >= 0 ? 'var(--green)' : 'var(--red)');
        $tauxTxt   = $defTaux === null ? '—' : (($defTaux >= 0 ? '+' : '') . number_format($defTaux, 1) . '%');
        $tauxSub   = $defTaux === null ? 'données insuffisantes' : '1er → dernier mois';
      ?>
      <div class="cd-stat">
        <div class="cd-stat-k">Évolution</div>
        <div class="cd-stat-v" id="gc-stat-taux" style="color:<?= $tauxColor ?>;"><?= $tauxTxt ?></div>
        <div class="cd-stat-s" id="gc-stat-taux-sub"><?= $tauxSub ?></div>
      </div>
    </div>

    <div class="cd-canvas-wrap">
      <canvas id="chartCroissance" height="92"></canvas>
    </div>
  </section>

  <!-- ── Rangée 2 : répartition + activité matières ────────── -->
  <div class="cd-row2">

    <!-- Répartition par série (donut) -->
    <section class="cd-chart">
      <div class="cd-chart-head">
        <div>
          <h3 class="cd-chart-title">Répartition par série</h3>
          <p class="cd-chart-desc">Base utilisateurs</p>
        </div>
      </div>
      <div class="cd-donut-row">
        <div class="cd-donut-canvas">
          <canvas id="chartSeries" width="132" height="132"></canvas>
          <div class="cd-donut-center">
            <span class="cd-donut-num"><?= number_format($totalVues) ?></span>
            <span class="cd-donut-lbl">membres</span>
          </div>
        </div>
        <div class="cd-donut-legend">
          <?php foreach ($parSerie as $s): ?>
          <div class="cd-leg-item">
            <span class="cd-leg-dot" style="background:<?= e($s['couleur'] ?? '#8B52FA') ?>;"></span>
            <span class="cd-leg-name">Terminale <?= e($s['serie']) ?></span>
            <span class="cd-leg-val"><?= number_format((int)$s['nb']) ?></span>
          </div>
          <?php endforeach; ?>
          <?php if (empty($parSerie)): ?>
          <p class="cd-empty">Aucune donnée disponible</p>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <!-- Activité par matière -->
    <section class="cd-chart">
      <div class="cd-chart-head">
        <div>
          <h3 class="cd-chart-title">Activité par matière</h3>
          <p class="cd-chart-desc">Consultations — 30 derniers jours</p>
        </div>
      </div>
      <div class="cd-mat-list">
        <?php foreach ($activiteMatieres as $mat):
          $pct = $maxConsult > 0 ? round(($mat['nb_consultations'] / $maxConsult) * 100) : 0;
        ?>
        <div class="cd-mat">
          <div class="cd-mat-name"><?= e($mat['nom']) ?></div>
          <div class="cd-mat-bar"><div class="cd-mat-fill" style="width:<?= $pct ?>%"></div></div>
          <div class="cd-mat-pct"><?= $pct ?>%</div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($activiteMatieres)): ?>
        <p class="cd-empty">Aucune donnée disponible</p>
        <?php endif; ?>
      </div>
    </section>
  </div>

  <?php if (!empty($suivi)): ?>
  <!-- ── Suivi des candidatures (spec §11) ──────────────────────────────
       Le super administrateur voit ici, sans quitter son accueil, l'état de
       chaque parcours : entonnoir de conversion, derniers dossiers déposés,
       et le nombre de dossiers à 50 % qui traînent depuis plus de 15 jours. -->
  <section class="cd-chart cd-chart-flush" style="margin-bottom:18px;">
    <div class="cd-chart-head cd-chart-head-pad">
      <div>
        <h3 class="cd-chart-title">Suivi des candidatures</h3>
        <p class="cd-chart-desc">
          Préinscriptions et inscriptions, tous établissements
          <?php if ((int)$suivi['attente'] > 0): ?>
            &nbsp;·&nbsp; <strong style="color:#B45309;"><?= (int)$suivi['attente'] ?> dossier<?= $suivi['attente'] > 1 ? 's' : '' ?> à 50 % en attente depuis plus de 15 jours</strong>
          <?php endif; ?>
        </p>
      </div>
      <a href="<?= url('/admin/dossiers') ?>" class="cd-link">Tous les dossiers</a>
    </div>

    <div class="cd-stats" style="border-bottom:1px solid var(--border,rgba(31,30,29,.08));">
      <?php foreach ($suivi['entonnoir'] as $i => $etape): ?>
      <div class="cd-stat">
        <div class="cd-stat-k"><?= e($etape['libelle']) ?></div>
        <div class="cd-stat-v"><?= number_format((int)$etape['valeur'], 0, ',', ' ') ?></div>
        <div class="cd-stat-s"><?= $i === 0 ? 'base' : number_format((float)$etape['taux'], 1, ',', ' ') . ' % de l\'étape précédente' ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="cd-feed">
      <?php foreach ($suivi['derniers'] as $d): ?>
      <div class="cd-feed-item">
        <div class="cd-avatar cd-avatar-soft">
          <?= strtoupper(mb_substr((string)($d['nom_complet'] ?? 'C'), 0, 2)) ?>
        </div>
        <div class="cd-feed-main">
          <div class="cd-feed-line">
            <strong><?= e($d['nom_complet'] ?? 'Candidat') ?></strong>
            <span class="cd-pill" style="background:rgba(139,82,250,.12);color:#7440D9;"><?= e($d['type_libelle']) ?></span>
          </div>
          <span><?= e($d['etab_nom'] ?? '') ?><?= !empty($d['reference']) ? ' · ' . e($d['reference']) : '' ?></span>
        </div>
        <div class="cd-feed-side">
          <span class="cd-pill <?= (int)$d['progression'] >= 100 ? 'cd-pill-ok' : '' ?>"
                <?= (int)$d['progression'] < 100 ? 'style="background:rgba(26,26,26,.05);color:var(--txt-m);"' : '' ?>>
            <?= e($d['statut_libelle']) ?> · <?= (int)$d['progression'] ?> %
          </span>
          <?php if (!empty($d['eleve_id'])): ?>
            <a class="cd-link" href="<?= url('/admin/utilisateurs/' . (int)$d['eleve_id']) ?>">Traçabilité</a>
          <?php else: ?>
            <span class="cd-feed-date"><?= !empty($d['soumis_le']) ? date('d M Y', strtotime((string)$d['soumis_le'])) : '' ?></span>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($suivi['derniers'])): ?>
      <p class="cd-empty">Aucun dossier déposé pour l'instant</p>
      <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- ── Rangée 3 : listes ─────────────────────────────────── -->
  <div class="cd-row2">

    <!-- Dernières inscriptions -->
    <section class="cd-chart cd-chart-flush">
      <div class="cd-chart-head cd-chart-head-pad">
        <div>
          <h3 class="cd-chart-title">Dernières inscriptions</h3>
          <p class="cd-chart-desc">Nouveaux comptes</p>
        </div>
        <a href="<?= url('/admin/utilisateurs') ?>" class="cd-link">Voir tout</a>
      </div>
      <div class="cd-feed">
        <?php foreach ($dernieres as $u): ?>
        <div class="cd-feed-item">
          <div class="cd-avatar" style="<?= $u['is_active'] ? '' : 'background:var(--txt-l);' ?>">
            <?= strtoupper(mb_substr($u['prenom'], 0, 1) . mb_substr($u['nom'], 0, 1)) ?>
          </div>
          <div class="cd-feed-main">
            <strong><?= e($u['prenom'] . ' ' . $u['nom']) ?></strong>
            <span><?= e($u['serie'] ?? ucfirst($u['role'])) ?></span>
          </div>
          <div class="cd-feed-side">
            <span class="cd-pill <?= $u['is_active'] ? 'cd-pill-ok' : 'cd-pill-off' ?>"><?= $u['is_active'] ? 'Actif' : 'Suspendu' ?></span>
            <span class="cd-feed-date"><?= date('d M Y', strtotime($u['created_at'])) ?></span>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($dernieres)): ?>
        <p class="cd-empty">Aucune inscription récente</p>
        <?php endif; ?>
      </div>
    </section>

    <!-- Activité récente -->
    <section class="cd-chart cd-chart-flush">
      <div class="cd-chart-head cd-chart-head-pad">
        <div>
          <h3 class="cd-chart-title">Activité récente</h3>
          <p class="cd-chart-desc">Aujourd'hui</p>
        </div>
      </div>
      <div class="cd-feed">
        <?php foreach ($activiteRecente as $act): ?>
        <div class="cd-feed-item">
          <div class="cd-avatar cd-avatar-soft">
            <?= strtoupper(mb_substr($act['prenom'] ?? 'U', 0, 1) . mb_substr($act['nom'] ?? 'S', 0, 1)) ?>
          </div>
          <div class="cd-feed-main">
            <div class="cd-feed-line">
              <strong><?= e(($act['prenom'] ?? '') . ' ' . ($act['nom'] ?? '')) ?></strong>
              <span class="cd-role cd-role-<?= e($act['role'] ?? 'eleve') ?>"><?= e(ucfirst($act['role'] ?? 'Élève')) ?></span>
            </div>
            <p class="cd-feed-sub"><?= e($act['action_label'] ?? '') ?><?= !empty($act['matiere_nom']) ? ' — ' . e($act['matiere_nom']) : '' ?></p>
          </div>
          <span class="cd-feed-time"><?= $act['action_at'] ? date('H:i', strtotime($act['action_at'])) : '' ?></span>
        </div>
        <?php endforeach; ?>
        <?php if (empty($activiteRecente)): ?>
        <p class="cd-empty">Aucune activité récente</p>
        <?php endif; ?>
      </div>
    </section>
  </div>

  <!-- ── Bilans & rapports PDF ─────────────────────────────── -->
  <section class="cd-chart cd-bilans" id="bilans">
    <div class="cd-chart-head cd-chart-head-pad">
      <div>
        <h3 class="cd-chart-title">Bilans &amp; rapports</h3>
        <p class="cd-chart-desc">Générer un document PDF des préinscriptions, inscriptions ou utilisateurs sur une période</p>
      </div>
      <span class="cd-bilan-badge">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8M16 17H8M10 9H8"/></svg>
        PDF
        <?php if ((\Core\Session::adminRole() ?? '') === 'super_admin'): ?>
        <a class="cd-bilan-diag" href="<?= url('/admin/bilans/diagnostic') ?>" target="_blank" rel="noopener"
           title="Vérifier la chaîne de génération PDF sur ce serveur">diagnostic</a>
        <?php endif; ?>
      </span>
    </div>

    <?php if (!empty($_GET['bilan_erreur'])): ?>
    <p class="cd-bilan-erreur" role="alert">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.01"/></svg>
      <?= e((string)$_GET['bilan_erreur']) ?>
    </p>
    <?php endif; ?>

    <!-- Pas de target="_blank" : le PDF est servi en téléchargement (attachment),
         un nouvel onglet resterait blanc une fois le fichier reçu. -->
    <form class="cd-bilan-form" action="<?= url('/admin/bilans/export') ?>" method="get" id="bilanForm">
      <div class="cd-bilan-row">
        <div class="cd-bilan-field cd-bilan-type">
          <label class="cd-bilan-label">Type de bilan</label>
          <div class="cd-bilan-seg">
            <label class="cd-seg-opt">
              <input type="radio" name="type" value="preinscriptions" checked>
              <span>Préinscriptions</span>
            </label>
            <label class="cd-seg-opt">
              <input type="radio" name="type" value="inscriptions">
              <span>Inscriptions</span>
            </label>
            <label class="cd-seg-opt">
              <input type="radio" name="type" value="utilisateurs">
              <span>Utilisateurs</span>
            </label>
          </div>
        </div>
      </div>

      <div class="cd-bilan-row">
        <div class="cd-bilan-field" id="bilanEtabField">
          <label class="cd-bilan-label" for="bilanEtab">Établissement</label>
          <select class="cd-bilan-input" name="etablissement_id" id="bilanEtab">
            <option value="">Tous les établissements</option>
            <?php foreach (($bilanEtabs ?? []) as $et): ?>
            <option value="<?= (int)$et['id'] ?>"><?= e($et['nom']) ?><?= !empty($et['ville']) ? ' — ' . e($et['ville']) : '' ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="cd-bilan-field">
          <label class="cd-bilan-label" for="bilanDebut">Du</label>
          <input class="cd-bilan-input" type="date" name="debut" id="bilanDebut">
        </div>
        <div class="cd-bilan-field">
          <label class="cd-bilan-label" for="bilanFin">Au</label>
          <input class="cd-bilan-input" type="date" name="fin" id="bilanFin">
        </div>
        <div class="cd-bilan-field cd-bilan-submit">
          <button type="submit" class="cd-bilan-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg>
            Générer le PDF
          </button>
        </div>
      </div>
      <p class="cd-bilan-hint" id="bilanHint">Le rapport s'ouvre dans un nouvel onglet, prêt à imprimer ou télécharger.</p>
    </form>
  </section>

</div>

<style>
  .cd-bilans .cd-bilan-badge { display:inline-flex; align-items:center; gap:5px; background:#f2ecff; color:#8B52FA; font-size:11px; font-weight:700; padding:5px 10px; border-radius:8px; letter-spacing:.3px; }
  .cd-bilan-form { padding:4px 20px 20px; }
  .cd-bilan-erreur { display:flex; align-items:center; gap:8px; margin:0 20px 4px; padding:10px 13px; border:1px solid #F5C6C0; border-left:3px solid #D97757; border-radius:10px; background:#FDF3F1; color:#8C3A26; font-size:13px; font-weight:600; }
  .cd-bilan-erreur svg { flex:0 0 auto; }
  .cd-bilan-diag { margin-left:2px; padding-left:8px; border-left:1px solid #D8C9F5; color:#7A45E0; font-size:10px; font-weight:700; text-decoration:none; letter-spacing:.2px; }
  .cd-bilan-diag:hover { text-decoration:underline; }
  .cd-bilan-row { display:flex; flex-wrap:wrap; gap:14px; align-items:flex-end; margin-bottom:14px; }
  .cd-bilan-field { display:flex; flex-direction:column; gap:6px; flex:1 1 160px; min-width:150px; }
  .cd-bilan-type { flex:1 1 100%; }
  .cd-bilan-submit { flex:0 0 auto; }
  .cd-bilan-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:#9E9E9E; }
  .cd-bilan-input { height:42px; padding:0 12px; border:1px solid #E7E4F0; border-radius:10px; background:#fff; font-size:14px; color:#1A1A1A; font-family:inherit; transition:border-color .15s, box-shadow .15s; }
  .cd-bilan-input:focus { outline:none; border-color:#8B52FA; box-shadow:0 0 0 3px rgba(139,82,250,.12); }
  .cd-bilan-input:disabled { background:#f5f4f8; color:#b3aec2; cursor:not-allowed; }
  .cd-bilan-seg { display:inline-flex; background:#f4f2fa; border-radius:12px; padding:4px; gap:2px; }
  .cd-seg-opt { position:relative; }
  .cd-seg-opt input { position:absolute; opacity:0; inset:0; cursor:pointer; }
  .cd-seg-opt span { display:block; padding:9px 18px; border-radius:9px; font-size:13.5px; font-weight:600; color:#6b6580; cursor:pointer; transition:background .15s, color .15s; white-space:nowrap; }
  .cd-seg-opt input:checked + span { background:#fff; color:#8B52FA; box-shadow:0 1px 3px rgba(26,26,26,.08); }
  .cd-bilan-btn { display:inline-flex; align-items:center; gap:8px; height:42px; padding:0 20px; border:none; border-radius:10px; background:#8B52FA; color:#fff; font-size:14px; font-weight:700; font-family:inherit; cursor:pointer; transition:background .15s, transform .1s; white-space:nowrap; }
  .cd-bilan-btn:hover { background:#7a41e8; }
  .cd-bilan-btn:active { transform:translateY(1px); }
  .cd-bilan-hint { font-size:12.5px; color:#9E9E9E; margin:0; }
  @media (max-width:640px){ .cd-bilan-seg{ width:100%; } .cd-seg-opt{ flex:1; } .cd-seg-opt span{ text-align:center; padding:9px 6px; } .cd-bilan-submit{ flex:1 1 100%; } .cd-bilan-btn{ width:100%; justify-content:center; } }
</style>
<script>
(function(){
  var form  = document.getElementById('bilanForm');
  if(!form) return;
  var etab  = document.getElementById('bilanEtab');
  var field = document.getElementById('bilanEtabField');
  var hint  = document.getElementById('bilanHint');
  var debut = document.getElementById('bilanDebut');
  var fin   = document.getElementById('bilanFin');

  // Défaut : du 1er du mois courant à aujourd'hui.
  var now = new Date();
  var pad = function(n){ return String(n).padStart(2,'0'); };
  var iso = function(d){ return d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate()); };
  if(fin && !fin.value)     fin.value   = iso(now);
  if(debut && !debut.value) debut.value = iso(new Date(now.getFullYear(), now.getMonth(), 1));

  // Le sélecteur d'établissement n'a pas de sens pour le bilan « utilisateurs ».
  function sync(){
    var type = form.querySelector('input[name="type"]:checked').value;
    var isUsers = (type === 'utilisateurs');
    etab.disabled = isUsers;
    field.style.opacity = isUsers ? '.5' : '1';
    hint.textContent = isUsers
      ? 'Bilan de tous les comptes inscrits sur la plateforme pour la période choisie.'
      : 'Le rapport s\'ouvre dans un nouvel onglet, prêt à imprimer ou télécharger.';
  }
  form.querySelectorAll('input[name="type"]').forEach(function(r){ r.addEventListener('change', sync); });
  sync();
})();
</script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
/* ── Croissance des inscriptions — rendu épuré ────────────── */
(function () {
  const PERIODS = <?= $datasetsJSON ?>;
  const PRIMARY = '#8B52FA';
  const INK     = '#1A1A1A';
  const TICK    = '#9E9E9E';
  const GRID    = 'rgba(26,26,26,0.05)';

  let chart        = null;
  let activePeriod = 6;
  const PERIOD_LABELS = { 3: '3 derniers mois', 6: '6 derniers mois', 12: '12 derniers mois' };

  function makeGradient(ctx2d, height) {
    const g = ctx2d.createLinearGradient(0, 0, 0, height);
    g.addColorStop(0,    'rgba(139,82,250,0.14)');
    g.addColorStop(0.6,  'rgba(139,82,250,0.04)');
    g.addColorStop(1,    'rgba(139,82,250,0)');
    return g;
  }

  function getDeltas(vals) {
    return vals.map((v, i) => {
      if (i === 0 || vals[i - 1] === 0) return null;
      return ((v - vals[i - 1]) / vals[i - 1] * 100).toFixed(1);
    });
  }

  function buildChart(period) {
    const d = PERIODS[period];
    const canvas = document.getElementById('chartCroissance');
    if (!canvas || !d) return;
    if (chart) { chart.destroy(); chart = null; }

    const ctx2d  = canvas.getContext('2d');
    const height = canvas.clientHeight || 260;
    const vals   = d.vals;
    const peak   = vals.length ? Math.max(...vals) : 0;
    const deltas = getDeltas(vals);
    const avgArr = vals.map(() => d.avg);
    const grad   = makeGradient(ctx2d, height);

    chart = new Chart(canvas, {
      type: 'line',
      data: {
        labels: d.labels,
        datasets: [
          {
            label: 'Inscriptions',
            data: vals,
            borderColor: PRIMARY,
            backgroundColor: grad,
            borderWidth: 2,
            pointRadius: 0,
            pointHoverRadius: 5,
            pointHoverBackgroundColor: '#fff',
            pointHoverBorderColor: PRIMARY,
            pointHoverBorderWidth: 2.5,
            fill: true,
            tension: 0.4,
            clip: false,
          },
          {
            label: 'Moyenne',
            data: avgArr,
            borderColor: 'rgba(139,82,250,0.30)',
            borderWidth: 1.5,
            borderDash: [5, 4],
            pointRadius: 0,
            pointHoverRadius: 0,
            fill: false,
            tension: 0,
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        interaction: { mode: 'index', intersect: false },
        animation: { duration: 480, easing: 'easeInOutQuart' },
        layout: { padding: { top: 12, left: 2, right: 2 } },
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: INK,
            titleColor: '#fff',
            titleFont: { family: 'Montserrat', weight: '600', size: 12 },
            bodyColor: 'rgba(255,255,255,0.72)',
            bodyFont: { family: 'Montserrat', size: 12 },
            padding: { x: 14, y: 11 },
            cornerRadius: 10,
            displayColors: false,
            caretSize: 5,
            callbacks: {
              title: items => items[0].label,
              label: item => {
                if (item.datasetIndex === 1) return null;
                const v = item.parsed.y;
                return `  ${v} inscription${v > 1 ? 's' : ''}`;
              },
              afterLabel: item => {
                if (item.datasetIndex === 1) return null;
                const delta = deltas[item.dataIndex];
                const lines = [];
                if (delta !== null) {
                  const up = parseFloat(delta) >= 0;
                  lines.push(`  ${up ? '↑' : '↓'} ${up ? '+' : ''}${delta}% vs mois préc.`);
                }
                lines.push(`  Moyenne : ${d.avg} / mois`);
                return lines;
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            grid: { color: GRID, drawBorder: false, drawTicks: false },
            border: { display: false },
            ticks: {
              color: TICK,
              font: { family: 'Montserrat', size: 11 },
              stepSize: 1,
              padding: 10,
              callback: v => Number.isInteger(v) ? v : null,
            },
            suggestedMax: peak + Math.max(2, Math.ceil(peak * 0.18)),
          },
          x: {
            grid: { display: false, drawBorder: false },
            border: { display: false },
            ticks: { color: TICK, font: { family: 'Montserrat', size: 11 }, padding: 8 },
          }
        }
      }
    });

    updateStats(d, period);
  }

  function updateStats(d, period) {
    document.getElementById('gc-period-label').textContent = PERIOD_LABELS[period] || `${period} mois`;
    const fmt = n => Number(n).toLocaleString('fr-FR');
    document.getElementById('gc-stat-total').textContent    = fmt(d.total);
    document.getElementById('gc-stat-best-val').textContent = fmt(d.bestVal);
    document.getElementById('gc-stat-best-mois').textContent = d.bestMois;
    document.getElementById('gc-stat-avg').textContent = Number(d.avg).toLocaleString('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 });

    const tauxEl  = document.getElementById('gc-stat-taux');
    const tauxSub = document.getElementById('gc-stat-taux-sub');
    if (d.taux === null) {
      tauxEl.style.color  = '#9E9E9E';
      tauxEl.textContent  = '—';
      tauxSub.textContent = 'données insuffisantes';
    } else {
      const up = d.taux >= 0;
      tauxEl.style.color  = up ? '#00C853' : '#FF5252';
      tauxEl.textContent  = (up ? '+' : '') + parseFloat(d.taux).toFixed(1) + '%';
      tauxSub.textContent = '1er → dernier mois';
    }
  }

  document.querySelectorAll('.period-tab').forEach(btn => {
    btn.addEventListener('click', function () {
      const p = parseInt(this.dataset.period, 10);
      if (p === activePeriod) return;
      activePeriod = p;
      document.querySelectorAll('.period-tab').forEach(b => {
        b.classList.toggle('active', b === this);
        b.setAttribute('aria-selected', b === this ? 'true' : 'false');
      });
      buildChart(p);
    });
  });

  buildChart(activePeriod);
})();

/* ── Répartition par série (donut) ────────────────────────── */
(function () {
  const ctx2 = document.getElementById('chartSeries');
  if (!ctx2) return;
  const labels   = <?= $serieLabels ?>;
  const dataVals = <?= $serieData ?>;
  const colors   = <?= $serieCouleurs ?>;
  if (!labels.length) return;
  new Chart(ctx2, {
    type: 'doughnut',
    data: { labels, datasets: [{ data: dataVals, backgroundColor: colors, borderWidth: 3, borderColor: '#fff', hoverOffset: 4 }] },
    options: {
      responsive: false,
      cutout: '72%',
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#1A1A1A',
          titleFont: { family: 'Montserrat', weight: '600', size: 12 },
          bodyFont: { family: 'Montserrat', size: 12 },
          padding: { x: 12, y: 9 },
          cornerRadius: 8,
          displayColors: false,
          callbacks: { label: ctx => ` ${ctx.label}: ${ctx.parsed} élèves` }
        }
      }
    }
  });
})();
</script>
