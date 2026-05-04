<div class="admin-auth-card">
    <div class="admin-auth-right" style="width: 100%; max-width: 450px; margin: 0 auto;">
        <div class="admin-auth-badge">Sécurisation</div>
        
        <h1>Nouveau mot de passe</h1>
        
        <?php if (isset($email) && !empty($email)): ?>
            <p class="auth-desc">
                Réinitialisation pour le compte : <br>
                <strong style="color: #8C52FF;"><?= e($email) ?></strong>
            </p>
        <?php else: ?>
            <p class="auth-desc">Choisissez un mot de passe robuste pour votre compte.</p>
        <?php endif; ?>

        <?php 
        $flashError = \Core\Session::flash('error');
        if ($flashError): 
        ?>
            <div style="background: rgba(255, 69, 58, 0.1); border: 1px solid #ff453a; color: #ff453a; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; display: flex; align-items: center; gap: 10px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <?= e($flashError) ?>
            </div>
        <?php endif; ?>

        <form action="<?= url('/admin/reset-password') ?>" method="POST">
            <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
            
            <input type="hidden" name="token" value="<?= isset($token) ? e($token) : '' ?>">

            <div class="admin-field">
                <div class="admin-input-wrap">
                    <span class="field-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                    </span>
                    <input type="password" name="password" class="admin-input" placeholder="Nouveau mot de passe" required minlength="8" autofocus autocomplete="new-password">
                </div>
            </div>

            <div class="admin-field">
                <div class="admin-input-wrap">
                    <span class="field-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 6L9 17l-5-5"/>
                        </svg>
                    </span>
                    <input type="password" name="confirm_password" class="admin-input" placeholder="Confirmer le mot de passe" required autocomplete="new-password">
                </div>
            </div>

            <button type="submit" class="btn-admin-primary" style="width: 100%; cursor: pointer; padding: 12px; background: #8C52FF; border: none; color: white; border-radius: 8px; font-weight: bold;">
                VALIDER LE CHANGEMENT
            </button>
        </form>

        <div style="margin-top: 20px; text-align: center;">
            <a href="<?= url('/admin/login') ?>" style="color: rgba(255,255,255,0.6); text-decoration: none; font-size: 13px;">
                Retour à la connexion
            </a>
        </div>
    </div>
</div>