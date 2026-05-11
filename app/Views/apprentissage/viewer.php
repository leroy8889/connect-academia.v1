<?php
$ressource = $ressource  ?? [];
$autres    = $autres     ?? [];
$estFavori = $est_favori ?? false;

$type  = $ressource['type'] ?? 'cours';
$titre = $ressource['titre'] ?? '';
$path  = $ressource['fichier_path'] ?? '';
$pct   = (int) ($ressource['pourcentage'] ?? 0);

$isVideo = preg_match('#\.(mp4|webm|ogg)$#i', $path) || preg_match('#(youtube|youtu\.be|vimeo)#i', $path);
$isPdf   = !$isVideo && !empty($path);

$fileUrl = '';
if (!empty($path)) {
    if (preg_match('#^https?://#i', $path)) {
        $fileUrl = $path; // URL absolue (YouTube, Vimeo, etc.)
    } else {
        // Chemin relatif ou absolu → toujours sous /public/
        $fileUrl = url('/public/' . ltrim($path, '/'));
    }
}

$typeLabels = [
    'cours'            => 'Cours',
    'td'               => 'TD',
    'ancienne_epreuve' => 'Ancienne épreuve',
    'corrige'          => 'Corrigé',
];
?>

<!-- KaTeX — rendu des formules mathématiques dans le chatbot BACY -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.css" crossorigin="anonymous">

<div class="viewer-layout" id="viewer-root">

  <!-- ══ SIDEBAR ══════════════════════════════════════════════ -->
  <aside class="viewer-sidebar">

    <div class="viewer-sidebar__header">
      <a href="javascript:history.back()" class="viewer-back-link">
        <i data-lucide="arrow-left" style="width:14px;height:14px;flex-shrink:0"></i>
        Retour aux ressources
      </a>
      <div class="viewer-sidebar__title"><?= e($titre) ?></div>
      <div class="viewer-sidebar__meta">
        <span class="badge badge-<?= e($type) ?>"><?= e($typeLabels[$type] ?? ucfirst(str_replace('_', ' ', $type))) ?></span>
        <?php if (!empty($ressource['matiere'])): ?>
          <span class="viewer-meta-text"><?= e($ressource['matiere']) ?></span>
        <?php endif; ?>
        <?php if (!empty($ressource['serie'])): ?>
          <span class="badge badge-serie-<?= e($ressource['serie']) ?>">Tle <?= e($ressource['serie']) ?></span>
        <?php endif; ?>
      </div>
    </div>

    <div class="viewer-sidebar__actions">
      <button
        id="btn-favori"
        onclick="toggleFavori(<?= (int)($ressource['id'] ?? 0) ?>, this)"
        class="viewer-fav-btn <?= $estFavori ? 'active' : '' ?>"
        title="<?= $estFavori ? 'Retirer des favoris' : 'Ajouter aux favoris' ?>">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="<?= $estFavori ? '#F59E0B' : 'none' ?>" stroke="<?= $estFavori ? '#F59E0B' : 'currentColor' ?>" stroke-width="2">
          <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
        </svg>
        <span id="btn-favori-label"><?= $estFavori ? 'Dans les favoris' : 'Ajouter aux favoris' ?></span>
      </button>
    </div>

    <?php if (!empty($autres)): ?>
      <div class="viewer-sidebar__section">
        <p class="viewer-section-label">Du même cours</p>
        <?php foreach ($autres as $a): ?>
          <a href="<?= url('/apprentissage/viewer/' . (int)$a['id']) ?>" class="viewer-sidebar__list-item">
            <div class="viewer-item-icon">
              <i data-lucide="file-text" style="width:15px;height:15px;"></i>
            </div>
            <div class="viewer-item-info">
              <div class="viewer-sidebar__list-title"><?= e($a['titre']) ?></div>
              <span class="badge badge-<?= e($a['type'] ?? 'cours') ?>" style="margin-top:4px;display:inline-block"><?= e($typeLabels[$a['type'] ?? ''] ?? ucfirst(str_replace('_', ' ', $a['type'] ?? ''))) ?></span>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="viewer-empty-resources">
        <i data-lucide="inbox" style="width:28px;height:28px;"></i>
        <span>Aucune autre ressource</span>
      </div>
    <?php endif; ?>

  </aside>

  <!-- ══ ZONE PRINCIPALE ══════════════════════════════════════ -->
  <div class="viewer-main">

    <div class="viewer-toolbar">
      <div class="viewer-toolbar__progress">
        <div class="ca-progress" style="flex:1;min-width:60px">
          <div class="ca-progress-fill" id="progressBar" style="width:<?= $pct ?>%"></div>
        </div>
        <span class="viewer-toolbar__pct" id="progressText"><?= $pct ?>%</span>
      </div>

      <?php if ($pct < 100): ?>
        <button onclick="markComplete()" class="viewer-toolbar__btn viewer-toolbar__btn--primary">
          <i data-lucide="check" style="width:14px;height:14px;"></i>
          Terminé
        </button>
      <?php else: ?>
        <span class="viewer-complete-badge">
          <i data-lucide="check-circle-2" style="width:15px;height:15px;"></i>
          Terminé
        </span>
      <?php endif; ?>

      <button onclick="toggleFullscreen()" class="viewer-toolbar__btn viewer-toolbar__btn--ghost" title="Plein écran">
        <i data-lucide="maximize-2" style="width:15px;height:15px;"></i>
      </button>

      <button id="ia-toggle" class="viewer-toolbar__btn viewer-toolbar__btn--ghost" title="Assistant IA BACY">
        <i data-lucide="message-square" style="width:15px;height:15px;"></i>
        BACY
      </button>

      <button id="btn-minimize-resource" onclick="toggleResourceMinimize()" class="viewer-toolbar__btn viewer-toolbar__btn--ghost" title="Agrandir le chat">
        <i data-lucide="panel-right-close" style="width:15px;height:15px;"></i>
      </button>
    </div>

    <div class="viewer-frame" id="viewer-frame">
      <?php if (!empty($fileUrl)): ?>
        <?php if ($isVideo): ?>
          <?php if (preg_match('#(youtube\.com|youtu\.be)#i', $fileUrl)): ?>
            <?php
              preg_match('#(?:v=|youtu\.be/)([a-zA-Z0-9_-]{11})#', $fileUrl, $ym);
              $ytId = $ym[1] ?? '';
            ?>
            <iframe src="https://www.youtube.com/embed/<?= e($ytId) ?>?rel=0" allowfullscreen></iframe>
          <?php elseif (preg_match('#vimeo\.com/(\d+)#i', $fileUrl, $vm)): ?>
            <iframe src="https://player.vimeo.com/video/<?= e($vm[1]) ?>" allowfullscreen></iframe>
          <?php else: ?>
            <video controls style="width:100%;height:100%;background:#000">
              <source src="<?= e($fileUrl) ?>">
              Votre navigateur ne supporte pas la lecture vidéo.
            </video>
          <?php endif; ?>
        <?php else: ?>
          <div id="pdf-container" class="pdf-js-container">
            <div id="pdf-loading" class="pdf-loading-state">
              <div class="pdf-spinner"></div>
              <span>Chargement du document…</span>
            </div>
            <div id="pdf-error" class="pdf-error-state" style="display:none">
              <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
              <span id="pdf-error-msg">Erreur de chargement du PDF.</span>
            </div>
            <div id="pdf-pages"></div>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <div class="viewer-no-content">
          <div class="viewer-no-content__icon">
            <i data-lucide="file-x" style="width:32px;height:32px;"></i>
          </div>
          <p class="viewer-no-content__title">Fichier non disponible</p>
          <p class="viewer-no-content__sub">Ce contenu n'a pas encore été mis en ligne par l'administrateur.</p>
        </div>
      <?php endif; ?>
    </div>

  </div>

  <!-- ══ PANEL IA ══════════════════════════════════════════════ -->
  <aside class="ia-panel ia-panel--hidden" id="ia-panel">

    <div class="ia-panel__handle" id="ia-panel-handle"></div>

    <div class="ia-panel__header">
      <div class="ia-panel__badge">
        <img src="<?= asset('images/logo-officiel.png') ?>" alt="Connect'Academia" style="width:28px;height:28px;object-fit:contain;">
      </div>
      <div>
        <div class="ia-panel__title">Assistant Connect'Academia</div>
        <div class="ia-panel__sub">Posez vos questions sur cette ressource</div>
      </div>
      <div class="ia-panel__header-actions">
        <button id="ia-new-chat" class="ia-new-chat-btn" title="Nouvelle conversation">
          <i data-lucide="plus-circle" style="width:16px;height:16px;"></i>
        </button>
        <button id="ia-close-mobile" style="display:none;background:none;border:none;cursor:pointer;color:var(--text-muted);padding:6px;border-radius:var(--radius-sm);transition:all 150ms ease" title="Fermer">
          <i data-lucide="x" style="width:16px;height:16px;"></i>
        </button>
        <button id="btn-restore-resource" style="display:none;background:none;border:none;cursor:pointer;color:var(--text-muted);padding:6px;border-radius:var(--radius-sm);transition:all 150ms ease" title="Restaurer la ressource" onclick="toggleResourceMinimize()">
          <i data-lucide="panel-left-open" style="width:16px;height:16px;"></i>
        </button>
      </div>
    </div>

    <div class="ia-messages" id="ia-messages">
      <div class="ia-msg ia-msg--ia">
        <div class="ia-msg__avatar">
          <img src="<?= asset('images/logo-officiel.png') ?>" alt="BACY" style="width:22px;height:22px;object-fit:contain;">
        </div>
        <div class="ia-msg__bubble">
          Bonjour ! Je suis l'assistant Connect'Academia, votre assistant IA pour <strong><?= e($titre) ?></strong>.
          Posez-moi vos questions, je suis là pour vous aider à comprendre le cours.
        </div>
      </div>
    </div>

    <div class="ia-input">
      <div class="ia-image-preview" id="ia-image-preview">
        <img id="ia-image-thumb" src="" alt="Image jointe">
        <button type="button" id="ia-image-remove" class="ia-image-remove" title="Supprimer l'image" aria-label="Supprimer l'image">
          <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>
      <div class="ia-input__row">
        <input type="file" id="ia-image-input" accept="image/*" style="display:none" aria-label="Choisir une image">
        <button type="button" id="ia-image-btn" class="ia-image-btn" title="Joindre une image (JPG, PNG, GIF, WEBP…)">
          <i data-lucide="image" style="width:16px;height:16px;"></i>
        </button>
        <textarea id="ia-textarea" placeholder="Posez votre question…" rows="1"></textarea>
        <button id="ia-send" disabled title="Envoyer (Entrée)">
          <i data-lucide="send" style="width:15px;height:15px;"></i>
        </button>
      </div>
    </div>
  </aside>

  <button class="ia-mobile-fab" id="ia-mobile-fab" title="Ouvrir l'assistant BACY">
    <img src="<?= asset('images/logo-officiel.png') ?>" alt="BACY" style="width:28px;height:28px;object-fit:contain;">
  </button>

  <div class="ia-mobile-overlay" id="ia-mobile-overlay"></div>

</div>

<style>
#main-content { padding-bottom: 0 !important; overflow: hidden; }

/* ── PDF.js renderer ─────────────────────────────────────── */
.pdf-js-container {
  width: 100%;
  height: 100%;
  overflow-y: auto;
  overflow-x: hidden;
  background: #525659;
  padding: 8px;
  box-sizing: border-box;
  -webkit-overflow-scrolling: touch;
  overscroll-behavior: contain;
}

.pdf-loading-state,
.pdf-error-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 200px;
  gap: 12px;
  color: #fff;
  font-family: 'Montserrat', sans-serif;
  font-size: 14px;
  text-align: center;
  padding: 20px;
}

.pdf-error-state { color: #fca5a5; }

.pdf-spinner {
  width: 36px;
  height: 36px;
  border: 3px solid rgba(255,255,255,0.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: pdf-spin 0.75s linear infinite;
  flex-shrink: 0;
}

@keyframes pdf-spin { to { transform: rotate(360deg); } }

.pdf-page-wrapper {
  margin-bottom: 8px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.4);
  background: #fff;
  line-height: 0;
  width: 100%;
}

.pdf-page-wrapper canvas {
  display: block;
  width: 100%;
  height: auto;
}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.js" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.2/html2pdf.bundle.min.js" crossorigin="anonymous"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    initViewer(
        <?= (int)($ressource['id'] ?? 0) ?>,
        <?= (int)($ressource['derniere_page'] ?? 1) ?>,
        <?= json_encode($fileUrl) ?>,
        <?= $pct ?>
    );

    <?php if ($isPdf && !empty($fileUrl)): ?>
    renderPdfJs(<?= json_encode($fileUrl) ?>);
    <?php endif; ?>
});

/* ── PDF.js: render all pages as canvas elements ──────────── */
async function renderPdfJs(pdfUrl) {
    const container   = document.getElementById('pdf-container');
    const loadingEl   = document.getElementById('pdf-loading');
    const errorEl     = document.getElementById('pdf-error');
    const errorMsgEl  = document.getElementById('pdf-error-msg');
    const pagesEl     = document.getElementById('pdf-pages');

    function showError(msg) {
        if (loadingEl) loadingEl.style.display = 'none';
        if (errorEl)   { errorEl.style.display = 'flex'; }
        if (errorMsgEl) errorMsgEl.textContent = msg;
    }

    if (typeof pdfjsLib === 'undefined') {
        showError('Lecteur PDF non disponible. Veuillez recharger la page.');
        return;
    }

    pdfjsLib.GlobalWorkerOptions.workerSrc =
        'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    try {
        const pdf = await pdfjsLib.getDocument({ url: pdfUrl, withCredentials: true }).promise;

        if (loadingEl) loadingEl.style.display = 'none';

        for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
            const page = await pdf.getPage(pageNum);

            /* Scale to fill container width */
            const containerWidth = (container ? container.clientWidth : window.innerWidth) - 16;
            const baseVp  = page.getViewport({ scale: 1 });
            const scale   = Math.max(containerWidth / baseVp.width, 0.5);
            const viewport = page.getViewport({ scale });

            /* Retina/HiDPI — cap at 2× for memory safety */
            const dpr = Math.min(window.devicePixelRatio || 1, 2);

            const wrapper = document.createElement('div');
            wrapper.className = 'pdf-page-wrapper';

            const canvas = document.createElement('canvas');
            canvas.width  = Math.floor(viewport.width  * dpr);
            canvas.height = Math.floor(viewport.height * dpr);
            canvas.style.width  = viewport.width  + 'px';
            canvas.style.height = viewport.height + 'px';

            wrapper.appendChild(canvas);
            pagesEl.appendChild(wrapper);

            const ctx = canvas.getContext('2d');
            await page.render({
                canvasContext: ctx,
                transform:    dpr !== 1 ? [dpr, 0, 0, dpr, 0, 0] : null,
                viewport,
            }).promise;
        }
    } catch (err) {
        showError('Impossible de charger ce document. Vérifiez votre connexion et rechargez.');
        console.error('[PDF.js]', err);
    }
}

function markComplete() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    fetch(window.CA.baseUrl + '/api/apprentissage/progression', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': csrf,
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            ressource_id: <?= (int)($ressource['id'] ?? 0) ?>,
            action: 'complete',
        }),
    }).then(r => r.json()).then(data => {
        if (data.success) {
            updateProgressBar(100);
            const btn = document.querySelector('.viewer-toolbar__btn--primary');
            if (btn) {
                btn.outerHTML = '<span class="viewer-complete-badge">'
                    + '<i data-lucide="check-circle-2" style="width:15px;height:15px;"></i>Terminé</span>';
                if (window.lucide) lucide.createIcons();
            }
            showToast('Cours marqué comme terminé ✓');
        }
    }).catch(() => showToast('Erreur lors de la mise à jour', 'error'));
}
</script>
