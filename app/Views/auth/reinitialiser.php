<?php
use Core\Session;
// Même piège que sur mot-de-passe-oublie.php : AuthController::showReset a déjà
// consommé le flash et le passe à la vue. Relire ici écrasait $errors par null
// et masquait les messages de validation du nouveau mot de passe.
$errors = $errors ?? [];
?>
<div class="auth-split-layout">

  <!-- Panneau gauche — illustration -->
  <div class="auth-image-panel auth-image-panel--reset" aria-hidden="true"></div>

  <!-- Panneau droit — formulaire -->
  <main class="auth-form-panel">
    <div class="auth-form-panel__inner">

      <!-- Eyebrow -->
      <div style="text-align:center;margin-bottom:var(--space-8);">
        <span class="ca-badge ca-badge-brand-soft" style="font-size:10px;letter-spacing:1.5px;margin-bottom:var(--space-4);display:inline-flex;">
          <i data-lucide="shield-check" style="width:13px;height:13px;"></i>
          SÉCURITÉ CONNECT'ACADEMIA
        </span>
        <h1 class="ca-h1" style="color:#fff;margin:var(--space-3) 0 var(--space-2);">Nouveau mot de passe</h1>
        <p class="ca-body" style="color:rgba(255,255,255,0.5);line-height:1.6;margin:0;">
          Choisis un mot de passe fort (min. 8 caractères).
        </p>
      </div>

      <!-- Erreur générale -->
      <?php if (!empty($errors['general'])): ?>
      <div class="ca-alert ca-alert--error" role="alert" style="margin-bottom:var(--space-6);">
        <i data-lucide="alert-circle" style="width:18px;height:18px;flex-shrink:0;"></i>
        <span><?= htmlspecialchars($errors['general'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></span>
      </div>
      <?php endif; ?>

      <!-- Formulaire -->
      <form id="reset-form" action="<?= url('/auth/reinitialiser') ?>" method="POST" novalidate>
        <input type="hidden" name="_csrf_token" value="<?= Session::getCsrfToken() ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">

        <div style="display:flex;flex-direction:column;gap:var(--space-5);">

          <!-- Champ mot de passe -->
          <div class="ca-field">
            <label class="ca-label" for="password">Nouveau mot de passe</label>
            <div class="ca-input-wrap">
              <i data-lucide="lock" class="ca-input-icon"></i>
              <input
                class="ca-input ca-input--dark <?= !empty($errors['password']) ? 'error' : '' ?>"
                type="password"
                id="password"
                name="password"
                placeholder="••••••••"
                autocomplete="new-password"
                required
                minlength="8"
                aria-describedby="pwd-strength <?= !empty($errors['password']) ? 'pwd-error' : '' ?>"
              >
              <button type="button" class="ca-toggle-pwd" aria-label="Afficher/masquer le mot de passe" data-target="password">
                <i data-lucide="eye" style="width:16px;height:16px;"></i>
              </button>
            </div>
            <?php if (!empty($errors['password'])): ?>
            <span id="pwd-error" class="ca-error">
              <i data-lucide="alert-circle" style="width:12px;height:12px;"></i>
              <?= htmlspecialchars($errors['password'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
            </span>
            <?php endif; ?>

            <!-- Jauge de force -->
            <div id="pwd-strength" class="pwd-strength" aria-live="polite">
              <div class="pwd-strength__bars">
                <span class="pwd-strength__bar"></span>
                <span class="pwd-strength__bar"></span>
                <span class="pwd-strength__bar"></span>
                <span class="pwd-strength__bar"></span>
              </div>
              <span id="pwd-strength-label" class="ca-caption" style="color:rgba(255,255,255,0.35);">Entrez un mot de passe</span>
            </div>
          </div>

          <!-- Champ confirmation -->
          <div class="ca-field">
            <label class="ca-label" for="password_confirmation">Confirmer le mot de passe</label>
            <div class="ca-input-wrap">
              <i data-lucide="check-circle" class="ca-input-icon"></i>
              <input
                class="ca-input ca-input--dark <?= !empty($errors['password_confirmation']) ? 'error' : '' ?>"
                type="password"
                id="password_confirmation"
                name="password_confirmation"
                placeholder="••••••••"
                autocomplete="new-password"
                required
              >
              <button type="button" class="ca-toggle-pwd" aria-label="Afficher/masquer la confirmation" data-target="password_confirmation">
                <i data-lucide="eye" style="width:16px;height:16px;"></i>
              </button>
            </div>
            <?php if (!empty($errors['password_confirmation'])): ?>
            <span class="ca-error">
              <i data-lucide="alert-circle" style="width:12px;height:12px;"></i>
              <?= htmlspecialchars($errors['password_confirmation'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
            </span>
            <?php endif; ?>
            <span id="confirm-match" class="ca-help" style="display:none;"></span>
          </div>

          <!-- Bouton submit -->
          <button type="submit" id="submit-btn" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;margin-top:var(--space-2);">
            <i data-lucide="shield-check" id="btn-icon" style="width:18px;height:18px;"></i>
            <span id="btn-label">Réinitialiser le mot de passe</span>
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

/* Panneau image gauche */
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
.auth-image-panel--reset {
  background-image: url('<?= css_safe(asset('images/Images en 409x610 - Page de Connexion 2.png')) ?>');
  background-color: #16161E;
}

/* Panneau formulaire droit */
.auth-form-panel {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-10) var(--space-10);
  position: relative;
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

/* ── Alerte erreur ────────────────────────────────────────────────── */
.ca-alert {
  display: flex;
  align-items: flex-start;
  gap: var(--space-3);
  padding: var(--space-4);
  border-radius: var(--radius-md);
  font-size: var(--fs-body);
  line-height: 1.5;
}
.ca-alert--error {
  background: rgba(255,82,82,0.09);
  border: 1px solid rgba(255,82,82,0.22);
  color: #FCA5A5;
}

/* ── Champs dark ──────────────────────────────────────────────────── */
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
.ca-field {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}
.ca-input-wrap {
  position: relative;
  display: flex;
  align-items: center;
}
.ca-input-icon {
  position: absolute;
  left: 16px;
  width: 18px;
  height: 18px;
  color: rgba(255,255,255,0.25);
  pointer-events: none;
  transition: color 150ms ease;
}
.ca-input-wrap:focus-within .ca-input-icon {
  color: var(--purple-neon);
}
.ca-input--dark {
  width: 100%;
  height: 54px;
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.10);
  border-radius: var(--radius-md);
  padding: 0 48px 0 48px;
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

/* Bouton œil */
.ca-toggle-pwd {
  position: absolute;
  right: 14px;
  background: none;
  border: none;
  color: rgba(255,255,255,0.25);
  cursor: pointer;
  padding: 4px;
  display: flex;
  align-items: center;
  transition: color 150ms ease;
}
.ca-toggle-pwd:hover { color: var(--purple-neon); }

/* ── Textes aide ──────────────────────────────────────────────────── */
.ca-help  { font-size: 11px; color: rgba(255,255,255,0.3); }
.ca-error {
  font-size: 11px;
  color: #FCA5A5;
  display: flex;
  align-items: center;
  gap: 5px;
}

/* ── Jauge de force ───────────────────────────────────────────────── */
.pwd-strength {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  margin-top: var(--space-1);
}
.pwd-strength__bars {
  display: flex;
  gap: 4px;
  flex: 1;
}
.pwd-strength__bar {
  flex: 1;
  height: 4px;
  border-radius: var(--radius-pill);
  background: rgba(255,255,255,0.08);
  transition: background 250ms ease;
}

/* ── Bouton primary (dark theme override) ─────────────────────────── */
.btn {
  font-family: var(--font-sans);
  font-weight: var(--fw-medium);
  font-size: var(--fs-button);
  letter-spacing: 0.01em;
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
.btn-primary:hover  { background: var(--purple-hover); transform: translateY(-1px); box-shadow: 0 12px 28px var(--purple-glow-30); }
.btn-primary:active { background: var(--purple-press); transform: scale(0.98); }
.btn-primary:disabled { background: #4B2D8A; cursor: not-allowed; transform: none; box-shadow: none; opacity: 0.7; }
.btn-lg { padding: 16px 32px; font-size: 15px; }

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

/* ── Badge dark override ──────────────────────────────────────────── */
.ca-badge-brand-soft {
  background: rgba(139,82,250,0.15);
  color: #A87EFB;
  border: 1px solid rgba(139,82,250,0.25);
}

/* ── Animation entrée ────────────────────────────────────────────── */
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

  // ── Toggle visibilité mot de passe ─────────────────────────────────
  document.querySelectorAll('.ca-toggle-pwd').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var targetId = btn.dataset.target;
      var input    = document.getElementById(targetId);
      var icon     = btn.querySelector('[data-lucide]');
      if (!input) return;
      if (input.type === 'password') {
        input.type = 'text';
        icon.setAttribute('data-lucide', 'eye-off');
      } else {
        input.type = 'password';
        icon.setAttribute('data-lucide', 'eye');
      }
      lucide.createIcons();
    });
  });

  // ── Jauge force mot de passe ───────────────────────────────────────
  var pwdInput    = document.getElementById('password');
  var bars        = document.querySelectorAll('.pwd-strength__bar');
  var strengthLbl = document.getElementById('pwd-strength-label');

  var LEVELS = [
    { label: '',                  color: 'rgba(255,255,255,0.08)', filled: 0 },
    { label: 'Très faible',       color: '#FF5252',               filled: 1 },
    { label: 'Faible',            color: '#FFAB00',               filled: 2 },
    { label: 'Moyen',             color: '#FFAB00',               filled: 3 },
    { label: 'Fort',              color: '#00C853',               filled: 4 },
  ];

  function scorePassword(pwd) {
    if (!pwd)         return 0;
    if (pwd.length < 8) return 1;
    var score = 1;
    if (pwd.length >= 12)             score++;
    if (/[A-Z]/.test(pwd) && /[a-z]/.test(pwd)) score++;
    if (/[0-9]/.test(pwd))            score++;
    if (/[^A-Za-z0-9]/.test(pwd))    score = Math.min(4, score + 1);
    return Math.min(4, score);
  }

  if (pwdInput) {
    pwdInput.addEventListener('input', function () {
      var score = scorePassword(this.value);
      var level = LEVELS[score];
      bars.forEach(function (bar, i) {
        bar.style.background = i < level.filled ? level.color : 'rgba(255,255,255,0.08)';
      });
      strengthLbl.textContent = level.label;
      strengthLbl.style.color = level.label ? level.color : 'rgba(255,255,255,0.35)';
    });
  }

  // ── Validation correspondance en temps réel ────────────────────────
  var confirmInput = document.getElementById('password_confirmation');
  var confirmHint  = document.getElementById('confirm-match');

  function checkMatch() {
    if (!confirmInput.value) {
      confirmHint.style.display = 'none';
      return;
    }
    confirmHint.style.display = 'flex';
    if (pwdInput.value === confirmInput.value) {
      confirmHint.textContent = '✓ Les mots de passe correspondent.';
      confirmHint.style.color = '#86EFAC';
    } else {
      confirmHint.textContent = '✗ Les mots de passe ne correspondent pas.';
      confirmHint.style.color = '#FCA5A5';
    }
  }

  if (confirmInput) {
    confirmInput.addEventListener('input', checkMatch);
    if (pwdInput) pwdInput.addEventListener('input', checkMatch);
  }

  // ── Désactivation bouton à la soumission ───────────────────────────
  var form      = document.getElementById('reset-form');
  var submitBtn = document.getElementById('submit-btn');
  var btnLabel  = document.getElementById('btn-label');
  var btnIcon   = document.getElementById('btn-icon');

  if (form) {
    form.addEventListener('submit', function (e) {
      // Validation côté client avant envoi
      var pwd     = pwdInput ? pwdInput.value : '';
      var confirm = confirmInput ? confirmInput.value : '';

      if (pwd.length < 8) {
        e.preventDefault();
        pwdInput.classList.add('error');
        pwdInput.focus();
        return;
      }
      if (pwd !== confirm) {
        e.preventDefault();
        confirmInput.classList.add('error');
        confirmInput.focus();
        return;
      }

      submitBtn.disabled = true;
      btnLabel.textContent = 'Réinitialisation…';
      btnIcon.setAttribute('data-lucide', 'loader');
      lucide.createIcons();
    });
  }
});
</script>
