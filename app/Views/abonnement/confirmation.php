<?php
$abonnement  = $abonnement ?? null;
$user        = $user ?? [];
$prenom      = htmlspecialchars($user['prenom'] ?? '', ENT_QUOTES);
$planLabel   = ($abonnement['plan'] ?? 'mensuel') === 'mensuel' ? 'Plan Découverte' : 'CONNECT+';
$debutFr     = $abonnement ? date('d/m/Y', strtotime($abonnement['debut'] ?? 'now')) : date('d/m/Y');
$finFr       = $abonnement ? date('d/m/Y', strtotime($abonnement['fin'] ?? '+30 days')) : date('d/m/Y', strtotime('+30 days'));
$joursRestants = $abonnement ? max(0, (int) ceil((strtotime($abonnement['fin']) - time()) / 86400)) : 30;
?>

<style>
/* ── Confirmation page ── */
.confirm-page {
  min-height: calc(100vh - var(--navbar-h, 64px));
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
}

.confirm-card {
  background: #ffffff;
  border-radius: var(--radius-xl, 24px);
  padding: 56px 48px 48px;
  max-width: 520px;
  width: 100%;
  text-align: center;
  box-shadow: var(--shadow-lg, 0 20px 50px rgba(0,0,0,0.10));
  position: relative;
  overflow: hidden;
}

/* Confetti top bar */
.confirm-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 5px;
  background: linear-gradient(90deg, #8B52FA 0%, #ec4899 50%, #f59e0b 100%);
}

/* Icône succès animée */
.confirm-icon-wrap {
  width: 80px; height: 80px;
  background: linear-gradient(135deg, #8B52FA 0%, #a855f7 100%);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 28px;
  box-shadow: 0 8px 24px rgba(139,82,250,0.35);
  animation: popIn 0.5s cubic-bezier(0.34,1.56,0.64,1) both;
}
@keyframes popIn {
  from { transform: scale(0); opacity: 0; }
  to   { transform: scale(1); opacity: 1; }
}

.confirm-icon-wrap svg {
  animation: drawCheck 0.4s ease 0.4s both;
}
@keyframes drawCheck {
  from { stroke-dashoffset: 60; opacity: 0; }
  to   { stroke-dashoffset: 0;  opacity: 1; }
}

/* Titre */
.confirm-title {
  font-family: 'Montserrat', sans-serif;
  font-size: 1.75rem;
  font-weight: 800;
  color: var(--text-primary, #1A1A2E);
  margin-bottom: 8px;
}

.confirm-subtitle {
  color: var(--text-secondary, #6B7280);
  font-size: 0.9375rem;
  margin-bottom: 36px;
}

/* Détails abonnement */
.confirm-details {
  background: var(--primary-pale, #FAF7FF);
  border: 1.5px solid rgba(139,82,250,0.15);
  border-radius: var(--radius-lg, 16px);
  padding: 20px 24px;
  margin-bottom: 32px;
  text-align: left;
}

.confirm-detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 0;
  font-size: 14px;
  color: var(--text-secondary, #6B7280);
}
.confirm-detail-row:not(:last-child) {
  border-bottom: 1px solid rgba(139,82,250,0.08);
}
.confirm-detail-row strong {
  color: var(--text-primary, #1A1A2E);
  font-weight: 700;
}

/* Badge plan */
.plan-badge-confirm {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(139,82,250,0.12);
  color: #8B52FA;
  font-weight: 700;
  font-size: 12px;
  padding: 4px 12px;
  border-radius: 999px;
}

/* Jours restants */
.days-pill {
  background: rgba(0,200,83,0.12);
  color: #00a846;
  font-weight: 700;
  font-size: 13px;
  padding: 4px 12px;
  border-radius: 999px;
}

/* Bouton */
.btn-confirm-hub {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  padding: 15px 24px;
  background: linear-gradient(135deg, #8B52FA 0%, #7440D9 100%);
  color: white;
  border: none;
  border-radius: var(--radius-md, 12px);
  font-weight: 700;
  font-size: 16px;
  cursor: pointer;
  text-decoration: none;
  transition: opacity 0.2s, transform 0.15s;
  box-shadow: 0 6px 20px rgba(139,82,250,0.35);
}
.btn-confirm-hub:hover {
  opacity: 0.92;
  transform: translateY(-1px);
  text-decoration: none;
  color: white;
}

/* Confetti particles */
.confetti-container {
  position: absolute;
  top: 0; left: 0; right: 0; bottom: 0;
  pointer-events: none;
  overflow: hidden;
}
.confetti-piece {
  position: absolute;
  width: 8px; height: 8px;
  border-radius: 2px;
  animation: confettiFall linear both;
  top: -10px;
}
@keyframes confettiFall {
  0%   { transform: translateY(0) rotate(0deg);   opacity: 1; }
  80%  { opacity: 1; }
  100% { transform: translateY(550px) rotate(720deg); opacity: 0; }
}

@media (max-width: 560px) {
  .confirm-card { padding: 44px 24px 36px; }
  .confirm-title { font-size: 1.5rem; }
}
</style>

<div class="confirm-page">
  <div class="confirm-card" id="confirm-card">

    <!-- Confetti générés par JS -->
    <div class="confetti-container" id="confetti-container"></div>

    <!-- Icône succès -->
    <div class="confirm-icon-wrap">
      <svg width="38" height="38" viewBox="0 0 24 24" fill="none"
           stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
           style="stroke-dasharray:60; stroke-dashoffset:60;">
        <polyline points="20 6 9 17 4 12"></polyline>
      </svg>
    </div>

    <!-- Titre -->
    <h1 class="confirm-title">
      <?php if ($prenom): ?>Félicitations, <?= $prenom ?> !<?php else: ?>Abonnement activé !<?php endif; ?>
    </h1>
    <p class="confirm-subtitle">
      Votre accès à Connect'Academia est maintenant actif. Bonne étude !
    </p>

    <!-- Détails -->
    <div class="confirm-details">
      <div class="confirm-detail-row">
        <span>Plan</span>
        <span class="plan-badge-confirm">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
          </svg>
          <?= $planLabel ?>
        </span>
      </div>
      <div class="confirm-detail-row">
        <span>Début</span>
        <strong><?= $debutFr ?></strong>
      </div>
      <div class="confirm-detail-row">
        <span>Expiration</span>
        <strong><?= $finFr ?></strong>
      </div>
      <div class="confirm-detail-row">
        <span>Jours restants</span>
        <span class="days-pill"><?= $joursRestants ?> jours</span>
      </div>
    </div>

    <!-- CTA -->
    <a href="<?= url('/hub') ?>" class="btn-confirm-hub">
      Accéder au Hub
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <line x1="5" y1="12" x2="19" y2="12"></line>
        <polyline points="12 5 19 12 12 19"></polyline>
      </svg>
    </a>

  </div>
</div>

<script>
(function () {
  const colors = ['#8B52FA','#ec4899','#f59e0b','#10b981','#3b82f6','#f43f5e'];
  const container = document.getElementById('confetti-container');
  if (!container) return;

  for (let i = 0; i < 28; i++) {
    const piece = document.createElement('div');
    piece.className = 'confetti-piece';
    piece.style.cssText = [
      'left:' + (Math.random() * 100) + '%',
      'background:' + colors[Math.floor(Math.random() * colors.length)],
      'width:' + (6 + Math.random() * 6) + 'px',
      'height:' + (6 + Math.random() * 6) + 'px',
      'animation-duration:' + (1.5 + Math.random() * 2) + 's',
      'animation-delay:' + (Math.random() * 0.8) + 's',
      'border-radius:' + (Math.random() > 0.5 ? '50%' : '2px'),
    ].join(';');
    container.appendChild(piece);
  }
})();
</script>
