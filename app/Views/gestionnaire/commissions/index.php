<?php
/**
 * Commissions Connect'Academia — tableau de bord établissement.
 *
 * Écran en LECTURE SEULE, et c'est délibéré : l'argent de la commission est
 * encaissé par Connect'Academia, qui seule peut le constater. L'établissement
 * voit ici, en temps réel, ce qu'il a déjà réglé et ce qu'il lui reste à régler
 * — jamais un bouton qui modifierait sa propre dette.
 *
 * Variables : $etab, $commissions (tranches attachées).
 */
use Services\Orientation\CommissionDossier;

$commissions = $commissions ?? [];

$tauxEtab      = (float)($etab['taux_commission_scolarite'] ?? 10);
$tauxEtabLabel = rtrim(rtrim(number_format($tauxEtab, 2, ',', ' '), '0'), ',');

$xaf = static fn($v): string => number_format((float)$v, 0, ',', ' ');

// Totaux. Une commission ANNULÉE (inscription défaite) sort du décompte : elle
// n'a jamais été due. Une commission en ABANDON y reste, parce que ce qui a
// déjà été encaissé est acquis — seul le solde s'éteint.
$totCommission = 0.0; $totEncaisse = 0.0; $totClos = 0.0; $nbSoldees = 0; $nbAbandons = 0;
foreach ($commissions as $c) {
    $statut = (string)($c['statut'] ?? '');
    if ($statut === CommissionDossier::ANNULEE) {
        continue;
    }
    $totCommission += (float)$c['montant_commission'];
    if (in_array($statut, [CommissionDossier::REGLEE, 'solde'], true)) { $nbSoldees++; }
    if ($statut === CommissionDossier::ABANDONNEE) { $nbAbandons++; }

    foreach (($c['tranches'] ?? []) as $t) {
        if ((int)$t['est_payee'] === 1)            { $totEncaisse += (float)$t['montant']; }
        elseif ((int)($t['est_abandonnee'] ?? 0) === 1) { $totClos += (float)$t['montant']; }
    }
}
$totRestant = max(0, $totCommission - $totEncaisse - $totClos);

// Nombre de colonnes de tranches à dresser.
//
// Le recouvrement est passé de trois tranches à deux (60 / 40), mais les
// échéanciers déjà entamés ou clos gardent les leurs : une commission de 2024
// et une de ce mois-ci coexistent dans ce tableau. On dimensionne donc sur ce
// que les données portent RÉELLEMENT, jamais sur la répartition du jour — sinon
// une ligne héritée perdrait silencieusement sa troisième tranche, et
// l'établissement croirait sa dette plus légère qu'elle ne l'est.
$nbColonnesTranches = count(CommissionDossier::REPARTITION_TRANCHES);
foreach ($commissions as $c) {
    foreach (($c['tranches'] ?? []) as $t) {
        $nbColonnesTranches = max($nbColonnesTranches, (int)$t['numero']);
    }
}
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
      Pour chaque élève apporté par la plateforme, <strong><?= e($tauxEtabLabel) ?> %</strong> de la
      scolarité annuelle du cycle sont recouvrés en <?= e(CommissionDossier::libelleRepartition()) ?>.
      Chaque tranche est marquée encaissée par Connect'Academia à réception du règlement :
      ce tableau est à jour à l'instant où vous le consultez.
    </p>
  </div>
</div>

<?php if (empty($commissions)): ?>
  <div class="g-card">
    <div class="g-empty">
      <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
      <p>Aucune commission enregistrée pour le moment.</p>
      <p style="font-size:12.5px;margin-top:6px;">Les commissions apparaissent ici dès qu'un élève apporté par la plateforme est inscrit à 100 %.</p>
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
      <div class="g-kpi-val purple"><?= $xaf($totCommission) ?> <small style="font-family:var(--font);font-size:13px;font-weight:500;color:var(--ink-3)">XAF</small></div>
      <div class="g-kpi-label">Total dû</div>
    </div>
    <div class="g-kpi">
      <div class="g-kpi-val"><?= $xaf($totEncaisse) ?> <small style="font-family:var(--font);font-size:13px;font-weight:500;color:var(--ink-3)">XAF</small></div>
      <div class="g-kpi-label">Déjà réglé</div>
    </div>
    <div class="g-kpi">
      <div class="g-kpi-val coral"><?= $xaf($totRestant) ?> <small style="font-family:var(--font);font-size:13px;font-weight:500;color:var(--ink-3)">XAF</small></div>
      <div class="g-kpi-label">Restant à verser</div>
    </div>
  </div>

  <?php if ($totClos > 0): ?>
    <!-- Abandons : l'établissement doit voir CE QU'IL NE DOIT PLUS. C'est
         l'information qu'il attend après avoir signalé un départ. -->
    <div class="g-card" style="margin-bottom:16px;padding:13px 16px;">
      <p style="font-size:12.5px;line-height:1.55;margin:0;">
        <strong><?= $xaf($totClos) ?> XAF</strong> de commission
        <?= $nbAbandons > 1 ? 'sur ' . $nbAbandons . ' dossiers ont été clos' : 'ont été clos' ?>
        à la suite d'un <strong>abandon d'année</strong> constaté par Connect'Academia.
        Ces tranches ne vous seront pas réclamées.
      </p>
    </div>
  <?php endif; ?>

  <!-- Détail par élève -->
  <div class="g-card" style="padding:0;overflow:hidden;">
    <div style="overflow-x:auto;">
      <table class="g-table">
        <thead>
          <tr>
            <th>Élève</th>
            <th>Filière</th>
            <?php // « Base » et non « Scolarité » : depuis que l'assiette des
                  // boursiers est négociable, cette colonne peut porter des
                  // frais d'inscription. L'intituler « Scolarité » ferait
                  // passer 30 000 F de frais pour une scolarité dérisoire. ?>
            <th>Base de calcul</th>
            <th>Taux</th>
            <th>Commission</th>
            <?php // Le pourcentage n'est PAS mis en en-tête : deux commissions
                  // de la colonne peuvent suivre des répartitions différentes
                  // (héritée 40/30/30, ou courante 60/40). Il est affiché
                  // cellule par cellule, où il est exact. ?>
            <?php for ($n = 1; $n <= $nbColonnesTranches; $n++): ?>
            <th>Tranche <?= $n ?></th>
            <?php endfor; ?>
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
            $statut  = (string)($c['statut'] ?? '');
            $abandon = $statut === CommissionDossier::ABANDONNEE || !empty($c['abandon_le']);
            $tranches = [];
            foreach (($c['tranches'] ?? []) as $t) {
                $tranches[(int)$t['numero']] = $t;
            }
            $ton = match ($statut) {
                CommissionDossier::REGLEE, 'solde'      => 'accepte',
                CommissionDossier::ABANDONNEE           => 'brouillon',
                CommissionDossier::ANNULEE              => 'brouillon',
                CommissionDossier::PARTIELLEMENT_REGLEE => 'soumis',
                default                                 => 'soumis',
            };
          ?>
          <tr<?= $abandon ? ' style="opacity:.72"' : '' ?>>
            <td style="font-weight:600;font-size:13px;">
              <?= e($eleve !== '' ? $eleve : '—') ?>
              <?php if (!empty($c['reference'])): ?>
                <div style="font-size:10.5px;color:var(--ink-3);font-weight:500;"><?= e($c['reference']) ?></div>
              <?php endif; ?>
            </td>
            <td style="font-size:12px;">
              <?= e($c['filiere_visee'] ?? '—') ?>
              <?php if (!empty($c['niveau_entree'])): ?>
                <div style="font-size:10.5px;color:var(--ink-3);"><?= e($c['niveau_entree']) ?></div>
              <?php endif; ?>
            </td>
            <td style="font-size:12px;font-variant-numeric:tabular-nums;"><?= $xaf($c['montant_scolarite']) ?> XAF</td>
            <td style="font-size:12px;font-variant-numeric:tabular-nums;"><?= e(rtrim(rtrim(number_format((float)$c['taux_commission'], 2, ',', ' '), '0'), ',')) ?> %</td>
            <td style="font-size:12px;font-weight:700;font-variant-numeric:tabular-nums;"><?= $xaf($c['montant_commission']) ?> XAF</td>

            <?php for ($n = 1; $n <= $nbColonnesTranches; $n++): $t = $tranches[$n] ?? null; ?>
            <td>
              <?php if ($t && (int)$t['est_payee'] === 1): ?>
                <span class="g-badge g-badge-accepte" title="<?= !empty($t['date_paiement']) ? 'Encaissée le ' . date('d/m/Y', strtotime((string)$t['date_paiement'])) : '' ?>">
                  ✓ Réglée<?= !empty($t['date_paiement']) ? ' · ' . date('d/m/y', strtotime((string)$t['date_paiement'])) : '' ?>
                </span>
              <?php elseif ($t && (int)($t['est_abandonnee'] ?? 0) === 1): ?>
                <span class="g-badge g-badge-brouillon" title="Close à la suite d'un abandon d'année">Close</span>
              <?php elseif ($t): ?>
                <span class="g-badge g-badge-brouillon">En attente</span>
              <?php else: ?>
                <span class="g-badge g-badge-brouillon">—</span>
              <?php endif; ?>
              <div style="font-size:10.5px;color:var(--ink-3);margin-top:3px;font-variant-numeric:tabular-nums;">
                <?= $t ? $xaf($t['montant']) . ' XAF · ' . rtrim(rtrim(number_format((float)$t['pourcentage'], 2, ',', ' '), '0'), ',') . ' %' : '—' ?>
              </div>
            </td>
            <?php endfor; ?>

            <td>
              <span class="g-badge g-badge-<?= e($ton) ?>"><?= e(CommissionDossier::libelle($statut)) ?></span>
              <?php if ($abandon && !empty($c['abandon_motif'])): ?>
                <div style="font-size:10.5px;color:var(--ink-3);margin-top:4px;max-width:22ch;">
                  <?= e($c['abandon_motif']) ?>
                </div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <p style="margin-top:14px;font-size:12px;line-height:1.6;color:var(--ink-3);max-width:78ch;">
    La commission est due pour chaque élève inscrit, quelle que soit sa situation de bourse : seule
    l'assiette en dépend — la scolarité annuelle dans le cas général, l'assiette convenue avec
    Connect'Academia pour un élève boursier. Le taux et l'assiette sont figés au moment de la
    validation à 100 % : une commission déjà émise ne bouge plus. Un élève qui abandonne l'année en
    cours doit être signalé à Connect'Academia : les tranches restantes sont alors closes et ne vous
    seront pas réclamées.
  </p>

<?php endif; ?>
