<?php
$favoris = $favoris ?? [];

function typeIconFav(string $type): string {
    return match($type) {
        'cours'            => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>',
        'td'               => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
        'ancienne_epreuve' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 9h6M9 12h6M9 15h4"/></svg>',
        default            => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>',
    };
}

$typeLabels = ['cours' => 'Cours', 'td' => 'TD', 'ancienne_epreuve' => 'Ancienne épreuve', 'corrige' => 'Corrigé'];
?>

<div class="appr-page favoris-page">

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
    <a href="<?= url('/apprentissage/progression') ?>" class="appr-subnav__link">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      Progression
    </a>
    <a href="<?= url('/apprentissage/favoris') ?>" class="appr-subnav__link active">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
      Favoris
    </a>
  </nav>

  <!-- ── Hero ──────────────────────────────────────────── -->
  <div class="appr-hero">
    <p style="color:#6B7280;font-size:14px;margin-bottom:4px">Module d'apprentissage</p>
    <h1>
      <svg width="24" height="24" viewBox="0 0 24 24" fill="#F59E0B" stroke="#F59E0B" stroke-width="1.5"
           style="vertical-align:middle;margin-right:6px">
        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
      </svg>
      Mes favoris
    </h1>
    <?php if (!empty($favoris)): ?>
      <p><?= count($favoris) ?> ressource<?= count($favoris) > 1 ? 's' : '' ?> sauvegardée<?= count($favoris) > 1 ? 's' : '' ?></p>
    <?php endif; ?>
  </div>

  <!-- ── Grille favoris ─────────────────────────────────── -->
  <?php if (empty($favoris)): ?>
    <div class="empty-state">
      <div class="empty-state__icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
        </svg>
      </div>
      <h3>Aucun favori pour l'instant</h3>
      <p>Ajoutez des ressources à vos favoris depuis la page des cours pour les retrouver ici.</p>
      <a href="<?= url('/apprentissage/matieres') ?>" class="btn-appr btn-appr--primary" style="margin-top:20px">
        Explorer les matières
      </a>
    </div>

  <?php else: ?>
    <div class="grid-cards">
      <?php foreach ($favoris as $r):
        $pct    = (int) ($r['pourcentage'] ?? 0);
        $statut = $r['statut'] ?? null;
      ?>
        <div class="resource-card" style="position:relative">

          <!-- Bouton retirer favori -->
          <button
            onclick="toggleFavori(<?= (int)$r['id'] ?>, this)"
            class="viewer-toolbar__btn viewer-toolbar__btn--star active"
            title="Retirer des favoris"
            style="position:absolute;top:12px;right:12px;padding:5px 8px;border-radius:7px;display:flex;align-items:center;gap:3px;font-size:11px;z-index:2">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="#F59E0B" stroke="#F59E0B" stroke-width="1.5">
              <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
            </svg>
          </button>

          <a href="<?= url('/apprentissage/viewer/' . (int)$r['id']) ?>" style="text-decoration:none;display:flex;flex-direction:column;gap:10px;flex:1">

            <div style="display:flex;align-items:flex-start;gap:12px;padding-right:50px">
              <div class="resource-card__icon">
                <?= typeIconFav($r['type'] ?? '') ?>
              </div>
              <div style="min-width:0;flex:1">
                <div class="resource-card__title"><?= e($r['titre']) ?></div>
                <div style="font-size:12px;color:#9CA3AF;margin-top:2px"><?= e($r['matiere'] ?? '') ?></div>
              </div>
            </div>

            <div class="resource-card__meta">
              <span class="badge badge-<?= e($r['type'] ?? 'cours') ?>"><?= e($typeLabels[$r['type'] ?? ''] ?? ucfirst(str_replace('_', ' ', $r['type'] ?? ''))) ?></span>
              <?php if (!empty($r['serie'])): ?>
                <span class="badge badge-serie-<?= e($r['serie']) ?>">Tle <?= e($r['serie']) ?></span>
              <?php endif; ?>
              <?php if ($statut === 'termine'): ?>
                <span class="badge badge-termine">✓ Terminé</span>
              <?php elseif ($statut === 'en_cours'): ?>
                <span class="badge badge-en_cours">En cours</span>
              <?php endif; ?>
            </div>

            <?php if ($pct > 0): ?>
              <div style="display:flex;align-items:center;gap:8px">
                <div class="progress-bar-container" style="flex:1">
                  <div class="progress-bar-fill" style="width:<?= $pct ?>%"></div>
                </div>
                <span style="font-size:12px;font-weight:700;color:#8B52FA"><?= $pct ?>%</span>
              </div>
            <?php endif; ?>

            <div class="resource-card__footer">
              <span style="font-size:12px;color:#9CA3AF">
                <?= $pct >= 100 ? '✅ Terminé' : ($pct > 0 ? 'En cours' : 'Non commencé') ?>
              </span>
              <span style="color:#8B52FA;font-size:12px;font-weight:600;display:flex;align-items:center;gap:3px">
                <?= $pct > 0 && $pct < 100 ? 'Continuer' : 'Consulter' ?>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
              </span>
            </div>

          </a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>
