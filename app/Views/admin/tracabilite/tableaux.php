<?php
/**
 * Tableaux de bord du super administrateur (spec §11.4).
 *
 * Cinq indicateurs, tous orientés action : où le parcours perd du monde, qui
 * traite lentement, qui attend depuis trop longtemps, et quels chiffres
 * méritent d'être vérifiés.
 */
use Services\Orientation\SituationBourse;

$dt = static fn(?string $v): string => $v ? date('d/m/Y', strtotime($v)) : '—';
$maxEntonnoir = max(1, (int)($entonnoir[0]['valeur'] ?? 1));
?>

<div class="tr-head">
  <div>
    <h1>Tableaux de bord</h1>
    <p>Conversion, situations de bourse, délais de traitement et signaux à vérifier.</p>
  </div>
  <form method="get" action="<?= url('/admin/tableaux-de-bord') ?>" class="tr-filtres tr-filtres-inline">
    <select name="etablissement_id" onchange="this.form.submit()">
      <option value="">Toute la plateforme</option>
      <?php foreach ($etablissements as $etab): ?>
        <option value="<?= (int)$etab['id'] ?>" <?= (int)$etabId === (int)$etab['id'] ? 'selected' : '' ?>><?= e($etab['nom']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<!-- Entonnoir : le taux est relatif à l'étape précédente, pas au sommet —
     c'est ce qui montre OÙ le parcours perd du monde. -->
<section class="tr-bloc">
  <h2>Entonnoir de conversion</h2>
  <div class="tr-entonnoir">
    <?php foreach ($entonnoir as $i => $etape): ?>
      <div class="tr-etape">
        <div class="tr-etape-barre" style="width: <?= max(6, round($etape['valeur'] / $maxEntonnoir * 100)) ?>%"></div>
        <div class="tr-etape-txt">
          <span class="tr-etape-l"><?= e($etape['libelle']) ?></span>
          <span class="tr-etape-v"><?= number_format($etape['valeur'], 0, ',', ' ') ?></span>
          <?php if ($i > 0): ?>
            <span class="tr-etape-t <?= $etape['taux'] < 40 ? 'faible' : '' ?>"><?= number_format($etape['taux'], 1, ',', ' ') ?> %</span>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<div class="tr-duo">
  <!-- Répartition des bourses -->
  <section class="tr-bloc">
    <h2>Situations de bourse</h2>
    <?php if ($bourses['total'] === 0): ?>
      <p class="tr-vide">Aucun dossier déposé.</p>
    <?php else: ?>
      <?php foreach ($bourses['repartition'] as $cle => $v): ?>
        <div class="tr-barre">
          <div class="tr-barre-l">
            <?= e($cle === 'non_renseigne' ? 'Non renseignée' : SituationBourse::libelleCourt($cle)) ?>
            <b><?= (int)$v['nombre'] ?></b>
          </div>
          <div class="tr-barre-p"><span style="width: <?= (float)$v['part'] ?>%"></span></div>
          <div class="tr-barre-v"><?= number_format($v['part'], 1, ',', ' ') ?> %</div>
        </div>
      <?php endforeach; ?>
      <p class="tr-note"><?= (int)$bourses['total'] ?> dossiers déposés. La commission est due quelle que soit la situation.</p>
    <?php endif; ?>
  </section>

  <!-- Délai moyen de traitement, du dépôt à la validation à 50 % -->
  <section class="tr-bloc">
    <h2>Délai de traitement (dépôt → 50 %)</h2>
    <?php if ($delais === []): ?>
      <p class="tr-vide">Aucun dossier validé à 50 % pour l'instant.</p>
    <?php else: ?>
    <table class="tr-table tr-table-compacte">
      <thead><tr><th>Établissement</th><th class="num">Dossiers</th><th class="num">Délai moyen</th></tr></thead>
      <tbody>
      <?php foreach ($delais as $d): ?>
        <tr>
          <td><?= e($d['nom']) ?></td>
          <td class="num tr-sec"><?= (int)$d['dossiers'] ?></td>
          <td class="num <?= $d['delai_moyen'] > 7 ? 'tr-alerte' : '' ?>">
            <?= number_format($d['delai_moyen'], 1, ',', ' ') ?> j
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </section>
</div>

<!-- Alerte sur les taux de boursiers anormaux -->
<section class="tr-bloc">
  <h2>Taux de boursiers déclarés</h2>
  <p class="tr-note">
    Moyenne de la plateforme : <strong><?= number_format($alerteBoursiers['moyenne'], 1, ',', ' ') ?> %</strong>.
    Un écart n'est pas une accusation : c'est un signal à vérifier. Les établissements
    de moins de 10 dossiers ne sont jamais signalés — un taux sur trois dossiers ne veut rien dire.
  </p>
  <?php if ($alerteBoursiers['etablissements'] === []): ?>
    <p class="tr-vide">Aucune situation de bourse déclarée pour le moment.</p>
  <?php else: ?>
  <table class="tr-table tr-table-compacte">
    <thead><tr><th>Établissement</th><th class="num">Dossiers</th><th class="num">Boursiers</th><th class="num">Taux</th><th class="num">Écart</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($alerteBoursiers['etablissements'] as $e2): ?>
      <tr class="<?= $e2['anormal'] ? 'tr-ligne-alerte' : '' ?>">
        <td><?= e($e2['nom']) ?></td>
        <td class="num tr-sec"><?= (int)$e2['dossiers'] ?></td>
        <td class="num tr-sec"><?= (int)$e2['boursiers'] ?></td>
        <td class="num"><?= number_format($e2['taux'], 1, ',', ' ') ?> %</td>
        <td class="num <?= $e2['anormal'] ? 'tr-alerte' : 'tr-sec' ?>">
          <?= $e2['ecart'] > 0 ? '+' : '' ?><?= number_format($e2['ecart'], 1, ',', ' ') ?> %
        </td>
        <td><?= $e2['anormal'] ? '<span class="tr-badge tr-danger">À vérifier</span>' : '' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</section>

<!-- Délai d'analyse dépassé : l'établissement n'a rendu aucune décision.
     Le compteur repart à chaque retour en « soumis » (un dossier renvoyé après
     une demande de pièce redonne un délai plein), et le candidat est encore
     sans réponse à cet instant précis. -->
<section class="tr-bloc">
  <h2>Délai d'analyse dépassé <span class="tr-seuil"><?= (int)$delaiAnalyse ?> jours</span> (<?= count($retards) ?>)</h2>
  <p class="tr-note">
    Ces dossiers n'ont reçu ni validation, ni demande de pièce, ni refus.
    Chacun est journalisé une fois par quinzaine et signalé par e-mail aux super administrateurs.
  </p>
  <?php if ($retards === []): ?>
    <p class="tr-vide">Tous les dossiers déposés ont été analysés dans le délai.</p>
  <?php else: ?>
  <div class="tr-scroll">
    <table class="tr-table tr-table-compacte">
      <thead><tr><th>Référence</th><th>Candidat</th><th>Établissement</th><th>En attente depuis</th><th class="num">Retard</th></tr></thead>
      <tbody>
      <?php foreach ($retards as $r): ?>
        <tr class="tr-ligne-alerte">
          <td class="tr-ref"><?= e($r['reference'] ?? '—') ?></td>
          <td><?= e($r['nom_complet'] ?? '—') ?></td>
          <td class="tr-sec"><?= e($r['etab_nom']) ?></td>
          <td class="tr-sec"><?= $dt($r['depuis_le']) ?></td>
          <td class="num tr-alerte"><?= (int)$r['jours_attente'] ?> j</td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>

<!-- Dossiers validés à 50 % en souffrance -->
<section class="tr-bloc">
  <h2>Validés à 50 % depuis plus de 15 jours (<?= count($attente) ?>)</h2>
  <?php if ($attente === []): ?>
    <p class="tr-vide">Aucun dossier en souffrance.</p>
  <?php else: ?>
  <div class="tr-scroll">
    <table class="tr-table tr-table-compacte">
      <thead><tr><th>Référence</th><th>Candidat</th><th>Établissement</th><th>Validé le</th><th class="num">Attente</th><th>Expire le</th></tr></thead>
      <tbody>
      <?php foreach ($attente as $a): ?>
        <tr>
          <td class="tr-ref"><?= e($a['reference'] ?? '—') ?></td>
          <td><?= e($a['nom_complet'] ?? '—') ?></td>
          <td class="tr-sec"><?= e($a['etab_nom']) ?></td>
          <td class="tr-sec"><?= $dt($a['valide_50_le']) ?></td>
          <td class="num tr-alerte"><?= (int)$a['jours_attente'] ?> j</td>
          <td class="tr-sec"><?= $dt($a['expire_le']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>

<!-- Inscriptions multiples (spec §17) -->
<?php if ($multiples !== []): ?>
<section class="tr-bloc">
  <h2>Inscrits à 100 % dans plusieurs établissements (<?= count($multiples) ?>)</h2>
  <p class="tr-note">Indicateur de traçabilité, pas une fraude en soi — mais il vaut mieux le voir.</p>
  <table class="tr-table tr-table-compacte">
    <thead><tr><th>Candidat</th><th>Année</th><th class="num">Établissements</th><th>Lesquels</th></tr></thead>
    <tbody>
    <?php foreach ($multiples as $m): ?>
      <tr class="tr-ligne-alerte">
        <td>
          <a class="tr-lien" href="<?= url('/admin/utilisateurs/' . (int)$m['eleve_id']) ?>"><?= e($m['nom_complet'] ?? '#' . (int)$m['eleve_id']) ?></a>
        </td>
        <td class="tr-sec"><?= e($m['annee_academique']) ?></td>
        <td class="num"><?= (int)$m['nb_etablissements'] ?></td>
        <td class="tr-sec"><?= e($m['etablissements']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php endif; ?>

<link rel="stylesheet" href="<?= asset('orientation/tracabilite-admin.css') ?>">
