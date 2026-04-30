/**
 * Connect'Academia - JavaScript Admin
 * Gestion du menu hamburger pour mobile
 */

(function() {
    // Créer le bouton hamburger pour admin si on est sur mobile
    function createAdminMobileMenuToggle() {
        if (window.innerWidth > 640) return; // Seulement sur mobile
        
        const existingToggle = document.querySelector('.admin-mobile-menu-toggle');
        if (existingToggle) return; // Déjà créé
        
        const toggle = document.createElement('button');
        toggle.className = 'admin-mobile-menu-toggle';
        toggle.setAttribute('aria-label', 'Ouvrir le menu');
        toggle.innerHTML = '<i data-lucide="menu"></i>';
        
        const sidebar = document.querySelector('.admin-sidebar');
        const adminMain = document.querySelector('.admin-main');
        
        if (sidebar && adminMain) {
            document.body.insertBefore(toggle, sidebar);
            
            // Créer l'overlay
            const overlay = document.createElement('div');
            overlay.className = 'admin-sidebar-overlay';
            document.body.appendChild(overlay);
            
            // Toggle sidebar
            toggle.addEventListener('click', (e) => {
                e.stopPropagation();
                sidebar.classList.toggle('open');
                overlay.classList.toggle('active');
                toggle.setAttribute('aria-label', sidebar.classList.contains('open') ? 'Fermer le menu' : 'Ouvrir le menu');
                const icon = toggle.querySelector('i');
                if (icon) {
                    icon.setAttribute('data-lucide', sidebar.classList.contains('open') ? 'x' : 'menu');
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
            });
            
            // Fermer au clic sur l'overlay
            overlay.addEventListener('click', () => {
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
                toggle.setAttribute('aria-label', 'Ouvrir le menu');
                const icon = toggle.querySelector('i');
                if (icon) {
                    icon.setAttribute('data-lucide', 'menu');
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
            });
            
            // Fermer au clic sur un lien de la sidebar
            const sidebarLinks = sidebar.querySelectorAll('a');
            sidebarLinks.forEach(link => {
                link.addEventListener('click', () => {
                    if (window.innerWidth <= 640) {
                        sidebar.classList.remove('open');
                        overlay.classList.remove('active');
                        const icon = toggle.querySelector('i');
                        if (icon) {
                            icon.setAttribute('data-lucide', 'menu');
                            if (typeof lucide !== 'undefined') {
                                lucide.createIcons();
                            }
                        }
                    }
                });
            });
            
            // Initialiser les icônes
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }
    }
    
    // Créer au chargement
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', createAdminMobileMenuToggle);
    } else {
        createAdminMobileMenuToggle();
    }
    
    // Recréer si la taille de la fenêtre change
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            const toggle = document.querySelector('.admin-mobile-menu-toggle');
            const overlay = document.querySelector('.admin-sidebar-overlay');
            
            if (window.innerWidth > 640) {
                // Desktop : supprimer le toggle et l'overlay
                if (toggle) toggle.remove();
                if (overlay) overlay.remove();
                const sidebar = document.querySelector('.admin-sidebar');
                if (sidebar) sidebar.classList.remove('open');
            } else {
                // Mobile : créer le toggle s'il n'existe pas
                createAdminMobileMenuToggle();
            }
        }, 250);
    });
})();

