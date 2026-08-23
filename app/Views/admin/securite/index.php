<?php
/** @var array $alerts @var array $stats @var array $filters @var bool $blockingEnabled */
$csrf = \Core\Session::getCsrfToken();

$sevMeta = [
    'critique' => ['Critique', '#B91C1C', 'rgba(185,28,28,.10)'],
    'elevee'   => ['Élevée',   '#C2410C', 'rgba(194,65,12,.10)'],
    'moyenne'  => ['Moyenne',  '#8B52FA', 'rgba(139,82,250,.10)'],
    'faible'   => ['Faible',   '#4B5563', 'rgba(75,85,99,.10)'],
];
$statutMeta = [
    'nouveau' => ['Nouveau', '#8B52FA'],
    'vu'      => ['Vu',      '#4B5563'],
    'resolu'  => ['Résolu',  '#15803D'],
];
$typeLabel = [
    'brute_force_admin' => 'Force brute (admin)',
    'sqli'              => 'Injection SQL',
    'xss'               => 'XSS',
    'path_traversal'    => 'Traversée de répertoire',
    'rce'               => 'Exécution de code',
    'scanner'           => 'Scanner / bot',
    'flood'             => 'Flood / scan de volume',
];
$typeIco = function(string $t): string {
    // Icônes Lucide inline (outline)
    return match ($t) {
        'brute_force_admin' => '<path d="M12 2 4 5v6c0 5 3.4 7.7 8 9 4.6-1.3 8-4 8-9V5z"/><path d="M9 12l2 2 4-4"/>',
        'sqli'              => '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>',
        'xss'               => '<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>',
        'path_traversal'    => '<path d="M3 7h5l2-2h9a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'rce'               => '<polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/>',
        'flood'             => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
        default             => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
    };
};
?>

<style>
/* ── Tableau de bord sécurité ─────────────────────────────── */
.sec-wrap { --sec-accent:#8B52FA; }
.sec-head { display:flex; align-items:flex-start; justify-content:space-between; gap:20px; flex-wrap:wrap; margin-bottom:22px; }
.sec-head h1 { margin:0 0 4px; font-size:24px; font-weight:800; letter-spacing:-.02em; color:#1F1C2C; }
.sec-head p { margin:0; font-size:13px; color:#6B7280; max-width:52ch; }
.sec-actions { display:flex; gap:10px; flex-wrap:wrap; }
.sec-btn { display:inline-flex; align-items:center; gap:7px; font:600 12.5px/1 'Montserrat',sans-serif; padding:10px 15px; border-radius:10px; border:1px solid rgba(31,28,44,.12); background:#fff; color:#4B5563; cursor:pointer; transition:all .16s; text-decoration:none; }
.sec-btn:hover { border-color:var(--sec-accent); color:var(--sec-accent); }
.sec-btn-primary { background:var(--sec-accent); border-color:var(--sec-accent); color:#fff; box-shadow:0 4px 14px rgba(139,82,250,.28); }
.sec-btn-primary:hover { filter:brightness(1.06); color:#fff; }
.sec-btn svg { width:15px; height:15px; }

/* Stat cards */
.sec-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:14px; margin-bottom:24px; }
.sec-stat { background:#fff; border:1px solid rgba(31,28,44,.09); border-radius:16px; padding:18px 20px; }
.sec-stat .v { font-size:28px; font-weight:800; letter-spacing:-.02em; color:#1F1C2C; line-height:1; }
.sec-stat .v.accent { color:var(--sec-accent); }
.sec-stat .v.danger { color:#B91C1C; }
.sec-stat .l { font-size:11.5px; color:#6B7280; margin-top:8px; font-weight:600; text-transform:uppercase; letter-spacing:.05em; }

/* Bandeau blocage */
.sec-shield { display:flex; align-items:center; gap:16px; flex-wrap:wrap; justify-content:space-between; background:#fff; border:1px solid rgba(31,28,44,.09); border-radius:16px; padding:16px 20px; margin-bottom:24px; }
.sec-shield__txt { display:flex; align-items:center; gap:13px; }
.sec-shield__ic { width:42px; height:42px; border-radius:12px; display:grid; place-items:center; background:rgba(139,82,250,.10); color:var(--sec-accent); flex:none; }
.sec-shield__ic svg { width:22px; height:22px; }
.sec-shield strong { display:block; font-size:13.5px; color:#1F1C2C; }
.sec-shield span { font-size:12px; color:#6B7280; }
/* interrupteur */
.sec-switch { position:relative; display:inline-flex; align-items:center; cursor:pointer; }
.sec-switch input { position:absolute; opacity:0; }
.sec-switch .track { width:46px; height:26px; border-radius:999px; background:rgba(31,28,44,.16); transition:background .2s; }
.sec-switch .track::after { content:''; position:absolute; top:3px; left:3px; width:20px; height:20px; border-radius:50%; background:#fff; box-shadow:0 1px 4px rgba(0,0,0,.25); transition:transform .2s; }
.sec-switch input:checked + .track { background:var(--sec-accent); }
.sec-switch input:checked + .track::after { transform:translateX(20px); }

/* Filtres */
.sec-filters { display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:16px; }
.sec-filters select { padding:9px 12px; border:1px solid rgba(31,28,44,.12); border-radius:10px; font:500 13px 'Montserrat',sans-serif; background:#fff; color:#1F1C2C; }
.sec-filters select:focus { outline:none; border-color:var(--sec-accent); box-shadow:0 0 0 3px rgba(139,82,250,.18); }

/* Liste des alertes */
.sec-list { display:flex; flex-direction:column; gap:10px; }
.sec-alert { background:#fff; border:1px solid rgba(31,28,44,.09); border-left-width:4px; border-radius:14px; padding:16px 18px; display:grid; grid-template-columns:auto 1fr auto; gap:14px; align-items:start; }
.sec-alert[data-sev="critique"] { border-left-color:#B91C1C; }
.sec-alert[data-sev="elevee"]   { border-left-color:#C2410C; }
.sec-alert[data-sev="moyenne"]  { border-left-color:#8B52FA; }
.sec-alert[data-sev="faible"]   { border-left-color:#9CA3AF; }
.sec-alert[data-statut="resolu"] { opacity:.62; }
.sec-alert__ic { width:38px; height:38px; border-radius:10px; display:grid; place-items:center; background:rgba(31,28,44,.05); color:#4B5563; flex:none; }
.sec-alert__ic svg { width:19px; height:19px; }
.sec-alert__body { min-width:0; }
.sec-alert__top { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:5px; }
.sec-alert__msg { font-size:14px; font-weight:700; color:#1F1C2C; }
.sec-badge { font-size:10.5px; font-weight:700; padding:2px 9px; border-radius:999px; letter-spacing:.02em; }
.sec-occ { font-size:11px; font-weight:700; color:#B91C1C; background:rgba(185,28,28,.08); padding:2px 8px; border-radius:999px; }
.sec-alert__meta { font-size:12px; color:#6B7280; display:flex; flex-wrap:wrap; gap:5px 14px; }
.sec-alert__meta b { color:#4B5563; font-weight:600; }
.sec-alert__uri { font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:11.5px; color:#6B7280; word-break:break-all; margin-top:6px; background:rgba(31,28,44,.04); padding:5px 9px; border-radius:8px; display:inline-block; max-width:100%; }
.sec-alert__ctx { margin-top:8px; }
.sec-alert__ctx summary { font-size:11.5px; color:var(--sec-accent); cursor:pointer; font-weight:600; }
.sec-alert__ctx pre { margin:6px 0 0; background:#1F1C2C; color:#E2E8F0; padding:11px 13px; border-radius:9px; font-size:11.5px; overflow:auto; }
.sec-alert__act { display:flex; flex-direction:column; gap:6px; align-items:flex-end; }
.sec-mini { font:600 11.5px 'Montserrat',sans-serif; padding:6px 11px; border-radius:8px; border:1px solid rgba(31,28,44,.12); background:#fff; color:#4B5563; cursor:pointer; white-space:nowrap; transition:all .15s; }
.sec-mini:hover { border-color:var(--sec-accent); color:var(--sec-accent); }
.sec-mini.done:hover { border-color:#15803D; color:#15803D; }

.sec-empty { background:#fff; border:1px dashed rgba(31,28,44,.16); border-radius:16px; padding:56px 24px; text-align:center; color:#6B7280; }
.sec-empty svg { width:44px; height:44px; color:#8B52FA; margin-bottom:12px; }
.sec-empty p { margin:0; font-size:14px; }

.sec-flash { padding:12px 16px; border-radius:12px; font-size:13px; font-weight:600; margin-bottom:18px; }
.sec-flash-ok { background:rgba(34,197,94,.12); color:#15803D; border:1px solid rgba(34,197,94,.28); }
.sec-flash-err{ background:rgba(239,68,68,.10); color:#B91C1C; border:1px solid rgba(239,68,68,.26); }

@media (max-width:640px){ .sec-alert { grid-template-columns:1fr; } .sec-alert__act { flex-direction:row; align-items:center; } }
</style>

<div class="sec-wrap">

  <?php if (!empty($flash)): ?><div class="sec-flash sec-flash-ok"><?= e($flash) ?></div><?php endif; ?>
  <?php if (!empty($flashError)): ?><div class="sec-flash sec-flash-err"><?= e($flashError) ?></div><?php endif; ?>

  <div class="sec-head">
    <div>
      <h1>Sécurité &amp; détection d'intrusion</h1>
      <p>Le moniteur inspecte chaque requête (injections, XSS, traversée de répertoire, scanners, flood) et les tentatives de connexion admin. Les événements sérieux déclenchent une alerte e-mail.</p>
    </div>
    <div class="sec-actions">
      <form method="post" action="<?= url('/admin/api/securite/vider') ?>" onsubmit="return confirm('Supprimer toutes les alertes résolues ?')">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <button class="sec-btn" type="submit">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
          Purger les résolues
        </button>
      </form>
      <form method="post" action="<?= url('/admin/api/securite/vue') ?>">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <button class="sec-btn sec-btn-primary" type="submit">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
          Tout marquer comme vu
        </button>
      </form>
    </div>
  </div>

  <!-- Stats -->
  <div class="sec-stats">
    <div class="sec-stat"><div class="v accent"><?= (int)$stats['nouveau'] ?></div><div class="l">Non lues</div></div>
    <div class="sec-stat"><div class="v"><?= (int)$stats['last24'] ?></div><div class="l">Dernières 24 h</div></div>
    <div class="sec-stat"><div class="v danger"><?= (int)$stats['critique'] ?></div><div class="l">Critiques</div></div>
    <div class="sec-stat"><div class="v"><?= (int)$stats['elevee'] ?></div><div class="l">Élevées</div></div>
    <div class="sec-stat"><div class="v"><?= (int)$stats['total'] ?></div><div class="l">Total</div></div>
  </div>

  <!-- Blocage automatique -->
  <form method="post" action="<?= url('/admin/api/securite/blocage') ?>" id="sec-block-form">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
    <div class="sec-shield">
      <div class="sec-shield__txt">
        <div class="sec-shield__ic">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2 4 5v6c0 5 3.4 7.7 8 9 4.6-1.3 8-4 8-9V5z"/></svg>
        </div>
        <div>
          <strong>Blocage automatique des attaques</strong>
          <span>Répond 403 aux requêtes contenant une signature d'attaque évidente. Désactivé = alerte seule (recommandé au départ).</span>
        </div>
      </div>
      <label class="sec-switch">
        <input type="checkbox" name="security_block_attacks" value="1" <?= $blockingEnabled ? 'checked' : '' ?> onchange="document.getElementById('sec-block-form').submit()">
        <span class="track"></span>
      </label>
    </div>
  </form>

  <!-- Filtres -->
  <form method="get" class="sec-filters">
    <select name="severity" onchange="this.form.submit()">
      <option value="">Toutes gravités</option>
      <?php foreach ($sevMeta as $k => $m): ?>
        <option value="<?= $k ?>" <?= ($filters['severity'] ?? '') === $k ? 'selected' : '' ?>><?= $m[0] ?></option>
      <?php endforeach; ?>
    </select>
    <select name="statut" onchange="this.form.submit()">
      <option value="">Tous statuts</option>
      <?php foreach ($statutMeta as $k => $m): ?>
        <option value="<?= $k ?>" <?= ($filters['statut'] ?? '') === $k ? 'selected' : '' ?>><?= $m[0] ?></option>
      <?php endforeach; ?>
    </select>
    <?php if (!empty($filters['severity']) || !empty($filters['statut']) || !empty($filters['type'])): ?>
      <a class="sec-btn" href="<?= url('/admin/securite') ?>">Réinitialiser</a>
    <?php endif; ?>
  </form>

  <!-- Liste -->
  <?php if (empty($alerts)): ?>
    <div class="sec-empty">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 2 4 5v6c0 5 3.4 7.7 8 9 4.6-1.3 8-4 8-9V5z"/><path d="m9 12 2 2 4-4"/></svg>
      <p>Aucune alerte. Le système est calme — le moniteur veille en arrière-plan.</p>
    </div>
  <?php else: ?>
    <div class="sec-list">
      <?php foreach ($alerts as $a):
        $sev = $sevMeta[$a['severity']] ?? $sevMeta['faible'];
        $stt = $statutMeta[$a['statut']] ?? $statutMeta['nouveau'];
        $ctx = $a['context'] ? json_decode($a['context'], true) : null;
      ?>
      <div class="sec-alert" data-sev="<?= e($a['severity']) ?>" data-statut="<?= e($a['statut']) ?>">
        <div class="sec-alert__ic">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $typeIco($a['type']) ?></svg>
        </div>
        <div class="sec-alert__body">
          <div class="sec-alert__top">
            <span class="sec-alert__msg"><?= e($a['message']) ?></span>
            <span class="sec-badge" style="color:<?= $sev[1] ?>;background:<?= $sev[2] ?>"><?= $sev[0] ?></span>
            <span class="sec-badge" style="color:<?= $stt[1] ?>;background:rgba(31,28,44,.05)"><?= $stt[0] ?></span>
            <?php if ((int)$a['occurrences'] > 1): ?><span class="sec-occ">×<?= (int)$a['occurrences'] ?></span><?php endif; ?>
          </div>
          <div class="sec-alert__meta">
            <span><b><?= e($typeLabel[$a['type']] ?? $a['type']) ?></b></span>
            <span>IP <b><?= e($a['ip'] ?? '—') ?></b></span>
            <span><?= e(date('d/m/Y H:i:s', strtotime($a['last_seen_at']))) ?></span>
            <?php if (!empty($a['method'])): ?><span><b><?= e($a['method']) ?></b></span><?php endif; ?>
          </div>
          <?php if (!empty($a['uri'])): ?><div class="sec-alert__uri"><?= e($a['uri']) ?></div><?php endif; ?>
          <?php if (!empty($ctx)): ?>
          <details class="sec-alert__ctx">
            <summary>Détails techniques</summary>
            <pre><?= e(json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?><?php if (!empty($a['user_agent'])): ?>

User-Agent: <?= e($a['user_agent']) ?><?php endif; ?></pre>
          </details>
          <?php endif; ?>
        </div>
        <div class="sec-alert__act">
          <?php if ($a['statut'] !== 'vu'): ?>
          <form method="post" action="<?= url('/admin/api/securite/' . (int)$a['id'] . '/statut') ?>">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="statut" value="vu">
            <button class="sec-mini" type="submit">Marquer vu</button>
          </form>
          <?php endif; ?>
          <?php if ($a['statut'] !== 'resolu'): ?>
          <form method="post" action="<?= url('/admin/api/securite/' . (int)$a['id'] . '/statut') ?>">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="statut" value="resolu">
            <button class="sec-mini done" type="submit">Résoudre</button>
          </form>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>
