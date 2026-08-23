<?php
/**
 * Gabarit PDF des bilans d'activité (rendu via dompdf).
 *
 * Dompdf ne supporte ni flexbox ni grid → mise en page 100 % tables.
 * Police DejaVu Sans (embarquée) pour accents. Style Connect'Academia :
 * bande d'accent violet, aplats crème, hairlines, radii 8-12, charbon.
 *
 * Variables (portée de BilansController::export) :
 *  $type, $rows, $titre, $sousTitre, $portee, $couleur, $periode,
 *  $dateEmission, $reference, $total, $filieres, $statuts, $etabId
 */
$h   = fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$dmy = fn($v): string => $v ? date('d/m/Y', strtotime((string)$v)) : '—';

$showEtab = ($type !== 'utilisateurs' && ($etabId ?? null) === null);

// Répartition pour le bandeau de synthèse.
$repartition = [];
if ($type === 'utilisateurs') {
    foreach ($rows as $r) {
        $k = (string)($r['role'] ?? 'eleve');
        $repartition[$k] = ($repartition[$k] ?? 0) + 1;
    }
    $repLabels = ['eleve' => 'Élèves', 'gestionnaire' => 'Gestionnaires', 'admin' => 'Admins', 'visiteur' => 'Visiteurs'];
} else {
    foreach ($rows as $r) {
        $k = (string)($r['statut'] ?? '');
        $repartition[$k] = ($repartition[$k] ?? 0) + 1;
    }
    $repLabels = $statuts;
}

$tel = static function (array $r): string {
    $num = trim((string)($r['telephone'] ?? ''));
    if ($num === '') return '—';
    if (str_starts_with($num, '+')) return $num;
    return trim((string)($r['indicatif_tel'] ?? '+241')) . ' ' . $num;
};
$badge = static function (string $statut): array {
    // [fond, texte] par statut.
    return match ($statut) {
        'inscrit', 'accepte' => ['#dcfce7', '#166534'],
        'paye'               => ['#e0e7ff', '#3730a3'],
        'soumis'             => ['#fef3c7', '#92400e'],
        'refuse'             => ['#fee2e2', '#991b1b'],
        default              => ['#eee', '#555'],
    };
};
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  @page { margin: 40pt 34pt 46pt 34pt; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: "DejaVu Sans", sans-serif; font-size: 9pt; color: #1c1a22; line-height: 1.45; }

  /* En-tête plein cadre (bleed sur les marges de la 1re page) */
  .band {
    margin: -40pt -34pt 0 -34pt;
    padding: 36pt 34pt 18pt 34pt;
    background-color: <?= $h($couleur) ?>;
    background-image: linear-gradient(120deg, <?= $h($couleur) ?> 0%, <?= $h($accent2) ?> 165%);
    color: #fff;
  }
  /* Fin liseré bicolore sous la bande */
  .band-rule { height: 3pt; margin: 0 -34pt 15pt -34pt; background-color: <?= $h($coral) ?>; }
  .band-table { width: 100%; border-collapse: collapse; }
  .logo-box {
    width: 36pt; height: 36pt; background: rgba(255,255,255,0.18);
    border: 0.75pt solid rgba(255,255,255,0.35);
    border-radius: 10pt; text-align: center; line-height: 36pt;
    font-size: 15pt; font-weight: bold; color: #fff;
  }
  .brand-name { font-size: 12.5pt; font-weight: bold; letter-spacing: 0.2pt; }
  .brand-sub  { font-size: 7.5pt; color: rgba(255,255,255,0.82); margin-top: 2pt; }
  .band-doc   { text-align: right; vertical-align: middle; }
  .band-doc .k { font-size: 6.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: 1.6pt; color: rgba(255,255,255,0.72); }
  .band-doc .t { font-size: 13pt; font-weight: bold; margin-top: 2pt; }
  .band-doc .s { font-size: 7.5pt; color: rgba(255,255,255,0.82); margin-top: 2pt; }

  /* Pied de document (fin de liste) */
  .foot {
    margin-top: 22pt; text-align: center; font-size: 7pt; color: #b3aec2;
    border-top: 0.5pt solid #ece9f4; padding-top: 9pt;
  }
  .foot strong { color: <?= $h($couleur) ?>; }

  /* Carte de contexte — bord d'accent à gauche */
  .ctx { background: #f8f7fd; border: 0.75pt solid #ebe8f6; border-left: 3pt solid <?= $h($couleur) ?>; border-radius: 10pt; padding: 11pt 15pt; margin-bottom: 13pt; }
  .ctx-table { width: 100%; border-collapse: collapse; }
  .ctx-cell { width: 25%; vertical-align: top; padding-right: 8pt; }
  .ctx-k { font-size: 6.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.7pt; color: #9a94b0; }
  .ctx-v { font-size: 10pt; font-weight: bold; color: #1c1a22; margin-top: 3pt; }
  .ctx-v.accent { color: <?= $h($couleur) ?>; }

  /* Bandeau de synthèse (compteurs) */
  .sum { width: 100%; border-collapse: separate; border-spacing: 6pt; margin: 0 -6pt 14pt -6pt; }
  .sum-cell { background: #fff; border: 0.75pt solid #ebe8f6; border-top: 2.5pt solid #d9d3ec; border-radius: 10pt; padding: 9pt 10pt; text-align: center; }
  .sum-cell.total {
    border: none;
    background-color: <?= $h($couleur) ?>;
    background-image: linear-gradient(125deg, <?= $h($couleur) ?> 0%, <?= $h($accent2) ?> 155%);
  }
  .sum-n { font-size: 16pt; font-weight: bold; color: #1c1a22; }
  .sum-cell.total .sum-n { color: #fff; }
  .sum-l { font-size: 6.3pt; text-transform: uppercase; letter-spacing: 0.6pt; color: #9a94b0; margin-top: 3pt; }
  .sum-cell.total .sum-l { color: rgba(255,255,255,0.88); }

  /* Tableau de données — carte encadrée */
  .sec-head { margin: 4pt 0 7pt 1pt; }
  .sec-title { font-size: 8pt; font-weight: bold; text-transform: uppercase; letter-spacing: 1.4pt; color: #6f6887; }
  .sec-count { font-size: 8pt; color: <?= $h($couleur) ?>; font-weight: bold; }
  .data-wrap { border: 0.75pt solid #ebe8f6; border-radius: 10pt; overflow: hidden; }
  table.data { width: 100%; border-collapse: collapse; }
  table.data th {
    text-align: left; font-size: 6.8pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5pt;
    color: #fff; background-color: <?= $h($couleur) ?>; padding: 7pt 8pt;
  }
  table.data td { font-size: 8pt; padding: 6.5pt 8pt; border-bottom: 0.5pt solid #efedf6; vertical-align: top; }
  table.data tr:last-child td { border-bottom: none; }
  table.data tr.alt td { background: #faf9fd; }
  .num { text-align: right; }
  .muted { color: #8a8598; }
  .strong { font-weight: bold; color: #12101a; }
  .chip { display: inline-block; border-radius: 20pt; padding: 2.5pt 8pt; font-size: 6.8pt; font-weight: bold; }
  .empty { text-align: center; color: #a7a2b5; font-style: italic; padding: 30pt 0; border: 0.75pt dashed #e2def0; border-radius: 10pt; }
</style>
</head>
<body>

<div class="band">
  <table class="band-table">
    <tr>
      <td style="width:44pt; vertical-align:middle;"><div class="logo-box">CA</div></td>
      <td style="vertical-align:middle; padding-left:9pt;">
        <div class="brand-name">Connect'Academia</div>
        <div class="brand-sub">Plateforme éducative — Gabon</div>
      </td>
      <td class="band-doc">
        <div class="k">Rapport d'activité</div>
        <div class="t"><?= $h($titre) ?></div>
        <div class="s"><?= $h($sousTitre) ?> · <?= $h($reference) ?></div>
      </td>
    </tr>
  </table>
</div>
<div class="band-rule"></div>

<!-- Contexte -->
<div class="ctx">
  <table class="ctx-table">
    <tr>
      <td class="ctx-cell">
        <div class="ctx-k">Portée</div>
        <div class="ctx-v accent"><?= $h($portee) ?></div>
      </td>
      <td class="ctx-cell">
        <div class="ctx-k">Période</div>
        <div class="ctx-v"><?= $h($periode) ?></div>
      </td>
      <td class="ctx-cell">
        <div class="ctx-k">Total</div>
        <div class="ctx-v"><?= number_format($total, 0, ',', ' ') ?> entrée<?= $total > 1 ? 's' : '' ?></div>
      </td>
      <td class="ctx-cell">
        <div class="ctx-k">Émis le</div>
        <div class="ctx-v"><?= $h($dateEmission) ?></div>
      </td>
    </tr>
  </table>
</div>

<!-- Synthèse -->
<?php if ($total > 0): ?>
<table class="sum">
  <tr>
    <td class="sum-cell total">
      <div class="sum-n"><?= number_format($total, 0, ',', ' ') ?></div>
      <div class="sum-l">Total</div>
    </td>
    <?php foreach ($repLabels as $cle => $lib): if (($repartition[$cle] ?? 0) === 0) continue; ?>
    <td class="sum-cell">
      <div class="sum-n"><?= number_format($repartition[$cle], 0, ',', ' ') ?></div>
      <div class="sum-l"><?= $h($lib) ?></div>
    </td>
    <?php endforeach; ?>
  </tr>
</table>
<?php endif; ?>

<table class="sec-head" style="width:100%; border-collapse:collapse;"><tr>
  <td><span class="sec-title">Détail</span></td>
  <td style="text-align:right;"><span class="sec-count"><?= number_format($total, 0, ',', ' ') ?> ligne<?= $total > 1 ? 's' : '' ?></span></td>
</tr></table>

<?php if ($total === 0): ?>
  <div class="empty">Aucune donnée pour cette période et cette portée.</div>

<?php elseif ($type === 'utilisateurs'): ?>
  <div class="data-wrap">
  <table class="data">
    <thead>
      <tr>
        <th style="width:20pt;">N°</th>
        <th>Nom &amp; prénom</th>
        <th>E-mail</th>
        <th style="width:52pt;">Rôle</th>
        <th style="width:34pt;">Série</th>
        <th>Établissement</th>
        <th style="width:40pt;">Statut</th>
        <th style="width:52pt;">Inscrit le</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $i => $r): ?>
      <tr class="<?= $i % 2 ? 'alt' : '' ?>">
        <td class="muted"><?= $i + 1 ?></td>
        <td class="strong"><?= $h(trim(($r['nom'] ?? '') . ' ' . ($r['prenom'] ?? ''))) ?: '—' ?></td>
        <td><?= $h($r['email'] ?? '—') ?></td>
        <td><?= $h(ucfirst((string)($r['role'] ?? '—'))) ?></td>
        <td><?= $h($r['serie_nom'] ?? '—') ?></td>
        <td><?= $h($r['etablissement'] ?: '—') ?></td>
        <td>
          <?php $ok = !empty($r['is_active']); $c = $ok ? ['#dcfce7', '#166534'] : ['#fee2e2', '#991b1b']; ?>
          <span class="chip" style="background:<?= $c[0] ?>; color:<?= $c[1] ?>;"><?= $ok ? 'Actif' : 'Inactif' ?></span>
        </td>
        <td class="muted"><?= $dmy($r['created_at'] ?? '') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>

<?php else: ?>
  <div class="data-wrap">
  <table class="data">
    <thead>
      <tr>
        <th style="width:20pt;">N°</th>
        <th>Candidat</th>
        <th>Contact</th>
        <?php if ($showEtab): ?><th>Établissement</th><?php endif; ?>
        <th>Filière / Niveau</th>
        <th style="width:46pt;">Ville</th>
        <th style="width:44pt;">Statut</th>
        <th style="width:52pt;">Déposé le</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $i => $r):
        $fil = (string)($r['filiere_visee'] ?? '');
        $filLib = $filieres[$fil] ?? ($fil !== '' ? $fil : '—');
        $spec = trim((string)($r['specialite_visee'] ?? ''));
        if ($spec !== '') $filLib .= ' · ' . $spec;
        $st = (string)($r['statut'] ?? '');
        $bc = $badge($st);
      ?>
      <tr class="<?= $i % 2 ? 'alt' : '' ?>">
        <td class="muted"><?= $i + 1 ?></td>
        <td class="strong"><?= $h($r['nom_complet'] ?: '—') ?></td>
        <td>
          <?= $h($r['email'] ?: '—') ?><br>
          <span class="muted"><?= $h($tel($r)) ?></span>
        </td>
        <?php if ($showEtab): ?><td><?= $h($r['etab_nom'] ?? '—') ?></td><?php endif; ?>
        <td>
          <?= $h($filLib) ?><br>
          <span class="muted"><?= $h($r['niveau_entree'] ?: '—') ?></span>
        </td>
        <td><?= $h($r['ville'] ?: '—') ?></td>
        <td><span class="chip" style="background:<?= $bc[0] ?>; color:<?= $bc[1] ?>;"><?= $h($statuts[$st] ?? $st) ?></span></td>
        <td class="muted"><?= $dmy($r['date_creation'] ?? '') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
<?php endif; ?>

<div class="foot">
  <strong>Connect'Academia</strong> · Document généré le <?= $h($dateEmission) ?> · connectacademia.io · Usage interne
</div>

</body>
</html>
