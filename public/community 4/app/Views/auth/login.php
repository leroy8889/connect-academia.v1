<?php
use Core\Session;
$errors = Session::getFlash('errors', []);
$old = Session::getFlash('old', []);
$success = Session::getFlash('success');
?>

<div class="auth-page">
    <!-- ── LEFT: Form Panel ────────────────────── -->
    <div class="auth-page__form-panel">
        <!-- Orbes décoratifs glass -->
        <div class="glass-orb glass-orb--1"></div>
        <div class="glass-orb glass-orb--2"></div>

        <div class="auth-form-container">
            <!-- Tabs -->
            <div class="auth-tabs">
                <a href="<?= url('/login') ?>" class="auth-tabs__tab auth-tabs__tab--active">Connexion</a>
                <a href="<?= url('/register') ?>" class="auth-tabs__tab">Inscription</a>
            </div>

            <div class="auth-form__header">
                <h2 class="auth-form__title">Bon retour !</h2>
                <p class="auth-form__subtitle">Accédez à votre espace StudyLink et retrouvez votre communauté.</p>
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
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
                    </svg>
                    <?= htmlspecialchars($errors['general']) ?>
                </div>
            <?php endif; ?>

            <form action="<?= url('/login') ?>" method="POST" id="login-form" novalidate>
                <input type="hidden" name="_csrf_token" value="<?= Session::getCsrfToken() ?>">

                <!-- Email -->
                <div class="form-group">
                    <label class="form-label" for="email">Adresse e-mail</label>
                    <div class="form-input-wrapper">
                        <span class="form-input-icon-left">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                            </svg>
                        </span>
                        <input type="email" id="email" name="email"
                               class="form-input form-input--with-icon"
                               placeholder="alex@ecole.fr"
                               value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                               required>
                    </div>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label class="form-label" for="password">Mot de passe</label>
                    <div class="form-input-wrapper">
                        <span class="form-input-icon-left">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                        </span>
                        <input type="password" id="password" name="password"
                               class="form-input form-input--with-icon"
                               placeholder="••••••••"
                               required>
                        <button type="button" class="form-input-icon" data-toggle-password="password" tabindex="-1">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Remember + Forgot -->
                <div class="auth-form__footer">
                    <label class="form-checkbox">
                        <input type="checkbox" name="remember">
                        <span>Se souvenir de moi</span>
                    </label>
                    <a href="<?= url('/forgot-password') ?>" class="auth-form__forgot">Mot de passe oublié ?</a>
                </div>

                <!-- Submit -->
                <button type="submit" class="auth-form__submit">Se connecter</button>

                <p class="auth-form__alt-link">
                    Pas encore de compte ? <a href="<?= url('/register') ?>">S'inscrire</a>
                </p>
            </form>
        </div>
    </div>

    <!-- ── RIGHT: Branding Panel ───────────────── -->
    <div class="auth-page__branding">
        <!-- Orbes décoratifs -->
        <div class="auth-branding__orb auth-branding__orb--1"></div>
        <div class="auth-branding__orb auth-branding__orb--2"></div>
        <div class="auth-branding__orb auth-branding__orb--3"></div>

        <div class="auth-branding__content">
            <!-- Logo glass icon -->
            <div class="auth-branding__illustration">
                <svg viewBox="0 0 52 52" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M26 8L6 20l20 12 20-12L26 8z" fill="rgba(255,255,255,0.9)"/>
                    <path d="M6 32l20 12 20-12" stroke="rgba(255,255,255,0.9)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                    <path d="M6 26l20 12 20-12" stroke="rgba(255,255,255,0.5)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                </svg>
            </div>

            <h1 class="auth-branding__title">StudyLink</h1>
            <p class="auth-branding__subtitle">
                Le réseau social bienveillant pour l'éducation. Collaboration et partage entre étudiants et enseignants.
            </p>

            <!-- Feature badges glass -->
            <div class="auth-branding__features">
                <div class="auth-branding__feature">
                    <span class="auth-branding__feature-icon">✨</span>
                    Apprentissage collaboratif
                </div>
                <div class="auth-branding__feature">
                    <span class="auth-branding__feature-icon">🛡️</span>
                    Environnement sécurisé
                </div>
                <div class="auth-branding__feature">
                    <span class="auth-branding__feature-icon">🎓</span>
                    Communauté éducative
                </div>
            </div>

            <div class="auth-branding__dots">
                <span class="auth-branding__dot auth-branding__dot--active"></span>
                <span class="auth-branding__dot"></span>
                <span class="auth-branding__dot"></span>
            </div>
        </div>
    </div>
</div>
