/**
 * Connect'Academia — Module Apprentissage
 * Gère : toggleFavori, toasts, progression UI
 */
'use strict';

// ── Toast ─────────────────────────────────────────────────────
function showToast(message, type = 'success') {
    const existing = document.getElementById('appr-toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.id = 'appr-toast';
    toast.style.cssText = `
        position:fixed;bottom:24px;left:50%;transform:translateX(-50%);
        background:${type === 'success' ? '#8B52FA' : '#EF4444'};color:#fff;
        padding:10px 20px;border-radius:10px;font-size:14px;font-weight:500;
        z-index:9999;box-shadow:0 4px 12px rgba(0,0,0,.2);
        animation:fadeInUp .3s ease;
    `;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

// ── Favori toggle ──────────────────────────────────────────────
async function toggleFavori(ressourceId, btn) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    try {
        const res = await fetch(`${window.CA?.baseUrl || ''}/api/apprentissage/favoris/${ressourceId}`, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrf,
            },
            credentials: 'same-origin',
        });

        const data = await res.json();
        if (!data.success) throw new Error(data.error?.message || 'Erreur');

        const isFavori = data.data?.favori ?? false;
        if (btn) {
            btn.style.color = isFavori ? '#F59E0B' : 'var(--color-text-light, #6B7280)';
            btn.classList.toggle('active', isFavori);
            btn.title = isFavori ? 'Retirer des favoris' : 'Ajouter aux favoris';
        }
        showToast(isFavori ? 'Ajouté aux favoris ⭐' : 'Retiré des favoris');

        // Si on est sur la page Favoris, retirer la carte
        if (!isFavori && btn) {
            const card = btn.closest('.resource-card');
            if (card && document.querySelector('.favoris-page')) {
                card.style.transition = 'opacity .3s, transform .3s';
                card.style.opacity = '0';
                card.style.transform = 'scale(.95)';
                setTimeout(() => {
                    card.remove();
                    // Afficher empty state si plus rien
                    const grid = document.querySelector('.grid-cards');
                    if (grid && !grid.querySelector('.resource-card')) {
                        grid.innerHTML = `<div class="empty-state" style="grid-column:1/-1">
                            <div class="empty-state__icon" style="font-size:48px;width:auto">⭐</div>
                            <h3>Aucun favori</h3>
                            <p>Vous n'avez pas encore de favoris.</p>
                        </div>`;
                    }
                }, 300);
            }
        }
    } catch (err) {
        showToast(err.message || 'Erreur réseau', 'error');
    }
}

// ── Progression (sauvegarde auto) ─────────────────────────────
function initProgressionAuto(ressourceId) {
    if (!ressourceId) return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let startTime = Date.now();

    async function saveProgression(pourcentage, dernierePage = 1, action = 'update') {
        try {
            await fetch(`${window.CA?.baseUrl || ''}/api/apprentissage/progression`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrf,
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    ressource_id: ressourceId,
                    pourcentage,
                    derniere_page: dernierePage,
                    action,
                    duree_secondes: Math.floor((Date.now() - startTime) / 1000),
                }),
            });
        } catch (e) { /* silent */ }
    }

    // Sauvegarder à la fermeture
    window.addEventListener('beforeunload', () => {
        saveProgression(50);
    });

    return saveProgression;
}

// ── Marquer comme terminé ──────────────────────────────────────
async function markAsComplete(ressourceId) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    try {
        const res = await fetch(`${window.CA?.baseUrl || ''}/api/apprentissage/progression`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrf,
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                ressource_id: ressourceId,
                pourcentage: 100,
                action: 'complete',
            }),
        });

        const data = await res.json();
        if (data.success) {
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            const completeBtn = document.getElementById('completeBtn');

            if (progressBar)  progressBar.style.width = '100%';
            if (progressText) progressText.textContent = '100%';
            if (completeBtn) {
                completeBtn.textContent = '✅ Terminé !';
                completeBtn.disabled = true;
                completeBtn.style.background = '#059669';
            }
            showToast('Ressource marquée comme terminée ! ✅');
        }
    } catch (e) {
        showToast('Erreur lors de la mise à jour', 'error');
    }
}

// ── Init lucide icons ─────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    if (window.lucide) lucide.createIcons();
});
