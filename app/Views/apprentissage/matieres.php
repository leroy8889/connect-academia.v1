<?php
$matieres   = $matieres   ?? [];
$serie      = $serie      ?? null;
$allSeries  = $all_series ?? [];
$currentUri = $_SERVER['REQUEST_URI'] ?? '';

function matiereIcon(string $nom): string {
    $n = mb_strtolower($nom, 'UTF-8');
    if (str_contains($n, 'math'))                                                                        return 'calculator';
    if (str_contains($n, 'physique') || str_contains($n, 'chimie'))                                     return 'flask-conical';
    if (str_contains($n, 'svt') || str_contains($n, 'biolog') || (str_contains($n, 'vie') && str_contains($n, 'terre'))) return 'leaf';
    if (str_contains($n, 'philo'))                                                                       return 'lightbulb';
    if (str_contains($n, 'histoire') || str_contains($n, 'géographie') || str_contains($n, 'geographie')) return 'globe';
    if (str_contains($n, 'français') || str_contains($n, 'francais') || str_contains($n, 'litt'))       return 'pen-line';
    if (str_contains($n, 'anglais') || str_contains($n, 'espagnol') || str_contains($n, 'allemand') || str_contains($n, 'langues vivantes')) return 'languages';
    if (str_contains($n, 'économie') || str_contains($n, 'economie'))                                   return 'trending-up';
    if (str_contains($n, 'comptab'))                                                                     return 'receipt';
    if (str_contains($n, 'informatique') || str_contains($n, 'numérique') || str_contains($n, 'numerique')) return 'monitor';
    if (str_contains($n, 'musique') || str_contains($n, 'plastique') || str_contains($n, 'arts'))       return 'palette';
    if (str_contains($n, 'eps') || str_contains($n, 'sport'))                                           return 'trophy';
    if (str_contains($n, 'technolog') || str_contains($n, 'industri'))                                  return 'settings-2';
    if (str_contains($n, 'commerce') || str_contains($n, 'gestion'))                                    return 'landmark';
    if (str_contains($n, 'droit') || str_contains($n, 'juridique'))                                     return 'scale';
    return 'book-open';
}

function serieIcon(string $nom): string {
    $n = strtolower(trim($nom));
    if (in_array($n, ['a', 'a1', 'a2'])) return 'book-text';
    if ($n === 'b')  return 'trending-up';
    if ($n === 'c')  return 'calculator';
    if ($n === 'd')  return 'leaf';
    if (in_array($n, ['f3', 'f4'])) return 'settings-2';
    if ($n === 'g')  return 'briefcase';
    return 'graduation-cap';
}
?>

<div class="appr-page">

  <!-- ── Sous-navigation ──────────────────────────────────── -->
  <nav class="appr-subnav">
    <a href="<?= url('/apprentissage') ?>" class="appr-subnav__link">
      <i data-lucide="layout-dashboard" style="width:15px;height:15px;flex-shrink:0"></i>
      Tableau de bord
    </a>
    <a href="<?= url('/apprentissage/matieres') ?>" class="appr-subnav__link active">
      <i data-lucide="book-copy" style="width:15px;height:15px;flex-shrink:0"></i>
      Mes matières
    </a>
    <a href="<?= url('/apprentissage/progression') ?>" class="appr-subnav__link">
      <i data-lucide="bar-chart-3" style="width:15px;height:15px;flex-shrink:0"></i>
      Progression
    </a>
    <a href="<?= url('/apprentissage/favoris') ?>" class="appr-subnav__link">
      <i data-lucide="star" style="width:15px;height:15px;flex-shrink:0"></i>
      Favoris
    </a>
  </nav>

  <!-- ── Hero ─────────────────────────────────────────────── -->
  <div class="appr-hero">
    <p class="appr-eyebrow">Module d'apprentissage</p>
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
          <div class="serie-card__icon-wrap">
            <i data-lucide="<?= serieIcon($s['nom']) ?>"></i>
          </div>
          <div class="serie-card__name">Tle <?= e($s['nom']) ?></div>
          <div class="serie-card__label"><?= e($s['description'] ?? 'Terminale série ' . $s['nom']) ?></div>
          <div style="margin-top:12px;display:flex;justify-content:center">
            <span class="ca-badge ca-badge-brand-soft">Série <?= e($s['nom']) ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>

  <!-- ── Cas 2 : série choisie mais sans matières ─────────── -->
  <?php elseif ($serie && empty($matieres)): ?>
    <div class="empty-state">
      <div class="empty-state__icon">
        <i data-lucide="book-open" style="width:32px;height:32px"></i>
      </div>
      <h3>Aucune matière disponible</h3>
      <p>Les matières de la série <?= e($serie['nom']) ?> seront bientôt disponibles.</p>
      <a href="<?= url('/apprentissage/matieres') ?>" class="btn-appr btn-appr--outline" style="margin-top:20px">
        Changer de série
      </a>
    </div>

  <!-- ── Cas 3 : affichage des matières ────────────────────── -->
  <?php elseif (!empty($matieres)): ?>
    <div class="matieres-grid">
      <?php foreach ($matieres as $m): ?>
        <?php
          $pct    = (int) round((float)($m['progression_moyenne'] ?? 0));
          $nb     = (int) ($m['nb_ressources'] ?? 0);
          $icon   = matiereIcon($m['nom'] ?? '');
          $status = $pct >= 100 ? 'termine' : ($pct > 0 ? 'en-cours' : 'nouveau');
        ?>
        <a href="<?= url('/apprentissage/ressources?matiere=' . (int)$m['id']) ?>" class="matiere-card">

          <div class="matiere-card__header">
            <div class="matiere-card__icon-cap">
              <i data-lucide="<?= $icon ?>" style="width:20px;height:20px"></i>
            </div>
            <div class="matiere-card__info">
              <div class="matiere-card__name"><?= e($m['nom']) ?></div>
              <div class="matiere-card__count">
                <i data-lucide="files" style="width:12px;height:12px"></i>
                <?= $nb ?> ressource<?= $nb > 1 ? 's' : '' ?>
              </div>
            </div>
          </div>

          <div class="matiere-card__progress-section">
            <div class="matiere-card__progress-header">
              <span class="ca-eyebrow">Progression</span>
              <span class="matiere-card__pct"><?= $pct ?>%</span>
            </div>
            <div class="ca-progress">
              <div class="ca-progress-fill" style="width:<?= $pct ?>%"></div>
            </div>
          </div>

          <div class="matiere-card__footer">
            <?php if ($status === 'termine'): ?>
              <span class="ca-badge ca-badge-success">
                <i data-lucide="check-circle-2" style="width:11px;height:11px"></i>
                Terminé
              </span>
            <?php elseif ($status === 'en-cours'): ?>
              <span class="ca-badge ca-badge-warning">
                <i data-lucide="clock" style="width:11px;height:11px"></i>
                En cours
              </span>
            <?php else: ?>
              <span class="ca-badge ca-badge-muted">
                <i data-lucide="circle-dashed" style="width:11px;height:11px"></i>
                Nouveau
              </span>
            <?php endif; ?>
            <span class="matiere-card__cta">
              Accéder
              <i data-lucide="arrow-right" style="width:14px;height:14px"></i>
            </span>
          </div>

        </a>
      <?php endforeach; ?>
    </div>

  <?php else: ?>
    <div class="empty-state">
      <div class="empty-state__icon">
        <i data-lucide="book-open" style="width:32px;height:32px"></i>
      </div>
      <h3>Aucune matière disponible</h3>
      <p>Les matières seront bientôt disponibles.</p>
    </div>
  <?php endif; ?>

</div>
