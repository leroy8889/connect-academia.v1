<?php
$couleur          = e($etab['couleur_principale'] ?? '#8B52FA');
$etabNom          = e($etab['nom']);
$etabSlug         = e($etab['slug']);
$filiereLibelle   = $filieres[$dossier['filiere_visee'] ?? ''] ?? ($dossier['filiere_visee'] ?: '—');
if (!empty($dossier['specialite_visee'])) {
    $filiereLibelle .= ' — ' . $dossier['specialite_visee'];
}
$niveauEntree     = e($dossier['niveau_entree'] ?? '—');
$nomComplet       = e($dossier['nom_complet'] ?? '');
$emailCandidat    = e($dossier['email'] ?? '');
$prixInscription  = (int)($etab['prix_inscription'] ?? 0);
$fraisDossier     = (int)($etab['frais_dossier']    ?? 0);
$total            = $prixInscription + $fraisDossier;
$err              = $errors ?? [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Inscription — Étape 3 — <?= $etabNom ?></title>
  <link rel="stylesheet" href="<?= asset('orientation/candidature.css') ?>">
  <style>:root{--brand:<?= $couleur ?>}</style>
</head>
<body>

<header class="cand-topbar">
  <a href="<?= url('orientation/' . $etabSlug) ?>" class="cand-back">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
    Retour à la page école
  </a>
  <span class="cand-step-label">Étape 3 / 3</span>
</header>

<main class="cand-page">

  <!-- Stepper -->
  <div class="stepper" aria-label="Progression">
    <div class="step done">
      <div class="step-circle">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
      </div>
      <div class="step-name">Informations</div>
    </div>
    <div class="step-connector done"></div>
    <div class="step done">
      <div class="step-circle">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
      </div>
      <div class="step-name">Formation &amp; dossier</div>
    </div>
    <div class="step-connector done"></div>
    <div class="step active">
      <div class="step-circle">3</div>
      <div class="step-name">Paiement</div>
    </div>
  </div>

  <?php if (!empty($err['general'])): ?>
  <div class="alert alert-error" style="margin-bottom:20px">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?= e($err['general']) ?>
  </div>
  <?php endif; ?>

  <?php if (!empty($err['paiement'])): ?>
  <div class="alert alert-info" style="margin-bottom:20px">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
    <?= e($err['paiement']) ?>
  </div>
  <?php endif; ?>

  <div class="cand-card">
    <div class="cand-card-head">
      <h2>Paiement de l'inscription</h2>
      <p>Dernière étape pour finaliser votre dossier à <?= $etabNom ?>.</p>
    </div>

    <!-- Résumé dossier -->
    <div class="form-section">
      <div class="section-label">Résumé de votre dossier</div>
      <dl class="dossier-summary">
        <dt>Candidat</dt>
        <dd><?= $nomComplet ?></dd>
        <dt>Formation visée</dt>
        <dd><?= e($filiereLibelle) ?><?= $niveauEntree !== '—' ? ' — ' . $niveauEntree : '' ?></dd>
        <dt>Établissement</dt>
        <dd><?= $etabNom ?></dd>
      </dl>
    </div>

    <!-- Détail du montant -->
    <?php if ($total > 0): ?>
    <div class="form-section">
      <div class="section-label">Montant à régler</div>
      <div class="pay-breakdown">
        <?php if ($prixInscription > 0): ?>
        <div class="pay-line">
          <span>Frais d'inscription</span>
          <strong><?= number_format($prixInscription, 0, ',', ' ') ?> XAF</strong>
        </div>
        <?php endif; ?>
        <?php if ($fraisDossier > 0): ?>
        <div class="pay-line">
          <span>Frais de dossier</span>
          <strong><?= number_format($fraisDossier, 0, ',', ' ') ?> XAF</strong>
        </div>
        <?php endif; ?>
        <div class="pay-total">
          <span>Total</span>
          <span class="pay-total-amount"><?= number_format($total, 0, ',', ' ') ?> XAF</span>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Formulaire de paiement -->
    <div class="form-section">
      <div class="section-label">Méthode de paiement</div>

      <?php if ($total > 0): ?>

      <div class="pay-methods">
        <div class="pay-method-badge">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
          Mobile Money (Airtel / Moov Gabon)
        </div>
        <div class="pay-method-badge">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          Paiement sécurisé via MoneyFusion
        </div>
      </div>

      <form method="POST" action="<?= url("orientation/{$etabSlug}/inscription/etape-3/payer") ?>" id="pay-form">
        <input type="hidden" name="_csrf_token" value="<?= e($csrf) ?>">

        <div class="pay-cta">
          <button type="submit" class="btn-pay" id="pay-btn">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Payer <?= number_format($total, 0, ',', ' ') ?> XAF
          </button>
          <p class="pay-disclaimer">
            Vous serez redirigé vers la plateforme sécurisée MoneyFusion.
            Votre reçu sera disponible immédiatement après confirmation du paiement.
          </p>
        </div>
      </form>

      <?php else: ?>

      <!-- Gratuit -->
      <form method="POST" action="<?= url("orientation/{$etabSlug}/inscription/etape-3/payer") ?>">
        <input type="hidden" name="_csrf_token" value="<?= e($csrf) ?>">
        <div class="pay-cta">
          <button type="submit" class="btn-pay">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            Confirmer mon inscription (gratuit)
          </button>
        </div>
      </form>

      <?php endif; ?>
    </div>

  </div><!-- /cand-card -->

  <div class="alert alert-info" style="margin-top:0">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
    <span>Un reçu sera envoyé à <strong><?= $emailCandidat ?></strong> après confirmation du paiement.</span>
  </div>

  <!-- Navigation -->
  <div class="nav-footer">
    <a href="<?= url("orientation/{$etabSlug}/inscription/etape-2") ?>" class="btn-ghost">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
      Précédent
    </a>
    <span></span>
  </div>

</main>

<script>
(function() {
  var form = document.getElementById('pay-form');
  var btn  = document.getElementById('pay-btn');
  if (!form || !btn) return;

  form.addEventListener('submit', function() {
    btn.disabled = true;
    btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="spin"><path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0"/></svg> Redirection en cours…';
    btn.style.opacity = '0.75';
  });
}());
</script>

<style>
.pay-breakdown{background:var(--clr-bg,#FAF9F5);border-radius:12px;padding:4px 0;overflow:hidden;border:1px solid var(--clr-border,#E8E4F0)}
.pay-line{display:flex;justify-content:space-between;align-items:center;padding:12px 18px;font-size:14px;border-bottom:1px solid var(--clr-border,#E8E4F0)}
.pay-line:last-child{border-bottom:none}
.pay-total{display:flex;justify-content:space-between;align-items:center;padding:14px 18px;font-weight:700;font-size:15px;background:var(--clr-card,#fff);border-top:2px solid var(--clr-border,#E8E4F0)}
.pay-total-amount{color:var(--brand);font-size:1.15rem}
.pay-methods{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:20px}
.pay-method-badge{display:inline-flex;align-items:center;gap:8px;padding:8px 14px;background:var(--clr-bg,#FAF9F5);border:1px solid var(--clr-border,#E8E4F0);border-radius:8px;font-size:13px;font-weight:600;color:var(--clr-txt,#1A1228)}
.pay-cta{display:flex;flex-direction:column;align-items:center;gap:12px;padding-top:8px}
.btn-pay{display:inline-flex;align-items:center;justify-content:center;gap:10px;background:var(--brand);color:#fff;border:none;border-radius:12px;padding:15px 32px;font-size:1rem;font-weight:700;cursor:pointer;width:100%;max-width:400px;transition:opacity .15s}
.btn-pay:hover:not(:disabled){opacity:.88}
.btn-pay:disabled{cursor:not-allowed}
.pay-disclaimer{font-size:12.5px;color:var(--clr-txt-m,#63596E);text-align:center;max-width:340px;line-height:1.55}
@keyframes spin{to{transform:rotate(360deg)}}
.spin{animation:spin .9s linear infinite}
</style>

</body>
</html>
