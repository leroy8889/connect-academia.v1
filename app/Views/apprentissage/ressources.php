<?php
$matiere    = $matiere    ?? [];
$ressources = $ressources ?? [];
$typeFilter = $type_filter ?? 'tous';

$types = [
    'tous'             => 'Tous',
    'cours'            => 'Cours',
    'td'               => 'TD',
    'ancienne_epreuve' => 'Anciennes épreuves',
    'corrige'          => 'Méthodologies',
];

function typeIconRes(string $type): string {
    return match($type) {
        'cours'            => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>',
        'td'               => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2-2H12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
        'ancienne_epreuve' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 9h6M9 12h6M9 15h4"/></svg>',
        'corrige'          => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
        default            => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>',
    };
}

$typeLabel = fn(string $t) => $types[$t] ?? ucfirst(str_replace('_', ' ', $t));
?>

<div class="appr-page">

  <nav class="appr-subnav">
    <a href="<?= url('/apprentissage') ?>" class="appr-subnav__link">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
      <span>Tableau de bord</span>
    </a>
    <a href="<?= url('/apprentissage/matieres') ?>" class="appr-subnav__link active">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
      <span>Mes matières</span>
    </a>
    <a href="<?= url('/apprentissage/progression') ?>" class="appr-subnav__link">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      <span>Progression</span>
    </a>
    <a href="<?= url('/apprentissage/favoris') ?>" class="appr-subnav__link">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
      <span>Favoris</span>
    </a>
  </nav>

  <div class="appr-breadcrumb">
    <a href="<?= url('/apprentissage/matieres') ?>" class="appr-breadcrumb__link">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
      Mes matières
    </a>
    <svg class="appr-breadcrumb__separator" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    <span class="appr-breadcrumb__current"><?= e($matiere['nom'] ?? 'Ressources') ?></span>
  </div>

  <div class="appr-hero">
    <div class="appr-hero__container">
      <?php if (!empty($matiere['icone'])): ?>
        <div class="appr-hero__icon">
          <?= e($matiere['icone']) ?>
        </div>
      <?php endif; ?>
      <div class="appr-hero__text">
        <h1><?= e($matiere['nom'] ?? 'Ressources') ?></h1>
        <?php if (!empty($matiere['serie'])): ?>
          <div class="appr-hero__badge-wrapper">
            <span class="badge badge-serie-<?= e($matiere['serie']) ?>">Terminale <?= e($matiere['serie']) ?></span>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="filter-tabs">
    <?php foreach ($types as $val => $label): ?>
      <a href="<?= url('/apprentissage/ressources?matiere=' . (int)($matiere['id'] ?? 0) . '&type=' . $val) ?>"
         class="filter-tab <?= $typeFilter === $val ? 'active' : '' ?>">
        <?= $label ?>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if (empty($ressources)): ?>
    <div class="empty-state">
      <div class="empty-state__icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
          <polyline points="14 2 14 8 20 8"/>
        </svg>
      </div>
      <h3>Aucune ressource disponible</h3>
      <p>
        <?= $typeFilter !== 'tous' ? 'Aucun contenu de type « ' . $typeLabel($typeFilter) . ' » pour cette matière.' : 'Les ressources de cette matière seront bientôt ajoutées.' ?>
      </p>
      <?php if ($typeFilter !== 'tous'): ?>
        <a href="<?= url('/apprentissage/ressources?matiere=' . (int)($matiere['id'] ?? 0)) ?>" class="btn-appr btn-appr--outline">
          Voir tous les types
        </a>
      <?php endif; ?>
    </div>

  <?php else: ?>
    <div class="section-header">
      <h2><?= count($ressources) ?> ressource<?= count($ressources) > 1 ? 's' : '' ?></h2>
    </div>

    <div class="grid-cards">
      <?php foreach ($ressources as $r):
        $pct    = (int) ($r['pourcentage'] ?? 0);
        $statut = $r['statut'] ?? null;
        $isFav  = (int) ($r['est_favori'] ?? 0);
      ?>
        <div class="resource-card">

          <button
            onclick="toggleFavori(<?= (int)$r['id'] ?>, this)"
            class="viewer-toolbar__btn viewer-toolbar__btn--star <?= $isFav ? 'active' : '' ?>"
            title="<?= $isFav ? 'Retirer des favoris' : 'Ajouter aux favoris' ?>">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="<?= $isFav ? '#F59E0B' : 'none' ?>" stroke="<?= $isFav ? '#F59E0B' : 'currentColor' ?>" stroke-width="2">
              <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
            </svg>
          </button>

          <a href="<?= url('/apprentissage/viewer/' . (int)$r['id']) ?>" class="resource-card__link">

            <div class="resource-card__header">
              <div class="resource-card__icon resource-card__icon--<?= e($r['type'] ?? 'cours') ?>">
                <?= typeIconRes($r['type'] ?? '') ?>
              </div>
              <div class="resource-card__title-container">
                <div class="resource-card__title"><?= e($r['titre']) ?></div>
                <?php if (!empty($r['chapitre'])): ?>
                  <div class="resource-card__subtitle"><?= e($r['chapitre']) ?></div>
                <?php endif; ?>
              </div>
            </div>

            <div class="resource-card__meta">
              <span class="badge badge-<?= e($r['type'] ?? 'cours') ?>"><?= e($typeLabel($r['type'] ?? '')) ?></span>
              <?php if ($statut === 'termine'): ?>
                <span class="badge badge-termine">✓ Terminé</span>
              <?php elseif ($statut === 'en_cours'): ?>
                <span class="badge badge-en_cours">En cours</span>
              <?php endif; ?>
              <?php if (!empty($r['nb_vues'])): ?>
                <span class="resource-card__views">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  <?= (int)$r['nb_vues'] ?>
                </span>
              <?php endif; ?>
            </div>

            <?php if ($pct > 0 || $statut === 'en_cours'): ?>
              <div class="resource-card__footer">
                <div class="resource-card__progress-wrapper">
                  <div class="progress-bar-container">
                    <div class="progress-bar-fill" style="width:<?= $pct ?>%"></div>
                  </div>
                </div>
                <span class="resource-card__pct"><?= $pct ?>%</span>
              </div>
            <?php else: ?>
              <div class="resource-card__footer">
                <span class="resource-card__status-text">
                  <?= $statut === 'termine' ? '' : 'Non commencé' ?>
                </span>
                <span class="resource-card__action-text">
                  Consulter
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                </span>
              </div>
            <?php endif; ?>

          </a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<style>
/* ── AJOUT RESPONSIVE GRILLE ET TITRES LONGS ── */
.appr-page {
  max-width: 100% !important;
  overflow-x: hidden !important;
  padding: 12px !important;
  box-sizing: border-box !important;
}

/* Rendre la barre d'onglets de filtrage défilable horizontalement sur mobile si trop d'éléments */
.filter-tabs {
  display: flex !important;
  gap: 8px !important;
  overflow-x: auto !important;
  white-space: nowrap !important;
  padding-bottom: 8px !important;
  -webkit-overflow-scrolling: touch;
}
.filter-tab {
  flex-shrink: 0 !important;
}

/* Sécurité d'affichage pour la structure des cartes */
.grid-cards {
  display: grid !important;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)) !important;
  gap: 16px !important;
  width: 100% !important;
}

.resource-card {
  min-width: 0 !important; /* Très important pour empêcher la carte de pousser la grille */
  word-wrap: break-word !important;
}

.resource-card__header {
  display: flex !important;
  align-items: flex-start !important;
  gap: 12px !important;
  min-width: 0 !important;
}

.resource-card__title-container {
  flex: 1 !important;
  min-width: 0 !important;
}

/* Sécurité absolue sur les titres longs sans espaces (_) */
.resource-card__title {
  white-space: normal !important;
  word-break: break-all !important;
  overflow-wrap: break-word !important;
}

.resource-card__meta {
  display: flex !important;
  flex-wrap: wrap !important;
  gap: 6px !important;
}

/* Ajustement sur petit écran */
@media (max-width: 480px) {
  .grid-cards {
    grid-template-columns: 1fr !important; /* Une seule colonne propre sur mobile */
  }
}
</style>