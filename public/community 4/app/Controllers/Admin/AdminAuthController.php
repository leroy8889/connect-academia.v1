<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\{Response, Session, Database};
use Models\User;

class AdminAuthController
{
    public function showLogin(): void
    {
        // Si déjà connecté en tant qu'admin, rediriger vers le dashboard
        if (Session::isLoggedIn() && Session::userRole() === 'admin') {
            Response::redirect('/admin');
        }
        Response::view('admin/auth/login', [
            'pageTitle' => 'Administration — StudyLink',
        ], 'admin-auth');
    }

    public function login(): void
    {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            Session::flash('errors', ['general' => 'Veuillez remplir tous les champs']);
            Response::redirect('/admin/login');
        }

        $userModel = new User();
        $user = $userModel->findByEmail($email);

        // Vérifier que l'utilisateur existe ET est admin
        if (!$user || $user['role'] !== 'admin' || !$userModel->verifyPassword($password, $user['password_hash'])) {
            $this->logLoginAttempt($user ? (int) $user['id'] : 0, 'failed');
            Session::flash('errors', ['general' => 'Identifiants incorrects ou accès non autorisé']);
            Session::flash('old', ['email' => $email]);
            Response::redirect('/admin/login');
        }

        if (!$user['is_active']) {
            Session::flash('errors', ['general' => 'Ce compte administrateur a été désactivé.']);
            Response::redirect('/admin/login');
        }

        // Connexion admin réussie
        Session::regenerate();
        Session::set('user_id', $user['id']);
        Session::set('user_uuid', $user['uuid']);
        Session::set('user_role', $user['role']);
        Session::set('user_name', $user['prenom'] . ' ' . $user['nom']);
        Session::set('user_photo', !empty($user['photo_profil']) ? url($user['photo_profil']) : null);
        Session::set('admin_login_at', time());
        Session::set('login_at', time());

        $userModel->updateLastLogin((int) $user['id']);
        $this->logLoginAttempt((int) $user['id'], 'success');

        Response::redirect('/admin');
    }

    public function logout(): void
    {
        Session::destroy();
        Response::redirect('/admin/login');
    }

    private function logLoginAttempt(int $userId, string $status): void
    {
        if ($userId <= 0) {
            return;
        }

        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare(
                "INSERT INTO admin_logins (user_id, ip_address, user_agent, status, created_at) VALUES (?, ?, ?, ?, NOW())"
            );
            $stmt->execute([
                $userId,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                $status,
            ]);
        } catch (\Exception $e) {
            // Ne pas bloquer la connexion si le log échoue
            error_log("Admin login log failed: " . $e->getMessage());
        }
    }
}

