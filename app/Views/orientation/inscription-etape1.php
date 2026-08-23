<?php
$couleur  = e($etab['couleur_principale'] ?? '#8B52FA');
$etabNom  = e($etab['nom']);
$etabSlug = e($etab['slug']);
$o   = $old ?? [];
$err = $errors ?? [];

// Inscription payante → parcours en 3 étapes (paiement), sinon 2 étapes
$payant     = ((int)($etab['prix_inscription'] ?? 0) + (int)($etab['frais_dossier'] ?? 0)) > 0;
$totalSteps = $payant ? 3 : 2;

function oldE1(string $key, array $old, string $default = ''): string {
    return isset($old[$key]) ? e($old[$key]) : $default;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Inscription — Étape 1 — <?= $etabNom ?></title>
  <link rel="stylesheet" href="<?= asset('orientation/candidature.css') ?>">
  <style>:root{--brand:<?= $couleur ?>}</style>
</head>
<body>

<header class="cand-topbar">
  <a href="<?= url('orientation/' . $etabSlug) ?>" class="cand-back">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
    Retour à la page école
  </a>
  <span class="cand-step-label">Étape 1 / <?= $totalSteps ?></span>
</header>

<main class="cand-page">

  <!-- Stepper -->
  <div class="stepper" aria-label="Progression">
    <div class="step active">
      <div class="step-circle">1</div>
      <div class="step-name">Informations</div>
    </div>
    <div class="step-connector"></div>
    <div class="step">
      <div class="step-circle">2</div>
      <div class="step-name">Formation &amp; dossier</div>
    </div>
    <?php if ($payant): ?>
    <div class="step-connector"></div>
    <div class="step">
      <div class="step-circle">3</div>
      <div class="step-name">Paiement</div>
    </div>
    <?php endif; ?>
  </div>

  <?php if (!empty($err['general'])): ?>
  <div class="alert alert-error" style="margin-bottom:20px">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?= e($err['general']) ?>
  </div>
  <?php endif; ?>

  <form method="POST" action="<?= url("orientation/{$etabSlug}/inscription/etape-1") ?>" novalidate>
    <input type="hidden" name="_csrf_token" value="<?= e($csrf) ?>">

    <div class="cand-card">
      <div class="cand-card-head">
        <h2>Informations personnelles</h2>
        <p>Ces informations seront utilisées pour créer votre dossier candidat à <?= $etabNom ?>.</p>
      </div>

      <div class="form-section">

        <div class="form-row full">
          <div class="form-group">
            <label for="nom_complet">Nom complet <span class="req">*</span></label>
            <input type="text" id="nom_complet" name="nom_complet"
                   value="<?= oldE1('nom_complet', $o) ?>"
                   placeholder="Ex. Christian ONDO"
                   class="<?= isset($err['nom_complet']) ? 'is-error' : '' ?>"
                   required autocomplete="name">
            <?php if (isset($err['nom_complet'])): ?><span class="field-error"><?= e($err['nom_complet']) ?></span><?php endif; ?>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="genre">Genre <span class="req">*</span></label>
            <select id="genre" name="genre" class="<?= isset($err['genre']) ? 'is-error' : '' ?>" required>
              <option value="">— Choisir —</option>
              <option value="homme" <?= oldE1('genre', $o) === 'homme' ? 'selected' : '' ?>>Homme</option>
              <option value="femme" <?= oldE1('genre', $o) === 'femme' ? 'selected' : '' ?>>Femme</option>
            </select>
            <?php if (isset($err['genre'])): ?><span class="field-error"><?= e($err['genre']) ?></span><?php endif; ?>
          </div>
          <div class="form-group">
            <label for="date_naissance">Date de naissance <span class="req">*</span></label>
            <input type="date" id="date_naissance" name="date_naissance"
                   value="<?= oldE1('date_naissance', $o) ?>"
                   min="1950-01-01" max="<?= date('Y-m-d') ?>"
                   class="<?= isset($err['date_naissance']) ? 'is-error' : '' ?>" required>
            <?php if (isset($err['date_naissance'])): ?><span class="field-error"><?= e($err['date_naissance']) ?></span><?php endif; ?>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="lieu_naissance">Lieu de naissance <span class="req">*</span></label>
            <input type="text" id="lieu_naissance" name="lieu_naissance"
                   value="<?= oldE1('lieu_naissance', $o) ?>"
                   placeholder="Ex. Libreville"
                   class="<?= isset($err['lieu_naissance']) ? 'is-error' : '' ?>" required>
            <?php if (isset($err['lieu_naissance'])): ?><span class="field-error"><?= e($err['lieu_naissance']) ?></span><?php endif; ?>
          </div>
          <div class="form-group">
            <label for="nationalite">Nationalité <span class="req">*</span></label>
            <input type="text" id="nationalite" name="nationalite"
                   value="<?= oldE1('nationalite', $o) ?: 'Gabonaise' ?>"
                   class="<?= isset($err['nationalite']) ? 'is-error' : '' ?>" required>
            <?php if (isset($err['nationalite'])): ?><span class="field-error"><?= e($err['nationalite']) ?></span><?php endif; ?>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="email">E-mail <span class="req">*</span></label>
            <input type="email" id="email" name="email"
                   value="<?= oldE1('email', $o) ?>"
                   placeholder="prenom@exemple.com"
                   class="<?= isset($err['email']) ? 'is-error' : '' ?>"
                   required autocomplete="email">
            <?php if (isset($err['email'])): ?><span class="field-error"><?= e($err['email']) ?></span><?php endif; ?>
          </div>
          <div class="form-group">
            <label for="telephone">Téléphone <span class="req">*</span></label>
            <div class="tel-group">
              <select name="indicatif_tel" class="tel-code" aria-label="Indicatif pays">
                <?php foreach ($indicatifs as $code => $label): ?>
                <option value="<?= e($code) ?>" <?= oldE1('indicatif_tel', $o, '+241') === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <input type="tel" id="telephone" name="telephone"
                     value="<?= oldE1('telephone', $o) ?>"
                     placeholder="07 XX XX XX"
                     class="tel-num <?= isset($err['telephone']) ? 'is-error' : '' ?>" required>
            </div>
            <?php if (isset($err['telephone'])): ?><span class="field-error"><?= e($err['telephone']) ?></span><?php endif; ?>
          </div>
        </div>

        <div class="form-row full">
          <div class="form-group">
            <label for="adresse">Adresse complète <span class="req">*</span></label>
            <input type="text" id="adresse" name="adresse"
                   value="<?= oldE1('adresse', $o) ?>"
                   placeholder="Quartier, rue, n°…"
                   class="<?= isset($err['adresse']) ? 'is-error' : '' ?>" required>
            <?php if (isset($err['adresse'])): ?><span class="field-error"><?= e($err['adresse']) ?></span><?php endif; ?>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="ville">Ville <span class="req">*</span></label>
            <input type="text" id="ville" name="ville"
                   value="<?= oldE1('ville', $o) ?>"
                   placeholder="Ex. Libreville"
                   class="<?= isset($err['ville']) ? 'is-error' : '' ?>" required>
            <?php if (isset($err['ville'])): ?><span class="field-error"><?= e($err['ville']) ?></span><?php endif; ?>
          </div>
          <div class="form-group">
            <label for="pays_provenance">Pays de provenance <span class="req">*</span></label>
            <input type="text" id="pays_provenance" name="pays_provenance"
                   value="<?= oldE1('pays_provenance', $o) ?: 'Gabon' ?>"
                   class="<?= isset($err['pays_provenance']) ? 'is-error' : '' ?>" required>
            <?php if (isset($err['pays_provenance'])): ?><span class="field-error"><?= e($err['pays_provenance']) ?></span><?php endif; ?>
          </div>
        </div>

        <div class="form-row full">
          <div class="form-group">
            <label for="dernier_etablissement">Dernier établissement fréquenté <span class="req">*</span></label>
            <input type="text" id="dernier_etablissement" name="dernier_etablissement"
                   value="<?= oldE1('dernier_etablissement', $o) ?>"
                   placeholder="Ex. Lycée Léon Mba"
                   class="<?= isset($err['dernier_etablissement']) ? 'is-error' : '' ?>" required>
            <?php if (isset($err['dernier_etablissement'])): ?><span class="field-error"><?= e($err['dernier_etablissement']) ?></span><?php endif; ?>
          </div>
        </div>

      </div><!-- /form-section -->
    </div><!-- /cand-card -->

    <!-- Navigation -->
    <div class="nav-footer">
      <button type="button" class="btn-ghost" disabled aria-disabled="true">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
        Précédent
      </button>
      <span></span>
      <button type="submit" class="btn-primary">
        Suivant
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
      </button>
    </div>

  </form>
</main>

</body>
</html>
