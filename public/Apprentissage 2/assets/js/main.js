/**
 * Connect'Academia - JavaScript principal
 */

// Toggle sidebar mobile front-office
(function() {
    function createMobileMenuToggle() {
        if (window.innerWidth > 768) return;
        if (document.querySelector('.mobile-menu-toggle')) return;

        const sidebar = document.querySelector('.sidebar');
        if (!sidebar) return;

        const toggle = document.createElement('button');
        toggle.className = 'mobile-menu-toggle';
        toggle.setAttribute('aria-label', 'Ouvrir le menu');
        toggle.innerHTML = '<i data-lucide="menu"></i>';

        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
        document.body.insertBefore(toggle, document.body.firstChild);

        function openSidebar() {
            sidebar.classList.add('open');
            overlay.classList.add('active');
            toggle.setAttribute('aria-label', 'Fermer le menu');
            const icon = toggle.querySelector('i');
            if (icon) {
                icon.setAttribute('data-lucide', 'x');
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }
        }

        function closeSidebar() {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
            toggle.setAttribute('aria-label', 'Ouvrir le menu');
            const icon = toggle.querySelector('i');
            if (icon) {
                icon.setAttribute('data-lucide', 'menu');
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }
        }

        toggle.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
        });

        overlay.addEventListener('click', closeSidebar);

        sidebar.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) closeSidebar();
            });
        });

        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', createMobileMenuToggle);
    } else {
        createMobileMenuToggle();
    }

    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            const toggle = document.querySelector('.mobile-menu-toggle');
            const overlay = document.querySelector('.sidebar-overlay');
            if (window.innerWidth > 768) {
                if (toggle) toggle.remove();
                if (overlay) overlay.remove();
                const sidebar = document.querySelector('.sidebar');
                if (sidebar) sidebar.classList.remove('open');
            } else {
                createMobileMenuToggle();
            }
        }, 250);
    });
})();

// Utilitaires
function toastSuccess(message) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: message,
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
    } else {
        alert(message);
    }
}

function toastError(message) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'error',
            title: message,
            showConfirmButton: false,
            timer: 4000
        });
    } else {
        alert(message);
    }
}

async function confirmAction(message) {
    if (typeof Swal !== 'undefined') {
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
    return confirm(message);
}

// Recherche globale
(function() {
    const searchInput = document.getElementById('global-search');
    if (!searchInput) return;
    
    const searchResults = document.getElementById('search-results');
    let debounceTimer;
    
    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        const query = searchInput.value.trim();
        
        if (query.length < 2) {
            if (searchResults) searchResults.classList.add('hidden');
            return;
        }
        
        debounceTimer = setTimeout(() => fetchSearchResults(query), 300);
    });
    
    async function fetchSearchResults(query) {
        try {
            const response = await fetch(`api/ressources.php?search=${encodeURIComponent(query)}&limit=8`);
            const data = await response.json();
            
            if (!searchResults) return;
            
            if (!data.success || !data.data.ressources.length) {
                searchResults.innerHTML = '<p class="no-results">Aucun résultat</p>';
            } else {
                searchResults.innerHTML = data.data.ressources.map(r => `
                    <a href="/ApprentissageV1/viewer.php?ressource=${r.id}" class="search-result-item">
                        <span class="badge badge-${r.type}">${r.type}</span>
                        <span class="result-title">${r.titre}</span>
                        <span class="result-meta">${r.matiere}</span>
                    </a>
                `).join('');
            }
            searchResults.classList.remove('hidden');
        } catch (error) {
            console.error('Erreur recherche:', error);
        }
    }
    
    // Fermer au clic extérieur
    document.addEventListener('click', e => {
        if (searchInput && !searchInput.contains(e.target) && searchResults && !searchResults.contains(e.target)) {
            searchResults.classList.add('hidden');
        }
    });
})();

// Toggle favori
async function toggleFavori(ressourceId, buttonEl) {
    try {
        const response = await fetch('api/favoris.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ressource_id: ressourceId })
        });
        const data = await response.json();
        
        if (data.success) {
            buttonEl.classList.toggle('active', data.est_favori);
            buttonEl.title = data.est_favori ? 'Retirer des favoris' : 'Ajouter aux favoris';
        }
    } catch (error) {
        console.error('Erreur toggle favori:', error);
        toastError('Erreur lors de la mise à jour des favoris');
    }
}

