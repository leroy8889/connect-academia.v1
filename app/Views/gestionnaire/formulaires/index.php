<?php
// Variables: $grouped, $etab
// $grouped['preinscription']['Section'][] = champ
$grouped = $grouped ?? [];
$typeLabels = ['preinscription' => 'Préinscription', 'inscription' => 'Inscription'];
?>

<div class="g-page-header">
  <div>
    <h2>Configuration des formulaires</h2>
    <p>Activer, désactiver ou ajouter des champs à vos formulaires de candidature.</p>
  </div>
  <button class="g-btn g-btn-primary" onclick="openModal('modal-add-champ')">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v8m-4-4h8"/></svg>
    Ajouter un champ
  </button>
</div>

<div style="display:flex;flex-direction:column;gap:20px;">
<?php foreach (['preinscription','inscription'] as $typeForm): ?>
  <?php if (empty($grouped[$typeForm])): continue; endif; ?>
  <div class="g-card">
    <h3 style="font-size:14px;font-weight:700;margin-bottom:16px;color:var(--ap);"><?= $typeLabels[$typeForm] ?? $typeForm ?></h3>
    <?php foreach ($grouped[$typeForm] as $section => $champs): ?>
      <div style="margin-bottom:14px;">
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-3);font-weight:600;margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid var(--border);"><?= e($section) ?></div>
        <div style="display:flex;flex-direction:column;gap:6px;">
          <?php foreach ($champs as $c): ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:<?= $c['actif'] ? 'var(--card)' : 'var(--surface)' ?>;border-radius:var(--r-md);border:1px solid var(--border);opacity:<?= $c['actif'] ? '1' : '.6' ?>;">
              <div style="display:flex;align-items:center;gap:12px;">
                <span style="font-size:13px;font-weight:600;"><?= e($c['libelle']) ?></span>
                <span style="font-size:11px;color:var(--ink-3);"><?= e($c['type_champ']) ?></span>
                <?php if ($c['obligatoire']): ?>
                  <span style="font-size:10px;color:#b91c1c;font-weight:600;">REQ</span>
                <?php endif; ?>
                <?php if ($c['standard']): ?>
                  <span style="font-size:10px;color:var(--ink-3);background:var(--surface);padding:1px 6px;border-radius:999px;border:1px solid var(--border);">Standard</span>
                <?php else: ?>
                  <span style="font-size:10px;color:var(--ap);background:var(--ap-tint);padding:1px 6px;border-radius:999px;">Personnalisé</span>
                <?php endif; ?>
              </div>
              <div style="display:flex;align-items:center;gap:8px;">
                <!-- Toggle actif -->
                <form action="<?= url('/gestionnaire/formulaires/' . $c['id'] . '/toggle') ?>" method="post" class="toggle-form" data-id="<?= $c['id'] ?>">
                  <input type="hidden" name="csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                  <button type="submit" class="g-btn g-btn-ghost g-btn-sm" style="padding:5px 12px;font-size:12px;"
                          title="<?= $c['actif'] ? 'Désactiver' : 'Activer' ?>">
                    <?= $c['actif'] ? 'Actif' : 'Inactif' ?>
                  </button>
                </form>
                <?php if (!$c['standard']): ?>
                  <form action="<?= url('/gestionnaire/formulaires/' . $c['id'] . '/delete') ?>" method="post" onsubmit="return confirm('Supprimer ce champ personnalisé ?')">
                    <input type="hidden" name="csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                    <button type="submit" class="g-btn g-btn-danger g-btn-sm" style="padding:5px 12px;font-size:12px;">×</button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>

<?php if (empty($grouped)): ?>
  <div class="g-card">
    <div class="g-empty">
      <p>Aucun champ configuré. Les champs standard sont insérés automatiquement à la création de votre établissement.</p>
    </div>
  </div>
<?php endif; ?>
</div>

<!-- Modal : Ajouter champ -->
<div class="g-modal-overlay" id="modal-add-champ">
  <div class="g-modal">
    <div class="g-modal-head">
      <h2>Ajouter un champ personnalisé</h2>
      <button class="g-modal-close" onclick="closeModal('modal-add-champ')">×</button>
    </div>
    <form action="<?= url('/gestionnaire/formulaires') ?>" method="post">
      <input type="hidden" name="csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
      <div class="g-form-group">
        <label>Formulaire</label>
        <select name="type_formulaire" style="width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:var(--font);font-size:13px;">
          <option value="preinscription">Préinscription</option>
          <option value="inscription">Inscription</option>
        </select>
      </div>
      <div class="g-form-row">
        <div class="g-form-group"><label>Section</label><input type="text" name="section" placeholder="Identité, Contact…" style="width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:var(--font);font-size:13px;"></div>
        <div class="g-form-group"><label>Libellé *</label><input type="text" name="libelle" required style="width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:var(--font);font-size:13px;"></div>
      </div>
      <div class="g-form-row">
        <div class="g-form-group">
          <label>Type de champ</label>
          <select name="type_champ" style="width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:var(--font);font-size:13px;">
            <option value="text">Texte</option>
            <option value="email">E-mail</option>
            <option value="tel">Téléphone</option>
            <option value="date">Date</option>
            <option value="select">Liste déroulante</option>
            <option value="textarea">Zone de texte</option>
            <option value="fichier">Fichier</option>
          </select>
        </div>
        <div class="g-form-group">
          <label>Ordre</label>
          <input type="number" name="ordre" value="99" min="0" style="width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:var(--font);font-size:13px;">
        </div>
      </div>
      <div class="g-form-group">
        <label>Options (pour select — une option par ligne)</label>
        <textarea name="options_raw" rows="3" placeholder="Oui&#10;Non&#10;Je ne sais pas" style="width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:var(--font);font-size:13px;resize:vertical;"></textarea>
      </div>
      <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;margin-bottom:18px;">
        <input type="checkbox" name="obligatoire" value="1"> Champ obligatoire
      </label>
      <div style="display:flex;gap:10px;">
        <button type="submit" class="g-btn g-btn-primary">Ajouter</button>
        <button type="button" class="g-btn g-btn-ghost" onclick="closeModal('modal-add-champ')">Annuler</button>
      </div>
    </form>
  </div>
</div>
