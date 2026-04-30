<?php use Core\Session; ?>

<div class="admin-login">
    <div class="admin-login__card">
        <div class="admin-login__logo">
            <div class="admin-login__logo-icon">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                    <rect width="40" height="40" rx="10" fill="#8B52FA"/>
                    <path d="M11 20L17 26L29 14" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div class="admin-login__logo-text">
                <span class="admin-login__brand">StudyLink</span>
                <span class="admin-login__subtitle">ADMIN CONSOLE</span>
            </div>
        </div>

        <h1 class="admin-login__title">Administration</h1>
        <p class="admin-login__desc">Connectez-vous pour accéder au panneau d'administration</p>

        <?php if ($error = Session::getFlash('errors')): ?>
            <div class="admin-login__error">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="15" y1="9" x2="9" y2="15"/>
                    <line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
                <span><?= htmlspecialchars($error['general'] ?? 'Erreur de connexion') ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['reason']) && $_GET['reason'] === 'suspended'): ?>
            <div class="admin-login__error">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="15" y1="9" x2="9" y2="15"/>
                    <line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
                <span>Ce compte a été suspendu.</span>
            </div>
        <?php endif; ?>

        <form class="admin-login__form" action="<?= url('/admin/login') ?>" method="POST">
            <input type="hidden" name="_csrf_token" value="<?= Session::getCsrfToken() ?>">

            <div class="admin-login__field">
                <label for="admin-email">Adresse email</label>
                <div class="admin-login__input-wrap">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <polyline points="22,6 12,13 2,6"/>
                    </svg>
                    <input type="email" id="admin-email" name="email" placeholder="admin@studylink.fr"
                           value="<?= htmlspecialchars(Session::getFlash('old')['email'] ?? '') ?>" required autofocus>
                </div>
            </div>

            <div class="admin-login__field">
                <label for="admin-password">Mot de passe</label>
                <div class="admin-login__input-wrap">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    <input type="password" id="admin-password" name="password" placeholder="••••••••" required>
                    <button type="button" class="admin-login__toggle-pw" id="toggle-password" aria-label="Afficher le mot de passe">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="admin-login__submit">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                    <polyline points="10 17 15 12 10 7"/>
                    <line x1="15" y1="12" x2="3" y2="12"/>
                </svg>
                Se connecter
            </button>
        </form>

        <div class="admin-login__footer">
            <a href="<?= url('/login') ?>" class="admin-login__back-link">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Retour au site principal
            </a>
        </div>
    </div>

    <div class="admin-login__bg-pattern"></div>
</div>

