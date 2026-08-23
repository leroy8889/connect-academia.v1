<?php
// Inscriptions — variables : $dossiers (avec pieces, pieces_ok, pieces_total, oblig_manquantes, attestation_url)
require_once __DIR__ . '/_helpers.php';
$dossiers = $dossiers ?? [];
?>

<div class="acct-shead">
  <div>
    <span class="acct-shead-kick"><?= acct_icon('briefcase', 12) ?> assistant en 2 étapes</span>
    <h1 class="acct-shead-t">Mes inscriptions</h1>
    <p class="acct-shead-d">Informations personnelles → Formation &amp; dossier académique. Après soumission, l'établissement étudie votre dossier puis délivre l'attestation d'inscription.</p>
  </div>
</div>

<?php if (empty($dossiers)): ?>
  <div class="acct-empty gls">
    <div class="acct-empty-ic"><?= acct_icon('briefcase', 22) ?></div>
    <div class="acct-empty-t">Aucun dossier d'inscription</div>
    <div class="acct-empty-d">Déposez votre dossier d'inscription en ligne : dès validation par l'établissement, votre attestation officielle apparaîtra ici.</div>
    <a href="<?= url('/orientation') ?>" class="abtn primary"><?= acct_icon('compass', 15) ?> Découvrir les établissements</a>
  </div>
<?php else: ?>
  <?php foreach ($dossiers as $d): ?>
    <?php
      $statut = $d['statut'] ?? 'brouillon';
      $estValide = in_array($statut, ['inscrit','accepte'], true); // inscription validée
      $refDossier = $d['numero_attestation'] ?? ('DOS-' . str_pad((string)(int)$d['id'], 6, '0', STR_PAD_LEFT));
    ?>
    <div class="acct-card gls">
      <div class="acct-card-top">
        <div>
          <div class="acct-card-title"><?= acct_etab_logo($d, 34) ?><?= e($d['etab_nom'] ?? '—') ?></div>
          <div class="acct-card-sub">Dossier <?= e($refDossier) ?> · créé le <?= acct_date($d['date_creation'] ?? null) ?></div>
        </div>
        <?= acct_statut_badge($d) ?>
      </div>

      <div class="acct-stepper" style="margin-top:4px;">
        <div class="acct-step done">
          <div class="line"></div><div class="acct-step-dot"><?= acct_icon('check', 13) ?></div>
          <div class="acct-step-t">1. Informations</div>
          <div class="acct-step-x">Enregistrées le <?= acct_date($d['date_creation'] ?? null) ?></div>
        </div>
        <div class="acct-step <?= $statut === 'brouillon' ? 'active' : 'done' ?>">
          <div class="line"></div>
          <div class="acct-step-dot"><?= $statut === 'brouillon' ? '2' : acct_icon('check', 13) ?></div>
          <div class="acct-step-t">2. Formation &amp; dossier</div>
          <div class="acct-step-x"><?= $statut === 'brouillon' ? 'Filière et pièces à compléter' : e(acct_filiere_spec($d)) . (!empty($d['niveau_entree']) ? ' · ' . e($d['niveau_entree']) : '') ?></div>
        </div>
        <div class="acct-step <?= $estValide ? 'done' : ($statut === 'refuse' ? 'err' : ($statut === 'brouillon' ? 'pending' : 'active')) ?>">
          <div class="line"></div>
          <div class="acct-step-dot"><?= $estValide ? acct_icon('check', 13) : '3' ?></div>
          <div class="acct-step-t">Validation</div>
          <div class="acct-step-x"><?= $estValide ? 'Inscription validée' : ($statut === 'refuse' ? 'Dossier refusé' : 'Étude par l\'établissement') ?></div>
        </div>
        <div class="acct-step <?= $estValide && !empty($d['numero_attestation']) ? 'done' : 'pending' ?>">
          <div class="acct-step-dot"><?= $estValide && !empty($d['numero_attestation']) ? acct_icon('check', 13) : '4' ?></div>
          <div class="acct-step-t">Attestation</div>
          <div class="acct-step-x"><?= $estValide && !empty($d['numero_attestation']) ? e($d['numero_attestation']) : 'Délivrée après validation' ?></div>
        </div>
      </div>

      <?php if ((int)($d['pieces_total'] ?? 0) > 0): ?>
        <div class="acct-card-sub" style="margin-top:14px;">
          <?= (int)$d['pieces_ok'] ?> pièce<?= (int)$d['pieces_ok'] > 1 ? 's' : '' ?> sur <?= (int)$d['pieces_total'] ?> déposée<?= (int)$d['pieces_ok'] > 1 ? 's' : '' ?>
          <?php if ((int)$d['oblig_manquantes'] > 0): ?>
            · <span style="color:var(--warn-fg);"><?= (int)$d['oblig_manquantes'] ?> obligatoire<?= (int)$d['oblig_manquantes'] > 1 ? 's' : '' ?> manquante<?= (int)$d['oblig_manquantes'] > 1 ? 's' : '' ?></span>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="acct-card-foot">
        <?php if ($statut === 'brouillon' && !empty($d['etab_slug'])): ?>
          <a href="<?= url('/orientation/' . e($d['etab_slug']) . '/inscription') ?>" class="abtn primary"><?= acct_icon('arrowR', 15) ?> Reprendre mon dossier</a>
        <?php endif; ?>
        <?php if ($estValide): ?>
          <a href="<?= url('/eleve/orientation/dossier/' . hid((int)$d['id']) . '/attestation') ?>" target="_blank" rel="noopener" class="abtn primary"><?= acct_icon('download', 15) ?> Télécharger l'attestation</a>
        <?php endif; ?>
        <a href="<?= url('/eleve/orientation/dossier/' . hid((int)$d['id'])) ?>" class="abtn glass">Voir le dossier <?= acct_icon('arrowR', 14) ?></a>
        <?php if (!empty($d['paiement_montant'])): ?>
          <span class="badge2 ok"><?= acct_icon('card', 12) ?> <?= acct_montant($d['paiement_montant']) ?> réglés</span>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="acct-footnote"><?= acct_icon('shield', 12) ?> Votre avancement est sauvegardé à chaque étape — vous pouvez reprendre un dossier en brouillon à tout moment.</div>
<?php endif; ?>
