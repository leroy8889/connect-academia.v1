<?php
$userName   = e(\Core\Session::get('user_name', 'élève'));
$hour       = (int) date('H');
$greeting   = $hour < 12 ? 'Bonjour' : ($hour < 18 ? 'Bon après-midi' : 'Bonsoir');
$serieLabel = $serie['nom'] ?? null;
$recentes   = $ressources_recentes ?? [];

function formatDuration(int $seconds): string {
    $h = (int)($seconds / 3600);
    $m = (int)(($seconds % 3600) / 60);
    if ($h > 0) return "{$h}h {$m}m";
    if ($m > 0) return "{$m}min";
    return "0min";
}

function typeLabel(string $type): string {
    return match($type) {
        'cours'            => 'Cours',
        'td'               => 'TD',
        'ancienne_epreuve' => 'Ancienne épreuve',
        'corrige'          => 'Méthodologie',
        default            => ucfirst($type),
    };
}
?>

<div class="appr-page">

  <!-- ── Sous-navigation ───────────────────────────────── -->
  <nav class="appr-subnav">
    <a href="<?= url('/apprentissage') ?>" class="appr-subnav__link active">
      <i data-lucide="layout-dashboard" style="width:15px;height:15px;flex-shrink:0"></i>
      Tableau de bord
    </a>
    <a href="<?= url('/apprentissage/matieres') ?>" class="appr-subnav__link">
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

  <!-- ── Hero ──────────────────────────────────────────── -->
  <div class="appr-hero">
    <p class="appr-eyebrow"><?= $greeting ?></p>
    <h1><?= $userName ?></h1>
    <?php if ($serieLabel): ?>
      <div style="margin-top:10px;display:flex;align-items:center;gap:8px">
        <span class="badge badge-serie-<?= e($serieLabel) ?>">Terminale <?= e($serieLabel) ?></span>
      </div>
    <?php else: ?>
      <div style="margin-top:12px">
        <a href="<?= url('/apprentissage/matieres') ?>" class="btn-appr btn-appr--outline btn-appr--sm">
          <i data-lucide="book-copy" style="width:14px;height:14px"></i>
          Choisir ma série
        </a>
      </div>
    <?php endif; ?>
  </div>

  <!-- ── KPIs ──────────────────────────────────────────── -->
  <div class="kpi-grid">
    <div class="kpi-card">
      <div class="kpi-card__value"><?= (int)($stats['cours']['consultes'] ?? 0) ?> / <?= (int)($stats['cours']['total'] ?? 0) ?></div>
      <div class="kpi-card__label">Cours consultés</div>
    </div>

    <div class="kpi-card">
      <div class="kpi-card__value"><?= formatDuration((int)($stats['temps'] ?? 0)) ?></div>
      <div class="kpi-card__label">Temps de révision (semaine)</div>
    </div>

    <div class="kpi-card">
      <div class="kpi-card__value"><?= (int)($stats['terminees'] ?? 0) ?></div>
      <div class="kpi-card__label">Ressources terminées</div>
    </div>

    <div class="kpi-card">
      <div class="kpi-card__value" style="font-size:18px;line-height:1.3;font-weight:500"><?= e($stats['matiere_fav']['nom'] ?? 'Aucune') ?></div>
      <div class="kpi-card__label">Matière favorite</div>
    </div>
  </div>

  <!-- ── Reprendre en cours ─────────────────────────────── -->
  <?php if (!empty($en_cours)): ?>
  <section style="margin-bottom:96px">
    <div class="section-header">
      <h2>Reprendre là où tu t'es arrêté</h2>
      <a href="<?= url('/apprentissage/matieres') ?>">
        Voir tout
        <i data-lucide="arrow-right" style="width:13px;height:13px"></i>
      </a>
    </div>
    <div class="grid-cards">
      <?php foreach ($en_cours as $r): ?>
      <div class="resource-card">
        <div>
          <div class="resource-card__title"><?= e($r['titre']) ?></div>
          <div class="resource-card__meta" style="margin-top:6px">
            <span class="badge badge-<?= e($r['type']) ?>"><?= typeLabel($r['type']) ?></span>
            <span><?= e($r['matiere']) ?></span>
          </div>
        </div>
        <div>
          <div class="progress-bar-container">
            <div class="progress-bar-fill" style="width:<?= (int)$r['pourcentage'] ?>%"></div>
          </div>
          <div class="ca-caption" style="margin-top:5px"><?= (int)$r['pourcentage'] ?>% complété</div>
        </div>
        <div class="resource-card__footer">
          <span class="ca-caption">En cours</span>
          <a href="<?= url('/apprentissage/viewer/' . (int)$r['id']) ?>" class="btn-appr btn-appr--primary btn-appr--sm">
            Continuer
            <i data-lucide="arrow-right" style="width:13px;height:13px"></i>
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>


  <!-- ── Accès rapide ───────────────────────────────────── -->
  <section>
    <div class="section-header">
      <h2>Accès rapide</h2>
    </div>
    <div class="grid-cards">
      <a href="<?= url('/apprentissage/matieres') ?>" class="resource-card">
        <div class="resource-card__icon">
          <i data-lucide="book-copy" style="width:22px;height:22px"></i>
        </div>
        <div class="resource-card__title">Mes matières</div>
        <p class="ca-body" style="margin:0">Accède aux ressources de ta série, matière par matière.</p>
        <div class="resource-card__footer">
          <span></span>
          <span class="matiere-card__cta">
            Explorer
            <i data-lucide="arrow-right" style="width:14px;height:14px"></i>
          </span>
        </div>
      </a>

      <a href="<?= url('/apprentissage/progression') ?>" class="resource-card">
        <div class="resource-card__icon">
          <i data-lucide="bar-chart-3" style="width:22px;height:22px"></i>
        </div>
        <div class="resource-card__title">Ma progression</div>
        <p class="ca-body" style="margin:0">Suis tes statistiques d'apprentissage en un coup d'œil.</p>
        <div class="resource-card__footer">
          <span></span>
          <span class="matiere-card__cta">
            Voir
            <i data-lucide="arrow-right" style="width:14px;height:14px"></i>
          </span>
        </div>
      </a>

      <a href="<?= url('/apprentissage/favoris') ?>" class="resource-card">
        <div class="resource-card__icon">
          <i data-lucide="star" style="width:22px;height:22px"></i>
        </div>
        <div class="resource-card__title">Mes favoris</div>
        <p class="ca-body" style="margin:0">Retrouve tes ressources sauvegardées, prêtes à réviser.</p>
        <div class="resource-card__footer">
          <span></span>
          <span class="matiere-card__cta">
            Voir
            <i data-lucide="arrow-right" style="width:14px;height:14px"></i>
          </span>
        </div>
      </a>
    </div>
  </section>

</div>
