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

        // Redirect vers la page demandée (paiement confirmé, etc.)
        $allowed = ['/abonnement/confirmation', '/abonnement/choisir', '/hub'];
        $dest    = '/hub';
        if ($redirectAfter) {
            foreach ($allowed as $prefix) {
                if (str_starts_with($redirectAfter, $prefix)) {
                    $dest = $redirectAfter;
                    break;
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
            Response::redirect('/auth/mot-de-passe-oublie');
        }

        $userModel = new User();
        $user = $userModel->findByEmail($email);

        if ($user) {
            $token = bin2hex(random_bytes(32));
            try {
                $db = \Core\Database::getInstance()->getConnection();
                $stmt = $db->prepare("DELETE FROM password_resets WHERE email = ?");
                $stmt->execute([$email]);

                $stmt = $db->prepare("INSERT INTO password_resets (email, token, created_at) VALUES (?, ?, ?)");
                $stmt->execute([$email, $token, date('Y-m-d H:i:s')]);

                // Appel de l'envoi du mail
                $this->sendResetEmail($email, $token);

            } catch (\Exception $e) {
                error_log("Erreur BDD forgot: " . $e->getMessage());
                Session::flash('errors', ['general' => 'Erreur technique de base de données.']);
                Response::redirect('/auth/mot-de-passe-oublie');
            }
        }

        // Utilise la clé 'success' attendue par ta vue
        Session::flash('success', 'Si cette adresse est enregistrée, vous recevrez un email sous peu.');
        Response::redirect('/auth/mot-de-passe-oublie');
    }

    private function sendResetEmail(string $email, string $token): void
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

            // Options pour éviter les blocages SSL en local
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            $mail->setFrom('security@connectacademia.com', "Connect'Academia");
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = "Réinitialisation de votre mot de passe";

            $link = "http://localhost:8000/auth/reinitialiser/" . $token;

            $mail->Body = "
                <div style='background: #1c1c1e; color: #ffffff; padding: 40px; font-family: sans-serif; border-radius: 20px;'>
                    <h2 style='color: #8C52FF;'>Bonjour,</h2>
                    <p>Vous avez demandé la réinitialisation de votre mot de passe Connect'Academia.</p>
                    <p>Cliquez sur le bouton ci-dessous pour continuer :</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='$link' style='display: inline-block; padding: 15px 25px; background: #8C52FF; color: white; text-decoration: none; border-radius: 10px; font-weight: bold;'>Réinitialiser mon mot de passe</a>
                    </div>
                    <p style='font-size: 12px; color: #94a3b8;'>Si vous n'êtes pas à l'origine de cette demande, ignorez cet email. Ce lien expirera dans 1 heure.</p>
                </div>";

            $mail->send();
        } catch (Exception $e) {
            error_log("Erreur PHPMailer : " . $mail->ErrorInfo);
            // Si le mail échoue, on flash l'erreur technique pour le debug
            Session::flash('errors', ['general' => "Le mail n'a pas pu être envoyé : " . $mail->ErrorInfo]);
        }
    }

    public function showReset(string $token): void
    {
        $db = \Core\Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM password_resets WHERE token = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $stmt->execute([$token]);
        $reset = $stmt->fetch();

        if (!$reset) {
            Session::flash('errors', ['general' => 'Le lien est invalide ou a expiré.']);
            Response::redirect('/auth/mot-de-passe-oublie');
        }

        Response::view('auth/reinitialiser', [
            'pageTitle' => "Nouveau mot de passe — Connect'Academia",
            'token'     => htmlspecialchars($token),
            'errors'    => Session::getFlash('errors', []),
        ], 'auth');
    }

    public function reset(): void
    {
        $token    = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['password_confirmation'] ?? '';

        if (empty($token) || strlen($password) < 8 || $password !== $confirm) {
            Session::flash('errors', ['general' => 'Informations invalides (min 8 caractères).']);
            Response::redirect('/auth/reinitialiser/' . $token);
        }

        try {
            $db = \Core\Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT email FROM password_resets WHERE token = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            $stmt->execute([$token]);
            $reset = $stmt->fetch();

            if ($reset) {
                $userModel = new User();
                $hashedPassword = password_hash($password, PASSWORD_ARGON2ID);
                
                $update = $db->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
                $update->execute([$hashedPassword, $reset['email']]);

                $delete = $db->prepare("DELETE FROM password_resets WHERE email = ?");
                $delete->execute([$reset['email']]);

                Session::flash('success', 'Votre mot de passe a été mis à jour.');
                Response::redirect('/auth/connexion');
            }
        } catch (\Exception $e) {
            error_log("Reset error: " . $e->getMessage());
        }

        Session::flash('errors', ['general' => 'Une erreur est survenue.']);
        Response::redirect('/auth/mot-de-passe-oublie');
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