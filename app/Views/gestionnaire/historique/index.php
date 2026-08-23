<?php
// Variables : $entrees, $categories, $categorie, $total, $page, $pages, $etab
$entrees = $entrees ?? [];

/** Couleur du badge selon la catégorie d'action. */
$catBadge = [
    'session'     => 'g-badge-brouillon',
    'candidature' => 'g-badge-soumis',
    'formation'   => 'g-badge-enligne',
    'actualite'   => 'g-badge-enligne',
    'filiere'     => 'g-badge-guichet',
    'piece'       => 'g-badge-guichet',
    'formulaire'  => 'g-badge-guichet',
    'parametres'  => 'g-badge-bientot',
    'profil'      => 'g-badge-brouillon',
];
// Actions de suppression / refus : badge rouge, quel que soit le module
$estSuppression = static fn (string $action): bool => str_ends_with($action, '.suppression');
?>

<div class="g-page-header">
  <div>
    <h2>Historique</h2>
    <p><?= (int)$total ?> action<?= $total > 1 ? 's' : '' ?> tracée<?= $total > 1 ? 's' : '' ?> — toutes les opérations menées dans cet espace sont journalisées.</p>
  </div>
  <form method="get" action="<?= url('/gestionnaire/historique') ?>">
    <select name="categorie" onchange="this.form.submit()"
            style="padding:8px 12px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:var(--font);font-size:13px;background:var(--card);color:var(--ink);cursor:pointer;">
      <option value="">Toutes les catégories</option>
      <?php foreach ($categories as $cle => $label): ?>
        <option value="<?= e($cle) ?>" <?= $categorie === $cle ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<div class="g-card" style="padding:0;overflow:hidden;">
<?php if (empty($entrees)): ?>
  <div class="g-empty">
    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
    <p>Aucune action journalisée<?= $categorie !== '' ? ' dans cette catégorie' : '' ?> pour le moment.</p>
  </div>
<?php else: ?>
  <table class="g-table">
    <thead>
      <tr>
        <th style="width:150px;">Date</th>
        <th style="width:160px;">Gestionnaire</th>
        <th style="width:180px;">Action</th>
        <th>Détail</th>
        <th style="width:110px;">IP</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($entrees as $ent): ?>
      <tr>
        <td style="font-size:12px;color:var(--ink-2);white-space:nowrap;">
          <?= date('d/m/Y', strtotime($ent['created_at'])) ?>
          <span style="color:var(--ink-3);"><?= date('H:i', strtotime($ent['created_at'])) ?></span>
        </td>
        <td style="font-size:12px;font-weight:600;">
          <?= e($ent['gestionnaire_nom'] !== '' ? $ent['gestionnaire_nom'] : '—') ?>
        </td>
        <td>
          <span class="g-badge <?= $estSuppression($ent['action']) ? 'g-badge-refuse' : ($catBadge[$ent['categorie']] ?? 'g-badge-brouillon') ?>">
            <?= e($ent['libelle']) ?>
          </span>
        </td>
        <td style="font-size:12px;color:var(--ink-2);">
          <?= e($ent['description'] !== '' ? $ent['description'] : '—') ?>
        </td>
        <td style="font-size:11px;color:var(--ink-3);font-variant-numeric:tabular-nums;">
          <?= e($ent['ip'] ?? '—') ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
</div>

<?php if ($pages > 1): ?>
<div style="display:flex;align-items:center;justify-content:center;gap:10px;margin-top:18px;">
  <?php
    $lienPage = static function (int $p) use ($categorie): string {
        $qs = ['page' => $p];
        if ($categorie !== '') { $qs['categorie'] = $categorie; }
        return url('/gestionnaire/historique') . '?' . http_build_query($qs);
    };
  ?>
  <?php if ($page > 1): ?>
    <a href="<?= $lienPage($page - 1) ?>" class="g-btn g-btn-ghost g-btn-sm">← Précédent</a>
  <?php endif; ?>
  <span style="font-size:12px;color:var(--ink-3);">Page <?= (int)$page ?> / <?= (int)$pages ?></span>
  <?php if ($page < $pages): ?>
    <a href="<?= $lienPage($page + 1) ?>" class="g-btn g-btn-ghost g-btn-sm">Suivant →</a>
  <?php endif; ?>
</div>
<?php endif; ?>
