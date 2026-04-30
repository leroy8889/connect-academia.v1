<?php
/** @var array $reports */
/** @var array $counts */
/** @var int $total */
/** @var int $page */
/** @var int $totalPages */
/** @var string $filterStatus */
$activePage = 'reports';
$headerTitle = 'Reports & Moderation';

$reasonLabels = [
    'inappropriate' => 'Inappropriate',
    'spam'          => 'Spam',
    'harassment'    => 'Harassment',
    'other'         => 'Other',
];
?>

<!-- ── FILTERS ─────────────────────────────── -->
<div class="admin-filters">
    <div class="admin-filters__tabs">
        <a href="<?= url('/admin/reports') ?>" class="admin-filters__tab <?= empty($filterStatus) ? 'admin-filters__tab--active' : '' ?>">
            All <span class="admin-filters__tab-count"><?= $counts['all'] ?></span>
        </a>
        <a href="<?= url('/admin/reports?status=pending') ?>" class="admin-filters__tab <?= $filterStatus === 'pending' ? 'admin-filters__tab--active' : '' ?>">
            <span class="admin-filters__dot admin-filters__dot--pending"></span>
            Pending <span class="admin-filters__tab-count"><?= $counts['pending'] ?></span>
        </a>
        <a href="<?= url('/admin/reports?status=reviewed') ?>" class="admin-filters__tab <?= $filterStatus === 'reviewed' ? 'admin-filters__tab--active' : '' ?>">
            <span class="admin-filters__dot admin-filters__dot--reviewed"></span>
            Reviewed <span class="admin-filters__tab-count"><?= $counts['reviewed'] ?></span>
        </a>
        <a href="<?= url('/admin/reports?status=dismissed') ?>" class="admin-filters__tab <?= $filterStatus === 'dismissed' ? 'admin-filters__tab--active' : '' ?>">
            <span class="admin-filters__dot admin-filters__dot--dismissed"></span>
            Dismissed <span class="admin-filters__tab-count"><?= $counts['dismissed'] ?></span>
        </a>
    </div>
</div>

<!-- ── REPORTS TABLE ──────────────────────── -->
<div class="admin-card admin-card--activity">
    <div class="admin-card__body admin-card__body--table">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>REPORTER</th>
                    <th>REASON</th>
                    <th>REPORTED CONTENT</th>
                    <th>STATUS</th>
                    <th>DATE</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($reports)): ?>
                    <?php foreach ($reports as $report): ?>
                        <tr id="report-row-<?= $report['id'] ?>">
                            <td>
                                <div class="admin-table__user">
                                    <img src="<?= htmlspecialchars(!empty($report['reporter_photo']) ? url($report['reporter_photo']) : asset('images/default-avatar.svg')) ?>"
                                         alt="" class="admin-table__avatar">
                                    <div class="admin-table__user-info">
                                        <span class="admin-table__user-name"><?= htmlspecialchars(($report['reporter_prenom'] ?? '') . ' ' . ($report['reporter_nom'] ?? '')) ?></span>
                                        <span class="admin-table__user-role"><?= $report['reporter_role'] === 'eleve' ? 'Student' : ($report['reporter_role'] === 'enseignant' ? 'Teacher' : 'Admin') ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="admin-badge admin-badge--<?= $report['reason'] === 'harassment' ? 'error' : ($report['reason'] === 'spam' ? 'pending' : 'info') ?>">
                                    <?= $reasonLabels[$report['reason']] ?? $report['reason'] ?>
                                </span>
                            </td>
                            <td>
                                <div class="admin-report-content">
                                    <?php if ($report['post_contenu']): ?>
                                        <p class="admin-report-content__text"><?= htmlspecialchars(mb_substr($report['post_contenu'], 0, 80)) ?><?= mb_strlen($report['post_contenu'] ?? '') > 80 ? '…' : '' ?></p>
                                        <span class="admin-report-content__author">by <?= htmlspecialchars(($report['author_prenom'] ?? '') . ' ' . ($report['author_nom'] ?? '')) ?></span>
                                    <?php elseif ($report['description']): ?>
                                        <p class="admin-report-content__text"><?= htmlspecialchars(mb_substr($report['description'], 0, 80)) ?>…</p>
                                    <?php else: ?>
                                        <span class="admin-report-content__empty">—</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="admin-badge admin-badge--<?= $report['status'] === 'pending' ? 'pending' : ($report['status'] === 'reviewed' ? 'success' : 'info') ?>"
                                      id="report-status-<?= $report['id'] ?>">
                                    <?= ucfirst($report['status']) ?>
                                </span>
                            </td>
                            <td class="admin-table__time"><?= date('M d, Y', strtotime($report['created_at'])) ?></td>
                            <td>
                                <div class="admin-actions">
                                    <?php if ($report['status'] === 'pending'): ?>
                                        <button class="admin-actions__btn admin-actions__btn--review"
                                                data-report-id="<?= $report['id'] ?>"
                                                title="Mark as Reviewed">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                                            </svg>
                                        </button>
                                        <button class="admin-actions__btn admin-actions__btn--dismiss"
                                                data-report-id="<?= $report['id'] ?>"
                                                title="Dismiss">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
                                            </svg>
                                        </button>
                                        <?php if ($report['post_id'] || $report['comment_id']): ?>
                                            <button class="admin-actions__btn admin-actions__btn--delete-content"
                                                    data-report-id="<?= $report['id'] ?>"
                                                    title="Delete reported content">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                                </svg>
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="admin-table__time"><?= $report['admin_note'] ? htmlspecialchars(mb_substr($report['admin_note'], 0, 40)) : '—' ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="admin-table__empty">
                            <?= $filterStatus ? 'Aucun signalement avec le statut "' . htmlspecialchars($filterStatus) . '"' : 'Aucun signalement' ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── PAGINATION ──────────────────────────── -->
<?php if ($totalPages > 1): ?>
<div class="admin-pagination">
    <?php $statusQuery = $filterStatus ? '&status=' . urlencode($filterStatus) : ''; ?>
    <?php if ($page > 1): ?>
        <a href="<?= url('/admin/reports?page=' . ($page - 1) . $statusQuery) ?>" class="admin-pagination__btn">&laquo; Prev</a>
    <?php endif; ?>
    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
        <a href="<?= url('/admin/reports?page=' . $i . $statusQuery) ?>"
           class="admin-pagination__btn <?= $i === $page ? 'admin-pagination__btn--active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?>
        <a href="<?= url('/admin/reports?page=' . ($page + 1) . $statusQuery) ?>" class="admin-pagination__btn">Next &raquo;</a>
    <?php endif; ?>
    <span class="admin-pagination__info"><?= $total ?> signalement(s) — Page <?= $page ?>/<?= $totalPages ?></span>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const BASE = window.STUDYLINK_ADMIN?.baseUrl || '';
    const CSRF = window.STUDYLINK_ADMIN?.csrfToken || '';

    async function updateReport(reportId, status) {
        try {
            const res = await fetch(BASE + '/admin/api/reports/' + reportId, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ status: status })
            });
            const data = await res.json();
            if (data.success) location.reload();
            else alert(data.error || 'Erreur');
        } catch(e) { alert('Erreur réseau'); }
    }

    document.querySelectorAll('.admin-actions__btn--review').forEach(btn => {
        btn.addEventListener('click', () => updateReport(btn.dataset.reportId, 'reviewed'));
    });

    document.querySelectorAll('.admin-actions__btn--dismiss').forEach(btn => {
        btn.addEventListener('click', () => updateReport(btn.dataset.reportId, 'dismissed'));
    });

    document.querySelectorAll('.admin-actions__btn--delete-content').forEach(btn => {
        btn.addEventListener('click', async function() {
            if (!confirm('Supprimer le contenu signalé ? Cette action est irréversible.')) return;
            try {
                const res = await fetch(BASE + '/admin/api/reports/' + this.dataset.reportId + '/content', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const data = await res.json();
                if (data.success) location.reload();
                else alert(data.error || 'Erreur');
            } catch(e) { alert('Erreur réseau'); }
        });
    });
});
</script>

