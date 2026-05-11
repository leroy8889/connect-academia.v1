<?php
$annonces    = $annonces    ?? [];
$totalAll    = $totalAll    ?? 0;
$totalActive = $totalActive ?? 0;

$typeConfig = [
    'info'    => ['label' => 'INFO',          'color' => '#3B82F6', 'bg' => '#EFF6FF', 'icon' => 'info'],
    'warning' => ['label' => 'AVERTISSEMENT', 'color' => '#F59E0B', 'bg' => '#FFFBEB', 'icon' => 'alert-triangle'],
    'success' => ['label' => 'SUCCÈS',        'color' => '#10B981', 'bg' => '#ECFDF5', 'icon' => 'check-circle'],
    'urgent'  => ['label' => 'URGENT',        'color' => '#EF4444', 'bg' => '#FEF2F2', 'icon' => 'zap'],
];
?>

<style>
/* ── Annonces Admin — Design System Connect'Academia ───────────────────── */
.ann-page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 28px;
  flex-wrap: wrap;
}
.ann-page-header h1 {
  font-size: 22px;
  font-weight: 700;
  color: var(--txt);
  margin: 0 0 4px;
}
.ann-page-header p {
  font-size: 13px;
  color: var(--txt-m);
  margin: 0;
}

/* Stats strip */
.ann-stats-strip {
  display: flex;
  gap: 12px;
  margin-bottom: 24px;
  flex-wrap: wrap;
}
.ann-stat-chip {
  display: flex;
  align-items: center;
  gap: 10px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 12px 20px;
  flex: 1;
  min-width: 140px;
}
.ann-stat-chip .ann-stat-icon {
  width: 36px; height: 36px;
  border-radius: 10px;
  display: grid; place-items: center;
  flex-shrink: 0;
}
.ann-stat-chip .ann-stat-icon svg { width: 18px; height: 18px; }
.ann-stat-chip strong { font-size: 22px; font-weight: 800; color: var(--txt); display: block; line-height: 1; }
.ann-stat-chip span   { font-size: 11px; color: var(--txt-m); font-weight: 500; margin-top: 2px; display: block; }

/* Grid des annonces */
.ann-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
  gap: 20px;
}

/* Carte annonce */
.ann-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 16px;
  overflow: hidden;
  transition: box-shadow .2s, transform .2s;
  position: relative;
}
.ann-card:hover {
  box-shadow: 0 8px 32px rgba(139,82,250,.12);
  transform: translateY(-2px);
}
.ann-card.is-inactive { opacity: .55; }

/* Bande couleur type */
.ann-card-stripe {
  height: 4px;
  width: 100%;
}

/* Image preview */
.ann-card-img {
  width: 100%;
  height: 160px;
  object-fit: cover;
  display: block;
  background: var(--bg);
}
.ann-card-img-placeholder {
  width: 100%;
  height: 120px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--bg);
}
.ann-card-img-placeholder svg { width: 32px; height: 32px; color: var(--txt-m); opacity: .4; }

/* Corps */
.ann-card-body { padding: 18px 18px 14px; }
.ann-card-meta {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 10px;
  flex-wrap: wrap;
}
.ann-type-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: .06em;
}
.ann-type-badge svg { width: 11px; height: 11px; }
.ann-custom-badge {
  background: rgba(139,82,250,.12);
  color: #8B52FA;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: .06em;
}
.ann-pinned-badge {
  background: #FFF7ED;
  color: #C2410C;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.ann-pinned-badge svg { width: 10px; height: 10px; }

.ann-card-title {
  font-size: 15px;
  font-weight: 700;
  color: var(--txt);
  margin: 0 0 8px;
  line-height: 1.3;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.ann-card-desc {
  font-size: 12.5px;
  color: var(--txt-m);
  line-height: 1.5;
  margin: 0 0 12px;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* CTA inline */
.ann-cta-preview {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px;
  border-radius: 8px;
  background: rgba(139,82,250,.1);
  color: #8B52FA;
  font-size: 11px;
  font-weight: 600;
  margin-bottom: 12px;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.ann-cta-preview svg { width: 11px; height: 11px; flex-shrink: 0; }

/* Dates */
.ann-dates {
  display: flex;
  gap: 12px;
  font-size: 11px;
  color: var(--txt-m);
  flex-wrap: wrap;
  margin-bottom: 14px;
}
.ann-dates span { display: flex; align-items: center; gap: 4px; }
.ann-dates svg  { width: 11px; height: 11px; }

/* Footer carte */
.ann-card-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 18px 14px;
  border-top: 1px solid var(--border);
  gap: 8px;
}
.ann-status-toggle {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  user-select: none;
}
.ann-toggle-switch {
  width: 36px; height: 20px;
  border-radius: 999px;
  position: relative;
  transition: background .2s;
  flex-shrink: 0;
}
.ann-toggle-switch::after {
  content: '';
  position: absolute;
  width: 14px; height: 14px;
  border-radius: 50%;
  background: #fff;
  top: 3px; left: 3px;
  transition: transform .2s;
  box-shadow: 0 1px 3px rgba(0,0,0,.2);
}
.ann-toggle-switch.on  { background: #8B52FA; }
.ann-toggle-switch.off { background: #D1D5DB; }
.ann-toggle-switch.on::after  { transform: translateX(16px); }
.ann-status-label { font-size: 12px; font-weight: 600; color: var(--txt-m); }
.ann-status-label.active { color: #8B52FA; }

.ann-card-actions { display: flex; gap: 6px; }
.ann-btn-icon {
  width: 32px; height: 32px;
  border-radius: 8px;
  border: 1px solid var(--border);
  background: var(--bg);
  display: grid;
  place-items: center;
  cursor: pointer;
  color: var(--txt-m);
  transition: all .15s;
}
.ann-btn-icon:hover          { background: var(--card); color: var(--txt); border-color: #8B52FA; }
.ann-btn-icon.delete:hover   { background: #FEF2F2; color: #EF4444; border-color: #EF4444; }
.ann-btn-icon svg { width: 14px; height: 14px; }

/* Carte vide */
.ann-empty {
  grid-column: 1 / -1;
  text-align: center;
  padding: 80px 20px;
  color: var(--txt-m);
}
.ann-empty svg { width: 48px; height: 48px; margin: 0 auto 16px; display: block; opacity: .3; }
.ann-empty h3  { font-size: 16px; font-weight: 600; margin: 0 0 8px; color: var(--txt); }
.ann-empty p   { font-size: 13px; margin: 0; }

/* ── MODAL ──────────────────────────────────────────────────────────────── */
.ann-modal-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(10,10,20,.55);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
  z-index: 1000;
  align-items: center;
  justify-content: center;
  padding: 20px;
}
.ann-modal-overlay.open { display: flex; }

.ann-modal {
  background: var(--card);
  border-radius: 20px;
  width: 100%;
  max-width: 640px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 32px 80px rgba(0,0,0,.25);
  animation: annSlideUp .3s cubic-bezier(.2,.8,.2,1) both;
}
@keyframes annSlideUp {
  from { opacity: 0; transform: translateY(24px) scale(.98); }
  to   { opacity: 1; transform: translateY(0) scale(1); }
}

.ann-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 22px 24px 0;
  position: sticky;
  top: 0;
  background: var(--card);
  z-index: 1;
  padding-bottom: 18px;
  border-bottom: 1px solid var(--border);
}
.ann-modal-header h2 {
  font-size: 17px;
  font-weight: 700;
  color: var(--txt);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 10px;
}
.ann-modal-header h2 .modal-icon {
  width: 32px; height: 32px;
  border-radius: 10px;
  background: rgba(139,82,250,.12);
  display: grid; place-items: center;
  color: #8B52FA;
}
.ann-modal-header h2 .modal-icon svg { width: 16px; height: 16px; }
.ann-modal-close {
  width: 32px; height: 32px;
  border-radius: 8px;
  border: 1px solid var(--border);
  background: var(--bg);
  display: grid; place-items: center;
  cursor: pointer;
  color: var(--txt-m);
  flex-shrink: 0;
  transition: all .15s;
}
.ann-modal-close:hover { background: #FEF2F2; color: #EF4444; border-color: #EF4444; }
.ann-modal-close svg { width: 15px; height: 15px; }

.ann-modal-body { padding: 22px 24px; }

/* Form fields */
.ann-form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}
.ann-form-row.full { grid-template-columns: 1fr; }
.ann-field { margin-bottom: 16px; }
.ann-label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: var(--txt);
  margin-bottom: 6px;
}
.ann-label .required { color: #EF4444; margin-left: 2px; }
.ann-input,
.ann-select,
.ann-textarea {
  width: 100%;
  padding: 10px 14px;
  border: 1.5px solid var(--border);
  border-radius: 10px;
  background: #FFFFFF;
  color: var(--txt);
  font-size: 13.5px;
  font-family: inherit;
  outline: none;
  transition: border-color .15s, box-shadow .15s;
  box-sizing: border-box;
}
.ann-input:focus,
.ann-select:focus,
.ann-textarea:focus {
  border-color: #8B52FA;
  box-shadow: 0 0 0 3px rgba(139,82,250,.15);
}
.ann-input.error  { border-color: #EF4444; }
.ann-error-msg    { font-size: 11px; color: #EF4444; margin-top: 4px; display: none; }
.ann-error-msg.visible { display: block; }
.ann-textarea { resize: vertical; min-height: 90px; line-height: 1.5; }

/* Type selector visuel */
.ann-type-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
}
.ann-type-option {
  border: 2px solid var(--border);
  border-radius: 10px;
  padding: 10px 6px;
  text-align: center;
  cursor: pointer;
  transition: all .15s;
  position: relative;
  background: #FFFFFF;
}
.ann-type-option input[type="radio"] { display: none; }
.ann-type-option .type-icon {
  width: 32px; height: 32px;
  border-radius: 8px;
  display: grid; place-items: center;
  margin: 0 auto 6px;
}
.ann-type-option .type-icon svg { width: 16px; height: 16px; }
.ann-type-option .type-name { font-size: 11px; font-weight: 600; }
.ann-type-option.selected { border-color: #8B52FA; background: rgba(139,82,250,.05); }
.ann-type-option.selected::after {
  content: '';
  position: absolute;
  top: 6px; right: 6px;
  width: 8px; height: 8px;
  border-radius: 50%;
  background: #8B52FA;
}

/* Toggles */
.ann-toggle-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 0;
  border-top: 1px solid var(--border);
}
.ann-toggle-row:first-child { border-top: none; padding-top: 0; }
.ann-toggle-info strong { font-size: 13px; font-weight: 600; color: var(--txt); display: block; }
.ann-toggle-info small  { font-size: 11.5px; color: var(--txt-m); }
.ann-switch-btn {
  width: 44px; height: 24px;
  border-radius: 999px;
  border: none;
  position: relative;
  cursor: pointer;
  transition: background .2s;
  flex-shrink: 0;
}
.ann-switch-btn::after {
  content: '';
  position: absolute;
  width: 18px; height: 18px;
  border-radius: 50%;
  background: #fff;
  top: 3px; left: 3px;
  transition: transform .2s;
  box-shadow: 0 1px 4px rgba(0,0,0,.2);
}
.ann-switch-btn.on  { background: #8B52FA; }
.ann-switch-btn.off { background: #D1D5DB; }
.ann-switch-btn.on::after  { transform: translateX(20px); }

/* Image preview dans modal */
.ann-img-preview {
  width: 100%;
  height: 120px;
  border-radius: 10px;
  object-fit: cover;
  border: 1px solid var(--border);
  display: none;
  margin-top: 8px;
}
.ann-img-preview.visible { display: block; }

/* Modal footer */
.ann-modal-footer {
  padding: 16px 24px 22px;
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  border-top: 1px solid var(--border);
  position: sticky;
  bottom: 0;
  background: var(--card);
}
.ann-btn-cancel {
  padding: 10px 20px;
  border-radius: 10px;
  border: 1.5px solid var(--border);
  background: var(--bg);
  color: var(--txt-m);
  font-size: 13.5px;
  font-weight: 600;
  cursor: pointer;
  transition: all .15s;
  font-family: inherit;
}
.ann-btn-cancel:hover { background: var(--card); color: var(--txt); }
.ann-btn-submit {
  padding: 10px 24px;
  border-radius: 10px;
  border: none;
  background: #8B52FA;
  color: #fff;
  font-size: 13.5px;
  font-weight: 700;
  cursor: pointer;
  transition: all .15s;
  font-family: inherit;
  display: flex;
  align-items: center;
  gap: 8px;
}
.ann-btn-submit:hover { background: #9D6EFB; }
.ann-btn-submit:disabled { opacity: .6; cursor: not-allowed; }
.ann-btn-submit svg { width: 15px; height: 15px; }

/* Toast */
.ann-toast {
  position: fixed;
  bottom: 28px;
  right: 28px;
  padding: 12px 18px;
  border-radius: 12px;
  background: #1A1A2E;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
  box-shadow: 0 8px 24px rgba(0,0,0,.25);
  z-index: 9999;
  display: flex;
  align-items: center;
  gap: 10px;
  transform: translateY(80px);
  opacity: 0;
  transition: all .3s cubic-bezier(.2,.8,.2,1);
  pointer-events: none;
}
.ann-toast.show { transform: translateY(0); opacity: 1; }
.ann-toast svg  { width: 16px; height: 16px; flex-shrink: 0; }
.ann-toast.success { border-left: 3px solid #10B981; }
.ann-toast.error   { border-left: 3px solid #EF4444; }

/* Confirm delete overlay */
.ann-confirm-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(10,10,20,.6);
  backdrop-filter: blur(4px);
  z-index: 1100;
  align-items: center;
  justify-content: center;
}
.ann-confirm-overlay.open { display: flex; }
.ann-confirm-box {
  background: var(--card);
  border-radius: 16px;
  padding: 28px;
  max-width: 380px;
  width: 100%;
  text-align: center;
  animation: annSlideUp .25s cubic-bezier(.2,.8,.2,1) both;
}
.ann-confirm-box .confirm-icon {
  width: 52px; height: 52px;
  border-radius: 14px;
  background: #FEF2F2;
  display: grid; place-items: center;
  margin: 0 auto 16px;
  color: #EF4444;
}
.ann-confirm-box .confirm-icon svg { width: 24px; height: 24px; }
.ann-confirm-box h3 { font-size: 16px; font-weight: 700; margin: 0 0 8px; color: var(--txt); }
.ann-confirm-box p  { font-size: 13px; color: var(--txt-m); margin: 0 0 22px; line-height: 1.5; }
.ann-confirm-actions { display: flex; gap: 10px; justify-content: center; }
.ann-btn-danger {
  padding: 10px 22px;
  border-radius: 10px;
  border: none;
  background: #EF4444;
  color: #fff;
  font-size: 13.5px;
  font-weight: 700;
  cursor: pointer;
  font-family: inherit;
  transition: background .15s;
}
.ann-btn-danger:hover { background: #DC2626; }
</style>

<!-- ── HEADER PAGE ──────────────────────────────────────────────────────── -->
<div class="ann-page-header">
  <div>
    <h1>Annonces</h1>
    <p>Créez et gérez les annonces diffusées dans les espaces utilisateurs.</p>
  </div>
  <button class="btn-primary" id="btn-new-annonce">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Nouvelle annonce
  </button>
</div>

<!-- ── STATS STRIP ──────────────────────────────────────────────────────── -->
<div class="ann-stats-strip">
  <div class="ann-stat-chip">
    <div class="ann-stat-icon" style="background:rgba(139,82,250,.1);color:#8B52FA;">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
    </div>
    <div>
      <strong><?= $totalAll ?></strong>
      <span>Total annonces</span>
    </div>
  </div>
  <div class="ann-stat-chip">
    <div class="ann-stat-icon" style="background:rgba(16,185,129,.1);color:#10B981;">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
    </div>
    <div>
      <strong><?= $totalActive ?></strong>
      <span>Actives maintenant</span>
    </div>
  </div>
  <div class="ann-stat-chip">
    <div class="ann-stat-icon" style="background:rgba(245,158,11,.1);color:#F59E0B;">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    </div>
    <div>
      <strong><?= $totalAll - $totalActive ?></strong>
      <span>Inactives / expirées</span>
    </div>
  </div>
</div>

<!-- ── GRID ANNONCES ────────────────────────────────────────────────────── -->
<div class="ann-grid" id="ann-grid">

<?php if (empty($annonces)): ?>
  <div class="ann-empty">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
      <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
      <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
    </svg>
    <h3>Aucune annonce créée</h3>
    <p>Crée ta première annonce pour informer les utilisateurs.</p>
  </div>
<?php else: ?>

<?php foreach ($annonces as $ann):
  $tc       = $typeConfig[$ann['type']] ?? $typeConfig['info'];
  $isActive = (bool)$ann['is_active'];
  $isPinned = (bool)$ann['is_pinned'];
  $dateNow  = new DateTime();
  $isExpired = ($ann['date_fin'] && new DateTime($ann['date_fin']) < $dateNow);
  $notStarted = ($ann['date_debut'] && new DateTime($ann['date_debut']) > $dateNow);
?>
<div class="ann-card <?= !$isActive ? 'is-inactive' : '' ?>" data-id="<?= $ann['id'] ?>">
  <!-- Stripe couleur -->
  <div class="ann-card-stripe" style="background:<?= $tc['color'] ?>;"></div>

  <?php if ($ann['image_url']): ?>
    <img src="<?= e($ann['image_url']) ?>" alt="" class="ann-card-img" onerror="this.style.display='none'">
  <?php else: ?>
    <div class="ann-card-img-placeholder">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
    </div>
  <?php endif; ?>

  <div class="ann-card-body">
    <!-- Badges -->
    <div class="ann-card-meta">
      <span class="ann-type-badge" style="background:<?= $tc['bg'] ?>;color:<?= $tc['color'] ?>;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
          <?php if ($ann['type'] === 'info'): ?>
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
          <?php elseif ($ann['type'] === 'warning'): ?>
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
          <?php elseif ($ann['type'] === 'success'): ?>
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
          <?php else: ?>
            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
          <?php endif; ?>
        </svg>
        <?= $tc['label'] ?>
      </span>
      <?php if ($ann['badge_label']): ?>
        <span class="ann-custom-badge"><?= e($ann['badge_label']) ?></span>
      <?php endif; ?>
      <?php if ($isPinned): ?>
        <span class="ann-pinned-badge">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="17" x2="12" y2="22"/><path d="M5 17h14v-1.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V6h1a2 2 0 0 0 0-4H8a2 2 0 0 0 0 4h1v4.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24V17z"/></svg>
          ÉPINGLÉE
        </span>
      <?php endif; ?>
      <?php if ($isExpired && $isActive): ?>
        <span style="background:#FEF2F2;color:#EF4444;padding:3px 8px;border-radius:999px;font-size:10px;font-weight:700;">EXPIRÉE</span>
      <?php endif; ?>
      <?php if ($notStarted && $isActive): ?>
        <span style="background:#EFF6FF;color:#3B82F6;padding:3px 8px;border-radius:999px;font-size:10px;font-weight:700;">PLANIFIÉE</span>
      <?php endif; ?>
    </div>

    <h3 class="ann-card-title"><?= e($ann['titre']) ?></h3>
    <p class="ann-card-desc"><?= e($ann['contenu']) ?></p>

    <?php if ($ann['cta_label'] && $ann['cta_url']): ?>
    <div class="ann-cta-preview">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
      <?= e($ann['cta_label']) ?> → <?= e($ann['cta_url']) ?>
    </div>
    <?php endif; ?>

    <?php if ($ann['date_debut'] || $ann['date_fin']): ?>
    <div class="ann-dates">
      <?php if ($ann['date_debut']): ?>
      <span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        Début : <?= date('d/m/Y H:i', strtotime($ann['date_debut'])) ?>
      </span>
      <?php endif; ?>
      <?php if ($ann['date_fin']): ?>
      <span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        Fin : <?= date('d/m/Y H:i', strtotime($ann['date_fin'])) ?>
      </span>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Footer -->
  <div class="ann-card-footer">
    <div class="ann-status-toggle" data-toggle-id="<?= $ann['id'] ?>">
      <div class="ann-toggle-switch <?= $isActive ? 'on' : 'off' ?>"></div>
      <span class="ann-status-label <?= $isActive ? 'active' : '' ?>">
        <?= $isActive ? 'Active' : 'Inactive' ?>
      </span>
    </div>
    <div class="ann-card-actions">
      <button class="ann-btn-icon edit-btn" data-id="<?= $ann['id'] ?>" title="Modifier">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
      </button>
      <button class="ann-btn-icon delete delete-btn" data-id="<?= $ann['id'] ?>" data-titre="<?= e($ann['titre']) ?>" title="Supprimer">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
      </button>
    </div>
  </div>
</div>
<?php endforeach; ?>

<?php endif; ?>
</div><!-- /.ann-grid -->


<!-- ══ MODAL CREATE / EDIT ═══════════════════════════════════════════════ -->
<div class="ann-modal-overlay" id="ann-modal-overlay">
  <div class="ann-modal" id="ann-modal">

    <div class="ann-modal-header">
      <h2>
        <span class="modal-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        </span>
        <span id="modal-title-text">Nouvelle annonce</span>
      </h2>
      <button class="ann-modal-close" id="modal-close-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <div class="ann-modal-body">
      <form id="ann-form" novalidate>
        <input type="hidden" id="f-id" value="">

        <!-- Titre -->
        <div class="ann-field">
          <label class="ann-label" for="f-titre">Titre <span class="required">*</span></label>
          <input type="text" id="f-titre" class="ann-input" placeholder="ex: Résultats du BAC 2026 disponibles" maxlength="255">
          <span class="ann-error-msg" id="err-titre"></span>
        </div>

        <!-- Contenu -->
        <div class="ann-field">
          <label class="ann-label" for="f-contenu">Description <span class="required">*</span></label>
          <textarea id="f-contenu" class="ann-textarea" placeholder="Décris l'annonce en quelques phrases claires et utiles pour les utilisateurs…" maxlength="1000"></textarea>
          <span class="ann-error-msg" id="err-contenu"></span>
        </div>

        <!-- Type -->
        <div class="ann-field">
          <label class="ann-label">Type d'annonce <span class="required">*</span></label>
          <div class="ann-type-grid">
            <label class="ann-type-option selected" data-type="info">
              <input type="radio" name="ann_type" value="info" checked>
              <div class="type-icon" style="background:#EFF6FF;color:#3B82F6;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              </div>
              <div class="type-name" style="color:#3B82F6;">INFO</div>
            </label>
            <label class="ann-type-option" data-type="warning">
              <input type="radio" name="ann_type" value="warning">
              <div class="type-icon" style="background:#FFFBEB;color:#F59E0B;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
              </div>
              <div class="type-name" style="color:#F59E0B;">WARN</div>
            </label>
            <label class="ann-type-option" data-type="success">
              <input type="radio" name="ann_type" value="success">
              <div class="type-icon" style="background:#ECFDF5;color:#10B981;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
              </div>
              <div class="type-name" style="color:#10B981;">SUCCÈS</div>
            </label>
            <label class="ann-type-option" data-type="urgent">
              <input type="radio" name="ann_type" value="urgent">
              <div class="type-icon" style="background:#FEF2F2;color:#EF4444;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
              </div>
              <div class="type-name" style="color:#EF4444;">URGENT</div>
            </label>
          </div>
        </div>

        <!-- Badge label + Image URL -->
        <div class="ann-form-row">
          <div class="ann-field" style="margin-bottom:0;">
            <label class="ann-label" for="f-badge">Badge personnalisé</label>
            <input type="text" id="f-badge" class="ann-input" placeholder="ex: BAC 2026, NOUVEAU" maxlength="60">
          </div>
          <div class="ann-field" style="margin-bottom:0;">
            <label class="ann-label" for="f-image">URL image (optionnel)</label>
            <input type="url" id="f-image" class="ann-input" placeholder="https://…" maxlength="600">
          </div>
        </div>
        <img id="f-image-preview" class="ann-img-preview" src="" alt="Aperçu">

        <!-- CTA -->
        <div class="ann-form-row" style="margin-top:16px;">
          <div class="ann-field" style="margin-bottom:0;">
            <label class="ann-label" for="f-cta-label">Texte du bouton CTA</label>
            <input type="text" id="f-cta-label" class="ann-input" placeholder="ex: Voir les résultats" maxlength="120">
          </div>
          <div class="ann-field" style="margin-bottom:0;">
            <label class="ann-label" for="f-cta-url">Lien du bouton CTA</label>
            <input type="text" id="f-cta-url" class="ann-input" placeholder="ex: /apprentissage">
          </div>
        </div>

        <!-- Dates -->
        <div class="ann-form-row" style="margin-top:16px;">
          <div class="ann-field" style="margin-bottom:0;">
            <label class="ann-label" for="f-date-debut">Début de diffusion</label>
            <input type="datetime-local" id="f-date-debut" class="ann-input">
          </div>
          <div class="ann-field" style="margin-bottom:0;">
            <label class="ann-label" for="f-date-fin">Fin de diffusion</label>
            <input type="datetime-local" id="f-date-fin" class="ann-input">
            <span class="ann-error-msg" id="err-date-fin"></span>
          </div>
        </div>

        <!-- Toggles -->
        <div style="margin-top:20px;">
          <div class="ann-toggle-row">
            <div class="ann-toggle-info">
              <strong>Annonce active</strong>
              <small>Affichée immédiatement dans le Hub utilisateur.</small>
            </div>
            <button type="button" class="ann-switch-btn on" id="f-is-active" data-state="1"></button>
          </div>
          <div class="ann-toggle-row">
            <div class="ann-toggle-info">
              <strong>Épingler (non fermable)</strong>
              <small>L'utilisateur ne peut pas fermer le popup — à utiliser pour les urgences.</small>
            </div>
            <button type="button" class="ann-switch-btn off" id="f-is-pinned" data-state="0"></button>
          </div>
        </div>

      </form>
    </div><!-- /.ann-modal-body -->

    <div class="ann-modal-footer">
      <button class="ann-btn-cancel" id="modal-cancel-btn">Annuler</button>
      <button class="ann-btn-submit" id="modal-submit-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
        <span id="submit-label">Créer l'annonce</span>
      </button>
    </div>

  </div><!-- /.ann-modal -->
</div><!-- /.ann-modal-overlay -->


<!-- ══ CONFIRM DELETE ════════════════════════════════════════════════════ -->
<div class="ann-confirm-overlay" id="ann-confirm-overlay">
  <div class="ann-confirm-box">
    <div class="confirm-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
    </div>
    <h3>Supprimer l'annonce ?</h3>
    <p id="confirm-text">Cette action est irréversible.</p>
    <div class="ann-confirm-actions">
      <button class="ann-btn-cancel" id="confirm-cancel-btn">Annuler</button>
      <button class="ann-btn-danger" id="confirm-delete-btn">Supprimer</button>
    </div>
  </div>
</div>

<!-- Toast -->
<div class="ann-toast" id="ann-toast">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
  <span id="ann-toast-msg">Action effectuée.</span>
</div>


<script>
(function () {
  'use strict';

  const BASE    = window.CA_ADMIN?.baseUrl ?? '';
  const CSRF    = window.CA_ADMIN?.csrfToken ?? '';

  // ── DOM refs ──────────────────────────────────────────────────────────
  const overlay        = document.getElementById('ann-modal-overlay');
  const modalTitleText = document.getElementById('modal-title-text');
  const submitLabel    = document.getElementById('submit-label');
  const submitBtn      = document.getElementById('modal-submit-btn');
  const grid           = document.getElementById('ann-grid');

  const fId         = document.getElementById('f-id');
  const fTitre      = document.getElementById('f-titre');
  const fContenu    = document.getElementById('f-contenu');
  const fBadge      = document.getElementById('f-badge');
  const fImage      = document.getElementById('f-image');
  const fImgPreview = document.getElementById('f-image-preview');
  const fCtaLabel   = document.getElementById('f-cta-label');
  const fCtaUrl     = document.getElementById('f-cta-url');
  const fDateDebut  = document.getElementById('f-date-debut');
  const fDateFin    = document.getElementById('f-date-fin');
  const fIsActive   = document.getElementById('f-is-active');
  const fIsPinned   = document.getElementById('f-is-pinned');

  const typeOptions    = document.querySelectorAll('.ann-type-option');
  const confirmOverlay = document.getElementById('ann-confirm-overlay');
  const confirmText    = document.getElementById('confirm-text');
  const confirmDelBtn  = document.getElementById('confirm-delete-btn');

  let deleteTargetId = null;

  // ── Toast ─────────────────────────────────────────────────────────────
  function showToast(msg, type = 'success') {
    const toast = document.getElementById('ann-toast');
    const msgEl = document.getElementById('ann-toast-msg');
    toast.className = 'ann-toast ' + type;
    msgEl.textContent = msg;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3200);
  }

  // ── Modal open/close ──────────────────────────────────────────────────
  function openModal(mode = 'create', data = null) {
    clearErrors();
    fId.value = '';

    if (mode === 'create') {
      modalTitleText.textContent = 'Nouvelle annonce';
      submitLabel.textContent    = 'Créer l\'annonce';
      resetForm();
    } else {
      modalTitleText.textContent = 'Modifier l\'annonce';
      submitLabel.textContent    = 'Enregistrer';
      fillForm(data);
    }

    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
    setTimeout(() => fTitre.focus(), 100);
  }

  function closeModal() {
    overlay.classList.remove('open');
    document.body.style.overflow = '';
  }

  function resetForm() {
    fTitre.value     = '';
    fContenu.value   = '';
    fBadge.value     = '';
    fImage.value     = '';
    fCtaLabel.value  = '';
    fCtaUrl.value    = '';
    fDateDebut.value = '';
    fDateFin.value   = '';
    fImgPreview.classList.remove('visible');
    fImgPreview.src  = '';
    setToggle(fIsActive, true);
    setToggle(fIsPinned, false);
    typeOptions.forEach(o => {
      o.classList.toggle('selected', o.dataset.type === 'info');
      const radio = o.querySelector('input[type="radio"]');
      if (radio) radio.checked = (o.dataset.type === 'info');
    });
  }

  function fillForm(d) {
    fId.value        = d.id;
    fTitre.value     = d.titre    ?? '';
    fContenu.value   = d.contenu  ?? '';
    fBadge.value     = d.badge_label ?? '';
    fImage.value     = d.image_url   ?? '';
    fCtaLabel.value  = d.cta_label   ?? '';
    fCtaUrl.value    = d.cta_url     ?? '';
    fDateDebut.value = d.date_debut ? d.date_debut.slice(0,16) : '';
    fDateFin.value   = d.date_fin   ? d.date_fin.slice(0,16)   : '';
    setToggle(fIsActive, !!parseInt(d.is_active));
    setToggle(fIsPinned, !!parseInt(d.is_pinned));

    const type = d.type ?? 'info';
    typeOptions.forEach(o => {
      o.classList.toggle('selected', o.dataset.type === type);
      const r = o.querySelector('input[type="radio"]');
      if (r) r.checked = (o.dataset.type === type);
    });

    if (d.image_url) {
      fImgPreview.src = d.image_url;
      fImgPreview.classList.add('visible');
    } else {
      fImgPreview.classList.remove('visible');
      fImgPreview.src = '';
    }
  }

  // ── Toggles switch ────────────────────────────────────────────────────
  function setToggle(btn, state) {
    btn.dataset.state = state ? '1' : '0';
    btn.classList.toggle('on', state);
    btn.classList.toggle('off', !state);
  }

  fIsActive.addEventListener('click', () => {
    const cur = fIsActive.dataset.state === '1';
    setToggle(fIsActive, !cur);
  });
  fIsPinned.addEventListener('click', () => {
    const cur = fIsPinned.dataset.state === '1';
    setToggle(fIsPinned, !cur);
  });

  // ── Type selector ─────────────────────────────────────────────────────
  typeOptions.forEach(opt => {
    opt.addEventListener('click', () => {
      typeOptions.forEach(o => o.classList.remove('selected'));
      opt.classList.add('selected');
      const radio = opt.querySelector('input[type="radio"]');
      if (radio) radio.checked = true;
    });
  });

  // ── Image preview ─────────────────────────────────────────────────────
  fImage.addEventListener('input', () => {
    const val = fImage.value.trim();
    if (val) {
      fImgPreview.src = val;
      fImgPreview.classList.add('visible');
    } else {
      fImgPreview.classList.remove('visible');
      fImgPreview.src = '';
    }
  });

  // ── Errors ────────────────────────────────────────────────────────────
  function clearErrors() {
    document.querySelectorAll('.ann-error-msg').forEach(el => {
      el.textContent = '';
      el.classList.remove('visible');
    });
    document.querySelectorAll('.ann-input, .ann-textarea').forEach(el => el.classList.remove('error'));
  }

  function showErrors(errors) {
    const map = { titre: 'err-titre', contenu: 'err-contenu', date_fin: 'err-date-fin' };
    Object.entries(errors).forEach(([field, msg]) => {
      const errEl = document.getElementById(map[field]);
      const inEl  = document.getElementById('f-' + field.replace('_', '-'));
      if (errEl) { errEl.textContent = msg; errEl.classList.add('visible'); }
      if (inEl)  { inEl.classList.add('error'); }
    });
  }

  // ── Submit form ───────────────────────────────────────────────────────
  document.getElementById('ann-form').addEventListener('submit', e => e.preventDefault());

  submitBtn.addEventListener('click', async () => {
    clearErrors();
    submitBtn.disabled = true;

    const id  = fId.value;
    const selectedType = document.querySelector('input[name="ann_type"]:checked')?.value ?? 'info';

    const payload = {
      titre:       fTitre.value.trim(),
      contenu:     fContenu.value.trim(),
      image_url:   fImage.value.trim(),
      type:        selectedType,
      badge_label: fBadge.value.trim(),
      cta_label:   fCtaLabel.value.trim(),
      cta_url:     fCtaUrl.value.trim(),
      date_debut:  fDateDebut.value || null,
      date_fin:    fDateFin.value   || null,
      is_active:   parseInt(fIsActive.dataset.state),
      is_pinned:   parseInt(fIsPinned.dataset.state),
    };

    const url    = id ? `${BASE}/admin/api/annonces/${id}` : `${BASE}/admin/api/annonces`;
    const method = id ? 'PATCH' : 'POST';

    try {
      const res = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify(payload),
      });
      const json = await res.json();

      if (!res.ok || !json.success) {
        if (json.errors) showErrors(json.errors);
        else showToast(json.message ?? 'Erreur serveur.', 'error');
        return;
      }

      closeModal();
      showToast(id ? 'Annonce modifiée !' : 'Annonce créée !', 'success');
      setTimeout(() => location.reload(), 800);

    } catch (err) {
      showToast('Erreur réseau.', 'error');
    } finally {
      submitBtn.disabled = false;
    }
  });

  // ── Open modal new ────────────────────────────────────────────────────
  document.getElementById('btn-new-annonce').addEventListener('click', () => openModal('create'));
  document.getElementById('modal-close-btn').addEventListener('click', closeModal);
  document.getElementById('modal-cancel-btn').addEventListener('click', closeModal);
  overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(); });

  // ── Edit buttons ──────────────────────────────────────────────────────
  document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
      const id = btn.dataset.id;
      try {
        const res  = await fetch(`${BASE}/admin/api/annonces/${id}`, {
          headers: { 'X-CSRF-Token': CSRF }
        });
        const json = await res.json();
        if (json.success && json.annonce) openModal('edit', json.annonce);
      } catch { showToast('Impossible de charger l\'annonce.', 'error'); }
    });
  });

  // ── Toggle actif (sur les cartes) ─────────────────────────────────────
  document.querySelectorAll('.ann-status-toggle').forEach(toggleEl => {
    toggleEl.addEventListener('click', async () => {
      const id = toggleEl.dataset.toggleId;
      try {
        const res  = await fetch(`${BASE}/admin/api/annonces/${id}/toggle`, {
          method: 'PATCH',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        });
        const json = await res.json();
        if (json.success) {
          const isNowActive = !!parseInt(json.annonce.is_active);
          const sw = toggleEl.querySelector('.ann-toggle-switch');
          const lb = toggleEl.querySelector('.ann-status-label');
          sw.classList.toggle('on',  isNowActive);
          sw.classList.toggle('off', !isNowActive);
          lb.textContent = isNowActive ? 'Active' : 'Inactive';
          lb.classList.toggle('active', isNowActive);
          const card = toggleEl.closest('.ann-card');
          card.classList.toggle('is-inactive', !isNowActive);
          showToast(isNowActive ? 'Annonce activée.' : 'Annonce désactivée.');
        }
      } catch { showToast('Erreur lors du changement de statut.', 'error'); }
    });
  });

  // ── Delete ────────────────────────────────────────────────────────────
  document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      deleteTargetId = btn.dataset.id;
      confirmText.textContent = `Supprimer "${btn.dataset.titre}" ? Cette action est irréversible.`;
      confirmOverlay.classList.add('open');
    });
  });

  document.getElementById('confirm-cancel-btn').addEventListener('click', () => {
    confirmOverlay.classList.remove('open');
    deleteTargetId = null;
  });
  confirmOverlay.addEventListener('click', e => {
    if (e.target === confirmOverlay) { confirmOverlay.classList.remove('open'); deleteTargetId = null; }
  });

  confirmDelBtn.addEventListener('click', async () => {
    if (!deleteTargetId) return;
    confirmDelBtn.disabled = true;
    try {
      const res  = await fetch(`${BASE}/admin/api/annonces/${deleteTargetId}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-Token': CSRF },
      });
      const json = await res.json();
      if (json.success) {
        const card = document.querySelector(`.ann-card[data-id="${deleteTargetId}"]`);
        if (card) {
          card.style.transition = 'opacity .3s, transform .3s';
          card.style.opacity = '0';
          card.style.transform = 'scale(.95)';
          setTimeout(() => { card.remove(); checkEmpty(); }, 320);
        }
        confirmOverlay.classList.remove('open');
        showToast('Annonce supprimée.', 'success');
      } else {
        showToast(json.message ?? 'Erreur.', 'error');
      }
    } catch { showToast('Erreur réseau.', 'error'); }
    finally { confirmDelBtn.disabled = false; deleteTargetId = null; }
  });

  function checkEmpty() {
    const cards = grid.querySelectorAll('.ann-card');
    if (cards.length === 0) {
      grid.innerHTML = `
        <div class="ann-empty">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
          </svg>
          <h3>Aucune annonce créée</h3>
          <p>Crée ta première annonce pour informer les utilisateurs.</p>
        </div>`;
    }
  }

  // ── Escape key ────────────────────────────────────────────────────────
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      if (overlay.classList.contains('open')) closeModal();
      if (confirmOverlay.classList.contains('open')) {
        confirmOverlay.classList.remove('open');
        deleteTargetId = null;
      }
    }
  });

})();
</script>
