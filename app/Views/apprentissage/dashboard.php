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
        'corrige'          => 'Corrigé',
        default            => ucfirst($type),
    };
}
?>

<div class="appr-page">

  <!-- ── Sous-navigation ───────────────────────────────── -->
  <nav class="appr-subnav">
    <a href="<?= url('/apprentissage') ?>" class="appr-subnav__link active">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
      Tableau de bord
    </a>
    <a href="<?= url('/apprentissage/matieres') ?>" class="appr-subnav__link">
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
    <p style="color:#6B7280;font-size:14px;margin-bottom:4px"><?= $greeting ?>,</p>
    <h1><?= $userName ?> </h1>
    <?php if ($serieLabel): ?>
      <div style="margin-top:8px;display:flex;align-items:center;gap:8px">
        <span class="badge badge-serie-<?= e($serieLabel) ?>">Terminale <?= e($serieLabel) ?></span>
      </div>
    <?php else: ?>
      <div style="margin-top:10px">
        <a href="<?= url('/apprentissage/matieres') ?>" class="btn-appr btn-appr--outline btn-appr--sm">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
          Choisir ma série →
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
      <div class="kpi-card__value" style="font-size:18px;line-height:1.3"><?= e($stats['matiere_fav']['nom'] ?? 'Aucune') ?></div>
      <div class="kpi-card__label">Matière favorite</div>
    </div>
  </div>

  <!-- ── Reprendre en cours ─────────────────────────────── -->
  <?php if (!empty($en_cours)): ?>
  <section style="margin-bottom:40px">
    <div class="section-header">
      <h2>Reprendre là où vous vous êtes arrêté</h2>
      <a href="<?= url('/apprentissage/matieres') ?>">Voir tout →</a>
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
          <div style="font-size:12px;color:#6B7280;margin-top:4px"><?= (int)$r['pourcentage'] ?>% complété</div>
        </div>
        <div class="resource-card__footer">
          <span style="font-size:12px;color:#6B7280">En cours</span>
          <a href="<?= url('/apprentissage/viewer/' . (int)$r['id']) ?>" class="btn-appr btn-appr--primary btn-appr--sm">
            Continuer →
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
        <div class="resource-card__title">Mes matières</div>
        <p style="font-size:13px;color:#6B7280;margin:0">Accéder aux ressources par matière</p>
        <div class="resource-card__footer">
          <span></span>
          <span style="color:#8B52FA;font-size:13px;font-weight:600;display:flex;align-items:center;gap:3px">
            Explorer <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
          </span>
        </div>
      </a>

      <a href="<?= url('/apprentissage/progression') ?>" class="resource-card">
        <div class="resource-card__title">Ma progression</div>
        <p style="font-size:13px;color:#6B7280;margin:0">Suivre vos statistiques d'apprentissage</p>
        <div class="resource-card__footer">
          <span></span>
          <span style="color:#3B82F6;font-size:13px;font-weight:600;display:flex;align-items:center;gap:3px">
            Voir <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
          </span>
        </div>
      </a>

      <a href="<?= url('/apprentissage/favoris') ?>" class="resource-card">
        <div class="resource-card__title">Mes favoris</div>
        <p style="font-size:13px;color:#6B7280;margin:0">Retrouver vos ressources sauvegardées</p>
        <div class="resource-card__footer">
          <span></span>
          <span style="color:#F59E0B;font-size:13px;font-weight:600;display:flex;align-items:center;gap:3px">
            Voir <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
          </span>
        </div>
      </a>
    </div>
  </section>

</div>
