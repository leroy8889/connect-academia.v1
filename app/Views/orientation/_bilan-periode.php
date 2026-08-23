<?php
/**
 * Bilan statistique de période (préinscriptions ou inscriptions) — document
 * imprimable aux couleurs Connect'Academia (design system : Purple Neon
 * #8B52FA, Montserrat, radii 24/16/12). Rendu autonome : logo plateforme en
 * data URI, aucune ressource externe hormis la police Google Fonts (repli
 * system-ui à l'impression hors ligne).
 *
 * Variables attendues (toutes avec repli sûr) :
 * $titreDoc, $etabNom, $etabVille, $duStr, $auStr, $genereLe,
 * $lignes (nom, email, tel, filiere, niveau, date), $total, $nbEnligne, $nbGuichet,
 * $logoDataUri (monogramme typographique si vide)
 */
$h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$titreDoc    = $titreDoc    ?? 'Bilan';
$etabNom     = $etabNom     ?? '';
$etabVille   = $etabVille   ?? '';
$duStr       = $duStr       ?? '—';
$auStr       = $auStr       ?? '—';
$genereLe    = $genereLe    ?? date('d/m/Y');
$lignes      = $lignes      ?? [];
$total       = $total       ?? count($lignes);
$nbEnligne   = $nbEnligne   ?? 0;
$nbGuichet   = $nbGuichet   ?? 0;
$logoDataUri = $logoDataUri ?? '';

$nombreFr = static fn(int $n): string => number_format($n, 0, ',', ' ');
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width">
<title><?= $h($titreDoc) ?> — <?= $h($etabNom) ?> — Connect'Academia</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
  --purple-neon:#8B52FA;
  --purple-tint:#F3EFFF;
  --purple-press:#7440D9;
  --purple-hover:#9D6EFB;
  --bg-surface:#F5F5F7;
  --text-primary:#1A1A1A;
  --text-secondary:#4A4A4A;
  --text-muted:#9E9E9E;
  --border-subtle:rgba(26,26,26,.08);
  --shadow-md:0 4px 12px rgba(26,26,26,.08),0 2px 4px rgba(26,26,26,.04);
  --radius-md:12px;
  --radius-lg:16px;
  --radius-xl:24px;
  --font-sans:'Montserrat',system-ui,-apple-system,'Segoe UI',sans-serif;
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--font-sans);background:#fff;color:var(--text-primary);font-size:14px;line-height:1.55;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.page{max-width:900px;margin:0 auto;padding:32px 44px 40px}

/* ── Barre écran (masquée à l'impression) ── */
.no-print{margin-bottom:14px;padding:12px 16px;background:var(--purple-tint);border:1px solid rgba(139,82,250,.25);border-radius:var(--radius-lg);font-size:12.5px;color:#5B32B8;display:flex;align-items:center;gap:10px}
.no-print svg{flex:none}
.print-btn{display:inline-flex;align-items:center;gap:8px;background:var(--purple-neon);color:#fff;border:none;border-radius:var(--radius-md);padding:10px 20px;font-family:var(--font-sans);font-size:14px;font-weight:500;letter-spacing:.01em;cursor:pointer;margin-bottom:24px;transition:background 200ms cubic-bezier(.2,.8,.2,1),transform 120ms ease-out;box-shadow:0 8px 24px rgba(139,82,250,.30)}
.print-btn:hover{background:var(--purple-hover)}
.print-btn:active{background:var(--purple-press);transform:scale(.98)}
@media print{
  .no-print,.print-btn{display:none}
  .page{padding:0;max-width:none}
}
@page{margin:14mm}

/* ── En-tête plateforme ── */
.entete{display:flex;align-items:center;justify-content:space-between;gap:20px;padding-bottom:18px;border-bottom:2px solid var(--purple-neon);margin-bottom:26px}
.marque{display:flex;align-items:center;gap:14px;min-width:0}
.marque img{width:56px;height:56px;object-fit:contain;flex:none}
.monogramme{display:inline-flex;align-items:center;justify-content:center;width:56px;height:56px;flex:none;background:var(--purple-neon);border-radius:var(--radius-lg);color:#fff;font-size:22px;font-weight:800;letter-spacing:-.02em}
.marque-nom{font-size:19px;font-weight:800;letter-spacing:-.01em;line-height:1.2;color:var(--text-primary)}
.marque-tagline{font-size:9.5px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--purple-neon);margin-top:3px}
.entete-droite{text-align:right;flex:none}
.eyebrow{font-size:10.5px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--text-muted)}
.entete-droite .genere{font-size:12px;font-weight:300;color:var(--text-muted);margin-top:4px}

/* ── Bloc titre ── */
.titre-doc{font-size:28px;font-weight:700;letter-spacing:-.01em;line-height:1.2;color:var(--text-primary)}
.sous-titre{font-size:14px;color:var(--text-secondary);margin-top:6px}
.sous-titre b{font-weight:600;color:var(--text-primary)}
.periode{display:inline-flex;align-items:center;gap:8px;margin-top:12px;background:var(--purple-tint);color:#5B32B8;border-radius:999px;padding:6px 16px;font-size:12.5px;font-weight:600}
.periode svg{flex:none}

/* ── Tuiles statistiques (Bento) ── */
.tuiles{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin:24px 0 28px}
.tuile{background:var(--bg-surface);border-radius:var(--radius-lg);padding:16px 20px}
.tuile-val{font-size:26px;font-weight:700;letter-spacing:-.01em;color:var(--purple-neon);font-variant-numeric:tabular-nums;line-height:1.2}
.tuile-label{font-size:11.5px;font-weight:500;color:var(--text-secondary);margin-top:4px}

/* ── Tableau des candidats ── */
.tableau-carte{border-radius:var(--radius-md);overflow:hidden;border:1px solid var(--border-subtle)}
table{width:100%;border-collapse:collapse;table-layout:fixed}
col.c-num{width:4.5%}
col.c-nom{width:18%}
col.c-email{width:23%}
col.c-tel{width:16%}
col.c-filiere{width:12.5%}
col.c-niveau{width:13%}
col.c-date{width:13%}
thead{display:table-header-group}
th{background:var(--purple-neon);color:#fff;text-align:left;font-size:10.5px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;padding:11px 12px;vertical-align:bottom}
td{padding:10px 12px;font-size:12.5px;color:var(--text-secondary);border-top:1px solid var(--border-subtle);vertical-align:top}
tbody tr:nth-child(even) td{background:var(--bg-surface)}
tr{page-break-inside:avoid}
.td-num{color:var(--text-muted);font-size:11.5px;font-variant-numeric:tabular-nums}
.td-nom{font-weight:600;color:var(--text-primary)}
.td-date{font-variant-numeric:tabular-nums;font-size:12px;white-space:nowrap}
.td-tel{white-space:nowrap;font-size:12px;font-variant-numeric:tabular-nums}
.td-email{overflow-wrap:anywhere}

/* ── État vide ── */
.vide{background:var(--purple-tint);border-radius:var(--radius-xl);padding:40px 24px;text-align:center;color:#5B32B8;font-size:14px;font-weight:500}
.vide svg{display:block;margin:0 auto 12px;opacity:.6}

/* ── Pied de document ── */
.basdepage{margin-top:32px;padding-top:14px;border-top:1px solid var(--border-subtle);display:flex;justify-content:space-between;gap:16px;font-size:10.5px;font-weight:300;color:var(--text-muted)}
.basdepage .accent{color:var(--purple-neon);font-weight:600}
</style>
</head>
<body>
<div class="page">

  <div class="no-print">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
    Astuce&nbsp;: utilisez <strong>&nbsp;Ctrl+P&nbsp;</strong> (ou Cmd+P) puis «&nbsp;Enregistrer au format PDF&nbsp;» pour télécharger ce bilan.
  </div>

  <button class="print-btn" onclick="window.print()">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
    Télécharger en PDF
  </button>

  <!-- En-tête plateforme -->
  <div class="entete">
    <div class="marque">
      <?php if ($logoDataUri !== ''): ?>
        <img src="<?= $h($logoDataUri) ?>" alt="Logo Connect'Academia">
      <?php else: ?>
        <span class="monogramme">CA</span>
      <?php endif; ?>
      <div>
        <div class="marque-nom">Connect'Academia</div>
        <div class="marque-tagline">Votre passerelle vers l'avenir</div>
      </div>
    </div>
    <div class="entete-droite">
      <div class="eyebrow">Bilan statistique</div>
      <div class="genere">Généré le <?= $h($genereLe) ?></div>
    </div>
  </div>

  <!-- Titre -->
  <h1 class="titre-doc"><?= $h($titreDoc) ?></h1>
  <p class="sous-titre"><b><?= $h($etabNom) ?></b><?= $etabVille !== '' ? ' — ' . $h($etabVille) : '' ?>, Gabon</p>
  <div class="periode">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
    Période du <?= $h($duStr) ?> au <?= $h($auStr) ?>
  </div>

  <!-- Tuiles statistiques -->
  <div class="tuiles">
    <div class="tuile">
      <div class="tuile-val"><?= $nombreFr($total) ?></div>
      <div class="tuile-label">Candidat<?= $total > 1 ? 's' : '' ?> sur la période</div>
    </div>
    <div class="tuile">
      <div class="tuile-val"><?= $nombreFr($nbEnligne) ?></div>
      <div class="tuile-label">Via la plateforme en ligne</div>
    </div>
    <div class="tuile">
      <div class="tuile-val"><?= $nombreFr($nbGuichet) ?></div>
      <div class="tuile-label">Au guichet de l'établissement</div>
    </div>
  </div>

  <?php if (empty($lignes)): ?>
    <div class="vide">
      <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="17" y1="8" x2="22" y2="13"/><line x1="22" y1="8" x2="17" y2="13"/></svg>
      Aucun candidat sur cette période.
    </div>
  <?php else: ?>
  <div class="tableau-carte">
    <table>
      <colgroup>
        <col class="c-num"><col class="c-nom"><col class="c-email">
        <col class="c-tel"><col class="c-filiere"><col class="c-niveau"><col class="c-date">
      </colgroup>
      <thead>
        <tr>
          <th>#</th>
          <th>Nom &amp; prénom</th>
          <th>Email</th>
          <th>Téléphone</th>
          <th>Filière</th>
          <th>Niveau</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($lignes as $i => $l): ?>
        <tr>
          <td class="td-num"><?= $i + 1 ?></td>
          <td class="td-nom"><?= $h($l['nom']) ?></td>
          <td class="td-email"><?= $h($l['email']) ?></td>
          <td class="td-tel"><?= $h($l['tel']) ?></td>
          <td><?= $h($l['filiere']) ?></td>
          <td><?= $h($l['niveau']) ?></td>
          <td class="td-date"><?= $h($l['date']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <div class="basdepage">
    <span><span class="accent">Connect'Academia</span> — plateforme d'orientation post-Bac, Gabon</span>
    <span>Document réservé à l'usage de <?= $h($etabNom) ?> — généré le <?= $h($genereLe) ?></span>
  </div>

</div>
</body>
</html>
