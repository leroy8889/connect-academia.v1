<?php
/**
 * Grille tarifaire d'une formation — fragment partagé par les DEUX back-offices
 * (super administrateur : /admin/orientation/{id}/modifier ; gestionnaire :
 * /gestionnaire/filieres). Un seul gabarit, parce qu'une grille de prix
 * affichée différemment selon qui la regarde finit par être remplie
 * différemment.
 *
 * Volontairement neutre en CSS : styles en ligne uniquement, aucune classe des
 * deux thèmes (`or-*` côté admin, `g-*` côté gestionnaire) n'est utilisée. Le
 * fragment s'insère donc dans l'un comme dans l'autre sans rien emprunter.
 *
 * Variables attendues :
 *   $gtFiliere  array   Ligne de `filieres_etablissement` (id, nom).
 *   $gtGrille   array   ['licence' => ['' => [...], 'L1' => [...]], 'master' => [...]]
 *   $gtAction   string  URL de POST de cette grille.
 *   $gtCsrf     string  Jeton CSRF.
 *   $gtChamp    string  Nom du champ CSRF ('csrf_token' ou '_csrf_token').
 *   $gtHeritage array   ['licence' => ['non_boursier' => int, 'boursier' => int], 'master' => …]
 *                       Tarifs de la fiche établissement, affichés en indication.
 */

use Models\Orientation\TarifFormation;
use Services\Orientation\TarifCycle;

$gtGrille   = $gtGrille   ?? [];
$gtHeritage = $gtHeritage ?? [];
$gtChamp    = $gtChamp    ?? 'csrf_token';

$gtVal = static function (array $grille, string $cycle, string $niveau, string $col): string {
    $v = (int)($grille[$cycle][$niveau][$col] ?? 0);
    return $v > 0 ? (string)$v : '';
};
?>
<form action="<?= e($gtAction) ?>" method="post"
      style="margin-top:10px;padding:14px;border:1px solid rgba(128,128,128,.28);border-radius:10px;">
  <input type="hidden" name="<?= e($gtChamp) ?>" value="<?= e($gtCsrf) ?>">

  <div style="font-size:12px;line-height:1.55;opacity:.78;margin-bottom:12px;">
    Scolarité annuelle en FCFA pour cette formation.
    <strong>« Tout le cycle »</strong> suffit si toutes les années coûtent le même prix ;
    ne remplissez L1/L2/L3 ou M1/M2 que si elles diffèrent.
    Une case vide n'est pas « gratuit » : elle signifie « reprendre la ligne du dessus »,
    puis les tarifs par défaut de l'établissement.
    Un tarif boursier vide reprend le tarif non boursier.
  </div>

  <?php foreach ([TarifCycle::LICENCE, TarifCycle::MASTER] as $gtCycle): ?>
    <?php
      $gtHerNb = (int)($gtHeritage[$gtCycle]['non_boursier'] ?? 0);
      $gtHerB  = (int)($gtHeritage[$gtCycle]['boursier'] ?? 0);
    ?>
    <div style="margin-bottom:14px;">
      <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;opacity:.65;margin-bottom:6px;">
        Cycle <?= e(TarifCycle::libelle($gtCycle)) ?>
      </div>
      <div style="overflow-x:auto;">
        <table style="width:100%;min-width:420px;border-collapse:collapse;font-size:12.5px;">
          <thead>
            <tr>
              <th style="text-align:left;padding:5px 8px;font-weight:600;opacity:.7;">Niveau</th>
              <th style="text-align:left;padding:5px 8px;font-weight:600;opacity:.7;">Non boursier</th>
              <th style="text-align:left;padding:5px 8px;font-weight:600;opacity:.7;">Boursier</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach (TarifFormation::niveauxDuCycle($gtCycle) as $gtNiveau): ?>
            <?php
              // Clé de FORMULAIRE, pas le niveau brut : « tout le cycle » a un
              // niveau vide en base, et `tarifs[licence][]` signifierait pour
              // PHP « ajoute à la fin », pas « clé vide ». La ligne serait
              // arrivée sous l'index 0 et n'aurait jamais été enregistrée.
              $gtCle   = TarifFormation::cleFormulaire($gtNiveau);
              $gtCleNb = "tarifs[{$gtCycle}][{$gtCle}][non_boursier]";
              $gtCleB  = "tarifs[{$gtCycle}][{$gtCle}][boursier]";
              $gtCycleEntier = $gtNiveau === '';

              // Le placeholder montre ce qui s'appliquerait si la case reste
              // vide : pour la ligne de cycle, le tarif de l'établissement ;
              // pour une année, le tarif de cycle saisi juste au-dessus.
              $gtPhNb = $gtCycleEntier
                  ? ($gtHerNb > 0 ? number_format($gtHerNb, 0, ',', ' ') : 'non renseigné')
                  : 'tarif du cycle';
              $gtPhB  = $gtCycleEntier
                  ? ($gtHerB > 0 ? number_format($gtHerB, 0, ',', ' ') : 'tarif non boursier')
                  : 'tarif du cycle';
            ?>
            <tr<?= $gtCycleEntier ? ' style="font-weight:600;"' : '' ?>>
              <td style="padding:4px 8px;white-space:nowrap;">
                <?= e(TarifFormation::libelleNiveau($gtNiveau)) ?>
              </td>
              <td style="padding:4px 8px;">
                <input type="text" inputmode="numeric" name="<?= e($gtCleNb) ?>"
                       value="<?= e($gtVal($gtGrille, $gtCycle, $gtNiveau, 'scolarite_non_boursier')) ?>"
                       placeholder="<?= e($gtPhNb) ?>"
                       style="width:100%;min-width:110px;padding:6px 9px;border:1px solid rgba(128,128,128,.35);border-radius:7px;font:inherit;font-size:12.5px;background:transparent;color:inherit;">
              </td>
              <td style="padding:4px 8px;">
                <input type="text" inputmode="numeric" name="<?= e($gtCleB) ?>"
                       value="<?= e($gtVal($gtGrille, $gtCycle, $gtNiveau, 'scolarite_boursier')) ?>"
                       placeholder="<?= e($gtPhB) ?>"
                       style="width:100%;min-width:110px;padding:6px 9px;border:1px solid rgba(128,128,128,.35);border-radius:7px;font:inherit;font-size:12.5px;background:transparent;color:inherit;">
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endforeach; ?>

  <button type="submit"
          style="padding:8px 16px;border:0;border-radius:8px;background:#8B52FA;color:#fff;font:inherit;font-size:12.5px;font-weight:700;cursor:pointer;">
    Enregistrer la grille
  </button>
</form>
