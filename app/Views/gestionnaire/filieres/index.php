<?php
// Variables : $filieres (chaque filière contient 'specialites'), $etab
$filieres = $filieres ?? [];
$csrf     = \Core\Session::getCsrfToken();
?>

<div class="g-page-header">
  <div>
    <h2>Filières &amp; spécialités</h2>
    <p>Filières proposées aux candidats dans les formulaires de préinscription et d'inscription (menu déroulant Filière → Spécialité).</p>
  </div>
  <button class="g-btn g-btn-primary" onclick="openModal('modal-add-filiere')">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v8m-4-4h8"/></svg>
    Ajouter une filière
  </button>
</div>

<div style="display:flex;flex-direction:column;gap:16px;">
<?php if (empty($filieres)): ?>
  <div class="g-card">
    <div class="g-empty">
      <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M12 14l9-5-9-5-9 5 9 5z"/><path d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
      <p>Aucune filière configurée.<br><span style="font-size:12px;color:var(--ink-3);">Tant qu'aucune filière n'est définie, les formulaires proposent les niveaux génériques (Socle L1-L2, Licence L3…).</span></p>
      <button class="g-btn g-btn-primary" onclick="openModal('modal-add-filiere')" style="margin-top:12px;">Première filière</button>
    </div>
  </div>
<?php else: ?>
  <?php foreach ($filieres as $f): ?>
    <div class="g-card">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
        <div style="display:flex;align-items:center;gap:10px;">
          <h3 style="font-size:14px;font-weight:700;color:var(--ap);"><?= e($f['nom']) ?></h3>
          <?php if (!(int)$f['actif']): ?>
            <span style="font-size:10px;color:#b91c1c;font-weight:700;background:#fee2e2;padding:2px 8px;border-radius:10px;">DÉSACTIVÉE</span>
          <?php endif; ?>
          <span style="font-size:12px;color:var(--ink-3);"><?= count($f['specialites']) ?> spécialité<?= count($f['specialites']) > 1 ? 's' : '' ?></span>
          <?php if ((int)$f['frais_inscription'] > 0 || (int)$f['frais_scolarite'] > 0): ?>
            <span style="font-size:12px;color:var(--ink-3);">· Inscription <?= number_format((int)$f['frais_inscription'], 0, ',', ' ') ?> FCFA · Scolarité <?= number_format((int)$f['frais_scolarite'], 0, ',', ' ') ?> FCFA</span>
          <?php endif; ?>
        </div>
        <div style="display:flex;gap:6px;">
          <button class="g-btn g-btn-ghost g-btn-sm" onclick='openEditFiliere(<?= htmlspecialchars(json_encode(["id"=>(int)$f["id"],"nom"=>$f["nom"],"frais_inscription"=>(int)$f["frais_inscription"],"frais_scolarite"=>(int)$f["frais_scolarite"],"ordre"=>(int)$f["ordre"],"actif"=>(int)$f["actif"]]), ENT_QUOTES) ?>)'>Modifier</button>
          <button class="g-btn g-btn-ghost g-btn-sm" onclick='openAddSpec(<?= (int)$f['id'] ?>, <?= htmlspecialchars(json_encode($f['nom']), ENT_QUOTES) ?>)'>+ Spécialité</button>
          <form action="<?= url('/gestionnaire/filieres/' . (int)$f['id'] . '/delete') ?>" method="post" onsubmit="return confirm('Supprimer cette filière et toutes ses spécialités ?')">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <button type="submit" class="g-btn g-btn-danger g-btn-sm">×</button>
          </form>
        </div>
      </div>

      <?php if (empty($f['specialites'])): ?>
        <p style="font-size:12px;color:var(--ink-3);padding:8px 2px;">Aucune spécialité — les candidats choisiront uniquement la filière.</p>
      <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:6px;">
          <?php foreach ($f['specialites'] as $s): ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:9px 14px;background:var(--surface);border-radius:var(--r-md);border:1px solid var(--border);">
              <div style="display:flex;align-items:center;gap:10px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="<?= (int)$s['actif'] ? '#8B52FA' : '#9E9E9E' ?>" stroke-width="2"><path d="M9 5l7 7-7 7"/></svg>
                <span style="font-size:13px;font-weight:500;"><?= e($s['nom']) ?></span>
                <?php if (!(int)$s['actif']): ?>
                  <span style="font-size:10px;color:#b91c1c;font-weight:700;">désactivée</span>
                <?php endif; ?>
              </div>
              <div style="display:flex;gap:6px;">
                <button class="g-btn g-btn-ghost g-btn-sm" onclick='openEditSpec(<?= htmlspecialchars(json_encode(["id"=>(int)$s["id"],"nom"=>$s["nom"],"ordre"=>(int)$s["ordre"],"actif"=>(int)$s["actif"]]), ENT_QUOTES) ?>)'>Modifier</button>
                <form action="<?= url('/gestionnaire/specialites/' . (int)$s['id'] . '/delete') ?>" method="post" onsubmit="return confirm('Supprimer cette spécialité ?')">
                  <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                  <button type="submit" class="g-btn g-btn-danger g-btn-sm">×</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
</div>

<!-- Modal : Ajouter filière -->
<div class="g-modal-overlay" id="modal-add-filiere">
  <div class="g-modal">
    <div class="g-modal-head">
      <h2>Ajouter une filière</h2>
      <button class="g-modal-close" onclick="closeModal('modal-add-filiere')">×</button>
    </div>
    <form action="<?= url('/gestionnaire/filieres') ?>" method="post">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <div class="g-form-group"><label>Nom de la filière *</label><input type="text" name="nom" placeholder="Ex. Droit" required style="width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:var(--font);font-size:13px;"></div>
      <div class="g-form-group"><label>Frais d'inscription (FCFA)</label><input type="text" name="frais_inscription" placeholder="Ex. 150 000" inputmode="numeric" style="width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:var(--font);font-size:13px;"></div>
      <div class="g-form-group"><label>Frais de scolarité (FCFA)</label><input type="text" name="frais_scolarite" placeholder="Ex. 1 200 000" inputmode="numeric" style="width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:var(--font);font-size:13px;"></div>
      <p style="font-size:11px;color:var(--ink-3);margin:-6px 0 10px;">Laisse vide (ou 0) pour utiliser le tarif générique de l'établissement.</p>
      <div class="g-form-group"><label>Ordre d'affichage</label><input type="number" name="ordre" value="0" min="0" style="width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:var(--font);font-size:13px;"></div>
      <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;"><input type="checkbox" name="actif" value="1" checked> Filière active (visible sur les formulaires)</label>
      <div style="display:flex;gap:10px;margin-top:18px;">
        <button type="submit" class="g-btn g-btn-primary">Ajouter</button>
        <button type="button" class="g-btn g-btn-ghost" onclick="closeModal('modal-add-filiere')">Annuler</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal : Modifier filière -->
<div class="g-modal-overlay" id="modal-edit-filiere">
  <div class="g-modal">
    <div class="g-modal-head">
      <h2>Modifier la filière</h2>
      <button class="g-modal-close" onclick="closeModal('modal-edit-filiere')">×</button>
    </div>
    <form id="form-edit-filiere" method="post">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <div class="g-form-group"><label>Nom de la filière *</label><input type="text" name="nom" id="edit-filiere-nom" required style="width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:var(--font);font-size:13px;"></div>
      <div class="g-form-group"><label>Frais d'inscription (FCFA)</label><input type="text" name="frais_inscription" id="edit-filiere-frais-inscription" inputmode="numeric" style="width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:var(--font);font-size:13px;"></div>
      <div class="g-form-group"><label>Frais de scolarité (FCFA)</label><input type="text" name="frais_scolarite" id="edit-filiere-frais-scolarite" inputmode="numeric" style="width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:var(--font);font-size:13px;"></div>
      <p style="font-size:11px;color:var(--ink-3);margin:-6px 0 10px;">Laisse vide (ou 0) pour utiliser le tarif générique de l'établissement.</p>
      <div class="g-form-group"><label>Ordre d'affichage</label><input type="number" name="ordre" id="edit-filiere-ordre" min="0" style="width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:var(--font);font-size:13px;"></div>
      <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;"><input type="checkbox" name="actif" value="1" id="edit-filiere-actif"> Filière active</label>
      <div style="display:flex;gap:10px;margin-top:18px;">
        <button type="submit" class="g-btn g-btn-primary">Enregistrer</button>
        <button type="button" class="g-btn g-btn-ghost" onclick="closeModal('modal-edit-filiere')">Annuler</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal : Ajouter spécialité -->
<div class="g-modal-overlay" id="modal-add-spec">
  <div class="g-modal">
    <div class="g-modal-head">
      <h2 id="add-spec-title">Ajouter une spécialité</h2>
      <button class="g-modal-close" onclick="closeModal('modal-add-spec')">×</button>
    </div>
    <form id="form-add-spec" method="post">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <div class="g-form-group"><label>Nom de la spécialité *</label><input type="text" name="nom" placeholder="Ex. Conseil juridique" required style="width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:var(--font);font-size:13px;"></div>
      <div class="g-form-group"><label>Ordre d'affichage</label><input type="number" name="ordre" value="0" min="0" style="width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:var(--font);font-size:13px;"></div>
      <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;"><input type="checkbox" name="actif" value="1" checked> Spécialité active</label>
      <div style="display:flex;gap:10px;margin-top:18px;">
        <button type="submit" class="g-btn g-btn-primary">Ajouter</button>
        <button type="button" class="g-btn g-btn-ghost" onclick="closeModal('modal-add-spec')">Annuler</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal : Modifier spécialité -->
<div class="g-modal-overlay" id="modal-edit-spec">
  <div class="g-modal">
    <div class="g-modal-head">
      <h2>Modifier la spécialité</h2>
      <button class="g-modal-close" onclick="closeModal('modal-edit-spec')">×</button>
    </div>
    <form id="form-edit-spec" method="post">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <div class="g-form-group"><label>Nom de la spécialité *</label><input type="text" name="nom" id="edit-spec-nom" required style="width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:var(--font);font-size:13px;"></div>
      <div class="g-form-group"><label>Ordre d'affichage</label><input type="number" name="ordre" id="edit-spec-ordre" min="0" style="width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:var(--font);font-size:13px;"></div>
      <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;"><input type="checkbox" name="actif" value="1" id="edit-spec-actif"> Spécialité active</label>
      <div style="display:flex;gap:10px;margin-top:18px;">
        <button type="submit" class="g-btn g-btn-primary">Enregistrer</button>
        <button type="button" class="g-btn g-btn-ghost" onclick="closeModal('modal-edit-spec')">Annuler</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditFiliere(f) {
  const base = window.CA_GEST.baseUrl;
  document.getElementById('form-edit-filiere').action = base + '/gestionnaire/filieres/' + f.id;
  document.getElementById('edit-filiere-nom').value   = f.nom;
  document.getElementById('edit-filiere-frais-inscription').value = f.frais_inscription || 0;
  document.getElementById('edit-filiere-frais-scolarite').value   = f.frais_scolarite || 0;
  document.getElementById('edit-filiere-ordre').value = f.ordre || 0;
  document.getElementById('edit-filiere-actif').checked = f.actif == 1;
  openModal('modal-edit-filiere');
}
function openAddSpec(filiereId, filiereNom) {
  const base = window.CA_GEST.baseUrl;
  document.getElementById('form-add-spec').action = base + '/gestionnaire/filieres/' + filiereId + '/specialites';
  document.getElementById('add-spec-title').textContent = 'Ajouter une spécialité — ' + filiereNom;
  openModal('modal-add-spec');
}
function openEditSpec(s) {
  const base = window.CA_GEST.baseUrl;
  document.getElementById('form-edit-spec').action = base + '/gestionnaire/specialites/' + s.id;
  document.getElementById('edit-spec-nom').value   = s.nom;
  document.getElementById('edit-spec-ordre').value = s.ordre || 0;
  document.getElementById('edit-spec-actif').checked = s.actif == 1;
  openModal('modal-edit-spec');
}
</script>
