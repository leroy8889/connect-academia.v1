/**
 * StudyLink — Auth Component
 * Gère les validations côté client pour login/register
 */

const Auth = (() => {
    'use strict';

    // ── Init ────────────────────────────────────────
    function init() {
        initRegisterForm();
        initLoginForm();
        initPasswordToggle();
        initPasswordStrength();
    }

    // ── Register Form ───────────────────────────────
    function initRegisterForm() {
        const form = document.getElementById('register-form');
        if (!form) return;

        form.addEventListener('submit', (e) => {
            try {
                clearErrors(form);
                let isValid = true;

                const firstName = form.querySelector('[name="prenom"]');
                const lastName = form.querySelector('[name="nom"]');
                const email = form.querySelector('[name="email"]');
                const classe = form.querySelector('[name="classe"]');
                const password = form.querySelector('[name="password"]');
                const passwordConfirm = form.querySelector('[name="password_confirm"]');
                const terms = form.querySelector('[name="terms"]');

                if (!firstName?.value.trim()) {
                    showError(firstName, 'Le prénom est requis');
                    isValid = false;
                }

                if (!lastName?.value.trim()) {
                    showError(lastName, 'Le nom est requis');
                    isValid = false;
                }

                if (!email?.value.trim() || !isValidEmail(email.value)) {
                    showError(email, 'Adresse e-mail invalide');
                    isValid = false;
                }

                if (!classe?.value || classe.value === '') {
                    showError(classe, 'Veuillez sélectionner une classe');
                    isValid = false;
                }

                if (!password?.value || password.value.length < 8) {
                    showError(password, 'Le mot de passe doit contenir au moins 8 caractères');
                    isValid = false;
                }

                if (password?.value !== passwordConfirm?.value) {
                    showError(passwordConfirm, 'Les mots de passe ne correspondent pas');
                    isValid = false;
                }

                if (terms && !terms.checked) {
                    showError(terms.closest('.form-checkbox'), 'Vous devez accepter les conditions');
                    isValid = false;
                }

                if (!isValid) {
                    e.preventDefault();
                    // Scroll vers première erreur
                    const firstError = form.querySelector('.form-input--error, .form-error');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
                // Si isValid est true, on laisse le formulaire se soumettre normalement
            } catch (error) {
                // En cas d'erreur JavaScript, on laisse le formulaire se soumettre
                // pour que la validation côté serveur puisse fonctionner
                console.error('Erreur lors de la validation du formulaire:', error);
            }
        });
    }

    // ── Login Form ──────────────────────────────────
    function initLoginForm() {
        const form = document.getElementById('login-form');
        if (!form) return;

        form.addEventListener('submit', (e) => {
            clearErrors(form);
            let isValid = true;

            const email = form.querySelector('[name="email"]');
            const password = form.querySelector('[name="password"]');

            if (!email?.value.trim() || !isValidEmail(email.value)) {
                showError(email, 'Adresse e-mail invalide');
                isValid = false;
            }

            if (!password?.value) {
                showError(password, 'Le mot de passe est requis');
                isValid = false;
            }

            if (!isValid) e.preventDefault();
        });
    }

    // ── Password Toggle ─────────────────────────────
    function initPasswordToggle() {
        document.querySelectorAll('[data-toggle-password]').forEach(btn => {
            btn.addEventListener('click', () => {
                const target = document.getElementById(btn.dataset.togglePassword);
                if (!target) return;

                const isPassword = target.type === 'password';
                target.type = isPassword ? 'text' : 'password';

                // Update icon
                const svg = btn.querySelector('svg');
                if (svg) {
                    svg.innerHTML = isPassword
                        ? '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>'
                        : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
                }
            });
        });
    }

    // ── Password Strength ───────────────────────────
    function initPasswordStrength() {
        const passwordInput = document.getElementById('register-password');
        const strengthFill = document.querySelector('.password-strength__fill');
        const strengthLabel = document.querySelector('.password-strength__label');
        if (!passwordInput || !strengthFill) return;

        passwordInput.addEventListener('input', () => {
            const strength = calculateStrength(passwordInput.value);
            strengthFill.className = 'password-strength__fill';

            if (passwordInput.value.length === 0) {
                strengthFill.style.width = '0';
                if (strengthLabel) strengthLabel.textContent = '';
                return;
            }

            const levels = [
                { class: 'password-strength__fill--weak', label: 'Faible' },
                { class: 'password-strength__fill--fair', label: 'Moyen' },
                { class: 'password-strength__fill--good', label: 'Bon' },
                { class: 'password-strength__fill--strong', label: 'Fort' }
            ];

            const level = levels[strength];
            if (level) {
                strengthFill.classList.add(level.class);
                if (strengthLabel) strengthLabel.textContent = level.label;
            }
        });
    }

    function calculateStrength(password) {
        let score = 0;
        if (password.length >= 8) score++;
        if (password.length >= 12) score++;
        if (/[A-Z]/.test(password) && /[a-z]/.test(password)) score++;
        if (/[0-9]/.test(password)) score++;
        if (/[^A-Za-z0-9]/.test(password)) score++;
        
        if (score <= 1) return 0;      // Weak
        if (score <= 2) return 1;      // Fair
        if (score <= 3) return 2;      // Good
        return 3;                       // Strong
    }

    // ── Validation Helpers ──────────────────────────
    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function showError(element, message) {
        if (!element) return;
        const input = element.tagName === 'INPUT' || element.tagName === 'SELECT'
            ? element
            : element.querySelector('input, select');

        if (input) input.classList.add('form-input--error');

        const group = element.closest('.form-group') || element.parentElement;
        if (group) {
            let errorEl = group.querySelector('.form-error');
            if (!errorEl) {
                errorEl = document.createElement('span');
                errorEl.className = 'form-error';
                group.appendChild(errorEl);
            }
            errorEl.textContent = message;
        }
    }

    function clearErrors(form) {
        form.querySelectorAll('.form-input--error').forEach(el => el.classList.remove('form-input--error'));
        form.querySelectorAll('.form-error').forEach(el => el.remove());
    }

    // ── Init on load ────────────────────────────────
    function safeInit() {
        try {
            init();
        } catch (error) {
            console.error('Erreur lors de l\'initialisation du module Auth:', error);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', safeInit);
    } else {
        safeInit();
    }

    return { init: safeInit };
})();

