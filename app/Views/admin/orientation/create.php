<?php
// Variables: $old (array), $errors (array)
$old    = $old ?? [];
$errors = $errors ?? [];

// La vue est incluse dans la portée de Response::view() — `global` n'y voit rien.
// On passe par $GLOBALS pour que les helpers lisent les vraies données.
$GLOBALS['__or_old']    = $old;
$GLOBALS['__or_errors'] = $errors;

// Composition du dossier d'inscription : rien en base (l'établissement n'existe
// pas encore), seulement ce qui a pu être saisi avant une erreur de validation.
// On repasse par le service plutôt que de relire $old brut : la vue affiche
// alors exactement ce qui SERAIT enregistré — nettoyé, dédoublonné, borné.
$compoPrefill = \Services\Orientation\CompositionDossier::decoder(
    \Services\Orientation\CompositionDossier::encoder($old)
);

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
?>

<style>
/* ── Layout formulaire ────────────────────────────────── */
.or-form-layout {
    display: grid;
    grid-template-columns: 220px 1fr;
    gap: 28px;
    align-items: start;
}
.or-nav {
    position: sticky;
    top: calc(var(--topbar-h) + 28px);
    background: #fff;
    border-radius: 16px;
    border: 1px solid var(--border);
    box-shadow: var(--shadow-sm);
    padding: 12px 0;
}
.or-nav a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 20px;
    font-size: 13px;
    font-weight: 500;
    color: var(--txt-m);
    text-decoration: none;
    border-left: 3px solid transparent;
    transition: all 0.15s;
}
.or-nav a:hover  { color: var(--ap); background: var(--ap-lt); }
.or-nav a.active { color: var(--ap); border-left-color: var(--ap); background: var(--ap-xl); font-weight: 600; }
.or-nav .nav-badge {
    margin-left: auto;
    background: var(--red-bg);
    color: var(--red);
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 7px;
}
.or-nav .nav-badge.ok { background: var(--green-bg); color: #15803d; }

/* ── Sections ─────────────────────────────────────────── */
.or-section {
    background: #fff;
    border-radius: 16px;
    border: 1px solid var(--border);
    box-shadow: var(--shadow-sm);
    margin-bottom: 20px;
    scroll-margin-top: calc(var(--topbar-h) + 28px);
}
.or-section-header {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
}
.or-section-icon {
    width: 40px; height: 40px;
    background: var(--ap-lt);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    color: var(--ap);
}
.or-section-title { font-size: 15px; font-weight: 600; color: var(--txt); margin: 0; }
.or-section-sub   { font-size: 12px; color: var(--txt-l); margin: 2px 0 0; }
.or-section-body  { padding: 24px; }
.or-section-body .or-optional {
    display: inline-block;
    font-size: 10px;
    font-weight: 600;
    background: var(--border);
    color: var(--txt-l);
    border-radius: 999px;
    padding: 2px 8px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-left: 8px;
    vertical-align: middle;
}

/* ── Form elements ────────────────────────────────────── */
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
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: var(--txt-m);
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.form-label span { color: var(--red); margin-left: 2px; }
.form-input, .form-select, .form-textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid var(--border-strong);
    border-radius: 10px;
    font-size: 14px;
    font-family: inherit;
    color: var(--txt);
    background: #fff;
    transition: border-color 0.15s;
    box-sizing: border-box;
}
.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none;
    border-color: var(--ap);
    box-shadow: 0 0 0 3px var(--ap-glow-20);
}
.form-textarea { resize: vertical; min-height: 90px; }
.or-input-error { border-color: var(--red) !important; }
.or-field-error { font-size: 12px; color: var(--red); margin: 4px 0 0; }

/* ── Color picker ─────────────────────────────────────── */
.or-color-wrap {
    display: flex; align-items: center; gap: 12px;
}
.or-color-preview {
    width: 40px; height: 40px; border-radius: 8px;
    border: 1.5px solid var(--border-strong);
    cursor: pointer; flex-shrink: 0;
}
input[type="color"] { width: 0; height: 0; opacity: 0; position: absolute; }

/* ── Upload zone ──────────────────────────────────────── */
.or-upload-zone {
    border: 2px dashed var(--border-strong);
    border-radius: 12px;
    padding: 24px;
    text-align: center;
    cursor: pointer;
    transition: all 0.15s;
    position: relative;
}
.or-upload-zone:hover, .or-upload-zone.drag-over {
    border-color: var(--ap);
    background: var(--ap-xl);
}
.or-upload-zone input[type="file"] {
    position: absolute;
    inset: 0;
    width: 100%; height: 100%;
    opacity: 0;
    cursor: pointer;
}
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

/* ── Formations dynamiques ────────────────────────────── */
.or-formation-row {
    background: var(--ap-xl);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 12px;
    position: relative;
}
.or-formation-row .row-num {
    position: absolute;
    top: 12px; left: 16px;
    font-size: 11px; font-weight: 700; color: var(--ap);
    text-transform: uppercase; letter-spacing: 0.06em;
}
.or-btn-remove {
    position: absolute;
    top: 10px; right: 12px;
    background: var(--red-bg);
    color: var(--red);
    border: none;
    border-radius: 6px;
    padding: 4px 8px;
    font-size: 12px;
    cursor: pointer;
    font-weight: 600;
}
.or-btn-remove:hover { background: var(--red); color: #fff; }

/* ── Blocs gestionnaires (répétables) ─────────────────── */
.or-gest-block {
    background: var(--ap-xl);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 14px 16px 4px;
    margin-bottom: 12px;
}
.or-gest-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}
.or-gest-title {
    font-size: 11px; font-weight: 700; color: var(--ap);
    text-transform: uppercase; letter-spacing: 0.06em;
}
.or-gest-del {
    background: var(--red-bg);
    color: var(--red);
    border: none;
    border-radius: 6px;
    width: 26px; height: 26px;
    font-size: 16px; line-height: 1;
    cursor: pointer;
    font-weight: 700;
}
.or-gest-del:hover { background: var(--red); color: #fff; }

/* ── Listes dynamiques ────────────────────────────────── */
.or-tag-list { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 10px; }
.or-tag {
    display: flex; align-items: center; gap: 6px;
    background: var(--ap-lt);
    color: var(--ap);
    border-radius: 999px;
    padding: 5px 12px;
    font-size: 13px; font-weight: 500;
}
.or-tag-del {
    background: none; border: none; cursor: pointer;
    color: var(--ap); font-size: 16px; line-height: 1; padding: 0;
}
.or-add-row { display: flex; gap: 8px; }
.or-add-input { flex: 1; }
.btn-add-tag {
    flex-shrink: 0;
    background: var(--ap);
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 10px 16px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
}
.btn-add-tag:hover { background: var(--ap-dk); }

/* ── Stat rows ────────────────────────────────────────── */
.or-stat-row {
    display: grid;
    grid-template-columns: 1fr 1fr auto;
    gap: 8px;
    align-items: center;
    margin-bottom: 8px;
}

/* ── Checkbox group ───────────────────────────────────── */
.or-check-group { display: flex; gap: 20px; flex-wrap: wrap; }
.or-check-label {
    display: flex; align-items: center; gap: 8px;
    font-size: 13px; font-weight: 500; color: var(--txt-m); cursor: pointer;
}
.or-check-label input[type="checkbox"] { accent-color: var(--ap); width: 16px; height: 16px; }

/* ── Footer sticky ────────────────────────────────────── */
.or-form-footer {
    position: sticky;
    bottom: 0;
    background: rgba(245,245,247,0.95);
    backdrop-filter: blur(8px);
    border-top: 1px solid var(--border);
    padding: 16px 0;
    margin-top: 8px;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    z-index: 10;
}

@media (max-width: 900px) {
    .or-form-layout { grid-template-columns: 1fr; }
    .or-nav { position: static; }
    .or-grid-2, .or-grid-3 { grid-template-columns: 1fr; }
}
</style>

<div class="admin-page-header-row">
    <div>
        <h1>Ajouter un établissement</h1>
        <p>Remplis les informations — le site naît dès la soumission</p>
    </div>
    <a href="<?= url('/admin/orientation') ?>" class="btn-outline">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px;vertical-align:-1px">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Retour à la liste
    </a>
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

<form method="POST" action="<?= url('/admin/orientation') ?>" enctype="multipart/form-data" id="or-create-form" data-presign-url="<?= url('/admin/orientation/presign') ?>" novalidate>
    <input type="hidden" name="_csrf_token" value="<?= e(\Core\Session::getCsrfToken()) ?>">

    <div class="or-form-layout">

        <!-- ── Navigation latérale ───────────────────────────────────── -->
        <nav class="or-nav" id="or-nav">
            <?php
            $navItems = [
                'identite'    => ['Identité',    'M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z', true],
                'medias'      => ['Médias',       'M4 16l4.586-4.586a2 2 0 0 1 2.828 0L16 16m-2-2 1.586-1.586a2 2 0 0 1 2.828 0L20 14m-6-6h.01M6 20h12a2 2 0 0 1 2-2V6a2 2 0 0 1-2-2H6a2 2 0 0 1-2 2v12a2 2 0 0 1 2 2z', true],
                'accroche'    => ['Accroche',     'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 0-2-2', false],
                'presentation'=> ['Présentation', 'M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z', false],
                'atouts'      => ['Atouts',       'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 0 0 1.946-.806 3.42 3.42 0 0 1 4.438 0 3.42 3.42 0 0 0 1.946.806 3.42 3.42 0 0 1 3.138 3.138', false],
                'contact'     => ['Contact',      'M17.657 16.657 13.414 20.9a1.998 1.998 0 0 1-2.827 0l-4.244-4.243a8 8 0 1 1 11.314 0z', true],
                'tarifs'      => ['Tarifs',       'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z', true],
                'dossier'     => ["Dossier d'inscription", 'M9 12h6M9 16h6M9 8h2M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z', false],
                'encaissement'=> ['Encaissement', 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 0 0 3-3V8a3 3 0 0 0-3-3H6a3 3 0 0 0-3 3v8a3 3 0 0 0 3 3z', false],
                'formations'  => ['Formations',   'M12 14l9-5-9-5-9 5 9 5zm0 0 6.16-3.422a12.083 12.083 0 0 1 .665 6.479A11.952 11.952 0 0 0 12 20.055', true],
                'gestionnaire'=> ['Gestionnaires', 'M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0zM12 14a7 7 0 0 0-7 7h14a7 7 0 0 0-7-7z', true],
            ];
            foreach ($navItems as $id => [$label, $icon, $required]):
                $hasError = array_filter(array_keys($errors), fn($k) => str_contains($k, $id) || ($id === 'identite' && in_array($k, ['nom','type','ville','couleur_principale'])) || ($id === 'gestionnaire' && str_starts_with($k, 'gestionnaire')) || ($id === 'medias' && in_array($k, ['logo','images_campus','video'])) || ($id === 'formations' && $k === 'formations') || ($id === 'contact' && in_array($k, ['email_contact','map_embed'])));
            ?>
            <a href="#sec-<?= $id ?>" class="<?= !empty($hasError) ? 'active' : '' ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $icon ?></svg>
                <?= $label ?>
                <?php if ($required): ?><span class="or-req" style="color:var(--red);margin-left:2px;font-size:10px;">*</span><?php endif; ?>
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
                                   value="<?= old('nom') ?>" placeholder="Ex : BGFI Business School" required>
                            <?= err('nom') ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="type">Type <span>*</span></label>
                            <select id="type" name="type" class="form-select<?= hasErr('type') ?>" required>
                                <option value="">— Choisir —</option>
                                <?php foreach(['universite'=>'Université','grande_ecole'=>'Grande école','institut'=>'Institut'] as $v=>$l): ?>
                                <option value="<?= $v ?>" <?= old('type') === $v ? 'selected' : '' ?>><?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= err('type') ?>
                        </div>
                    </div>
                    <div class="or-grid-2">
                        <div class="form-group">
                            <label class="form-label" for="ville">Ville <span>*</span></label>
                            <input type="text" id="ville" name="ville" class="form-input<?= hasErr('ville') ?>"
                                   value="<?= old('ville') ?>" placeholder="Ex : Libreville" required>
                            <?= err('ville') ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="statut">Statut initial</label>
                            <select id="statut" name="statut" class="form-select">
                                <option value="brouillon" <?= (old('statut','brouillon')==='brouillon')?'selected':'' ?>>Brouillon</option>
                                <option value="actif"     <?= old('statut')==='actif'?'selected':'' ?>>Actif</option>
                                <option value="inactif"   <?= old('statut')==='inactif'?'selected':'' ?>>Inactif</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Couleur principale de marque</label>
                        <div class="or-color-wrap">
                            <div class="or-color-preview" id="color-preview" style="background:<?= old('couleur_principale','#1F4E79') ?>" onclick="document.getElementById('color-picker').click()"></div>
                            <input type="color" id="color-picker" name="couleur_principale"
                                   value="<?= old('couleur_principale','#1F4E79') ?>"
                                   oninput="document.getElementById('color-preview').style.background=this.value">
                            <div>
                                <div style="font-size:13px;font-weight:600;color:var(--txt)" id="color-hex"><?= old('couleur_principale','#1F4E79') ?></div>
                                <div style="font-size:11px;color:var(--txt-l)">Injecté dans <code>--brand</code> du template</div>
                            </div>
                        </div>
                    </div>
                    <div class="or-check-group">
                        <label class="or-check-label">
                            <input type="checkbox" name="preinscription_active" value="1" <?= (old('preinscription_active','1')==='1')?'checked':'' ?>>
                            Préinscription activée
                        </label>
                        <label class="or-check-label">
                            <input type="checkbox" name="inscription_active" value="1" <?= (old('inscription_active','0')==='1')?'checked':'' ?>>
                            Inscription en ligne activée
                        </label>
                        <label class="or-check-label">
                            <input type="checkbox" name="genere_recepisse" value="1" <?= (old('genere_recepisse','1')==='1')?'checked':'' ?>>
                            Génère une attestation de préinscription (PDF)
                        </label>
                        <label class="or-check-label">
                            <input type="checkbox" name="genere_attestation" value="1" <?= (old('genere_attestation','1')==='1')?'checked':'' ?>>
                            Génère une attestation d'inscription
                        </label>
                        <label class="or-check-label">
                            <input type="checkbox" name="accepte_boursiers" value="1" <?= (old('accepte_boursiers','1')==='1')?'checked':'' ?>>
                            Accepte les candidatures de boursiers
                        </label>
                    </div>
                    <p style="font-size:12px;color:var(--txt-l);margin-top:8px;">
                        <strong style="color:var(--txt)">Inscription en ligne</strong> : cochée, le bouton
                        « S'inscrire » et le parcours en trois étapes s'ouvrent aux candidats. À laisser
                        décochée tant que les formations et les pièces requises ne sont pas saisies —
                        un formulaire sans référentiel de pièces produit des dossiers vides.
                    </p>
                    <p style="font-size:12px;color:var(--txt-l);margin-top:8px;">
                        Décochée, la question de la bourse disparaît entièrement du front-office :
                        bloc « situation de bourse » du formulaire d'inscription, colonne « Boursier »
                        des grilles tarifaires et pièces ANBG. Les dossiers déjà déposés ne sont pas
                        modifiés.
                    </p>
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
                        <p class="or-section-sub">Logo obligatoire — images et vidéo optionnelles</p>
                    </div>
                </div>
                <div class="or-section-body">
                    <div class="or-grid-2">
                        <div class="form-group">
                            <label class="form-label">Logo <span>*</span></label>
                            <div class="or-upload-zone<?= hasErr('logo') ?>" id="logo-zone">
                                <input type="file" name="logo" accept="image/jpeg,image/png,image/webp"
                                       onchange="previewImg(this,'logo-prev')">
                                <div class="or-upload-icon">
                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                        <polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                                    </svg>
                                </div>
                                <div class="or-upload-label">Clique ou glisse le logo</div>
                                <div class="or-upload-hint">JPG, PNG, WEBP — max 10 Mo</div>
                                <img id="logo-prev" class="or-upload-preview" alt="Aperçu logo">
                            </div>
                            <?= err('logo') ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Cachet officiel <span class="or-optional">optionnel</span></label>
                            <div class="or-upload-zone<?= hasErr('cachet') ?>" id="cachet-zone">
                                <input type="file" name="cachet" accept="image/jpeg,image/png,image/webp"
                                       onchange="previewImg(this,'cachet-prev')">
                                <div class="or-upload-icon">
                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                        <polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                                    </svg>
                                </div>
                                <div class="or-upload-label">Clique ou glisse le cachet</div>
                                <div class="or-upload-hint">JPG, PNG, WEBP — max 10 Mo — utilisé sur la facture proforma</div>
                                <img id="cachet-prev" class="or-upload-preview" alt="Aperçu cachet">
                            </div>
                            <?= err('cachet') ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Photos campus <span class="or-optional">optionnel</span></label>
                            <div class="or-upload-zone<?= hasErr('images_campus') ?>" id="campus-zone">
                                <input type="file" name="images_campus[]" accept="image/jpeg,image/png,image/webp" multiple
                                       onchange="campusSelected(this)">
                                <div class="or-upload-icon">
                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/>
                                        <polyline points="21 15 16 10 5 21"/>
                                    </svg>
                                </div>
                                <div class="or-upload-label" id="campus-label">Jusqu'à 10 photos</div>
                                <div class="or-upload-hint">JPG, PNG, WEBP — max 10 Mo chacune</div>
                            </div>
                            <?= err('images_campus') ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Vidéo de présentation <span class="or-optional">optionnel</span></label>
                        <div class="or-upload-zone<?= hasErr('video') ?>" id="video-zone">
                            <input type="file" name="video" accept="video/*" onchange="videoSelected(this)">
                            <div class="or-upload-icon">
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <polygon points="23 7 16 12 23 17 23 7"/>
                                    <rect x="1" y="5" width="15" height="14" rx="2"/>
                                </svg>
                            </div>
                            <div class="or-upload-label" id="video-label">Clique ou glisse la vidéo</div>
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
                                   value="<?= old('label_campagne') ?>" placeholder="Ex : Inscriptions 2025-2026 ouvertes">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="date_limite_dossier">Date limite dossier</label>
                            <input type="date" id="date_limite_dossier" name="date_limite_dossier" class="form-input"
                                   value="<?= old('date_limite_dossier') ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="titre_accroche">Titre principal du hero</label>
                        <input type="text" id="titre_accroche" name="titre_accroche" class="form-input"
                               value="<?= old('titre_accroche') ?>" placeholder="Ex : Forge ton avenir à l'ESAG-NDE">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="sous_titre">Sous-titre</label>
                        <textarea id="sous_titre" name="sous_titre" class="form-textarea" placeholder="Texte sous le titre principal…"><?= old('sous_titre') ?></textarea>
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
                        <textarea id="description" name="description" class="form-textarea" rows="4"
                                  placeholder="Histoire, mission, valeurs…"><?= old('description') ?></textarea>
                    </div>
                    <div class="or-grid-3">
                        <div class="form-group" style="grid-column:span 3">
                            <label class="form-label" for="citation_texte">Citation</label>
                            <textarea id="citation_texte" name="citation_texte" class="form-textarea" rows="2"
                                      placeholder="« L'excellence est notre seule ambition. »"><?= old('citation_texte') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="citation_auteur">Auteur</label>
                            <input type="text" id="citation_auteur" name="citation_auteur" class="form-input"
                                   value="<?= old('citation_auteur') ?>" placeholder="Pr. Jean Dupont">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="citation_fonction">Fonction</label>
                            <input type="text" id="citation_fonction" name="citation_fonction" class="form-input"
                                   value="<?= old('citation_fonction') ?>" placeholder="Directeur général">
                        </div>
                    </div>
                </div>
            </section>

            <!-- §5 Atouts -->
            <section class="or-section" id="sec-atouts">
                <div class="or-section-header">
                    <div class="or-section-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 0 0 1.946-.806 3.42 3.42 0 0 1 4.438 0 3.42 3.42 0 0 0 1.946.806 3.42 3.42 0 0 1 3.138 3.138 3.42 3.42 0 0 0 .806 1.946 3.42 3.42 0 0 1 0 4.438 3.42 3.42 0 0 0-.806 1.946 3.42 3.42 0 0 1-3.138 3.138 3.42 3.42 0 0 0-1.946.806 3.42 3.42 0 0 1-4.438 0 3.42 3.42 0 0 0-1.946-.806 3.42 3.42 0 0 1-3.138-3.138 3.42 3.42 0 0 0-.806-1.946 3.42 3.42 0 0 1 0-4.438 3.42 3.42 0 0 0 .806-1.946 3.42 3.42 0 0 1 3.138-3.138z"/>
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
                        <div class="or-tag-list" id="badges-list"></div>
                        <input type="hidden" name="badges[]" id="badges-hidden">
                        <div class="or-add-row">
                            <input type="text" class="form-input or-add-input" id="badge-input"
                                   placeholder="Ex : Accrédité CAMES" maxlength="80"
                                   onkeydown="if(event.key==='Enter'){event.preventDefault();addTag('badges','badge-input','badges-hidden','badges-list');}">
                            <button type="button" class="btn-add-tag" onclick="addTag('badges','badge-input','badges-hidden','badges-list')">+</button>
                        </div>
                    </div>
                    <!-- Atouts -->
                    <div class="form-group">
                        <label class="form-label">Points forts</label>
                        <div class="or-tag-list" id="atouts-list"></div>
                        <input type="hidden" name="atouts[]" id="atouts-hidden">
                        <div class="or-add-row">
                            <input type="text" class="form-input or-add-input" id="atout-input"
                                   placeholder="Ex : Enseignants qualifiés à 90%" maxlength="120"
                                   onkeydown="if(event.key==='Enter'){event.preventDefault();addTag('atouts','atout-input','atouts-hidden','atouts-list');}">
                            <button type="button" class="btn-add-tag" onclick="addTag('atouts','atout-input','atouts-hidden','atouts-list')">+</button>
                        </div>
                    </div>
                    <!-- Statistiques -->
                    <div class="form-group">
                        <label class="form-label">Statistiques clés</label>
                        <div id="stats-rows"></div>
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
                               value="<?= old('adresse') ?>" placeholder="Ex : BP 2512, Libreville, Gabon">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="map_embed">Carte Google Maps <span class="or-optional">optionnel</span></label>
                        <textarea id="map_embed" name="map_embed" class="form-textarea<?= hasErr('map_embed') ?>" rows="4"
                                  style="font-family:monospace;font-size:12px;"
                                  placeholder="Colle ici le code &lt;iframe&gt; complet fourni par Google Maps"><?= old('map_embed') ?></textarea>
                        <?= err('map_embed') ?>
                        <p style="font-size:12px;color:var(--txt-l);margin:6px 0 0;line-height:1.5;">
                            Sur <strong>Google Maps</strong> : recherche l'établissement → <strong>Partager</strong> →
                            onglet <strong>« Intégrer une carte »</strong> → <strong>Copier le code HTML</strong>, puis colle-le ici.
                            La carte s'affichera sur la page publique de l'établissement.
                        </p>
                    </div>
                    <div class="or-grid-3">
                        <div class="form-group">
                            <label class="form-label" for="email_contact">Email <span>*</span></label>
                            <input type="email" id="email_contact" name="email_contact" class="form-input<?= hasErr('email_contact') ?>"
                                   value="<?= old('email_contact') ?>" placeholder="contact@ecole.ga">
                            <?= err('email_contact') ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="telephone">Téléphone</label>
                            <input type="tel" id="telephone" name="telephone" class="form-input"
                                   value="<?= old('telephone') ?>" placeholder="+241 01 23 45 67">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="whatsapp">WhatsApp</label>
                            <input type="tel" id="whatsapp" name="whatsapp" class="form-input"
                                   value="<?= old('whatsapp') ?>" placeholder="+241 06 XX XX XX">
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
                        <p class="or-section-sub">Montants en FCFA — 0 = gratuit</p>
                    </div>
                </div>
                <div class="or-section-body">
                    <div class="or-grid-3">
                        <div class="form-group">
                            <label class="form-label" for="prix_preinscription">Prix préinscription (FCFA)</label>
                            <input type="number" id="prix_preinscription" name="prix_preinscription" class="form-input"
                                   min="0" value="<?= old('prix_preinscription','0') ?>" placeholder="0">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="prix_inscription">Prix inscription (FCFA)</label>
                            <input type="number" id="prix_inscription" name="prix_inscription" class="form-input"
                                   min="0" value="<?= old('prix_inscription','0') ?>" placeholder="150000">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="frais_dossier">Frais de dossier (FCFA)</label>
                            <input type="number" id="frais_dossier" name="frais_dossier" class="form-input"
                                   min="0" value="<?= old('frais_dossier','0') ?>" placeholder="5000">
                        </div>
                    </div>

                    <!-- Frais d'inscription par cycle × situation de bourse -->
                    <div class="or-subhead" style="margin-top:22px;">Frais d'inscription par cycle</div>
                    <div class="or-upload-hint" style="margin:-4px 0 12px;">
                        Les frais d'inscription ne sont pas les mêmes pour tous : ils dépendent du
                        <strong>cycle</strong> suivi et de la <strong>situation de bourse</strong> de l'étudiant.
                        Ces quatre montants sont ceux qui seront appliqués à un dossier d'inscription.
                        <br><strong>Laissez un champ à 0 pour ne rien changer</strong> : la plateforme retombe
                        alors sur « Prix inscription + Frais de dossier » ci-dessus, exactement comme aujourd'hui.
                        Un champ boursier laissé à 0 reprend le tarif non boursier du même cycle.
                        <br><strong>Pour un étudiant déclaré boursier, la commission Connect'Academia porte sur
                        CES frais d'inscription</strong> — jamais sur sa scolarité, prise en charge par l'ANBG.
                    </div>
                    <div class="or-grid-2">
                        <div class="form-group">
                            <label class="form-label" for="frais_inscription_licence">Licence — non boursier (FCFA)</label>
                            <input type="number" id="frais_inscription_licence" name="frais_inscription_licence" class="form-input"
                                   min="0" step="1000" value="<?= old('frais_inscription_licence','0') ?>" placeholder="150000">
                            <div class="or-upload-hint">Frais d’inscription du cycle Licence, tarif plein. 0 = hérite du tarif public.</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="frais_inscription_licence_boursier">Licence — boursier (FCFA)</label>
                            <input type="number" id="frais_inscription_licence_boursier" name="frais_inscription_licence_boursier" class="form-input"
                                   min="0" step="1000" value="<?= old('frais_inscription_licence_boursier','0') ?>" placeholder="100000">
                            <div class="or-upload-hint">Base de la commission plateforme pour un boursier de Licence. 0 = hérite du tarif public.</div>
                        </div>
                    </div>
                    <div class="or-grid-2" style="margin-top:16px;">
                        <div class="form-group">
                            <label class="form-label" for="frais_inscription_master">Master — non boursier (FCFA)</label>
                            <input type="number" id="frais_inscription_master" name="frais_inscription_master" class="form-input"
                                   min="0" step="1000" value="<?= old('frais_inscription_master','0') ?>" placeholder="200000">
                            <div class="or-upload-hint">Frais d’inscription du cycle Master, tarif plein. 0 = hérite du tarif public.</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="frais_inscription_master_boursier">Master — boursier (FCFA)</label>
                            <input type="number" id="frais_inscription_master_boursier" name="frais_inscription_master_boursier" class="form-input"
                                   min="0" step="1000" value="<?= old('frais_inscription_master_boursier','0') ?>" placeholder="150000">
                            <div class="or-upload-hint">Base de la commission plateforme pour un boursier de Master. 0 = hérite du tarif public.</div>
                        </div>
                    </div>

                    <!-- Scolarité annuelle par cycle × statut boursier -->
                    <div class="or-subhead" style="margin-top:22px;">Frais de scolarité annuels</div>
                    <div class="or-upload-hint" style="margin:-4px 0 12px;">
                        Un seul tarif ? Renseignez uniquement la colonne « Non boursier » et laissez
                        « Boursier » à 0 : la page publique n'affichera qu'un montant, et les boursiers
                        seront tarifés comme les autres.
                        <br>Ces montants sont les <strong>tarifs par défaut de l'établissement</strong>. Ils
                        s'appliquent à toute formation qui n'a pas sa propre grille — voir « Tarifs par
                        formation » plus bas, qui permet de distinguer L1, L2, L3, M1 et M2.
                        <br>Ils s'appliquent <strong>automatiquement</strong> quand l'établissement valide une
                        inscription à 100 % : son guichet n'a rien à saisir ni à confirmer.
                        <br><strong>Base de la commission :</strong> la scolarité pour un étudiant non
                        boursier ; les <em>frais d'inscription</em> pour un boursier confirmé.
                    </div>
                    <div class="or-grid-2">
                        <div class="form-group">
                            <label class="form-label" for="frais_scolarite">Licence — non boursier (FCFA)</label>
                            <input type="number" id="frais_scolarite" name="frais_scolarite" class="form-input"
                                   min="0" step="1000" value="<?= old('frais_scolarite','0') ?>" placeholder="450000"
                                   data-frais-scolarite>
                            <div class="or-upload-hint">Tarif plein du cycle Licence. 0 = non renseigné (le cycle n'apparaît pas).</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="frais_scolarite_boursier">Licence — boursier (FCFA)</label>
                            <input type="number" id="frais_scolarite_boursier" name="frais_scolarite_boursier" class="form-input"
                                   min="0" step="1000" value="<?= old('frais_scolarite_boursier','0') ?>" placeholder="300000">
                            <div class="or-upload-hint">Tarif réservé aux élèves boursiers. 0 = pas de tarif boursier en Licence.</div>
                        </div>
                    </div>
                    <div class="or-grid-2" style="margin-top:16px;">
                        <div class="form-group">
                            <label class="form-label" for="frais_scolarite_master">Master — non boursier (FCFA)</label>
                            <input type="number" id="frais_scolarite_master" name="frais_scolarite_master" class="form-input"
                                   min="0" step="1000" value="<?= old('frais_scolarite_master','0') ?>" placeholder="650000"
                                   data-frais-scolarite-master>
                            <div class="or-upload-hint">Tarif plein du cycle Master. 0 = non renseigné (le cycle n'apparaît pas).</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="frais_scolarite_master_boursier">Master — boursier (FCFA)</label>
                            <input type="number" id="frais_scolarite_master_boursier" name="frais_scolarite_master_boursier" class="form-input"
                                   min="0" step="1000" value="<?= old('frais_scolarite_master_boursier','0') ?>" placeholder="450000">
                            <div class="or-upload-hint">Tarif réservé aux élèves boursiers. 0 = pas de tarif boursier en Master.</div>
                        </div>
                    </div>
                    <div class="or-grid-2" style="margin-top:16px;">
                        <div class="form-group">
                            <label class="form-label" for="frais_uniforme">Frais d'uniforme (FCFA)</label>
                            <input type="number" id="frais_uniforme" name="frais_uniforme" class="form-input"
                                   min="0" step="1000" value="<?= old('frais_uniforme','0') ?>" placeholder="25000">
                            <div class="or-upload-hint">Tenue / uniforme obligatoire. Affiché dans les frais de la page publique. 0 = non concerné.</div>
                        </div>
                    </div>
                    <div class="or-grid-2" style="margin-top:16px;">
                        <div class="form-group">
                            <label class="form-label" for="taux_commission">Commission plateforme (% scolarité)</label>
                            <input type="number" id="taux_commission" name="taux_commission" class="form-input<?= hasErr('taux_commission') ?>"
                                   min="0" max="100" step="0.01" value="<?= old('taux_commission','10') ?>" placeholder="10"
                                   data-taux-commission>
                            <?= err('taux_commission') ?>
                            <div class="or-upload-hint">Pourcentage perçu par Connect'Academia pour chaque élève inscrit via la plateforme. <strong>La base dépend de la situation de bourse déclarée :</strong> la scolarité annuelle pour un non boursier ; pour un boursier confirmé, elle suit le réglage ci-contre. La commission est créée automatiquement à la validation à 100 %, avec ses <?= e(\Services\Orientation\CommissionDossier::libelleRepartition()) ?> que Connect'Academia encaisse ensuite une par une. Le taux est gelé à cet instant : le modifier n'affecte que les inscriptions suivantes. 0 = aucune commission.</div>
                            <div class="or-commission-preview" id="commission-preview" hidden></div>
                        </div>
                        <?php // ── Assiette de la commission des BOURSIERS ──
                              // Seul cas négociable de la règle : certains accords prévoient
                              // que la commission d'un boursier porte sur sa scolarité, que
                              // l'établissement perçoit alors de l'ANBG. Les non boursiers
                              // restent sur la scolarité quoi qu'il arrive — ils ne sont pas
                              // proposés ici, il n'y a rien à choisir. ?>
                        <div class="form-group">
                            <label class="form-label" for="base_commission_boursier">Commission des boursiers — prélevée sur</label>
                            <select id="base_commission_boursier" name="base_commission_boursier" class="form-select">
                                <option value="frais_inscription" <?= old('base_commission_boursier','frais_inscription') === 'frais_inscription' ? 'selected' : '' ?>>Les frais d'inscription (par défaut)</option>
                                <option value="scolarite"         <?= old('base_commission_boursier') === 'scolarite' ? 'selected' : '' ?>>La scolarité annuelle (accord particulier)</option>
                            </select>
                            <div class="or-upload-hint"><strong>Ne concerne que les boursiers confirmés.</strong> Par défaut la commission porte sur leurs frais d'inscription, leur scolarité étant prise en charge par l'ANBG. Basculez sur la scolarité si l'accord signé avec cet établissement le prévoit. Les élèves non boursiers restent commissionnés sur la scolarité, quel que soit ce réglage. Comme le taux, l'assiette est gelée à la validation à 100 % : la changer n'affecte que les inscriptions suivantes.</div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Modes de paiement acceptés <span class="or-optional">optionnel</span></label>
                        <div class="or-tag-list" id="modes-list"></div>
                        <div class="or-add-row">
                            <input type="text" class="form-input or-add-input" id="mode-input"
                                   placeholder="Ex : Airtel Money, Orange Money, virement…"
                                   onkeydown="if(event.key==='Enter'){event.preventDefault();addTag('modes','mode-input',null,'modes-list',true);}">
                            <button type="button" class="btn-add-tag" onclick="addTag('modes','mode-input',null,'modes-list',true)">+</button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- §8 Encaissement -->
            <section class="or-section" id="sec-encaissement">
                <div class="or-section-header">
                    <div class="or-section-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                            <line x1="1" y1="10" x2="23" y2="10"/>
                        </svg>
                    </div>
                    <div>
                        <p class="or-section-title">Compte d'encaissement <span class="or-optional">optionnel</span></p>
                        <p class="or-section-sub">Numéro Mobile Money pour le reversement automatique — activable plus tard</p>
                    </div>
                </div>
                <div class="or-section-body">
                    <div class="or-grid-3">
                        <div class="form-group">
                            <label class="form-label" for="mm_mode">Opérateur</label>
                            <select id="mm_mode" name="mm_mode" class="form-select">
                                <option value="">— Choisir —</option>
                                <option value="airtel-ga"      <?= old('mm_mode')==='airtel-ga'?'selected':'' ?>>Airtel Money</option>
                                <option value="moov-ga"        <?= old('mm_mode')==='moov-ga'?'selected':'' ?>>Moov Money</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="mm_numero">Numéro Mobile Money</label>
                            <input type="tel" id="mm_numero" name="mm_numero" class="form-input"
                                   value="<?= old('mm_numero') ?>" placeholder="+241 06 XX XX XX">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="mm_titulaire">Titulaire du compte</label>
                            <input type="text" id="mm_titulaire" name="mm_titulaire" class="form-input"
                                   value="<?= old('mm_titulaire') ?>" placeholder="Nom complet">
                        </div>
                    </div>
                    <p style="font-size:12px;color:var(--txt-l);margin-top:4px;">
                        ℹ️ L'inscription payante ne sera activée qu'une fois ce compte validé (Phase 6).
                    </p>
                </div>
            </section>

            <!-- §8 Composition du dossier d'inscription -->
            <section class="or-section" id="sec-dossier">
                <div class="or-section-header">
                    <div class="or-section-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 12h6M9 16h6M9 8h2"/>
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="or-section-title">Dossier d'inscription <span class="or-optional">optionnel</span></p>
                        <p class="or-section-sub">Pièces à fournir — imprimées sur l'attestation, la proforma et la page publique</p>
                    </div>
                </div>
                <div class="or-section-body">
                    <div class="or-upload-hint" style="margin:-4px 0 16px;">
                        Ce que le candidat devra <strong>apporter</strong> pour transformer sa
                        préinscription en inscription. La liste est reprise telle quelle sur
                        l'<strong>attestation de préinscription</strong>, sur la <strong>facture
                        proforma</strong> et sur la <strong>page publique</strong> de l'établissement.
                        <br>Les pièces <strong>communes</strong> valent pour tout le monde ; celles d'un
                        cycle <strong>s'y ajoutent</strong>. Un candidat de licence ne voit jamais les
                        pièces du master, et inversement — le cycle est déduit de son niveau d'entrée
                        (L1 → L3 = Licence, M1 → M2 = Master).
                        <br><strong>Tout laisser vide ne casse rien</strong> : les documents gardent alors
                        leur formulation générique, comme aujourd'hui.
                    </div>
                    <?php
                    // Libellés du FORMULAIRE (plus explicites que ceux imprimés sur les
                    // documents, qui restent la propriété de CompositionDossier).
                    $compoChamps = [
                        'commun'  => [
                            'Pièces communes à tous les candidats',
                            'Ex : Acte de naissance légalisé',
                            'Exigées de tout candidat, quel que soit le cycle visé.',
                        ],
                        'licence' => [
                            'Cycle Licence — pièces supplémentaires',
                            'Ex : Relevé de notes du baccalauréat',
                            "S'ajoutent aux pièces communes pour un candidat de Licence 1 à 3.",
                        ],
                        'master'  => [
                            'Cycle Master — pièces supplémentaires',
                            'Ex : Diplôme de licence et relevés des trois années',
                            "S'ajoutent aux pièces communes pour un candidat de Master 1 ou 2.",
                        ],
                    ];
                    foreach ($compoChamps as $compoCle => [$compoLabel, $compoExemple, $compoAide]):
                        $compoListId  = 'compo-' . $compoCle . '-list';
                        $compoInputId = 'compo-' . $compoCle . '-input';
                        // addTag() de cette vue construit le name à partir de la
                        // clé : « composition_licence » donne « composition_licence[] ».
                        $compoCleJs   = 'composition_' . $compoCle;
                        $compoChamp   = $compoCleJs . '[]';
                    ?>
                    <div class="form-group">
                        <label class="form-label" for="<?= $compoInputId ?>"><?= e($compoLabel) ?></label>
                        <div class="or-tag-list" id="<?= $compoListId ?>">
                            <?php foreach (($compoPrefill[$compoCle] ?? []) as $compoPiece): ?>
                            <div class="or-tag">
                                <input type="hidden" name="<?= $compoChamp ?>" value="<?= e($compoPiece) ?>">
                                <span><?= e($compoPiece) ?></span>
                                <button type="button" class="or-tag-del" onclick="removeTag(this)">×</button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="or-add-row">
                            <input type="text" class="form-input or-add-input" id="<?= $compoInputId ?>"
                                   placeholder="<?= e($compoExemple) ?>" maxlength="200"
                                   onkeydown="if(event.key==='Enter'){event.preventDefault();addTag('<?= $compoCleJs ?>','<?= $compoInputId ?>',null,'<?= $compoListId ?>');}">
                            <button type="button" class="btn-add-tag" onclick="addTag('<?= $compoCleJs ?>','<?= $compoInputId ?>',null,'<?= $compoListId ?>')">+</button>
                        </div>
                        <div class="or-upload-hint"><?= e($compoAide) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- §10 Formations -->
            <section class="or-section" id="sec-formations">
                <div class="or-section-header">
                    <div class="or-section-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 14l9-5-9-5-9 5 9 5z"/>
                            <path d="M12 14l6.16-3.422a12.083 12.083 0 0 1 .665 6.479A11.952 11.952 0 0 0 12 20.055a11.952 11.952 0 0 0-6.824-2.998 12.078 12.078 0 0 1 .665-6.479L12 14z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="or-section-title">Formations <span>*</span></p>
                        <p class="or-section-sub">Au moins une formation obligatoire</p>
                    </div>
                </div>
                <div class="or-section-body">
                    <?= err('formations') ?>
                    <div id="formations-list"></div>
                    <button type="button" class="btn-outline" style="width:100%;margin-top:4px" onclick="addFormation()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:6px;vertical-align:-1px">
                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Ajouter une formation
                    </button>
                </div>
            </section>

            <!-- §11 Gestionnaires -->
            <?php
            // Blocs à ré-afficher : ceux de la soumission précédente (erreur de
            // validation) ou un bloc vide pour une création neuve. Les mots de
            // passe ne reviennent jamais (oldSansMotDePasse), seule l'identité
            // est ré-affichée — l'admin ne resaisit pas tout à cause d'un
            // email en double.
            $gBlocs = [];
            foreach ((array)($GLOBALS['__or_old']['gestionnaires'] ?? []) as $gKey => $gVal) {
                if (is_array($gVal)) {
                    $gBlocs[(int)$gKey] = $gVal;
                }
            }
            if ($gBlocs === []) {
                // Repli sur les champs de l'ancien formulaire mono-gestionnaire.
                $gBlocs = [0 => [
                    'prenom' => $GLOBALS['__or_old']['gestionnaire_prenom'] ?? '',
                    'nom'    => $GLOBALS['__or_old']['gestionnaire_nom']    ?? '',
                    'email'  => $GLOBALS['__or_old']['gestionnaire_email']  ?? '',
                ]];
            }
            ?>
            <section class="or-section" id="sec-gestionnaire">
                <div class="or-section-header">
                    <div class="or-section-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                    </div>
                    <div>
                        <p class="or-section-title">Comptes gestionnaires <span>*</span></p>
                        <p class="or-section-sub">Un ou plusieurs comptes — chacun reçoit ses identifiants par email</p>
                    </div>
                </div>
                <div class="or-section-body">

                    <div id="gestionnaires-list">
                        <?php $gRang = 0; foreach ($gBlocs as $gi => $gb): $gRang++; ?>
                        <div class="or-gest-block" data-gest-block data-gest-index="<?= (int)$gi ?>">
                            <div class="or-gest-head">
                                <span class="or-gest-title">Gestionnaire <span data-gest-rang><?= $gRang ?></span></span>
                                <button type="button" class="or-gest-del" onclick="removeGestionnaire(this)" title="Retirer ce gestionnaire">×</button>
                            </div>

                            <div class="or-grid-2">
                                <div class="form-group">
                                    <label class="form-label" for="g-prenom-<?= (int)$gi ?>">Prénom <span>*</span></label>
                                    <input type="text" id="g-prenom-<?= (int)$gi ?>" name="gestionnaires[<?= (int)$gi ?>][prenom]"
                                           class="form-input<?= hasErr('gestionnaire_prenom_' . (int)$gi) ?>"
                                           value="<?= e((string)($gb['prenom'] ?? '')) ?>" required>
                                    <?= err('gestionnaire_prenom_' . (int)$gi) ?>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="g-nom-<?= (int)$gi ?>">Nom <span>*</span></label>
                                    <input type="text" id="g-nom-<?= (int)$gi ?>" name="gestionnaires[<?= (int)$gi ?>][nom]"
                                           class="form-input<?= hasErr('gestionnaire_nom_' . (int)$gi) ?>"
                                           value="<?= e((string)($gb['nom'] ?? '')) ?>" required>
                                    <?= err('gestionnaire_nom_' . (int)$gi) ?>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="g-email-<?= (int)$gi ?>">Email <span>*</span></label>
                                <input type="email" id="g-email-<?= (int)$gi ?>" name="gestionnaires[<?= (int)$gi ?>][email]"
                                       class="form-input<?= hasErr('gestionnaire_email_' . (int)$gi) ?>"
                                       value="<?= e((string)($gb['email'] ?? '')) ?>" required
                                       placeholder="gestionnaire@ecole.ga">
                                <?= err('gestionnaire_email_' . (int)$gi) ?>
                            </div>

                            <div class="or-grid-2">
                                <div class="form-group">
                                    <label class="form-label" for="g-mdp-<?= (int)$gi ?>">Mot de passe <span class="or-optional">optionnel</span></label>
                                    <div style="position:relative;">
                                        <input type="password" id="g-mdp-<?= (int)$gi ?>" name="gestionnaires[<?= (int)$gi ?>][password]"
                                               class="form-input<?= hasErr('gestionnaire_password_' . (int)$gi) ?>"
                                               data-gest-mdp minlength="8" autocomplete="new-password"
                                               placeholder="8 caractères minimum" style="padding-right:44px;">
                                        <button type="button" onclick="toggleMdp(this)" title="Afficher / masquer"
                                                style="position:absolute;right:6px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--txt-l);padding:6px;">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                            </svg>
                                        </button>
                                    </div>
                                    <?= err('gestionnaire_password_' . (int)$gi) ?>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="g-mdp-c-<?= (int)$gi ?>">Confirmation</label>
                                    <input type="password" id="g-mdp-c-<?= (int)$gi ?>" name="gestionnaires[<?= (int)$gi ?>][password_confirm]"
                                           class="form-input<?= hasErr('gestionnaire_password_confirm_' . (int)$gi) ?>"
                                           data-gest-mdp-confirm minlength="8" autocomplete="new-password"
                                           placeholder="Retape le mot de passe">
                                    <?= err('gestionnaire_password_confirm_' . (int)$gi) ?>
                                </div>
                            </div>

                            <div style="margin:-6px 0 2px;">
                                <button type="button" class="btn-outline btn-sm" onclick="genererMdp(this)">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:5px;vertical-align:-2px">
                                        <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0 3 3L22 7l-3-3m-3.5 3.5L19 4"/>
                                    </svg>
                                    Générer un mot de passe fort
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <button type="button" class="btn-outline" style="width:100%;margin-top:4px" onclick="addGestionnaire()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:6px;vertical-align:-1px">
                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Ajouter un gestionnaire
                    </button>

                    <div style="background:var(--ap-xl);border-radius:10px;padding:14px 16px;font-size:13px;color:var(--txt-m);margin-top:14px;">
                        ℹ️ Laisse le mot de passe vide pour en générer un temporaire automatiquement. Dans tous les cas, chaque gestionnaire reçoit ses identifiants par email et accède au même espace établissement.
                    </div>
                </div>
            </section>

        </div><!-- /.or-form-content -->
    </div><!-- /.or-form-layout -->

    <!-- Footer sticky -->
    <div class="or-form-footer">
        <a href="<?= url('/admin/orientation') ?>" class="btn-outline">Annuler</a>
        <button type="submit" class="btn-admin-primary" id="or-submit-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:6px;vertical-align:-2px">
                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                <polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
            </svg>
            Créer l'établissement
        </button>
    </div>
</form>

<script>
// ── Compteur formations ────────────────────────────────────────────────
var formationCount = 0;

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
var tagData = { badges: [], atouts: [], modes: [],
                composition_commun: [], composition_licence: [], composition_master: [] };

function addTag(key, inputId, hiddenId, listId, namedInput) {
    var input = document.getElementById(inputId);
    var val = input.value.trim();
    if (!val) return;
    tagData[key].push(val);

    var list = document.getElementById(listId);
    var tag  = document.createElement('div');
    tag.className = 'or-tag';
    var idx = tagData[key].length - 1;
    tag.innerHTML = (namedInput
        ? '<input type="hidden" name="mode_paiement[]" value="' + escHtml(val) + '">'
        : '<input type="hidden" name="' + key + '[]" value="' + escHtml(val) + '">') +
        '<span>' + escHtml(val) + '</span>' +
        '<button type="button" class="or-tag-del" onclick="removeTag(this)">×</button>';
    list.appendChild(tag);
    input.value = '';
    input.focus();
}

function removeTag(btn) { btn.parentElement.remove(); }

function escHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Statistiques ───────────────────────────────────────────────────────
var statCount = 0;
function addStatRow() {
    var i   = statCount++;
    var row = document.createElement('div');
    row.className = 'or-stat-row';
    row.id = 'stat-row-' + i;
    row.innerHTML =
        '<input type="text" name="stat_label[]" class="form-input" placeholder="Ex : Taux d\'insertion">' +
        '<input type="text" name="stat_valeur[]" class="form-input" placeholder="Ex : 87%">' +
        '<button type="button" class="or-btn-remove" style="position:static" onclick="this.parentElement.remove()">×</button>';
    document.getElementById('stats-rows').appendChild(row);
}

// ── Gestionnaires : blocs répétables ───────────────────────────────────
// Un établissement peut recevoir plusieurs comptes dès sa création. Chaque
// bloc porte son propre index dans le nom des champs
// (gestionnaires[3][email]) : retirer un bloc laisse un trou dans la
// numérotation, ce que PHP accepte sans broncher — seul le rang AFFICHÉ est
// renuméroté, pour que l'écran reste lisible.
var gestionnaireIndex = document.querySelectorAll('[data-gest-block]').length;

function addGestionnaire() {
    var i    = gestionnaireIndex++;
    var wrap = document.createElement('div');
    wrap.className = 'or-gest-block';
    wrap.setAttribute('data-gest-block', '');
    wrap.setAttribute('data-gest-index', i);
    wrap.innerHTML =
        '<div class="or-gest-head">' +
          '<span class="or-gest-title">Gestionnaire <span data-gest-rang></span></span>' +
          '<button type="button" class="or-gest-del" onclick="removeGestionnaire(this)" title="Retirer ce gestionnaire">×</button>' +
        '</div>' +
        '<div class="or-grid-2">' +
          '<div class="form-group">' +
            '<label class="form-label" for="g-prenom-' + i + '">Prénom <span>*</span></label>' +
            '<input type="text" id="g-prenom-' + i + '" name="gestionnaires[' + i + '][prenom]" class="form-input" required>' +
          '</div>' +
          '<div class="form-group">' +
            '<label class="form-label" for="g-nom-' + i + '">Nom <span>*</span></label>' +
            '<input type="text" id="g-nom-' + i + '" name="gestionnaires[' + i + '][nom]" class="form-input" required>' +
          '</div>' +
        '</div>' +
        '<div class="form-group">' +
          '<label class="form-label" for="g-email-' + i + '">Email <span>*</span></label>' +
          '<input type="email" id="g-email-' + i + '" name="gestionnaires[' + i + '][email]" class="form-input" required placeholder="gestionnaire@ecole.ga">' +
        '</div>' +
        '<div class="or-grid-2">' +
          '<div class="form-group">' +
            '<label class="form-label" for="g-mdp-' + i + '">Mot de passe <span class="or-optional">optionnel</span></label>' +
            '<div style="position:relative;">' +
              '<input type="password" id="g-mdp-' + i + '" name="gestionnaires[' + i + '][password]" class="form-input" data-gest-mdp minlength="8" autocomplete="new-password" placeholder="8 caractères minimum" style="padding-right:44px;">' +
              '<button type="button" onclick="toggleMdp(this)" title="Afficher / masquer" style="position:absolute;right:6px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--txt-l);padding:6px;">' +
                '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>' +
              '</button>' +
            '</div>' +
          '</div>' +
          '<div class="form-group">' +
            '<label class="form-label" for="g-mdp-c-' + i + '">Confirmation</label>' +
            '<input type="password" id="g-mdp-c-' + i + '" name="gestionnaires[' + i + '][password_confirm]" class="form-input" data-gest-mdp-confirm minlength="8" autocomplete="new-password" placeholder="Retape le mot de passe">' +
          '</div>' +
        '</div>' +
        '<div style="margin:-6px 0 2px;">' +
          '<button type="button" class="btn-outline btn-sm" onclick="genererMdp(this)">Générer un mot de passe fort</button>' +
        '</div>';

    document.getElementById('gestionnaires-list').appendChild(wrap);
    renumeroterGestionnaires();
    var premier = wrap.querySelector('input');
    if (premier) premier.focus();
}

function removeGestionnaire(btn) {
    var blocs = document.querySelectorAll('[data-gest-block]');
    // Le dernier bloc n'est jamais supprimable : un établissement sans
    // gestionnaire n'a personne pour ouvrir son guichet.
    if (blocs.length <= 1) {
        alert('Au moins un compte gestionnaire est obligatoire.');
        return;
    }
    btn.closest('[data-gest-block]').remove();
    renumeroterGestionnaires();
}

function renumeroterGestionnaires() {
    var blocs = document.querySelectorAll('[data-gest-block]');
    blocs.forEach(function (bloc, n) {
        var rang = bloc.querySelector('[data-gest-rang]');
        if (rang) rang.textContent = (n + 1);
        var del = bloc.querySelector('.or-gest-del');
        if (del) del.style.visibility = blocs.length > 1 ? 'visible' : 'hidden';
    });
}

// ── Mot de passe d'UN gestionnaire (bloc courant) ─────────────────────
function toggleMdp(btn) {
    var bloc = btn.closest('[data-gest-block]');
    var inp  = bloc.querySelector('[data-gest-mdp]');
    var cnf  = bloc.querySelector('[data-gest-mdp-confirm]');
    var t = inp.type === 'password' ? 'text' : 'password';
    inp.type = t; cnf.type = t;
}

function genererMdp(btn) {
    var chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%';
    var arr = new Uint32Array(14);
    crypto.getRandomValues(arr);
    var mdp = '';
    for (var i = 0; i < arr.length; i++) mdp += chars[arr[i] % chars.length];
    var bloc = btn.closest('[data-gest-block]');
    var inp  = bloc.querySelector('[data-gest-mdp]');
    var cnf  = bloc.querySelector('[data-gest-mdp-confirm]');
    inp.value = mdp; cnf.value = mdp;
    inp.type = 'text'; cnf.type = 'text'; // visible pour que l'admin puisse le noter
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
        label.textContent = 'Jusqu\'à 10 photos';
        return;
    }
    label.textContent = n > 0 ? n + ' photo(s) sélectionnée(s) ✓' : 'Jusqu\'à 10 photos';
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

// ── Soumission + upload direct des médias : géré par le module partagé
//    assets/js/orientation-media-upload.js (inclus plus bas). Il intercepte le
//    submit, pousse logo/photos/vidéo DIRECTEMENT vers B2 (URL présignée), puis
//    envoie le formulaire allégé. Plus aucun timeout serveur sur gros fichiers.

// ── Aperçu commission = frais de scolarité × taux ─────────────────────
(function () {
    var scoL = document.querySelector('[data-frais-scolarite]');
    var scoM = document.querySelector('[data-frais-scolarite-master]');
    var tx   = document.querySelector('[data-taux-commission]');
    var out  = document.getElementById('commission-preview');
    if (!tx || !out) return;

    function fmt(n) { return n.toLocaleString('fr-FR').replace(/ | /g, ' '); }

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

// ── Init : ajouter 1 formation par défaut ─────────────────────────────
addFormation();

// ── Init : rangs et bouton « retirer » des blocs gestionnaires ────────
renumeroterGestionnaires();
</script>
<script src="<?= asset('js/orientation-media-upload.js') ?>"></script>
