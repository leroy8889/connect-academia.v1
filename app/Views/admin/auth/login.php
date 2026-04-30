<?php
$is2fa   = !empty($_SESSION['admin_2fa_pending']);
$error   = \Core\Session::getFlash('error', '');
$success = \Core\Session::getFlash('success');
?>

<div class="admin-auth-card">

  <!-- ── Panneau gauche illustré ──────────────────────────── -->
  <div class="admin-auth-left">

    <div class="admin-auth-brand">
      <div class="admin-auth-brand-icon">
        <svg width="20" height="20" viewBox="0 0 40 40" fill="none">
          <path d="M8 28L20 10L32 28H8Z" fill="white" fill-opacity="0.9"/>
          <circle cx="20" cy="20" r="5" fill="white"/>
        </svg>
      </div>
      <div class="admin-auth-brand-text">
        <strong>Connect'Academia</strong>
        <span>Espace Administrateur</span>
      </div>
    </div>

    <!-- Illustration padlock -->
    <div class="admin-auth-illustration">
      <svg width="200" height="220" viewBox="0 0 200 220" fill="none" xmlns="http://www.w3.org/2000/svg">
        <!-- Blob bg -->
        <ellipse cx="100" cy="200" rx="80" ry="16" fill="rgba(139,82,250,0.15)"/>

        <!-- Padlock corps -->
        <rect x="32" y="92" width="136" height="108" rx="18" fill="#2D1B69"/>
        <rect x="38" y="98" width="124" height="96" rx="14" fill="#1F1248"/>

        <!-- Padlock anneau -->
        <path d="M55 92V62C55 38 145 38 145 62V92" stroke="rgba(196,160,248,0.7)" stroke-width="16" stroke-linecap="round" fill="none"/>

        <!-- Fingerprint icon -->
        <g transform="translate(100,146)" stroke="#8B52FA" stroke-linecap="round" fill="none">
          <path d="M0-24C13-24 24-13 24 0C24 13 13 24 0 24C-13 24-24 13-24 0" stroke-width="2.5" stroke-dasharray="4 4"/>
          <path d="M0-16C9-16 16-9 16 0C16 9 9 16 0 16C-9 16-16 9-16 0" stroke-width="2.5"/>
          <path d="M0-8C4-8 8-4 8 0C8 4 4 8 0 8C-4 8-8 4-8 0" stroke-width="2.5"/>
          <circle cx="0" cy="0" r="2" fill="#8B52FA"/>
        </g>

        <!-- Keyhole -->
        <circle cx="100" cy="146" r="10" fill="#8B52FA" opacity="0.3"/>

        <!-- Étudiant gauche -->
        <g transform="translate(16,155)">
          <!-- Corps -->
          <ellipse cx="22" cy="50" rx="14" ry="6" fill="rgba(139,82,250,0.2)"/>
          <rect x="14" y="28" width="16" height="18" rx="5" fill="#C4A0F8"/>
          <!-- Tête -->
          <circle cx="22" cy="20" r="9" fill="#C4A0F8"/>
          <!-- Laptop -->
          <rect x="8" y="44" width="28" height="5" rx="2" fill="#E8D5FF"/>
          <rect x="10" y="39" width="24" height="8" rx="2" fill="#D4B5FF"/>
        </g>

        <!-- Étudiant droite -->
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

  <!-- ── Panneau droit formulaire ─────────────────────────── -->
  <div class="admin-auth-right">

    <?php if (!$is2fa): ?>
    <!-- === FORMULAIRE LOGIN === -->
    <div class="admin-auth-badge">Portail Sécurisé</div>
    <h1>Connexion administrateur</h1>
    <p class="auth-desc">Accédez au tableau de bord pour gérer la plateforme, les utilisateurs et la communauté.</p>

    <?php if ($error): ?>
      <div class="admin-alert admin-alert-error">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?= e($error) ?>
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
        <div class="admin-input-wrap">
          <span class="field-icon">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          </span>
          <input type="email" name="email" class="admin-input"
                 placeholder="admin@connect-academia.ga"
                 value="<?= e(\Core\Session::getFlash('old_email', '')) ?>"
                 required autocomplete="email">
        </div>
      </div>

      <div class="admin-field">
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
        <a href="#" class="admin-forgot">Mot de passe oublié ?</a>
      </div>

      <button type="submit" class="btn-admin-primary">
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
    <!-- === FORMULAIRE 2FA === -->
    <div class="admin-auth-badge">Vérification 2FA</div>
    <h1>Code de sécurité</h1>
    <p class="auth-desc">Entrez le code à 6 chiffres de votre application d'authentification (Google Authenticator, Authy…).</p>

    <?php if (!empty($errors['otp'])): ?>
      <div class="admin-alert admin-alert-error">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/></svg>
        <?= e($errors['otp']) ?>
      </div>
    <?php endif; ?>

    <form action="<?= url('/admin/verifier-2fa') ?>" method="POST" id="otp-form">
      <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
      <input type="hidden" name="otp_code" id="otp-code-hidden">

      <div class="otp-inputs">
        <?php for ($i = 0; $i < 6; $i++): ?>
          <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="one-time-code">
        <?php endfor; ?>
      </div>

      <button type="submit" class="btn-admin-primary">VÉRIFIER</button>
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
