<?php
/**
 * Journal d'activité — CONSULTATION SEULE (spec §11.3).
 *
 * Aucun bouton d'édition ni de suppression n'existe ici, et aucune route ne
 * les fournit : un journal modifiable ne prouve rien. Une purge éventuelle se
 * fait par un script d'administration séparé, jamais depuis l'application.
 */
$dt = static fn(?string $v): string => $v ? date('d/m/Y à H:i:s', strtotime($v)) : '—';
?>

<div class="tr-head">
  <div>
    <h1>Journal d'activité</h1>
    <p><?= (int)$total ?> entrée<?= $total > 1 ? 's' : '' ?>. Consultation seule — le journal est en ajout seul.</p>
  </div>
</div>

<form method="get" action="<?= url('/admin/journal') ?>" class="tr-filtres">
  <select name="type">
    <option value="">Tous les événements</option>
    <?php foreach ($types as $cle => $libelle): ?>
      <option value="<?= e($cle) ?>" <?= ($filtres['type'] ?? '') === $cle ? 'selected' : '' ?>><?= e($libelle) ?></option>
    <?php endforeach; ?>
  </select>

  <select name="etablissement_id">
    <option value="">Tous les établissements</option>
    <?php foreach ($etablissements as $etab): ?>
      <option value="<?= (int)$etab['id'] ?>" <?= (int)($filtres['etablissement_id'] ?? 0) === (int)$etab['id'] ? 'selected' : '' ?>><?= e($etab['nom']) ?></option>
    <?php endforeach; ?>
  </select>

  <input type="date" name="du" value="<?= e($filtres['du'] ?? '') ?>" aria-label="Du">
  <input type="date" name="au" value="<?= e($filtres['au'] ?? '') ?>" aria-label="Au">

  <button type="submit" class="tr-btn tr-btn-p">Filtrer</button>
  <?php if ($filtres !== []): ?><a href="<?= url('/admin/journal') ?>" class="tr-btn">Réinitialiser</a><?php endif; ?>
</form>

<?php if ($entrees === []): ?>
  <p class="tr-vide">Aucune entrée ne correspond à ces critères.</p>
<?php else: ?>
<div class="tr-scroll">
  <table class="tr-table tr-table-journal">
    <thead>
      <tr><th>Horodatage</th><th>Événement</th><th>Utilisateur</th><th>Auteur</th><th>Établissement</th><th>IP</th><th>Contexte</th></tr>
    </thead>
    <tbody>
    <?php foreach ($entrees as $ev): ?>
      <tr>
        <td class="tr-sec tr-nowrap"><?= $dt($ev['cree_le']) ?></td>
        <td><span class="tr-evt"><?= e($ev['libelle']) ?></span></td>
        <td class="tr-sec">
          <?php if (!empty($ev['utilisateur_id'])): ?>
            <a class="tr-lien" href="<?= url('/admin/utilisateurs/' . (int)$ev['utilisateur_id']) ?>">
              <?= e(trim(($ev['sujet_prenom'] ?? '') . ' ' . ($ev['sujet_nom'] ?? '')) ?: '#' . (int)$ev['utilisateur_id']) ?>
            </a>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td class="tr-sec"><?= e($ev['acteur_role'] ?? '—') ?><?= !empty($ev['acteur_id']) ? ' #' . (int)$ev['acteur_id'] : '' ?></td>
        <td class="tr-sec"><?= e($ev['etab_nom'] ?? '—') ?></td>
        <td class="tr-sec tr-mono"><?= e($ev['adresse_ip'] ?? '—') ?></td>
        <td class="tr-ctx">
          <?php foreach ($ev['donnees'] as $cle => $valeur): ?>
            <?php if (is_scalar($valeur) || $valeur === null): ?>
              <span><b><?= e((string)$cle) ?></b> <?= e((string)$valeur) ?></span>
            <?php endif; ?>
          <?php endforeach; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if ($pages > 1): ?>
<nav class="tr-pagination">
  <?php for ($p = max(1, $page - 5); $p <= min($pages, $page + 5); $p++): $q = $filtres; $q['page'] = $p; ?>
    <a href="<?= e(url('/admin/journal') . '?' . http_build_query($q)) ?>" class="<?= $p === (int)$page ? 'actif' : '' ?>"><?= $p ?></a>
  <?php endfor; ?>
</nav>
<?php endif; ?>
<?php endif; ?>

<link rel="stylesheet" href="<?= asset('orientation/tracabilite-admin.css') ?>">
