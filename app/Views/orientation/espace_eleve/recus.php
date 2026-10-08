<?php
/**
 * Onglet « Mes reçus ».
 *
 * Deux origines, une seule liste — le candidat a payé, il veut son reçu, peu
 * lui importe par quel canal la plateforme en a eu connaissance :
 *
 *   type `guichet`  règlement remis à l'établissement, enregistré à la
 *                   validation à 100 %. Le PDF est reconstruit à la demande :
 *                   le lien ne périme jamais.
 *   type `en_ligne` paiement encaissé par la plateforme (parcours MoneyFusion).
 *                   Le PDF est archivé, servi par une URL signée temporaire.
 *
 * Variables : $recus — voir EspaceEleveOrientationController::recus().
 */
require_once __DIR__ . '/_helpers.php';
$recus = $recus ?? [];

$aUnLienTemporaire = false;
foreach ($recus as $r) {
    if (($r['type'] ?? '') === 'en_ligne') { $aUnLienTemporaire = true; break; }
}
?>

<div class="acct-shead">
  <div>
    <span class="acct-shead-kick"><?= acct_icon('receipt', 12) ?> justificatifs de paiement</span>
    <h1 class="acct-shead-t">Mes reçus</h1>
    <p class="acct-shead-d">Tous vos justificatifs de règlement, qu'ils aient été payés en ligne ou remis au guichet de l'établissement.</p>
  </div>
</div>

<?php if (empty($recus)): ?>
  <div class="acct-empty gls">
    <div class="acct-empty-ic"><?= acct_icon('receipt', 22) ?></div>
    <div class="acct-empty-t">Pas encore de reçu</div>
    <div class="acct-empty-d">Vos reçus apparaîtront ici dès qu'un règlement sera enregistré : paiement en ligne confirmé, ou frais réglés au guichet lors de la validation de votre inscription.</div>
  </div>
<?php else: ?>
  <?php foreach ($recus as $r):
      $guichet = ($r['type'] ?? '') === 'guichet';
      $detail  = $r['detail'] ?? null;
  ?>
    <div class="acct-card gls">
      <div class="acct-card-top" style="margin-bottom:0;">
        <div style="min-width:0;">
          <div class="acct-card-title">
            <div class="acct-doc-ic ok" style="width:38px;height:38px;"><?= acct_icon('receipt', 17) ?></div>
            Reçu <?= e((string)$r['numero']) ?>
          </div>
          <div class="acct-card-sub" style="margin-left:48px;">
            <?= e($r['etab_nom'] ?? '—') ?>
            · <?= acct_montant($r['montant'] ?? 0) ?>
            <?php if (!empty($r['mode'])): ?> · <?= e(acct_mode_paiement($r['mode'])) ?><?php endif; ?>
            · <?= $guichet ? 'réglé au guichet le ' : 'payé en ligne le ' ?><?= acct_date($r['date'] ?? null) ?>
          </div>

          <?php // Le détail dit ce que recouvre le montant. Sans lui, un
                // candidat qui a réglé frais + acompte le même jour ne peut pas
                // rapprocher la somme affichée de ce qu'il a sorti de sa poche. ?>
          <?php if ($guichet && $detail !== null): ?>
            <div class="acct-card-sub" style="margin-left:48px;margin-top:4px;">
              <?php if (($detail['frais'] ?? null) !== null && $detail['frais'] > 0): ?>
                Frais d'inscription <?= acct_montant($detail['frais']) ?>
                <?php if (($detail['scolarite'] ?? 0) > 0): ?>
                  · versement sur la scolarité <?= acct_montant($detail['scolarite']) ?>
                <?php endif; ?>
              <?php elseif (($detail['scolarite'] ?? 0) > 0): ?>
                Versement sur la scolarité
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="acct-doc-actions">
          <?php if (!empty($r['url'])): ?>
            <a href="<?= e((string)$r['url']) ?>" target="_blank" rel="noopener" class="abtn primary sm"><?= acct_icon('download', 13) ?> Télécharger</a>
          <?php else: ?>
            <span class="abtn glass sm disabled"><?= acct_icon('clock', 13) ?> Indisponible</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if ($aUnLienTemporaire): ?>
    <div class="acct-footnote"><?= acct_icon('clock', 12) ?> Les reçus de paiement en ligne s'ouvrent via un lien temporaire — rechargez la page pour en générer un nouveau. Les reçus réglés au guichet sont, eux, toujours disponibles.</div>
  <?php else: ?>
    <div class="acct-footnote"><?= acct_icon('clock', 12) ?> Vos reçus restent disponibles en permanence : le document est reconstruit à chaque téléchargement.</div>
  <?php endif; ?>
<?php endif; ?>
