<?php
declare(strict_types=1);

namespace Controllers;

use Core\{Response, Session, Validator, RateLimiter};
use Models\User;

class AuthController
{
    public function showConnexion(): void
    {
        if (Session::isLoggedIn()) {
            Response::redirect('/hub');
        }

        Response::view('auth/connexion', [
            'pageTitle' => "Connexion — Connect'Academia",
            'errors'    => Session::getFlash('errors', []),
            'old'       => Session::getFlash('old', []),
            'success'   => Session::getFlash('success'),
        ], 'auth');
    }

    public function connexion(): void
    {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$email || !$password) {
            Session::flash('errors', ['general' => 'Veuillez remplir tous les champs.']);
            Session::flash('old', ['email' => $email]);
            Response::redirect('/auth/connexion');
        }

        // Rate limiting via Redis : 10 tentatives / 15 minutes par IP
        $rlKey = RateLimiter::ipKey('user_login');
        if (RateLimiter::tooManyAttempts($rlKey, 10)) {
            $waitMin = max(1, (int) ceil(RateLimiter::availableIn($rlKey) / 60));
            Session::flash('errors', ['general' => "Trop de tentatives. Réessayez dans {$waitMin} minute(s)."]);
            Response::redirect('/auth/connexion');
        }

        $userModel = new User();
        $user      = $userModel->findByEmail($email);

        if (!$user || !$userModel->verifyPassword($password, $user['password_hash'])) {
            RateLimiter::hit($rlKey, 900); // fenêtre 15 min
            Session::flash('errors', ['general' => 'Email ou mot de passe incorrect.']);
            Session::flash('old', ['email' => $email]);
            Response::redirect('/auth/connexion');
        }

        if (!$user['is_active'] || $user['is_deleted']) {
            Session::flash('errors', ['general' => 'Votre compte a été suspendu. Contactez un administrateur.']);
            Response::redirect('/auth/connexion');
        }

        // Connexion réussie : on réinitialise le compteur d'échecs
        RateLimiter::clear($rlKey);

        Session::regenerate();
        $this->setUserSession($user);
        $userModel->updateLastLogin((int) $user['id']);

        Response::redirect('/hub');
    }

    public function showInscription(): void
    {
        if (Session::isLoggedIn()) {
            Response::redirect('/hub');
        }

        $series = $this->getSeries();

        Response::view('auth/inscription', [
            'pageTitle' => "Inscription — Connect'Academia",
            'errors'    => Session::getFlash('errors', []),
            'old'       => Session::getFlash('old', []),
            'series'    => $series,
        ], 'auth');
    }

    public function inscription(): void
    {
        $data = [
            'nom'            => trim($_POST['nom'] ?? ''),
            'prenom'         => trim($_POST['prenom'] ?? ''),
            'email'          => trim(strtolower($_POST['email'] ?? '')),
            'password'       => $_POST['password'] ?? '',
            'password_confirmation' => $_POST['password_confirmation'] ?? '',
            'serie_id'       => !empty($_POST['serie_id']) ? (int) $_POST['serie_id'] : null,
            'etablissement'  => trim($_POST['etablissement'] ?? ''),
        ];

        $validator = new Validator();
        if (!$validator->validate($data, [
            'nom'      => 'required|min:2|max:100|alpha',
            'prenom'   => 'required|min:2|max:100|alpha',
            'email'    => 'required|email|max:255',
            'password' => 'required|min:8|max:128|confirmed',
        ])) {
            Session::flash('errors', $validator->getErrors());
            Session::flash('old', array_merge($data, ['password' => '', 'password_confirmation' => '']));
            Response::redirect('/auth/inscription');
        }

        $userModel = new User();

        if ($userModel->findByEmail($data['email'])) {
            Session::flash('errors', ['email' => 'Cette adresse email est déjà utilisée.']);
            Session::flash('old', array_merge($data, ['password' => '']));
            Response::redirect('/auth/inscription');
        }

        try {
            $userId = $userModel->create($data);
        } catch (\Exception $e) {
            error_log('Inscription error: ' . $e->getMessage());
            Session::flash('errors', ['general' => 'Erreur lors de la création du compte. Réessayez.']);
            Session::flash('old', array_merge($data, ['password' => '']));
            Response::redirect('/auth/inscription');
        }

        Session::flash('success', 'Compte créé avec succès ! Bienvenue sur Connect\'Academia.');
        Response::redirect('/auth/connexion');
    }

    public function deconnexion(): void
    {
        Session::destroy();
        Response::redirect('/auth/connexion');
    }

    public function showForgot(): void
    {
        Response::view('auth/mot-de-passe-oublie', [
            'pageTitle' => "Mot de passe oublié — Connect'Academia",
            'errors'    => Session::getFlash('errors', []),
            'success'   => Session::getFlash('success'),
        ], 'auth');
    }

    public function forgot(): void
    {
        $email = trim(strtolower($_POST['email'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('errors', ['email' => 'Adresse email invalide.']);
            Response::redirect('/auth/mot-de-passe-oublie');
        }

        // Toujours confirmer (ne pas révéler si l'email existe)
        $userModel = new User();
        $user = $userModel->findByEmail($email);

        if ($user) {
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600);
            // TODO Phase 9: envoyer email + insérer dans password_resets
        }

        Session::flash('success', 'Si cette adresse est enregistrée, vous recevrez un email sous peu.');
        Response::redirect('/auth/mot-de-passe-oublie');
    }

    public function showReset(string $token): void
    {
        Response::view('auth/reinitialiser', [
            'pageTitle' => "Nouveau mot de passe — Connect'Academia",
            'token'     => htmlspecialchars($token),
            'errors'    => Session::getFlash('errors', []),
        ], 'auth');
    }

    public function reset(): void
    {
        Session::flash('errors', ['general' => 'Fonctionnalité bientôt disponible.']);
        Response::redirect('/auth/connexion');
    }

    private function setUserSession(array $user): void
    {
        Session::set('user_id',    $user['id']);
        Session::set('user_uuid',  $user['uuid']);
        Session::set('user_name',  $user['prenom'] . ' ' . $user['nom']);
        Session::set('user_role',  $user['role']);
        Session::set('user_serie_id', $user['serie_id']);
        Session::set('user_photo', $user['photo_profil'] ?? null);
        Session::set('login_at',   time());
    }

    private function getSeries(): array
    {
        try {
            return \Core\Database::getInstance()->getConnection()
                ->query("SELECT id, nom, description, couleur FROM series WHERE is_active = 1 ORDER BY nom")
                ->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
}
