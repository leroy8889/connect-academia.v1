<div class="main-container">

  <!-- Image gauche fixe -->
  <div class="image-section image-section--connexion"></div>

  <!-- Formulaire droit -->
  <div class="form-section">
    <div class="form-wrapper">

      <!-- Logo -->
      <div class="logo-container">
        <img src="<?= asset('images/logo.jpeg') ?>" alt="Logo Connect'Academia">
      </div>

      <div class="title-small">Se connecter à</div>
      <div class="title-main">MON ESPACE</div>

      <?php if (!empty($success)): ?>
      <div class="auth-alert auth-alert--success">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
        </svg>
        <?= e($success) ?>
      </div>
      <?php endif; ?>

      <?php if (!empty($errors['general'])): ?>
      <div class="auth-alert auth-alert--error">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
        </svg>
        <?= e($errors['general']) ?>
      </div>
      <?php endif; ?>

      <form action="<?= url('/auth/connexion') ?>" method="POST" novalidate>
        <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">

        <div class="form-group">
          <label for="email">Email <span class="required">*</span></label>
          <input type="email" id="email" name="email"
                 placeholder="Entrez votre email"
                 value="<?= e($old['email'] ?? '') ?>"
                 autocomplete="email" required>
          <?php if (!empty($errors['email'])): ?>
          <span class="field-error"><?= e($errors['email']) ?></span>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label for="password-input">Mot de passe <span class="required">*</span></label>
          <div class="input-wrapper">
            <input type="password" id="password-input" name="password"
                   placeholder="Entrez votre mot de passe"
                   class="has-eye"
                   autocomplete="current-password" required>
            <button type="button" class="eye-icon" id="eye-icon" onclick="togglePassword()" aria-label="Voir le mot de passe">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>
          <?php if (!empty($errors['password'])): ?>
          <span class="field-error"><?= e($errors['password']) ?></span>
          <?php endif; ?>
        </div>

        <div class="toggle-container">
          <label class="switch">
            <input type="checkbox" id="remember-me" name="remember_me" value="1" checked>
            <span class="slider"></span>
          </label>
          <label for="remember-me" class="toggle-label">Se souvenir de moi</label>
        </div>

        <button type="submit" class="btn-submit">Se connecter</button>
      </form>

      <div class="footer-links">
        <a href="<?= url('/auth/mot-de-passe-oublie') ?>" class="link-gray">Mot de passe oublié ?</a>
        <p class="signup-text">Pas encore membre ? <a href="<?= url('/auth/inscription') ?>">S'inscrire</a></p>
        <p class="copyright">Tous droits réservés - Connect'Academia</p>
      </div>

    </div>
  </div>

</div>

<script>
function togglePassword() {
    const input = document.getElementById('password-input');
    const btn   = document.getElementById('eye-icon');
    if (input.type === 'password') {
        input.type = 'text';
        btn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
    } else {
        input.type = 'password';
        btn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
    }
}
</script>
