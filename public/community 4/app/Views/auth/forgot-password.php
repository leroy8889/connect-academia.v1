<?php
use Core\Session;
$errors  = Session::getFlash('errors', []);
$success = Session::getFlash('success');
?>

<div class="auth-page">
    <div class="auth-page__form-panel">
        <div class="glass-orb glass-orb--1"></div>
        <div class="glass-orb glass-orb--2"></div>

        <div class="auth-form-container">
            <div class="auth-form__header">
                <h2 class="auth-form__title">Mot de passe oublié</h2>
                <p class="auth-form__subtitle">Saisissez votre email pour recevoir un lien de réinitialisation.</p>
            </div>

            <?php if ($success): ?>
                <div class="auth-alert auth-alert--success">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors['general'])): ?>
                <div class="auth-alert auth-alert--error">
                    <?= htmlspecialchars($errors['general']) ?>
                </div>
            <?php endif; ?>

            <form action="<?= url('/forgot-password') ?>" method="POST" novalidate>
                <input type="hidden" name="_csrf_token" value="<?= Session::getCsrfToken() ?>">

                <div class="form-group">
                    <label class="form-label" for="email">Adresse e-mail</label>
                    <div class="form-input-wrapper">
                        <input type="email" id="email" name="email"
                               class="form-input <?= !empty($errors['email']) ? 'form-input--error' : '' ?>"
                               placeholder="alex@ecole.fr" required>
                    </div>
                    <?php if (!empty($errors['email'])): ?>
                        <span class="form-error"><?= htmlspecialchars($errors['email']) ?></span>
                    <?php endif; ?>
                </div>

                <button type="submit" class="auth-form__submit">Envoyer le lien</button>

                <p class="auth-form__alt-link">
                    <a href="<?= url('/login') ?>">← Retour à la connexion</a>
                </p>
            </form>
        </div>
    </div>

    <div class="auth-page__branding">
        <div class="auth-branding__orb auth-branding__orb--1"></div>
        <div class="auth-branding__orb auth-branding__orb--2"></div>
        <div class="auth-branding__orb auth-branding__orb--3"></div>
        <div class="auth-branding__content">
            <div class="auth-branding__illustration">
                <svg viewBox="0 0 52 52" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M26 8L6 20l20 12 20-12L26 8z" fill="rgba(255,255,255,0.9)"/>
                    <path d="M6 32l20 12 20-12" stroke="rgba(255,255,255,0.9)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                </svg>
            </div>
            <h1 class="auth-branding__title">StudyLink</h1>
            <p class="auth-branding__subtitle">Réinitialisez votre mot de passe en toute sécurité.</p>
        </div>
    </div>
</div>
