<?php
declare(strict_types=1);

namespace Controllers\Orientation;

use Core\{RateLimiter, Response, Session};
use Models\Orientation\Dossier;
use Models\User;

/**
 * Compte express Orientation — pour le grand public (parents, étudiants,
 * candidats libres). Création de compte légère : prénom, nom, email,
 * mot de passe. Aucune série, aucun établissement — le compte sert
 * uniquement à suivre ses dossiers d'inscription dans son espace.
 */
class CompteOrientationController
{
    /**
     * Destination de retour après création de compte / connexion : n'accepte
     * que les chemins internes du parcours orientation (barrière préinscription/
     * inscription). Toute URL externe ou non autorisée retombe sur l'espace.
     */
    private function safeRedirect(string $target): string
    {
        $default = '/eleve/orientation';
        $allowed = ['/orientation', '/eleve/orientation'];
        if ($target === '' || !str_starts_with($target, '/') || str_starts_with($target, '//')) {
            return $default;
        }
        foreach ($allowed as $prefix) {
            if ($target === $prefix || str_starts_with($target, $prefix . '/') || str_starts_with($target, $prefix . '?')) {
                return $target;
            }
        }
        return $default;
    }

    public function show(): void
    {
        $redirect = $this->safeRedirect(trim($_GET['redirect'] ?? ''));

        if (Session::isLoggedIn()) {
            Response::redirect($redirect);
        }

        // Préremplissage depuis la page succès préinscription (GET) ou re-flash
        $old = Session::getFlash('old', []);
        if (empty($old)) {
            $old = [
                'email'     => trim($_GET['email'] ?? ''),
                'prenom'    => trim($_GET['prenom'] ?? ''),
                'nom'       => trim($_GET['nom'] ?? ''),
                'telephone' => trim($_GET['telephone'] ?? ''),
            ];
        }

        Response::view('orientation/compte', [
            'pageTitle' => "Créer mon espace — Connect'Academia",
            'errors'    => Session::getFlash('errors', []),
            'old'       => $old,
            'redirect'  => $redirect,
            'csrf'      => Session::getCsrfToken(),
        ], '');
    }

    public function store(): void
    {
        $redirect = $this->safeRedirect(trim($_POST['redirect'] ?? ''));
        // URL de retour vers ce formulaire en conservant la destination du parcours
        $backUrl  = '/orientation/compte'
                  . ($redirect !== '/eleve/orientation' ? '?redirect=' . urlencode($redirect) : '');

        if (Session::isLoggedIn()) {
            Response::redirect($redirect);
        }

        $rlKey = RateLimiter::ipKey('compte_orientation');
        if (RateLimiter::tooManyAttempts($rlKey, 10)) {
            $waitMin = max(1, (int) ceil(RateLimiter::availableIn($rlKey) / 60));
            Session::flash('errors', ['general' => "Trop de tentatives. Réessayez dans {$waitMin} minute(s)."]);
            Response::redirect($backUrl);
        }

        $data = [
            'prenom'    => trim($_POST['prenom'] ?? ''),
            'nom'       => trim($_POST['nom'] ?? ''),
            'email'     => trim(strtolower($_POST['email'] ?? '')),
            'telephone' => trim($_POST['telephone'] ?? ''),
            'password'  => $_POST['password'] ?? '',
            'confirm'   => $_POST['password_confirmation'] ?? '',
        ];

        // Numéros gabonais uniquement — normalisé au format national 0XXXXXXXX
        $phoneNormalized = \Core\Phone::normalizeGabon($data['telephone']);

        // Valeurs à repeupler en cas d'erreur (jamais le mot de passe)
        $oldRepop = [
            'prenom'    => $data['prenom'],
            'nom'       => $data['nom'],
            'email'     => $data['email'],
            'telephone' => $data['telephone'],
        ];

        $errors = [];
        if (mb_strlen($data['prenom']) < 2 || mb_strlen($data['prenom']) > 100) {
            $errors['prenom'] = 'Le prénom doit contenir entre 2 et 100 caractères.';
        }
        if (mb_strlen($data['nom']) < 2 || mb_strlen($data['nom']) > 100) {
            $errors['nom'] = 'Le nom doit contenir entre 2 et 100 caractères.';
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || strlen($data['email']) > 255) {
            $errors['email'] = 'Adresse email invalide.';
        }
        if ($data['telephone'] === '' || $phoneNormalized === null) {
            $errors['telephone'] = 'Numéro gabonais invalide — ex : 074 12 34 56 (01, 06 ou 07)';
        }
        if (strlen($data['password']) < 8 || strlen($data['password']) > 128) {
            $errors['password'] = 'Le mot de passe doit contenir au moins 8 caractères.';
        }
        if ($data['password'] !== $data['confirm']) {
            $errors['password_confirmation'] = 'Les mots de passe ne correspondent pas.';
        }

        if (!empty($errors)) {
            RateLimiter::hit($rlKey, 900);
            Session::flash('errors', $errors);
            Session::flash('old', $oldRepop);
            Response::redirect($backUrl);
        }

        $userModel = new User();
        if ($userModel->findByEmail($data['email'])) {
            RateLimiter::hit($rlKey, 900);
            Session::flash('errors', ['email' => 'Un compte existe déjà avec cette adresse. Connectez-vous pour retrouver vos dossiers.']);
            Session::flash('old', $oldRepop);
            Response::redirect($backUrl);
        }

        try {
            $userId = $userModel->create([
                'nom'       => $data['nom'],
                'prenom'    => $data['prenom'],
                'email'     => $data['email'],
                'telephone' => $phoneNormalized,
                'password'  => $data['password'],
                'role'      => 'visiteur',
                'serie_id'  => null,
            ]);

            // Rattacher les dossiers déjà déposés avec cet email
            (new Dossier())->claimByEmail($userId, $data['email']);
        } catch (\Exception $e) {
            error_log('[CompteOrientation] Création échouée : ' . $e->getMessage());
            Session::flash('errors', ['general' => 'Erreur lors de la création du compte. Veuillez réessayer.']);
            Session::flash('old', $oldRepop);
            Response::redirect($backUrl);
        }

        RateLimiter::clear($rlKey);

        // Connexion automatique
        $user = $userModel->findByEmail($data['email']);
        if ($user) {
            Session::regenerate();
            Session::set('user_id',       $user['id']);
            Session::set('user_uuid',     $user['uuid']);
            Session::set('user_name',     $user['prenom'] . ' ' . $user['nom']);
            Session::set('user_role',     $user['role']);
            Session::set('user_serie_id', $user['serie_id']);
            Session::set('user_photo',    $user['photo_profil'] ?? null);
            Session::set('login_at',      time());
            $userModel->updateLastLogin((int)$user['id']);
        }

        $poursuite = $redirect !== '/eleve/orientation';
        Session::flash('success', $poursuite
            ? '🎉 Compte créé. Reprenez votre démarche — vos informations sont pré-remplies.'
            : '🎉 Compte créé. Bienvenue dans votre espace !');
        Response::redirect($redirect);
    }
}
