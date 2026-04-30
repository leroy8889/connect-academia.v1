<?php
declare(strict_types=1);

namespace Controllers;

use Core\{Response, Session, Validator};
use Models\User;

class AuthController
{
    public function showLogin(): void
    {
        if (Session::isLoggedIn()) {
            Response::redirect('/');
        }

        Response::view('auth/login', [
            'pageTitle' => 'Connexion — StudyLink',
        ], 'auth');
    }

    public function showRegister(): void
    {
        if (Session::isLoggedIn()) {
            Response::redirect('/');
        }

        Response::view('auth/register', [
            'pageTitle' => 'Inscription — StudyLink',
        ], 'auth');
    }

    public function register(): void
    {
        $data = [
            'nom'       => trim($_POST['nom'] ?? ''),
            'prenom'    => trim($_POST['prenom'] ?? ''),
            'email'     => trim($_POST['email'] ?? ''),
            'password'  => $_POST['password'] ?? '',
            'password_confirm' => $_POST['password_confirm'] ?? '',
            'classe'    => $_POST['classe'] ?? null,
            'role'      => 'eleve',
            'terms'     => isset($_POST['terms'])
        ];

        $validator = new Validator();

        $rules = [
            'nom'      => 'required|min:2|max:100',
            'prenom'   => 'required|min:2|max:100',
            'email'    => 'required|email',
            'password' => 'required|min:8',
            'classe'   => 'required'
        ];

        if (!$validator->validate($data, $rules)) {

            Session::flash('errors', $validator->getErrors());
            Session::flash('old', $data);

            Response::redirect('/register');
        }

        if ($data['password'] !== $data['password_confirm']) {

            Session::flash('errors', [
                'password' => 'Les mots de passe ne correspondent pas'
            ]);

            Session::flash('old', $data);

            Response::redirect('/register');
        }

        if (!$data['terms']) {

            Session::flash('errors', [
                'terms' => "Vous devez accepter les conditions d'utilisation"
            ]);

            Session::flash('old', $data);

            Response::redirect('/register');
        }

        $userModel = new User();

        if ($userModel->findByEmail($data['email'])) {

            Session::flash('errors', [
                'email' => 'Cette adresse email est déjà utilisée'
            ]);

            Session::flash('old', $data);

            Response::redirect('/register');
        }

        try {

            $userId = $userModel->create($data);

            if (!$userId) {
                throw new \Exception("Erreur création utilisateur");
            }

        } catch (\Exception $e) {

            Session::flash('errors', [
                'general' => 'Erreur lors de la création du compte'
            ]);

            Session::flash('old', $data);

            Response::redirect('/register');
        }

        Session::flash('success', 'Compte créé avec succès');

        Response::redirect('/login');
    }

    public function login(): void
    {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$email || !$password) {
            Session::flash('errors', ['general' => 'Veuillez remplir tous les champs']);
            Response::redirect('/login');
        }

        $userModel = new User();
        $user = $userModel->findByEmail($email);

        if (!$user || !$userModel->verifyPassword($password, $user['password_hash'])) {
            Session::flash('errors', ['general' => 'Email ou mot de passe incorrect']);
            Response::redirect('/login');
        }

        if (!$user['is_active']) {
            Session::flash('errors', ['general' => 'Votre compte a été suspendu. Contactez un administrateur.']);
            Response::redirect('/login');
        }

        Session::regenerate();

        Session::set('user_id',    $user['id']);
        Session::set('user_uuid',  $user['uuid']);
        Session::set('user_name',  $user['prenom'] . ' ' . $user['nom']);
        Session::set('user_role',  $user['role']);
        Session::set('user_classe',$user['classe'] ?? null);
        Session::set('user_photo', !empty($user['photo_profil']) ? url($user['photo_profil']) : null);
        Session::set('login_at',   time());

        $userModel->updateLastLogin((int) $user['id']);

        Response::redirect('/');
    }

    public function logout(): void
    {
        Session::destroy();
        Response::redirect('/login');
    }

    public function showForgotPassword(): void
    {
        if (Session::isLoggedIn()) {
            Response::redirect('/');
        }
        Response::view('auth/forgot-password', [
            'pageTitle' => 'Mot de passe oublié — StudyLink',
        ], 'auth');
    }

    public function forgotPassword(): void
    {
        $email = trim($_POST['email'] ?? '');

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('errors', ['email' => 'Adresse email invalide']);
            Response::redirect('/forgot-password');
        }

        // On confirme toujours le succès pour ne pas divulguer si l'email existe
        Session::flash('success', 'Si cette adresse est enregistrée, vous recevrez un email de réinitialisation dans quelques minutes.');
        Response::redirect('/forgot-password');
    }

    public function resetPassword(): void
    {
        Session::flash('errors', ['general' => 'Lien de réinitialisation invalide ou expiré.']);
        Response::redirect('/login');
    }

    public function verifyEmail(string $token): void
    {
        if (empty($token)) {
            Session::flash('errors', ['general' => 'Lien de vérification invalide.']);
            Response::redirect('/login');
        }

        $userModel = new User();
        $user = $userModel->findByEmailToken($token);

        if (!$user) {
            Session::flash('errors', ['general' => 'Lien de vérification invalide ou déjà utilisé.']);
            Response::redirect('/login');
        }

        $userModel->markEmailVerified((int) $user['id']);

        Session::flash('success', 'Email vérifié avec succès. Vous pouvez maintenant vous connecter.');
        Response::redirect('/login');
    }
}