<?php
/** @var array $systemInfo */
/** @var array $dbStats */
/** @var array $recentLogins */
$activePage = 'support';
$headerTitle = 'Support & System Info';
?>

<!-- ── SYSTEM INFO ─────────────────────────── -->
<div class="admin-kpi-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 24px;">
    <div class="admin-kpi-card">
        <div class="admin-kpi-card__header">
            <span class="admin-kpi-card__label">PHP VERSION</span>
            <div class="admin-kpi-card__icon admin-kpi-card__icon--blue">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>
                </svg>
            </div>
        </div>
        <div class="admin-kpi-card__value" style="font-size:1.5rem;"><?= htmlspecialchars($systemInfo['php_version']) ?></div>
    </div>
    <div class="admin-kpi-card">
        <div class="admin-kpi-card__header">
            <span class="admin-kpi-card__label">DATABASE</span>
            <div class="admin-kpi-card__icon admin-kpi-card__icon--green">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>
                </svg>
            </div>
        </div>
        <div class="admin-kpi-card__value" style="font-size:1.5rem;"><?= htmlspecialchars($systemInfo['database']) ?></div>
    </div>
    <div class="admin-kpi-card">
        <div class="admin-kpi-card__header">
            <span class="admin-kpi-card__label">ENVIRONMENT</span>
            <div class="admin-kpi-card__icon admin-kpi-card__icon--<?= $systemInfo['app_env'] === 'production' ? 'red' : 'yellow' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3"/><path d="M12 1v2m0 18v2M4.22 4.22l1.42 1.42m12.72 12.72l1.42 1.42M1 12h2m18 0h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>
                </svg>
            </div>
        </div>
        <div class="admin-kpi-card__value" style="font-size:1.5rem;"><?= ucfirst(htmlspecialchars($systemInfo['app_env'])) ?></div>
    </div>
</div>

<!-- ── DATABASE STATS ──────────────────────── -->
<div class="admin-card" style="margin-bottom: 24px;">
    <div class="admin-card__header">
        <h2 class="admin-card__title">Database Statistics</h2>
        <span class="admin-card__subtitle" style="margin-top: 0;">Current record counts</span>
    </div>
    <div class="admin-card__body">
        <div class="admin-stats-grid">
            <div class="admin-stat-item">
                <span class="admin-stat-item__value"><?= number_format($dbStats['users']) ?></span>
                <span class="admin-stat-item__label">Users</span>
            </div>
            <div class="admin-stat-item">
                <span class="admin-stat-item__value"><?= number_format($dbStats['posts']) ?></span>
                <span class="admin-stat-item__label">Posts</span>
            </div>
            <div class="admin-stat-item">
                <span class="admin-stat-item__value"><?= number_format($dbStats['comments']) ?></span>
                <span class="admin-stat-item__label">Comments</span>
            </div>
            <div class="admin-stat-item">
                <span class="admin-stat-item__value"><?= number_format($dbStats['reports']) ?></span>
                <span class="admin-stat-item__label">Reports</span>
            </div>
            <div class="admin-stat-item">
                <span class="admin-stat-item__value admin-stat-item__value--pending"><?= number_format($dbStats['pending_reports']) ?></span>
                <span class="admin-stat-item__label">Pending Reports</span>
            </div>
        </div>
    </div>
</div>

<!-- ── SERVER INFO ─────────────────────────── -->
<div class="admin-card" style="margin-bottom: 24px;">
    <div class="admin-card__header">
        <h2 class="admin-card__title">Server Information</h2>
    </div>
    <div class="admin-card__body admin-card__body--table">
        <table class="admin-table">
            <tbody>
                <tr>
                    <td style="font-weight:500; color:var(--admin-text);">Web Server</td>
                    <td><?= htmlspecialchars($systemInfo['server']) ?></td>
                </tr>
                <tr>
                    <td style="font-weight:500; color:var(--admin-text);">PHP Version</td>
                    <td><?= htmlspecialchars($systemInfo['php_version']) ?></td>
                </tr>
                <tr>
                    <td style="font-weight:500; color:var(--admin-text);">Database Version</td>
                    <td><?= htmlspecialchars($systemInfo['database']) ?></td>
                </tr>
                <tr>
                    <td style="font-weight:500; color:var(--admin-text);">Platform</td>
                    <td><?= htmlspecialchars($systemInfo['platform']) ?></td>
                </tr>
                <tr>
                    <td style="font-weight:500; color:var(--admin-text);">Environment</td>
                    <td>
                        <span class="admin-badge admin-badge--<?= $systemInfo['app_env'] === 'production' ? 'error' : 'success' ?>">
                            <?= ucfirst(htmlspecialchars($systemInfo['app_env'])) ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td style="font-weight:500; color:var(--admin-text);">Max Upload Size</td>
                    <td><?= ini_get('upload_max_filesize') ?></td>
                </tr>
                <tr>
                    <td style="font-weight:500; color:var(--admin-text);">Memory Limit</td>
                    <td><?= ini_get('memory_limit') ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ── RECENT ADMIN LOGINS ─────────────────── -->
<?php if (!empty($recentLogins)): ?>
<div class="admin-card">
    <div class="admin-card__header">
        <h2 class="admin-card__title">Recent Admin Logins</h2>
    </div>
    <div class="admin-card__body admin-card__body--table">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ADMIN</th>
                    <th>IP ADDRESS</th>
                    <th>STATUS</th>
                    <th>DATE</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentLogins as $login): ?>
                    <tr>
                        <td>
                            <span class="admin-table__user-name"><?= htmlspecialchars(($login['prenom'] ?? '') . ' ' . ($login['nom'] ?? '')) ?></span>
                            <br><span class="admin-table__user-role"><?= htmlspecialchars($login['email'] ?? '') ?></span>
                        </td>
                        <td class="admin-table__time"><?= htmlspecialchars($login['ip_address']) ?></td>
                        <td>
                            <span class="admin-badge admin-badge--<?= $login['status'] === 'success' ? 'success' : 'error' ?>">
                                <?= ucfirst($login['status']) ?>
                            </span>
                        </td>
                        <td class="admin-table__time"><?= date('M d, Y H:i', strtotime($login['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ── HELP SECTION ────────────────────────── -->
<div class="admin-card" style="margin-top: 24px;">
    <div class="admin-card__header">
        <h2 class="admin-card__title">Need Help?</h2>
    </div>
    <div class="admin-card__body">
        <div class="admin-help-grid">
            <div class="admin-help-item">
                <div class="admin-help-item__icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                    </svg>
                </div>
                <h3 class="admin-help-item__title">Documentation</h3>
                <p class="admin-help-item__desc">Consultez la documentation complète de la plateforme StudyLink pour comprendre les fonctionnalités.</p>
            </div>
            <div class="admin-help-item">
                <div class="admin-help-item__icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                </div>
                <h3 class="admin-help-item__title">FAQ</h3>
                <p class="admin-help-item__desc">Réponses aux questions fréquemment posées sur la gestion de la plateforme.</p>
            </div>
            <div class="admin-help-item">
                <div class="admin-help-item__icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
                    </svg>
                </div>
                <h3 class="admin-help-item__title">Contact</h3>
                <p class="admin-help-item__desc">Pour toute assistance technique, contactez l'équipe de développement.</p>
            </div>
        </div>
    </div>
</div>

