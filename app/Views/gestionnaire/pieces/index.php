<?php
// Variables: $grouped (niveau => pieces[]), $pieces, $etab
$grouped = $grouped ?? [];
$pieces  = $pieces  ?? [];

use Services\Orientation\PorteePiece;

/**
 * Titre d'un groupe de pièces.
 *
 * Les référentiels existants portent des intitulés hérités du premier
 * back-office (« Socle L1-L2 »). On les traduit en niveaux réels au lieu de
 * les afficher tels quels : l'établissement voit ainsi exactement à qui la
 * pièce sera demandée, ce qui est la question qu'il se pose.
 */
$porteeTitre = static function (string $niveau): string {
    if ($niveau === '__tous' || $niveau === '') {
        return 'Tous niveaux';
    }

    $libelle = PorteePiece::libelle($niveau);

    return $libelle === $niveau ? $niveau : $niveau . ' — ' . $libelle;
};

/**
 * Options d'un <select name="niveau">, groupées par cycle et vocabulaire
 * aligné sur celui du formulaire d'inscription (L1…M2). Tant que les deux
 * écrans écrivaient dans deux langues différentes, aucun filtrage par niveau
 * ne pouvait fonctionner.
 */
$porteeOptions = static function (?string $valeur): string {
    $html = '';
    foreach (PorteePiece::optionsAvec($valeur) as $groupe => $entrees) {
        $groupe = (string)$groupe;
        if ($groupe !== '') {
            $html .= '<optgroup label="' . e($groupe) . '">';
        }
        foreach ($entrees as $val => $lbl) {
            $selected = ((string)$valeur === (string)$val) ? ' selected' : '';
            $html .= '<option value="' . e((string)$val) . '"' . $selected . '>' . e($lbl) . '</option>';
        }
        if ($groupe !== '') {
            $html .= '</optgroup>';
        }
    }

    return $html;
};
?>

<div class="g-page-header">
  <div>
    <h2>Pièces justificatives requises</h2>
    <p>Documents que les candidats doivent fournir lors de l'inscription.</p>
  </div>
  <button class="g-btn g-btn-primary" onclick="openModal('modal-add-piece')">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v8m-4-4h8"/></svg>
    Ajouter une pièce
  </button>
</div>

<div style="display:flex;flex-direction:column;gap:16px;">
<?php if (empty($grouped)): ?>
  <div class="g-card">
    <div class="g-empty">
      <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
      <p>Aucune pièce requise configurée.</p>
      <button class="g-btn g-btn-primary" onclick="openModal('modal-add-piece')" style="margin-top:12px;">Première pièce</button>
    </div>
  </div>
<?php else: ?>
  <?php foreach ($grouped as $niveau => $nPieces): ?>
    <div class="g-card">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
        <h3 style="font-size:13px;font-weight:700;color:var(--ap);"><?= e($porteeTitre((string)$niveau)) ?></h3>
        <span style="font-size:12px;color:var(--ink-3);"><?= count($nPieces) ?> pièce<?= count($nPieces) > 1 ? 's' : '' ?></span>
      </div>
      <div style="display:flex;flex-direction:column;gap:6px;">
        <?php foreach ($nPieces as $p): ?>
          <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:var(--surface);border-radius:var(--r-md);border:1px solid var(--border);">
            <div style="display:flex;align-items:center;gap:10px;">
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="<?= $p['obligatoire'] ? '#8B52FA' : '#9E9E9E' ?>" stroke-width="2"><path d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
              <span style="font-size:13px;font-weight:500;"><?= e($p['libelle']) ?></span>
              <?php if ($p['obligatoire']): ?>
                <span style="font-size:10px;color:#b91c1c;font-weight:700;">OBLIGATOIRE</span>
              <?php else: ?>
                <span style="font-size:10px;color:var(--ink-3);">facultatif</span>
              <?php endif; ?>
            </div>
            <div style="display:flex;gap:6px;">
              <button class="g-btn g-btn-ghost g-btn-sm" onclick="openEditPiece(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)">Modifier</button>
              <form action="<?= url('/gestionnaire/pieces/' . $p['id'] . '/delete') ?>" method="post" onsubmit="return confirm('Supprimer cette pièce ?')">
                <input type="hidden" name="csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                <button type="submit" class="g-btn g-btn-danger g-btn-sm">×</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
</div>

<!-- Modal : Ajouter -->
<div class="g-modal-overlay" id="modal-add-piece">
  <div class="g-modal">
    <div class="g-modal-head">
      <h2>Ajouter une pièce</h2>
      <button class="g-modal-close" onclick="closeModal('modal-add-piece')">×</button>
    </div>
    <form action="<?= url('/gestionnaire/pieces') ?>" method="post">
      <input type="hidden" name="csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
      <?= _pieceFields([], $porteeOptions) ?>
      <div style="display:flex;gap:10px;margin-top:18px;">
        <button type="submit" class="g-btn g-btn-primary">Ajouter</button>
        <button type="button" class="g-btn g-btn-ghost" onclick="closeModal('modal-add-piece')">Annuler</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal : Modifier -->
<div class="g-modal-overlay" id="modal-edit-piece">
  <div class="g-modal">
    <div class="g-modal-head">
      <h2>Modifier la pièce</h2>
      <button class="g-modal-close" onclick="closeModal('modal-edit-piece')">×</button>
    </div>
    <form id="form-edit-piece" method="post">
      <input type="hidden" name="csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
      <div id="edit-piece-fields"></div>
      <div style="display:flex;gap:10px;margin-top:18px;">
        <button type="submit" class="g-btn g-btn-primary">Enregistrer</button>
        <button type="button" class="g-btn g-btn-ghost" onclick="closeModal('modal-edit-piece')">Annuler</button>
      </div>
    </form>
  </div>
</div>

<?php
function _pieceFields(array $p, callable $porteeOptions): string {
    $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    // Le menu des portées vient de PorteePiece : même vocabulaire que le
    // formulaire d'inscription, valeur héritée conservée si elle existe.
    $sel = $porteeOptions($p['niveau'] ?? null);
    $obligatoire = !empty($p['obligatoire']) ? 'checked' : '';
    return "
      <div class=\"g-form-group\"><label>Libellé *</label><input type=\"text\" name=\"libelle\" value=\"{$e($p['libelle']??'')}\" required style=\"width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:var(--font);font-size:13px;\"></div>
      <div class=\"g-form-row\">
        <div class=\"g-form-group\"><label>Niveau concerné</label><select name=\"niveau\" style=\"width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:var(--font);font-size:13px;\">{$sel}</select></div>
        <div class=\"g-form-group\"><label>Ordre</label><input type=\"number\" name=\"ordre\" value=\"{$e($p['ordre']??0)}\" min=\"0\" style=\"width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:var(--font);font-size:13px;\"></div>
      </div>
      <label style=\"display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;\"><input type=\"checkbox\" name=\"obligatoire\" value=\"1\" {$obligatoire}> Pièce obligatoire</label>";
}
?>

<script>
function openEditPiece(p) {
  const base = window.CA_GEST.baseUrl;
  document.getElementById('form-edit-piece').action = base + '/gestionnaire/pieces/' + p.id;
  const e = v => (v ?? '').toString().replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;');

  // Les portées viennent du serveur (Services\Orientation\PorteePiece) : le
  // menu ne peut plus diverger de ce que le formulaire d'inscription sait
  // relire. Une valeur héritée absente de la liste est réinjectée telle
  // quelle, sinon l'enregistrement la remplacerait en douce par « tous
  // niveaux » sans que personne ne l'ait demandé.
  const portees = <?= json_encode(\Services\Orientation\PorteePiece::options(), JSON_UNESCAPED_UNICODE) ?>;
  let valeurs = [];
  let selOpts = '';
  for (const [groupe, entrees] of Object.entries(portees)) {
    const opts = Object.entries(entrees)
      .map(([v, l]) => { valeurs.push(v); return `<option value="${e(v)}">${e(l)}</option>`; })
      .join('');
    selOpts += groupe ? `<optgroup label="${e(groupe)}">${opts}</optgroup>` : opts;
  }
  const niveauActuel = (p.niveau ?? '') + '';
  if (niveauActuel !== '' && valeurs.indexOf(niveauActuel) === -1) {
    selOpts += `<optgroup label="Portée enregistrée"><option value="${e(niveauActuel)}">${e(niveauActuel)}</option></optgroup>`;
  }

  document.getElementById('edit-piece-fields').innerHTML = `
    <div class="g-form-group"><label>Libellé *</label><input type="text" name="libelle" value="${e(p.libelle)}" required style="width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:var(--font);font-size:13px;"></div>
    <div class="g-form-row">
      <div class="g-form-group"><label>Niveau</label><select name="niveau" style="width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:var(--font);font-size:13px;">${selOpts}</select></div>
      <div class="g-form-group"><label>Ordre</label><input type="number" name="ordre" value="${e(p.ordre||0)}" min="0" style="width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:var(--font);font-size:13px;"></div>
    </div>
    <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;margin-top:6px;"><input type="checkbox" name="obligatoire" value="1" ${p.obligatoire==1?'checked':''}> Pièce obligatoire</label>`;

  // Sélection posée APRÈS l'injection : `selected` dans une chaîne de gabarit
  // se perd dès qu'un <optgroup> s'intercale.
  const champNiveau = document.querySelector('#edit-piece-fields select[name="niveau"]');
  if (champNiveau) { champNiveau.value = niveauActuel; }

  openModal('modal-edit-piece');
}
</script>
