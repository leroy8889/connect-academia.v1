<?php
// Variables : $paiements, $stats, $etabs, $etabFiltre, $statFiltre
$paiements  = $paiements ?? [];
$stats      = $stats ?? [];
$etabs      = $etabs ?? [];
$etabFiltre = $etabFiltre ?? null;
$statFiltre = $statFiltre ?? '';

$flashError = \Core\Session::getFlash('error');

$statutBadge = [
    'initie'     => ['class' => 'badge-attente',  'label' => 'Initié'],
    'en_attente' => ['class' => 'badge-attente',  'label' => 'En attente'],
    'reussi'     => ['class' => 'badge-actif',    'label' => 'Payé'],
    'echoue'     => ['class' => 'badge-suspendu', 'label' => 'Échoué'],
];
$revBadge = [
    'initie'    => ['class' => 'badge-attente',  'label' => 'Initié'],
    'en_cours'  => ['class' => 'badge-info',     'label' => 'En cours'],
    'reussi'    => ['class' => 'badge-actif',    'label' => 'Reversé'],
    'a_rejouer' => ['class' => 'badge-suspendu', 'label' => 'À rejouer'],
    'echoue'    => ['class' => 'badge-suspendu', 'label' => 'Échoué'],
];
$fmt = static fn (int|float|null $v): string => number_format((int)$v, 0, ',', ' ');

$filtresActifs = ($etabFiltre !== null) || ($statFiltre !== '');
$echecs        = (int)($stats['reversements_en_echec'] ?? 0);
?>

<?php if ($flashError): ?>
<div class="flash-banner flash-error" role="alert" style="border-radius:12px;padding:14px 20px;margin-bottom:20px;font-size:14px;background:var(--red-bg);color:#b91c1c;border:1px solid #fca5a5;">
    <?= e($flashError) ?>
</div>
<?php endif; ?>

<style>
/* ══════════════════════════════════════════════════════════════
   Paiements écoles — conception Anthropic / couleurs Connect'Academia
   Hairlines neutres · profondeur par surface · typo porte l'emphase
   ══════════════════════════════════════════════════════════════ */
.badge-info { background:#e0f2fe; color:#0369a1; }

.pe-head { margin-bottom:32px; }
.pe-eyebrow { font-size:11px; font-weight:700; letter-spacing:.14em; text-transform:uppercase; color:var(--txt-l); margin-bottom:10px; }
.pe-head-row { display:flex; align-items:flex-end; justify-content:space-between; gap:24px; flex-wrap:wrap; }
.pe-head h1 { font-family:var(--font-head); font-size:26px; font-weight:800; letter-spacing:-0.02em; color:var(--txt); margin:0; }
.pe-head p  { font-size:14px; line-height:1.5; color:var(--txt-m); margin:6px 0 0; max-width:560px; }
.pe-link { display:inline-flex; align-items:center; gap:7px; font-size:13.5px; font-weight:600; color:var(--txt-m); text-decoration:none; padding-bottom:2px; border-bottom:1.5px solid transparent; transition:color .13s, border-color .13s; }
.pe-link:hover { color:var(--ap); border-color:var(--ap); }
.pe-link svg { transition:transform .15s; }
.pe-link:hover svg { transform:translateX(2px); }

/* ── Stats : cartes nues, hairline, aucune bordure colorée ──── */
.pe-stats { display:grid; grid-template-columns:repeat(auto-fit, minmax(184px, 1fr)); gap:1px; background:var(--border); border:1px solid var(--border); border-radius:16px; overflow:hidden; margin-bottom:32px; }
.pe-stat { background:var(--card); padding:20px 22px; }
.pe-stat-label { font-size:11px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--txt-l); margin-bottom:12px; }
.pe-stat-val { font-family:var(--font-head); font-size:28px; font-weight:800; line-height:1; letter-spacing:-0.02em; color:var(--txt); font-variant-numeric:tabular-nums; }
.pe-stat-val .u { font-size:12px; font-weight:600; color:var(--txt-l); letter-spacing:0; margin-left:2px; }
.pe-stat-val.accent { color:var(--ap); }
.pe-stat-val.pos { color:#0f8a43; }
.pe-stat-val.warn { color:#b91c1c; }
.pe-stat-sub { font-size:11.5px; color:var(--txt-l); margin-top:8px; font-weight:500; }

/* ── Barre filtres ─────────────────────────────────────────── */
.pe-filters { display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:16px; }
.pe-filters-lbl { font-size:12px; font-weight:600; color:var(--txt-l); }
.pe-select { padding:8px 32px 8px 12px; border:1px solid var(--border-strong); border-radius:8px; font-size:13px; font-family:var(--font-body); background:var(--card); color:var(--txt); cursor:pointer; appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%239E9E9E' stroke-width='2' stroke-linecap='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 10px center; transition:border-color .13s; }
.pe-select:hover { border-color:var(--txt-l); }
.pe-select:focus { outline:none; border-color:var(--ap); box-shadow:0 0 0 3px var(--ap-glow-20); }
.pe-reset { font-size:12.5px; font-weight:600; color:var(--txt-l); text-decoration:none; border-bottom:1.5px solid transparent; transition:color .13s, border-color .13s; }
.pe-reset:hover { color:var(--ap); border-color:var(--ap); }
.pe-tally { margin-left:auto; font-size:12.5px; color:var(--txt-l); }
.pe-tally b { color:var(--txt); font-weight:700; font-variant-numeric:tabular-nums; }

/* ── Table ─────────────────────────────────────────────────── */
.montant-cell { font-variant-numeric:tabular-nums; font-weight:600; white-space:nowrap; }
.pe-card { background:var(--card); border:1px solid var(--border); border-radius:16px; overflow:hidden; }
.pe-etab-name { font-size:13px; font-weight:600; color:var(--txt); }
.btn-recu-pdf { display:inline-flex; align-items:center; gap:5px; padding:5px 10px; border-radius:7px; font-size:11.5px; font-weight:600; border:1px solid var(--border-strong); background:transparent; color:var(--txt-m); text-decoration:none; white-space:nowrap; transition:border-color .13s, color .13s; }
.btn-recu-pdf:hover { border-color:var(--ap); color:var(--ap); }
.pe-empty { padding:64px 24px; text-align:center; }
.pe-empty svg { color:var(--txt-l); opacity:.4; margin-bottom:14px; }
.pe-empty p { font-family:var(--font-head); font-size:16px; font-weight:700; color:var(--txt); margin:0 0 4px; }
.pe-empty span { font-size:13px; color:var(--txt-m); }
.u-mut { color:var(--txt-l); font-weight:500; }

@media (max-width:640px) {
    .pe-filters { align-items:stretch; flex-direction:column; }
    .pe-select { width:100%; }
    .pe-tally { margin-left:0; }
}
</style>

<!-- ── En-tête ──────────────────────────────────────────────── -->
<div class="pe-head">
    <div class="pe-eyebrow">Orientation · Finance</div>
    <div class="pe-head-row">
        <div>
            <h1>Paiements écoles</h1>
            <p>Inscriptions encaissées via MoneyFusion, commissions de la plateforme et reversements aux établissements.</p>
        </div>
        <a href="<?= url('/admin/orientation/reconciliation') ?>" class="pe-link">
            Réconciliation des reversements
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </a>
    </div>
</div>

<!-- ── Statistiques ─────────────────────────────────────────── -->
<div class="pe-stats">
    <div class="pe-stat">
        <div class="pe-stat-label">Paiements confirmés</div>
        <div class="pe-stat-val"><?= $fmt($stats['nb_reussis'] ?? 0) ?></div>
    </div>
    <div class="pe-stat">
        <div class="pe-stat-label">Total encaissé</div>
        <div class="pe-stat-val accent"><?= $fmt($stats['montant_total'] ?? 0) ?><span class="u">XAF</span></div>
    </div>
    <div class="pe-stat">
        <div class="pe-stat-label">Reversé aux écoles</div>
        <div class="pe-stat-val pos"><?= $fmt($stats['montant_reverse'] ?? 0) ?><span class="u">XAF</span></div>
    </div>
    <div class="pe-stat">
        <div class="pe-stat-label">Commissions</div>
        <div class="pe-stat-val"><?= $fmt($stats['commission_totale'] ?? 0) ?><span class="u">XAF</span></div>
    </div>
    <div class="pe-stat">
        <div class="pe-stat-label">Reversements en échec</div>
        <div class="pe-stat-val <?= $echecs > 0 ? 'warn' : '' ?>"><?= $fmt($echecs) ?></div>
        <?php if ($echecs > 0): ?>
        <div class="pe-stat-sub">à relancer en réconciliation</div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Filtres ──────────────────────────────────────────────── -->
<form method="get" action="<?= url('/admin/orientation/paiements') ?>" class="pe-filters">
    <span class="pe-filters-lbl">Filtrer</span>
    <select name="etab" class="pe-select" onchange="this.form.submit()">
        <option value="">Tous les établissements</option>
        <?php foreach ($etabs as $et): ?>
        <option value="<?= (int)$et['id'] ?>" <?= $etabFiltre === (int)$et['id'] ? 'selected' : '' ?>>
            <?= e($et['nom']) ?>
        </option>
        <?php endforeach; ?>
    </select>
    <select name="statut" class="pe-select" onchange="this.form.submit()">
        <option value="">Tous les statuts</option>
        <?php foreach ($statutBadge as $cle => $b): ?>
        <option value="<?= e($cle) ?>" <?= $statFiltre === $cle ? 'selected' : '' ?>><?= e($b['label']) ?></option>
        <?php endforeach; ?>
    </select>
    <?php if ($filtresActifs): ?>
    <a href="<?= url('/admin/orientation/paiements') ?>" class="pe-reset">Réinitialiser</a>
    <?php endif; ?>
    <span class="pe-tally"><b><?= count($paiements) ?></b> paiement<?= count($paiements) > 1 ? 's' : '' ?></span>
</form>

<!-- ── Tableau ──────────────────────────────────────────────── -->
<div class="pe-card">
<?php if (empty($paiements)): ?>
    <div class="pe-empty">
        <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
        <p>Aucun paiement</p>
        <span><?= $filtresActifs ? 'Aucun résultat pour ces filtres.' : 'Les paiements apparaîtront ici dès la première inscription payée.' ?></span>
    </div>
<?php else: ?>
    <div class="admin-table-wrap" style="box-shadow:none;margin:0;border-radius:0;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Établissement</th>
                    <th>Candidat</th>
                    <th style="text-align:right;">Montant</th>
                    <th>Statut</th>
                    <th style="text-align:right;">Commission</th>
                    <th style="text-align:right;">Net reversé</th>
                    <th>Reversement</th>
                    <th>Reçu</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($paiements as $p): ?>
            <?php
                $ps = $statutBadge[$p['statut']] ?? ['class' => '', 'label' => $p['statut']];
                $rv = $p['reversement_statut'] !== null
                    ? ($revBadge[$p['reversement_statut']] ?? ['class' => '', 'label' => $p['reversement_statut']])
                    : null;
            ?>
            <tr>
                <td style="font-size:12px;color:var(--txt-l);white-space:nowrap;">
                    <?= date('d/m/Y', strtotime($p['date_paiement'])) ?>
                    <div style="font-size:11px;"><?= date('H:i', strtotime($p['date_paiement'])) ?></div>
                </td>
                <td><span class="pe-etab-name"><?= e($p['etab_nom']) ?></span></td>
                <td>
                    <div style="font-size:13px;font-weight:600;"><?= e($p['nom_complet'] ?? '—') ?></div>
                    <div style="font-size:11.5px;color:var(--txt-l);"><?= e($p['email'] ?? '') ?></div>
                </td>
                <td class="montant-cell" style="text-align:right;"><?= $fmt($p['montant']) ?> <span class="u-mut" style="font-size:11px;">XAF</span></td>
                <td><span class="badge <?= $ps['class'] ?>"><?= e($ps['label']) ?></span></td>
                <td class="montant-cell" style="text-align:right;color:var(--txt-l);">
                    <?= $rv !== null ? $fmt($p['commission_plateforme']) . ' XAF' : '—' ?>
                </td>
                <td class="montant-cell" style="text-align:right;color:var(--ap);">
                    <?= $rv !== null ? $fmt($p['montant_reverse']) . ' XAF' : '—' ?>
                </td>
                <td>
                    <?php if ($rv !== null): ?>
                    <span class="badge <?= $rv['class'] ?>"><?= e($rv['label']) ?></span>
                    <div style="font-size:11px;color:var(--txt-l);margin-top:3px;"><?= e($p['numero_destination'] ?? '') ?></div>
                    <?php else: ?>
                    <span style="font-size:12px;color:var(--txt-l);">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($p['recu_cle'])): ?>
                    <a href="<?= url('/admin/orientation/paiements/' . (int)$p['id'] . '/recu') ?>" target="_blank" rel="noopener" class="btn-recu-pdf">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        <?= e($p['numero_recu'] ?? 'PDF') ?>
                    </a>
                    <?php else: ?>
                    <span style="font-size:12px;color:var(--txt-l);">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</div>
