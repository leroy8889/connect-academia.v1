<?php
/**
 * Suivi des commissions — écran super administrateur (spec §10.3 / §10.4).
 *
 * La plateforme ne collecte plus l'argent de la scolarité : cet écran est la
 * seule vue d'ensemble de ce qui lui est dû, et le seul endroit où l'on
 * constate ce qui est rentré. Il doit donc être exact avant d'être joli —
 * d'où les totaux en tête, calculés en base sur les mêmes filtres que la liste.
 *
 * Deux gestes vivent ici, et NULLE PART ailleurs :
 *   · encaisser une tranche (60 % / 40 % ; échéanciers hérités : 40/30/30) —
 *     l'établissement lit le résultat sur son tableau de bord, il ne coche
 *     rien ;
 *   · constater un abandon d'année — l'établissement le signale, le super
 *     administrateur le statue. Laisser l'établissement fermer ses propres
 *     tranches reviendrait à lui laisser annuler sa dette.
 */
use Services\Orientation\CommissionDossier;

$commissions = $commissions ?? [];
$totaux      = $totaux      ?? [];
$tranches    = $tranches    ?? ['encaisse' => 0.0, 'restant' => 0.0, 'abandonne' => 0.0];
$filtres     = $filtres     ?? [];
$peutAbandon = !empty($peutAbandon);
$page        = (int)($page ?? 1);

$xaf = static fn($v): string => number_format((float)$v, 0, ',', ' ');

/** Champs cachés qui ramènent à la liste filtrée après un POST. */
$retourFiltres = static function (array $filtres, int $page): string {
    $html = '';
    foreach (['etablissement_id', 'statut', 'periode', 'du', 'au'] as $cle) {
        if (($filtres[$cle] ?? '') !== '') {
            $html .= '<input type="hidden" name="retour_' . $cle . '" value="'
                   . htmlspecialchars((string)$filtres[$cle], ENT_QUOTES, 'UTF-8') . '">';
        }
    }
    if ($page > 1) {
        $html .= '<input type="hidden" name="retour_page" value="' . $page . '">';
    }

    return $html;
};
$retour = $retourFiltres($filtres, $page);
?>

<div class="ac-head">
  <div>
    <h1>Suivi des commissions</h1>
    <p>Commissions générées automatiquement à chaque validation d'inscription à 100 %, recouvrées en <?= e(CommissionDossier::libelleRepartition()) ?>. Les échéanciers déjà entamés avant la révision du recouvrement conservent leurs trois tranches.</p>
  </div>
</div>

<?php if (!empty($flash)): ?><div class="ac-flash ac-flash-ok"><?= e($flash) ?></div><?php endif; ?>
<?php if (!empty($flashError)): ?><div class="ac-flash ac-flash-err"><?= e($flashError) ?></div><?php endif; ?>

<!-- Totaux par statut, sur les filtres courants -->
<div class="cm-totaux">
  <?php foreach ($statuts as $cle => $libelle): ?>
    <div class="cm-total cm-total-<?= e($cle) ?>">
      <div class="cm-total-l"><?= e($libelle) ?></div>
      <div class="cm-total-v"><?= $xaf($totaux[$cle]['montant'] ?? 0) ?> <span>XAF</span></div>
      <div class="cm-total-n"><?= (int)($totaux[$cle]['nombre'] ?? 0) ?> dossier<?= (int)($totaux[$cle]['nombre'] ?? 0) > 1 ? 's' : '' ?></div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Trésorerie réelle : ce qui est rentré, ce qui reste à recouvrer, ce qui ne
     rentrera jamais. Calculé en base — un total limité à la page affichée
     serait faux, et faux sur de l'argent. -->
<div class="cm-caisse">
  <div class="cm-caisse-item">
    <span class="cm-caisse-l">Encaissé</span>
    <span class="cm-caisse-v cm-caisse-ok"><?= $xaf($tranches['encaisse']) ?> XAF</span>
  </div>
  <div class="cm-caisse-item">
    <span class="cm-caisse-l">Reste à recouvrer</span>
    <span class="cm-caisse-v"><?= $xaf($tranches['restant']) ?> XAF</span>
  </div>
  <div class="cm-caisse-item">
    <span class="cm-caisse-l">Clos par abandon</span>
    <span class="cm-caisse-v cm-caisse-mort"><?= $xaf($tranches['abandonne']) ?> XAF</span>
  </div>
</div>

<form method="get" action="<?= url('/admin/commissions') ?>" class="cm-filtres">
  <select name="etablissement_id">
    <option value="">Tous les établissements</option>
    <?php foreach ($etablissements as $etab): ?>
      <option value="<?= (int)$etab['id'] ?>" <?= (int)($filtres['etablissement_id'] ?? 0) === (int)$etab['id'] ? 'selected' : '' ?>>
        <?= e($etab['nom']) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <select name="statut">
    <option value="">Tous les statuts</option>
    <?php foreach ($statuts as $cle => $libelle): ?>
      <option value="<?= e($cle) ?>" <?= ($filtres['statut'] ?? '') === $cle ? 'selected' : '' ?>><?= e($libelle) ?></option>
    <?php endforeach; ?>
  </select>

  <select name="periode">
    <option value="">Toutes les périodes</option>
    <?php foreach ($periodes as $p): ?>
      <option value="<?= e($p) ?>" <?= ($filtres['periode'] ?? '') === $p ? 'selected' : '' ?>><?= e($p) ?></option>
    <?php endforeach; ?>
  </select>

  <input type="date" name="du" value="<?= e($filtres['du'] ?? '') ?>" aria-label="Du">
  <input type="date" name="au" value="<?= e($filtres['au'] ?? '') ?>" aria-label="Au">

  <button type="submit" class="ac-btn ac-btn-primary">Filtrer</button>
  <?php if ($filtres !== []): ?>
    <a href="<?= url('/admin/commissions') ?>" class="ac-btn">Réinitialiser</a>
  <?php endif; ?>
</form>

<?php if ($commissions === []): ?>
  <div class="cm-vide">Aucune commission ne correspond à ces critères.</div>
<?php else: ?>
<div class="cm-table-wrap">
  <table class="cm-table">
    <thead>
      <tr>
        <th>Référence</th>
        <th>Candidat</th>
        <th>Établissement</th>
        <th class="num">Base</th>
        <th class="num">Taux</th>
        <th class="num">Commission</th>
        <th>Recouvrement</th>
        <th>Statut</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($commissions as $c):
      $statut = (string)$c['statut'];
      $cle    = match ($statut) { 'en_cours' => 'a_facturer', 'solde' => 'reglee', default => $statut };
      $abandon = !empty($c['abandon_le']);
      $lignes  = [];
      foreach (($c['tranches'] ?? []) as $t) { $lignes[(int)$t['numero']] = $t; }
      // Numéros RÉELLEMENT présents, ordonnés. Le recouvrement est passé de
      // trois tranches à deux : une liste [1, 2, 3] écrite en dur masquerait la
      // troisième tranche d'un échéancier hérité — donc une créance — et
      // proposerait une troisième colonne vide sur tous les échéanciers neufs.
      $numeros = array_keys($lignes);
      sort($numeros);

      // Une tranche ne s'encaisse qu'après la précédente : le bouton n'apparaît
      // que sur la PROCHAINE tranche due. Les proposer toutes d'un coup ferait
      // cliquer sur la mauvaise.
      $prochaine = null;
      foreach ($numeros as $n) {
          $t = $lignes[$n];
          if ((int)$t['est_payee'] === 0 && (int)$t['est_abandonnee'] === 0) { $prochaine = $n; break; }
      }
      // Dernière encaissée : la seule que l'on puisse défaire sans trouer l'ordre.
      $derniere = null;
      foreach (array_reverse($numeros) as $n) {
          if ((int)$lignes[$n]['est_payee'] === 1) { $derniere = $n; break; }
      }
    ?>
      <tr<?= $abandon ? ' class="cm-tr-abandon"' : '' ?>>
        <td class="cm-ref">
          <?= e($c['reference'] ?? '—') ?>
          <?php if (!empty($c['niveau_entree'])): ?>
            <div class="cm-sec"><?= e($c['niveau_entree']) ?></div>
          <?php endif; ?>
          <?php if (!empty($c['revision_requise'])): ?>
            <?php
              // La situation de bourse du candidat a changé après l'émission,
              // mais la commission n'était plus révisable automatiquement
              // (tranche encaissée, ou commission close). Le recalcul aurait pu
              // produire un total inférieur au déjà-perçu : la plateforme
              // s'arrête et vous rend la main.
            ?>
            <div class="cm-revision">
              <strong>À réexaminer</strong>
              <div><?= e((string)($c['revision_motif'] ?? 'Situation de bourse modifiée.')) ?></div>
              <form method="post" action="<?= url('/admin/commissions/' . (int)$c['id'] . '/revision/clore') ?>"
                    onsubmit="return confirm('Marquer cette commission comme réexaminée ?\n\nLe signalement disparaît ; les montants ne sont pas modifiés.')">
                <input type="hidden" name="_csrf_token" value="<?= e(\Core\Session::getCsrfToken()) ?>">
                <button type="submit">J’ai réexaminé</button>
              </form>
            </div>
          <?php endif; ?>
        </td>
        <td>
          <?= e($c['nom_complet'] ?? '—') ?>
          <div class="cm-sec"><?= e(\Services\Orientation\SituationBourse::libelleCourt($c['statut_bourse'] ?? null)) ?></div>
        </td>
        <td class="cm-sec"><?= e($c['etab_nom'] ?? '—') ?></td>
        <td class="num">
          <?= $xaf($c['montant_scolarite']) ?>
          <?php
            // `montant_scolarite` porte désormais LA BASE RETENUE, qui n'est
            // plus forcément la scolarité : sans cette mention, un montant de
            // 30 000 F passerait pour une scolarité dérisoire alors que ce sont
            // des frais d'inscription.
            $baseNat = (string)($c['base_commission'] ?? \Services\Orientation\TarifCycle::BASE_SCOLARITE);
          ?>
          <div class="cm-sec"><?= e(\Services\Orientation\TarifCycle::libelleBase($baseNat)) ?></div>
        </td>
        <td class="num"><?= number_format((float)$c['taux_commission'], 2, ',', ' ') ?> %</td>
        <td class="num cm-montant"><?= $xaf($c['montant_commission']) ?></td>

        <td class="cm-tranches">
          <?php if ($lignes === []): ?>
            <span class="cm-sec">Échéancier absent — commission antérieure à la refonte.</span>
          <?php else: ?>
            <?php foreach ($numeros as $n): $t = $lignes[$n]; ?>
              <?php
                $etatT = (int)$t['est_payee'] === 1
                    ? 'payee'
                    : ((int)$t['est_abandonnee'] === 1 ? 'close' : 'due');
              ?>
              <div class="cm-tranche cm-tranche-<?= $etatT ?>">
                <span class="cm-tranche-n">T<?= $n ?> · <?= e(rtrim(rtrim(number_format((float)$t['pourcentage'], 2, ',', ' '), '0'), ',')) ?> %</span>
                <span class="cm-tranche-m"><?= $xaf($t['montant']) ?></span>
                <span class="cm-tranche-e">
                  <?php if ($etatT === 'payee'): ?>
                    Encaissée<?= !empty($t['date_paiement']) ? ' le ' . date('d/m/y', strtotime((string)$t['date_paiement'])) : '' ?>
                    <?= !empty($t['reference_reglement']) ? ' · ' . e($t['reference_reglement']) : '' ?>
                  <?php elseif ($etatT === 'close'): ?>
                    Close (abandon)
                  <?php else: ?>
                    En attente
                  <?php endif; ?>
                </span>

                <?php if ($etatT === 'due' && $n === $prochaine && $cle !== 'annulee'): ?>
                  <form method="post" action="<?= url('/admin/commissions/tranches/' . (int)$t['id']) ?>"
                        onsubmit="var r = prompt('Référence du règlement reçu (facultatif) :'); this.reference_reglement.value = r || ''; return true;">
                    <input type="hidden" name="_csrf_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="encaisser">
                    <input type="hidden" name="reference_reglement" value="">
                    <?= $retour ?>
                    <button type="submit" class="ac-btn ac-btn-sm ac-btn-primary">Encaisser</button>
                  </form>
                <?php elseif ($etatT === 'payee' && $n === $derniere): ?>
                  <form method="post" action="<?= url('/admin/commissions/tranches/' . (int)$t['id']) ?>"
                        onsubmit="return confirm('Remettre la tranche <?= $n ?> en attente ? Elle ne comptera plus comme encaissée.')">
                    <input type="hidden" name="_csrf_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="annuler">
                    <?= $retour ?>
                    <button type="submit" class="ac-btn ac-btn-sm">Annuler</button>
                  </form>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </td>

        <td>
          <span class="cm-badge cm-badge-<?= e($cle) ?>"><?= e(CommissionDossier::libelle($statut)) ?></span>
          <div class="cm-sec"><?= e($c['periode_facturation'] ?? '—') ?></div>
          <?php if ($abandon && !empty($c['abandon_motif'])): ?>
            <div class="cm-sec cm-abandon-motif" title="<?= e($c['abandon_motif']) ?>"><?= e($c['abandon_motif']) ?></div>
          <?php endif; ?>
        </td>

        <td class="cm-actions">
          <?php if ($cle === 'a_facturer'): ?>
          <form method="post" action="<?= url('/admin/commissions/' . (int)$c['id'] . '/statut') ?>">
            <input type="hidden" name="_csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="statut" value="facturee">
            <?= $retour ?>
            <button type="submit" class="ac-btn ac-btn-sm">Facturer</button>
          </form>
          <?php endif; ?>

          <?php // ── Abandon d'année : super administrateur uniquement ──
                // Rien à proposer sur une commission annulée : le dossier lui-même
                // n'est plus inscrit. ?>
          <?php if ($peutAbandon && !$abandon && $cle !== 'annulee'): ?>
            <form method="post" action="<?= url('/admin/commissions/' . (int)$c['id'] . '/abandon') ?>"
                  onsubmit="var m = prompt('Motif de l’abandon d’année (l’établissement le lira) :'); if (!m || m.trim().length < 5) { return false; } this.motif.value = m; return true;">
              <input type="hidden" name="_csrf_token" value="<?= e($csrf) ?>">
              <input type="hidden" name="motif" value="">
              <?= $retour ?>
              <button type="submit" class="ac-btn ac-btn-sm ac-btn-danger">Abandon d'année</button>
            </form>
          <?php elseif ($peutAbandon && $abandon): ?>
            <form method="post" action="<?= url('/admin/commissions/' . (int)$c['id'] . '/abandon/lever') ?>"
                  onsubmit="return confirm('Lever l’abandon ? Les tranches non encaissées redeviennent dues.')">
              <input type="hidden" name="_csrf_token" value="<?= e($csrf) ?>">
              <?= $retour ?>
              <button type="submit" class="ac-btn ac-btn-sm">Lever l'abandon</button>
            </form>
          <?php endif; ?>

          <?php if ($cle === 'reglee'): ?>
            <span class="cm-sec">
              <?= !empty($c['regle_le']) ? 'Soldée le ' . date('d/m/Y', strtotime((string)$c['regle_le'])) : '' ?>
            </span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<p class="cm-note">
  La commission porte sur le tarif <strong>non boursier</strong> du cycle suivi (Licence ou Master),
  fixé sur la fiche de l'établissement : elle est donc due à l'identique, que l'étudiant soit
  boursier ou non. Le taux est gelé au moment de la validation à 100 % — modifier le taux d'un
  établissement n'affecte que les dossiers validés ensuite.
  Un abandon d'année ferme les tranches non encaissées : celles déjà réglées restent acquises.
</p>

<link rel="stylesheet" href="<?= asset('orientation/commissions-admin.css') ?>">
