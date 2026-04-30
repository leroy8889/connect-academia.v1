<?php
/** @var array $subjects */
/** @var array $classes */
/** @var int $totalPosts */
$activePage = 'subjects';
$headerTitle = 'Subjects & Classes';

$barColors = ['#8B52FA', '#F59E0B', '#06B6D4', '#3B82F6', '#6366F1', '#22c55e', '#ef4444', '#f97316', '#ec4899', '#14b8a6', '#a855f7', '#eab308'];
?>

<!-- ── STATS CARDS ─────────────────────────── -->
<div class="admin-kpi-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 24px;">
    <div class="admin-kpi-card">
        <div class="admin-kpi-card__header">
            <span class="admin-kpi-card__label">TOTAL SUBJECTS</span>
            <div class="admin-kpi-card__icon admin-kpi-card__icon--purple">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                </svg>
            </div>
        </div>
        <div class="admin-kpi-card__value"><?= count($subjects) ?></div>
    </div>
    <div class="admin-kpi-card">
        <div class="admin-kpi-card__header">
            <span class="admin-kpi-card__label">TOTAL POSTS</span>
            <div class="admin-kpi-card__icon admin-kpi-card__icon--blue">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/>
                </svg>
            </div>
        </div>
        <div class="admin-kpi-card__value"><?= number_format($totalPosts) ?></div>
    </div>
    <div class="admin-kpi-card">
        <div class="admin-kpi-card__header">
            <span class="admin-kpi-card__label">CLASSES</span>
            <div class="admin-kpi-card__icon admin-kpi-card__icon--green">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
            </div>
        </div>
        <div class="admin-kpi-card__value"><?= count($classes) ?></div>
    </div>
</div>

<!-- ── SUBJECTS TABLE ─────────────────────── -->
<div class="admin-card admin-card--activity">
    <div class="admin-card__header">
        <h2 class="admin-card__title">Subjects Overview</h2>
        <span class="admin-card__subtitle" style="margin-top: 0;">Activity metrics per subject</span>
    </div>
    <div class="admin-card__body admin-card__body--table">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>SUBJECT</th>
                    <th>POSTS</th>
                    <th>USERS</th>
                    <th>LIKES</th>
                    <th>COMMENTS</th>
                    <th>LAST ACTIVITY</th>
                    <th>ACTIVITY</th>
                </tr>
            </thead>
            <tbody>
                <?php $maxPosts = max(array_column($subjects, 'posts_count') ?: [1]); ?>
                <?php foreach ($subjects as $i => $subject): ?>
                    <tr>
                        <td>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <span style="width:8px; height:8px; border-radius:50%; background:<?= $barColors[$i % count($barColors)] ?>; flex-shrink:0;"></span>
                                <span class="admin-table__user-name"><?= htmlspecialchars($subject['name']) ?></span>
                                <?php if (!$subject['is_official']): ?>
                                    <span class="admin-badge admin-badge--pending" style="font-size:0.6rem; padding:2px 6px;">Unofficial</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><strong><?= number_format($subject['posts_count']) ?></strong></td>
                        <td><?= number_format($subject['users_count']) ?></td>
                        <td><?= number_format($subject['total_likes']) ?></td>
                        <td><?= number_format($subject['total_comments']) ?></td>
                        <td class="admin-table__time">
                            <?= $subject['last_activity'] ? date('M d, Y', strtotime($subject['last_activity'])) : '—' ?>
                        </td>
                        <td style="min-width:120px;">
                            <div class="admin-subject-item__bar" style="margin:0;">
                                <div class="admin-subject-item__bar-fill"
                                     style="width: <?= $maxPosts > 0 ? round(($subject['posts_count'] / $maxPosts) * 100) : 0 ?>%; background: <?= $barColors[$i % count($barColors)] ?>;">
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($subjects)): ?>
                    <tr><td colspan="7" class="admin-table__empty">Aucune matière configurée</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── CLASSES ──────────────────────────────── -->
<div class="admin-card" style="margin-top: 24px;">
    <div class="admin-card__header">
        <h2 class="admin-card__title">Available Classes</h2>
        <span class="admin-card__subtitle" style="margin-top: 0;">Configured in platform settings</span>
    </div>
    <div class="admin-card__body">
        <div style="display: flex; flex-wrap: wrap; gap: 10px;">
            <?php foreach ($classes as $classe): ?>
                <span class="admin-badge admin-badge--info" style="padding: 8px 16px; font-size: 0.8rem;">
                    <?= htmlspecialchars($classe) ?>
                </span>
            <?php endforeach; ?>
            <?php if (empty($classes)): ?>
                <span style="color: var(--admin-text-muted); font-size: 0.85rem;">Aucune classe configurée</span>
            <?php endif; ?>
        </div>
    </div>
</div>

