<?php
/** @var array $settings */
use Core\Session;
$activePage = 'settings';
$headerTitle = 'Platform Settings';

$flash = Session::getFlash('success');
?>

<?php if ($flash): ?>
    <div class="admin-alert admin-alert--success">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
        </svg>
        <?= htmlspecialchars($flash) ?>
    </div>
<?php endif; ?>

<form method="POST" action="<?= url('/admin/api/settings') ?>" class="admin-settings-form" id="settings-form">
    <input type="hidden" name="_csrf_token" value="<?= Session::getCsrfToken() ?>">

    <!-- ── GENERAL ──────────────────────────── -->
    <div class="admin-card" style="margin-bottom: 24px;">
        <div class="admin-card__header">
            <div class="admin-card__header-left">
                <h2 class="admin-card__title">General Settings</h2>
                <p class="admin-card__subtitle">Basic platform configuration</p>
            </div>
        </div>
        <div class="admin-card__body">
            <div class="admin-form-grid">
                <div class="admin-form-group">
                    <label class="admin-form-label" for="site_name">Site Name</label>
                    <input type="text" name="site_name" id="site_name"
                           value="<?= htmlspecialchars($settings['site_name'] ?? 'StudyLink') ?>"
                           class="admin-form-input" placeholder="StudyLink">
                </div>
                <div class="admin-form-group">
                    <label class="admin-form-label" for="max_upload_mb">Max Upload Size (MB)</label>
                    <input type="number" name="max_upload_mb" id="max_upload_mb"
                           value="<?= htmlspecialchars($settings['max_upload_mb'] ?? '10') ?>"
                           class="admin-form-input" min="1" max="100">
                </div>
                <div class="admin-form-group admin-form-group--full">
                    <label class="admin-form-label" for="site_description">Description</label>
                    <textarea name="site_description" id="site_description" rows="3"
                              class="admin-form-input admin-form-textarea"
                              placeholder="Description de la plateforme..."><?= htmlspecialchars($settings['site_description'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- ── ACADEMIC ─────────────────────────── -->
    <div class="admin-card" style="margin-bottom: 24px;">
        <div class="admin-card__header">
            <div class="admin-card__header-left">
                <h2 class="admin-card__title">Academic Configuration</h2>
                <p class="admin-card__subtitle">Classes and subjects available on the platform</p>
            </div>
        </div>
        <div class="admin-card__body">
            <div class="admin-form-grid">
                <div class="admin-form-group admin-form-group--full">
                    <label class="admin-form-label" for="classes_list">Classes (comma-separated)</label>
                    <input type="text" name="classes_list" id="classes_list"
                           value="<?= htmlspecialchars($settings['classes_list'] ?? '') ?>"
                           class="admin-form-input"
                           placeholder="6ème, 5ème, 4ème, 3ème, Seconde, Première, Terminale">
                    <p class="admin-form-hint">Separate each class with a comma</p>
                </div>
                <div class="admin-form-group admin-form-group--full">
                    <label class="admin-form-label" for="matieres_list">Subjects (comma-separated)</label>
                    <textarea name="matieres_list" id="matieres_list" rows="3"
                              class="admin-form-input admin-form-textarea"
                              placeholder="Mathématiques, Physique-Chimie, SVT, Français..."><?= htmlspecialchars($settings['matieres_list'] ?? '') ?></textarea>
                    <p class="admin-form-hint">Separate each subject with a comma</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ── FEATURES ─────────────────────────── -->
    <div class="admin-card" style="margin-bottom: 24px;">
        <div class="admin-card__header">
            <div class="admin-card__header-left">
                <h2 class="admin-card__title">Feature Toggles</h2>
                <p class="admin-card__subtitle">Enable or disable platform features</p>
            </div>
        </div>
        <div class="admin-card__body">
            <div class="admin-toggle-list">
                <div class="admin-toggle-item">
                    <div class="admin-toggle-item__info">
                        <span class="admin-toggle-item__name">Follow System</span>
                        <span class="admin-toggle-item__desc">Allow users to follow each other</span>
                    </div>
                    <label class="admin-toggle">
                        <input type="hidden" name="enable_follow" value="0">
                        <input type="checkbox" name="enable_follow" value="1" <?= ($settings['enable_follow'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <span class="admin-toggle__slider"></span>
                    </label>
                </div>
                <div class="admin-toggle-item">
                    <div class="admin-toggle-item__info">
                        <span class="admin-toggle-item__name">Direct Messaging</span>
                        <span class="admin-toggle-item__desc">Enable private messaging between users</span>
                    </div>
                    <label class="admin-toggle">
                        <input type="hidden" name="enable_messaging" value="0">
                        <input type="checkbox" name="enable_messaging" value="1" <?= ($settings['enable_messaging'] ?? '0') === '1' ? 'checked' : '' ?>>
                        <span class="admin-toggle__slider"></span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <!-- ── SUBMIT ───────────────────────────── -->
    <div style="display:flex; justify-content:flex-end; gap:12px;">
        <button type="submit" class="admin-header__export-btn" style="margin:0;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
            </svg>
            Save Settings
        </button>
    </div>
</form>

