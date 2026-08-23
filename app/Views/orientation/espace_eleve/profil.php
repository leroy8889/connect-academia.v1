<?php
// Profil — variables : $user, $identite (dernier dossier ou null), $stats, $errors
require_once __DIR__ . '/_helpers.php';
$errors   = $errors ?? [];
$stats    = $stats  ?? ['total' => 0, 'en_attente' => 0, 'confirmes' => 0, 'refuses' => 0];
$identite = $identite ?? null;

$prenom = $user['prenom'] ?? '';
$nom    = $user['nom'] ?? '';
$email  = $user['email'] ?? '';
$role   = $user['role'] ?? 'visiteur';
$fullName = trim($prenom . ' ' . $nom);
$initiales = mb_strtoupper(mb_substr(trim($prenom) !== '' ? $prenom : ($nom !== '' ? $nom : '?'), 0, 1))
           . ($nom !== '' && trim($prenom) !== '' ? mb_strtoupper(mb_substr($nom, 0, 1)) : '');

$roleLabels = [
    'eleve'        => 'Élève',
    'visiteur'     => 'Candidat orientation',
    'gestionnaire' => 'Gestionnaire',
    'admin'        => 'Administrateur',
];
$depuis = !empty($user['created_at']) ? acct_date($user['created_at']) : '—';

$genreLabels = ['homme' => 'Masculin', 'femme' => 'Féminin', 'autre' => 'Autre'];
?>

<div class="acct-shead">
  <div>
    <span class="acct-shead-kick"><?= acct_icon('user', 12) ?> identité</span>
    <h1 class="acct-shead-t">Mon profil</h1>
    <p class="acct-shead-d">Gérez les informations de votre compte. Votre adresse email sert à rattacher automatiquement vos dossiers d'inscription.</p>
  </div>
</div>

<div class="acct-card gls" style="display:flex;gap:22px;align-items:center;flex-wrap:wrap;">
  <div class="acct-avatar-lg"><?= e($initiales) ?></div>
  <div style="flex:1;min-width:200px;">
    <div class="acct-card-title"><?= e($fullName ?: 'Mon compte') ?></div>
    <div class="acct-card-sub"><?= e($email) ?> · <?= e($roleLabels[$role] ?? $role) ?> depuis <?= e($depuis) ?></div>
  </div>
  <div style="display:flex;gap:18px;flex-wrap:wrap;">
    <div class="acct-kv"><div class="l">Dossiers</div><div class="v" style="text-align:center;"><?= (int)$stats['total'] ?></div></div>
    <div class="acct-kv"><div class="l">En attente</div><div class="v" style="text-align:center;"><?= (int)$stats['en_attente'] ?></div></div>
    <div class="acct-kv"><div class="l">Confirmés</div><div class="v" style="text-align:center;"><?= (int)$stats['confirmes'] ?></div></div>
  </div>
</div>

<?php if ($identite): ?>
  <div class="acct-card gls">
    <div class="acct-card-title" style="margin-bottom:2px;"><?= acct_icon('idcard', 16) ?> Identité candidat</div>
    <div class="acct-card-sub" style="margin-bottom:10px;">Informations issues de votre dernier dossier — elles pré-remplissent vos prochaines démarches.</div>
    <div class="acct-profile-grid">
      <div class="acct-field"><span class="l">Nom complet</span><span class="v"><?= e($identite['nom_complet'] ?? '—') ?></span></div>
      <div class="acct-field"><span class="l">Genre</span><span class="v"><?= e($genreLabels[$identite['genre'] ?? ''] ?? '—') ?></span></div>
      <div class="acct-field"><span class="l">Date de naissance</span><span class="v"><?= acct_date($identite['date_naissance'] ?? null) ?></span></div>
      <div class="acct-field"><span class="l">Lieu de naissance</span><span class="v"><?= e($identite['lieu_naissance'] ?? '—') ?></span></div>
      <div class="acct-field"><span class="l">Nationalité</span><span class="v"><?= e($identite['nationalite'] ?? '—') ?></span></div>
      <div class="acct-field"><span class="l">Dernier établissement fréquenté</span><span class="v"><?= e($identite['dernier_etablissement'] ?? '—') ?></span></div>
      <div class="acct-field"><span class="l">Téléphone</span><span class="v"><?= e(trim(($identite['indicatif_tel'] ?? '') . ' ' . ($identite['telephone'] ?? '')) ?: '—') ?></span></div>
      <div class="acct-field"><span class="l">Pays de provenance</span><span class="v"><?= e($identite['pays_provenance'] ?? '—') ?></span></div>
      <div class="acct-field"><span class="l">Ville</span><span class="v"><?= e($identite['ville'] ?? '—') ?></span></div>
      <div class="acct-field"><span class="l">Adresse</span><span class="v"><?= e($identite['adresse'] ?? '—') ?></span></div>
    </div>
  </div>
<?php endif; ?>

<div class="acct-card gls">
  <div class="acct-card-title" style="margin-bottom:14px;"><?= acct_icon('user', 16) ?> Informations du compte</div>
  <form method="POST" action="<?= url('/eleve/orientation/profil') ?>">
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
    <div class="acct-form-row">
      <div class="acct-form-field">
        <label for="acct-prenom">Prénom</label>
        <input type="text" id="acct-prenom" name="prenom" class="acct-input <?= isset($errors['prenom']) ? 'error' : '' ?>" value="<?= e($prenom) ?>" required minlength="2" maxlength="100">
        <?php if (isset($errors['prenom'])): ?><div class="acct-field-err"><?= e($errors['prenom']) ?></div><?php endif; ?>
      </div>
      <div class="acct-form-field">
        <label for="acct-nom">Nom</label>
        <input type="text" id="acct-nom" name="nom" class="acct-input <?= isset($errors['nom']) ? 'error' : '' ?>" value="<?= e($nom) ?>" required minlength="2" maxlength="100">
        <?php if (isset($errors['nom'])): ?><div class="acct-field-err"><?= e($errors['nom']) ?></div><?php endif; ?>
      </div>
    </div>
    <div class="acct-form-field">
      <label for="acct-email">Adresse email</label>
      <input type="email" id="acct-email" name="email" class="acct-input <?= isset($errors['email']) ? 'error' : '' ?>" value="<?= e($email) ?>" required maxlength="255">
      <?php if (isset($errors['email'])): ?><div class="acct-field-err"><?= e($errors['email']) ?></div><?php endif; ?>
      <div class="acct-field-hint">Les dossiers déposés avec cette adresse sont automatiquement rattachés à votre compte.</div>
    </div>
    <div class="acct-form-field" id="acct-email-password" style="display:none;">
      <label for="acct-current">Mot de passe actuel <span style="color:var(--ink-faint);font-weight:400;">(requis pour changer l'email)</span></label>
      <input type="password" id="acct-current" name="current_password" class="acct-input <?= isset($errors['current_password']) ? 'error' : '' ?>" autocomplete="current-password">
      <?php if (isset($errors['current_password'])): ?><div class="acct-field-err"><?= e($errors['current_password']) ?></div><?php endif; ?>
    </div>
    <button type="submit" class="abtn primary" style="margin-top:4px;"><?= acct_icon('check', 15) ?> Enregistrer</button>
  </form>
</div>

<div class="acct-card gls">
  <div class="acct-card-title" style="margin-bottom:14px;"><?= acct_icon('lock', 16) ?> Changer mon mot de passe</div>
  <form method="POST" action="<?= url('/eleve/orientation/profil/mot-de-passe') ?>">
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
    <div class="acct-form-field">
      <label for="acct-pass-current">Mot de passe actuel</label>
      <input type="password" id="acct-pass-current" name="current_password" class="acct-input <?= isset($errors['current_password']) && !isset($errors['email']) ? 'error' : '' ?>" required autocomplete="current-password">
    </div>
    <div class="acct-form-row">
      <div class="acct-form-field">
        <label for="acct-pass-new">Nouveau mot de passe</label>
        <input type="password" id="acct-pass-new" name="new_password" class="acct-input <?= isset($errors['new_password']) ? 'error' : '' ?>" required minlength="8" maxlength="128" autocomplete="new-password">
        <?php if (isset($errors['new_password'])): ?><div class="acct-field-err"><?= e($errors['new_password']) ?></div><?php endif; ?>
      </div>
      <div class="acct-form-field">
        <label for="acct-pass-confirm">Confirmation</label>
        <input type="password" id="acct-pass-confirm" name="new_password_confirmation" class="acct-input <?= isset($errors['new_password_confirmation']) ? 'error' : '' ?>" required autocomplete="new-password">
        <?php if (isset($errors['new_password_confirmation'])): ?><div class="acct-field-err"><?= e($errors['new_password_confirmation']) ?></div><?php endif; ?>
      </div>
    </div>
    <div class="acct-field-hint" style="margin-bottom:12px;">Au moins 8 caractères. Après le changement, votre session reste ouverte.</div>
    <button type="submit" class="abtn primary"><?= acct_icon('lock', 15) ?> Mettre à jour le mot de passe</button>
  </form>
</div>

<script>
// Affiche le champ "mot de passe actuel" seulement si l'email change
(function () {
  var emailInput = document.getElementById('acct-email');
  var pwdBlock   = document.getElementById('acct-email-password');
  if (!emailInput || !pwdBlock) return;
  var original = emailInput.value;
  <?php if (isset($errors['current_password']) || isset($errors['email'])): ?>
  pwdBlock.style.display = 'block';
  <?php endif; ?>
  emailInput.addEventListener('input', function () {
    pwdBlock.style.display = (emailInput.value.trim().toLowerCase() !== original.trim().toLowerCase()) ? 'block' : 'none';
  });
})();
</script>
