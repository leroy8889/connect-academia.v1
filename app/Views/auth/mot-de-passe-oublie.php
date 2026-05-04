<?php
use Core\Session;

// On récupère les données flash une seule fois ici
$errors  = Session::getFlash('errors', []);
$success = Session::getFlash('success');
$old     = Session::getFlash('old', []);
?>

<div class="main-container" style="display: flex; min-height: 100vh; background: #0f0f12; font-family: 'Inter', sans-serif;">

  <div class="image-section" style="flex: 1; background: url('../../../assets/images/Images en 409x610 - Page de Connexion 2.png') no-repeat center center; background-size: cover; border-right: 1px solid rgba(255, 255, 255, 0.05);"></div>

  <div class="form-section" style="flex: 1; display: flex; align-items: center; justify-content: center; padding: 40px; position: relative;">
    
    <div style="position: absolute; top: 10%; right: 10%; width: 200px; height: 200px; background: rgba(140, 82, 255, 0.1); filter: blur(60px); border-radius: 50%; pointer-events: none;"></div>

    <div class="auth-form-wrapper" style="width: 100%; max-width: 420px; background: #1c1c1e; border-radius: 24px; padding: 40px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); border: 1px solid rgba(255, 255, 255, 0.08);">
      
      <div style="text-align: center; margin-bottom: 32px;">
        <div style="display: inline-flex; align-items: center; background: rgba(140, 82, 255, 0.1); color: #a855f7; padding: 6px 14px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 16px; border: 1px solid rgba(140, 82, 255, 0.2);">
          SÉCURITÉ CONNECT'ACADEMIA
        </div>
        <h1 style="color: #ffffff; font-size: 26px; margin: 0 0 12px 0; font-weight: 800; letter-spacing: -0.5px;">Mot de passe oublié</h1>
        <p style="color: #94a3b8; font-size: 14px; line-height: 1.6; margin: 0;">
          Saisissez votre email pour recevoir un lien de réinitialisation.
        </p>
      </div>

      <?php if ($success): ?>
      <div id="alert-success" style="background: rgba(34, 197, 94, 0.08); border: 1px solid rgba(34, 197, 94, 0.2); border-radius: 16px; padding: 16px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; animation: fadeIn 0.4s ease;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#86efac" stroke-width="2">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
        </svg>
        <div style="color: #86efac; font-size: 13.5px; font-weight: 500;">
          <?= htmlspecialchars($success) ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if (!empty($errors['general'])): ?>
      <div style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 16px; padding: 16px; margin-bottom: 24px; text-align: center; animation: fadeIn 0.4s ease;">
        <div style="color: #fca5a5; font-size: 13.5px; font-weight: 500;">
            <?= htmlspecialchars($errors['general']) ?>
        </div>
      </div>
      <?php endif; ?>

      <form id="forgot-password-form" action="<?= url('/auth/mot-de-passe-oublie') ?>" method="POST" novalidate style="display: flex; flex-direction: column; gap: 24px;">
        <input type="hidden" name="_csrf_token" value="<?= Session::getCsrfToken() ?>">

        <div style="display: flex; flex-direction: column; gap: 8px;">
          <label style="color: #94a3b8; font-size: 12px; font-weight: 600; margin-left: 4px; text-transform: uppercase; letter-spacing: 0.5px;">Adresse email professionnel</label>
          <div style="position: relative; display: flex; align-items: center;">
            <span style="position: absolute; left: 16px; color: <?= !empty($errors['email']) ? '#fca5a5' : 'rgba(255,255,255,0.3)' ?>;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
            <input type="email" id="email" name="email" placeholder="nom@etablissement.com" 
                   value="<?= htmlspecialchars($old['email'] ?? '') ?>" required autofocus
                   style="width: 100%; height: 54px; background: rgba(255,255,255,0.03); border: 1px solid <?= !empty($errors['email']) ? '#ef4444' : 'rgba(255,255,255,0.1)' ?>; border-radius: 14px; padding: 0 16px 0 48px; color: #ffffff; outline: none; font-size: 15px; transition: all 0.3s ease;">
          </div>
          <?php if (!empty($errors['email'])): ?>
            <span style="color: #fca5a5; font-size: 12px; margin-left: 4px; font-weight: 500;">
                <?= htmlspecialchars($errors['email']) ?>
            </span>
          <?php endif; ?>
        </div>

        <button type="submit" id="submit-btn" class="btn-submit-premium">
          <span class="btn-text">ENVOYER LE LIEN</span>
        </button>
      </form>

      <div style="margin-top: 32px; padding-top: 24px; border-top: 1px solid rgba(255,255,255,0.06); text-align: center;">
        <a href="<?= url('/auth/connexion') ?>" class="link-back-premium">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-right: 8px;">
              <path d="M10 19l-7-7m0 0l7-7m-7 7h18" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          Retour à la connexion
        </a>
      </div>

      <p style="text-align: center; color: rgba(255,255,255,0.2); font-size: 11px; margin-top: 24px; font-weight: 500;">Tous droits réservés - Connect'Academia</p>
    </div>
  </div>

  <style>
    @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    
    .btn-submit-premium { 
        width: 100%; height: 54px; background: #8C52FF; color: #ffffff; border: none; border-radius: 14px; 
        font-weight: 700; font-size: 14px; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
        box-shadow: 0 10px 25px rgba(140, 82, 255, 0.3); letter-spacing: 0.5px;
        position: relative; overflow: hidden;
    }
    .btn-submit-premium:hover { background: #7a41eb; transform: translateY(-2px); box-shadow: 0 15px 30px rgba(140, 82, 255, 0.4); }
    .btn-submit-premium:disabled { background: #4b2c85; cursor: not-allowed; transform: none; box-shadow: none; opacity: 0.7; }
    
    .link-back-premium { color: rgba(255,255,255,0.4); text-decoration: none; font-size: 13px; font-weight: 500; transition: all 0.2s; display: inline-flex; align-items: center; }
    .link-back-premium:hover { color: #8C52FF; transform: translateX(-3px); }
    
    input:focus { border-color: #8C52FF !important; background: rgba(140, 82, 255, 0.05) !important; box-shadow: 0 0 0 4px rgba(140, 82, 255, 0.1); }
    
    @media (max-width: 900px) { .image-section { display: none; } .form-section { flex: none; width: 100%; padding: 20px; } }
  </style>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('forgot-password-form');
        const submitBtn = document.getElementById('submit-btn');
        const btnText = submitBtn.querySelector('.btn-text');

        // Animation de soumission
        form.addEventListener('submit', function() {
            submitBtn.disabled = true;
            btnText.innerHTML = '<svg class="spinner" width="20" height="20" viewBox="0 0 50 50" style="animation: rotate 2s linear infinite; margin: auto;"><circle cx="25" cy="25" r="20" fill="none" stroke="currentColor" stroke-width="5" stroke-dasharray="90,150" stroke-dashoffset="0" style="stroke-linecap: round;"></circle></svg>';
        });

        // Suppression automatique de l'alerte succès après 8 secondes
        const successAlert = document.getElementById('alert-success');
        if (successAlert) {
            setTimeout(() => {
                successAlert.style.transition = "opacity 0.5s ease";
                successAlert.style.opacity = "0";
                setTimeout(() => successAlert.remove(), 500);
            }, 8000);
        }
    });
  </script>

  <style>
    @keyframes rotate { 100% { transform: rotate(360deg); } }
  </style>

</div>