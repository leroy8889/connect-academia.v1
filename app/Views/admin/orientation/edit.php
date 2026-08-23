<?php
// Variables: $etab (array), $formations (array), $old (array), $errors (array)
$etab       = $etab ?? [];
$formations = $formations ?? [];
$old        = $old ?? [];
$errors     = $errors ?? [];

// La vue est incluse dans la portée de Response::view() — `global` n'y voit rien.
// On passe par $GLOBALS pour que les helpers lisent les vraies données.
$GLOBALS['__or_old']    = $old;
$GLOBALS['__or_errors'] = $errors;

if (!function_exists('old')) {
    function old(string $key, string $default = ''): string {
        $old = $GLOBALS['__or_old'] ?? [];
        return htmlspecialchars((string)($old[$key] ?? $default), ENT_QUOTES);
    }
}
if (!function_exists('err')) {
    function err(string $key): string {
        $errors = $GLOBALS['__or_errors'] ?? [];
        if (!isset($errors[$key])) return '';
        return '<p class="or-field-error">' . htmlspecialchars($errors[$key], ENT_QUOTES) . '</p>';
    }
}
if (!function_exists('hasErr')) {
    function hasErr(string $key): string {
        $errors = $GLOBALS['__or_errors'] ?? [];
        return isset($errors[$key]) ? ' or-input-error' : '';
    }
}

// Valeur DB comme défaut : $old (re-soumission échouée) prioritaire
if (!function_exists('etabVal')) {
    function etabVal(string $key): string {
        return (string)($GLOBALS['__or_etab'][$key] ?? '');
    }
}
$GLOBALS['__or_etab'] = $etab;

// Pré-remplissage des listes : old prioritaire, sinon JSON en base
$badgesPrefill = isset($old['badges'])
    ? array_values(array_filter(array_map('trim', (array)$old['badges'])))
    : (json_decode($etab['badges_confiance'] ?? '[]', true) ?: []);
$atoutsPrefill = isset($old['atouts'])
    ? array_values(array_filter(array_map('trim', (array)$old['atouts'])))
    : (json_decode($etab['atouts'] ?? '[]', true) ?: []);
$modesPrefill = isset($old['mode_paiement'])
    ? array_values(array_filter(array_map('trim', (array)$old['mode_paiement'])))
    : (json_decode($etab['infos_paiement'] ?? '[]', true) ?: []);

$statsPrefill = [];
if (isset($old['stat_label'])) {
    foreach ((array)$old['stat_label'] as $i => $label) {
        $label = trim((string)$label);
        $val   = trim((string)($old['stat_valeur'][$i] ?? ''));
        if ($label !== '' || $val !== '') $statsPrefill[] = ['label' => $label, 'valeur' => $val];
    }
} else {
    $statsPrefill = json_decode($etab['statistiques'] ?? '[]', true) ?: [];
}

// Formations : old prioritaire (avec id conservé), sinon lignes DB
$formationsPrefill = [];
if (isset($old['formations'])) {
    foreach ((array)$old['formations'] as $f) {
        $formationsPrefill[] = [
            'id'          => (int)($f['id'] ?? 0),
            'intitule'    => trim($f['intitule'] ?? ''),
            'niveau'      => trim($f['niveau'] ?? ''),
            'icone'       => trim($f['icone'] ?? ''),
            'duree_texte' => trim($f['duree_texte'] ?? ''),
            'places_texte'=> trim($f['places_texte'] ?? ''),
            'prix'        => trim((string)($f['prix'] ?? '')),
            'statut'      => $f['statut'] ?? 'ouverte',
        ];
    }
} else {
    foreach ($formations as $f) {
        $formationsPrefill[] = [
            'id'          => (int)$f['id'],
            'intitule'    => (string)$f['intitule'],
            'niveau'      => (string)($f['niveau'] ?? ''),
            'icone'       => (string)($f['icone'] ?? ''),
            'duree_texte' => (string)($f['duree_texte'] ?? ''),
            'places_texte'=> (string)($f['places_texte'] ?? ''),
            'prix'        => (string)($f['prix'] ?? ''),
            'statut'      => (string)($f['statut'] ?? 'ouverte'),
        ];
    }
}

// Cases à cocher : old si re-soumission, sinon valeur DB
$preinsChecked = isset($old['nom'])
    ? isset($old['preinscription_active'])
    : (bool)($etab['preinscription_active'] ?? 1);
$recepChecked = isset($old['nom'])
    ? isset($old['genere_recepisse'])
    : (bool)($etab['genere_recepisse'] ?? 1);
$attestChecked = isset($old['nom'])
    ? isset($old['genere_attestation'])
    : (bool)($etab['genere_attestation'] ?? 1);

$imagesActuelles = json_decode($etab['images_cles'] ?? '[]', true) ?: [];
?>

<style>
/* ── Layout formulaire (identique à la création) ───────── */
.or-form-layout { display: grid; grid-template-columns: 220px 1fr; gap: 28px; align-items: start; }
.or-nav {
    position: sticky; top: calc(var(--topbar-h) + 28px);
    background: #fff; border-radius: 16px; border: 1px solid var(--border);
    box-shadow: var(--shadow-sm); padding: 12px 0;
}
.or-nav a {
    display: flex; align-items: center; gap: 10px; padding: 10px 20px;
    font-size: 13px; font-weight: 500; color: var(--txt-m); text-decoration: none;
    border-left: 3px solid transparent; transition: all 0.15s;
}
.or-nav a:hover  { color: var(--ap); background: var(--ap-lt); }
.or-nav a.active { color: var(--ap); border-left-color: var(--ap); background: var(--ap-xl); font-weight: 600; }
.or-nav .nav-badge {
    margin-left: auto; background: var(--red-bg); color: var(--red);
    border-radius: 999px; font-size: 10px; font-weight: 700; padding: 2px 7px;
}

.or-section {
    background: #fff; border-radius: 16px; border: 1px solid var(--border);
    box-shadow: var(--shadow-sm); margin-bottom: 20px;
    scroll-margin-top: calc(var(--topbar-h) + 28px);
}
.or-section-header { display: flex; align-items: center; gap: 14px; padding: 20px 24px; border-bottom: 1px solid var(--border); }
.or-section-icon {
    width: 40px; height: 40px; background: var(--ap-lt); border-radius: 10px;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: var(--ap);
}
.or-section-title { font-size: 15px; font-weight: 600; color: var(--txt); margin: 0; }
.or-section-sub   { font-size: 12px; color: var(--txt-l); margin: 2px 0 0; }
.or-section-body  { padding: 24px; }
.or-section-body .or-optional {
    display: inline-block; font-size: 10px; font-weight: 600;
    background: var(--border); color: var(--txt-l); border-radius: 999px;
    padding: 2px 8px; text-transform: uppercase; letter-spacing: 0.06em;
    margin-left: 8px; vertical-align: middle;
}

.or-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.or-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
/* Intertitre de sous-groupe dans une section (ex. grille de tarifs) */
.or-subhead {
    font-size: 12px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase;
    color: var(--txt-m); padding-top: 4px; border-top: 1px solid var(--border);
    margin-bottom: 8px;
}
.form-group { margin-bottom: 16px; }
.form-group:last-child { margin-bottom: 0; }
.form-label {
    display: block; font-size: 12px; font-weight: 600; color: var(--txt-m);
    margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.05em;
}
.form-label span { color: var(--red); margin-left: 2px; }
.form-input, .form-select, .form-textarea {
    width: 100%; padding: 10px 14px; border: 1.5px solid var(--border-strong);
    border-radius: 10px; font-size: 14px; font-family: inherit; color: var(--txt);
    background: #fff; transition: border-color 0.15s; box-sizing: border-box;
}
.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none; border-color: var(--ap); box-shadow: 0 0 0 3px var(--ap-glow-20);
}
.form-textarea { resize: vertical; min-height: 90px; }
.or-input-error { border-color: var(--red) !important; }
.or-field-error { font-size: 12px; color: var(--red); margin: 4px 0 0; }

.or-color-wrap { display: flex; align-items: center; gap: 12px; }
.or-color-preview {
    width: 40px; height: 40px; border-radius: 8px;
    border: 1.5px solid var(--border-strong); cursor: pointer; flex-shrink: 0;
}
input[type="color"] { width: 0; height: 0; opacity: 0; position: absolute; }

.or-upload-zone {
    border: 2px dashed var(--border-strong); border-radius: 12px; padding: 24px;
    text-align: center; cursor: pointer; transition: all 0.15s; position: relative;
}
.or-upload-zone:hover { border-color: var(--ap); background: var(--ap-xl); }
.or-upload-zone input[type="file"] { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
.or-upload-icon { color: var(--ap); margin-bottom: 8px; }
.or-upload-label { font-size: 13px; font-weight: 500; color: var(--txt-m); }
.or-upload-hint  { font-size: 11px; color: var(--txt-l); margin-top: 4px; }
.or-upload-preview { max-height: 100px; border-radius: 8px; margin-top: 12px; display: none; }
.or-commission-preview {
    margin-top: 8px; padding: 8px 12px; border-radius: 8px;
    background: var(--ap-lt); color: var(--ap);
    font-size: 11.5px; font-weight: 500; line-height: 1.5;
    font-variant-numeric: tabular-nums;
}
.or-commission-preview strong { font-weight: 800; }
.or-commission-preview[hidden] { display: none; }

.or-formation-row {
    background: var(--ap-xl); border: 1px solid var(--border); border-radius: 12px;
    padding: 16px; margin-bottom: 12px; position: relative;
}
.or-formation-row .row-num {
    position: absolute; top: 12px; left: 16px;
    font-size: 11px; font-weight: 700; color: var(--ap);
    text-transform: uppercase; letter-spacing: 0.06em;
}
.or-btn-remove {
    position: absolute; top: 10px; right: 12px;
    background: var(--red-bg); color: var(--red); border: none; border-radius: 6px;
    padding: 4px 8px; font-size: 12px; cursor: pointer; font-weight: 600;
}
.or-btn-remove:hover { background: var(--red); color: #fff; }

.or-tag-list { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 10px; }
.or-tag {
    display: flex; align-items: center; gap: 6px;
    background: var(--ap-lt); color: var(--ap); border-radius: 999px;
    padding: 5px 12px; font-size: 13px; font-weight: 500;
}
.or-tag-del { background: none; border: none; cursor: pointer; color: var(--ap); font-size: 16px; line-height: 1; padding: 0; }
.or-add-row { display: flex; gap: 8px; }
.or-add-input { flex: 1; }
.btn-add-tag {
    flex-shrink: 0; background: var(--ap); color: #fff; border: none; border-radius: 10px;
    padding: 10px 16px; font-size: 13px; font-weight: 600; cursor: pointer;
}
.btn-add-tag:hover { background: var(--ap-dk); }

.or-stat-row { display: grid; grid-template-columns: 1fr 1fr auto; gap: 8px; align-items: center; margin-bottom: 8px; }

.or-check-group { display: flex; gap: 20px; flex-wrap: wrap; }
.or-check-label { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 500; color: var(--txt-m); cursor: pointer; }
.or-check-label input[type="checkbox"] { accent-color: var(--ap); width: 16px; height: 16px; }

.or-form-footer {
    position: sticky; bottom: 0;
    background: rgba(245,245,247,0.95); backdrop-filter: blur(8px);
    border-top: 1px solid var(--border); padding: 16px 0; margin-top: 8px;
    display: flex; justify-content: flex-end; gap: 12px; z-index: 10;
}

.or-media-actuel {
    display: flex; align-items: center; gap: 10px;
    background: var(--ap-xl); border-radius: 10px; padding: 10px 14px;
    font-size: 12px; color: var(--txt-m); margin-bottom: 10px;
}

@media (max-width: 900px) {
    .or-form-layout { grid-template-columns: 1fr; }
    .or-nav { position: static; }
    .or-grid-2, .or-grid-3 { grid-template-columns: 1fr; }
}
</style>

<div class="admin-page-header-row">
    <div>
        <h1>Modifier l'établissement</h1>
        <p><?= e($etab['nom']) ?> — le slug <code>/orientation/<?= e($etab['slug']) ?></code> reste inchangé</p>
    </div>
    <div style="display:flex;gap:10px;">
        <a href="<?= url('/admin/orientation/' . $etab['id'] . '/voir') ?>" class="btn-outline">Voir la fiche</a>
        <a href="<?= url('/admin/orientation') ?>" class="btn-outline">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px;vertical-align:-1px">
                <polyline points="15 18 9 12 15 6"/>
            </svg>
            Retour à la liste
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="flash-banner flash-error" style="margin-bottom:20px;border-radius:12px;padding:14px 20px;font-size:14px;background:var(--red-bg);color:#b91c1c;border:1px solid #fca5a5;">
    <strong><?= count($errors) ?> erreur(s) à corriger avant de soumettre :</strong>
    <ul style="margin:8px 0 0;padding-left:20px;">
        <?php foreach ($errors as $msg): ?>
        <li><?= htmlspecialchars((string)$msg, ENT_QUOTES) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form method="POST" action="<?= url('/admin/orientation/' . $etab['id'] . '/modifier') ?>" enctype="multipart/form-data" id="or-edit-form" data-presign-url="<?= url('/admin/orientation/presign') ?>" novalidate>
    <input type="hidden" name="_csrf_token" value="<?= e(\Core\Session::getCsrfToken()) ?>">

    <div class="or-form-layout">

        <!-- ── Navigation latérale ───────────────────────────────────── -->
        <nav class="or-nav" id="or-nav">
            <?php
            $navItems = [
                'identite'    => ['Identité',    true],
                'medias'      => ['Médias',       false],
                'accroche'    => ['Accroche',     false],
                'presentation'=> ['Présentation', false],
                'atouts'      => ['Atouts',       false],
                'contact'     => ['Contact',      true],
                'tarifs'      => ['Tarifs',       true],
                'formations'  => ['Formations',   true],
            ];
            foreach ($navItems as $id => [$label, $required]):
                $hasError = array_filter(array_keys($errors), fn($k) => str_contains($k, $id) || ($id === 'identite' && in_array($k, ['nom','type','ville','couleur_principale'])) || ($id === 'medias' && in_array($k, ['logo','images_campus','video'])) || ($id === 'formations' && $k === 'formations') || ($id === 'contact' && in_array($k, ['email_contact','map_embed'])));
            ?>
            <a href="#sec-<?= $id ?>" class="<?= !empty($hasError) ? 'active' : '' ?>">
                <?= $label ?>
                <?php if ($required): ?><span style="color:var(--red);margin-left:2px;font-size:10px;">*</span><?php endif; ?>
                <?php if (!empty($hasError)): ?><span class="nav-badge">!</span><?php endif; ?>
            </a>
            <?php endforeach; ?>
        </nav>

        <!-- ── Contenu du formulaire ─────────────────────────────────── -->
        <div>

            <!-- §1 Identité -->
            <section class="or-section" id="sec-identite">
                <div class="or-section-header">
                    <div class="or-section-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="or-section-title">Identité</p>
                        <p class="or-section-sub">Nom, type et couleur de marque</p>
                    </div>
                </div>
                <div class="or-section-body">
                    <div class="or-grid-2">
                        <div class="form-group">
                            <label class="form-label" for="nom">Nom officiel <span>*</span></label>
                            <input type="text" id="nom" name="nom" class="form-input<?= hasErr('nom') ?>"
                                   value="<?= old('nom', etabVal('nom')) ?>" required>
                            <?= err('nom') ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="type">Type <span>*</span></label>
                            <select id="type" name="type" class="form-select<?= hasErr('type') ?>" required>
                                <option value="">— Choisir —</option>
                                <?php foreach(['universite'=>'Université','grande_ecole'=>'Grande école','institut'=>'Institut'] as $v=>$l): ?>
                                <option value="<?= $v ?>" <?= old('type', etabVal('type')) === $v ? 'selected' : '' ?>><?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= err('type') ?>
                        </div>
                    </div>
                    <div class="or-grid-2">
                        <div class="form-group">
                            <label class="form-label" for="ville">Ville <span>*</span></label>
                            <input type="text" id="ville" name="ville" class="form-input<?= hasErr('ville') ?>"
                                   value="<?= old('ville', etabVal('ville')) ?>" required>
                            <?= err('ville') ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="statut">Statut</label>
                            <select id="statut" name="statut" class="form-select">
                                <?php foreach (['brouillon'=>'Brouillon','actif'=>'Actif','inactif'=>'Inactif'] as $v=>$l): ?>
                                <option value="<?= $v ?>" <?= old('statut', etabVal('statut')) === $v ? 'selected' : '' ?>><?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Couleur principale de marque</label>
                        <div class="or-color-wrap">
                            <div class="or-color-preview" id="color-preview" style="background:<?= old('couleur_principale', etabVal('couleur_principale') ?: '#1F4E79') ?>" onclick="document.getElementById('color-picker').click()"></div>
                            <input type="color" id="color-picker" name="couleur_principale"
                                   value="<?= old('couleur_principale', etabVal('couleur_principale') ?: '#1F4E79') ?>"
                                   oninput="document.getElementById('color-preview').style.background=this.value">
                            <div>
                                <div style="font-size:13px;font-weight:600;color:var(--txt)" id="color-hex"><?= old('couleur_principale', etabVal('couleur_principale') ?: '#1F4E79') ?></div>
                                <div style="font-size:11px;color:var(--txt-l)">Injecté dans <code>--brand</code> du template</div>
                            </div>
                        </div>
                    </div>
                    <div class="or-check-group">
                        <label class="or-check-label">
                            <input type="checkbox" name="preinscription_active" value="1" <?= $preinsChecked ? 'checked' : '' ?>>
                            Préinscription activée
                        </label>
                        <label class="or-check-label">
                            <input type="checkbox" name="genere_recepisse" value="1" <?= $recepChecked ? 'checked' : '' ?>>
                            Génère un récépissé PDF
                        </label>
                        <label class="or-check-label">
                            <input type="checkbox" name="genere_attestation" value="1" <?= $attestChecked ? 'checked' : '' ?>>
                            Génère une attestation d'inscription
                        </label>
                    </div>
                </div>
            </section>

            <!-- §2 Médias -->
            <section class="or-section" id="sec-medias">
                <div class="or-section-header">
                    <div class="or-section-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/>
                            <polyline points="21 15 16 10 5 21"/>
                        </svg>
                    </div>
                    <div>
                        <p class="or-section-title">Médias</p>
                        <p class="or-section-sub">Laisse vide pour conserver les fichiers actuels</p>
                    </div>
                </div>
                <div class="or-section-body">
                    <div class="or-grid-2">
                        <div class="form-group">
                            <label class="form-label">Logo</label>
                            <?php if (!empty($etab['logo_cle'])): ?>
                            <div class="or-media-actuel">
                                ✓ Logo actuel : <code style="font-size:11px;"><?= e($etab['logo_cle']) ?></code>
                            </div>
                            <?php endif; ?>
                            <div class="or-upload-zone<?= hasErr('logo') ?>">
                                <input type="file" name="logo" accept="image/jpeg,image/png,image/webp"
                                       onchange="previewImg(this,'logo-prev')">
                                <div class="or-upload-icon">
                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                        <polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                                    </svg>
                                </div>
                                <div class="or-upload-label">Remplacer le logo</div>
                                <div class="or-upload-hint">JPG, PNG, WEBP — max 10 Mo</div>
                                <img id="logo-prev" class="or-upload-preview" alt="Aperçu logo">
                            </div>
                            <?= err('logo') ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Cachet officiel <span class="or-optional">optionnel</span></label>
                            <?php if (!empty($etab['cachet_cle'])): ?>
                            <div class="or-media-actuel">
                                ✓ Cachet actuel : <code style="font-size:11px;"><?= e($etab['cachet_cle']) ?></code>
                            </div>
                            <?php endif; ?>
                            <div class="or-upload-zone<?= hasErr('cachet') ?>">
                                <input type="file" name="cachet" accept="image/jpeg,image/png,image/webp"
                                       onchange="previewImg(this,'cachet-prev')">
                                <div class="or-upload-icon">
                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                        <polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                                    </svg>
                                </div>
                                <div class="or-upload-label">Remplacer le cachet</div>
                                <div class="or-upload-hint">JPG, PNG, WEBP — max 10 Mo — utilisé sur la facture proforma</div>
                                <img id="cachet-prev" class="or-upload-preview" alt="Aperçu cachet">
                            </div>
                            <?= err('cachet') ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Photos campus</label>
                            <?php if ($imagesActuelles): ?>
                            <div class="or-media-actuel">
                                ✓ <?= count($imagesActuelles) ?> photo(s) actuellement en ligne — un nouvel envoi les remplace toutes
                            </div>
                            <?php endif; ?>
                            <div class="or-upload-zone<?= hasErr('images_campus') ?>" id="campus-zone">
                                <input type="file" name="images_campus[]" accept="image/jpeg,image/png,image/webp" multiple
                                       onchange="campusSelected(this)">
                                <div class="or-upload-icon">
                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/>
                                        <polyline points="21 15 16 10 5 21"/>
                                    </svg>
                                </div>
                                <div class="or-upload-label" id="campus-label">Remplacer les photos (jusqu'à 10)</div>
                                <div class="or-upload-hint">JPG, PNG, WEBP — max 10 Mo chacune</div>
                            </div>
                            <?= err('images_campus') ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Vidéo de présentation <span class="or-optional">optionnel</span></label>
                        <?php if (!empty($etab['video_cle'])): ?>
                        <div class="or-media-actuel">
                            ✓ Vidéo actuellement en ligne : <code style="font-size:11px;"><?= e($etab['video_cle']) ?></code> — un nouvel envoi la remplace
                        </div>
                        <?php elseif (!empty($etab['video_url'])): ?>
                        <div class="or-media-actuel">
                            ⚠ Lien YouTube legacy : <code style="font-size:11px;"><?= e($etab['video_url']) ?></code> — envoie un fichier pour le remplacer
                        </div>
                        <?php endif; ?>
                        <div class="or-upload-zone<?= hasErr('video') ?>" id="video-zone">
                            <input type="file" name="video" accept="video/*" onchange="videoSelected(this)">
                            <div class="or-upload-icon">
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <polygon points="23 7 16 12 23 17 23 7"/>
                                    <rect x="1" y="5" width="15" height="14" rx="2"/>
                                </svg>
                            </div>
                            <div class="or-upload-label" id="video-label"><?= !empty($etab['video_cle']) ? 'Remplacer la vidéo' : 'Clique ou glisse la vidéo' ?></div>
                            <div class="or-upload-hint">MP4, WebM, MOV, AVI, MKV, WMV… — max 500 Mo</div>
                        </div>
                        <?= err('video') ?>
                    </div>
                </div>
            </section>

            <!-- §3 Accroche -->
            <section class="or-section" id="sec-accroche">
                <div class="or-section-header">
                    <div class="or-section-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 0-2-2"/>
                        </svg>
                    </div>
                    <div>
                        <p class="or-section-title">Accroche <span class="or-optional">optionnel</span></p>
                        <p class="or-section-sub">Section hero du template — bandeau principal</p>
                    </div>
                </div>
                <div class="or-section-body">
                    <div class="or-grid-2">
                        <div class="form-group">
                            <label class="form-label" for="label_campagne">Label campagne</label>
                            <input type="text" id="label_campagne" name="label_campagne" class="form-input"
                                   value="<?= old('label_campagne', etabVal('label_campagne')) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="date_limite_dossier">Date limite dossier</label>
                            <input type="date" id="date_limite_dossier" name="date_limite_dossier" class="form-input"
                                   value="<?= old('date_limite_dossier', etabVal('date_limite_dossier')) ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="titre_accroche">Titre principal du hero</label>
                        <input type="text" id="titre_accroche" name="titre_accroche" class="form-input"
                               value="<?= old('titre_accroche', etabVal('titre_accroche')) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="sous_titre">Sous-titre</label>
                        <textarea id="sous_titre" name="sous_titre" class="form-textarea"><?= old('sous_titre', etabVal('sous_titre')) ?></textarea>
                    </div>
                </div>
            </section>

            <!-- §4 Présentation -->
            <section class="or-section" id="sec-presentation">
                <div class="or-section-header">
                    <div class="or-section-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="or-section-title">Présentation <span class="or-optional">optionnel</span></p>
                        <p class="or-section-sub">Description et citation — section « À propos »</p>
                    </div>
                </div>
                <div class="or-section-body">
                    <div class="form-group">
                        <label class="form-label" for="description">Description de l'établissement</label>
                        <textarea id="description" name="description" class="form-textarea" rows="4"><?= old('description', etabVal('description')) ?></textarea>
                    </div>
                    <div class="or-grid-3">
                        <div class="form-group" style="grid-column:span 3">
                            <label class="form-label" for="citation_texte">Citation</label>
                            <textarea id="citation_texte" name="citation_texte" class="form-textarea" rows="2"><?= old('citation_texte', etabVal('citation_texte')) ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="citation_auteur">Auteur</label>
                            <input type="text" id="citation_auteur" name="citation_auteur" class="form-input"
                                   value="<?= old('citation_auteur', etabVal('citation_auteur')) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="citation_fonction">Fonction</label>
                            <input type="text" id="citation_fonction" name="citation_fonction" class="form-input"
                                   value="<?= old('citation_fonction', etabVal('citation_fonction')) ?>">
                        </div>
                    </div>
                </div>
            </section>

            <!-- §5 Atouts -->
            <section class="or-section" id="sec-atouts">
                <div class="or-section-header">
                    <div class="or-section-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="10"/>
                        </svg>
                    </div>
                    <div>
                        <p class="or-section-title">Atouts, badges & statistiques <span class="or-optional">optionnel</span></p>
                        <p class="or-section-sub">Éléments de confiance affichés dans le template</p>
                    </div>
                </div>
                <div class="or-section-body">
                    <!-- Badges de confiance -->
                    <div class="form-group">
                        <label class="form-label">Badges de confiance</label>
                        <div class="or-tag-list" id="badges-list">
                            <?php foreach ($badgesPrefill as $b): ?>
                            <div class="or-tag">
                                <input type="hidden" name="badges[]" value="<?= e($b) ?>">
                                <span><?= e($b) ?></span>
                                <button type="button" class="or-tag-del" onclick="removeTag(this)">×</button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="or-add-row">
                            <input type="text" class="form-input or-add-input" id="badge-input"
                                   placeholder="Ex : Accrédité CAMES" maxlength="80"
                                   onkeydown="if(event.key==='Enter'){event.preventDefault();addTag('badge-input','badges[]','badges-list');}">
                            <button type="button" class="btn-add-tag" onclick="addTag('badge-input','badges[]','badges-list')">+</button>
                        </div>
                    </div>
                    <!-- Atouts -->
                    <div class="form-group">
                        <label class="form-label">Points forts</label>
                        <div class="or-tag-list" id="atouts-list">
                            <?php foreach ($atoutsPrefill as $a): ?>
                            <div class="or-tag">
                                <input type="hidden" name="atouts[]" value="<?= e($a) ?>">
                                <span><?= e($a) ?></span>
                                <button type="button" class="or-tag-del" onclick="removeTag(this)">×</button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="or-add-row">
                            <input type="text" class="form-input or-add-input" id="atout-input"
                                   placeholder="Ex : Enseignants qualifiés à 90%" maxlength="120"
                                   onkeydown="if(event.key==='Enter'){event.preventDefault();addTag('atout-input','atouts[]','atouts-list');}">
                            <button type="button" class="btn-add-tag" onclick="addTag('atout-input','atouts[]','atouts-list')">+</button>
                        </div>
                    </div>
                    <!-- Statistiques -->
                    <div class="form-group">
                        <label class="form-label">Statistiques clés</label>
                        <div id="stats-rows">
                            <?php foreach ($statsPrefill as $s): ?>
                            <div class="or-stat-row">
                                <input type="text" name="stat_label[]" class="form-input" value="<?= e($s['label'] ?? '') ?>" placeholder="Ex : Taux d'insertion">
                                <input type="text" name="stat_valeur[]" class="form-input" value="<?= e($s['valeur'] ?? '') ?>" placeholder="Ex : 87%">
                                <button type="button" class="or-btn-remove" style="position:static" onclick="this.parentElement.remove()">×</button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn-outline btn-sm" style="margin-top:8px" onclick="addStatRow()">
                            + Ajouter une stat
                        </button>
                    </div>
                </div>
            </section>

            <!-- §6 Contact -->
            <section class="or-section" id="sec-contact">
                <div class="or-section-header">
                    <div class="or-section-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17.657 16.657 13.414 20.9a1.998 1.998 0 0 1-2.827 0l-4.244-4.243a8 8 0 1 1 11.314 0z"/>
                            <circle cx="12" cy="11" r="3"/>
                        </svg>
                    </div>
                    <div>
                        <p class="or-section-title">Contact</p>
                        <p class="or-section-sub">Adresse et coordonnées de l'établissement</p>
                    </div>
                </div>
                <div class="or-section-body">
                    <div class="form-group">
                        <label class="form-label" for="adresse">Adresse physique</label>
                        <input type="text" id="adresse" name="adresse" class="form-input"
                               value="<?= old('adresse', etabVal('adresse')) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="map_embed">Carte Google Maps <span class="or-optional">optionnel</span></label>
                        <textarea id="map_embed" name="map_embed" class="form-textarea<?= hasErr('map_embed') ?>" rows="4"
                                  style="font-family:monospace;font-size:12px;"
                                  placeholder="Colle ici le code &lt;iframe&gt; complet fourni par Google Maps"><?= old('map_embed', etabVal('map_embed_url')) ?></textarea>
                        <?= err('map_embed') ?>
                        <p style="font-size:12px;color:var(--txt-l);margin:6px 0 0;line-height:1.5;">
                            Sur <strong>Google Maps</strong> : recherche l'établissement → <strong>Partager</strong> →
                            onglet <strong>« Intégrer une carte »</strong> → <strong>Copier le code HTML</strong>, puis colle-le ici.
                            Laisser vide pour retirer la carte de la page publique.
                        </p>
                    </div>
                    <div class="or-grid-3">
                        <div class="form-group">
                            <label class="form-label" for="email_contact">Email</label>
                            <input type="email" id="email_contact" name="email_contact" class="form-input<?= hasErr('email_contact') ?>"
                                   value="<?= old('email_contact', etabVal('email_contact')) ?>" placeholder="contact@ecole.ga">
                            <?= err('email_contact') ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="telephone">Téléphone</label>
                            <input type="tel" id="telephone" name="telephone" class="form-input"
                                   value="<?= old('telephone', etabVal('telephone')) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="whatsapp">WhatsApp</label>
                            <input type="tel" id="whatsapp" name="whatsapp" class="form-input"
                                   value="<?= old('whatsapp', etabVal('whatsapp')) ?>">
                        </div>
                    </div>
                </div>
            </section>

            <!-- §7 Tarifs -->
            <section class="or-section" id="sec-tarifs">
                <div class="or-section-header">
                    <div class="or-section-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                        </svg>
                    </div>
                    <div>
                        <p class="or-section-title">Tarifs & modes de paiement</p>
                        <p class="or-section-sub">Montants en FCFA — 0 = gratuit. Le compte Mobile Money se gère sur la page « Paiement »</p>
                    </div>
                </div>
                <div class="or-section-body">
                    <div class="or-grid-3">
                        <div class="form-group">
                            <label class="form-label" for="prix_preinscription">Prix préinscription (FCFA)</label>
                            <input type="number" id="prix_preinscription" name="prix_preinscription" class="form-input"
                                   min="0" value="<?= old('prix_preinscription', etabVal('prix_preinscription')) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="prix_inscription">Prix inscription (FCFA)</label>
                            <input type="number" id="prix_inscription" name="prix_inscription" class="form-input"
                                   min="0" value="<?= old('prix_inscription', etabVal('prix_inscription')) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="frais_dossier">Frais de dossier (FCFA)</label>
                            <input type="number" id="frais_dossier" name="frais_dossier" class="form-input"
                                   min="0" value="<?= old('frais_dossier', etabVal('frais_dossier')) ?>">
                        </div>
                    </div>

                    <!-- Scolarité annuelle par cycle × statut boursier -->
                    <div class="or-subhead" style="margin-top:22px;">Frais de scolarité annuels</div>
                    <div class="or-upload-hint" style="margin:-4px 0 12px;">
                        Un seul tarif ? Renseignez uniquement la colonne « Non boursier » et laissez
                        « Boursier » à 0 : la page publique n'affichera alors qu'un montant, comme aujourd'hui.
                        La commission plateforme se calcule sur les tarifs non boursiers.
                    </div>
                    <div class="or-grid-2">
                        <div class="form-group">
                            <label class="form-label" for="frais_scolarite">Licence — non boursier (FCFA)</label>
                            <input type="number" id="frais_scolarite" name="frais_scolarite" class="form-input"
                                   min="0" step="1000" value="<?= old('frais_scolarite', etabVal('frais_scolarite')) ?>" placeholder="450000"
                                   data-frais-scolarite>
                            <div class="or-upload-hint">Tarif plein du cycle Licence. 0 = non renseigné (le cycle n'apparaît pas).</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="frais_scolarite_boursier">Licence — boursier (FCFA)</label>
                            <input type="number" id="frais_scolarite_boursier" name="frais_scolarite_boursier" class="form-input"
                                   min="0" step="1000" value="<?= old('frais_scolarite_boursier', etabVal('frais_scolarite_boursier')) ?>" placeholder="300000">
                            <div class="or-upload-hint">Tarif réservé aux élèves boursiers. 0 = pas de tarif boursier en Licence.</div>
                        </div>
                    </div>
                    <div class="or-grid-2" style="margin-top:16px;">
                        <div class="form-group">
                            <label class="form-label" for="frais_scolarite_master">Master — non boursier (FCFA)</label>
                            <input type="number" id="frais_scolarite_master" name="frais_scolarite_master" class="form-input"
                                   min="0" step="1000" value="<?= old('frais_scolarite_master', etabVal('frais_scolarite_master')) ?>" placeholder="650000"
                                   data-frais-scolarite-master>
                            <div class="or-upload-hint">Tarif plein du cycle Master. 0 = non renseigné (le cycle n'apparaît pas).</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="frais_scolarite_master_boursier">Master — boursier (FCFA)</label>
                            <input type="number" id="frais_scolarite_master_boursier" name="frais_scolarite_master_boursier" class="form-input"
                                   min="0" step="1000" value="<?= old('frais_scolarite_master_boursier', etabVal('frais_scolarite_master_boursier')) ?>" placeholder="450000">
                            <div class="or-upload-hint">Tarif réservé aux élèves boursiers. 0 = pas de tarif boursier en Master.</div>
                        </div>
                    </div>
                    <div class="or-grid-2" style="margin-top:16px;">
                        <div class="form-group">
                            <label class="form-label" for="frais_uniforme">Frais d'uniforme (FCFA)</label>
                            <input type="number" id="frais_uniforme" name="frais_uniforme" class="form-input"
                                   min="0" step="1000" value="<?= old('frais_uniforme', etabVal('frais_uniforme')) ?>" placeholder="25000">
                            <div class="or-upload-hint">Tenue / uniforme obligatoire. Affiché dans les frais de la page publique. 0 = non concerné.</div>
                        </div>
                    </div>
                    <div class="or-grid-2" style="margin-top:16px;">
                        <div class="form-group">
                            <label class="form-label" for="taux_commission">Commission plateforme (% scolarité)</label>
                            <input type="number" id="taux_commission" name="taux_commission" class="form-input<?= hasErr('taux_commission') ?>"
                                   min="0" max="100" step="0.01" value="<?= old('taux_commission', etabVal('taux_commission_scolarite') !== '' ? etabVal('taux_commission_scolarite') : '10') ?>" placeholder="10"
                                   data-taux-commission>
                            <?= err('taux_commission') ?>
                            <div class="or-upload-hint">Pourcentage de la scolarité perçu par Connect'Academia pour chaque élève inscrit via la plateforme — appliqué aux nouvelles commissions (les commissions déjà créées gardent leur taux). 0 = aucune commission.</div>
                            <div class="or-commission-preview" id="commission-preview" hidden></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Modes de paiement acceptés <span class="or-optional">optionnel</span></label>
                        <div class="or-tag-list" id="modes-list">
                            <?php foreach ($modesPrefill as $m): ?>
                            <div class="or-tag">
                                <input type="hidden" name="mode_paiement[]" value="<?= e($m) ?>">
                                <span><?= e($m) ?></span>
                                <button type="button" class="or-tag-del" onclick="removeTag(this)">×</button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="or-add-row">
                            <input type="text" class="form-input or-add-input" id="mode-input"
                                   placeholder="Ex : Airtel Money, Orange Money, virement…"
                                   onkeydown="if(event.key==='Enter'){event.preventDefault();addTag('mode-input','mode_paiement[]','modes-list');}">
                            <button type="button" class="btn-add-tag" onclick="addTag('mode-input','mode_paiement[]','modes-list')">+</button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- §8 Formations -->
            <section class="or-section" id="sec-formations">
                <div class="or-section-header">
                    <div class="or-section-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 14l9-5-9-5-9 5 9 5z"/>
                            <path d="M12 14l6.16-3.422a12.083 12.083 0 0 1 .665 6.479A11.952 11.952 0 0 0 12 20.055a11.952 11.952 0 0 0-6.824-2.998 12.078 12.078 0 0 1 .665-6.479L12 14z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="or-section-title">Formations <span style="color:var(--red)">*</span></p>
                        <p class="or-section-sub">Les formations supprimées ici disparaissent de la page publique — les dossiers déjà déposés sont conservés</p>
                    </div>
                </div>
                <div class="or-section-body">
                    <?= err('formations') ?>
                    <div id="formations-list">
                        <?php foreach ($formationsPrefill as $i => $f): ?>
                        <div class="or-formation-row" id="formation-<?= $i ?>">
                            <span class="row-num">Formation #<?= $i + 1 ?></span>
                            <button type="button" class="or-btn-remove" onclick="removeFormation(<?= $i ?>)">Supprimer</button>
                            <?php if ($f['id'] > 0): ?>
                            <input type="hidden" name="formations[<?= $i ?>][id]" value="<?= $f['id'] ?>">
                            <?php endif; ?>
                            <div style="margin-top:28px">
                                <div class="or-grid-2" style="margin-bottom:12px">
                                    <div class="form-group">
                                        <label class="form-label">Intitulé <span style="color:var(--red)">*</span></label>
                                        <input type="text" name="formations[<?= $i ?>][intitule]" class="form-input" value="<?= e($f['intitule']) ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Niveau</label>
                                        <input type="text" name="formations[<?= $i ?>][niveau]" class="form-input" value="<?= e($f['niveau']) ?>" placeholder="Ex : Bac+3">
                                    </div>
                                </div>
                                <div class="or-grid-3">
                                    <div class="form-group">
                                        <label class="form-label">Prix (FCFA)</label>
                                        <input type="number" name="formations[<?= $i ?>][prix]" class="form-input" min="0" value="<?= e($f['prix']) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Durée</label>
                                        <input type="text" name="formations[<?= $i ?>][duree_texte]" class="form-input" value="<?= e($f['duree_texte']) ?>" placeholder="Ex : 3 ans">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Places</label>
                                        <input type="text" name="formations[<?= $i ?>][places_texte]" class="form-input" value="<?= e($f['places_texte']) ?>" placeholder="Ex : 60 places">
                                    </div>
                                </div>
                                <div class="or-grid-2">
                                    <div class="form-group">
                                        <label class="form-label">Icône (Lucide)</label>
                                        <input type="text" name="formations[<?= $i ?>][icone]" class="form-input" value="<?= e($f['icone']) ?>" placeholder="Ex : laptop-2">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Statut</label>
                                        <select name="formations[<?= $i ?>][statut]" class="form-select">
                                            <?php foreach (['ouverte'=>'Ouverte','bientot'=>'Bientôt','fermee'=>'Fermée'] as $v=>$l): ?>
                                            <option value="<?= $v ?>" <?= $f['statut'] === $v ? 'selected' : '' ?>><?= $l ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn-outline" style="width:100%;margin-top:4px" onclick="addFormation()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:6px;vertical-align:-1px">
                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Ajouter une formation
                    </button>
                </div>
            </section>

        </div><!-- /.or-form-content -->
    </div><!-- /.or-form-layout -->

    <!-- Footer sticky -->
    <div class="or-form-footer">
        <a href="<?= url('/admin/orientation/' . $etab['id'] . '/voir') ?>" class="btn-outline">Annuler</a>
        <button type="submit" class="btn-admin-primary" id="or-submit-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:6px;vertical-align:-2px">
                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                <polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
            </svg>
            Enregistrer les modifications
        </button>
    </div>
</form>

<script>
// ── Compteur formations (démarre après les lignes existantes) ─────────
var formationCount = <?= count($formationsPrefill) ?>;

function addFormation() {
    var idx = formationCount++;
    var div = document.createElement('div');
    div.className = 'or-formation-row';
    div.id = 'formation-' + idx;
    div.innerHTML =
        '<span class="row-num">Formation #' + (idx + 1) + '</span>' +
        '<button type="button" class="or-btn-remove" onclick="removeFormation(' + idx + ')">Supprimer</button>' +
        '<div style="margin-top:28px">' +
        '<div class="or-grid-2" style="margin-bottom:12px">' +
            '<div class="form-group">' +
                '<label class="form-label">Intitulé <span style="color:var(--red)">*</span></label>' +
                '<input type="text" name="formations[' + idx + '][intitule]" class="form-input" placeholder="Ex : Licence en Informatique" required>' +
            '</div>' +
            '<div class="form-group">' +
                '<label class="form-label">Niveau</label>' +
                '<input type="text" name="formations[' + idx + '][niveau]" class="form-input" placeholder="Ex : Bac+3">' +
            '</div>' +
        '</div>' +
        '<div class="or-grid-3">' +
            '<div class="form-group">' +
                '<label class="form-label">Prix (FCFA)</label>' +
                '<input type="number" name="formations[' + idx + '][prix]" class="form-input" min="0" placeholder="0">' +
            '</div>' +
            '<div class="form-group">' +
                '<label class="form-label">Durée</label>' +
                '<input type="text" name="formations[' + idx + '][duree_texte]" class="form-input" placeholder="Ex : 3 ans">' +
            '</div>' +
            '<div class="form-group">' +
                '<label class="form-label">Places</label>' +
                '<input type="text" name="formations[' + idx + '][places_texte]" class="form-input" placeholder="Ex : 60 places">' +
            '</div>' +
        '</div>' +
        '<div class="or-grid-2">' +
            '<div class="form-group">' +
                '<label class="form-label">Icône (Lucide)</label>' +
                '<input type="text" name="formations[' + idx + '][icone]" class="form-input" placeholder="Ex : laptop-2">' +
            '</div>' +
            '<div class="form-group">' +
                '<label class="form-label">Statut</label>' +
                '<select name="formations[' + idx + '][statut]" class="form-select">' +
                    '<option value="ouverte">Ouverte</option>' +
                    '<option value="bientot">Bientôt</option>' +
                    '<option value="fermee">Fermée</option>' +
                '</select>' +
            '</div>' +
        '</div>' +
        '</div>';
    document.getElementById('formations-list').appendChild(div);
}

function removeFormation(idx) {
    var el = document.getElementById('formation-' + idx);
    if (el) el.remove();
}

// ── Tags dynamiques ───────────────────────────────────────────────────
function addTag(inputId, fieldName, listId) {
    var input = document.getElementById(inputId);
    var val = input.value.trim();
    if (!val) return;

    var list = document.getElementById(listId);
    var tag  = document.createElement('div');
    tag.className = 'or-tag';

    var hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = fieldName;
    hidden.value = val;

    var span = document.createElement('span');
    span.textContent = val;

    var del = document.createElement('button');
    del.type = 'button';
    del.className = 'or-tag-del';
    del.textContent = '×';
    del.onclick = function() { removeTag(del); };

    tag.appendChild(hidden);
    tag.appendChild(span);
    tag.appendChild(del);
    list.appendChild(tag);
    input.value = '';
    input.focus();
}

function removeTag(btn) { btn.parentElement.remove(); }

// ── Statistiques ───────────────────────────────────────────────────────
function addStatRow() {
    var row = document.createElement('div');
    row.className = 'or-stat-row';
    row.innerHTML =
        '<input type="text" name="stat_label[]" class="form-input" placeholder="Ex : Taux d\'insertion">' +
        '<input type="text" name="stat_valeur[]" class="form-input" placeholder="Ex : 87%">' +
        '<button type="button" class="or-btn-remove" style="position:static" onclick="this.parentElement.remove()">×</button>';
    document.getElementById('stats-rows').appendChild(row);
}

// ── Preview image ──────────────────────────────────────────────────────
function previewImg(input, previewId) {
    var prev = document.getElementById(previewId);
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            prev.src = e.target.result;
            prev.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// ── Sélection photos campus (max 10) ───────────────────────────────────
function campusSelected(input) {
    var label = document.getElementById('campus-label');
    var n = input.files ? input.files.length : 0;
    if (n > 10) {
        alert('Maximum 10 photos — tu en as sélectionné ' + n + '. Recommence en choisissant 10 photos au plus.');
        input.value = '';
        label.textContent = 'Remplacer les photos (jusqu\'à 10)';
        return;
    }
    label.textContent = n > 0 ? n + ' photo(s) sélectionnée(s) ✓' : 'Remplacer les photos (jusqu\'à 10)';
}

// ── Sélection vidéo ────────────────────────────────────────────────────
function videoSelected(input) {
    var label = document.getElementById('video-label');
    if (input.files && input.files[0]) {
        var f = input.files[0];
        if (f.size > 500 * 1024 * 1024) {
            alert('Vidéo trop lourde : ' + (f.size / 1048576).toFixed(0) + ' Mo (max 500 Mo).');
            input.value = '';
            label.textContent = 'Clique ou glisse la vidéo';
            return;
        }
        label.textContent = f.name + ' (' + (f.size / 1048576).toFixed(1) + ' Mo) ✓';
    } else {
        label.textContent = 'Clique ou glisse la vidéo';
    }
}

// ── Couleur hex label ──────────────────────────────────────────────────
document.getElementById('color-picker').addEventListener('input', function() {
    document.getElementById('color-hex').textContent = this.value.toUpperCase();
});

// ── Active nav on scroll ───────────────────────────────────────────────
(function() {
    var sections = document.querySelectorAll('.or-section[id]');
    var navLinks = document.querySelectorAll('.or-nav a[href^="#"]');

    function onScroll() {
        var scrollY = window.scrollY + 100;
        var current = '';
        sections.forEach(function(s) {
            if (s.offsetTop <= scrollY) current = s.id;
        });
        navLinks.forEach(function(a) {
            a.classList.toggle('active', a.getAttribute('href') === '#' + current);
        });
    }
    window.addEventListener('scroll', onScroll, { passive: true });
})();

// ── Aperçu commission = frais de scolarité × taux ─────────────────────
(function () {
    var scoL = document.querySelector('[data-frais-scolarite]');
    var scoM = document.querySelector('[data-frais-scolarite-master]');
    var tx   = document.querySelector('[data-taux-commission]');
    var out  = document.getElementById('commission-preview');
    if (!tx || !out) return;

    function fmt(n) { return n.toLocaleString('fr-FR').replace(/ | /g, ' '); }

    function ligne(label, s, t) {
        if (s <= 0 || t <= 0) return '';
        var c  = Math.round(s * t / 100);
        var t1 = Math.round(c * 0.40), t2 = Math.round(c * 0.30), t3 = c - t1 - t2;
        return '<div>' + label + ' : <strong>' + fmt(c) + ' FCFA</strong> par élève inscrit'
             + ' · tranches ' + fmt(t1) + ' / ' + fmt(t2) + ' / ' + fmt(t3) + '</div>';
    }

    function render() {
        var t = parseFloat(tx.value) || 0;
        var html = ligne('Licence', scoL ? (parseFloat(scoL.value) || 0) : 0, t)
                 + ligne('Master',  scoM ? (parseFloat(scoM.value) || 0) : 0, t);
        if (html === '') { out.hidden = true; return; }
        out.hidden = false;
        out.innerHTML = html;
    }
    if (scoL) scoL.addEventListener('input', render);
    if (scoM) scoM.addEventListener('input', render);
    tx.addEventListener('input', render);
    render();
})();

// ── Soumission + upload direct des médias : géré par le module partagé
//    assets/js/orientation-media-upload.js (inclus plus bas).
</script>
<script src="<?= asset('js/orientation-media-upload.js') ?>"></script>
