<?php
// Création de compte visiteur — variables : $pageTitle, $errors, $old, $csrf
// Typographie : design system Connect'Academia — Montserrat (300–800), casse phrase,
// accent unique Purple Neon #8B52FA. Canvas crème et mise en page éditoriale conservés.
$errors = $errors ?? [];
$old    = $old    ?? [];
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle ?? "Créer mon espace — Connect'Academia") ?></title>
  <link rel="icon" href="<?= asset('images/logo-officiel.png') ?>" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,500&display=swap" rel="stylesheet">
  <style>
    /* ── Tokens : crème Claude + accent Purple Neon (Connect'Academia) ── */
    :root {
      --canvas:#faf9f5; --soft:#f5f0e8; --card:#efe9de; --strong:#e8e0d2;
      --hairline:#e6dfd8; --hairline-soft:#ebe6df;
      --ink:#141413; --body:#3d3d3a; --muted:#6c6a64; --faint:#8e8b82;
      --purple:#8B52FA; --purple-hover:#9D6EFB; --purple-press:#7440D9; --purple-tint:#F3EFFF;
      --ring:rgba(139,82,250,.18);
      /* Design system Connect'Academia — famille unique Montserrat */
      --serif:'Montserrat',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;
      --sans:'Montserrat',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;
      --ease:cubic-bezier(.2,.8,.2,1);
    }
    * { margin:0; padding:0; box-sizing:border-box; }
    html { -webkit-text-size-adjust:100%; }
    body {
      font-family:var(--sans); color:var(--body); background:var(--canvas);
      min-height:100vh; display:flex; flex-direction:column;
      -webkit-font-smoothing:antialiased; text-rendering:optimizeLegibility;
    }

    /* ── En-tête minimal ── */
    .oc-top { display:flex; align-items:center; justify-content:space-between; padding:22px 30px; }
    .oc-brand { display:inline-flex; align-items:center; gap:11px; text-decoration:none; color:var(--ink); }
    .oc-brand img { width:38px; height:38px; object-fit:contain; }
    .oc-brand b { font-weight:600; font-size:16px; letter-spacing:-.01em; }
    .oc-back {
      display:inline-flex; align-items:center; gap:7px; font-size:14px; font-weight:500;
      color:var(--muted); text-decoration:none; transition:color 150ms var(--ease);
    }
    .oc-back:hover { color:var(--ink); }
    .oc-back svg { width:15px; height:15px; }

    /* ── Coquille éditoriale à deux colonnes ── */
    .oc-main { flex:1; display:flex; align-items:center; justify-content:center; padding:8px 24px 56px; }
    .oc-shell {
      width:100%; max-width:1000px; display:grid; grid-template-columns:1.02fr .98fr;
      background:var(--canvas); border:1px solid var(--hairline); border-radius:20px;
      overflow:hidden; box-shadow:0 1px 3px rgba(20,20,19,.06);
    }

    /* Colonne éditoriale (gauche) — color-block crème, accent purple discret */
    .oc-aside {
      position:relative; padding:52px 46px; background:var(--card);
      border-right:1px solid var(--hairline); display:flex; flex-direction:column;
    }
    .oc-aside::before { /* la seule lueur autorisée — halo purple très doux */
      content:""; position:absolute; inset:0; pointer-events:none;
      background:radial-gradient(420px 260px at 90% -8%, rgba(139,82,250,.14), transparent 70%);
    }
    .oc-eyebrow {
      position:relative; font-size:12px; font-weight:600; letter-spacing:1.5px;
      text-transform:uppercase; color:var(--purple); margin-bottom:20px;
    }
    .oc-title {
      position:relative; font-family:var(--serif); font-weight:700; letter-spacing:-.5px;
      font-size:38px; line-height:1.12; color:var(--ink); margin-bottom:18px;
    }
    .oc-lede { position:relative; font-size:15.5px; line-height:1.6; color:var(--muted); max-width:38ch; }
    .oc-points { position:relative; list-style:none; margin:34px 0 0; display:flex; flex-direction:column; gap:16px; }
    .oc-points li { display:flex; align-items:flex-start; gap:12px; font-size:14.5px; line-height:1.5; color:var(--body); }
    .oc-check {
      flex:none; width:22px; height:22px; border-radius:999px; margin-top:1px;
      background:var(--purple-tint); color:var(--purple);
      display:inline-flex; align-items:center; justify-content:center;
    }
    .oc-check svg { width:13px; height:13px; }
    .oc-aside-foot {
      position:relative; margin-top:auto; padding-top:26px; border-top:1px solid var(--hairline-soft);
      font-family:var(--serif); font-weight:500; font-size:16px; line-height:1.45; color:var(--muted); font-style:italic;
    }

    /* Colonne formulaire (droite) */
    .oc-form { padding:52px 48px; display:flex; flex-direction:column; justify-content:center; }
    .oc-form-h { font-family:var(--serif); font-weight:700; letter-spacing:-.3px; font-size:26px; color:var(--ink); margin-bottom:6px; }
    .oc-form-sub { font-size:14px; color:var(--muted); margin-bottom:26px; }

    .oc-alert {
      border-radius:10px; padding:12px 15px; margin-bottom:20px; font-size:13.5px; font-weight:500;
      background:rgba(198,69,69,.07); color:#a83b3b; border:1px solid rgba(198,69,69,.20);
    }
    .oc-info {
      display:flex; align-items:flex-start; gap:10px;
      border-radius:10px; padding:12px 15px; margin-bottom:20px; font-size:13px; line-height:1.5;
      background:rgba(139,82,250,.06); color:#5b3aa8; border:1px solid rgba(139,82,250,.18);
    }
    .oc-info svg { flex-shrink:0; margin-top:1px; }

    .oc-field { margin-bottom:16px; }
    .oc-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .oc-field label { display:block; font-size:13px; font-weight:500; color:var(--ink); margin-bottom:7px; }
    .oc-inwrap { position:relative; }
    .oc-input {
      width:100%; font-family:inherit; font-size:15px; color:var(--ink);
      padding:12px 14px; border:1px solid var(--hairline); border-radius:8px;
      background:#fff; transition:border-color 150ms var(--ease), box-shadow 150ms var(--ease);
    }
    .oc-input::placeholder { color:var(--faint); }
    .oc-input:focus { outline:none; border-color:var(--purple); box-shadow:0 0 0 3px var(--ring); }
    .oc-input.has-toggle { padding-right:44px; }
    .oc-input-error { border-color:#c64545; }
    .oc-input-error:focus { box-shadow:0 0 0 3px rgba(198,69,69,.16); }
    .oc-eye {
      position:absolute; top:50%; right:8px; transform:translateY(-50%);
      width:30px; height:30px; border:0; background:transparent; cursor:pointer;
      color:var(--faint); display:inline-flex; align-items:center; justify-content:center;
      border-radius:6px; transition:color 150ms var(--ease);
    }
    .oc-eye:hover { color:var(--muted); }
    .oc-eye svg { width:17px; height:17px; }
    .oc-err { font-size:12.5px; color:#a83b3b; margin-top:6px; font-weight:500; }
    .oc-hint { font-size:12px; color:var(--faint); margin-top:6px; }

    .oc-submit {
      width:100%; font-family:inherit; font-size:15px; font-weight:600; color:#fff;
      background:var(--purple); border:0; border-radius:8px; padding:13px 20px; cursor:pointer;
      margin-top:10px; transition:background 150ms var(--ease), transform 120ms var(--ease);
    }
    .oc-submit:hover { background:var(--purple-hover); }
    .oc-submit:active { background:var(--purple-press); transform:scale(.985); }

    .oc-login { margin-top:22px; font-size:14px; color:var(--muted); }
    .oc-login a { color:var(--purple); font-weight:500; text-decoration:none; }
    .oc-login a:hover { text-decoration:underline; }

    .oc-note {
      margin-top:20px; padding:13px 15px; border-radius:10px;
      background:var(--soft); border:1px solid var(--hairline-soft);
      font-size:13px; color:var(--muted); line-height:1.55;
    }
    .oc-note strong { color:var(--ink); font-weight:600; }

    /* ── Responsive ── */
    @media (max-width:860px) {
      .oc-shell { grid-template-columns:1fr; max-width:480px; }
      .oc-aside { border-right:0; border-bottom:1px solid var(--hairline); padding:38px 34px; }
      .oc-aside-foot { display:none; }
      .oc-points { margin-top:26px; }
      .oc-title { font-size:31px; }
      .oc-form { padding:38px 34px; }
      /* Empêche le zoom auto iOS au focus : 16px = seuil minimum sans zoom */
      .oc-input { font-size:16px; }
    }
    @media (max-width:520px) {
      .oc-top { padding:16px 18px; }
      .oc-back span { display:none; }
      .oc-main { padding:0 14px 40px; }
      .oc-aside { padding:32px 24px; }
      .oc-form { padding:32px 24px; }
      .oc-row { grid-template-columns:1fr; gap:0; }
      .oc-title { font-size:28px; }
    }
    @media (prefers-reduced-motion:reduce) { * { transition:none !important; } }
  </style>
</head>
<body>

  <header class="oc-top">
    <a href="<?= url('/orientation') ?>" class="oc-brand" aria-label="Connect'Academia — Orientation">
      <img src="<?= BASE_URL ?>/assets/images/logo-officiel.png" alt="">
      <b>Connect'Academia</b>
    </a>
    <a href="<?= url('/orientation') ?>" class="oc-back">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
      <span>Retour à l'orientation</span>
    </a>
  </header>

  <main class="oc-main">
    <div class="oc-shell">

      <!-- Panneau éditorial -->
      <aside class="oc-aside">
        <div class="oc-eyebrow">Espace orientation</div>
        <h1 class="oc-title">Ta passerelle vers l'orientation.</h1>
        <p class="oc-lede">Un seul espace pour suivre tes démarches auprès des établissements, garder tes documents à portée de main et avancer à ton rythme.</p>

        <ul class="oc-points">
          <li>
            <span class="oc-check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg></span>
            <span>Retrouve tes préinscriptions et inscriptions, réunies au même endroit.</span>
          </li>
          <li>
            <span class="oc-check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg></span>
            <span>Télécharge tes attestations officielles quand tu veux.</span>
          </li>
          <li>
            <span class="oc-check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg></span>
            <span>Ouvert à tous — parents, étudiants et candidats libres, sans être élève de la plateforme.</span>
          </li>
        </ul>

        <p class="oc-aside-foot">« De la préinscription à l'inscription, garde une longueur d'avance. »</p>
      </aside>

      <!-- Formulaire -->
      <section class="oc-form">
        <?php $poursuite = !empty($redirect) && $redirect !== '/eleve/orientation'; ?>
        <h2 class="oc-form-h">Crée ton compte</h2>
        <p class="oc-form-sub"><?= $poursuite
            ? 'Une étape avant ta démarche : crée ton compte pour poursuivre. Tes informations seront ensuite pré-remplies.'
            : 'Quelques informations suffisent pour commencer.' ?></p>

        <?php if ($poursuite): ?>
          <div class="oc-info" role="status">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            <span>Ton compte te permet de suivre ton dossier et de récupérer tes documents. Tu reprendras ta démarche juste après.</span>
          </div>
        <?php endif; ?>

        <?php if (!empty($errors['general'])): ?>
          <div class="oc-alert" role="alert"><?= e($errors['general']) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= url('/orientation/compte') ?>" novalidate>
          <input type="hidden" name="_csrf_token" value="<?= e($csrf ?? \Core\Session::getCsrfToken()) ?>">
          <input type="hidden" name="redirect" value="<?= e($redirect ?? '') ?>">

          <div class="oc-row">
            <div class="oc-field">
              <label for="oc-prenom">Prénom</label>
              <input type="text" id="oc-prenom" name="prenom" class="oc-input <?= isset($errors['prenom']) ? 'oc-input-error' : '' ?>"
                     value="<?= e($old['prenom'] ?? '') ?>" required minlength="2" maxlength="100" autocomplete="given-name" placeholder="Aïcha">
              <?php if (isset($errors['prenom'])): ?><div class="oc-err"><?= e($errors['prenom']) ?></div><?php endif; ?>
            </div>
            <div class="oc-field">
              <label for="oc-nom">Nom</label>
              <input type="text" id="oc-nom" name="nom" class="oc-input <?= isset($errors['nom']) ? 'oc-input-error' : '' ?>"
                     value="<?= e($old['nom'] ?? '') ?>" required minlength="2" maxlength="100" autocomplete="family-name" placeholder="Moussavou">
              <?php if (isset($errors['nom'])): ?><div class="oc-err"><?= e($errors['nom']) ?></div><?php endif; ?>
            </div>
          </div>

          <div class="oc-field">
            <label for="oc-email">Adresse email</label>
            <input type="email" id="oc-email" name="email" class="oc-input <?= isset($errors['email']) ? 'oc-input-error' : '' ?>"
                   value="<?= e($old['email'] ?? '') ?>" required maxlength="255" autocomplete="email" placeholder="toi@exemple.com">
            <?php if (isset($errors['email'])): ?><div class="oc-err"><?= e($errors['email']) ?></div><?php endif; ?>
          </div>

          <div class="oc-field">
            <label for="oc-telephone">Téléphone</label>
            <input type="tel" id="oc-telephone" name="telephone" class="oc-input <?= isset($errors['telephone']) ? 'oc-input-error' : '' ?>"
                   value="<?= e($old['telephone'] ?? '') ?>" required maxlength="20" inputmode="tel" autocomplete="tel"
                   placeholder="074 12 34 56" aria-describedby="oc-tel-hint">
            <?php if (isset($errors['telephone'])): ?>
              <div class="oc-err"><?= e($errors['telephone']) ?></div>
            <?php else: ?>
              <div id="oc-tel-hint" class="oc-hint">Numéro gabonais — commence par 01, 06 ou 07.</div>
            <?php endif; ?>
          </div>

          <div class="oc-row">
            <div class="oc-field">
              <label for="oc-password">Mot de passe</label>
              <div class="oc-inwrap">
                <input type="password" id="oc-password" name="password" class="oc-input has-toggle <?= isset($errors['password']) ? 'oc-input-error' : '' ?>"
                       required minlength="8" maxlength="128" autocomplete="new-password" placeholder="8 caractères min.">
                <button type="button" class="oc-eye" data-toggle="oc-password" aria-label="Afficher le mot de passe">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
              </div>
              <?php if (isset($errors['password'])): ?><div class="oc-err"><?= e($errors['password']) ?></div><?php endif; ?>
            </div>
            <div class="oc-field">
              <label for="oc-confirm">Confirmation</label>
              <div class="oc-inwrap">
                <input type="password" id="oc-confirm" name="password_confirmation" class="oc-input has-toggle <?= isset($errors['password_confirmation']) ? 'oc-input-error' : '' ?>"
                       required autocomplete="new-password" placeholder="Répète le mot de passe">
                <button type="button" class="oc-eye" data-toggle="oc-confirm" aria-label="Afficher le mot de passe">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
              </div>
              <?php if (isset($errors['password_confirmation'])): ?><div class="oc-err"><?= e($errors['password_confirmation']) ?></div><?php endif; ?>
            </div>
          </div>

          <button type="submit" class="oc-submit">Créer mon espace</button>
        </form>

        <p class="oc-login">Déjà un compte ? <a href="<?= url('/auth/connexion') ?>?redirect=<?= urlencode($redirect ?? '/eleve/orientation') ?>">Se connecter</a></p>

        <div class="oc-note">
          <strong>Déjà préinscrit ?</strong> Utilise la même adresse email que celle de ton dossier : tes candidatures seront automatiquement retrouvées dans ton espace.
        </div>
      </section>

    </div>
  </main>

  <script>
    // Bascule affichage mot de passe (aucune dépendance externe)
    document.querySelectorAll('.oc-eye').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var input = document.getElementById(btn.getAttribute('data-toggle'));
        if (!input) return;
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-label', show ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
        btn.style.color = show ? 'var(--purple)' : '';
      });
    });
  </script>

</body>
</html>
