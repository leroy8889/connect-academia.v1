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

function fmtXAF(float $v): string {
    return number_format($v, 0, ',', ' ') . ' XAF';
}

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
/* ────────────────────────────────────────────────────────────
   PAIEMENTS — styles spécifiques
   ──────────────────────────────────────────────────────────── */

/* Revenue grid */
.pay-revenue-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 16px;
}

/* Hero card — CA Total */
.pay-hero-card {
  grid-column: span 2;
  background: linear-gradient(135deg, var(--ap) 0%, #6B32DA 100%);
  border-radius: var(--r);
  padding: 26px 28px;
  position: relative;
  overflow: hidden;
  color: #fff;
  box-shadow: 0 10px 40px rgba(139,82,250,0.32);
  min-height: 140px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}
.pay-hero-card::before {
  content: '';
  position: absolute;
  top: -50px; right: -50px;
  width: 200px; height: 200px;
  background: radial-gradient(circle, rgba(255,255,255,0.15), transparent 65%);
  border-radius: 50%;
  pointer-events: none;
}
.pay-hero-card::after {
  content: '';
  position: absolute;
  bottom: -30px; left: -10px;
  width: 120px; height: 120px;
  background: radial-gradient(circle, rgba(255,255,255,0.08), transparent 70%);
  border-radius: 50%;
  pointer-events: none;
}
.pay-hero-eyebrow {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: rgba(255,255,255,0.72);
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 12px;
}
.pay-hero-value {
  font-family: var(--font-head);
  font-size: 30px;
  font-weight: 800;
  color: #fff;
  line-height: 1;
  position: relative; z-index: 1;
}
.pay-hero-sub {
  font-size: 12px;
  color: rgba(255,255,255,0.65);
  margin-top: 6px;
  position: relative; z-index: 1;
}
.pay-hero-bg-icon {
  position: absolute;
  right: 24px;
  top: 50%;
  transform: translateY(-50%);
  opacity: 0.12;
  pointer-events: none;
}

/* Metric cards */
.pay-metric-card {
  background: var(--card);
  border-radius: var(--r);
  padding: 20px 22px;
  box-shadow: var(--shadow);
  border: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  position: relative;
  overflow: hidden;
  transition: box-shadow 0.2s, transform 0.2s;
}
.pay-metric-card:hover {
  box-shadow: var(--shadow-md);
  transform: translateY(-1px);
}
.pay-metric-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 12px;
}
.pay-metric-icon {
  width: 34px; height: 34px;
  border-radius: 8px;
  display: grid; place-items: center;
  flex-shrink: 0;
}
.pay-metric-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--txt-m);
  margin-bottom: 4px;
}
.pay-metric-value {
  font-family: var(--font-head);
  font-size: 22px;
  font-weight: 800;
  line-height: 1;
  color: var(--txt);
}
.pay-metric-sub {
  font-size: 11px;
  color: var(--txt-l);
  margin-top: 4px;
}

/* Metrics strip */
.pay-metrics-strip {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 24px;
}

/* Badges */
.badge-pay {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.15px;
  white-space: nowrap;
}
.badge-dot {
  width: 6px; height: 6px;
  border-radius: 50%;
  display: inline-block;
  flex-shrink: 0;
}
.badge-pay--success { background: rgba(34,197,94,0.12);  color: #15803D; }
.badge-pay--success .badge-dot { background: #22C55E; }
.badge-pay--pending { background: rgba(245,158,11,0.13); color: #B45309; }
.badge-pay--pending .badge-dot { background: #F59E0B; }
.badge-pay--error   { background: rgba(239,68,68,0.12);  color: #B91C1C; }
.badge-pay--error   .badge-dot { background: #EF4444; }
.badge-pay--refund  { background: rgba(59,130,246,0.12); color: #1D4ED8; }
.badge-pay--refund  .badge-dot { background: #3B82F6; }
.badge-pay--muted   { background: var(--bg); color: var(--txt-m); }

/* Plan badges */
.pay-plan-badge {
  display: inline-flex;
  align-items: center;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
}
.pay-plan-mensuel { background: var(--ap-lt); color: var(--ap); }
.pay-plan-annuel  { background: #1A1A2E; color: #fff; }

/* Filter bar */
.pay-filter-bar {
  padding: 13px 20px;
  border-bottom: 1px solid var(--border);
  background: #FAFAFE;
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.pay-filter-bar select,
.pay-filter-bar input[type="date"] {
  height: 36px;
  padding: 0 11px;
  border: 1.5px solid var(--border);
  border-radius: 8px;
  font-family: var(--font-body);
  font-size: 13px;
  background: #fff;
  color: var(--txt);
  outline: none;
  transition: border-color 0.15s, box-shadow 0.15s;
  cursor: pointer;
}
.pay-filter-bar select:focus,
.pay-filter-bar input[type="date"]:focus {
  border-color: var(--ap);
  box-shadow: 0 0 0 3px rgba(139,82,250,0.10);
}
.pay-search-wrap {
  position: relative;
  flex: 1;
  min-width: 200px;
  max-width: 280px;
}
.pay-search-wrap svg {
  position: absolute;
  left: 10px; top: 50%;
  transform: translateY(-50%);
  color: var(--txt-l);
  pointer-events: none;
}
.pay-search-input {
  width: 100%;
  height: 36px;
  padding: 0 12px 0 34px;
  border: 1.5px solid var(--border);
  border-radius: 8px;
  font-family: var(--font-body);
  font-size: 13px;
  background: #fff;
  color: var(--txt);
  outline: none;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.pay-search-input:focus {
  border-color: var(--ap);
  box-shadow: 0 0 0 3px rgba(139,82,250,0.10);
}
.pay-search-input::placeholder { color: var(--txt-l); }

/* Table header inside wrap */
.pay-table-hd {
  padding: 16px 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  border-bottom: 1px solid var(--border);
}
.pay-table-hd-left h2 {
  font-family: var(--font-mid);
  font-size: 15px;
  font-weight: 700;
  color: var(--txt);
}
.pay-table-hd-left p {
  font-size: 12px;
  color: var(--txt-m);
  margin-top: 2px;
}
.pay-reset-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 6px 12px;
  border-radius: 7px;
  border: 1.5px solid var(--border);
  background: #fff;
  font-size: 12px;
  font-weight: 600;
  color: var(--txt-m);
  text-decoration: none;
  transition: all 0.15s;
  font-family: var(--font-body);
}
.pay-reset-btn:hover {
  border-color: var(--red);
  color: var(--red);
  background: var(--red-bg);
}

/* Reference cell */
.pay-ref-cell {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-family: 'Courier New', 'Cascadia Code', monospace;
  font-size: 11px;
  color: var(--txt-m);
  background: var(--bg);
  border-radius: 6px;
  padding: 4px 8px;
  max-width: 160px;
}
.pay-ref-text {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  flex: 1;
}
.pay-copy-btn {
  cursor: pointer;
  background: none;
  border: none;
  padding: 0;
  display: flex;
  align-items: center;
  color: var(--txt-l);
  transition: color 0.15s;
  flex-shrink: 0;
}
.pay-copy-btn:hover { color: var(--ap); }

/* Payload button */
.pay-payload-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 9px;
  background: none;
  border: 1.5px solid var(--border);
  border-radius: 6px;
  font-size: 11px;
  font-weight: 600;
  color: var(--txt-m);
  cursor: pointer;
  transition: all 0.15s;
  font-family: var(--font-body);
}
.pay-payload-btn:hover {
  border-color: var(--ap);
  color: var(--ap);
  background: var(--ap-lt);
}

/* Modal */
.pay-modal-overlay {
  position: fixed; inset: 0;
  background: rgba(20,20,43,0.52);
  backdrop-filter: blur(5px);
  -webkit-backdrop-filter: blur(5px);
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  animation: pov-in 180ms ease;
}
@keyframes pov-in { from { opacity:0; } to { opacity:1; } }
.pay-modal {
  background: var(--card);
  border-radius: 16px;
  width: 100%;
  max-width: 680px;
  max-height: 85vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 24px 64px rgba(20,20,43,0.22);
  animation: pm-in 280ms cubic-bezier(0.2,0.8,0.2,1);
}
@keyframes pm-in {
  from { opacity:0; transform: translateY(14px) scale(0.98); }
  to   { opacity:1; transform: translateY(0)    scale(1);    }
}
.pay-modal-head {
  padding: 18px 22px;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-shrink: 0;
}
.pay-modal-head-left {
  display: flex;
  align-items: center;
  gap: 11px;
}
.pay-modal-icon {
  width: 36px; height: 36px;
  background: var(--ap-lt);
  border-radius: 9px;
  display: grid; place-items: center;
  color: var(--ap);
  flex-shrink: 0;
}
.pay-modal-head h3 {
  font-family: var(--font-mid);
  font-size: 14px;
  font-weight: 700;
  color: var(--txt);
}
.pay-modal-head p {
  font-size: 11px;
  color: var(--txt-m);
  margin-top: 1px;
}
.pay-modal-x {
  width: 32px; height: 32px;
  border-radius: 8px;
  background: none;
  border: 1.5px solid var(--border);
  cursor: pointer;
  display: grid; place-items: center;
  color: var(--txt-m);
  transition: all 0.15s;
}
.pay-modal-x:hover { background: var(--bg); border-color: var(--txt-m); color: var(--txt); }
.pay-modal-body {
  padding: 20px 22px;
  overflow-y: auto;
  flex: 1;
}
.pay-modal-body pre {
  background: #0D0D1A;
  color: #A9B1D6;
  padding: 18px 20px;
  border-radius: 10px;
  font-size: 12px;
  line-height: 1.7;
  overflow-x: auto;
  white-space: pre-wrap;
  word-break: break-all;
  font-family: 'Courier New', monospace;
  border: 1px solid rgba(255,255,255,0.05);
}

/* Pagination */
.pay-pager {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 16px 20px;
  border-top: 1px solid var(--border);
}
.pay-pager-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 7px 14px;
  border-radius: 8px;
  border: 1.5px solid var(--border);
  background: var(--card);
  font-size: 13px;
  font-weight: 600;
  color: var(--txt);
  text-decoration: none;
  font-family: var(--font-body);
  transition: all 0.15s;
}
.pay-pager-btn:hover {
  border-color: var(--ap);
  color: var(--ap);
  background: var(--ap-lt);
}
.pay-pager-info {
  padding: 7px 12px;
  font-size: 12px;
  font-weight: 600;
  color: var(--txt-m);
  background: var(--bg);
  border-radius: 7px;
}

/* Copy toast */
.pay-copy-toast {
  position: fixed;
  bottom: 28px;
  left: 50%;
  transform: translateX(-50%) translateY(8px);
  background: #1A1A2E;
  color: #fff;
  padding: 9px 18px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
  font-family: var(--font-body);
  z-index: 9999;
  pointer-events: none;
  opacity: 0;
  transition: opacity 0.2s, transform 0.2s;
  display: flex;
  align-items: center;
  gap: 6px;
  white-space: nowrap;
}
.pay-copy-toast.show {
  opacity: 1;
  transform: translateX(-50%) translateY(0);
}

/* Empty state */
.pay-empty {
  padding: 56px 20px;
  text-align: center;
}
.pay-empty-icon {
  width: 52px; height: 52px;
  background: var(--bg);
  border-radius: 14px;
  display: grid; place-items: center;
  margin: 0 auto 14px;
  color: var(--txt-l);
}
.pay-empty h4 {
  font-family: var(--font-mid);
  font-size: 14px;
  font-weight: 700;
  color: var(--txt);
  margin-bottom: 5px;
}
.pay-empty p {
  font-size: 12px;
  color: var(--txt-m);
}

/* Amount */
.pay-amount {
  display: inline-flex;
  align-items: baseline;
  gap: 3px;
}
.pay-amount strong {
  font-family: var(--font-head);
  font-size: 14px;
  font-weight: 800;
  color: var(--txt);
}
.pay-amount span {
  font-size: 10px;
  font-weight: 700;
  color: var(--txt-m);
}

@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

/* Responsive */
@media (max-width: 960px) {
  .pay-revenue-grid { grid-template-columns: repeat(2, 1fr); }
  .pay-hero-card { grid-column: span 2; }
  .pay-metrics-strip { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 600px) {
  .pay-revenue-grid { grid-template-columns: 1fr; }
  .pay-hero-card { grid-column: 1; }
  .pay-metrics-strip { grid-template-columns: 1fr 1fr; }
  .pay-filter-bar { gap: 8px; }
  .pay-search-wrap { max-width: 100%; }
}
</style>

<!-- Copy toast -->
<div class="pay-copy-toast" id="pay-toast">
  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
    <polyline points="20 6 9 17 4 12"/>
  </svg>
  Référence copiée
</div>

<!-- ── Header ──────────────────────────────────────────────── -->
<div class="admin-page-header-row">
  <div>
    <h1 style="font-family:var(--font-head);font-size:22px;font-weight:800;color:var(--txt);letter-spacing:-0.3px;">
      Paiements &amp; Abonnements
    </h1>
    <p style="font-size:13px;color:var(--txt-m);margin-top:4px;">
      <strong style="color:var(--txt);"><?= number_format($counts['total']) ?></strong> transactions ·
      <strong style="color:#15803D;"><?= number_format($counts['succes']) ?></strong> réussies
    </p>
  </div>
  <div style="display:flex;gap:8px;align-items:center;">
    <button id="sync-btn"
            onclick="syncTransactions()"
            class="btn-outline"
            style="height:36px;padding:0 14px;font-size:13px;display:inline-flex;align-items:center;gap:6px;border-color:#F59E0B;color:#B45309;">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
        <polyline points="23 4 23 10 17 10"/>
        <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
      </svg>
      Sync MF
      <?php if ($counts['en_attente'] > 0): ?>
      <span style="background:#F59E0B;color:#fff;border-radius:999px;padding:1px 7px;font-size:10px;font-weight:800;margin-left:2px;">
        <?= $counts['en_attente'] ?>
      </span>
      <?php endif; ?>
    </button>
    <a href="<?= url('/admin/paiement') ?>"
       class="btn-outline"
       style="height:36px;padding:0 14px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
        <polyline points="23 4 23 10 17 10"/>
        <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
      </svg>
      Actualiser
    </a>
    <button onclick="window.print()"
            class="btn-primary"
            style="height:36px;padding:0 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
        <polyline points="7 10 12 15 17 10"/>
        <line x1="12" y1="15" x2="12" y2="3"/>
      </svg>
      Exporter
    </button>
  </div>
</div>

<!-- ── Revenue Section ─────────────────────────────────────── -->
<div class="pay-revenue-grid">

  <!-- Hero — CA Total -->
  <div class="pay-hero-card">
    <div>
      <div class="pay-hero-eyebrow">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
          <rect x="2" y="5" width="20" height="14" rx="2"/>
          <line x1="2" y1="10" x2="22" y2="10"/>
        </svg>
        Chiffre d'affaires total
      </div>
      <div class="pay-hero-value"><?= fmtXAF($caTotal) ?></div>
      <div class="pay-hero-sub">Depuis le lancement · Toutes périodes</div>
    </div>
    <div style="display:flex;align-items:center;gap:10px;margin-top:16px;position:relative;z-index:1;">
      <div style="background:rgba(255,255,255,0.18);border-radius:8px;padding:5px 12px;font-size:11px;font-weight:700;color:rgba(255,255,255,0.90);">
        <?= number_format($counts['succes']) ?> tx réussies
      </div>
      <?php if ($abonnementsActifs > 0): ?>
      <div style="background:rgba(255,255,255,0.18);border-radius:8px;padding:5px 12px;font-size:11px;font-weight:700;color:rgba(255,255,255,0.90);">
        <?= number_format($abonnementsActifs) ?> abonnés actifs
      </div>
      <?php endif; ?>
    </div>
    <div class="pay-hero-bg-icon">
      <svg width="100" height="100" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="0.8" stroke-linecap="round">
        <rect x="2" y="5" width="20" height="14" rx="2"/>
        <line x1="2" y1="10" x2="22" y2="10"/>
        <line x1="6" y1="14" x2="9" y2="14"/>
        <line x1="11" y1="14" x2="16" y2="14"/>
      </svg>
    </div>
  </div>

  <!-- CA 30j -->
  <div class="pay-metric-card">
    <div class="pay-metric-top">
      <div class="pay-metric-label">CA 30 jours</div>
      <div class="pay-metric-icon" style="background:var(--ap-lt);color:var(--ap);">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <rect x="3" y="4" width="18" height="18" rx="2"/>
          <line x1="16" y1="2" x2="16" y2="6"/>
          <line x1="8" y1="2" x2="8" y2="6"/>
          <line x1="3" y1="10" x2="21" y2="10"/>
        </svg>
      </div>
    </div>
    <div class="pay-metric-value"><?= fmtXAF($ca30j) ?></div>
    <div class="pay-metric-sub">Dernier mois glissant</div>
  </div>

  <!-- CA 7j -->
  <div class="pay-metric-card">
    <div class="pay-metric-top">
      <div class="pay-metric-label">CA 7 jours</div>
      <div class="pay-metric-icon" style="background:#F0FDF4;color:#16A34A;">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
        </svg>
      </div>
    </div>
    <div class="pay-metric-value"><?= fmtXAF($ca7j) ?></div>
    <div class="pay-metric-sub">Dernière semaine</div>
  </div>

</div>

<!-- ── Metrics Strip ───────────────────────────────────────── -->
<div class="pay-metrics-strip">

  <!-- Transactions -->
  <div class="pay-metric-card">
    <div class="pay-metric-top">
      <div class="pay-metric-label">Transactions</div>
      <div class="pay-metric-icon" style="background:var(--bg);color:var(--txt-m);">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <line x1="8" y1="6" x2="21" y2="6"/>
          <line x1="8" y1="12" x2="21" y2="12"/>
          <line x1="8" y1="18" x2="21" y2="18"/>
          <line x1="3" y1="6" x2="3.01" y2="6"/>
          <line x1="3" y1="12" x2="3.01" y2="12"/>
          <line x1="3" y1="18" x2="3.01" y2="18"/>
        </svg>
      </div>
    </div>
    <div class="pay-metric-value" style="font-size:20px;"><?= number_format($counts['total']) ?></div>
    <div class="pay-metric-sub">au total</div>
  </div>

  <!-- Réussies -->
  <div class="pay-metric-card">
    <div class="pay-metric-top">
      <div class="pay-metric-label">Réussies</div>
      <div class="pay-metric-icon" style="background:var(--green-bg);color:#16A34A;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
          <polyline points="22 4 12 14.01 9 11.01"/>
        </svg>
      </div>
    </div>
    <div class="pay-metric-value" style="font-size:20px;color:#15803D;"><?= number_format($counts['succes']) ?></div>
    <div class="pay-metric-sub">validées</div>
  </div>

  <!-- Abonnements actifs -->
  <div class="pay-metric-card">
    <div class="pay-metric-top">
      <div class="pay-metric-label">Abonnés actifs</div>
      <div class="pay-metric-icon" style="background:var(--ap-lt);color:var(--ap);">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
      </div>
    </div>
    <div class="pay-metric-value" style="font-size:20px;color:var(--ap);"><?= number_format($abonnementsActifs) ?></div>
    <div class="pay-metric-sub">en cours</div>
  </div>

  <!-- En attente + Expirés -->
  <div class="pay-metric-card">
    <div class="pay-metric-top">
      <div class="pay-metric-label">En attente</div>
      <div class="pay-metric-icon" style="background:var(--amber-bg);color:#B45309;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="12"/>
          <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
      </div>
    </div>
    <div class="pay-metric-value" style="font-size:20px;color:#B45309;"><?= number_format($counts['en_attente']) ?></div>
    <div class="pay-metric-sub">webhooks non reçus</div>
    <?php if ($abonnementsExpiresM > 0): ?>
    <div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
      <span style="font-size:11px;color:var(--txt-m);">Expirés ce mois</span>
      <span style="font-size:12px;font-weight:800;color:#B91C1C;"><?= number_format($abonnementsExpiresM) ?></span>
    </div>
    <?php endif; ?>
  </div>

</div>

<!-- ── Table Section ───────────────────────────────────────── -->
<div class="admin-table-wrap">

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

      <button type="submit"
              class="btn-primary"
              style="height:36px;padding:0 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px;margin-left:auto;">
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
            <div class="pay-empty-icon">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
                <rect x="2" y="5" width="20" height="14" rx="2"/>
                <line x1="2" y1="10" x2="22" y2="10"/>
              </svg>
            </div>
            <h4>Aucune transaction trouvée</h4>
            <p>Modifie les filtres ou attends les premiers paiements.</p>
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
            <span style="color:var(--txt-l);font-size:12px;">—</span>
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
            <span style="display:inline-flex;align-items:center;gap:5px;font-size:12px;color:var(--txt-m);">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <rect x="2" y="5" width="20" height="14" rx="2"/>
                <line x1="2" y1="10" x2="22" y2="10"/>
              </svg>
              <?= e($tx['methode_paiement']) ?>
            </span>
          <?php else: ?>
            <span style="color:var(--txt-l);font-size:12px;">—</span>
          <?php endif; ?>
        </td>

        <!-- Date -->
        <td>
          <?php if (!empty($tx['created_at'])): ?>
            <div style="font-size:12px;font-weight:600;color:var(--txt);"><?= date('d/m/Y', strtotime($tx['created_at'])) ?></div>
            <div style="font-size:11px;color:var(--txt-m);margin-top:1px;"><?= date('H:i', strtotime($tx['created_at'])) ?></div>
          <?php else: ?>
            <span style="color:var(--txt-l);font-size:12px;">—</span>
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

</div><!-- /.admin-table-wrap -->

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
