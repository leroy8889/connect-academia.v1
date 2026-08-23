<?php
/**
 * Page publique de vérification d'authenticité (scan du QR code d'un document).
 * Rendue directement par VerificationController (pas de layout — page autonome,
 * mobile-first : elle est ouverte depuis l'appareil photo d'un téléphone).
 *
 * Design : registre éditorial sobre — canvas crème fixe (registre « papier
 * officiel », pas de dark mode), profondeur par filets 1px plutôt que par
 * ombres, accent violet rare (verdict et lien uniquement), statuts en point
 * coloré + libellé. Typographie Montserrat (design système Connect'Academia),
 * numéros de document en JetBrains Mono.
 *
 * Variables (état "document trouvé", toutes absentes si $doc est null) :
 * $doc, $type ('recepisse'|'attestation'), $typeLibelle, $delivreAccord,
 * $numero, $dateEmission, $dateNaissance, $dateDepot, $filiereLibelle,
 * $telephone, $statutLibelle, $statutTon ('valide'|'attente'|'refuse'|'neutre'),
 * $avertissement, $origineLibelle, $compte (array|null : nom, email, verifie, cree_le)
 *
 * Surface formelle (vérificateurs : établissements, administrations) → vouvoiement.
 */
$h     = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$doc   = $doc ?? null;
$titre = $doc ? 'Document authentique' : 'Document non reconnu';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= $h($titre) ?> — Connect'Academia</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root{
  --canvas:#faf9f5;          /* crème — jamais de blanc pur */
  --ink:#181715;
  --ink-secondary:#57534c;
  --ink-muted:#8a857c;
  --hairline:#e6dfd8;
  --accent:#8B52FA;          /* Purple Neon Connect'Academia — usage rare */
  --ok:#1a8917;
  --attente:#b45309;
  --refuse:#b3261e;
  --neutre:#8a857c;
}
*{box-sizing:border-box;margin:0;padding:0}
html{-webkit-text-size-adjust:100%}
body{
  font-family:'Montserrat',sans-serif;
  background:var(--canvas);
  color:var(--ink);
  font-size:15px;line-height:1.6;
  -webkit-font-smoothing:antialiased;
  min-height:100vh;display:flex;flex-direction:column;
}
.page{width:100%;max-width:680px;margin:0 auto;padding:0 20px;flex:1}
.mono{font-family:'JetBrains Mono',monospace}

/* ── Bandeau de marque ── */
.marque{
  display:flex;align-items:baseline;gap:12px;flex-wrap:wrap;
  padding:22px 0 18px;border-bottom:1px solid var(--hairline);
}
.marque .nom{font-size:15px;font-weight:700;letter-spacing:-0.01em}
.marque .nom span{color:var(--accent)}
.marque .role{font-size:13px;color:var(--ink-muted)}

/* ── Verdict ── */
.verdict{padding:52px 0 40px}
.verdict .eyebrow{
  font-size:11px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;
  color:var(--ink-muted);margin-bottom:14px;
}
.verdict h1{
  font-size:clamp(28px,7vw,38px);font-weight:600;letter-spacing:-0.025em;
  line-height:1.15;margin-bottom:10px;
}
.verdict .contexte{font-size:15px;color:var(--ink-secondary);max-width:46ch}
.verdict .numero{
  display:inline-block;margin-top:20px;padding:7px 14px;
  border:1px solid var(--hairline);border-radius:8px;background:#fffdf9;
  font-size:13px;letter-spacing:.02em;color:var(--ink);
}

/* ── Avertissement ── */
.alerte{
  display:flex;gap:10px;align-items:baseline;
  border:1px solid var(--hairline);border-left:3px solid var(--attente);
  border-radius:8px;padding:14px 16px;margin-bottom:28px;
  font-size:13.5px;color:var(--ink-secondary);background:#fffdf9;
}

/* ── Fiche (sections séparées par filets) ── */
.fiche{border:1px solid var(--hairline);border-radius:12px;background:#fffdf9;overflow:hidden}
.section{padding:24px 28px}
.section + .section{border-top:1px solid var(--hairline)}
.section .eyebrow{
  font-size:11px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;
  color:var(--ink-muted);margin-bottom:14px;
}
.rangee{display:grid;grid-template-columns:172px 1fr;gap:4px 24px;padding:6px 0;font-size:14px}
.rangee .cle{color:var(--ink-muted);font-size:13px;padding-top:1px}
.rangee .val{font-weight:500;overflow-wrap:anywhere}
.section .note{font-size:13.5px;color:var(--ink-secondary)}
@media (max-width:520px){
  .rangee{grid-template-columns:1fr;padding:8px 0}
  .section{padding:20px 18px}
}

/* ── Statut : point + libellé ── */
.statut{display:inline-flex;align-items:center;gap:8px;font-weight:500}
.statut::before{content:"";width:8px;height:8px;border-radius:50%;flex-shrink:0}
.statut.valide::before{background:var(--ok)}
.statut.attente::before{background:var(--attente)}
.statut.refuse::before{background:var(--refuse)}
.statut.neutre::before{background:var(--neutre)}

/* ── État « non reconnu » ── */
.verdict.ko h1{color:var(--refuse)}

/* ── Pied ── */
.pied{
  max-width:680px;width:100%;margin:44px auto 0;padding:18px 20px 30px;
  border-top:1px solid var(--hairline);
  font-size:12px;color:var(--ink-muted);
}
.pied a{color:var(--accent);text-decoration:none;font-weight:500}
</style>
</head>
<body>
<div class="page">

  <div class="marque">
    <span class="nom">Connect<span>'</span>Academia</span>
    <span class="role">Vérification de documents</span>
  </div>

<?php if (!$doc): ?>

  <!-- ── Token inconnu : document non reconnu ── -->
  <div class="verdict ko">
    <div class="eyebrow">Vérification officielle</div>
    <h1>Document non reconnu.</h1>
    <p class="contexte">Ce code de vérification ne correspond à aucun document délivré via Connect'Academia.</p>
  </div>

  <div class="fiche">
    <div class="section">
      <div class="eyebrow">Que faire</div>
      <p class="note">
        Le document présenté est peut-être falsifié, altéré ou périmé.
        Vérifiez que le QR code scanné est bien celui imprimé sur le document
        original, puis contactez l'établissement émetteur ou l'équipe
        Connect'Academia.
      </p>
    </div>
  </div>

<?php else: ?>

  <!-- ── Document authentique ── -->
  <div class="verdict">
    <div class="eyebrow">Vérification officielle</div>
    <h1>Document authentique.</h1>
    <p class="contexte"><?= $h($typeLibelle) ?> <?= $h($delivreAccord ?? 'délivrée') ?> par <?= $h($doc['etab_nom'] ?? '—') ?>, enregistrée dans le système Connect'Academia.</p>
    <span class="numero mono"><?= $h($numero) ?></span>
  </div>

  <?php if (!empty($avertissement)): ?>
    <div class="alerte"><?= $h($avertissement) ?></div>
  <?php endif; ?>

  <div class="fiche">

    <div class="section">
      <div class="eyebrow">Document</div>
      <div class="rangee"><span class="cle">Type</span><span class="val"><?= $h($typeLibelle) ?></span></div>
      <div class="rangee"><span class="cle">Numéro</span><span class="val mono"><?= $h($numero) ?></span></div>
      <div class="rangee"><span class="cle">Date d'émission</span><span class="val"><?= $h($dateEmission) ?></span></div>
      <div class="rangee"><span class="cle">Établissement</span><span class="val"><?= $h($doc['etab_nom'] ?? '—') ?></span></div>
      <div class="rangee"><span class="cle">Ville</span><span class="val"><?= $h(($doc['etab_ville'] ?? '') !== '' ? $doc['etab_ville'] : 'Libreville') ?></span></div>
    </div>

    <div class="section">
      <div class="eyebrow">Candidat</div>
      <div class="rangee"><span class="cle">Nom &amp; prénom</span><span class="val"><?= $h(trim((string)($doc['nom_complet'] ?? '')) ?: '—') ?></span></div>
      <div class="rangee"><span class="cle">Né(e) le</span><span class="val"><?= $h($dateNaissance) ?><?= ($doc['lieu_naissance'] ?? '') !== '' ? ' à ' . $h($doc['lieu_naissance']) : '' ?></span></div>
      <div class="rangee"><span class="cle">Nationalité</span><span class="val"><?= $h(trim((string)($doc['nationalite'] ?? '')) ?: '—') ?></span></div>
      <div class="rangee"><span class="cle">Téléphone</span><span class="val"><?= $h($telephone) ?></span></div>
      <div class="rangee"><span class="cle">Email</span><span class="val"><?= $h(trim((string)($doc['email'] ?? '')) ?: '—') ?></span></div>
    </div>

    <div class="section">
      <div class="eyebrow"><?= $type === 'attestation' ? 'Inscription' : 'Préinscription' ?></div>
      <div class="rangee"><span class="cle">Statut actuel</span><span class="val"><span class="statut <?= $h($statutTon) ?>"><?= $h($statutLibelle) ?></span></span></div>
      <div class="rangee"><span class="cle">Filière</span><span class="val"><?= $h($filiereLibelle) ?></span></div>
      <div class="rangee"><span class="cle">Niveau d'entrée</span><span class="val"><?= $h(trim((string)($doc['niveau_entree'] ?? '')) ?: '—') ?></span></div>
      <div class="rangee"><span class="cle">Dossier déposé le</span><span class="val"><?= $h($dateDepot) ?></span></div>
      <div class="rangee"><span class="cle">Origine</span><span class="val"><?= $h($origineLibelle) ?></span></div>
    </div>

    <div class="section">
      <div class="eyebrow">Compte Connect'Academia</div>
      <?php if ($compte): ?>
        <div class="rangee"><span class="cle">Titulaire</span><span class="val"><?= $h($compte['nom']) ?></span></div>
        <div class="rangee"><span class="cle">Email du compte</span><span class="val"><?= $h($compte['email']) ?></span></div>
        <div class="rangee"><span class="cle">Compte vérifié</span><span class="val"><span class="statut <?= $compte['verifie'] ? 'valide' : 'neutre' ?>"><?= $compte['verifie'] ? 'Oui' : 'Non' ?></span></span></div>
        <div class="rangee"><span class="cle">Membre depuis</span><span class="val"><?= $h($compte['cree_le']) ?></span></div>
      <?php else: ?>
        <p class="note">Aucun compte en ligne lié — dossier déposé directement au guichet de l'établissement.</p>
      <?php endif; ?>
    </div>

  </div>

<?php endif; ?>

</div>

<div class="pied">
  Vérification effectuée le <?= $h(date('d/m/Y à H\hi')) ?> —
  <a href="<?= url('') ?>">Connect'Academia</a>, votre passerelle vers l'avenir.
</div>
</body>
</html>
