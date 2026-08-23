<?php
// Variables: $formations, $etab
$formations = $formations ?? [];
$statutBadge = ['ouverte'=>'g-badge-ouverte','bientot'=>'g-badge-bientot','fermee'=>'g-badge-fermee'];
$statutLabel = ['ouverte'=>'Ouverte','bientot'=>'Bientôt','fermee'=>'Fermée'];
?>

<style>
/* ── Formulaire formation — redesign (design system Connect'Academia) ── */
#modal-add-formation .g-modal,
#modal-edit-formation .g-modal { max-width:560px; }
.ff-form { display:block; }
.ff-sec { padding:18px 0; border-top:1px solid var(--border); }
.ff-sec:first-child { padding-top:2px; border-top:none; }
.ff-eyebrow {
  font-size:10.5px; text-transform:uppercase; letter-spacing:.08em;
  color:var(--ink-3); font-weight:700; margin:0 0 14px;
  display:flex; align-items:center; gap:8px;
}
.ff-eyebrow::after { content:''; flex:1; height:1px; background:var(--border); }
.ff-form .g-form-group:last-child { margin-bottom:0; }
.ff-opt {
  font-size:9.5px; font-weight:700; letter-spacing:.04em; text-transform:uppercase;
  color:var(--ap-deep); background:var(--ap-tint);
  padding:2px 7px; border-radius:var(--r-pill); margin-left:6px;
}
.ff-form .g-form-hint a { color:var(--ap-deep); font-weight:600; text-decoration:none; }
.ff-form .g-form-hint a:hover { text-decoration:underline; }
.ff-req { color:var(--ap); font-weight:700; }
/* Statut — contrôle segmenté */
.ff-seg { display:grid; grid-template-columns:repeat(3,1fr); gap:6px; }
.ff-seg-opt input { position:absolute; opacity:0; width:0; height:0; }
.ff-seg-opt span {
  display:flex; align-items:center; justify-content:center; gap:6px;
  padding:9.5px 6px; font-size:12px; font-weight:600; color:var(--ink-2);
  border:1px solid var(--bdr-s); border-radius:var(--r-md);
  background:rgba(255,255,255,.6); cursor:pointer;
  transition:color 150ms, background 150ms, border-color 150ms, box-shadow 150ms;
}
.ff-seg-opt span::before {
  content:''; width:7px; height:7px; border-radius:50%;
  background:currentColor; opacity:.45; transition:opacity 150ms;
}
.ff-seg-opt span:hover { border-color:var(--ap); color:var(--ap-deep); }
.ff-seg-opt input:checked + span {
  color:#fff; border-color:var(--ap-deep);
  background:linear-gradient(180deg,#9663FB,var(--ap) 55%,var(--ap-deep));
  box-shadow:0 5px 16px var(--ap-glow), inset 0 1px 0 rgba(255,255,255,.3);
}
.ff-seg-opt input:checked + span::before { opacity:1; background:#fff; }
.ff-seg-opt input:focus-visible + span { box-shadow:0 0 0 3px var(--ap-glow); }
/* Aperçu prix */
.ff-price-hint { font-size:11.5px; color:var(--ink-3); margin-top:5px; }
.ff-price-hint strong { color:var(--ink); font-weight:700; }
</style>

<div class="g-page-header">
  <div>
    <h2>Formations</h2>
    <p><?= count($formations) ?> filière<?= count($formations) > 1 ? 's' : '' ?> configurée<?= count($formations) > 1 ? 's' : '' ?></p>
  </div>
  <button class="g-btn g-btn-primary" onclick="openModal('modal-add-formation')">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v8m-4-4h8"/></svg>
    Ajouter
  </button>
</div>

<div class="g-card" style="padding:0;overflow:hidden;">
<?php if (empty($formations)): ?>
  <div class="g-empty">
    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M12 14l9-5-9-5-9 5 9 5z"/></svg>
    <p>Aucune formation configurée.</p>
    <button class="g-btn g-btn-primary" onclick="openModal('modal-add-formation')" style="margin-top:12px;">Ajouter la première formation</button>
  </div>
<?php else: ?>
  <table class="g-table">
    <thead>
      <tr>
        <th>Intitulé</th>
        <th>Niveau</th>
        <th>Durée</th>
        <th>Prix</th>
        <th>Statut</th>
        <th>Ordre</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($formations as $f): ?>
      <tr>
        <td>
          <div style="font-weight:600;font-size:13px;"><?= e($f['intitule']) ?></div>
          <?php if (!empty($f['icone'])): ?>
            <div style="font-size:11px;color:var(--ink-3);">icon: <?= e($f['icone']) ?></div>
          <?php endif; ?>
        </td>
        <td style="font-size:12px;color:var(--ink-2);"><?= e($f['niveau'] ?? '—') ?></td>
        <td style="font-size:12px;color:var(--ink-2);"><?= e($f['duree_texte'] ?? '—') ?></td>
        <td style="font-size:13px;font-weight:600;"><?= number_format((int)$f['prix']) ?> <span style="font-size:11px;font-weight:400;color:var(--ink-3);"><?= e($f['prix_unite']) ?></span></td>
        <td><span class="g-badge <?= $statutBadge[$f['statut']] ?? 'g-badge-brouillon' ?>"><?= $statutLabel[$f['statut']] ?? $f['statut'] ?></span></td>
        <td style="font-size:12px;color:var(--ink-3);"><?= (int)$f['ordre'] ?></td>
        <td style="display:flex;gap:6px;">
          <button class="g-btn g-btn-ghost g-btn-sm" onclick="openEditFormation(<?= htmlspecialchars(json_encode($f), ENT_QUOTES) ?>)">Modifier</button>
          <form action="<?= url('/gestionnaire/formations/' . $f['id'] . '/delete') ?>" method="post" onsubmit="return confirm('Supprimer cette formation ?')">
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

<!-- Modal : Ajouter formation -->
<div class="g-modal-overlay" id="modal-add-formation">
  <div class="g-modal">
    <div class="g-modal-head">
      <h2>Ajouter une formation</h2>
      <button class="g-modal-close" onclick="closeModal('modal-add-formation')">×</button>
    </div>
    <form action="<?= url('/gestionnaire/formations') ?>" method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
      <?= _formationFields([]) ?>
      <div style="display:flex;gap:10px;margin-top:20px;">
        <button type="submit" class="g-btn g-btn-primary">Créer</button>
        <button type="button" class="g-btn g-btn-ghost" onclick="closeModal('modal-add-formation')">Annuler</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal : Modifier formation -->
<div class="g-modal-overlay" id="modal-edit-formation">
  <div class="g-modal">
    <div class="g-modal-head">
      <h2>Modifier la formation</h2>
      <button class="g-modal-close" onclick="closeModal('modal-edit-formation')">×</button>
    </div>
    <form id="form-edit-formation" method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
      <div id="edit-formation-fields"></div>
      <div style="display:flex;gap:10px;margin-top:20px;">
        <button type="submit" class="g-btn g-btn-primary">Enregistrer</button>
        <button type="button" class="g-btn g-btn-ghost" onclick="closeModal('modal-edit-formation')">Annuler</button>
      </div>
    </form>
  </div>
</div>

<?php
function _formationFields(array $f, string $prefix = ''): string {
    $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    // Champ input générique — libellé, nom, valeur, type, placeholder, hint HTML
    $field = function(string $lbl, string $name, $val, string $type='text',
                      string $ph='', string $hint='', string $extra='') use ($e): string {
        $req  = str_replace('*', '<span class="ff-req">*</span>', $e($lbl));
        $phA  = $ph !== '' ? ' placeholder="'.$e($ph).'"' : '';
        $reqA = $type === 'number' ? ' min="0"' : '';
        $hintH= $hint !== '' ? '<p class="g-form-hint">'.$hint.'</p>' : '';
        return "
        <div class=\"g-form-group\">
          <label>{$req}</label>
          <input type=\"{$type}\" name=\"{$name}\" value=\"{$e($val)}\"{$phA}{$reqA}{$extra}>
          {$hintH}
        </div>";
    };
    // Statut — contrôle segmenté (radios)
    $cur = $f['statut'] ?? 'ouverte';
    $seg = '';
    foreach (['ouverte'=>'Ouverte','bientot'=>'Bientôt','fermee'=>'Fermée'] as $val=>$lbl) {
        $ck = $cur === $val ? ' checked' : '';
        $seg .= "<label class=\"ff-seg-opt\"><input type=\"radio\" name=\"statut\" value=\"{$val}\"{$ck}><span>{$lbl}</span></label>";
    }

    return "
      <div class=\"ff-form\">
        <section class=\"ff-sec\">
          <p class=\"ff-eyebrow\">Identité</p>
          {$field('Intitulé de la formation *','intitule',$f['intitule']??'','text','Ex. Licence en Informatique','Le nom affiché aux candidats sur la page de l\'établissement.')}
          <div class=\"g-form-row\">
            {$field('Niveau','niveau',$f['niveau']??'','text','Ex. Licence 3, Master, BTS')}
            {$field('Icône Lucide','icone',$f['icone']??'','text','Ex. graduation-cap','Nom d\'une icône sur <a href=\"https://lucide.dev/icons\" target=\"_blank\" rel=\"noopener\">lucide.dev</a>.')}
          </div>
        </section>
        <section class=\"ff-sec\">
          <p class=\"ff-eyebrow\">Présentation <span class=\"ff-opt\">Optionnel</span></p>
          <div class=\"g-form-group\">
            <label>Description de la formation</label>
            <textarea name=\"description\" rows=\"3\" placeholder=\"Un court paragraphe de presentation de la formation.\" style=\"width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:var(--font);font-size:13px;resize:vertical;\">{$e($f['description']??'')}</textarea>
          </div>
          <div class=\"g-form-group\">
            <label>Image de la formation</label>
            <input type=\"file\" name=\"image\" accept=\"image/jpeg,image/png,image/webp\" style=\"width:100%;padding:8px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:var(--font);font-size:12px;\">
            <p class=\"g-form-hint\">Format horizontal recommande, 2 Mo maximum.</p>
          </div>
        </section>
        <section class=\"ff-sec\">
          <p class=\"ff-eyebrow\">Détails pratiques</p>
          <div class=\"g-form-row\">
            {$field('Durée','duree_texte',$f['duree_texte']??'','text','Ex. 3 ans')}
            {$field('Places','places_texte',$f['places_texte']??'','text','Ex. 40 places')}
          </div>
        </section>
        <section class=\"ff-sec\">
          <p class=\"ff-eyebrow\">Tarification</p>
          <div class=\"g-form-row\">
            {$field('Frais de scolarité','prix',(string)(int)($f['prix']??0),'number','0')}
            {$field('Unité','prix_unite',$f['prix_unite']??'FCFA/an','text','FCFA/an')}
          </div>
        </section>
        <section class=\"ff-sec\">
          <p class=\"ff-eyebrow\">Fiche détaillée <span class=\"ff-opt\">Optionnel</span></p>
          <div class=\"g-form-group\">
            <label>Débouchés &amp; opportunités</label>
            <textarea name=\"debouches\" rows=\"3\" placeholder=\"Une idée par ligne, ex: Responsable logistique\" style=\"width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:var(--font);font-size:13px;resize:vertical;\">{$e($f['debouches']??'')}</textarea>
            <p class=\"g-form-hint\">Une idée par ligne. Commence une ligne par \"-\" pour un affichage en liste sur la fiche publique.</p>
          </div>
          <div class=\"g-form-group\">
            <label>Avantages du programme</label>
            <textarea name=\"avantages\" rows=\"3\" placeholder=\"Ex: Stage garanti, corps professoral international\" style=\"width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:var(--font);font-size:13px;resize:vertical;\">{$e($f['avantages']??'')}</textarea>
          </div>
        </section>
        <section class=\"ff-sec\">
          <p class=\"ff-eyebrow\">Publication</p>
          <div class=\"g-form-row\">
            <div class=\"g-form-group\">
              <label>Statut des candidatures</label>
              <div class=\"ff-seg\" role=\"radiogroup\" aria-label=\"Statut\">{$seg}</div>
            </div>
            {$field('Ordre d\'affichage','ordre',(string)(int)($f['ordre']??0),'number','0','Plus petit = affiché en premier.')}
          </div>
        </section>
      </div>";
}
?>

<script>
function openEditFormation(f) {
  const base = window.CA_GEST.baseUrl;
  document.getElementById('form-edit-formation').action = base + '/gestionnaire/formations/' + f.id;
  document.getElementById('edit-formation-fields').innerHTML = buildFormationFields(f);
  openModal('modal-edit-formation');
}
function buildFormationFields(f) {
  const e = v => (v ?? '').toString().replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;');
  const field = (lbl,name,val,type='text',ph='',hint='') => {
    const label = lbl.replace('*','<span class="ff-req">*</span>');
    const min   = type === 'number' ? ' min="0"' : '';
    const phA   = ph ? ` placeholder="${e(ph)}"` : '';
    const hintH = hint ? `<p class="g-form-hint">${hint}</p>` : '';
    return `
    <div class="g-form-group">
      <label>${label}</label>
      <input type="${type}" name="${name}" value="${e(val)}"${phA}${min}>
      ${hintH}
    </div>`;
  };
  const statuts = {ouverte:'Ouverte',bientot:'Bientôt',fermee:'Fermée'};
  const cur = f.statut || 'ouverte';
  const seg = Object.entries(statuts).map(([v,l]) =>
    `<label class="ff-seg-opt"><input type="radio" name="statut" value="${v}"${cur===v?' checked':''}><span>${l}</span></label>`).join('');
  return `
  <div class="ff-form">
    <section class="ff-sec">
      <p class="ff-eyebrow">Identité</p>
      ${field('Intitulé de la formation *','intitule',f.intitule,'text','Ex. Licence en Informatique','Le nom affiché aux candidats sur la page de l\'établissement.')}
      <div class="g-form-row">
        ${field('Niveau','niveau',f.niveau,'text','Ex. Licence 3, Master, BTS')}
        ${field('Icône Lucide','icone',f.icone,'text','Ex. graduation-cap','Nom d\'une icône sur <a href="https://lucide.dev/icons" target="_blank" rel="noopener">lucide.dev</a>.')}
      </div>
    </section>
    <section class="ff-sec">
      <p class="ff-eyebrow">Présentation <span class="ff-opt">Optionnel</span></p>
      <div class="g-form-group">
        <label>Description de la formation</label>
        <textarea name="description" rows="3" placeholder="Un court paragraphe de presentation de la formation." style="width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:var(--font);font-size:13px;resize:vertical;">${e(f.description)}</textarea>
      </div>
      <div class="g-form-group">
        <label>Image de la formation</label>
        <input type="file" name="image" accept="image/jpeg,image/png,image/webp" style="width:100%;padding:8px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:var(--font);font-size:12px;">
        <p class="g-form-hint">${f.image_cle ? "Une image est deja en ligne, un nouvel envoi la remplace." : "Format horizontal recommande, 2 Mo maximum."}</p>
      </div>
    </section>
    <section class="ff-sec">
      <p class="ff-eyebrow">Détails pratiques</p>
      <div class="g-form-row">
        ${field('Durée','duree_texte',f.duree_texte,'text','Ex. 3 ans')}
        ${field('Places','places_texte',f.places_texte,'text','Ex. 40 places')}
      </div>
    </section>
    <section class="ff-sec">
      <p class="ff-eyebrow">Tarification</p>
      <div class="g-form-row">
        ${field('Frais de scolarité','prix',f.prix,'number','0')}
        ${field('Unité','prix_unite',f.prix_unite||'FCFA/an','text','FCFA/an')}
      </div>
    </section>
    <section class="ff-sec">
      <p class="ff-eyebrow">Fiche détaillée <span class="ff-opt">Optionnel</span></p>
      <div class="g-form-group">
        <label>Débouchés &amp; opportunités</label>
        <textarea name="debouches" rows="3" placeholder="Une idée par ligne, ex: Responsable logistique" style="width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:var(--font);font-size:13px;resize:vertical;">${e(f.debouches)}</textarea>
        <p class="g-form-hint">Une idée par ligne. Commence une ligne par "-" pour un affichage en liste sur la fiche publique.</p>
      </div>
      <div class="g-form-group">
        <label>Avantages du programme</label>
        <textarea name="avantages" rows="3" placeholder="Ex: Stage garanti, corps professoral international" style="width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:var(--font);font-size:13px;resize:vertical;">${e(f.avantages)}</textarea>
      </div>
    </section>
    <section class="ff-sec">
      <p class="ff-eyebrow">Publication</p>
      <div class="g-form-row">
        <div class="g-form-group">
          <label>Statut des candidatures</label>
          <div class="ff-seg" role="radiogroup" aria-label="Statut">${seg}</div>
        </div>
        ${field('Ordre d\'affichage','ordre',f.ordre,'number','0','Plus petit = affiché en premier.')}
      </div>
    </section>
  </div>`;
}
</script>
