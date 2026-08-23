<?php
// Page dédiée : Commissions Connect'Academia (% scolarité, 3 tranches 40/30/30).
// Variables : $etab, $commissions
$commissions = $commissions ?? [];

$tauxEtab      = (float)($etab['taux_commission_scolarite'] ?? 10);
$tauxEtabLabel = rtrim(rtrim(number_format($tauxEtab, 2, ',', ' '), '0'), ',');

// Totaux : commission due, encaissé (tranches payées), restant, soldées
$totCommission = 0.0; $totEncaisse = 0.0; $nbSoldees = 0;
foreach ($commissions as $c) {
    $totCommission += (float)$c['montant_commission'];
    if (($c['statut'] ?? '') === 'solde') $nbSoldees++;
    foreach (($c['tranches'] ?? []) as $t) {
        if ((int)$t['est_payee'] === 1) $totEncaisse += (float)$t['montant'];
    }
}
$totRestant = max(0, $totCommission - $totEncaisse);
?>

<!-- En-tête de page -->
<div class="g-page-header">
  <div>
    <a href="<?= url('/gestionnaire') ?>" class="g-back-link">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
      Vue d'ensemble
    </a>
    <h2>Commissions Connect'Academia</h2>
    <p>
      Pour chaque élève apporté par la plateforme, <strong><?= e($tauxEtabLabel) ?> %</strong> de la scolarité
      sont recouvrés en présentiel en 3 tranches (40 % / 30 % / 30 %).
      L'état ci-dessous est mis à jour par Connect'Academia à chaque encaissement.
    </p>
  </div>
</div>

<?php if (empty($commissions)): ?>
  <div class="g-card">
    <div class="g-empty">
      <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
      <p>Aucune commission enregistrée pour le moment.</p>
      <p style="font-size:12.5px;margin-top:6px;">Les commissions apparaîtront ici dès qu'un élève apporté par la plateforme sera inscrit.</p>
    </div>
  </div>
<?php else: ?>

  <!-- Récapitulatif chiffré -->
  <div class="g-kpi-row">
    <div class="g-kpi">
      <div class="g-kpi-val"><?= count($commissions) ?><small style="font-family:var(--font);font-size:13px;font-weight:500;color:var(--ink-3)"> · <?= $nbSoldees ?> soldée<?= $nbSoldees > 1 ? 's' : '' ?></small></div>
      <div class="g-kpi-label">Commissions</div>
    </div>
    <div class="g-kpi">
      <div class="g-kpi-val purple"><?= number_format($totCommission, 0, ',', ' ') ?> <small style="font-family:var(--font);font-size:13px;font-weight:500;color:var(--ink-3)">XAF</small></div>
      <div class="g-kpi-label">Total dû</div>
    </div>
    <div class="g-kpi">
      <div class="g-kpi-val"><?= number_format($totEncaisse, 0, ',', ' ') ?> <small style="font-family:var(--font);font-size:13px;font-weight:500;color:var(--ink-3)">XAF</small></div>
      <div class="g-kpi-label">Déjà encaissé</div>
    </div>
    <div class="g-kpi">
      <div class="g-kpi-val coral"><?= number_format($totRestant, 0, ',', ' ') ?> <small style="font-family:var(--font);font-size:13px;font-weight:500;color:var(--ink-3)">XAF</small></div>
      <div class="g-kpi-label">Restant à verser</div>
    </div>
  </div>

  <!-- Détail par élève -->
  <div class="g-card" style="padding:0;overflow:hidden;">
    <div style="overflow-x:auto;">
      <table class="g-table">
        <thead>
          <tr>
            <th>Élève</th>
            <th>Filière</th>
            <th>Scolarité</th>
            <th>Taux</th>
            <th>Commission</th>
            <th>Tranche 1 · 40 %</th>
            <th>Tranche 2 · 30 %</th>
            <th>Tranche 3 · 30 %</th>
            <th>Statut</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($commissions as $c): ?>
          <?php
            $eleve = trim((string)($c['eleve_nom'] ?? ''));
            if ($eleve === '' && (!empty($c['user_prenom']) || !empty($c['user_nom']))) {
                $eleve = trim(($c['user_prenom'] ?? '') . ' ' . ($c['user_nom'] ?? ''));
            }
            $tranches = [];
            foreach (($c['tranches'] ?? []) as $t) {
                $tranches[(int)$t['numero']] = $t;
            }
          ?>
          <tr>
            <td style="font-weight:600;font-size:13px;"><?= e($eleve !== '' ? $eleve : '—') ?></td>
            <td style="font-size:12px;"><?= e($c['filiere_visee'] ?? '—') ?></td>
            <td style="font-size:12px;font-variant-numeric:tabular-nums;"><?= number_format((float)$c['montant_scolarite'], 0, ',', ' ') ?> XAF</td>
            <td style="font-size:12px;font-variant-numeric:tabular-nums;"><?= e(rtrim(rtrim(number_format((float)$c['taux_commission'], 2, ',', ' '), '0'), ',')) ?> %</td>
            <td style="font-size:12px;font-weight:700;font-variant-numeric:tabular-nums;"><?= number_format((float)$c['montant_commission'], 0, ',', ' ') ?> XAF</td>
            <?php for ($n = 1; $n <= 3; $n++): $t = $tranches[$n] ?? null; ?>
            <td>
              <?php if ($t && (int)$t['est_payee'] === 1): ?>
                <span class="g-badge g-badge-accepte" title="<?= $t['date_paiement'] ? 'Encaissée le ' . date('d/m/Y', strtotime($t['date_paiement'])) : '' ?>">
                  ✓ Payée<?= $t['date_paiement'] ? ' · ' . date('d/m/y', strtotime($t['date_paiement'])) : '' ?>
                </span>
              <?php else: ?>
                <span class="g-badge g-badge-brouillon">En attente</span>
              <?php endif; ?>
              <div style="font-size:10.5px;color:var(--ink-3);margin-top:3px;font-variant-numeric:tabular-nums;">
                <?= $t ? number_format((float)$t['montant'], 0, ',', ' ') . ' XAF' : '—' ?>
              </div>
            </td>
            <?php endfor; ?>
            <td>
              <?php if (($c['statut'] ?? '') === 'solde'): ?>
                <span class="g-badge g-badge-accepte">Soldée</span>
              <?php else: ?>
                <span class="g-badge g-badge-soumis">En cours</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php endif; ?>
