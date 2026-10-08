<?php
// Variables: $etablissements, $stats, $statsGlobales, $statsParEtab, $statsVilles,
//            $avisAgg, $gestionnaires, $flash, $flashError, $nbARejouer
$etablissements = $etablissements ?? [];
$stats          = ($stats ?? []) + ['total' => 0, 'actif' => 0, 'brouillon' => 0, 'inactif' => 0];
$statsGlobales  = $statsGlobales  ?? ['nb_dossiers'=>0,'nb_preinscriptions'=>0,'nb_inscriptions'=>0,'revenus_total'=>0,'nb_gestionnaires'=>0];
$statsParEtab   = $statsParEtab   ?? [];
$statsVilles    = $statsVilles    ?? [];
$avisAgg        = $avisAgg        ?? [];
$gestionnaires  = $gestionnaires  ?? [];
$flash          = $flash ?? null;
$flashError     = $flashError ?? null;
$nbARejouer     = $nbARejouer ?? 0;

// Couleur par établissement pour les donuts : couleur de marque si valide,
// sinon couleur de repli — jamais deux établissements avec la même couleur.
$donutPalette = ['#8B52FA', '#D97757', '#2563EB', '#00795A', '#C13584', '#9A6A00', '#0E7490', '#B45309'];
$donutUsed    = [];
$etabColors   = [];
foreach ($statsParEtab as $row) {
    $c = preg_match('/^#[0-9A-Fa-f]{6}$/', $row['couleur_principale'] ?? '') ? strtoupper($row['couleur_principale']) : null;
    if ($c === null || in_array($c, $donutUsed, true)) {
        foreach ($donutPalette as $p) {
            if (!in_array(strtoupper($p), $donutUsed, true)) { $c = strtoupper($p); break; }
        }
        $c = $c ?? '#8B52FA';
    }
    $donutUsed[] = $c;
    $etabColors[(int)$row['id']] = $c;
}

// Segments donut "Préinscriptions par établissement" (dossiers soumis)
$segPreinscriptions = [];
foreach ($statsParEtab as $row) {
    if ((int)($row['nb_preinscriptions'] ?? 0) > 0) {
        $segPreinscriptions[] = ['label' => $row['nom'], 'n' => (int)$row['nb_preinscriptions'], 'color' => $etabColors[(int)$row['id']]];
    }
}
usort($segPreinscriptions, static fn(array $a, array $b): int => $b['n'] <=> $a['n']);

// Segments donut "Inscriptions par établissement"
$segInscriptions = [];
foreach ($statsParEtab as $row) {
    if ((int)$row['nb_inscriptions'] > 0) {
        $segInscriptions[] = ['label' => $row['nom'], 'n' => (int)$row['nb_inscriptions'], 'color' => $etabColors[(int)$row['id']]];
    }
}
usort($segInscriptions, static fn(array $a, array $b): int => $b['n'] <=> $a['n']);

// Segments donut "Revenus par établissement"
$segRevenus = [];
foreach ($statsParEtab as $row) {
    if ((int)$row['revenus'] > 0) {
        $segRevenus[] = ['label' => $row['nom'], 'n' => (int)$row['revenus'], 'color' => $etabColors[(int)$row['id']]];
    }
}
usort($segRevenus, static fn(array $a, array $b): int => $b['n'] <=> $a['n']);

$typeLabels = ['universite' => 'Université', 'grande_ecole' => 'Grande école', 'institut' => 'Institut'];

// Colonnes kanban établissements — groupées par statut
$colonnes = [
    'actif'     => ['label' => 'Actifs',     'dot' => 'var(--green)', 'items' => []],
    'brouillon' => ['label' => 'Brouillons', 'dot' => 'var(--amber)', 'items' => []],
    'inactif'   => ['label' => 'Inactifs',   'dot' => 'var(--red)',   'items' => []],
];
foreach ($etablissements as $e) {
    $key = isset($colonnes[$e['statut']]) ? $e['statut'] : 'brouillon';
    $colonnes[$key]['items'][] = $e;
}

// Colonnes kanban gestionnaires — accès actif / suspendu
$gestActifs    = array_values(array_filter($gestionnaires, static fn(array $g): bool => (bool)$g['is_active']));
$gestSuspendus = array_values(array_filter($gestionnaires, static fn(array $g): bool => !$g['is_active']));
?>

<?php if ($flash): ?>
<div class="flash-banner flash-success" role="alert">
    <?= e($flash) ?>
    <button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;float:right;font-size:18px;line-height:1;color:inherit;">&times;</button>
</div>
<?php endif; ?>
<?php if ($flashError): ?>
<div class="flash-banner flash-error" role="alert">
    <?= e($flashError) ?>
    <button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;float:right;font-size:18px;line-height:1;color:inherit;">&times;</button>
</div>
<?php endif; ?>

<style>
/* ══════════════════════════════════════════════════════════════════
   Orientation admin — Connect'Academia × Liquid Glass
   Fond ambiant doux, cartes en verre translucide, board kanban
   ══════════════════════════════════════════════════════════════════ */
.flash-banner {
    border-radius: 12px;
    padding: 14px 20px;
    margin-bottom: 20px;
    font-size: 14px;
    font-weight: 500;
}
.flash-success { background: var(--green-bg); color: #15803d; border: 1px solid #bbf7d0; }
.flash-error   { background: var(--red-bg);   color: #b91c1c; border: 1px solid #fca5a5; }

/* Surfaces pleines, hairlines, aucune transparence : design system Claude.
   Le canvas crème (#FAF9F5) d'admin-claude.css sert de fond — pas de wash. */
.or-page { position: relative; }

/* ── Cartes ───────────────────────────────────────────────────────── */
.or-glass {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    box-shadow: 0 1px 2px rgba(26,26,26,.04);
    transition: border-color .16s ease, box-shadow .16s ease;
}
.or-glass:hover { border-color: var(--border-strong); box-shadow: 0 2px 8px rgba(26,26,26,.06); }

/* ── KPI — bloc soudé, séparateurs 1px, aucune ombre par carte ────── */
.or-kpi-row, .or-kpi-row-2 {
    display: grid; gap: 1px;
    background: var(--border);
    border: 1px solid var(--border);
    border-radius: 16px; overflow: hidden;
    box-shadow: 0 1px 2px rgba(26,26,26,.04);
}
.or-kpi-row   { grid-template-columns: repeat(4,1fr); margin-bottom: 16px; }
.or-kpi-row-2 { grid-template-columns: repeat(2,1fr); margin-bottom: 24px; }
.or-kpi {
    background: var(--card); padding: 20px 24px;
    border: 0; border-radius: 0; box-shadow: none;
    transition: background .14s ease;
}
.or-kpi:hover { background: var(--ap-xl); border-color: transparent; box-shadow: none; }
.or-kpi .kpi-val   { font-size: 28px; font-weight: 800; color: var(--txt); letter-spacing: -.025em; font-variant-numeric: tabular-nums; line-height: 1; }
.or-kpi .kpi-label { font-size: 11px; color: var(--txt-l); margin-top: 8px; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; }
.or-kpi.kpi-purple .kpi-val { color: var(--ap); }

/* ── Actions d'en-tête ────────────────────────────────────────────── */
.or-head-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
/* Le bouton primaire d'admin.css est calibré pour les formulaires (width:100%) :
   on le ramène à une taille de barre d'actions, alignée sur la réconciliation. */
.or-head-actions .btn-admin-primary {
    width: auto; height: 38px; padding: 0 18px;
    border-radius: var(--r-pill); font-size: 13px; letter-spacing: 0;
    text-decoration: none; white-space: nowrap;
}

/* Réconciliation — bouton secondaire hairline, compteur intégré (pas de
   pastille flottante rognée) ; passe en rouge quand des reversements
   attendent d'être rejoués. */
.or-btn-reconcile {
    display: inline-flex; align-items: center; gap: 8px;
    height: 38px; padding: 0 16px; border-radius: var(--r-pill);
    border: 1px solid var(--border-strong); background: var(--card);
    font-family: var(--font-body); font-size: 13px; font-weight: 600;
    color: var(--txt-m); text-decoration: none; white-space: nowrap;
    transition: border-color .14s ease, color .14s ease, background .14s ease;
}
.or-btn-reconcile svg { width: 15px; height: 15px; flex: none; }
.or-btn-reconcile:hover { border-color: var(--ap); color: var(--ap); background: var(--ap-lt); }
.or-btn-count {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 20px; height: 20px; padding: 0 6px; border-radius: 999px;
    background: var(--red); color: #fff;
    font-size: 11px; font-weight: 800; font-variant-numeric: tabular-nums;
}
.or-btn-reconcile.is-alert { border-color: #F3B0B0; color: #B91C1C; background: var(--red-bg); }
.or-btn-reconcile.is-alert:hover { border-color: var(--red); background: #FFDFDF; color: #B91C1C; }

/* ── Cartes donut (villes + préinscriptions/inscriptions/revenus) ─── */
.or-geo-card { padding: 26px 28px; margin-bottom: 28px; }
.or-trio { display: grid; grid-template-columns: repeat(3,1fr); gap: 16px; margin-bottom: 28px; }
.or-trio .or-geo-card { margin-bottom: 0; }
.or-geo-head { display: flex; align-items: baseline; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 18px; }
.or-geo-head h2 { font-size: 16px; font-weight: 700; color: var(--txt); margin: 0; letter-spacing: -.01em; }
.or-geo-head p  { font-size: 12px; color: var(--txt-l); margin: 4px 0 0; }

.dv-flex { display: flex; align-items: center; gap: 40px; flex-wrap: wrap; }
.dv-donut-box { position: relative; width: 190px; height: 190px; flex: none; margin: 0 auto; }
.dv-donut { width: 100%; height: 100%; display: block; }
.dv-center {
    position: absolute; inset: 0; display: flex; flex-direction: column;
    align-items: center; justify-content: center; pointer-events: none;
}
.dv-center-val   { font-size: 30px; font-weight: 800; color: var(--txt); letter-spacing: -.03em; line-height: 1; }
.dv-center-val--sm { font-size: 19px; }
.dv-center-label { font-size: 11px; color: var(--txt-l); margin-top: 5px; font-weight: 600; }
.dv-legend { list-style: none; margin: 0; padding: 0; flex: 1; min-width: 240px; display: flex; flex-direction: column; gap: 9px; }
.dv-legend-row { display: flex; align-items: center; gap: 10px; font-size: 13px; }
.dv-chip { width: 11px; height: 11px; border-radius: 4px; flex: none; }
.dv-legend-ville { font-weight: 600; color: var(--txt); flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.dv-legend-n   { font-weight: 700; color: var(--txt); font-variant-numeric: tabular-nums; }
.dv-legend-pct { color: var(--txt-l); font-size: 12px; width: 42px; text-align: right; font-variant-numeric: tabular-nums; }
.dv-empty { text-align: center; padding: 28px 16px; color: var(--txt-l); font-size: 13px; }
.dv-empty svg { opacity: .3; margin: 0 auto 10px; display: block; }

/* Trois donuts côte à côte : colonne étroite → donut au-dessus de la légende */
.or-trio .dv-flex   { flex-direction: column; align-items: stretch; gap: 22px; }
.or-trio .dv-donut-box { width: 168px; height: 168px; }
.or-trio .dv-legend { min-width: 0; width: 100%; }
.or-trio .dv-legend-row { font-size: 12.5px; }

/* ── Board kanban ─────────────────────────────────────────────────── */
.or-board-head {
    display: flex; align-items: center; justify-content: space-between;
    gap: 16px; flex-wrap: wrap; margin: 0 0 16px;
}
.or-board-head h2 { font-size: 17px; font-weight: 700; color: var(--txt); margin: 0; letter-spacing: -.01em; }
.or-board-head p  { font-size: 12px; color: var(--txt-l); margin: 4px 0 0; }
.or-search {
    padding: 10px 16px 10px 38px; min-width: 260px;
    font-family: inherit; font-size: 13px; font-weight: 500; color: var(--txt);
    background: var(--card) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='%239E9E9E' stroke-width='2'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='M21 21l-4.35-4.35'/%3E%3C/svg%3E") no-repeat 13px center;
    border: 1px solid var(--border-strong); border-radius: var(--r-pill);
    outline: none; transition: border-color .16s, box-shadow .16s;
}
.or-search:focus { border-color: var(--ap); box-shadow: 0 0 0 3px var(--ap-glow-20); }

.or-board { display: grid; grid-template-columns: repeat(3,1fr); gap: 18px; align-items: start; margin-bottom: 34px; }
.or-board.cols-2 { grid-template-columns: repeat(2,1fr); max-width: 900px; }
.or-col-head {
    display: flex; align-items: center; gap: 9px;
    padding: 6px 4px 12px; font-size: 11.5px; font-weight: 700;
    letter-spacing: .12em; text-transform: uppercase; color: var(--txt-m);
}
.or-col-dot   { width: 8px; height: 8px; border-radius: 999px; flex: none; }
.or-col-count { margin-left: auto; font-size: 12px; font-weight: 700; color: var(--txt-l); letter-spacing: 0; }
.or-col-body  { display: flex; flex-direction: column; gap: 12px; min-height: 40px; }
.or-col-empty {
    font-size: 12px; color: var(--txt-l); text-align: center;
    padding: 22px 12px; border: 1.5px dashed var(--border-strong); border-radius: 16px;
}

/* ── Carte kanban ─────────────────────────────────────────────────── */
.kb-card {
    position: relative; overflow: hidden;
    border-radius: 16px; padding: 16px 18px 14px 21px;
    display: flex; flex-direction: column; gap: 10px;
}
.kb-card::before {
    content: ''; position: absolute; top: 0; left: 0; bottom: 0; width: 4px;
    background: var(--kb-accent, var(--ap)); border-radius: 4px 0 0 4px;
}
.kb-card:hover { border-color: var(--border-strong); box-shadow: 0 4px 14px rgba(26,26,26,.07); }

.kb-top { display: flex; align-items: center; gap: 8px; font-size: 11.5px; font-weight: 600; color: var(--txt-m); flex-wrap: wrap; }
.kb-top .kb-dot { width: 7px; height: 7px; border-radius: 999px; background: var(--kb-accent, var(--ap)); flex: none; }
.kb-top .kb-ville { color: var(--txt-l); font-weight: 500; }
.kb-top .kb-ville::before { content: '·'; margin-right: 8px; color: var(--txt-l); }

.kb-title { font-size: 15px; font-weight: 700; color: var(--txt); letter-spacing: -.01em; line-height: 1.3; margin: 0; }
.kb-title a { color: inherit; text-decoration: none; }
.kb-title a:hover { color: var(--ap); }
.kb-slug { font-size: 11px; color: var(--txt-l); font-family: ui-monospace, monospace; }

.kb-meta { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; font-size: 12px; color: var(--txt-m); }
.kb-meta span { display: inline-flex; align-items: center; gap: 5px; }
.kb-meta svg  { width: 13px; height: 13px; color: var(--txt-l); flex: none; }
.kb-note { color: var(--txt); font-weight: 600; }
.kb-note .star { color: #E8A400; }

.kb-badges { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.kb-chip {
    font-size: 10.5px; font-weight: 700; padding: 3px 9px;
    border-radius: var(--r-pill); letter-spacing: .02em;
}
.kb-chip-green { background: var(--green-bg); color: #15803d; }
.kb-chip-amber { background: var(--amber-bg); color: #92600a; }
.kb-chip-red   { background: var(--red-bg);   color: #b91c1c; }
.kb-chip-mute  { background: rgba(26,26,26,.05); color: var(--txt-m); }
.kb-chip-purple{ background: var(--ap-lt); color: var(--ap); }

.kb-foot {
    display: flex; align-items: center; gap: 8px;
    padding-top: 10px; border-top: 1px solid var(--border);
}
.kb-date { font-size: 11px; color: var(--txt-l); font-weight: 500; }
.kb-actions { margin-left: auto; display: flex; align-items: center; gap: 2px; }
.kb-act {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: 9px; border: none; cursor: pointer;
    background: transparent; color: var(--txt-m);
    transition: background .14s, color .14s;
    text-decoration: none; font-size: 13px; line-height: 1;
}
.kb-act:hover { background: var(--ap-lt); color: var(--ap); }
.kb-act svg { width: 14px; height: 14px; }
.kb-avatar {
    width: 30px; height: 30px; border-radius: 999px; flex: none;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 800; color: #fff;
    background: var(--kb-accent, var(--ap));
    box-shadow: 0 2px 8px rgba(26,26,26,.18);
}

.kb-mail { font-size: 12px; color: var(--txt-m); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.kb-btn-toggle {
    font-family: inherit; font-size: 12px; font-weight: 700; cursor: pointer;
    padding: 6px 14px; border-radius: var(--r-pill); border: 1.5px solid var(--border-strong);
    background: var(--card); transition: all .16s;
}
.kb-btn-toggle.is-danger  { color: var(--red); }
.kb-btn-toggle.is-danger:hover  { border-color: var(--red); background: var(--red-bg); }
.kb-btn-toggle.is-success { color: #15803d; }
.kb-btn-toggle.is-success:hover { border-color: var(--green); background: var(--green-bg); }

.empty-state { text-align: center; padding: 64px 20px; color: var(--txt-l); }
.empty-state svg { opacity: 0.3; margin-bottom: 16px; }
.empty-state p { font-size: 15px; margin-bottom: 20px; }

@media (max-width: 1280px) {
    .or-trio { grid-template-columns: 1fr 1fr; }
    .or-trio > .or-geo-card:last-child { grid-column: 1 / -1; }
    .or-trio .dv-flex { flex-direction: row; align-items: center; gap: 32px; }
    .or-trio .dv-donut-box { width: 180px; height: 180px; }
    .or-trio .dv-legend { min-width: 220px; }
}
@media (max-width: 1100px) {
    .or-board, .or-board.cols-2 { grid-template-columns: 1fr; }
    .or-kpi-row { grid-template-columns: repeat(2,1fr); }
}
@media (max-width: 900px) {
    .or-trio { grid-template-columns: 1fr; }
    .or-trio > .or-geo-card:last-child { grid-column: auto; }
}
@media (max-width: 640px) {
    .or-kpi-row, .or-kpi-row-2 { grid-template-columns: 1fr 1fr; }
    .dv-flex, .or-trio .dv-flex { gap: 20px; flex-direction: column; align-items: stretch; }
    .or-trio .dv-legend { min-width: 0; }
    .dv-donut-box, .or-trio .dv-donut-box { width: 168px; height: 168px; }
    .or-search { min-width: 0; width: 100%; }
}
</style>

<div class="or-page">

<div class="admin-page-header-row">
    <div>
        <h1>Orientation</h1>
        <p>Gestion des établissements partenaires</p>
    </div>
    <div class="or-head-actions">
        <a href="<?= url('/admin/orientation/reconciliation') ?>"
           class="or-btn-reconcile<?= $nbARejouer > 0 ? ' is-alert' : '' ?>"
           title="<?= $nbARejouer > 0 ? $nbARejouer . ' reversement(s) à rejouer' : 'Aucun reversement en échec' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 12a9 9 0 0 1 15.36-6.36L21 8"/><path d="M21 3v5h-5"/>
                <path d="M21 12a9 9 0 0 1-15.36 6.36L3 16"/><path d="M3 21v-5h5"/>
            </svg>
            Réconciliation
            <?php if ($nbARejouer > 0): ?>
            <span class="or-btn-count"><?= (int)$nbARejouer ?></span>
            <?php endif; ?>
        </a>
        <a href="<?= url('/admin/orientation/creer') ?>" class="btn-admin-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:6px;vertical-align:-2px">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Ajouter un établissement
        </a>
    </div>
</div>

<!-- KPI établissements -->
<div class="or-kpi-row">
    <div class="or-kpi or-glass">
        <div class="kpi-val"><?= $stats['total'] ?></div>
        <div class="kpi-label">Total établissements</div>
    </div>
    <div class="or-kpi or-glass kpi-purple">
        <div class="kpi-val"><?= $stats['actif'] ?></div>
        <div class="kpi-label">Actifs</div>
    </div>
    <div class="or-kpi or-glass">
        <div class="kpi-val" style="color:var(--amber)"><?= $stats['brouillon'] ?></div>
        <div class="kpi-label">Brouillons</div>
    </div>
    <div class="or-kpi or-glass">
        <div class="kpi-val" style="color:var(--red)"><?= $stats['inactif'] ?></div>
        <div class="kpi-label">Inactifs</div>
    </div>
</div>

<!-- KPI globaux orientation -->
<div class="or-kpi-row-2">
    <div class="or-kpi or-glass">
        <div class="kpi-val"><?= number_format((int)$statsGlobales['nb_dossiers']) ?></div>
        <div class="kpi-label">Dossiers total</div>
    </div>
    <div class="or-kpi or-glass">
        <div class="kpi-val"><?= (int)$statsGlobales['nb_gestionnaires'] ?></div>
        <div class="kpi-label">Gestionnaires</div>
    </div>
</div>

<!-- Préinscriptions, inscriptions & revenus ventilés par établissement -->
<div class="or-trio">
    <div class="or-geo-card or-glass">
        <div class="or-geo-head">
            <div>
                <h2>Préinscriptions</h2>
                <p>Dossiers soumis en attente de traitement — répartition par établissement.</p>
            </div>
        </div>
        <?php
            $drItems       = $segPreinscriptions;
            $drCenterLabel = 'préinscriptions';
            $drCenterVal   = number_format((int)$statsGlobales['nb_preinscriptions'], 0, ',', ' ');
            $drUnit        = '';
            $drAria        = 'Préinscriptions par établissement';
            $drEmpty       = 'Aucune préinscription pour le moment — la répartition par établissement apparaîtra ici.';
            $drAutres      = 'Autres établissements';
            require dirname(__DIR__, 2) . '/partials/donut-repartition.php';
        ?>
    </div>
    <div class="or-geo-card or-glass">
        <div class="or-geo-head">
            <div>
                <h2>Inscriptions</h2>
                <p>Dossiers payés, acceptés ou inscrits — répartition par établissement.</p>
            </div>
        </div>
        <?php
            $drItems       = $segInscriptions;
            $drCenterLabel = 'inscriptions';
            $drCenterVal   = number_format((int)$statsGlobales['nb_inscriptions'], 0, ',', ' ');
            $drUnit        = '';
            $drAria        = 'Inscriptions par établissement';
            $drEmpty       = 'Aucune inscription pour le moment — la répartition par établissement apparaîtra ici.';
            $drAutres      = 'Autres établissements';
            require dirname(__DIR__, 2) . '/partials/donut-repartition.php';
        ?>
    </div>
    <div class="or-geo-card or-glass">
        <div class="or-geo-head">
            <div>
                <h2>Revenus totaux</h2>
                <p>Paiements encaissés (XAF) — répartition par établissement.</p>
            </div>
        </div>
        <?php
            $drItems       = $segRevenus;
            $drCenterLabel = 'XAF encaissés';
            $drCenterVal   = number_format((int)$statsGlobales['revenus_total'], 0, ',', ' ');
            $drUnit        = ' XAF';
            $drAria        = 'Revenus par établissement';
            $drEmpty       = 'Aucun paiement encaissé pour le moment — la répartition par établissement apparaîtra ici.';
            $drAutres      = 'Autres établissements';
            require dirname(__DIR__, 2) . '/partials/donut-repartition.php';
        ?>
    </div>
</div>

<!-- Répartition géographique des candidatures -->
<div class="or-geo-card or-glass">
    <div class="or-geo-head">
        <div>
            <h2>Villes des candidatures</h2>
            <p>Dossiers déposés (hors brouillons) selon la ville du candidat — toute la plateforme.</p>
        </div>
    </div>
    <?php
        $dvVilles = $statsVilles;
        $dvLabel  = 'candidatures';
        require dirname(__DIR__, 2) . '/partials/donut-villes.php';
    ?>
</div>

<?php if (empty($etablissements)): ?>
<div class="or-glass" style="padding:24px;">
    <div class="empty-state">
        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <rect x="3" y="3" width="18" height="18" rx="3"/><path d="M3 9h18M9 21V9"/>
        </svg>
        <p>Aucun établissement enregistré.</p>
        <a href="<?= url('/admin/orientation/creer') ?>" class="btn-admin-primary">Ajouter le premier</a>
    </div>
</div>
<?php else: ?>

<!-- ── Board établissements ─────────────────────────────────────────── -->
<div class="or-board-head">
    <div>
        <h2>Établissements (<?= count($etablissements) ?>)</h2>
        <p>Cartes groupées par statut — un clic sur le nom ouvre la fiche complète.</p>
    </div>
    <input type="text" id="etab-search" class="or-search" placeholder="Rechercher un établissement, une ville…" oninput="filterEtabs(this.value)">
</div>

<div class="or-board" id="etab-board">
<?php foreach ($colonnes as $statutKey => $col): ?>
    <section class="or-col" aria-label="<?= e($col['label']) ?>">
        <div class="or-col-head">
            <span class="or-col-dot" style="background:<?= $col['dot'] ?>;"></span>
            <?= e($col['label']) ?>
            <span class="or-col-count"><?= count($col['items']) ?></span>
        </div>
        <div class="or-col-body">
        <?php if (empty($col['items'])): ?>
            <div class="or-col-empty">Aucun établissement <?= strtolower(e($col['label'])) ?>.</div>
        <?php endif; ?>
        <?php foreach ($col['items'] as $e): ?>
        <?php
            $couleur = preg_match('/^#[0-9A-Fa-f]{6}$/', $e['couleur_principale'] ?? '') ? $e['couleur_principale'] : '#8B52FA';
            $ps      = $e['paiement_statut'] ?? null;
            $inscrit = (bool)($e['inscription_active'] ?? false);
            $agg     = $avisAgg[(int)$e['id']] ?? null;
        ?>
            <article class="kb-card or-glass" style="--kb-accent:<?= e($couleur) ?>;"
                     data-search="<?= e(strtolower($e['nom'] . ' ' . $e['ville'] . ' ' . ($typeLabels[$e['type']] ?? $e['type']))) ?>">
                <div class="kb-top">
                    <span class="kb-dot"></span>
                    <?= e($typeLabels[$e['type']] ?? $e['type']) ?>
                    <span class="kb-ville"><?= e($e['ville']) ?></span>
                </div>
                <h3 class="kb-title">
                    <a href="<?= url('/admin/orientation/' . $e['id'] . '/voir') ?>"><?= e($e['nom']) ?></a>
                </h3>
                <div class="kb-slug">/orientation/<?= e($e['slug']) ?></div>
                <div class="kb-meta">
                    <span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10 12 5 2 10l10 5 10-5z"/><path d="M6 12v5c0 1 2.7 2.5 6 2.5s6-1.5 6-2.5v-5"/></svg>
                        <?= (int)$e['nb_formations'] ?> formation<?= (int)$e['nb_formations'] > 1 ? 's' : '' ?>
                    </span>
                    <span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
                        <?= (int)$e['nb_dossiers'] ?> dossier<?= (int)$e['nb_dossiers'] > 1 ? 's' : '' ?>
                    </span>
                    <?php if ($agg && $agg['nb_avis'] > 0): ?>
                    <span class="kb-note"><span class="star">★</span> <?= number_format($agg['note_moyenne'], 1, ',', ' ') ?> <span style="color:var(--txt-l);font-weight:500;">(<?= $agg['nb_avis'] ?>)</span></span>
                    <?php endif; ?>
                </div>
                <div class="kb-badges">
                    <?php if ($ps === 'actif'): ?>
                        <span class="kb-chip kb-chip-green">Paiement actif</span>
                    <?php elseif ($ps === 'en_attente'): ?>
                        <span class="kb-chip kb-chip-amber">Paiement en attente</span>
                    <?php elseif ($ps): ?>
                        <span class="kb-chip kb-chip-red">Paiement inactif</span>
                    <?php else: ?>
                        <span class="kb-chip kb-chip-mute">Paiement non configuré</span>
                    <?php endif; ?>
                    <?php if ($inscrit): ?>
                        <span class="kb-chip kb-chip-purple">Inscriptions ouvertes</span>
                    <?php else: ?>
                        <span class="kb-chip kb-chip-mute">Inscriptions en pause</span>
                    <?php endif; ?>
                </div>
                <div class="kb-foot">
                    <span class="kb-date"><?= date('d/m/Y', strtotime($e['date_creation'])) ?></span>
                    <div class="kb-actions">
                        <a href="<?= url('/admin/orientation/' . $e['id'] . '/voir') ?>" class="kb-act" title="Voir la fiche">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </a>
                        <a href="<?= url('/admin/orientation/' . $e['id'] . '/modifier') ?>" class="kb-act" title="Modifier">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                        </a>
                        <a href="<?= url('/admin/orientation/' . $e['id'] . '/paiement') ?>" class="kb-act" title="Configurer paiement">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                        </a>
                        <?php /* Ouverture / fermeture des inscriptions en un clic.
                                 Le bouton portait deux glyphes (⏸ ▶) dans un jeu
                                 d'icônes entièrement vectoriel, et son action —
                                 ouvrir un formulaire au public — n'était confirmée
                                 nulle part. */ ?>
                        <form method="POST" action="<?= url('/admin/orientation/' . $e['id'] . '/activer-inscription') ?>" style="display:inline"
                              onsubmit="return confirm(<?= e(json_encode(($inscrit ? 'Fermer' : 'Ouvrir') . ' les inscriptions en ligne de « ' . $e['nom'] . ' » ?', JSON_UNESCAPED_UNICODE)) ?>);">
                            <input type="hidden" name="_csrf_token" value="<?= e(\Core\Session::getCsrfToken()) ?>">
                            <button type="submit" class="kb-act"
                                    style="background:none;border:none;padding:0;cursor:pointer;font:inherit;<?= $inscrit ? 'color:var(--green,#1f9d55);' : '' ?>"
                                    title="<?= $inscrit ? 'Fermer les inscriptions en ligne' : 'Ouvrir les inscriptions en ligne' ?>"
                                    aria-label="<?= $inscrit ? 'Fermer les inscriptions en ligne' : 'Ouvrir les inscriptions en ligne' ?>">
                                <?php if ($inscrit): ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                <?php else: ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>
                                <?php endif; ?>
                            </button>
                        </form>
                    </div>
                    <span class="kb-avatar"><?= strtoupper(mb_substr($e['nom'], 0, 1)) ?></span>
                </div>
            </article>
        <?php endforeach; ?>
        </div>
    </section>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── Board gestionnaires ──────────────────────────────────────────── -->
<div class="or-board-head" style="margin-top:8px;">
    <div>
        <h2>Gestionnaires (<?= count($gestionnaires) ?>)</h2>
        <p>Activer ou suspendre l'accès à l'espace gestionnaire.</p>
    </div>
</div>

<?php if (empty($gestionnaires)): ?>
<div class="or-glass" style="padding:40px;text-align:center;color:var(--txt-l);font-size:14px;">
    Aucun gestionnaire enregistré.
</div>
<?php else: ?>
<div class="or-board cols-2">
    <?php
        $gestCols = [
            ['label' => 'Accès actifs', 'dot' => 'var(--green)', 'items' => $gestActifs],
            ['label' => 'Suspendus',    'dot' => 'var(--red)',   'items' => $gestSuspendus],
        ];
    ?>
    <?php foreach ($gestCols as $col): ?>
    <section class="or-col" aria-label="<?= e($col['label']) ?>">
        <div class="or-col-head">
            <span class="or-col-dot" style="background:<?= $col['dot'] ?>;"></span>
            <?= e($col['label']) ?>
            <span class="or-col-count"><?= count($col['items']) ?></span>
        </div>
        <div class="or-col-body">
        <?php if (empty($col['items'])): ?>
            <div class="or-col-empty">Aucun gestionnaire dans cette colonne.</div>
        <?php endif; ?>
        <?php foreach ($col['items'] as $g): ?>
        <?php $isActive = (bool)$g['is_active']; ?>
            <article class="kb-card or-glass" style="--kb-accent:<?= $isActive ? 'var(--green)' : 'var(--red)' ?>;">
                <div class="kb-top">
                    <span class="kb-dot"></span>
                    <span class="kb-chip kb-chip-purple" style="text-transform:capitalize;"><?= e($g['role']) ?></span>
                    <span class="kb-ville"><?= e($g['etab_nom']) ?></span>
                </div>
                <h3 class="kb-title"><?= e($g['prenom'] . ' ' . $g['nom']) ?></h3>
                <div class="kb-mail"><?= e($g['email']) ?></div>
                <div class="kb-slug">/orientation/<?= e($g['etab_slug']) ?></div>
                <div class="kb-foot">
                    <span class="kb-date">
                        Dernière connexion : <?= $g['last_login'] ? date('d/m/Y H:i', strtotime($g['last_login'])) : '—' ?>
                    </span>
                    <div class="kb-actions">
                        <form method="POST" action="<?= url('/admin/orientation/gestionnaires/' . $g['user_id'] . '/toggle') ?>"
                              onsubmit="return confirm('<?= $isActive ? 'Suspendre' : 'Réactiver' ?> l\'accès de <?= e(addslashes($g['prenom'] . ' ' . $g['nom'])) ?> ?')">
                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                            <button type="submit" class="kb-btn-toggle <?= $isActive ? 'is-danger' : 'is-success' ?>">
                                <?= $isActive ? 'Suspendre' : 'Réactiver' ?>
                            </button>
                        </form>
                    </div>
                    <span class="kb-avatar" style="background:<?= $isActive ? 'var(--ap)' : 'var(--txt-l)' ?>;">
                        <?= strtoupper(mb_substr($g['prenom'] ?: $g['nom'], 0, 1)) ?>
                    </span>
                </div>
            </article>
        <?php endforeach; ?>
        </div>
    </section>
    <?php endforeach; ?>
</div>
<?php endif; ?>

</div><!-- .or-page -->

<script>
function filterEtabs(q) {
    q = q.toLowerCase();
    document.querySelectorAll('#etab-board .kb-card').forEach(function (card) {
        card.style.display = (card.dataset.search || '').includes(q) ? '' : 'none';
    });
    // Compteurs de colonnes mis à jour selon les cartes visibles
    document.querySelectorAll('#etab-board .or-col').forEach(function (col) {
        var visible = col.querySelectorAll('.kb-card:not([style*="display: none"])').length;
        var count = col.querySelector('.or-col-count');
        if (count) count.textContent = visible;
        var empty = col.querySelector('.or-col-empty');
        if (empty) empty.style.display = visible === 0 ? '' : 'none';
    });
}
</script>
