<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width">
<title>Reçu <?= htmlspecialchars($numero, ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($etabNom, ENT_QUOTES, 'UTF-8') ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;background:#fff;color:#111;font-size:14px;line-height:1.6}
.page{max-width:780px;margin:0 auto;padding:40px 48px}
.no-print{margin-bottom:24px;padding:12px 16px;background:#f0fdf4;border:1.5px solid #86efac;border-radius:10px;font-size:13px;color:#166534;display:flex;align-items:center;gap:10px}
.print-btn{display:inline-flex;align-items:center;gap:6px;background:<?= htmlspecialchars($couleur, ENT_QUOTES, 'UTF-8') ?>;color:#fff;border:none;border-radius:8px;padding:9px 18px;font-size:13px;font-weight:700;cursor:pointer;margin-bottom:24px}
@media print{.no-print,.print-btn{display:none}.page{padding:14mm 16mm}}
/* margin:0 supprime le pied de page navigateur (URL interne, date). */
@page{margin:0}
.header{display:flex;justify-content:space-between;align-items:flex-start;padding-bottom:24px;border-bottom:3px solid <?= htmlspecialchars($couleur, ENT_QUOTES, 'UTF-8') ?>;margin-bottom:28px}
.logo-box{width:52px;height:52px;background:<?= htmlspecialchars($couleur, ENT_QUOTES, 'UTF-8') ?>;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:22px;font-weight:800;flex-shrink:0}
.etab-info{margin-left:12px}
.etab-name{font-size:18px;font-weight:800;color:<?= htmlspecialchars($couleur, ENT_QUOTES, 'UTF-8') ?>}
.etab-ville{font-size:13px;color:#777;margin-top:2px}
.header-left{display:flex;align-items:center}
.header-right{text-align:right}
.green-badge{display:inline-block;padding:5px 14px;background:#dcfce7;border:1.5px solid #86efac;border-radius:100px;color:#166534;font-size:12px;font-weight:700}
.emit-date{font-size:12px;color:#888;margin-top:6px}
h1{font-size:22px;font-weight:800;margin-bottom:4px}
.numero{color:<?= htmlspecialchars($couleur, ENT_QUOTES, 'UTF-8') ?>;font-size:14px;font-weight:700;margin-bottom:28px}
.section{margin-bottom:24px}
.sec-title{font-size:10.5px;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:#999;margin-bottom:10px;border-bottom:1px solid #eee;padding-bottom:6px}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.grid.wide .field:last-child{grid-column:span 2}
.field{background:#f8f7ff;border-radius:8px;padding:10px 14px}
.field-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#aaa;margin-bottom:3px}
.field-value{font-size:14px;font-weight:600;color:#1a1a1a;word-break:break-all}
.amount-box{background:<?= htmlspecialchars($couleur, ENT_QUOTES, 'UTF-8') ?>18;border:2px solid <?= htmlspecialchars($couleur, ENT_QUOTES, 'UTF-8') ?>44;border-radius:12px;padding:18px 22px;display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}
.amount-label{font-size:13px;color:#555;font-weight:600}
.amount-value{font-size:24px;font-weight:800;color:<?= htmlspecialchars($couleur, ENT_QUOTES, 'UTF-8') ?>}
.footer{margin-top:40px;display:flex;justify-content:space-between;align-items:flex-end;padding-top:24px;border-top:1px solid #eee}
.sig-box{text-align:center}
.sig-line{width:160px;border-top:1.5px solid #333;padding-top:8px;font-size:11.5px;color:#777;margin-top:48px}
.stamp-box{width:110px;height:80px;border:1.5px dashed #ccc;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:11px;color:#ccc;text-align:center}
.disclaimer{margin-top:24px;padding:10px 14px;background:#f5f5f5;border-radius:8px;font-size:11.5px;color:#888;text-align:center;line-height:1.5}
.branding{text-align:center;margin-top:16px;font-size:11px;color:#ccc}
</style>
</head>
<body>
<div class="page">

  <div class="no-print">
    ✓ Ce reçu confirme votre inscription. Conservez-le précieusement.
  </div>

  <button class="print-btn" onclick="window.print()">
    &#128247; Imprimer / Télécharger PDF
  </button>

  <!-- En-tête -->
  <div class="header">
    <div class="header-left">
      <div class="logo-box"><?= htmlspecialchars($etabInitiale, ENT_QUOTES, 'UTF-8') ?></div>
      <div class="etab-info">
        <div class="etab-name"><?= htmlspecialchars($etabNom, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="etab-ville">Établissement d'enseignement supérieur</div>
      </div>
    </div>
    <div class="header-right">
      <span class="green-badge">✓ INSCRIPTION CONFIRMÉE</span>
      <div class="emit-date">Émis le <?= htmlspecialchars($dateEmission, ENT_QUOTES, 'UTF-8') ?></div>
    </div>
  </div>

  <h1>Reçu d'inscription</h1>
  <div class="numero"><?= htmlspecialchars($numero, ENT_QUOTES, 'UTF-8') ?></div>

  <!-- Montant -->
  <div class="amount-box">
    <div>
      <div class="amount-label">Montant payé</div>
      <div class="amount-value"><?= number_format($montant, 0, ',', ' ') ?> <span style="font-size:16px">XAF</span></div>
    </div>
    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="<?= htmlspecialchars($couleur, ENT_QUOTES, 'UTF-8') ?>" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
  </div>

  <!-- Identité candidat -->
  <div class="section">
    <div class="sec-title">Candidat</div>
    <div class="grid wide">
      <div class="field">
        <div class="field-label">Nom complet</div>
        <div class="field-value"><?= htmlspecialchars($nomComplet, ENT_QUOTES, 'UTF-8') ?></div>
      </div>
      <div class="field">
        <div class="field-label">E-mail</div>
        <div class="field-value"><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></div>
      </div>
      <div class="field">
        <div class="field-label">Téléphone</div>
        <div class="field-value"><?= htmlspecialchars($telephone, ENT_QUOTES, 'UTF-8') ?></div>
      </div>
    </div>
  </div>

  <!-- Formation -->
  <div class="section">
    <div class="sec-title">Formation</div>
    <div class="grid">
      <div class="field">
        <div class="field-label">Filière</div>
        <div class="field-value"><?= htmlspecialchars($filiereLibelle, ENT_QUOTES, 'UTF-8') ?></div>
      </div>
      <div class="field">
        <div class="field-label">Niveau d'entrée</div>
        <div class="field-value"><?= htmlspecialchars($niveauEntree, ENT_QUOTES, 'UTF-8') ?></div>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <div class="footer">
    <div class="sig-box">
      <div class="sig-line">Signature du responsable</div>
    </div>
    <div class="stamp-box">Cachet<br>officiel</div>
  </div>

  <div class="disclaimer">
    Ce document est un reçu de paiement officiel délivré par <?= htmlspecialchars($etabNom, ENT_QUOTES, 'UTF-8') ?>.
    Conservez-le pour votre dossier. En cas de litige, ce document fait foi.
  </div>

  <div class="branding">Généré via Connect'Academia · connectacademia.io</div>

</div>
</body>
</html>
