<?php
use Core\Session;
$errors  = Session::getFlash('errors', []);
$success = Session::getFlash('success');
$old     = Session::getFlash('old', []);
?>
<div class="auth-split-layout">

  <!-- Panneau image gauche -->
  <div class="auth-image-panel auth-image-panel--forgot" aria-hidden="true"></div>

  <!-- Panneau formulaire droit -->
  <main class="auth-form-panel">
    <div class="auth-form-panel__inner">

      <!-- Eyebrow -->
      <div style="text-align:center;margin-bottom:var(--space-8);">
        <span class="ca-badge ca-badge-brand-soft" style="font-size:10px;letter-spacing:1.5px;margin-bottom:var(--space-4);display:inline-flex;">
          <i data-lucide="shield" style="width:13px;height:13px;"></i>
          SÉCURITÉ CONNECT'ACADEMIA
        </span>
        <h1 class="ca-h1" style="color:#fff;margin:var(--space-3) 0 var(--space-2);">Mot de passe oublié ?</h1>
        <p class="ca-body" style="color:rgba(255,255,255,0.5);line-height:1.6;margin:0;">
          Saisis ton adresse email pour recevoir un lien de réinitialisation.
        </p>
      </div>

      <!-- Alerte succès -->
      <?php if ($success): ?>
      <div id="alert-success" class="ca-alert ca-alert--success" role="status" aria-live="polite" style="margin-bottom:var(--space-6);">
        <i data-lucide="check-circle" style="width:18px;height:18px;flex-shrink:0;"></i>
        <span><?= htmlspecialchars($success, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></span>
      </div>
      <?php endif; ?>

      <!-- Alerte erreur générale -->
      <?php if (!empty($errors['general'])): ?>
      <div class="ca-alert ca-alert--error" role="alert" style="margin-bottom:var(--space-6);">
        <i data-lucide="alert-circle" style="width:18px;height:18px;flex-shrink:0;"></i>
        <span><?= htmlspecialchars($errors['general'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></span>
      </div>
      <?php endif; ?>

      <!-- Formulaire -->
      <form id="forgot-form" action="<?= url('/auth/mot-de-passe-oublie') ?>" method="POST" novalidate>
        <input type="hidden" name="_csrf_token" value="<?= Session::getCsrfToken() ?>">

        <div style="display:flex;flex-direction:column;gap:var(--space-6);">

          <div class="ca-field">
            <label class="ca-label" for="email">Adresse email</label>
            <div class="ca-input-wrap">
              <i data-lucide="mail" class="ca-input-icon"></i>
              <input
                class="ca-input ca-input--dark <?= !empty($errors['email']) ? 'error' : '' ?>"
                type="email"
                id="email"
                name="email"
                placeholder="nom@etablissement.com"
                value="<?= htmlspecialchars($old['email'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                autocomplete="email"
                required
                autofocus
              >
            </div>
            <?php if (!empty($errors['email'])): ?>
            <span class="ca-error">
              <i data-lucide="alert-circle" style="width:12px;height:12px;"></i>
              <?= htmlspecialchars($errors['email'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
            </span>
            <?php endif; ?>
          </div>

          <button type="submit" id="submit-btn" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;">
            <i data-lucide="send" id="btn-icon" style="width:17px;height:17px;"></i>
            <span id="btn-label">Envoyer le lien</span>
          </button>

        </div>
      </form>

      <!-- Retour connexion -->
      <div style="margin-top:var(--space-8);padding-top:var(--space-6);border-top:1px solid rgba(255,255,255,0.07);text-align:center;">
        <a href="<?= url('/auth/connexion') ?>" class="ca-link-back">
          <i data-lucide="arrow-left" style="width:15px;height:15px;"></i>
          Retour à la connexion
        </a>
      </div>

      <p style="text-align:center;color:rgba(255,255,255,0.15);font-size:11px;margin-top:var(--space-6);">
        Tous droits réservés — Connect'Academia
      </p>

    </div><!-- /.auth-form-panel__inner -->
  </main>

</div><!-- /.auth-split-layout -->

<style>
/* ── Layout split ─────────────────────────────────────────────────── */
.auth-split-layout {
  display: flex;
  min-height: 100vh;
  background: #0F0F12;
}
.auth-image-panel {
  flex: 1;
  min-height: 100vh;
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
  position: sticky;
  top: 0;
  align-self: flex-start;
  height: 100vh;
}
.auth-image-panel--forgot {
  background-image: url('<?= asset('images/Images en 409x610 - Page de Connexion 2.png') ?>');
  background-color: #16161E;
}
.auth-form-panel {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-10);
  overflow-y: auto;
}
.auth-form-panel__inner {
  width: 100%;
  max-width: 420px;
  background: #1C1C22;
  border-radius: var(--radius-xl);
  padding: var(--space-10);
  box-shadow: 0 24px 48px rgba(0,0,0,0.5);
  border: 1px solid rgba(255,255,255,0.07);
  animation: ca-slide-up 400ms cubic-bezier(0.2,0.8,0.2,1) both;
}

/* ── Alertes ──────────────────────────────────────────────────────── */
.ca-alert {
  display: flex;
  align-items: flex-start;
  gap: var(--space-3);
  padding: var(--space-4);
  border-radius: var(--radius-md);
  font-size: var(--fs-body);
  line-height: 1.5;
}
.ca-alert--success {
  background: rgba(0,200,83,0.09);
  border: 1px solid rgba(0,200,83,0.22);
  color: #86EFAC;
}
.ca-alert--error {
  background: rgba(255,82,82,0.09);
  border: 1px solid rgba(255,82,82,0.22);
  color: #FCA5A5;
}

/* ── Label / Input dark ───────────────────────────────────────────── */
.ca-label {
  font-family: var(--font-sans);
  font-size: 11px;
  font-weight: var(--fw-semibold);
  color: rgba(255,255,255,0.55);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin-bottom: var(--space-2);
  display: block;
}
.ca-field { display: flex; flex-direction: column; gap: var(--space-2); }
.ca-input-wrap { position: relative; display: flex; align-items: center; }
.ca-input-icon {
  position: absolute;
  left: 16px;
  width: 18px;
  height: 18px;
  color: rgba(255,255,255,0.25);
  pointer-events: none;
  transition: color 150ms ease;
}
.ca-input-wrap:focus-within .ca-input-icon { color: var(--purple-neon); }
.ca-input--dark {
  width: 100%;
  height: 54px;
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.10);
  border-radius: var(--radius-md);
  padding: 0 16px 0 48px;
  color: #fff;
  font-family: var(--font-sans);
  font-size: var(--fs-body-lg);
  outline: none;
  transition: border-color 150ms ease, box-shadow 150ms ease, background 150ms ease;
}
.ca-input--dark::placeholder { color: rgba(255,255,255,0.2); }
.ca-input--dark:focus {
  border-color: var(--purple-neon);
  background: rgba(139,82,250,0.05);
  box-shadow: 0 0 0 4px rgba(139,82,250,0.15);
}
.ca-input--dark.error {
  border-color: var(--error);
  box-shadow: 0 0 0 4px rgba(255,82,82,0.13);
}

/* ── Textes aide ──────────────────────────────────────────────────── */
.ca-error {
  font-size: 11px;
  color: #FCA5A5;
  display: flex;
  align-items: center;
  gap: 5px;
}

/* ── Bouton primary dark ──────────────────────────────────────────── */
.btn {
  font-family: var(--font-sans);
  font-weight: var(--fw-medium);
  font-size: var(--fs-button);
  border: 0;
  cursor: pointer;
  border-radius: var(--radius-pill);
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  transition: all 200ms cubic-bezier(.2,.8,.2,1);
}
.btn-primary {
  padding: 14px 24px;
  background: var(--purple-neon);
  color: #fff;
  box-shadow: 0 8px 24px var(--purple-glow-30);
}
.btn-primary:hover  { background: var(--purple-hover); transform: translateY(-1px); }
.btn-primary:active { background: var(--purple-press); transform: scale(0.98); }
.btn-primary:disabled { background: #4B2D8A; cursor: not-allowed; transform: none; box-shadow: none; opacity: 0.7; }
.btn-lg { padding: 16px 32px; font-size: 15px; }

/* ── Badge dark override ──────────────────────────────────────────── */
.ca-badge-brand-soft {
  background: rgba(139,82,250,0.15);
  color: #A87EFB;
  border: 1px solid rgba(139,82,250,0.25);
}

/* ── Lien retour ──────────────────────────────────────────────────── */
.ca-link-back {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  color: rgba(255,255,255,0.35);
  font-size: 13px;
  font-family: var(--font-sans);
  font-weight: var(--fw-medium);
  text-decoration: none;
  transition: all 200ms ease;
}
.ca-link-back:hover { color: var(--purple-neon); transform: translateX(-3px); }

/* ── Animation ────────────────────────────────────────────────────── */
@keyframes ca-slide-up {
  from { opacity: 0; transform: translateY(16px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ── Responsive ──────────────────────────────────────────────────── */
@media (max-width: 900px) {
  .auth-image-panel { display: none; }
  .auth-form-panel  { padding: var(--space-5); }
}
@media (max-width: 480px) {
  .auth-form-panel__inner { padding: var(--space-8) var(--space-6); }
}
</style>

<!-- Lucide icons -->
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  lucide.createIcons();

  var form      = document.getElementById('forgot-form');
  var submitBtn = document.getElementById('submit-btn');
  var btnLabel  = document.getElementById('btn-label');
  var btnIcon   = document.getElementById('btn-icon');

  // Soumission — désactiver le bouton et montrer un état chargement
  if (form) {
    form.addEventListener('submit', function () {
      submitBtn.disabled = true;
      btnLabel.textContent = 'Envoi en cours…';
      btnIcon.setAttribute('data-lucide', 'loader');
      lucide.createIcons();
    });
  }

  // Suppression automatique de l'alerte succès après 10 secondes
  var successAlert = document.getElementById('alert-success');
  if (successAlert) {
    setTimeout(function () {
      successAlert.style.transition = 'opacity 0.5s ease';
      successAlert.style.opacity = '0';
      setTimeout(function () { successAlert.remove(); }, 500);
    }, 10000);
  }
});
</script>
