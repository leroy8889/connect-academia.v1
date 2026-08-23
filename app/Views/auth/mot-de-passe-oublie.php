<?php
use Core\Session;
// Les flashs sont déjà lus (et donc CONSOMMÉS) par AuthController::showForgot,
// qui les passe à la vue. Les relire ici renvoyait systématiquement null :
// le message « Si cette adresse est enregistrée… » n'apparaissait jamais après
// l'envoi du formulaire — l'utilisateur avait l'impression que rien ne se passait.
$errors  = $errors  ?? [];
$success = $success ?? null;
$old     = $old     ?? [];
?>
<div class="main-container">

  <!-- Section gauche — image fixe (identique à la connexion) -->
  <div class="image-section image-section--connexion"></div>

  <!-- Section droite — formulaire -->
  <div class="form-section">

    

    <div class="form-wrapper">

      <!-- Icône sécurité -->
      <div class="forgot-hero-icon" aria-hidden="true">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
          <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
        </svg>
      </div>

      <div class="title-small">Récupération de compte</div>
      <div class="title-main">MOT DE PASSE</div>

      <p class="forgot-lead">
        Saisis l'adresse email liée à ton compte. On t'envoie un lien
        sécurisé pour choisir un nouveau mot de passe.
      </p>

      <?php if (!empty($success)): ?>
      <div id="alert-success" class="auth-alert auth-alert--success" role="status" aria-live="polite">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
        </svg>
        <?= e($success) ?>
      </div>
      <?php endif; ?>

      <?php if (!empty($errors['general'])): ?>
      <div class="auth-alert auth-alert--error" role="alert">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
        </svg>
        <?= e($errors['general']) ?>
      </div>
      <?php endif; ?>

      <form id="forgot-form" action="<?= url('/auth/mot-de-passe-oublie') ?>" method="POST" novalidate>
        <input type="hidden" name="_csrf_token" value="<?= Session::getCsrfToken() ?>">

        <div class="form-group">
          <label for="email">Email <span class="required">*</span></label>
          <input type="email" id="email" name="email"
                 placeholder="Entrez votre email"
                 value="<?= e($old['email'] ?? '') ?>"
                 autocomplete="email" required autofocus>
          <?php if (!empty($errors['email'])): ?>
          <span class="field-error"><?= e($errors['email']) ?></span>
          <?php endif; ?>
        </div>

        <button type="submit" id="submit-btn" class="btn-forgot">
          <svg id="btn-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="22" y1="2" x2="11" y2="13"></line>
            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
          </svg>
          <span id="btn-label">Envoyer le lien</span>
        </button>
      </form>

      <!-- Note info sécurité -->
      <div class="forgot-note">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="16" x2="12" y2="12"></line>
          <line x1="12" y1="8" x2="12.01" y2="8"></line>
        </svg>
        <span>Le lien reste valide 1 heure et ne sert qu'une seule fois.</span>
      </div>

      <div class="footer-links">
        <a href="<?= url('/auth/connexion') ?>" class="link-back-connexion">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="19" y1="12" x2="5" y2="12"></line>
            <polyline points="12 19 5 12 12 5"></polyline>
          </svg>
          Retour à la connexion
        </a>
        <p class="copyright">Tous droits réservés - Connect'Academia</p>
      </div>

    </div>
  </div>

</div>

<style>
/* ── Page mot de passe oublié — tokens Connect'Academia ─────── */
.forgot-hero-icon {
  width: 56px;
  height: 56px;
  margin: 0 auto 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-lg, 16px);
  background: var(--purple-tint, #F3EFFF);
  color: var(--purple-neon, #8B52FA);
  box-shadow: 0 6px 18px var(--purple-glow-20, rgba(139,82,250,0.20));
}
.forgot-lead {
  color: #6b7280;
  font-size: 14px;
  line-height: 1.6;
  text-align: center;
  margin: -24px 0 28px;
}
.btn-forgot {
  width: 100%;
  padding: 14px 24px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  background: var(--purple-neon, #8B52FA);
  color: #fff;
  border: none;
  border-radius: var(--radius-md, 12px);
  font-family: 'Inter', var(--font-sans, sans-serif);
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  box-shadow: 0 8px 24px var(--purple-glow-30, rgba(139,82,250,0.30));
  transition: background 0.2s ease, transform 0.12s ease, box-shadow 0.2s ease;
}
.btn-forgot:hover  { background: var(--purple-hover, #9D6EFB); transform: translateY(-1px); }
.btn-forgot:active { background: var(--purple-press, #7440D9); transform: scale(0.98); }
.btn-forgot:disabled { opacity: 0.65; cursor: not-allowed; transform: none; box-shadow: none; }
.btn-forgot svg { flex-shrink: 0; }

.forgot-note {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 18px 0 6px;
  padding: 11px 14px;
  border-radius: var(--radius-md, 12px);
  background: var(--purple-tint, #F3EFFF);
  color: #6d5aa8;
  font-size: 12.5px;
  line-height: 1.5;
  text-align: left;
}
.forgot-note svg { flex-shrink: 0; color: var(--purple-neon, #8B52FA); }

.link-back-connexion {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: #6b7280;
  font-size: 13px;
  font-weight: 600;
  text-decoration: none;
  transition: color 0.2s ease, transform 0.2s ease;
}
.link-back-connexion:hover { color: var(--purple-neon, #8B52FA); transform: translateX(-3px); }
.link-back-connexion svg { transition: transform 0.2s ease; }

/* Le loader tourne pendant l'envoi */
@keyframes forgot-spin { to { transform: rotate(360deg); } }
.btn-forgot .spinning { animation: forgot-spin 0.8s linear infinite; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var form      = document.getElementById('forgot-form');
  var submitBtn = document.getElementById('submit-btn');
  var btnLabel  = document.getElementById('btn-label');
  var btnIcon   = document.getElementById('btn-icon');

  if (form) {
    form.addEventListener('submit', function () {
      submitBtn.disabled = true;
      if (btnLabel) btnLabel.textContent = 'Envoi en cours…';
      if (btnIcon) {
        btnIcon.innerHTML = '<line x1="12" y1="2" x2="12" y2="6"></line><line x1="12" y1="18" x2="12" y2="22"></line><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line><line x1="2" y1="12" x2="6" y2="12"></line><line x1="18" y1="12" x2="22" y2="12"></line><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line>';
        btnIcon.classList.add('spinning');
      }
    });
  }

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
