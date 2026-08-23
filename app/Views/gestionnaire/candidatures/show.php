<?php
// Variables: $dossier, $documents, $signedUrls, $filieres, $etab
$dossier    = $dossier    ?? [];
$documents  = $documents  ?? [];
$signedUrls = $signedUrls ?? [];
$filieres   = $filieres   ?? [];

// Une inscription (ligne dans `inscriptions`) suit un cycle « à valider → inscrit » ;
// une préinscription n'exige aucune validation (le récépissé est immédiat).
$estInscription = !empty($estInscription);

$statutLabels = [
    'brouillon' => 'Brouillon',
    'soumis'    => $estInscription ? 'À valider' : 'Préinscrit',
    'paye'      => 'Payé',
    'accepte'   => $estInscription ? 'Inscrit' : 'Accepté',
    'inscrit'   => 'Inscrit',
    'refuse'    => 'Refusé',
];
$statutColors = [
    'brouillon' => '#9E9E9E',
    'soumis'    => $estInscription ? '#b45309' : '#1d4ed8',
    'paye'      => '#15803d',
    'accepte'   => '#15803d',
    'inscrit'   => '#15803d',
    'refuse'    => '#b91c1c',
];
$statut     = $dossier['statut'] ?? 'brouillon';
$estValide  = in_array($statut, ['inscrit','accepte'], true); // inscription validée
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

<div style="display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start;" class="g-show-grid">

  <!-- Colonne principale -->
  <div>
    <!-- Identité -->
    <div class="g-card" style="margin-bottom:20px;">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;gap:12px;flex-wrap:wrap;">
        <h2 style="font-size:16px;font-weight:700;"><?= e($dossier['nom_complet'] ?? '—') ?></h2>
        <div style="display:flex;align-items:center;gap:10px;">
          <span class="g-badge g-badge-<?= e($statut) ?>" style="font-size:12px;"><?= e($statutLabels[$statut] ?? $statut) ?></span>
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

    <!-- Documents -->
    <?php if (!empty($documents)): ?>
    <div class="g-card">
      <h3 style="font-size:14px;font-weight:700;margin-bottom:14px;">Pièces déposées (<?= count($documents) ?>)</h3>
      <div style="display:flex;flex-direction:column;gap:10px;">
        <?php foreach ($documents as $doc): ?>
          <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:var(--surface);border-radius:var(--r-md);gap:12px;">
            <div>
              <div style="font-size:13px;font-weight:600;"><?= e($doc['piece_libelle'] ?? $doc['libelle'] ?? 'Document') ?></div>
              <div style="font-size:11px;color:var(--ink-3);"><?= e($doc['mime_type'] ?? '') ?> — <?= round(($doc['taille_octets'] ?? 0) / 1024) ?> Ko</div>
            </div>
            <?php if (!empty($signedUrls[$doc['id']])): ?>
              <a href="<?= e($signedUrls[$doc['id']]) ?>" target="_blank" rel="noopener" class="g-btn g-btn-outline g-btn-sm">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Télécharger
              </a>
            <?php else: ?>
              <span style="font-size:11px;color:var(--ink-3);">Lien expiré</span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
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

    <div class="g-card" style="margin-bottom:16px;">
      <h3 style="font-size:13px;font-weight:700;margin-bottom:14px;text-transform:uppercase;letter-spacing:.04em;color:var(--ink-3);">
        <?= $estInscription ? "Validation de l'inscription" : 'Statut de la préinscription' ?>
      </h3>
      <?php if (!$estInscription): ?>
        <p style="font-size:11px;color:var(--ink-3);margin-bottom:12px;line-height:1.5;">La préinscription ne nécessite aucune validation : l'attestation de préinscription est délivrée immédiatement.</p>
      <?php elseif ($statut === 'soumis'): ?>
        <p style="font-size:11px;color:var(--ink-3);margin-bottom:12px;line-height:1.5;">Vérifiez que le dossier est complet (pièces justificatives reçues) avant de valider l'inscription — l'attestation officielle sera alors délivrée au candidat.</p>
      <?php endif; ?>
      <form action="<?= url('/gestionnaire/candidatures/' . $dossier['id'] . '/statut') ?>" method="post">
        <input type="hidden" name="csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
        <input type="hidden" name="_method" value="POST">
        <div style="display:flex;flex-direction:column;gap:8px;">
          <?php
          // Transitions contextuelles : une inscription se valide (→ inscrit),
          // une préinscription n'a pas de validation (refus éventuel seulement).
          if ($estInscription) {
              $transitions = [
                  'inscrit' => ["Valider l'inscription", 'g-btn-primary'],
                  'soumis'  => ['Remettre à l\'étude',    'g-btn-outline'],
                  'refuse'  => ['Refuser le dossier',     'g-btn-danger'],
              ];
          } else {
              $transitions = [
                  'soumis'  => ['Rétablir la préinscription', 'g-btn-outline'],
                  'refuse'  => ['Refuser',                    'g-btn-danger'],
              ];
          }
          foreach ($transitions as $val => [$label, $cls]): ?>
            <?php if ($val !== $statut): ?>
            <button type="submit" name="statut" value="<?= $val ?>" class="g-btn <?= $cls ?>"
                    onclick="return confirm('Changer le statut en «<?= addslashes($label) ?>» ?')">
              <?= $label ?>
            </button>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </form>
    </div>

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

<style>
@media(max-width:768px) {
  .g-show-grid { grid-template-columns:1fr !important; }
}
</style>
