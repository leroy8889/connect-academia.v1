<div class="main-container">

  <div class="image-section image-section--inscription"></div>

  <div class="form-section form-section--scroll">
    
    <style>
      /* Bouton Retour */
      .btn-back-login {
        position: absolute;
        top: 30px;
        right: 40px;
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 20px;
        background-color: transparent;
        color: #6b7280;
        text-decoration: none;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-radius: 50px;
        border: 1.5px solid #e5e7eb;
        transition: all 0.4s ease;
        z-index: 10;
      }
      .btn-back-login:hover {
        color: #1f2937;
        border-color: #a855f7;
        background: white;
        box-shadow: 0 10px 15px rgba(168, 85, 247, 0.1);
        transform: translateY(-2px);
      }

      /* Style pour l'œil (Eye Icon) */
      .input-wrapper {
        position: relative;
        display: flex;
        align-items: center;
      }
      
      .input-wrapper input {
        width: 100%;
        padding-right: 50px; /* Espace pour l'icône */
      }
      
      .eye-icon {
        position: absolute;
        right: 15px;
        background: none;
        border: none;
        color: #9ca3af;
        cursor: pointer;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: color 0.3s;
      }
      
      .eye-icon:hover {
        color: #6366f1; /* Couleur primary de ton dégradé */
      }

      /* reCAPTCHA */
      .g-recaptcha {
        margin: 20px 0;
      }

      @media (max-width: 900px) {
        .btn-back-login { top: 15px; right: 15px; padding: 8px 15px; font-size: 10px; }
      }
    </style>

    <a href="<?= url('/auth/connexion') ?>" class="btn-back-login">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline>
      </svg>
      Connexion
    </a>

    <div class="form-content">
      <div class="logo">
        <img src="<?= asset('images/logo.jpeg') ?>" alt="Logo Connect'Academia">
      </div>

      <h1>Renseignez vos informations personnelles</h1>
      <p class="subtitle">Veuillez remplir tous les champs obligatoires pour continuer.</p>

      <?php if (!empty($errors['general'])): ?>
      <div class="auth-alert auth-alert--error">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
        </svg>
        <?= e($errors['general']) ?>
      </div>
      <?php endif; ?>

      <form action="<?= url('/auth/inscription') ?>" method="POST" id="register-form" novalidate>
        <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">

        <div class="form-group">
          <label for="nom">Nom <span class="required">*</span></label>
          <input type="text" id="nom" name="nom" placeholder="Entrez votre nom" value="<?= e($old['nom'] ?? '') ?>" required>
        </div>
        
        <div class="form-group">
          <label for="prenom">Prénom <span class="required">*</span></label>
          <input type="text" id="prenom" name="prenom" placeholder="Entrez votre prénom" value="<?= e($old['prenom'] ?? '') ?>" required>
        </div>

        <div class="form-group">
          <label for="email">Email <span class="required">*</span></label>
          <input type="email" id="email" name="email" placeholder="Entrez votre adresse email" value="<?= e($old['email'] ?? '') ?>" required>
        </div>

        <div class="form-group">
          <label>Niveau</label>
          <input type="text" value="Terminale" readonly>
        </div>

        <div class="form-group">
          <label for="serie_id">Série <span class="required">*</span></label>
          <select id="serie_id" name="serie_id" required>
            <option value="" disabled <?= empty($old['serie_id']) ? 'selected' : '' ?>>Sélectionnez votre série</option>
            <?php foreach ($series as $serie): ?>
              <option value="<?= (int)$serie['id'] ?>" <?= (int)($old['serie_id'] ?? 0) === (int)$serie['id'] ? 'selected' : '' ?>>Série <?= e($serie['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="password">Mot de passe <span class="required">*</span></label>
          <div class="input-wrapper">
            <input type="password" id="password" name="password" 
                   placeholder="Entrez votre mot de passe" 
                   autocomplete="new-password" required 
                   oninput="checkConstraints(this.value)">
            <button type="button" class="eye-icon" onclick="togglePassword('password', this)" aria-label="Voir le mot de passe">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>
          <?php if (!empty($errors['password'])): ?>
          <span class="field-error"><?= e($errors['password']) ?></span>
          <?php endif; ?>
        </div>

        <div class="password-constraints">
          <div class="constraint" id="c-length">8 caractères min.</div>
          <div class="constraint" id="c-digit">1 chiffre</div>
          <div class="constraint" id="c-case">1 lettre min. et maj.</div>
          <div class="constraint" id="c-special">1 caractère spécial</div>
        </div>

        <div class="form-group">
          <label for="password_confirmation">Confirmer le mot de passe <span class="required">*</span></label>
          <div class="input-wrapper">
            <input type="password" id="password_confirmation" name="password_confirmation" 
                   placeholder="Confirmez votre mot de passe" 
                   autocomplete="new-password" required>
            <button type="button" class="eye-icon" onclick="togglePassword('password_confirmation', this)" aria-label="Voir le mot de passe">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>
        </div>

        <div class="g-recaptcha" data-sitekey="VOTRE_CLE_SITE_ICI"></div>

        <div class="checkbox-group">
          <input type="checkbox" id="terms" name="terms" value="1" <?= !empty($old['terms']) ? 'checked' : '' ?>>
          <label for="terms">J'accepte les <a href="#" style="color:#9b51e0;font-weight:600">conditions d'utilisation</a></label>
        </div>

        <button type="submit" class="btn-next">Créer mon compte</button>
      </form>

      <div class="footer-links">
        <p class="signup-text">Déjà membre ? <a href="<?= url('/auth/connexion') ?>">Se connecter</a></p>
        <p class="copyright">Tous droits réservés - Connect'Academia</p>
      </div>

    </div>
  </div>
</div>

<script src="https://www.google.com/recaptcha/api.js" async defer></script>

<script>
// Fonction générique pour masquer/afficher le mot de passe
function togglePassword(inputId, btnEl) {
    const input = document.getElementById(inputId);
    
    // Icône Œil Ouvert (Masquer)
    const eyeOpen = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
    
    // Icône Œil Barré (Afficher)
    const eyeClosed = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';

    if (input.type === 'password') {
        input.type = 'text';
        btnEl.innerHTML = eyeClosed;
    } else {
        input.type = 'password';
        btnEl.innerHTML = eyeOpen;
    }
}

// Ta fonction de contraintes existante
function checkConstraints(val) {
    const set = (id, ok) => {
        const el = document.getElementById(id);
        if (el) el.classList.toggle('ok', ok);
    };
    set('c-length',  val.length >= 8);
    set('c-digit',   /[0-9]/.test(val));
    set('c-case',    /[a-z]/.test(val) && /[A-Z]/.test(val));
    set('c-special', /[^A-Za-z0-9]/.test(val));
}
</script>