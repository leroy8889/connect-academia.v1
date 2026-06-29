<?php
// Variables: $stats, $posts, $topContributeurs
$stats            = $stats ?? ['posts' => 0, 'comments' => 0, 'likes' => 0, 'reports' => 0];
$posts            = $posts ?? [];
$topContributeurs = $topContributeurs ?? [];

function relativeTime(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'À l\'instant';
    if ($diff < 3600)   return 'il y a ' . floor($diff / 60) . ' min';
    if ($diff < 86400)  return 'il y a ' . floor($diff / 3600) . 'h';
    if ($diff < 172800) return 'HIER';
    return date('d M', strtotime($datetime));
}
?>

<!-- Header -->
<div class="admin-page-header-row">
  <div>
    <h1>Communauté <span class="badge-live" style="margin-left:10px;">En direct</span></h1>
    <p><?= number_format($stats['posts'] ?? 0) ?> posts · <?= number_format($stats['comments'] ?? 0) ?> commentaires · <?= number_format($stats['likes'] ?? 0) ?> likes</p>
  </div>
  <div style="display:flex;gap:10px;">
    <button class="btn-outline">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      Exporter
    </button>
    <button class="btn-primary">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      Épingler un post
    </button>
  </div>
</div>

<!-- Stats communauté -->
<div class="communaute-stats-row mb-24">
  <?php
  $statCards = [
    [
      'label' => 'Posts ce mois', 'val' => number_format($stats['posts_mois'] ?? 847),
      'trend' => '+16%', 'up' => true, 'color' => '#8B52FA',
      'icon'  => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
      'spark' => 'M0,30 C10,28 20,22 30,20 C40,18 50,15 60,12 C70,9 80,10 90,7 C95,6 98,5 100,4',
    ],
    [
      'label' => 'Commentaires', 'val' => number_format($stats['comments'] ?? 4210),
      'trend' => '+24%', 'up' => true, 'color' => '#F59E0B',
      'icon'  => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>',
      'spark' => 'M0,28 C15,26 25,20 35,16 C45,12 55,10 65,8 C75,6 85,5 95,3 C97,3 99,2 100,2',
    ],
    [
      'label' => 'Likes', 'val' => number_format($stats['likes'] ?? 12890),
      'trend' => '+9%', 'up' => true, 'color' => '#EC4899',
      'icon'  => '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>',
      'spark' => 'M0,25 C10,24 20,20 30,18 C40,16 50,14 60,12 C70,10 80,8 90,6 C95,5 98,4 100,3',
    ],
    [
      'label' => 'Signalements', 'val' => number_format($stats['reports'] ?? 18),
      'trend' => '-12%', 'up' => false, 'color' => '#EF4444',
      'icon'  => '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/>',
      'spark' => 'M0,5 C10,7 20,10 30,14 C40,18 50,20 60,22 C70,24 80,26 90,28 C95,29 98,30 100,30',
    ],
  ];
  foreach ($statCards as $sc):
    $sparkId = 'cgrad-' . md5($sc['label']);
  ?>
  <div class="communaute-stat-card" style="position:relative;overflow:hidden;">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;">
      <div>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
          <div style="width:32px;height:32px;border-radius:8px;background:<?= $sc['color'] ?>1a;display:flex;align-items:center;justify-content:center;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="<?= $sc['color'] ?>" stroke-width="2"><?= $sc['icon'] ?></svg>
          </div>
          <span class="communaute-stat-trend <?= $sc['up'] ? 'kpi-trend-up' : 'kpi-trend-down' ?>">
            <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
              <?php if ($sc['up']): ?><polyline points="18 15 12 9 6 15"/>
              <?php else: ?><polyline points="6 9 12 15 18 9"/><?php endif; ?>
            </svg>
            <?= $sc['trend'] ?>
          </span>
        </div>
        <div class="communaute-stat-val"><?= $sc['val'] ?></div>
        <div class="communaute-stat-label"><?= $sc['label'] ?></div>
      </div>
      <svg width="80" height="36" viewBox="0 0 100 36" preserveAspectRatio="none" style="position:absolute;bottom:0;right:0;opacity:0.5;">
        <defs>
          <linearGradient id="<?= $sparkId ?>" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="<?= $sc['color'] ?>" stop-opacity="0.3"/>
            <stop offset="100%" stop-color="<?= $sc['color'] ?>" stop-opacity="0"/>
          </linearGradient>
        </defs>
        <path d="<?= $sc['spark'] ?> L100,36 L0,36 Z" fill="url(#<?= $sparkId ?>)"/>
        <path d="<?= $sc['spark'] ?>" fill="none" stroke="<?= $sc['color'] ?>" stroke-width="1.5"/>
      </svg>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Layout: Fil + Top contributeurs -->
<div class="communaute-layout">

  <!-- Fil des publications -->
  <div class="admin-table-wrap">
    <div class="admin-card-header" style="padding:16px 20px;">
      <div>
        <h3 style="font-size:15px;font-weight:700;margin:0;">Fil des publications</h3>
        <p style="font-size:11px;color:var(--txt-m);margin:3px 0 0;">Posts récents — toutes matières</p>
      </div>
      <div style="display:flex;gap:8px;">
        <button class="btn-ghost btn-sm" style="font-size:12px;">Tous ▾</button>
        <button class="btn-ghost btn-sm" style="font-size:12px;">Matière ▾</button>
      </div>
    </div>
    <div>
      <?php if (empty($posts)): ?>
      <div class="empty-state" style="padding:40px;">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <h3>Aucune publication</h3><p>Le fil est vide.</p>
      </div>
      <?php endif; ?>
      <?php foreach ($posts as $post): ?>
      <div class="post-item" style="padding:14px 20px;border-bottom:1px solid var(--brd);">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
          <div class="user-avatar" style="width:34px;height:34px;min-width:34px;font-size:11px;">
            <?= strtoupper(mb_substr($post['prenom'] ?? 'U', 0, 1) . mb_substr($post['nom'] ?? 'S', 0, 1)) ?>
          </div>
          <div style="flex:1;min-width:0;">
            <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
              <strong style="font-size:13px;"><?= e(($post['prenom'] ?? '') . ' ' . ($post['nom'] ?? '')) ?></strong>
              <span class="badge badge-<?= e($post['role'] ?? 'eleve') ?>" style="font-size:10px;"><?= e(ucfirst($post['role'] ?? 'Élève')) ?></span>
              <?php if (!empty($post['serie_nom'])): ?>
                <span style="font-size:10px;color:var(--txt-m);">· T<?= e($post['serie_nom']) ?></span>
              <?php endif; ?>
              <?php if (!empty($post['is_pinned'])): ?>
                <span style="font-size:10px;font-weight:600;color:var(--ap);background:var(--ap-xl);padding:1px 7px;border-radius:20px;display:inline-flex;align-items:center;gap:3px;">
                  <svg width="9" height="9" viewBox="0 0 24 24" fill="currentColor"><path d="M16,12V4H17V2H7V4H8V12L6,14V16H11.2V22H12.8V16H18V14L16,12Z"/></svg>
                  Épinglé
                </span>
              <?php endif; ?>
            </div>
          </div>
          <span style="font-size:11px;color:var(--txt-l);white-space:nowrap;">
            <?= $post['created_at'] ? relativeTime($post['created_at']) : '' ?>
          </span>
          <div style="display:flex;gap:3px;flex-shrink:0;">
            <button class="action-btn btn-pin-post"
                    title="<?= $post['is_pinned'] ? 'Désépingler' : 'Épingler' ?>"
                    data-post-id="<?= $post['id'] ?>"
                    data-pinned="<?= $post['is_pinned'] ? '1' : '0' ?>"
                    style="width:26px;height:26px;color:<?= $post['is_pinned'] ? 'var(--ap)' : 'var(--txt-l)' ?>;">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="<?= $post['is_pinned'] ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2"><path d="M12 2L8.5 8.5H2l5.5 5L5 22l7-4 7 4-2.5-8.5L22 8.5h-6.5L12 2z"/></svg>
            </button>
            <button class="action-btn btn-delete-post"
                    title="Supprimer"
                    data-post-id="<?= $post['id'] ?>"
                    style="width:26px;height:26px;color:var(--red,#EF4444);">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
            </button>
          </div>
        </div>
        <p style="font-size:13px;color:var(--txt);margin:0 0 8px;line-height:1.5;padding-left:44px;">
          <?= e(mb_substr($post['contenu'] ?? '', 0, 160)) ?><?= mb_strlen($post['contenu'] ?? '') > 160 ? '…' : '' ?>
        </p>
        <div style="padding-left:44px;display:flex;align-items:center;gap:8px;">
          <?php if (!empty($post['matiere_nom'])): ?>
          <span style="font-size:11px;font-weight:600;background:var(--ap-xl);color:var(--ap);padding:2px 10px;border-radius:20px;"><?= e($post['matiere_nom']) ?></span>
          <?php endif; ?>
          <span style="font-size:11px;color:var(--txt-m);display:flex;align-items:center;gap:3px;">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            <?= number_format((int)($post['likes_count'] ?? 0)) ?>
          </span>
          <span style="font-size:11px;color:var(--txt-m);display:flex;align-items:center;gap:3px;">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            <?= number_format((int)($post['comments_count'] ?? 0)) ?>
          </span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Top contributeurs -->
  <div>
    <div class="admin-table-wrap">
      <div class="admin-card-header" style="padding:16px 20px;">
        <h3 style="font-size:15px;font-weight:700;margin:0;">Top contributeurs</h3>
        <span style="font-size:12px;color:var(--txt-m);">30 derniers jours</span>
      </div>
      <div style="padding:8px 16px 16px;">
        <?php foreach ($topContributeurs as $i => $contrib): ?>
        <div class="top-contributor-item" style="display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--brd);">
          <div style="width:24px;font-size:12px;font-weight:700;color:var(--txt-l);text-align:center;">#<?= $i + 1 ?></div>
          <div class="user-avatar" style="width:32px;height:32px;min-width:32px;font-size:10px;background:var(--ap);">
            <?= strtoupper(mb_substr($contrib['prenom'] ?? 'U', 0, 1) . mb_substr($contrib['nom'] ?? 'S', 0, 1)) ?>
          </div>
          <div style="flex:1;min-width:0;">
            <div style="font-size:13px;font-weight:600;color:var(--txt);"><?= e(($contrib['prenom'] ?? '') . ' ' . ($contrib['nom'] ?? '')) ?></div>
            <div style="font-size:11px;color:var(--txt-m);">
              <?= e(ucfirst($contrib['role'] ?? '')) ?>
              <?php if (!empty($contrib['serie_nom'])): ?>
                · T<?= e($contrib['serie_nom']) ?>
              <?php endif; ?>
            </div>
          </div>
          <div style="text-align:right;">
            <div style="font-size:15px;font-weight:700;color:var(--txt);"><?= number_format((int)($contrib['posts_count'] ?? 0)) ?></div>
            <div style="font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:var(--txt-l);">posts</div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($topContributeurs)): ?>
          <p style="text-align:center;padding:24px 0;color:var(--txt-m);font-size:13px;">Aucune activité</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>
