/**
 * StudyLink — Follow Component
 * Gère le système de suivi des utilisateurs
 */

const Follow = (() => {
    'use strict';

    // ── Init ────────────────────────────────────────
    function init() {
        // Gérer les boutons de suivi sur la page de profil
        document.addEventListener('click', async (e) => {
            const followBtn = e.target.closest('.follow-btn, .suggested-user__follow-btn');
            if (!followBtn) return;

            e.preventDefault();
            e.stopPropagation();

            const userId = followBtn.dataset.userId;
            if (!userId) return;

            await toggleFollow(userId, followBtn);
        });
    }

    // ── Toggle Follow ───────────────────────────────
    async function toggleFollow(userId, btn) {
        // Désactiver le bouton pendant la requête
        const originalText = btn.textContent.trim();
        const originalDisabled = btn.disabled;
        btn.disabled = true;
        btn.style.opacity = '0.6';
        btn.style.cursor = 'wait';

        try {
            const response = await API.post(`/api/users/${userId}/follow`);
            const isFollowing = response.data?.following ?? false;

            // Mettre à jour le bouton
            if (btn.classList.contains('follow-btn')) {
                // Bouton sur la page de profil
                if (isFollowing) {
                    btn.textContent = 'Abonné';
                    btn.classList.remove('btn--primary');
                    btn.classList.add('btn--outline');
                } else {
                    btn.textContent = 'Suivre';
                    btn.classList.remove('btn--outline');
                    btn.classList.add('btn--primary');
                }
            } else if (btn.classList.contains('suggested-user__follow-btn')) {
                // Bouton dans les suggestions
                if (isFollowing) {
                    btn.textContent = 'Abonné';
                    btn.style.opacity = '0.7';
                } else {
                    btn.textContent = 'Suivre';
                    btn.style.opacity = '1';
                }
            }

            // Mettre à jour les compteurs si on est sur la page de profil
            updateCounters(userId, isFollowing);

            App.toast.success(isFollowing ? 'Vous suivez maintenant cet utilisateur' : 'Vous ne suivez plus cet utilisateur');
        } catch (error) {
            App.toast.error(error.message || 'Erreur lors de l\'action');
            console.error('Follow error:', error);
        } finally {
            btn.disabled = originalDisabled;
            btn.style.opacity = '';
            btn.style.cursor = '';
        }
    }

    // ── Update Counters ─────────────────────────────
    function updateCounters(userId, isFollowing) {
        const currentUserId = window.STUDYLINK_CONFIG?.userId;
        if (!currentUserId) return;

        // Mettre à jour le compteur "Abonnés" sur la page de profil de l'utilisateur suivi
        const profileStats = document.querySelector('.profile-hero__stats');
        if (profileStats) {
            const followBtn = document.querySelector('.follow-btn');
            const profileUserId = followBtn?.dataset?.userId;
            
            // Si on est sur la page de profil de l'utilisateur qu'on suit/se désabonne
            if (profileUserId && parseInt(profileUserId) === parseInt(userId)) {
                const followersStat = profileStats.querySelector('.profile-stat:nth-child(2) .profile-stat__value');
                if (followersStat) {
                    let count = parseInt(followersStat.textContent) || 0;
                    count = isFollowing ? count + 1 : Math.max(0, count - 1);
                    followersStat.textContent = count;
                }
            }
            
            // Si on est sur notre propre profil, mettre à jour le compteur "Abonnements"
            if (profileUserId && parseInt(profileUserId) === currentUserId) {
                const followingStat = profileStats.querySelector('.profile-stat:nth-child(3) .profile-stat__value');
                if (followingStat) {
                    let count = parseInt(followingStat.textContent) || 0;
                    count = isFollowing ? count + 1 : Math.max(0, count - 1);
                    followingStat.textContent = count;
                }
            }
        }
    }

    // ── Init on load ────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    return {
        init,
        toggleFollow
    };
})();

