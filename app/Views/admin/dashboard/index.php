<?php
// Variables attendues du DashboardController:
// $kpi, $croissance, $parSerie, $dernieres, $activiteRecente, $activiteMatieres
$kpi              = $kpi ?? [];
$croissance       = $croissance ?? [];
$parSerie         = $parSerie ?? [];
$dernieres        = $dernieres ?? [];
$activiteRecente  = $activiteRecente ?? [];
$activiteMatieres = $activiteMatieres ?? [];

$totalVues = array_sum(array_column($parSerie, 'nb'));

$croissanceLabels = json_encode(array_column($croissance, 'mois'));
$croissanceData   = json_encode(array_map('intval', array_column($croissance, 'nb')));
$serieLabels      = json_encode(array_column($parSerie, 'serie'));
$serieData        = json_encode(array_map('intval', array_column($parSerie, 'nb')));
$serieCouleurs    = json_encode(array_column($parSerie, 'couleur'));

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
  <div class="chart-wrap">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;gap:12px;flex-wrap:wrap;">
      <div>
        <h3>Croissance des inscriptions</h3>
        <p>Évolution du nombre d'élèves inscrits — 6 derniers mois</p>
      </div>
      <div style="display:flex;gap:6px;">
        <span style="display:inline-flex;align-items:center;gap:5px;font-size:11px;color:var(--txt-m);">
          <span style="width:8px;height:8px;border-radius:50%;background:var(--ap);display:inline-block;"></span>
          Élèves
        </span>
      </div>
    </div>
    <canvas id="chartCroissance" height="160"></canvas>
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
(function() {
  const primary = '#8B52FA';
  const gridColor = '#EBEBF5';
  const tickColor = '#9898B0';

  // Croissance
  const ctx1 = document.getElementById('chartCroissance');
  if (ctx1) {
    new Chart(ctx1, {
      type: 'line',
      data: {
        labels: <?= $croissanceLabels ?>,
        datasets: [{
          label: 'Inscriptions',
          data: <?= $croissanceData ?>,
          borderColor: primary,
          backgroundColor: (ctx) => {
            const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 200);
            gradient.addColorStop(0, 'rgba(139,82,250,0.18)');
            gradient.addColorStop(1, 'rgba(139,82,250,0)');
            return gradient;
          },
          borderWidth: 2.5,
          pointRadius: 4,
          pointBackgroundColor: primary,
          pointBorderColor: '#fff',
          pointBorderWidth: 2,
          fill: true,
          tension: 0.4,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } },
        scales: {
          y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: tickColor, font: { size: 11 } } },
          x: { grid: { display: false }, ticks: { color: tickColor, font: { size: 11 } } }
        }
      }
    });
  }

  // Séries donut
  const ctx2 = document.getElementById('chartSeries');
  if (ctx2) {
    const labels   = <?= $serieLabels ?>;
    const dataVals = <?= $serieData ?>;
    const colors   = <?= $serieCouleurs ?>;
    if (labels.length > 0) {
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
    }
  }
})();
</script>
