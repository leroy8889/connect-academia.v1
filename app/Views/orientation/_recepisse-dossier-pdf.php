<?php
/**
 * Récépissé de dépôt de dossier — gabarit PDF (spec §6.3).
 *
 * Reproduit la maquette `docs/Récépissé de dépôt de dossier.png`.
 *
 * Contraintes dompdf : ni flexbox ni grid. Toute la mise en page passe par des
 * tables et des unités `pt`. Police DejaVu Sans (embarquée dans dompdf, gère
 * les accents) ; DejaVu Sans Mono pour la référence et la bande MRZ.
 *
 * Reçoit `$donnees` (voir RecepisseDossierService::rassembler). Le gabarit ne
 * fait AUCUNE requête : il affiche des chaînes déjà préparées.
 */
$h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$violet      = '#8B52FA';   // Purple Neon — unique accent chromatique
$violetClair = '#F3EFFF';   // Purple Tint
$encre       = '#1A1A1A';
$grisTexte   = '#4A4A4A';
$grisLabel   = '#9E9E9E';
$grisFond    = '#F5F5F7';
$hairline    = '#E8E8ED';

$d          = $donnees['dossier'];
$paliers    = $donnees['paliers'];
$documents  = $donnees['documents'];
$reference  = $donnees['reference'] !== '' ? $donnees['reference'] : 'En cours d’attribution';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Récépissé de dépôt de dossier <?= $h($donnees['reference']) ?></title>
<style>
/* @page margin 0 : la marge est portée par .page. Sans cela dompdf cumule les
   deux et le document se décale vers le bas à chaque page. */
@page { margin: 0; }
* { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: "DejaVu Sans", sans-serif;
  font-size: 8.5pt;
  color: <?= $encre ?>;
  line-height: 1.45;
}
.page { padding: 24pt 32pt 12pt 32pt; }

/* ── En-tête : établissement à gauche, Connect'Academia à droite ─────────── */
.entete { width: 100%; border-collapse: collapse; margin-bottom: 12pt; }
.entete td { vertical-align: middle; }
.entete-g { width: 58%; }
.entete-d { width: 42%; text-align: right; }

.logo-etab { max-height: 40pt; max-width: 46pt; vertical-align: middle; }
.logo-pastille {
  display: inline-block; width: 34pt; height: 34pt;
  background: <?= $violet ?>; border-radius: 8pt; color: #fff;
  font-size: 15pt; font-weight: bold; text-align: center; line-height: 34pt;
  vertical-align: middle;
}
.etab-nom   { font-size: 12pt; font-weight: bold; line-height: 1.15; }
.etab-sous  { font-size: 6.5pt; color: <?= $grisLabel ?>; letter-spacing: 0.9pt; text-transform: uppercase; margin-top: 2pt; }

.ca-nom     { font-size: 11pt; font-weight: bold; color: <?= $violet ?>; line-height: 1.15; }
.ca-sous    { font-size: 6pt; color: <?= $grisLabel ?>; letter-spacing: 0.9pt; text-transform: uppercase; margin-top: 2pt; }

/* ── Titre ───────────────────────────────────────────────────────────────── */
.eyebrow {
  text-align: center; font-size: 7pt; font-weight: bold; color: <?= $violet ?>;
  letter-spacing: 1.6pt; text-transform: uppercase; margin-bottom: 4pt;
}
.titre {
  text-align: center; font-size: 19.5pt; font-weight: bold;
  letter-spacing: -0.3pt; margin-bottom: 8pt;
}
.regle { height: 2.4pt; background: <?= $violet ?>; border-radius: 2pt; margin-bottom: 9pt; }

/* ── Bloc identification ─────────────────────────────────────────────────── */
.carte-id {
  border: 0.75pt solid <?= $hairline ?>;
  border-radius: 12pt;
  margin-bottom: 10pt;
}
.carte-bandeau {
  width: 100%; border-collapse: collapse;
  background: <?= $violet ?>;
  border-top-left-radius: 11pt; border-top-right-radius: 11pt;
}
.carte-bandeau td {
  color: #fff; font-size: 7pt; font-weight: bold;
  letter-spacing: 0.9pt; text-transform: uppercase;
  padding: 6.5pt 13pt;
}
.carte-bandeau .droite { text-align: right; font-weight: normal; opacity: 0.88; }

/* La trame guillochée signale le document officiel et rend une photocopie
   grossière visible. Purement décorative : absente, le document reste valide. */
.carte-corps {
  width: 100%; border-collapse: collapse;
  <?php if ($donnees['motif'] !== ''): ?>
  background-image: url('<?= $donnees['motif'] ?>');
  background-repeat: repeat;
  <?php endif; ?>
}
.carte-corps > tbody > tr > td { vertical-align: top; padding: 9pt 12pt; }
.col-photo { width: 90pt; }
.col-champs { padding-left: 4pt !important; }

.photo-cadre {
  width: 78pt; height: 94pt;
  border: 0.75pt solid <?= $hairline ?>;
  border-radius: 8pt;
  background: <?= $grisFond ?>;
  text-align: center;
  overflow: hidden;
}
.photo-cadre img { width: 78pt; height: 94pt; }
.photo-vide { font-size: 6.5pt; color: <?= $grisLabel ?>; padding-top: 36pt; line-height: 1.4; }
.photo-label { font-size: 5.4pt; color: <?= $violet ?>; letter-spacing: 0.5pt; text-transform: uppercase; margin-top: 5pt; font-weight: bold; white-space: nowrap; }

.champs { width: 100%; border-collapse: collapse; }
.champs td { vertical-align: top; padding: 0 0 5pt 0; }
.champs td.d { padding-left: 14pt; }
.label {
  display: block; font-size: 5.8pt; color: <?= $grisLabel ?>;
  letter-spacing: 0.7pt; text-transform: uppercase; margin-bottom: 1.5pt;
}
.valeur { display: block; font-size: 9pt; font-weight: bold; line-height: 1.3; }
.valeur.mono { font-family: "DejaVu Sans Mono", monospace; font-size: 14pt; letter-spacing: 0.5pt; }
.valeur.violet { color: <?= $violet ?>; font-size: 8.5pt; }
.champs .filet { border-bottom: 0.5pt solid <?= $hairline ?>; padding: 0 0 5pt 0 !important; }
.champs .apres-filet td { padding-top: 6pt !important; }

/* ── Bande de lecture optique ────────────────────────────────────────────── */
.mrz {
  background: <?= $encre ?>;
  border-bottom-left-radius: 11pt; border-bottom-right-radius: 11pt;
  padding: 7pt 12pt;
}
.mrz div {
  font-family: "DejaVu Sans Mono", monospace;
  font-size: 8.5pt; letter-spacing: 1.1pt; line-height: 1.35;
  white-space: nowrap;
}
.mrz .l1 { color: #FFFFFF; }
.mrz .l2 { color: #8A8A8A; }

/* ── Deux colonnes : pièces / vérification ───────────────────────────────── */
.duo { width: 100%; border-collapse: separate; border-spacing: 9pt 0; margin: 0 -9pt 8pt -9pt; }
.duo > tbody > tr > td { vertical-align: top; }
.duo-g { width: 55%; }
.duo-d { width: 45%; }

.bloc { border-radius: 12pt; padding: 11pt; }
.bloc-gris   { background: <?= $grisFond ?>; }
.bloc-violet { background: <?= $violetClair ?>; text-align: center; }

.bloc-titre {
  font-size: 6.5pt; color: <?= $violet ?>; font-weight: bold;
  letter-spacing: 0.9pt; text-transform: uppercase; margin-bottom: 7pt;
}

.pieces { width: 100%; border-collapse: collapse; }
.pieces td { font-size: 8.5pt; padding: 2.8pt 0; border-bottom: 0.5pt solid <?= $hairline ?>; }
.pieces tr:last-child td { border-bottom: 0; }
.pieces td.date { text-align: right; color: <?= $grisLabel ?>; font-size: 7.5pt; white-space: nowrap; }
.pieces tr.bourse td { color: <?= $violet ?>; font-weight: bold; }
.pieces tr.bourse td.date { color: <?= $violet ?>; font-weight: normal; }
.pieces-vide { font-size: 8pt; color: <?= $grisLabel ?>; font-style: italic; }

.progression-titre {
  font-size: 6pt; color: <?= $grisLabel ?>; font-weight: bold;
  letter-spacing: 0.8pt; text-transform: uppercase; margin: 9pt 0 5pt 0;
}
.paliers { width: 100%; border-collapse: separate; border-spacing: 3pt 0; }
.paliers td { height: 4pt; border-radius: 2pt; background: #DEDEE4; padding: 0; font-size: 0; line-height: 0; }
.paliers td.atteint { background: <?= $violet ?>; }
.paliers-noms { width: 100%; border-collapse: collapse; margin-top: 5pt; }
.paliers-noms td { font-size: 7pt; color: <?= $grisLabel ?>; }
.paliers-noms td.atteint { color: <?= $encre ?>; font-weight: bold; }
.paliers-noms td.c { text-align: center; }
.paliers-noms td.r { text-align: right; }

.qr-cadre { background: #fff; border-radius: 8pt; padding: 7pt; display: inline-block; }
.qr-cadre img { width: 90pt; height: 90pt; }
.code-court {
  font-family: "DejaVu Sans Mono", monospace;
  font-size: 14pt; font-weight: bold; letter-spacing: 2.4pt;
  margin-top: 8pt;
}
.qr-note { font-size: 6.8pt; color: <?= $grisTexte ?>; line-height: 1.4; margin-top: 6pt; }

/* ── Mention obligatoire ─────────────────────────────────────────────────── */
.mention {
  border: 1pt solid <?= $violet ?>;
  border-radius: 12pt;
  padding: 9pt 12pt;
  margin-bottom: 8pt;
}
.mention-titre {
  font-size: 6.5pt; color: <?= $violet ?>; font-weight: bold;
  letter-spacing: 0.9pt; text-transform: uppercase; margin-bottom: 5pt;
}
.mention-texte { font-size: 8.5pt; line-height: 1.55; }

.suite { font-size: 8.2pt; color: <?= $grisTexte ?>; line-height: 1.5; margin-bottom: 9pt; }

/* ── Pied de page ────────────────────────────────────────────────────────── */
.pied { width: 100%; border-collapse: collapse; border-top: 0.5pt solid <?= $hairline ?>; padding-top: 10pt; }
.pied td { vertical-align: top; padding-top: 7pt; font-size: 7.5pt; }
.pied .label { margin-bottom: 2pt; }
.pied .val { font-size: 7.5pt; color: <?= $encre ?>; }
.pied .mono { font-family: "DejaVu Sans Mono", monospace; }
.pied .lien { color: <?= $violet ?>; font-weight: bold; }
.pied .marque { text-align: right; font-size: 8pt; font-weight: bold; color: <?= $encre ?>; }
</style>
</head>
<body>
<div class="page">

  <!-- ══ En-tête ══ -->
  <table class="entete">
    <tr>
      <td class="entete-g">
        <table style="border-collapse:collapse"><tr>
          <td style="width:52pt;vertical-align:middle">
            <?php if ($donnees['logo_etab'] !== ''): ?>
              <img class="logo-etab" src="<?= $h($donnees['logo_etab']) ?>" alt="">
            <?php else: ?>
              <span class="logo-pastille"><?= $h(mb_substr((string)$d['etab_nom'], 0, 1)) ?></span>
            <?php endif; ?>
          </td>
          <td style="vertical-align:middle">
            <div class="etab-nom"><?= $h($d['etab_nom']) ?></div>
            <?php if (!empty($d['etab_ville'])): ?>
              <div class="etab-sous"><?= $h($d['etab_ville']) ?></div>
            <?php endif; ?>
          </td>
        </tr></table>
      </td>
      <td class="entete-d">
        <div class="ca-nom">Connect'Academia</div>
        <div class="ca-sous">Votre passerelle vers l'avenir</div>
      </td>
    </tr>
  </table>

  <div class="eyebrow">Année académique <?= $h($donnees['annee_libelle']) ?></div>
  <div class="titre">Récépissé de dépôt de dossier</div>
  <div class="regle"></div>

  <!-- ══ Identification du dossier ══ -->
  <div class="carte-id">

    <table class="carte-bandeau">
      <tr>
        <td>Identification du dossier / File identification</td>
        <td class="droite">Statut à l'émission : <?= $h($donnees['statut_libelle']) ?> — <?= (int)$donnees['progression'] ?> %</td>
      </tr>
    </table>

    <table class="carte-corps">
      <tr>
        <td class="col-photo">
          <div class="photo-cadre">
            <?php if ($donnees['photo'] !== ''): ?>
              <img src="<?= $h($donnees['photo']) ?>" alt="">
            <?php else: ?>
              <div class="photo-vide">Photo<br>d'identité<br>non fournie</div>
            <?php endif; ?>
          </div>
          <div class="photo-label">Photo / Photograph</div>
        </td>
        <td class="col-champs">
          <table class="champs">
            <tr>
              <td class="filet" style="width:58%">
                <span class="label">Référence du dossier / File reference</span>
                <span class="valeur mono"><?= $h($reference) ?></span>
              </td>
              <td class="filet d" style="text-align:right">
                <span class="label">Dépôt / Filed on</span>
                <span class="valeur"><?= $h($donnees['depot']->format('d/m/Y')) ?> · <?= $h($donnees['depot']->format('H\hi')) ?></span>
              </td>
            </tr>
            <tr class="apres-filet">
              <td>
                <span class="label">Nom / Surname</span>
                <span class="valeur"><?= $h($donnees['nom']) ?></span>
              </td>
              <td class="d">
                <span class="label">Prénoms / Given names</span>
                <span class="valeur"><?= $h($donnees['prenoms']) ?></span>
              </td>
            </tr>
            <tr>
              <td>
                <span class="label">Naissance / Date &amp; place of birth</span>
                <span class="valeur" style="font-weight:normal"><?= $h($donnees['naissance']) ?></span>
              </td>
              <td class="d">
                <span class="label">Téléphone / Phone</span>
                <span class="valeur" style="font-weight:normal"><?= $h($donnees['telephone']) ?></span>
              </td>
            </tr>
            <tr>
              <td>
                <span class="label">Adresse électronique / Email</span>
                <span class="valeur" style="font-weight:normal"><?= $h($donnees['email']) ?></span>
              </td>
              <td class="d">
                <span class="label">Filière demandée / Programme</span>
                <span class="valeur" style="font-weight:normal"><?= $h($donnees['filiere']) ?></span>
              </td>
            </tr>
            <tr>
              <td colspan="2" style="padding-bottom:0">
                <span class="label">Situation de bourse déclarée / Scholarship status</span>
                <span class="valeur violet"><?= $h($donnees['bourse']) ?></span>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>

    <!-- Bande de lecture optique : relève manuelle de la référence et du code
         court en caractères non ambigus, quand le scan est impossible. -->
    <div class="mrz">
      <div class="l1"><?= $h($donnees['mrz'][0]) ?></div>
      <div class="l2"><?= $h($donnees['mrz'][1]) ?></div>
    </div>
  </div>

  <!-- ══ Pièces reçues / Vérification ══ -->
  <table class="duo">
    <tr>
      <td class="duo-g">
        <div class="bloc bloc-gris">
          <div class="bloc-titre">Pièces reçues / Documents received</div>

          <?php if ($documents === []): ?>
            <div class="pieces-vide">Aucune pièce enregistrée à ce jour.</div>
          <?php else: ?>
            <table class="pieces">
              <?php foreach ($documents as $piece): ?>
              <tr class="<?= $piece['bourse'] ? 'bourse' : '' ?>">
                <td><?= $h($piece['libelle']) ?></td>
                <td class="date"><?= $h($piece['date']) ?></td>
              </tr>
              <?php endforeach; ?>
            </table>
          <?php endif; ?>

          <div class="progression-titre">Progression de l'inscription</div>
          <table class="paliers">
            <tr>
              <?php foreach ($paliers as $palier): ?>
                <td class="<?= $palier['atteint'] ? 'atteint' : '' ?>">&nbsp;</td>
              <?php endforeach; ?>
            </tr>
          </table>
          <table class="paliers-noms">
            <tr>
              <?php foreach ($paliers as $i => $palier):
                $align = $i === 0 ? '' : ($i === count($paliers) - 1 ? 'r' : 'c');
              ?>
                <td class="<?= trim($align . ($palier['atteint'] ? ' atteint' : '')) ?>"><?= $h($palier['libelle']) ?></td>
              <?php endforeach; ?>
            </tr>
          </table>
        </div>
      </td>

      <td class="duo-d">
        <div class="bloc bloc-violet">
          <div class="bloc-titre" style="text-align:left">Vérification / Verification</div>

          <?php if ($donnees['qr'] !== ''): ?>
            <div class="qr-cadre"><img src="<?= $h($donnees['qr']) ?>" alt=""></div>
          <?php endif; ?>

          <?php if ($donnees['code_court'] !== ''): ?>
            <div class="code-court"><?= $h($donnees['code_court']) ?></div>
          <?php endif; ?>

          <div class="qr-note">
            Ce document doit être vérifié par scan du code ci-dessus.
            À défaut, saisir le code court à 8 caractères dans le back-office.
          </div>
        </div>
      </td>
    </tr>
  </table>

  <!-- ══ Mention obligatoire (spec §6.3) ══ -->
  <div class="mention">
    <div class="mention-titre">Mention obligatoire</div>
    <div class="mention-texte">
      Ce récépissé atteste du dépôt d'un dossier de candidature. Il ne constitue pas
      une attestation d'inscription. L'inscription ne devient définitive qu'après
      validation par l'établissement et règlement des frais de scolarité.
    </div>
  </div>

  <div class="suite">
    Prochaine étape : présente-toi à <?= $h($d['etab_nom']) ?> avec ce récépissé, imprimé
    ou sur ton téléphone. L'agent scanne le QR code et lit le statut réel de ton dossier.
  </div>

  <!-- ══ Pied de page ══ -->
  <table class="pied">
    <tr>
      <td style="width:26%">
        <span class="label">Généré le</span>
        <span class="val"><?= $h($donnees['genere_le']->format('d/m/Y')) ?> à <?= $h($donnees['genere_le']->format('H\hi')) ?></span>
      </td>
      <td style="width:26%">
        <span class="label">Empreinte du document</span>
        <span class="val mono">{{EMPREINTE}}</span>
      </td>
      <td style="width:28%">
        <span class="label">Vérification en ligne</span>
        <span class="val lien"><?= $h($donnees['url_verification']) ?></span>
      </td>
      <td class="marque" style="width:20%">Connect'Academia</td>
    </tr>
  </table>

</div>
</body>
</html>
