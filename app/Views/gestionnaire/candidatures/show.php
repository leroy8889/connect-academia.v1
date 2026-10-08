<?php
// Variables: $dossier, $documents, $signedUrls, $filieres, $etab
$dossier    = $dossier    ?? [];
$documents  = $documents  ?? [];
$signedUrls = $signedUrls ?? [];
$filieres   = $filieres   ?? [];

// ── Nature du dossier : deux parcours, un seul écran ────────────────────────
// Une INSCRIPTION suit la machine à états complète : validation sur pièces
// (50 %), passage au guichet, règlement, commission, attestation d'inscription.
// Une PRÉINSCRIPTION n'est rien de tout cela : elle est reçue, puis acceptée ou
// refusée. Elle n'a ni pièce à examiner, ni scolarité à encaisser, ni délai de
// présentation. Lui afficher le parcours d'inscription — c'est ce que faisait
// cet écran — pousse l'agent à valider à 50 % un candidat qui n'a rien déposé,
// et fait naître une commission sur une scolarité que personne ne doit.
// La règle vient du contrôleur, qui la tient du modèle (Dossier::estInscription).
$estInscription = !empty($estInscription);

// Les libellés d'état ne sont plus tenus ici : DossierStatut les rend, dans le
// vocabulaire du parcours concerné (préinscription ou inscription). Deux tables
// parallèles finissaient toujours par diverger de la machine à états.
$statut = $dossier['statut'] ?? 'brouillon';

// ── Refonte inscription (spec §8.2) ──
use Models\Orientation\DossierStatut;
use Services\Orientation\CommissionDossier;
use Services\Orientation\DecisionEtablissement;
use Services\Orientation\JetonDossier;
use Services\Orientation\SituationBourse;

$etat        = DossierStatut::normaliser($statut);

// Inscription menée à son terme. Les valeurs héritées ('inscrit', 'accepte')
// restent reconnues : des dossiers d'avant la machine à états les portent
// encore, et leur attestation doit rester téléchargeable.
$estValide   = $etat === DossierStatut::VALIDE_100 || in_array($statut, ['inscrit', 'accepte'], true);
$actions     = $actions     ?? [];
$historique  = $historique  ?? [];
$histBourse  = $histBourse  ?? [];
$scans       = $scans       ?? [];
$erreurs100  = is_array($erreurs100 ?? null) ? $erreurs100 : [];
$old100      = is_array($old100 ?? null) ? $old100 : [];
$modesPaiement = $modesPaiement ?? [];
// Les trois paliers (déposé → 50 % → inscrit) décrivent le parcours
// d'inscription. Une préinscription n'en a aucun à parcourir.
$paliers     = $estInscription ? DossierStatut::paliers($etat) : [];

$peut = static fn(string $vers): bool => in_array($vers, $actions, true);

// ── Retour arrière sur la dernière décision ──
// Déduit de l'état courant, jamais choisi par l'écran : le serveur refait le
// même calcul au moment du POST (DecisionEtablissement::annuler).
// Annuler suppose d'avoir décidé : sans décision possible sur une
// préinscription, il n'y a jamais de retour arrière à lui proposer.
$retourLibelle = $estInscription ? DossierStatut::retourLibelle($etat) : null;
$retourEffet   = $estInscription ? DossierStatut::retourEffet($etat) : null;

// ── Tarifs APPLIQUÉS, plus saisis (spec §8.4 révisée) ──
// Le montant de la scolarité et les frais d'inscription viennent de la fiche
// établissement, remplie par le super administrateur. L'agent du guichet
// constate un règlement : il ne fixe pas un tarif. La seule saisie restante
// concerne l'argent réellement remis aujourd'hui.
$tarif         = $tarif ?? ['montant' => null, 'cycle_libelle' => '', 'configure' => false, 'tarif_boursier' => null, 'source' => 'aucune'];
$fraisProposes = $fraisProposes ?? null;
$tranches      = $tranches ?? [];

$scolariteAppliquee = $tarif['montant'] ?? null;

// Repli : aucun tarif configuré nulle part. On rend la main à l'agent plutôt
// que de bloquer un guichet devant lequel un candidat attend.
$saisieManuelle = !$tarif['configure'];
$montantPropose = $old100['montant_scolarite_annuelle'] ?? ($scolarite !== null ? (string)(int)$scolarite : '');

$xaf = static fn($v): string => number_format((float)$v, 0, ',', ' ');
// ── Base de la commission ───────────────────────────────────────────────────
// Elle dépend de la situation de bourse : scolarité dans le cas général, et
// pour un boursier confirmé l'assiette convenue avec l'établissement (frais
// d'inscription par défaut, scolarité si l'accord le prévoit — réglage réservé
// au super administrateur). L'agent doit voir laquelle s'applique AVANT de
// valider — c'est le montant que son établissement devra ensuite à la
// plateforme, et il n'a aucun autre endroit où le lire.
$baseNature   = $tarif['base_commission'] ?? \Services\Orientation\TarifCycle::BASE_SCOLARITE;
$baseLibelle  = \Services\Orientation\TarifCycle::libelleBase($baseNature);
$baseMontant  = $tarif['montant_base_commission'] ?? $scolariteAppliquee;

$commissionPrevue = $baseMontant !== null
    ? round((float)$baseMontant * (float)$tauxCourant / 100, 0)
    : null;
?>

<div style="margin-bottom:18px;">
  <a href="<?= url('/gestionnaire/candidatures') ?>" style="font-size:13px;color:var(--ap);display:inline-flex;align-items:center;gap:5px;">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M5 12l7 7M5 12l7-7"/></svg>
    Retour aux dossiers
  </a>
</div>

<?php if (!empty($waUrl)): ?>
<div id="wa-cta" style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:18px;padding:14px 18px;border-radius:12px;background:#E7F9EE;border:1px solid #A7E9C1;">
  <svg width="24" height="24" viewBox="0 0 24 24" fill="#25D366" style="flex-shrink:0;"><path d="M17.5 14.4c-.3-.15-1.7-.84-2-.94-.26-.1-.45-.15-.64.15-.19.29-.74.94-.9 1.13-.17.19-.33.22-.62.07-.3-.15-1.24-.46-2.36-1.46-.87-.78-1.46-1.73-1.63-2.03-.17-.29-.02-.45.13-.6.13-.13.3-.34.44-.51.15-.17.2-.29.3-.48.1-.19.05-.36-.02-.51-.08-.15-.64-1.55-.88-2.12-.23-.55-.47-.48-.64-.49h-.55c-.19 0-.5.07-.76.36-.26.29-1 .98-1 2.38s1.02 2.76 1.17 2.95c.15.19 2.02 3.08 4.9 4.32.68.3 1.22.47 1.63.6.69.22 1.31.19 1.8.12.55-.08 1.7-.69 1.94-1.36.24-.67.24-1.24.17-1.36-.07-.12-.26-.19-.55-.34zM12 2a10 10 0 0 0-8.6 15.06L2 22l5.06-1.33A10 10 0 1 0 12 2z"/></svg>
  <div style="flex:1;min-width:200px;">
    <div style="font-size:13.5px;font-weight:700;color:#15803d;">Finalise l'envoi sur WhatsApp</div>
    <div style="font-size:12px;color:#166534;margin-top:2px;">Le message est déjà arrivé dans l'espace du candidat. Ouvre WhatsApp pour lui envoyer aussi la copie.</div>
  </div>
  <a href="<?= e($waUrl) ?>" target="_blank" rel="noopener" id="wa-open-link"
     style="display:inline-flex;align-items:center;gap:7px;background:#25D366;color:#fff;font-size:13px;font-weight:700;padding:10px 18px;border-radius:9px;text-decoration:none;white-space:nowrap;">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M17.5 14.4c-.3-.15-1.7-.84-2-.94-.26-.1-.45-.15-.64.15-.19.29-.74.94-.9 1.13-.17.19-.33.22-.62.07-.3-.15-1.24-.46-2.36-1.46-.87-.78-1.46-1.73-1.63-2.03-.17-.29-.02-.45.13-.6.13-.13.3-.34.44-.51.15-.17.2-.29.3-.48.1-.19.05-.36-.02-.51-.08-.15-.64-1.55-.88-2.12-.23-.55-.47-.48-.64-.49h-.55c-.19 0-.5.07-.76.36-.26.29-1 .98-1 2.38s1.02 2.76 1.17 2.95c.15.19 2.02 3.08 4.9 4.32.68.3 1.22.47 1.63.6.69.22 1.31.19 1.8.12.55-.08 1.7-.69 1.94-1.36.24-.67.24-1.24.17-1.36-.07-.12-.26-.19-.55-.34zM12 2a10 10 0 0 0-8.6 15.06L2 22l5.06-1.33A10 10 0 1 0 12 2z"/></svg>
    Ouvrir WhatsApp
  </a>
</div>
<script>
  // Tentative d'ouverture auto (peut être bloquée par le navigateur → le bouton reste disponible)
  (function () {
    try { window.open(<?= json_encode($waUrl, JSON_UNESCAPED_SLASHES) ?>, '_blank', 'noopener'); } catch (e) {}
  })();
</script>
<?php endif; ?>

<div style="display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:20px;align-items:start;" class="g-show-grid">

  <!-- Colonne principale -->
  <div>

    <!-- ══ Zone identité — en-tête (spec §8.2) ══ -->
    <div class="g-card f-bloc">
      <div class="f-entete">
        <div class="f-entete-txt">
          <?php if (!empty($dossier['reference'])): ?>
            <div class="f-ref"><?= e($dossier['reference']) ?></div>
          <?php endif; ?>
          <div class="f-nom"><?= e($dossier['nom_complet'] ?? '—') ?></div>
          <div class="f-sous">
            <?= e($dossier['niveau_entree'] ?? $dossier['filiere_visee'] ?? '—') ?>
            <?php if (!empty($dossier['specialite_visee'])): ?> — <?= e($dossier['specialite_visee']) ?><?php endif; ?>
          </div>
        </div>
        <div style="text-align:right">
          <span class="g-badge d-badge-<?= e(DossierStatut::ton($etat)) ?>"><?= e(DossierStatut::libelle($etat, $estInscription)) ?></span>
          <?php if (!empty($dossier['statut_bourse'])): ?>
            <div style="margin-top:6px"><span class="d-bourse d-bourse-<?= e($dossier['statut_bourse']) ?>"><?= e(SituationBourse::libelleCourt($dossier['statut_bourse'])) ?></span></div>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($estInscription): ?>
      <!-- Trois paliers : déposé, validé à 50 %, inscrit -->
      <div class="f-paliers">
        <?php foreach ($paliers as $palier): ?>
          <span class="<?= $palier['atteint'] ? 'f-atteint' : '' ?>"></span>
        <?php endforeach; ?>
      </div>
      <div class="f-paliers-noms">
        <?php foreach ($paliers as $palier): ?>
          <?php if ($palier['atteint']): ?><b><?= e($palier['libelle']) ?></b><?php else: ?><span><?= e($palier['libelle']) ?></span><?php endif; ?>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <!-- Préinscription : aucun palier à parcourir. Une phrase qui dit ce que
           ce dossier est, et ce qu'il n'est pas — c'est tout ce que l'agent a
           besoin de savoir avant de décider. -->
      <p class="f-aide" style="margin-top:14px">
        <strong>Préinscription</strong> — le candidat a manifesté son intérêt et reçu son attestation
        de préinscription. Aucune pièce, aucun paiement, aucun délai, et rien à valider de votre côté.
        Le parcours d'inscription (validation à 50 %, passage au guichet, scolarité) commencera
        lorsqu'il déposera son dossier d'inscription.
      </p>
      <?php endif; ?>

      <?php if ($etat === DossierStatut::REJETE && !empty($dossier['motif_rejet'])): ?>
        <p class="f-erreur" style="margin-top:14px">Motif du refus : <?= e($dossier['motif_rejet']) ?></p>
      <?php elseif ($etat === DossierStatut::PIECE_MANQUANTE && !empty($dossier['pieces_attendues'])): ?>
        <p class="f-aide" style="margin-top:14px">Pièces demandées : <strong><?= e($dossier['pieces_attendues']) ?></strong></p>
      <?php endif; ?>

      <dl class="f-faits" style="margin-top:18px">
        <div><dt>Déposé le</dt><dd><?= e(!empty($dossier['soumis_le']) ? date('d/m/Y à H\hi', strtotime((string)$dossier['soumis_le'])) : '—') ?></dd></div>
        <?php if (!empty($dossier['valide_50_le'])): ?>
          <div><dt><?= $estInscription ? 'Validé à 50 %' : 'Acceptée le' ?></dt><dd><?= e(date('d/m/Y', strtotime((string)$dossier['valide_50_le']))) ?></dd></div>
        <?php endif; ?>
        <?php if ($estInscription && !empty($dossier['expire_le']) && $etat === DossierStatut::VALIDE_50): ?>
          <div><dt>À finaliser avant</dt><dd><?= e(date('d/m/Y', strtotime((string)$dossier['expire_le']))) ?></dd></div>
        <?php endif; ?>
        <?php if ($estInscription && !empty($dossier['valide_100_le'])): ?>
          <div><dt>Inscrit le</dt><dd><?= e(date('d/m/Y', strtotime((string)$dossier['valide_100_le']))) ?></dd></div>
        <?php endif; ?>
        <?php if (!empty($jeton)): ?>
          <div><dt>Code de vérification</dt><dd style="font-family:ui-monospace,Menlo,monospace"><?= e(JetonDossier::formaterCodeCourt($jeton['code_court'])) ?></dd></div>
        <?php endif; ?>
      </dl>

      <?php if (!empty($dossier['statut_bourse'])): ?>
        <p class="f-aide" style="margin-top:14px">
          Situation de bourse déclarée par l'étudiant
          <?php if (!empty($dossier['bourse_declaree_le'])): ?>
            le <?= e(date('d/m/Y à H\hi', strtotime((string)$dossier['bourse_declaree_le']))) ?>
          <?php endif; ?>.
          <?php if (count($histBourse) > 1): ?>
            <?= count($histBourse) ?> changements enregistrés.
          <?php endif; ?>
          Elle n'a aucune incidence sur la commission.
        </p>
      <?php endif; ?>
    </div>

    <!-- Identité -->
    <div class="g-card" style="margin-bottom:20px;">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;gap:12px;flex-wrap:wrap;">
        <h2 style="font-size:16px;font-weight:700;"><?= e($dossier['nom_complet'] ?? '—') ?></h2>
        <div style="display:flex;align-items:center;gap:10px;">
          <span class="g-badge d-badge-<?= e(DossierStatut::ton($etat)) ?>" style="font-size:12px;"><?= e(DossierStatut::libelle($etat, $estInscription)) ?></span>
          <a href="<?= url('/gestionnaire/candidatures/' . $dossier['id'] . '/modifier') ?>" class="g-btn g-btn-outline g-btn-sm">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
            Modifier
          </a>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <?php
        $fields = [
            'email'                 => 'E-mail',
            'telephone'             => 'Téléphone',
            'genre'                 => 'Genre',
            'date_naissance'        => 'Date de naissance',
            'lieu_naissance'        => 'Lieu de naissance',
            'nationalite'           => 'Nationalité',
            'pays_provenance'       => 'Pays de provenance',
            'ville'                 => 'Ville',
            'adresse'               => 'Adresse',
            'dernier_etablissement' => 'Dernier établissement',
        ];
        foreach ($fields as $key => $label): ?>
          <div>
            <div style="font-size:10px;text-transform:uppercase;letter-spacing:.05em;color:var(--ink-3);font-weight:600;margin-bottom:3px;"><?= $label ?></div>
            <div style="font-size:13px;font-weight:500;"><?= e($dossier[$key] ?? '—') ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Formation -->
    <div class="g-card" style="margin-bottom:20px;">
      <h3 style="font-size:14px;font-weight:700;margin-bottom:14px;">Formation souhaitée</h3>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <div>
          <div style="font-size:10px;text-transform:uppercase;letter-spacing:.05em;color:var(--ink-3);font-weight:600;margin-bottom:3px;">Filière visée</div>
          <div style="font-size:13px;font-weight:500;"><?= e($filieres[$dossier['filiere_visee'] ?? ''] ?? $dossier['filiere_visee'] ?? '—') ?></div>
        </div>
        <?php if (!empty($dossier['specialite_visee'])): ?>
        <div>
          <div style="font-size:10px;text-transform:uppercase;letter-spacing:.05em;color:var(--ink-3);font-weight:600;margin-bottom:3px;">Spécialité</div>
          <div style="font-size:13px;font-weight:500;"><?= e($dossier['specialite_visee']) ?></div>
        </div>
        <?php endif; ?>
        <div>
          <div style="font-size:10px;text-transform:uppercase;letter-spacing:.05em;color:var(--ink-3);font-weight:600;margin-bottom:3px;">Niveau d'entrée</div>
          <div style="font-size:13px;font-weight:500;"><?= e($dossier['niveau_entree'] ?? '—') ?></div>
        </div>
        <?php /* Bac : imprimé sur le récépissé et l'attestation. Le voir ici
                 permet de repérer un dossier ancien qui en manque encore. */ ?>
        <div>
          <div style="font-size:10px;text-transform:uppercase;letter-spacing:.05em;color:var(--ink-3);font-weight:600;margin-bottom:3px;">Série du Bac</div>
          <div style="font-size:13px;font-weight:500;"><?= e((string)($dossier['serie_bac'] ?? '') ?: '—') ?></div>
        </div>
        <div>
          <div style="font-size:10px;text-transform:uppercase;letter-spacing:.05em;color:var(--ink-3);font-weight:600;margin-bottom:3px;">Année du Bac</div>
          <div style="font-size:13px;font-weight:500;"><?= e((string)($dossier['annee_bac'] ?? '') ?: '—') ?></div>
        </div>
        <?php if (!empty($dossier['numero_recepisse'])): ?>
        <div>
          <div style="font-size:10px;text-transform:uppercase;letter-spacing:.05em;color:var(--ink-3);font-weight:600;margin-bottom:3px;">Attestation</div>
          <div style="font-size:13px;font-weight:600;color:var(--ap);"><?= e($dossier['numero_recepisse']) ?></div>
        </div>
        <?php endif; ?>
        <?php if (!empty($dossier['paiement_montant'])): ?>
        <div>
          <div style="font-size:10px;text-transform:uppercase;letter-spacing:.05em;color:var(--ink-3);font-weight:600;margin-bottom:3px;">Paiement</div>
          <div style="font-size:13px;font-weight:600;color:#15803d;"><?= number_format((int)$dossier['paiement_montant']) ?> XAF</div>
        </div>
        <?php endif; ?>
      </div>
      <div style="margin-top:12px;">
        <?php if (($dossier['origine'] ?? '') === 'etablissement'): ?>
          <span class="g-badge g-badge-guichet">Saisie au guichet</span>
        <?php else: ?>
          <span class="g-badge g-badge-enligne">Candidature en ligne</span>
        <?php endif; ?>
      </div>
    </div>

    <!-- ══ Zone pièces (spec §8.2) ══
         Chaque pièce se consulte par une URL signée de 5 minutes, et se
         décide individuellement : acceptée, ou refusée avec motif. -->
    <?php if (!empty($documents)): ?>
    <div class="g-card f-bloc" id="pieces">
      <h3>Pièces du dossier (<?= count($documents) ?>)</h3>
      <?php foreach ($documents as $doc):
        $etatPiece = (string)($doc['statut'] ?? 'en_attente');
        $estBourse = in_array((string)($doc['type_document'] ?? ''), ['attestation_bourse', 'preuve_depot_anbg'], true);
      ?>
        <div class="f-piece">
          <div class="f-piece-txt">
            <div class="f-piece-nom">
              <?= e($doc['piece_libelle'] ?? $doc['libelle'] ?? 'Document') ?>
              <?php if ($estBourse): ?>
                <span class="d-bourse d-bourse-boursier" style="margin-left:6px">Bourse</span>
              <?php endif; ?>
            </div>
            <div class="f-piece-meta">
              <?= e($doc['mime_type'] ?? '') ?> · <?= round(((int)($doc['taille_octets'] ?? 0)) / 1024) ?> Ko
              · déposée le <?= e(date('d/m/Y', strtotime((string)$doc['date_depot']))) ?>
              <?php if (!empty($doc['hash_sha256'])): ?>
                · empreinte <?= e(substr((string)$doc['hash_sha256'], 0, 8)) ?>
              <?php endif; ?>
            </div>
            <?php if ($etatPiece === 'refuse' && !empty($doc['motif_refus'])): ?>
              <div class="f-piece-motif">Refusée : <?= e($doc['motif_refus']) ?></div>
            <?php endif; ?>
          </div>

          <span class="f-etat f-etat-<?= e($etatPiece) ?>">
            <?= e(match ($etatPiece) { 'accepte' => 'Acceptée', 'refuse' => 'Refusée', default => 'À vérifier' }) ?>
          </span>

          <div class="f-piece-actions">
            <?php if (!empty($signedUrls[$doc['id']])): ?>
              <a href="<?= e($signedUrls[$doc['id']]) ?>" target="_blank" rel="noopener" class="g-btn g-btn-outline g-btn-sm">Consulter</a>
            <?php else: ?>
              <span class="f-piece-meta">Lien indisponible</span>
            <?php endif; ?>

            <?php if ($etatPiece !== 'accepte'): ?>
            <form method="post" action="<?= url('/gestionnaire/candidatures/' . (int)$dossier['id'] . '/piece/' . (int)$doc['id']) ?>" style="display:inline">
              <input type="hidden" name="_csrf_token" value="<?= e(\Core\Session::getCsrfToken()) ?>">
              <input type="hidden" name="decision" value="accepte">
              <button type="submit" class="g-btn g-btn-ghost g-btn-sm">Accepter</button>
            </form>
            <?php endif; ?>

            <?php if ($etatPiece !== 'refuse'): ?>
            <form method="post" action="<?= url('/gestionnaire/candidatures/' . (int)$dossier['id'] . '/piece/' . (int)$doc['id']) ?>" style="display:inline"
                  onsubmit="var m = prompt('Motif du refus de cette pièce ?'); if (!m) { return false; } this.motif.value = m; return true;">
              <input type="hidden" name="_csrf_token" value="<?= e(\Core\Session::getCsrfToken()) ?>">
              <input type="hidden" name="decision" value="refuse">
              <input type="hidden" name="motif" value="">
              <button type="submit" class="g-btn g-btn-ghost g-btn-sm">Refuser</button>
            </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Colonne latérale : actions -->
  <div>
    <div class="g-card" style="margin-bottom:16px;">
      <h3 style="font-size:13px;font-weight:700;margin-bottom:14px;text-transform:uppercase;letter-spacing:.04em;color:var(--ink-3);">Attestation de préinscription</h3>
      <?php
        $recepisseExiste  = !empty($recepisse['numero_recepisse']);
        $recepisseActive  = (int)($dossier['genere_recepisse'] ?? 1) === 1;
      ?>
      <?php if ($recepisseExiste): ?>
        <div style="font-size:12px;color:var(--ink-2);margin-bottom:10px;">N° <strong style="color:var(--ap);"><?= e($recepisse['numero_recepisse']) ?></strong></div>
      <?php endif; ?>
      <?php if ($recepisseExiste || $recepisseActive): ?>
        <a href="<?= url('/gestionnaire/candidatures/' . $dossier['id'] . '/recepisse') ?>" target="_blank" rel="noopener" class="g-btn g-btn-primary" style="width:100%;justify-content:center;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
          Télécharger l'attestation
        </a>
        <p style="font-size:11px;color:var(--ink-3);margin-top:8px;line-height:1.5;">S'ouvre dans un nouvel onglet — réimprimez ou enregistrez en PDF (Ctrl+P) autant de fois que nécessaire.</p>
      <?php else: ?>
        <p style="font-size:11px;color:var(--ink-3);line-height:1.5;">La génération d'attestation de préinscription est désactivée dans vos <a href="<?= url('/gestionnaire/parametres') ?>" style="color:var(--ap-deep);font-weight:600;">paramètres</a>.</p>
      <?php endif; ?>
    </div>

    <?php
      $attestationExiste = !empty($attestation['numero_attestation']);
      $attestationActive = (int)($dossier['genere_attestation'] ?? 1) === 1;
    ?>
    <?php if ($estInscription && ($attestationExiste || ($estValide && $attestationActive))): ?>
    <div class="g-card" style="margin-bottom:16px;">
      <h3 style="font-size:13px;font-weight:700;margin-bottom:14px;text-transform:uppercase;letter-spacing:.04em;color:var(--ink-3);">Attestation d'inscription</h3>
      <?php if ($attestationExiste): ?>
        <div style="font-size:12px;color:var(--ink-2);margin-bottom:10px;">N° <strong style="color:var(--ap);"><?= e($attestation['numero_attestation']) ?></strong></div>
      <?php endif; ?>
      <a href="<?= url('/gestionnaire/candidatures/' . $dossier['id'] . '/attestation') ?>" target="_blank" rel="noopener" class="g-btn g-btn-primary" style="width:100%;justify-content:center;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        Télécharger l'attestation
      </a>
      <?php if ($attestationExiste): ?>
        <p style="font-size:11px;color:var(--ink-3);margin-top:8px;line-height:1.5;">Délivrée le <?= !empty($attestation['date_emission']) ? date('d/m/Y', strtotime((string)$attestation['date_emission'])) : '—' ?> — réimprimable sans limite, même après un changement de statut. Le candidat la retrouve aussi dans son espace.</p>
      <?php else: ?>
        <p style="font-size:11px;color:var(--ink-3);margin-top:8px;line-height:1.5;">Délivrée après validation de l'inscription — le candidat la retrouve aussi dans son espace.</p>
      <?php endif; ?>
    </div>
    <?php elseif ($estInscription && $estValide && !$attestationActive): ?>
    <div class="g-card" style="margin-bottom:16px;">
      <h3 style="font-size:13px;font-weight:700;margin-bottom:14px;text-transform:uppercase;letter-spacing:.04em;color:var(--ink-3);">Attestation d'inscription</h3>
      <p style="font-size:11px;color:var(--ink-3);line-height:1.5;">La génération d'attestation est désactivée dans vos <a href="<?= url('/gestionnaire/parametres') ?>" style="color:var(--ap-deep);font-weight:600;">paramètres</a>.</p>
    </div>
    <?php endif; ?>

    <?php if ($estInscription && \Services\Orientation\RecuInscriptionService::disponible($dossier)): ?>
    <!-- Reçu de scolarité : preuve du VERSEMENT, distincte de l'attestation qui
         prouve l'inscription. Réimprimable au guichet, là où l'argent change
         de main. -->
    <div class="g-card" style="margin-bottom:16px;">
      <h3 style="font-size:13px;font-weight:700;margin-bottom:14px;text-transform:uppercase;letter-spacing:.04em;color:var(--ink-3);">Reçu de paiement</h3>
      <div style="font-size:12px;color:var(--ink-2);margin-bottom:10px;">
        N° <strong style="color:var(--ap);"><?= e(\Services\Orientation\RecuInscriptionService::numero($dossier)) ?></strong>
      </div>
      <a href="<?= url('/gestionnaire/candidatures/' . (int)$dossier['id'] . '/recu') ?>" target="_blank" rel="noopener" class="g-btn g-btn-outline" style="width:100%;justify-content:center;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2h12v19l-3-2-3 2-3-2-3 2z"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="9" y1="12" x2="15" y2="12"/></svg>
        Imprimer le reçu
      </a>
      <p style="font-size:11px;color:var(--ink-3);margin-top:8px;line-height:1.5;">
        <?= e(number_format((float)$dossier['montant_encaisse_initial'], 0, ',', ' ')) ?> XAF encaissés
        le <?= !empty($dossier['date_paiement']) ? e(date('d/m/Y', strtotime((string)$dossier['date_paiement']))) : '—' ?>.
        Le candidat le retrouve aussi dans son espace.
      </p>
    </div>
    <?php endif; ?>

    <?php if ($estInscription): ?>
    <!-- ══ Zone décision (spec §8.2) ══
         Les actions proposées viennent de la machine à états : cet écran ne
         décide de rien. Un bouton absent correspond à une transition que le
         serveur refuserait de toute façon.

         Réservée au parcours d'INSCRIPTION. Une préinscription ne se valide ni
         ne se refuse : elle n'engage rien et son attestation part dès le dépôt.
         Lui donner une zone « Décision » invente une étape qui n'existe pas. -->
    <div class="g-card f-bloc f-decision" id="decision">
      <h3>Décision</h3>

      <?php if ($actions === []): ?>
        <p class="f-aide">
          <?php if (DossierStatut::estTerminal($etat)): ?>
            Ce dossier est dans un état définitif (<?= e(DossierStatut::libelle($etat, $estInscription)) ?>).
            <?php if ($retourLibelle !== null): ?>
              La dernière décision reste annulable ci-dessous ; au-delà, seul un super
              administrateur peut le rouvrir.
            <?php else: ?>
              Seul un super administrateur peut encore le rouvrir, et l'action est journalisée.
            <?php endif; ?>
          <?php else: ?>
            Aucune action disponible sur ce dossier pour le moment.
          <?php endif; ?>
        </p>
      <?php endif; ?>

      <?php if ($peut(DossierStatut::VALIDE_50) && $estInscription): ?>
      <!-- ── Validation sur pièces ── -->
      <div class="f-action">
        <form method="post" action="<?= url('/gestionnaire/candidatures/' . (int)$dossier['id'] . '/valider-50') ?>"
              onsubmit="return confirm('Valider ce dossier à 50 % ? Le candidat sera invité à se présenter avec son récépissé.')">
          <input type="hidden" name="_csrf_token" value="<?= e(\Core\Session::getCsrfToken()) ?>">
          <label for="commentaire50">Commentaire pour le candidat (facultatif)</label>
          <textarea id="commentaire50" name="commentaire" maxlength="2000"
                    placeholder="Ex. Présentez-vous au bureau des inscriptions, bâtiment A, du lundi au vendredi."></textarea>
          <button type="submit" class="g-btn g-btn-primary">Valider à 50 %</button>
          <p class="f-aide">Le dossier est jugé recevable sur pièces. La date limite de présentation
          est calculée automatiquement selon le délai réglé dans vos paramètres.</p>
        </form>
      </div>
      <?php endif; ?>

      <?php if ($peut(DossierStatut::VALIDE_100)): ?>
      <!-- ── Validation à 100 % (spec §8.4 révisée) ──
           Les MONTANTS ne se saisissent plus ici. Ils sont fixés par le super
           administrateur — grille tarifaire de la formation, puis fiche de
           l'établissement — et dépendent du niveau d'entrée et de la situation
           de bourse déclarée. L'agent constate un règlement ; il ne négocie pas
           un tarif, et il n'a rien à « confirmer » qui soit déjà décidé
           ailleurs. Ce qui reste saisissable, c'est l'argent réellement remis
           aujourd'hui — la seule chose que la configuration ne peut pas savoir. -->
      <div class="f-action" id="valider-100">
        <form method="post" action="<?= url('/gestionnaire/candidatures/' . (int)$dossier['id'] . '/valider-100') ?>"
              id="form-100">
          <input type="hidden" name="_csrf_token" value="<?= e(\Core\Session::getCsrfToken()) ?>">

          <?php if (!$saisieManuelle): ?>
            <div class="f-tarif">
              <div class="f-tarif-t">Tarifs appliqués — cycle <?= e($tarif['cycle_libelle']) ?></div>
              <dl class="f-tarif-l">
                <div>
                  <dt>Scolarité annuelle</dt>
                  <dd><span class="f-tarif-n"><?= e($xaf($scolariteAppliquee)) ?> XAF</span></dd>
                </div>
                <?php if ($fraisProposes !== null): ?>
                <div>
                  <dt>Frais d'inscription</dt>
                  <dd><span class="f-tarif-n"><?= e($xaf($fraisProposes)) ?> XAF</span></dd>
                </div>
                <?php endif; ?>
                <div>
                  <dt>Commission plateforme (<?= e(rtrim(rtrim(number_format((float)$tauxCourant, 2, ',', ' '), '0'), ',')) ?> %)</dt>
                  <dd><span class="f-tarif-n"><?= e($xaf($commissionPrevue)) ?> XAF</span></dd>
                </div>
                <div>
                  <dt>Base de la commission</dt>
                  <?php /* Le libellé et le montant sont deux éléments distincts :
                           collés dans une seule chaîne insécable, ils poussaient
                           la ligne hors de la carte dès que la base était
                           « Frais d'inscription ». Seul le nombre reste
                           insécable, le libellé peut revenir à la ligne. */ ?>
                  <dd>
                    <span class="f-tarif-txt"><?= e($baseLibelle) ?></span>
                    <?php if ($baseMontant !== null): ?>
                      <span class="f-tarif-n"><?= e($xaf($baseMontant)) ?> XAF</span>
                    <?php endif; ?>
                  </dd>
                </div>
              </dl>
              <p class="f-aide" style="margin:8px 0 0">
                Montants issus de la grille de la formation
                <?= !empty($tarif['niveau_libelle']) ? '(' . e($tarif['niveau_libelle']) . ')' : '' ?>,
                fixée par Connect'Academia. Ils s'appliquent automatiquement : rien à ressaisir.
                <?php if ($baseNature === \Services\Orientation\TarifCycle::BASE_FRAIS_INSCRIPTION): ?>
                  <br><strong>Ce candidat est déclaré boursier :</strong> la commission porte sur ses
                  <strong>frais d'inscription</strong>, pas sur sa scolarité — celle-ci est prise en
                  charge par l'ANBG.
                <?php elseif (($dossier['statut_bourse'] ?? '') === \Services\Orientation\SituationBourse::NON_BOURSIER): ?>
                  <br>Candidat non boursier : la commission porte sur la scolarité. S'il se déclare
                  boursier plus tard — sa demande ANBG ayant abouti — elle sera recalculée sur les
                  frais d'inscription, tant qu'aucune tranche n'a été encaissée.
                <?php endif; ?>
                Pour modifier ces tarifs, contactez Connect'Academia.
              </p>
            </div>
          <?php else: ?>
            <?php // Aucun tarif configuré pour ce cycle : on ne bloque pas le
                  // guichet. La saisie reste possible, et le message dit
                  // exactement quoi faire pour qu'elle disparaisse. ?>
            <p class="f-aide" style="margin:0 0 10px;color:#b45309">
              Aucun tarif de scolarité n'est configuré pour le cycle
              « <?= e($tarif['cycle_libelle']) ?> » de votre établissement.
              Saisissez le montant pour ne pas bloquer ce candidat, puis demandez
              à Connect'Academia de renseigner la fiche.
            </p>
            <label for="montant_scolarite_annuelle">Montant de la scolarité annuelle (XAF) *</label>
            <input type="text" inputmode="numeric" id="montant_scolarite_annuelle" name="montant_scolarite_annuelle"
                   value="<?= e($montantPropose) ?>" placeholder="750000" required
                   class="f-obligatoire<?= isset($erreurs100['montant_scolarite_annuelle']) ? ' f-champ-erreur' : '' ?>">
            <?php if (isset($erreurs100['montant_scolarite_annuelle'])): ?>
              <p class="f-erreur"><?= e($erreurs100['montant_scolarite_annuelle']) ?></p>
            <?php endif; ?>
          <?php endif; ?>

          <?php /* ══ Versement du jour — champ RETIRÉ ══
                   Le règlement de la scolarité se constate par tranche, dans la
                   zone dédiée du dossier, et non au moment de la validation à
                   100 % : les deux saisies faisaient double emploi et l'agent
                   pouvait enregistrer ici un montant qu'il ressaisirait ensuite.
                   Le champ `montant_encaisse_initial` reste en base et sur le
                   reçu — DecisionEtablissement le lit à 0 quand il n'est pas
                   posté (« laissez vide si aucun versement »), donc aucun
                   dossier existant n'est affecté. */ ?>

          <div class="f-duo">
            <div>
              <label for="mode_paiement">Mode de paiement *</label>
              <select id="mode_paiement" name="mode_paiement" required
                      class="f-obligatoire<?= isset($erreurs100['mode_paiement']) ? ' f-champ-erreur' : '' ?>">
                <?php foreach ($modesPaiement as $val => $lbl): ?>
                  <option value="<?= e($val) ?>" <?= ($old100['mode_paiement'] ?? 'especes') === $val ? 'selected' : '' ?>><?= e($lbl) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label for="date_paiement">Date de paiement *</label>
              <input type="date" id="date_paiement" name="date_paiement"
                     value="<?= e($old100['date_paiement'] ?? date('Y-m-d')) ?>"
                     max="<?= date('Y-m-d') ?>" required
                     class="f-obligatoire<?= isset($erreurs100['date_paiement']) ? ' f-champ-erreur' : '' ?>">
            </div>
          </div>
          <?php foreach (['mode_paiement', 'date_paiement'] as $champ): ?>
            <?php if (isset($erreurs100[$champ])): ?><p class="f-erreur"><?= e($erreurs100[$champ]) ?></p><?php endif; ?>
          <?php endforeach; ?>

          <label for="reference_paiement">Référence du paiement (facultatif)</label>
          <input type="text" id="reference_paiement" name="reference_paiement" maxlength="80"
                 value="<?= e($old100['reference_paiement'] ?? '') ?>" placeholder="N° du reçu de l'établissement">

          <button type="submit" class="g-btn g-btn-primary" id="btn-100">Valider l'inscription à 100 %</button>
          <p class="f-aide">
            La commission est calculée sur <strong><?= e(mb_strtolower($baseLibelle)) ?></strong>,
            au taux de <strong><?= e(number_format((float)$tauxCourant, 2, ',', ' ')) ?> %</strong>,
            gelé à cet instant. Elle est due quelle que soit la situation de bourse — seule sa
            base en dépend — et se recouvre ensuite en <?= e(CommissionDossier::libelleRepartition()) ?>.
          </p>
        </form>
      </div>
      <?php endif; ?>

      <?php if ($peut(DossierStatut::PIECE_MANQUANTE)): ?>
      <div class="f-action">
        <details class="f-repli">
          <summary>Demander une pièce complémentaire</summary>
          <form method="post" action="<?= url('/gestionnaire/candidatures/' . (int)$dossier['id'] . '/demander-piece') ?>">
            <input type="hidden" name="_csrf_token" value="<?= e(\Core\Session::getCsrfToken()) ?>">
            <label for="pieces_attendues">Pièces attendues *</label>
            <textarea id="pieces_attendues" name="pieces_attendues" required maxlength="1000"
                      placeholder="Ex. Relevé de notes du baccalauréat lisible, et photo d'identité récente."></textarea>
            <button type="submit" class="g-btn g-btn-outline">Demander la pièce</button>
            <p class="f-aide">Soyez précis : c'est ce texte que le candidat lira dans son espace.</p>
          </form>
        </details>
      </div>
      <?php endif; ?>

      <?php if ($etat === DossierStatut::PIECE_MANQUANTE): ?>
      <div class="f-action">
        <form method="post" action="<?= url('/gestionnaire/candidatures/' . (int)$dossier['id'] . '/relancer') ?>">
          <input type="hidden" name="_csrf_token" value="<?= e(\Core\Session::getCsrfToken()) ?>">
          <button type="submit" class="g-btn g-btn-outline">Relancer le candidat</button>
        </form>
      </div>
      <?php endif; ?>

      <?php if ($etat === DossierStatut::VALIDE_50 && $estInscription): ?>
      <div class="f-action">
        <details class="f-repli">
          <summary>Prolonger le délai de présentation</summary>
          <form method="post" action="<?= url('/gestionnaire/candidatures/' . (int)$dossier['id'] . '/prolonger') ?>">
            <input type="hidden" name="_csrf_token" value="<?= e(\Core\Session::getCsrfToken()) ?>">
            <label for="jours">Jours supplémentaires</label>
            <input type="number" id="jours" name="jours" min="1" max="90" value="15" required>
            <button type="submit" class="g-btn g-btn-outline">Prolonger</button>
          </form>
        </details>
      </div>
      <?php endif; ?>

      <?php if ($peut(DossierStatut::REJETE)): ?>
      <div class="f-action">
        <details class="f-repli f-danger">
          <summary>Rejeter le dossier</summary>
          <form method="post" action="<?= url('/gestionnaire/candidatures/' . (int)$dossier['id'] . '/rejeter') ?>"
                onsubmit="return confirm('Rejeter définitivement ce dossier ? Cette décision est irréversible.')">
            <input type="hidden" name="_csrf_token" value="<?= e(\Core\Session::getCsrfToken()) ?>">
            <label for="motif">Motif du refus *</label>
            <textarea id="motif" name="motif" required minlength="10" maxlength="2000"
                      placeholder="Ex. Le diplôme fourni ne correspond pas au niveau requis pour cette filière."></textarea>
            <button type="submit" class="g-btn g-btn-danger">Rejeter le dossier</button>
            <p class="f-aide">Le motif est obligatoire et transmis au candidat. Un refus sans motif
            est indéfendable en cas de litige. État définitif.</p>
          </form>
        </details>
      </div>
      <?php endif; ?>

      <?php if ($retourLibelle !== null): ?>
      <!-- ══ Retour arrière (droit à l'erreur du guichet) ══
           Séparé des décisions au-dessus, et pour cause : ce n'est pas une
           décision de plus mais l'annulation de la précédente. L'état rendu
           n'est pas choisi ici, il est déduit côté serveur de l'état réel du
           dossier au moment du clic. -->
      <div class="f-action f-retour">
        <details class="f-repli">
          <summary><?= e($retourLibelle) ?></summary>
          <p class="f-aide" style="margin:0 0 10px"><?= e($retourEffet ?? '') ?></p>
          <form method="post"
                action="<?= url('/gestionnaire/candidatures/' . (int)$dossier['id'] . '/annuler-decision') ?>"
                onsubmit="return confirm(<?= e(json_encode($retourLibelle . ' ? ' . ($retourEffet ?? ''), JSON_UNESCAPED_UNICODE)) ?>)">
            <input type="hidden" name="_csrf_token" value="<?= e(\Core\Session::getCsrfToken()) ?>">
            <label for="motif_annulation">Raison de l'annulation (facultatif, conservée dans l'historique)</label>
            <textarea id="motif_annulation" name="motif_annulation" maxlength="2000"
                      placeholder="Ex. Validation enregistrée sur le mauvais dossier."></textarea>
            <button type="submit" class="g-btn g-btn-outline"><?= e($retourLibelle) ?></button>
            <p class="f-aide">L'annulation est tracée au nom de l'agent connecté et le candidat en est informé.</p>
          </form>
        </details>
      </div>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <!-- Préinscription : rien à décider. On dit ce qui a déjà eu lieu et ce qui
         viendra, plutôt que de laisser un cadre vide devant l'agent. -->
    <div class="g-card f-bloc">
      <h3>Suite du parcours</h3>
      <p class="f-aide" style="margin:0">
        Rien à décider ici : une préinscription ne se valide pas et ne se refuse pas.
        Le candidat a reçu son attestation de préinscription dès le dépôt.
        S'il poursuit, son <strong>dossier d'inscription</strong> apparaîtra dans vos dossiers,
        avec les pièces à examiner et la validation à 50 % puis 100 %.
        En attendant, vous pouvez le contacter ci-dessous.
      </p>
    </div>
    <?php endif; ?>

    <?php if ($estInscription && !empty($commission)): ?>
    <!-- Commission générée à la validation à 100 % — taux gelé (spec §10.2) -->
    <div class="g-card f-bloc">
      <h3>Commission</h3>
      <dl class="f-commission">
        <div><dt>Base</dt><dd><?= number_format((float)$commission['montant_scolarite'], 0, ',', ' ') ?></dd></div>
        <div><dt>Taux gelé</dt><dd><?= number_format((float)$commission['taux_commission'], 2, ',', ' ') ?> %</dd></div>
        <div><dt>Montant</dt><dd><?= number_format((float)$commission['montant_commission'], 0, ',', ' ') ?></dd></div>
      </dl>
      <p class="f-aide" style="text-align:center">
        <?= e(CommissionDossier::libelle((string)$commission['statut'])) ?>
        <?php if (!empty($commission['periode_facturation'])): ?> · période <?= e($commission['periode_facturation']) ?><?php endif; ?>
      </p>

      <?php if ($tranches !== []): ?>
      <?php // ── Échéancier de recouvrement ──
            // Rendu tel qu'il est EN BASE, tranche par tranche : les
            // échéanciers créés avant le passage à 60/40 en portent trois, et
            // les afficher tels quels est la seule façon honnête de le dire.
            // L'établissement voit ici où en est SON recouvrement, sans quitter
            // la fiche. Il ne coche rien : c'est Connect'Academia qui constate
            // les règlements, elle seule les reçoit. ?>
      <ul class="f-tranches">
        <?php foreach ($tranches as $t):
          $etatT = (int)$t['est_payee'] === 1
              ? 'payee'
              : ((int)($t['est_abandonnee'] ?? 0) === 1 ? 'close' : 'due');
        ?>
        <li class="f-tranche f-tranche-<?= $etatT ?>">
          <span class="f-tranche-n">T<?= (int)$t['numero'] ?> · <?= (int)$t['pourcentage'] ?> %</span>
          <span class="f-tranche-m"><?= e($xaf($t['montant'])) ?> XAF</span>
          <span class="f-tranche-e">
            <?php if ($etatT === 'payee'): ?>
              Réglée<?= !empty($t['date_paiement']) ? ' le ' . date('d/m/y', strtotime((string)$t['date_paiement'])) : '' ?>
            <?php elseif ($etatT === 'close'): ?>
              Close — abandon
            <?php else: ?>
              En attente
            <?php endif; ?>
          </span>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>

      <?php if (!empty($dossier['abandon_le'])): ?>
      <p class="f-aide" style="margin-top:10px">
        <strong>Abandon d'année constaté</strong> le
        <?= e(date('d/m/Y', strtotime((string)$dossier['abandon_le']))) ?> par Connect'Academia.
        <?= !empty($dossier['abandon_motif']) ? e($dossier['abandon_motif']) . ' ' : '' ?>
        Les tranches non réglées sont closes : elles ne vous seront pas réclamées.
      </p>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($historique !== []): ?>
    <!-- Historique : chaque transition laisse une trace (spec §4.2) -->
    <div class="g-card f-bloc">
      <h3>Historique du dossier</h3>
      <ul class="f-histo">
        <?php foreach ($historique as $h): ?>
        <li>
          <div class="f-histo-titre"><?= e(DossierStatut::libelle((string)$h['nouveau_statut'], $estInscription)) ?></div>
          <div class="f-histo-meta">
            <?= e(date('d/m/Y à H\hi', strtotime((string)$h['cree_le']))) ?>
            · <?= e(trim(($h['acteur_prenom'] ?? '') . ' ' . ($h['acteur_nom'] ?? '')) ?: ($h['acteur_role'] ?? 'système')) ?>
          </div>
          <?php if (!empty($h['commentaire'])): ?>
            <div class="f-histo-note"><?= e($h['commentaire']) ?></div>
          <?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <?php if ($scans !== []): ?>
    <div class="g-card f-bloc">
      <h3>Scans du récépissé</h3>
      <ul class="f-histo">
        <?php foreach ($scans as $sc): ?>
        <li>
          <div class="f-histo-titre"><?= e(\Services\Orientation\ScanQr::libelle((string)$sc['resultat'])) ?></div>
          <div class="f-histo-meta">
            <?= e(date('d/m/Y à H\hi', strtotime((string)$sc['scanne_le']))) ?>
            <?php if (!empty($sc['agent_nom'])): ?> · <?= e(trim(($sc['agent_prenom'] ?? '') . ' ' . $sc['agent_nom'])) ?><?php endif; ?>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <div class="g-card" style="margin-bottom:16px;">
      <h3 style="font-size:13px;font-weight:700;margin-bottom:10px;text-transform:uppercase;letter-spacing:.04em;color:var(--ink-3);">Envoyer un message</h3>
      <?php if (!empty($dossier['eleve_id'])): ?>
        <div style="font-size:12px;color:var(--ink-2);margin-bottom:10px;">
          Destinataire : <strong><?= e($dossier['nom_complet'] ?? '') ?></strong> — le message arrive dans les notifications de son compte Connect'Academia, puis <strong>WhatsApp s'ouvre pré-rempli</strong> pour que tu lui envoies aussi la copie en un clic.
        </div>
        <form action="<?= url('/gestionnaire/candidatures/' . $dossier['id'] . '/message') ?>" method="post"
              onsubmit="return confirm('Envoyer ce message au candidat ?')">
          <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
          <textarea name="message" rows="4" maxlength="500" required id="g-msg-message"
                    placeholder="Ex. Bonjour, il manque une pièce à votre dossier…"
                    style="width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:var(--r-md);font-family:var(--font);font-size:13px;resize:vertical;"></textarea>
          <div style="display:flex;align-items:center;justify-content:space-between;margin-top:8px;gap:8px;">
            <span style="font-size:11px;color:var(--ink-3);"><span id="g-msg-count">0</span>/500</span>
            <button type="submit" class="g-btn g-btn-primary g-btn-sm">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
              Envoyer
            </button>
          </div>
        </form>
        <script>
          (function () {
            var t = document.getElementById('g-msg-message'), c = document.getElementById('g-msg-count');
            if (t && c) t.addEventListener('input', function () { c.textContent = t.value.length; });
          })();
        </script>
      <?php else: ?>
        <div style="font-size:12px;color:var(--ink-2);">
          Ce candidat n'a pas de compte Connect'Academia — le message in-app n'est pas disponible.
          Contactez-le directement au
          <strong><?= e(trim(($dossier['indicatif_tel'] ?? '+241') . ' ' . ($dossier['telephone'] ?? ''))) ?></strong>.
        </div>
      <?php endif; ?>
    </div>

    <div class="g-card" style="font-size:12px;color:var(--ink-2);">
      <div style="margin-bottom:8px;"><strong>Dossier #<?= $dossier['id'] ?></strong></div>
      <div>Créé le <?= date('d/m/Y à H\hi', strtotime($dossier['date_creation'])) ?></div>
      <div style="margin-top:6px;">
        <?php if (($dossier['origine'] ?? '') === 'etablissement'): ?>
          Saisi au guichet<?php if (!empty($dossier['cree_par_nom'])): ?> par <strong><?= e($dossier['cree_par_nom']) ?></strong><?php endif; ?>
        <?php else: ?>
          Déposé en ligne par le candidat
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<link rel="stylesheet" href="<?= asset('orientation/dossiers-backoffice.css') ?>">
<style>
@media(max-width:980px) {
  .g-show-grid { grid-template-columns:minmax(0,1fr) !important; }
  .f-decision { position:static !important; }
}
</style>

<script>
/* Validation à 100 % — le bouton n'est plus verrouillé par défaut : les
   montants viennent désormais de la configuration, et le formulaire est
   valide dès son affichage (mode de paiement et date sont préremplis).
   Ne reste qu'un garde-fou de confort sur les champs marqués obligatoires
   dans le cas de repli, où l'agent doit encore saisir le montant. Le serveur
   refuse de toute façon la transition sans données financières. */
(function () {
    var form = document.getElementById('form-100');
    var btn  = document.getElementById('btn-100');
    if (!form || !btn) { return; }

    var champs = form.querySelectorAll('.f-obligatoire');

    function evaluer() {
        var complet = true;
        champs.forEach(function (champ) {
            if (String(champ.value || '').trim() === '') { complet = false; }
        });
        btn.disabled = !complet;
    }

    champs.forEach(function (champ) {
        champ.addEventListener('input', evaluer);
        champ.addEventListener('change', evaluer);
    });
    evaluer();

    // Double soumission : au guichet, le second clic part toujours avant que la
    // page ait eu le temps de se recharger — et il vaudrait une seconde
    // tentative de validation à 100 %.
    form.addEventListener('submit', function () {
        if (btn.disabled) { return; }
        window.setTimeout(function () {
            btn.disabled = true;
            btn.textContent = 'Validation…';
        }, 0);
    });
}());
</script>
