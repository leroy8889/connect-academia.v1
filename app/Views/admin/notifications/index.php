<?php
// Variables: $notifs (array avec 'aujourd_hui', 'hier', 'plus_ancien')
$notifs    = $notifs ?? [];
$nbNonLus  = $nbNonLus ?? 0;
$nbTotal   = $nbTotal ?? 0;

$iconByType = [
    'inscription'  => ['class' => 'purple', 'icon' => '<path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/>'],
    'signalement'  => ['class' => 'red',    'icon' => '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/>'],
    'upload'       => ['class' => 'green',  'icon' => '<polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/>'],
    'connexion'    => ['class' => 'amber',  'icon' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>'],
    'activite'     => ['class' => 'purple', 'icon' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>'],
    'systeme'      => ['class' => 'gray',   'icon' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06A1.65 1.65 0 0 0 12 19.4v.09a2 2 0 0 1-4 0V19.4a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 12H3.51a2 2 0 0 1 0-4H4.6A1.65 1.65 0 0 0 6 6.6a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 12 4.6V3.51a2 2 0 0 1 4 0V4.6a1.65 1.65 0 0 0 1 1.51z"/>'],
];
?>

<!-- Header -->
<div class="admin-page-header-row">
  <div>
    <h1>Notifications</h1>
    <p><?= $nbNonLus ?> non lues · <?= $nbTotal ?> notifications des 48 dernières heures</p>
  </div>
  <div style="display:flex;gap:10px;">
    <button class="btn-ghost">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
      Filtrer
    </button>
    <button class="btn-primary" id="mark-all-read">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
      Tout marquer lu
    </button>
  </div>
</div>

<div class="admin-table-wrap">
  <?php
  $groups = [
    "Aujourd'hui" => $notifs['aujourd_hui'] ?? [],
    'Hier'        => $notifs['hier'] ?? [],
    'Plus ancien' => $notifs['plus_ancien'] ?? [],
  ];
  $hasAny = false;
  foreach ($groups as $groupLabel => $items):
    if (empty($items)) continue;
    $hasAny = true;
  ?>
  <div class="notif-group-label" style="padding-left:20px;"><?= $groupLabel ?></div>
  <?php foreach ($items as $notif):
    $type = $notif['type'] ?? 'systeme';
    $icon = $iconByType[$type] ?? $iconByType['systeme'];
    $time = $notif['created_at'] ? date('H:i', strtotime($notif['created_at'])) : '';
    $isUnread = !($notif['is_read'] ?? false);
  ?>
  <div class="notif-item <?= $isUnread ? 'unread' : '' ?>">
    <div class="notif-icon-wrap <?= $icon['class'] ?>">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
        <?= $icon['icon'] ?>
      </svg>
    </div>
    <div class="notif-body">
      <div class="notif-title"><?= e($notif['titre'] ?? 'Notification') ?></div>
      <div class="notif-desc"><?= e($notif['message'] ?? '') ?></div>
    </div>
    <div class="notif-time"><?= $time ?></div>
  </div>
  <?php endforeach; ?>
  <?php endforeach; ?>

  <?php if (!$hasAny): ?>
  <div class="empty-state">
    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
    <h3>Aucune notification</h3>
    <p>Vous êtes à jour ! Revenez plus tard.</p>
  </div>
  <?php endif; ?>
</div>

<script>
document.getElementById('mark-all-read')?.addEventListener('click', async () => {
  const btn = document.getElementById('mark-all-read');
  if (btn) { btn.disabled = true; btn.style.opacity = '0.6'; }
  try {
    await fetch(`${window.CA_ADMIN?.baseUrl ?? ''}/admin/api/notifications/mark-all`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': window.CA_ADMIN?.csrfToken ?? '',
      },
      body: JSON.stringify({ _csrf_token: window.CA_ADMIN?.csrfToken ?? '' }),
    });
    document.querySelectorAll('.notif-item.unread').forEach(n => n.classList.remove('unread'));
    window.showToast?.('Toutes les notifications marquées comme lues', 'success');
  } catch {
    window.showToast?.('Erreur réseau', 'error');
  } finally {
    if (btn) { btn.disabled = false; btn.style.opacity = ''; }
  }
});
</script>
