<?php
// Variables: $settings (array key=>value), $admin (current admin), $totpQrUrl, $totpSecret
$settings  = $settings ?? [];
$admin     = $admin    ?? [];
$activeTab = $activeTab ?? 'general';

// Flash messages
$flashSuccess = \Core\Session::getFlash('success');
$flashError   = \Core\Session::getFlash('error');

function sv(array $s, string $key, mixed $default = ''): mixed {
    return $s[$key] ?? $default;
}
?>

<!-- Header -->
<div class="admin-page-header-row">
  <div>
    <h1>Paramètres</h1>
    <p>Configuration globale de la plateforme Connect'Academia</p>
  </div>
  <button class="btn-primary" form="form-settings-main" type="submit">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
    Enregistrer
  </button>
</div>

<?php if ($flashSuccess): ?>
<div class="flash-success">
  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
  <?= e($flashSuccess) ?>
</div>
<?php endif; ?>
<?php if ($flashError): ?>
<div class="flash-error">
  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
  <?= e($flashError) ?>
</div>
<?php endif; ?>

<div class="settings-layout" style="grid-template-columns:220px 1fr;gap:24px;align-items:start;display:grid;">

  <!-- ── Onglets latéraux ──────────────────────────────────── -->
  <div class="settings-tabs-v2">
    <?php
    $tabs = [
      'general'   => ['icon' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06A1.65 1.65 0 0 0 12 19.4v.09a2 2 0 0 1-4 0V19.4a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 12H3.51a2 2 0 0 1 0-4H4.6A1.65 1.65 0 0 0 6 6.6a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 12 4.6V3.51a2 2 0 0 1 4 0V4.6a1.65 1.65 0 0 0 1 1.51z"/>', 'label' => 'Général'],
      'plateforme'=> ['icon' => '<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>', 'label' => 'Plateforme'],
      'communaute'=> ['icon' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>', 'label' => 'Communauté'],
      'securite'  => ['icon' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>', 'label' => 'Sécurité'],
      'emails'    => ['icon' => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>', 'label' => 'Emails'],
      'equipe'    => ['icon' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>', 'label' => 'Équipe admin'],
    ];
    foreach ($tabs as $key => $tab): ?>
    <button type="button" class="settings-tab-v2 <?= $activeTab === $key ? 'active' : '' ?>"
            onclick="switchSettingsTab('<?= $key ?>')" id="stab-<?= $key ?>">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <?= $tab['icon'] ?>
      </svg>
      <?= $tab['label'] ?>
    </button>
    <?php endforeach; ?>
  </div>

  <!-- ── Contenu des onglets ──────────────────────────────── -->
  <div class="settings-content-v2">

    <!-- ══════════════════════════════════════════════════════
         GÉNÉRAL
         ══════════════════════════════════════════════════════ -->
    <form id="form-settings-main" method="POST" action="<?= url('/admin/parametres') ?>">
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
    <input type="hidden" name="_tab" id="active-tab-input" value="<?= e($activeTab) ?>">

    <div class="settings-panel-v2" id="spanel-general" style="<?= $activeTab !== 'general' ? 'display:none;' : '' ?>">

      <!-- Informations générales -->
      <div class="settings-section-v2">
        <div class="settings-section-v2-header">
          <h3>Informations générales</h3>
          <p>Identité de la plateforme affichée aux utilisateurs</p>
        </div>
        <div class="settings-section-v2-body">

          <div class="settings-field-v2">
            <div class="settings-field-v2-label">
              <label for="site_name">Nom du site</label>
            </div>
            <div>
              <input type="text" id="site_name" name="site_name" class="form-input"
                     value="<?= e(sv($settings, 'site_name', "Connect'Academia")) ?>">
            </div>
          </div>

          <div class="settings-field-v2" style="align-items:flex-start;">
            <div class="settings-field-v2-label">
              <label for="description_publique">Description publique</label>
            </div>
            <div>
              <textarea id="description_publique" name="description_publique" class="form-textarea"
                        placeholder="Plateforme d'apprentissage et d'entraide pour les élèves de Terminale au Gabon."
                        rows="3"><?= e(sv($settings, 'description_publique', "Plateforme d'apprentissage et d'entraide pour les élèves de Terminale au Gabon.")) ?></textarea>
            </div>
          </div>

          <div class="settings-two-col" style="padding:14px 0;border-bottom:1px solid var(--border);">
            <div>
              <label class="form-label" style="margin-bottom:6px;">Email de contact</label>
              <input type="email" name="email_contact" class="form-input"
                     value="<?= e(sv($settings, 'email_contact', 'contact@connect-academia.ga')) ?>"
                     placeholder="contact@connect-academia.ga">
            </div>
            <div>
              <label class="form-label" style="margin-bottom:6px;">Taille max upload PDF</label>
              <div style="display:flex;align-items:center;gap:8px;">
                <input type="number" name="max_upload_mb" class="form-input"
                       value="<?= e(sv($settings, 'max_upload_mb', '20')) ?>" min="1" max="200" style="max-width:120px;">
                <span style="font-size:13px;color:var(--txt-m);white-space:nowrap;">Mo</span>
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- Fonctionnalités communautaires -->
      <div class="settings-section-v2">
        <div class="settings-section-v2-header">
          <h3>Fonctionnalités communautaires</h3>
          <p>Activez ou désactivez certaines capacités</p>
        </div>
        <div class="settings-section-v2-body">

          <div class="settings-field-v2 settings-toggle-v2">
            <div class="settings-field-v2-label">
              <label>Système de suivi</label>
              <span>Les utilisateurs peuvent se suivre entre eux</span>
            </div>
            <label class="toggle-switch">
              <input type="hidden" name="enable_suivi" value="0">
              <input type="checkbox" name="enable_suivi" value="1"
                     <?= sv($settings, 'enable_suivi', '1') == '1' ? 'checked' : '' ?>>
              <span class="toggle-slider"></span>
            </label>
          </div>

          <div class="settings-field-v2 settings-toggle-v2">
            <div class="settings-field-v2-label">
              <label>Messagerie directe</label>
              <span>Activer les messages privés entre membres</span>
            </div>
            <label class="toggle-switch">
              <input type="hidden" name="enable_messagerie" value="0">
              <input type="checkbox" name="enable_messagerie" value="1"
                     <?= sv($settings, 'enable_messagerie', '0') == '1' ? 'checked' : '' ?>>
              <span class="toggle-slider"></span>
            </label>
          </div>

          <div class="settings-field-v2 settings-toggle-v2">
            <div class="settings-field-v2-label">
              <label>Signalements</label>
              <span>Permettre aux utilisateurs de signaler des contenus</span>
            </div>
            <label class="toggle-switch">
              <input type="hidden" name="enable_signalements" value="0">
              <input type="checkbox" name="enable_signalements" value="1"
                     <?= sv($settings, 'enable_signalements', '1') == '1' ? 'checked' : '' ?>>
              <span class="toggle-slider"></span>
            </label>
          </div>

          <div class="settings-field-v2 settings-toggle-v2">
            <div class="settings-field-v2-label">
              <label>Notifications email</label>
              <span>Envoyer des emails pour les événements importants</span>
            </div>
            <label class="toggle-switch">
              <input type="hidden" name="enable_notif_email" value="0">
              <input type="checkbox" name="enable_notif_email" value="1"
                     <?= sv($settings, 'enable_notif_email', '1') == '1' ? 'checked' : '' ?>>
              <span class="toggle-slider"></span>
            </label>
          </div>

        </div>
      </div>

    </div><!-- /spanel-general -->

    <!-- ══════════════════════════════════════════════════════
         PLATEFORME
         ══════════════════════════════════════════════════════ -->
    <div class="settings-panel-v2" id="spanel-plateforme" style="<?= $activeTab !== 'plateforme' ? 'display:none;' : '' ?>">

      <div class="settings-section-v2">
        <div class="settings-section-v2-header">
          <h3>Période d'essai</h3>
          <p>Accès gratuit avant souscription</p>
        </div>
        <div class="settings-section-v2-body">
          <div class="settings-field-v2">
            <div class="settings-field-v2-label">
              <label for="periode_gratuite_jours">Durée de l'essai gratuit</label>
              <span>Mettre 0 pour désactiver</span>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
              <input type="number" id="periode_gratuite_jours" name="periode_gratuite_jours" class="form-input"
                     value="<?= e(sv($settings, 'periode_gratuite_jours', '1')) ?>" min="0" max="90" style="max-width:120px;">
              <span style="font-size:13px;color:var(--txt-m);">jours</span>
            </div>
          </div>
        </div>
      </div>

      <div class="settings-section-v2">
        <div class="settings-section-v2-header">
          <h3>Tarification</h3>
          <p>Prix des abonnements en Francs CFA (XAF)</p>
        </div>
        <div class="settings-section-v2-body">
          <div class="settings-field-v2">
            <div class="settings-field-v2-label">
              <label for="prix_mensuel_xaf">Abonnement mensuel</label>
              <span>Facturé chaque mois</span>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
              <input type="number" id="prix_mensuel_xaf" name="prix_mensuel_xaf" class="form-input"
                     value="<?= e(sv($settings, 'prix_mensuel_xaf', '2000')) ?>" min="0" step="100" style="max-width:160px;">
              <span style="font-size:13px;color:var(--txt-m);">XAF / mois</span>
            </div>
          </div>
          <div class="settings-field-v2">
            <div class="settings-field-v2-label">
              <label for="prix_annuel_xaf">Abonnement annuel</label>
              <span>Facturé une fois par an</span>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
              <input type="number" id="prix_annuel_xaf" name="prix_annuel_xaf" class="form-input"
                     value="<?= e(sv($settings, 'prix_annuel_xaf', '15000')) ?>" min="0" step="500" style="max-width:160px;">
              <span style="font-size:13px;color:var(--txt-m);">XAF / an</span>
            </div>
          </div>
          <?php
          $mensuel = (int) sv($settings, 'prix_mensuel_xaf', 2000);
          $annuel  = (int) sv($settings, 'prix_annuel_xaf', 15000);
          if ($mensuel > 0 && $annuel > 0):
            $economie = round((1 - $annuel / ($mensuel * 12)) * 100);
          ?>
          <div style="background:var(--ap-xl);border-radius:10px;padding:12px 16px;margin-top:12px;display:flex;align-items:center;gap:10px;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--ap)" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span style="font-size:13px;color:var(--ap);">
              L'abonnement annuel représente <?= $economie ?>% d'économie vs mensuel ×12
            </span>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="settings-section-v2">
        <div class="settings-section-v2-header">
          <h3>Fonctionnalités</h3>
          <p>Activer ou désactiver des modules de la plateforme</p>
        </div>
        <div class="settings-section-v2-body">
          <div class="settings-field-v2 settings-toggle-v2">
            <div class="settings-field-v2-label">
              <label>Chat communautaire</label>
              <span>Accès aux salons de discussion pour les abonnés</span>
            </div>
            <label class="toggle-switch">
              <input type="hidden" name="enable_chat" value="0">
              <input type="checkbox" name="enable_chat" value="1"
                     <?= sv($settings, 'enable_chat', '1') == '1' ? 'checked' : '' ?>>
              <span class="toggle-slider"></span>
            </label>
          </div>
          <div class="settings-field-v2 settings-toggle-v2">
            <div class="settings-field-v2-label">
              <label>Paiement en ligne</label>
              <span>Activation du module Cinetpay</span>
            </div>
            <label class="toggle-switch">
              <input type="hidden" name="enable_paiement" value="0">
              <input type="checkbox" name="enable_paiement" value="1"
                     <?= sv($settings, 'enable_paiement', '0') == '1' ? 'checked' : '' ?>>
              <span class="toggle-slider"></span>
            </label>
          </div>
          <div class="settings-field-v2">
            <div class="settings-field-v2-label">
              <label for="gemini_rate_limit">Limite requêtes IA / minute</label>
              <span>Par utilisateur — protège les quotas Gemini</span>
            </div>
            <div>
              <input type="number" id="gemini_rate_limit" name="gemini_rate_limit_per_minute" class="form-input"
                     value="<?= e(sv($settings, 'gemini_rate_limit_per_minute', '10')) ?>" min="1" max="100" style="max-width:120px;">
            </div>
          </div>
        </div>
      </div>

    </div><!-- /spanel-plateforme -->

    </form><!-- form-settings-main -->

    <!-- ══════════════════════════════════════════════════════
         COMMUNAUTÉ (onglet dédié)
         ══════════════════════════════════════════════════════ -->
    <div class="settings-panel-v2" id="spanel-communaute" style="<?= $activeTab !== 'communaute' ? 'display:none;' : '' ?>">

      <div class="settings-section-v2">
        <div class="settings-section-v2-header">
          <h3>Modération automatique</h3>
          <p>Paramètres de modération du contenu communautaire</p>
        </div>
        <div class="settings-section-v2-body">

          <div class="settings-field-v2 settings-toggle-v2">
            <div class="settings-field-v2-label">
              <label>Approbation avant publication</label>
              <span>Les posts sont soumis à validation avant d'être visibles</span>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" disabled>
              <span class="toggle-slider"></span>
            </label>
          </div>

          <div class="settings-field-v2 settings-toggle-v2">
            <div class="settings-field-v2-label">
              <label>Filtre de contenu</label>
              <span>Détection automatique de contenu inapproprié</span>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" disabled>
              <span class="toggle-slider"></span>
            </label>
          </div>

          <div style="padding-top:14px;">
            <div style="background:var(--amber-bg);border-radius:10px;padding:14px 16px;display:flex;align-items:flex-start;gap:10px;">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--amber)" stroke-width="2" style="flex-shrink:0;margin-top:1px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
              <span style="font-size:13px;color:#92400E;">Ces fonctionnalités avancées sont planifiées pour la prochaine phase — elles seront disponibles après déploiement du module IA de modération.</span>
            </div>
          </div>

        </div>
      </div>

      <div class="settings-section-v2">
        <div class="settings-section-v2-header">
          <h3>Statistiques communauté</h3>
          <p>Indicateurs clés de la vie communautaire</p>
        </div>
        <div class="settings-section-v2-body">
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">
            <?php
            $commStats = [
              ['label' => 'Posts totaux',      'link' => url('/admin/communaute')],
              ['label' => 'Signalements',       'link' => url('/admin/signalements')],
              ['label' => 'Membres actifs',     'link' => url('/admin/utilisateurs')],
            ];
            foreach ($commStats as $cs): ?>
            <a href="<?= $cs['link'] ?>" style="background:var(--bg);border:1px solid var(--border);border-radius:var(--r-sm);padding:16px;text-align:center;text-decoration:none;display:block;transition:all 0.15s;" onmouseover="this.style.borderColor='var(--ap)'" onmouseout="this.style.borderColor='var(--border)'">
              <div style="font-size:11px;color:var(--txt-m);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:4px;"><?= $cs['label'] ?></div>
              <div style="font-size:22px;font-weight:800;font-family:var(--font-head);color:var(--txt);">—</div>
            </a>
            <?php endforeach; ?>
          </div>
          <p style="font-size:12px;color:var(--txt-l);margin-top:12px;">Cliquez sur une carte pour accéder à la section correspondante.</p>
        </div>
      </div>

    </div><!-- /spanel-communaute -->

    <!-- ══════════════════════════════════════════════════════
         SÉCURITÉ
         ══════════════════════════════════════════════════════ -->
    <div class="settings-panel-v2" id="spanel-securite" style="<?= $activeTab !== 'securite' ? 'display:none;' : '' ?>">

      <!-- 2FA -->
      <div class="settings-section-v2">
        <div class="settings-section-v2-header">
          <h3>Authentification à deux facteurs (2FA)</h3>
          <p>Sécurisez votre compte administrateur avec Google Authenticator</p>
        </div>
        <div class="settings-section-v2-body">
          <?php if (!empty($admin['totp_enabled'])): ?>
          <div style="display:flex;align-items:flex-start;gap:16px;padding:20px;background:var(--green-bg);border-radius:12px;border:1px solid rgba(34,197,94,0.2);margin-bottom:20px;">
            <div style="width:40px;height:40px;border-radius:50%;background:var(--green);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <div>
              <strong style="color:var(--green);font-size:14px;">2FA activée sur votre compte</strong>
              <p style="color:var(--txt-m);font-size:13px;margin-top:4px;margin-bottom:12px;">Un code OTP vous sera demandé à chaque connexion. Votre compte est bien protégé.</p>
              <button type="button" class="btn-ghost btn-sm" style="color:var(--red);border-color:var(--red-bg);"
                      onclick="if(confirm('Désactiver la 2FA ? Cela réduit la sécurité de votre compte.')) document.getElementById('form-disable-2fa').submit();">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                Désactiver la 2FA
              </button>
            </div>
          </div>
          <form id="form-disable-2fa" action="<?= url('/admin/parametres/2fa/desactiver') ?>" method="POST" style="display:none;">
            <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
          </form>
          <?php else: ?>
          <div style="display:flex;align-items:flex-start;gap:16px;padding:20px;background:#FFF7ED;border-radius:12px;border:1px solid rgba(234,179,8,0.2);margin-bottom:20px;">
            <div style="width:40px;height:40px;border-radius:50%;background:#F59E0B;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <div>
              <strong style="color:#92400E;font-size:14px;">2FA non activée</strong>
              <p style="color:var(--txt-m);font-size:13px;margin-top:4px;">Activez la 2FA pour renforcer la sécurité de votre compte administrateur.</p>
            </div>
          </div>
          <?php if (!empty($totpQrUrl) && !empty($totpSecret)): ?>
          <div style="display:grid;grid-template-columns:auto 1fr;gap:32px;align-items:start;">
            <div style="text-align:center;">
              <div style="border:2px solid var(--border);border-radius:12px;padding:12px;display:inline-block;background:white;">
                <img src="<?= e($totpQrUrl) ?>" alt="QR Code 2FA" width="180" height="180" style="display:block;">
              </div>
              <p style="font-size:11px;color:var(--txt-l);margin-top:8px;">Scannez avec Google Authenticator</p>
            </div>
            <div>
              <p style="font-size:13px;color:var(--txt-m);margin-bottom:16px;line-height:1.6;">
                1. Installez <strong>Google Authenticator</strong> sur votre téléphone<br>
                2. Scannez ce QR code ou entrez la clé manuellement<br>
                3. Entrez le code à 6 chiffres pour confirmer l'activation
              </p>
              <div style="background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:12px 16px;margin-bottom:16px;">
                <div style="font-size:11px;color:var(--txt-l);margin-bottom:4px;">Clé secrète (saisie manuelle)</div>
                <code style="font-size:13px;font-family:monospace;color:var(--ap);letter-spacing:2px;"><?= e($totpSecret) ?></code>
              </div>
              <form id="form-enable-2fa" action="<?= url('/admin/parametres/2fa/activer') ?>" method="POST">
                <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                <input type="hidden" name="totp_secret_tmp" value="<?= e($totpSecret) ?>">
                <input type="hidden" name="totp_code_confirm" id="sync-totp-code">
                <div style="display:flex;gap:8px;">
                  <input type="text" id="totp-confirm-input" class="form-input" placeholder="000000"
                         maxlength="6" pattern="[0-9]{6}" inputmode="numeric"
                         style="width:130px;letter-spacing:4px;text-align:center;font-size:18px;font-weight:700;">
                  <button type="submit" class="btn-primary">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Activer la 2FA
                  </button>
                </div>
              </form>
              <script>
              document.getElementById('totp-confirm-input')?.addEventListener('input', function() {
                document.getElementById('sync-totp-code').value = this.value;
              });
              </script>
            </div>
          </div>
          <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Changer mot de passe -->
      <div class="settings-section-v2">
        <div class="settings-section-v2-header">
          <h3>Changer le mot de passe</h3>
          <p>Réinitialiser les credentials administrateur</p>
        </div>
        <div class="settings-section-v2-body">
          <form id="form-change-password" action="<?= url('/admin/parametres/password') ?>" method="POST">
            <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
            <div class="settings-field-v2">
              <div class="settings-field-v2-label">
                <label for="current_password">Mot de passe actuel</label>
              </div>
              <div>
                <input type="password" id="current_password" name="current_password" class="form-input"
                       placeholder="••••••••" autocomplete="current-password">
              </div>
            </div>
            <div class="settings-field-v2">
              <div class="settings-field-v2-label">
                <label for="new_password">Nouveau mot de passe</label>
                <span>Minimum 10 caractères</span>
              </div>
              <div>
                <input type="password" id="new_password" name="new_password" class="form-input"
                       placeholder="••••••••" autocomplete="new-password">
              </div>
            </div>
            <div class="settings-field-v2">
              <div class="settings-field-v2-label">
                <label for="confirm_password">Confirmer</label>
              </div>
              <div>
                <input type="password" id="confirm_password" name="confirm_password" class="form-input"
                       placeholder="••••••••" autocomplete="new-password">
              </div>
            </div>
            <div style="display:flex;justify-content:flex-end;margin-top:4px;">
              <button type="submit" class="btn-primary btn-sm">Changer le mot de passe</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Informations système -->
      <div class="settings-section-v2">
        <div class="settings-section-v2-header">
          <h3>Informations système</h3>
          <p>Environnement d'exécution</p>
        </div>
        <div class="settings-section-v2-body">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1px;background:var(--border);border:1px solid var(--border);border-radius:8px;overflow:hidden;">
            <?php
            $sysInfo = [
              'Version PHP'    => PHP_VERSION,
              'Serveur'        => e($_SERVER['SERVER_SOFTWARE'] ?? 'Apache'),
              'Environnement'  => '<span style="color:' . ($_ENV['APP_ENV'] ?? 'production' === 'production' ? 'var(--green)' : '#F59E0B') . ';font-weight:600;">' . ucfirst($_ENV['APP_ENV'] ?? 'production') . '</span>',
              'Fuseau horaire' => date_default_timezone_get(),
              'Heure serveur'  => date('d/m/Y H:i'),
              'Mémoire PHP'    => ini_get('memory_limit'),
            ];
            foreach ($sysInfo as $key => $val): ?>
            <div style="background:var(--card);padding:12px 16px;display:flex;justify-content:space-between;align-items:center;gap:8px;">
              <span style="font-size:12px;color:var(--txt-m);"><?= $key ?></span>
              <span style="font-size:12px;font-weight:600;color:var(--txt);"><?= $val ?></span>
            </div>
            <?php endforeach; ?>
          </div>
          <div style="margin-top:16px;">
            <button type="button" class="btn-ghost btn-sm" onclick="adminClearCache()">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.1"/></svg>
              Vider le cache
            </button>
          </div>
        </div>
      </div>

    </div><!-- /spanel-securite -->

    <!-- ══════════════════════════════════════════════════════
         EMAILS
         ══════════════════════════════════════════════════════ -->
    <div class="settings-panel-v2" id="spanel-emails" style="<?= $activeTab !== 'emails' ? 'display:none;' : '' ?>">

      <div class="settings-section-v2">
        <div class="settings-section-v2-header">
          <h3>Configuration SMTP</h3>
          <p>Paramètres d'envoi des emails transactionnels</p>
        </div>
        <div class="settings-section-v2-body">
          <div style="background:var(--amber-bg);border-radius:10px;padding:14px 16px;display:flex;align-items:center;gap:10px;margin-bottom:16px;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--amber)" stroke-width="2" style="flex-shrink:0;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <span style="font-size:13px;color:#92400E;">Configuration SMTP à définir dans le fichier <code style="font-size:12px;background:rgba(0,0,0,0.06);padding:2px 6px;border-radius:4px;">.env</code></span>
          </div>
          <div class="settings-two-col">
            <div class="form-group">
              <label class="form-label">Hôte SMTP</label>
              <input type="text" class="form-input" placeholder="smtp.gmail.com" disabled value="<?= e(sv($settings, 'smtp_host', '')) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Port</label>
              <input type="number" class="form-input" placeholder="587" disabled value="<?= e(sv($settings, 'smtp_port', '587')) ?>">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Email expéditeur</label>
            <input type="email" class="form-input" placeholder="noreply@connect-academia.ga" disabled value="<?= e(sv($settings, 'smtp_from', '')) ?>">
          </div>
        </div>
      </div>

      <div class="settings-section-v2">
        <div class="settings-section-v2-header">
          <h3>Types de notifications</h3>
          <p>Choisissez quels événements déclenchent un email</p>
        </div>
        <div class="settings-section-v2-body">
          <?php
          $emailNotifs = [
            ['key' => 'email_inscription', 'label' => 'Nouvelle inscription',     'desc' => 'Email de bienvenue à l\'utilisateur',       'default' => '1'],
            ['key' => 'email_signalement',  'label' => 'Signalement critique',      'desc' => 'Alerte à l\'admin pour les signalements',  'default' => '1'],
            ['key' => 'email_ressource',    'label' => 'Ressource publiée',          'desc' => 'Notification aux abonnés du contenu',     'default' => '0'],
          ];
          foreach ($emailNotifs as $n): ?>
          <div class="settings-field-v2 settings-toggle-v2">
            <div class="settings-field-v2-label">
              <label><?= $n['label'] ?></label>
              <span><?= $n['desc'] ?></span>
            </div>
            <label class="toggle-switch">
              <input type="hidden" name="<?= $n['key'] ?>" value="0">
              <input type="checkbox" name="<?= $n['key'] ?>" value="1"
                     <?= sv($settings, $n['key'], $n['default']) == '1' ? 'checked' : '' ?>>
              <span class="toggle-slider"></span>
            </label>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

    </div><!-- /spanel-emails -->

    <!-- ══════════════════════════════════════════════════════
         ÉQUIPE ADMIN
         ══════════════════════════════════════════════════════ -->
    <div class="settings-panel-v2" id="spanel-equipe" style="<?= $activeTab !== 'equipe' ? 'display:none;' : '' ?>">

      <div class="settings-section-v2">
        <div class="settings-section-v2-header" style="padding-bottom:16px;display:flex;align-items:center;justify-content:space-between;padding:20px 24px;">
          <div>
            <h3 style="margin-bottom:2px;">Membres administrateurs</h3>
            <p style="margin-bottom:0;">Gérez les accès au tableau de bord</p>
          </div>
          <button type="button" class="btn-primary btn-sm" data-modal-open="modal-create-admin">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Ajouter un admin
          </button>
        </div>
        <?php
        // Récupération de la liste des admins
        try {
            $admins = (new \Models\Admin())->getAll(20, 0);
        } catch (\Throwable $e) {
            $admins = [];
        }
        ?>
        <div>
          <?php if (empty($admins)): ?>
          <div class="empty-state" style="padding:32px;">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            <p>Aucun administrateur trouvé</p>
          </div>
          <?php endif; ?>
          <?php foreach ($admins as $a):
            $initiales = strtoupper(mb_substr($a['prenom'] ?? 'A', 0, 1) . mb_substr($a['nom'] ?? 'D', 0, 1));
            $isMe = (int)($a['id'] ?? 0) === (int)(\Core\Session::adminId() ?? 0);
          ?>
          <div class="admin-team-row">
            <div class="user-avatar" style="width:36px;height:36px;font-size:12px;background:<?= $isMe ? 'var(--ap)' : '#64748B' ?>;">
              <?= $initiales ?>
            </div>
            <div class="admin-team-info">
              <strong><?= e(($a['prenom'] ?? '') . ' ' . ($a['nom'] ?? '')) ?></strong>
              <span><?= e($a['email'] ?? '') ?></span>
            </div>
            <div class="admin-team-status">
              <span class="badge badge-<?= $a['role'] === 'super_admin' ? 'admin' : 'eleve' ?>" style="font-size:10px;">
                <?= e(ucfirst(str_replace('_', ' ', $a['role'] ?? 'admin'))) ?>
              </span>
              <?php if ($a['totp_enabled']): ?>
                <span title="2FA activée" style="color:var(--green);">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </span>
              <?php endif; ?>
              <?php if (!$isMe): ?>
              <button class="action-btn btn-delete-admin" title="Désactiver ce compte"
                      data-admin-id="<?= (int)$a['id'] ?>"
                      data-name="<?= e(($a['prenom'] ?? '') . ' ' . ($a['nom'] ?? '')) ?>"
                      style="color:var(--red,#EF4444);">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
              </button>
              <?php else: ?>
              <span style="font-size:11px;color:var(--ap);font-weight:600;">Vous</span>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

    </div><!-- /spanel-equipe -->

  </div><!-- .settings-content-v2 -->
</div><!-- .settings-layout -->

<!-- ── Modal Créer admin ───────────────────────────────────── -->
<div class="admin-modal-overlay" id="modal-create-admin">
  <div class="admin-modal">
    <div class="admin-modal-header">
      <h2>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--ap)" stroke-width="2">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
        Ajouter un administrateur
      </h2>
      <button class="modal-close" data-modal-close="modal-create-admin">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="admin-modal-body">
      <form id="form-create-admin">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div class="form-group">
            <label class="form-label">Prénom *</label>
            <input type="text" name="prenom" class="form-input" placeholder="Ex: Jean" required>
          </div>
          <div class="form-group">
            <label class="form-label">Nom *</label>
            <input type="text" name="nom" class="form-input" placeholder="Ex: Obame" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Email *</label>
          <input type="email" name="email" class="form-input" placeholder="Ex: jean.obame@connect-academia.ga" required>
        </div>
        <div class="form-group">
          <label class="form-label">Rôle *</label>
          <select name="role" class="form-select" required>
            <option value="admin">Admin</option>
            <option value="moderateur">Modérateur</option>
            <option value="super_admin">Super Admin</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Mot de passe temporaire *</label>
          <input type="password" name="password" class="form-input" placeholder="Min. 10 caractères" minlength="10" required>
          <span style="font-size:11px;color:var(--txt-m);margin-top:4px;display:block;">À transmettre de façon sécurisée au nouveau membre.</span>
        </div>
      </form>
    </div>
    <div class="admin-modal-footer">
      <button type="button" class="btn-ghost" data-modal-close="modal-create-admin">Annuler</button>
      <button type="button" class="btn-primary" id="btn-submit-create-admin">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        Créer le compte
      </button>
    </div>
  </div>
</div>

<script>
function switchSettingsTab(tab) {
  // Masquer tous les panels
  document.querySelectorAll('.settings-panel-v2').forEach(p => p.style.display = 'none');
  // Désactiver tous les boutons
  document.querySelectorAll('.settings-tab-v2').forEach(b => b.classList.remove('active'));
  // Afficher le panel actif
  const panel = document.getElementById('spanel-' + tab);
  if (panel) panel.style.display = '';
  // Activer le bouton
  const btn = document.getElementById('stab-' + tab);
  if (btn) btn.classList.add('active');
  // Mettre à jour le champ caché
  const input = document.getElementById('active-tab-input');
  if (input) input.value = tab;
}

function adminClearCache() {
  fetch(window.CA_ADMIN.baseUrl + '/admin/api/cache/clear', {
    method: 'POST',
    headers: { 'X-CSRF-Token': window.CA_ADMIN.csrfToken, 'Content-Type': 'application/json' }
  }).then(r => r.json()).then(d => {
    window.showToast?.(d.message || 'Cache vidé', d.success ? 'success' : 'error');
  }).catch(() => window.showToast?.('Erreur lors du vidage du cache', 'error'));
}
</script>
