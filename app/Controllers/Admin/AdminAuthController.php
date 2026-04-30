<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\{Response, Session, Totp, Redis, RateLimiter};
use Models\Admin;

class AdminAuthController
{
    private Admin $model;

    public function __construct()
    {
        $this->model = new Admin();
    }

    public function showLogin(): void
    {
        if (Session::isAdmin()) {
            Response::redirect('/admin');
        }

        // Purge tout état 2FA résiduel pour toujours afficher le formulaire de connexion
        Session::remove('admin_2fa_pending');

        Response::view('admin/auth/login', [
            'pageTitle' => "Connexion Admin — Connect'Academia",
        ], 'admin-auth');
    }

    public function login(): void
    {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $agent    = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if (!$email || !$password) {
            Session::flash('error', 'Veuillez remplir tous les champs.');
            Response::redirect('/admin/login');
        }

        // Rate limiting admin : 5 tentatives / 15 minutes par IP
        $rlKey = RateLimiter::ipKey('admin_login');
        if (RateLimiter::tooManyAttempts($rlKey, 5)) {
            $waitMin = max(1, (int) ceil(RateLimiter::availableIn($rlKey) / 60));
            Session::flash('error', "Trop de tentatives. Réessayez dans {$waitMin} minute(s).");
            Response::redirect('/admin/login');
        }

        $admin = $this->model->findByEmail($email);

        if (!$admin || !$this->model->verifyPassword($password, $admin['password_hash'])) {
            RateLimiter::hit($rlKey, 900);
            $this->model->logConnection($admin['id'] ?? null, $email, $ip, $agent, 'echec');
            Session::flash('error', 'Email ou mot de passe incorrect.');
            Response::redirect('/admin/login');
        }

        // Connexion valide : réinitialise le rate limiter
        RateLimiter::clear($rlKey);

        // Connexion directe — 2FA désactivée
        $this->startAdminSession($admin);
        $this->model->logConnection($admin['id'], $email, $ip, $agent, 'succes');
        Response::redirect('/admin');
    }

    public function verify2fa(): void
    {
        $pendingId = Session::get('admin_2fa_pending');
        if (!$pendingId) {
            Response::redirect('/admin/login');
        }

        $code  = preg_replace('/\D/', '', (string) ($_POST['otp_code'] ?? ''));
        $admin = $this->model->findById((int) $pendingId);

        if (!$admin) {
            Session::remove('admin_2fa_pending');
            Response::redirect('/admin/login');
        }

        if (!Totp::verify($admin['totp_secret'], $code)) {
            Session::flash('error', 'Code OTP invalide. Réessayez.');
            Response::redirect('/admin/login');
        }

        Session::remove('admin_2fa_pending');
        $this->startAdminSession($admin);
        $this->model->logConnection(
            $admin['id'],
            $admin['email'],
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            'succes'
        );
        Response::redirect('/admin');
    }

    public function logout(): void
    {
        // Supprimer la clé d'activité Redis AVANT de régénérer l'ID de session
        $redis = Redis::getInstance();
        if ($redis->isAvailable()) {
            $redis->del('admin:activity:' . session_id());
        }

        Session::remove('admin_id');
        Session::remove('admin_2fa_ok');
        Session::remove('admin_name');
        Session::remove('admin_email');
        Session::remove('admin_role');
        Session::regenerate();

        Response::redirect('/admin/login');
    }

    private function startAdminSession(array $admin): void
    {
        Session::regenerate();
        Session::set('admin_id',    $admin['id']);
        Session::set('admin_2fa_ok', true);
        Session::set('admin_name',  trim($admin['prenom'] . ' ' . $admin['nom']));
        Session::set('admin_email', $admin['email']);
        Session::set('admin_role',  $admin['role']);

        // Initialiser le tracker d'activité Redis pour la détection d'inactivité
        $redis = Redis::getInstance();
        if ($redis->isAvailable()) {
            $timeout = (int)($_ENV['ADMIN_INACTIVITY_TIMEOUT'] ?? 1800);
            $redis->set('admin:activity:' . session_id(), (string)time(), $timeout);
        }
    }
}
