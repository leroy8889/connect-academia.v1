<?php
use Core\Session;
$errors = Session::getFlash('errors', []);
$old = Session::getFlash('old', []);
?>

<div class="auth-page">
    <!-- ── LEFT: Branding ────────────────────── -->
    <div class="auth-page__branding">
        <div class="auth-branding__content">
            <div class="auth-branding__illustration">
                <svg width="280" height="200" viewBox="0 0 280 200" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <!-- Illustration : collaboration étudiants -->
                    <rect x="60" y="40" width="160" height="120" rx="16" fill="rgba(255,255,255,0.15)" stroke="rgba(255,255,255,0.3)" stroke-width="2"/>
                    <circle cx="110" cy="90" r="25" fill="rgba(255,255,255,0.2)"/>
                    <circle cx="170" cy="90" r="25" fill="rgba(255,255,255,0.2)"/>
                    <path d="M110 75L115 70L120 75" stroke="white" stroke-width="2" stroke-linecap="round"/>
                    <path d="M170 75L175 70L180 75" stroke="white" stroke-width="2" stroke-linecap="round"/>
                    <rect x="95" y="130" width="90" height="20" rx="10" fill="rgba(255,255,255,0.2)"/>
                    <path d="M130 140H150" stroke="white" stroke-width="2" stroke-linecap="round"/>
                    <!-- Étoiles / sparkles -->
                    <circle cx="40" cy="60" r="3" fill="rgba(255,255,255,0.5)"/>
                    <circle cx="240" cy="50" r="4" fill="rgba(255,255,255,0.4)"/>
                    <circle cx="50" cy="150" r="2" fill="rgba(255,255,255,0.6)"/>
                    <circle cx="230" cy="140" r="3" fill="rgba(255,255,255,0.3)"/>
                </svg>
            </div>
            <h1 class="auth-branding__title">Rejoignez StudyLink</h1>
            <p class="auth-branding__subtitle">
                Le réseau social bienveillant dédié à l'éducation. Collaborez, apprenez et partagez avec votre communauté scolaire.
            </p>
            <div class="auth-branding__dots">
                <span class="auth-branding__dot auth-branding__dot--active"></span>
                <span class="auth-branding__dot"></span>
                <span class="auth-branding__dot"></span>
            </div>
        </div>
    </div>

    <!-- ── RIGHT: Form ───────────────────────── -->
    <div class="auth-page__form-panel">
        <div class="auth-form-container">
           
            <div class="auth-tabs">
                <a href="<?= url('/login') ?>" class="auth-tabs__tab">Connexion</a>
                <a href="<?= url('/register') ?>" class="auth-tabs__tab auth-tabs__tab--active">Inscription</a>
            </div>

            <div class="auth-form__header">
                <h2 class="auth-form__title">Créer un compte</h2>
                <p class="auth-form__subtitle">Rejoignez la communauté éducative dès maintenant</p>
            </div>

            <?php if (!empty($errors['general'])): ?>
                <div class="auth-alert auth-alert--error">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
                    </svg>
                    <?= htmlspecialchars($errors['general']) ?>
                </div>
            <?php endif; ?>

            <form action="<?= url('/register') ?>" method="POST" id="register-form" novalidate>
                <input type="hidden" name="_csrf_token" value="<?= Session::getCsrfToken() ?>">

                <!-- Nom + Prénom -->
                <div class="form-group form-group--row">
                    <div>
                        <label class="form-label" for="prenom">Prénom</label>
                        <input type="text" id="prenom" name="prenom"
                               class="form-input <?= !empty($errors['prenom']) ? 'form-input--error' : '' ?>"
                               placeholder="Alex"
                               value="<?= htmlspecialchars($old['prenom'] ?? '') ?>"
                               required>
                        <?php if (!empty($errors['prenom'])): ?>
                            <span class="form-error"><?= htmlspecialchars($errors['prenom']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="form-label" for="nom">Nom</label>
                        <input type="text" id="nom" name="nom"
                               class="form-input <?= !empty($errors['nom']) ? 'form-input--error' : '' ?>"
                               placeholder="Rivera"
                               value="<?= htmlspecialchars($old['nom'] ?? '') ?>"
                               required>
                        <?php if (!empty($errors['nom'])): ?>
                            <span class="form-error"><?= htmlspecialchars($errors['nom']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Email -->
                <div class="form-group">
                    <label class="form-label" for="email">Adresse e-mail</label>
                    <input type="email" id="email" name="email"
                           class="form-input <?= !empty($errors['email']) ? 'form-input--error' : '' ?>"
                           placeholder="alex@ecole.fr"
                           value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                           required>
                    <?php if (!empty($errors['email'])): ?>
                        <span class="form-error"><?= htmlspecialchars($errors['email']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Classe -->
                <div class="form-group">
                    <label class="form-label" for="classe">Classe</label>
                    <select id="classe" name="classe" class="form-select <?= !empty($errors['classe']) ? 'form-input--error' : '' ?>">
                        <option value="">Sélectionner la classe</option>
                        <option value="6ème" <?= ($old['classe'] ?? '') === '6ème' ? 'selected' : '' ?>>6ème</option>
                        <option value="5ème" <?= ($old['classe'] ?? '') === '5ème' ? 'selected' : '' ?>>5ème</option>
                        <option value="4ème" <?= ($old['classe'] ?? '') === '4ème' ? 'selected' : '' ?>>4ème</option>
                        <option value="3ème" <?= ($old['classe'] ?? '') === '3ème' ? 'selected' : '' ?>>3ème</option>
                        <option value="Seconde" <?= ($old['classe'] ?? '') === 'Seconde' ? 'selected' : '' ?>>Seconde</option>
                        <option value="Première" <?= ($old['classe'] ?? '') === 'Première' ? 'selected' : '' ?>>Première</option>
                        <option value="Terminale" <?= ($old['classe'] ?? '') === 'Terminale' ? 'selected' : '' ?>>Terminale</option>
                    </select>
                    <?php if (!empty($errors['classe'])): ?>
                        <span class="form-error"><?= htmlspecialchars($errors['classe']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Mot de passe -->
                <div class="form-group">
                    <label class="form-label" for="register-password">Mot de passe</label>
                    <div class="form-input-wrapper">
                        <input type="password" id="register-password" name="password"
                               class="form-input <?= !empty($errors['password']) ? 'form-input--error' : '' ?>"
                               placeholder="Minimum 8 caractères"
                               minlength="8" required>
                        <button type="button" class="form-input-icon" data-toggle-password="register-password" tabindex="-1">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                    <div class="password-strength">
                        <div class="password-strength__bar">
                            <div class="password-strength__fill"></div>
                        </div>
                        <span class="password-strength__label"></span>
                    </div>
                    <?php if (!empty($errors['password'])): ?>
                        <span class="form-error"><?= htmlspecialchars($errors['password']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Confirmation mot de passe -->
                <div class="form-group">
                    <label class="form-label" for="password_confirm">Confirmer le mot de passe</label>
                    <div class="form-input-wrapper">
                        <input type="password" id="password_confirm" name="password_confirm"
                               class="form-input"
                               placeholder="Retapez le mot de passe" required>
                        <button type="button" class="form-input-icon" data-toggle-password="password_confirm" tabindex="-1">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- CGU -->
                <div class="form-group">
                    <label class="form-checkbox">
                        <input type="checkbox" name="terms" required>
                        <span>J'accepte les <a href="#">Conditions d'utilisation</a> et la <a href="#">Politique de confidentialité</a></span>
                    </label>
                    <?php if (!empty($errors['terms'])): ?>
                        <span class="form-error"><?= htmlspecialchars($errors['terms']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Submit -->
                <button type="submit" class="auth-form__submit">Créer mon compte</button>

                <p class="auth-form__alt-link">
                    Déjà inscrit ? <a href="<?= url('/login') ?>">Se connecter</a>
                </p>
            </form>
        </div>
    </div>
</div>

