<?php
// Variables: $etab, $errors, $old
$etab   = $etab   ?? [];
$errors = $errors ?? [];
$old    = $old    ?? [];

// Valeur affichée : ancien POST invalide sinon valeur en base
function pVal(array $old, array $etab, string $key): string {
    return htmlspecialchars((string)($old[$key] ?? $etab[$key] ?? ''), ENT_QUOTES, 'UTF-8');
}
function pErr(array $errors, string $key): string {
    return isset($errors[$key]) ? '<div class="g-error-msg">' . htmlspecialchars($errors[$key], ENT_QUOTES, 'UTF-8') . '</div>' : '';
}
$checked = function (string $key) use ($old, $etab): string {
    if (!empty($old)) return isset($old[$key]) ? 'checked' : '';
    return (int)($etab[$key] ?? 0) === 1 ? 'checked' : '';
};

$typeLabels = ['universite' => 'Université', 'grande_ecole' => 'Grande école', 'institut' => 'Institut'];
$inscriptionActive = (int)($etab['inscription_active'] ?? 0) === 1;
$paiementStatut    = $etab['paiement_statut'] ?? null;
?>

<div class="g-page-header">
  <div>
    <h2>Paramètres de l'établissement</h2>
    <p>Ces informations alimentent directement votre page publique <code style="font-size:12px;background:rgba(31,30,29,.06);padding:2px 8px;border-radius:6px;">/orientation/<?= e($etab['slug'] ?? '') ?></code></p>
  </div>
  <a href="<?= url('/orientation/' . ($etab['slug'] ?? '')) ?>" target="_blank" class="g-btn g-btn-ghost">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6M15 3h6v6M10 14L21 3"/></svg>
    Prévisualiser le site
  </a>
</div>

<!-- Identité gérée par Connect'Academia (lecture seule) -->
<div class="g-card" style="margin-bottom:20px;display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:18px;background:rgba(255,255,255,.42);">
  <div>
    <div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-3);font-weight:600;margin-bottom:4px;">Établissement</div>
    <div style="font-size:14px;font-weight:700;"><?= e($etab['nom'] ?? '—') ?></div>
  </div>
  <div>
    <div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-3);font-weight:600;margin-bottom:4px;">Type</div>
    <div style="font-size:13px;font-weight:500;"><?= e($typeLabels[$etab['type'] ?? ''] ?? ($etab['type'] ?? '—')) ?> · <?= e($etab['ville'] ?? '') ?></div>
  </div>
  <div>
    <div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-3);font-weight:600;margin-bottom:4px;">Statut du site</div>
    <span class="g-badge <?= ($etab['statut'] ?? '') === 'actif' ? 'g-badge-ouverte' : 'g-badge-fermee' ?>"><?= e(ucfirst($etab['statut'] ?? '—')) ?></span>
  </div>
  <div>
    <div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-3);font-weight:600;margin-bottom:4px;">Inscription en ligne</div>
    <?php if ($inscriptionActive): ?>
      <span class="g-badge g-badge-ouverte">Activée</span>
    <?php else: ?>
      <span class="g-badge g-badge-fermee" title="Activable dans la section « Candidatures en ligne » ci-dessous">Non activée</span>
    <?php endif; ?>
  </div>
  <div style="grid-column:1/-1;font-size:11.5px;color:var(--ink-3);border-top:1px solid var(--border);padding-top:12px;">
    Nom, type, logo, statut du site et compte de reversement<?= $paiementStatut ? ' (' . e($paiementStatut) . ')' : '' ?> sont gérés par Connect'Academia — contactez le support pour les modifier.
  </div>
</div>

<form action="<?= url('/gestionnaire/parametres') ?>" method="post" enctype="multipart/form-data" style="max-width:820px;">
  <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">

  <!-- Présentation -->
  <div class="g-card" style="margin-bottom:20px;">
    <h3 class="g-section-title">Présentation publique</h3>
    <div class="g-form-row">
      <div class="g-form-group">
        <label>Label de campagne</label>
        <input type="text" name="label_campagne" value="<?= pVal($old, $etab, 'label_campagne') ?>" placeholder="Ex. Admissions 2026 ouvertes" maxlength="160">
      </div>
      <div class="g-form-group">
        <label>Date limite de dossier</label>
        <input type="date" name="date_limite_dossier" value="<?= pVal($old, $etab, 'date_limite_dossier') ?>">
        <?= pErr($errors, 'date_limite_dossier') ?>
      </div>
    </div>
    <div class="g-form-group">
      <label>Titre d'accroche</label>
      <input type="text" name="titre_accroche" value="<?= pVal($old, $etab, 'titre_accroche') ?>" placeholder="Ex. Formez-vous aux métiers de demain" maxlength="255">
    </div>
    <div class="g-form-group">
      <label>Sous-titre</label>
      <textarea name="sous_titre" rows="2" placeholder="Phrase d'appui sous le titre du hero"><?= pVal($old, $etab, 'sous_titre') ?></textarea>
    </div>
    <div class="g-form-group">
      <label>Description de l'établissement</label>
      <textarea name="description" rows="5" placeholder="Présentation affichée sur la page publique"><?= pVal($old, $etab, 'description') ?></textarea>
    </div>
  </div>

  <!-- Citation -->
  <div class="g-card" style="margin-bottom:20px;">
    <h3 class="g-section-title">Citation (section témoignage)</h3>
    <div class="g-form-group">
      <label>Texte de la citation</label>
      <textarea name="citation_texte" rows="3" placeholder="Ex. Notre mission est de révéler chaque talent…"><?= pVal($old, $etab, 'citation_texte') ?></textarea>
    </div>
    <div class="g-form-row">
      <div class="g-form-group">
        <label>Auteur</label>
        <input type="text" name="citation_auteur" value="<?= pVal($old, $etab, 'citation_auteur') ?>" placeholder="Ex. Dr Marie Ntoutoume" maxlength="150">
      </div>
      <div class="g-form-group">
        <label>Fonction</label>
        <input type="text" name="citation_fonction" value="<?= pVal($old, $etab, 'citation_fonction') ?>" placeholder="Ex. Directrice générale" maxlength="150">
      </div>
    </div>
  </div>

  <!-- Contact -->
  <div class="g-card" style="margin-bottom:20px;">
    <h3 class="g-section-title">Coordonnées de contact</h3>
    <div class="g-form-group">
      <label>Adresse</label>
      <input type="text" name="adresse" value="<?= pVal($old, $etab, 'adresse') ?>" placeholder="Quartier, rue, ville">
    </div>
    <div class="g-form-row">
      <div class="g-form-group">
        <label>E-mail de contact</label>
        <input type="email" name="email_contact" value="<?= pVal($old, $etab, 'email_contact') ?>" placeholder="contact@etablissement.ga">
        <?= pErr($errors, 'email_contact') ?>
      </div>
      <div class="g-form-group">
        <label>Téléphone</label>
        <input type="tel" name="telephone" value="<?= pVal($old, $etab, 'telephone') ?>" placeholder="+241 06 12 34 56">
      </div>
    </div>
    <div class="g-form-row">
      <div class="g-form-group">
        <label>WhatsApp</label>
        <input type="tel" name="whatsapp" value="<?= pVal($old, $etab, 'whatsapp') ?>" placeholder="+241 06 12 34 56">
      </div>
      <div></div>
    </div>
  </div>

  <!-- Tarifs -->
  <div class="g-card" style="margin-bottom:20px;">
    <h3 class="g-section-title">Tarifs (XAF)</h3>
    <p style="font-size:12px;color:var(--ink-3);margin-bottom:14px;">Montants affichés aux candidats et utilisés au guichet. 0 = gratuit.</p>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;">
      <div class="g-form-group" style="margin:0;">
        <label>Préinscription</label>
        <input type="number" name="prix_preinscription" value="<?= pVal($old, $etab, 'prix_preinscription') ?: '0' ?>" min="0">
      </div>
      <div class="g-form-group" style="margin:0;">
        <label>Inscription</label>
        <input type="number" name="prix_inscription" value="<?= pVal($old, $etab, 'prix_inscription') ?: '0' ?>" min="0">
      </div>
      <div class="g-form-group" style="margin:0;">
        <label>Frais de dossier</label>
        <input type="number" name="frais_dossier" value="<?= pVal($old, $etab, 'frais_dossier') ?: '0' ?>" min="0">
      </div>
    </div>
  </div>

  <!-- Candidatures -->
  <div class="g-card" style="margin-bottom:20px;">
    <h3 class="g-section-title">Candidatures en ligne</h3>
    <div style="display:flex;flex-direction:column;gap:18px;">
      <label class="g-switch" style="display:flex;width:100%;justify-content:space-between;align-items:center;gap:16px;">
        <span>
          <span style="display:block;font-size:13px;font-weight:600;">Préinscriptions ouvertes</span>
          <span style="display:block;font-size:11.5px;color:var(--ink-3);margin-top:2px;">Les élèves peuvent déposer une préinscription depuis votre page publique.</span>
        </span>
        <input type="checkbox" name="preinscription_active" value="1" <?= $checked('preinscription_active') ?>>
        <span class="g-switch-track" style="position:relative;"></span>
      </label>
      <label class="g-switch" style="display:flex;width:100%;justify-content:space-between;align-items:center;gap:16px;">
        <span>
          <span style="display:block;font-size:13px;font-weight:600;">Inscriptions ouvertes</span>
          <span style="display:block;font-size:11.5px;color:var(--ink-3);margin-top:2px;">Les élèves peuvent effectuer une inscription complète en ligne (dossier + pièces justificatives) depuis votre page publique.</span>
        </span>
        <input type="checkbox" name="inscription_active" value="1" <?= $checked('inscription_active') ?>>
        <span class="g-switch-track" style="position:relative;"></span>
      </label>
      <label class="g-switch" style="display:flex;width:100%;justify-content:space-between;align-items:center;gap:16px;">
        <span>
          <span style="display:block;font-size:13px;font-weight:600;">Générer une attestation de préinscription</span>
          <span style="display:block;font-size:11.5px;color:var(--ink-3);margin-top:2px;">Une attestation officielle numérotée (PRE-AAAA-XXXX) est produite à chaque préinscription — en ligne comme au guichet. Sans limite : réimprimez-le autant de fois que nécessaire.</span>
        </span>
        <input type="checkbox" name="genere_recepisse" value="1" <?= $checked('genere_recepisse') ?>>
        <span class="g-switch-track" style="position:relative;"></span>
      </label>
      <label class="g-switch" style="display:flex;width:100%;justify-content:space-between;align-items:center;gap:16px;">
        <span>
          <span style="display:block;font-size:13px;font-weight:600;">Générer une attestation d'inscription</span>
          <span style="display:block;font-size:11.5px;color:var(--ink-3);margin-top:2px;">Une attestation officielle numérotée (ATT-AAAA-XXXX) est délivrée à chaque inscription validée — sans limite de génération. Désactivez si votre établissement ne délivre pas d'attestation via la plateforme.</span>
        </span>
        <input type="checkbox" name="genere_attestation" value="1" <?= $checked('genere_attestation') ?>>
        <span class="g-switch-track" style="position:relative;"></span>
      </label>
    </div>
  </div>

  <!-- Apparence -->
  <div class="g-card" style="margin-bottom:24px;">
    <h3 class="g-section-title">Apparence du site public</h3>
    <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
      <div class="g-form-group" style="margin:0;">
        <label>Couleur principale</label>
        <div style="display:flex;align-items:center;gap:10px;">
          <input type="color" name="couleur_principale" value="<?= pVal($old, $etab, 'couleur_principale') ?: '#1F4E79' ?>"
                 style="width:52px;height:40px;padding:4px;cursor:pointer;">
          <span style="font-size:12px;color:var(--ink-3);">Teinte tout le thème de votre page (boutons, titres, accents).</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Signature / cachet officiel -->
  <div class="g-card" style="margin-bottom:24px;">
    <h3 class="g-section-title">Signature &amp; cachet officiel</h3>
    <p style="font-size:12.5px;color:var(--ink-3);margin:-4px 0 16px;max-width:640px;">
      Cette image (signature manuscrite ou cachet de la direction) s'imprime en bas
      des <strong>attestations d'inscription</strong>, au-dessus de « La Direction Académique »,
      pour authentifier le document. Fond transparent recommandé (PNG), max 10&nbsp;Mo.
    </p>
    <div style="display:flex;align-items:flex-start;gap:22px;flex-wrap:wrap;">
      <?php if (!empty($signatureDataUri)): ?>
        <div style="flex:0 0 auto;text-align:center;">
          <div style="border:1px solid var(--border);border-radius:10px;padding:12px 18px;background:#fff;display:inline-block;">
            <img src="<?= e($signatureDataUri) ?>" alt="Signature actuelle" style="max-height:70px;max-width:200px;object-fit:contain;display:block;">
          </div>
          <label style="display:flex;align-items:center;gap:7px;justify-content:center;margin-top:10px;font-size:12.5px;color:var(--red,#c0392b);cursor:pointer;">
            <input type="checkbox" name="supprimer_signature" value="1" style="cursor:pointer;">
            Retirer la signature actuelle
          </label>
        </div>
      <?php endif; ?>
      <div class="g-form-group" style="margin:0;flex:1;min-width:240px;">
        <label><?= !empty($signatureDataUri) ? 'Remplacer par une nouvelle image' : 'Ajouter une signature / un cachet' ?></label>
        <input type="file" name="signature" accept="image/png,image/jpeg,image/webp"
               style="display:block;font-size:13px;padding:8px 0;">
        <?= pErr($errors, 'signature') ?>
        <span style="font-size:11.5px;color:var(--ink-3);display:block;margin-top:6px;">
          Formats acceptés : PNG (transparence conseillée), JPG, WEBP.
        </span>
      </div>
    </div>
  </div>

  <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
    <button type="submit" class="g-btn g-btn-primary">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
      Enregistrer les paramètres
    </button>
    <span style="font-size:12px;color:var(--ink-3);">Le cache du site public est rafraîchi immédiatement.</span>
  </div>
</form>
