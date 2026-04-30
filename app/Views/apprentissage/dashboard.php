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
    <h1><?= $userName ?> 👋</h1>
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
      <div class="kpi-card__icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
        </svg>
      </div>
      <div class="kpi-card__value"><?= (int)($stats['cours']['consultes'] ?? 0) ?> / <?= (int)($stats['cours']['total'] ?? 0) ?></div>
      <div class="kpi-card__label">Cours consultés</div>
    </div>

    <div class="kpi-card">
      <div class="kpi-card__icon" style="background:#EFF6FF;color:#3B82F6">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
        </svg>
      </div>
      <div class="kpi-card__value"><?= formatDuration((int)($stats['temps'] ?? 0)) ?></div>
      <div class="kpi-card__label">Temps de révision (semaine)</div>
    </div>

    <div class="kpi-card">
      <div class="kpi-card__icon" style="background:#ECFDF5;color:#059669">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
        </svg>
      </div>
      <div class="kpi-card__value"><?= (int)($stats['terminees'] ?? 0) ?></div>
      <div class="kpi-card__label">Ressources terminées</div>
    </div>

    <div class="kpi-card">
      <div class="kpi-card__icon" style="background:#FFF7ED;color:#E85D04">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
        </svg>
      </div>
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
        <div style="display:flex;align-items:flex-start;gap:12px">
          <div class="resource-card__icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
              <polyline points="14 2 14 8 20 8"/>
            </svg>
          </div>
          <div style="min-width:0;flex:1">
            <div class="resource-card__title"><?= e($r['titre']) ?></div>
            <div class="resource-card__meta" style="margin-top:4px">
              <span class="badge badge-<?= e($r['type']) ?>"><?= typeLabel($r['type']) ?></span>
              <span><?= e($r['matiere']) ?></span>
            </div>
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

  <!-- ── Ressources récentes ────────────────────────────── -->
  <?php if (!empty($recentes)): ?>
  <section style="margin-bottom:40px">
    <div class="section-header">
      <h2>Récemment ajoutés</h2>
      <a href="<?= url('/apprentissage/matieres') ?>">Explorer →</a>
    </div>
    <div class="grid-cards">
      <?php foreach ($recentes as $r): ?>
      <a href="<?= url('/apprentissage/viewer/' . (int)$r['id']) ?>" class="resource-card">
        <div style="display:flex;align-items:flex-start;gap:12px">
          <div class="resource-card__icon">
            <?php if ($r['type'] === 'cours'): ?>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
            <?php elseif ($r['type'] === 'td'): ?>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            <?php else: ?>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 9h6M9 12h6M9 15h4"/></svg>
            <?php endif; ?>
          </div>
          <div style="min-width:0;flex:1">
            <div class="resource-card__title"><?= e($r['titre']) ?></div>
            <div style="font-size:12px;color:#9CA3AF;margin-top:2px"><?= e($r['matiere']) ?></div>
          </div>
        </div>
        <div class="resource-card__meta">
          <span class="badge badge-<?= e($r['type']) ?>"><?= typeLabel($r['type']) ?></span>
          <?php if (!empty($r['serie'])): ?>
            <span class="badge badge-serie-<?= e($r['serie']) ?>">Tle <?= e($r['serie']) ?></span>
          <?php endif; ?>
          <?php if (!empty($r['nb_vues'])): ?>
            <span style="margin-left:auto;display:flex;align-items:center;gap:3px;font-size:11px;color:#9CA3AF">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <?= (int)$r['nb_vues'] ?>
            </span>
          <?php endif; ?>
        </div>
        <div class="resource-card__footer">
          <span class="recent-badge">Nouveau</span>
          <span style="font-size:12px;color:#8B52FA;font-weight:600;display:flex;align-items:center;gap:3px">
            Consulter <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
          </span>
        </div>
      </a>
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
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
          </svg>
        </div>
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
        <div class="resource-card__icon" style="background:#EFF6FF;color:#3B82F6">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/>
            <line x1="6" y1="20" x2="6" y2="14"/>
          </svg>
        </div>
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
        <div class="resource-card__icon" style="background:#FFF7ED;color:#F59E0B">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
          </svg>
        </div>
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
