<?php
/**
 * Écran de scan du guichet (spec §8.5).
 *
 * ── Ce que l'écran doit être ───────────────────────────────────────────────
 * Un agent le garde ouvert toute la journée en période de rentrée, debout,
 * avec quelqu'un devant lui. L'écran a donc UN sujet : retrouver un dossier.
 * Il n'offre pas deux chemins équivalents côte à côte — il en offre un
 * principal, la saisie du code, qui fonctionne toujours ; et un raccourci, la
 * caméra, quand le navigateur sait décoder. D'où le panneau unique plutôt que
 * deux cartes jumelles : deux options présentées à l'identique obligent à
 * choisir, et choisir prend du temps qu'on n'a pas au guichet.
 *
 * ── Écart assumé avec la spec : pas de html5-qrcode ────────────────────────
 * La spec recommande `html5-qrcode`. Elle n'est pas retenue, pour trois
 * raisons propres à ce projet :
 *
 *  1. La politique de sécurité de contenu du site interdit les scripts de
 *     domaines tiers ; il faudrait vendoriser ~300 Ko de JavaScript, chargés à
 *     chaque ouverture d'un écran utilisé toute la journée, sur des connexions
 *     mobiles gabonaises.
 *  2. Le QR code contient une URL. N'IMPORTE QUEL appareil photo de téléphone
 *     l'ouvre déjà, sans application ni bibliothèque.
 *  3. Là où le navigateur sait décoder lui-même (`BarcodeDetector`), on
 *     l'utilise : zéro octet de dépendance.
 *
 * Résultat : trois chemins, du plus rapide au plus robuste. Aucun ne dépend
 * d'un CDN, et le dernier fonctionne même avec un document abîmé.
 */
$recents = $recents ?? [];
?>

<div class="scan-page">

  <header class="scan-head">
    <h1>Vérifier un récépissé</h1>
    <p>Saisissez le code imprimé sous le QR code, ou scannez-le si votre navigateur
    sait le lire. L'état réel du dossier s'affiche, pas celui du papier.</p>
  </header>

  <?php if (!empty($erreur)): ?>
    <div class="scan-alerte" role="alert">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v5M12 16h.01"/></svg>
      <span><?= e($erreur) ?></span>
    </div>
  <?php endif; ?>

  <!-- ══ Panneau de recherche ══
       Un seul bloc, deux rangs. Le rang du haut n'apparaît que si le
       navigateur sait décoder ; le rang du bas marche partout, toujours. -->
  <section class="scan-panneau">

    <div class="scan-camera" id="scan-camera-bloc" hidden>
      <div class="scan-viseur">
        <video id="scan-video" playsinline muted></video>
        <div class="scan-cadre" aria-hidden="true"></div>
      </div>
      <div class="scan-camera-barre">
        <button type="button" class="scan-btn scan-btn-second" id="scan-demarrer">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
          Scanner avec la caméra
        </button>
        <p class="scan-etat" id="scan-etat" role="status" aria-live="polite"></p>
      </div>
    </div>

    <form method="POST" action="<?= url('verification/code-court') ?>" class="scan-saisie" id="scan-form-code">
      <input type="hidden" name="_csrf_token" value="<?= e($csrf) ?>">
      <label for="code_court">Code du récépissé</label>
      <div class="scan-champ">
        <input type="text" name="code_court" id="code_court"
               placeholder="ABCD-EFGH" maxlength="9"
               autocomplete="off" autocapitalize="characters" spellcheck="false"
               aria-describedby="scan-aide-code" required>
        <button type="submit" class="scan-btn scan-btn-primaire">Vérifier</button>
      </div>
      <p class="scan-aide" id="scan-aide-code">
        Huit caractères, sans I, O, 0 ni 1 : aucune confusion possible à la
        lecture, ni au téléphone.
      </p>
    </form>

  </section>

  <!-- Repli universel : le navigateur ne sait pas décoder dans la page. -->
  <section class="scan-repli" id="scan-natif-bloc" hidden>
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM18 18h3v3h-3z"/></svg>
    <div>
      <b>Ce navigateur ne lit pas les QR codes dans la page.</b>
      Ouvrez l'appareil photo de votre téléphone et visez le récépissé : il vous
      amène directement sur la fiche du dossier.
    </div>
  </section>

  <?php if ($recents !== []): ?>
  <section class="scan-recents">
    <h2>Derniers dossiers vérifiés</h2>
    <ul>
      <?php foreach ($recents as $scan): ?>
        <li>
          <a href="<?= url('gestionnaire/candidatures/' . (int)$scan['dossier_id']) ?>">
            <span class="scan-ref"><?= e($scan['reference'] ?? '—') ?></span>
            <span class="scan-nom"><?= e($scan['nom_complet'] ?? '—') ?></span>
          </a>
          <span class="scan-quand"><?= e(date('d/m à H\hi', strtotime((string)$scan['scanne_le']))) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

</div>

<link rel="stylesheet" href="<?= asset('orientation/scanner.css') ?>">

<script>
(function () {
    'use strict';

    var BASE = <?= json_encode(BASE_URL, JSON_UNESCAPED_SLASHES) ?>;

    // ── Mise en forme du code pendant la frappe ───────────────────────────
    // L'agent lit un code imprimé « ABCD-EFGH ». On accepte tout (espaces,
    // minuscules, avec ou sans tiret) et on normalise à l'écran ; le serveur
    // renormalise de toute façon.
    var champ = document.getElementById('code_court');
    if (champ) {
        champ.addEventListener('input', function () {
            var brut = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 8);
            this.value = brut.length > 4 ? brut.slice(0, 4) + '-' + brut.slice(4) : brut;
        });
        champ.focus();
    }

    // ── Décodage dans la page, si le navigateur sait le faire ─────────────
    var bloc    = document.getElementById('scan-camera-bloc');
    var natif   = document.getElementById('scan-natif-bloc');
    var video   = document.getElementById('scan-video');
    var bouton  = document.getElementById('scan-demarrer');
    var etat    = document.getElementById('scan-etat');

    var supporte = 'BarcodeDetector' in window
        && navigator.mediaDevices
        && typeof navigator.mediaDevices.getUserMedia === 'function';

    if (!supporte) {
        if (natif) { natif.hidden = false; }
        return;
    }
    if (bloc) { bloc.hidden = false; }

    var flux = null;
    var arret = false;

    function dire(message) { if (etat) { etat.textContent = message; } }

    function stopper() {
        arret = true;
        if (flux) {
            flux.getTracks().forEach(function (piste) { piste.stop(); });
            flux = null;
        }
    }

    // Le QR encode une URL absolue vers /v/{token}. On n'en garde que le
    // chemin, et on refuse tout ce qui ne ressemble pas à cette route : un QR
    // malveillant collé sur un faux récépissé ne doit pas pouvoir rediriger
    // l'agent vers un site tiers.
    function destination(valeur) {
        var chemin = null;

        try {
            chemin = new URL(valeur, window.location.origin).pathname;
        } catch (e) {
            chemin = String(valeur || '');
        }

        var m = chemin.match(/\/v\/([0-9a-f]{48})$/i);
        return m ? BASE + '/v/' + m[1].toLowerCase() : null;
    }

    bouton.addEventListener('click', function () {
        bouton.disabled = true;
        dire('Ouverture de la caméra…');

        navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment' }
        }).then(function (media) {
            flux = media;
            video.srcObject = media;
            bloc.classList.add('scan-actif');
            dire('Présentez le QR code dans le cadre.');
            return video.play();
        }).then(function () {
            var detecteur = new window.BarcodeDetector({ formats: ['qr_code'] });

            (function boucle() {
                if (arret || !flux) { return; }

                detecteur.detect(video).then(function (codes) {
                    if (!codes.length) { return; }

                    var url = destination(codes[0].rawValue);
                    if (!url) {
                        dire('Ce QR code ne correspond pas à un récépissé Connect\'Academia.');
                        return;
                    }

                    stopper();
                    dire('Dossier trouvé, ouverture…');
                    window.location.href = url;
                }).catch(function () {
                    /* Image floue ou trame partielle : on retente au tour suivant. */
                }).then(function () {
                    if (!arret) { setTimeout(boucle, 220); }
                });
            }());
        }).catch(function () {
            bouton.disabled = false;
            bloc.hidden = true;
            if (natif) { natif.hidden = false; }
            dire('');
        });
    });

    // Libérer la caméra dès que la page part : sur un téléphone de guichet,
    // un flux vidéo oublié vide la batterie en une matinée.
    window.addEventListener('pagehide', stopper);
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) { stopper(); }
    });
}());
</script>
