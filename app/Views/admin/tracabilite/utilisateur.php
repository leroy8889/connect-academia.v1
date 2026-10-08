<?php
/**
 * Fiche utilisateur enrichie (spec §11.1).
 *
 * Répond à une seule question, posée mille fois : « qu'est-ce que cette
 * personne a fait sur la plateforme, et quand ? » D'où l'ordre des blocs —
 * activité, préinscriptions, dossiers, puis la frise qui rejoue tout.
 */
use Models\Orientation\DossierStatut;
use Services\Orientation\Journal;

$u   = $utilisateur;
$act = $activite;

// Vocabulaire du parcours candidat. À 100 % la personne n'est plus un élève ni
// un candidat : c'est un ÉTUDIANT de l'établissement — c'est ce mot que le
// super administrateur doit lire, quel que soit le rôle technique du compte.
$parcoursLibelle = ['visiteur' => 'Visiteur', 'candidat' => 'Candidat', 'inscrit' => 'Étudiant'];

$roleLibelle = ['eleve' => 'Élève', 'visiteur' => 'Visiteur', 'gestionnaire' => 'Gestionnaire', 'admin' => 'Administrateur'];
$estEtudiant = ($act['statut_parcours'] ?? '') === 'inscrit';
$dt = static fn(?string $v, bool $seconde = false): string =>
    $v ? date($seconde ? 'd/m/Y à H:i:s' : 'd/m/Y à H\hi', strtotime($v)) : '—';
?>

<div class="tr-head">
  <div>
    <a href="<?= url('/admin/utilisateurs') ?>" class="tr-back">← Tous les utilisateurs</a>
    <h1><?= e(trim(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? '')) ?: 'Utilisateur #' . (int)$u['id']) ?></h1>
    <p><?= e($u['email'] ?? '') ?><?= !empty($u['telephone']) ? ' · ' . e($u['telephone']) : '' ?></p>
  </div>
  <span class="tr-parcours tr-parcours-<?= e($act['statut_parcours']) ?>">
    <?= e($parcoursLibelle[$act['statut_parcours']] ?? $act['statut_parcours']) ?>
  </span>
</div>

<?php if (!empty($flash)): ?><div class="tr-flash ok"><?= e($flash) ?></div><?php endif; ?>
<?php if (!empty($flashError)): ?><div class="tr-flash err"><?= e($flashError) ?></div><?php endif; ?>

<!-- Bloc « Activité sur la plateforme » -->
<section class="tr-bloc">
  <h2>Activité sur la plateforme</h2>
  <dl class="tr-kpis">
    <div><dt>Compte créé le</dt><dd><?= $dt($u['created_at'] ?? null) ?><br><span><?= e($act['canal'] ?? '') ?></span></dd></div>
    <div><dt>Dernière connexion</dt><dd><?= $dt($act['derniere_connexion']) ?></dd></div>
    <div><dt>Préinscriptions</dt><dd><?= (int)$act['nb_preinscriptions'] ?></dd></div>
    <div><dt>Dossiers d'inscription</dt><dd><?= (int)$act['nb_dossiers'] ?></dd></div>
    <div>
      <dt>Statut</dt>
      <dd>
        <?= $estEtudiant ? 'Étudiant' : e($roleLibelle[$u['role'] ?? ''] ?? ($u['role'] ?? '—')) ?>
        <?php if ($estEtudiant): ?><span>Inscription validée à 100 % — compte <?= e($roleLibelle[$u['role'] ?? ''] ?? ($u['role'] ?? '—')) ?></span><?php endif; ?>
      </dd>
    </div>
  </dl>
</section>

<!-- Préinscriptions -->
<section class="tr-bloc">
  <h2>Préinscriptions (<?= count($preinscriptions) ?>)</h2>
  <?php if ($preinscriptions === []): ?>
    <p class="tr-vide">Aucune préinscription.</p>
  <?php else: ?>
  <div class="tr-scroll">
    <table class="tr-table">
      <thead><tr><th>Référence</th><th>Établissement</th><th>Filière</th><th>Statut</th><th>Date</th><th>Dossier d'inscription ?</th></tr></thead>
      <tbody>
      <?php foreach ($preinscriptions as $p):
        $etatPre = DossierStatut::normaliser((string)($p['statut'] ?? '')); ?>
        <tr>
          <td class="tr-ref"><?= e($p['reference'] ?? '—') ?></td>
          <td><?= e($p['etab_nom']) ?></td>
          <td class="tr-sec"><?= e($p['filiere_visee'] ?? '—') ?><?= !empty($p['specialite_visee']) ? ' — ' . e($p['specialite_visee']) : '' ?></td>
          <td>
            <?php // Vocabulaire de la préinscription, et pas de pourcentage :
                  // la progression (0 → 50 → 100 %) mesure le parcours
                  // d'inscription. Une préinscription affichée « Validé à 50 %
                  // · 50 % » laisse croire qu'un dossier est en cours. ?>
            <span class="tr-badge tr-<?= e(DossierStatut::ton($etatPre)) ?>">
              <?= e(DossierStatut::libelle($etatPre, false)) ?>
            </span>
          </td>
          <td class="tr-sec"><?= $dt($p['soumis_le'] ?? $p['date_creation']) ?></td>
          <td><?= !empty($p['a_donne_dossier'])
                ? '<span class="tr-oui">Oui</span>'
                : '<span class="tr-non">Non</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>

<!-- Dossiers d'inscription, avec progression et jalons -->
<section class="tr-bloc">
  <h2>Dossiers d'inscription (<?= count($dossiers) ?>)</h2>
  <?php if ($dossiers === []): ?>
    <p class="tr-vide">Aucun dossier d'inscription.</p>
  <?php else: ?>
  <div class="tr-scroll">
    <table class="tr-table">
      <thead>
        <tr>
          <th>Référence</th><th>Établissement</th><th>Filière</th><th>Bourse</th>
          <th>Statut</th><th>Déposé</th><th>50 %</th><th>100 %</th><th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($dossiers as $d): ?>
        <tr>
          <td class="tr-ref"><?= e($d['reference'] ?? '—') ?></td>
          <td class="tr-sec"><?= e($d['etab_nom']) ?></td>
          <td class="tr-sec"><?= e($d['niveau_entree'] ?? $d['filiere_visee'] ?? '—') ?></td>
          <td class="tr-sec"><?= e($d['bourse_libelle']) ?></td>
          <td>
            <span class="tr-badge tr-<?= e(DossierStatut::ton($d['statut_normalise'])) ?>">
              <?= e($d['statut_libelle']) ?> · <?= (int)$d['progression'] ?> %
            </span>
          </td>
          <td class="tr-sec"><?= $dt($d['soumis_le']) ?></td>
          <td class="tr-sec"><?= $dt($d['valide_50_le']) ?></td>
          <td class="tr-sec"><?= $dt($d['valide_100_le']) ?></td>
          <td><a href="<?= url('/admin/dossiers?q=' . urlencode((string)($d['reference'] ?? $d['nom_complet']))) ?>" class="tr-lien">Voir</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>

<!-- Frise chronologique : tout, du plus récent au plus ancien, à la seconde -->
<section class="tr-bloc">
  <h2>Frise chronologique (<?= count($frise) ?> événements)</h2>
  <?php if ($frise === []): ?>
    <p class="tr-vide">Aucun événement journalisé pour cet utilisateur.</p>
  <?php else: ?>
  <ol class="tr-frise">
    <?php foreach ($frise as $ev): ?>
      <li>
        <div class="tr-frise-t"><?= e($ev['libelle']) ?></div>
        <div class="tr-frise-m">
          <time datetime="<?= e($ev['cree_le']) ?>"><?= $dt($ev['cree_le'], true) ?></time>
          <?php if (!empty($ev['acteur_role'])): ?> · par <?= e($ev['acteur_role']) ?><?php endif; ?>
          <?php if (!empty($ev['etab_nom'])): ?> · <?= e($ev['etab_nom']) ?><?php endif; ?>
          <?php if (!empty($ev['adresse_ip'])): ?> · <?= e($ev['adresse_ip']) ?><?php endif; ?>
        </div>
        <?php if ($ev['donnees'] !== []): ?>
          <div class="tr-frise-d">
            <?php foreach ($ev['donnees'] as $cle => $valeur): ?>
              <?php if (is_scalar($valeur) || $valeur === null): ?>
                <span><b><?= e((string)$cle) ?></b> <?= e((string)$valeur) ?></span>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ol>
  <?php endif; ?>
</section>

<link rel="stylesheet" href="<?= asset('orientation/tracabilite-admin.css') ?>">
