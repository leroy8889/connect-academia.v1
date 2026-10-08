<?php
// Détail d'un dossier — variables : $dossier, $pieces, $recuUrl
require_once __DIR__ . '/_helpers.php';
$d      = $dossier ?? [];
$pieces = $pieces  ?? [];

$estInscription = \Models\Orientation\Dossier::estInscription($d);
$statut  = $d['statut'] ?? 'brouillon';
$estValide = in_array($statut, ['inscrit','accepte'], true); // inscription validée
$refDossier = $d['numero_attestation'] ?? $d['numero_recepisse'] ?? ('DOS-' . str_pad((string)(int)($d['id'] ?? 0), 6, '0', STR_PAD_LEFT));
$genreLabels = ['homme' => 'Masculin', 'femme' => 'Féminin', 'autre' => 'Autre'];
$retour = $estInscription ? '/eleve/orientation/inscriptions' : '/eleve/orientation/preinscriptions';

// Téléchargements (routes on-demand : toujours fiables, indépendantes du B2)
$dossierId       = (int)($d['id'] ?? 0);
$dossierHash     = hid($dossierId);
$peutRecepisse   = !empty($d['numero_recepisse']) || (!$estInscription && !in_array($statut, ['brouillon','refuse'], true));
$peutAttestation = $estValide;

$nbOk = count(array_filter($pieces, fn($p) => $p['ok']));

// ── Refonte inscription (spec §9) ──
// Un dossier portant une référence relève du nouveau parcours : trois paliers,
// message en langage clair, et bloc de mise à jour de la bourse.
$refonte       = !empty($d['reference']);
$paliers       = $paliers       ?? [];
$messageStatut = $messageStatut ?? '';
$tonStatut     = $tonStatut     ?? 'neutre';
$bourseOptions = $bourseOptions ?? [];
$bourseHisto   = $bourseHisto   ?? [];
$bourseActuelle= $bourseActuelle ?? null;

// ── Complément de dossier (spec §9.3) ──
// `depotOuvert` dit si le candidat peut encore agir sur ses pièces ;
// `blocages` dit ce qui empêche le dossier de repartir en analyse.
$depotOuvert = !empty($depotOuvert);
$blocages    = $blocages ?? [];
$piecesUrl   = url('/eleve/orientation/dossier/' . $dossierHash . '/piece');
$csrf        = $csrf ?? \Core\Session::getCsrfToken();

if ($refonte) {
    $refDossier      = (string)$d['reference'];
    $peutRecepisse   = !in_array($statut, ['brouillon', 'annule'], true);
    $peutAttestation = in_array($statut, ['valide_100', 'inscrit', 'accepte'], true);
}
?>

<div class="acct-shead">
  <div>
    <a href="<?= url($retour) ?>" class="acct-shead-kick" style="text-decoration:none;cursor:pointer;">← <?= $estInscription ? 'mes inscriptions' : 'mes préinscriptions' ?></a>
    <h1 class="acct-shead-t"><?= e($d['etab_nom'] ?? 'Dossier') ?></h1>
    <p class="acct-shead-d"><?= e(acct_filiere_spec($d)) ?><?= !empty($d['niveau_entree']) ? ' · ' . e($d['niveau_entree']) : '' ?><?= !empty($d['formation_intitule']) ? ' · ' . e($d['formation_intitule']) : '' ?></p>
  </div>
  <?= acct_statut_badge($d) ?>
</div>

<?php if (!empty($flashError)): ?>
  <p class="acct-suivi acct-suivi-danger" style="margin:0 0 16px"><?= e($flashError) ?></p>
<?php elseif (!empty($flash)): ?>
  <p class="acct-suivi acct-suivi-succes" style="margin:0 0 16px"><?= e($flash) ?></p>
<?php endif; ?>

<div class="acct-dossier gls">
  <div class="acct-dossier-top">
    <?= acct_etab_logo($d) ?>
    <div>
      <div class="acct-dossier-name"><?= $estInscription ? 'Dossier d\'inscription' : 'Préinscription' ?></div>
      <div class="acct-dossier-meta">Déposé le <?= acct_date($d['date_creation'] ?? null) ?> · <?= ($d['origine'] ?? '') === 'etablissement' ? 'au guichet de l\'établissement' : 'en ligne' ?></div>
    </div>
    <div class="acct-dossier-ref">
      <div class="l">Référence</div>
      <div class="v"><?= e($refDossier) ?></div>
    </div>
  </div>

  <?php if ($refonte): ?>
    <!-- Trois paliers : Déposé — Validé à 50 % — Inscrit (spec §9.1).
         Quatre étapes auraient laissé croire que l'attestation est une étape
         du parcours ; c'est une conséquence de la troisième. -->
    <div class="acct-stepper s3">
      <?php foreach ($paliers as $i => $palier):
        $classe = $palier['atteint'] ? ($palier['courant'] ? 'active' : 'done') : 'pending';
        if (in_array($statut, ['rejete', 'refuse', 'expire'], true) && !$palier['atteint']) { $classe = 'err'; }
      ?>
      <div class="acct-step <?= $classe ?>">
        <?php if ($i < count($paliers) - 1): ?><div class="line"></div><?php endif; ?>
        <div class="acct-step-dot"><?= $palier['atteint'] && !$palier['courant'] ? acct_icon('check', 13) : (string)($i + 1) ?></div>
        <div class="acct-step-t"><?= e($palier['libelle']) ?></div>
        <div class="acct-step-x">
          <?php if ($palier['cle'] === 'depose'): ?>
            <?= !empty($d['soumis_le']) ? 'Le ' . acct_date($d['soumis_le']) : 'Non déposé' ?>
          <?php elseif ($palier['cle'] === 'valide'): ?>
            <?= !empty($d['valide_50_le']) ? 'Le ' . acct_date($d['valide_50_le']) : 'En attente de l\'établissement' ?>
          <?php else: ?>
            <?= !empty($d['valide_100_le']) ? 'Le ' . acct_date($d['valide_100_le']) : 'Après passage sur place' ?>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Message en langage clair, jamais le nom technique de l'état (spec §9.2) -->
    <div class="acct-suivi acct-suivi-<?= e($tonStatut) ?>">
      <p><?= e($messageStatut) ?></p>
      <?php if ($statut === 'valide_50' && !empty($d['expire_le'])): ?>
        <?php // Le message parle de « cette date » : sans ce rappel, elle n'a pas d'antécédent.
              // La date reste utile — la tâche planifiée fait réellement expirer le dossier après. ?>
        <p class="acct-suivi-sub">Soit avant le <strong><?= acct_date($d['expire_le']) ?></strong>.</p>
      <?php elseif ($statut === 'piece_manquante'): ?>
        <?php if (!empty($d['pieces_attendues'])): ?>
          <p class="acct-suivi-sub">Pièces demandées : <strong><?= e($d['pieces_attendues']) ?></strong></p>
        <?php endif; ?>
        <?php // Le message dit quoi faire ; ce lien mène à l'endroit où le faire.
              // Sans lui, le candidat lit « téléversez la pièce » et cherche où. ?>
        <p class="acct-suivi-sub"><a href="#pieces" class="acct-suivi-lien">Déposer la pièce demandée ↓</a></p>
      <?php elseif (!empty($blocages)): ?>
        <p class="acct-suivi-sub"><a href="#pieces" class="acct-suivi-lien">Corriger mes pièces ↓</a></p>
      <?php endif; ?>
    </div>
  <?php elseif ($estInscription): ?>
    <div class="acct-stepper">
      <div class="acct-step done">
        <div class="line"></div><div class="acct-step-dot"><?= acct_icon('check', 13) ?></div>
        <div class="acct-step-t">Informations</div>
        <div class="acct-step-x">Enregistrées le <?= acct_date($d['date_creation'] ?? null) ?></div>
      </div>
      <div class="acct-step <?= $statut === 'brouillon' ? 'active' : 'done' ?>">
        <div class="line"></div>
        <div class="acct-step-dot"><?= $statut === 'brouillon' ? '2' : acct_icon('check', 13) ?></div>
        <div class="acct-step-t">Dossier &amp; pièces</div>
        <div class="acct-step-x"><?= $statut === 'brouillon' ? 'À compléter' : 'Dossier soumis' ?></div>
      </div>
      <div class="acct-step <?= $estValide ? 'done' : ($statut === 'refuse' ? 'err' : ($statut === 'brouillon' ? 'pending' : 'active')) ?>">
        <div class="line"></div>
        <div class="acct-step-dot"><?= $estValide ? acct_icon('check', 13) : '3' ?></div>
        <div class="acct-step-t">Validation</div>
        <div class="acct-step-x"><?= $estValide ? 'Inscription validée' : ($statut === 'refuse' ? 'Dossier refusé' : 'Étude par l\'établissement') ?></div>
      </div>
      <div class="acct-step <?= $estValide && !empty($d['numero_attestation']) ? 'done' : 'pending' ?>">
        <div class="acct-step-dot"><?= $estValide && !empty($d['numero_attestation']) ? acct_icon('check', 13) : '4' ?></div>
        <div class="acct-step-t">Attestation</div>
        <div class="acct-step-x"><?= $estValide && !empty($d['numero_attestation']) ? e($d['numero_attestation']) : 'Délivrée après validation' ?></div>
      </div>
    </div>
  <?php else: ?>
    <div class="acct-stepper s3">
      <div class="acct-step done">
        <div class="line"></div><div class="acct-step-dot"><?= acct_icon('check', 13) ?></div>
        <div class="acct-step-t">Préinscription</div>
        <div class="acct-step-x">Soumise le <?= acct_date($d['date_creation'] ?? null) ?></div>
      </div>
      <div class="acct-step <?= !empty($d['numero_recepisse']) ? 'done' : 'active' ?>">
        <div class="line"></div>
        <div class="acct-step-dot"><?= !empty($d['numero_recepisse']) ? acct_icon('check', 13) : '2' ?></div>
        <div class="acct-step-t">Attestation</div>
        <div class="acct-step-x"><?= !empty($d['numero_recepisse']) ? e($d['numero_recepisse']) : 'En cours de génération' ?></div>
      </div>
      <div class="acct-step <?= $statut === 'accepte' ? 'done' : 'pending' ?>">
        <div class="acct-step-dot"><?= $statut === 'accepte' ? acct_icon('check', 13) : '3' ?></div>
        <div class="acct-step-t">Suite du parcours</div>
        <div class="acct-step-x"><?= $statut === 'accepte' ? 'Préinscription acceptée' : 'Poursuivre vers l\'inscription' ?></div>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php
  // CTA « poursuivre vers l'inscription » pour une préinscription non refusée
  $peutPoursuivre = !$estInscription && !empty($d['etab_slug']) && !in_array($statut, ['refuse'], true);
?>
<?php
  // Reçu de règlement de scolarité : preuve du VERSEMENT effectué au guichet,
  // à ne pas confondre avec l'attestation (qui prouve l'inscription) ni avec
  // $recuUrl (reçu d'un paiement en ligne, parcours hérité).
  $peutRecuScolarite = !empty($peutRecuScolarite);
  $numeroRecu        = $numeroRecu ?? null;
?>
<?php if ($peutRecepisse || $peutAttestation || $recuUrl || $peutRecuScolarite || $peutPoursuivre || ($statut === 'brouillon' && !empty($d['etab_slug']))): ?>
  <div class="acct-card gls">
    <div class="acct-card-title" style="margin-bottom:12px;"><?= acct_icon('download', 16) ?> Documents &amp; démarches</div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <?php if ($statut === 'brouillon' && !empty($d['etab_slug'])): ?>
        <a href="<?= url('/orientation/' . e($d['etab_slug']) . '/inscription') ?>" class="abtn primary"><?= acct_icon('arrowR', 15) ?> Reprendre mon dossier</a>
      <?php endif; ?>
      <?php if ($peutAttestation): ?>
        <a href="<?= url('/eleve/orientation/dossier/' . $dossierHash . '/attestation') ?>" target="_blank" rel="noopener" class="abtn primary"><?= acct_icon('download', 15) ?> Attestation d'inscription<?= !empty($d['numero_attestation']) ? ' · ' . e($d['numero_attestation']) : '' ?></a>
      <?php endif; ?>
      <?php if ($peutRecepisse): ?>
        <a href="<?= url('/eleve/orientation/dossier/' . $dossierHash . '/recepisse') ?>" target="_blank" rel="noopener" class="abtn <?= $peutAttestation ? 'glass' : 'primary' ?>"><?= acct_icon('download', 15) ?> <?= $refonte ? 'Récépissé de dépôt de dossier · ' . e($refDossier) : 'Attestation de préinscription' ?></a>
      <?php endif; ?>
      <?php if ($peutRecuScolarite): ?>
        <a href="<?= url('/eleve/orientation/dossier/' . $dossierHash . '/recu') ?>" target="_blank" rel="noopener" class="abtn glass"><?= acct_icon('receipt', 15) ?> Reçu de paiement<?= $numeroRecu !== null ? ' · ' . e($numeroRecu) : '' ?></a>
      <?php endif; ?>
      <?php if ($recuUrl): ?>
        <a href="<?= e($recuUrl) ?>" target="_blank" rel="noopener" class="abtn glass"><?= acct_icon('receipt', 15) ?> Reçu de paiement en ligne<?= !empty($d['numero_recu']) ? ' · ' . e($d['numero_recu']) : '' ?></a>
      <?php endif; ?>
      <?php if ($peutPoursuivre): ?>
        <a href="<?= url('/orientation/' . e($d['etab_slug']) . '/inscription') ?>" class="abtn ghost"><?= acct_icon('arrowR', 15) ?> Poursuivre vers l'inscription</a>
      <?php endif; ?>
    </div>
    <div class="acct-footnote" style="margin-top:12px;"><?= acct_icon('clock', 12) ?> Documents officiels aux couleurs de l'établissement — ouvrez-les puis imprimez ou enregistrez en PDF (Ctrl+P).</div>
  </div>
<?php endif; ?>

<?php if (!empty($d['paiement_montant'])): ?>
  <div class="acct-card gls">
    <div class="acct-card-title" style="margin-bottom:4px;"><?= acct_icon('card', 16) ?> Paiement</div>
    <div class="acct-kv-row" style="grid-template-columns:repeat(3,1fr);">
      <div class="acct-kv"><div class="l">Montant</div><div class="v"><?= acct_montant($d['paiement_montant']) ?></div></div>
      <div class="acct-kv"><div class="l">Statut</div><div class="v" style="color:var(--ok-fg);">Confirmé</div></div>
      <div class="acct-kv"><div class="l">Reçu</div><div class="v"><?= e($d['numero_recu'] ?? 'En cours de génération') ?></div></div>
    </div>
  </div>
<?php endif; ?>

<?php if (!empty($pieces)): ?>
  <div class="acct-card gls" id="pieces">
    <div class="acct-card-title" style="margin-bottom:4px;"><?= acct_icon('folder', 16) ?> Pièces justificatives</div>
    <div class="acct-card-sub" style="margin-bottom:8px;"><?= $nbOk ?> sur <?= count($pieces) ?> validée<?= $nbOk > 1 ? 's' : '' ?> ou en cours de vérification</div>

    <?php /* ══ Ce qui bloque encore le dossier (spec §9.3) ══
             Affiché AVANT la liste : le candidat doit savoir ce qu'on attend de
             lui avant de faire défiler dix lignes. */ ?>
    <?php if ($blocages !== []): ?>
      <div class="acct-suivi acct-suivi-attente" style="margin:6px 0 14px">
        <p><strong>À corriger pour poursuivre :</strong></p>
        <p class="acct-suivi-sub"><?= e(implode(' · ', $blocages)) ?></p>
        <p class="acct-suivi-sub">Dès que tout est déposé, votre dossier repart automatiquement en analyse — vous n'avez rien d'autre à faire.</p>
      </div>
    <?php endif; ?>

    <?php foreach ($pieces as $i => $p): ?>
      <?php
        $doc     = $p['doc'] ?? null;
        $etat    = $p['etat'] ?? null;
        $refusee = $etat === 'refuse';
        $motif   = trim((string)($p['motif_refus'] ?? ''));
        // Identifiant du champ : unique par ligne, pour que le <label> du
        // sélecteur de fichier vise bien SON input et pas celui du voisin.
        $champId = 'piece-fichier-' . $i;
      ?>
      <div class="acct-doc-row<?= $refusee ? ' acct-doc-row-err' : '' ?>">
        <div class="acct-doc-ic <?= $refusee ? 'err' : ($p['ok'] ? 'ok' : 'miss') ?>">
          <?= acct_icon($refusee ? 'alert' : ($p['ok'] ? 'check' : 'upload'), 17) ?>
        </div>
        <div style="min-width:0">
          <div class="acct-doc-name"><?= e($p['libelle']) ?></div>
          <div class="acct-doc-sub">
            <?php if ($refusee): ?>
              Refusée par l'établissement<?= $motif !== '' ? ' — ' . e($motif) : '' ?>
            <?php elseif ($etat === 'accepte' && $doc): ?>
              Acceptée<?= !empty($doc['verifie_le']) ? ' le ' . acct_date($doc['verifie_le']) : '' ?>
            <?php elseif ($doc): ?>
              Déposée le <?= acct_date($doc['date_depot'] ?? null) ?><?= !empty($doc['taille_octets']) ? ' · ' . acct_taille((int)$doc['taille_octets']) : '' ?> · en cours de vérification
            <?php elseif ($p['obligatoire']): ?>
              Manquante — requise pour finaliser l'inscription
            <?php else: ?>
              Optionnelle — non déposée
            <?php endif; ?>
          </div>
        </div>
        <div class="acct-doc-actions">
          <?php if (!empty($doc['url_doc'])): ?>
            <a href="<?= e($doc['url_doc']) ?>" target="_blank" rel="noopener" class="abtn ghost sm"><?= acct_icon('download', 13) ?> Voir</a>
          <?php endif; ?>
          <?php if ($etat === 'accepte'): ?>
            <span class="badge2 ok"><span class="d"></span> Validée</span>
          <?php elseif ($refusee): ?>
            <span class="badge2 err"><span class="d"></span> Refusée</span>
          <?php elseif ($doc): ?>
            <span class="badge2 info"><span class="d"></span> En vérification</span>
          <?php elseif (!$p['deposable']): ?>
            <span class="badge2 <?= $p['obligatoire'] ? 'warn' : 'draft' ?>"><?= $p['obligatoire'] ? 'À déposer' : 'Optionnelle' ?></span>
          <?php endif; ?>
        </div>

        <?php /* ══ Dépôt / remplacement ══
                 Le formulaire vit DANS la ligne de la pièce : un formulaire
                 unique en bas de page obligerait à re-choisir la pièce dans une
                 liste déroulante, et c'est exactement là que les candidats se
                 trompent de ligne. */ ?>
        <?php if (!empty($p['deposable'])): ?>
        <form method="post" action="<?= e($piecesUrl) ?>" enctype="multipart/form-data" class="acct-piece-form">
          <input type="hidden" name="_csrf_token" value="<?= e($csrf) ?>">
          <?php if (!empty($p['piece_id'])): ?>
            <input type="hidden" name="piece_requise_id" value="<?= (int)$p['piece_id'] ?>">
          <?php endif; ?>
          <?php if (!empty($doc['id'])): ?>
            <input type="hidden" name="document_id" value="<?= (int)$doc['id'] ?>">
          <?php endif; ?>

          <input type="file" id="<?= e($champId) ?>" name="fichier" required
                 accept="<?= e(\Services\Orientation\PieceDossier::acceptHtml()) ?>" class="acct-piece-file">
          <label for="<?= e($champId) ?>" class="abtn glass sm acct-piece-pick">
            <?= acct_icon('upload', 13) ?> <?= $doc ? 'Choisir un nouveau fichier' : 'Choisir un fichier' ?>
          </label>
          <span class="acct-piece-nom" aria-live="polite"></span>
          <button type="submit" class="abtn primary sm acct-piece-envoi" disabled>
            <?= $doc ? 'Remplacer' : 'Déposer' ?>
          </button>
          <span class="acct-piece-aide"><?= e(\Services\Orientation\PieceDossier::consigneDepot()) ?>.</span>
        </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <?php /* ══ Renvoi explicite ══
             Le cas nominal est automatique (dernier dépôt = renvoi). Ce bouton
             couvre les demandes formulées en texte libre, sans pièce du
             référentiel en face — sinon ces dossiers-là resteraient bloqués. */ ?>
    <?php if ($statut === 'piece_manquante'): ?>
      <form method="post" action="<?= url('/eleve/orientation/dossier/' . $dossierHash . '/renvoyer') ?>"
            style="margin-top:16px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
        <input type="hidden" name="_csrf_token" value="<?= e($csrf) ?>">
        <button type="submit" class="abtn primary<?= $blocages !== [] ? ' disabled' : '' ?>"
                <?= $blocages !== [] ? 'disabled' : '' ?>>
          <?= acct_icon('check', 14) ?> Renvoyer mon dossier
        </button>
        <span class="acct-piece-aide">
          <?= $blocages !== []
              ? 'Disponible dès que les pièces ci-dessus sont déposées.'
              : 'Votre dossier repartira en analyse auprès de l\'établissement.' ?>
        </span>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="acct-card gls">
  <div class="acct-card-title" style="margin-bottom:4px;"><?= acct_icon('idcard', 16) ?> Identité du candidat</div>
  <div class="acct-profile-grid" style="margin-top:6px;">
    <div class="acct-field"><span class="l">Nom complet</span><span class="v"><?= e($d['nom_complet'] ?? '—') ?></span></div>
    <div class="acct-field"><span class="l">Genre</span><span class="v"><?= e($genreLabels[$d['genre'] ?? ''] ?? '—') ?></span></div>
    <div class="acct-field"><span class="l">Date de naissance</span><span class="v"><?= acct_date($d['date_naissance'] ?? null) ?></span></div>
    <div class="acct-field"><span class="l">Lieu de naissance</span><span class="v"><?= e($d['lieu_naissance'] ?? '—') ?></span></div>
    <div class="acct-field"><span class="l">Nationalité</span><span class="v"><?= e($d['nationalite'] ?? '—') ?></span></div>
    <div class="acct-field"><span class="l">Dernier établissement</span><span class="v"><?= e($d['dernier_etablissement'] ?? '—') ?></span></div>
    <div class="acct-field"><span class="l">Email</span><span class="v"><?= e($d['email'] ?? '—') ?></span></div>
    <div class="acct-field"><span class="l">Téléphone</span><span class="v"><?= e(trim(($d['indicatif_tel'] ?? '') . ' ' . ($d['telephone'] ?? '')) ?: '—') ?></span></div>
    <div class="acct-field"><span class="l">Pays de provenance</span><span class="v"><?= e($d['pays_provenance'] ?? '—') ?></span></div>
    <div class="acct-field"><span class="l">Ville · Adresse</span><span class="v"><?= e(trim(($d['ville'] ?? '') . (empty($d['adresse']) ? '' : ' · ' . $d['adresse'])) ?: '—') ?></span></div>
  </div>
</div>

<?php
// Établissement fermé aux boursiers : le bloc disparaît de l'espace candidat.
// Le dossier garde la situation déjà déclarée (elle reste visible du guichet et
// des documents officiels) ; c'est la possibilité d'en poser une NOUVELLE qui
// est retirée — et le serveur la refuse aussi (updateBourse).
$bourseActive = $bourseActive ?? true;
?>
<?php if ($refonte && $bourseActive): ?>
<!-- ══ Situation de bourse (spec §9.4) ══
     Deux réponses seulement : boursier, ou non. Modifiable À TOUT MOMENT, y
     compris après l'inscription définitive — c'est ici qu'un candidat dont la
     demande ANBG finit par aboutir se déclare boursier, des mois plus tard.
     Le changement ne touche pas le statut du dossier. -->
<div class="acct-card gls" id="bourse">
  <div class="acct-card-title" style="margin-bottom:4px;"><?= acct_icon('idcard', 16) ?> Ma situation de bourse</div>

  <p class="acct-bourse-note"><?= e($bourseNote ?? '') ?></p>

  <form method="post" action="<?= url('/eleve/orientation/dossier/' . $dossierHash . '/bourse') ?>"
        enctype="multipart/form-data" class="acct-bourse-form">
    <input type="hidden" name="_csrf_token" value="<?= e($csrf ?? '') ?>">

    <div class="acct-bourse-options">
      <?php foreach ($bourseOptions as $opt): ?>
      <label class="acct-bourse-opt">
        <input type="radio" name="statut_bourse" value="<?= e($opt['valeur']) ?>"
               data-bourse-choix="<?= e($opt['valeur']) ?>"
               <?= $bourseActuelle === $opt['valeur'] ? 'checked' : '' ?> required>
        <span class="acct-bourse-body">
          <span class="acct-bourse-radio" aria-hidden="true"></span>
          <span>
            <strong><?= e($opt['libelle']) ?></strong>
            <em><?= e($opt['aide']) ?></em>
          </span>
        </span>
      </label>
      <?php endforeach; ?>
    </div>

    <div class="acct-bourse-cond" data-bourse-pour="boursier" <?= $bourseActuelle === 'boursier' ? '' : 'hidden' ?>>
      <label for="numero_attestation_anbg">Numéro d'attestation ANBG <span class="opt">(facultatif)</span></label>
      <input type="text" id="numero_attestation_anbg" name="numero_attestation_anbg" maxlength="50"
             value="<?= e($d['numero_attestation_anbg'] ?? '') ?>" placeholder="ANBG/2026/014782">

      <label for="justificatif">Attestation de bourse ANBG</label>
      <input type="file" id="justificatif" name="justificatif" accept="<?= e(\Services\Orientation\PieceDossier::acceptHtml()) ?>">
      <p class="acct-bourse-aide"><?= e(\Services\Orientation\PieceDossier::consigneDepot()) ?>. Inutile si vous l'avez déjà déposée avec votre dossier.</p>
    </div>

    <button type="submit" class="abtn glass">Mettre à jour ma situation</button>
    <p class="acct-bourse-aide">
      Cette mise à jour ne change ni l'avancement de votre dossier, ni les frais dus.
    </p>
  </form>

  <?php if (count($bourseHisto) > 1): ?>
  <div class="acct-bourse-histo">
    <div class="acct-bourse-histo-t">Historique de vos déclarations</div>
    <ul>
      <?php foreach (array_reverse($bourseHisto) as $h): ?>
        <li>
          <strong><?= e($h['nouveau_libelle']) ?></strong>
          <?php if (!empty($h['ancien_statut'])): ?>
            <span>(auparavant <?= e($h['ancien_libelle']) ?>)</span>
          <?php endif; ?>
          — <?= acct_date($h['modifie_le'], true) ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>
</div>

<script>
/* Champs conditionnels du bloc bourse — confort de saisie uniquement.
   La validation qui compte est celle du serveur (SituationBourse::valider). */
(function () {
    var choix = document.querySelectorAll('[data-bourse-choix]');
    var blocs = document.querySelectorAll('[data-bourse-pour]');
    if (!choix.length) { return; }

    function appliquer(valeur) {
        blocs.forEach(function (bloc) {
            var concerne = bloc.getAttribute('data-bourse-pour') === valeur;
            bloc.hidden  = !concerne;
            // Un champ fichier masqué doit être neutralisé : sinon deux entrées
            // « justificatif » partiraient dans la même requête.
            bloc.querySelectorAll('input').forEach(function (champ) {
                champ.disabled = !concerne;
            });
        });
    }

    choix.forEach(function (radio) {
        radio.addEventListener('change', function () { appliquer(this.value); });
    });

    var coche = document.querySelector('[data-bourse-choix]:checked');
    appliquer(coche ? coche.value : null);
}());
</script>
<?php endif; ?>

<script>
/* Dépôt de pièce — confort de saisie uniquement.
   Le bouton reste désactivé tant qu'aucun fichier n'est choisi : sans cela, un
   envoi vide part au serveur, revient en erreur, et le candidat croit que le
   dépôt a échoué. La barrière qui compte reste `required` + la validation
   serveur (PieceDossier : type réel, taille, liste blanche). */
(function () {
    document.querySelectorAll('.acct-piece-form').forEach(function (form) {
        var champ  = form.querySelector('input[type="file"]');
        var nom    = form.querySelector('.acct-piece-nom');
        var envoi  = form.querySelector('.acct-piece-envoi');
        if (!champ || !envoi) { return; }

        champ.addEventListener('change', function () {
            var fichier = champ.files && champ.files[0] ? champ.files[0] : null;
            if (nom) { nom.textContent = fichier ? fichier.name : ''; }
            envoi.disabled = !fichier;
        });

        // Double envoi : au guichet comme chez soi, le second clic arrive
        // toujours avant la fin du téléversement.
        form.addEventListener('submit', function () {
            envoi.disabled = true;
            envoi.textContent = 'Envoi…';
        });
    });
}());
</script>

<link rel="stylesheet" href="<?= asset('orientation/espace-suivi.css') ?>">
