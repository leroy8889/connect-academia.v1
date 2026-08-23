<?php
// Détail d'un dossier — variables : $dossier, $pieces, $recuUrl
require_once __DIR__ . '/_helpers.php';
$d      = $dossier ?? [];
$pieces = $pieces  ?? [];

$estInscription = !empty($d['inscription_id']) || ($d['statut'] ?? '') === 'brouillon';
$statut  = $d['statut'] ?? 'brouillon';
$estValide = in_array($statut, ['inscrit','accepte'], true); // inscription validée
$refDossier = $d['numero_attestation'] ?? $d['numero_recepisse'] ?? ('DOS-' . str_pad((string)(int)($d['id'] ?? 0), 6, '0', STR_PAD_LEFT));
$genreLabels = ['homme' => 'Masculin', 'femme' => 'Féminin', 'autre' => 'Autre'];
$retour = $estInscription ? '/eleve/orientation/inscriptions' : '/eleve/orientation/preinscriptions';

// Téléchargements (routes on-demand : toujours fiables, indépendantes du B2)
$dossierId       = (int)($d['id'] ?? 0);
$dossierHash     = hid($dossierId);
$peutRecepisse   = !empty($d['numero_recepisse']) || (!$estInscription && !in_array($statut, ['brouillon','refuse'], true));
$peutAttestation = $estValide;

$nbOk = count(array_filter($pieces, fn($p) => $p['ok']));
?>

<div class="acct-shead">
  <div>
    <a href="<?= url($retour) ?>" class="acct-shead-kick" style="text-decoration:none;cursor:pointer;">← <?= $estInscription ? 'mes inscriptions' : 'mes préinscriptions' ?></a>
    <h1 class="acct-shead-t"><?= e($d['etab_nom'] ?? 'Dossier') ?></h1>
    <p class="acct-shead-d"><?= e(acct_filiere_spec($d)) ?><?= !empty($d['niveau_entree']) ? ' · ' . e($d['niveau_entree']) : '' ?><?= !empty($d['formation_intitule']) ? ' · ' . e($d['formation_intitule']) : '' ?></p>
  </div>
  <?= acct_statut_badge($d) ?>
</div>

<div class="acct-dossier gls">
  <div class="acct-dossier-top">
    <?= acct_etab_logo($d) ?>
    <div>
      <div class="acct-dossier-name"><?= $estInscription ? 'Dossier d\'inscription' : 'Préinscription' ?></div>
      <div class="acct-dossier-meta">Déposé le <?= acct_date($d['date_creation'] ?? null) ?> · <?= ($d['origine'] ?? '') === 'etablissement' ? 'au guichet de l\'établissement' : 'en ligne' ?></div>
    </div>
    <div class="acct-dossier-ref">
      <div class="l">Référence</div>
      <div class="v"><?= e($refDossier) ?></div>
    </div>
  </div>

  <?php if ($estInscription): ?>
    <div class="acct-stepper">
      <div class="acct-step done">
        <div class="line"></div><div class="acct-step-dot"><?= acct_icon('check', 13) ?></div>
        <div class="acct-step-t">Informations</div>
        <div class="acct-step-x">Enregistrées le <?= acct_date($d['date_creation'] ?? null) ?></div>
      </div>
      <div class="acct-step <?= $statut === 'brouillon' ? 'active' : 'done' ?>">
        <div class="line"></div>
        <div class="acct-step-dot"><?= $statut === 'brouillon' ? '2' : acct_icon('check', 13) ?></div>
        <div class="acct-step-t">Dossier &amp; pièces</div>
        <div class="acct-step-x"><?= $statut === 'brouillon' ? 'À compléter' : 'Dossier soumis' ?></div>
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
  <?php else: ?>
    <div class="acct-stepper s3">
      <div class="acct-step done">
        <div class="line"></div><div class="acct-step-dot"><?= acct_icon('check', 13) ?></div>
        <div class="acct-step-t">Préinscription</div>
        <div class="acct-step-x">Soumise le <?= acct_date($d['date_creation'] ?? null) ?></div>
      </div>
      <div class="acct-step <?= !empty($d['numero_recepisse']) ? 'done' : 'active' ?>">
        <div class="line"></div>
        <div class="acct-step-dot"><?= !empty($d['numero_recepisse']) ? acct_icon('check', 13) : '2' ?></div>
        <div class="acct-step-t">Attestation</div>
        <div class="acct-step-x"><?= !empty($d['numero_recepisse']) ? e($d['numero_recepisse']) : 'En cours de génération' ?></div>
      </div>
      <div class="acct-step <?= $statut === 'accepte' ? 'done' : 'pending' ?>">
        <div class="acct-step-dot"><?= $statut === 'accepte' ? acct_icon('check', 13) : '3' ?></div>
        <div class="acct-step-t">Suite du parcours</div>
        <div class="acct-step-x"><?= $statut === 'accepte' ? 'Préinscription acceptée' : 'Poursuivre vers l\'inscription' ?></div>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php
  // CTA « poursuivre vers l'inscription » pour une préinscription non refusée
  $peutPoursuivre = !$estInscription && !empty($d['etab_slug']) && !in_array($statut, ['refuse'], true);
?>
<?php if ($peutRecepisse || $peutAttestation || $recuUrl || $peutPoursuivre || ($statut === 'brouillon' && !empty($d['etab_slug']))): ?>
  <div class="acct-card gls">
    <div class="acct-card-title" style="margin-bottom:12px;"><?= acct_icon('download', 16) ?> Documents &amp; démarches</div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <?php if ($statut === 'brouillon' && !empty($d['etab_slug'])): ?>
        <a href="<?= url('/orientation/' . e($d['etab_slug']) . '/inscription') ?>" class="abtn primary"><?= acct_icon('arrowR', 15) ?> Reprendre mon dossier</a>
      <?php endif; ?>
      <?php if ($peutAttestation): ?>
        <a href="<?= url('/eleve/orientation/dossier/' . $dossierHash . '/attestation') ?>" target="_blank" rel="noopener" class="abtn primary"><?= acct_icon('download', 15) ?> Attestation d'inscription<?= !empty($d['numero_attestation']) ? ' · ' . e($d['numero_attestation']) : '' ?></a>
      <?php endif; ?>
      <?php if ($peutRecepisse): ?>
        <a href="<?= url('/eleve/orientation/dossier/' . $dossierHash . '/recepisse') ?>" target="_blank" rel="noopener" class="abtn <?= $peutAttestation ? 'glass' : 'primary' ?>"><?= acct_icon('download', 15) ?> Attestation de préinscription</a>
      <?php endif; ?>
      <?php if ($recuUrl): ?>
        <a href="<?= e($recuUrl) ?>" target="_blank" rel="noopener" class="abtn glass"><?= acct_icon('receipt', 15) ?> Reçu de paiement<?= !empty($d['numero_recu']) ? ' · ' . e($d['numero_recu']) : '' ?></a>
      <?php endif; ?>
      <?php if ($peutPoursuivre): ?>
        <a href="<?= url('/orientation/' . e($d['etab_slug']) . '/inscription') ?>" class="abtn ghost"><?= acct_icon('arrowR', 15) ?> Poursuivre vers l'inscription</a>
      <?php endif; ?>
    </div>
    <div class="acct-footnote" style="margin-top:12px;"><?= acct_icon('clock', 12) ?> Documents officiels aux couleurs de l'établissement — ouvrez-les puis imprimez ou enregistrez en PDF (Ctrl+P).</div>
  </div>
<?php endif; ?>

<?php if (!empty($d['paiement_montant'])): ?>
  <div class="acct-card gls">
    <div class="acct-card-title" style="margin-bottom:4px;"><?= acct_icon('card', 16) ?> Paiement</div>
    <div class="acct-kv-row" style="grid-template-columns:repeat(3,1fr);">
      <div class="acct-kv"><div class="l">Montant</div><div class="v"><?= acct_montant($d['paiement_montant']) ?></div></div>
      <div class="acct-kv"><div class="l">Statut</div><div class="v" style="color:var(--ok-fg);">Confirmé</div></div>
      <div class="acct-kv"><div class="l">Reçu</div><div class="v"><?= e($d['numero_recu'] ?? 'En cours de génération') ?></div></div>
    </div>
  </div>
<?php endif; ?>

<?php if (!empty($pieces)): ?>
  <div class="acct-card gls">
    <div class="acct-card-title" style="margin-bottom:4px;"><?= acct_icon('folder', 16) ?> Pièces justificatives</div>
    <div class="acct-card-sub" style="margin-bottom:8px;"><?= $nbOk ?> sur <?= count($pieces) ?> déposée<?= $nbOk > 1 ? 's' : '' ?></div>
    <?php foreach ($pieces as $p): ?>
      <?php $doc = $p['doc'] ?? null; ?>
      <div class="acct-doc-row">
        <div class="acct-doc-ic <?= $p['ok'] ? 'ok' : 'miss' ?>"><?= acct_icon($p['ok'] ? 'check' : 'upload', 17) ?></div>
        <div>
          <div class="acct-doc-name"><?= e($p['libelle']) ?></div>
          <div class="acct-doc-sub">
            <?php if ($p['ok'] && $doc): ?>
              Déposée le <?= acct_date($doc['date_depot'] ?? null) ?><?= !empty($doc['taille_octets']) ? ' · ' . acct_taille((int)$doc['taille_octets']) : '' ?>
            <?php elseif ($p['obligatoire']): ?>
              Manquante — requise pour finaliser l'inscription
            <?php else: ?>
              Optionnelle — non déposée
            <?php endif; ?>
          </div>
        </div>
        <div class="acct-doc-actions">
          <?php if ($p['ok'] && !empty($doc['url_doc'])): ?>
            <a href="<?= e($doc['url_doc']) ?>" target="_blank" rel="noopener" class="abtn ghost sm"><?= acct_icon('download', 13) ?> Voir</a>
          <?php elseif (!$p['ok'] && $statut === 'brouillon' && !empty($d['etab_slug'])): ?>
            <a href="<?= url('/orientation/' . e($d['etab_slug']) . '/inscription') ?>" class="abtn glass sm"><?= acct_icon('upload', 13) ?> Déposer</a>
          <?php elseif (!$p['ok']): ?>
            <span class="badge2 <?= $p['obligatoire'] ? 'warn' : 'draft' ?>"><?= $p['obligatoire'] ? 'À déposer' : 'Optionnelle' ?></span>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="acct-card gls">
  <div class="acct-card-title" style="margin-bottom:4px;"><?= acct_icon('idcard', 16) ?> Identité du candidat</div>
  <div class="acct-profile-grid" style="margin-top:6px;">
    <div class="acct-field"><span class="l">Nom complet</span><span class="v"><?= e($d['nom_complet'] ?? '—') ?></span></div>
    <div class="acct-field"><span class="l">Genre</span><span class="v"><?= e($genreLabels[$d['genre'] ?? ''] ?? '—') ?></span></div>
    <div class="acct-field"><span class="l">Date de naissance</span><span class="v"><?= acct_date($d['date_naissance'] ?? null) ?></span></div>
    <div class="acct-field"><span class="l">Lieu de naissance</span><span class="v"><?= e($d['lieu_naissance'] ?? '—') ?></span></div>
    <div class="acct-field"><span class="l">Nationalité</span><span class="v"><?= e($d['nationalite'] ?? '—') ?></span></div>
    <div class="acct-field"><span class="l">Dernier établissement</span><span class="v"><?= e($d['dernier_etablissement'] ?? '—') ?></span></div>
    <div class="acct-field"><span class="l">Email</span><span class="v"><?= e($d['email'] ?? '—') ?></span></div>
    <div class="acct-field"><span class="l">Téléphone</span><span class="v"><?= e(trim(($d['indicatif_tel'] ?? '') . ' ' . ($d['telephone'] ?? '')) ?: '—') ?></span></div>
    <div class="acct-field"><span class="l">Pays de provenance</span><span class="v"><?= e($d['pays_provenance'] ?? '—') ?></span></div>
    <div class="acct-field"><span class="l">Ville · Adresse</span><span class="v"><?= e(trim(($d['ville'] ?? '') . (empty($d['adresse']) ? '' : ' · ' . $d['adresse'])) ?: '—') ?></span></div>
  </div>
</div>
