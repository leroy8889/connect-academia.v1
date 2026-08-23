<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\{Response, Session, Totp, Redis, RateLimiter, AdminPermissions};
use Models\Admin;
// Importation de PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class AdminAuthController
{
    private ?Admin $model = null;
    private const MAX_ATTEMPTS    = 5;
    private const LOCKOUT_SECONDS = 900; // 15 min

    private function model(): Admin
    {
        if ($this->model === null) {
            $this->model = new Admin();
        }
        return $this->model;
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
            Response::redirect('/Administratorconnect/login');
        }

        // ── Couche 1 : lockout session (fiable, sans Redis) ───────────────
        $lockUntil = (int) Session::get('admin_lock_until', 0);
        if ($lockUntil > 0 && time() < $lockUntil) {
            $waitSecs = $lockUntil - time();
            $waitMin  = max(1, (int) ceil($waitSecs / 60));
            Session::flash('login_locked', true);
            Session::flash('wait_min', $waitMin);
            Session::flash('wait_secs', $waitSecs);
            Response::redirect('/Administratorconnect/login');
        }

        // ── Couche 2 : Redis rate limiter (IP-based) ──────────────────────
        $rlKey = RateLimiter::ipKey('admin_login');
        if (RateLimiter::tooManyAttempts($rlKey, self::MAX_ATTEMPTS)) {
            $waitSecs = RateLimiter::availableIn($rlKey);
            $waitMin  = max(1, (int) ceil($waitSecs / 60));
            Session::flash('login_locked', true);
            Session::flash('wait_min', $waitMin);
            Session::flash('wait_secs', $waitSecs);
            Response::redirect('/Administratorconnect/login');
        }

        $admin = $this->model()->findByEmail($email);

        if (!$admin || !$this->model()->verifyPassword($password, $admin['password_hash'])) {
            RateLimiter::hit($rlKey, self::LOCKOUT_SECONDS);
            $this->model()->logConnection($admin['id'] ?? null, $email, $ip, $agent, 'echec');
            \Core\SecurityMonitor::loginFailure($email, $ip);

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

            Response::redirect('/Administratorconnect/login');
        }

        // ── Succès : on efface tous les compteurs ─────────────────────────
        Session::remove('admin_login_attempts');
        Session::remove('admin_lock_until');
        RateLimiter::clear($rlKey);

        // --- LOGIQUE 2FA EMAIL AJOUTÉE ---
        $otp = (string)random_int(100000, 999999);
        
        // Stockage temporaire des infos admin en session
        Session::set('admin_2fa_pending', [
            'id'      => $admin['id'],
            'email'   => $admin['email'],
            'otp'     => $otp,
            'expires' => time() + 600 // 10 minutes
        ]);
        // Nouveau code = compteur de tentatives OTP remis à zéro
        Session::remove('admin_2fa_attempts');

        // Envoi du code de sécurité par email (jamais affiché à l'écran)
        $emailSent = $this->sendOTPEmail($admin['email'], $otp);

        if ($emailSent) {
            Session::flash('success', 'Un code de sécurité a été envoyé à votre adresse email.');
            Response::redirect('/Administratorconnect/login');
        } else {
            Session::flash('error', "Erreur lors de l'envoi du code de sécurité. Réessayez.");
            Response::redirect('/Administratorconnect/login');
        }
    }

    public function verify2fa(): void
    {
        $pending = Session::get('admin_2fa_pending');
        if (!$pending) {
            Response::redirect('/Administratorconnect/login');
        }

        // Code expiré → on invalide tout, l'admin doit se reconnecter (nouveau code).
        if (time() >= (int)($pending['expires'] ?? 0)) {
            Session::remove('admin_2fa_pending');
            Session::remove('admin_2fa_attempts');
            Session::flash('error', 'Votre code de sécurité a expiré. Reconnectez-vous pour en recevoir un nouveau.');
            Response::redirect('/Administratorconnect/login');
            return;
        }

        // On privilégie 'otp_code_val' qui est le champ caché contenant le code complet (6 chiffres)
        $code = preg_replace('/\D/', '', (string)($_POST['otp_code_val'] ?? $_POST['otp_code'] ?? ''));

        if (strlen($code) !== 6) {
            Session::flash('error', 'Veuillez entrer le code complet à 6 chiffres.');
            Response::redirect('/Administratorconnect/login');
            return;
        }

        // ── Anti-brute-force OTP : 5 essais maximum par code envoyé ────────
        // Sans ce garde, 10⁶ combinaisons seraient testables tant que le code
        // reste valide (10 min). Au 5ᵉ échec on détruit le code en attente.
        $attempts = (int) Session::get('admin_2fa_attempts', 0);
        if ($attempts >= self::MAX_ATTEMPTS) {
            Session::remove('admin_2fa_pending');
            Session::remove('admin_2fa_attempts');
            Session::flash('error', 'Trop de tentatives sur le code de sécurité. Reconnectez-vous pour recevoir un nouveau code.');
            Response::redirect('/Administratorconnect/login');
            return;
        }

        // Comparaison à temps constant (anti-timing)
        if (hash_equals((string)$pending['otp'], $code)) {
            $admin = $this->model()->findById((int) $pending['id']);

            if (!$admin) {
                Session::remove('admin_2fa_pending');
                Session::remove('admin_2fa_attempts');
                Response::redirect('/Administratorconnect/login');
                return;
            }

            Session::remove('admin_2fa_pending');
            Session::remove('admin_2fa_attempts');
            $this->startAdminSession($admin);

            $this->model()->logConnection(
                $admin['id'],
                $admin['email'],
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                'succes'
            );
            Response::redirect(AdminPermissions::getDefaultPath($admin['role'] ?? 'admin'));
        } else {
            $attempts++;
            $remaining = max(0, self::MAX_ATTEMPTS - $attempts);
            if ($remaining === 0) {
                Session::remove('admin_2fa_pending');
                Session::remove('admin_2fa_attempts');
                Session::flash('error', 'Trop de tentatives sur le code de sécurité. Reconnectez-vous pour recevoir un nouveau code.');
            } else {
                Session::set('admin_2fa_attempts', $attempts);
                Session::flash('error', "Code de sécurité invalide. Il vous reste {$remaining} tentative(s).");
            }
            Response::redirect('/Administratorconnect/login');
        }
    }

    /**
     * Envoi de l'OTP via PHPMailer.
     * Le code est toujours envoyé par email (SMTP) — jamais affiché à l'écran
     * ni court-circuité, quel que soit l'environnement.
     */
    private function sendOTPEmail(string $email, string $otp): bool
    {
        // ── Envoi SMTP ────────────────────────────────────────────────────
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Timeout    = 10; // ne bloque jamais plus de 10 secondes
            $mail->Host       = $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['MAIL_USERNAME'] ?? '';
            $mail->Password   = $_ENV['MAIL_PASSWORD'] ?? '';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = (int)($_ENV['MAIL_PORT'] ?? 465);
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom(
                $_ENV['MAIL_FROM_ADDRESS'] ?? 'security@connectacademia.com',
                $_ENV['MAIL_FROM_NAME'] ?? 'Sécurité Connect Academia'
            );
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = "Code d'accès Administrateur — Connect'Academia";

            $mail->Body = "
            <div style='background-color:#1c1c1e;padding:40px 20px;font-family:Arial,sans-serif;text-align:center;'>
              <div style='max-width:500px;margin:0 auto;background-color:#2c2c2e;border-radius:15px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,0.5);'>
                <div style='background-color:#8B52FA;padding:25px;color:#ffffff;'>
                  <h2 style='margin:0;font-size:20px;text-transform:uppercase;'>Portail Administratif</h2>
                </div>
                <div style='padding:40px 30px;'>
                  <p style='color:#ffffff;'>Votre code de sécurité temporaire :</p>
                  <div style='margin:30px 0;border:2px dashed #8B52FA;padding:20px;display:inline-block;'>
                    <span style='font-size:42px;font-weight:bold;letter-spacing:10px;color:#8B52FA;'>$otp</span>
                  </div>
                  <p style='font-size:13px;color:#b0b0b0;'>Ce code expire dans 10 minutes.</p>
                </div>
              </div>
            </div>";

            return $mail->send();
        } catch (Exception $e) {
            error_log("Erreur PHPMailer SMTP (OTP admin) : " . $mail->ErrorInfo);
            return false;
        }
    }

    public function logout(): void
    {
        Session::remove('admin_id');
        Session::remove('admin_2fa_ok');
        Session::remove('admin_name');
        Session::remove('admin_email');
        Session::remove('admin_role');
        Session::remove('admin_2fa_pending');
        Session::regenerate();

        Response::redirect('/Administratorconnect/login');
    }

    private function startAdminSession(array $admin): void
    {
        Session::regenerate();
        Session::set('admin_id',    $admin['id']);
        Session::set('admin_2fa_ok', true);
        Session::set('admin_name',  trim(($admin['prenom'] ?? '') . ' ' . ($admin['nom'] ?? '')));
        Session::set('admin_email', $admin['email']);
        Session::set('admin_role',  $admin['role']);
        // Plus de clé Redis d'inactivité : la session admin ne se déconnecte
        // plus automatiquement (voir AdminMiddleware).
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
            Response::redirect('/Administratorconnect/forgot-password');
            return;
        }

        // Rate limiting par IP : 5 demandes / 15 min (anti-abus / anti-spam)
        $rlKey = RateLimiter::ipKey('admin_forgot');
        if (RateLimiter::tooManyAttempts($rlKey, 5)) {
            // Même réponse générique — on ne divulgue jamais l'état du compte
            Session::flash('success', 'Si votre compte existe, un lien de réinitialisation vous a été envoyé.');
            Response::redirect('/Administratorconnect/forgot-password');
            return;
        }
        RateLimiter::hit($rlKey, 900);

        $admin = $this->model()->findByEmail($email);

        // IMPORTANT : aucune divulgation de l'existence du compte (anti-énumération).
        // Qu'un admin existe ou non, on envoie éventuellement l'email en silence et
        // on affiche toujours le même message générique en bas de méthode.
        if ($admin) {
            $token = bin2hex(random_bytes(32));

            // Token stocké HASHÉ (SHA-256) en base — jamais en clair.
            $this->model()->setResetToken((int)$admin['id'], $token);

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Timeout    = 10; // ne bloque jamais plus de 10 secondes
                $mail->Host       = $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = $_ENV['MAIL_USERNAME'] ?? '';
                $mail->Password   = $_ENV['MAIL_PASSWORD'] ?? '';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = (int)($_ENV['MAIL_PORT'] ?? 465);
                $mail->CharSet    = 'UTF-8';

                $mail->setFrom(
                    $_ENV['MAIL_FROM_ADDRESS'] ?? 'security@connectacademia.com',
                    'Connect Académie'
                );
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = "Réinitialisation de votre mot de passe";

                // Modification de l'URL pour pointer vers localhost en développement
                $resetLink = rtrim($_ENV['APP_URL'] ?? '', '/') . '/Administratorconnect/reset-password?token=' . urlencode($token);

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
        Response::redirect('/Administratorconnect/forgot-password');
    }

    /**
     * Affiche le formulaire pour saisir le nouveau mot de passe
     */
    public function showResetPassword(): void
    {
        $token = $_GET['token'] ?? '';
        
        if (!$token) {
            Response::redirect('/Administratorconnect/login');
        }

        // --- RÉCUPÉRATION DE L'EMAIL VIA LE TOKEN ---
        $admin = $this->model()->findByResetToken($token);

        Response::view('admin/auth/reset', [
            'pageTitle'  => "Nouveau mot de passe — Connect'Academia",
            'token'      => $token,
            'email'      => $admin['email'] ?? null, // On passe l'email à la vue
            // Le flash est consommé ici : une vue ne lit jamais la session.
            'flashError' => Session::getFlash('error'),
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

        // Format token attendu : 64 caractères hexadécimaux (bin2hex(random_bytes(32)))
        if (!is_string($token) || strlen($token) !== 64 || !ctype_xdigit($token)) {
            Session::flash('error', 'Lien de réinitialisation invalide.');
            Response::redirect('/Administratorconnect/forgot-password');
            return;
        }

        if (empty($password) || $password !== $confirm) {
            Session::flash('error', 'Les mots de passe sont vides ou ne correspondent pas.');
            Response::redirect("/Administratorconnect/reset-password?token=$token");
            return;
        }

        // Robustesse minimale du mot de passe administrateur
        if (strlen($password) < 10) {
            Session::flash('error', 'Le mot de passe administrateur doit contenir au moins 10 caractères.');
            Response::redirect("/Administratorconnect/reset-password?token=$token");
            return;
        }

        // --- VÉRIFICATION ET MISE À JOUR ---
        $admin = $this->model()->findByResetToken($token);

        if (!$admin) {
            Session::flash('error', 'Ce lien de réinitialisation est invalide ou a expiré.');
            Response::redirect('/Administratorconnect/forgot-password');
            return;
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        
        if ($this->model()->updatePassword((int)$admin['id'], $hashedPassword)) {
            // Suppression du token après usage pour la sécurité
            $this->model()->deleteResetToken($token);

            Session::flash('success', 'Votre mot de passe a été mis à jour avec succès.');
            Response::redirect('/Administratorconnect/login');
        } else {
            Session::flash('error', 'Une erreur est survenue lors de la mise à jour.');
            Response::redirect("/Administratorconnect/reset-password?token=$token");
        }
    }
}