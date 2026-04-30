<div class="auth-form">
  <div class="auth-form__header">
    <h1 class="auth-form__title">Nouveau mot de passe</h1>
    <p class="auth-form__subtitle">Choisissez un mot de passe sécurisé (minimum 8 caractères)</p>
  </div>

  <?php if (!empty($errors['general'])): ?>
  <div class="alert alert--error">
    <?= e($errors['general']) ?>
  </div>
  <?php endif; ?>

  <form action="<?= url('/auth/reinitialiser') ?>" method="POST" class="form" novalidate>
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
    <input type="hidden" name="token" value="<?= e($token ?? '') ?>">

    <div class="form__group">
      <label class="form__label" for="password">Nouveau mot de passe</label>
      <div class="form__input-wrap">
        <svg class="form__input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
        <input type="password" id="password" name="password" class="form__input"
               placeholder="Minimum 8 caractères" autocomplete="new-password" required>
        <button type="button" class="form__input-toggle" data-toggle="password">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="icon-eye">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
          </svg>
        </button>
      </div>
    </div>

    <div class="form__group">
      <label class="form__label" for="password_confirmation">Confirmer le mot de passe</label>
      <div class="form__input-wrap">
        <svg class="form__input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="20 6 9 17 4 12"/>
        </svg>
        <input type="password" id="password_confirmation" name="password_confirmation" class="form__input"
               placeholder="••••••••" autocomplete="new-password" required>
      </div>
    </div>

    <button type="submit" class="btn btn--primary btn--full btn--lg">Réinitialiser</button>
  </form>

  <p class="auth-form__switch">
    <a href="<?= url('/auth/connexion') ?>">← Retour à la connexion</a>
  </p>
</div>
