<?php
// Variables: $dossiers (array), $filters (array), $etab
$dossiers = $dossiers ?? [];
$filters  = $filters  ?? [];

$statutLabels = [
    'brouillon' => 'Brouillon',
    'soumis'    => 'Soumis',
    'paye'      => 'Payé',
    'accepte'   => 'Accepté',
    'inscrit'   => 'Inscrit',
    'refuse'    => 'Refusé',
];
?>

<div class="g-page-header">
  <div>
    <h2>Dossiers reçus</h2>
    <p><?= count($dossiers) ?> résultat<?= count($dossiers) > 1 ? 's' : '' ?></p>
  </div>
  <div style="display:flex;gap:10px;flex-wrap:wrap;">
    <a href="<?= url('/gestionnaire/candidatures/creer?type=preinscription') ?>" class="g-btn g-btn-outline">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 4v16m8-8H4"/></svg>
      Préinscription guichet
    </a>
    <a href="<?= url('/gestionnaire/candidatures/creer?type=inscription') ?>" class="g-btn g-btn-primary">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6m3-3h-6"/></svg>
      Inscription guichet
    </a>
  </div>
</div>

<!-- Filtres -->
<div class="g-card" style="padding:16px 20px;margin-bottom:20px;">
  <form method="get" action="" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
    <input type="text" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Rechercher nom, email…"
           style="flex:1;min-width:200px;padding:8px 12px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:var(--font);font-size:13px;">
    <select name="statut" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:var(--font);font-size:13px;">
      <option value="">Tous les statuts</option>
      <?php foreach ($statutLabels as $val => $lbl): ?>
        <option value="<?= $val ?>" <?= ($filters['statut'] ?? '') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
      <?php endforeach; ?>
    </select>
    <select name="origine" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:var(--font);font-size:13px;">
      <option value="">Toutes les origines</option>
      <option value="en_ligne"      <?= ($filters['origine'] ?? '') === 'en_ligne'      ? 'selected' : '' ?>>En ligne</option>
      <option value="etablissement" <?= ($filters['origine'] ?? '') === 'etablissement' ? 'selected' : '' ?>>Guichet</option>
    </select>
    <button type="submit" class="g-btn g-btn-primary g-btn-sm">Filtrer</button>
    <?php if (!empty($filters['q']) || !empty($filters['statut']) || !empty($filters['origine'])): ?>
      <a href="<?= url('/gestionnaire/candidatures') ?>" class="g-btn g-btn-ghost g-btn-sm">Réinitialiser</a>
    <?php endif; ?>
  </form>
</div>

<div class="g-card" style="padding:0;overflow:hidden;">
<?php if (empty($dossiers)): ?>
  <div class="g-empty">
    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
    <p>Aucun dossier trouvé.</p>
  </div>
<?php else: ?>
  <table class="g-table">
    <thead>
      <tr>
        <th>Candidat</th>
        <th>Filière</th>
        <th>Attestation</th>
        <th>Origine</th>
        <th>Statut</th>
        <th>Date</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($dossiers as $d): ?>
      <tr>
        <td>
          <div style="font-weight:600;font-size:13px;"><?= e($d['nom_complet'] ?? '—') ?></div>
          <div style="font-size:11px;color:var(--ink-3);"><?= e($d['email'] ?? '') ?></div>
        </td>
        <td style="font-size:12px;color:var(--ink-2);"><?= e($d['filiere_visee'] ?? '—') ?></td>
        <td style="font-size:12px;color:var(--ink-3);">
          <?php if (!empty($d['numero_recepisse'])): ?>
            <a href="<?= url('/gestionnaire/candidatures/' . $d['id'] . '/recepisse') ?>" target="_blank" rel="noopener" title="Télécharger l'attestation de préinscription" style="display:inline-flex;align-items:center;gap:5px;color:var(--ap);font-weight:600;">
              <?= e($d['numero_recepisse']) ?>
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </a>
          <?php else: ?>
            —
          <?php endif; ?>
        </td>
        <td>
          <?php if (($d['origine'] ?? '') === 'etablissement'): ?>
            <span class="g-badge g-badge-guichet">Guichet</span>
          <?php else: ?>
            <span class="g-badge g-badge-enligne">En ligne</span>
          <?php endif; ?>
        </td>
        <td>
          <span class="g-badge g-badge-<?= e($d['statut'] ?? 'brouillon') ?>"><?= e($statutLabels[$d['statut'] ?? 'brouillon'] ?? $d['statut']) ?></span>
        </td>
        <td style="font-size:11px;color:var(--ink-3);white-space:nowrap;"><?= date('d/m/Y', strtotime($d['date_creation'])) ?></td>
        <td>
          <a href="<?= url('/gestionnaire/candidatures/' . $d['id']) ?>" class="g-btn g-btn-ghost g-btn-sm">Voir</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
</div>
