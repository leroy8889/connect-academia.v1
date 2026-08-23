<?php
declare(strict_types=1);

namespace Controllers\Gestionnaire;

use Core\{Database, RateLimiter, Response, Session};
use Services\Orientation\JournalGestionnaire;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Espace de connexion dédié aux gestionnaires d'établissements (module Orientation).
 * Totalement séparé de l'espace utilisateur (/auth/connexion) et de l'espace
 * super-admin (/Administratorconnect) : clés de session propres, page propre.
 */
class GestionnaireAuthController
{
    private const MAX_ATTEMPTS     = 5;
    private const LOCKOUT_SECONDS  = 900; // 15 min

    public function showLogin(): void
    {
        if (Session::isGestionnaire()) {
            Response::redirect('/gestionnaire');
        }

        Response::view('gestionnaire/auth/login', [
            'pageTitle'         => "Espace établissement — Connect'Academia",
            'error'             => Session::getFlash('error'),
            'success'           => Session::getFlash('success'),
            'oldEmail'          => Session::getFlash('old_email', ''),
            'loginLocked'       => (bool) Session::getFlash('login_locked', false),
            'waitMin'           => (int) Session::getFlash('wait_min', 0),
            'waitSecs'          => (int) Session::getFlash('wait_secs', 0),
            'attemptsUsed'      => (int) Session::getFlash('login_attempts_used', 0),
            'attemptsRemaining' => (int) Session::getFlash('login_attempts_remaining', 0),
        ], 'admin-auth');
    }

    public function login(): void
    {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$email || !$password) {
            Session::flash('error', 'Veuillez remplir tous les champs.');
            Session::flash('old_email', $email);
            Response::redirect('/gestionnaire/login');
            return;
        }

        // Verrou session (fiable même sans Redis)
        $lockUntil = (int) Session::get('gest_lock_until', 0);
        if ($lockUntil > 0 && time() < $lockUntil) {
            Session::flash('login_locked', true);
            Session::flash('wait_min', max(1, (int) ceil(($lockUntil - time()) / 60)));
            Session::flash('wait_secs', max(1, $lockUntil - time()));
            Response::redirect('/gestionnaire/login');
            return;
        }

        // Rate limiter Redis (par IP)
        $rlKey = RateLimiter::ipKey('gestionnaire_login');
        if (RateLimiter::tooManyAttempts($rlKey, self::MAX_ATTEMPTS)) {
            $availableIn = max(1, RateLimiter::availableIn($rlKey));
            Session::flash('login_locked', true);
            Session::flash('wait_min', max(1, (int) ceil($availableIn / 60)));
            Session::flash('wait_secs', $availableIn);
            Response::redirect('/gestionnaire/login');
            return;
        }

        $gest = $this->findGestionnaireByEmail($email);

        if (!$gest || !password_verify($password, $gest['password_hash'])) {
            RateLimiter::hit($rlKey, self::LOCKOUT_SECONDS);
            $attempts = (int) Session::get('gest_login_attempts', 0) + 1;
            if ($attempts >= self::MAX_ATTEMPTS) {
                Session::set('gest_lock_until', time() + self::LOCKOUT_SECONDS);
                Session::remove('gest_login_attempts');
                Session::flash('login_locked', true);
                Session::flash('wait_min', (int) ceil(self::LOCKOUT_SECONDS / 60));
                Session::flash('wait_secs', self::LOCKOUT_SECONDS);
            } else {
                Session::set('gest_login_attempts', $attempts);
                Session::flash('error', 'Email ou mot de passe incorrect.');
                Session::flash('login_attempts_used', $attempts);
                Session::flash('login_attempts_remaining', self::MAX_ATTEMPTS - $attempts);
            }
            Session::flash('old_email', $email);
            Response::redirect('/gestionnaire/login');
            return;
        }

        if (!(int)$gest['is_active']) {
            Session::flash('error', 'Ce compte gestionnaire est suspendu. Contactez Connect\'Academia.');
            Response::redirect('/gestionnaire/login');
            return;
        }

        // Mot de passe validé → on efface les compteurs d'échec.
        Session::remove('gest_login_attempts');
        Session::remove('gest_lock_until');
        RateLimiter::clear($rlKey);

        // ── Deuxième facteur : code à usage unique envoyé par e-mail ───────
        // La session gestionnaire n'est PAS ouverte tant que le code n'est pas
        // vérifié (voir verify2fa). On ne stocke que l'id + l'e-mail + le code.
        $otp = (string) random_int(100000, 999999);

        Session::set('gest_2fa_pending', [
            'id'      => (int) $gest['id'],
            'email'   => $gest['email'],
            'otp'     => $otp,
            'expires' => time() + 600, // 10 minutes
        ]);
        // Nouveau code = compteur de tentatives OTP remis à zéro.
        Session::remove('gest_2fa_attempts');

        if ($this->sendOTPEmail($gest['email'], $otp)) {
            Session::flash('success', 'Un code de sécurité a été envoyé à votre adresse e-mail.');
        } else {
            // Échec d'envoi → on annule le 2FA en attente pour ne pas bloquer.
            Session::remove('gest_2fa_pending');
            Session::flash('error', "Erreur lors de l'envoi du code de sécurité. Réessayez.");
        }
        Response::redirect('/gestionnaire/login');
    }

    /**
     * Vérifie le code 2FA reçu par e-mail et ouvre la session gestionnaire.
     * Anti-brute-force : 5 essais maximum par code, code détruit ensuite.
     */
    public function verify2fa(): void
    {
        $pending = Session::get('gest_2fa_pending');
        if (!$pending) {
            Response::redirect('/gestionnaire/login');
            return;
        }

        // Code expiré → on invalide tout, l'utilisateur doit se reconnecter.
        if (time() >= (int) ($pending['expires'] ?? 0)) {
            Session::remove('gest_2fa_pending');
            Session::remove('gest_2fa_attempts');
            Session::flash('error', 'Votre code de sécurité a expiré. Reconnectez-vous pour en recevoir un nouveau.');
            Response::redirect('/gestionnaire/login');
            return;
        }

        // Champ caché 'otp_code_val' (code complet) prioritaire.
        $code = preg_replace('/\D/', '', (string) ($_POST['otp_code_val'] ?? $_POST['otp_code'] ?? ''));

        if (strlen($code) !== 6) {
            Session::flash('error', 'Veuillez entrer le code complet à 6 chiffres.');
            Response::redirect('/gestionnaire/login');
            return;
        }

        $attempts = (int) Session::get('gest_2fa_attempts', 0);
        if ($attempts >= self::MAX_ATTEMPTS) {
            Session::remove('gest_2fa_pending');
            Session::remove('gest_2fa_attempts');
            Session::flash('error', 'Trop de tentatives sur le code de sécurité. Reconnectez-vous pour recevoir un nouveau code.');
            Response::redirect('/gestionnaire/login');
            return;
        }

        // Comparaison à temps constant (anti-timing).
        if (!hash_equals((string) $pending['otp'], $code)) {
            $attempts++;
            $remaining = max(0, self::MAX_ATTEMPTS - $attempts);
            if ($remaining === 0) {
                Session::remove('gest_2fa_pending');
                Session::remove('gest_2fa_attempts');
                Session::flash('error', 'Trop de tentatives sur le code de sécurité. Reconnectez-vous pour recevoir un nouveau code.');
            } else {
                Session::set('gest_2fa_attempts', $attempts);
                Session::flash('error', "Code de sécurité invalide. Il vous reste {$remaining} tentative(s).");
            }
            Response::redirect('/gestionnaire/login');
            return;
        }

        // Code valide → on re-vérifie que le lien gestionnaire est toujours actif.
        $gest = $this->findGestionnaireById((int) $pending['id']);

        Session::remove('gest_2fa_pending');
        Session::remove('gest_2fa_attempts');

        if (!$gest || !(int) $gest['is_active']) {
            Session::flash('error', 'Ce compte gestionnaire est suspendu ou introuvable. Contactez Connect\'Academia.');
            Response::redirect('/gestionnaire/login');
            return;
        }

        Session::regenerate();
        Session::set('gestionnaire_id',       (int) $gest['id']);
        Session::set('gestionnaire_name',     trim(($gest['prenom'] ?? '') . ' ' . ($gest['nom'] ?? '')));
        Session::set('gestionnaire_email',    $gest['email']);
        Session::set('gestionnaire_etab_id',  (int) $gest['etablissement_id']);
        Session::set('gestionnaire_etab_nom', $gest['etab_nom']);
        Session::set('gestionnaire_role',     $gest['ea_role']);

        Database::getInstance()->getConnection()
            ->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')
            ->execute([(int) $gest['id']]);

        JournalGestionnaire::log('session.connexion', 'user', (int) $gest['id'],
            'Connexion à l\'espace gestionnaire (2FA validé)');

        Response::redirect('/gestionnaire');
    }

    /**
     * Envoi du code OTP par e-mail (SMTP). Jamais affiché à l'écran.
     */
    private function sendOTPEmail(string $email, string $otp): bool
    {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Timeout    = 10;
            $mail->Host       = $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['MAIL_USERNAME'] ?? '';
            $mail->Password   = $_ENV['MAIL_PASSWORD'] ?? '';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = (int) ($_ENV['MAIL_PORT'] ?? 465);
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom(
                $_ENV['MAIL_FROM_ADDRESS'] ?? 'security@connectacademia.com',
                $_ENV['MAIL_FROM_NAME'] ?? 'Sécurité Connect Academia'
            );
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = "Code d'accès Établissement — Connect'Academia";

            $mail->Body = "
            <div style='background-color:#1c1c1e;padding:40px 20px;font-family:Arial,sans-serif;text-align:center;'>
              <div style='max-width:500px;margin:0 auto;background-color:#2c2c2e;border-radius:15px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,0.5);'>
                <div style='background-color:#8B52FA;padding:25px;color:#ffffff;'>
                  <h2 style='margin:0;font-size:20px;text-transform:uppercase;'>Espace Établissement</h2>
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
            error_log('Erreur PHPMailer SMTP (OTP gestionnaire) : ' . $mail->ErrorInfo);
            return false;
        }
    }

    public function logout(): void
    {
        if (Session::gestionnaireId()) {
            JournalGestionnaire::log('session.deconnexion', 'user', (int)Session::gestionnaireId(),
                'Déconnexion de l\'espace gestionnaire');
        }

        Session::remove('gestionnaire_id');
        Session::remove('gestionnaire_name');
        Session::remove('gestionnaire_email');
        Session::remove('gestionnaire_etab_id');
        Session::remove('gestionnaire_etab_nom');
        Session::remove('gestionnaire_role');
        Session::remove('gest_2fa_pending');
        Session::remove('gest_2fa_attempts');
        Session::regenerate();

        Session::flash('success', 'Tu es bien déconnecté.');
        Response::redirect('/gestionnaire/login');
    }

    // ─────────────────────────────────────────────────────────────────────

    private function findGestionnaireByEmail(string $email): ?array
    {
        $st = Database::getInstance()->getConnection()->prepare('
            SELECT u.id, u.nom, u.prenom, u.email, u.password_hash, u.is_active,
                   ea.etablissement_id, ea.role AS ea_role,
                   e.nom AS etab_nom
            FROM users u
            JOIN etablissement_admins ea ON ea.user_id = u.id
            JOIN etablissements e        ON e.id = ea.etablissement_id
            WHERE u.email = ? AND u.is_deleted = 0
            ORDER BY ea.date_creation DESC
            LIMIT 1
        ');
        $st->execute([$email]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function findGestionnaireById(int $id): ?array
    {
        $st = Database::getInstance()->getConnection()->prepare('
            SELECT u.id, u.nom, u.prenom, u.email, u.password_hash, u.is_active,
                   ea.etablissement_id, ea.role AS ea_role,
                   e.nom AS etab_nom
            FROM users u
            JOIN etablissement_admins ea ON ea.user_id = u.id
            JOIN etablissements e        ON e.id = ea.etablissement_id
            WHERE u.id = ? AND u.is_deleted = 0
            ORDER BY ea.date_creation DESC
            LIMIT 1
        ');
        $st->execute([$id]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
