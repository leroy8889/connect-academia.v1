<?php
$userName    = e(\Core\Session::get('user_name', 'utilisateur'));
$hour        = (int) date('H');
$greeting    = $hour < 12 ? 'Bonjour' : ($hour < 18 ? 'Bon après-midi' : 'Bonsoir');
$serie       = $user['serie_id'] ?? null;
$serieLabel  = '';
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
        <h1 class="hub__hero-name"><?= $userName ?></h1>

        <?php if ($serieLabel): ?>
        <span class="hub__serie-badge">Série <?= $serieLabel ?></span>
        <?php endif; ?>

        <?php if ($acces): ?>
          <?php if ($resteGratuit > 0): ?>
          <p class="hub__hero-sub">
            <i data-lucide="clock"></i>
            Période gratuite — il te reste <?= ceil($resteGratuit / 3600) ?>h d'accès
          </p>
          <?php else: ?>
          <p class="hub__hero-sub hub__hero-sub--premium">
            <i data-lucide="star"></i>
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
          <i data-lucide="alert-triangle"></i>
          Période gratuite expirée —
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

  <!-- ── STATS ─────────────────────────────────────────────────────────────── -->
  <section class="hub__stats">
    <p class="hub__section-eyebrow">Ton activité</p>
    <div class="hub__stats-grid">

      <div class="hub__stat-card">
        <div class="hub__stat-icon hub__stat-icon--purple">
          <i data-lucide="book-open"></i>
        </div>
        <div class="hub__stat-info">
          <span class="hub__stat-num" id="stat-cours"><?= (int) ($stats['cours_consultes'] ?? 0) ?></span>
          <span class="hub__stat-label">Cours consultés</span>
        </div>
      </div>

      <div class="hub__stat-card">
        <div class="hub__stat-icon hub__stat-icon--green">
          <i data-lucide="check-square"></i>
        </div>
        <div class="hub__stat-info">
          <span class="hub__stat-num" id="stat-termines"><?= (int) ($stats['cours_termines'] ?? 0) ?></span>
          <span class="hub__stat-label">Cours terminés</span>
        </div>
      </div>

      <div class="hub__stat-card">
        <div class="hub__stat-icon hub__stat-icon--blue">
          <i data-lucide="message-square"></i>
        </div>
        <div class="hub__stat-info">
          <span class="hub__stat-num" id="stat-posts"><?= (int) ($stats['posts'] ?? 0) ?></span>
          <span class="hub__stat-label">Publications</span>
        </div>
      </div>

      <div class="hub__stat-card">
        <div class="hub__stat-icon hub__stat-icon--orange">
          <i data-lucide="users"></i>
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
    <p class="hub__section-eyebrow">En cours</p>
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
    <p class="hub__section-eyebrow">Plateforme</p>
    <h2 class="hub__section-title">Mes modules</h2>
    <div class="hub__modules-grid">

      <!-- Apprentissage -->
      <a href="<?= url('/apprentissage') ?>"
         class="hub__module-card hub__module-card--apprentissage <?= !$acces ? 'hub__module-card--locked' : '' ?>">
        <div class="hub__module-card__header">
          <div class="hub__module-icon">
            <i data-lucide="book-open"></i>
          </div>
          <?php if (!$acces): ?>
          <span class="hub__module-lock">
            <i data-lucide="lock"></i>
          </span>
          <?php else: ?>
          <span class="hub__module-badge hub__module-badge--active">Accès</span>
          <?php endif; ?>
        </div>
        <h3 class="hub__module-title">Apprentissage</h3>
        <p class="hub__module-desc">Cours, TD et épreuves par série et matière. Assistant IA intégré.</p>
        <div class="hub__module-features">
          <span><i data-lucide="file-text"></i> Cours PDF</span>
          <span><i data-lucide="pen-tool"></i> TD et exercices</span>
          <span><i data-lucide="cpu"></i> IA Gemini</span>
          <span><i data-lucide="bar-chart-2"></i> Progression</span>
        </div>
        <div class="hub__module-cta">
          <?= $acces ? 'Accéder' : "S'abonner" ?>
          <i data-lucide="arrow-right"></i>
        </div>
      </a>

      <!-- Communauté -->
      <a href="<?= url('/communaute') ?>"
         class="hub__module-card hub__module-card--communaute <?= !$acces ? 'hub__module-card--locked' : '' ?>">
        <div class="hub__module-card__header">
          <div class="hub__module-icon">
            <i data-lucide="users"></i>
          </div>
          <?php if (!$acces): ?>
          <span class="hub__module-lock">
            <i data-lucide="lock"></i>
          </span>
          <?php else: ?>
          <span class="hub__module-badge hub__module-badge--active">Accès</span>
          <?php endif; ?>
        </div>
        <h3 class="hub__module-title">Communauté</h3>
        <p class="hub__module-desc">Échangez avec d'autres élèves et étudiants. Posez vos questions, partagez vos astuces.</p>
        <div class="hub__module-features">
          <span><i data-lucide="message-circle"></i> Fil d'actualité</span>
          <span><i data-lucide="help-circle"></i> Questions</span>
          <span><i data-lucide="zap"></i> Chat en direct</span>
          <span><i data-lucide="bell"></i> Notifications</span>
        </div>
        <div class="hub__module-cta">
          <?= $acces ? 'Accéder' : "S'abonner" ?>
          <i data-lucide="arrow-right"></i>
        </div>
      </a>

      <!-- Orientation -->
      <a href="<?= BASE_URL ?>/public/orientation/orientation.html" class="hub__module-card hub__module-card--orientation">
        <div class="hub__module-card__header">
          <div class="hub__module-icon">
            <i data-lucide="compass"></i>
          </div>
          <span class="hub__module-badge hub__module-badge--free">Gratuit</span>
        </div>
        <h3 class="hub__module-title">Orientation</h3>
        <p class="hub__module-desc">Découvrez les universités et filières au Gabon. Guide d'orientation pour votre avenir.</p>
        <div class="hub__module-features">
          <span><i data-lucide="building"></i> Universités</span>
          <span><i data-lucide="graduation-cap"></i> Instituts privés</span>
          <span><i data-lucide="map-pin"></i> Localisations</span>
          <span><i data-lucide="list"></i> Filières</span>
        </div>
        <div class="hub__module-cta">
          Explorer
          <i data-lucide="arrow-right"></i>
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
