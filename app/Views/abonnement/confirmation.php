<?php
$abonnement    = $abonnement ?? null;
$user          = $user ?? [];
$prenom        = htmlspecialchars($user['prenom'] ?? '', ENT_QUOTES);
$planLabel     = ($abonnement['plan'] ?? 'mensuel') === 'mensuel' ? 'Plan Découverte' : 'CONNECT+';
$debutFr       = $abonnement ? date('d/m/Y', strtotime($abonnement['debut'] ?? 'now')) : date('d/m/Y');
$finFr         = $abonnement ? date('d/m/Y', strtotime($abonnement['fin'] ?? '+30 days')) : date('d/m/Y', strtotime('+30 days'));
$joursRestants = $abonnement ? max(0, (int) ceil((strtotime($abonnement['fin']) - time()) / 86400)) : 30;

$totalDays = 30;
if ($abonnement && !empty($abonnement['debut']) && !empty($abonnement['fin'])) {
    $span = strtotime($abonnement['fin']) - strtotime($abonnement['debut']);
    if ($span > 0) $totalDays = (int) ceil($span / 86400);
}
$progressPct = min(100, max(0, (int) round(($joursRestants / $totalDays) * 100)));
?>

<style>
/* ── Confirmation — Connect'Academia ── */

@keyframes confettiFall {
  0%   { transform: translateY(0) translateX(0) rotate(0deg); opacity: 1; }
  100% { transform: translateY(110vh) translateX(var(--drift, 0px)) rotate(660deg); opacity: 0; }
}
@keyframes ringPulse {
  0%   { transform: scale(0.92); opacity: 0.65; }
  50%  { transform: scale(1.06); opacity: 0.25; }
  100% { transform: scale(0.92); opacity: 0.65; }
}
@keyframes popIn {
  from { transform: scale(0) rotate(-12deg); opacity: 0; }
  to   { transform: scale(1) rotate(0deg);   opacity: 1; }
}
@keyframes slideUp {
  from { opacity: 0; transform: translateY(22px); }
  to   { opacity: 1; transform: translateY(0); }
}
@keyframes fadeIn {
  from { opacity: 0; }
  to   { opacity: 1; }
}

/* ── Page ── */
.confirm-page {
  min-height: calc(100vh - var(--navbar-h, 64px));
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-10, 40px) var(--space-5, 20px);
  background: var(--bg-surface, #F5F5F7);
  background-image: radial-gradient(circle, rgba(139,82,250,0.06) 1px, transparent 1px);
  background-size: 28px 28px;
  position: relative;
}

.confetti-layer {
  position: fixed;
  inset: 0;
  pointer-events: none;
  overflow: hidden;
  z-index: 0;
}

.confirm-wrapper {
  width: 100%;
  max-width: 860px;
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  gap: var(--space-4, 16px);
}

/* ── Card split ── */
.confirm-card {
  display: grid;
  grid-template-columns: 1fr 1fr;
  border-radius: var(--radius-xl, 24px);
  overflow: hidden;
  box-shadow:
    0 24px 64px rgba(0,0,0,0.10),
    0 4px 16px rgba(139,82,250,0.14);
  animation: slideUp 420ms cubic-bezier(0.2,0.8,0.2,1) both;
}

/* ── Hero (gauche) ── */
.confirm-hero {
  background: linear-gradient(155deg,
    var(--purple-neon, #8B52FA) 0%,
    var(--purple-press, #7440D9) 68%,
    #5C2EBF 100%
  );
  padding: 52px 40px 48px;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  position: relative;
  overflow: hidden;
}

/* Orbes décoratifs */
.confirm-hero::before {
  content: '';
  position: absolute;
  bottom: -70px; right: -50px;
  width: 260px; height: 260px;
  background: radial-gradient(circle, rgba(255,255,255,0.09) 0%, transparent 65%);
  border-radius: 50%;
  pointer-events: none;
}
.confirm-hero::after {
  content: '';
  position: absolute;
  top: -60px; left: -40px;
  width: 200px; height: 200px;
  background: radial-gradient(circle, rgba(255,255,255,0.07) 0%, transparent 65%);
  border-radius: 50%;
  pointer-events: none;
}

.confirm-hero-eyebrow {
  font-family: var(--font-sans, 'Montserrat', sans-serif);
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: rgba(255,255,255,0.55);
  margin: 0 0 28px 0;
  animation: fadeIn 300ms 100ms both;
}

/* Rings orbitaux */
.confirm-ring-wrap {
  position: relative;
  width: 96px;
  height: 96px;
  margin: 0 0 28px 24px;
  animation: slideUp 500ms cubic-bezier(0.2,0.8,0.2,1) 150ms both;
}

.confirm-ring {
  position: absolute;
  border-radius: 50%;
  animation: ringPulse 3.2s cubic-bezier(0.2,0.8,0.2,1) infinite;
}
.confirm-ring-1 {
  inset: 0;
  border: 1.5px solid rgba(255,255,255,0.30);
  animation-delay: 0s;
}
.confirm-ring-2 {
  inset: -16px;
  border: 1.5px solid rgba(255,255,255,0.15);
  animation-delay: 0.55s;
}
.confirm-ring-3 {
  inset: -32px;
  border: 1.5px solid rgba(255,255,255,0.06);
  animation-delay: 1.1s;
}

.confirm-check-circle {
  position: absolute;
  inset: 0;
  background: rgba(255,255,255,0.16);
  border: 2px solid rgba(255,255,255,0.38);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
  animation: popIn 0.5s cubic-bezier(0.2,0.8,0.2,1) 0.3s both;
}

.confirm-hero-title {
  font-family: var(--font-sans, 'Montserrat', sans-serif);
  font-size: 2rem;
  font-weight: 800;
  line-height: 1.2;
  color: #fff;
  margin: 0 0 12px 0;
  animation: slideUp 400ms cubic-bezier(0.2,0.8,0.2,1) 260ms both;
}

.confirm-hero-tagline {
  font-family: var(--font-sans, 'Montserrat', sans-serif);
  font-size: 14px;
  color: rgba(255,255,255,0.72);
  line-height: 1.55;
  margin: 0 0 28px 0;
  animation: slideUp 400ms cubic-bezier(0.2,0.8,0.2,1) 330ms both;
}

.confirm-plan-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(255,255,255,0.16);
  border: 1px solid rgba(255,255,255,0.28);
  color: #fff;
  font-family: var(--font-sans, 'Montserrat', sans-serif);
  font-size: 12px;
  font-weight: 700;
  padding: 7px 16px;
  border-radius: var(--radius-pill, 999px);
  letter-spacing: 0.03em;
  animation: slideUp 400ms cubic-bezier(0.2,0.8,0.2,1) 400ms both;
}

/* ── Panel détails (droite) ── */
.confirm-details-panel {
  background: var(--bg-main, #ffffff);
  padding: 52px 44px 48px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 20px;
  animation: slideUp 420ms cubic-bezier(0.2,0.8,0.2,1) 80ms both;
}

.confirm-panel-label {
  font-family: var(--font-sans, 'Montserrat', sans-serif);
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--text-muted, #9E9E9E);
  padding-bottom: 16px;
  border-bottom: 1px solid var(--border-subtle, rgba(26,26,26,0.08));
}

/* Lignes receipt */
.confirm-receipt {
  display: flex;
  flex-direction: column;
  gap: 13px;
}

.confirm-receipt-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-family: var(--font-sans, 'Montserrat', sans-serif);
  font-size: 14px;
}

.confirm-receipt-left {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--text-secondary, #4A4A4A);
}

.confirm-receipt-left svg,
.confirm-receipt-left [data-lucide] {
  color: var(--text-muted, #9E9E9E);
  flex-shrink: 0;
}

.confirm-receipt-val {
  font-weight: 600;
  color: var(--text-primary, #1A1A1A);
}

.confirm-days-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: rgba(0,200,83,0.10);
  color: #006B2B;
  font-weight: 700;
  font-size: 12px;
  padding: 4px 11px;
  border-radius: var(--radius-pill, 999px);
  border: 1px solid rgba(0,200,83,0.22);
}

/* Progress */
.confirm-progress-block {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.confirm-progress-bar {
  height: 5px;
  background: var(--border-subtle, rgba(26,26,26,0.08));
  border-radius: var(--radius-pill, 999px);
  overflow: hidden;
}

.confirm-progress-fill {
  height: 100%;
  background: var(--purple-neon, #8B52FA);
  border-radius: var(--radius-pill, 999px);
  width: 0%;
  transition: width 700ms cubic-bezier(0.2,0.8,0.2,1);
}

.confirm-progress-meta {
  display: flex;
  justify-content: space-between;
  font-family: var(--font-sans, 'Montserrat', sans-serif);
  font-size: 11px;
  color: var(--text-muted, #9E9E9E);
}

/* Séparateur pointillé */
.confirm-dashed {
  height: 1px;
  background: repeating-linear-gradient(
    90deg,
    var(--border-subtle, rgba(26,26,26,0.10)) 0,
    var(--border-subtle, rgba(26,26,26,0.10)) 6px,
    transparent 6px,
    transparent 12px
  );
}

/* CTA group */
.confirm-cta-group {
  display: flex;
  flex-direction: column;
  gap: 9px;
}

.btn-confirm-hub {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  padding: 14px 24px;
  background: var(--purple-neon, #8B52FA);
  color: #fff;
  border: none;
  border-radius: var(--radius-pill, 999px);
  font-family: var(--font-sans, 'Montserrat', sans-serif);
  font-weight: 700;
  font-size: 15px;
  cursor: pointer;
  text-decoration: none;
  transition: background 200ms cubic-bezier(0.2,0.8,0.2,1), transform 120ms, box-shadow 200ms;
  box-shadow: 0 6px 22px rgba(139,82,250,0.38);
}
.btn-confirm-hub:hover {
  background: var(--purple-hover, #9D6EFB);
  transform: translateY(-1px);
  box-shadow: 0 10px 30px rgba(139,82,250,0.45);
  text-decoration: none;
  color: #fff;
}
.btn-confirm-hub:active {
  transform: scale(0.98);
  background: var(--purple-press, #7440D9);
}

.btn-confirm-home {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  width: 100%;
  padding: 12px 24px;
  background: transparent;
  color: var(--text-secondary, #4A4A4A);
  border: 1px solid var(--border-subtle, rgba(26,26,26,0.10));
  border-radius: var(--radius-pill, 999px);
  font-family: var(--font-sans, 'Montserrat', sans-serif);
  font-weight: 500;
  font-size: 14px;
  cursor: pointer;
  text-decoration: none;
  transition: all 200ms cubic-bezier(0.2,0.8,0.2,1);
}
.btn-confirm-home:hover {
  background: var(--bg-surface, #F5F5F7);
  border-color: var(--border-strong, rgba(26,26,26,0.16));
  color: var(--text-primary, #1A1A1A);
  text-decoration: none;
}

/* ── Tuiles fonctionnalités ── */
.confirm-features {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--space-4, 16px);
  animation: slideUp 420ms cubic-bezier(0.2,0.8,0.2,1) 580ms both;
}

.confirm-feature {
  background: var(--bg-main, #fff);
  border-radius: var(--radius-lg, 16px);
  padding: 16px 14px;
  display: flex;
  align-items: center;
  gap: 12px;
  border: 1px solid var(--border-subtle, rgba(26,26,26,0.08));
  transition: transform 200ms cubic-bezier(0.2,0.8,0.2,1), box-shadow 200ms, border-color 200ms;
  cursor: default;
  user-select: none;
}
.confirm-feature:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(139,82,250,0.16);
  border-color: rgba(139,82,250,0.22);
}

.confirm-feature-icon {
  width: 36px; height: 36px;
  border-radius: 10px;
  background: var(--purple-tint, #F3EFFF);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--purple-neon, #8B52FA);
  flex-shrink: 0;
}

.confirm-feature-name {
  font-family: var(--font-sans, 'Montserrat', sans-serif);
  font-size: 13px;
  font-weight: 600;
  color: var(--text-primary, #1A1A1A);
  margin: 0 0 2px 0;
  line-height: 1.3;
}

.confirm-feature-desc {
  font-family: var(--font-sans, 'Montserrat', sans-serif);
  font-size: 11px;
  color: var(--text-muted, #9E9E9E);
  margin: 0;
  line-height: 1.3;
}

/* ── Responsive ── */
@media (max-width: 740px) {
  .confirm-card { grid-template-columns: 1fr; }
  .confirm-hero { padding: 44px 28px 36px; }
  .confirm-details-panel { padding: 32px 28px 36px; }
  .confirm-hero-title { font-size: 1.65rem; }
  .confirm-ring-wrap { width: 80px; height: 80px; margin-left: 20px; }
  .confirm-features { grid-template-columns: 1fr 1fr; }
}

@media (max-width: 480px) {
  .confirm-page { padding: 20px 14px; }
  .confirm-hero { padding: 36px 22px 32px; }
  .confirm-details-panel { padding: 28px 22px 32px; }
  .confirm-hero-title { font-size: 1.45rem; }
  .confirm-features { grid-template-columns: 1fr; }
}
</style>

<!-- Confetti plein-écran -->
<div class="confetti-layer" id="confettiLayer"></div>

<div class="confirm-page">
  <div class="confirm-wrapper">

    <!-- Carte principale split -->
    <div class="confirm-card">

      <!-- Panneau gauche : hero purple -->
      <div class="confirm-hero">
        <p class="confirm-hero-eyebrow">Paiement confirmé</p>

        <!-- Rings + check -->
        <div class="confirm-ring-wrap">
          <div class="confirm-ring confirm-ring-3"></div>
          <div class="confirm-ring confirm-ring-2"></div>
          <div class="confirm-ring confirm-ring-1"></div>
          <div class="confirm-check-circle">
            <i data-lucide="check" style="width:28px;height:28px;color:#fff;stroke-width:2.8"></i>
          </div>
        </div>

        <h1 class="confirm-hero-title">
          <?php if ($prenom): ?>Félicitations,<br><?= $prenom ?>&nbsp;!<?php else: ?>Accès activé !<?php endif; ?>
        </h1>

        <p class="confirm-hero-tagline">
          Ton abonnement Connect'Academia est maintenant actif. Bonne étude&nbsp;!
        </p>

        <div class="confirm-plan-chip">
          
          <?= $planLabel ?>
        </div>
      </div>

      <!-- Panneau droit : récapitulatif -->
      <div class="confirm-details-panel">
        <p class="confirm-panel-label">Récapitulatif</p>

        <div class="confirm-receipt">
          <div class="confirm-receipt-row">
            <div class="confirm-receipt-left">
              <i data-lucide="calendar-check" style="width:15px;height:15px"></i>
              Début
            </div>
            <span class="confirm-receipt-val"><?= $debutFr ?></span>
          </div>
          <div class="confirm-receipt-row">
            <div class="confirm-receipt-left">
              <i data-lucide="calendar-x" style="width:15px;height:15px"></i>
              Expiration
            </div>
            <span class="confirm-receipt-val"><?= $finFr ?></span>
          </div>
          <div class="confirm-receipt-row">
            <div class="confirm-receipt-left">
              <i data-lucide="clock" style="width:15px;height:15px"></i>
              Jours restants
            </div>
            <span class="confirm-days-tag">
              <i data-lucide="zap" style="width:10px;height:10px;fill:currentColor;stroke:none"></i>
              <?= $joursRestants ?> jours
            </span>
          </div>
        </div>

        <!-- Barre validité -->
        <div class="confirm-progress-block">
          <div class="confirm-progress-bar">
            <div class="confirm-progress-fill" id="confirmProgressFill"></div>
          </div>
          <div class="confirm-progress-meta">
            <span><?= $debutFr ?></span>
            <span><?= $finFr ?></span>
          </div>
        </div>

        <div class="confirm-dashed"></div>

        <!-- CTAs -->
        <div class="confirm-cta-group">
          <a href="<?= url('/hub') ?>" class="btn-confirm-hub">
            <i data-lucide="layout-dashboard" style="width:17px;height:17px"></i>
            Accéder au Hub
          </a>
          <a href="<?= url('/') ?>" class="btn-confirm-home">
            <i data-lucide="arrow-left" style="width:15px;height:15px"></i>
            Retour à l'accueil
          </a>
        </div>
      </div>
    </div>

    <!-- Tuiles fonctionnalités débloquées -->
    <div class="confirm-features">
      <div class="confirm-feature">
        
        <div>
          <p class="confirm-feature-name">Hub d'apprentissage</p>
          <p class="confirm-feature-desc">Cours, fiches & quiz</p>
        </div>
      </div>
      <div class="confirm-feature">
        
        <div>
          <p class="confirm-feature-name">Communauté</p>
          <p class="confirm-feature-desc">Posts & discussions</p>
        </div>
      </div>
      <div class="confirm-feature">
        
        <div>
          <p class="confirm-feature-name">Ressources</p>
          <p class="confirm-feature-desc">Documents & PDF</p>
        </div>
      </div>
    </div>

  </div>
</div>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>
(function () {
  if (typeof lucide !== 'undefined') lucide.createIcons();

  /* Animate progress bar */
  var fill = document.getElementById('confirmProgressFill');
  if (fill) {
    requestAnimationFrame(function () {
      setTimeout(function () { fill.style.width = '<?= $progressPct ?>%'; }, 600);
    });
  }

  /* Confetti */
  var colors  = ['#8B52FA','#ec4899','#f59e0b','#10b981','#3b82f6','#a855f7','#f43f5e'];
  var layer   = document.getElementById('confettiLayer');
  if (!layer) return;

  for (var i = 0; i < 60; i++) {
    var el      = document.createElement('div');
    var color   = colors[Math.floor(Math.random() * colors.length)];
    var streak  = Math.random() > 0.72;
    var circle  = !streak && Math.random() > 0.45;
    var w       = streak ? 3 : (5 + Math.random() * 7);
    var h       = streak ? (10 + Math.random() * 14) : w;
    var left    = (Math.random() * 102).toFixed(1);
    var dur     = (1.8 + Math.random() * 2.2).toFixed(2);
    var delay   = (Math.random() * 1.8).toFixed(2);
    var drift   = ((Math.random() - 0.5) * 160).toFixed(0);

    el.style.cssText = [
      'position:absolute',
      'left:' + left + '%',
      'top:-14px',
      'width:' + w + 'px',
      'height:' + h + 'px',
      'background:' + color,
      'border-radius:' + (circle ? '50%' : (streak ? '2px' : '3px')),
      'animation:confettiFall ' + dur + 's cubic-bezier(.45,0,.55,1) ' + delay + 's both',
      '--drift:' + drift + 'px',
      'opacity:.92',
    ].join(';');
    layer.appendChild(el);
  }
})();
</script>
