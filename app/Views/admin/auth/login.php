<?php
$is2fa             = !empty($_SESSION['admin_2fa_pending']);
$error             = \Core\Session::getFlash('error', '');
$success           = \Core\Session::getFlash('success');
$loginLocked       = (bool) \Core\Session::getFlash('login_locked', false);
$waitMin           = (int)  \Core\Session::getFlash('wait_min', 0);
$waitSecs          = (int)  \Core\Session::getFlash('wait_secs', 0);
$attemptsUsed      = (int)  \Core\Session::getFlash('login_attempts_used', 0);
$attemptsRemaining = (int)  \Core\Session::getFlash('login_attempts_remaining', 0);

// QR Code en mode 2FA
$qr_url = "";
if ($is2fa) {
    $otp_code = $_SESSION['admin_2fa_pending']['otp'] ?? '000000';
    $qr_url = "https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl=" . urlencode("OTP:$otp_code") . "&choe=UTF-8";
}
?>

<style>
    /* QR Code — non défini dans admin.css */
    .qr-container { background: #fff; padding: 12px; border-radius: 14px; margin: 20px auto; width: fit-content; border: 1px solid rgba(139,82,250,0.18); box-shadow: 0 4px 16px rgba(139,82,250,0.08); }
    .qr-container img { width: 130px; height: 130px; display: block; }
    .qr-label { font-family: var(--font-mid); color: #2D1B69; font-size: 10px; text-align: center; margin-top: 6px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; }
    /* label champs formulaire */
    .field-label { display: block; font-family: var(--font-mid); font-size: 12px; font-weight: 600; color: var(--txt-m); margin-bottom: 7px; letter-spacing: 0.02em; }
    .hidden { display: none; }

    /* ── Attempt dots & lockout card ─────────────────────────── */
    .admin-alert { align-items: flex-start; }
    .admin-alert-body { display: flex; flex-direction: column; gap: 6px; flex: 1; }
    .admin-alert-body strong { font-size: 13px; font-weight: 700; display: block; }
    .admin-alert-body span  { font-size: 12px; line-height: 1.4; }

    .attempt-dots-wrap { display: flex; flex-direction: column; gap: 5px; margin-top: 4px; }
    .attempt-dots      { display: flex; align-items: center; gap: 5px; }
    .attempt-dot {
        width: 11px; height: 11px; border-radius: 50%;
        background: #FECACA; border: 1.5px solid #FCA5A5;
        transition: background .2s, border-color .2s;
        flex-shrink: 0;
    }
    .attempt-dot.used  { background: #DC2626; border-color: #B91C1C; }
    .attempt-dot.last  { background: #991B1B; border-color: #7F1D1D; box-shadow: 0 0 0 2px rgba(220,38,38,.25); }
    .attempt-meta { font-size: 11px; font-weight: 600; color: #B91C1C; }
    .attempt-warn { font-size: 11px; font-weight: 500; color: #991B1B; }

    /* Lockout countdown block */
    .lockout-countdown-wrap {
        display: flex; align-items: center; gap: 6px;
        background: rgba(239,68,68,.08); border: 1px solid #FECACA;
        border-radius: 8px; padding: 7px 12px;
        font-size: 12px; font-weight: 700; color: #991B1B;
        margin-top: 6px; width: 100%; box-sizing: border-box;
    }
    .lockout-countdown-wrap svg { flex-shrink: 0; }
    #lockout-countdown { font-variant-numeric: tabular-nums; letter-spacing: .03em; }
</style>

<div class="admin-auth-card">

  <div class="admin-auth-left">

    <div class="admin-auth-brand">
      <div class="admin-auth-brand-icon">
        <img src="<?= asset('images/logo-officiel.png') ?>" alt="Connect'Academia logo">
      </div>
      <div class="admin-auth-brand-text">
        <strong>Connect'Academia</strong>
        <span>Espace Administrateur</span>
      </div>
    </div>

    <div class="admin-auth-illustration">
      <svg width="200" height="220" viewBox="0 0 200 220" fill="none" xmlns="http://www.w3.org/2000/svg">
        <ellipse cx="100" cy="200" rx="80" ry="16" fill="rgba(139,82,250,0.15)"/>
        <rect x="32" y="92" width="136" height="108" rx="18" fill="#2D1B69"/>
        <rect x="38" y="98" width="124" height="96" rx="14" fill="#1F1248"/>
        <path d="M55 92V62C55 38 145 38 145 62V92" stroke="rgba(196,160,248,0.7)" stroke-width="16" stroke-linecap="round" fill="none"/>
        <g transform="translate(100,146)" stroke="#8B52FA" stroke-linecap="round" fill="none">
          <path d="M0-24C13-24 24-13 24 0C24 13 13 24 0 24C-13 24-24 13-24 0" stroke-width="2.5" stroke-dasharray="4 4"/>
          <path d="M0-16C9-16 16-9 16 0C16 9 9 16 0 16C-9 16-16 9-16 0" stroke-width="2.5"/>
          <path d="M0-8C4-8 8-4 8 0C8 4 4 8 0 8C-4 8-8 4-8 0" stroke-width="2.5"/>
          <circle cx="0" cy="0" r="2" fill="#8B52FA"/>
        </g>
        <circle cx="100" cy="146" r="10" fill="#8B52FA" opacity="0.3"/>
        <g transform="translate(16,155)">
          <ellipse cx="22" cy="50" rx="14" ry="6" fill="rgba(139,82,250,0.2)"/>
          <rect x="14" y="28" width="16" height="18" rx="5" fill="#C4A0F8"/>
          <circle cx="22" cy="20" r="9" fill="#C4A0F8"/>
          <rect x="8" y="44" width="28" height="5" rx="2" fill="#E8D5FF"/>
          <rect x="10" y="39" width="24" height="8" rx="2" fill="#D4B5FF"/>
        </g>
        <g transform="translate(148,155)">
          <ellipse cx="18" cy="50" rx="14" ry="6" fill="rgba(139,82,250,0.2)"/>
          <rect x="10" y="28" width="16" height="18" rx="5" fill="#C4A0F8"/>
          <circle cx="18" cy="20" r="9" fill="#C4A0F8"/>
          <rect x="4" y="44" width="28" height="5" rx="2" fill="#E8D5FF"/>
          <rect x="6" y="39" width="24" height="8" rx="2" fill="#D4B5FF"/>
        </g>
      </svg>
    </div>

    <div class="admin-auth-footer">
      Accès sécurisé · TLS 1.3 · 2FA disponible
    </div>
  </div>

  <div class="admin-auth-right">

    <?php if (!$is2fa): ?>
    <div class="admin-auth-badge">Portail Sécurisé</div>
    <h1>Connexion administrateur</h1>
    <p class="auth-desc">Accédez au tableau de bord pour gérer la plateforme, les utilisateurs et la communauté.</p>

    <?php if ($loginLocked): ?>
      <!-- ── Carte lockout — Design System: status colors (error) ── -->
      <div class="admin-alert admin-alert-error" role="alert">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#B91C1C" stroke-width="2" style="flex-shrink:0;margin-top:1px;">
          <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
        <div class="admin-alert-body">
          <strong>Accès temporairement bloqué</strong>
          <span>Trop de tentatives échouées. Votre accès est suspendu pendant :</span>
          <div class="lockout-countdown-wrap">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <span id="lockout-countdown" data-secs="<?= $waitSecs ?>">
              <?= $waitMin ?>m 00s
            </span>
            restantes avant réactivation
          </div>
          <div class="attempt-dots-wrap">
            <div class="attempt-dots">
              <?php for ($i = 0; $i < 5; $i++): ?>
                <span class="attempt-dot used<?= $i === 4 ? ' last' : '' ?>"></span>
              <?php endfor; ?>
            </div>
            <span class="attempt-meta">5 / 5 tentatives épuisées</span>
          </div>
        </div>
      </div>
    <?php elseif ($error): ?>
      <!-- ── Carte erreur avec compteur — Design System: status colors (error) ── -->
      <div class="admin-alert admin-alert-error" role="alert">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#B91C1C" stroke-width="2" style="flex-shrink:0;margin-top:2px;">
          <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <div class="admin-alert-body">
          <span><?= e($error) ?></span>
          <?php if ($attemptsUsed > 0): ?>
          <div class="attempt-dots-wrap">
            <div class="attempt-dots">
              <?php for ($i = 0; $i < 5; $i++): ?>
                <span class="attempt-dot <?= $i < $attemptsUsed ? 'used' . ($i === $attemptsUsed - 1 ? ' last' : '') : '' ?>"></span>
              <?php endfor; ?>
            </div>
            <span class="attempt-meta"><?= $attemptsUsed ?> / 5 tentatives utilisées</span>
            <?php if ($attemptsRemaining > 0): ?>
              <span class="attempt-warn">
                ⚠ <?= $attemptsRemaining ?> tentative<?= $attemptsRemaining > 1 ? 's' : '' ?> restante<?= $attemptsRemaining > 1 ? 's' : '' ?> avant blocage de 15 min
              </span>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="admin-alert admin-alert-success">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        <?= e($success) ?>
      </div>
    <?php endif; ?>

    <form action="<?= url('/admin/login') ?>" method="POST" autocomplete="off">
      <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">

      <div class="admin-field">
        <label class="field-label" for="admin-email">Adresse e-mail</label>
        <div class="admin-input-wrap">
          <span class="field-icon">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          </span>
          <input type="email" id="admin-email" name="email" class="admin-input"
                 placeholder="admin@connect-academia.ga"
                 value="<?= e(\Core\Session::getFlash('old_email', '')) ?>"
                 required autocomplete="email">
        </div>
      </div>

      <div class="admin-field">
        <label class="field-label" for="admin-pwd">Mot de passe</label>
        <div class="admin-input-wrap">
          <span class="field-icon">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          </span>
          <input type="password" name="password" class="admin-input" id="admin-pwd"
                 placeholder="••••••••••••" required autocomplete="current-password">
          <button type="button" class="password-toggle" aria-label="Afficher le mot de passe">
            <svg class="icon-eye" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg class="icon-eye-off hidden" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
          </button>
        </div>
      </div>

      <div class="admin-auth-options">
        <label class="admin-checkbox-label">
          <input type="checkbox" name="remember" value="1">
          Rester connecté
        </label>
        <a href="<?= url('/admin/forgot-password') ?>" class="admin-forgot">Mot de passe oublié ?</a>
      </div>

      <button type="submit" class="btn-admin-primary" id="btn-login-submit"
              <?= $loginLocked ? 'disabled style="opacity:.5;cursor:not-allowed;"' : '' ?>>
        CONNEXION
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
        </svg>
      </button>
    </form>

    <div class="admin-auth-divider">OU</div>

    <div class="admin-auth-security">
      <div class="admin-auth-security-badge">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        Connexion chiffrée
      </div>
      <div>Besoin d'aide ? <a href="mailto:contact@connect-academia.ga">Support</a></div>
    </div>

    <?php else: ?>
    <div class="admin-auth-badge">Vérification 2FA</div>
    <h1>Code de sécurité</h1>
    <p class="auth-desc">Entrez le code à 6 chiffres envoyé par mail ou scannez le QR code ci-dessous.</p>

    <?php if ($error): ?>
      <div class="admin-alert admin-alert-error">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/></svg>
        <?= e($error) ?>
      </div>
    <?php endif; ?>

    <div class="qr-container">
        <img src="<?= $qr_url ?>" alt="QR Code OTP">
        <div class="qr-label">SCANNEZ L'OTP</div>
    </div>

    <form action="<?= url('/admin/verifier-2fa') ?>" method="POST" id="otp-form">
      <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
      <input type="hidden" name="otp_code_val" id="otp-code-hidden">

      <div class="otp-inputs">
        <?php for ($i = 0; $i < 6; $i++): ?>
          <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="one-time-code">
        <?php endfor; ?>
      </div>

      <button type="submit" class="btn-admin-primary">VÉRIFIER LE CODE</button>
    </form>

    <div class="admin-auth-divider">OU</div>

    <div style="text-align:center;">
      <form action="<?= url('/admin/logout') ?>" method="POST" style="display:inline;">
        <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
        <button type="submit" class="admin-forgot">← Retour à la connexion</button>
      </form>
    </div>
    <?php endif; ?>

  </div>
</div>

<p class="admin-auth-page-footer">
  © 2026 <strong>Connect'Academia</strong> · Plateforme d'apprentissage et d'orientation des Terminales · Libreville, Gabon
</p>

<script>
document.addEventListener('DOMContentLoaded', function() {

    // ── Countdown lockout ─────────────────────────────────────────────
    const countdownEl = document.getElementById('lockout-countdown');
    if (countdownEl) {
        let secs = parseInt(countdownEl.dataset.secs || '900', 10);
        const btnSubmit = document.getElementById('btn-login-submit');
        const fmt = (n) => String(n).padStart(2, '0');
        const tick = () => {
            if (secs <= 0) { window.location.reload(); return; }
            const m = Math.floor(secs / 60);
            const s = secs % 60;
            countdownEl.textContent = `${m}m ${fmt(s)}s`;
            secs--;
            setTimeout(tick, 1000);
        };
        tick();
        if (btnSubmit) btnSubmit.disabled = true;
    }

    // Logique du Toggle Password (Oeil)
    const toggleBtn = document.querySelector('.password-toggle');
    const pwdInput = document.getElementById('admin-pwd');
    if (toggleBtn && pwdInput) {
        const eyeIcon = toggleBtn.querySelector('.icon-eye');
        const eyeOffIcon = toggleBtn.querySelector('.icon-eye-off');

        toggleBtn.addEventListener('click', function() {
            const isPassword = pwdInput.type === 'password';
            pwdInput.type = isPassword ? 'text' : 'password';
            eyeIcon.classList.toggle('hidden', isPassword);
            eyeOffIcon.classList.toggle('hidden', !isPassword);
        });
    }

    const otpForm = document.getElementById('otp-form');
    if (otpForm) {
        const inputs = otpForm.querySelectorAll('.otp-input');
        const hiddenInput = document.getElementById('otp-code-hidden');

        // Passage automatique au suivant
        inputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                if (e.target.value.length === 1 && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }
            });

            // Retour arrière
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    inputs[index - 1].focus();
                }
            });
        });

        // Fusionner avant l'envoi
        otpForm.addEventListener('submit', (e) => {
            let fullCode = "";
            inputs.forEach(input => fullCode += input.value);
            hiddenInput.value = fullCode;
            
            if (fullCode.length !== 6) {
                e.preventDefault();
                alert("Veuillez entrer les 6 chiffres.");
            }
        });
    }
});
</script>