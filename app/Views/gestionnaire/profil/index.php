<?php
// Variables: $user, $etabNom, $role, $errors
$user    = $user    ?? [];
$etabNom = $etabNom ?? '';
$role    = $role    ?? 'gestionnaire';
$errors  = $errors  ?? [];

function prErr(array $errors, string $key): string {
    return isset($errors[$key]) ? '<div class="g-error-msg">' . htmlspecialchars($errors[$key], ENT_QUOTES, 'UTF-8') . '</div>' : '';
}
$fullName = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));
$initial  = mb_strtoupper(mb_substr($fullName !== '' ? $fullName : 'G', 0, 1));
?>

<div class="g-page-header">
  <div>
    <h2>Mon profil</h2>
    <p>Gérez votre identité, votre e-mail de connexion et votre mot de passe.</p>
  </div>
</div>

<div style="display:grid;grid-template-columns:300px 1fr;gap:20px;align-items:start;" class="g-profil-grid">

  <!-- Carte identité -->
  <div class="g-card" style="text-align:center;padding:32px 24px;">
    <div class="g-avatar" style="width:72px;height:72px;font-size:28px;margin:0 auto 16px;"><?= e($initial) ?></div>
    <div style="font-family:var(--serif);font-size:19px;font-weight:500;letter-spacing:-.01em;"><?= e($fullName ?: 'Gestionnaire') ?></div>
    <div style="font-size:12.5px;color:var(--ink-3);margin-top:4px;"><?= e($user['email'] ?? '') ?></div>
    <div style="margin-top:14px;">
      <span class="g-badge g-badge-enligne"><?= e(ucfirst($role)) ?></span>
    </div>
    <div style="border-top:1px solid var(--border);margin-top:20px;padding-top:16px;font-size:12px;color:var(--ink-2);text-align:left;display:flex;flex-direction:column;gap:8px;">
      <div style="display:flex;justify-content:space-between;gap:10px;">
        <span style="color:var(--ink-3);">Établissement</span>
        <strong style="text-align:right;"><?= e($etabNom ?: '—') ?></strong>
      </div>
      <div style="display:flex;justify-content:space-between;gap:10px;">
        <span style="color:var(--ink-3);">Dernière connexion</span>
        <strong><?= !empty($user['last_login']) ? date('d/m/Y H\hi', strtotime($user['last_login'])) : '—' ?></strong>
      </div>
      <div style="display:flex;justify-content:space-between;gap:10px;">
        <span style="color:var(--ink-3);">Compte créé le</span>
        <strong><?= !empty($user['created_at']) ? date('d/m/Y', strtotime($user['created_at'])) : '—' ?></strong>
      </div>
    </div>
  </div>

  <div>
    <!-- Identité & e-mail -->
    <form action="<?= url('/gestionnaire/profil') ?>" method="post" class="g-card" style="margin-bottom:20px;">
      <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
      <h3 class="g-section-title">Informations personnelles</h3>
      <div class="g-form-row">
        <div class="g-form-group">
          <label>Prénom *</label>
          <input type="text" name="prenom" value="<?= e($user['prenom'] ?? '') ?>" required>
          <?= prErr($errors, 'prenom') ?>
        </div>
        <div class="g-form-group">
          <label>Nom *</label>
          <input type="text" name="nom" value="<?= e($user['nom'] ?? '') ?>" required>
          <?= prErr($errors, 'nom') ?>
        </div>
      </div>
      <div class="g-form-group">
        <label>E-mail de connexion *</label>
        <input type="email" name="email" value="<?= e($user['email'] ?? '') ?>" required>
        <?= prErr($errors, 'email') ?>
        <div class="g-form-hint">Cet e-mail sert à vous connecter à l'espace établissement.</div>
      </div>
      <div class="g-form-group" id="pwd-confirm-group" style="display:none;">
        <label>Mot de passe actuel (requis pour changer l'e-mail) *</label>
        <input type="password" name="password_confirm" autocomplete="current-password">
        <?= prErr($errors, 'password_confirm') ?>
      </div>
      <?php if (isset($errors['password_confirm'])): ?>
      <script>document.addEventListener('DOMContentLoaded',()=>{document.getElementById('pwd-confirm-group').style.display='block';});</script>
      <?php endif; ?>
      <button type="submit" class="g-btn g-btn-primary">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        Enregistrer
      </button>
    </form>

    <!-- Mot de passe -->
    <form action="<?= url('/gestionnaire/profil/mot-de-passe') ?>" method="post" class="g-card">
      <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
      <h3 class="g-section-title">Changer le mot de passe</h3>
      <div class="g-form-group">
        <label>Mot de passe actuel *</label>
        <input type="password" name="password_actuel" required autocomplete="current-password">
        <?= prErr($errors, 'password_actuel') ?>
      </div>
      <div class="g-form-row">
        <div class="g-form-group">
          <label>Nouveau mot de passe *</label>
          <input type="password" name="password_nouveau" required minlength="10" autocomplete="new-password">
          <?= prErr($errors, 'password_nouveau') ?>
          <div class="g-form-hint">Au moins 10 caractères.</div>
        </div>
        <div class="g-form-group">
          <label>Confirmation *</label>
          <input type="password" name="password_confirmation" required minlength="10" autocomplete="new-password">
          <?= prErr($errors, 'password_confirmation') ?>
        </div>
      </div>
      <button type="submit" class="g-btn g-btn-outline">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        Mettre à jour le mot de passe
      </button>
    </form>
  </div>
</div>

<style>
@media(max-width:900px) {
  .g-profil-grid { grid-template-columns:1fr !important; }
}
</style>

<script>
// Afficher le champ mot de passe si l'e-mail change
document.addEventListener('DOMContentLoaded', () => {
  const emailInput = document.querySelector('input[name="email"]');
  const grp = document.getElementById('pwd-confirm-group');
  if (!emailInput || !grp) return;
  const initial = emailInput.value;
  emailInput.addEventListener('input', () => {
    grp.style.display = emailInput.value.trim() !== initial ? 'block' : 'none';
  });
});
</script>
