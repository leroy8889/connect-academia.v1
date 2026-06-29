<?php
$salon        = $salon        ?? [];
$messages     = $messages     ?? [];
$unreadNotifs = (int) ($unreadNotifs ?? 0);
$salonId      = (int) ($salon['id'] ?? 0);
$userId       = \Core\Session::userId();

// ID du dernier message pour le long polling
$lastMessageId = !empty($messages) ? (int) end($messages)['id'] : 0;

// Grouper les messages par date
function formatChatDate(string $date): string {
    $ts   = strtotime($date);
    $today = strtotime('today');
    $yest  = strtotime('yesterday');
    if ($ts >= $today) return "Aujourd'hui";
    if ($ts >= $yest)  return "Hier";
    return date('d/m/Y', $ts);
}

$previousDate = null;
?>

<div class="chat-layout">

  <!-- ══ SIDEBAR SALONS ════════════════════════════════════ -->
  <aside class="chat-sidebar">
    <div class="chat-sidebar__header">
      <div class="chat-sidebar__title">Salons</div>
      <div class="chat-sidebar__sub">Chat en temps réel</div>
    </div>
    <div class="chat-sidebar__list">
      <a href="<?= url('/communaute/chat') ?>"
         class="chat-sidebar__item"
         style="font-size:12px;color:var(--color-gray-500);padding:8px 16px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="15 18 9 12 15 6"/>
        </svg>
        Tous les salons
      </a>
      <!-- Salon actif -->
      <div class="chat-sidebar__item chat-sidebar__item--active">
        <div class="chat-sidebar__item-icon">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
          </svg>
        </div>
        <span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($salon['nom'] ?? '') ?></span>
      </div>
    </div>
  </aside>

  <!-- ══ ZONE PRINCIPALE ══════════════════════════════════ -->
  <div class="chat-main">

    <!-- Header du salon -->
    <div class="chat-header">
      <a href="<?= url('/communaute/chat') ?>" class="chat-header__back" style="display:none" aria-label="Retour">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="15 18 9 12 15 6"/>
        </svg>
      </a>
      <div class="chat-header__icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#8B52FA" stroke-width="2">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
        </svg>
      </div>
      <div>
        <div class="chat-header__name"><?= e($salon['nom'] ?? '') ?></div>
        <?php if (!empty($salon['description'])): ?>
          <div class="chat-header__desc"><?= e($salon['description']) ?></div>
        <?php endif; ?>
      </div>
      <?php if (!empty($salon['serie_tag']) || !empty($salon['matiere_tag'])): ?>
        <div style="margin-left:auto;display:flex;gap:6px;flex-wrap:wrap">
          <?php if (!empty($salon['serie_tag'])): ?>
            <span style="padding:3px 10px;background:var(--color-lavender);color:var(--color-primary);border-radius:var(--radius-full);font-size:11px;font-weight:600">
              Terminale <?= e($salon['serie_tag']) ?>
            </span>
          <?php endif; ?>
          <?php if (!empty($salon['matiere_tag'])): ?>
            <span style="padding:3px 10px;background:var(--color-lavender);color:var(--color-primary);border-radius:var(--radius-full);font-size:11px;font-weight:600">
              <?= e($salon['matiere_tag']) ?>
            </span>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Messages -->
    <div id="chat-messages">

      <?php if (empty($messages)): ?>
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--color-gray-500);gap:12px;padding:40px">
          <div style="font-size:40px">💬</div>
          <p style="font-size:15px;text-align:center">Soyez le premier à écrire dans ce salon !</p>
        </div>
      <?php else: ?>

        <?php foreach ($messages as $msg):
          $msgDate = formatChatDate($msg['created_at'] ?? '');
          $isMe    = ((int)($msg['user_id'] ?? 0)) === $userId;
          $photo   = \Models\User::normalizePhotoPath($msg['photo_profil'] ?? null);
          $name    = e(($msg['prenom'] ?? '') . ' ' . ($msg['nom'] ?? ''));
          $time    = date('H:i', strtotime($msg['created_at'] ?? 'now'));
        ?>

          <!-- Séparateur de date -->
          <?php if ($msgDate !== $previousDate): ?>
            <div class="chat-date-sep"><?= $msgDate ?></div>
            <?php $previousDate = $msgDate; ?>
          <?php endif; ?>

          <div class="chat-msg <?= $isMe ? 'chat-msg--me' : '' ?>" data-id="<?= (int)$msg['id'] ?>">

            <?php if (!$isMe): ?>
              <img class="chat-msg__avatar"
                   src="<?= e($photo ? url($photo) : asset('images/default-avatar.svg')) ?>"
                   alt="<?= $name ?>"
                   onerror="this.onerror=null;this.src=<?= htmlspecialchars(json_encode(asset('images/default-avatar.svg')), ENT_QUOTES) ?>">
            <?php endif; ?>

            <div class="chat-msg__body">
              <?php if (!$isMe): ?>
                <span class="chat-msg__name"><?= $name ?></span>
              <?php endif; ?>
              <div class="chat-msg__bubble"><?= htmlspecialchars($msg['contenu'] ?? '', ENT_QUOTES | ENT_HTML5) ?></div>
              <span class="chat-msg__time"><?= $time ?></span>
            </div>

            <?php if ($isMe): ?>
              <img class="chat-msg__avatar"
                   src="<?= e($photo ? url($photo) : asset('images/default-avatar.svg')) ?>"
                   alt="<?= $name ?>"
                   onerror="this.onerror=null;this.src=<?= htmlspecialchars(json_encode(asset('images/default-avatar.svg')), ENT_QUOTES) ?>">
            <?php endif; ?>

          </div>

        <?php endforeach; ?>
      <?php endif; ?>

    </div>

    <!-- Zone de saisie -->
    <div class="chat-input-zone">
      <form id="chat-form" style="display:contents" autocomplete="off">
        <textarea
          id="chat-input"
          placeholder="Écrivez un message…"
          rows="1"
          maxlength="1000"
          aria-label="Message"></textarea>
        <button type="submit" id="chat-send" aria-label="Envoyer">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
          </svg>
        </button>
      </form>
    </div>

  </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    Chat.init(<?= $salonId ?>, <?= $lastMessageId ?>);
    if (typeof Notifications !== 'undefined') Notifications.init(<?= $unreadNotifs ?>);

    // Afficher le bouton retour sur mobile
    if (window.innerWidth < 768) {
        const backBtn = document.querySelector('.chat-header__back');
        if (backBtn) backBtn.style.display = 'flex';
    }
});
</script>
