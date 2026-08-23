<?php
/**
 * Donut "candidatures par ville" — partial réutilisable (admin + gestionnaire).
 *
 * Entrée :
 *   $dvVilles = [['ville' => 'Libreville', 'n' => 35], …]  (trié par volume desc)
 *   $dvLabel  = libellé du total au centre (défaut : 'candidatures')
 *
 * Sortie : bloc .dv-flex (svg donut + légende). Les classes dv-* sont stylées
 * par la vue appelante. Palette catégorielle validée (contraste + CVD),
 * espaceurs 2px entre segments, identités portées par la légende (jamais
 * par la couleur seule).
 */
$dvVilles = $dvVilles ?? [];
$dvLabel  = $dvLabel  ?? 'candidatures';

$dvPalette = ['#8B52FA', '#D97757', '#2563EB', '#00795A', '#C13584', '#9A6A00'];
$dvAutres  = '#96591D';

// Top 6 villes + agrégat "Autres villes"
$dvTop   = array_slice($dvVilles, 0, 6);
$dvReste = array_slice($dvVilles, 6);
if (!empty($dvReste)) {
    $dvTop[] = ['ville' => 'Autres villes', 'n' => array_sum(array_column($dvReste, 'n'))];
}

$dvSegments = [];
$dvTotal    = 0;
foreach ($dvTop as $i => $v) {
    $dvSegments[] = [
        'ville' => $v['ville'],
        'n'     => (int)$v['n'],
        'color' => $i < 6 ? $dvPalette[$i] : $dvAutres,
    ];
    $dvTotal += (int)$v['n'];
}
?>
<?php if ($dvTotal === 0): ?>
<div class="dv-empty">
    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
    </svg>
    <p>Aucune candidature déposée pour le moment — la répartition par ville apparaîtra ici.</p>
</div>
<?php else: ?>
<?php
    // Géométrie du donut : r=58, épaisseur 24, espaceur 2px entre segments
    $dvR    = 58;
    $dvC    = 2 * M_PI * $dvR;
    $dvGap  = count($dvSegments) > 1 ? 2.0 : 0.0;
    $dvOff  = 0.0;
    $dvAria = implode(', ', array_map(
        static fn(array $s): string => $s['ville'] . ' : ' . $s['n'],
        $dvSegments
    ));
?>
<div class="dv-flex">
    <div class="dv-donut-box">
        <svg class="dv-donut" viewBox="0 0 160 160" role="img"
             aria-label="Candidatures par ville — <?= e($dvAria) ?>">
            <g transform="rotate(-90 80 80)">
            <?php foreach ($dvSegments as $s): ?>
                <?php
                    $len  = max(0.6, $s['n'] / $dvTotal * $dvC - $dvGap);
                    $rest = $dvC - $len;
                ?>
                <circle cx="80" cy="80" r="<?= $dvR ?>" fill="none"
                        stroke="<?= $s['color'] ?>" stroke-width="24"
                        stroke-dasharray="<?= number_format($len, 2, '.', '') ?> <?= number_format($rest, 2, '.', '') ?>"
                        stroke-dashoffset="<?= number_format(-$dvOff, 2, '.', '') ?>">
                    <title><?= e($s['ville']) ?> — <?= $s['n'] ?> (<?= round($s['n'] / $dvTotal * 100) ?> %)</title>
                </circle>
                <?php $dvOff += $s['n'] / $dvTotal * $dvC; ?>
            <?php endforeach; ?>
            </g>
        </svg>
        <div class="dv-center">
            <span class="dv-center-val"><?= number_format($dvTotal, 0, ',', ' ') ?></span>
            <span class="dv-center-label"><?= e($dvLabel) ?></span>
        </div>
    </div>
    <ul class="dv-legend">
    <?php foreach ($dvSegments as $s): ?>
        <li class="dv-legend-row">
            <span class="dv-chip" style="background:<?= $s['color'] ?>;"></span>
            <span class="dv-legend-ville"><?= e($s['ville']) ?></span>
            <span class="dv-legend-n"><?= number_format($s['n'], 0, ',', ' ') ?></span>
            <span class="dv-legend-pct"><?= round($s['n'] / $dvTotal * 100) ?> %</span>
        </li>
    <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
