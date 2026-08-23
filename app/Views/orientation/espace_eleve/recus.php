<?php
// Reçus — variables : $recus (paiements avec numero_recu + recu_url)
require_once __DIR__ . '/_helpers.php';
$recus = $recus ?? [];
?>

<div class="acct-shead">
  <div>
    <span class="acct-shead-kick"><?= acct_icon('receipt', 12) ?> justificatifs de paiement</span>
    <h1 class="acct-shead-t">Mes reçus</h1>
    <p class="acct-shead-d">Vos reçus de paiement, générés automatiquement après chaque transaction réussie, avec lien de téléchargement temporaire.</p>
  </div>
</div>

<?php if (empty($recus)): ?>
  <div class="acct-empty gls">
    <div class="acct-empty-ic"><?= acct_icon('receipt', 22) ?></div>
    <div class="acct-empty-t">Pas encore de reçu</div>
    <div class="acct-empty-d">Vos reçus apparaîtront ici dès qu'un paiement sera confirmé — chaque reçu reste téléchargeable via un lien sécurisé temporaire.</div>
  </div>
<?php else: ?>
  <?php foreach ($recus as $r): ?>
    <div class="acct-card gls">
      <div class="acct-card-top" style="margin-bottom:0;">
        <div>
          <div class="acct-card-title">
            <div class="acct-doc-ic ok" style="width:38px;height:38px;"><?= acct_icon('receipt', 17) ?></div>
            Reçu <?= e($r['numero_recu']) ?>
          </div>
          <div class="acct-card-sub" style="margin-left:48px;">
            <?= e($r['etab_nom'] ?? '—') ?> · <?= acct_montant($r['montant'] ?? 0) ?> · <?= e(acct_mode_paiement($r['mode'] ?? null)) ?>
            · émis le <?= acct_date($r['recu_date'] ?? $r['date_confirmation'] ?? null) ?>
          </div>
        </div>
        <div class="acct-doc-actions">
          <?php if (!empty($r['recu_url'])): ?>
            <a href="<?= e($r['recu_url']) ?>" target="_blank" rel="noopener" class="abtn primary sm"><?= acct_icon('download', 13) ?> Télécharger</a>
          <?php else: ?>
            <span class="abtn glass sm disabled"><?= acct_icon('clock', 13) ?> Indisponible</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="acct-footnote"><?= acct_icon('clock', 12) ?> Les liens de téléchargement sont temporaires — rechargez la page pour en générer de nouveaux.</div>
<?php endif; ?>
