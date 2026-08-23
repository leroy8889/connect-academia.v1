<?php
// Variables: $etab, $stats, $recent
$stats  = $stats  ?? [];
$recent = $recent ?? [];

$statutLabels = [
    'brouillon' => 'Brouillon',
    'soumis'    => 'Préinscrit',
    'paye'      => 'Payé',
    'accepte'   => 'Accepté',
    'refuse'    => 'Refusé',
];

$nbEnligne = (int)($stats['nb_enligne'] ?? 0);
$nbGuichet = (int)($stats['nb_guichet'] ?? 0);
$nbCanaux  = max(1, $nbEnligne + $nbGuichet);
$pctEnligne = (int)round($nbEnligne / $nbCanaux * 100);

$userPrenom = trim(explode(' ', (string)($_SESSION['gestionnaire_name'] ?? ''))[0] ?? '');
?>

<!-- Accueil -->
<div style="margin-bottom:26px;">
  <h2 style="font-family:var(--serif);font-size:28px;font-weight:500;letter-spacing:-.02em;line-height:1.15;">
    Bonjour<?= $userPrenom !== '' ? ' ' . e($userPrenom) : '' ?>
  </h2>
  <p style="font-size:13.5px;color:var(--ink-2);margin-top:6px;">
    Voici l'activité de <strong><?= e($etab['nom'] ?? 'votre établissement') ?></strong> en un coup d'œil.
  </p>
</div>

<!-- KPIs -->
<div class="g-kpi-row">
  <div class="g-kpi">
    <div class="g-kpi-val purple"><?= number_format((int)($stats['nb_visites'] ?? 0), 0, ',', ' ') ?></div>
    <div class="g-kpi-label">Visites de la page</div>
  </div>
  <div class="g-kpi">
    <div class="g-kpi-val"><?= number_format((int)($stats['nb_preinscriptions'] ?? 0), 0, ',', ' ') ?></div>
    <div class="g-kpi-label">Préinscriptions</div>
  </div>
  <div class="g-kpi">
    <div class="g-kpi-val"><?= number_format((int)($stats['nb_inscriptions'] ?? 0), 0, ',', ' ') ?></div>
    <div class="g-kpi-label">Inscriptions</div>
  </div>
  <div class="g-kpi">
    <div class="g-kpi-val coral"><?= number_format((int)($stats['revenus'] ?? 0), 0, ',', ' ') ?> <small style="font-family:var(--font);font-size:13px;font-weight:500;color:var(--ink-3)">XAF</small></div>
    <div class="g-kpi-label">Revenus confirmés</div>
  </div>
</div>

<!-- Répartition des canaux : en ligne vs guichet -->
<div class="g-card" style="margin-bottom:26px;padding:22px 26px;">
  <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:14px;">
    <h3 class="g-section-title" style="margin:0;">Origine des candidatures</h3>
    <div style="display:flex;gap:8px;">
      <span class="g-badge g-badge-enligne"><?= number_format($nbEnligne, 0, ',', ' ') ?> en ligne</span>
      <span class="g-badge g-badge-guichet"><?= number_format($nbGuichet, 0, ',', ' ') ?> au guichet</span>
    </div>
  </div>
  <?php if ($nbEnligne + $nbGuichet > 0): ?>
  <div style="height:10px;border-radius:999px;overflow:hidden;display:flex;background:rgba(31,30,29,.06);box-shadow:inset 0 1px 3px rgba(31,30,29,.08);" role="img"
       aria-label="<?= $pctEnligne ?> % en ligne, <?= 100 - $pctEnligne ?> % au guichet">
    <div style="width:<?= $pctEnligne ?>%;background:linear-gradient(90deg,#9663FB,var(--ap));"></div>
    <div style="flex:1;background:linear-gradient(90deg,rgba(217,119,87,.85),var(--coral));"></div>
  </div>
  <div style="display:flex;justify-content:space-between;margin-top:8px;font-size:11.5px;color:var(--ink-3);">
    <span>Candidatures déposées par les élèves sur le site</span>
    <span>Dossiers saisis par votre équipe</span>
  </div>
  <?php else: ?>
  <p style="font-size:12.5px;color:var(--ink-3);">Aucune candidature pour le moment — les deux canaux apparaîtront ici.</p>
  <?php endif; ?>
</div>

<!-- Bilan statistique PDF : préinscriptions / inscriptions sur une période -->
<div class="g-card" style="margin-bottom:26px;padding:22px 26px;">
  <div style="margin-bottom:14px;">
    <h3 class="g-section-title" style="margin:0;">Bilan statistique (PDF)</h3>
    <p style="font-size:11.5px;color:var(--ink-3);margin-top:4px;">
      Téléchargez la liste des candidats (nom, email, téléphone) ayant effectué une préinscription
      ou une inscription sur la période de votre choix.
    </p>
  </div>
  <form method="GET" action="<?= url('/gestionnaire/bilan') ?>" target="_blank"
        style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;align-items:end;">
    <div class="g-form-group" style="margin:0;">
      <label for="bilan-type">Type de bilan</label>
      <select id="bilan-type" name="type" class="g-input">
        <option value="preinscription">Préinscriptions</option>
        <option value="inscription">Inscriptions</option>
      </select>
    </div>
    <div class="g-form-group" style="margin:0;">
      <label for="bilan-du">Du</label>
      <input type="date" id="bilan-du" name="du" class="g-input" required
             max="2035-12-31" value="<?= date('Y-m-01') ?>">
    </div>
    <div class="g-form-group" style="margin:0;">
      <label for="bilan-au">Au</label>
      <input type="date" id="bilan-au" name="au" class="g-input" required
             max="2035-12-31" value="<?= date('Y-m-d') ?>">
    </div>
    <button type="submit" class="g-btn g-btn-primary" style="justify-content:center;">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      Télécharger le bilan
    </button>
  </form>
</div>

<!-- Croissance : préinscriptions vs inscriptions sur les 12 derniers mois -->
<?php
  $croissance = $stats['croissance'] ?? [];
  $cTotalPre  = array_sum(array_column($croissance, 'preinscriptions'));
  $cTotalIns  = array_sum(array_column($croissance, 'inscriptions'));
  $cHasData   = ($cTotalPre + $cTotalIns) > 0 && count($croissance) >= 2;
?>
<div class="g-card" style="margin-bottom:26px;padding:22px 26px;">
  <div style="display:flex;align-items:baseline;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:6px;">
    <div>
      <h3 class="g-section-title" style="margin:0;">Croissance des candidatures</h3>
      <p style="font-size:11.5px;color:var(--ink-3);margin-top:4px;">
        Évolution mensuelle des préinscriptions et des inscriptions de votre établissement sur les 12 derniers mois.
      </p>
    </div>
    <div class="gc-legend">
      <span class="gc-legend-item"><i class="gc-dot gc-dot-pre"></i>Préinscriptions</span>
      <span class="gc-legend-item"><i class="gc-dot gc-dot-ins"></i>Inscriptions</span>
    </div>
  </div>

<?php if (!$cHasData): ?>
  <p style="font-size:12.5px;color:var(--ink-3);padding:24px 0 8px;">
    Pas encore assez de données pour tracer la courbe — elle apparaîtra dès les premières préinscriptions et inscriptions.
  </p>
<?php else:
  // ── Géométrie du graphique (viewBox fixe, rendu responsive via width:100%) ──
  $VBW = 860; $VBH = 380;
  $padL = 46; $padR = 92; $padT = 22; $padB = 40;
  $plotW = $VBW - $padL - $padR;
  $plotH = $VBH - $padT - $padB;

  $pre = array_map(static fn($r) => (int)$r['preinscriptions'], $croissance);
  $ins = array_map(static fn($r) => (int)$r['inscriptions'], $croissance);
  $labels = array_map(static fn($r) => (string)$r['label'], $croissance);
  $n = count($croissance);

  // Échelle Y : plafond « rond » au-dessus du max, min 4 pour éviter l'aplatissement
  $rawMax = max(1, max(max($pre), max($ins)));
  $niceStep = (int)max(1, (int)ceil($rawMax / 4));
  // arrondir le pas à 1/2/5×10^k pour des graduations lisibles
  $mag = (int)pow(10, max(0, strlen((string)$niceStep) - 1));
  foreach ([1, 2, 5, 10] as $m) { if ($niceStep <= $m * $mag) { $niceStep = $m * $mag; break; } }
  $ticks   = 4;
  $yMax    = $niceStep * $ticks;

  $xAt = static fn(int $i) => $padL + ($n <= 1 ? 0 : $i * $plotW / ($n - 1));
  $yAt = static fn(float $v) => $padT + $plotH * (1 - ($v / $yMax));

  $buildPoints = static function(array $vals) use ($xAt, $yAt): string {
      $pts = [];
      foreach ($vals as $i => $v) { $pts[] = round($xAt($i), 1) . ',' . round($yAt((float)$v), 1); }
      return implode(' ', $pts);
  };
  $ptsPre = $buildPoints($pre);
  $ptsIns = $buildPoints($ins);
?>
  <div class="gc-wrap">
    <svg class="gc-svg" viewBox="0 0 <?= $VBW ?> <?= $VBH ?>" preserveAspectRatio="xMidYMid meet" role="img"
         aria-label="Courbes de croissance : <?= $cTotalPre ?> préinscriptions et <?= $cTotalIns ?> inscriptions sur 12 mois">
      <!-- Grille horizontale + graduations Y -->
      <?php for ($t = 0; $t <= $ticks; $t++):
        $val = $niceStep * $t; $y = round($yAt($val), 1); ?>
        <line x1="<?= $padL ?>" y1="<?= $y ?>" x2="<?= $padL + $plotW ?>" y2="<?= $y ?>"
              stroke="rgba(31,30,29,.09)" stroke-width="1" <?= $t === 0 ? '' : 'stroke-dasharray="1 0"' ?> />
        <text x="<?= $padL - 12 ?>" y="<?= $y + 4 ?>" text-anchor="end"
              font-size="12" fill="#8D8A83" font-family="'Inter', system-ui, sans-serif"><?= $val ?></text>
      <?php endfor; ?>

      <!-- Étiquettes X (mois) -->
      <?php foreach ($labels as $i => $lab):
        // on n'affiche pas toutes les étiquettes si trop serrées
        $skip = $n > 8 ? 1 : 0;
        if ($skip && ($i % 2 !== 0) && $i !== $n - 1) continue;
        $x = round($xAt($i), 1); ?>
        <text x="<?= $x ?>" y="<?= $VBH - $padB + 22 ?>" text-anchor="middle"
              font-size="11.5" fill="#8D8A83" font-family="'Inter', system-ui, sans-serif"><?= e($lab) ?></text>
      <?php endforeach; ?>

      <!-- Courbe préinscriptions -->
      <polyline points="<?= $ptsPre ?>" fill="none" stroke="#8B52FA" stroke-width="2.6"
                stroke-linejoin="round" stroke-linecap="round" />
      <!-- Courbe inscriptions -->
      <polyline points="<?= $ptsIns ?>" fill="none" stroke="#D97757" stroke-width="2.6"
                stroke-linejoin="round" stroke-linecap="round" />

      <!-- Marqueurs préinscriptions -->
      <?php foreach ($pre as $i => $v): ?>
        <circle cx="<?= round($xAt($i), 1) ?>" cy="<?= round($yAt((float)$v), 1) ?>" r="3.6"
                fill="#FAF9F5" stroke="#8B52FA" stroke-width="2.4">
          <title><?= e($labels[$i]) ?> — <?= $v ?> préinscription<?= $v > 1 ? 's' : '' ?></title>
        </circle>
      <?php endforeach; ?>
      <!-- Marqueurs inscriptions -->
      <?php foreach ($ins as $i => $v): ?>
        <circle cx="<?= round($xAt($i), 1) ?>" cy="<?= round($yAt((float)$v), 1) ?>" r="3.6"
                fill="#FAF9F5" stroke="#D97757" stroke-width="2.4">
          <title><?= e($labels[$i]) ?> — <?= $v ?> inscription<?= $v > 1 ? 's' : '' ?></title>
        </circle>
      <?php endforeach; ?>

      <!-- Étiquettes de série en bout de courbe (comme le template) -->
      <?php
        $lastX = round($xAt($n - 1), 1);
        $yPre  = round($yAt((float)end($pre)), 1);
        $yIns  = round($yAt((float)end($ins)), 1);
        // éviter le chevauchement des deux étiquettes
        if (abs($yPre - $yIns) < 16) { if ($yPre <= $yIns) { $yPre -= 8; $yIns += 8; } else { $yPre += 8; $yIns -= 8; } }
      ?>
      <text x="<?= $lastX + 12 ?>" y="<?= $yPre + 4 ?>" font-size="12.5" font-weight="700"
            fill="#7440D9" font-family="'Inter', system-ui, sans-serif">Préinsc.</text>
      <text x="<?= $lastX + 12 ?>" y="<?= $yIns + 4 ?>" font-size="12.5" font-weight="700"
            fill="#B45309" font-family="'Inter', system-ui, sans-serif">Insc.</text>
    </svg>
  </div>
<?php endif; ?>
</div>

<style>
.gc-legend { display:flex; gap:16px; flex-wrap:wrap; }
.gc-legend-item { display:inline-flex; align-items:center; gap:7px; font-size:12px; font-weight:600; color:var(--ink-2); }
.gc-dot { width:11px; height:11px; border-radius:50%; flex:none; box-shadow:inset 0 1px 0 rgba(255,255,255,.4); }
.gc-dot-pre { background:#8B52FA; }
.gc-dot-ins { background:#D97757; }
.gc-wrap { width:100%; overflow-x:auto; margin-top:6px; }
.gc-svg { width:100%; min-width:520px; height:auto; display:block; }
.gc-svg circle { transition:r 140ms var(--ease); cursor:pointer; }
.gc-svg circle:hover { r:5.4; }
</style>

<!-- Répartition géographique des candidatures -->
<style>
.dv-flex { display:flex; align-items:center; gap:36px; flex-wrap:wrap; }
.dv-donut-box { position:relative; width:180px; height:180px; flex:none; margin:0 auto; }
.dv-donut { width:100%; height:100%; display:block; }
.dv-center { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; pointer-events:none; }
.dv-center-val { font-family:var(--serif); font-size:30px; font-weight:500; color:var(--ink); letter-spacing:-.02em; line-height:1; }
.dv-center-label { font-size:10.5px; color:var(--ink-3); margin-top:5px; font-weight:600; }
.dv-legend { list-style:none; margin:0; padding:0; flex:1; min-width:220px; display:flex; flex-direction:column; gap:9px; }
.dv-legend-row { display:flex; align-items:center; gap:10px; font-size:12.5px; }
.dv-chip { width:11px; height:11px; border-radius:4px; flex:none; }
.dv-legend-ville { font-weight:600; color:var(--ink); flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.dv-legend-n { font-weight:700; color:var(--ink); font-variant-numeric:tabular-nums; }
.dv-legend-pct { color:var(--ink-3); font-size:11.5px; width:40px; text-align:right; font-variant-numeric:tabular-nums; }
.dv-empty { text-align:center; padding:24px 16px; color:var(--ink-3); font-size:12.5px; }
.dv-empty svg { opacity:.35; margin:0 auto 10px; display:block; }
</style>
<div class="g-card" style="margin-bottom:26px;padding:22px 26px;">
  <div style="display:flex;align-items:baseline;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:16px;">
    <div>
      <h3 class="g-section-title" style="margin:0;">Villes des candidats</h3>
      <p style="font-size:11.5px;color:var(--ink-3);margin-top:4px;">Candidatures déposées selon la ville indiquée dans le dossier.</p>
    </div>
  </div>
  <?php
    $dvVilles = $stats['villes'] ?? [];
    $dvLabel  = 'candidatures';
    require dirname(__DIR__) . '/partials/donut-villes.php';
  ?>
</div>

<!-- Derniers dossiers -->
<div style="display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:14px;flex-wrap:wrap;">
  <h3 class="g-section-title" style="margin:0;">Derniers dossiers</h3>
  <div style="display:flex;gap:10px;flex-wrap:wrap;">
    <a href="<?= url('/gestionnaire/candidatures/creer?type=preinscription') ?>" class="g-btn g-btn-outline g-btn-sm">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 4v16m8-8H4"/></svg>
      Préinscription
    </a>
    <a href="<?= url('/gestionnaire/candidatures/creer?type=inscription') ?>" class="g-btn g-btn-primary g-btn-sm">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6m3-3h-6"/></svg>
      Inscription
    </a>
    <a href="<?= url('/gestionnaire/candidatures') ?>" class="g-btn g-btn-ghost g-btn-sm">Voir tout</a>
  </div>
</div>

<div class="g-card" style="padding:0;overflow:hidden;">
<?php if (empty($recent)): ?>
  <div class="g-empty">
    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
    <p>Aucun dossier pour le moment.</p>
    <a href="<?= url('/gestionnaire/candidatures/creer?type=preinscription') ?>" class="g-btn g-btn-primary" style="margin-top:14px;">Première préinscription au guichet</a>
  </div>
<?php else: ?>
  <table class="g-table">
    <thead>
      <tr>
        <th>Candidat</th>
        <th>Filière</th>
        <th>Origine</th>
        <th>Statut</th>
        <th>Date</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($recent as $d): ?>
      <tr>
        <td>
          <div style="font-weight:600;font-size:13px;"><?= e($d['nom_complet'] ?? '—') ?></div>
          <div style="font-size:11px;color:var(--ink-3);"><?= e($d['email'] ?? '') ?></div>
        </td>
        <td style="font-size:12px;"><?= e($d['filiere_visee'] ?? '—') ?></td>
        <td>
          <?php if (($d['origine'] ?? '') === 'etablissement'): ?>
            <span class="g-badge g-badge-guichet">Guichet</span>
          <?php else: ?>
            <span class="g-badge g-badge-enligne">En ligne</span>
          <?php endif; ?>
        </td>
        <td>
          <span class="g-badge g-badge-<?= e($d['statut'] ?? 'brouillon') ?>"><?= e($statutLabels[$d['statut'] ?? 'brouillon'] ?? $d['statut']) ?></span>
        </td>
        <td style="font-size:11px;color:var(--ink-3);"><?= date('d/m/Y', strtotime($d['date_creation'])) ?></td>
        <td>
          <a href="<?= url('/gestionnaire/candidatures/' . $d['id']) ?>" class="g-btn g-btn-ghost g-btn-sm">Voir</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
</div>

<!-- Accès rapides -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:14px;margin-top:26px;">
  <a href="<?= url('/gestionnaire/formations') ?>" class="g-card" style="display:flex;align-items:center;gap:14px;padding:18px 20px;">
    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#8B52FA" stroke-width="1.8"><path d="M12 14l9-5-9-5-9 5 9 5z"/><path d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055"/></svg>
    <div>
      <div style="font-size:13px;font-weight:700;">Formations</div>
      <div style="font-size:11px;color:var(--ink-3);">Gérer les filières</div>
    </div>
  </a>
  <a href="<?= url('/gestionnaire/actualites') ?>" class="g-card" style="display:flex;align-items:center;gap:14px;padding:18px 20px;">
    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#8B52FA" stroke-width="1.8"><path d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2"/></svg>
    <div>
      <div style="font-size:13px;font-weight:700;">Actualités</div>
      <div style="font-size:11px;color:var(--ink-3);">Blog de l'école</div>
    </div>
  </a>
  <a href="<?= url('/gestionnaire/parametres') ?>" class="g-card" style="display:flex;align-items:center;gap:14px;padding:18px 20px;">
    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#D97757" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 01-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09a1.65 1.65 0 00-1-1.51 1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09a1.65 1.65 0 001.51-1 1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06a1.65 1.65 0 001.82.33h0a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51h0a1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82v0a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
    <div>
      <div style="font-size:13px;font-weight:700;">Paramètres</div>
      <div style="font-size:11px;color:var(--ink-3);">Tarifs, contact, site public</div>
    </div>
  </a>
  <a href="<?= url('/gestionnaire/profil') ?>" class="g-card" style="display:flex;align-items:center;gap:14px;padding:18px 20px;">
    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#8B52FA" stroke-width="1.8"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    <div>
      <div style="font-size:13px;font-weight:700;">Mon profil</div>
      <div style="font-size:11px;color:var(--ink-3);">Identité & mot de passe</div>
    </div>
  </a>
</div>
