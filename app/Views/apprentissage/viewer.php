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

<div class="viewer-layout" id="viewer-root">

  <!-- ══ SIDEBAR GAUCHE ═══════════════════════════════════════ -->
  <aside class="viewer-sidebar">

    <div class="viewer-sidebar__header">
      <div style="margin-bottom:10px">
        <a href="javascript:history.back()" style="font-size:12px;color:#6B7280;text-decoration:none;display:inline-flex;align-items:center;gap:4px;transition:color .15s" onmouseover="this.style.color='#8B52FA'" onmouseout="this.style.color='#6B7280'">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
          Retour aux ressources
        </a>
      </div>
      <div class="viewer-sidebar__title"><?= e($titre) ?></div>
      <div class="viewer-sidebar__meta">
        <span class="badge badge-<?= e($type) ?>"><?= e($typeLabels[$type] ?? ucfirst(str_replace('_', ' ', $type))) ?></span>
        <?php if (!empty($ressource['matiere'])): ?>
          <span><?= e($ressource['matiere']) ?></span>
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
        class="viewer-toolbar__btn viewer-toolbar__btn--star <?= $estFavori ? 'active' : '' ?>"
        title="<?= $estFavori ? 'Retirer des favoris' : 'Ajouter aux favoris' ?>"
        style="flex:1;padding:8px 12px;display:flex;align-items:center;justify-content:center;gap:6px;border-radius:10px">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="<?= $estFavori ? '#F59E0B' : 'none' ?>" stroke="<?= $estFavori ? '#F59E0B' : 'currentColor' ?>" stroke-width="2">
          <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
        </svg>
        <span id="btn-favori-label"><?= $estFavori ? 'Dans les favoris' : 'Ajouter aux favoris' ?></span>
      </button>
    </div>

    <!-- Autres ressources de la même matière -->
    <?php if (!empty($autres)): ?>
      <div class="viewer-sidebar__section">
        <h4>Du même cours</h4>
        <?php foreach ($autres as $a): ?>
          <a href="<?= url('/apprentissage/viewer/' . (int)$a['id']) ?>" class="viewer-sidebar__list-item">
            <div style="width:32px;height:32px;background:#F3EFFF;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#8B52FA;flex-shrink:0">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            </div>
            <div style="min-width:0">
              <div class="viewer-sidebar__list-title"><?= e($a['titre']) ?></div>
              <span class="badge badge-<?= e($a['type'] ?? 'cours') ?>" style="margin-top:3px"><?= e($typeLabels[$a['type'] ?? ''] ?? ucfirst(str_replace('_', ' ', $a['type'] ?? ''))) ?></span>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div style="padding:20px;color:#9CA3AF;font-size:13px;text-align:center">
        Aucune autre ressource
      </div>
    <?php endif; ?>

  </aside>

  <!-- ══ ZONE PRINCIPALE ══════════════════════════════════════ -->
  <div class="viewer-main">

    <!-- Toolbar progression -->
    <div class="viewer-toolbar">
      <div class="viewer-toolbar__progress">
        <div class="progress-bar-container" style="flex:1;min-width:60px">
          <div class="progress-bar-fill" id="progressBar" style="width:<?= $pct ?>%"></div>
        </div>
        <span class="viewer-toolbar__pct" id="progressText"><?= $pct ?>%</span>
      </div>

      <?php if ($pct < 100): ?>
        <button onclick="markComplete()" class="viewer-toolbar__btn viewer-toolbar__btn--primary">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
          Terminé
        </button>
      <?php else: ?>
        <span style="font-size:13px;font-weight:600;color:#059669;display:flex;align-items:center;gap:5px;white-space:nowrap">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
          Terminé ✓
        </span>
      <?php endif; ?>

      <button onclick="toggleFullscreen()" class="viewer-toolbar__btn viewer-toolbar__btn--ghost" title="Plein écran">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/>
        </svg>
      </button>

      <!-- Toggle IA panel -->
      <button id="ia-toggle" class="viewer-toolbar__btn viewer-toolbar__btn--ghost" title="Assistant IA BACY">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
        </svg>
        IA
      </button>
    </div>

    <!-- Zone de lecture -->
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
          <!-- PDF viewer -->
          <iframe src="<?= e($fileUrl) ?>#toolbar=1&view=FitH" id="pdf-iframe"></iframe>
        <?php endif; ?>
      <?php else: ?>
        <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:#9CA3AF;gap:16px;padding:24px;text-align:center">
          <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
          </svg>
          <p style="font-size:16px;font-weight:500;color:#374151">Fichier non disponible</p>
          <p style="font-size:13px">Ce contenu n'a pas encore été mis en ligne par l'administrateur.</p>
        </div>
      <?php endif; ?>
    </div>

  </div>

  <!-- ══ PANEL IA ══════════════════════════════════════════════ -->
  <aside class="ia-panel ia-panel--hidden" id="ia-panel">
    <div class="ia-panel__header">
      <div class="ia-panel__badge">✦</div>
      <div>
        <div class="ia-panel__title">BACY — Assistant IA</div>
        <div class="ia-panel__sub">Posez vos questions sur ce cours</div>
      </div>
    </div>

    <div class="ia-messages" id="ia-messages">
      <div class="ia-msg ia-msg--ia">
        <div class="ia-msg__avatar">✦</div>
        <div class="ia-msg__bubble">
          Bonjour ! Je suis BACY, votre assistant IA pour <strong><?= e($titre) ?></strong>.
          Posez-moi vos questions, je suis là pour vous aider à comprendre le cours.
        </div>
      </div>
    </div>

    <div class="ia-input">
      <textarea id="ia-textarea" placeholder="Posez votre question…" rows="1"></textarea>
      <button id="ia-send" disabled title="Envoyer (Entrée)">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
        </svg>
      </button>
    </div>
  </aside>

</div>

<style>
/* Le viewer occupe tout l'espace disponible sous la navbar */
#main-content { padding-bottom: 0 !important; overflow: hidden; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    initViewer(
        <?= (int)($ressource['id'] ?? 0) ?>,
        <?= (int)($ressource['derniere_page'] ?? 1) ?>,
        <?= json_encode($fileUrl) ?>,
        <?= $pct ?>
    );

    // Activer le bouton IA send quand textarea a du contenu
    const textarea = document.getElementById('ia-textarea');
    const sendBtn  = document.getElementById('ia-send');
    if (textarea && sendBtn) {
        textarea.addEventListener('input', function() {
            sendBtn.disabled = this.value.trim().length === 0;
        });
    }
});

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
                btn.outerHTML = '<span style="font-size:13px;font-weight:600;color:#059669;display:flex;align-items:center;gap:5px;white-space:nowrap">'
                    + '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Terminé ✓</span>';
            }
            showToast('Cours marqué comme terminé ✓');
        }
    }).catch(() => showToast('Erreur lors de la mise à jour', 'error'));
}
</script>
