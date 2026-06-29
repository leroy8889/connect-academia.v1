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

$kpiCards = [
  [
    'label'   => 'Actifs',
    'value'   => number_format((int)($kpi['totalUsers'] ?? 0)),
    'trend'   => '+18.5%',
    'up'      => true,
    'color'   => '#22C55E',
    'sparkline' => 'M0,35 L20,28 L40,20 L60,12 L80,5',
    'type'    => 'line',
  ],
  [
    'label'   => 'Ressources publiées',
    'value'   => number_format((int)($kpi['totalRessources'] ?? 0)),
    'trend'   => 'stable',
    'up'      => null,
    'color'   => '#8B52FA',
    'type'    => 'bar',
  ],
  [
    'label'   => 'Actifs ce mois',
    'value'   => number_format((int)($kpi['activesMonth'] ?? 0)),
    'trend'   => '-1.7%',
    'up'      => false,
    'color'   => '#3B82F6',
    'sparkline' => 'M0,30 L15,25 L30,28 L45,15 L60,20 L75,8',
    'type'    => 'line',
  ],
  [
    'label'   => 'Engagement',
    'value'   => number_format((float)($kpi['engagement'] ?? 0), 1) . '%',
    'trend'   => '+4.7%',
    'up'      => true,
    'color'   => '#F59E0B',
    'sparkline' => 'M0,35 L20,30 L40,22 L55,25 L70,15 L80,10',
    'type'    => 'line',
  ],
];
?>

<!-- ── Titre page ─────────────────────────────────────────── -->
<h1 class="dashboard-title">Dashboard</h1>
<div class="dashboard-subtitle">
  <span>Vue d'ensemble de la plateforme — données temps réel</span>
  <button class="btn-ghost btn-sm" style="margin-left:auto;" onclick="window.print()">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <polyline points="8 17 3 17 3 7 21 7 21 17 16 17"/><polyline points="8 2 8 22 16 22 16 2"/>
    </svg>
    Exporter
  </button>
</div>

<!-- ── KPI Cards ──────────────────────────────────────────── -->
<div class="kpi-grid">
  <?php foreach ($kpiCards as $card): ?>
  <div class="kpi-card">
    <div class="kpi-card-label"><?= $card['label'] ?></div>
    <div class="kpi-card-value"><?= $card['value'] ?></div>

    <?php if ($card['up'] === true): ?>
    <span class="kpi-card-trend kpi-trend-up">
      <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
      <?= $card['trend'] ?>
    </span>
    <?php elseif ($card['up'] === false): ?>
    <span class="kpi-card-trend kpi-trend-down">
      <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="6 9 12 15 18 9"/></svg>
      <?= $card['trend'] ?>
    </span>
    <?php else: ?>
    <span class="kpi-card-trend kpi-trend-neutral">
      <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="5" y1="12" x2="19" y2="12"/></svg>
      <?= $card['trend'] ?>
    </span>
    <?php endif; ?>

    <?php if ($card['type'] === 'line' && isset($card['sparkline'])): ?>
    <svg class="kpi-sparkline" width="90" height="50" viewBox="0 0 90 50">
      <defs>
        <linearGradient id="grad-<?= md5($card['label']) ?>" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="<?= $card['color'] ?>" stop-opacity="0.4"/>
          <stop offset="100%" stop-color="<?= $card['color'] ?>" stop-opacity="0"/>
        </linearGradient>
      </defs>
      <path d="<?= $card['sparkline'] ?> L80,50 L0,50 Z" fill="url(#grad-<?= md5($card['label']) ?>)"/>
      <polyline points="<?= $card['sparkline'] ?>" stroke="<?= $card['color'] ?>" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    <?php elseif ($card['type'] === 'bar'): ?>
    <svg class="kpi-sparkline" width="90" height="50" viewBox="0 0 90 50">
      <rect x="5"  y="30" width="12" height="20" rx="2" fill="#8B52FA"/>
      <rect x="25" y="22" width="12" height="28" rx="2" fill="#8B52FA"/>
      <rect x="45" y="15" width="12" height="35" rx="2" fill="#8B52FA"/>
      <rect x="65" y="8"  width="12" height="42" rx="2" fill="#8B52FA"/>
    </svg>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>

<!-- ── Graphiques ─────────────────────────────────────────── -->
<div class="charts-grid mb-24">

  <!-- Croissance inscriptions -->
  <div class="chart-wrap" style="grid-column: 1 / -1;">

    <!-- En-tête -->
    <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:14px;gap:12px;flex-wrap:wrap;">
      <div>
        <h3 style="margin-bottom:3px;">Croissance des inscriptions</h3>
        <p style="margin-bottom:0;">
          Nouvelles inscriptions —
          <span id="gc-period-label">6 derniers mois</span>
        </p>
      </div>
      <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
        <!-- Légende -->
        <div style="display:flex;gap:14px;align-items:center;">
          <span style="display:inline-flex;align-items:center;gap:5px;font-size:11px;color:var(--txt-m);">
            <span style="width:20px;height:2.5px;border-radius:2px;background:#8B52FA;display:inline-block;"></span>
            Inscriptions
          </span>
          <span style="display:inline-flex;align-items:center;gap:5px;font-size:11px;color:var(--txt-m);">
            <svg width="20" height="6" style="overflow:visible;"><line x1="0" y1="3" x2="20" y2="3" stroke="rgba(139,82,250,0.45)" stroke-width="1.5" stroke-dasharray="5 3"/></svg>
            Moyenne
          </span>
        </div>
        <!-- Tabs de période -->
        <div class="period-tabs" role="tablist" aria-label="Période d'analyse">
          <button class="period-tab" data-period="3" role="tab" aria-selected="false">3M</button>
          <button class="period-tab active" data-period="6" role="tab" aria-selected="true">6M</button>
          <button class="period-tab" data-period="12" role="tab" aria-selected="false">12M</button>
        </div>
      </div>
    </div>

    <!-- Stats strip (dynamique — mis à jour par JS lors du changement de période) -->
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:20px;">

      <div style="background:rgba(139,82,250,0.06);border:1px solid rgba(139,82,250,0.15);border-radius:8px;padding:10px 14px;">
        <div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--txt-m);margin-bottom:4px;">Total période</div>
        <div id="gc-stat-total" style="font-size:20px;font-weight:800;font-family:var(--font-head);color:var(--txt);"><?= number_format($def['total']) ?></div>
        <div style="font-size:10px;color:var(--txt-l);">inscriptions</div>
      </div>

      <div style="background:rgba(34,197,94,0.06);border:1px solid rgba(34,197,94,0.15);border-radius:8px;padding:10px 14px;">
        <div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--txt-m);margin-bottom:4px;">Meilleur mois</div>
        <div id="gc-stat-best-val" style="font-size:20px;font-weight:800;font-family:var(--font-head);color:var(--txt);"><?= number_format($def['bestVal']) ?></div>
        <div id="gc-stat-best-mois" style="font-size:10px;color:var(--txt-l);"><?= e($def['bestMois']) ?></div>
      </div>

      <div style="background:rgba(59,130,246,0.06);border:1px solid rgba(59,130,246,0.15);border-radius:8px;padding:10px 14px;">
        <div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--txt-m);margin-bottom:4px;">Moyenne / mois</div>
        <div id="gc-stat-avg" style="font-size:20px;font-weight:800;font-family:var(--font-head);color:var(--txt);"><?= number_format($def['avg'], 1) ?></div>
        <div style="font-size:10px;color:var(--txt-l);">inscriptions</div>
      </div>

      <?php
        $defTaux   = $def['taux'];
        $tauxBg    = $defTaux === null ? 'rgba(152,152,176,0.06)' : ($defTaux >= 0 ? 'rgba(34,197,94,0.06)' : 'rgba(239,68,68,0.06)');
        $tauxBd    = $defTaux === null ? 'rgba(152,152,176,0.15)' : ($defTaux >= 0 ? 'rgba(34,197,94,0.15)' : 'rgba(239,68,68,0.15)');
        $tauxColor = $defTaux === null ? 'var(--txt-l)' : ($defTaux >= 0 ? '#22C55E' : '#EF4444');
        $tauxTxt   = $defTaux === null ? '—' : (($defTaux >= 0 ? '+' : '') . number_format($defTaux, 1) . '%');
        $tauxSub   = $defTaux === null ? 'données insuffisantes' : '1er → dernier mois';
      ?>
      <div id="gc-stat-taux-wrap" style="background:<?= $tauxBg ?>;border:1px solid <?= $tauxBd ?>;border-radius:8px;padding:10px 14px;transition:background .25s,border-color .25s;">
        <div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--txt-m);margin-bottom:4px;">Évolution</div>
        <div id="gc-stat-taux" style="font-size:20px;font-weight:800;font-family:var(--font-head);color:<?= $tauxColor ?>;"><?= $tauxTxt ?></div>
        <div id="gc-stat-taux-sub" style="font-size:10px;color:var(--txt-l);"><?= $tauxSub ?></div>
      </div>
    </div>

    <!-- Canvas -->
    <div class="chart-canvas-wrap">
      <canvas id="chartCroissance" height="95"></canvas>
    </div>
  </div>

  <!-- Activité par matière -->
  <div class="chart-wrap">
    <h3>Activité par matière</h3>
    <p>Consultations — 30 derniers jours</p>
    <?php foreach ($activiteMatieres as $mat):
      $pct = $maxConsult > 0 ? round(($mat['nb_consultations'] / $maxConsult) * 100) : 0;
    ?>
    <div class="matiere-progress-item">
      <div class="matiere-progress-name"><?= e($mat['nom']) ?></div>
      <div class="matiere-progress-bar">
        <div class="matiere-progress-fill" style="width:<?= $pct ?>%"></div>
      </div>
      <div class="matiere-progress-pct"><?= $pct ?>%</div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($activiteMatieres)): ?>
      <p style="color:var(--txt-l);text-align:center;padding:20px 0;font-size:13px;">Aucune donnée disponible</p>
    <?php endif; ?>
  </div>
</div>

<!-- ── Widgets bas ────────────────────────────────────────── -->
<div class="bottom-grid">

  <!-- Répartition par série (donut) -->
  <div class="chart-wrap">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
      <div>
        <h3 style="margin-bottom:2px;">Répartition par série</h3>
        <p style="margin-bottom:0;font-size:11px;">Base utilisateurs</p>
      </div>
    </div>
    <div style="display:flex;align-items:center;gap:20px;">
      <div style="position:relative;flex-shrink:0;">
        <canvas id="chartSeries" width="120" height="120"></canvas>
        <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;flex-direction:column;pointer-events:none;">
          <span style="font-family:var(--font-head);font-size:20px;font-weight:800;color:var(--txt);"><?= number_format($totalVues) ?></span>
          <span style="font-size:9px;color:var(--txt-m);text-transform:uppercase;letter-spacing:0.05em;">membres</span>
        </div>
      </div>
      <div style="flex:1;min-width:0;">
        <?php foreach ($parSerie as $s): ?>
        <div style="display:flex;align-items:center;gap:8px;padding:3px 0;font-size:12px;">
          <span style="width:8px;height:8px;border-radius:50%;background:<?= e($s['couleur'] ?? '#8B52FA') ?>;flex-shrink:0;display:inline-block;"></span>
          <span style="flex:1;color:var(--txt);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:500;">Terminale <?= e($s['serie']) ?></span>
          <span style="font-weight:700;color:var(--txt-m);font-size:11px;"><?= number_format((int)$s['nb']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Dernières inscriptions -->
  <div class="admin-card" style="overflow:hidden;">
    <div class="admin-card-header">
      <div>
        <h3>Dernières inscriptions</h3>
      </div>
      <a href="<?= url('/admin/utilisateurs') ?>" style="font-size:12px;color:var(--ap);text-decoration:none;font-weight:600;">Voir tout</a>
    </div>
    <div>
      <?php foreach ($dernieres as $u): ?>
      <div style="display:flex;align-items:center;gap:10px;padding:10px 20px;border-bottom:1px solid var(--border);">
        <div class="user-avatar" style="width:34px;height:34px;font-size:11px;background:<?= $u['is_active'] ? 'var(--ap)' : 'var(--txt-l)' ?>;">
          <?= strtoupper(mb_substr($u['prenom'], 0, 1) . mb_substr($u['nom'], 0, 1)) ?>
        </div>
        <div style="flex:1;min-width:0;">
          <strong style="font-size:12px;color:var(--txt);display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
            <?= e($u['prenom'] . ' ' . $u['nom']) ?>
          </strong>
          <span style="font-size:11px;color:var(--txt-m);"><?= e($u['serie'] ?? ucfirst($u['role'])) ?></span>
        </div>
        <div style="text-align:right;flex-shrink:0;">
          <span class="badge <?= $u['is_active'] ? 'badge-actif' : 'badge-suspendu' ?>" style="font-size:10px;">
            <?= $u['is_active'] ? 'Actif' : 'Suspendu' ?>
          </span>
          <div style="font-size:10px;color:var(--txt-l);margin-top:2px;"><?= date('d M Y', strtotime($u['created_at'])) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($dernieres)): ?>
        <p style="text-align:center;padding:20px;color:var(--txt-l);font-size:13px;">Aucune inscription récente</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Activité récente -->
  <div class="admin-card" style="overflow:hidden;">
    <div class="admin-card-header">
      <h3>Activité récente</h3>
      <span style="font-size:12px;color:var(--txt-m);">Aujourd'hui</span>
    </div>
    <div>
      <?php foreach ($activiteRecente as $act): ?>
      <div style="display:flex;align-items:flex-start;gap:10px;padding:10px 20px;border-bottom:1px solid var(--border);">
        <div class="user-avatar" style="width:34px;height:34px;font-size:11px;flex-shrink:0;">
          <?= strtoupper(mb_substr($act['prenom'] ?? 'U', 0, 1) . mb_substr($act['nom'] ?? 'S', 0, 1)) ?>
        </div>
        <div style="flex:1;min-width:0;">
          <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
            <strong style="font-size:12px;color:var(--txt);"><?= e(($act['prenom'] ?? '') . ' ' . ($act['nom'] ?? '')) ?></strong>
            <span class="badge badge-<?= e($act['role'] ?? 'eleve') ?>" style="font-size:10px;"><?= e(ucfirst($act['role'] ?? 'Élève')) ?></span>
          </div>
          <p style="font-size:11px;color:var(--txt-m);margin-top:2px;line-height:1.4;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
            <?= e($act['action_label'] ?? '') ?><?= !empty($act['matiere_nom']) ? ' — ' . e($act['matiere_nom']) : '' ?>
          </p>
        </div>
        <span style="font-size:10px;color:var(--txt-l);flex-shrink:0;white-space:nowrap;">
          <?= $act['action_at'] ? date('H:i', strtotime($act['action_at'])) : '' ?>
        </span>
      </div>
      <?php endforeach; ?>
      <?php if (empty($activiteRecente)): ?>
        <p style="text-align:center;padding:20px;color:var(--txt-l);font-size:13px;">Aucune activité récente</p>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
/* ── Croissance des inscriptions ──────────────────────────── */
(function () {
  const PERIODS = <?= $datasetsJSON ?>;
  const PRIMARY = '#8B52FA';
  const TICK    = '#9898B0';
  const GRID    = 'rgba(235,235,245,0.9)';

  let chart        = null;
  let activePeriod = 6;

  const PERIOD_LABELS = { 3: '3 derniers mois', 6: '6 derniers mois', 12: '12 derniers mois' };

  function makeGradient(ctx2d, height) {
    const g = ctx2d.createLinearGradient(0, 0, 0, height);
    g.addColorStop(0,    'rgba(139,82,250,0.20)');
    g.addColorStop(0.55, 'rgba(139,82,250,0.07)');
    g.addColorStop(1,    'rgba(139,82,250,0)');
    return g;
  }

  function getDeltas(vals) {
    return vals.map((v, i) => {
      if (i === 0 || vals[i - 1] === 0) return null;
      return ((v - vals[i - 1]) / vals[i - 1] * 100).toFixed(1);
    });
  }

  /* plugin inline : étiquettes de valeur + badge pic */
  const peakPlugin = {
    id: 'peakPlugin',
    afterDatasetsDraw(ch) {
      const { ctx: c, data } = ch;
      const meta = ch.getDatasetMeta(0);
      const vals = data.datasets[0].data;
      if (!vals.length) return;
      const peak = Math.max(...vals);

      meta.data.forEach((pt, i) => {
        const v = vals[i];
        if (v == null) return;
        const isPeak = v === peak && peak > 0;
        c.save();

        if (isPeak) {
          /* badge violet arrondi */
          const lbl = String(v);
          c.font = 'bold 11px system-ui,-apple-system';
          const tw = c.measureText(lbl).width;
          const pw = tw + 14, ph = 18;
          const px = pt.x - pw / 2;
          const py = pt.y - 42;
          /* fond */
          c.fillStyle = PRIMARY;
          c.beginPath();
          c.roundRect(px, py, pw, ph, 5);
          c.fill();
          /* petite flèche vers le bas */
          c.fillStyle = PRIMARY;
          c.beginPath();
          c.moveTo(pt.x - 4, py + ph);
          c.lineTo(pt.x + 4, py + ph);
          c.lineTo(pt.x,     py + ph + 5);
          c.closePath();
          c.fill();
          /* texte */
          c.fillStyle = '#fff';
          c.textAlign = 'center';
          c.textBaseline = 'middle';
          c.fillText(lbl, pt.x, py + ph / 2);
        } else {
          /* étiquette simple */
          c.fillStyle = PRIMARY;
          c.font = '600 11px system-ui,-apple-system';
          c.textAlign = 'center';
          c.textBaseline = 'bottom';
          c.globalAlpha = 0.80;
          c.fillText(v, pt.x, pt.y - 10);
        }
        c.restore();
      });
    }
  };

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
            borderWidth: 2.5,
            pointRadius: vals.map(v => v === peak && peak > 0 ? 7 : 5),
            pointBackgroundColor: vals.map(v => v === peak && peak > 0 ? '#fff' : PRIMARY),
            pointBorderColor:     vals.map(v => v === peak && peak > 0 ? PRIMARY : '#fff'),
            pointBorderWidth:     vals.map(v => v === peak && peak > 0 ? 3 : 2),
            pointHoverRadius: 8,
            pointHoverBackgroundColor: '#fff',
            pointHoverBorderColor: PRIMARY,
            pointHoverBorderWidth: 2.5,
            fill: true,
            tension: 0.42,
          },
          {
            label: 'Moyenne',
            data: avgArr,
            borderColor: 'rgba(139,82,250,0.32)',
            borderWidth: 1.5,
            borderDash: [6, 4],
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
        animation: { duration: 520, easing: 'easeInOutQuart' },
        layout: { padding: { top: 30 } },
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: '#13112A',
            titleColor: '#fff',
            bodyColor: 'rgba(255,255,255,0.72)',
            padding: { x: 14, y: 12 },
            cornerRadius: 10,
            borderColor: 'rgba(139,82,250,0.30)',
            borderWidth: 1,
            displayColors: false,
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
                  const up   = parseFloat(delta) >= 0;
                  const sign = up ? '+' : '';
                  lines.push(`  ${up ? '↑' : '↓'} ${sign}${delta}% vs mois préc.`);
                }
                lines.push(`  Moy. : ${d.avg} / mois`);
                return lines;
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            grid: { color: GRID, drawBorder: false },
            border: { display: false },
            ticks: {
              color: TICK,
              font: { size: 11 },
              stepSize: 1,
              padding: 6,
              callback: v => Number.isInteger(v) ? v : null,
            },
            suggestedMax: peak + Math.max(2, Math.ceil(peak * 0.18)),
          },
          x: {
            grid: { display: false },
            border: { display: false },
            ticks: { color: TICK, font: { size: 11 }, padding: 6 },
          }
        }
      },
      plugins: [peakPlugin]
    });

    /* stats strip */
    updateStats(d, period);
  }

  function updateStats(d, period) {
    document.getElementById('gc-period-label').textContent = PERIOD_LABELS[period] || `${period} mois`;

    const fmt = n => Number(n).toLocaleString('fr-FR');
    document.getElementById('gc-stat-total').textContent    = fmt(d.total);
    document.getElementById('gc-stat-best-val').textContent = fmt(d.bestVal);
    document.getElementById('gc-stat-best-mois').textContent = d.bestMois;
    document.getElementById('gc-stat-avg').textContent = Number(d.avg).toLocaleString('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 });

    const wrap    = document.getElementById('gc-stat-taux-wrap');
    const tauxEl  = document.getElementById('gc-stat-taux');
    const tauxSub = document.getElementById('gc-stat-taux-sub');
    if (d.taux === null) {
      wrap.style.background   = 'rgba(152,152,176,0.06)';
      wrap.style.borderColor  = 'rgba(152,152,176,0.15)';
      tauxEl.style.color      = 'var(--txt-l)';
      tauxEl.textContent      = '—';
      tauxSub.textContent     = 'données insuffisantes';
    } else {
      const up = d.taux >= 0;
      wrap.style.background  = up ? 'rgba(34,197,94,0.06)' : 'rgba(239,68,68,0.06)';
      wrap.style.borderColor = up ? 'rgba(34,197,94,0.15)' : 'rgba(239,68,68,0.15)';
      tauxEl.style.color     = up ? '#22C55E' : '#EF4444';
      tauxEl.textContent     = (up ? '+' : '') + parseFloat(d.taux).toFixed(1) + '%';
      tauxSub.textContent    = '1er → dernier mois';
    }
  }

  /* tabs */
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
    data: { labels, datasets: [{ data: dataVals, backgroundColor: colors, borderWidth: 2, borderColor: '#fff' }] },
    options: {
      responsive: false,
      cutout: '68%',
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.parsed} élèves` } }
      }
    }
  });
})();
</script>
