/**
 * StudyLink — API Helper
 * Gère toutes les requêtes HTTP vers le backend
 */

const API = (() => {
    'use strict';

    // ── Config ──────────────────────────────────────
    const BASE_URL = window.CA?.baseUrl || window.STUDYLINK_CONFIG?.baseUrl || '';
    let csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    /**
     * Requête générique
     */
    async function request(method, url, data = null, options = {}) {
        const config = {
            method: method.toUpperCase(),
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken,
                ...options.headers
            },
            credentials: 'same-origin'
        };

        if (data && !(data instanceof FormData)) {
            config.headers['Content-Type'] = 'application/json';
            config.body = JSON.stringify(data);
        } else if (data instanceof FormData) {
            config.body = data;
        }

        try {
            const response = await fetch(BASE_URL + url, config);

            // Mise à jour du CSRF token si renvoyé
            const newToken = response.headers.get('X-CSRF-Token');
            if (newToken) csrfToken = newToken;

            const contentType = response.headers.get('content-type');
            let result;

            if (contentType && contentType.includes('application/json')) {
                result = await response.json();
            } else {
                result = await response.text();
            }

            if (!response.ok) {
                // Réponse non JSON (413 nginx, page d'erreur 5xx…) : on remonte
                // le code HTTP plutôt qu'un « Une erreur est survenue » opaque,
                // sinon un problème serveur est indiagnosticable côté support.
                let message = result?.error?.message || result?.message;
                if (!message) {
                    message = response.status === 413
                        ? "Fichier trop volumineux : le serveur a refusé l'envoi."
                        : `Une erreur est survenue (HTTP ${response.status}).`;
                }
                throw {
                    status: response.status,
                    message,
                    errors: result?.error?.fields || result?.errors || {}
                };
            }

            return result;
        } catch (error) {
            if (error.status) throw error;
            throw {
                status: 0,
                message: 'Erreur de connexion',
                errors: {}
            };
        }
    }

    return {
        get:    (url, options)       => request('GET', url, null, options),
        post:   (url, data, options) => request('POST', url, data, options),
        put:    (url, data, options) => request('PUT', url, data, options),
        patch:  (url, data, options) => request('PATCH', url, data, options),
        delete: (url, options)       => request('DELETE', url, null, options),

        updateCsrfToken(token) {
            csrfToken = token;
        }
    };
})();

