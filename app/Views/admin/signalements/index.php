<?php
// Variables: $pending, $reviewed, $rejected
$pending  = $pending ?? [];
$reviewed = $reviewed ?? [];
$rejected = $rejected ?? [];

$tagClass = [
    'harcelement'   => 'tag-harassment',
    'spam'          => 'tag-spam',
    'inapproprie'   => 'tag-inappropriate',
    'autre'         => 'tag-other',
];
$tagLabel = [
    'harcelement'  => 'Harcèlement',
    'spam'         => 'Spam',
    'inapproprie'  => 'Inapproprié',
    'autre'        => 'Autre',
];

function signalCard(array $r, string $col, array $tagClass, array $tagLabel): void {
    $tag   = $r['reason'] ?? 'autre';
    $cls   = $tagClass[$tag] ?? 'tag-other';
    $lbl   = $tagLabel[$tag] ?? ucfirst($tag);
    $rapporteur = trim(($r['reporter_prenom'] ?? '') . ' ' . ($r['reporter_nom'] ?? ''));
    $cible      = trim(($r['cible_prenom'] ?? '') . ' ' . ($r['cible_nom'] ?? ''));
    $initR = strtoupper(mb_substr($rapporteur, 0, 1) ?: 'A');
    $initC = strtoupper(mb_substr($cible, 0, 1) ?: 'A');
    $contenu = mb_substr($r['post_content'] ?? $r['description'] ?? '', 0, 80);
    $diff = $r['created_at'] ? (time() - strtotime($r['created_at'])) : 0;
    $timeStr = $diff < 3600 ? floor($diff/60).'min' : ($diff < 86400 ? floor($diff/3600).'h' : floor($diff/86400).'j');
    ?>
<div class="kanban-card">
  <span class="kanban-card-tag <?= $cls ?>"><?= e($lbl) ?></span>
  <?php if ($contenu): ?>
  <p class="kanban-card-text">"<?= e($contenu) ?>…"</p>
  <?php else: ?>
  <p class="kanban-card-text" style="color:var(--txt-l);font-style:italic;">Signalement sans contenu</p>
  <?php endif; ?>
  <div class="kanban-card-meta">
    <div class="kanban-card-avatars">
      <div class="mini-avatar" title="Rapporté par <?= e($rapporteur ?: 'anonyme') ?>"><?= $initR ?></div>
      <div class="mini-avatar" style="background:var(--txt-m);" title="Signalé : <?= e($cible ?: '?') ?>"><?= $initC ?></div>
      <span style="font-size:11px;color:var(--txt-m);">par <?= e($rapporteur ?: 'anonyme') ?></span>
    </div>
    <span>il y a <?= $timeStr ?></span>
  </div>
  <?php if ($col === 'pending'): ?>
  <div class="kanban-card-actions">
    <button class="btn-ghost btn-sm btn-traiter-report" data-id="<?= $r['id'] ?>" data-action="reviewed" style="flex:1;">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      Examiner
    </button>
    <button class="btn-ghost btn-sm btn-traiter-report" data-id="<?= $r['id'] ?>" data-action="rejected"
            style="flex:1;color:var(--red);border-color:var(--red-bg);">
      Rejeter
    </button>
  </div>
  <?php endif; ?>
</div>
    <?php
}
?>

<!-- Header -->
<div class="admin-page-header-row">
  <div>
    <h1>Modération — Signalements</h1>
    <p>
      <?= count($pending) + count($reviewed) + count($rejected) ?> signalement(s) au total ·
      <?= count($pending) ?> en attente de traitement
    </p>
  </div>
  <div style="display:flex;gap:10px;">
    <button class="btn-ghost">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
      Vue liste
    </button>
    <button class="btn-primary">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      Règles modération
    </button>
  </div>
</div>

<!-- Kanban -->
<div class="kanban-grid">

  <!-- Colonne En attente -->
  <div class="kanban-col">
    <div class="kanban-col-header">
      <div class="kanban-col-title">
        <span class="kanban-dot pending"></span>
        En attente
      </div>
      <span class="kanban-count"><?= count($pending) ?></span>
    </div>
    <div class="kanban-cards">
      <?php foreach ($pending as $r): signalCard($r, 'pending', $tagClass, $tagLabel); endforeach; ?>
      <?php if (empty($pending)): ?>
        <p style="text-align:center;color:var(--txt-l);font-size:12px;padding:20px 0;">Aucun signalement en attente 🎉</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Colonne Examinés -->
  <div class="kanban-col">
    <div class="kanban-col-header">
      <div class="kanban-col-title">
        <span class="kanban-dot reviewed"></span>
        Examinés
      </div>
      <span class="kanban-count"><?= count($reviewed) ?></span>
    </div>
    <div class="kanban-cards">
      <?php foreach ($reviewed as $r): signalCard($r, 'reviewed', $tagClass, $tagLabel); endforeach; ?>
      <?php if (empty($reviewed)): ?>
        <p style="text-align:center;color:var(--txt-l);font-size:12px;padding:20px 0;">Aucun signalement examiné</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Colonne Rejetés -->
  <div class="kanban-col">
    <div class="kanban-col-header">
      <div class="kanban-col-title">
        <span class="kanban-dot rejected"></span>
        Rejetés
      </div>
      <span class="kanban-count"><?= count($rejected) ?></span>
    </div>
    <div class="kanban-cards">
      <?php foreach ($rejected as $r): signalCard($r, 'rejected', $tagClass, $tagLabel); endforeach; ?>
      <?php if (empty($rejected)): ?>
        <p style="text-align:center;color:var(--txt-l);font-size:12px;padding:20px 0;">Aucun signalement rejeté</p>
      <?php endif; ?>
    </div>
  </div>

</div>
