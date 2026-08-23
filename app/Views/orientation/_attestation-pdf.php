<?php
/**
 * Attestation d'inscription — gabarit PDF (rendu via dompdf).
 *
 * Jumeau de _attestation.php (version écran). Dompdf ne supporte ni flexbox ni
 * grid : mise en page 100 % tables, unités « pt », police DejaVu Sans.
 *
 * Générer un vrai PDF plutôt qu'une page à imprimer supprime définitivement
 * l'en-tête / pied de page du navigateur (URL de la page, date, titre).
 *
 * Variables : voir AttestationService::rendre().
 */
$h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$navy = '#1E3A5F';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Attestation d'inscription <?= $h($numero) ?></title>
<style>
@page { margin: 0; }
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5pt; color: #111; line-height: 1.5; }
.page { padding: 30pt 40pt 24pt 40pt; }

.entete { width: 100%; border-collapse: collapse; margin-bottom: 22pt; }
.entete td { vertical-align: top; }
.ent-g { width: 38%; }
.ent-c { width: 24%; text-align: center; }
.ent-d { width: 38%; text-align: right; }
.ent-titre { font-size: 10pt; font-weight: bold; line-height: 1.3; }
.ent-sous { font-size: 7.5pt; line-height: 1.4; margin-top: 4pt; }
.ent-logo { max-height: 56pt; max-width: 130pt; }
.logo-fallback { display: inline-block; width: 46pt; height: 46pt; background: <?= $h($couleur) ?>; border-radius: 9pt; color: #fff; font-size: 20pt; font-weight: bold; text-align: center; line-height: 1; padding-top: 13pt; }

.bandeau { background: <?= $h($navy) ?>; color: #fff; border-radius: 10pt; text-align: center; padding: 11pt 14pt; margin: 0 26pt 8pt 26pt; font-size: 14pt; font-weight: bold; letter-spacing: 1.6pt; text-transform: uppercase; }
.sous-bandeau { text-align: center; font-size: 9pt; margin-bottom: 20pt; }

.intro { font-size: 10pt; margin-bottom: 13pt; }
.cadre { position: relative; border: 1.4pt solid <?= $h($navy) ?>; border-radius: 13pt; padding: 17pt 22pt; margin-bottom: 16pt; }
.filigrane { position: absolute; top: 22pt; left: 120pt; width: 250pt; text-align: center; }
.filigrane img { width: 230pt; opacity: 0.07; }
.ligne { font-size: 10pt; margin-bottom: 8pt; }
.ligne.last { margin-bottom: 0; }
.sep { display: inline-block; width: 16pt; }
.texte { font-size: 10pt; margin-bottom: 11pt; text-align: justify; }

.pied { width: 100%; border-collapse: collapse; margin-top: 26pt; }
.pied td { vertical-align: bottom; }
.pied-g { width: 46%; }
.pied-d { width: 54%; text-align: center; }
.qr img { width: 74pt; height: 74pt; border: 0.75pt solid #e2e2e2; border-radius: 5pt; padding: 3pt; }
.qr-legende { font-size: 6.5pt; color: #555; line-height: 1.4; width: 130pt; margin-top: 4pt; }
.fait { font-size: 9.5pt; margin-bottom: 6pt; }
.cachet { max-height: 74pt; max-width: 150pt; margin-bottom: 2pt; }
.logo-pied { max-height: 32pt; max-width: 90pt; margin-bottom: 5pt; }
.service { font-size: 9.5pt; font-weight: bold; text-decoration: underline; }

.basdepage { margin-top: 26pt; padding-top: 9pt; border-top: 0.75pt solid #d8d8d8; text-align: center; font-size: 7.5pt; color: #666; }
</style>
</head>
<body>
<div class="page">

  <table class="entete">
    <tr>
      <td class="ent-g">
        <div class="ent-titre">Ministère de l'Enseignement Supérieur</div>
        <div class="ent-sous">de la Recherche Scientifique, du Transfert de Technologie, de la Culture et des Arts</div>
      </td>
      <td class="ent-c">
        <?php if ($logoDataUri !== ''): ?>
          <img class="ent-logo" src="<?= $h($logoDataUri) ?>" alt="">
        <?php else: ?>
          <span class="logo-fallback"><?= $h($etabInitiale) ?></span>
        <?php endif; ?>
      </td>
      <td class="ent-d">
        <div class="ent-titre">RÉPUBLIQUE GABONAISE</div>
        <div class="ent-sous">Union — Travail — Justice</div>
      </td>
    </tr>
  </table>

  <div class="bandeau">Attestation d'Inscription</div>
  <div class="sous-bandeau">Année universitaire <?= $h($anneeUniv) ?></div>

  <p class="intro">Nous soussignés, <b><?= $h($etabNom) ?></b>, attestons que l'étudiant(e) :</p>

  <div class="cadre">
    <?php if ($logoDataUri !== ''): ?>
    <div class="filigrane"><img src="<?= $h($logoDataUri) ?>" alt=""></div>
    <?php endif; ?>
    <div class="ligne"><b>Nom &amp; Prénom :</b> <?= $h($nomComplet) ?></div>
    <div class="ligne"><b>Né(e) le :</b> <?= $h($dateNaissance) ?> <span class="sep"></span> <b>à</b> <?= $h($lieuNaissance) ?></div>
    <div class="ligne"><b>Sexe :</b> <?= $h($genreLibelle) ?></div>
    <div class="ligne"><b>Adresse :</b> <?= $h($adresse) ?></div>
    <div class="ligne"><b>Téléphone :</b> <?= $h($telephone) ?> <span class="sep"></span> <b>Email :</b> <?= $h($email) ?></div>
    <div class="ligne"><b>Filière :</b> <?= $h($filiereLibelle) ?></div>
    <div class="ligne"><b>Niveau :</b> <?= $h($niveauEntree) ?></div>
    <div class="ligne last"><b>N° d'inscription :</b> <?= $h($numero) ?></div>
  </div>

  <p class="texte">est régulièrement inscrit(e) dans notre établissement pour l'année universitaire <b><?= $h($anneeUniv) ?></b>.</p>

  <p class="texte">En foi de quoi nous lui délivrons la présente attestation d'inscription pour servir et valoir ce que de droit.</p>

  <table class="pied">
    <tr>
      <td class="pied-g">
        <?php if ($qrDataUri !== ''): ?>
          <div class="qr"><img src="<?= $h($qrDataUri) ?>" alt=""></div>
          <div class="qr-legende">Scannez ce QR code pour vérifier l'authenticité de ce document sur Connect'Academia.</div>
        <?php endif; ?>
      </td>
      <td class="pied-d">
        <div class="fait">Fait à <?= $h($etabVille) ?>, le <?= $h($dateEmissionStr) ?></div>
        <?php if ($signatureDataUri !== ''): ?>
          <img class="cachet" src="<?= $h($signatureDataUri) ?>" alt="">
        <?php elseif ($logoDataUri !== ''): ?>
          <img class="logo-pied" src="<?= $h($logoDataUri) ?>" alt="">
        <?php endif; ?>
        <div class="service">La Direction Académique</div>
      </td>
    </tr>
  </table>

  <div class="basdepage">
    <?= $h($etabNom) ?> — <?= $h($etabVille) ?>, Gabon — Attestation d'inscription — <?= $h($numero) ?>
  </div>

</div>
</body>
</html>
