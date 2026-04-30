# ⚡ PRD-07 — Fonctionnalités Transversales
## Connect'Academia — Progression, Recherche, Upload, Performance

> **Référence** : PRD principal v1.0 — Sections 11, 15
> **Usage Cursor** : Implémente les modules JS partagés entre plusieurs pages.

---

## 1. Système de Progression & Timer de Révision

### 1.1 Fonctionnement global

La progression est calculée à partir du nombre de pages PDF consultées :
```
Progression (%) = (page_actuelle / total_pages) * 100
```

Statut `termine` : atteindre la dernière page **OU** clic manuel "Marquer comme terminé".

### 1.2 Fichier JS : `/assets/js/viewer.js`

Ce fichier gère l'intégration PDF.js + le tracking automatique.

```javascript
// ============================================================
// VIEWER.JS — PDF.js + Timer de révision + Progression AJAX
// ============================================================

let startTime;
let currentPage = 1;
let totalPages  = 1;
let ressourceId;
let timerInterval;
let heartbeatInterval;

// === INITIALISATION ===
async function initViewer(ressId, lastPage = 1) {
    ressourceId = ressId;
    
    // Charger le PDF via PDF.js
    const pdfUrl   = `/api/ressources.php?action=get_file&id=${ressourceId}`;
    const loadingTask = pdfjsLib.getDocument(pdfUrl);
    const pdf      = await loadingTask.promise;
    totalPages     = pdf.numPages;
    
    // Aller à la dernière page consultée
    currentPage = lastPage;
    renderPage(currentPage);
    
    // Démarrer la session
    await startRevisionSession();
    
    // Timer UI (affiché en temps réel)
    startTime    = Date.now();
    timerInterval = setInterval(updateTimerDisplay, 1000);
    
    // Heartbeat toutes les 30 secondes
    heartbeatInterval = setInterval(sendHeartbeat, 30000);
}

// === DÉMARRAGE SESSION ===
async function startRevisionSession() {
    await fetch('/api/progression.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'start', ressource_id: ressourceId })
    });
}

// === HEARTBEAT (toutes les 30s) ===
async function sendHeartbeat() {
    const elapsed = Math.floor((Date.now() - startTime) / 1000);
    await fetch('/api/progression.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'heartbeat',
            ressource_id: ressourceId,
            temps: elapsed,
            page_actuelle: currentPage,
            total_pages: totalPages
        })
    });
    updateProgressBar();
}

// === FIN DE SESSION (fermeture page) ===
window.addEventListener('beforeunload', () => {
    const elapsed = Math.floor((Date.now() - startTime) / 1000);
    // Utiliser sendBeacon pour fiabilité à la fermeture
    navigator.sendBeacon('/api/progression.php', JSON.stringify({
        action: 'end',
        ressource_id: ressourceId,
        temps: elapsed,
        page_actuelle: currentPage,
        total_pages: totalPages
    }));
});

// === TIMER UI ===
function updateTimerDisplay() {
    const elapsed = Math.floor((Date.now() - startTime) / 1000);
    const h = Math.floor(elapsed / 3600);
    const m = Math.floor((elapsed % 3600) / 60);
    const s = elapsed % 60;
    document.getElementById('timer-display').textContent =
        `${h > 0 ? h + 'h ' : ''}${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
}

// === BARRE DE PROGRESSION ===
function updateProgressBar() {
    const pct = Math.min(100, Math.round((currentPage / totalPages) * 100));
    document.getElementById('progress-bar').style.width = pct + '%';
    document.getElementById('progress-text').textContent = pct + '%';
}

// === NAVIGATION PAGES ===
function goToPage(pageNum) {
    currentPage = Math.max(1, Math.min(pageNum, totalPages));
    renderPage(currentPage);
    updateProgressBar();
    sendHeartbeat(); // Sauvegarder immédiatement au changement de page
}

// === MARQUER COMME TERMINÉ ===
async function markAsComplete() {
    await fetch('/api/progression.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'complete', ressource_id: ressourceId })
    });
    document.getElementById('progress-bar').style.width = '100%';
    document.getElementById('complete-btn').textContent = '✅ Terminé !';
    document.getElementById('complete-btn').disabled = true;
}
```

### 1.3 Fichier JS : `/assets/js/progression.js`

Module léger pour les pages ne nécessitant pas le viewer complet (dashboard, page progression).

```javascript
// Mise à jour du favori sans rechargement
async function toggleFavori(ressourceId, buttonEl) {
    const response = await fetch('/api/favoris.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ressource_id: ressourceId })
    });
    const data = await response.json();
    if (data.success) {
        buttonEl.classList.toggle('active', data.est_favori);
        buttonEl.title = data.est_favori ? 'Retirer des favoris' : 'Ajouter aux favoris';
    }
}
```

---

## 2. Système de Recherche Globale

### 2.1 Barre de recherche (front-office header)

```html
<div class="search-bar">
    <input type="search" id="global-search" placeholder="Rechercher un cours, une matière..."
           autocomplete="off" aria-label="Recherche globale">
    <div id="search-results" class="search-dropdown hidden"></div>
</div>
```

### 2.2 Logique JS (dans `/assets/js/main.js`)

```javascript
const searchInput   = document.getElementById('global-search');
const searchResults = document.getElementById('search-results');
let debounceTimer;

searchInput.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    const query = searchInput.value.trim();
    
    if (query.length < 2) {
        searchResults.classList.add('hidden');
        return;
    }
    
    debounceTimer = setTimeout(() => fetchSearchResults(query), 300);
});

async function fetchSearchResults(query) {
    const response = await fetch(`/api/ressources.php?search=${encodeURIComponent(query)}&limit=8`);
    const data     = await response.json();
    
    if (!data.success || !data.data.ressources.length) {
        searchResults.innerHTML = '<p class="no-results">Aucun résultat</p>';
    } else {
        searchResults.innerHTML = data.data.ressources.map(r => `
            <a href="/viewer.php?ressource=${r.id}" class="search-result-item">
                <span class="badge badge-${r.type}">${r.type}</span>
                <span class="result-title">${r.titre}</span>
                <span class="result-meta">${r.matiere}</span>
            </a>
        `).join('');
    }
    searchResults.classList.remove('hidden');
}

// Fermer au clic extérieur
document.addEventListener('click', e => {
    if (!searchInput.contains(e.target)) searchResults.classList.add('hidden');
});
```

---

## 3. Système d'Upload avec Progress Bar (Admin)

### Fichier JS : `/assets/js/upload.js`

```javascript
// ============================================================
// UPLOAD.JS — Upload PDF avec barre de progression
// ============================================================

const dropZone    = document.getElementById('drop-zone');
const fileInput   = document.getElementById('fichier-input');
const progressBar = document.getElementById('upload-progress-bar');
const progressPct = document.getElementById('upload-progress-pct');

// Drag & Drop
dropZone.addEventListener('dragover', e => {
    e.preventDefault();
    dropZone.classList.add('drag-over');
});
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('drag-over');
    const file = e.dataTransfer.files[0];
    if (validateFile(file)) setFile(file);
});

function validateFile(file) {
    if (file.type !== 'application/pdf') {
        showError('Seuls les fichiers PDF sont acceptés.');
        return false;
    }
    if (file.size > 50 * 1024 * 1024) {
        showError('Fichier trop volumineux (max 50 Mo).');
        return false;
    }
    return true;
}

function setFile(file) {
    document.getElementById('file-name').textContent = `${file.name} (${formatSize(file.size)})`;
    dropZone.dataset.file = 'ready';
}

// Upload avec XMLHttpRequest pour avoir l'événement progress
async function uploadRessource(formData) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        
        xhr.upload.addEventListener('progress', e => {
            if (e.lengthComputable) {
                const pct = Math.round((e.loaded / e.total) * 100);
                progressBar.style.width = pct + '%';
                progressPct.textContent = pct + '%';
            }
        });
        
        xhr.addEventListener('load', () => {
            const data = JSON.parse(xhr.responseText);
            data.success ? resolve(data) : reject(data.error);
        });
        
        xhr.addEventListener('error', () => reject('Erreur réseau'));
        
        xhr.open('POST', '/api/ressources.php');
        xhr.send(formData);
    });
}

function formatSize(bytes) {
    if (bytes > 1024 * 1024) return (bytes / 1024 / 1024).toFixed(1) + ' Mo';
    return Math.round(bytes / 1024) + ' Ko';
}
```

---

## 4. Notifications UI — SweetAlert2

Fonctions utilitaires dans `/assets/js/main.js` :

```javascript
// Toast succès
function toastSuccess(message) {
    Swal.fire({
        toast: true, position: 'top-end', icon: 'success',
        title: message, showConfirmButton: false, timer: 3000,
        timerProgressBar: true
    });
}

// Toast erreur
function toastError(message) {
    Swal.fire({
        toast: true, position: 'top-end', icon: 'error',
        title: message, showConfirmButton: false, timer: 4000
    });
}

// Modal confirmation (ex: suppression)
async function confirmAction(message) {
    const result = await Swal.fire({
        title: 'Confirmer l\'action',
        text: message,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#8B52FA',
        cancelButtonText: 'Annuler',
        confirmButtonText: 'Confirmer'
    });
    return result.isConfirmed;
}
```

---

## 5. Gestion des erreurs

### Pages d'erreur
- `/404.php` : Style Connect'Academia, lien retour
- `/403.php` : Accès refusé

### En-têtes PHP pour les erreurs
```php
http_response_code(404); // ou 403
require '404.php';
exit;
```

### Gestion erreurs dans les API
```php
try {
    // ... logique
} catch (PDOException $e) {
    error_log($e->getMessage()); // Logger en interne
    http_response_code(500);
    die(json_encode(['success' => false, 'error' => 'Erreur serveur. Réessayez.']));
}
```

---

## 6. Performance & Accessibilité

### Optimisations performance
- **Minification** CSS/JS en production (via `.htaccess` ou build step)
- **Compression GZIP** activée dans `.htaccess`
- **Cache navigateur** : assets statiques `max-age=86400`
- **Lazy loading** images : `<img loading="lazy">`
- **Pagination** : 20 items max par page
- **Index MySQL** sur colonnes fréquemment filtrées : `serie_id`, `matiere_id`, `user_id`
- **Debounce** sur toutes les recherches AJAX : 300ms

### Variables CSS (dans `/assets/css/main.css`)

```css
:root {
    /* Couleurs */
    --color-primary:    #8B52FA;
    --color-primary-bg: #F3EFFF;
    --color-dark:       #2D2D2D;
    --color-white:      #FFFFFF;
    --color-text:       #333333;
    --color-border:     #E5E7EB;
    --color-shadow:     rgba(0,0,0,0.08);
    
    /* Typographie */
    --font-main:  'Inter', 'Poppins', sans-serif;
    --font-size-base: 15px;
    
    /* Spacing */
    --radius-sm:  6px;
    --radius-md:  8px;
    --radius-lg:  12px;
    
    /* Transitions */
    --transition: 0.2s ease;
}
```

### Accessibilité WCAG 2.1 AA
- Contraste texte/fond ≥ 4.5:1 (violet `#8B52FA` sur blanc ✅)
- Navigation clavier complète (tab, enter, escape)
- `aria-label` sur tous les boutons icônes : `<button aria-label="Ajouter aux favoris">`
- Focus visible sur éléments interactifs : `outline: 2px solid var(--color-primary)`
- `alt` sur toutes les images : `<img alt="Description">`
- Titres hiérarchisés (H1 → H2 → H3)

### Responsive CSS (dans `main.css`)
```css
/* Mobile first */
.grid-cards { display: grid; grid-template-columns: 1fr; gap: 16px; }
.sidebar { display: none; }
.hamburger { display: block; }

@media (min-width: 768px) {
    .sidebar { display: flex; width: 64px; } /* Icônes seulement */
    .grid-cards { grid-template-columns: repeat(2, 1fr); }
}

@media (min-width: 1024px) {
    .sidebar { width: 240px; } /* Sidebar complète */
    .grid-cards { grid-template-columns: repeat(3, 1fr); }
}
```

---

## 7. Chargement dynamique des sélecteurs (Admin)

Lors de l'upload d'une ressource, les dropdowns sont liés :
Série → charge les Matières → charge les Chapitres.

```javascript
document.getElementById('serie-select').addEventListener('change', async function() {
    const serieId = this.value;
    const response = await fetch(`/api/matieres.php?serie_id=${serieId}`);
    const data     = await response.json();
    
    const matiereSelect = document.getElementById('matiere-select');
    matiereSelect.innerHTML = '<option value="">-- Choisir une matière --</option>';
    data.data.forEach(m => {
        matiereSelect.innerHTML += `<option value="${m.id}">${m.nom}</option>`;
    });
    
    // Réinitialiser chapitres
    document.getElementById('chapitre-select').innerHTML = '<option value="">-- Optionnel --</option>';
});
```

---

*PRD-07 Fonctionnalités Transversales — Connect'Academia v1.0*
