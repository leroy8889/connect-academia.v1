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
    $icon = match($type) {
        'cours'            => 'book-open',
        'td'               => 'file-text',
        'ancienne_epreuve' => 'clipboard-list',
        'corrige'          => 'check-square',
        default            => 'file',
    };
    return '<i data-lucide="' . $icon . '" style="width:20px;height:20px"></i>';
}

$typeLabel = fn(string $t) => $types[$t] ?? ucfirst(str_replace('_', ' ', $t));

function matiereIconHero(string $nom): string {
    $n = mb_strtolower($nom, 'UTF-8');
    if (str_contains($n, 'math'))                                                                        return 'calculator';
    if (str_contains($n, 'physique') || str_contains($n, 'chimie'))                                     return 'flask-conical';
    if (str_contains($n, 'svt') || str_contains($n, 'biolog') || (str_contains($n, 'vie') && str_contains($n, 'terre'))) return 'leaf';
    if (str_contains($n, 'philo'))                                                                       return 'lightbulb';
    if (str_contains($n, 'histoire') || str_contains($n, 'géographie') || str_contains($n, 'geographie')) return 'globe';
    if (str_contains($n, 'français') || str_contains($n, 'francais') || str_contains($n, 'litt'))       return 'pen-line';
    if (str_contains($n, 'anglais') || str_contains($n, 'espagnol') || str_contains($n, 'allemand') || str_contains($n, 'langues vivantes')) return 'languages';
    if (str_contains($n, 'économie') || str_contains($n, 'economie'))                                   return 'trending-up';
    return 'book-open';
}
?>

<div class="appr-page">

  <!-- ── Sous-navigation ───────────────────────────────── -->
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

  <!-- ── Breadcrumb ─────────────────────────────────────── -->
  <div class="appr-breadcrumb">
    <a href="<?= url('/apprentissage/matieres') ?>">
      <i data-lucide="chevron-left" style="width:13px;height:13px"></i>
      Mes matières
    </a>
    <i data-lucide="chevron-right" style="width:12px;height:12px"></i>
    <span><?= e($matiere['nom'] ?? 'Ressources') ?></span>
  </div>

  <!-- ── Hero ──────────────────────────────────────────── -->
  <div class="appr-hero">
    <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
      <div class="appr-hero__icon-cap">
        <i data-lucide="<?= matiereIconHero($matiere['nom'] ?? '') ?>" style="width:24px;height:24px"></i>
      </div>
      <div>
        <h1 style="margin:0"><?= e($matiere['nom'] ?? 'Ressources') ?></h1>
        <?php if (!empty($matiere['serie'])): ?>
          <div style="margin-top:8px">
            <span class="badge badge-serie-<?= e($matiere['serie']) ?>">Terminale <?= e($matiere['serie']) ?></span>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ── Filtres par type ───────────────────────────────── -->
  <div class="filter-tabs">
    <?php foreach ($types as $val => $label): ?>
      <a href="<?= url('/apprentissage/ressources?matiere=' . (int)($matiere['id'] ?? 0) . '&type=' . $val) ?>"
         class="filter-tab <?= $typeFilter === $val ? 'active' : '' ?>">
        <?= $label ?>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- ── Grille ressources ──────────────────────────────── -->
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
        <a href="<?= url('/apprentissage/ressources?matiere=' . (int)($matiere['id'] ?? 0)) ?>" class="btn-appr btn-appr--outline" style="margin-top:20px">
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
        <div class="resource-card" style="position:relative">

          <!-- Bouton favori (hors du lien principal) -->
          <button
            onclick="toggleFavori(<?= (int)$r['id'] ?>, this)"
            class="viewer-toolbar__btn viewer-toolbar__btn--star <?= $isFav ? 'active' : '' ?>"
            title="<?= $isFav ? 'Retirer des favoris' : 'Ajouter aux favoris' ?>"
            style="position:absolute;top:12px;right:12px;padding:5px 8px;font-size:11px;border-radius:7px;display:flex;align-items:center;gap:3px;z-index:2">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="<?= $isFav ? '#F59E0B' : 'none' ?>" stroke="<?= $isFav ? '#F59E0B' : 'currentColor' ?>" stroke-width="2">
              <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
            </svg>
          </button>

          <!-- Lien vers le viewer -->
          <a href="<?= url('/apprentissage/viewer/' . (int)$r['id']) ?>" style="display:flex;flex-direction:column;gap:10px;flex:1;text-decoration:none">

            <div style="display:flex;align-items:flex-start;gap:12px;padding-right:56px">
              <div class="resource-card__icon resource-card__icon--<?= e($r['type'] ?? 'cours') ?>">
                <?= typeIconRes($r['type'] ?? '') ?>
              </div>
              <div style="min-width:0;flex:1">
                <div class="resource-card__title"><?= e($r['titre']) ?></div>
                <?php if (!empty($r['chapitre'])): ?>
                  <div class="ca-caption" style="margin-top:2px"><?= e($r['chapitre']) ?></div>
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
                <span style="margin-left:auto;display:flex;align-items:center;gap:3px;font-size:11px;color:var(--text-muted)">
                  <i data-lucide="eye" style="width:11px;height:11px"></i>
                  <?= (int)$r['nb_vues'] ?>
                </span>
              <?php endif; ?>
            </div>

            <?php if ($pct > 0 || $statut === 'en_cours'): ?>
              <div class="resource-card__footer">
                <div style="flex:1">
                  <div class="progress-bar-container">
                    <div class="progress-bar-fill" style="width:<?= $pct ?>%"></div>
                  </div>
                </div>
                <span style="font-size:12px;font-weight:700;color:var(--purple-neon);white-space:nowrap"><?= $pct ?>%</span>
              </div>
            <?php else: ?>
              <div class="resource-card__footer">
                <span class="ca-caption">
                  <?= $statut === 'termine' ? '' : 'Non commencé' ?>
                </span>
                <span class="matiere-card__cta">
                  Consulter
                  <i data-lucide="arrow-right" style="width:13px;height:13px"></i>
                </span>
              </div>
            <?php endif; ?>

          </a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

