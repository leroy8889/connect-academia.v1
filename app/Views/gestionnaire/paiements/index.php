<?php
// Variables : $etab, $paiements, $stats, $compte
$paiements = $paiements ?? [];
$stats     = $stats ?? [];
$compte    = $compte ?? null;

$flashError = \Core\Session::getFlash('error');

$statutPaiement = [
    'initie'     => ['Initié',     'g-badge-brouillon'],
    'en_attente' => ['En attente', 'g-badge-bientot'],
    'reussi'     => ['Payé',       'g-badge-paye'],
    'echoue'     => ['Échoué',     'g-badge-refuse'],
];
$statutReversement = [
    'initie'    => ['Initié',            'g-badge-brouillon'],
    'en_cours'  => ['En cours',          'g-badge-soumis'],
    'reussi'    => ['Reversé',           'g-badge-paye'],
    'echoue'    => ['Échoué',            'g-badge-refuse'],
    'a_rejouer' => ['En file d\'attente', 'g-badge-bientot'],
];
$fmt = static fn (int|float|null $v): string => number_format((int)$v, 0, ',', ' ');
?>

<div class="g-page-header">
  <div>
    <h2>Paiements d'inscription</h2>
    <p>Chaque paiement en ligne est encaissé via MoneyFusion puis reversé automatiquement sur le compte mobile money de votre établissement (moins la commission plateforme).</p>
  </div>
</div>

<?php if ($flashError): ?>
<div class="g-card" style="margin-bottom:18px;padding:14px 20px;border-left:4px solid #b91c1c;color:#b91c1c;font-size:13px;">
  <?= e($flashError) ?>
</div>
<?php endif; ?>

<?php if (!$compte || ($compte['statut'] ?? '') !== 'actif'): ?>
<div class="g-card" style="margin-bottom:18px;padding:14px 20px;border-left:4px solid #B45309;font-size:13px;color:var(--ink-2);">
  <strong style="color:#B45309;">Compte mobile money non actif.</strong>
  Les paiements des candidats sont bien encaissés, mais les reversements automatiques vers votre établissement sont suspendus
  jusqu'à l'activation de votre compte par l'administrateur Connect'Academia.
</div>
<?php endif; ?>

<div class="g-kpi-row">
  <div class="g-kpi">
    <div class="g-kpi-val"><?= $fmt($stats['nb_reussis'] ?? 0) ?></div>
    <div class="g-kpi-label">Paiements confirmés</div>
  </div>
  <div class="g-kpi">
    <div class="g-kpi-val purple"><?= $fmt($stats['montant_total'] ?? 0) ?> <small style="font-size:13px;font-weight:500;color:var(--ink-3)">XAF</small></div>
    <div class="g-kpi-label">Total encaissé</div>
  </div>
  <div class="g-kpi">
    <div class="g-kpi-val coral"><?= $fmt($stats['montant_reverse'] ?? 0) ?> <small style="font-size:13px;font-weight:500;color:var(--ink-3)">XAF</small></div>
    <div class="g-kpi-label">Reversé à l'établissement</div>
  </div>
  <div class="g-kpi">
    <div class="g-kpi-val"><?= $fmt($stats['commission_totale'] ?? 0) ?> <small style="font-size:13px;font-weight:500;color:var(--ink-3)">XAF</small></div>
    <div class="g-kpi-label">Commission plateforme</div>
  </div>
</div>

<div class="g-card" style="padding:0;overflow:hidden;">
<?php if (empty($paiements)): ?>
  <div class="g-empty">
    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
    <p>Aucun paiement d'inscription pour le moment.</p>
  </div>
<?php else: ?>
  <div style="overflow-x:auto;">
  <table class="g-table">
    <thead>
      <tr>
        <th style="width:130px;">Date</th>
        <th>Candidat</th>
        <th style="width:120px;">Montant</th>
        <th style="width:110px;">Statut</th>
        <th style="width:150px;">Reversement</th>
        <th style="width:120px;">Net reversé</th>
        <th style="width:110px;">Reçu</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($paiements as $p): ?>
      <?php
        [$psLbl, $psCls] = $statutPaiement[$p['statut']] ?? [$p['statut'], 'g-badge-brouillon'];
        $rvStatut = $p['reversement_statut'] ?? null;
        [$rvLbl, $rvCls] = $rvStatut !== null
            ? ($statutReversement[$rvStatut] ?? [$rvStatut, 'g-badge-brouillon'])
            : ['—', ''];
      ?>
      <tr>
        <td style="font-size:12px;color:var(--ink-2);white-space:nowrap;">
          <?= date('d/m/Y', strtotime($p['date_paiement'])) ?>
          <span style="color:var(--ink-3);"><?= date('H:i', strtotime($p['date_paiement'])) ?></span>
        </td>
        <td>
          <div style="font-size:13px;font-weight:600;"><?= e($p['nom_complet'] ?? '—') ?></div>
          <div style="font-size:11.5px;color:var(--ink-3);"><?= e($p['email'] ?? '') ?></div>
        </td>
        <td style="font-size:13px;font-weight:700;font-variant-numeric:tabular-nums;white-space:nowrap;">
          <?= $fmt($p['montant']) ?> XAF
        </td>
        <td><span class="g-badge <?= $psCls ?>"><?= e($psLbl) ?></span></td>
        <td>
          <?php if ($rvStatut !== null): ?>
            <span class="g-badge <?= $rvCls ?>"><?= e($rvLbl) ?></span>
          <?php else: ?>
            <span style="font-size:12px;color:var(--ink-3);">—</span>
          <?php endif; ?>
        </td>
        <td style="font-size:12.5px;font-weight:600;font-variant-numeric:tabular-nums;white-space:nowrap;">
          <?= $rvStatut !== null ? $fmt($p['montant_reverse']) . ' XAF' : '—' ?>
        </td>
        <td>
          <?php if (!empty($p['recu_cle'])): ?>
            <a href="<?= url('/gestionnaire/paiements/' . (int)$p['id'] . '/recu') ?>" target="_blank" rel="noopener"
               class="g-btn g-btn-ghost g-btn-sm" style="white-space:nowrap;">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              <?= e($p['numero_recu'] ?? 'PDF') ?>
            </a>
          <?php else: ?>
            <span style="font-size:12px;color:var(--ink-3);">—</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
<?php endif; ?>
</div>

<?php if ($compte && ($compte['statut'] ?? '') === 'actif'): ?>
<p style="margin-top:14px;font-size:12px;color:var(--ink-3);">
  Reversements envoyés au <?= e($compte['numero_mobile_money'] ?? '—') ?>
  (<?= e($compte['withdraw_mode'] ?? '—') ?>)
  — commission plateforme : <?= e((string)($compte['taux_commission'] ?? $_ENV['COMMISSION_PLATEFORME_PERCENT'] ?? '5')) ?> %.
</p>
<?php endif; ?>
