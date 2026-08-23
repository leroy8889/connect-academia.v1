<?php
// Notifications — variables : $notifs, $navCounts
require_once __DIR__ . '/_helpers.php';
$notifs  = $notifs ?? [];
$nonLues = (int)($navCounts['notifications'] ?? 0);

/** Icône + tonalité selon le type de notification. */
$notifStyle = function (?string $type): array {
    return match (true) {
        str_contains((string)$type, 'recepisse')     => ['fileText',    'p'],
        str_contains((string)$type, 'preinscription')=> ['fileText',    'p'],
        str_contains((string)$type, 'inscription')   => ['briefcase',   'p'],
        str_contains((string)$type, 'valid'),
        str_contains((string)$type, 'accepte')       => ['checkCircle', 'g'],
        str_contains((string)$type, 'refus')         => ['alert',       't'],
        str_contains((string)$type, 'paiement')      => ['card',        'g'],
        str_contains((string)$type, 'piece'),
        str_contains((string)$type, 'document')      => ['folder',      't'],
        default                                      => ['bell',        ''],
    };
};

// Regroupement : Aujourd'hui / Cette semaine / Plus ancien
$groupes = ['Aujourd\'hui' => [], 'Cette semaine' => [], 'Plus ancien' => []];
$debutJour    = strtotime('today');
$debutSemaine = strtotime('-7 days');
foreach ($notifs as $n) {
    $ts = strtotime($n['date_creation'] ?? '') ?: 0;
    if ($ts >= $debutJour)         { $groupes['Aujourd\'hui'][] = $n; }
    elseif ($ts >= $debutSemaine)  { $groupes['Cette semaine'][] = $n; }
    else                           { $groupes['Plus ancien'][] = $n; }
}
?>

<div class="acct-shead">
  <div>
    <span class="acct-shead-kick"><?= acct_icon('bell', 12) ?> <?= $nonLues ?> non lue<?= $nonLues > 1 ? 's' : '' ?></span>
    <h1 class="acct-shead-t">Mes notifications</h1>
    <p class="acct-shead-d">Toutes les mises à jour de vos dossiers, tous établissements confondus.</p>
  </div>
  <?php if ($nonLues > 0): ?>
    <form method="POST" action="<?= url('/eleve/orientation/notifications/tout-lu') ?>" style="margin:0;">
      <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
      <button type="submit" class="abtn ghost">Tout marquer comme lu</button>
    </form>
  <?php endif; ?>
</div>

<?php if (empty($notifs)): ?>
  <div class="acct-empty gls">
    <div class="acct-empty-ic"><?= acct_icon('bell', 22) ?></div>
    <div class="acct-empty-t">Aucune notification</div>
    <div class="acct-empty-d">Vous serez averti ici à chaque étape importante : attestation disponible, dossier étudié, inscription validée…</div>
  </div>
<?php else: ?>
  <div class="acct-card gls" style="padding-bottom:8px;">
    <?php foreach ($groupes as $label => $liste): ?>
      <?php if (empty($liste)) continue; ?>
      <div class="acct-notif-day"><?= e($label) ?></div>
      <?php foreach ($liste as $n): ?>
        <?php
          [$icon, $tone] = $notifStyle($n['type'] ?? null);
          $ts = strtotime($n['date_creation'] ?? '') ?: 0;
          $heure = $ts >= $debutJour ? date('H\hi', $ts) : acct_date($n['date_creation'] ?? null);
        ?>
        <div class="acct-notif <?= empty($n['lu']) ? 'unread' : 'read' ?>">
          <div class="acct-notif-ic <?= e($tone) ?>"><?= acct_icon($icon, 17) ?></div>
          <div style="flex:1;min-width:0;">
            <div class="acct-notif-t"><?= e($n['titre'] ?? '') ?></div>
            <div class="acct-notif-d">
              <?= e($n['message'] ?? '') ?>
              <?php if (!empty($n['etab_nom'])): ?><span style="color:var(--ink-faint);"> · <?= e($n['etab_nom']) ?></span><?php endif; ?>
            </div>
          </div>
          <div class="acct-notif-time"><?= e($heure) ?></div>
        </div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
