<?php
$matieres   = $matieres   ?? [];
$serie      = $serie      ?? null;
$allSeries  = $all_series ?? [];
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
?>

<div class="appr-page">

  <!-- ── Sous-navigation ───────────────────────────────── -->
  <nav class="appr-subnav">
    <a href="<?= url('/apprentissage') ?>" class="appr-subnav__link">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
      Tableau de bord
    </a>
    <a href="<?= url('/apprentissage/matieres') ?>" class="appr-subnav__link active">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
      Mes matières
    </a>
    <a href="<?= url('/apprentissage/progression') ?>" class="appr-subnav__link">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      Progression
    </a>
    <a href="<?= url('/apprentissage/favoris') ?>" class="appr-subnav__link">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
      Favoris
    </a>
  </nav>

  <!-- ── Hero ──────────────────────────────────────────── -->
  <div class="appr-hero">
    <p style="color:#6B7280;font-size:14px;margin-bottom:4px">Module d'apprentissage</p>
    <h1>
      <?php if ($serie): ?>
        Terminale <?= e($serie['nom']) ?> — Mes matières
      <?php else: ?>
        Choisissez votre série
      <?php endif; ?>
    </h1>
    <?php if ($serie && !empty($serie['description'])): ?>
      <p><?= e($serie['description']) ?></p>
    <?php elseif (!$serie): ?>
      <p>Sélectionnez votre série pour accéder aux matières correspondantes.</p>
    <?php endif; ?>
  </div>

  <!-- ── Cas 1 : pas de série → afficher toutes les séries ── -->
  <?php if (!$serie && !empty($allSeries)): ?>
    <div class="series-grid">
      <?php foreach ($allSeries as $s): ?>
        <a href="<?= url('/apprentissage/matieres?serie=' . (int)$s['id']) ?>" class="serie-card">
          <div class="serie-card__name">Tle <?= e($s['nom']) ?></div>
          <div class="serie-card__label">
            <?= e($s['description'] ?? 'Terminale série ' . $s['nom']) ?>
          </div>
          <div style="margin-top:12px;display:flex;justify-content:center">
            <span class="badge badge-serie-<?= e($s['nom']) ?>">Série <?= e($s['nom']) ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>

  <!-- ── Cas 2 : série choisie mais sans matières ─────── -->
  <?php elseif ($serie && empty($matieres)): ?>
    <div class="empty-state">
      <div class="empty-state__icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
          <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
        </svg>
      </div>
      <h3>Aucune matière disponible</h3>
      <p>Les matières de la série <?= e($serie['nom']) ?> seront bientôt disponibles.</p>
      <a href="<?= url('/apprentissage/matieres') ?>" class="btn-appr btn-appr--outline" style="margin-top:20px">
        Changer de série
      </a>
    </div>

  <!-- ── Cas 3 : affichage des matières ───────────────── -->
  <?php elseif (!empty($matieres)): ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,240px),1fr));gap:20px">
      <?php foreach ($matieres as $m): ?>
        <?php
          $pct = (int) round((float)($m['progression_moyenne'] ?? 0));
          $nb  = (int) ($m['nb_ressources'] ?? 0);
        ?>
        <a href="<?= url('/apprentissage/ressources?matiere=' . (int)$m['id']) ?>" class="matiere-card">

          <div style="display:flex;align-items:center;gap:14px">
            <div style="width:48px;height:48px;background:#F3EFFF;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0">
              <?= !empty($m['icone']) ? e($m['icone']) : '📚' ?>
            </div>
            <div style="min-width:0">
              <div style="font-family:'Poppins','Inter',sans-serif;font-weight:700;font-size:16px;color:#1F1C2C;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($m['nom']) ?></div>
              <div style="font-size:12px;color:#6B7280;margin-top:2px"><?= $nb ?> ressource<?= $nb > 1 ? 's' : '' ?></div>
            </div>
          </div>

          <div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
              <span style="font-size:12px;color:#6B7280">Progression</span>
              <span style="font-size:13px;font-weight:700;color:#8B52FA"><?= $pct ?>%</span>
            </div>
            <div class="progress-bar-container">
              <div class="progress-bar-fill" style="width:<?= $pct ?>%"></div>
            </div>
          </div>

          <div class="resource-card__footer" style="padding-top:10px">
            <span style="font-size:12px;color:#6B7280">
              <?= $pct >= 100 ? '✅ Terminé' : ($pct > 0 ? 'En cours' : 'Non commencé') ?>
            </span>
            <span style="display:flex;align-items:center;gap:4px;color:#8B52FA;font-size:13px;font-weight:600">
              Accéder
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
            </span>
          </div>

        </a>
      <?php endforeach; ?>
    </div>

  <?php else: ?>
    <div class="empty-state">
      <div class="empty-state__icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
          <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
        </svg>
      </div>
      <h3>Aucune matière disponible</h3>
      <p>Les matières seront bientôt disponibles.</p>
    </div>
  <?php endif; ?>

</div>
