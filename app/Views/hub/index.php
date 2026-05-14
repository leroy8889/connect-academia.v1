<?php
$userName    = e(\Core\Session::get('user_name', 'utilisateur'));
$hour        = (int) date('H');
$greeting    = $hour < 12 ? 'Bonjour' : ($hour < 18 ? 'Bon après-midi' : 'Bonsoir');
$serie       = $user['serie_id'] ?? null;
$serieLabel  = '';
if ($serie) {
    $serieNames = [1 => 'A1', 2 => 'B', 3 => 'C', 4 => 'D', 5 => 'A2'];
    $serieLabel = $serieNames[$serie] ?? '';
}
$progGlobale = (int) ($stats['progression_globale'] ?? 0);
$annonce     = $annonce ?? false;
?>

<div class="hub">

  <!-- ── HERO ──────────────────────────────────────────────────────────────── -->
  <section class="hub__hero">
    <div class="hub__hero-content">
      <div class="hub__hero-text">
        <p class="hub__hero-greeting"><?= $greeting ?>,</p>
        <h1 class="hub__hero-name"><?= $userName ?></h1>

        <?php if ($serieLabel): ?>
        <span class="hub__serie-badge">Série <?= $serieLabel ?></span>
        <?php endif; ?>

        <?php if ($acces): ?>
          <?php if ($abonnement): ?>
          <p class="hub__hero-sub hub__hero-sub--premium">
            <i data-lucide="star"></i>
            Abonnement actif — accès complet
          </p>
          <?php elseif ($resteGratuit > 0): ?>
          <p class="hub__hero-sub">
            <i data-lucide="clock"></i>
            Période gratuite — il te reste <?= ceil($resteGratuit / 3600) ?>h d'accès
          </p>
          <?php endif; ?>

          <?php if ($progGlobale > 0): ?>
          <div class="hub__hero-progress">
            <div class="hub__hero-progress-bar">
              <div class="hub__hero-progress-fill" style="width:<?= $progGlobale ?>%"></div>
            </div>
            <span class="hub__hero-progress-label"><?= $progGlobale ?>% progression globale</span>
          </div>
          <?php endif; ?>

        <?php else: ?>
        <p class="hub__hero-sub hub__hero-sub--warning">
          <i data-lucide="alert-triangle"></i>
          Période gratuite expirée —
          <a href="<?= url('/abonnement/choisir') ?>">S'abonner pour 2 000 XAF/mois</a>
        </p>
        <?php endif; ?>
      </div>

      <!-- Avatar -->
      <div class="hub__hero-avatar">
        <img src="<?= e(\Core\Session::get('user_photo') ? url(\Core\Session::get('user_photo')) : asset('images/default-avatar.svg')) ?>"
             alt="<?= $userName ?>"
             onerror="this.onerror=null;this.src=<?= htmlspecialchars(json_encode(asset('images/default-avatar.svg')), ENT_QUOTES) ?>">
      </div>
    </div>
  </section>

  <!-- ── STATS ─────────────────────────────────────────────────────────────── -->
  <section class="hub__stats">
    <p class="hub__section-eyebrow">Ton activité</p>
    <div class="hub__stats-grid">

      <div class="hub__stat-card">
        <div class="hub__stat-icon hub__stat-icon--purple">
          <i data-lucide="book-open"></i>
        </div>
        <div class="hub__stat-info">
          <span class="hub__stat-num" id="stat-cours"><?= (int) ($stats['cours_consultes'] ?? 0) ?></span>
          <span class="hub__stat-label">Cours consultés</span>
        </div>
      </div>

      <div class="hub__stat-card">
        <div class="hub__stat-icon hub__stat-icon--green">
          <i data-lucide="check-square"></i>
        </div>
        <div class="hub__stat-info">
          <span class="hub__stat-num" id="stat-termines"><?= (int) ($stats['cours_termines'] ?? 0) ?></span>
          <span class="hub__stat-label">Cours terminés</span>
        </div>
      </div>

      <div class="hub__stat-card">
        <div class="hub__stat-icon hub__stat-icon--blue">
          <i data-lucide="message-square"></i>
        </div>
        <div class="hub__stat-info">
          <span class="hub__stat-num" id="stat-posts"><?= (int) ($stats['posts'] ?? 0) ?></span>
          <span class="hub__stat-label">Publications</span>
        </div>
      </div>

      <div class="hub__stat-card">
        <div class="hub__stat-icon hub__stat-icon--orange">
          <i data-lucide="users"></i>
        </div>
        <div class="hub__stat-info">
          <span class="hub__stat-num" id="stat-abonnes"><?= (int) ($stats['abonnes'] ?? 0) ?></span>
          <span class="hub__stat-label">Abonnés</span>
        </div>
      </div>

    </div>
  </section>

  <!-- ── COURS EN COURS ─────────────────────────────────────────────────────── -->
  <?php if (!empty($enCours)): ?>
  <section class="hub__encours">
    <p class="hub__section-eyebrow">En cours</p>
    <h2 class="hub__section-title">Continuer l'apprentissage</h2>
    <div class="hub__encours-list">
      <?php foreach ($enCours as $cours): ?>
      <a href="<?= url('/apprentissage/viewer/' . (int) $cours['id']) ?>" class="hub__encours-item">
        <div class="hub__encours-meta">
          <span class="hub__encours-matiere"><?= e($cours['matiere']) ?></span>
          <span class="hub__encours-type"><?= e(ucfirst($cours['type'] ?? 'cours')) ?></span>
        </div>
        <p class="hub__encours-titre"><?= e($cours['titre']) ?></p>
        <div class="hub__encours-progress">
          <div class="hub__encours-bar">
            <div class="hub__encours-fill" style="width:<?= (int) $cours['pourcentage'] ?>%"></div>
          </div>
          <span class="hub__encours-pct"><?= (int) $cours['pourcentage'] ?>%</span>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- ── MODULES ────────────────────────────────────────────────────────────── -->
  <section class="hub__modules">
    <p class="hub__section-eyebrow">Plateforme</p>
    <h2 class="hub__section-title">Mes modules</h2>
    <div class="hub__modules-grid">

      <!-- Apprentissage -->
      <a href="<?= url('/apprentissage') ?>"
         class="hub__module-card hub__module-card--apprentissage <?= !$acces ? 'hub__module-card--locked' : '' ?>">
        <div class="hub__module-card__header">
          <div class="hub__module-icon">
            <i data-lucide="book-open"></i>
          </div>
          <?php if (!$acces): ?>
          <span class="hub__module-lock">
            <i data-lucide="lock"></i>
          </span>
          <?php else: ?>
          <span class="hub__module-badge hub__module-badge--active">Accès</span>
          <?php endif; ?>
        </div>
        <h3 class="hub__module-title">Apprentissage</h3>
        <p class="hub__module-desc">Cours, TD et épreuves par série et matière. Assistant IA intégré.</p>
        <div class="hub__module-features">
          <span><i data-lucide="file-text"></i> Cours PDF</span>
          <span><i data-lucide="pen-tool"></i> TD et exercices</span>
          <span><i data-lucide="cpu"></i> IA Gemini</span>
          <span><i data-lucide="bar-chart-2"></i> Progression</span>
        </div>
        <div class="hub__module-cta">
          <?= $acces ? 'Accéder' : "S'abonner" ?>
          <i data-lucide="arrow-right"></i>
        </div>
      </a>

      <!-- Communauté -->
      <a href="<?= url('/communaute') ?>"
         class="hub__module-card hub__module-card--communaute <?= !$acces ? 'hub__module-card--locked' : '' ?>">
        <div class="hub__module-card__header">
          <div class="hub__module-icon">
            <i data-lucide="users"></i>
          </div>
          <?php if (!$acces): ?>
          <span class="hub__module-lock">
            <i data-lucide="lock"></i>
          </span>
          <?php else: ?>
          <span class="hub__module-badge hub__module-badge--active">Accès</span>
          <?php endif; ?>
        </div>
        <h3 class="hub__module-title">Communauté</h3>
        <p class="hub__module-desc">Échangez avec d'autres élèves et étudiants. Posez vos questions, partagez vos astuces.</p>
        <div class="hub__module-features">
          <span><i data-lucide="message-circle"></i> Fil d'actualité</span>
          <span><i data-lucide="help-circle"></i> Questions</span>
          <span><i data-lucide="zap"></i> Chat en direct</span>
          <span><i data-lucide="bell"></i> Notifications</span>
        </div>
        <div class="hub__module-cta">
          <?= $acces ? 'Accéder' : "S'abonner" ?>
          <i data-lucide="arrow-right"></i>
        </div>
      </a>

      <!-- Orientation -->
      <a href="<?= BASE_URL ?>/public/orientation/orientation.html" class="hub__module-card hub__module-card--orientation">
        <div class="hub__module-card__header">
          <div class="hub__module-icon">
            <i data-lucide="compass"></i>
          </div>
          <span class="hub__module-badge hub__module-badge--free">Gratuit</span>
        </div>
        <h3 class="hub__module-title">Orientation</h3>
        <p class="hub__module-desc">Découvrez les universités et filières au Gabon. Guide d'orientation pour votre avenir.</p>
        <div class="hub__module-features">
          <span><i data-lucide="building"></i> Universités</span>
          <span><i data-lucide="graduation-cap"></i> Instituts privés</span>
          <span><i data-lucide="map-pin"></i> Localisations</span>
          <span><i data-lucide="list"></i> Filières</span>
        </div>
        <div class="hub__module-cta">
          Explorer
          <i data-lucide="arrow-right"></i>
        </div>
      </a>

    </div>
  </section>

  <!-- ── BANNER ABONNEMENT (si pas d'accès) ────────────────────────────────── -->
  <?php if (!$acces): ?>
  <section class="hub__pricing">
    <div class="hub__pricing-card">
      <div class="hub__pricing-badge">Déverrouillez l'accès complet</div>
      <h2 class="hub__pricing-title">Choisissez votre plan</h2>
      <p class="hub__pricing-desc">Accès illimité à tous les cours, à la communauté et à l'assistant IA</p>
      <div class="hub__pricing-plans">
        <div class="hub__plan">
          <div class="hub__plan-header">
            <span class="hub__plan-name">Mensuel</span>
          </div>
          <div class="hub__plan-price">
            <span class="hub__plan-amount">2 000</span>
            <span class="hub__plan-currency">XAF/mois</span>
          </div>
          <a href="<?= url('/abonnement/choisir') ?>" class="btn btn--outline btn--full">Choisir</a>
        </div>
        <div class="hub__plan hub__plan--featured">
          <div class="hub__plan-header">
            <span class="hub__plan-name">Annuel</span>
            <span class="hub__plan-save">-37%</span>
          </div>
          <div class="hub__plan-price">
            <span class="hub__plan-amount">15 000</span>
            <span class="hub__plan-currency">XAF/an</span>
          </div>
          <a href="<?= url('/abonnement/choisir') ?>" class="btn btn--primary btn--full">Choisir</a>
        </div>
      </div>
    </div>
  </section>
  <?php endif; ?>

</div><!-- /.hub -->

<?php if ($annonce): ?>
<?php
$aId       = (int) $annonce['id'];
$aTitre    = e($annonce['titre']);
$aContenu  = e($annonce['contenu']);
$aImage    = $annonce['image_url'] ? e($annonce['image_url']) : '';
$aType     = $annonce['type'] ?? 'info';
$aBadge    = $annonce['badge_label'] ? e($annonce['badge_label']) : '';
$aCtaLabel = $annonce['cta_label'] ? e($annonce['cta_label']) : '';
$aCtaUrl   = $annonce['cta_url']   ? e($annonce['cta_url'])   : '';
$aPinned   = (bool) $annonce['is_pinned'];

$typeStyles = [
    'info'    => ['gradient' => 'linear-gradient(135deg,#3B82F6,#6366F1)', 'badge_bg' => 'rgba(99,102,241,.15)', 'badge_color' => '#6366F1', 'label' => 'INFO'],
    'warning' => ['gradient' => 'linear-gradient(135deg,#F59E0B,#EF4444)', 'badge_bg' => 'rgba(245,158,11,.15)',  'badge_color' => '#D97706', 'label' => 'AVERTISSEMENT'],
    'success' => ['gradient' => 'linear-gradient(135deg,#8B52FA,#7C3AED)', 'badge_bg' => 'rgba(139,82,250,.15)', 'badge_color' => '#8B52FA', 'label' => 'SUCCÈS'],
    'urgent'  => ['gradient' => 'linear-gradient(135deg,#8B52FA,#EF4444)', 'badge_bg' => 'rgba(139,82,250,.15)', 'badge_color' => '#8B52FA', 'label' => 'URGENT'],
];
$ts = $typeStyles[$aType] ?? $typeStyles['info'];
?>

<!-- ══ POPUP ANNONCE ═══════════════════════════════════════════════════ -->
<style>
.ca-ann-overlay {
  position: fixed;
  inset: 0;
  background: rgba(8,8,20,.65);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  z-index: 9000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  animation: caAnnFadeIn .35s ease both;
}
@keyframes caAnnFadeIn { from { opacity:0; } to { opacity:1; } }

.ca-ann-popup {
  width: 100%;
  max-width: 480px;
  border-radius: 24px;
  overflow: hidden;
  background: #fff;
  box-shadow: 0 40px 100px rgba(0,0,0,.3), 0 0 0 1px rgba(255,255,255,.08);
  animation: caAnnSlideUp .4s cubic-bezier(.2,.8,.2,1) both;
  position: relative;
}
@keyframes caAnnSlideUp {
  from { opacity:0; transform: translateY(32px) scale(.96); }
  to   { opacity:1; transform: translateY(0) scale(1); }
}

/* Header gradient */
.ca-ann-header {
  background: <?= $ts['gradient'] ?>;
  padding: 28px 24px 24px;
  position: relative;
  min-height: 100px;
  overflow: hidden;
}
.ca-ann-header::before {
  content: '';
  position: absolute;
  right: -30px;
  bottom: -40px;
  width: 200px;
  height: 200px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(255,255,255,.18) 0%, transparent 65%);
}
.ca-ann-header::after {
  content: '';
  position: absolute;
  left: -20px;
  top: -30px;
  width: 140px;
  height: 140px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(255,255,255,.1) 0%, transparent 65%);
}

.ca-ann-close {
  position: absolute;
  top: 14px;
  right: 14px;
  width: 30px;
  height: 30px;
  border-radius: 50%;
  border: none;
  background: rgba(255,255,255,.2);
  color: #fff;
  cursor: pointer;
  display: grid;
  place-items: center;
  z-index: 2;
  transition: background .15s, transform .15s;
  backdrop-filter: blur(4px);
}
.ca-ann-close:hover  { background: rgba(255,255,255,.35); transform: scale(1.1); }
.ca-ann-close svg    { width: 14px; height: 14px; }

.ca-ann-header-badges {
  display: flex;
  gap: 8px;
  margin-bottom: 14px;
  position: relative;
  z-index: 1;
  flex-wrap: wrap;
}
.ca-ann-type-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: 999px;
  background: rgba(255,255,255,.2);
  color: #fff;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: .08em;
  backdrop-filter: blur(4px);
}
.ca-ann-type-badge svg { width: 10px; height: 10px; }
.ca-ann-custom-badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  border-radius: 999px;
  background: rgba(255,255,255,.9);
  color: #1A1A2E;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: .06em;
}

.ca-ann-header-title {
  font-size: 20px;
  font-weight: 800;
  color: #fff;
  line-height: 1.25;
  margin: 0;
  position: relative;
  z-index: 1;
  text-shadow: 0 1px 3px rgba(0,0,0,.15);
}

/* Image */
.ca-ann-image {
  width: 100%;
  height: 180px;
  object-fit: cover;
  display: block;
}

/* Body */
.ca-ann-body {
  padding: 22px 24px 20px;
}
.ca-ann-desc {
  font-size: 14px;
  color: #4A4A4A;
  line-height: 1.65;
  margin: 0 0 20px;
}

/* CTA */
.ca-ann-cta {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  padding: 13px 20px;
  border-radius: 12px;
  border: none;
  background: <?= $ts['gradient'] ?>;
  color: #fff;
  font-size: 14.5px;
  font-weight: 700;
  cursor: pointer;
  text-decoration: none;
  transition: opacity .2s, transform .15s;
  margin-bottom: 12px;
}
.ca-ann-cta:hover { opacity: .9; transform: translateY(-1px); }
.ca-ann-cta svg   { width: 16px; height: 16px; }

/* Footer */
.ca-ann-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px 20px;
  gap: 12px;
}
.ca-ann-dismiss {
  font-size: 12px;
  color: #9E9E9E;
  background: none;
  border: none;
  cursor: pointer;
  padding: 0;
  display: flex;
  align-items: center;
  gap: 5px;
  transition: color .15s;
}
.ca-ann-dismiss:hover { color: #4A4A4A; }
.ca-ann-dismiss svg   { width: 13px; height: 13px; }

.ca-ann-date {
  font-size: 11px;
  color: #C0C0C0;
  display: flex;
  align-items: center;
  gap: 4px;
}
.ca-ann-date svg { width: 11px; height: 11px; }

/* Pinned — pas de fermeture → header différent */
.ca-ann-popup.pinned .ca-ann-close { display: none; }
.ca-ann-popup.pinned .ca-ann-footer { justify-content: center; }

/* Dark mode */
@media (prefers-color-scheme: dark) {
  .ca-ann-popup  { background: #1E1E2E; }
  .ca-ann-desc   { color: #C0C0C0; }
  .ca-ann-footer .ca-ann-dismiss { color: #666; }
  .ca-ann-footer .ca-ann-dismiss:hover { color: #999; }
  .ca-ann-date   { color: #555; }
}
@media (max-width: 520px) {
  .ca-ann-popup { border-radius: 20px; max-width: 100%; }
  .ca-ann-header-title { font-size: 18px; }
  .ca-ann-image { height: 140px; }
}
</style>

<div class="ca-ann-overlay" id="ca-ann-overlay">
  <div class="ca-ann-popup <?= $aPinned ? 'pinned' : '' ?>" role="dialog" aria-modal="true" aria-labelledby="ca-ann-title">

    <!-- Header -->
    <div class="ca-ann-header">
      <?php if (!$aPinned): ?>
      <button class="ca-ann-close" id="ca-ann-close" aria-label="Fermer">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
      <?php endif; ?>

      <div class="ca-ann-header-badges">
        <span class="ca-ann-type-badge">
          <?php if ($aType === 'info'): ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <?php elseif ($aType === 'warning'): ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          <?php elseif ($aType === 'success'): ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          <?php else: ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
          <?php endif; ?>
          <?= $ts['label'] ?>
        </span>
        <?php if ($aBadge): ?>
          <span class="ca-ann-custom-badge"><?= $aBadge ?></span>
        <?php endif; ?>
        <?php if ($aPinned): ?>
          <span class="ca-ann-type-badge">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="17" x2="12" y2="22"/><path d="M5 17h14v-1.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V6h1a2 2 0 0 0 0-4H8a2 2 0 0 0 0 4h1v4.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24V17z"/></svg>
            ÉPINGLÉE
          </span>
        <?php endif; ?>
      </div>

      <h2 class="ca-ann-header-title" id="ca-ann-title"><?= $aTitre ?></h2>
    </div>

    <?php if ($aImage): ?>
      <img src="<?= $aImage ?>" alt="" class="ca-ann-image" loading="lazy"
           onerror="this.style.display='none'">
    <?php endif; ?>

    <!-- Body -->
    <div class="ca-ann-body">
      <p class="ca-ann-desc"><?= nl2br($aContenu) ?></p>

      <?php if ($aCtaLabel && $aCtaUrl): ?>
        <a href="<?= $aCtaUrl ?>" class="ca-ann-cta">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          <?= $aCtaLabel ?>
        </a>
      <?php endif; ?>
    </div>

    <!-- Footer -->
    <div class="ca-ann-footer">
      <?php if (!$aPinned): ?>
      <button class="ca-ann-dismiss" id="ca-ann-dismiss">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
        Ne plus afficher aujourd'hui
      </button>
      <?php else: ?>
      <div></div>
      <?php endif; ?>
      <span class="ca-ann-date">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <?= date('d/m/Y', strtotime($annonce['created_at'])) ?>
      </span>
    </div>

  </div><!-- /.ca-ann-popup -->
</div><!-- /.ca-ann-overlay -->

<script>
(function () {
  const ANNOUNCE_ID = <?= $aId ?>;
  const IS_PINNED   = <?= $aPinned ? 'true' : 'false' ?>;
  const SESSION_KEY = 'ca_ann_dismissed_' + ANNOUNCE_ID;
  const DAILY_KEY   = 'ca_ann_daily_' + ANNOUNCE_ID + '_' + new Date().toISOString().slice(0, 10);

  const overlay = document.getElementById('ca-ann-overlay');
  if (!overlay) return;

  function closeAnn(persist) {
    overlay.style.transition = 'opacity .3s ease';
    overlay.style.opacity    = '0';
    setTimeout(() => overlay.remove(), 320);
    document.body.style.overflow = '';
    if (persist) sessionStorage.setItem(DAILY_KEY, '1');
  }

  // Toujours montrer sauf si dismissed aujourd'hui (non-pinned)
  if (!IS_PINNED && sessionStorage.getItem(DAILY_KEY)) {
    overlay.remove();
    return;
  }

  // Show — bloquer scroll
  document.body.style.overflow = 'hidden';

  // Bouton X
  const closeBtn = document.getElementById('ca-ann-close');
  if (closeBtn) closeBtn.addEventListener('click', () => closeAnn(false));

  // "Ne plus afficher aujourd'hui"
  const dismissBtn = document.getElementById('ca-ann-dismiss');
  if (dismissBtn) dismissBtn.addEventListener('click', () => closeAnn(true));

  // Clic overlay (hors popup) — sauf si pinned
  overlay.addEventListener('click', function (e) {
    if (!IS_PINNED && e.target === overlay) closeAnn(false);
  });

  // Escape — sauf si pinned
  document.addEventListener('keydown', function onEsc(e) {
    if (e.key === 'Escape' && !IS_PINNED) {
      document.removeEventListener('keydown', onEsc);
      closeAnn(false);
    }
  });

})();
</script>
<?php endif; ?>
