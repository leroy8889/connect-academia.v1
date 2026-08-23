<?php
// Paiements — variables : $paiements
require_once __DIR__ . '/_helpers.php';
$paiements = $paiements ?? [];

$statutPaiement = function (?string $s): array {
    return match ($s) {
        'reussi'     => ['ok',   'Réussi'],
        'en_attente' => ['warn', 'En attente'],
        'initie'     => ['warn', 'Initié'],
        'echoue'     => ['err',  'Échoué'],
        default      => ['draft', $s ?: '—'],
    };
};
?>

<div class="acct-shead">
  <div>
    <span class="acct-shead-kick"><?= acct_icon('card', 12) ?> frais d'inscription</span>
    <h1 class="acct-shead-t">Mes paiements</h1>
    <p class="acct-shead-d">Historique de tous vos paiements d'inscription, tous établissements confondus.</p>
  </div>
</div>

<?php if (empty($paiements)): ?>
  <div class="acct-empty gls">
    <div class="acct-empty-ic"><?= acct_icon('clock', 22) ?></div>
    <div class="acct-empty-t">Aucun paiement pour l'instant</div>
    <div class="acct-empty-d">Vos paiements d'inscription apparaîtront ici, qu'ils soient réglés en ligne ou directement au guichet de l'établissement. Chaque paiement réussi est immédiatement suivi d'un reçu.</div>
  </div>
<?php else: ?>
  <div class="acct-card gls">
    <div class="acct-pay-head acct-pay-row" style="border-bottom:1px solid var(--glass-line-soft);">
      <span>Établissement</span><span>Montant</span><span>Mode</span><span>Statut</span><span></span>
    </div>
    <?php foreach ($paiements as $p): ?>
      <?php [$bClass, $bLabel] = $statutPaiement($p['statut'] ?? null); ?>
      <div class="acct-pay-row">
        <div>
          <div class="acct-pay-main"><?= e($p['etab_nom'] ?? '—') ?></div>
          <div class="acct-pay-sub">Inscription · <?= acct_date($p['date_paiement'] ?? null) ?></div>
        </div>
        <div class="acct-pay-main"><?= acct_montant($p['montant'] ?? 0) ?></div>
        <div class="acct-pay-sub"><?= e(acct_mode_paiement($p['mode'] ?? null)) ?></div>
        <span class="badge2 <?= $bClass ?>"><span class="d"></span><?= e($bLabel) ?></span>
        <?php if (!empty($p['numero_recu'])): ?>
          <a href="<?= url('/eleve/orientation/recus') ?>" class="abtn ghost sm"><?= acct_icon('receipt', 13) ?> Reçu</a>
        <?php else: ?>
          <a href="<?= url('/eleve/orientation/dossier/' . hid((int)$p['dossier_id'])) ?>" class="abtn ghost sm">Dossier</a>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="acct-footnote"><?= acct_icon('shield', 12) ?> Chaque paiement réussi génère automatiquement un reçu officiel, disponible dans l'onglet Reçus.</div>
<?php endif; ?>
