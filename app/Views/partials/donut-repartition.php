<?php
/**
 * Donut de répartition générique — partial réutilisable (admin).
 *
 * Entrée :
 *   $drItems       = [['label' => 'UOB', 'n' => 12, 'color' => '#8B52FA'], …] (trié par volume desc)
 *   $drCenterLabel = libellé sous la valeur centrale (ex : 'inscriptions')
 *   $drCenterVal   = valeur centrale pré-formatée (défaut : total formaté)
 *   $drUnit        = suffixe des valeurs de légende (ex : ' XAF', défaut : '')
 *   $drAria        = préfixe du libellé accessible (ex : 'Inscriptions par établissement')
 *   $drEmpty       = texte affiché quand aucune donnée
 *   $drAutres      = libellé de l'agrégat au-delà de 6 segments (défaut : 'Autres')
 *
 * Sortie : bloc .dv-flex (svg donut + légende). Mêmes classes dv-* que
 * donut-villes — stylées par la vue appelante. Identités portées par la
 * légende (jamais par la couleur seule), espaceurs 2px entre segments.
 */
$drItems       = $drItems       ?? [];
$drCenterLabel = $drCenterLabel ?? '';
$drUnit        = $drUnit        ?? '';
$drAria        = $drAria        ?? 'Répartition';
$drEmpty       = $drEmpty       ?? 'Aucune donnée pour le moment.';
$drAutres      = $drAutres      ?? 'Autres';
$drAutresColor = '#96591D';

// Top 6 + agrégat "Autres"
$drTop   = array_slice($drItems, 0, 6);
$drReste = array_slice($drItems, 6);
if (!empty($drReste)) {
    $drTop[] = ['label' => $drAutres, 'n' => array_sum(array_column($drReste, 'n')), 'color' => $drAutresColor];
}

$drSegments = [];
$drTotal    = 0;
foreach ($drTop as $it) {
    $n = (int)$it['n'];
    if ($n <= 0) continue;
    $drSegments[] = ['label' => $it['label'], 'n' => $n, 'color' => $it['color']];
    $drTotal += $n;
}
$drCenterVal = $drCenterVal ?? number_format($drTotal, 0, ',', ' ');
?>
<?php if ($drTotal === 0): ?>
<div class="dv-empty">
    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
        <path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/>
    </svg>
    <p><?= e($drEmpty) ?></p>
</div>
<?php else: ?>
<?php
    // Géométrie du donut : r=58, épaisseur 24, espaceur 2px entre segments
    $drR    = 58;
    $drC    = 2 * M_PI * $drR;
    $drGap  = count($drSegments) > 1 ? 2.0 : 0.0;
    $drOff  = 0.0;
    $drAriaFull = $drAria . ' — ' . implode(', ', array_map(
        static fn(array $s): string => $s['label'] . ' : ' . $s['n'],
        $drSegments
    ));
?>
<div class="dv-flex">
    <div class="dv-donut-box">
        <svg class="dv-donut" viewBox="0 0 160 160" role="img" aria-label="<?= e($drAriaFull) ?>">
            <g transform="rotate(-90 80 80)">
            <?php foreach ($drSegments as $s): ?>
                <?php
                    $len  = max(0.6, $s['n'] / $drTotal * $drC - $drGap);
                    $rest = $drC - $len;
                ?>
                <circle cx="80" cy="80" r="<?= $drR ?>" fill="none"
                        stroke="<?= e($s['color']) ?>" stroke-width="24"
                        stroke-dasharray="<?= number_format($len, 2, '.', '') ?> <?= number_format($rest, 2, '.', '') ?>"
                        stroke-dashoffset="<?= number_format(-$drOff, 2, '.', '') ?>">
                    <title><?= e($s['label']) ?> — <?= number_format($s['n'], 0, ',', ' ') ?><?= e($drUnit) ?> (<?= round($s['n'] / $drTotal * 100) ?> %)</title>
                </circle>
                <?php $drOff += $s['n'] / $drTotal * $drC; ?>
            <?php endforeach; ?>
            </g>
        </svg>
        <div class="dv-center">
            <span class="dv-center-val<?= mb_strlen($drCenterVal) > 6 ? ' dv-center-val--sm' : '' ?>"><?= e($drCenterVal) ?></span>
            <span class="dv-center-label"><?= e($drCenterLabel) ?></span>
        </div>
    </div>
    <ul class="dv-legend">
    <?php foreach ($drSegments as $s): ?>
        <li class="dv-legend-row">
            <span class="dv-chip" style="background:<?= e($s['color']) ?>;"></span>
            <span class="dv-legend-ville"><?= e($s['label']) ?></span>
            <span class="dv-legend-n"><?= number_format($s['n'], 0, ',', ' ') ?><?= e($drUnit) ?></span>
            <span class="dv-legend-pct"><?= round($s['n'] / $drTotal * 100) ?> %</span>
        </li>
    <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
