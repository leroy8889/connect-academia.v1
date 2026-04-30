<?php
$userName = e(\Core\Session::get('user_name', 'utilisateur'));
$hour     = (int) date('H');
$greeting = $hour < 12 ? 'Bonjour' : ($hour < 18 ? 'Bon après-midi' : 'Bonsoir');
$serie    = $user['serie_id'] ?? null;
$serieLabel = '';
if ($serie) {
    $serieNames = [1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D', 5 => 'F3', 6 => 'G'];
    $serieLabel = $serieNames[$serie] ?? '';
}
$progGlobale = (int) ($stats['progression_globale'] ?? 0);
?>

<div class="hub">

  <!-- ── HERO ──────────────────────────────────────────────────────────────── -->
  <section class="hub__hero">
    <div class="hub__hero-content">
      <div class="hub__hero-text">
        <p class="hub__hero-greeting"><?= $greeting ?>,</p>
        <h1 class="hub__hero-name"><?= $userName ?> 👋</h1>

        <?php if ($serieLabel): ?>
        <span class="hub__serie-badge">Série <?= $serieLabel ?></span>
        <?php endif; ?>

        <?php if ($acces): ?>
          <?php if ($resteGratuit > 0): ?>
          <p class="hub__hero-sub">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
            Période gratuite — il vous reste <?= ceil($resteGratuit / 3600) ?>h d'accès
          </p>
          <?php else: ?>
          <p class="hub__hero-sub hub__hero-sub--premium">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
            </svg>
            Abonnement actif — accès complet
          </p>
          <?php endif; ?>

          <?php if ($progGlobale > 0): ?>
          <div class="hub__hero-progress">
            <div class="hub__hero-progress-bar">
              <div class="hub__hero-progress-fill" style="width:<?= $progGlobale ?>%"></div>
            </div>
            <span class="hub__hero-progress-label"><?= $progGlobale ?>% progression globale</span>
          </div>
          <?php endif; ?>

        <?php else: ?>
        <p class="hub__hero-sub hub__hero-sub--warning">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
          </svg>
          Votre période gratuite est expirée —
          <a href="<?= url('/abonnement/choisir') ?>">S'abonner pour 2 000 XAF/mois</a>
        </p>
        <?php endif; ?>
      </div>

      <!-- Avatar -->
      <div class="hub__hero-avatar">
        <img src="<?= e(\Core\Session::get('user_photo') ? url(\Core\Session::get('user_photo')) : asset('images/default-avatar.svg')) ?>"
             alt="<?= $userName ?>"
             onerror="this.onerror=null;this.src=<?= htmlspecialchars(json_encode(asset('images/default-avatar.svg')), ENT_QUOTES) ?>">
      </div>
    </div>
  </section>

  <!-- ── STATS ──────────────────────────────────────────────────────────────── -->
  <section class="hub__stats">
    <div class="hub__stats-grid">
      <div class="hub__stat-card">
        <div class="hub__stat-icon hub__stat-icon--purple">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
            <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
          </svg>
        </div>
        <div class="hub__stat-info">
          <span class="hub__stat-num" id="stat-cours"><?= (int) ($stats['cours_consultes'] ?? 0) ?></span>
          <span class="hub__stat-label">Cours consultés</span>
        </div>
      </div>

      <div class="hub__stat-card">
        <div class="hub__stat-icon hub__stat-icon--green">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="9 11 12 14 22 4"/>
            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
          </svg>
        </div>
        <div class="hub__stat-info">
          <span class="hub__stat-num" id="stat-termines"><?= (int) ($stats['cours_termines'] ?? 0) ?></span>
          <span class="hub__stat-label">Cours terminés</span>
        </div>
      </div>

      <div class="hub__stat-card">
        <div class="hub__stat-icon hub__stat-icon--blue">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
          </svg>
        </div>
        <div class="hub__stat-info">
          <span class="hub__stat-num" id="stat-posts"><?= (int) ($stats['posts'] ?? 0) ?></span>
          <span class="hub__stat-label">Publications</span>
        </div>
      </div>

      <div class="hub__stat-card">
        <div class="hub__stat-icon hub__stat-icon--orange">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
          </svg>
        </div>
        <div class="hub__stat-info">
          <span class="hub__stat-num" id="stat-abonnes"><?= (int) ($stats['abonnes'] ?? 0) ?></span>
          <span class="hub__stat-label">Abonnés</span>
        </div>
      </div>
    </div>
  </section>

  <!-- ── COURS EN COURS ─────────────────────────────────────────────────────── -->
  <?php if (!empty($enCours)): ?>
  <section class="hub__encours">
    <h2 class="hub__section-title">Continuer l'apprentissage</h2>
    <div class="hub__encours-list">
      <?php foreach ($enCours as $cours): ?>
      <a href="<?= url('/apprentissage/viewer/' . (int) $cours['id']) ?>" class="hub__encours-item">
        <div class="hub__encours-meta">
          <span class="hub__encours-matiere"><?= e($cours['matiere']) ?></span>
          <span class="hub__encours-type"><?= e(ucfirst($cours['type'] ?? 'cours')) ?></span>
        </div>
        <p class="hub__encours-titre"><?= e($cours['titre']) ?></p>
        <div class="hub__encours-progress">
          <div class="hub__encours-bar">
            <div class="hub__encours-fill" style="width:<?= (int) $cours['pourcentage'] ?>%"></div>
          </div>
          <span class="hub__encours-pct"><?= (int) $cours['pourcentage'] ?>%</span>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- ── MODULES ────────────────────────────────────────────────────────────── -->
  <section class="hub__modules">
    <h2 class="hub__section-title">Mes modules</h2>
    <div class="hub__modules-grid">

      <!-- Apprentissage -->
      <a href="<?= url('/apprentissage') ?>"
         class="hub__module-card hub__module-card--apprentissage <?= !$acces ? 'hub__module-card--locked' : '' ?>">
        <div class="hub__module-card__header">
          <div class="hub__module-icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
              <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
            </svg>
          </div>
          <?php if (!$acces): ?>
          <span class="hub__module-lock">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
          </span>
          <?php else: ?>
          <span class="hub__module-badge hub__module-badge--active">Accès</span>
          <?php endif; ?>
        </div>
        <h3 class="hub__module-title">Apprentissage</h3>
        <p class="hub__module-desc">Cours, TD et épreuves par série et matière. Assistant IA intégré.</p>
        <div class="hub__module-features">
          <span>📚 Cours PDF</span>
          <span>📝 TD et exercices</span>
          <span>🤖 IA Gemini</span>
          <span>📊 Progression</span>
        </div>
        <div class="hub__module-cta">
          <?= $acces ? 'Accéder' : "S'abonner" ?>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="5" y1="12" x2="19" y2="12"/>
            <polyline points="12 5 19 12 12 19"/>
          </svg>
        </div>
      </a>

      <!-- Communauté -->
      <a href="<?= url('/communaute') ?>"
         class="hub__module-card hub__module-card--communaute <?= !$acces ? 'hub__module-card--locked' : '' ?>">
        <div class="hub__module-card__header">
          <div class="hub__module-icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
              <circle cx="9" cy="7" r="4"/>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
              <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
          </div>
          <?php if (!$acces): ?>
          <span class="hub__module-lock">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
          </span>
          <?php else: ?>
          <span class="hub__module-badge hub__module-badge--active">Accès</span>
          <?php endif; ?>
        </div>
        <h3 class="hub__module-title">Communauté</h3>
        <p class="hub__module-desc">Échangez avec d'autres élèves et étudiants. Posez vos questions, partagez vos astuces.</p>
        <div class="hub__module-features">
          <span>💬 Fil d'actualité</span>
          <span>❓ Questions/Réponses</span>
          <span>💭 Chat en direct</span>
          <span>🔔 Notifications</span>
        </div>
        <div class="hub__module-cta">
          <?= $acces ? 'Accéder' : "S'abonner" ?>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="5" y1="12" x2="19" y2="12"/>
            <polyline points="12 5 19 12 12 19"/>
          </svg>
        </div>
      </a>

      <!-- Orientation — module gratuit, fichier statique -->
      <a href="<?= BASE_URL ?>/public/orientation/orientation.html" class="hub__module-card hub__module-card--orientation">
        <div class="hub__module-card__header">
          <div class="hub__module-icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <circle cx="12" cy="12" r="10"/>
              <line x1="2" y1="12" x2="22" y2="12"/>
              <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
            </svg>
          </div>
          <span class="hub__module-badge hub__module-badge--free">Gratuit</span>
        </div>
        <h3 class="hub__module-title">Orientation</h3>
        <p class="hub__module-desc">Découvrez les universités et filières au Gabon. Guide d'orientation pour votre avenir.</p>
        <div class="hub__module-features">
          <span>🏛️ Universités publiques</span>
          <span>🎓 Instituts privés</span>
          <span>📍 Localisations</span>
          <span>📋 Filières</span>
        </div>
        <div class="hub__module-cta">
          Explorer
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="5" y1="12" x2="19" y2="12"/>
            <polyline points="12 5 19 12 12 19"/>
          </svg>
        </div>
      </a>

    </div>
  </section>

  <!-- ── BANNER ABONNEMENT (si pas d'accès) ────────────────────────────────── -->
  <?php if (!$acces): ?>
  <section class="hub__pricing">
    <div class="hub__pricing-card">
      <div class="hub__pricing-badge">Déverrouillez l'accès complet</div>
      <h2 class="hub__pricing-title">Choisissez votre plan</h2>
      <p class="hub__pricing-desc">Accès illimité à tous les cours, à la communauté et à l'assistant IA</p>
      <div class="hub__pricing-plans">
        <div class="hub__plan">
          <div class="hub__plan-header">
            <span class="hub__plan-name">Mensuel</span>
          </div>
          <div class="hub__plan-price">
            <span class="hub__plan-amount">2 000</span>
            <span class="hub__plan-currency">XAF/mois</span>
          </div>
          <a href="<?= url('/abonnement/choisir') ?>" class="btn btn--outline btn--full">Choisir</a>
        </div>
        <div class="hub__plan hub__plan--featured">
          <div class="hub__plan-header">
            <span class="hub__plan-name">Annuel</span>
            <span class="hub__plan-save">-37%</span>
          </div>
          <div class="hub__plan-price">
            <span class="hub__plan-amount">15 000</span>
            <span class="hub__plan-currency">XAF/an</span>
          </div>
          <a href="<?= url('/abonnement/choisir') ?>" class="btn btn--primary btn--full">Choisir</a>
        </div>
      </div>
    </div>
  </section>
  <?php endif; ?>

</div>
