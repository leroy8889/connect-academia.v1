<?php
// Vue d'ensemble — variables : $user, $actif, $preins, $inscs, $pieces, $feed, $navCounts
require_once __DIR__ . '/_helpers.php';

$prenom   = trim($user['prenom'] ?? '');
$actif    = $actif  ?? null;
$preins   = $preins ?? [];
$inscs    = $inscs  ?? [];
$pieces   = $pieces ?? [];
$feed     = $feed   ?? [];
$nbDocs   = (int)($navCounts['documents'] ?? 0);
$nbNotifs = (int)($navCounts['notifications'] ?? 0);

$nbPreinsOk = count(array_filter($preins, fn($d) => in_array($d['statut'] ?? '', ['soumis', 'accepte'], true)));
$nbInscsEnCours = count(array_filter($inscs, fn($d) => in_array($d['statut'] ?? '', ['brouillon', 'soumis', 'paye'], true)));

$piecesOk    = count(array_filter($pieces, fn($p) => $p['ok']));
$piecesTotal = count($pieces);
$piecesManquantes = count(array_filter($pieces, fn($p) => $p['obligatoire'] && !$p['ok']));
?>

<div class="acct-shead">
  <div>
    <span class="acct-shead-kick"><?= acct_icon('grid', 12) ?> vue d'ensemble</span>
    <h1 class="acct-shead-t"><?= $prenom !== '' ? 'Bonjour ' . e($prenom) : 'Bienvenue' ?></h1>
    <p class="acct-shead-d">Un seul espace pour tous vos établissements&nbsp;: préinscriptions, inscriptions, paiements, reçus, documents et notifications.</p>
  </div>
</div>

<?php if ($actif): ?>
  <?php
    $estInscription = \Models\Orientation\Dossier::estInscription($actif);
    $statut = $actif['statut'] ?? 'brouillon';
    $estValide = in_array($statut, ['inscrit','accepte'], true); // inscription validée
    $refDossier = $actif['numero_attestation'] ?? $actif['numero_recepisse'] ?? ('DOS-' . str_pad((string)(int)$actif['id'], 6, '0', STR_PAD_LEFT));
    $couleur = preg_match('/^#[0-9a-fA-F]{6}$/', $actif['couleur_principale'] ?? '') ? $actif['couleur_principale'] : '#3B6EA5';
  ?>
  <a href="<?= url('/eleve/orientation/dossier/' . hid((int)$actif['id'])) ?>" class="acct-dossier gls" style="--brand:<?= e($couleur) ?>;">
    <div class="acct-dossier-top">
      <?= acct_etab_logo($actif) ?>
      <div>
        <div class="acct-dossier-name"><?= e($actif['etab_nom'] ?? '—') ?></div>
        <div class="acct-dossier-meta">
          <?= e(acct_filiere_spec($actif)) ?><?= !empty($actif['niveau_entree']) ? ' · ' . e($actif['niveau_entree']) : '' ?><?= !empty($actif['ville']) ? ' · ' . e($actif['ville']) : '' ?>
        </div>
      </div>
      <div class="acct-dossier-ref">
        <div class="l">Dossier</div>
        <div class="v"><?= e($refDossier) ?></div>
      </div>
    </div>

    <?php if ($estInscription): ?>
      <div class="acct-stepper">
        <div class="acct-step done">
          <div class="line"></div><div class="acct-step-dot"><?= acct_icon('check', 13) ?></div>
          <div class="acct-step-t">Informations</div>
          <div class="acct-step-x">Enregistrées le <?= acct_date($actif['date_creation'] ?? null) ?></div>
        </div>
        <div class="acct-step <?= $statut === 'brouillon' ? 'active' : 'done' ?>">
          <div class="line"></div>
          <div class="acct-step-dot"><?= $statut === 'brouillon' ? '2' : acct_icon('check', 13) ?></div>
          <div class="acct-step-t">Dossier &amp; pièces</div>
          <div class="acct-step-x"><?= $statut === 'brouillon' ? 'À compléter — reprendre le dossier' : ($piecesTotal > 0 ? $piecesOk . ' pièce' . ($piecesOk > 1 ? 's' : '') . ' sur ' . $piecesTotal . ' déposée' . ($piecesOk > 1 ? 's' : '') : 'Dossier soumis') ?></div>
        </div>
        <div class="acct-step <?= $estValide ? 'done' : ($statut === 'refuse' ? 'err' : ($statut === 'brouillon' ? 'pending' : 'active')) ?>">
          <div class="line"></div>
          <div class="acct-step-dot"><?= $estValide ? acct_icon('check', 13) : '3' ?></div>
          <div class="acct-step-t">Validation</div>
          <div class="acct-step-x"><?= $estValide ? 'Inscription validée' : ($statut === 'refuse' ? 'Dossier refusé' : 'Étude par l\'établissement') ?></div>
        </div>
        <div class="acct-step <?= $estValide && !empty($actif['numero_attestation']) ? 'done' : 'pending' ?>">
          <div class="acct-step-dot"><?= $estValide && !empty($actif['numero_attestation']) ? acct_icon('check', 13) : '4' ?></div>
          <div class="acct-step-t">Attestation</div>
          <div class="acct-step-x"><?= $estValide && !empty($actif['numero_attestation']) ? e($actif['numero_attestation']) : 'Délivrée après validation' ?></div>
        </div>
      </div>
    <?php else: ?>
      <div class="acct-stepper s3">
        <div class="acct-step done">
          <div class="line"></div><div class="acct-step-dot"><?= acct_icon('check', 13) ?></div>
          <div class="acct-step-t">Préinscription</div>
          <div class="acct-step-x">Soumise le <?= acct_date($actif['date_creation'] ?? null) ?></div>
        </div>
        <div class="acct-step <?= !empty($actif['numero_recepisse']) ? 'done' : 'active' ?>">
          <div class="line"></div>
          <div class="acct-step-dot"><?= !empty($actif['numero_recepisse']) ? acct_icon('check', 13) : '2' ?></div>
          <div class="acct-step-t">Attestation</div>
          <div class="acct-step-x"><?= !empty($actif['numero_recepisse']) ? e($actif['numero_recepisse']) : 'En cours de génération' ?></div>
        </div>
        <div class="acct-step <?= $statut === 'accepte' ? 'done' : 'pending' ?>">
          <div class="acct-step-dot"><?= $statut === 'accepte' ? acct_icon('check', 13) : '3' ?></div>
          <div class="acct-step-t">Suite du parcours</div>
          <div class="acct-step-x"><?= $statut === 'accepte' ? 'Préinscription acceptée' : 'Poursuivre vers l\'inscription en ligne' ?></div>
        </div>
      </div>
    <?php endif; ?>
  </a>
<?php else: ?>
  <div class="acct-empty gls" style="margin-bottom:16px;">
    <div class="acct-empty-ic"><?= acct_icon('compass', 22) ?></div>
    <div class="acct-empty-t">Aucun dossier pour le moment</div>
    <div class="acct-empty-d">Explorez les universités et grandes écoles partenaires, puis déposez votre première préinscription — gratuite et instantanée.</div>
    <a href="<?= url('/orientation') ?>" class="abtn primary"><?= acct_icon('compass', 15) ?> Explorer les établissements</a>
  </div>
<?php endif; ?>

<div class="acct-stats">
  <a href="<?= url('/eleve/orientation/preinscriptions') ?>" class="acct-stat gls">
    <div class="acct-stat-head"><div class="acct-stat-ic"><?= acct_icon('fileText', 17) ?></div>
      <?php if ($nbPreinsOk > 0): ?><span class="badge2 ok">Actives</span><?php endif; ?></div>
    <div class="acct-stat-v"><?= count($preins) ?></div>
    <div class="acct-stat-l">Préinscription<?= count($preins) > 1 ? 's' : '' ?> soumise<?= count($preins) > 1 ? 's' : '' ?></div>
  </a>
  <a href="<?= url('/eleve/orientation/inscriptions') ?>" class="acct-stat gls">
    <div class="acct-stat-head"><div class="acct-stat-ic t"><?= acct_icon('briefcase', 17) ?></div>
      <?php if ($nbInscsEnCours > 0): ?><span class="badge2 info">En cours</span><?php endif; ?></div>
    <div class="acct-stat-v"><?= count($inscs) ?></div>
    <div class="acct-stat-l">Dossier<?= count($inscs) > 1 ? 's' : '' ?> d'inscription</div>
  </a>
  <a href="<?= url('/eleve/orientation/documents') ?>" class="acct-stat gls">
    <div class="acct-stat-head"><div class="acct-stat-ic n"><?= acct_icon('folder', 17) ?></div>
      <?php if ($piecesManquantes > 0): ?><span class="badge2 warn"><?= $piecesManquantes ?> manquante<?= $piecesManquantes > 1 ? 's' : '' ?></span><?php endif; ?></div>
    <div class="acct-stat-v"><?= $piecesTotal > 0 ? $piecesOk . '/' . $piecesTotal : $nbDocs ?></div>
    <div class="acct-stat-l">Document<?= $nbDocs > 1 ? 's' : '' ?> déposé<?= $nbDocs > 1 ? 's' : '' ?></div>
  </a>
  <a href="<?= url('/eleve/orientation/notifications') ?>" class="acct-stat gls">
    <div class="acct-stat-head"><div class="acct-stat-ic g"><?= acct_icon('bell', 17) ?></div>
      <?php if ($nbNotifs > 0): ?><span class="badge2 info">Nouveau</span><?php endif; ?></div>
    <div class="acct-stat-v"><?= $nbNotifs ?></div>
    <div class="acct-stat-l">Notification<?= $nbNotifs > 1 ? 's' : '' ?> non lue<?= $nbNotifs > 1 ? 's' : '' ?></div>
  </a>
</div>

<div class="acct-2col">
  <div class="acct-feed gls">
    <div class="acct-block-title">Activité récente <small>Tous établissements</small></div>
    <?php if (empty($feed)): ?>
      <div class="acct-doc-sub" style="padding:8px 0;">Aucune activité pour le moment — votre première démarche apparaîtra ici.</div>
    <?php else: ?>
      <?php foreach ($feed as $ev): ?>
        <div class="acct-feed-item">
          <div class="acct-feed-ic <?= e($ev['tone']) ?>"><?= acct_icon($ev['icon'], 15) ?></div>
          <div>
            <div class="acct-feed-txt"><?= $ev['txt'] /* HTML construit côté contrôleur, valeurs échappées */ ?></div>
            <div class="acct-feed-time"><?= acct_date($ev['date']) ?> · <?= e($ev['cat']) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="acct-feed gls">
    <div class="acct-block-title">Pièces à finaliser
      <small><a href="<?= url('/eleve/orientation/documents') ?>" style="color:#C7A8FF;">Tout voir →</a></small>
    </div>
    <?php if (empty($pieces)): ?>
      <div class="acct-doc-sub" style="padding:8px 0;">
        <?= $actif ? 'Aucune pièce requise pour ce dossier.' : 'Les pièces de votre dossier d\'inscription apparaîtront ici.' ?>
      </div>
    <?php else: ?>
      <?php /* Une pièce REFUSÉE n'est pas une pièce « manquante » : le candidat
               l'a déposée, l'établissement l'a écartée, et il doit en déposer
               une autre. Les confondre affichait « Manquante » sur un document
               déjà envoyé — le candidat cherchait un dépôt qu'il avait fait. */ ?>
      <?php foreach (array_slice($pieces, 0, 6) as $p): ?>
        <?php $refusee = ($p['etat'] ?? null) === 'refuse'; ?>
        <div class="acct-mini-doc">
          <div class="acct-mini-doc-ic <?= $p['ok'] ? 'ok' : '' ?>"><?= acct_icon($p['ok'] ? 'check' : 'alert', 14) ?></div>
          <div class="acct-mini-doc-t"><?= e($p['libelle']) ?></div>
          <span class="badge2 <?= $p['ok'] ? 'ok' : ($refusee || $p['obligatoire'] ? 'err' : 'draft') ?>">
            <?= $p['ok'] ? 'Déposée' : ($refusee ? 'Refusée' : ($p['obligatoire'] ? 'Manquante' : 'Optionnelle')) ?>
          </span>
        </div>
      <?php endforeach; ?>

      <?php /* Le tableau de bord ne porte pas le formulaire de dépôt (il vit
               dans la fiche du dossier, au niveau de la ligne de chaque pièce).
               Sans ce lien, l'écran signale un problème sans dire où le régler. */ ?>
      <?php
        $aCorriger = array_filter(
            $pieces,
            static fn(array $p): bool => ($p['etat'] ?? null) === 'refuse'
                                      || (!$p['ok'] && !empty($p['obligatoire']))
        );
      ?>
      <?php if ($aCorriger !== [] && $actif): ?>
        <a href="<?= url('/eleve/orientation/dossier/' . hid((int)$actif['id'])) ?>#pieces"
           class="abtn glass sm" style="margin-top:12px;">
          <?= acct_icon('upload', 13) ?> Déposer ou remplacer une pièce
        </a>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
