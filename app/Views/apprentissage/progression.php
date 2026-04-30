<?php
$progressionGlobale  = (int) ($progression_globale  ?? 0);
$tempsSemaine        = (int) ($temps_semaine        ?? 0);
$progressionMatieres = $progression_matieres ?? [];

function fmtDuration(int $s): string {
    $h = (int)($s / 3600);
    $m = (int)(($s % 3600) / 60);
    if ($h > 0) return "{$h}h {$m}min";
    return "{$m}min";
}
?>

<div class="appr-page">

  <!-- ── Sous-navigation ───────────────────────────────── -->
  <nav class="appr-subnav">
    <a href="<?= url('/apprentissage') ?>" class="appr-subnav__link">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
      Tableau de bord
    </a>
    <a href="<?= url('/apprentissage/matieres') ?>" class="appr-subnav__link">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
      Mes matières
    </a>
    <a href="<?= url('/apprentissage/progression') ?>" class="appr-subnav__link active">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      Progression
    </a>
    <a href="<?= url('/apprentissage/favoris') ?>" class="appr-subnav__link">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
      Favoris
    </a>
  </nav>

  <!-- ── Hero ──────────────────────────────────────────────── -->
  <div class="appr-hero">
    <p style="color:#6B7280;font-size:14px;margin-bottom:4px">Module d'apprentissage</p>
    <h1>Ma progression</h1>
  </div>

  <!-- ── KPIs globaux ──────────────────────────────────────── -->
  <div class="kpi-grid" style="margin-bottom:40px">

    <!-- Progression globale (cercle) -->
    <div class="kpi-card" style="align-items:center;padding:28px 20px">
      <div class="circular-progress" style="--pct:<?= $progressionGlobale * 3.6 ?>deg">
        <span class="circular-progress__value"><?= $progressionGlobale ?>%</span>
      </div>
      <div class="kpi-card__label" style="text-align:center">Progression globale</div>
    </div>

    <!-- Temps de révision -->
    <div class="kpi-card">
      <div class="kpi-card__icon" style="background:#EFF6FF;color:#3B82F6">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
        </svg>
      </div>
      <div class="kpi-card__value"><?= fmtDuration($tempsSemaine) ?></div>
      <div class="kpi-card__label">Temps de révision (7 jours)</div>
    </div>

    <!-- Matières commencées -->
    <?php $nbMatieres = count(array_filter($progressionMatieres, fn($m) => (int)($m['terminees'] ?? 0) + (int)($m['en_cours'] ?? 0) > 0)); ?>
    <div class="kpi-card">
      <div class="kpi-card__icon" style="background:#ECFDF5;color:#059669">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
        </svg>
      </div>
      <div class="kpi-card__value"><?= $nbMatieres ?> / <?= count($progressionMatieres) ?></div>
      <div class="kpi-card__label">Matières commencées</div>
    </div>

    <!-- Ressources terminées -->
    <?php $nbTerminees = array_sum(array_column($progressionMatieres, 'terminees')); ?>
    <div class="kpi-card">
      <div class="kpi-card__icon" style="background:#FFF7ED;color:#D97706">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="20 6 9 17 4 12"/>
        </svg>
      </div>
      <div class="kpi-card__value"><?= (int)$nbTerminees ?></div>
      <div class="kpi-card__label">Ressources terminées</div>
    </div>

  </div>

  <!-- ── Progression par matière ───────────────────────────── -->
  <div class="section-header">
    <h2>Par matière</h2>
    <a href="<?= url('/apprentissage') ?>">← Tableau de bord</a>
  </div>

  <?php if (empty($progressionMatieres)): ?>
    <div class="empty-state">
      <div class="empty-state__icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
      </div>
      <h3>Aucune progression enregistrée</h3>
      <p>Consultez des ressources pour voir votre progression ici.</p>
      <a href="<?= url('/apprentissage') ?>" class="btn-appr btn-appr--primary" style="margin-top:20px">Commencer à apprendre</a>
    </div>

  <?php else: ?>
    <div style="display:grid;gap:16px">
      <?php foreach ($progressionMatieres as $m):
        $total    = max(1, (int) ($m['total_ressources'] ?? 1));
        $termines = (int) ($m['terminees'] ?? 0);
        $enCours  = (int) ($m['en_cours']  ?? 0);
        $pct      = (int) round((float) ($m['progression_moyenne'] ?? 0));
      ?>
        <div class="matiere-progress-card">

          <div class="matiere-progress-header">
            <div style="display:flex;align-items:center;gap:12px;min-width:0;flex:1">
              <div class="matiere-progress-icon">
                <?php if (!empty($m['icone'])): ?>
                  <span style="font-size:18px"><?= e($m['icone']) ?></span>
                <?php else: ?>
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                  </svg>
                <?php endif; ?>
              </div>
              <div class="matiere-progress-info">
                <div class="matiere-progress-name"><?= e($m['nom']) ?></div>
                <div class="matiere-progress-sub">
                  <?= $termines ?> terminé<?= $termines > 1 ? 's' : '' ?>
                  · <?= $enCours ?> en cours
                  · <?= $total ?> total
                </div>
              </div>
            </div>
            <div class="matiere-progress-pct"><?= $pct ?>%</div>
          </div>

          <div class="progress-bar-container lg">
            <div class="progress-bar-fill" style="width:<?= $pct ?>%"></div>
          </div>

          <div style="display:flex;justify-content:flex-end;margin-top:12px">
            <a href="<?= url('/apprentissage/ressources?matiere=' . (int)$m['id']) ?>"
               class="btn-appr btn-appr--outline btn-appr--sm">
              Voir les ressources
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
            </a>
          </div>

        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<style>
/* Circular progress utilise deg pour compatibilité via conic-gradient CSS natif */
.circular-progress {
  background: conic-gradient(#8B52FA var(--pct, 0deg), #F3EFFF 0deg) !important;
}
</style>

<script>
// Animer la progression circulaire au chargement
document.addEventListener('DOMContentLoaded', function () {
    const el = document.querySelector('.circular-progress');
    if (el) {
        const pct = <?= $progressionGlobale ?>;
        el.style.setProperty('--pct', (pct * 3.6) + 'deg');
    }
});
</script>
