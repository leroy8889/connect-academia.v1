<?php
// Préinscriptions — variables : $dossiers (chacun avec recepisse_url éventuel)
require_once __DIR__ . '/_helpers.php';
$dossiers = $dossiers ?? [];
?>

<div class="acct-shead">
  <div>
    <span class="acct-shead-kick"><?= acct_icon('fileText', 12) ?> gratuite · instantanée</span>
    <h1 class="acct-shead-t">Mes préinscriptions</h1>
    <p class="acct-shead-d">Toutes vos démarches de préinscription, tous établissements confondus, avec l'attestation officielle à télécharger.</p>
  </div>
  <a href="<?= url('/orientation') ?>" class="abtn glass"><?= acct_icon('compass', 15) ?> Explorer les établissements</a>
</div>

<?php if (empty($dossiers)): ?>
  <div class="acct-empty gls">
    <div class="acct-empty-ic"><?= acct_icon('fileText', 22) ?></div>
    <div class="acct-empty-t">Aucune préinscription pour le moment</div>
    <div class="acct-empty-d">Explorez les universités et grandes écoles partenaires et déposez votre première préinscription — elle est gratuite et votre attestation est générée immédiatement.</div>
    <a href="<?= url('/orientation') ?>" class="abtn primary"><?= acct_icon('compass', 15) ?> Explorer les établissements</a>
  </div>
<?php else: ?>
  <?php foreach ($dossiers as $d): ?>
    <div class="acct-card gls">
      <div class="acct-card-top">
        <div>
          <div class="acct-card-title"><?= acct_etab_logo($d, 34) ?><?= e($d['etab_nom'] ?? '—') ?></div>
          <div class="acct-card-sub"><?= e(acct_filiere_spec($d)) ?><?= !empty($d['niveau_entree']) ? ' · Niveau d\'entrée : ' . e($d['niveau_entree']) : '' ?></div>
        </div>
        <?= acct_statut_badge($d) ?>
      </div>
      <div class="acct-kv-row">
        <div class="acct-kv"><div class="l">Attestation</div><div class="v"><?= e($d['numero_recepisse'] ?? '—') ?></div></div>
        <div class="acct-kv"><div class="l">Soumise le</div><div class="v"><?= acct_date($d['date_creation'] ?? null) ?></div></div>
        <div class="acct-kv"><div class="l">Candidat</div><div class="v"><?= e($d['nom_complet'] ?? '—') ?></div></div>
        <div class="acct-kv"><div class="l">Statut du dossier</div><div class="v"><?= e(acct_statut_label($d)) ?></div></div>
      </div>
      <div class="acct-card-foot">
        <?php if (($d['statut'] ?? '') !== 'refuse'): ?>
          <a href="<?= url('/eleve/orientation/dossier/' . hid((int)$d['id']) . '/recepisse') ?>" target="_blank" rel="noopener" class="abtn primary"><?= acct_icon('download', 15) ?> Télécharger l'attestation</a>
        <?php endif; ?>
        <a href="<?= url('/eleve/orientation/dossier/' . hid((int)$d['id'])) ?>" class="abtn glass">Voir le dossier <?= acct_icon('arrowR', 14) ?></a>
        <?php if (!empty($d['etab_slug']) && ($d['statut'] ?? '') !== 'refuse'): ?>
          <a href="<?= url('/orientation/' . e($d['etab_slug']) . '/inscription') ?>" class="abtn ghost">Poursuivre vers l'inscription <?= acct_icon('arrowR', 14) ?></a>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="acct-footnote"><?= acct_icon('clock', 12) ?> Attestation officielle générée aux couleurs de l'établissement — ouvrez-le puis imprimez ou enregistrez en PDF (Ctrl+P).</div>
<?php endif; ?>
