<div class="auth-form">
  <div class="auth-form__header">
    <h1 class="auth-form__title">Mot de passe oublié</h1>
    <p class="auth-form__subtitle">Entrez votre email pour recevoir un lien de réinitialisation</p>
  </div>

  <?php if (!empty($success)): ?>
  <div class="alert alert--success">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
    </svg>
    <?= e($success) ?>
  </div>
  <?php endif; ?>

  <form action="<?= url('/auth/mot-de-passe-oublie') ?>" method="POST" class="form" novalidate>
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">

    <div class="form__group <?= !empty($errors['email']) ? 'form__group--error' : '' ?>">
      <label class="form__label" for="email">Adresse email</label>
      <div class="form__input-wrap">
        <svg class="form__input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
          <polyline points="22,6 12,13 2,6"/>
        </svg>
        <input type="email" id="email" name="email" class="form__input"
               placeholder="votre@email.com" autocomplete="email" required>
      </div>
      <?php if (!empty($errors['email'])): ?>
      <span class="form__error"><?= e($errors['email']) ?></span>
      <?php endif; ?>
    </div>

    <button type="submit" class="btn btn--primary btn--full btn--lg">Envoyer le lien</button>
  </form>

  <p class="auth-form__switch">
    <a href="<?= url('/auth/connexion') ?>">← Retour à la connexion</a>
  </p>
</div>
