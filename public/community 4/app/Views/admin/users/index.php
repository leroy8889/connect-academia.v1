<?php
/** @var array $users */
/** @var array $counts */
/** @var int $total */
/** @var int $page */
/** @var int $totalPages */
/** @var array $filters */
$activePage = 'users';
$headerTitle = 'Users Management';
?>

<!-- ── FILTERS BAR ────────────────────────── -->
<div class="admin-filters">
    <div class="admin-filters__tabs">
        <a href="<?= url('/admin/users') ?>" class="admin-filters__tab <?= empty($filters['role']) && empty($filters['status']) ? 'admin-filters__tab--active' : '' ?>">
            All <span class="admin-filters__tab-count"><?= $counts['all'] ?></span>
        </a>
        <a href="<?= url('/admin/users?role=eleve') ?>" class="admin-filters__tab <?= $filters['role'] === 'eleve' ? 'admin-filters__tab--active' : '' ?>">
            Students <span class="admin-filters__tab-count"><?= $counts['students'] ?></span>
        </a>
        <a href="<?= url('/admin/users?role=enseignant') ?>" class="admin-filters__tab <?= $filters['role'] === 'enseignant' ? 'admin-filters__tab--active' : '' ?>">
            Teachers <span class="admin-filters__tab-count"><?= $counts['teachers'] ?></span>
        </a>
        <a href="<?= url('/admin/users?role=admin') ?>" class="admin-filters__tab <?= $filters['role'] === 'admin' ? 'admin-filters__tab--active' : '' ?>">
            Admins <span class="admin-filters__tab-count"><?= $counts['admins'] ?></span>
        </a>
        <a href="<?= url('/admin/users?status=suspended') ?>" class="admin-filters__tab <?= $filters['status'] === 'suspended' ? 'admin-filters__tab--active' : '' ?>">
            Suspended <span class="admin-filters__tab-count"><?= $counts['suspended'] ?></span>
        </a>
    </div>
    <div class="admin-filters__search">
        <form method="GET" action="<?= url('/admin/users') ?>" class="admin-filters__search-form">
            <?php if ($filters['role']): ?><input type="hidden" name="role" value="<?= htmlspecialchars($filters['role']) ?>"><?php endif; ?>
            <?php if ($filters['status']): ?><input type="hidden" name="status" value="<?= htmlspecialchars($filters['status']) ?>"><?php endif; ?>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
            </svg>
            <input type="text" name="q" value="<?= htmlspecialchars($filters['q']) ?>" placeholder="Search by name or email..." class="admin-filters__search-input">
        </form>
    </div>
</div>

<!-- ── USERS TABLE ────────────────────────── -->
<div class="admin-card admin-card--activity">
    <div class="admin-card__body admin-card__body--table">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>USER</th>
                    <th>ROLE</th>
                    <th>CLASS / SUBJECT</th>
                    <th>STATUS</th>
                    <th>JOINED</th>
                    <th>LAST LOGIN</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $user): ?>
                        <tr id="user-row-<?= $user['id'] ?>">
                            <td>
                                <div class="admin-table__user">
                                    <img src="<?= htmlspecialchars(!empty($user['photo_profil']) ? url($user['photo_profil']) : asset('images/default-avatar.svg')) ?>"
                                         alt="" class="admin-table__avatar">
                                    <div class="admin-table__user-info">
                                        <span class="admin-table__user-name"><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></span>
                                        <span class="admin-table__user-role"><?= htmlspecialchars($user['email']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="admin-badge admin-badge--<?= $user['role'] === 'admin' ? 'info' : ($user['role'] === 'enseignant' ? 'pending' : 'success') ?>">
                                    <?= $user['role'] === 'eleve' ? 'Student' : ($user['role'] === 'enseignant' ? 'Teacher' : 'Admin') ?>
                                </span>
                            </td>
                            <td class="admin-table__time">
                                <?= htmlspecialchars($user['matiere'] ?: ($user['classe'] ?: '—')) ?>
                            </td>
                            <td>
                                <span class="admin-badge admin-badge--<?= $user['is_active'] ? 'success' : 'error' ?>" id="status-badge-<?= $user['id'] ?>">
                                    <?= $user['is_active'] ? 'Active' : 'Suspended' ?>
                                </span>
                            </td>
                            <td class="admin-table__time"><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                            <td class="admin-table__time"><?= $user['last_login'] ? date('M d, Y', strtotime($user['last_login'])) : 'Never' ?></td>
                            <td>
                                <div class="admin-actions">
                                    <button class="admin-actions__btn admin-actions__btn--toggle"
                                            data-user-id="<?= $user['id'] ?>"
                                            data-active="<?= $user['is_active'] ?>"
                                            title="<?= $user['is_active'] ? 'Suspendre' : 'Activer' ?>">
                                        <?php if ($user['is_active']): ?>
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
                                            </svg>
                                        <?php else: ?>
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                                            </svg>
                                        <?php endif; ?>
                                    </button>
                                    <?php if ($user['role'] !== 'admin'): ?>
                                        <button class="admin-actions__btn admin-actions__btn--delete"
                                                data-user-id="<?= $user['id'] ?>"
                                                data-user-name="<?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?>"
                                                title="Supprimer">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                            </svg>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="admin-table__empty">Aucun utilisateur trouvé</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── PAGINATION ──────────────────────────── -->
<?php if ($totalPages > 1): ?>
<div class="admin-pagination">
    <?php
    $queryParams = $filters;
    unset($queryParams['page']);
    $queryParams = array_filter($queryParams, fn($v) => $v !== '');
    $baseQuery = $queryParams ? '&' . http_build_query($queryParams) : '';
    ?>
    <?php if ($page > 1): ?>
        <a href="<?= url('/admin/users?page=' . ($page - 1) . $baseQuery) ?>" class="admin-pagination__btn">&laquo; Prev</a>
    <?php endif; ?>

    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
        <a href="<?= url('/admin/users?page=' . $i . $baseQuery) ?>"
           class="admin-pagination__btn <?= $i === $page ? 'admin-pagination__btn--active' : '' ?>">
            <?= $i ?>
        </a>
    <?php endfor; ?>

    <?php if ($page < $totalPages): ?>
        <a href="<?= url('/admin/users?page=' . ($page + 1) . $baseQuery) ?>" class="admin-pagination__btn">Next &raquo;</a>
    <?php endif; ?>

    <span class="admin-pagination__info"><?= $total ?> utilisateur(s) — Page <?= $page ?>/<?= $totalPages ?></span>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const BASE = window.STUDYLINK_ADMIN?.baseUrl || '';
    const CSRF = window.STUDYLINK_ADMIN?.csrfToken || '';

    // Toggle user status
    document.querySelectorAll('.admin-actions__btn--toggle').forEach(btn => {
        btn.addEventListener('click', async function() {
            const userId = this.dataset.userId;
            try {
                const res = await fetch(BASE + '/admin/api/users/' + userId + '/toggle', {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const data = await res.json();
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Erreur');
                }
            } catch(e) {
                alert('Erreur réseau');
            }
        });
    });

    // Delete user
    document.querySelectorAll('.admin-actions__btn--delete').forEach(btn => {
        btn.addEventListener('click', async function() {
            const userId = this.dataset.userId;
            const userName = this.dataset.userName;
            if (!confirm('Supprimer l\'utilisateur "' + userName + '" ? Cette action est irréversible.')) return;
            try {
                const res = await fetch(BASE + '/admin/api/users/' + userId, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const data = await res.json();
                if (data.success) {
                    const row = document.getElementById('user-row-' + userId);
                    if (row) row.remove();
                } else {
                    alert(data.error || 'Erreur');
                }
            } catch(e) {
                alert('Erreur réseau');
            }
        });
    });
});
</script>

