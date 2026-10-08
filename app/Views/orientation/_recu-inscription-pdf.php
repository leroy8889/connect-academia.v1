<?php
/**
 * Reçu de règlement de scolarité — gabarit PDF.
 *
 * Reçoit `$donnees` (voir RecuInscriptionService::rassembler). Le gabarit ne
 * fait AUCUNE requête : il affiche des chaînes déjà préparées.
 *
 * ── Couleurs et typographie ────────────────────────────────────────────────
 * Palette Connect'Academia : Purple Neon #8B52FA comme accent UNIQUE, encre
 * #1A1A1A, gris de hiérarchie #4A4A4A / #9E9E9E, fond crème #FAF9F5.
 * La famille du produit est Montserrat, indisponible côté dompdf (aucun
 * fichier de police dans le dépôt, et les polices distantes sont hors de
 * portée du moteur). On garde donc DejaVu Sans, embarquée, aux accents
 * complets, comme le récépissé et l'attestation : trois documents d'une même
 * famille doivent se ressembler. L'échelle de graisses respecte le système
 * (700 pour les titres, 400 pour le corps).
 *
 * ── Composition ───────────────────────────────────────────────────────────
 * Aplats et filets, jamais d'ombres ni de dégradés. Rayons 8 / 12 / 16 pt.
 * Un seul chiffre porte l'accent chromatique : le total réglé ce jour. Tout le reste
 * est en encre et en gris, pour que l'œil sache immédiatement où regarder.
 *
 * Contrainte dompdf : ni flexbox ni grid. Tables et unités `pt` partout.
 * Le document tient sur UNE page A4 : toute addition doit être compensée.
 */
$h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$violet      = '#8B52FA';
$violetClair = '#F3EFFF';
$encre       = '#1A1A1A';
$grisTexte   = '#4A4A4A';
$grisLabel   = '#9E9E9E';
$creme       = '#FAF9F5';
$hairline    = '#E8E8ED';

$d = $donnees['dossier'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Reçu <?= $h($donnees['numero']) ?></title>
<style>
/* @page margin 0 : la marge est portée par .page. Sans cela dompdf cumule les
   deux et le document se décale vers le bas. */
@page { margin: 0; }
* { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: "DejaVu Sans", sans-serif;
  font-size: 8.6pt;
  color: <?= $encre ?>;
  line-height: 1.5;
}
.page { padding: 20pt 32pt 6pt 32pt; }

/* ── En-tête ─────────────────────────────────────────────────────────────── */
.entete { width: 100%; border-collapse: collapse; margin-bottom: 10pt; }
.entete td { vertical-align: middle; }
.entete-g { width: 60%; }
.entete-d { width: 40%; text-align: right; }

.logo-etab { max-height: 38pt; max-width: 44pt; vertical-align: middle; }
.logo-pastille {
  display: inline-block; width: 32pt; height: 32pt;
  background: <?= $violet ?>; border-radius: 8pt; color: #fff;
  font-size: 14pt; font-weight: bold; text-align: center; line-height: 32pt;
  vertical-align: middle;
}
.etab-nom  { font-size: 11.5pt; font-weight: bold; line-height: 1.15; }
/* Un nom long descend d'un cran de corps : mieux vaut le lire un peu plus
   petit que le voir passer sur trois lignes et pousser le pied de page. */
.etab-nom.long { font-size: 9.6pt; }
.etab-sous { font-size: 6.2pt; color: <?= $grisLabel ?>; letter-spacing: 0.6pt; text-transform: uppercase; margin-top: 2pt; line-height: 1.3; }
.ca-nom    { font-size: 10.5pt; font-weight: bold; color: <?= $violet ?>; line-height: 1.15; }
.ca-sous   { font-size: 6pt; color: <?= $grisLabel ?>; letter-spacing: 0.85pt; text-transform: uppercase; margin-top: 2pt; }

/* ── Titre ───────────────────────────────────────────────────────────────── */
.eyebrow {
  font-size: 6.8pt; font-weight: bold; color: <?= $violet ?>;
  letter-spacing: 1.6pt; text-transform: uppercase; margin-bottom: 3pt;
}
.titre { font-size: 17.5pt; font-weight: bold; letter-spacing: -0.35pt; line-height: 1.1; }
.sous-titre { font-size: 8.4pt; color: <?= $grisTexte ?>; margin-top: 4pt; }
.regle { height: 2pt; background: <?= $violet ?>; border-radius: 2pt; margin: 8pt 0 10pt; }

/* ── Bandeau d'identification ────────────────────────────────────────────── */
.bandeau { width: 100%; border-collapse: collapse; background: <?= $violet ?>; border-radius: 12pt; margin-bottom: 10pt; }
.bandeau td { padding: 7pt 14pt; color: #fff; vertical-align: middle; }
.bandeau .cle { font-size: 6.4pt; letter-spacing: 0.9pt; text-transform: uppercase; opacity: .78; }
.bandeau .val { font-size: 12.5pt; font-weight: bold; letter-spacing: 0.5pt; margin-top: 1pt; }
.bandeau .droite { text-align: right; }
.bandeau .droite .val { font-size: 9.5pt; letter-spacing: 0.2pt; }

/* ── Parties ─────────────────────────────────────────────────────────────── */
.parties { width: 100%; border-collapse: separate; border-spacing: 10pt 0; margin: 0 -10pt 10pt -10pt; }
.parties td { width: 50%; vertical-align: top; }
.carte {
  border: 0.75pt solid <?= $hairline ?>;
  border-radius: 12pt;
  padding: 9pt 12pt;
  <?php if ($donnees['motif'] !== ''): ?>
  background-image: url('<?= $donnees['motif'] ?>');
  background-repeat: repeat;
  <?php endif; ?>
}
.carte-titre {
  font-size: 6.4pt; font-weight: bold; color: <?= $violet ?>;
  letter-spacing: 1.1pt; text-transform: uppercase; margin-bottom: 6pt;
}
.carte-nom { font-size: 10.5pt; font-weight: bold; line-height: 1.2; }
.carte-nom.long { font-size: 8.9pt; }
.carte-l { font-size: 7.5pt; color: <?= $grisTexte ?>; margin-top: 2.5pt; line-height: 1.35; }
.carte-l b { color: <?= $encre ?>; font-weight: bold; }

/* ── Montants ──────────────────────────────────────────────────────────────
   Un tableau par NATURE de somme. Le découpage n'est pas décoratif : frais
   d'inscription et scolarité obéissent à des règles différentes, et les
   empiler dans une seule grille ferait croire qu'ils s'additionnent. */
.montants { width: 100%; border-collapse: collapse; border: 0.75pt solid <?= $hairline ?>; border-radius: 12pt; margin-bottom: 8pt; }
.montants td { padding: 5pt 14pt; border-bottom: 0.75pt solid <?= $hairline ?>; }
.montants tr:last-child td { border-bottom: none; }
.m-lib { font-size: 8.4pt; color: <?= $grisTexte ?>; }
.m-lib b { color: <?= $encre ?>; }
.m-val { font-size: 9.5pt; font-weight: bold; text-align: right; white-space: nowrap; }

/* Intitulé de bloc : dit de quoi parle le tableau, sans introduire une
   troisième taille de titre dans le document. */
.m-section {
  background: <?= $creme ?>;
  font-size: 6.4pt; font-weight: bold; letter-spacing: 1.1pt;
  text-transform: uppercase; color: <?= $violet ?>;
  padding-top: 5.5pt !important; padding-bottom: 5.5pt !important;
}
.m-note { font-size: 6.6pt; line-height: 1.35; color: <?= $grisLabel ?>; }


.ligne-versee td { background: <?= $violetClair ?>; }
.ligne-versee .m-lib { color: <?= $encre ?>; font-weight: bold; font-size: 9pt; }
.ligne-versee .m-val { color: <?= $violet ?>; font-size: 14pt; letter-spacing: -0.3pt; }

.m-reste { font-size: 9.5pt; font-weight: bold; text-align: right; white-space: nowrap; }
.m-solde { font-size: 8pt; font-weight: bold; color: #0F7B3F; text-align: right; }

/* ── Récapitulatif de clôture (somme en lettres + circonstances) ─────────── */
.recap {
  border: 0.75pt solid <?= $hairline ?>;
  border-left: 2.5pt solid <?= $violet ?>;
  border-radius: 8pt;
  background: <?= $creme ?>;
  padding: 7pt 12pt;
  margin-bottom: 8pt;
}
.recap-l { font-size: 8.4pt; font-weight: bold; line-height: 1.4; }
.recap .cle {
  display: block;
  font-size: 6.2pt; font-weight: normal; letter-spacing: 0.9pt;
  text-transform: uppercase; color: <?= $grisLabel ?>;
}
.recap-sec { font-weight: normal; font-size: 7.8pt; color: <?= $grisTexte ?>; margin-top: 4pt; }
.recap-sec .cle { display: inline; margin-right: 5pt; }
.recap-sec b { color: <?= $encre ?>; }

/* ── Pied : vérification, signature, empreinte ───────────────────────────── */
/* La mention explicative ayant disparu du corps, le pied hérite de la marge
   qu'elle portait : sans cela, le récapitulatif se collerait au filet. */
.pied { width: 100%; border-collapse: collapse; border-top: 0.75pt solid <?= $hairline ?>; padding-top: 10pt; margin-top: 9pt; }
.pied td { vertical-align: top; padding-top: 7pt; }
.pied-qr { width: 50pt; }
.pied-qr img { width: 44pt; height: 44pt; }
.pied-verif { width: 46%; padding-left: 4pt !important; }
.pied-sign { text-align: right; }

.verif-titre { font-size: 6.4pt; font-weight: bold; letter-spacing: 0.9pt; text-transform: uppercase; color: <?= $violet ?>; }
.verif-txt { font-size: 6.8pt; color: <?= $grisTexte ?>; line-height: 1.45; margin-top: 3pt; }
.verif-code { font-family: "DejaVu Sans Mono", monospace; font-size: 8.5pt; font-weight: bold; letter-spacing: 1.4pt; margin-top: 3pt; }

.sign-img { max-height: 44pt; max-width: 130pt; }
.sign-ligne { border-top: 0.75pt solid <?= $hairline ?>; width: 130pt; margin-left: auto; margin-top: 20pt; }
.sign-l { font-size: 6.6pt; color: <?= $grisLabel ?>; margin-top: 3pt; }

.empreinte {
  margin-top: 7pt;
  padding-top: 6pt;
  border-top: 0.75pt solid <?= $hairline ?>;
  font-size: 5.9pt;
  color: <?= $grisLabel ?>;
}
.empreinte .code { font-family: "DejaVu Sans Mono", monospace; letter-spacing: 0.4pt; }
</style>
</head>
<body>
<div class="page">

  <!-- ══ En-tête ══ -->
  <table class="entete">
    <tr>
      <td class="entete-g">
        <?php if ($donnees['logo_etab'] !== ''): ?>
          <img src="<?= $donnees['logo_etab'] ?>" alt="" class="logo-etab">
        <?php else: ?>
          <span class="logo-pastille"><?= $h(mb_substr((string)$d['etab_nom'], 0, 1)) ?></span>
        <?php endif; ?>
        <div class="etab-nom<?= $donnees['etab_nom_long'] ? ' long' : '' ?>"><?= $h($d['etab_nom']) ?></div>
        <?php if ($donnees['etab_ligne'] !== ''): ?>
          <div class="etab-sous"><?= $h($donnees['etab_ligne']) ?></div>
        <?php endif; ?>
      </td>
      <td class="entete-d">
        <div class="ca-nom">Connect'Academia</div>
        <div class="ca-sous">Document vérifiable en ligne</div>
      </td>
    </tr>
  </table>

  <!-- ══ Titre ══ -->
  <div class="eyebrow">Justificatif de règlement</div>
  <div class="titre">Reçu de paiement</div>
  <div class="sous-titre">
    Année académique <?= $h($donnees['annee_libelle']) ?> · Inscription définitive validée
  </div>
  <div class="regle"></div>

  <!-- ══ Identification ══
       Le numéro est ce que le porteur lira au téléphone, et ce qu'un agent
       saisira pour retrouver l'opération. Il passe donc avant tout le reste. -->
  <table class="bandeau">
    <tr>
      <td>
        <div class="cle">Numéro du reçu</div>
        <div class="val"><?= $h($donnees['numero']) ?></div>
      </td>
      <td class="droite">
        <div class="cle">Dossier</div>
        <div class="val"><?= $h($donnees['reference'] !== '' ? $donnees['reference'] : '—') ?></div>
      </td>
      <td class="droite">
        <div class="cle">Émis le</div>
        <div class="val"><?= $h($donnees['date_emission']) ?></div>
      </td>
    </tr>
  </table>

  <!-- ══ Les deux parties ══ -->
  <table class="parties">
    <tr>
      <td>
        <div class="carte">
          <div class="carte-titre">Reçu de</div>
          <div class="carte-nom<?= $donnees['candidat_long'] ? ' long' : '' ?>"><?= $h($donnees['candidat']) ?></div>
          <div class="carte-l"><?= $h($donnees['formation']) ?></div>
          <div class="carte-l"><?= $h($donnees['email']) ?> · <?= $h($donnees['telephone']) ?></div>
        </div>
      </td>
      <td>
        <div class="carte">
          <div class="carte-titre">Encaissé par</div>
          <div class="carte-nom"><?= $h($d['etab_nom']) ?></div>
          <div class="carte-l">Règlement effectué au guichet de l'établissement.</div>
          <div class="carte-l">Statut du dossier : <b><?= $h($donnees['statut_libelle']) ?></b></div>
        </div>
      </td>
    </tr>
  </table>

  <!-- ══ Montants ══
       DEUX natures de somme, présentées séparément — c'est tout l'objet de ce
       découpage. Les frais d'inscription sont dus une fois et acquis à
       l'établissement ; le versement s'impute sur la scolarité annuelle. Les
       additionner dans une même colonne minorerait le reste à régler du
       candidat, et lui ferait perdre la trace écrite de ses frais.
       Seule la dernière ligne, le total réellement remis ce jour, porte
       l'accent chromatique. -->

  <?php // Un SEUL tableau, deux sections repérées par leur intitulé : trois
        // tableaux empilés ajoutaient six filets et trois marges, et faisaient
        // déborder le document sur une deuxième page. La séparation des natures
        // tient aux intitulés et au fond crème, pas au nombre de cadres. ?>
  <table class="montants">
    <?php if ($donnees['a_frais']): ?>
    <tr>
      <td class="m-section" colspan="2">Frais d'inscription</td>
    </tr>
    <tr>
      <td class="m-lib">Frais d'inscription acquittés<br>
        <span class="m-note">Dus une seule fois. Ils ne s'imputent pas sur la scolarité.</span>
      </td>
      <td class="m-val"><?= $h($donnees['frais']) ?></td>
    </tr>
    <?php endif; ?>

    <tr>
      <td class="m-section" colspan="2">Scolarité <?= $h($donnees['annee_libelle']) ?></td>
    </tr>
    <tr>
      <td class="m-lib">Scolarité annuelle</td>
      <td class="m-val"><?= $h($donnees['scolarite']) ?></td>
    </tr>
    <tr>
      <td class="m-lib">Versement effectué ce jour</td>
      <td class="m-val">- <?= $h($donnees['encaisse']) ?></td>
    </tr>
    <tr>
      <td class="m-lib"><b>Reste à régler sur la scolarité</b></td>
      <td class="<?= $donnees['solde_regle'] ? 'm-solde' : 'm-reste' ?>">
        <?= $donnees['solde_regle'] ? 'Soldé' : $h($donnees['reste']) ?>
      </td>
    </tr>

    <?php // Total remis ce jour : la seule somme réellement sortie de la poche
          // du candidat, et donc la seule qu'il pourra opposer en cas de litige. ?>
    <tr class="ligne-versee">
      <td class="m-lib">Total réglé ce jour<?php if ($donnees['a_frais']): ?> (frais d'inscription)<?php endif; ?></td>
      <td class="m-val"><?= $h($donnees['total_jour']) ?></td>
    </tr>
  </table>

  <?php // Un seul encadré pour les deux informations qui closent l'opération :
        // la somme en toutes lettres (un chiffre se retouche au stylo, une
        // phrase beaucoup moins) et les circonstances du règlement. Deux boîtes
        // séparées coûtaient une vingtaine de points de hauteur pour trois
        // lignes de texte, et faisaient basculer le reçu sur une seconde page. ?>
  <div class="recap">
    <div class="recap-l">
      <span class="cle">Arrêté, pour le règlement de ce jour, à la somme de</span>
      <span class="val"><?= $h($donnees['lettres']) ?></span>
    </div>
    <div class="recap-l recap-sec">
      <span class="cle">Réglé en</span>
      <?= $h($donnees['mode_paiement']) ?>, le <?= $h($donnees['date_paiement']) ?>
      <?php if ($donnees['ref_paiement'] !== '—'): ?>
        · référence <b><?= $h($donnees['ref_paiement']) ?></b>
      <?php endif; ?>
    </div>
  </div>

  <?php // La mention explicative qui suivait le récapitulatif a été retirée à
        // la demande de la direction : le reçu porte les montants, leur nature
        // et leur origine, et n'a pas à les commenter une seconde fois. Ce que
        // le document doit prouver — qui a encaissé, quand, combien — reste
        // écrit dans le bandeau, les cartes de parties et le récapitulatif. ?>

  <!-- ══ Pied ══ -->
  <table class="pied">
    <tr>
      <?php if ($donnees['qr'] !== ''): ?>
      <td class="pied-qr"><img src="<?= $donnees['qr'] ?>" alt=""></td>
      <?php endif; ?>
      <td class="pied-verif">
        <div class="verif-titre">Vérifier ce reçu</div>
        <div class="verif-txt">
          Scannez le code, ou saisissez celui ci-dessous sur connectacademia.com :
          l'état réel du dossier s'affiche.
        </div>
        <?php if ($donnees['code_court'] !== ''): ?>
          <div class="verif-code"><?= $h($donnees['code_court']) ?></div>
        <?php endif; ?>
      </td>
      <td class="pied-sign">
        <?php if ($donnees['signature'] !== ''): ?>
          <img src="<?= $donnees['signature'] ?>" alt="" class="sign-img">
        <?php else: ?>
          <div class="sign-ligne"></div>
        <?php endif; ?>
        <div class="sign-l">Cachet et signature de l'établissement</div>
      </td>
    </tr>
  </table>

  <div class="empreinte">
    Document généré par Connect'Academia · Empreinte <span class="code">{{EMPREINTE}}</span>
    · Reçu <span class="code"><?= $h($donnees['numero']) ?></span>
  </div>

</div>
</body>
</html>
