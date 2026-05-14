<?php
$user       = $user       ?? [];
$abonnement = $abonnement ?? null;
$status     = $status     ?? '';
$userEmail  = htmlspecialchars($user['email'] ?? '', ENT_QUOTES);
$csrfToken  = \Core\Session::getCsrfToken();
?>

<style>
/* ── Pricing page — scoped styles ── */
.pricing-wrap {
  max-width: 1000px;
  margin: 0 auto;
  padding: 48px 20px 80px;
}

.pricing-hero {
  text-align: center;
  margin-bottom: 48px;
}

.pricing-hero h1 {
  font-family: 'Montserrat', sans-serif;
  font-size: 2rem;
  font-weight: 800;
  color: var(--text-primary);
  margin-bottom: 10px;
}

.pricing-hero p {
  color: var(--text-secondary);
  font-size: 1rem;
  max-width: 500px;
  margin: 0 auto;
}

/* Alert pending */
.alert-pending {
  background: #fffbeb;
  border: 1.5px solid #f59e0b;
  border-radius: var(--radius-md);
  padding: 14px 20px;
  margin-bottom: 28px;
  color: #92400e;
  font-size: 14px;
  text-align: center;
}

/* Email notice */
.email-notice {
  background: var(--primary-light, #F0EBFF);
  border: 1.5px solid rgba(139,82,250,0.3);
  border-radius: var(--radius-md);
  padding: 14px 20px;
  margin-bottom: 36px;
  font-size: 13.5px;
  color: var(--text-primary);
  text-align: center;
}
.email-notice strong { color: var(--primary, #8B52FA); }

/* Grid */
.pricing-grid {
  display: flex;
  justify-content: center;
  gap: 40px;
  align-items: flex-start;
}

.pricing-card {
  flex: 1;
  max-width: 380px;
  background: #fff;
  border-radius: 24px;
  padding: 44px 36px 36px;
  display: flex;
  flex-direction: column;
  align-items: center;
  position: relative;
  box-shadow: 0 10px 30px rgba(0,0,0,0.06);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.pricing-card:hover { transform: translateY(-3px); box-shadow: 0 16px 40px rgba(0,0,0,0.09); }

.card-light-pink { border: 2px solid #fce7f3; }
.card-popular    { border: 3px solid #8b5cf6; transform: scale(1.03); }
.card-popular:hover { transform: scale(1.03) translateY(-3px); }

.popular-badge {
  position: absolute;
  top: -18px;
  background: #8b5cf6;
  color: white;
  padding: 7px 22px;
  border-radius: 12px;
  font-weight: 900;
  font-size: 13px;
  white-space: nowrap;
  letter-spacing: 0.3px;
}

.plan-name {
  font-family: 'Montserrat', sans-serif;
  font-size: 22px;
  font-weight: 800;
  margin-bottom: 20px;
  color: #1f2937;
}

.plan-price { margin-bottom: 32px; text-align: center; }
.price-big  { font-size: 32px; font-weight: 800; color: #000; font-family: 'Montserrat', sans-serif; }
.price-sub  { font-size: 13px; color: #6b7280; font-weight: 600; }

.plan-features {
  list-style: none;
  padding: 0;
  width: 100%;
  margin-bottom: 32px;
}
.plan-features li {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  margin-bottom: 14px;
  font-size: 13.5px;
  color: #374151;
  font-weight: 500;
  line-height: 1.4;
}

.check-pink, .check-purple {
  width: 20px; height: 20px;
  border-radius: 4px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  margin-top: 1px;
}
.check-pink   { background: #fce7f3; color: #ec4899; }
.check-purple { background: #ede9fe; color: #8b5cf6; }

.check-pink svg, .check-purple svg {
  width: 12px; height: 12px;
  stroke: currentColor; fill: none; stroke-width: 3;
}

.plan-divider { width: 100%; height: 1px; background: #e5e7eb; margin-bottom: 24px; }

/* Boutons */
.btn-plan-pay {
  width: 100%;
  padding: 15px;
  background: #7c3aed;
  color: white;
  border: none;
  border-radius: 12px;
  font-weight: 700;
  font-size: 15px;
  cursor: pointer;
  transition: background 0.2s, opacity 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}
.btn-plan-pay:hover:not(:disabled) { background: #6d28d9; }
.btn-plan-pay:disabled { opacity: 0.6; cursor: not-allowed; }

.btn-plan-disabled {
  width: 100%;
  padding: 15px;
  background: #f3f4f6;
  color: #9ca3af;
  border: 1.5px solid #e5e7eb;
  border-radius: 12px;
  font-weight: 600;
  font-size: 15px;
  cursor: not-allowed;
  text-align: center;
}

.badge-soon {
  display: inline-block;
  background: #f3f4f6;
  color: #6b7280;
  font-size: 11px;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 999px;
  margin-bottom: 12px;
  letter-spacing: 0.4px;
}

/* Spinner */
.btn-spinner {
  width: 16px; height: 16px;
  border: 2px solid rgba(255,255,255,0.4);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* FAQ teaser */
.pricing-footer {
  text-align: center;
  margin-top: 48px;
  color: var(--text-secondary);
  font-size: 13.5px;
}

@media (max-width: 720px) {
  .pricing-grid {
    flex-direction: column;
    align-items: center;
    gap: 50px;
  }
  .pricing-card { width: 100%; max-width: 420px; transform: none !important; }
  .card-popular { transform: none !important; }
}
</style>

<div class="pricing-wrap">

  <!-- Hero -->
  <div class="pricing-hero">
    <h1>Choisissez votre plan</h1>
    <p>Accédez à tous les modules Connect'Academia — Apprentissage, IA et Communauté.</p>
  </div>

  <?php if ($status === 'pending'): ?>
  <div class="alert-pending" id="alert-pending">
    ⏳ Votre paiement est en cours de vérification. Vérification automatique en cours…
  </div>
  <?php endif; ?>

  <!-- Email notice -->
  <div class="email-notice">
    ⚠️ Lors du paiement sur MoneyFusion, utilisez obligatoirement votre adresse email :
    <br><strong><?= $userEmail ?></strong>
    <br><small style="color:#6b7280;margin-top:4px;display:block;">C'est ainsi que nous lierons votre paiement à votre compte.</small>
  </div>

  <!-- Cards -->
  <div class="pricing-grid">

    <!-- Plan Découverte -->
    <div class="pricing-card card-light-pink">
      <h3 class="plan-name">Plan Découverte</h3>
      <div class="plan-price">
        <div class="price-big">2 000 FCFA</div>
        <div class="price-sub">/ mois <sup>HT</sup></div>
      </div>
      <ul class="plan-features">
        <li><span class="check-pink"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg></span> Communauté Connect' (Illimité)</li>
        <li><span class="check-pink"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg></span> Orientation Post-Bac (Illimité)</li>
        <li><span class="check-pink"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg></span> Accès Catalogue d'Annales, Sujets et Corrigés (Illimité)</li>
        <li><span class="check-pink"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg></span> Accès Catalogue National (24h)</li>
        <li><span class="check-pink"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg></span> Assistant IA Découverte</li>
        <li><span class="check-pink"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg></span> Générations d'essai</li>
      </ul>
      <div class="plan-divider"></div>
      <button id="btn-payer-mensuel" class="btn-plan-pay" onclick="initierPaiement()">
        Payer 2 000 XAF / mois
      </button>
    </div>

    <!-- Plan CONNECT+ (Annuel — désactivé) -->
    <div class="pricing-card card-popular">
      <div class="popular-badge">LA PLUS POPULAIRE ✨</div>
      <h3 class="plan-name">CONNECT+</h3>
      <div class="plan-price">
        <div class="price-big">15 000 FCFA</div>
        <div class="price-sub">/ année <sup>HT</sup></div>
      </div>
      <ul class="plan-features">
        <li><span class="check-purple"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg></span> Tous les avantages du plan Découverte</li>
        <li><span class="check-purple"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg></span> Accès Illimité au Catalogue</li>
        <li><span class="check-purple"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg></span> Assistant IA "Illimité"</li>
        <li><span class="check-purple"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg></span> Générations Magiques Illimitées</li>
        <li><span class="check-purple"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg></span> Priorité fonctionnalités</li>
      </ul>
      <div class="plan-divider"></div>
      <span class="badge-soon">BIENTÔT DISPONIBLE</span>
      <div class="btn-plan-disabled">Bientôt disponible</div>
    </div>

  </div>

  <div class="pricing-footer">
    <a href="<?= url('/hub') ?>" style="color:var(--primary);">← Retour au Hub</a>
  </div>
</div>

<script>
async function initierPaiement() {
  const btn = document.getElementById('btn-payer-mensuel');
  btn.disabled = true;
  btn.innerHTML = '<span class="btn-spinner"></span> Redirection en cours…';

  try {
    const r = await fetch(<?= json_encode(url('/api/paiement/initier')) ?>, {
      method: 'POST',
      headers: {
        'X-CSRF-Token':     window.CA?.csrfToken ?? <?= json_encode($csrfToken) ?>,
        'Content-Type':     'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify({}),
    });

    const data = await r.json();

    if (data.success && data.payment_url) {
      // Stocker le token MF pour vérification au retour
      if (data.mf_token) {
        localStorage.setItem('ca_mf_token', data.mf_token);
      }
      window.location.href = data.payment_url;
    } else {
      const msg = data.error?.message ?? 'Une erreur est survenue. Réessayez.';
      showError(msg);
      btn.disabled = false;
      btn.innerHTML = 'Payer 2 000 XAF / mois';
    }
  } catch (e) {
    showError('Erreur réseau. Vérifiez votre connexion et réessayez.');
    btn.disabled = false;
    btn.innerHTML = 'Payer 2 000 XAF / mois';
  }
}

function showError(msg) {
  if (typeof window.toast === 'function') {
    window.toast(msg, 'error');
    return;
  }
  const toast = document.getElementById('toast-container');
  if (toast) {
    const el = document.createElement('div');
    el.style.cssText = 'background:#EF4444;color:white;padding:12px 18px;border-radius:10px;margin-top:8px;font-size:14px;font-weight:600;';
    el.textContent = msg;
    toast.appendChild(el);
    setTimeout(() => el.remove(), 5000);
  } else {
    alert(msg);
  }
}

// Polling automatique si paiement en attente
(function () {
  const alertEl = document.getElementById('alert-pending');
  if (!alertEl) return;

  const storedMfToken = localStorage.getItem('ca_mf_token') ?? '';
  const statutBase    = <?= json_encode(url('/api/paiement/statut')) ?>;

  let attempt = 0;
  const maxAttempts = 36; // ~3 min
  const intervalMs  = 5000;

  const dots = ['', '.', '..', '...'];
  let dot = 0;

  const interval = setInterval(async () => {
    attempt++;
    dot = (dot + 1) % dots.length;
    alertEl.textContent = '⏳ Vérification de votre paiement' + dots[dot];

    try {
      const url = storedMfToken
        ? statutBase + '?mf_token=' + encodeURIComponent(storedMfToken)
        : statutBase;

      const r    = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      const data = await r.json();

      if (data.abonne && data.redirect) {
        clearInterval(interval);
        localStorage.removeItem('ca_mf_token');
        alertEl.style.background  = '#d1fae5';
        alertEl.style.borderColor = '#6ee7b7';
        alertEl.style.color       = '#065f46';
        alertEl.textContent       = '✅ Paiement confirmé ! Redirection…';
        setTimeout(() => { window.location.href = data.redirect; }, 1000);
      }
    } catch (e) { /* réseau — on réessaie */ }

    if (attempt >= maxAttempts) {
      clearInterval(interval);
      alertEl.textContent = '⏳ Paiement en cours de traitement. Rechargez la page dans quelques minutes ou contactez le support.';
    }
  }, intervalMs);
})();
</script>
