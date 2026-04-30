<?php
// Variables: $heatmap (array jour×heure), $topRessources, $funnel, $repartition
$heatmap      = $heatmap ?? [];
$topRessources = $topRessources ?? [];
$funnel       = $funnel ?? [];
$repartition  = $repartition ?? [];

// Construire matrice heatmap [jour][heure] => nb
$heatMatrix = [];
foreach ($heatmap as $row) {
    $j = (int)$row['jour_num'];
    $h = (int)$row['heure'];
    $heatMatrix[$j][$h] = (int)$row['nb'];
}
$maxHeat = max(1, ...array_merge([1], array_map('max', array_map(fn($r) => array_values($r) ?: [0], $heatMatrix) ?: [[0]])));
$jours = ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'];

// Funnel
$inscrits = max(1, (int)($funnel['inscrits'] ?? 1));

// Top ressources max vues
$maxVues = max(1, max(array_column($topRessources, 'nb_vues') ?: [1]));

// Répartition donut labels
$roles = ['Élèves' => '#8B52FA', 'Enseignants' => '#B06400', 'Admins' => '#1A1A2E'];
$roleLabels = json_encode(array_keys($roles));
$roleColors = json_encode(array_values($roles));
$roleData   = json_encode([
    (int)($repartition['eleve'] ?? 0),
    (int)($repartition['enseignant'] ?? 0),
    (int)($repartition['admin'] ?? 0),
]);
$totalMembers = ($repartition['eleve'] ?? 0) + ($repartition['enseignant'] ?? 0) + ($repartition['admin'] ?? 0);
?>

<!-- Header -->
<div class="admin-page-header-row">
  <div>
    <h1>Analytics</h1>
    <p>Vue approfondie de l'activité plateforme · 30 derniers jours</p>
  </div>
  <div style="display:flex;gap:10px;">
    <button class="btn-ghost">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      30 jours ▾
    </button>
    <button class="btn-outline">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      Exporter PDF
    </button>
  </div>
</div>

<div class="widgets-grid mb-24">

  <!-- Heatmap -->
  <div class="admin-card">
    <div class="admin-card-header">
      <h3>Heatmap d'activité</h3>
      <div style="display:flex;align-items:center;gap:6px;font-size:11px;color:var(--txt-m);">
        Moins
        <div style="display:flex;gap:2px;">
          <?php for ($l = 0; $l <= 5; $l++): ?>
          <div style="width:12px;height:12px;border-radius:2px;background:<?= $l === 0 ? 'var(--bg)' : 'rgba(139,82,250,'. ($l * 0.18) .')' ?>"></div>
          <?php endfor; ?>
        </div>
        Plus
      </div>
    </div>
    <div class="admin-card-body">
      <div style="overflow-x:auto;">
        <table style="border-collapse:separate;border-spacing:3px;font-size:10px;">
          <thead>
            <tr>
              <td style="width:30px;"></td>
              <?php for ($h = 0; $h < 24; $h++): ?>
              <td style="text-align:center;color:var(--txt-l);width:18px;padding:0;"><?= $h % 4 === 0 ? $h.'h' : '' ?></td>
              <?php endfor; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($jours as $j => $jourLabel): ?>
            <tr>
              <td style="color:var(--txt-m);font-size:11px;padding-right:6px;white-space:nowrap;vertical-align:middle;"><?= $jourLabel ?></td>
              <?php for ($h = 0; $h < 24; $h++):
                $nb  = $heatMatrix[$j][$h] ?? 0;
                $lvl = $nb === 0 ? 0 : min(5, ceil(($nb / $maxHeat) * 5));
                $bg  = $lvl === 0 ? 'var(--bg)' : 'rgba(139,82,250,' . ($lvl * 0.18) . ')';
              ?>
              <td style="width:18px;height:16px;border-radius:3px;background:<?= $bg ?>;cursor:default;" title="<?= $jourLabel ?> <?= $h ?>h : <?= $nb ?> sessions"></td>
              <?php endfor; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <p style="font-size:11px;color:var(--txt-l);margin-top:10px;">Sessions de révision · jours × heures</p>
      </div>
    </div>
  </div>

  <!-- Répartition des rôles -->
  <div class="admin-card">
    <div class="admin-card-header">
      <h3>Répartition des rôles</h3>
      <span style="font-size:12px;color:var(--txt-m);">Base utilisateurs</span>
    </div>
    <div class="admin-card-body" style="display:flex;flex-direction:column;align-items:center;">
      <div style="position:relative;margin-bottom:16px;">
        <canvas id="chartRoles" width="160" height="160"></canvas>
        <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;flex-direction:column;">
          <span style="font-family:var(--font-head);font-size:24px;font-weight:800;color:var(--txt);"><?= number_format($totalMembers) ?></span>
          <span style="font-size:10px;color:var(--txt-m);">membres</span>
        </div>
      </div>
      <div style="width:100%;">
        <?php foreach ($repartition as $role => $nb): if (!in_array($role, ['eleve','enseignant','admin'])) continue;
          $pct = $totalMembers > 0 ? round($nb / $totalMembers * 100, 1) : 0;
          $colors = ['eleve' => '#8B52FA', 'enseignant' => '#B06400', 'admin' => '#1A1A2E'];
          $labels = ['eleve' => 'Élèves', 'enseignant' => 'Enseignants', 'admin' => 'Admins'];
        ?>
        <div style="display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid var(--border);">
          <span style="width:10px;height:10px;border-radius:50%;background:<?= $colors[$role] ?>;flex-shrink:0;display:inline-block;"></span>
          <span style="flex:1;font-size:13px;color:var(--txt);"><?= $labels[$role] ?></span>
          <span style="font-weight:700;font-size:13px;color:var(--txt);"><?= number_format($nb) ?></span>
          <span style="font-size:11px;color:var(--txt-m);width:42px;text-align:right;"><?= $pct ?>%</span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<div class="widgets-grid">

  <!-- Top ressources -->
  <div class="admin-card">
    <div class="admin-card-header">
      <h3>Top ressources</h3>
      <span style="font-size:12px;color:var(--txt-m);">Par nombre de vues</span>
    </div>
    <div class="admin-card-body">
      <?php foreach ($topRessources as $r):
        $pct = $maxVues > 0 ? round(($r['nb_vues'] / $maxVues) * 100) : 0;
      ?>
      <div style="padding:8px 0;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;">
        <div style="flex:1;min-width:0;">
          <div style="font-size:13px;font-weight:500;color:var(--txt);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($r['titre']) ?></div>
          <div class="matiere-progress-bar" style="height:5px;margin-top:5px;">
            <div class="matiere-progress-fill" style="width:<?= $pct ?>%;"></div>
          </div>
        </div>
        <span style="font-size:12px;font-weight:700;color:var(--txt-m);flex-shrink:0;width:40px;text-align:right;"><?= number_format($r['nb_vues']) ?></span>
      </div>
      <?php endforeach; ?>
      <?php if (empty($topRessources)): ?>
        <p style="text-align:center;color:var(--txt-l);font-size:13px;padding:20px 0;">Aucune donnée</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Funnel d'activation -->
  <div class="admin-card">
    <div class="admin-card-header">
      <h3>Funnel d'activation</h3>
      <span style="font-size:12px;color:var(--txt-m);">Inscription → 1er post</span>
    </div>
    <div class="admin-card-body">
      <?php
      $funnelSteps = [
        'Inscrits'          => ['val' => $funnel['inscrits'] ?? 0,         'pct' => 100],
        'Profil complété'   => ['val' => $funnel['profilComplet'] ?? 0,    'pct' => 0],
        '1ère consultation' => ['val' => $funnel['premiereConsult'] ?? 0,  'pct' => 0],
        '1er favori'        => ['val' => $funnel['premierFavori'] ?? 0,    'pct' => 0],
        '1er post'          => ['val' => $funnel['premierPost'] ?? 0,      'pct' => 0],
        'Actifs réguliers'  => ['val' => $funnel['actifsReguliers'] ?? 0, 'pct' => 0],
      ];
      $base = max(1, $funnelSteps['Inscrits']['val']);
      foreach ($funnelSteps as $k => &$step) {
        $step['pct'] = $base > 0 ? round($step['val'] / $base * 100) : 0;
      }
      unset($step);
      foreach ($funnelSteps as $label => $step):
        $fColor = $step['pct'] >= 70 ? 'var(--ap)' : ($step['pct'] >= 40 ? '#F59E0B' : '#374151');
      ?>
      <div class="funnel-item">
        <div class="funnel-label">
          <span><?= $label ?></span>
          <span><?= number_format($step['val']) ?> <span style="color:<?= $fColor ?>;font-weight:700;"><?= $step['pct'] ?>%</span></span>
        </div>
        <div class="funnel-bar">
          <div class="funnel-fill" style="width:<?= $step['pct'] ?>%;background:<?= $fColor ?>;"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

</div>

<!-- Chart.js pour donut rôles -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function() {
  const ctx = document.getElementById('chartRoles');
  if (!ctx) return;
  new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: <?= $roleLabels ?>,
      datasets: [{
        data: <?= $roleData ?>,
        backgroundColor: <?= $roleColors ?>,
        borderWidth: 2,
        borderColor: '#fff',
      }]
    },
    options: {
      responsive: false,
      cutout: '68%',
      plugins: { legend: { display: false } }
    }
  });
})();
</script>
