<?php
declare(strict_types=1);

namespace Controllers;

use Core\{Response, Session, Validator, RateLimiter};
use Models\User;
// Import de PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class AuthController
{
    public function showConnexion(): void
    {
        if (Session::isLoggedIn()) {
            Response::redirect('/hub');
        }

        $redirect  = $_GET['redirect']    ?? '';
        $paymentOk = isset($_GET['payment_ok']);

        Response::view('auth/connexion', [
            'pageTitle'  => "Connexion — Connect'Academia",
            'errors'     => Session::getFlash('errors', []),
            'old'        => Session::getFlash('old', []),
            'success'    => $paymentOk
                ? '✅ Paiement confirmé ! Connectez-vous pour accéder à votre abonnement.'
                : Session::getFlash('success'),
            'redirectAfter' => $redirect,
        ], 'auth');
    }

    public function connexion(): void
    {
        $email        = trim($_POST['email'] ?? '');
        $password     = $_POST['password']  ?? '';
        $redirectAfter = $_POST['redirect_after'] ?? '';

        if (!$email || !$password) {
            Session::flash('errors', ['general' => 'Veuillez remplir tous les champs.']);
            Session::flash('old', ['email' => $email]);
            Response::redirect('/auth/connexion' . ($redirectAfter ? '?redirect=' . urlencode($redirectAfter) : ''));
        }

        $rlKey = RateLimiter::ipKey('user_login');
        if (RateLimiter::tooManyAttempts($rlKey, 10)) {
            $waitMin = max(1, (int) ceil(RateLimiter::availableIn($rlKey) / 60));
            Session::flash('errors', ['general' => "Trop de tentatives. Réessayez dans {$waitMin} minute(s)."]);
            Response::redirect('/auth/connexion');
        }

        $userModel = new User();
        $user      = $userModel->findByEmail($email);

        if (!$user || !$userModel->verifyPassword($password, $user['password_hash'])) {
            RateLimiter::hit($rlKey, 900);
            Session::flash('errors', ['general' => 'Email ou mot de passe incorrect.']);
            Session::flash('old', ['email' => $email]);
            Response::redirect('/auth/connexion' . ($redirectAfter ? '?redirect=' . urlencode($redirectAfter) : ''));
        }

        if (!$user['is_active'] || $user['is_deleted']) {
            Session::flash('errors', ['general' => 'Votre compte a été suspendu. Contactez un administrateur.']);
            Response::redirect('/auth/connexion');
        }

        RateLimiter::clear($rlKey);
        Session::regenerate();
        $this->setUserSession($user);
        $userModel->updateLastLogin((int) $user['id']);

        // Redirect vers la page demandée — whitelist stricte + protection open redirect
        $allowed = ['/abonnement/confirmation', '/abonnement/choisir', '/hub'];
        $dest    = '/hub';
        if ($redirectAfter) {
            // Refuser toute URL externe ou protocol-relative
            if (str_starts_with($redirectAfter, '/') && !str_starts_with($redirectAfter, '//')) {
                foreach ($allowed as $prefix) {
                    if ($redirectAfter === $prefix || str_starts_with($redirectAfter, $prefix . '/') || str_starts_with($redirectAfter, $prefix . '?')) {
                        $dest = $redirectAfter;
                        break;
                    }
                }
            }
        }

        Response::redirect($dest);
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
            $userModel->create($data);
        } catch (\Exception $e) {
            error_log('Inscription error: ' . $e->getMessage());
            Session::flash('errors', ['general' => 'Erreur lors de la création du compte.']);
            Response::redirect('/auth/inscription');
        }

        Session::flash('success', 'Compte créé avec succès !');
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
            'old'       => Session::getFlash('old', []),
        ], 'auth');
    }

    public function forgot(): void
    {
        $email = trim(strtolower($_POST['email'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('errors', ['email' => 'Adresse email invalide.']);
            Session::flash('old', ['email' => $email]);
            Response::redirect('/auth/mot-de-passe-oublie');
        }

        // Rate limiting — par IP : 5 req / 15 min
        $rlIp = RateLimiter::ipKey('forgot');
        if (RateLimiter::tooManyAttempts($rlIp, 5)) {
            $wait = max(1, (int) ceil(RateLimiter::availableIn($rlIp) / 60));
            Session::flash('errors', ['general' => "Trop de tentatives. Réessayez dans {$wait} minute(s)."]);
            Response::redirect('/auth/mot-de-passe-oublie');
        }

        // Rate limiting — par email : 3 req / 15 min (réponse identique = anti-énumération)
        $rlEmail = RateLimiter::emailKey('forgot', $email);
        if (RateLimiter::tooManyAttempts($rlEmail, 3)) {
            Session::flash('success', 'Si cette adresse est enregistrée, vous recevrez un email sous peu.');
            Response::redirect('/auth/mot-de-passe-oublie');
        }

        RateLimiter::hit($rlIp,    900); // fenêtre 15 min
        RateLimiter::hit($rlEmail, 900);

        $userModel = new User();
        $user      = $userModel->findByEmail($email);

        if ($user) {
            // Token cryptographique 256 bits — stocké hashé (SHA-256) en base
            $token     = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);

            try {
                $db = \Core\Database::getInstance()->getConnection();

                // Supprime tout token précédent (un seul reset actif par compte)
                $db->prepare("DELETE FROM password_resets WHERE email = ?")
                   ->execute([$email]);

                $db->prepare("INSERT INTO password_resets (email, token_hash, created_at) VALUES (?, ?, NOW())")
                   ->execute([$email, $tokenHash]);

                $this->sendResetEmail($email, $token, $user['prenom'] ?? '');

            } catch (\Exception $e) {
                error_log("Forgot password error: " . $e->getMessage());
                // Erreur silencieuse — réponse identique (anti-énumération)
            }
        }

        // Toujours la même réponse, qu'il y ait un compte ou non
        Session::flash('success', 'Si cette adresse est enregistrée, vous recevrez un email sous peu.');
        Response::redirect('/auth/mot-de-passe-oublie');
    }

    private function sendResetEmail(string $email, string $token, string $prenom = ''): void
    {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host     = $_ENV['MAIL_HOST']     ?? 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['MAIL_USERNAME']  ?? '';
            $mail->Password = $_ENV['MAIL_PASSWORD']  ?? '';
            $mail->Port     = (int) ($_ENV['MAIL_PORT'] ?? 465);
            $mail->CharSet  = 'UTF-8';

            $enc = strtolower($_ENV['MAIL_ENCRYPTION'] ?? 'smtps');
            $mail->SMTPSecure = ($enc === 'tls')
                ? PHPMailer::ENCRYPTION_STARTTLS
                : PHPMailer::ENCRYPTION_SMTPS;

            // SSL strict en production — désactivé uniquement en local/dev
            if (($_ENV['APP_ENV'] ?? 'production') !== 'production') {
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer'       => false,
                        'verify_peer_name'  => false,
                        'allow_self_signed' => true,
                    ],
                ];
            }

            $mail->setFrom(
                $_ENV['MAIL_FROM_ADDRESS'] ?? 'security@connectacademia.com',
                $_ENV['MAIL_FROM_NAME']    ?? "Connect'Academia"
            );
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = "Réinitialise ton mot de passe — Connect'Academia";

            // URL robuste : APP_URL en prod, reconstruction dynamique en dev
            $appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
            if (!$appUrl) {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $appUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(BASE_URL, '/');
            }
            $link = $appUrl . '/auth/reinitialiser/' . $token;
            $name = htmlspecialchars($prenom ?: 'Étudiant', ENT_QUOTES | ENT_HTML5, 'UTF-8');

            $mail->Body    = $this->buildResetEmailHtml($name, $link);
            $mail->AltBody = "Bonjour {$name},\n\nRéinitialise ton mot de passe ici : {$link}\n\nCe lien expire dans 1 heure. Usage unique.\n\nSi tu n'es pas à l'origine de cette demande, ignore cet email.";

            $mail->send();
        } catch (Exception $e) {
            error_log("PHPMailer reset error: " . ($mail->ErrorInfo ?: $e->getMessage()));
            throw new \RuntimeException("Envoi email échoué : " . ($mail->ErrorInfo ?: $e->getMessage()));
        }
    }

    private function buildResetEmailHtml(string $name, string $link): string
    {
        $safeLink = htmlspecialchars($link, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $year     = date('Y');
        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Réinitialisation de mot de passe</title>
</head>
<body style="margin:0;padding:0;background:#0A0A0F;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#0A0A0F;padding:40px 16px;">
  <tr><td align="center">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width:560px;">

      <!-- En-tête logo -->
      <tr><td align="center" style="padding-bottom:28px;">
        <table cellpadding="0" cellspacing="0" role="presentation">
          <tr><td style="background:linear-gradient(135deg,#8B52FA,#7440D9);width:48px;height:48px;border-radius:14px;text-align:center;vertical-align:middle;">
            <span style="color:#fff;font-size:22px;line-height:48px;">&#128274;</span>
          </td></tr>
        </table>
        <p style="margin:14px 0 0;color:#8B52FA;font-size:11px;font-weight:700;letter-spacing:2.5px;text-transform:uppercase;">CONNECT'ACADEMIA</p>
      </td></tr>

      <!-- Carte principale -->
      <tr><td style="background:#16161E;border-radius:20px;padding:40px 36px;border:1px solid rgba(255,255,255,0.07);">

        <p style="margin:0 0 6px;color:#FFFFFF;font-size:22px;font-weight:700;line-height:1.3;">Réinitialise ton mot de passe</p>
        <p style="margin:0 0 28px;color:#9E9E9E;font-size:14px;line-height:1.65;">
          Bonjour <strong style="color:#FFFFFF;">{$name}</strong>,<br>
          nous avons reçu une demande de réinitialisation de mot de passe pour ton compte Connect'Academia.
          Clique sur le bouton ci-dessous pour en choisir un nouveau.
        </p>

        <!-- Bouton CTA -->
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
          <tr><td align="center" style="padding:4px 0 32px;">
            <a href="{$safeLink}"
               style="display:inline-block;background:#8B52FA;color:#FFFFFF;text-decoration:none;padding:15px 38px;border-radius:50px;font-size:15px;font-weight:600;letter-spacing:0.2px;">
              Réinitialiser mon mot de passe
            </a>
          </td></tr>
        </table>

        <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
          <tr><td style="border-top:1px solid rgba(255,255,255,0.07);padding-top:28px;">

            <!-- Expiration -->
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
              <tr><td style="background:rgba(139,82,250,0.09);border:1px solid rgba(139,82,250,0.20);border-radius:12px;padding:13px 16px;">
                <p style="margin:0;color:#A87EFB;font-size:13px;line-height:1.5;">
                  &#9200;&nbsp; Ce lien est valide pendant <strong>1 heure</strong> uniquement et ne peut être utilisé qu'<strong>une seule fois</strong>.
                </p>
              </td></tr>
            </table>

            <!-- URL secours -->
            <p style="margin:22px 0 4px;color:#6B7280;font-size:12px;">Si le bouton ne s'affiche pas, copie ce lien dans ton navigateur :</p>
            <p style="margin:0;word-break:break-all;font-size:11px;color:#4A4A6A;">{$safeLink}</p>

            <!-- Avis sécurité -->
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-top:24px;">
              <tr><td style="background:rgba(255,82,82,0.06);border-left:3px solid rgba(255,82,82,0.35);border-radius:0 8px 8px 0;padding:12px 14px;">
                <p style="margin:0;color:#9E9E9E;font-size:12px;line-height:1.6;">
                  &#128737;&nbsp; Si tu n'es pas à l'origine de cette demande, ignore cet email. Ton mot de passe restera inchangé.
                </p>
              </td></tr>
            </table>

          </td></tr>
        </table>

      </td></tr>

      <!-- Pied de page -->
      <tr><td align="center" style="padding-top:28px;">
        <p style="margin:0;color:#3A3A4A;font-size:12px;">© {$year} Connect'Academia — Gabon</p>
        <p style="margin:5px 0 0;color:#3A3A4A;font-size:11px;">Ce message est automatique, merci de ne pas y répondre.</p>
      </td></tr>

    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
    }

    public function showReset(string $token): void
    {
        // Validation format avant toute requête DB
        if (empty($token) || strlen($token) !== 64 || !ctype_xdigit($token)) {
            Session::flash('errors', ['general' => 'Lien de réinitialisation invalide.']);
            Response::redirect('/auth/mot-de-passe-oublie');
        }

        $tokenHash = hash('sha256', $token);

        $db   = \Core\Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            "SELECT email FROM password_resets
             WHERE token_hash = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        $stmt->execute([$tokenHash]);
        $reset = $stmt->fetch();

        if (!$reset) {
            Session::flash('errors', ['general' => 'Ce lien est expiré ou invalide. Faites une nouvelle demande.']);
            Response::redirect('/auth/mot-de-passe-oublie');
        }

        Response::view('auth/reinitialiser', [
            'pageTitle' => "Nouveau mot de passe — Connect'Academia",
            'token'     => $token,
            'errors'    => Session::getFlash('errors', []),
        ], 'auth');
    }

    public function reset(): void
    {
        $token    = $_POST['token']                 ?? '';
        $password = $_POST['password']              ?? '';
        $confirm  = $_POST['password_confirmation'] ?? '';

        // Validation format token
        if (empty($token) || strlen($token) !== 64 || !ctype_xdigit($token)) {
            Session::flash('errors', ['general' => 'Token invalide.']);
            Response::redirect('/auth/mot-de-passe-oublie');
        }

        // Validation mot de passe
        if (strlen($password) < 8) {
            Session::flash('errors', ['password' => 'Le mot de passe doit contenir au moins 8 caractères.']);
            Response::redirect('/auth/reinitialiser/' . $token);
        }

        if ($password !== $confirm) {
            Session::flash('errors', ['password_confirmation' => 'Les mots de passe ne correspondent pas.']);
            Response::redirect('/auth/reinitialiser/' . $token);
        }

        $tokenHash = hash('sha256', $token);

        try {
            $db   = \Core\Database::getInstance()->getConnection();
            $stmt = $db->prepare(
                "SELECT email FROM password_resets
                 WHERE token_hash = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
            );
            $stmt->execute([$tokenHash]);
            $reset = $stmt->fetch();

            if (!$reset) {
                Session::flash('errors', ['general' => 'Ce lien est expiré ou invalide. Faites une nouvelle demande.']);
                Response::redirect('/auth/mot-de-passe-oublie');
            }

            $email          = $reset['email'];
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

            // Met à jour le mot de passe
            $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE email = ? AND is_deleted = 0")
               ->execute([$hashedPassword, $email]);

            // Supprime le token immédiatement (usage unique garanti)
            $db->prepare("DELETE FROM password_resets WHERE email = ?")
               ->execute([$email]);

            // Libère le rate limiter email
            RateLimiter::clear(RateLimiter::emailKey('forgot', $email));

        } catch (\Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
            Session::flash('errors', ['general' => 'Une erreur technique est survenue. Veuillez réessayer.']);
            Response::redirect('/auth/mot-de-passe-oublie');
        }

        Session::flash('success', 'Mot de passe mis à jour. Tu peux te connecter.');
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