<?php
$transactions        = $transactions        ?? [];
$counts              = $counts              ?? ['total'=>0,'succes'=>0,'en_attente'=>0,'echec'=>0];
$caTotal             = $caTotal             ?? 0;
$ca30j               = $ca30j              ?? 0;
$ca7j                = $ca7j               ?? 0;
$abonnementsActifs   = $abonnementsActifs   ?? 0;
$abonnementsExpiresM = $abonnementsExpiresM ?? 0;
$filters             = $filters             ?? [];
$page                = (int)($page          ?? 1);
$totalPages          = (int)($totalPages    ?? 1);
$total               = (int)($total         ?? 0);
$csrfToken           = \Core\Session::getCsrfToken();

function statutBadgePay(string $statut): string {
    return match($statut) {
        'succes'     => '<span class="badge-pay badge-pay--success"><span class="badge-dot"></span>Succès</span>',
        'en_attente' => '<span class="badge-pay badge-pay--pending"><span class="badge-dot"></span>En attente</span>',
        'echec'      => '<span class="badge-pay badge-pay--error"><span class="badge-dot"></span>Échec</span>',
        'rembourse'  => '<span class="badge-pay badge-pay--refund"><span class="badge-dot"></span>Remboursé</span>',
        default      => '<span class="badge-pay badge-pay--muted">' . e($statut) . '</span>',
    };
}

function paiUrl(array $filters, int $page): string {
    $p = array_filter($filters, fn($v) => $v !== '');
    if ($page > 1) $p['page'] = $page;
    return url('/admin/paiement') . ($p ? '?' . http_build_query($p) : '');
}
?>

<style>
/* ══════════════════════════════════════════════════════════════════════
   PAIEMENTS — conception Claude / couleurs Connect'Academia
   Surfaces pleines, hairlines neutres, aucune ombre portée décorative.
   La typographie porte la hiérarchie ; le violet ne sert qu'aux accents.
   ══════════════════════════════════════════════════════════════════════ */

/* ── En-tête ─────────────────────────────────────────────────────── */
.pay-head { margin-bottom: 28px; }
.pay-eyebrow {
  font-size: 11px; font-weight: 700; letter-spacing: .14em;
  text-transform: uppercase; color: var(--txt-l); margin-bottom: 10px;
}
.pay-head-row { display: flex; align-items: flex-end; justify-content: space-between; gap: 24px; flex-wrap: wrap; }
.pay-head h1 {
  font-family: var(--font-head); font-size: 26px; font-weight: 800;
  letter-spacing: -0.02em; color: var(--txt); margin: 0;
}
.pay-head p { font-size: 14px; line-height: 1.5; color: var(--txt-m); margin: 6px 0 0; max-width: 560px; }
.pay-head p b { color: var(--txt); font-weight: 700; font-variant-numeric: tabular-nums; }
.pay-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

/* Boutons de la barre d'actions — pilules hairline, un seul plein */
.pay-btn {
  display: inline-flex; align-items: center; gap: 7px;
  height: 36px; padding: 0 15px; border-radius: var(--r-pill);
  border: 1px solid var(--border-strong); background: var(--card);
  font-family: var(--font-body); font-size: 12.5px; font-weight: 600;
  color: var(--txt-m); text-decoration: none; cursor: pointer; white-space: nowrap;
  transition: border-color .14s ease, color .14s ease, background .14s ease;
}
.pay-btn svg { flex: none; }
.pay-btn:hover { border-color: var(--ap); color: var(--ap); background: var(--ap-lt); }
.pay-btn[disabled] { opacity: .55; cursor: default; }
.pay-btn--solid { background: var(--txt); border-color: var(--txt); color: #fff; }
.pay-btn--solid:hover { background: #000; border-color: #000; color: #fff; }
.pay-btn--alert { border-color: #F0C98B; color: #92600A; background: var(--amber-bg); }
.pay-btn--alert:hover { border-color: #D9930F; color: #92600A; background: #FFE9BE; }
.pay-btn-count {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 19px; height: 19px; padding: 0 6px; border-radius: 999px;
  background: #B45309; color: #fff; font-size: 10.5px; font-weight: 800;
  font-variant-numeric: tabular-nums;
}

/* ── Statistiques — bloc soudé, séparateurs 1px ──────────────────── */
.pay-stats {
  display: grid; grid-template-columns: repeat(4, 1fr); gap: 1px;
  background: var(--border); border: 1px solid var(--border);
  border-radius: 16px; overflow: hidden; margin-bottom: 16px;
}
.pay-stats--sub { grid-template-columns: repeat(4, 1fr); margin-bottom: 28px; }
.pay-stat { background: var(--card); padding: 20px 22px; transition: background .14s ease; }
.pay-stat:hover { background: var(--ap-xl); }
.pay-stat--wide { grid-column: span 2; }
.pay-stat-label {
  font-size: 11px; font-weight: 700; letter-spacing: .08em;
  text-transform: uppercase; color: var(--txt-l); margin-bottom: 12px;
  display: flex; align-items: center; gap: 7px;
}
.pay-stat-label svg { color: var(--txt-l); flex: none; }
.pay-stat-val {
  font-family: var(--font-head); font-size: 28px; font-weight: 800;
  line-height: 1; letter-spacing: -0.025em; color: var(--txt);
  font-variant-numeric: tabular-nums;
}
.pay-stat--wide .pay-stat-val { font-size: 34px; }
.pay-stat-val .u { font-size: 12px; font-weight: 700; color: var(--txt-l); letter-spacing: 0; margin-left: 4px; }
.pay-stat-val.accent { color: var(--ap); }
.pay-stat-val.pos    { color: #0F8A43; }
.pay-stat-val.warn   { color: #B45309; }
.pay-stat-sub { font-size: 11.5px; color: var(--txt-l); margin-top: 10px; font-weight: 500; }
.pay-stat-sub b { color: var(--txt-m); font-weight: 700; }

/* Jauge de conversion — barre 4px, jamais un camembert pour 2 valeurs */
.pay-meter { height: 4px; border-radius: 999px; background: var(--border); overflow: hidden; margin-top: 14px; }
.pay-meter i { display: block; height: 100%; background: var(--ap); border-radius: 999px; }
.pay-meter i.pos { background: #0F8A43; }

/* ── Badges statut ───────────────────────────────────────────────── */
.badge-pay {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 4px 10px; border-radius: 999px;
  font-size: 11px; font-weight: 700; letter-spacing: .15px; white-space: nowrap;
}
.badge-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
.badge-pay--success { background: var(--green-bg); color: #15803D; }
.badge-pay--success .badge-dot { background: #22C55E; }
.badge-pay--pending { background: var(--amber-bg); color: #92600A; }
.badge-pay--pending .badge-dot { background: #F59E0B; }
.badge-pay--error   { background: var(--red-bg);   color: #B91C1C; }
.badge-pay--error   .badge-dot { background: #EF4444; }
.badge-pay--refund  { background: var(--blue-bg);  color: #1D4ED8; }
.badge-pay--refund  .badge-dot { background: #3B82F6; }
.badge-pay--muted   { background: var(--bg-2, #F3F1EA); color: var(--txt-m); }

/* ── Badges plan ─────────────────────────────────────────────────── */
.pay-plan-badge {
  display: inline-flex; align-items: center; padding: 3px 10px;
  border-radius: 999px; font-size: 11px; font-weight: 700;
}
.pay-plan-mensuel { background: var(--ap-lt); color: var(--ap); }
.pay-plan-annuel  { background: var(--txt); color: #fff; }

/* ── Carte tableau ───────────────────────────────────────────────── */
.pay-card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; }
.pay-table-hd {
  padding: 18px 22px; display: flex; align-items: center;
  justify-content: space-between; gap: 12px; border-bottom: 1px solid var(--border);
}
.pay-table-hd-left h2 { font-family: var(--font-head); font-size: 15px; font-weight: 800; color: var(--txt); letter-spacing: -0.01em; }
.pay-table-hd-left p  { font-size: 12px; color: var(--txt-l); margin-top: 3px; }
.pay-reset-btn {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 12.5px; font-weight: 600; color: var(--txt-l);
  text-decoration: none; border-bottom: 1.5px solid transparent;
  transition: color .13s, border-color .13s; background: none; border-radius: 0;
  padding: 0 0 1px;
}
.pay-reset-btn:hover { color: var(--ap); border-bottom-color: var(--ap); }

/* ── Barre de filtres ────────────────────────────────────────────── */
.pay-filter-bar {
  padding: 14px 22px; border-bottom: 1px solid var(--border);
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
.pay-filter-bar select,
.pay-filter-bar input[type="date"] {
  height: 36px; padding: 0 12px;
  border: 1px solid var(--border-strong); border-radius: 8px;
  font-family: var(--font-body); font-size: 13px;
  background: var(--card); color: var(--txt); outline: none; cursor: pointer;
  transition: border-color .13s, box-shadow .13s;
}
.pay-filter-bar select:hover,
.pay-filter-bar input[type="date"]:hover { border-color: var(--txt-l); }
.pay-filter-bar select:focus,
.pay-filter-bar input[type="date"]:focus { border-color: var(--ap); box-shadow: 0 0 0 3px var(--ap-glow-20); }
.pay-search-wrap { position: relative; flex: 1; min-width: 200px; max-width: 300px; }
.pay-search-wrap svg { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--txt-l); pointer-events: none; }
.pay-search-input {
  width: 100%; height: 36px; padding: 0 12px 0 34px;
  border: 1px solid var(--border-strong); border-radius: 8px;
  font-family: var(--font-body); font-size: 13px;
  background: var(--card); color: var(--txt); outline: none;
  transition: border-color .13s, box-shadow .13s;
}
.pay-search-input:hover { border-color: var(--txt-l); }
.pay-search-input:focus { border-color: var(--ap); box-shadow: 0 0 0 3px var(--ap-glow-20); }
.pay-search-input::placeholder { color: var(--txt-l); }

/* ── Cellules ────────────────────────────────────────────────────── */
.pay-ref-cell {
  display: inline-flex; align-items: center; gap: 6px;
  font-family: ui-monospace, 'Cascadia Code', 'Courier New', monospace;
  font-size: 11px; color: var(--txt-m);
  background: var(--bg-2, #F3F1EA); border-radius: 6px; padding: 4px 8px; max-width: 170px;
}
.pay-ref-text { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; }
.pay-copy-btn {
  cursor: pointer; background: none; border: none; padding: 0;
  display: flex; align-items: center; color: var(--txt-l);
  transition: color .13s; flex-shrink: 0;
}
.pay-copy-btn:hover { color: var(--ap); }
.pay-payload-btn {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 5px 10px; background: none;
  border: 1px solid var(--border-strong); border-radius: 7px;
  font-size: 11.5px; font-weight: 600; color: var(--txt-m);
  cursor: pointer; font-family: var(--font-body);
  transition: border-color .13s, color .13s;
}
.pay-payload-btn:hover { border-color: var(--ap); color: var(--ap); }
.pay-amount { display: inline-flex; align-items: baseline; gap: 3px; font-variant-numeric: tabular-nums; }
.pay-amount strong { font-family: var(--font-head); font-size: 14px; font-weight: 800; color: var(--txt); }
.pay-amount span   { font-size: 10px; font-weight: 700; color: var(--txt-l); }
.pay-cell-date  { font-size: 12px; font-weight: 600; color: var(--txt); font-variant-numeric: tabular-nums; }
.pay-cell-time  { font-size: 11px; color: var(--txt-l); margin-top: 1px; font-variant-numeric: tabular-nums; }
.pay-cell-mute  { color: var(--txt-l); font-size: 12px; }
.pay-method { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: var(--txt-m); }

/* ── État vide ───────────────────────────────────────────────────── */
.pay-empty { padding: 64px 24px; text-align: center; }
.pay-empty-icon { color: var(--txt-l); opacity: .4; margin: 0 auto 14px; display: block; }
.pay-empty h4 { font-family: var(--font-head); font-size: 16px; font-weight: 700; color: var(--txt); margin-bottom: 5px; }
.pay-empty p  { font-size: 13px; color: var(--txt-m); }

/* ── Pagination ──────────────────────────────────────────────────── */
.pay-pager {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  padding: 16px 22px; border-top: 1px solid var(--border);
}
.pay-pager-btn {
  display: inline-flex; align-items: center; gap: 6px;
  height: 34px; padding: 0 14px; border-radius: var(--r-pill);
  border: 1px solid var(--border-strong); background: var(--card);
  font-size: 12.5px; font-weight: 600; color: var(--txt-m);
  text-decoration: none; font-family: var(--font-body);
  transition: border-color .13s, color .13s, background .13s;
}
.pay-pager-btn:hover { border-color: var(--ap); color: var(--ap); background: var(--ap-lt); }
.pay-pager-info { padding: 0 12px; font-size: 12.5px; font-weight: 600; color: var(--txt-l); font-variant-numeric: tabular-nums; }

/* ── Modale payload ──────────────────────────────────────────────── */
.pay-modal-overlay {
  position: fixed; inset: 0; background: rgba(26,26,26,.42);
  -webkit-backdrop-filter: blur(3px); backdrop-filter: blur(3px);
  z-index: 1000; display: flex; align-items: center; justify-content: center;
  padding: 20px; animation: pov-in 160ms ease;
}
@keyframes pov-in { from { opacity: 0; } to { opacity: 1; } }
.pay-modal {
  background: var(--card); border: 1px solid var(--border); border-radius: 16px;
  width: 100%; max-width: 680px; max-height: 85vh;
  display: flex; flex-direction: column; overflow: hidden;
  box-shadow: 0 24px 64px rgba(26,26,26,.18);
  animation: pm-in 240ms cubic-bezier(.2,.8,.2,1);
}
@keyframes pm-in {
  from { opacity: 0; transform: translateY(10px) scale(.99); }
  to   { opacity: 1; transform: translateY(0)    scale(1);   }
}
.pay-modal-head {
  padding: 18px 22px; border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-shrink: 0;
}
.pay-modal-head-left { display: flex; align-items: center; gap: 12px; }
.pay-modal-icon {
  width: 36px; height: 36px; background: var(--ap-lt); border-radius: 10px;
  display: grid; place-items: center; color: var(--ap); flex-shrink: 0;
}
.pay-modal-head h3 { font-family: var(--font-head); font-size: 14px; font-weight: 800; color: var(--txt); }
.pay-modal-head p  { font-size: 11.5px; color: var(--txt-l); margin-top: 2px; }
.pay-modal-x {
  width: 32px; height: 32px; border-radius: 999px; background: none;
  border: 1px solid var(--border-strong); cursor: pointer;
  display: grid; place-items: center; color: var(--txt-m); transition: all .13s;
}
.pay-modal-x:hover { background: var(--bg-2, #F3F1EA); border-color: var(--txt-l); color: var(--txt); }
.pay-modal-body { padding: 20px 22px; overflow-y: auto; flex: 1; }
.pay-modal-body pre {
  background: #1A1A1A; color: #E8E5DE; padding: 18px 20px; border-radius: 12px;
  font-size: 12px; line-height: 1.7; overflow-x: auto;
  white-space: pre-wrap; word-break: break-all;
  font-family: ui-monospace, 'Cascadia Code', 'Courier New', monospace;
  border: 1px solid rgba(255,255,255,.06);
}

/* ── Toast copie ─────────────────────────────────────────────────── */
.pay-copy-toast {
  position: fixed; bottom: 28px; left: 50%;
  transform: translateX(-50%) translateY(8px);
  background: var(--txt); color: #fff; padding: 10px 18px; border-radius: 999px;
  font-size: 12.5px; font-weight: 600; font-family: var(--font-body);
  z-index: 9999; pointer-events: none; opacity: 0;
  transition: opacity .2s, transform .2s;
  display: flex; align-items: center; gap: 7px; white-space: nowrap;
  box-shadow: 0 12px 32px rgba(26,26,26,.22);
}
.pay-copy-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }

@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

/* ── Responsive ──────────────────────────────────────────────────── */
@media (max-width: 1100px) {
  .pay-stats, .pay-stats--sub { grid-template-columns: repeat(2, 1fr); }
  .pay-stat--wide { grid-column: span 2; }
}
@media (max-width: 640px) {
  .pay-head-row { align-items: flex-start; }
  .pay-actions { width: 100%; }
  .pay-stats, .pay-stats--sub { grid-template-columns: 1fr 1fr; }
  .pay-stat--wide { grid-column: 1 / -1; }
  .pay-stat { padding: 16px 18px; }
  .pay-stat-val { font-size: 23px; }
  .pay-stat--wide .pay-stat-val { font-size: 27px; }
  .pay-filter-bar { gap: 8px; }
  .pay-search-wrap { max-width: 100%; }
  .pay-filter-bar select, .pay-filter-bar input[type="date"] { flex: 1; min-width: 130px; }
}
</style>

<!-- Copy toast -->
<div class="pay-copy-toast" id="pay-toast">
  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
    <polyline points="20 6 9 17 4 12"/>
  </svg>
  Référence copiée
</div>

<?php
  // Taux de réussite — jauge plutôt qu'un camembert à deux parts
  $txTotal   = (int) $counts['total'];
  $txSucces  = (int) $counts['succes'];
  $tauxSucces = $txTotal > 0 ? round($txSucces / $txTotal * 100) : 0;
?>

<!-- ── En-tête ─────────────────────────────────────────────── -->
<div class="pay-head">
  <div class="pay-eyebrow">Finance · Plateforme</div>
  <div class="pay-head-row">
    <div>
      <h1>Paiements &amp; abonnements</h1>
      <p>
        Transactions encaissées via MoneyFusion sur la plateforme —
        <b><?= number_format($txTotal) ?></b> au total, <b><?= number_format($txSucces) ?></b> réussies.
      </p>
    </div>
    <div class="pay-actions">
      <button id="sync-btn"
              onclick="syncTransactions()"
              class="pay-btn<?= $counts['en_attente'] > 0 ? ' pay-btn--alert' : '' ?>"
              title="Vérifier auprès de MoneyFusion les paiements restés en attente">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <polyline points="23 4 23 10 17 10"/>
          <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
        </svg>
        Sync MF
        <?php if ($counts['en_attente'] > 0): ?>
        <span class="pay-btn-count"><?= (int)$counts['en_attente'] ?></span>
        <?php endif; ?>
      </button>
      <a href="<?= url('/admin/paiement') ?>" class="pay-btn">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <polyline points="23 4 23 10 17 10"/>
          <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
        </svg>
        Actualiser
      </a>
      <button onclick="window.print()" class="pay-btn pay-btn--solid">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
          <polyline points="7 10 12 15 17 10"/>
          <line x1="12" y1="15" x2="12" y2="3"/>
        </svg>
        Exporter
      </button>
    </div>
  </div>
</div>

<!-- ── Revenus ─────────────────────────────────────────────── -->
<div class="pay-stats">

  <div class="pay-stat pay-stat--wide">
    <div class="pay-stat-label">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
        <rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>
      </svg>
      Chiffre d'affaires total
    </div>
    <div class="pay-stat-val accent"><?= number_format($caTotal, 0, ',', ' ') ?><span class="u">XAF</span></div>
    <div class="pay-stat-sub">
      Depuis le lancement · <b><?= number_format($counts['succes']) ?></b> transaction<?= $counts['succes'] > 1 ? 's' : '' ?> réussie<?= $counts['succes'] > 1 ? 's' : '' ?>
      <?php if ($abonnementsActifs > 0): ?> · <b><?= number_format($abonnementsActifs) ?></b> abonné<?= $abonnementsActifs > 1 ? 's' : '' ?> actif<?= $abonnementsActifs > 1 ? 's' : '' ?><?php endif; ?>
    </div>
  </div>

  <div class="pay-stat">
    <div class="pay-stat-label">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
        <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
        <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
      </svg>
      CA 30 jours
    </div>
    <div class="pay-stat-val"><?= number_format($ca30j, 0, ',', ' ') ?><span class="u">XAF</span></div>
    <div class="pay-stat-sub">Dernier mois glissant</div>
  </div>

  <div class="pay-stat">
    <div class="pay-stat-label">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
      </svg>
      CA 7 jours
    </div>
    <div class="pay-stat-val"><?= number_format($ca7j, 0, ',', ' ') ?><span class="u">XAF</span></div>
    <div class="pay-stat-sub">Dernière semaine</div>
  </div>

</div>

<!-- ── Volumes ─────────────────────────────────────────────── -->
<div class="pay-stats pay-stats--sub">

  <div class="pay-stat">
    <div class="pay-stat-label">Transactions</div>
    <div class="pay-stat-val"><?= number_format($txTotal) ?></div>
    <div class="pay-stat-sub">toutes périodes confondues</div>
  </div>

  <div class="pay-stat">
    <div class="pay-stat-label">Réussies</div>
    <div class="pay-stat-val pos"><?= number_format($txSucces) ?></div>
    <div class="pay-meter"><i class="pos" style="width:<?= $tauxSucces ?>%"></i></div>
    <div class="pay-stat-sub"><b><?= $tauxSucces ?> %</b> de taux de réussite</div>
  </div>

  <div class="pay-stat">
    <div class="pay-stat-label">Abonnés actifs</div>
    <div class="pay-stat-val accent"><?= number_format($abonnementsActifs) ?></div>
    <div class="pay-stat-sub">
      <?= $abonnementsExpiresM > 0
            ? '<b>' . number_format($abonnementsExpiresM) . '</b> expiré' . ($abonnementsExpiresM > 1 ? 's' : '') . ' ce mois-ci'
            : 'aucune expiration ce mois-ci' ?>
    </div>
  </div>

  <div class="pay-stat">
    <div class="pay-stat-label">En attente</div>
    <div class="pay-stat-val <?= $counts['en_attente'] > 0 ? 'warn' : '' ?>"><?= number_format($counts['en_attente']) ?></div>
    <div class="pay-stat-sub">
      <?= $counts['en_attente'] > 0
            ? 'webhooks non reçus — lancer « Sync MF »'
            : 'aucun webhook manquant' ?>
    </div>
  </div>

</div>

<!-- ── Table Section ───────────────────────────────────────── -->
<div class="pay-card">

  <!-- Header -->
  <div class="pay-table-hd">
    <div class="pay-table-hd-left">
      <h2>Historique des transactions</h2>
      <p>
        <?= number_format($total) ?> transaction<?= $total > 1 ? 's' : '' ?>
        <?= array_filter($filters) ? ' · résultats filtrés' : '' ?>
      </p>
    </div>
    <?php if (array_filter($filters)): ?>
    <a href="<?= url('/admin/paiement') ?>" class="pay-reset-btn">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
        <line x1="18" y1="6" x2="6" y2="18"/>
        <line x1="6" y1="6" x2="18" y2="18"/>
      </svg>
      Effacer les filtres
    </a>
    <?php endif; ?>
  </div>

  <!-- Filters -->
  <form method="GET" action="<?= url('/admin/paiement') ?>">
    <div class="pay-filter-bar">

      <div class="pay-search-wrap">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <circle cx="11" cy="11" r="8"/>
          <line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <input type="text"
               name="q"
               class="pay-search-input"
               value="<?= e($filters['q']) ?>"
               placeholder="Email, nom, référence…">
      </div>

      <select name="statut">
        <option value="">Tous les statuts</option>
        <option value="succes"     <?= $filters['statut'] === 'succes'     ? 'selected' : '' ?>>Succès</option>
        <option value="en_attente" <?= $filters['statut'] === 'en_attente' ? 'selected' : '' ?>>En attente</option>
        <option value="echec"      <?= $filters['statut'] === 'echec'      ? 'selected' : '' ?>>Échec</option>
        <option value="rembourse"  <?= $filters['statut'] === 'rembourse'  ? 'selected' : '' ?>>Remboursé</option>
      </select>

      <select name="plan">
        <option value="">Tous les plans</option>
        <option value="mensuel" <?= $filters['plan'] === 'mensuel' ? 'selected' : '' ?>>Mensuel</option>
        <option value="annuel"  <?= $filters['plan'] === 'annuel'  ? 'selected' : '' ?>>Annuel</option>
      </select>

      <input type="date"
             name="date_debut"
             value="<?= e($filters['date_debut']) ?>"
             title="Date de début">
      <input type="date"
             name="date_fin"
             value="<?= e($filters['date_fin']) ?>"
             title="Date de fin">

      <button type="submit" class="pay-btn pay-btn--solid" style="margin-left:auto;">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
        </svg>
        Filtrer
      </button>

    </div>
  </form>

  <!-- Table -->
  <table class="admin-table">
    <thead>
      <tr>
        <th>Référence</th>
        <th>Utilisateur</th>
        <th>Plan</th>
        <th>Montant</th>
        <th>Statut</th>
        <th>Méthode</th>
        <th>Date</th>
        <th style="width:44px;"></th>
      </tr>
    </thead>
    <tbody>

      <?php if (empty($transactions)): ?>
      <tr>
        <td colspan="8">
          <div class="pay-empty">
            <svg class="pay-empty-icon" width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
              <rect x="2" y="5" width="20" height="14" rx="2"/>
              <line x1="2" y1="10" x2="22" y2="10"/>
            </svg>
            <h4>Aucune transaction</h4>
            <p><?= array_filter($filters) ? 'Aucun résultat pour ces filtres.' : 'Les transactions apparaîtront ici dès le premier paiement.' ?></p>
          </div>
        </td>
      </tr>
      <?php endif; ?>

      <?php foreach ($transactions as $tx): ?>
      <?php
        $prenom = trim($tx['prenom'] ?? '');
        $nom    = trim($tx['nom']    ?? '');
        $fullname = trim("$prenom $nom") ?: '—';
        $initial = strtoupper(mb_substr($prenom ?: $nom, 0, 1)) ?: '?';
        $avatarPalette = ['#8B52FA','#0EA5E9','#22C55E','#F59E0B','#EF4444','#6366F1','#EC4899'];
        $avatarColor = $avatarPalette[abs(crc32($tx['email'] ?? $fullname)) % count($avatarPalette)];
        $ref = $tx['reference'] ?? '';
        $refDisplay = strlen($ref) > 20 ? substr($ref, 0, 20) . '…' : $ref;
      ?>
      <tr>

        <!-- Référence -->
        <td>
          <div class="pay-ref-cell" title="<?= e($ref) ?>">
            <span class="pay-ref-text"><?= e($refDisplay) ?></span>
            <?php if ($ref): ?>
            <button class="pay-copy-btn"
                    onclick="payCopyRef('<?= e(addslashes($ref)) ?>')"
                    title="Copier la référence">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <rect x="9" y="9" width="13" height="13" rx="2"/>
                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
              </svg>
            </button>
            <?php endif; ?>
          </div>
        </td>

        <!-- Utilisateur -->
        <td>
          <div class="user-cell">
            <div class="user-avatar" style="background:<?= $avatarColor ?>;"><?= $initial ?></div>
            <div class="user-info">
              <strong><?= e($fullname) ?></strong>
              <span><?= e($tx['email'] ?? '') ?></span>
            </div>
          </div>
        </td>

        <!-- Plan -->
        <td>
          <?php if (($tx['plan'] ?? '') === 'mensuel'): ?>
            <span class="pay-plan-badge pay-plan-mensuel">Mensuel</span>
          <?php elseif (($tx['plan'] ?? '') === 'annuel'): ?>
            <span class="pay-plan-badge pay-plan-annuel">Annuel</span>
          <?php else: ?>
            <span class="pay-cell-mute">—</span>
          <?php endif; ?>
        </td>

        <!-- Montant -->
        <td>
          <div class="pay-amount">
            <strong><?= number_format((float)($tx['montant'] ?? 0), 0, ',', ' ') ?></strong>
            <span>XAF</span>
          </div>
        </td>

        <!-- Statut -->
        <td><?= statutBadgePay($tx['statut'] ?? '') ?></td>

        <!-- Méthode -->
        <td>
          <?php if (!empty($tx['methode_paiement'])): ?>
            <span class="pay-method">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <rect x="2" y="5" width="20" height="14" rx="2"/>
                <line x1="2" y1="10" x2="22" y2="10"/>
              </svg>
              <?= e($tx['methode_paiement']) ?>
            </span>
          <?php else: ?>
            <span class="pay-cell-mute">—</span>
          <?php endif; ?>
        </td>

        <!-- Date -->
        <td>
          <?php if (!empty($tx['created_at'])): ?>
            <div class="pay-cell-date"><?= date('d/m/Y', strtotime($tx['created_at'])) ?></div>
            <div class="pay-cell-time"><?= date('H:i', strtotime($tx['created_at'])) ?></div>
          <?php else: ?>
            <span class="pay-cell-mute">—</span>
          <?php endif; ?>
        </td>

        <!-- Action -->
        <td style="white-space:nowrap;">
          <?php if (!empty($tx['webhook_payload'])): ?>
          <button class="pay-payload-btn"
                  onclick="showWebhook(<?= htmlspecialchars(json_encode($tx['webhook_payload']), ENT_QUOTES) ?>)"
                  style="margin-bottom:4px;">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <polyline points="16 18 22 12 16 6"/>
              <polyline points="8 6 2 12 8 18"/>
            </svg>
            Payload
          </button>
          <?php endif; ?>
        </td>

      </tr>
      <?php endforeach; ?>

    </tbody>
  </table>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
  <div class="pay-pager">
    <?php if ($page > 1): ?>
      <a href="<?= paiUrl($filters, $page - 1) ?>" class="pay-pager-btn">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <polyline points="15 18 9 12 15 6"/>
        </svg>
        Précédent
      </a>
    <?php endif; ?>

    <span class="pay-pager-info">Page <?= $page ?> / <?= $totalPages ?></span>

    <?php if ($page < $totalPages): ?>
      <a href="<?= paiUrl($filters, $page + 1) ?>" class="pay-pager-btn">
        Suivant
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <polyline points="9 18 15 12 9 6"/>
        </svg>
      </a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div><!-- /.pay-card -->

<!-- ── Modal webhook payload ──────────────────────────────── -->
<div id="pay-modal-ov"
     class="pay-modal-overlay"
     style="display:none;"
     onclick="if(event.target===this)closeWebhook()">
  <div class="pay-modal">

    <div class="pay-modal-head">
      <div class="pay-modal-head-left">
        <div class="pay-modal-icon">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <polyline points="16 18 22 12 16 6"/>
            <polyline points="8 6 2 12 8 18"/>
          </svg>
        </div>
        <div>
          <h3>Webhook Payload</h3>
          <p>Données reçues de MoneyFusion</p>
        </div>
      </div>
      <button class="pay-modal-x" onclick="closeWebhook()" aria-label="Fermer">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
          <line x1="18" y1="6" x2="6" y2="18"/>
          <line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>

    <div class="pay-modal-body">
      <pre id="pay-payload-pre"></pre>
    </div>

  </div>
</div>

<script>
function showWebhook(raw) {
  try {
    const parsed = typeof raw === 'string' ? JSON.parse(raw) : raw;
    document.getElementById('pay-payload-pre').textContent = JSON.stringify(parsed, null, 2);
  } catch(e) {
    document.getElementById('pay-payload-pre').textContent = raw;
  }
  document.getElementById('pay-modal-ov').style.display = 'flex';
}

function closeWebhook() {
  document.getElementById('pay-modal-ov').style.display = 'none';
}

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeWebhook();
});

function payCopyRef(ref) {
  const toast = document.getElementById('pay-toast');
  const show = () => { toast.classList.add('show'); setTimeout(() => toast.classList.remove('show'), 2200); };
  if (navigator.clipboard) {
    navigator.clipboard.writeText(ref).then(show).catch(fallback);
  } else { fallback(); }
  function fallback() {
    const ta = document.createElement('textarea');
    ta.value = ref;
    ta.style.cssText = 'position:fixed;opacity:0;';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); show(); } catch(e) {}
    document.body.removeChild(ta);
  }
}

async function syncTransactions() {
  const btn = document.getElementById('sync-btn');
  const originalHTML = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="animation:spin 1s linear infinite"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg> Vérification…';

  try {
    const r = await fetch(<?= json_encode(url('/admin/api/paiement/sync')) ?>, {
      method: 'POST',
      headers: {
        'X-CSRF-Token':     '<?= e($csrfToken) ?>',
        'Content-Type':     'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify({}),
    });
    const data = await r.json();
    const toast = document.getElementById('pay-toast');
    const msg = data.activated > 0
      ? `${data.activated} abonnement(s) activé(s) automatiquement`
      : 'Aucun paiement en attente confirmé par MF';
    toast.querySelector('svg').nextSibling.textContent = ' ' + msg;
    toast.classList.add('show');
    setTimeout(() => { toast.classList.remove('show'); if (data.activated > 0) location.reload(); }, 3000);
  } catch(e) {
    alert('Erreur réseau lors de la synchronisation.');
  } finally {
    btn.disabled = false;
    btn.innerHTML = originalHTML;
  }
}

</script>
