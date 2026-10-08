<?php
// Documents — variables : $groupes = [['dossier' => …, 'pieces' => [['libelle','obligatoire','ok','doc'], …]], …]
require_once __DIR__ . '/_helpers.php';
$groupes = $groupes ?? [];
?>

<div class="acct-shead">
  <div>
    <span class="acct-shead-kick"><?= acct_icon('folder', 12) ?> pièces justificatives</span>
    <h1 class="acct-shead-t">Mes documents</h1>
    <p class="acct-shead-d">Toutes les pièces déposées pour vos dossiers, stockées de façon sécurisée. Les pièces manquantes se déposent depuis l'assistant d'inscription de l'établissement.</p>
  </div>
</div>

<?php if (empty($groupes)): ?>
  <div class="acct-empty gls">
    <div class="acct-empty-ic"><?= acct_icon('folder', 22) ?></div>
    <div class="acct-empty-t">Aucun document pour le moment</div>
    <div class="acct-empty-d">Les pièces justificatives de vos dossiers d'inscription (carte d'identité, bulletins, diplômes…) apparaîtront ici dès votre premier dépôt.</div>
    <a href="<?= url('/orientation') ?>" class="abtn glass"><?= acct_icon('compass', 15) ?> Explorer les établissements</a>
  </div>
<?php else: ?>
  <?php foreach ($groupes as $g): ?>
    <?php
      $d      = $g['dossier'];
      $pieces = $g['pieces'];
      $nbOk   = count(array_filter($pieces, fn($p) => $p['ok']));
      $nbMiss = count($pieces) - $nbOk;
      $refDossier = $d['numero_attestation'] ?? $d['numero_recepisse'] ?? ('DOS-' . str_pad((string)(int)$d['id'], 6, '0', STR_PAD_LEFT));
    ?>
    <div class="acct-card gls">
      <div class="acct-card-title" style="margin-bottom:4px;">
        <?= e($d['etab_nom'] ?? '—') ?>
        <span style="font-weight:500;color:var(--ink-faint);font-size:12px;">· <?= e($refDossier) ?></span>
      </div>
      <div class="acct-card-sub" style="margin-bottom:8px;">
        <?= $nbOk ?> pièce<?= $nbOk > 1 ? 's' : '' ?> déposée<?= $nbOk > 1 ? 's' : '' ?><?= $nbMiss > 0 ? ' · ' . $nbMiss . ' manquante' . ($nbMiss > 1 ? 's' : '') : '' ?>
      </div>
      <?php foreach ($pieces as $p): ?>
        <?php
          $doc = $p['doc'] ?? null;
          // Une pièce refusée était rangée avec les manquantes : le candidat
          // lisait « Manquante » sur un document qu'il avait bel et bien
          // envoyé, sans le motif du refus ni le moindre bouton pour agir.
          $refusee = ($p['etat'] ?? null) === 'refuse';
          $motif   = trim((string)($p['motif_refus'] ?? ''));
        ?>
        <div class="acct-doc-row<?= $refusee ? ' acct-doc-row-err' : '' ?>">
          <div class="acct-doc-ic <?= $refusee ? 'err' : ($p['ok'] ? 'ok' : 'miss') ?>"><?= acct_icon($refusee ? 'alert' : ($p['ok'] ? 'idcard' : 'upload'), 17) ?></div>
          <div style="min-width:0">
            <div class="acct-doc-name"><?= e($p['libelle']) ?></div>
            <div class="acct-doc-sub">
              <?php if ($refusee): ?>
                Refusée par l'établissement<?= $motif !== '' ? ' — ' . e($motif) : '' ?>
              <?php elseif ($p['ok'] && $doc): ?>
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
              <a href="<?= e($doc['url_doc']) ?>" target="_blank" rel="noopener" class="abtn ghost sm"><?= acct_icon('download', 13) ?> Télécharger</a>
            <?php endif; ?>
            <?php /* Le dépôt vit dans la fiche du dossier : c'est le seul écran
                     qui porte un formulaire par pièce. On y renvoie plutôt que
                     de laisser un état sans issue. */ ?>
            <?php if (!$p['ok'] && ($d['statut'] ?? '') === 'brouillon' && !empty($d['etab_slug'])): ?>
              <a href="<?= url('/orientation/' . e($d['etab_slug']) . '/inscription') ?>" class="abtn glass sm"><?= acct_icon('upload', 13) ?> Déposer</a>
            <?php elseif (!$p['ok'] && !empty($p['deposable'])): ?>
              <a href="<?= url('/eleve/orientation/dossier/' . hid((int)$d['id'])) ?>#pieces" class="abtn glass sm">
                <?= acct_icon('upload', 13) ?> <?= $refusee ? 'Remplacer' : 'Déposer' ?>
              </a>
            <?php elseif (!$p['ok']): ?>
              <span class="badge2 <?= $refusee ? 'err' : ($p['obligatoire'] ? 'warn' : 'draft') ?>"><?= $refusee ? 'Refusée' : ($p['obligatoire'] ? 'À déposer' : 'Optionnelle') ?></span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

  <div class="acct-footnote"><?= acct_icon('shield', 12) ?> Chaque dépôt est vérifié (type de fichier réel, taille maximale) avant stockage — les liens de consultation sont temporaires.</div>
<?php endif; ?>
