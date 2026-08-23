<?php
/**
 * Gabarit PDF du reçu de paiement d'inscription (rendu via dompdf).
 *
 * Dompdf ne supporte ni flexbox ni grid : mise en page 100 % tables.
 * Police DejaVu Sans (embarquée dans dompdf) pour les accents et le symbole XAF.
 *
 * Variables attendues :
 *  $numero, $dateEmission, $couleur, $etabNom, $etabVille, $etabInitiale,
 *  $logoDataUri (data URI ou ''), $nomComplet, $email, $telephone,
 *  $filiereLibelle, $niveauEntree, $montant (int, total),
 *  $prixInscription (int), $fraisDossier (int), $reference, $modePaiement
 */
$h = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$fmt = fn(int $v): string => number_format($v, 0, ',', ' ');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
@page { margin: 0; }
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: "DejaVu Sans", sans-serif; font-size: 10.5pt; color: #1a1a1a; line-height: 1.5; }
.band { background: <?= $h($couleur) ?>; padding: 26pt 40pt 22pt 40pt; color: #fff; }
.band-table { width: 100%; border-collapse: collapse; }
.logo-cell { width: 46pt; vertical-align: middle; }
.logo-box { width: 40pt; height: 40pt; background: rgba(255,255,255,0.22); border-radius: 8pt; text-align: center; font-size: 19pt; font-weight: bold; color: #fff; line-height: 40pt; }
.logo-img { width: 40pt; height: 40pt; border-radius: 8pt; }
.etab-cell { vertical-align: middle; padding-left: 10pt; }
.etab-name { font-size: 14.5pt; font-weight: bold; color: #fff; }
.etab-sub { font-size: 8.5pt; color: rgba(255,255,255,0.85); margin-top: 2pt; }
.badge-cell { text-align: right; vertical-align: middle; }
.paid-badge { display: inline-block; background: #dcfce7; color: #166534; border-radius: 20pt; padding: 4pt 12pt; font-size: 8.5pt; font-weight: bold; }
.page { padding: 28pt 40pt 20pt 40pt; }
.doc-title { font-size: 17pt; font-weight: bold; }
.doc-numero { font-size: 10.5pt; font-weight: bold; color: <?= $h($couleur) ?>; margin-top: 2pt; }
.doc-date { font-size: 8.5pt; color: #888; margin-top: 2pt; margin-bottom: 16pt; }
.sec-title { font-size: 7.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: 1.2pt; color: #999; border-bottom: 0.75pt solid #e5e5e5; padding-bottom: 4pt; margin: 16pt 0 8pt 0; }
.info-table { width: 100%; border-collapse: separate; border-spacing: 4pt; margin-left: -4pt; margin-right: -4pt; }
.info-cell { background: #f7f6fc; border-radius: 6pt; padding: 7pt 10pt; width: 50%; vertical-align: top; }
.info-label { font-size: 7pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.6pt; color: #9a94b0; }
.info-value { font-size: 10pt; font-weight: bold; color: #1a1a1a; margin-top: 1pt; }
.pay-table { width: 100%; border-collapse: collapse; margin-top: 4pt; }
.pay-table th { text-align: left; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.8pt; color: #999; padding: 6pt 10pt; border-bottom: 1pt solid #e5e5e5; }
.pay-table th.num, .pay-table td.num { text-align: right; }
.pay-table td { font-size: 10pt; padding: 7pt 10pt; border-bottom: 0.5pt solid #efefef; }
.total-row td { border-top: 1.5pt solid <?= $h($couleur) ?>; border-bottom: none; font-weight: bold; font-size: 12pt; color: <?= $h($couleur) ?>; padding-top: 9pt; }
.meta-table { width: 100%; border-collapse: collapse; margin-top: 12pt; }
.meta-table td { font-size: 8.5pt; color: #777; padding: 2pt 0; }
.meta-table td.k { width: 130pt; color: #aaa; }
.footer-table { width: 100%; border-collapse: collapse; margin-top: 34pt; }
.sig-cell { width: 50%; vertical-align: bottom; }
.sig-line { width: 150pt; border-top: 1pt solid #333; padding-top: 5pt; font-size: 8pt; color: #777; text-align: center; }
.stamp-cell { text-align: right; vertical-align: bottom; }
.stamp-box { display: inline-block; width: 95pt; height: 62pt; border: 1pt dashed #ccc; border-radius: 6pt; font-size: 8pt; color: #ccc; text-align: center; padding-top: 22pt; }
.disclaimer { margin-top: 20pt; background: #f5f5f5; border-radius: 6pt; padding: 8pt 12pt; font-size: 7.5pt; color: #888; text-align: center; line-height: 1.5; }
.branding { text-align: center; margin-top: 10pt; font-size: 7.5pt; color: #bbb; }
</style>
</head>
<body>

<div class="band">
  <table class="band-table">
    <tr>
      <td class="logo-cell">
        <?php if (!empty($logoDataUri)): ?>
          <img class="logo-img" src="<?= $logoDataUri ?>" alt="">
        <?php else: ?>
          <div class="logo-box"><?= $h($etabInitiale) ?></div>
        <?php endif; ?>
      </td>
      <td class="etab-cell">
        <div class="etab-name"><?= $h($etabNom) ?></div>
        <div class="etab-sub"><?= $h($etabVille !== '' ? $etabVille . ' — ' : '') ?>Établissement d'enseignement supérieur</div>
      </td>
      <td class="badge-cell">
        <span class="paid-badge">✓ PAIEMENT CONFIRMÉ</span>
      </td>
    </tr>
  </table>
</div>

<div class="page">

  <div class="doc-title">Reçu de paiement — Inscription</div>
  <div class="doc-numero"><?= $h($numero) ?></div>
  <div class="doc-date">Émis le <?= $h($dateEmission) ?></div>

  <div class="sec-title">Candidat</div>
  <table class="info-table">
    <tr>
      <td class="info-cell">
        <div class="info-label">Nom complet</div>
        <div class="info-value"><?= $h($nomComplet) ?></div>
      </td>
      <td class="info-cell">
        <div class="info-label">E-mail</div>
        <div class="info-value"><?= $h($email) ?></div>
      </td>
    </tr>
    <tr>
      <td class="info-cell">
        <div class="info-label">Téléphone</div>
        <div class="info-value"><?= $h($telephone) ?></div>
      </td>
      <td class="info-cell">
        <div class="info-label">Établissement</div>
        <div class="info-value"><?= $h($etabNom) ?></div>
      </td>
    </tr>
  </table>

  <div class="sec-title">Formation</div>
  <table class="info-table">
    <tr>
      <td class="info-cell">
        <div class="info-label">Filière</div>
        <div class="info-value"><?= $h($filiereLibelle) ?></div>
      </td>
      <td class="info-cell">
        <div class="info-label">Niveau d'entrée</div>
        <div class="info-value"><?= $h($niveauEntree) ?></div>
      </td>
    </tr>
  </table>

  <div class="sec-title">Détail du paiement</div>
  <table class="pay-table">
    <tr>
      <th>Désignation</th>
      <th class="num">Montant</th>
    </tr>
    <?php if ($prixInscription > 0): ?>
    <tr>
      <td>Frais d'inscription</td>
      <td class="num"><?= $fmt($prixInscription) ?> XAF</td>
    </tr>
    <?php endif; ?>
    <?php if ($fraisDossier > 0): ?>
    <tr>
      <td>Frais de dossier</td>
      <td class="num"><?= $fmt($fraisDossier) ?> XAF</td>
    </tr>
    <?php endif; ?>
    <tr class="total-row">
      <td>Total payé</td>
      <td class="num"><?= $fmt($montant) ?> XAF</td>
    </tr>
  </table>

  <table class="meta-table">
    <tr><td class="k">Mode de paiement</td><td><?= $h($modePaiement) ?></td></tr>
    <tr><td class="k">Référence transaction</td><td><?= $h($reference) ?></td></tr>
    <tr><td class="k">Statut</td><td>Payé — fonds reçus par l'établissement</td></tr>
  </table>

  <table class="footer-table">
    <tr>
      <td class="sig-cell">
        <div class="sig-line">Signature du responsable</div>
      </td>
      <td class="stamp-cell">
        <div class="stamp-box">Cachet<br>officiel</div>
      </td>
    </tr>
  </table>

  <div class="disclaimer">
    Ce document est un reçu de paiement officiel délivré par <?= $h($etabNom) ?> via la plateforme Connect'Academia.
    Conservez-le pour votre dossier. En cas de litige, ce document fait foi.
  </div>

  <div class="branding">Généré via Connect'Academia · connectacademia.io</div>

</div>

</body>
</html>
