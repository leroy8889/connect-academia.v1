<?php
// Variables : $notifs, $etab
// Design des messages de notification : design system Claude (Anthropic) —
// canvas crème, Spectral serif pour le display, Inter pour le corps, coral rare
// comme seul accent (non lues + messages admin), hairlines, ombres rares.
// Le style est confiné sous .cd-notifs pour ne pas déteindre sur le reste du
// tableau de bord gestionnaire (chrome Purple Neon Connect'Academia).
$notifs = $notifs ?? [];
$unread = 0;
foreach ($notifs as $n) { if ((int)($n['lu'] ?? 0) === 0) $unread++; }

/** Icône + teinte selon le type de notification (palette Claude). */
$typeMeta = static function (string $type): array {
    return match ($type) {
        'message_admin'          => ['#cc785c', 'rgba(204,120,92,.12)', '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>'],
        'inscription_validee'    => ['#4a9d63', 'rgba(93,184,114,.16)', '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>'],
        'preinscription_acceptee'=> ['#4a9d63', 'rgba(93,184,114,.16)', '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>'],
        'dossier_refuse'         => ['#c64545', 'rgba(198,69,69,.12)',  '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>'],
        'paiement_confirme'      => ['#b8791f', 'rgba(232,165,90,.18)', '<rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>'],
        default                  => ['#6c6a64', 'rgba(20,20,19,.06)',   '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>'],
    };
};

/** Date relative en français. */
$rel = static function (?string $date): string {
    if (!$date) return '';
    $ts = strtotime($date);
    if ($ts === false) return '';
    $d = time() - $ts;
    if ($d < 60)     return "à l'instant";
    if ($d < 3600)   return floor($d/60) . ' min';
    if ($d < 86400)  return floor($d/3600) . ' h';
    if ($d < 172800) return 'hier';
    if ($d < 604800) return floor($d/86400) . ' j';
    return date('d/m/Y', $ts);
};
?>

<link href="https://fonts.googleapis.com/css2?family=Spectral:wght@400;500&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

<div class="cd-notifs">

  <header class="cd-head">
    <div>
      <h2 class="cd-title">Notifications</h2>
      <p class="cd-sub">
        <?php if ($unread > 0): ?>
          <strong><?= $unread ?> non lue<?= $unread > 1 ? 's' : '' ?></strong> — messages de l'administration et suivi de vos dossiers.
        <?php else: ?>
          Messages de l'administration Connect'Academia et suivi de vos dossiers.
        <?php endif; ?>
      </p>
    </div>
    <?php if ($unread > 0): ?>
    <form method="post" action="<?= url('/gestionnaire/notifications/lire-tout') ?>" style="margin:0;">
      <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
      <button type="submit" class="cd-mark">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        Tout marquer comme lu
      </button>
    </form>
    <?php endif; ?>
  </header>

  <?php if (empty($notifs)): ?>
    <div class="cd-empty">
      <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      <p>Aucune notification pour le moment.</p>
    </div>
  <?php else: ?>
    <ul class="cd-list">
      <?php foreach ($notifs as $n):
        $isUnread = (int)($n['lu'] ?? 0) === 0;
        [$color, $tint, $icon] = $typeMeta((string)($n['type'] ?? ''));
        $isAdmin = ($n['type'] ?? '') === 'message_admin';
      ?>
      <li class="cd-item<?= $isUnread ? ' is-unread' : '' ?>">
        <span class="cd-icon" style="background:<?= $tint ?>;color:<?= $color ?>;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $icon ?></svg>
        </span>
        <div class="cd-body">
          <div class="cd-row">
            <strong class="cd-name"><?= e($n['titre'] ?? 'Notification') ?></strong>
            <?php if ($isUnread): ?><span class="cd-dot" title="Non lue"></span><?php endif; ?>
            <span class="cd-time"><?= e($rel($n['date_creation'] ?? null)) ?></span>
          </div>
          <?php if (!empty($n['message'])): ?>
          <p class="cd-msg"><?= e($n['message']) ?></p>
          <?php endif; ?>
          <?php if ($isAdmin): ?>
          <div class="cd-from">
            <span class="cd-from-badge">Administration Connect'Academia</span>
          </div>
          <?php endif; ?>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

</div>

<style>
/* ══════════ Messages de notification — design system Claude ══════════ */
.cd-notifs {
  --cd-canvas:#faf9f5; --cd-card:#fdfcf9; --cd-card-unread:#f5f0e8;
  --cd-hairline:#e6dfd8; --cd-hairline-soft:#ebe6df;
  --cd-ink:#141413; --cd-body:#3d3d3a; --cd-muted:#6c6a64; --cd-faint:#8e8b82;
  --cd-coral:#cc785c;
  --cd-serif:'Spectral','Tiempos Headline',Georgia,serif;
  --cd-sans:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;
  font-family:var(--cd-sans);
  -webkit-font-smoothing:antialiased; text-rendering:optimizeLegibility;
}

.cd-head { display:flex; align-items:flex-end; justify-content:space-between; gap:18px; flex-wrap:wrap; margin-bottom:26px; }
.cd-title { font-family:var(--cd-serif); font-weight:400; font-size:32px; letter-spacing:-.02em; color:var(--cd-ink); line-height:1.1; margin:0; }
.cd-sub { font-size:14px; line-height:1.55; color:var(--cd-muted); margin:8px 0 0; max-width:56ch; }
.cd-sub strong { color:var(--cd-coral); font-weight:600; }

.cd-mark {
  display:inline-flex; align-items:center; gap:7px; cursor:pointer;
  font-family:var(--cd-sans); font-size:13px; font-weight:500; color:var(--cd-body);
  background:var(--cd-card); border:1px solid var(--cd-hairline); border-radius:8px;
  padding:9px 14px; transition:border-color .18s ease, color .18s ease, background .18s ease;
}
.cd-mark:hover { border-color:var(--cd-coral); color:var(--cd-coral); background:#fff; }
.cd-mark svg { width:14px; height:14px; }

.cd-empty {
  display:flex; flex-direction:column; align-items:center; gap:12px;
  padding:64px 20px; text-align:center; color:var(--cd-faint);
  background:var(--cd-card); border:1px solid var(--cd-hairline); border-radius:16px;
}
.cd-empty p { font-size:14px; color:var(--cd-muted); margin:0; }

/* Liste — cartes crème, ombres rares, hairlines */
.cd-list { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:10px; }
.cd-item {
  display:flex; gap:14px; align-items:flex-start;
  background:var(--cd-card); border:1px solid var(--cd-hairline); border-radius:14px;
  padding:16px 18px; transition:border-color .18s ease, background .18s ease;
}
.cd-item:hover { border-color:var(--cd-hairline-soft); background:#fff; }
/* Non lue : rail coral discret + surface crème appuyée (accent rare = coral) */
.cd-item.is-unread {
  background:var(--cd-card-unread);
  border-color:var(--cd-hairline);
  box-shadow:inset 3px 0 0 var(--cd-coral);
}

.cd-icon {
  flex:none; width:38px; height:38px; border-radius:10px;
  display:inline-flex; align-items:center; justify-content:center;
}

.cd-body { flex:1; min-width:0; }
.cd-row { display:flex; align-items:center; gap:9px; flex-wrap:wrap; }
.cd-name { font-size:14.5px; font-weight:600; color:var(--cd-ink); letter-spacing:-.01em; }
.cd-dot { width:7px; height:7px; border-radius:50%; background:var(--cd-coral); flex:none; }
.cd-time { margin-left:auto; font-size:12px; color:var(--cd-faint); white-space:nowrap; }

.cd-msg {
  font-size:13.5px; line-height:1.62; color:var(--cd-body); margin:6px 0 0;
  white-space:pre-wrap; word-break:break-word;
}

.cd-from { margin-top:10px; }
.cd-from-badge {
  display:inline-flex; align-items:center; font-size:11.5px; font-weight:500;
  color:var(--cd-coral); background:rgba(204,120,92,.10);
  border:1px solid rgba(204,120,92,.22); border-radius:999px; padding:3px 10px;
}

@media (max-width:560px) {
  .cd-title { font-size:27px; }
  .cd-item { padding:14px 14px; gap:12px; }
  .cd-time { margin-left:0; width:100%; }
}
</style>
