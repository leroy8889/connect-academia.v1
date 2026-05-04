<div class="main-container" style="display: flex; min-height: 100vh; background: #0f0f12; font-family: 'Inter', sans-serif;">

  <div class="image-section" style="flex: 1; background: url('../../../assets/images/Images en 409x610 - Page de Connexion 2.png') no-repeat center center; background-size: cover; border-right: 1px solid rgba(255, 255, 255, 0.05);"></div>

  <div class="form-section" style="flex: 1; display: flex; align-items: center; justify-content: center; padding: 40px; position: relative;">
    
    <div style="position: absolute; top: 10%; right: 10%; width: 200px; height: 200px; background: rgba(140, 82, 255, 0.1); filter: blur(60px); border-radius: 50%; pointer-events: none;"></div>

    <div class="auth-form-wrapper" style="width: 100%; max-width: 420px; background: #1c1c1e; border-radius: 24px; padding: 40px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); border: 1px solid rgba(255, 255, 255, 0.08);">
      
      <div style="text-align: center; margin-bottom: 25px;">
        <img src="<?= asset('images/logo.jpeg') ?>" alt="Logo Connect'Academia" style="max-width: 120px; height: auto; border-radius: 12px;">
      </div>

      <div style="text-align: center; margin-bottom: 32px;">
        <div style="display: inline-flex; align-items: center; background: rgba(140, 82, 255, 0.1); color: #a855f7; padding: 6px 14px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 16px; border: 1px solid rgba(140, 82, 255, 0.2);">
          SÉCURITÉ CONNECT'ACADEMIA
        </div>
        <h1 style="color: #ffffff; font-size: 26px; margin: 0 0 12px 0; font-weight: 800; letter-spacing: -0.5px;">Nouveau mot de passe</h1>
        <p style="color: #94a3b8; font-size: 14px; line-height: 1.6; margin: 0;">
          Choisissez un mot de passe sécurisé (minimum 8 caractères).
        </p>
      </div>

      <?php if (!empty($errors['general'])): ?>
      <div style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 16px; padding: 16px; margin-bottom: 24px; text-align: center;">
        <div style="color: #fca5a5; font-size: 13.5px; font-weight: 500;"><?= e($errors['general']) ?></div>
      </div>
      <?php endif; ?>

      <form action="<?= url('/auth/reinitialiser') ?>" method="POST" style="display: flex; flex-direction: column; gap: 20px;">
        <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
        <input type="hidden" name="token" value="<?= e($token ?? '') ?>">

        <div style="display: flex; flex-direction: column; gap: 8px;">
          <label style="color: #94a3b8; font-size: 12px; font-weight: 600; margin-left: 4px; text-transform: uppercase;">Nouveau mot de passe</label>
          <div style="position: relative; display: flex; align-items: center;">
            <span style="position: absolute; left: 16px; color: rgba(255,255,255,0.3);">
              <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </span>
            <input type="password" name="password" placeholder="••••••••" required
                   style="width: 100%; height: 54px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; padding: 0 16px 0 48px; color: #ffffff; outline: none; font-size: 15px; transition: all 0.3s ease;">
          </div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 8px;">
          <label style="color: #94a3b8; font-size: 12px; font-weight: 600; margin-left: 4px; text-transform: uppercase;">Confirmer le mot de passe</label>
          <div style="position: relative; display: flex; align-items: center;">
            <span style="position: absolute; left: 16px; color: rgba(255,255,255,0.3);">
              <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            </span>
            <input type="password" name="password_confirmation" placeholder="••••••••" required
                   style="width: 100%; height: 54px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; padding: 0 16px 0 48px; color: #ffffff; outline: none; font-size: 15px; transition: all 0.3s ease;">
          </div>
        </div>

        <button type="submit" class="btn-submit-premium">
          RÉINITIALISER LE MOT DE PASSE
        </button>
      </form>

      <div style="margin-top: 32px; padding-top: 24px; border-top: 1px solid rgba(255,255,255,0.06); text-align: center;">
        <a href="<?= url('/auth/connexion') ?>" class="link-back-premium">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10 19l-7-7m0 0l7-7m-7 7h18" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Retour à la connexion
        </a>
      </div>

      <p style="text-align: center; color: rgba(255,255,255,0.2); font-size: 11px; margin-top: 24px; font-weight: 500;">Tous droits réservés - Connect'Academia</p>
    </div>
  </div>

  <style>
    .btn-submit-premium { width: 100%; height: 54px; background: #8C52FF; color: #ffffff; border: none; border-radius: 14px; font-weight: 700; font-size: 14px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; transition: all 0.3s ease; box-shadow: 0 10px 25px rgba(140, 82, 255, 0.3); }
    .btn-submit-premium:hover { background: #7a41eb; transform: translateY(-2px); box-shadow: 0 15px 30px rgba(140, 82, 255, 0.4); }
    .link-back-premium { color: rgba(255,255,255,0.4); text-decoration: none; font-size: 13px; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; }
    .link-back-premium:hover { color: #8C52FF; transform: translateX(-3px); }
    input:focus { border-color: #8C52FF !important; background: rgba(140, 82, 255, 0.05) !important; box-shadow: 0 0 0 4px rgba(140, 82, 255, 0.1); }
    @media (max-width: 900px) { .image-section { display: none; } .form-section { flex: none; width: 100%; padding: 20px; } }
  </style>
</div>