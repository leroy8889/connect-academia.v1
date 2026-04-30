<?php
$salons       = $salons       ?? [];
$unreadNotifs = (int) ($unreadNotifs ?? 0);

// Icônes par défaut selon le tag matière/série
function salonIcon(?string $tag): string {
    if (!$tag) return '💬';
    return match (true) {
        str_contains(strtolower($tag), 'math')    => '🔢',
        str_contains(strtolower($tag), 'phys')    => '⚛️',
        str_contains(strtolower($tag), 'chim')    => '🧪',
        str_contains(strtolower($tag), 'bio')     => '🧬',
        str_contains(strtolower($tag), 'fran')    => '📖',
        str_contains(strtolower($tag), 'hist')    => '🗺️',
        str_contains(strtolower($tag), 'svt')     => '🌿',
        str_contains(strtolower($tag), 'philo')   => '🤔',
        str_contains(strtolower($tag), 'info')    => '💻',
        str_contains(strtolower($tag), 'ang')     => '🌐',
        default                                   => '💬',
    };
}
?>

<div class="salons-page">

  <!-- ── Hero ──────────────────────────────────────────────── -->
  <div style="margin-bottom:32px">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px">
      <a href="<?= url('/communaute') ?>" style="display:flex;align-items:center;gap:4px;font-size:13px;color:var(--color-gray-500);text-decoration:none">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        Communauté
      </a>
    </div>
    <h1 style="font-size:28px;font-weight:700;color:var(--color-dark);margin-bottom:6px">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;color:var(--color-primary)">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
      </svg>
      Chat — Salons
    </h1>
    <p style="color:var(--color-gray-500);font-size:14px">
      Rejoignez un salon de discussion et échangez en temps réel avec vos camarades.
    </p>
  </div>

  <!-- ── Liste des salons ───────────────────────────────────── -->
  <?php if (empty($salons)): ?>
    <div style="text-align:center;padding:60px 20px;color:var(--color-gray-500)">
      <div style="font-size:48px;margin-bottom:16px">💬</div>
      <h3 style="font-size:20px;color:var(--color-dark);margin-bottom:8px">Aucun salon disponible</h3>
      <p style="font-size:14px">Les salons de discussion seront bientôt créés par les administrateurs.</p>
    </div>
  <?php else: ?>
    <?php foreach ($salons as $s): ?>
      <a href="<?= url('/communaute/chat/' . (int)$s['id']) ?>" class="salon-card">

        <div class="salon-card__icon">
          <?= salonIcon($s['matiere_tag'] ?? $s['serie_tag'] ?? null) ?>
        </div>

        <div class="salon-card__info">
          <div class="salon-card__name"><?= e($s['nom']) ?></div>
          <?php if (!empty($s['description'])): ?>
            <div class="salon-card__desc"><?= e($s['description']) ?></div>
          <?php endif; ?>
          <div class="salon-card__tags">
            <?php if (!empty($s['serie_tag'])): ?>
              <span class="salon-card__tag">Terminale <?= e($s['serie_tag']) ?></span>
            <?php endif; ?>
            <?php if (!empty($s['matiere_tag'])): ?>
              <span class="salon-card__tag"><?= e($s['matiere_tag']) ?></span>
            <?php endif; ?>
          </div>
        </div>

        <div class="salon-card__chevron">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="9 18 15 12 9 6"/>
          </svg>
        </div>

      </a>
    <?php endforeach; ?>
  <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Notifications !== 'undefined') Notifications.init(<?= $unreadNotifs ?>);
});
</script>
