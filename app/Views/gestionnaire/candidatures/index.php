<?php
/**
 * Liste des dossiers — vue unique filtrable (spec §8.1).
 *
 * Colonnes imposées : référence, candidat, filière, situation de bourse,
 * statut, date de dépôt, ancienneté. Les compteurs du haut agissent comme
 * raccourcis de filtre, et un dossier non traité depuis plus de 7 jours est
 * signalé visuellement — c'est ce qui empêche un dossier de dormir.
 */
use Models\Orientation\Dossier;
use Models\Orientation\DossierStatut;
use Services\Orientation\SituationBourse;

$dossiers      = $dossiers      ?? [];
$compteurs     = $compteurs     ?? [];
$compteursType = $compteursType ?? [];
$filtres       = $filtres       ?? [];
$filieres      = $filieres      ?? [];

/** Au-delà, la ligne est signalée (spec §8.1). */
$SEUIL_ANCIENNETE = 7;

// ── Onglet courant : préinscriptions, inscriptions, ou les deux ─────────────
// Ce sont deux parcours distincts. Une préinscription n'a ni pièce, ni
// scolarité, ni validation à 50 % ; les mêler dans une seule liste conduit à
// leur appliquer les mêmes gestes.
$typeCourant = (string)($filtres['type'] ?? '');
$ongletEstInscription = $typeCourant !== Dossier::TYPE_PREINSCRIPTION;

$titrePage = match ($typeCourant) {
    Dossier::TYPE_PREINSCRIPTION => 'Préinscriptions',
    Dossier::TYPE_INSCRIPTION    => 'Dossiers d\'inscription',
    default                      => 'Dossiers reçus',
};

/**
 * Statuts affichés en compteur, dans l'ordre du parcours.
 *
 * Une préinscription est REÇUE, et c'est son seul état : elle ne se valide ni
 * ne se refuse. Les deux autres compteurs ne s'affichent que s'ils comptent
 * réellement quelque chose — des lignes héritées d'un ancien parcours où
 * l'établissement se prononçait. Les montrer à zéro laisserait croire à une
 * décision attendue.
 */
$compteursAffiches = $typeCourant === Dossier::TYPE_PREINSCRIPTION
    ? array_values(array_filter(
        [DossierStatut::SOUMIS, DossierStatut::VALIDE_50, DossierStatut::REJETE],
        static fn(string $st): bool => $st === DossierStatut::SOUMIS || (int)($compteurs[$st] ?? 0) > 0
    ))
    : [
        DossierStatut::SOUMIS,
        DossierStatut::PIECE_MANQUANTE,
        DossierStatut::VALIDE_50,
        DossierStatut::VALIDE_100,
        DossierStatut::REJETE,
        DossierStatut::EXPIRE,
    ];

/** Onglets de nature : libellé, valeur du filtre, effectif. */
$onglets = [
    ['cle' => '',                            'libelle' => 'Tous',            'n' => array_sum($compteursType)],
    ['cle' => Dossier::TYPE_PREINSCRIPTION,  'libelle' => 'Préinscriptions', 'n' => (int)($compteursType[Dossier::TYPE_PREINSCRIPTION] ?? 0)],
    ['cle' => Dossier::TYPE_INSCRIPTION,     'libelle' => 'Inscriptions',    'n' => (int)($compteursType[Dossier::TYPE_INSCRIPTION] ?? 0)],
];

/** Conserve les filtres courants dans un lien, en n'écrasant qu'une clé. */
$lienFiltre = static function (array $filtres, string $cle, string $valeur): string {
    $filtres[$cle] = $valeur;

    // Changer d'onglet remet le statut à zéro : « Validé à 50 % » et
    // « Acceptée » ne désignent pas la même chose d'un parcours à l'autre, et
    // un filtre de statut hérité de l'onglet précédent ramènerait une liste
    // vide sans que rien ne l'explique.
    if ($cle === 'type') {
        $filtres['statut'] = '';
    }

    $filtres = array_filter($filtres, static fn($v): bool => (string)$v !== '');

    return url('/gestionnaire/candidatures') . ($filtres === [] ? '' : '?' . http_build_query($filtres));
};

$aDesFiltres = array_filter($filtres, static fn($v): bool => (string)$v !== '') !== [];
?>

<div class="g-page-header">
  <div>
    <h2><?= e($titrePage) ?></h2>
    <p><?= (int)($total ?? count($dossiers)) ?> dossier<?= (int)($total ?? 0) > 1 ? 's' : '' ?><?= $aDesFiltres ? ' (filtrés)' : '' ?></p>
  </div>
  <div style="display:flex;gap:10px;flex-wrap:wrap;">
    <a href="<?= url('/gestionnaire/scanner') ?>" class="g-btn g-btn-outline">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3z"/></svg>
      Scanner un récépissé
    </a>
    <a href="<?= url('/gestionnaire/candidatures/creer?type=inscription') ?>" class="g-btn g-btn-primary">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 4v16m8-8H4"/></svg>
      Dossier au guichet
    </a>
  </div>
</div>

<!-- Onglets de nature — préinscription et inscription ne se traitent pas de la
     même façon, donc ne se lisent pas dans la même liste. -->
<div class="d-onglets" role="tablist">
  <?php foreach ($onglets as $onglet): ?>
    <a href="<?= e($lienFiltre($filtres, 'type', $onglet['cle'])) ?>"
       class="d-onglet<?= $typeCourant === $onglet['cle'] ? ' d-onglet-actif' : '' ?>"
       role="tab" aria-selected="<?= $typeCourant === $onglet['cle'] ? 'true' : 'false' ?>">
      <?= e($onglet['libelle']) ?>
      <span class="d-onglet-n"><?= (int)$onglet['n'] ?></span>
    </a>
  <?php endforeach; ?>
</div>

<!-- Compteurs cliquables : raccourcis de filtre (spec §8.1) -->
<div class="d-compteurs">
  <a href="<?= e($lienFiltre($filtres, 'statut', '')) ?>"
     class="d-compteur<?= ($filtres['statut'] ?? '') === '' ? ' d-actif' : '' ?>">
    <span class="d-compteur-n"><?= array_sum($compteurs) ?></span>
    <span class="d-compteur-l">Tous statuts</span>
  </a>
  <?php foreach ($compteursAffiches as $st): ?>
    <a href="<?= e($lienFiltre($filtres, 'statut', $st)) ?>"
       class="d-compteur d-ton-<?= e(DossierStatut::ton($st)) ?><?= ($filtres['statut'] ?? '') === $st ? ' d-actif' : '' ?>">
      <span class="d-compteur-n"><?= (int)($compteurs[$st] ?? 0) ?></span>
      <span class="d-compteur-l"><?= e(DossierStatut::libelle($st, $ongletEstInscription)) ?></span>
    </a>
  <?php endforeach; ?>
</div>

<!-- Filtres — persistants en session -->
<div class="g-card d-filtres">
  <form method="get" action="<?= url('/gestionnaire/candidatures') ?>">
    <input type="text" name="q" value="<?= e($filtres['q'] ?? '') ?>"
           placeholder="Nom, référence ou e-mail…" class="d-champ d-champ-large">

    <select name="filiere" class="d-champ">
      <option value="">Toutes les filières</option>
      <?php foreach ($filieres as $f): ?>
        <option value="<?= e($f) ?>" <?= ($filtres['filiere'] ?? '') === $f ? 'selected' : '' ?>><?= e($f) ?></option>
      <?php endforeach; ?>
    </select>

    <select name="statut_bourse" class="d-champ">
      <option value="">Toutes situations de bourse</option>
      <?php foreach (SituationBourse::valeurs() as $b): ?>
        <option value="<?= e($b) ?>" <?= ($filtres['statut_bourse'] ?? '') === $b ? 'selected' : '' ?>>
          <?= e(SituationBourse::libelleCourt($b)) ?>
        </option>
      <?php endforeach; ?>
      <option value="non_renseigne" <?= ($filtres['statut_bourse'] ?? '') === 'non_renseigne' ? 'selected' : '' ?>>Non renseignée</option>
    </select>

    <label class="d-periode">
      Du <input type="date" name="du" value="<?= e($filtres['du'] ?? '') ?>" class="d-champ">
      au <input type="date" name="au" value="<?= e($filtres['au'] ?? '') ?>" class="d-champ">
    </label>

    <select name="tri" class="d-champ">
      <option value="">Plus récents d'abord</option>
      <option value="anciennete" <?= ($filtres['tri'] ?? '') === 'anciennete' ? 'selected' : '' ?>>Les plus anciens d'abord</option>
      <option value="nom"        <?= ($filtres['tri'] ?? '') === 'nom' ? 'selected' : '' ?>>Nom du candidat</option>
      <option value="reference"  <?= ($filtres['tri'] ?? '') === 'reference' ? 'selected' : '' ?>>Référence</option>
    </select>

    <?php if (($filtres['statut'] ?? '') !== ''): ?>
      <input type="hidden" name="statut" value="<?= e($filtres['statut']) ?>">
    <?php endif; ?>
    <?php if ($typeCourant !== ''): ?>
      <input type="hidden" name="type" value="<?= e($typeCourant) ?>">
    <?php endif; ?>

    <button type="submit" class="g-btn g-btn-primary g-btn-sm">Filtrer</button>
    <?php if ($aDesFiltres): ?>
      <a href="<?= url('/gestionnaire/candidatures?reset=1') ?>" class="g-btn g-btn-ghost g-btn-sm">Réinitialiser</a>
    <?php endif; ?>
  </form>
</div>

<div class="g-card" style="padding:0;overflow:hidden;">
<?php if ($dossiers === []): ?>
  <div class="g-empty">
    <svg width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
    <p><?= $aDesFiltres
        ? 'Aucun dossier ne correspond à ces filtres.'
        : ($typeCourant === Dossier::TYPE_PREINSCRIPTION
            ? 'Aucune préinscription reçue pour le moment.'
            : ($typeCourant === Dossier::TYPE_INSCRIPTION
                ? 'Aucun dossier d\'inscription reçu pour le moment.'
                : 'Aucun dossier reçu pour le moment.')) ?></p>
  </div>
<?php else: ?>
  <div style="overflow-x:auto">
  <table class="g-table d-table">
    <thead>
      <tr>
        <th>Référence</th>
        <th>Candidat</th>
        <th>Filière</th>
        <th>Bourse</th>
        <th>Statut</th>
        <th>Déposé le</th>
        <th>Ancienneté</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($dossiers as $d):
      $statut     = DossierStatut::normaliser((string)($d['statut'] ?? ''));
      $anciennete = (int)($d['anciennete'] ?? 0);

      // Nature de CETTE ligne — la liste « Tous » en mêle deux, et le même
      // état s'y lit différemment : « Validé à 50 % » pour une inscription,
      // « Acceptée » pour une préinscription.
      $ligneInscription = Dossier::estInscription($d);

      // Un dossier « qui dort » : déposé, pas encore décidé, et au-delà du
      // seuil. Un dossier déjà tranché n'a plus d'ancienneté qui compte.
      $enRetard = $anciennete > $SEUIL_ANCIENNETE
          && in_array($statut, [DossierStatut::SOUMIS, DossierStatut::PIECE_MANQUANTE], true);
    ?>
      <tr class="<?= $enRetard ? 'd-retard' : '' ?>">
        <td>
          <?php if (!empty($d['reference'])): ?>
            <span class="d-ref"><?= e($d['reference']) ?></span>
          <?php elseif (!$ligneInscription && !empty($d['numero_recepisse'])): ?>
            <?php // Une préinscription n'a pas de référence de dossier : son
                  // numéro d'attestation est le seul identifiant que le
                  // candidat ait en main. ?>
            <span class="d-ref"><?= e($d['numero_recepisse']) ?></span>
          <?php else: ?>
            <span class="d-ref d-ref-vide">—</span>
          <?php endif; ?>
          <?php if ($typeCourant === ''): ?>
            <div class="d-nature d-nature-<?= $ligneInscription ? 'insc' : 'pre' ?>">
              <?= $ligneInscription ? 'Inscription' : 'Préinscription' ?>
            </div>
          <?php endif; ?>
        </td>
        <td>
          <div class="d-nom"><?= e($d['nom_complet'] ?? '—') ?></div>
          <div class="d-mail"><?= e($d['email'] ?? '') ?></div>
        </td>
        <td class="d-cell-sec">
          <?= e($d['niveau_entree'] ?? $d['filiere_visee'] ?? '—') ?>
          <?php if (!empty($d['specialite_visee'])): ?>
            <div class="d-mail"><?= e($d['specialite_visee']) ?></div>
          <?php endif; ?>
        </td>
        <td class="d-cell-sec">
          <?php if (!empty($d['statut_bourse'])): ?>
            <span class="d-bourse d-bourse-<?= e($d['statut_bourse']) ?>">
              <?= e(SituationBourse::libelleCourt($d['statut_bourse'])) ?>
            </span>
          <?php else: ?>
            <span class="d-mail">Non renseignée</span>
          <?php endif; ?>
        </td>
        <td>
          <span class="g-badge d-badge-<?= e(DossierStatut::ton($statut)) ?>">
            <?= e(DossierStatut::libelle($statut, $ligneInscription)) ?>
          </span>
        </td>
        <td class="d-cell-date">
          <?= e(!empty($d['soumis_le'])
              ? date('d/m/Y', strtotime((string)$d['soumis_le']))
              : date('d/m/Y', strtotime((string)$d['date_creation']))) ?>
        </td>
        <td class="d-cell-date">
          <?php if ($enRetard): ?>
            <span class="d-alerte" title="Sans traitement depuis plus de <?= $SEUIL_ANCIENNETE ?> jours">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 9v4M12 17h.01"/><circle cx="12" cy="12" r="10"/></svg>
              <?= $anciennete ?> j
            </span>
          <?php else: ?>
            <?= $anciennete ?> j
          <?php endif; ?>
        </td>
        <td>
          <a href="<?= url('/gestionnaire/candidatures/' . (int)$d['id']) ?>" class="g-btn g-btn-ghost g-btn-sm">Ouvrir</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>

  <?php if (($pages ?? 1) > 1): ?>
  <nav class="d-pagination">
    <?php for ($p = 1; $p <= $pages; $p++): ?>
      <?php $q = array_filter($filtres, static fn($v): bool => (string)$v !== ''); $q['page'] = $p; ?>
      <a href="<?= e(url('/gestionnaire/candidatures') . '?' . http_build_query($q)) ?>"
         class="<?= $p === (int)$page ? 'd-page-actif' : '' ?>"><?= $p ?></a>
    <?php endfor; ?>
  </nav>
  <?php endif; ?>
<?php endif; ?>
</div>

<link rel="stylesheet" href="<?= asset('orientation/dossiers-backoffice.css') ?>">
