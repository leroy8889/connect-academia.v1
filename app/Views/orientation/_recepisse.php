<?php
/**
 * Attestation officielle de préinscription — gabarit maquette (docs/preinscription-recepisser.png).
 * Rendu autonome (aucune ressource externe) : le logo établissement arrive en data URI
 * via RecepisseService ($logoDataUri) — en-tête, filigrane et bloc signature.
 *
 * Variables attendues (toutes avec repli sûr) :
 * $numero, $etabNom, $etabVille, $etabInitiale, $couleur, $logoDataUri,
 * $nomComplet, $dateNaissance, $lieuNaissance, $nationalite, $paysProvenance,
 * $telephone, $email, $filiereLibelle, $niveauEntree, $dernierEtab,
 * $anneeUniv, $dateEmissionStr, $qrDataUri (QR d'authenticité — bloc masqué si vide)
 */
$h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$numero          = $numero          ?? '—';
$etabNom         = $etabNom         ?? '';
$etabVille       = ($etabVille      ?? '') !== '' ? $etabVille : 'Libreville';
$etabInitiale    = $etabInitiale    ?? '?';
$couleur         = $couleur         ?? '#1E3A5F';
$logoDataUri     = $logoDataUri     ?? '';
$nomComplet      = $nomComplet      ?? '—';
$dateNaissance   = $dateNaissance   ?? '—';
$lieuNaissance   = $lieuNaissance   ?? '—';
$nationalite     = $nationalite     ?? '—';
$paysProvenance  = $paysProvenance  ?? '—';
$telephone       = $telephone       ?? '—';
$email           = $email           ?? '—';
$filiereLibelle  = $filiereLibelle  ?? '—';
$niveauEntree    = $niveauEntree    ?? '—';
$dernierEtab     = $dernierEtab     ?? '—';
$anneeUniv       = $anneeUniv       ?? date('Y') . ' — ' . (date('Y') + 1);
$dateEmissionStr = $dateEmissionStr ?? date('d/m/Y');
$qrDataUri       = $qrDataUri       ?? '';
$navy            = '#1E3A5F';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Attestation de préinscription <?= $h($numero) ?> — <?= $h($etabNom) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Georgia,'Times New Roman',serif;background:#fff;color:#111;font-size:15px;line-height:1.55}
.page{max-width:820px;margin:0 auto;padding:36px 52px 28px}
.no-print{font-family:-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;margin-bottom:20px;padding:12px 16px;background:#fffbeb;border:1.5px solid #fde68a;border-radius:10px;font-size:13px;color:#92400e;display:flex;align-items:center;gap:10px}
.print-btn{font-family:-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;display:inline-flex;align-items:center;gap:6px;background:<?= $h($navy) ?>;color:#fff;border:none;border-radius:8px;padding:9px 18px;font-size:13px;font-weight:700;cursor:pointer;margin-bottom:22px}
@media print{
  .no-print,.print-btn{display:none}
  .page{padding:14mm 16mm;max-width:none}
  body{-webkit-print-color-adjust:exact;print-color-adjust:exact}
}
/* margin:0 supprime l'en-tête/pied de page navigateur (URL, date, titre) —
   sinon Chrome imprime le chemin interne .../gestionnaire/... en bas de page.
   La marge visuelle est reportée dans le padding de .page ci-dessus. */
@page{margin:0}

/* ── En-tête officiel : ministère / logo / république ── */
.entete{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;margin-bottom:34px}
.entete-gauche{width:250px;flex-shrink:0}
.entete-gauche .titre{font-size:15.5px;font-weight:700;line-height:1.35}
.entete-gauche .sous{font-size:12px;line-height:1.5;margin-top:6px}
.entete-centre{flex:1;text-align:center;padding-top:4px}
.entete-centre img{max-height:74px;max-width:190px;object-fit:contain}
.logo-fallback{display:inline-flex;align-items:center;justify-content:center;width:64px;height:64px;background:<?= $h($couleur) ?>;border-radius:12px;color:#fff;font-size:28px;font-weight:700}
.entete-droite{width:220px;flex-shrink:0;text-align:right}
.entete-droite .rep{font-size:15.5px;font-weight:700;line-height:1.35}
.entete-droite .devise{font-size:12.5px;margin-top:4px}

/* ── Bandeau titre ── */
.bandeau{background:<?= $h($navy) ?>;color:#fff;border-radius:14px;text-align:center;padding:17px 20px;margin:0 42px 12px;font-size:21px;font-weight:700;letter-spacing:.14em;text-transform:uppercase}
.sous-bandeau{text-align:center;font-size:13.5px;margin-bottom:32px}

/* ── Corps ── */
.intro{margin:0 0 20px;font-size:15.5px}
.cadre{position:relative;border:2px solid <?= $h($navy) ?>;border-radius:20px;padding:28px 34px;margin:0 6px 26px;overflow:hidden}
.filigrane{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;z-index:0}
.filigrane img{width:62%;max-height:88%;object-fit:contain;opacity:.07}
.filigrane .initiale{font-size:220px;font-weight:700;color:<?= $h($navy) ?>;opacity:.05;line-height:1}
.cadre .ligne{position:relative;z-index:1;font-size:15.5px;margin-bottom:13px}
.cadre .ligne:last-child{margin-bottom:0}
.cadre .ligne b{font-weight:700}
.cadre .sep{display:inline-block;width:26px}

.texte{margin:0 0 18px;font-size:15.5px;text-align:justify}
.texte b{font-weight:700}

/* ── Signature ── */
.pied{display:flex;justify-content:space-between;align-items:flex-end;margin-top:40px}
.pied-gauche{width:200px;min-height:10px}
.qr-bloc{display:inline-block;text-align:center}
.qr-bloc img{width:96px;height:96px;display:block;margin:0 auto 6px;border:1px solid #e2e2e2;border-radius:6px;padding:4px;background:#fff}
.qr-bloc .qr-legende{font-size:9.5px;color:#555;line-height:1.45;max-width:170px}
.pied-droite{text-align:center}
.pied-droite .fait{font-size:15px;margin-bottom:12px}
.pied-droite img{max-height:44px;max-width:120px;object-fit:contain;display:block;margin:0 auto 8px}
.pied-droite .service{font-size:15px;font-weight:700;text-decoration:underline;text-underline-offset:4px}

/* ── Ligne de bas de page ── */
.basdepage{margin-top:42px;padding-top:14px;border-top:1px solid #d8d8d8;text-align:center;font-size:11.5px;color:#666}

/* ── Affichage mobile (écran uniquement — l'impression garde le format A4) ── */
@media screen and (max-width:640px){
  .page{padding:20px 16px}
  .no-print{font-size:12px;padding:10px 12px}
  .entete{flex-direction:column;align-items:center;text-align:center;gap:12px;margin-bottom:22px}
  .entete-gauche,.entete-droite{width:100%;text-align:center}
  .entete-droite .rep br{display:none}
  .bandeau{margin:0 0 12px;padding:14px 14px;font-size:16px;letter-spacing:.06em}
  .sous-bandeau{margin-bottom:22px;font-size:12.5px}
  .intro,.texte{font-size:14px}
  .cadre{padding:20px 18px;margin:0 0 22px;border-radius:16px}
  .cadre .ligne{font-size:14px;margin-bottom:11px}
  .cadre .sep{width:8px}
  .filigrane .initiale{font-size:140px}
  .pied{flex-direction:column;align-items:center;gap:22px;text-align:center;margin-top:28px}
  .pied-gauche,.pied-droite{width:100%}
}
</style>
</head>
<body>
<div class="page">

  <div class="no-print">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
    Astuce : Utilisez <strong>Ctrl+P</strong> (ou Cmd+P) pour imprimer cette attestation ou l'enregistrer en PDF.
  </div>

  <button class="print-btn" onclick="window.print()">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
    Imprimer / Enregistrer en PDF
  </button>

  <!-- En-tête officiel -->
  <div class="entete">
    <div class="entete-gauche">
      <div class="titre">Ministère de l'Enseignement Supérieur</div>
      <div class="sous">de la Recherche Scientifique, du Transfert de Technologie, de la Culture et des Arts</div>
    </div>
    <div class="entete-centre">
      <?php if ($logoDataUri !== ''): ?>
        <img src="<?= $h($logoDataUri) ?>" alt="Logo <?= $h($etabNom) ?>">
      <?php else: ?>
        <span class="logo-fallback"><?= $h($etabInitiale) ?></span>
      <?php endif; ?>
    </div>
    <div class="entete-droite">
      <div class="rep">RÉPUBLIQUE<br>GABONAISE</div>
      <div class="devise">Union — Travail — Justice</div>
    </div>
  </div>

  <!-- Bandeau titre -->
  <div class="bandeau">Attestation de Préinscription</div>
  <div class="sous-bandeau">Année universitaire <?= $h($anneeUniv) ?></div>

  <p class="intro">La présente attestation certifie que la demande de préinscription de&nbsp;:</p>

  <!-- Cadre candidat + filigrane logo -->
  <div class="cadre">
    <div class="filigrane">
      <?php if ($logoDataUri !== ''): ?>
        <img src="<?= $h($logoDataUri) ?>" alt="">
      <?php else: ?>
        <span class="initiale"><?= $h($etabInitiale) ?></span>
      <?php endif; ?>
    </div>
    <div class="ligne"><b>Nom &amp; Prénom&nbsp;:</b> <?= $h($nomComplet) ?></div>
    <div class="ligne"><b>Né(e) le&nbsp;:</b> <?= $h($dateNaissance) ?> <span class="sep"></span> <b>à</b> <?= $h($lieuNaissance) ?></div>
    <div class="ligne"><b>Nationalité&nbsp;:</b> <?= $h($nationalite) ?> <span class="sep"></span> <b>Pays&nbsp;:</b> <?= $h($paysProvenance) ?></div>
    <div class="ligne"><b>Téléphone&nbsp;:</b> <?= $h($telephone) ?> <span class="sep"></span> <b>Email&nbsp;:</b> <?= $h($email) ?></div>
    <div class="ligne"><b>Filière souhaitée&nbsp;:</b> <?= $h($filiereLibelle) ?></div>
    <div class="ligne"><b>Niveau visé&nbsp;:</b> <?= $h($niveauEntree) ?></div>
    <div class="ligne"><b>Établissement de provenance&nbsp;:</b> <?= $h($dernierEtab) ?></div>
    <div class="ligne"><b>N° de préinscription&nbsp;:</b> <?= $h($numero) ?></div>
  </div>

  <p class="texte">a bien été <b>enregistrée</b> auprès de <?= $h($etabNom) ?>. Cette attestation n'a pas valeur d'admission définitive&nbsp;; il atteste uniquement de l'enregistrement du dossier dans notre système.</p>

  <!-- Signature + QR d'authenticité -->
  <div class="pied">
    <div class="pied-gauche">
      <?php if ($qrDataUri !== ''): ?>
        <div class="qr-bloc">
          <img src="<?= $h($qrDataUri) ?>" alt="QR code de vérification d'authenticité">
          <div class="qr-legende">Scannez ce QR code pour vérifier l'authenticité de ce document sur Connect'Academia.</div>
        </div>
      <?php endif; ?>
    </div>
    <div class="pied-droite">
      <div class="fait">Fait à <?= $h($etabVille) ?>, le <?= $h($dateEmissionStr) ?></div>
      <div class="service">Service des Admissions</div>
    </div>
  </div>

  <div class="basdepage">
    <?= $h($etabNom) ?> — <?= $h($etabVille) ?>, Gabon — Attestation de préinscription — <?= $h($numero) ?>
  </div>

</div>
</body>
</html>
