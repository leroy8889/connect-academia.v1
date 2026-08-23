<?php
$favoris = $favoris ?? [];

function typeIconFav(string $type): string {
    return match($type) {
        'cours'            => 'book-open',
        'td'               => 'file-text',
        'ancienne_epreuve' => 'clipboard-list',
        'corrige'          => 'check-square',
        default            => 'file',
    };
}

$typeLabels = [
    'cours'            => 'Cours',
    'td'               => 'TD',
    'ancienne_epreuve' => 'Ancienne épreuve',
    'corrige'          => 'Méthodologie',
];
?>

<div class="appr-page favoris-page">

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
    <a href="<?= url('/apprentissage/progression') ?>" class="appr-subnav__link">
      <i data-lucide="bar-chart-2" style="width:15px;height:15px"></i>
      Progression
    </a>
    <a href="<?= url('/apprentissage/favoris') ?>" class="appr-subnav__link active">
      <i data-lucide="star" style="width:15px;height:15px"></i>
      Favoris
    </a>
  </nav>

  <!-- ── Hero ──────────────────────────────────────────── -->
  <header class="fav-hero">
    <p class="fav-eyebrow">Module d'apprentissage</p>
    <div class="fav-hero-row">
      <h1 class="fav-h1">Mes favoris</h1>
      <?php if (!empty($favoris)): ?>
        <span class="fav-count-chip"><?= count($favoris) ?> ressource<?= count($favoris) > 1 ? 's' : '' ?></span>
      <?php endif; ?>
    </div>
    <p class="fav-subtitle">Retrouve ici toutes tes ressources sauvegardées.</p>
  </header>

  <!-- ── Grille favoris ────────────────────────────────── -->
  <?php if (empty($favoris)): ?>

    <div class="empty-state">
      <div class="empty-state__icon">
        <i data-lucide="star" style="width:32px;height:32px"></i>
      </div>
      <h3>Aucun favori pour l'instant</h3>
      <p>Ajoute des ressources à tes favoris depuis la page des cours pour les retrouver ici.</p>
      <a href="<?= url('/apprentissage/matieres') ?>" class="btn-appr btn-appr--primary" style="margin-top:20px">
        <i data-lucide="compass" style="width:14px;height:14px"></i>
        Explorer les matières
      </a>
    </div>

  <?php else: ?>

    <div class="grid-cards fav-grid">
      <?php foreach ($favoris as $r):
        $pct      = (int) ($r['pourcentage'] ?? 0);
        $statut   = $r['statut'] ?? null;
        $icon     = typeIconFav($r['type'] ?? '');
        $typeCls  = e($r['type'] ?? 'cours');
        $typeLabel = $typeLabels[$r['type'] ?? ''] ?? ucfirst(str_replace('_', ' ', $r['type'] ?? ''));
      ?>
        <div class="resource-card fav-card">

          <a href="<?= url('/apprentissage/viewer/' . (int)$r['id']) ?>" class="fav-card-link">

            <!-- En-tête: icône + titre -->
            <div class="fav-card-head">
              <div class="fav-card-icon fav-card-icon--<?= $typeCls ?>">
                <i data-lucide="<?= $icon ?>" style="width:20px;height:20px"></i>
              </div>
              <div class="fav-card-title-block">
                <div class="fav-card-title"><?= e($r['titre']) ?></div>
                <?php if (!empty($r['matiere'])): ?>
                  <div class="fav-card-matiere">
                    <i data-lucide="tag" style="width:10px;height:10px"></i>
                    <?= e($r['matiere']) ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <!-- Badges -->
            <div class="fav-card-badges">
              
              <?php if (!empty($r['serie'])): ?>
                <span class="fav-badge fav-badge--serie">Tle <?= e($r['serie']) ?></span>
              <?php endif; ?>
              <?php if ($statut === 'termine'): ?>
                <span class="fav-badge fav-badge--done">
                  
                  Terminé
                </span>
              <?php elseif ($statut === 'en_cours'): ?>
                <span class="fav-badge fav-badge--progress">En cours</span>
              <?php endif; ?>
            </div>

            <!-- Barre de progression -->
            <?php if ($pct > 0): ?>
              <div class="fav-progress-row">
                <div class="fav-progress-track">
                  <div class="fav-progress-fill" style="width:<?= $pct ?>%"></div>
                </div>
                <span class="fav-progress-pct"><?= $pct ?>%</span>
              </div>
            <?php endif; ?>

            <!-- Footer -->
            <div class="fav-card-footer">
              <span class="fav-card-status">
                <?php if ($pct >= 100): ?>
                  
                  <span style="color:#007A33;font-weight:600">Terminé</span>
                <?php elseif ($pct > 0): ?>
                  <i data-lucide="play-circle" style="width:13px;height:13px;color:var(--purple-neon)"></i>
                  <span style="color:var(--purple-neon);font-weight:600">En cours</span>
                <?php else: ?>
                  <i data-lucide="circle" style="width:13px;height:13px;color:var(--text-muted)"></i>
                  <span style="color:var(--text-muted)">Non commencé</span>
                <?php endif; ?>
              </span>
              <span class="fav-card-cta">
                <?= ($pct > 0 && $pct < 100) ? 'Continuer' : 'Consulter' ?>
                <i data-lucide="arrow-right" style="width:12px;height:12px"></i>
              </span>
            </div>

          </a>
        </div>
      <?php endforeach; ?>
    </div>

  <?php endif; ?>

</div>

<style>
/* ═══════════════════════════════════════════════════════
   FAVORIS PAGE — langage Claude, tokens Connect'Academia
   ═══════════════════════════════════════════════════════ */

/* ── Hero ─────────────────────────────────────────────── */
.fav-hero {
  margin-bottom: 48px;
  padding-bottom: 40px;
  border-bottom: 1px solid var(--appr-hairline, var(--border-subtle));
}

.fav-eyebrow {
  font-family: var(--font-sans);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--purple-neon);
  margin-bottom: 12px;
}

.fav-hero-row {
  display: flex;
  align-items: baseline;
  gap: 14px;
  flex-wrap: wrap;
  margin-bottom: 12px;
}

.fav-h1 {
  font-family: var(--font-sans);
  font-size: clamp(32px, 5vw, 46px);
  font-weight: var(--fw-regular);
  color: var(--text-primary);
  line-height: 1.12;
  letter-spacing: -0.03em;
  margin: 0;
}

.fav-count-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-family: var(--font-sans);
  font-size: 13px;
  font-weight: var(--fw-semibold);
  color: var(--purple-press);
  background: var(--purple-tint);
  padding: 6px 14px;
  border-radius: var(--radius-pill);
}

.fav-subtitle {
  font-family: var(--font-sans);
  font-size: 15px;
  color: var(--text-secondary);
  margin: 0;
  line-height: 1.55;
}

/* ── Grille ──────────────────────────────────────────── */
.fav-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 280px), 1fr));
  gap: 16px;
}

/* ── Card — aplat blanc hairline, ombre à peine au survol ── */
.fav-card {
  background: var(--bg-main);
  border-radius: var(--radius-md);
  padding: 22px;
  border: 1px solid var(--appr-hairline, var(--border-subtle));
  box-shadow: none;
  display: flex;
  flex-direction: column;
  gap: 0;
  position: relative;
  transition: box-shadow 0.15s ease, border-color 0.15s ease;
  cursor: pointer;
}
.fav-card:hover {
  border-color: var(--border-strong);
  box-shadow: var(--appr-shadow-hover, 0 1px 3px rgba(26,26,26,0.08));
}
/* Lien principal */
.fav-card-link {
  text-decoration: none;
  display: flex;
  flex-direction: column;
  gap: 14px;
  flex: 1;
}
.fav-card-link:hover { text-decoration: none; }

/* En-tête: icône + titre */
.fav-card-head {
  display: flex;
  align-items: flex-start;
  gap: 14px;
}

.fav-card-icon {
  width: 44px;
  height: 44px;
  border-radius: var(--radius-sm);
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}
.fav-card-icon--cours,
.fav-card-icon--td,
.fav-card-icon--ancienne_epreuve,
.fav-card-icon--corrige { background: var(--purple-tint); color: var(--purple-neon); }

.fav-card-title-block {
  min-width: 0;
  flex: 1;
}
.fav-card-title {
  font-family: var(--font-sans);
  font-size: 14px;
  font-weight: var(--fw-semibold);
  color: var(--text-primary);
  line-height: 1.45;
  letter-spacing: -0.01em;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.fav-card-matiere {
  display: flex;
  align-items: center;
  gap: 4px;
  font-family: var(--font-sans);
  font-size: 11px;
  color: var(--text-muted);
  margin-top: 4px;
}

/* Badges */
.fav-card-badges {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
}

.fav-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-family: var(--font-sans);
  font-size: 11px;
  font-weight: 600;
  padding: 4px 10px;
  border-radius: 999px;
  letter-spacing: 0.01em;
}
.fav-badge--cours            { background: var(--purple-tint); color: var(--purple-press); }
.fav-badge--td               { background: var(--purple-tint); color: var(--purple-press); }
.fav-badge--ancienne_epreuve { background: rgba(255,171,0,0.14); color: #8A5C00; }
.fav-badge--corrige          { background: rgba(0,200,83,0.12); color: #007A33; }
.fav-badge--serie            { background: var(--purple-tint); color: var(--purple-press); }
.fav-badge--done             { background: rgba(0,200,83,0.12); color: #007A33; }
.fav-badge--progress         { background: rgba(255,171,0,0.16); color: #8A5C00; }

/* Barre de progression */
.fav-progress-row {
  display: flex;
  align-items: center;
  gap: 10px;
}
.fav-progress-track {
  flex: 1;
  height: 6px;
  background: rgba(var(--purple-neon-rgb), 0.12);
  border-radius: var(--radius-pill);
  overflow: hidden;
}
.fav-progress-fill {
  height: 100%;
  background: var(--purple-neon);
  border-radius: var(--radius-pill);
  transition: width 0.5s cubic-bezier(0.2,0.8,0.2,1);
}
.fav-progress-pct {
  font-family: var(--font-sans);
  font-size: 12px;
  font-weight: var(--fw-bold);
  color: var(--purple-neon);
  min-width: 34px;
  text-align: right;
}

/* Footer */
.fav-card-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding-top: 14px;
  border-top: 1px solid var(--border-subtle);
  margin-top: auto;
}
.fav-card-status {
  display: flex;
  align-items: center;
  gap: 5px;
  font-family: var(--font-sans);
  font-size: 12px;
}
.fav-card-cta {
  display: flex;
  align-items: center;
  gap: 4px;
  font-family: var(--font-sans);
  font-size: 12px;
  font-weight: var(--fw-semibold);
  color: var(--purple-neon);
  white-space: nowrap;
  letter-spacing: 0.01em;
}

/* ── Responsive ─────────────────────────────────────── */
@media (max-width: 640px) {
  .fav-grid { grid-template-columns: 1fr; }
  .fav-card { padding: 18px 16px; }
}
</style>
