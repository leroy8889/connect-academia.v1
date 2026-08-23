<?php
/**
 * Facture proforma — fragment PDF ajouté à la suite de l'attestation de
 * préinscription (page 2 du même document). Injecté par
 * RecepisseService::renderPdfComplet() : ne pas inclure directement,
 * les classes .fp-* dépendent du <style> combiné.
 *
 * Variables : voir FactureProformaService::render().
 */
$h   = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$fmt = static fn(int $n): string => number_format($n, 0, ',', ' ') . ' FCFA';
$navy = '#1E3A5F';
?>
<style>
.fp-page { page-break-before: always; padding: 22pt 40pt 14pt 40pt; font-family: "DejaVu Sans", sans-serif; font-size: 9.5pt; color: #111; line-height: 1.4; }
.fp-entete { width: 100%; border-collapse: collapse; margin-bottom: 18pt; }
.fp-entete td { vertical-align: top; }
.fp-ent-g { width: 62%; }
.fp-ent-d { width: 38%; text-align: center; }
.fp-ent-nom { font-size: 13pt; font-weight: bold; color: <?= $h($navy) ?>; }
.fp-ent-sous { font-size: 8pt; color: #555; margin-top: 3pt; }
.fp-ent-logo { max-height: 56pt; max-width: 130pt; }
.fp-logo-fallback { display: inline-block; width: 46pt; height: 46pt; background: <?= $h($couleur) ?>; border-radius: 9pt; color: #fff; font-size: 20pt; font-weight: bold; text-align: center; line-height: 1; padding-top: 13pt; }
.fp-refbox { width: 100%; border-collapse: collapse; margin-bottom: 16pt; }
.fp-refbox td { border: 1.1pt solid <?= $h($navy) ?>; padding: 8pt 12pt; vertical-align: middle; }
.fp-refbox .fp-titre { font-weight: bold; font-size: 10pt; color: <?= $h($navy) ?>; width: 34%; }
.fp-refbox .fp-num { font-size: 8.5pt; color: #555; font-weight: normal; }
.fp-refbox .fp-candidat { font-weight: bold; font-size: 10.5pt; }
table.fp-tarifs { width: 100%; border-collapse: collapse; margin-bottom: 10pt; }
table.fp-tarifs th, table.fp-tarifs td { border: 0.9pt solid #ccc; padding: 7pt 8pt; font-size: 9pt; }
table.fp-tarifs th { background: <?= $h($navy) ?>; color: #fff; text-align: left; }
table.fp-tarifs td.fp-montant { text-align: right; white-space: nowrap; }
table.fp-tarifs tr.fp-total td { font-weight: bold; background: #f3f3f3; }
table.fp-tarifs tr.fp-ttc td { font-weight: bold; background: <?= $h($navy) ?>; color: #fff; }
.fp-arrete { font-size: 9.5pt; margin: 8pt 0; text-align: justify; }
table.fp-echeancier { width: 100%; border-collapse: collapse; margin-bottom: 10pt; }
table.fp-echeancier th, table.fp-echeancier td { border: 0.9pt solid #ccc; padding: 6pt 8pt; font-size: 8.5pt; }
table.fp-echeancier th { background: #f0f0f0; text-align: left; }
table.fp-echeancier td.fp-montant { text-align: right; }
.fp-pied { width: 100%; border-collapse: collapse; margin-top: 4pt; }
.fp-pied td { vertical-align: top; }
.fp-pied-g { width: 55%; font-size: 8.5pt; color: #555; line-height: 1.6; }
.fp-pied-d { width: 45%; text-align: center; font-size: 9pt; }
.fp-fait { margin-bottom: 24pt; }
.fp-service { font-weight: bold; text-decoration: underline; }
.fp-basdepage { margin-top: 8pt; padding-top: 5pt; border-top: 0.75pt solid #d8d8d8; text-align: center; font-size: 7.5pt; color: #666; }
.fp-qr img { width: 48pt; height: 48pt; border: 0.75pt solid #e2e2e2; border-radius: 5pt; padding: 3pt; }
.fp-qr-legende { font-size: 6.5pt; color: #555; line-height: 1.4; width: 130pt; margin-top: 4pt; }
.fp-cachet { max-width: 70pt; max-height: 52pt; margin: 0 auto 4pt auto; display: block; }
</style>

<div class="fp-page">

  <table class="fp-entete">
    <tr>
      <td class="fp-ent-g">
        <div class="fp-ent-nom"><?= $h($etabNom) ?></div>
        <?php if ($etabVille !== ''): ?><div class="fp-ent-sous"><?= $h($etabVille) ?>, Gabon</div><?php endif; ?>
        <?php if (!empty($etab['telephone']) || !empty($etab['email_contact'])): ?>
        <div class="fp-ent-sous">
          <?= !empty($etab['telephone']) ? 'Tél. ' . $h($etab['telephone']) : '' ?>
          <?= (!empty($etab['telephone']) && !empty($etab['email_contact'])) ? ' — ' : '' ?>
          <?= !empty($etab['email_contact']) ? $h($etab['email_contact']) : '' ?>
        </div>
        <?php endif; ?>
      </td>
      <td class="fp-ent-d">
        <?php if ($logoDataUri !== ''): ?>
          <img class="fp-ent-logo" src="<?= $h($logoDataUri) ?>" alt="">
        <?php else: ?>
          <span class="fp-logo-fallback"><?= $h($etabInitiale) ?></span>
        <?php endif; ?>
      </td>
    </tr>
  </table>

  <table class="fp-refbox">
    <tr>
      <td class="fp-titre">FACTURE PROFORMA<br><span class="fp-num"><?= $h($numeroFacture) ?></span></td>
      <td class="fp-candidat">M/Mme : <?= $h(mb_strtoupper($nomComplet)) ?></td>
    </tr>
  </table>

  <table class="fp-tarifs">
    <tr>
      <th>Référence</th>
      <th>Intitulé de la formation</th>
      <th>Année académique</th>
      <th>Nb. stagiaire</th>
      <th>Montant</th>
    </tr>
    <?php if ($fraisInscription > 0): ?>
    <tr>
      <td>1 - Inscription</td>
      <td><?= $h($filiereLibelle) ?></td>
      <td><?= $h($anneeUniv) ?></td>
      <td>1</td>
      <td class="fp-montant"><?= $fmt($fraisInscription) ?></td>
    </tr>
    <?php endif; ?>
    <?php if ($fraisScolarite > 0): ?>
    <tr>
      <td>2 - Scolarité</td>
      <td><?= $h($filiereLibelle) ?></td>
      <td>—</td>
      <td>1</td>
      <td class="fp-montant"><?= $fmt($fraisScolarite) ?></td>
    </tr>
    <?php endif; ?>
    <tr class="fp-total">
      <td colspan="4">Net à payer HTVA</td>
      <td class="fp-montant"><?= $fmt($total) ?></td>
    </tr>
    <tr>
      <td colspan="4">TVA 18%</td>
      <td class="fp-montant">—</td>
    </tr>
    <tr class="fp-ttc">
      <td colspan="4">Montant TTC</td>
      <td class="fp-montant"><?= $fmt($total) ?></td>
    </tr>
  </table>

  <p class="fp-arrete">Arrêté la présente facture proforma à la somme de <b><?= $fmt($total) ?></b>, à titre indicatif — les modalités de règlement et l'échéancier définitif seront confirmés par le service comptabilité de l'établissement.</p>

  <table class="fp-echeancier">
    <tr><th>Échéance</th><th>Montant</th></tr>
    <tr><td>À l'inscription</td><td class="fp-montant">—</td></tr>
    <tr><td>1er versement scolarité</td><td class="fp-montant">—</td></tr>
    <tr><td>Solde scolarité (réparti selon accord avec l'établissement)</td><td class="fp-montant">—</td></tr>
  </table>

  <table class="fp-pied">
    <tr>
      <td class="fp-pied-g">
        <?php if ($qrDataUri !== ''): ?>
          <div class="fp-qr"><img src="<?= $h($qrDataUri) ?>" alt=""></div>
          <div class="fp-qr-legende">Scannez ce QR code pour vérifier l'authenticité de ce document sur Connect'Academia.</div>
        <?php endif; ?>
        <p style="margin-top:10pt;">Document indicatif, sans valeur d'admission ni d'engagement de paiement. Il ne remplace pas la facture définitive émise après validation du dossier.</p>
      </td>
      <td class="fp-pied-d">
        <div class="fp-fait">Fait à <?= $h($etabVille) ?>, le <?= $h($dateStr) ?></div>
        <?php if ($cachetDataUri !== ''): ?>
          <img class="fp-cachet" src="<?= $h($cachetDataUri) ?>" alt="Cachet">
        <?php endif; ?>
        <div class="fp-service">La Comptabilité</div>
      </td>
    </tr>
  </table>

  <div class="fp-basdepage">
    <?= $h($etabNom) ?> — <?= $h($etabVille) ?>, Gabon — Facture proforma — <?= $h($numeroFacture) ?>
  </div>

</div>
