<?php
/**
 * Vue transversale des dossiers, tous établissements (spec §13.4).
 * C'est d'ici que le super administrateur révoque un jeton compromis.
 */
use Models\Orientation\DossierStatut;
use Services\Orientation\SituationBourse;
?>

<div class="tr-head">
  <div>
    <h1>Tous les dossiers</h1>
    <p><?= (int)$total ?> dossier<?= $total > 1 ? 's' : '' ?>, préinscriptions et inscriptions confondues, tous établissements.</p>
  </div>
  <a href="<?= url('/admin/tableaux-de-bord') ?>" class="tr-lien">Tableaux de bord →</a>
</div>

<?php if (!empty($flash)): ?><div class="tr-flash ok"><?= e($flash) ?></div><?php endif; ?>
<?php if (!empty($flashError)): ?><div class="tr-flash err"><?= e($flashError) ?></div><?php endif; ?>

<form method="get" action="<?= url('/admin/dossiers') ?>" class="tr-filtres">
  <input type="text" name="q" value="<?= e($filtres['q'] ?? '') ?>" placeholder="Nom, référence ou e-mail…">

  <select name="etablissement_id">
    <option value="">Tous les établissements</option>
    <?php foreach ($etablissements as $etab): ?>
      <option value="<?= (int)$etab['id'] ?>" <?= (int)($filtres['etablissement_id'] ?? 0) === (int)$etab['id'] ? 'selected' : '' ?>><?= e($etab['nom']) ?></option>
    <?php endforeach; ?>
  </select>

  <select name="type">
    <option value="">Préinscriptions et inscriptions</option>
    <option value="preinscription" <?= ($filtres['type'] ?? '') === 'preinscription' ? 'selected' : '' ?>>Préinscriptions seules</option>
    <option value="inscription" <?= ($filtres['type'] ?? '') === 'inscription' ? 'selected' : '' ?>>Inscriptions seules</option>
  </select>

  <select name="statut">
    <option value="">Tous les statuts</option>
    <?php foreach ($statuts as $st): ?>
      <option value="<?= e($st) ?>" <?= ($filtres['statut'] ?? '') === $st ? 'selected' : '' ?>><?= e(DossierStatut::libelle($st)) ?></option>
    <?php endforeach; ?>
  </select>

  <select name="statut_bourse">
    <option value="">Toutes situations de bourse</option>
    <?php foreach (SituationBourse::valeurs() as $b): ?>
      <option value="<?= e($b) ?>" <?= ($filtres['statut_bourse'] ?? '') === $b ? 'selected' : '' ?>><?= e(SituationBourse::libelleCourt($b)) ?></option>
    <?php endforeach; ?>
    <option value="non_renseigne" <?= ($filtres['statut_bourse'] ?? '') === 'non_renseigne' ? 'selected' : '' ?>>Non renseignée</option>
  </select>

  <select name="annee">
    <option value="">Toutes les années</option>
    <?php foreach ($annees as $a): ?>
      <option value="<?= e($a) ?>" <?= ($filtres['annee'] ?? '') === $a ? 'selected' : '' ?>><?= e($a) ?></option>
    <?php endforeach; ?>
  </select>

  <button type="submit" class="tr-btn tr-btn-p">Filtrer</button>
  <?php if ($filtres !== []): ?><a href="<?= url('/admin/dossiers') ?>" class="tr-btn">Réinitialiser</a><?php endif; ?>
</form>

<?php if ($dossiers === []): ?>
  <p class="tr-vide">Aucun dossier ne correspond à ces critères.</p>
<?php else: ?>
<div class="tr-scroll">
  <table class="tr-table">
    <thead>
      <tr>
        <th>Référence</th><th>Type</th><th>Candidat</th><th>Établissement</th><th>Bourse</th>
        <th>Statut</th><th>Ancienneté</th><th class="num">Commission</th><th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($dossiers as $d): ?>
      <tr>
        <td class="tr-ref"><?= e($d['reference'] ?? '—') ?></td>
        <td>
          <span class="tr-badge tr-<?= $d['est_inscription'] ? 'info' : 'attente' ?>"><?= e($d['type_libelle']) ?></span>
        </td>
        <td>
          <?= e($d['nom_complet'] ?? '—') ?>
          <?php if (!empty($d['eleve_id'])): ?>
            <a class="tr-lien tr-mini" href="<?= url('/admin/utilisateurs/' . (int)$d['eleve_id']) ?>">fiche</a>
          <?php endif; ?>
        </td>
        <td class="tr-sec"><?= e($d['etab_nom']) ?></td>
        <td class="tr-sec"><?= e(SituationBourse::libelleCourt($d['statut_bourse'] ?? null)) ?></td>
        <td>
          <span class="tr-badge tr-<?= e(DossierStatut::ton($d['statut_normalise'])) ?>">
            <?= e($d['statut_libelle']) ?> · <?= (int)$d['progression'] ?> %
          </span>
        </td>
        <td class="tr-sec"><?= (int)$d['anciennete'] ?> j</td>
        <td class="num tr-sec">
          <?= $d['montant_commission'] !== null ? number_format((float)$d['montant_commission'], 0, ',', ' ') : '—' ?>
        </td>
        <td>
          <!-- Révocation : invalide le récépissé déjà imprimé et en régénère un.
               Action irréversible pour le document en circulation, d'où la
               confirmation et le motif demandé. -->
          <form method="post" action="<?= url('/admin/dossiers/' . (int)$d['id'] . '/revoquer-jeton') ?>"
                onsubmit="var m = prompt('Motif de la révocation ? Le récépissé déjà remis au candidat deviendra invalide.'); if (m === null) { return false; } this.motif.value = m; return true;">
            <input type="hidden" name="_csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="motif" value="">
            <button type="submit" class="tr-btn tr-btn-danger tr-mini">Révoquer le jeton</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if ($pages > 1): ?>
<nav class="tr-pagination">
  <?php for ($p = 1; $p <= $pages; $p++): $q = $filtres; $q['page'] = $p; ?>
    <a href="<?= e(url('/admin/dossiers') . '?' . http_build_query($q)) ?>" class="<?= $p === (int)$page ? 'actif' : '' ?>"><?= $p ?></a>
  <?php endfor; ?>
</nav>
<?php endif; ?>
<?php endif; ?>

<link rel="stylesheet" href="<?= asset('orientation/tracabilite-admin.css') ?>">
