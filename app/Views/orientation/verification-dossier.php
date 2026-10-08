<?php
/**
 * Résultat de vérification d'un dossier (spec §7.4).
 *
 * Écran de guichet : l'agent doit décider en une seule vue, sans défiler.
 * La couleur porte l'information avant le texte — un agent en période de
 * rentrée ne lit pas un paragraphe, il regarde une couleur. D'où le liseré
 * plein largeur sous la barre, lisible à un mètre, avant même le bandeau.
 *
 * Rien de ce qui est affiché ne vient du document papier : tout est relu en
 * base au moment du scan.
 *
 * ── Deux corrections portées ici ──────────────────────────────────────────
 *  1. Le retour pointait sur /etablissement/scanner, une route qui n'a jamais
 *     existé dans la table de routage : le bouton « Scanner un autre dossier »
 *     renvoyait sur une 404. La destination est désormais calculée par le
 *     contrôleur (`$retour`), qui seul sait à quel espace appartient l'acteur.
 *  2. Le gabarit ne verrouillait pas le thème : sur un poste en préférence
 *     sombre, tokens.css basculait `--bg-main` et la page s'affichait en noir.
 *     `data-theme="light"` ferme la porte, la feuille de style ne dépend plus
 *     d'aucun token qui bascule.
 */
use Models\Orientation\DossierStatut;

$estDossier = ($cas ?? '') === 'dossier';
$d          = $dossier ?? null;

// Valeurs de repli : la vue reste rendable même appelée sans ces clés.
$retour          = $retour          ?? '/gestionnaire/scanner';
$retourLibelle   = $retourLibelle   ?? 'Scanner un autre dossier';
$peutOuvrirFiche = $peutOuvrirFiche ?? false;

// Bandeau : pour un dossier trouvé, la tonalité suit le statut réel.
if ($estDossier && $d !== null) {
    $ton = match (DossierStatut::normaliser($statut)) {
        DossierStatut::VALIDE_50  => 'succes',
        DossierStatut::VALIDE_100 => 'info',
        DossierStatut::REJETE,
        DossierStatut::EXPIRE,
        DossierStatut::ANNULE     => 'danger',
        default                    => 'attente',
    };

    $titreBandeau = match (DossierStatut::normaliser($statut)) {
        DossierStatut::VALIDE_50       => 'Dossier validé à 50 % — présentation acceptée',
        DossierStatut::VALIDE_100      => 'Déjà inscrit',
        DossierStatut::SOUMIS          => 'Dossier en cours d’analyse, pas encore validé',
        DossierStatut::PIECE_MANQUANTE => 'Pièce complémentaire attendue',
        DossierStatut::REJETE          => 'Dossier rejeté',
        DossierStatut::EXPIRE          => 'Dossier expiré',
        DossierStatut::ANNULE          => 'Dossier annulé',
        default                         => 'Dossier non finalisé',
    };

    $texteBandeau = match (DossierStatut::normaliser($statut)) {
        DossierStatut::VALIDE_50  => 'Vous pouvez encaisser la scolarité et valider l’inscription à 100 %.',
        DossierStatut::VALIDE_100 => 'Inscription définitive le ' . (!empty($d['valide_100_le'])
            ? date('d/m/Y', strtotime((string)$d['valide_100_le'])) : '—') . '.',
        DossierStatut::REJETE     => 'Motif : ' . (trim((string)($d['motif_rejet'] ?? '')) ?: 'non précisé') . '.',
        DossierStatut::PIECE_MANQUANTE => 'Pièces attendues : '
            . (trim((string)($d['pieces_attendues'] ?? '')) ?: 'non précisées') . '.',
        DossierStatut::EXPIRE     => 'Le délai de finalisation est dépassé.',
        default                    => 'Analysez les pièces depuis la fiche du dossier avant toute décision.',
    };
} else {
    $ton          = $bandeau['ton'];
    $titreBandeau = $bandeau['titre'];
    $texteBandeau = $bandeau['texte'];
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <meta name="color-scheme" content="light">
  <title>Vérification de dossier</title>
  <link rel="stylesheet" href="<?= asset('orientation/verification-dossier.css') ?>">
</head>
<body>

<header class="v-topbar">
  <a href="<?= url($retour) ?>" class="v-back">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
    <?= e($retourLibelle) ?>
  </a>
</header>

<!-- Liseré de verdict : la couleur avant la lecture. -->
<div class="v-lisere v-t-<?= e($ton) ?>" aria-hidden="true"></div>

<main class="v-page">

  <!-- Bandeau : lisible à un mètre, avant toute lecture de détail -->
  <div class="v-bandeau v-<?= e($ton) ?>" role="status" aria-live="polite">
    <div class="v-bandeau-ico" aria-hidden="true">
      <?php if ($ton === 'succes'): ?>
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
      <?php elseif ($ton === 'danger'): ?>
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
      <?php elseif ($ton === 'info'): ?>
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/></svg>
      <?php else: ?>
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg>
      <?php endif; ?>
    </div>
    <div class="v-bandeau-txt">
      <h1><?= e($titreBandeau) ?></h1>
      <?php if ($texteBandeau !== ''): ?><p><?= e($texteBandeau) ?></p><?php endif; ?>
    </div>
  </div>

  <?php if ($cas === 'limite' && !empty($attente)): ?>
    <p class="v-note">Réessayez dans <?= (int)$attente ?> seconde<?= (int)$attente > 1 ? 's' : '' ?>.</p>
  <?php endif; ?>

  <?php if ($estDossier && $d !== null): ?>

    <div class="v-identite">
      <?php if (!empty($photoUrl)): ?>
        <img class="v-photo" src="<?= e($photoUrl) ?>" alt="Photo du candidat">
      <?php else: ?>
        <div class="v-photo v-photo-vide" aria-hidden="true">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6 8-6s8 2 8 6"/></svg>
        </div>
      <?php endif; ?>

      <div class="v-identite-txt">
        <div class="v-reference"><?= e($d['reference'] ?? 'Référence non attribuée') ?></div>
        <div class="v-nom"><?= e($d['nom_complet'] ?? '—') ?></div>
        <div class="v-meta">
          <?= e(trim((string)($d['niveau_entree'] ?? $d['filiere_visee'] ?? '—'))) ?>
          <?php if (!empty($d['specialite_visee'])): ?> — <?= e($d['specialite_visee']) ?><?php endif; ?>
        </div>
        <div class="v-chips">
          <span class="v-chip v-chip-<?= e(DossierStatut::ton($statut)) ?>"><?= e(DossierStatut::libelle($statut)) ?> · <?= (int)DossierStatut::progression($statut) ?> %</span>
          <span class="v-chip"><?= e($bourse) ?></span>
        </div>
      </div>
    </div>

    <dl class="v-faits">
      <div><dt>Date de naissance</dt><dd><?= e(!empty($d['date_naissance']) ? date('d/m/Y', strtotime((string)$d['date_naissance'])) : '—') ?></dd></div>
      <div><dt>Téléphone</dt><dd><?= e(trim((string)($d['indicatif_tel'] ?? '') . ' ' . (string)($d['telephone'] ?? '')) ?: '—') ?></dd></div>
      <div><dt>Déposé le</dt><dd><?= e(!empty($d['soumis_le']) ? date('d/m/Y à H\hi', strtotime((string)$d['soumis_le'])) : '—') ?></dd></div>
      <div><dt>À finaliser avant</dt><dd><?= e(!empty($d['expire_le']) ? date('d/m/Y', strtotime((string)$d['expire_le'])) : '—') ?></dd></div>
    </dl>

    <!-- Liste des pièces uniquement : la page de vérification n'affiche JAMAIS
         les pièces elles-mêmes (spec §7.5). Leur consultation passe par la
         fiche dossier, avec une URL signée et un contrôle de droits. -->
    <section class="v-pieces">
      <h2>Pièces au dossier</h2>
      <?php if (empty($documents)): ?>
        <p class="v-vide">Aucune pièce enregistrée.</p>
      <?php else: ?>
        <ul>
          <?php foreach ($documents as $doc): ?>
            <li>
              <span class="v-piece-nom"><?= e($doc['libelle'] ?? 'Document') ?></span>
              <span class="v-piece-etat v-etat-<?= e($doc['statut'] ?? 'en_attente') ?>">
                <?= e(match ($doc['statut'] ?? 'en_attente') {
                    'accepte' => 'Acceptée',
                    'refuse'  => 'Refusée',
                    default   => 'À vérifier',
                }) ?>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <!-- La fiche dossier vit dans l'espace gestionnaire, fermé aux sessions
         purement administrateur : on ne propose que ce qui est ouvrable. -->
    <?php if ($peutOuvrirFiche): ?>
      <div class="v-actions">
        <?php if (!empty($peutValider100)): ?>
          <!-- Le bouton n'apparaît qu'à 50 % : c'est la machine à états qui
               décide, pas l'écran. Un POST forgé serait refusé de la même façon. -->
          <a class="v-btn v-btn-primaire" href="<?= url('gestionnaire/candidatures/' . (int)$d['id'] . '#valider-100') ?>">
            Valider à 100 %
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
          </a>
        <?php endif; ?>
        <a class="v-btn v-btn-secondaire" href="<?= url('gestionnaire/candidatures/' . (int)$d['id']) ?>">
          Consulter le dossier
        </a>
      </div>
    <?php endif; ?>

  <?php else: ?>

    <form class="v-repli" method="POST" action="<?= url('verification/code-court') ?>">
      <input type="hidden" name="_csrf_token" value="<?= e($csrf) ?>">
      <label for="code_court">Saisir le code court à 8 caractères</label>
      <div class="v-repli-ligne">
        <input type="text" id="code_court" name="code_court"
               placeholder="ABCD-EFGH" maxlength="9"
               autocomplete="off" autocapitalize="characters" spellcheck="false"
               inputmode="text" autofocus required>
        <button type="submit" class="v-btn v-btn-primaire">Vérifier</button>
      </div>
      <p class="v-note">Le code est imprimé sous le QR code du récépissé.</p>
    </form>

  <?php endif; ?>

</main>

<script>
// Mise en forme du code pendant la frappe, à l'identique de l'écran de scan :
// l'agent lit « ABCD-EFGH » sur le papier, il tape ce qu'il voit. On accepte
// tout (espaces, minuscules, avec ou sans tiret), le serveur renormalise.
(function () {
    'use strict';
    var champ = document.getElementById('code_court');
    if (!champ) { return; }
    champ.addEventListener('input', function () {
        var brut = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 8);
        this.value = brut.length > 4 ? brut.slice(0, 4) + '-' + brut.slice(4) : brut;
    });
}());
</script>

</body>
</html>
