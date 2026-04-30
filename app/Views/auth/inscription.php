<div class="main-container">

  <!-- Image gauche fixe -->
  <div class="image-section image-section--inscription"></div>

  <!-- Formulaire droit -->
  <div class="form-section form-section--scroll">
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

      <form action="<?= url('/auth/inscription') ?>" method="POST" novalidate>
        <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">

        <div class="form-group">
          <label for="nom">Nom <span class="required">*</span></label>
          <input type="text" id="nom" name="nom"
                 placeholder="Entrez votre nom"
                 value="<?= e($old['nom'] ?? '') ?>"
                 autocomplete="family-name" required>
          <?php if (!empty($errors['nom'])): ?>
          <span class="field-error"><?= e($errors['nom']) ?></span>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label for="prenom">Prénom <span class="required">*</span></label>
          <input type="text" id="prenom" name="prenom"
                 placeholder="Entrez votre prénom"
                 value="<?= e($old['prenom'] ?? '') ?>"
                 autocomplete="given-name" required>
          <?php if (!empty($errors['prenom'])): ?>
          <span class="field-error"><?= e($errors['prenom']) ?></span>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label for="email">Email <span class="required">*</span></label>
          <input type="email" id="email" name="email"
                 placeholder="Entrez votre adresse email"
                 value="<?= e($old['email'] ?? '') ?>"
                 autocomplete="email" required>
          <?php if (!empty($errors['email'])): ?>
          <span class="field-error"><?= e($errors['email']) ?></span>
          <?php endif; ?>
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
            <option value="<?= (int)$serie['id'] ?>"
                    <?= (int)($old['serie_id'] ?? 0) === (int)$serie['id'] ? 'selected' : '' ?>>
              Série <?= e($serie['nom']) ?><?= !empty($serie['description']) ? ' — ' . e($serie['description']) : '' ?>
            </option>
            <?php endforeach; ?>
          </select>
          <?php if (!empty($errors['serie_id'])): ?>
          <span class="field-error"><?= e($errors['serie_id']) ?></span>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label for="password">Mot de passe <span class="required">*</span></label>
          <input type="password" id="password" name="password"
                 placeholder="Entrez votre mot de passe"
                 autocomplete="new-password" required
                 oninput="checkConstraints(this.value)">
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
          <input type="password" id="password_confirmation" name="password_confirmation"
                 placeholder="Confirmez votre mot de passe"
                 autocomplete="new-password" required>
        </div>

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

<script>
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
