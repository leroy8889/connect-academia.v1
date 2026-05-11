<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\{Response, Session, Totp, Redis, RateLimiter};
use Models\Admin;
// Importation de PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class AdminAuthController
{
    private Admin $model;
    private const MAX_ATTEMPTS    = 5;
    private const LOCKOUT_SECONDS = 900; // 15 min

    public function __construct()
    {
        $this->model = new Admin();
    }

    public function showLogin(): void
    {
        if (Session::isAdmin()) {
            Response::redirect('/admin');
        }

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

        // ── Couche 1 : lockout session (fiable, sans Redis) ───────────────
        $lockUntil = (int) Session::get('admin_lock_until', 0);
        if ($lockUntil > 0 && time() < $lockUntil) {
            $waitSecs = $lockUntil - time();
            $waitMin  = max(1, (int) ceil($waitSecs / 60));
            Session::flash('login_locked', true);
            Session::flash('wait_min', $waitMin);
            Session::flash('wait_secs', $waitSecs);
            Response::redirect('/admin/login');
        }

        // ── Couche 2 : Redis rate limiter (IP-based) ──────────────────────
        $rlKey = RateLimiter::ipKey('admin_login');
        if (RateLimiter::tooManyAttempts($rlKey, self::MAX_ATTEMPTS)) {
            $waitSecs = RateLimiter::availableIn($rlKey);
            $waitMin  = max(1, (int) ceil($waitSecs / 60));
            Session::flash('login_locked', true);
            Session::flash('wait_min', $waitMin);
            Session::flash('wait_secs', $waitSecs);
            Response::redirect('/admin/login');
        }

        $admin = $this->model->findByEmail($email);

        if (!$admin || !$this->model->verifyPassword($password, $admin['password_hash'])) {
            RateLimiter::hit($rlKey, self::LOCKOUT_SECONDS);
            $this->model->logConnection($admin['id'] ?? null, $email, $ip, $agent, 'echec');

            // Compteur session : incrémente et vérifie le seuil
            $attempts  = (int) Session::get('admin_login_attempts', 0) + 1;
            $remaining = max(0, self::MAX_ATTEMPTS - $attempts);

            if ($remaining === 0) {
                // Seuil atteint → verrou session
                Session::set('admin_lock_until', time() + self::LOCKOUT_SECONDS);
                Session::remove('admin_login_attempts');
                Session::flash('login_locked', true);
                Session::flash('wait_min', (int) ceil(self::LOCKOUT_SECONDS / 60));
                Session::flash('wait_secs', self::LOCKOUT_SECONDS);
            } else {
                Session::set('admin_login_attempts', $attempts);
                Session::flash('login_attempts_used', $attempts);
                Session::flash('login_attempts_remaining', $remaining);
                Session::flash('error', 'Email ou mot de passe incorrect.');
            }

            Response::redirect('/admin/login');
        }

        // ── Succès : on efface tous les compteurs ─────────────────────────
        Session::remove('admin_login_attempts');
        Session::remove('admin_lock_until');
        RateLimiter::clear($rlKey);

        // --- LOGIQUE 2FA EMAIL AJOUTÉE ---
        $otp = (string)rand(100000, 999999);
        
        // Stockage temporaire des infos admin en session
        Session::set('admin_2fa_pending', [
            'id'      => $admin['id'],
            'email'   => $admin['email'],
            'otp'     => $otp,
            'expires' => time() + 600 // 10 minutes
        ]);

        // Envoi de l'email
        if ($this->sendOTPEmail($admin['email'], $otp)) {
            Session::flash('success', 'Un code de sécurité a été envoyé à votre adresse email.');
            Response::redirect('/admin/login'); // La vue détectera 'admin_2fa_pending' et affichera l'OTP
        } else {
            Session::flash('error', "Erreur lors de l'envoi du code de sécurité.");
            Response::redirect('/admin/login');
        }
    }

    public function verify2fa(): void
    {
        $pending = Session::get('admin_2fa_pending');
        if (!$pending) {
            Response::redirect('/admin/login');
        }

        // On privilégie 'otp_code_val' qui est le champ caché contenant le code complet (6 chiffres)
        $code = trim((string)($_POST['otp_code_val'] ?? $_POST['otp_code'] ?? ''));
        
        if (empty($code) || strlen($code) !== 6) {
            Session::flash('error', 'Veuillez entrer le code complet à 6 chiffres.');
            Response::redirect('/admin/login');
            return;
        }

        // Vérification de l'OTP, de la correspondance et de l'expiration
        if ($code === (string)$pending['otp'] && time() < $pending['expires']) {
            $admin = $this->model->findById((int) $pending['id']);
            
            if (!$admin) {
                Session::remove('admin_2fa_pending');
                Response::redirect('/admin/login');
                return;
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
        } else {
            Session::flash('error', 'Code de sécurité invalide ou expiré.');
            Response::redirect('/admin/login');
        }
    }

    /**
     * Envoi de l'OTP via PHPMailer
     */
    private function sendOTPEmail(string $email, string $otp): bool
    {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'nguemaferdinand02@gmail.com'; 
            $mail->Password   = 'plauhcjfwnxapcja'; 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom('security@connectacademia.com', 'Sécurité Connect Académie');
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = "Code d'accès Administrateur - $otp";

            $mail->Body = "
            <div style='background-color: #1c1c1e; padding: 40px 20px; font-family: Arial, sans-serif; text-align: center;'>
                <div style='max-width: 500px; margin: 0 auto; background-color: #2c2c2e; border-radius: 15px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5);'>
                    <div style='background-color: #8C52FF; padding: 25px; color: #ffffff;'>
                        <h2 style='margin: 0; font-size: 20px; text-transform: uppercase;'>Portail Administratif</h2>
                    </div>
                    <div style='padding: 40px 30px;'>
                        <p style='color: #ffffff;'>Votre code de sécurité temporaire :</p>
                        <div style='margin: 30px 0; border: 2px dashed #8C52FF; padding: 20px; display: inline-block;'>
                            <span style='font-size: 42px; font-weight: bold; letter-spacing: 10px; color: #8C52FF;'>$otp</span>
                        </div>
                        <p style='font-size: 13px; color: #b0b0b0;'>Ce code expire dans 10 minutes.</p>
                    </div>
                </div>
            </div>";

            return $mail->send();
        } catch (Exception $e) {
            return false;
        }
    }

    public function logout(): void
    {
        $redis = Redis::getInstance();
        if ($redis->isAvailable()) {
            $redis->del('admin:activity:' . session_id());
        }

        Session::remove('admin_id');
        Session::remove('admin_2fa_ok');
        Session::remove('admin_name');
        Session::remove('admin_email');
        Session::remove('admin_role');
        Session::remove('admin_2fa_pending');
        Session::regenerate();

        Response::redirect('/admin/login');
    }

    private function startAdminSession(array $admin): void
    {
        Session::regenerate();
        Session::set('admin_id',    $admin['id']);
        Session::set('admin_2fa_ok', true);
        Session::set('admin_name',  trim(($admin['prenom'] ?? '') . ' ' . ($admin['nom'] ?? '')));
        Session::set('admin_email', $admin['email']);
        Session::set('admin_role',  $admin['role']);

        $redis = Redis::getInstance();
        if ($redis->isAvailable()) {
            $timeout = (int)($_ENV['ADMIN_INACTIVITY_TIMEOUT'] ?? 1800);
            $redis->set('admin:activity:' . session_id(), (string)time(), $timeout);
        }
    }

    /**
     * Affiche la vue "Mot de passe oublié"
     */
    public function forgotPassword(): void
    {
        Response::view('admin/auth/forgot', [
            'pageTitle' => "Récupération — Connect'Academia",
        ], 'admin-auth');
    }

    /**
     * Traite la demande de réinitialisation et envoie l'email
     */
    public function sendResetLink(): void
    {
        $email = trim($_POST['email'] ?? '');

        if (!$email) {
            Session::flash('error', 'Veuillez entrer votre adresse email.');
            Response::redirect('/admin/forgot-password');
            return;
        }

        $admin = $this->model->findByEmail($email);

        // --- AJOUT : Si l'email n'existe pas dans la base ---
        if (!$admin) {
            Session::flash('error', "Cette adresse email n'est pas enregistrée dans notre base de données.");
            Response::redirect('/admin/forgot-password');
            return;
        }

        if ($admin) {
            $token = bin2hex(random_bytes(32));
            // Ici, vous devriez idéalement avoir une méthode dans votre modèle 
            // pour stocker ce token en base de données avec une expiration.
            
            // --- AJOUT : Sauvegarde réelle du token ---
            $this->model->setResetToken((int)$admin['id'], $token);

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'nguemaferdinand02@gmail.com'; 
                $mail->Password   = 'plauhcjfwnxapcja'; 
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = 465;
                $mail->CharSet    = 'UTF-8';

                $mail->setFrom('security@connectacademia.com', 'Connect Académie');
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = "Réinitialisation de votre mot de passe";

                // Modification de l'URL pour pointer vers localhost en développement
                $resetLink = "http://localhost:8000/admin/reset-password?token=" . $token;

                $mail->Body = "
                <div style='background-color: #f4f4f9; padding: 40px; font-family: sans-serif;'>
                    <div style='max-width: 600px; margin: auto; background: white; padding: 20px; border-radius: 10px;'>
                        <h2 style='color: #2D1B69;'>Réinitialisation de mot de passe</h2>
                        <p>Vous avez demandé la réinitialisation de votre accès administrateur.</p>
                        <p>Cliquez sur le bouton ci-dessous pour choisir un nouveau mot de passe :</p>
                        <a href='$resetLink' style='display: inline-block; padding: 12px 25px; background-color: #8C52FF; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0;'>Réinitialiser mon mot de passe</a>
                        <p style='font-size: 12px; color: #777;'>Si vous n'êtes pas à l'origine de cette demande, ignorez cet e-mail.</p>
                    </div>
                </div>";

                $mail->send();
            } catch (Exception $e) {
                // Log de l'erreur si nécessaire
            }
        }

        // Message générique par sécurité (ne pas confirmer si l'email existe ou non)
        Session::flash('success', 'Si votre compte existe, un lien de réinitialisation vous a été envoyé.');
        Response::redirect('/admin/forgot-password');
    }

    /**
     * Affiche le formulaire pour saisir le nouveau mot de passe
     */
    public function showResetPassword(): void
    {
        $token = $_GET['token'] ?? '';
        
        if (!$token) {
            Response::redirect('/admin/login');
        }

        // --- RÉCUPÉRATION DE L'EMAIL VIA LE TOKEN ---
        $admin = $this->model->findByResetToken($token);

        Response::view('admin/auth/reset', [
            'pageTitle' => "Nouveau mot de passe — Connect'Academia",
            'token'     => $token,
            'email'     => $admin['email'] ?? null // On passe l'email à la vue
        ], 'admin-auth');
    }

    /**
     * Met à jour le mot de passe dans la base de données
     */
    public function updatePassword(): void
    {
        $token    = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if (empty($password) || $password !== $confirm) {
            Session::flash('error', 'Les mots de passe sont vides ou ne correspondent pas.');
            Response::redirect("/admin/reset-password?token=$token");
        }

        // Note: Vous devrez implémenter la logique de vérification du token 
        // et de mise à jour du hash dans votre modèle Admin.
        
        // --- VÉRIFICATION ET MISE À JOUR ---
        $admin = $this->model->findByResetToken($token);

        if (!$admin) {
            Session::flash('error', 'Ce lien de réinitialisation est invalide ou a expiré.');
            Response::redirect('/admin/forgot-password');
            return;
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        
        if ($this->model->updatePassword((int)$admin['id'], $hashedPassword)) {
            // Suppression du token après usage pour la sécurité
            $this->model->deleteResetToken($token);
            
            Session::flash('success', 'Votre mot de passe a été mis à jour avec succès.');
            Response::redirect('/admin/login');
        } else {
            Session::flash('error', 'Une erreur est survenue lors de la mise à jour.');
            Response::redirect("/admin/reset-password?token=$token");
        }
    }
}