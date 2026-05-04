<div style="display: flex; align-items: center; justify-content: center; min-height: 100vh; width: 100%; background: #0f0f12; font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; margin: 0; padding: 20px; box-sizing: border-box;">

  <div style="width: 100%; max-width: 420px; background: #1c1c1e; border-radius: 24px; padding: 40px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); border: 1px solid rgba(255, 255, 255, 0.08); position: relative; overflow: hidden;">
    
    <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: rgba(140, 82, 255, 0.1); filter: blur(40px); border-radius: 50%;"></div>

    <div style="text-align: center; margin-bottom: 32px;">
      <div style="display: inline-flex; align-items: center; background: rgba(140, 82, 255, 0.1); color: #a855f7; padding: 6px 14px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 16px; border: 1px solid rgba(140, 82, 255, 0.2);">
        SÉCURITÉ ADMIN
      </div>
      <h1 style="color: #ffffff; font-size: 26px; margin: 0 0 12px 0; font-weight: 800; letter-spacing: -0.5px;">Mot de passe oublié</h1>
      <p style="color: #94a3b8; font-size: 14px; line-height: 1.6; margin: 0;">
        Un lien de récupération vous sera envoyé si votre adresse est reconnue.
      </p>
    </div>

    <?php if ($error = \Core\Session::getFlash('error')): ?>
      <div style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 16px; padding: 16px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
        <div style="color: #ef4444;">
          <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <div style="color: #fca5a5; font-size: 13.5px; font-weight: 500;"><?= e($error) ?></div>
      </div>
      
      <a href="<?= url('/admin/forgot-password') ?>" style="display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; height: 52px; background: #8C52FF; color: #ffffff; text-decoration: none; border-radius: 14px; font-weight: 600; font-size: 14px; transition: all 0.2s ease; box-shadow: 0 8px 20px rgba(140, 82, 255, 0.25);">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Réessayer avec un autre email
      </a>
    <?php endif; ?>

    <?php if ($success = \Core\Session::getFlash('success')): ?>
      <div style="background: rgba(34, 197, 94, 0.08); border: 1px solid rgba(34, 197, 94, 0.2); border-radius: 16px; padding: 16px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
        <div style="color: #22c55e;">
          <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <div style="color: #86efac; font-size: 13.5px; font-weight: 500;"><?= e($success) ?></div>
      </div>
    <?php endif; ?>

    <?php if (!$success && !$error): ?>
      <form action="<?= url('/admin/forgot-password') ?>" method="POST" style="display: flex; flex-direction: column; gap: 20px;">
        <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
        
        <div style="display: flex; flex-direction: column; gap: 8px;">
          <label style="color: #94a3b8; font-size: 12px; font-weight: 600; margin-left: 4px;">ADRESSE E-MAIL</label>
          <div style="position: relative; display: flex; align-items: center;">
            <span style="position: absolute; left: 16px; color: rgba(255,255,255,0.3);">
              <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            <input type="email" name="email" placeholder="admin@connect-academia.ga" required autofocus 
                   style="width: 100%; height: 54px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; padding: 0 16px 0 48px; color: #ffffff; outline: none; font-size: 15px; transition: all 0.3s ease;">
          </div>
        </div>

        <button type="submit" style="width: 100%; height: 54px; background: #8C52FF; color: #ffffff; border: none; border-radius: 14px; font-weight: 700; font-size: 14px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; transition: all 0.3s ease; box-shadow: 0 10px 25px rgba(140, 82, 255, 0.3);">
          ENVOYER LE LIEN
          <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M14 5l7 7m0 0l-7 7m7-7H3" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
      </form>
    <?php endif; ?>

    <div style="margin-top: 32px; padding-top: 24px; border-top: 1px solid rgba(255,255,255,0.06); text-align: center;">
      <a href="<?= url('/admin/login') ?>" style="color: rgba(255,255,255,0.4); text-decoration: none; font-size: 13px; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; transition: color 0.2s;">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10 19l-7-7m0 0l7-7m-7 7h18" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Retour à la connexion
      </a>
    </div>

  </div>
</div>

<style>
  /* Ajout d'effets interactifs au survol */
  input:focus { border-color: #8C52FF !important; background: rgba(140, 82, 255, 0.05) !important; box-shadow: 0 0 0 4px rgba(140, 82, 255, 0.1); }
  button:hover { background: #7a41eb !important; transform: translateY(-1px); }
  a:hover { color: #ffffff !important; }
</style>