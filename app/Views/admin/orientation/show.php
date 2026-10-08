<?php
// Variables: $etab, $formations, $gestionnaires, $statsEtab, $logoUrl, $imageUrls, $flash, $flashError
$etab          = $etab ?? [];
$formations    = $formations ?? [];
$gestionnaires = $gestionnaires ?? [];
$statsEtab     = ($statsEtab ?? []) + ['nb_dossiers'=>0,'nb_preinscriptions'=>0,'nb_inscriptions'=>0,'revenus_total'=>0];
$logoUrl       = $logoUrl ?? '';
$imageUrls     = $imageUrls ?? [];
$videoFileUrl  = $videoFileUrl ?? '';
$flash         = $flash ?? null;
$flashError    = $flashError ?? null;

$typeLabels = ['universite' => 'Université', 'grande_ecole' => 'Grande école', 'institut' => 'Institut'];
$statutBadge = ['actif' => 'badge-actif', 'brouillon' => 'badge-attente', 'inactif' => 'badge-suspendu'];
$formationStatut = ['ouverte' => 'Ouverte', 'bientot' => 'Bientôt', 'fermee' => 'Fermée'];
$modeLabels = ['airtel_money' => 'Airtel Money', 'moov_money' => 'Moov Money', 'orange_money' => 'Orange Money'];

$badges = json_decode($etab['badges_confiance'] ?? '[]', true) ?: [];
$atouts = json_decode($etab['atouts'] ?? '[]', true) ?: [];
$statsJson = json_decode($etab['statistiques'] ?? '[]', true) ?: [];
$modesPaiement = json_decode($etab['infos_paiement'] ?? '[]', true) ?: [];

$ps = $etab['paiement_statut'] ?? null;
$pBadge = $ps === 'actif' ? 'badge-actif' : ($ps === 'en_attente' ? 'badge-attente' : 'badge-suspendu');

$couleur = $etab['couleur_principale'] ?? '#1F4E79';

// Frais de scolarité annuels par cycle × statut boursier — 0 = non renseigné
$scoLicence   = (int)($etab['frais_scolarite'] ?? 0);
$scoLicenceB  = (int)($etab['frais_scolarite_boursier'] ?? 0);
$scoMaster    = (int)($etab['frais_scolarite_master'] ?? 0);
$scoMasterB   = (int)($etab['frais_scolarite_master_boursier'] ?? 0);
$insLicence   = (int)($etab['frais_inscription_licence'] ?? 0);
$insLicenceB  = (int)($etab['frais_inscription_licence_boursier'] ?? 0);
$insMaster    = (int)($etab['frais_inscription_master'] ?? 0);
$insMasterB   = (int)($etab['frais_inscription_master_boursier'] ?? 0);
$fraisUnif    = (int)($etab['frais_uniforme'] ?? 0);

// Localisation : embed Google Maps configuré prioritaire, sinon GPS legacy,
// sinon recherche par adresse (même logique que la page publique)
$mapSrc  = '';
$mapHref = '';
if (!empty($etab['map_embed_url'])) {
    $mapSrc  = $etab['map_embed_url'];
    $q       = urlencode(trim(($etab['adresse'] ?? '') . ' ' . ($etab['nom'] ?? '') . ' ' . ($etab['ville'] ?? '')));
    $mapHref = "https://maps.google.com/?q={$q}";
} elseif (!empty($etab['latitude']) && !empty($etab['longitude'])) {
    $lat = (float)$etab['latitude'];
    $lng = (float)$etab['longitude'];
    $mapSrc  = "https://maps.google.com/maps?q={$lat},{$lng}&output=embed";
    $mapHref = "https://maps.google.com/?q={$lat},{$lng}";
} elseif (!empty($etab['adresse']) || !empty($etab['ville'])) {
    $q = urlencode(trim(($etab['adresse'] ?? '') . ' ' . ($etab['ville'] ?? '')));
    $mapSrc  = "https://maps.google.com/maps?q={$q}&output=embed";
    $mapHref = "https://maps.google.com/?q={$q}";
}

/** Ligne de définition — masque proprement les valeurs vides. */
$row = static function (string $label, ?string $value, bool $raw = false): void {
    $v = $value === null || $value === '' ? '—' : $value;
    echo '<div class="ef-row"><dt>' . e($label) . '</dt><dd>' . ($raw ? $v : e($v)) . '</dd></div>';
};
?>

<?php if ($flash): ?>
<div class="ef-flash ef-flash-ok" role="alert">
    <?= e($flash) ?>
    <button type="button" onclick="this.parentElement.remove()" aria-label="Fermer">&times;</button>
</div>
<?php endif; ?>
<?php if ($flashError): ?>
<div class="ef-flash ef-flash-err" role="alert">
    <?= e($flashError) ?>
    <button type="button" onclick="this.parentElement.remove()" aria-label="Fermer">&times;</button>
</div>
<?php endif; ?>

<div class="ef" style="--etab-brand: <?= e($couleur) ?>;">

    <!-- ── Fil d'ariane + titre éditorial ───────────────────────────── -->
    <div class="ef-top">
        <div>
            <a class="ef-back" href="<?= url('/admin/orientation') ?>">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                Établissements
            </a>
            <span class="ef-eyebrow">Fiche établissement</span>
        </div>
    </div>

    <!-- ── Identité ─────────────────────────────────────────────────── -->
    <header class="ef-identity">
        <div class="ef-logo" <?= $logoUrl ? '' : 'style="color:' . e($couleur) . ';background:#FBFAF7;background:color-mix(in srgb, ' . e($couleur) . ' 12%, #fff)"' ?>>
            <?php if ($logoUrl): ?>
            <img src="<?= e($logoUrl) ?>" alt="Logo <?= e($etab['nom']) ?>">
            <?php else: ?>
            <?= e(strtoupper(mb_substr($etab['nom'] ?? '?', 0, 1, 'UTF-8'))) ?>
            <?php endif; ?>
        </div>

        <div class="ef-identity-main">
            <h1><?= e($etab['nom']) ?></h1>
            <div class="ef-meta">
                <span class="badge <?= $statutBadge[$etab['statut']] ?? '' ?>"><?= e($etab['statut']) ?></span>
                <span class="ef-chip"><?= e($typeLabels[$etab['type']] ?? $etab['type']) ?></span>
                <span class="ef-meta-item">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <?= e($etab['ville']) ?>
                </span>
                <a class="ef-slug" href="<?= url('/orientation/' . $etab['slug']) ?>" target="_blank" rel="noopener">/orientation/<?= e($etab['slug']) ?></a>
            </div>
        </div>

        <div class="ef-actions">
            <a href="<?= url('/orientation/' . $etab['slug']) ?>" class="ef-btn" target="_blank" rel="noopener">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                Page publique
            </a>
            <a href="<?= url('/admin/orientation/' . $etab['id'] . '/paiement') ?>" class="ef-btn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                Paiement
            </a>
            <button type="button" class="ef-btn" onclick="openEtabMsg()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                Message
            </button>
            <a href="<?= url('/admin/orientation/' . $etab['id'] . '/modifier') ?>" class="ef-btn ef-btn-primary">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                Modifier
            </a>
        </div>
    </header>

    <!-- ── Chiffres clés ────────────────────────────────────────────── -->
    <section class="ef-stats">
        <div class="ef-stat">
            <span class="ef-stat-label">Dossiers total</span>
            <span class="ef-stat-val"><?= number_format((int)$statsEtab['nb_dossiers'], 0, ',', ' ') ?></span>
        </div>
        <div class="ef-stat">
            <span class="ef-stat-label">Préinscriptions</span>
            <span class="ef-stat-val"><?= number_format((int)$statsEtab['nb_preinscriptions'], 0, ',', ' ') ?></span>
        </div>
        <div class="ef-stat">
            <span class="ef-stat-label">Inscriptions</span>
            <span class="ef-stat-val"><?= number_format((int)$statsEtab['nb_inscriptions'], 0, ',', ' ') ?></span>
        </div>
        <div class="ef-stat ef-stat-accent">
            <span class="ef-stat-label">Revenus encaissés</span>
            <span class="ef-stat-val"><?= number_format((int)$statsEtab['revenus_total'], 0, ',', ' ') ?><span class="ef-stat-unit">XAF</span></span>
        </div>
    </section>

    <!-- ── Frais de scolarité par cycle ─────────────────────────────── -->
    <section class="ef-panel ef-tuition">
        <div class="ef-panel-head">
            <div class="ef-panel-ic">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10 12 5 2 10l10 5 10-5z"/><path d="M6 12v5c0 1.7 2.7 3 6 3s6-1.3 6-3v-5"/></svg>
            </div>
            <div>
                <h2>Frais de scolarité annuels</h2>
                <p>Montants publiés sur la page publique de l'établissement</p>
            </div>
        </div>
        <div class="ef-cycle-grid">
            <div class="ef-cycle<?= $scoLicence > 0 ? '' : ' is-empty' ?>">
                <span class="ef-cycle-label">Cycle Licence<?= $scoLicenceB > 0 ? ' — non boursier' : '' ?></span>
                <?php if ($scoLicence > 0): ?>
                <span class="ef-cycle-val"><?= number_format($scoLicence, 0, ',', ' ') ?><span class="ef-cycle-unit">FCFA / an</span></span>
                <?php else: ?>
                <span class="ef-cycle-val ef-cycle-none">Non renseigné</span>
                <?php endif; ?>
            </div>
            <?php if ($scoLicenceB > 0): ?>
            <div class="ef-cycle">
                <span class="ef-cycle-label">Cycle Licence — boursier</span>
                <span class="ef-cycle-val"><?= number_format($scoLicenceB, 0, ',', ' ') ?><span class="ef-cycle-unit">FCFA / an</span></span>
            </div>
            <?php endif; ?>
            <div class="ef-cycle<?= $scoMaster > 0 ? '' : ' is-empty' ?>">
                <span class="ef-cycle-label">Cycle Master<?= $scoMasterB > 0 ? ' — non boursier' : '' ?></span>
                <?php if ($scoMaster > 0): ?>
                <span class="ef-cycle-val"><?= number_format($scoMaster, 0, ',', ' ') ?><span class="ef-cycle-unit">FCFA / an</span></span>
                <?php else: ?>
                <span class="ef-cycle-val ef-cycle-none">Non renseigné</span>
                <?php endif; ?>
            </div>
            <?php if ($scoMasterB > 0): ?>
            <div class="ef-cycle">
                <span class="ef-cycle-label">Cycle Master — boursier</span>
                <span class="ef-cycle-val"><?= number_format($scoMasterB, 0, ',', ' ') ?><span class="ef-cycle-unit">FCFA / an</span></span>
            </div>
            <?php endif; ?>
            <?php if ($fraisUnif > 0): ?>
            <div class="ef-cycle ef-cycle-soft">
                <span class="ef-cycle-label">Uniforme</span>
                <span class="ef-cycle-val"><?= number_format($fraisUnif, 0, ',', ' ') ?><span class="ef-cycle-unit">FCFA</span></span>
            </div>
            <?php endif; ?>
            <div class="ef-cycle ef-cycle-soft">
                <span class="ef-cycle-label">Commission plateforme</span>
                <span class="ef-cycle-val"><?= e(rtrim(rtrim(number_format((float)($etab['taux_commission_scolarite'] ?? 10), 2, ',', ' '), '0'), ',')) ?><span class="ef-cycle-unit">% de la scolarité Licence</span></span>
            </div>
            <?php // L'assiette des boursiers est le seul terme négociable de la
                  // règle de commission : elle doit se lire sur la fiche, sinon
                  // il faut ouvrir le formulaire d'édition pour savoir ce qui a
                  // été convenu avec ce partenaire.
                  $baseBoursier = \Services\Orientation\TarifCycle::normaliserBaseBoursier(
                      ($etab['base_commission_boursier'] ?? null) !== null
                          ? (string)$etab['base_commission_boursier'] : null
                  ); ?>
            <div class="ef-cycle ef-cycle-soft">
                <span class="ef-cycle-label">Commission des boursiers</span>
                <span class="ef-cycle-val" style="font-size:15px;">
                    <?= e(\Services\Orientation\TarifCycle::libelleBase($baseBoursier)) ?>
                    <span class="ef-cycle-unit"><?= $baseBoursier === \Services\Orientation\TarifCycle::BASE_SCOLARITE ? 'accord particulier' : 'règle par défaut' ?></span>
                </span>
            </div>
        </div>
    </section>

    <!-- ── Grille de détail ─────────────────────────────────────────── -->
    <div class="ef-grid">

        <!-- Identité & contact -->
        <section class="ef-panel">
            <div class="ef-panel-head">
                <div class="ef-panel-ic">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M17.657 16.657 13.414 20.9a1.998 1.998 0 0 1-2.827 0l-4.244-4.243a8 8 0 1 1 11.314 0z"/><circle cx="12" cy="11" r="3"/></svg>
                </div>
                <div><h2>Identité &amp; contact</h2><p>Coordonnées et marque</p></div>
            </div>
            <dl class="ef-rows">
                <?php
                $row('Type', $typeLabels[$etab['type']] ?? $etab['type']);
                $row('Ville', $etab['ville'] ?? null);
                $row('Adresse', $etab['adresse'] ?? null);
                $row('Email', $etab['email_contact'] ?? null);
                $row('Téléphone', $etab['telephone'] ?? null);
                $row('WhatsApp', $etab['whatsapp'] ?? null);
                $row('Carte Google Maps', !empty($etab['map_embed_url'])
                        ? 'Intégrée'
                        : ((($etab['latitude'] ?? '') !== '' && $etab['latitude'] !== null)
                            ? 'GPS legacy : ' . $etab['latitude'] . ', ' . $etab['longitude']
                            : null));
                $row('Couleur de marque',
                    '<span class="ef-swatch" style="background:' . e($couleur) . '"></span>' . e($couleur), true);
                $row('Créé le', !empty($etab['date_creation']) ? date('d/m/Y à H:i', strtotime($etab['date_creation'])) : null);
                ?>
            </dl>
        </section>

        <!-- Tarifs & encaissement -->
        <section class="ef-panel">
            <div class="ef-panel-head">
                <div class="ef-panel-ic">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="10"/><path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/></svg>
                </div>
                <div><h2>Tarifs &amp; encaissement</h2><p>Montants de candidature et reversement</p></div>
            </div>
            <dl class="ef-rows">
                <?php
                $row('Préinscription', number_format((int)($etab['prix_preinscription'] ?? 0), 0, ',', ' ') . ' FCFA');
                $row('Inscription', number_format((int)($etab['prix_inscription'] ?? 0), 0, ',', ' ') . ' FCFA');
                $row('Frais de dossier', number_format((int)($etab['frais_dossier'] ?? 0), 0, ',', ' ') . ' FCFA');

                // Les quatre frais d'inscription par cycle et bourse : ce sont EUX qui
                // s'appliquent à un dossier dès qu'ils sont renseignés. Un montant à 0
                // s'affiche « — » et non « 0 FCFA » : il signifie « non renseigné, hérite
                // du tarif public ci-dessus », et un zéro imprimé laisserait croire que
                // l'établissement n'a pas de frais d'inscription.
                $row('Inscription Licence', $insLicence  > 0 ? number_format($insLicence,  0, ',', ' ') . ' FCFA' : null);
                $row('Inscription Licence (boursier)', $insLicenceB > 0 ? number_format($insLicenceB, 0, ',', ' ') . ' FCFA' : null);
                $row('Inscription Master',  $insMaster   > 0 ? number_format($insMaster,   0, ',', ' ') . ' FCFA' : null);
                $row('Inscription Master (boursier)',  $insMasterB  > 0 ? number_format($insMasterB,  0, ',', ' ') . ' FCFA' : null);

                $row('Scolarité Licence', $scoLicence > 0 ? number_format($scoLicence, 0, ',', ' ') . ' FCFA / an' : null);
                $row('Scolarité Licence (boursier)', $scoLicenceB > 0 ? number_format($scoLicenceB, 0, ',', ' ') . ' FCFA / an' : null);
                $row('Scolarité Master', $scoMaster > 0 ? number_format($scoMaster, 0, ',', ' ') . ' FCFA / an' : null);
                $row('Scolarité Master (boursier)', $scoMasterB > 0 ? number_format($scoMasterB, 0, ',', ' ') . ' FCFA / an' : null);
                $row('Frais d\'uniforme', $fraisUnif > 0 ? number_format($fraisUnif, 0, ',', ' ') . ' FCFA' : null);
                $row('Modes acceptés', $modesPaiement ? implode(', ', $modesPaiement) : null);
                $row('Préinscription en ligne',
                    (int)($etab['preinscription_active'] ?? 0)
                        ? '<span class="ef-state ef-state-on">Activée</span>'
                        : '<span class="ef-state ef-state-off">Désactivée</span>', true);
                // Inscriptions en ligne : l'état était affiché sans aucun moyen
                // de le changer depuis cette fiche. Le bouton pousse sur la même
                // route que la liste — un seul point d'écriture, qui journalise
                // et purge le cache de la page publique.
                $insActive = (int)($etab['inscription_active'] ?? 0) === 1;
                $row('Inscriptions en ligne',
                    ($insActive
                        ? '<span class="ef-state ef-state-on">Activées</span>'
                        : '<span class="ef-state ef-state-off">Désactivées</span>')
                    . '<form method="post" action="' . url('/admin/orientation/' . (int)$etab['id'] . '/activer-inscription') . '"'
                    . ' style="display:inline-block;margin-left:10px;"'
                    . ' onsubmit="return confirm(' . e(json_encode(($insActive ? 'Fermer' : 'Ouvrir') . ' les inscriptions en ligne de « ' . ($etab['nom'] ?? '') . ' » ?', JSON_UNESCAPED_UNICODE)) . ');">'
                    . '<input type="hidden" name="_csrf_token" value="' . e(\Core\Session::getCsrfToken()) . '">'
                    . '<button type="submit" class="btn btn-sm btn-outline" style="font-size:12px;padding:4px 10px;">'
                    . ($insActive ? 'Fermer les inscriptions' : 'Ouvrir les inscriptions')
                    . '</button></form>', true);
                $row('Compte Mobile Money',
                    $ps
                        ? '<span class="badge ' . $pBadge . '">' . e($ps) . '</span> '
                          . e($modeLabels[$etab['withdraw_mode']] ?? $etab['withdraw_mode'] ?? '')
                          . ' · ' . e($etab['numero_mobile_money'] ?? '')
                        : 'Non configuré', true);
                if ($ps) {
                    $row('Titulaire', $etab['titulaire'] ?? null);
                    $row('Commission plateforme', ($etab['taux_commission'] ?? '') !== '' ? $etab['taux_commission'] . ' %' : null);
                }
                ?>
            </dl>
        </section>

        <!-- Contenu de la page publique -->
        <section class="ef-panel">
            <div class="ef-panel-head">
                <div class="ef-panel-ic">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                </div>
                <div><h2>Contenu de la page publique</h2><p>Ce que voient les candidats</p></div>
            </div>
            <dl class="ef-rows">
                <?php
                $row('Label campagne', $etab['label_campagne'] ?? null);
                $row('Titre accroche', $etab['titre_accroche'] ?? null);
                $row('Sous-titre', $etab['sous_titre'] ?? null);
                $row('Date limite dossier', !empty($etab['date_limite_dossier']) ? date('d/m/Y', strtotime($etab['date_limite_dossier'])) : null);
                $row('Description', !empty($etab['description']) ? mb_strimwidth($etab['description'], 0, 160, '…') : null);
                $row('Citation', !empty($etab['citation_texte']) ? mb_strimwidth($etab['citation_texte'], 0, 120, '…') : null);
                $row('Vidéo', !empty($etab['video_cle'])
                        ? (!empty($videoFileUrl)
                            ? '<a class="ef-link" href="' . e($videoFileUrl) . '" target="_blank" rel="noopener">Fichier vidéo en ligne — prévisualiser</a>'
                            : 'Fichier vidéo en ligne')
                        : e($etab['video_url'] ?? ''), true);
                $row('Attestation de préinscription', (int)($etab['genere_recepisse'] ?? 0) ? 'Oui' : 'Non');
                $row('Attestation d\'inscription', (int)($etab['genere_attestation'] ?? 1) ? 'Oui' : 'Non');
                // Colonne absente (migration non rejouée) → « Oui », qui est le
                // comportement effectif de la plateforme dans ce cas.
                $row('Candidatures de boursiers', (int)($etab['accepte_boursiers'] ?? 1) ? 'Acceptées' : 'Fermées');
                ?>
            </dl>

            <?php if ($badges || $atouts || $statsJson || $imageUrls): ?>
            <div class="ef-panel-extra">
                <?php if ($badges): ?>
                <div class="ef-taggroup">
                    <span class="ef-taggroup-label">Badges de confiance</span>
                    <div class="ef-tags"><?php foreach ($badges as $b): ?><span class="ef-tag"><?= e($b) ?></span><?php endforeach; ?></div>
                </div>
                <?php endif; ?>
                <?php if ($atouts): ?>
                <div class="ef-taggroup">
                    <span class="ef-taggroup-label">Points forts</span>
                    <div class="ef-tags"><?php foreach ($atouts as $a): ?><span class="ef-tag"><?= e($a) ?></span><?php endforeach; ?></div>
                </div>
                <?php endif; ?>
                <?php if ($statsJson): ?>
                <div class="ef-taggroup">
                    <span class="ef-taggroup-label">Statistiques affichées</span>
                    <div class="ef-tags">
                        <?php foreach ($statsJson as $s): ?>
                        <span class="ef-tag"><?= e(($s['label'] ?? '') . ' : ' . ($s['valeur'] ?? '')) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($imageUrls): ?>
                <div class="ef-taggroup">
                    <span class="ef-taggroup-label">Photos du campus</span>
                    <div class="ef-shots">
                        <?php foreach ($imageUrls as $img): ?>
                        <img src="<?= e($img) ?>" alt="Photo campus <?= e($etab['nom']) ?>" loading="lazy">
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </section>

        <!-- Gestionnaires -->
        <section class="ef-panel">
            <div class="ef-panel-head">
                <div class="ef-panel-ic">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
                <div><h2>Gestionnaires</h2><p><?= count($gestionnaires) ?> compte<?= count($gestionnaires) > 1 ? 's' : '' ?> lié<?= count($gestionnaires) > 1 ? 's' : '' ?></p></div>
            </div>
            <?php if (empty($gestionnaires)): ?>
            <div class="ef-empty">Aucun gestionnaire lié à cet établissement.</div>
            <?php else: ?>
            <ul class="ef-people">
                <?php foreach ($gestionnaires as $g): ?>
                <li>
                    <div class="ef-avatar"><?= e(strtoupper(mb_substr($g['prenom'] ?: $g['nom'] ?: '?', 0, 1, 'UTF-8'))) ?></div>
                    <div class="ef-person">
                        <strong><?= e(trim($g['prenom'] . ' ' . $g['nom'])) ?></strong>
                        <span><?= e($g['email']) ?></span>
                    </div>
                    <div class="ef-person-side">
                        <span class="badge <?= (int)$g['is_active'] ? 'badge-actif' : 'badge-suspendu' ?>"><?= (int)$g['is_active'] ? 'Actif' : 'Suspendu' ?></span>
                        <span class="ef-person-last">
                            <?= $g['last_login'] ? 'Vu le ' . date('d/m/Y à H:i', strtotime($g['last_login'])) : 'Jamais connecté' ?>
                        </span>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>

        <!-- Localisation -->
        <section class="ef-panel ef-panel-wide">
            <div class="ef-panel-head">
                <div class="ef-panel-ic">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                </div>
                <div>
                    <h2>Localisation</h2>
                    <p><?= e(trim(($etab['adresse'] ?? '') !== '' ? ($etab['adresse'] . ', ' . ($etab['ville'] ?? '')) : ($etab['ville'] ?? '—'))) ?></p>
                </div>
                <?php if ($mapSrc): ?>
                <a class="ef-link ef-panel-link" href="<?= e($mapHref) ?>" target="_blank" rel="noopener">
                    Ouvrir dans Maps
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                </a>
                <?php endif; ?>
            </div>
            <?php if ($mapSrc): ?>
            <iframe class="ef-map" src="<?= e($mapSrc) ?>"
                    loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                    allowfullscreen title="Localisation de <?= e($etab['nom'] ?? '') ?> sur Google Maps"></iframe>
            <?php else: ?>
            <div class="ef-empty">Aucune localisation enregistrée — ajoutez l'adresse ou le code d'intégration Google Maps via « Modifier ».</div>
            <?php endif; ?>
        </section>

    </div>

    <!-- ── Formations ───────────────────────────────────────────────── -->
    <section class="ef-panel ef-panel-table">
        <div class="ef-panel-head">
            <div class="ef-panel-ic">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            </div>
            <div><h2>Formations</h2><p><?= count($formations) ?> offre<?= count($formations) > 1 ? 's' : '' ?> affichée<?= count($formations) > 1 ? 's' : '' ?> sur la page publique</p></div>
        </div>
        <?php if (empty($formations)): ?>
        <div class="ef-empty">Aucune formation enregistrée.</div>
        <?php else: ?>
        <div class="ef-table-scroll">
            <table class="ef-table">
                <thead>
                    <tr>
                        <th>Intitulé</th><th>Niveau</th><th>Durée</th><th>Places</th><th>Prix</th><th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($formations as $f): ?>
                    <tr>
                        <td class="ef-td-strong"><?= e($f['intitule']) ?></td>
                        <td><?= e($f['niveau'] ?? '—') ?></td>
                        <td><?= e($f['duree_texte'] ?? '—') ?></td>
                        <td><?= e($f['places_texte'] ?? '—') ?></td>
                        <td><?= (int)$f['prix'] > 0 ? number_format((int)$f['prix'], 0, ',', ' ') . ' ' . e($f['prix_unite'] ?? 'FCFA/an') : 'Gratuit' ?></td>
                        <td>
                            <span class="badge <?= $f['statut'] === 'ouverte' ? 'badge-actif' : ($f['statut'] === 'bientot' ? 'badge-attente' : 'badge-suspendu') ?>">
                                <?= e($formationStatut[$f['statut']] ?? $f['statut']) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

</div>

<style>
/* ════════════════════════════════════════════════════════════════
   Fiche établissement — langage visuel Claude
   Papier crème, surfaces plates, filets fins, ombres rares, air.
   Accent unique Purple Neon #8B52FA · Montserrat.
   Portée : uniquement sous .ef — aucun impact ailleurs.
   ════════════════════════════════════════════════════════════════ */
.ef {
  --ef-line:  rgba(26,26,26,.09);
  --ef-line-2: rgba(26,26,26,.06);
  --ef-r:     16px;
  color: var(--txt);
  font-family: var(--font-head, 'Montserrat', system-ui, sans-serif);
}
.ef-panel {
  background: #FFFFFF;
  border: 1px solid var(--ef-line);
  border-radius: var(--ef-r);
  box-shadow: none;
  overflow: hidden;
}

/* ── Flash ───────────────────────────────────────────────────── */
.ef-flash {
  display: flex; align-items: center; justify-content: space-between; gap: 14px;
  border-radius: 12px; padding: 13px 18px; margin-bottom: 20px;
  font-size: 13.5px; font-weight: 600;
}
.ef-flash button { background: none; border: none; cursor: pointer; font-size: 20px; line-height: 1; color: inherit; opacity: .6; }
.ef-flash button:hover { opacity: 1; }
.ef-flash-ok  { background: #ECFDF3; color: #15803D; border: 1px solid #BBF7D0; }
.ef-flash-err { background: #FEF2F2; color: #B91C1C; border: 1px solid #FCA5A5; }

/* ── Fil d'ariane ────────────────────────────────────────────── */
.ef-top { margin-bottom: 18px; }
.ef-back {
  display: inline-flex; align-items: center; gap: 6px;
  font-size: 12.5px; font-weight: 600; color: var(--txt-l);
  text-decoration: none; transition: color .15s;
}
.ef-back:hover { color: var(--ap); }
.ef-eyebrow {
  display: block; margin-top: 12px;
  font-size: 10.5px; font-weight: 700; letter-spacing: .18em;
  text-transform: uppercase; color: var(--ap);
}

/* ── Identité ────────────────────────────────────────────────── */
.ef-identity {
  display: flex; align-items: center; gap: 20px; flex-wrap: wrap;
  padding: 0 0 26px; margin-bottom: 26px;
  border-bottom: 1px solid var(--ef-line);
}
.ef-logo {
  width: 68px; height: 68px; flex: none; border-radius: 16px;
  display: grid; place-items: center; overflow: hidden;
  font-size: 26px; font-weight: 700;
  border: 1px solid var(--ef-line); background: #fff;
}
.ef-logo img { width: 100%; height: 100%; object-fit: cover; }
.ef-identity-main { flex: 1; min-width: 240px; }
.ef-identity-main h1 {
  font-size: 30px; font-weight: 700; letter-spacing: -.028em;
  line-height: 1.1; color: var(--txt); margin: 0 0 12px;
}
.ef-meta { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.ef-meta-item { display: inline-flex; align-items: center; gap: 5px; font-size: 12.5px; color: var(--txt-m); }
.ef-chip {
  display: inline-block; padding: 4px 11px; border-radius: 999px;
  font-size: 11.5px; font-weight: 600;
  color: var(--txt-m); border: 1px solid var(--ef-line); background: transparent;
}
.ef-slug {
  font-size: 11.5px; font-weight: 600; color: var(--ap);
  text-decoration: none; padding: 4px 10px; border-radius: 999px;
  background: var(--ap-lt);
}
.ef-slug:hover { text-decoration: underline; }

.ef-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.ef-btn {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 9px 15px; border-radius: 10px;
  border: 1px solid var(--ef-line); background: #FFFFFF;
  font-family: inherit; font-size: 12.5px; font-weight: 600;
  color: var(--txt-m); text-decoration: none; cursor: pointer;
  transition: border-color .15s, color .15s, background .15s;
}
.ef-btn:hover { border-color: var(--ap); color: var(--ap); background: var(--ap-lt); }
.ef-btn-primary { background: var(--ap); border-color: var(--ap); color: #fff; }
.ef-btn-primary:hover { background: var(--ap-dk); border-color: var(--ap-dk); color: #fff; }

/* ── Chiffres clés ───────────────────────────────────────────── */
.ef-stats {
  display: grid; grid-template-columns: repeat(4, minmax(0,1fr));
  gap: 0; margin-bottom: 24px;
  border: 1px solid var(--ef-line); border-radius: var(--ef-r);
  background: #FFFFFF; overflow: hidden;
}
.ef-stat { padding: 20px 24px; border-right: 1px solid var(--ef-line-2); }
.ef-stat:last-child { border-right: none; }
.ef-stat-label {
  display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .14em;
  text-transform: uppercase; color: var(--txt-l); margin-bottom: 10px;
}
.ef-stat-val {
  display: block; font-size: 28px; font-weight: 700;
  letter-spacing: -.03em; line-height: 1; color: var(--txt);
}
.ef-stat-unit { font-size: 13px; font-weight: 600; letter-spacing: 0; color: var(--txt-l); margin-left: 6px; }
.ef-stat-accent .ef-stat-val { color: var(--ap); }

/* ── Scolarité par cycle ─────────────────────────────────────── */
.ef-tuition { margin-bottom: 24px; }
/* Jusqu'à 6 tuiles depuis l'ajout des tarifs boursiers + uniforme : la grille
   passe sur plusieurs rangées, d'où le filet horizontal entre rangées. */
.ef-cycle-grid { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); }
.ef-cycle { padding: 22px 24px; border-right: 1px solid var(--ef-line-2); }
.ef-cycle:nth-child(3n) { border-right: none; }
.ef-cycle:nth-child(n+4) { border-top: 1px solid var(--ef-line-2); }
.ef-cycle:last-child { border-right: none; }
.ef-cycle-label {
  display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .14em;
  text-transform: uppercase; color: var(--txt-l); margin-bottom: 12px;
}
.ef-cycle-val {
  display: block; font-size: 26px; font-weight: 700;
  letter-spacing: -.03em; line-height: 1.05; color: var(--txt);
}
.ef-cycle-unit { display: block; margin-top: 7px; font-size: 12px; font-weight: 600; letter-spacing: 0; color: var(--txt-l); }
.ef-cycle-none { font-size: 15px; font-weight: 500; color: var(--txt-l); letter-spacing: 0; }
.ef-cycle-soft { background: #FBFAF7; }
.ef-cycle-soft .ef-cycle-val { color: var(--ap); }

/* ── Panneaux ────────────────────────────────────────────────── */
.ef-grid {
  display: grid; grid-template-columns: repeat(2, minmax(0,1fr));
  gap: 20px; align-items: start; margin-bottom: 20px;
}
.ef-panel-wide { grid-column: 1 / -1; }
.ef-panel-head {
  display: flex; align-items: center; gap: 13px;
  padding: 18px 24px; border-bottom: 1px solid var(--ef-line);
}
.ef-panel-ic {
  width: 34px; height: 34px; flex: none; border-radius: 10px;
  background: var(--ap-lt); color: var(--ap);
  display: grid; place-items: center;
}
.ef-panel-head h2 { font-size: 14px; font-weight: 700; letter-spacing: -.01em; color: var(--txt); margin: 0; }
.ef-panel-head p { font-size: 11.5px; color: var(--txt-l); margin: 3px 0 0; }
.ef-panel-link { margin-left: auto; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; }

.ef-rows { margin: 0; padding: 6px 24px 18px; }
.ef-row {
  display: flex; align-items: baseline; justify-content: space-between; gap: 20px;
  padding: 11px 0; border-bottom: 1px solid var(--ef-line-2);
}
.ef-row:last-child { border-bottom: none; }
.ef-row dt { flex: none; font-size: 12.5px; color: var(--txt-l); margin: 0; }
.ef-row dd { margin: 0; font-size: 13px; font-weight: 600; color: var(--txt); text-align: right; overflow-wrap: anywhere; }
.ef-swatch {
  display: inline-block; width: 11px; height: 11px; border-radius: 999px;
  border: 1px solid var(--ef-line); vertical-align: middle; margin-right: 7px;
}
.ef-link { color: var(--ap); font-weight: 600; text-decoration: none; }
.ef-link:hover { text-decoration: underline; }
.ef-state { display: inline-flex; align-items: center; gap: 6px; font-weight: 600; }
.ef-state::before { content: ''; width: 7px; height: 7px; border-radius: 999px; }
.ef-state-on::before  { background: var(--green, #00C853); }
.ef-state-off::before { background: rgba(26,26,26,.22); }

.ef-panel-extra { padding: 4px 24px 22px; display: grid; gap: 16px; }
.ef-taggroup-label {
  display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .14em;
  text-transform: uppercase; color: var(--txt-l); margin-bottom: 9px;
}
.ef-tags { display: flex; flex-wrap: wrap; gap: 7px; }
.ef-tag {
  padding: 5px 12px; border-radius: 999px;
  font-size: 11.5px; font-weight: 600;
  color: var(--txt-m); border: 1px solid var(--ef-line); background: #FBFAF7;
}
.ef-shots { display: flex; gap: 10px; flex-wrap: wrap; }
.ef-shots img { width: 112px; height: 76px; object-fit: cover; border-radius: 12px; border: 1px solid var(--ef-line); }

.ef-empty { padding: 34px 24px; text-align: center; font-size: 13px; color: var(--txt-l); }

/* ── Gestionnaires ───────────────────────────────────────────── */
.ef-people { list-style: none; margin: 0; padding: 6px 24px 18px; }
.ef-people li {
  display: flex; align-items: center; gap: 14px;
  padding: 13px 0; border-bottom: 1px solid var(--ef-line-2);
}
.ef-people li:last-child { border-bottom: none; }
.ef-avatar {
  width: 36px; height: 36px; flex: none; border-radius: 999px;
  background: var(--ap-lt); color: var(--ap);
  display: grid; place-items: center; font-size: 14px; font-weight: 700;
}
.ef-person { flex: 1; min-width: 0; }
.ef-person strong { display: block; font-size: 13px; font-weight: 600; color: var(--txt); }
.ef-person span { display: block; margin-top: 2px; font-size: 11.5px; color: var(--txt-l); overflow-wrap: anywhere; }
.ef-person-side { flex: none; text-align: right; }
.ef-person-last { display: block; margin-top: 5px; font-size: 10.5px; color: var(--txt-l); }

/* ── Carte ───────────────────────────────────────────────────── */
.ef-map { width: 100%; height: 340px; border: 0; display: block; background: var(--ap-lt); }

/* ── Table formations ────────────────────────────────────────── */
.ef-panel-table { margin-bottom: 20px; }
.ef-table-scroll { overflow-x: auto; }
.ef-table { width: 100%; border-collapse: collapse; min-width: 640px; }
.ef-table thead th {
  text-align: left; padding: 12px 24px;
  font-size: 10.5px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase;
  color: var(--txt-l); border-bottom: 1px solid var(--ef-line);
  background: #FBFAF7; white-space: nowrap;
}
.ef-table tbody td {
  padding: 14px 24px; font-size: 12.5px; color: var(--txt-m);
  border-bottom: 1px solid var(--ef-line-2);
}
.ef-table tbody tr:last-child td { border-bottom: none; }
.ef-table tbody tr:hover td { background: #FBFAF7; }
.ef-td-strong { font-size: 13px; font-weight: 600; color: var(--txt); }

@media (max-width: 1000px) {
  .ef-grid { grid-template-columns: minmax(0,1fr); }
  .ef-stats { grid-template-columns: repeat(2, minmax(0,1fr)); }
  .ef-stat:nth-child(2n) { border-right: none; }
  .ef-stat:nth-child(-n+2) { border-bottom: 1px solid var(--ef-line-2); }
  .ef-cycle-grid { grid-template-columns: minmax(0,1fr); }
  .ef-cycle { border-right: none; border-bottom: 1px solid var(--ef-line-2); }
  .ef-cycle:last-child { border-bottom: none; }
}
@media (max-width: 620px) {
  .ef-identity-main h1 { font-size: 24px; }
  .ef-stats { grid-template-columns: minmax(0,1fr); }
  .ef-stat { border-right: none; border-bottom: 1px solid var(--ef-line-2); }
  .ef-stat:last-child { border-bottom: none; }
  .ef-actions { width: 100%; }
  .ef-row { flex-direction: column; align-items: flex-start; gap: 4px; }
  .ef-row dd { text-align: left; }
}
</style>

<!-- ── Modale : message à l'établissement ─────────────────────────────── -->
<div class="em-overlay" id="etabMsgOverlay" onclick="if(event.target===this)closeEtabMsg()">
    <div class="em-modal" role="dialog" aria-modal="true" aria-labelledby="emTitle">
        <div class="em-head">
            <div>
                <h2 id="emTitle">Écrire à l'établissement</h2>
                <p>Le message arrive dans les notifications du portail gestionnaire de <strong><?= e($etab['nom']) ?></strong>.</p>
            </div>
            <button type="button" class="em-close" onclick="closeEtabMsg()" aria-label="Fermer">&times;</button>
        </div>
        <form id="etabMsgForm" onsubmit="return submitEtabMsg(event)">
            <div class="em-field">
                <label for="emSujet">Objet <span style="color:var(--txt-l);font-weight:400;">(facultatif)</span></label>
                <input type="text" id="emSujet" name="sujet" maxlength="120" placeholder="Ex : Mise à jour de votre dossier de partenariat">
            </div>
            <div class="em-field">
                <label for="emMessage">Message</label>
                <textarea id="emMessage" name="message" rows="5" maxlength="1000" required placeholder="Votre message à l'établissement…"></textarea>
                <div class="em-count"><span id="emCount">0</span> / 1000</div>
            </div>
            <div class="em-actions">
                <button type="button" class="btn-outline" onclick="closeEtabMsg()">Annuler</button>
                <button type="submit" class="btn-admin-primary" id="emSubmit">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px;vertical-align:-1px"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    Envoyer le message
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.em-overlay { display:none; position:fixed; inset:0; z-index:1000; background:rgba(31,30,29,.42); backdrop-filter:blur(5px); -webkit-backdrop-filter:blur(5px); align-items:center; justify-content:center; padding:20px; }
.em-overlay.open { display:flex; }
.em-modal { background:#FAF9F5; border:1px solid rgba(31,30,29,.10); border-radius:20px; width:100%; max-width:520px; padding:26px; box-shadow:0 28px 80px rgba(31,30,29,.24); max-height:90vh; overflow-y:auto; }
.em-head { display:flex; align-items:flex-start; justify-content:space-between; gap:14px; margin-bottom:20px; }
.em-head h2 { font-size:19px; font-weight:600; color:var(--txt,#1F1E1D); margin:0 0 4px; letter-spacing:-.01em; }
.em-head p { font-size:12.5px; color:var(--txt-l,#8D8A83); margin:0; line-height:1.5; }
.em-close { background:none; border:none; cursor:pointer; color:var(--txt-l,#8D8A83); font-size:24px; line-height:1; padding:2px 8px; border-radius:8px; transition:all .15s; }
.em-close:hover { color:var(--txt,#1F1E1D); background:rgba(31,30,29,.06); }
.em-field { margin-bottom:16px; }
.em-field label { display:block; font-size:12.5px; font-weight:600; color:var(--txt,#1F1E1D); margin-bottom:7px; }
.em-field input, .em-field textarea { width:100%; padding:11px 14px; border:1px solid rgba(31,30,29,.16); border-radius:12px; font-family:inherit; font-size:13.5px; color:var(--txt,#1F1E1D); background:#fff; transition:border-color .18s, box-shadow .18s; resize:vertical; }
.em-field input:focus, .em-field textarea:focus { outline:none; border-color:#8B52FA; box-shadow:0 0 0 3px rgba(139,82,250,.18); }
.em-count { text-align:right; font-size:11px; color:var(--txt-l,#8D8A83); margin-top:5px; }
.em-actions { display:flex; justify-content:flex-end; gap:10px; margin-top:22px; }
.em-actions button { cursor:pointer; }
.em-actions button[disabled] { opacity:.6; cursor:not-allowed; }
</style>

<script>
(function () {
    var overlay = document.getElementById('etabMsgOverlay');
    var ta = document.getElementById('emMessage');
    var counter = document.getElementById('emCount');
    window.openEtabMsg = function () { overlay.classList.add('open'); setTimeout(function(){ document.getElementById('emSujet').focus(); }, 40); };
    window.closeEtabMsg = function () { overlay.classList.remove('open'); };
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') window.closeEtabMsg(); });
    if (ta) ta.addEventListener('input', function () { counter.textContent = ta.value.length; });

    window.submitEtabMsg = function (e) {
        e.preventDefault();
        var btn = document.getElementById('emSubmit');
        var base = (window.CA_ADMIN && window.CA_ADMIN.baseUrl) || '';
        var token = (window.CA_ADMIN && window.CA_ADMIN.csrfToken) || (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        var msg = ta.value.trim();
        if (!msg) { ta.focus(); return false; }
        btn.disabled = true;
        var fd = new FormData(document.getElementById('etabMsgForm'));
        fd.append('csrf_token', token);
        fetch(base + '/admin/orientation/<?= (int)$etab['id'] ?>/message', {
            method: 'POST',
            headers: { 'X-CSRF-Token': token, 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        })
        .then(function (r) { return r.json().catch(function(){ return { success:false, message:'Réponse invalide du serveur.' }; }); })
        .then(function (data) {
            btn.disabled = false;
            if (data.success) {
                window.closeEtabMsg();
                document.getElementById('etabMsgForm').reset();
                counter.textContent = '0';
                if (window.CA && CA.toast) { CA.toast(data.message, 'success'); }
                else if (window.showToast) { showToast(data.message, 'success'); }
                else { alert(data.message); }
            } else {
                if (window.CA && CA.toast) { CA.toast(data.message || 'Échec de l\'envoi.', 'error'); }
                else { alert(data.message || 'Échec de l\'envoi.'); }
            }
        })
        .catch(function () { btn.disabled = false; alert('Erreur réseau — réessayez.'); });
        return false;
    };
})();
</script>
