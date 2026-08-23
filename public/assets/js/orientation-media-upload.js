/**
 * Upload DIRECT navigateur → Backblaze B2 pour les médias d'établissement
 * (logo, photos campus, vidéo de présentation).
 *
 * Pourquoi : à ~1 Mo/s, une vidéo (jusqu'à 500 Mo) transitant par PHP dépasse
 * le timeout FastCGI/nginx → 500, établissement jamais créé. Ici le gros
 * fichier part DIRECTEMENT vers B2 via une URL PUT présignée ; le formulaire
 * n'envoie plus que les clés B2 (quelques octets) → aucun timeout possible.
 *
 * Auto-configuration : s'attache à #or-create-form ou #or-edit-form si présent.
 * Repli : si le JS échoue, la case de départ (upload serveur via $_FILES) reste
 * fonctionnelle côté PHP pour les petits fichiers / navigateurs sans JS.
 */
(function () {
    'use strict';

    var form = document.getElementById('or-create-form') || document.getElementById('or-edit-form');
    if (!form) return;

    var btn        = document.getElementById('or-submit-btn');
    var presignUrl = form.getAttribute('data-presign-url');
    var csrfEl     = form.querySelector('input[name="_csrf_token"]');
    if (!btn || !presignUrl || !csrfEl) return;
    var csrf = csrfEl.value;

    var MAX = { logo: 10 * 1024 * 1024, image: 10 * 1024 * 1024, video: 500 * 1024 * 1024 };
    var submitted = false; // true = médias déjà poussés, on laisse partir le form

    var logoInput   = form.querySelector('input[name="logo"]');
    var campusInput = form.querySelector('input[name="images_campus[]"]');
    var videoInput  = form.querySelector('input[name="video"]');

    var SPIN = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:spin 1s linear infinite;margin-right:6px;vertical-align:-2px"><path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" opacity=".25"/><path d="M21 12a9 9 0 0 1-9 9"/></svg>';

    // ── Étiquette de progression par zone ────────────────────────────────
    function labelEl(kind) {
        if (kind === 'logo')  { var z = document.getElementById('logo-zone'); return z ? z.querySelector('.or-upload-label') : null; }
        if (kind === 'video') return document.getElementById('video-label');
        if (kind === 'image') return document.getElementById('campus-label');
        return null;
    }
    function mkProgress(kind, prefixTxt) {
        var el = labelEl(kind);
        return function (frac) {
            if (!el) return;
            el.textContent = prefixTxt + ' — envoi ' + Math.round(frac * 100) + '%';
        };
    }

    // ── 1. Demande d'URL présignée ───────────────────────────────────────
    function presign(kind, file) {
        return fetch(presignUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ kind: kind, contentType: file.type, filename: file.name })
        }).then(function (r) {
            return r.json().catch(function () { throw new Error('Réponse serveur illisible.'); })
                .then(function (j) {
                    if (!r.ok || !j || !j.success) {
                        throw new Error(j && j.error ? j.error : 'Signature refusée (HTTP ' + r.status + ')');
                    }
                    return j;
                });
        });
    }

    // ── 2. PUT direct vers B2 avec progression ───────────────────────────
    function putToB2(info, file, onProgress) {
        return new Promise(function (resolve, reject) {
            var xhr = new XMLHttpRequest();
            xhr.open('PUT', info.url, true);
            xhr.setRequestHeader('Content-Type', info.contentType);
            if (xhr.upload) {
                xhr.upload.onprogress = function (e) {
                    if (e.lengthComputable && onProgress) onProgress(e.loaded / e.total);
                };
            }
            xhr.onload = function () {
                if (xhr.status >= 200 && xhr.status < 300) resolve(info.key);
                else reject(new Error('Upload B2 refusé (HTTP ' + xhr.status + ').'));
            };
            xhr.onerror = function () { reject(new Error('Réseau interrompu pendant l\'upload.')); };
            xhr.ontimeout = function () { reject(new Error('Upload trop long, réessaie.')); };
            xhr.send(file);
        });
    }

    // ── Upload complet d'un fichier → renvoie la clé B2 ──────────────────
    function uploadFile(kind, file, onProgress) {
        if (file.size > MAX[kind]) {
            return Promise.reject(new Error('« ' + file.name + ' » trop volumineux (max ' + (MAX[kind] / 1048576) + ' Mo).'));
        }
        return presign(kind, file).then(function (info) { return putToB2(info, file, onProgress); });
    }

    function addHidden(name, value) {
        var h = document.createElement('input');
        h.type = 'hidden';
        h.name = name;
        h.value = value;
        form.appendChild(h);
    }

    // ── Interception de la soumission ────────────────────────────────────
    form.addEventListener('submit', function (ev) {
        if (submitted) return; // 2e passage (form.submit programmatique) → laisser partir

        var hasLogo   = logoInput   && logoInput.files   && logoInput.files[0];
        var hasCampus = campusInput && campusInput.files && campusInput.files.length;
        var hasVideo  = videoInput  && videoInput.files  && videoInput.files[0];

        // Aucun média sélectionné (édition sans changement de fichier) → form normal
        if (!hasLogo && !hasCampus && !hasVideo) {
            btn.disabled = true;
            btn.innerHTML = SPIN + 'Enregistrement…';
            return;
        }

        ev.preventDefault();
        btn.disabled = true;
        btn.innerHTML = SPIN + 'Envoi des médias…';

        var tasks = [];

        if (hasLogo) {
            tasks.push(uploadFile('logo', logoInput.files[0], mkProgress('logo', 'Logo'))
                .then(function (key) { addHidden('logo_key', key); }));
        }
        if (hasCampus) {
            var files = Array.prototype.slice.call(campusInput.files, 0, 10);
            files.forEach(function (f) {
                tasks.push(uploadFile('image', f, mkProgress('image', 'Photos'))
                    .then(function (key) { addHidden('images_keys[]', key); }));
            });
        }
        if (hasVideo) {
            tasks.push(uploadFile('video', videoInput.files[0], mkProgress('video', 'Vidéo'))
                .then(function (key) { addHidden('video_key', key); }));
        }

        Promise.all(tasks).then(function () {
            // Désactiver les <input file> : leurs octets ne repartent PAS via le
            // formulaire (le point clé — c'est ce qui supprime le timeout).
            if (logoInput)   logoInput.disabled = true;
            if (campusInput) campusInput.disabled = true;
            if (videoInput)  videoInput.disabled = true;

            submitted = true;
            btn.innerHTML = SPIN + 'Enregistrement…';
            form.submit();
        }).catch(function (err) {
            btn.disabled = false;
            btn.innerHTML = 'Réessayer';
            alert('Envoi des médias échoué : ' + err.message + '\n\nRien n\'a été créé. Vérifie ta connexion et réessaie.');
        });
    });
})();
