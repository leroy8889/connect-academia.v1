<?php
// Variables: $actualites, $etab
$actualites = $actualites ?? [];

// Limite d'upload image — doit rester alignée sur
// Services\Orientation\DocumentStorage::IMAGE_MAX_SIZE_BYTES
$imageMaxMo = (int)(\Services\Orientation\DocumentStorage::IMAGE_MAX_SIZE_BYTES / 1048576);
?>

<div class="g-page-header">
  <div>
    <h2>Actualités</h2>
    <p><?= count($actualites) ?> article<?= count($actualites) > 1 ? 's' : '' ?></p>
  </div>
  <button class="g-btn g-btn-primary" onclick="openActuForm('create')">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v8m-4-4h8"/></svg>
    Ajouter
  </button>
</div>

<div class="g-card" style="padding:0;overflow:hidden;">
<?php if (empty($actualites)): ?>
  <div class="g-empty">
    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2"/></svg>
    <p>Aucune actualité publiée.</p>
    <button class="g-btn g-btn-primary" onclick="openActuForm('create')" style="margin-top:12px;">Premier article</button>
  </div>
<?php else: ?>
  <table class="g-table">
    <thead>
      <tr>
        <th>Titre</th>
        <th>Catégorie</th>
        <th>Date</th>
        <th>À la une</th>
        <th>Statut</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($actualites as $a): ?>
      <tr>
        <td style="font-weight:600;font-size:13px;max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($a['titre']) ?></td>
        <td style="font-size:12px;color:var(--ink-2);"><?= e($a['categorie'] ?? '—') ?></td>
        <td style="font-size:12px;color:var(--ink-3);"><?= $a['date_publication'] ? date('d/m/Y', strtotime($a['date_publication'])) : '—' ?></td>
        <td><?= $a['a_la_une'] ? '<span style="color:#8B52FA;font-size:18px;">★</span>' : '<span style="color:var(--border);font-size:18px;">☆</span>' ?></td>
        <td>
          <?php if ($a['statut'] === 'publie'): ?>
            <span class="g-badge g-badge-ouverte">Publié</span>
          <?php else: ?>
            <span class="g-badge g-badge-brouillon">Brouillon</span>
          <?php endif; ?>
        </td>
        <td style="display:flex;gap:6px;">
          <button class="g-btn g-btn-ghost g-btn-sm" onclick="openActuForm('edit', <?= htmlspecialchars(json_encode($a), ENT_QUOTES) ?>)">Modifier</button>
          <form action="<?= url('/gestionnaire/actualites/' . $a['id'] . '/delete') ?>" method="post" onsubmit="return confirm('Supprimer cet article ?')">
            <input type="hidden" name="csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
            <button type="submit" class="g-btn g-btn-danger g-btn-sm">Suppr.</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
</div>

<!-- ════════════════════════════════════════════════════════════════
     Composeur d'article — un seul formulaire pour créer ET modifier.
     Langage visuel Claude : papier crème, aplats, filets fins, air.
     ════════════════════════════════════════════════════════════════ -->
<div class="g-modal-overlay actu-overlay" id="modal-actu">
  <div class="g-modal actu-modal" role="dialog" aria-modal="true" aria-labelledby="actu-title">
    <div class="actu-head">
      <div>
        <span class="actu-eyebrow" id="actu-eyebrow">Nouvelle publication</span>
        <h2 id="actu-title">Nouvel article</h2>
        <p class="actu-head-sub">Il apparaîtra dans la rubrique « Actualités » de votre page publique.</p>
      </div>
      <button type="button" class="g-modal-close" onclick="closeModal('modal-actu')" aria-label="Fermer">&times;</button>
    </div>

    <form id="actu-form" method="post" enctype="multipart/form-data" novalidate>
      <input type="hidden" name="csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">

      <!-- ── L'essentiel ─────────────────────────────────────── -->
      <section class="actu-block">
        <div class="actu-block-label">L'essentiel</div>
        <div class="actu-field">
          <label for="actu-titre">Titre <span class="req">*</span></label>
          <input type="text" id="actu-titre" name="titre" maxlength="180" required
                 placeholder="Ex : Ouverture des inscriptions 2026-2027">
        </div>
        <div class="actu-grid-2">
          <div class="actu-field">
            <label for="actu-categorie">Catégorie</label>
            <input type="text" id="actu-categorie" name="categorie" maxlength="80"
                   list="actu-categories" placeholder="Événement, Bourse…">
            <datalist id="actu-categories">
              <option value="Événement"></option>
              <option value="Bourse"></option>
              <option value="Admission"></option>
              <option value="Vie du campus"></option>
              <option value="Partenariat"></option>
            </datalist>
          </div>
          <div class="actu-field">
            <label for="actu-date">Date de publication</label>
            <input type="date" id="actu-date" name="date_publication" value="<?= date('Y-m-d') ?>">
          </div>
        </div>
      </section>

      <!-- ── Illustration ───────────────────────────────────── -->
      <section class="actu-block">
        <div class="actu-block-label">Illustration</div>

        <label class="actu-drop" id="actu-drop" for="actu-image">
          <div class="actu-drop-thumb" id="actu-drop-thumb" hidden>
            <img id="actu-drop-img" alt="Aperçu de l'illustration">
          </div>
          <div class="actu-drop-ic" id="actu-drop-ic">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>
            </svg>
          </div>
          <div class="actu-drop-txt">
            <strong id="actu-drop-title">Déposer une image ou parcourir</strong>
            <span id="actu-drop-hint">Tous formats image — JPG, PNG, WebP, GIF, BMP, TIFF, AVIF, HEIC · <?= $imageMaxMo ?> Mo maximum</span>
          </div>
          <button type="button" class="actu-drop-clear" id="actu-drop-clear" hidden
                  onclick="event.preventDefault();event.stopPropagation();clearActuImage();">Retirer</button>
          <input type="file" id="actu-image" name="image" accept="image/*" hidden
                 data-max-bytes="<?= (int)(\Services\Orientation\DocumentStorage::IMAGE_MAX_SIZE_BYTES) ?>">
        </label>

        <p class="actu-inline-error" id="actu-image-error" role="alert" hidden></p>
        <p class="actu-keep-note" id="actu-keep-note" hidden>Une image est déjà associée à cet article — elle est conservée tant qu'aucun nouveau fichier n'est choisi.</p>
      </section>

      <!-- ── Contenu ────────────────────────────────────────── -->
      <section class="actu-block">
        <div class="actu-block-label">Contenu</div>
        <div class="actu-field">
          <label for="actu-extrait">Extrait <span class="opt">résumé affiché en tête d'article</span></label>
          <textarea id="actu-extrait" name="extrait" rows="2" maxlength="400"
                    placeholder="Une ou deux phrases pour donner envie de lire la suite."></textarea>
        </div>
        <div class="actu-field">
          <label for="actu-contenu">Corps de l'article</label>
          <textarea id="actu-contenu" name="contenu" rows="9"
                    placeholder="Rédigez votre article…"></textarea>
          <div class="actu-help">
            <span class="actu-help-title">Mise en page</span>
            <ul>
              <li><code>## Titre</code> — ouvre une section (alimente le sommaire)</li>
              <li><code>### Sous-titre</code> — sous-point d'une section</li>
              <li><code>**gras**</code> — met un passage en valeur</li>
              <li>Une ligne vide sépare deux paragraphes</li>
            </ul>
          </div>
        </div>
      </section>

      <!-- ── Publication ────────────────────────────────────── -->
      <section class="actu-block actu-block-last">
        <div class="actu-block-label">Publication</div>
        <div class="actu-pub">
          <label class="actu-switch">
            <input type="checkbox" id="actu-une" name="a_la_une" value="1">
            <span class="actu-switch-track"><span class="actu-switch-knob"></span></span>
            <span class="actu-switch-txt">
              <strong>Mettre à la une</strong>
              <em>L'article est mis en avant en tête de la rubrique.</em>
            </span>
          </label>

          <div class="actu-seg" role="radiogroup" aria-label="Statut de publication">
            <label class="actu-seg-opt">
              <input type="radio" name="statut" value="brouillon" checked>
              <span>Brouillon</span>
            </label>
            <label class="actu-seg-opt">
              <input type="radio" name="statut" value="publie">
              <span>Publié</span>
            </label>
          </div>
        </div>
      </section>

      <div class="actu-actions">
        <button type="button" class="g-btn g-btn-ghost" onclick="closeModal('modal-actu')">Annuler</button>
        <button type="submit" class="g-btn g-btn-primary" id="actu-submit">Créer l'article</button>
      </div>
    </form>
  </div>
</div>

<style>
/* ════════════════════════════════════════════════════════════════
   Composeur d'article — design system Claude
   Papier crème, surfaces plates, filets fins, un seul accent violet.
   Portée : uniquement sous .actu-modal / .actu-overlay
   ════════════════════════════════════════════════════════════════ */
.actu-modal {
  max-width: 720px;
  padding: 0;
  background: #FAF9F5;
  -webkit-backdrop-filter: none; backdrop-filter: none;
  border: 1px solid rgba(31,30,29,.10);
  border-radius: 20px;
  box-shadow: 0 30px 80px rgba(31,30,29,.22);
  overflow: hidden;
}
.actu-head {
  display: flex; align-items: flex-start; justify-content: space-between; gap: 16px;
  padding: 26px 30px 22px;
  border-bottom: 1px solid rgba(31,30,29,.09);
  background: #FFFFFF;
}
.actu-eyebrow {
  display: block; font-size: 10.5px; font-weight: 700;
  letter-spacing: .16em; text-transform: uppercase;
  color: var(--ap); margin-bottom: 9px;
}
.actu-head h2 { font-size: 21px; font-weight: 700; letter-spacing: -.02em; color: var(--ink); margin: 0; }
.actu-head-sub { font-size: 12.5px; color: var(--ink-3); margin: 6px 0 0; line-height: 1.5; }

#actu-form { max-height: calc(90vh - 118px); overflow-y: auto; padding: 0 30px; }

.actu-block { padding: 24px 0; border-bottom: 1px solid rgba(31,30,29,.08); }
.actu-block-last { border-bottom: none; }
.actu-block-label {
  font-size: 10.5px; font-weight: 700; letter-spacing: .16em;
  text-transform: uppercase; color: var(--ink-3); margin-bottom: 16px;
}

.actu-field { margin-bottom: 16px; }
.actu-field:last-child { margin-bottom: 0; }
.actu-field label {
  display: block; font-size: 12.5px; font-weight: 600;
  color: var(--ink); margin-bottom: 7px;
}
.actu-field label .req { color: var(--ap); }
.actu-field label .opt { font-weight: 400; color: var(--ink-3); }
.actu-field input[type="text"],
.actu-field input[type="date"],
.actu-field textarea {
  width: 100%; padding: 11px 14px;
  background: #FFFFFF;
  border: 1px solid rgba(31,30,29,.14);
  border-radius: 12px;
  font-family: var(--font); font-size: 13.5px; color: var(--ink);
  transition: border-color .16s var(--ease), box-shadow .16s var(--ease);
  resize: vertical;
}
.actu-field input::placeholder, .actu-field textarea::placeholder { color: var(--ink-3); }
.actu-field input:focus, .actu-field textarea:focus {
  outline: none; border-color: var(--ap);
  box-shadow: 0 0 0 3px rgba(139,82,250,.16);
}
.actu-field input.is-invalid { border-color: #DC2626; box-shadow: 0 0 0 3px rgba(220,38,38,.14); }
.actu-grid-2 { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 14px; }

/* ── Zone de dépôt image ─────────────────────────────────────── */
.actu-drop {
  display: flex; align-items: center; gap: 15px;
  padding: 16px 18px;
  background: #FFFFFF;
  border: 1px dashed rgba(31,30,29,.22);
  border-radius: 14px; cursor: pointer;
  transition: border-color .16s var(--ease), background .16s var(--ease);
}
.actu-drop:hover, .actu-drop.is-hover { border-color: var(--ap); background: rgba(139,82,250,.04); }
.actu-drop.is-error { border-color: #DC2626; background: rgba(220,38,38,.04); }
.actu-drop-ic {
  width: 42px; height: 42px; flex: none; border-radius: 11px;
  background: rgba(139,82,250,.10); color: var(--ap);
  display: grid; place-items: center;
}
.actu-drop-thumb { width: 60px; height: 44px; flex: none; border-radius: 10px; overflow: hidden; border: 1px solid rgba(31,30,29,.10); }
.actu-drop-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.actu-drop-txt { flex: 1; min-width: 0; }
.actu-drop-txt strong { display: block; font-size: 13px; font-weight: 600; color: var(--ink); }
.actu-drop-txt span {
  display: block; margin-top: 4px; font-size: 11.5px; color: var(--ink-3);
  line-height: 1.5; overflow-wrap: anywhere;
}
.actu-drop-clear {
  flex: none; background: none; border: 1px solid rgba(31,30,29,.14);
  border-radius: 999px; padding: 6px 13px; cursor: pointer;
  font-family: var(--font); font-size: 11.5px; font-weight: 600; color: var(--ink-2);
  transition: all .16s var(--ease);
}
.actu-drop-clear:hover { border-color: #DC2626; color: #DC2626; }

.actu-inline-error {
  margin-top: 10px; padding: 10px 13px;
  background: rgba(220,38,38,.08); border: 1px solid rgba(220,38,38,.24);
  border-radius: 10px; font-size: 12.5px; font-weight: 600; color: #B91C1C;
}
.actu-keep-note { margin-top: 10px; font-size: 11.5px; color: var(--ink-3); line-height: 1.5; }

/* ── Aide mise en page ───────────────────────────────────────── */
.actu-help {
  margin-top: 10px; padding: 13px 15px;
  background: rgba(31,30,29,.03);
  border: 1px solid rgba(31,30,29,.07);
  border-radius: 12px;
}
.actu-help-title {
  display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .14em;
  text-transform: uppercase; color: var(--ink-3); margin-bottom: 8px;
}
.actu-help ul { list-style: none; margin: 0; padding: 0; display: grid; gap: 5px; }
.actu-help li { font-size: 12px; color: var(--ink-2); line-height: 1.5; }
.actu-help code {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 11.5px; background: #FFFFFF; color: var(--ink);
  border: 1px solid rgba(31,30,29,.10); border-radius: 6px; padding: 1px 6px;
}

/* ── Publication ─────────────────────────────────────────────── */
.actu-pub { display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap; }
.actu-switch { display: flex; align-items: center; gap: 13px; cursor: pointer; }
.actu-switch input { position: absolute; opacity: 0; width: 0; height: 0; }
.actu-switch-track {
  width: 42px; height: 24px; flex: none; border-radius: 999px;
  background: rgba(31,30,29,.16); position: relative;
  transition: background .18s var(--ease);
}
.actu-switch-knob {
  position: absolute; top: 3px; left: 3px; width: 18px; height: 18px;
  border-radius: 999px; background: #FFFFFF;
  box-shadow: 0 1px 3px rgba(31,30,29,.24);
  transition: transform .18s var(--ease);
}
.actu-switch input:checked + .actu-switch-track { background: var(--ap); }
.actu-switch input:checked + .actu-switch-track .actu-switch-knob { transform: translateX(18px); }
.actu-switch input:focus-visible + .actu-switch-track { box-shadow: 0 0 0 3px rgba(139,82,250,.24); }
.actu-switch-txt strong { display: block; font-size: 13px; font-weight: 600; color: var(--ink); }
.actu-switch-txt em { display: block; margin-top: 3px; font-size: 11.5px; font-style: normal; color: var(--ink-3); }

.actu-seg {
  display: inline-flex; padding: 3px; gap: 3px;
  background: rgba(31,30,29,.05); border: 1px solid rgba(31,30,29,.08);
  border-radius: 999px;
}
.actu-seg-opt { position: relative; }
.actu-seg-opt input { position: absolute; opacity: 0; width: 0; height: 0; }
.actu-seg-opt span {
  display: block; padding: 7px 17px; border-radius: 999px; cursor: pointer;
  font-size: 12.5px; font-weight: 600; color: var(--ink-2);
  transition: background .16s var(--ease), color .16s var(--ease);
}
.actu-seg-opt input:checked + span { background: #FFFFFF; color: var(--ink); box-shadow: 0 1px 3px rgba(31,30,29,.10); }
.actu-seg-opt input:focus-visible + span { box-shadow: 0 0 0 3px rgba(139,82,250,.24); }

.actu-actions {
  position: sticky; bottom: 0;
  display: flex; justify-content: flex-end; gap: 10px;
  padding: 18px 0 22px;
  background: linear-gradient(to bottom, rgba(250,249,245,0) 0%, #FAF9F5 24%);
}

@media (max-width: 640px) {
  .actu-modal { max-width: 100%; }
  .actu-head { padding: 22px 20px 18px; }
  #actu-form { padding: 0 20px; }
  .actu-grid-2 { grid-template-columns: minmax(0,1fr); }
  .actu-pub { align-items: flex-start; flex-direction: column; }
  .actu-drop { flex-wrap: wrap; }
}
</style>

<script>
(function () {
  var STORE_URL = <?= json_encode(url('/gestionnaire/actualites')) ?>;
  var MAX_BYTES = <?= (int)\Services\Orientation\DocumentStorage::IMAGE_MAX_SIZE_BYTES ?>;
  var MAX_MO    = <?= $imageMaxMo ?>;

  var form   = document.getElementById('actu-form');
  var input  = document.getElementById('actu-image');
  var drop   = document.getElementById('actu-drop');
  var errBox = document.getElementById('actu-image-error');
  var thumb  = document.getElementById('actu-drop-thumb');
  var thumbImg = document.getElementById('actu-drop-img');
  var dropIc = document.getElementById('actu-drop-ic');
  var dTitle = document.getElementById('actu-drop-title');
  var dHint  = document.getElementById('actu-drop-hint');
  var dClear = document.getElementById('actu-drop-clear');
  var keepNote = document.getElementById('actu-keep-note');
  var previewUrl = null;

  var DEFAULT_TITLE = 'Déposer une image ou parcourir';
  var DEFAULT_HINT  = 'Tous formats image — JPG, PNG, WebP, GIF, BMP, TIFF, AVIF, HEIC · ' + MAX_MO + ' Mo maximum';

  function mo(bytes) { return (bytes / 1048576).toFixed(1).replace('.', ','); }

  function showError(msg) {
    errBox.textContent = msg;
    errBox.hidden = false;
    drop.classList.add('is-error');
  }
  function clearError() {
    errBox.hidden = true;
    errBox.textContent = '';
    drop.classList.remove('is-error');
  }

  function resetPreview() {
    if (previewUrl) { URL.revokeObjectURL(previewUrl); previewUrl = null; }
    thumb.hidden = true;
    thumbImg.removeAttribute('src');
    dropIc.hidden = false;
    dClear.hidden = true;
    dTitle.textContent = DEFAULT_TITLE;
    dHint.textContent  = DEFAULT_HINT;
  }

  window.clearActuImage = function () {
    input.value = '';
    clearError();
    resetPreview();
  };

  function handleFile(file) {
    clearError();
    if (!file) { resetPreview(); return; }

    // Refus immédiat côté client — le serveur re-vérifie de toute façon
    if (file.size > MAX_BYTES) {
      showError("La taille de l'image n'est pas supportée : " + mo(file.size) + ' Mo (maximum ' + MAX_MO + ' Mo).');
      input.value = '';
      resetPreview();
      return;
    }
    if (file.type && file.type.indexOf('image/') !== 0) {
      showError("Ce fichier n'est pas une image. Formats acceptés : JPG, PNG, WebP, GIF, BMP, TIFF, AVIF, HEIC.");
      input.value = '';
      resetPreview();
      return;
    }

    if (previewUrl) URL.revokeObjectURL(previewUrl);
    previewUrl = URL.createObjectURL(file);
    thumbImg.src = previewUrl;
    thumb.hidden = false;
    dropIc.hidden = true;
    dClear.hidden = false;
    dTitle.textContent = file.name;
    dHint.textContent  = mo(file.size) + ' Mo · prêt à être envoyé';
    if (keepNote) keepNote.hidden = true;
  }

  input.addEventListener('change', function () { handleFile(this.files && this.files[0]); });

  ['dragenter', 'dragover'].forEach(function (ev) {
    drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('is-hover'); });
  });
  ['dragleave', 'drop'].forEach(function (ev) {
    drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('is-hover'); });
  });
  drop.addEventListener('drop', function (e) {
    var f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
    if (!f) return;
    // DataTransfer -> input : conserve le fichier pour la soumission classique
    try {
      var dt = new DataTransfer();
      dt.items.add(f);
      input.files = dt.files;
    } catch (err) { /* navigateur sans DataTransfer constructible : ignore */ }
    handleFile(f);
  });

  form.addEventListener('submit', function (e) {
    var titre = document.getElementById('actu-titre');
    if (!titre.value.trim()) {
      e.preventDefault();
      titre.classList.add('is-invalid');
      titre.focus();
      return;
    }
    titre.classList.remove('is-invalid');
    if (!errBox.hidden) { e.preventDefault(); errBox.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
  });

  /**
   * Ouvre le composeur — mode 'create' ou 'edit' (avec l'article en 2e arg).
   */
  window.openActuForm = function (mode, a) {
    var base = (window.CA_GEST && window.CA_GEST.baseUrl) || '';
    clearError();
    resetPreview();
    input.value = '';
    document.getElementById('actu-titre').classList.remove('is-invalid');

    if (mode === 'edit' && a) {
      form.action = base + '/gestionnaire/actualites/' + a.id;
      document.getElementById('actu-eyebrow').textContent = 'Modification';
      document.getElementById('actu-title').textContent   = "Modifier l'article";
      document.getElementById('actu-submit').textContent  = 'Enregistrer';
      document.getElementById('actu-titre').value     = a.titre     || '';
      document.getElementById('actu-categorie').value = a.categorie || '';
      document.getElementById('actu-date').value      = a.date_publication || '';
      document.getElementById('actu-extrait').value   = a.extrait   || '';
      document.getElementById('actu-contenu').value   = a.contenu   || '';
      document.getElementById('actu-une').checked     = (a.a_la_une == 1);
      var statut = (a.statut === 'publie') ? 'publie' : 'brouillon';
      form.querySelector('input[name="statut"][value="' + statut + '"]').checked = true;
      if (keepNote) keepNote.hidden = !a.image_cle;
      dTitle.textContent = a.image_cle ? 'Remplacer l\'illustration' : DEFAULT_TITLE;
    } else {
      form.action = STORE_URL;
      form.reset();
      document.getElementById('actu-eyebrow').textContent = 'Nouvelle publication';
      document.getElementById('actu-title').textContent   = 'Nouvel article';
      document.getElementById('actu-submit').textContent  = "Créer l'article";
      document.getElementById('actu-date').value = new Date().toISOString().slice(0, 10);
      if (keepNote) keepNote.hidden = true;
    }

    openModal('modal-actu');
    setTimeout(function () { document.getElementById('actu-titre').focus(); }, 60);
  };
})();
</script>
