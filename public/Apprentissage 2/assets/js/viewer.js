/**
 * Connect'Academia - Viewer PDF simplifié (iframe)
 */

let ressourceId = null;
let isStudent = true;

function initViewer(ressId, lastPage, pdfUrl, isStudentUser = true) {
    ressourceId = ressId;
    isStudent = isStudentUser;
    
    // Le PDF est affiché directement via iframe, pas besoin d'initialisation
    // La progression peut être gérée manuellement via le bouton "Marquer comme terminé"
}

async function markAsComplete() {
    if (!isStudent || !ressourceId) return;
    
    try {
        const response = await fetch('api/progression.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'complete', ressource_id: ressourceId })
        });
        
        const data = await response.json();
        if (data.success) {
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            const completeBtn = document.getElementById('completeBtn');
            
            if (progressBar) progressBar.style.width = '100%';
            if (progressText) progressText.textContent = '100%';
            if (completeBtn) {
                completeBtn.textContent = '✅ Terminé !';
                completeBtn.disabled = true;
            }
            
            if (typeof Swal !== 'undefined') {
                Swal.fire('Succès', 'Ressource marquée comme terminée !', 'success');
            }
        }
    } catch (error) {
        console.error('Erreur complete:', error);
        if (typeof Swal !== 'undefined') {
            Swal.fire('Erreur', 'Impossible de marquer comme terminé', 'error');
        }
    }
}

function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen();
    } else {
        document.exitFullscreen();
    }
}

// Fonctions de navigation désactivées (PDF affiché via iframe)
function previousPage() {
    // Navigation désactivée avec iframe
}

function nextPage() {
    // Navigation désactivée avec iframe
}

function zoomIn() {
    // Zoom désactivé avec iframe
}

function zoomOut() {
    // Zoom désactivé avec iframe
}
