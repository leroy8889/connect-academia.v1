<?php
// Variables: $aRejouer (array), $tous (array), $flash (string|null), $flashError (string|null)
$aRejouer  = $aRejouer ?? [];
$tous      = $tous ?? [];
$flash     = $flash ?? null;
$flashError = $flashError ?? null;

$statutBadge = [
    'initie'    => ['class' => 'badge-attente',  'label' => 'Initié'],
    'en_cours'  => ['class' => 'badge-info',     'label' => 'En cours'],
    'reussi'    => ['class' => 'badge-actif',    'label' => 'Réussi'],
    'a_rejouer' => ['class' => 'badge-suspendu', 'label' => 'À rejouer'],
    'echoue'    => ['class' => 'badge-suspendu', 'label' => 'Échoué'],
];

// ── Synthèse dérivée des tableaux (aucune requête supplémentaire) ──
$fmt = static fn (int|float|null $v): string => number_format((int)$v, 0, ',', ' ');
$nbRejouer      = count($aRejouer);
$nbTotal        = count($tous);
$montantAttente = 0; $totalReverse = 0; $nbReussi = 0; $nbEchoue = 0;
foreach ($aRejouer as $r) { $montantAttente += (int)($r['montant_reverse'] ?? 0); }
foreach ($tous as $r) {
    if (($r['statut'] ?? '') === 'reussi') { $nbReussi++; $totalReverse += (int)($r['montant_reverse'] ?? 0); }
    if (($r['statut'] ?? '') === 'echoue') { $nbEchoue++; }
}
?>

<?php if ($flash): ?>
<div class="flash-banner flash-success" role="alert">
    <?= e($flash) ?>
    <button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;float:right;font-size:18px;color:inherit;">&times;</button>
</div>
<?php endif; ?>
<?php if ($flashError): ?>
<div class="flash-banner flash-error" role="alert">
    <?= e($flashError) ?>
    <button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;float:right;font-size:18px;color:inherit;">&times;</button>
</div>
<?php endif; ?>

<style>
/* ══════════════════════════════════════════════════════════════
   Réconciliation — conception Anthropic / couleurs Connect'Academia
   ══════════════════════════════════════════════════════════════ */
.flash-banner { border-radius:12px; padding:14px 20px; margin-bottom:20px; font-size:14px; font-weight:500; }
.flash-success { background:var(--green-bg); color:#15803d; border:1px solid #bbf7d0; }
.flash-error   { background:var(--red-bg);   color:#b91c1c; border:1px solid #fca5a5; }
.badge-info { background:#e0f2fe; color:#0369a1; }

.rc-head { margin-bottom:32px; }
.rc-eyebrow { font-size:11px; font-weight:700; letter-spacing:.14em; text-transform:uppercase; color:var(--txt-l); margin-bottom:10px; }
.rc-head-row { display:flex; align-items:flex-end; justify-content:space-between; gap:24px; flex-wrap:wrap; }
.rc-head h1 { font-family:var(--font-head); font-size:26px; font-weight:800; letter-spacing:-0.02em; color:var(--txt); margin:0; }
.rc-head p  { font-size:14px; line-height:1.5; color:var(--txt-m); margin:6px 0 0; max-width:560px; }
.rc-link { display:inline-flex; align-items:center; gap:7px; font-size:13.5px; font-weight:600; color:var(--txt-m); text-decoration:none; padding-bottom:2px; border-bottom:1.5px solid transparent; transition:color .13s, border-color .13s; }
.rc-link:hover { color:var(--ap); border-color:var(--ap); }
.rc-link svg { transition:transform .15s; }
.rc-link:hover svg { transform:translateX(-2px); }

/* ── Synthèse : hairline neutre, valeur porte l'emphase ─────── */
.rc-stats { display:grid; grid-template-columns:repeat(auto-fit, minmax(184px, 1fr)); gap:1px; background:var(--border); border:1px solid var(--border); border-radius:16px; overflow:hidden; margin-bottom:36px; }
.rc-stat { background:var(--card); padding:20px 22px; }
.rc-stat-label { font-size:11px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--txt-l); margin-bottom:12px; }
.rc-stat-val { font-family:var(--font-head); font-size:28px; font-weight:800; line-height:1; letter-spacing:-0.02em; color:var(--txt); font-variant-numeric:tabular-nums; }
.rc-stat-val .u { font-size:12px; font-weight:600; color:var(--txt-l); letter-spacing:0; margin-left:2px; }
.rc-stat-val.accent { color:var(--ap); }
.rc-stat-val.pos { color:#0f8a43; }
.rc-stat-val.warn { color:#b45309; }
.rc-stat-val.bad  { color:#b91c1c; }
.rc-stat-sub { font-size:11.5px; color:var(--txt-l); margin-top:8px; font-weight:500; }

/* ── Sections ──────────────────────────────────────────────── */
.rc-section { border:1px solid var(--border); border-radius:16px; overflow:hidden; margin-bottom:24px; background:var(--card); }
/* profondeur par surface (Anthropic), pas de bordure colorée */
.rc-section.pending { background:#FFFBF3; border-color:#F3E4C6; }
.rc-section-head { display:flex; align-items:baseline; gap:10px; padding:18px 22px; border-bottom:1px solid var(--border); }
.rc-section.pending .rc-section-head { border-bottom-color:#F3E4C6; }
.rc-section.pending .admin-table thead th { background:transparent; border-bottom-color:#F3E4C6; }
.rc-section.pending .admin-table tbody tr { border-bottom-color:#F3E4C6; }
.rc-section.pending .admin-table tbody tr:hover { background:#FFF6E6; }
.rc-dot { width:7px; height:7px; border-radius:50%; flex-shrink:0; align-self:center; }
.rc-dot.amber { background:var(--amber); }
.rc-dot.mut { background:var(--txt-l); }
.rc-section-head h2 { font-family:var(--font-head); font-size:15px; font-weight:700; color:var(--txt); margin:0; }
.rc-section-head p { font-size:12.5px; color:var(--txt-m); margin:0; }
.rc-count { margin-left:auto; font-size:12px; font-weight:700; padding:2px 10px; border-radius:999px; background:var(--bg); color:var(--txt-m); font-variant-numeric:tabular-nums; align-self:center; }
.rc-count.amber { background:#FBEBCE; color:#b45309; }

.montant-cell { font-variant-numeric:tabular-nums; font-weight:600; white-space:nowrap; }
.err-cell { font-size:11px; color:var(--txt-l); max-width:220px; white-space:pre-wrap; word-break:break-word; }

.btn-rejouer { display:inline-flex; align-items:center; gap:5px; padding:6px 12px; border-radius:8px; font-size:12px; font-weight:600; border:none; cursor:pointer; background:var(--ap); color:#fff; transition:background .13s, opacity .13s; }
.btn-rejouer:hover:not(:disabled) { background:var(--ap-dk); }
.btn-rejouer:disabled { opacity:.5; cursor:not-allowed; }
.rc-maxed { font-size:11px; font-weight:500; color:var(--txt-l); }

.rc-empty { display:flex; flex-direction:column; align-items:center; gap:6px; padding:44px 24px; text-align:center; }
.rc-empty svg { color:var(--txt-l); opacity:.4; margin-bottom:6px; }
.rc-empty p { font-family:var(--font-head); font-size:15px; font-weight:700; color:var(--txt); margin:0; }
.rc-empty span { font-size:12.5px; color:var(--txt-m); }
.u-mut { color:var(--txt-l); font-weight:500; font-size:11px; }
</style>

<!-- ── En-tête ──────────────────────────────────────────────── -->
<div class="rc-head">
    <div class="rc-eyebrow">Orientation · Finance</div>
    <div class="rc-head-row">
        <div>
            <h1>Réconciliation</h1>
            <p>Reversements en échec à relancer et historique complet des transferts vers les établissements.</p>
        </div>
        <a href="<?= url('/admin/orientation/paiements') ?>" class="rc-link">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            Paiements écoles
        </a>
    </div>
</div>

<!-- ── Synthèse ─────────────────────────────────────────────── -->
<div class="rc-stats">
    <div class="rc-stat">
        <div class="rc-stat-label">À rejouer</div>
        <div class="rc-stat-val <?= $nbRejouer > 0 ? 'warn' : '' ?>"><?= $fmt($nbRejouer) ?></div>
    </div>
    <div class="rc-stat">
        <div class="rc-stat-label">Montant en attente</div>
        <div class="rc-stat-val <?= $montantAttente > 0 ? 'warn' : '' ?>"><?= $fmt($montantAttente) ?><span class="u">XAF</span></div>
    </div>
    <div class="rc-stat">
        <div class="rc-stat-label">Total reversé</div>
        <div class="rc-stat-val pos"><?= $fmt($totalReverse) ?><span class="u">XAF</span></div>
        <div class="rc-stat-sub"><?= $fmt($nbReussi) ?> transfert<?= $nbReussi > 1 ? 's' : '' ?> confirmé<?= $nbReussi > 1 ? 's' : '' ?></div>
    </div>
    <div class="rc-stat">
        <div class="rc-stat-label">Échoués</div>
        <div class="rc-stat-val <?= $nbEchoue > 0 ? 'bad' : '' ?>"><?= $fmt($nbEchoue) ?></div>
    </div>
</div>

<!-- ── À rejouer ────────────────────────────────────────────── -->
<div class="rc-section<?= $aRejouer ? ' pending' : '' ?>">
    <div class="rc-section-head">
        <span class="rc-dot <?= $aRejouer ? 'amber' : 'mut' ?>"></span>
        <div>
            <h2>Reversements à rejouer</h2>
            <p>Transferts en échec récupérables — 5 tentatives maximum</p>
        </div>
        <span class="rc-count <?= $aRejouer ? 'amber' : '' ?>"><?= $fmt($nbRejouer) ?></span>
    </div>

    <?php if (empty($aRejouer)): ?>
    <div class="rc-empty">
        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        <p>Tout est à jour</p>
        <span>Aucun reversement en attente.</span>
    </div>
    <?php else: ?>
    <div class="admin-table-wrap" style="box-shadow:none;margin:0;border-radius:0;background:transparent;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Établissement</th>
                    <th style="text-align:right;">Brut</th>
                    <th style="text-align:right;">Commission</th>
                    <th style="text-align:right;">À reverser</th>
                    <th>Destination</th>
                    <th>Mode</th>
                    <th style="text-align:center;">Essais</th>
                    <th>Statut</th>
                    <th>Erreur</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($aRejouer as $r): ?>
            <?php
                $b  = $statutBadge[$r['statut']] ?? ['class' => '', 'label' => $r['statut']];
                $maxed = (int)$r['tentatives'] >= 5;
            ?>
            <tr id="rev-row-<?= $r['id'] ?>">
                <td style="color:var(--txt-l);font-size:12px;"><?= $r['id'] ?></td>
                <td><strong style="font-weight:600;"><?= e($r['etab_nom']) ?></strong></td>
                <td class="montant-cell" style="text-align:right;"><?= $fmt($r['montant_brut']) ?> <span class="u-mut">XAF</span></td>
                <td class="montant-cell" style="text-align:right;color:var(--txt-l);"><?= $fmt($r['commission_plateforme']) ?> XAF</td>
                <td class="montant-cell" style="text-align:right;color:var(--ap);"><?= $fmt($r['montant_reverse']) ?> XAF</td>
                <td style="font-size:12px;"><?= e($r['numero_destination'] ?? '—') ?></td>
                <td style="font-size:12px;"><?= e($r['withdraw_mode'] ?? '—') ?></td>
                <td style="text-align:center;font-variant-numeric:tabular-nums;font-weight:600;"><?= (int)$r['tentatives'] ?><span style="color:var(--txt-l);font-weight:400;">/5</span></td>
                <td><span class="badge <?= $b['class'] ?>"><?= $b['label'] ?></span></td>
                <td class="err-cell"><?= e(mb_substr($r['message_erreur'] ?? '', 0, 100)) ?></td>
                <td>
                    <?php if (!$maxed): ?>
                    <button
                        class="btn-rejouer"
                        data-rev-id="<?= $r['id'] ?>"
                        onclick="rejouerRev(this, <?= $r['id'] ?>)"
                    >
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2v6h6"/><path d="M21 12A9 9 0 0 0 6 5.3L3 8"/></svg>
                        Rejouer
                    </button>
                    <?php else: ?>
                    <span class="rc-maxed">Max atteint</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ── Historique ───────────────────────────────────────────── -->
<div class="rc-section">
    <div class="rc-section-head">
        <span class="rc-dot mut"></span>
        <div>
            <h2>Historique complet</h2>
            <p>Tous les reversements enregistrés</p>
        </div>
        <span class="rc-count"><?= $fmt($nbTotal) ?></span>
    </div>

    <?php if (empty($tous)): ?>
    <div class="rc-empty">
        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v5h5"/><path d="M3.05 13A9 9 0 1 0 6 5.3L3 8"/><path d="M12 7v5l4 2"/></svg>
        <p>Aucun reversement</p>
        <span>L'historique est vide pour le moment.</span>
    </div>
    <?php else: ?>
    <div class="admin-table-wrap" style="box-shadow:none;margin:0;border-radius:0;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Établissement</th>
                    <th style="text-align:right;">Brut</th>
                    <th style="text-align:right;">Commission</th>
                    <th style="text-align:right;">Reversé</th>
                    <th>Destination</th>
                    <th>Statut</th>
                    <th>Initié le</th>
                    <th>Confirmé le</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tous as $r): ?>
            <?php $b = $statutBadge[$r['statut']] ?? ['class' => '', 'label' => $r['statut']]; ?>
            <tr>
                <td style="color:var(--txt-l);font-size:12px;"><?= $r['id'] ?></td>
                <td><?= e($r['etab_nom']) ?></td>
                <td class="montant-cell" style="text-align:right;"><?= $fmt($r['montant_brut']) ?> <span class="u-mut">XAF</span></td>
                <td class="montant-cell" style="text-align:right;color:var(--txt-l);"><?= $fmt($r['commission_plateforme']) ?> XAF</td>
                <td class="montant-cell" style="text-align:right;color:var(--ap);"><?= $fmt($r['montant_reverse']) ?> XAF</td>
                <td style="font-size:12px;"><?= e($r['numero_destination'] ?? '—') ?></td>
                <td><span class="badge <?= $b['class'] ?>"><?= $b['label'] ?></span></td>
                <td style="font-size:12px;color:var(--txt-l);"><?= $r['date_initiation'] ? date('d/m/Y H:i', strtotime($r['date_initiation'])) : '—' ?></td>
                <td style="font-size:12px;color:var(--txt-l);"><?= $r['date_confirmation'] ? date('d/m/Y H:i', strtotime($r['date_confirmation'])) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
function rejouerRev(btn, revId) {
    btn.disabled = true;
    btn.textContent = '…';

    fetch(`<?= url('/admin/orientation/reconciliation/') ?>${revId}/rejouer`, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ _csrf: '<?= e($_SESSION['csrf_token'] ?? '') ?>' }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const row = document.getElementById(`rev-row-${revId}`);
            if (row) {
                row.style.opacity = '.4';
                setTimeout(() => row.remove(), 800);
            }
            showAlert(data.message, 'success');
        } else {
            btn.disabled = false;
            btn.textContent = 'Rejouer';
            showAlert(data.message, 'error');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.textContent = 'Rejouer';
        showAlert('Erreur réseau.', 'error');
    });
}

function showAlert(msg, type) {
    const el = document.createElement('div');
    el.className = `flash-banner flash-${type}`;
    el.textContent = msg;
    document.querySelector('.rc-head').after(el);
    setTimeout(() => el.remove(), 5000);
}
</script>
