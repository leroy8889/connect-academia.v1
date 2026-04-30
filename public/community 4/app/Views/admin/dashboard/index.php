<?php
/** @var array $stats */
/** @var array $recentActivity */
/** @var array $subjectActivity */
$activePage = 'dashboard';
$headerTitle = 'Dashboard Overview';

// Couleurs pour les barres d'activité par matière
$barColors = ['#8B52FA', '#F59E0B', '#06B6D4', '#3B82F6', '#6366F1'];
?>

<!-- ── KPI CARDS ────────────────────────── -->
<div class="admin-kpi-grid">
    <!-- Total Students -->
    <div class="admin-kpi-card">
        <div class="admin-kpi-card__header">
            <span class="admin-kpi-card__label">TOTAL STUDENTS</span>
            <div class="admin-kpi-card__icon admin-kpi-card__icon--green">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
                    <polyline points="17 6 23 6 23 12"/>
                </svg>
            </div>
        </div>
        <div class="admin-kpi-card__value" id="kpi-students">
            <?= number_format($stats['total_students'] ?? 0) ?>
        </div>
        <div class="admin-kpi-card__change <?= ($stats['students_growth'] ?? 0) >= 0 ? 'admin-kpi-card__change--up' : 'admin-kpi-card__change--down' ?>">
            <?= ($stats['students_growth'] ?? 0) >= 0 ? '+' : '' ?><?= $stats['students_growth'] ?? 0 ?>%
            <span class="admin-kpi-card__change-label">vs last month</span>
        </div>
    </div>

    <!-- Active Teachers -->
    <div class="admin-kpi-card">
        <div class="admin-kpi-card__header">
            <span class="admin-kpi-card__label">ACTIVE TEACHERS</span>
            <div class="admin-kpi-card__icon admin-kpi-card__icon--blue">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="8.5" cy="7" r="4"/>
                    <line x1="20" y1="8" x2="20" y2="14"/>
                    <line x1="23" y1="11" x2="17" y2="11"/>
                </svg>
            </div>
        </div>
        <div class="admin-kpi-card__value" id="kpi-teachers">
            <?= number_format($stats['active_teachers'] ?? 0) ?>
        </div>
        <div class="admin-kpi-card__change <?= ($stats['teachers_growth'] ?? 0) >= 0 ? 'admin-kpi-card__change--up' : 'admin-kpi-card__change--down' ?>">
            <?= ($stats['teachers_growth'] ?? 0) >= 0 ? '+' : '' ?><?= $stats['teachers_growth'] ?? 0 ?>%
            <span class="admin-kpi-card__change-label">vs last month</span>
        </div>
    </div>

    <!-- Active Users (MAU) -->
    <div class="admin-kpi-card">
        <div class="admin-kpi-card__header">
            <span class="admin-kpi-card__label">ACTIVE USERS (MAU)</span>
            <div class="admin-kpi-card__icon admin-kpi-card__icon--yellow">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                </svg>
            </div>
        </div>
        <div class="admin-kpi-card__value" id="kpi-active-users">
            <?= number_format($stats['active_users'] ?? 0) ?>
        </div>
        <div class="admin-kpi-card__change <?= ($stats['active_users_growth'] ?? 0) >= 0 ? 'admin-kpi-card__change--up' : 'admin-kpi-card__change--down' ?>">
            <?= ($stats['active_users_growth'] ?? 0) >= 0 ? '+' : '' ?><?= $stats['active_users_growth'] ?? 0 ?>%
            <span class="admin-kpi-card__change-label">vs last month</span>
        </div>
    </div>

    <!-- Engagement Rate -->
    <div class="admin-kpi-card">
        <div class="admin-kpi-card__header">
            <span class="admin-kpi-card__label">ENGAGEMENT RATE</span>
            <div class="admin-kpi-card__icon admin-kpi-card__icon--red">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                    <path d="M3 9h18"/>
                    <path d="M9 21V9"/>
                </svg>
            </div>
        </div>
        <div class="admin-kpi-card__value" id="kpi-engagement">
            <?= $stats['engagement_rate'] ?? 0 ?>%
        </div>
        <div class="admin-kpi-card__change <?= ($stats['engagement_growth'] ?? 0) >= 0 ? 'admin-kpi-card__change--up' : 'admin-kpi-card__change--down' ?>">
            <?= ($stats['engagement_growth'] ?? 0) >= 0 ? '+' : '' ?><?= $stats['engagement_growth'] ?? 0 ?>%
            <span class="admin-kpi-card__change-label">vs last month</span>
        </div>
    </div>
</div>

<!-- ── CHARTS ROW ───────────────────────── -->
<div class="admin-charts-row">
    <!-- Registration Growth -->
    <div class="admin-card admin-card--chart">
        <div class="admin-card__header">
            <div class="admin-card__header-left">
                <h2 class="admin-card__title">Registration Growth</h2>
                <p class="admin-card__subtitle">Daily student and teacher registrations over time</p>
            </div>
            <div class="admin-card__header-right">
                <div class="admin-period-toggle">
                    <button class="admin-period-btn" data-period="7">7D</button>
                    <button class="admin-period-btn admin-period-btn--active" data-period="30">30D</button>
                    <button class="admin-period-btn" data-period="90">90D</button>
                </div>
            </div>
        </div>
        <div class="admin-card__body admin-card__body--chart">
            <canvas id="registrationChart" height="280"></canvas>
        </div>
    </div>

    <!-- Activity by Subject -->
    <div class="admin-card admin-card--subject">
        <div class="admin-card__header">
            <div class="admin-card__header-left">
                <h2 class="admin-card__title">Activity by Subject</h2>
                <p class="admin-card__subtitle">Engagement frequency per category</p>
            </div>
        </div>
        <div class="admin-card__body">
            <div class="admin-subject-list">
                <?php if (!empty($subjectActivity)): ?>
                    <?php foreach ($subjectActivity as $i => $subject): ?>
                        <div class="admin-subject-item">
                            <div class="admin-subject-item__header">
                                <span class="admin-subject-item__name"><?= htmlspecialchars($subject['subject']) ?></span>
                                <span class="admin-subject-item__value"><?= $subject['percentage'] ?>%</span>
                            </div>
                            <div class="admin-subject-item__bar">
                                <div class="admin-subject-item__bar-fill" 
                                     style="width: <?= $subject['percentage'] ?>%; background: <?= $barColors[$i % count($barColors)] ?>;">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="admin-subject-empty">Aucune donnée disponible</div>
                <?php endif; ?>
            </div>
            <button class="admin-card__detail-btn">View Detailed Breakdown</button>
        </div>
    </div>
</div>

<!-- ── RECENT ACTIVITY ──────────────────── -->
<div class="admin-card admin-card--activity">
    <div class="admin-card__header">
        <h2 class="admin-card__title">Recent Activity</h2>
        <a href="<?= url('/admin/reports') ?>" class="admin-card__link">View all logs</a>
    </div>
    <div class="admin-card__body admin-card__body--table">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>USER</th>
                    <th>ACTION</th>
                    <th>GROUP/SUBJECT</th>
                    <th>TIMESTAMP</th>
                    <th>STATUS</th>
                </tr>
            </thead>
            <tbody id="recent-activity-tbody">
                <?php if (!empty($recentActivity)): ?>
                    <?php foreach ($recentActivity as $activity): ?>
                        <tr>
                            <td>
                                <div class="admin-table__user">
                                    <img src="<?= htmlspecialchars(!empty($activity['user_photo']) ? url($activity['user_photo']) : asset('images/default-avatar.svg')) ?>"
                                         alt="" class="admin-table__avatar">
                                    <div class="admin-table__user-info">
                                        <span class="admin-table__user-name"><?= htmlspecialchars($activity['user_name']) ?></span>
                                        <span class="admin-table__user-role"><?= htmlspecialchars($activity['user_role']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($activity['action']) ?></td>
                            <td>
                                <a href="#" class="admin-table__link"><?= htmlspecialchars($activity['group']) ?></a>
                            </td>
                            <td class="admin-table__time"><?= htmlspecialchars($activity['timestamp']) ?></td>
                            <td>
                                <span class="admin-badge admin-badge--<?= $activity['status'] ?>">
                                    <?= ucfirst($activity['status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="admin-table__empty">Aucune activité récente</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

