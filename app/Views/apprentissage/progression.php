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

<div class="appr-page prog-page">

  <!-- ── Sous-navigation ───────────────────────────────── -->
  <nav class="appr-subnav">
    <a href="<?= url('/apprentissage') ?>" class="appr-subnav__link">
      <i data-lucide="layout-dashboard" style="width:15px;height:15px"></i>
      Tableau de bord
    </a>
    <a href="<?= url('/apprentissage/matieres') ?>" class="appr-subnav__link">
      <i data-lucide="book-open" style="width:15px;height:15px"></i>
      Mes matières
    </a>
    <a href="<?= url('/apprentissage/progression') ?>" class="appr-subnav__link active">
      <i data-lucide="bar-chart-2" style="width:15px;height:15px"></i>
      Progression
    </a>
    <a href="<?= url('/apprentissage/favoris') ?>" class="appr-subnav__link">
      <i data-lucide="star" style="width:15px;height:15px"></i>
      Favoris
    </a>
  </nav>

  <!-- ── Hero ──────────────────────────────────────────── -->
  <header class="prog-hero">
    <p class="prog-eyebrow">Module d'apprentissage</p>
    <h1 class="prog-h1">Ma progression</h1>
    <p class="prog-subtitle">Suis l'évolution de ton apprentissage, matière par matière.</p>
  </header>

  <!-- ── KPI bento ─────────────────────────────────────── -->
  <div class="prog-bento">

    <!-- Score global — carte promue -->
    <div class="prog-kpi-promoted">
      <span class="prog-kpi-eyebrow">Score global</span>
      <div class="circular-progress" style="--pct:<?= $progressionGlobale * 3.6 ?>deg">
        <span class="circular-progress__value"><?= $progressionGlobale ?>%</span>
      </div>
      <p class="prog-kpi-label-white">Progression globale</p>
      <div class="prog-kpi-promoted-glow"></div>
    </div>

    <!-- Temps révision -->
    <div class="prog-kpi-card">
      
      <div class="prog-kpi-val"><?= fmtDuration($tempsSemaine) ?></div>
      <div class="prog-kpi-desc">Temps de révision</div>
      <div class="prog-kpi-hint">sur les 7 derniers jours</div>
    </div>

    <!-- Matières commencées -->
    <?php $nbMatieres = count(array_filter($progressionMatieres, fn($m) => (int)($m['terminees'] ?? 0) + (int)($m['en_cours'] ?? 0) > 0)); ?>
    <div class="prog-kpi-card">
      
      <div class="prog-kpi-val">
        <?= $nbMatieres ?><span class="prog-kpi-total"> / <?= count($progressionMatieres) ?></span>
      </div>
      <div class="prog-kpi-desc">Matières commencées</div>
      <div class="prog-kpi-hint">sur ton programme</div>
    </div>

    <!-- Ressources terminées -->
    <?php $nbTerminees = array_sum(array_column($progressionMatieres, 'terminees')); ?>
    <div class="prog-kpi-card">
      
      <div class="prog-kpi-val"><?= (int)$nbTerminees ?></div>
      <div class="prog-kpi-desc">Ressources terminées</div>
      <div class="prog-kpi-hint">toutes matières confondues</div>
    </div>

  </div>

  <!-- ── Section par matière ───────────────────────────── -->
  <div class="prog-section-head">
    <div class="prog-section-head-left">
      <h2 class="prog-h2">Par matière</h2>
      <span class="prog-section-count"><?= count($progressionMatieres) ?> matière<?= count($progressionMatieres) > 1 ? 's' : '' ?></span>
    </div>
    <a href="<?= url('/apprentissage') ?>" class="prog-back-link">
      <i data-lucide="arrow-left" style="width:14px;height:14px"></i>
      Tableau de bord
    </a>
  </div>

  <?php if (empty($progressionMatieres)): ?>

    <div class="empty-state">
      <div class="empty-state__icon">
        <i data-lucide="bar-chart-2" style="width:32px;height:32px"></i>
      </div>
      <h3>Aucune progression enregistrée</h3>
      <p>Consulte des ressources pour voir ta progression ici.</p>
      <a href="<?= url('/apprentissage') ?>" class="btn-appr btn-appr--primary" style="margin-top:20px">
        <i data-lucide="play" style="width:14px;height:14px"></i>
        Commencer à apprendre
      </a>
    </div>

  <?php else: ?>

    <div class="prog-matieres-list">
      <?php foreach ($progressionMatieres as $m):
        $total    = max(1, (int) ($m['total_ressources'] ?? 1));
        $termines = (int) ($m['terminees'] ?? 0);
        $enCours  = (int) ($m['en_cours']  ?? 0);
        $pct      = (int) round((float) ($m['progression_moyenne'] ?? 0));

        if ($pct >= 100) {
            $statusClass = 'prog-mat-status--done';
            $statusIcon  = 'check-circle-2';
            $statusLabel = 'Terminé';
        } elseif ($pct > 0) {
            $statusClass = 'prog-mat-status--progress';
            $statusIcon  = 'loader-2';
            $statusLabel = 'En cours';
        } else {
            $statusClass = 'prog-mat-status--idle';
            $statusIcon  = 'circle';
            $statusLabel = 'Non commencé';
        }
      ?>
        <div class="prog-mat-card">

          <div class="prog-mat-header">

            <div class="prog-mat-left">
              <div class="prog-mat-icon">
                <?php if (!empty($m['icone'])): ?>
                  <span style="font-size:20px;line-height:1"><?= e($m['icone']) ?></span>
                <?php else: ?>
                  <i data-lucide="book" style="width:20px;height:20px"></i>
                <?php endif; ?>
              </div>
              <div class="prog-mat-info">
                <div class="prog-mat-name"><?= e($m['nom']) ?></div>
                <div class="prog-mat-stats">
                  <span class="prog-stat-pill prog-stat-pill--done">
                    <i data-lucide="check" style="width:10px;height:10px"></i>
                    <?= $termines ?> terminé<?= $termines > 1 ? 's' : '' ?>
                  </span>
                  <span class="prog-stat-pill prog-stat-pill--active">
                    <i data-lucide="loader-2" style="width:10px;height:10px"></i>
                    <?= $enCours ?> en cours
                  </span>
                  <span class="prog-stat-pill">
                    <i data-lucide="layers" style="width:10px;height:10px"></i>
                    <?= $total ?> total
                  </span>
                </div>
              </div>
            </div>

            <div class="prog-mat-right">
              <span class="prog-mat-status <?= $statusClass ?>">
                <i data-lucide="<?= $statusIcon ?>" style="width:12px;height:12px"></i>
                <?= $statusLabel ?>
              </span>
              <span class="prog-mat-pct"><?= $pct ?>%</span>
            </div>

          </div>

          <div class="prog-bar-track">
            <div class="prog-bar-fill" style="width:<?= $pct ?>%"></div>
          </div>

          <div class="prog-mat-footer">
            <span class="prog-mat-footer-hint">
              <?php if ($pct >= 100): ?>
                <i data-lucide="award" style="width:13px;height:13px;color:#007A33"></i>
                <span style="color:#007A33">Matière maîtrisée</span>
              <?php elseif ($pct > 0): ?>
                <i data-lucide="trending-up" style="width:13px;height:13px;color:var(--appr-primary)"></i>
                <span style="color:var(--appr-primary)">Continue comme ça !</span>
              <?php else: ?>
                <i data-lucide="info" style="width:13px;height:13px;color:var(--text-muted)"></i>
                <span style="color:var(--text-muted)">Pas encore commencé</span>
              <?php endif; ?>
            </span>
            <a href="<?= url('/apprentissage/ressources?matiere=' . (int)$m['id']) ?>"
               class="btn-appr btn-appr--outline btn-appr--sm">
              Voir les ressources
              <i data-lucide="arrow-right" style="width:13px;height:13px"></i>
            </a>
          </div>

        </div>
      <?php endforeach; ?>
    </div>

  <?php endif; ?>

</div>

<style>
/* ═══════════════════════════════════════════════════════
   PROGRESSION PAGE — langage Claude, tokens Connect'Academia
   Aplats + hairlines ; la carte sombre porte le moment produit.
   ═══════════════════════════════════════════════════════ */

/* ── Hero ─────────────────────────────────────────────── */
.prog-hero {
  margin-bottom: 48px;
  padding-bottom: 40px;
  border-bottom: 1px solid var(--appr-hairline, var(--border-subtle));
}
.prog-eyebrow {
  font-family: var(--font-sans);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--purple-neon);
  margin-bottom: 12px;
}
.prog-h1 {
  font-family: var(--font-sans);
  font-size: clamp(32px, 5vw, 46px);
  font-weight: var(--fw-regular);
  color: var(--text-primary);
  line-height: 1.12;
  letter-spacing: -0.03em;
  margin-bottom: 12px;
}
.prog-subtitle {
  font-family: var(--font-sans);
  font-size: 15px;
  color: var(--text-secondary);
  margin: 0;
  line-height: 1.55;
}

/* ── KPI Bento ───────────────────────────────────────── */
.prog-bento {
  display: grid;
  grid-template-columns: 1.1fr repeat(3, 1fr);
  gap: 16px;
  margin-bottom: 96px;
  align-items: stretch;
}

/* Carte promue — aplat charbon : le chrome sombre porte le
   moment produit, l'anneau violet porte la donnée */
.prog-kpi-promoted {
  background: var(--appr-dark-card, #1A1A1A);
  border-radius: var(--radius-lg);
  padding: 28px 20px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 14px;
  box-shadow: none;
  position: relative;
  overflow: hidden;
  min-height: 220px;
}
[data-theme="dark"] .prog-kpi-promoted {
  border: 1px solid rgba(255, 255, 255, 0.10);
}
.prog-kpi-promoted-glow { display: none; }

.prog-kpi-eyebrow {
  font-family: var(--font-sans);
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: #9D6EFB;
}

/* Circular progress override pour fond sombre */
.prog-kpi-promoted .circular-progress {
  background: conic-gradient(var(--purple-neon) var(--pct, 0deg), rgba(255,255,255,0.14) 0deg) !important;
  margin: 0;
  width: 110px;
  height: 110px;
  box-shadow: none;
}
.prog-kpi-promoted .circular-progress::before {
  background: var(--appr-dark-card, #1A1A1A) !important;
  inset: 12px;
}
.prog-kpi-promoted .circular-progress__value {
  color: #fff !important;
  font-size: 26px;
  font-weight: var(--fw-light);
}

.prog-kpi-label-white {
  font-family: var(--font-sans);
  font-size: 13px;
  font-weight: 500;
  color: rgba(255,255,255,0.80);
  text-align: center;
  margin: 0;
}

/* Cartes KPI standard — aplat blanc hairline, sans ombre */
.prog-kpi-card {
  background: var(--bg-main);
  border-radius: var(--radius-md);
  padding: 22px 20px;
  border: 1px solid var(--appr-hairline, var(--border-subtle));
  box-shadow: none;
  display: flex;
  flex-direction: column;
  gap: 10px;
  justify-content: center;
  position: relative;
  overflow: hidden;
}

/* Icon cap */
.prog-kpi-cap {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: #F3EFFF;
  color: #8B52FA;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.prog-kpi-cap--blue  { background: #EFF6FF; color: #3B82F6; }
.prog-kpi-cap--green { background: #ECFDF5; color: #059669; }
.prog-kpi-cap--amber { background: #FFF7ED; color: #D97706; }

.prog-kpi-val {
  font-family: var(--font-sans);
  font-size: 34px;
  font-weight: var(--fw-light);
  color: var(--text-primary);
  line-height: 1.1;
  letter-spacing: -0.02em;
}
.prog-kpi-total {
  font-size: 18px;
  font-weight: var(--fw-regular);
  color: var(--text-muted);
}
.prog-kpi-desc {
  font-family: var(--font-sans);
  font-size: 13px;
  font-weight: var(--fw-semibold);
  color: var(--text-secondary);
  line-height: 1.3;
}
.prog-kpi-hint {
  font-family: var(--font-sans);
  font-size: 11px;
  color: var(--text-muted);
  line-height: 1.4;
}

/* ── Section header ──────────────────────────────────── */
.prog-section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}
.prog-section-head-left {
  display: flex;
  align-items: center;
  gap: 10px;
}
.prog-h2 {
  font-family: var(--font-sans);
  font-size: var(--fs-h2);
  font-weight: var(--fw-semibold);
  color: var(--text-primary);
  margin: 0;
  letter-spacing: -0.01em;
}
.prog-section-count {
  font-family: var(--font-sans);
  font-size: 12px;
  font-weight: var(--fw-semibold);
  color: var(--purple-press);
  background: var(--purple-tint);
  padding: 3px 10px;
  border-radius: var(--radius-pill);
}
.prog-back-link {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-family: var(--font-sans);
  font-size: 13px;
  font-weight: var(--fw-medium);
  color: var(--text-muted);
  text-decoration: none;
  transition: color 0.2s;
}
.prog-back-link:hover {
  color: var(--purple-neon);
  text-decoration: none;
}

/* ── Matières list ───────────────────────────────────── */
.prog-matieres-list {
  display: grid;
  gap: 14px;
}

.prog-mat-card {
  background: var(--bg-main);
  border-radius: var(--radius-md);
  padding: 22px 24px;
  border: 1px solid var(--appr-hairline, var(--border-subtle));
  box-shadow: none;
  display: flex;
  flex-direction: column;
  gap: 16px;
  transition: box-shadow 0.15s ease, border-color 0.15s ease;
}
.prog-mat-card:hover {
  border-color: var(--border-strong);
  box-shadow: var(--appr-shadow-hover, 0 1px 3px rgba(26,26,26,0.08));
}

.prog-mat-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}

.prog-mat-left {
  display: flex;
  align-items: center;
  gap: 14px;
  min-width: 0;
  flex: 1;
}

.prog-mat-icon {
  width: 44px;
  height: 44px;
  flex-shrink: 0;
  background: var(--purple-tint);
  border-radius: var(--radius-sm);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--purple-neon);
}

.prog-mat-info { min-width: 0; flex: 1; }

.prog-mat-name {
  font-family: var(--font-sans);
  font-size: 15px;
  font-weight: var(--fw-semibold);
  color: var(--text-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  line-height: 1.3;
  letter-spacing: -0.01em;
}

.prog-mat-stats {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  margin-top: 6px;
}

.prog-stat-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-family: var(--font-sans);
  font-size: 11px;
  font-weight: var(--fw-medium);
  color: var(--text-muted);
  background: var(--bg-surface);
  padding: 3px 8px;
  border-radius: var(--radius-pill);
  border: 1px solid var(--border-subtle);
}
.prog-stat-pill--done   { color: #007A33; background: rgba(0,200,83,0.10); border-color: rgba(0,200,83,0.20); }
.prog-stat-pill--active { color: #8A5C00; background: rgba(255,171,0,0.12); border-color: rgba(255,171,0,0.25); }

.prog-mat-right {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-shrink: 0;
}

.prog-mat-status {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-family: var(--font-sans);
  font-size: 11px;
  font-weight: 600;
  padding: 5px 10px;
  border-radius: 20px;
}
.prog-mat-status--done     { background: rgba(0,200,83,0.12); color: #007A33; }
.prog-mat-status--progress { background: rgba(255,171,0,0.16); color: #8A5C00; }
.prog-mat-status--idle     { background: var(--bg-surface); color: var(--text-muted); border: 1px solid var(--border-subtle); }

.prog-mat-pct {
  font-family: var(--font-sans);
  font-size: 24px;
  font-weight: var(--fw-light);
  color: var(--purple-neon);
  letter-spacing: -0.02em;
  min-width: 56px;
  text-align: right;
  line-height: 1;
}

/* Barre de progression */
.prog-bar-track {
  background: rgba(var(--purple-neon-rgb), 0.12);
  border-radius: var(--radius-pill);
  height: 8px;
  overflow: hidden;
}
.prog-bar-fill {
  background: var(--purple-neon);
  height: 100%;
  border-radius: var(--radius-pill);
  transition: width 0.6s cubic-bezier(0.2,0.8,0.2,1);
}

/* Footer matière */
.prog-mat-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding-top: 14px;
  border-top: 1px solid var(--border-subtle);
  flex-wrap: wrap;
}
.prog-mat-footer-hint {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-family: var(--font-sans);
  font-size: 12px;
  font-weight: 500;
}

/* ── Responsive ─────────────────────────────────────── */
@media (max-width: 900px) {
  .prog-bento {
    grid-template-columns: 1fr 1fr;
  }
  .prog-kpi-promoted {
    grid-column: span 2;
    min-height: 180px;
    flex-direction: row;
    justify-content: center;
    gap: 28px;
    padding: 24px 32px;
  }
  .prog-kpi-promoted .circular-progress { width: 100px; height: 100px; }
  .prog-kpi-promoted .circular-progress__value { font-size: 22px; }
}

@media (max-width: 600px) {
  .prog-bento {
    grid-template-columns: 1fr 1fr;
    gap: 10px;
  }
  .prog-kpi-promoted {
    grid-column: span 2;
    flex-direction: column;
    padding: 20px;
    gap: 14px;
    min-height: 160px;
  }
  .prog-mat-card { padding: 18px 16px; }
  .prog-mat-right { width: 100%; justify-content: space-between; }
  .prog-mat-pct { font-size: 20px; }
  .prog-kpi-card { padding: 16px 14px; }
  .prog-kpi-val { font-size: 22px; }
}

@media (max-width: 400px) {
  .prog-bento { grid-template-columns: 1fr; }
  .prog-kpi-promoted { grid-column: span 1; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const el = document.querySelector('.circular-progress');
    if (el) {
        const pct = <?= $progressionGlobale ?>;
        el.style.setProperty('--pct', (pct * 3.6) + 'deg');
    }
});
</script>
