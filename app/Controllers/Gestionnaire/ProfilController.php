<?php
declare(strict_types=1);

namespace Controllers\Gestionnaire;

use Core\{Database, Response, Session};
use Services\Orientation\JournalGestionnaire;

/**
 * Profil du gestionnaire connecté : identité, e-mail de connexion, mot de passe.
 * Le changement d'e-mail et de mot de passe exigent le mot de passe actuel.
 */
class ProfilController
{
    public function index(): void
    {
        $user = $this->findUser();
        if (!$user) {
            Response::redirect('/gestionnaire/login');
            return;
        }

        Response::view('gestionnaire/profil/index', [
            'pageTitle'  => 'Mon profil',
            'user'       => $user,
            'etabNom'    => (string)Session::get('gestionnaire_etab_nom', ''),
            'role'       => (string)Session::get('gestionnaire_role', 'gestionnaire'),
            'errors'     => Session::getFlash('form_errors') ?? [],
            'flash'      => Session::getFlash('success'),
            'flashError' => Session::getFlash('error'),
        ], 'gestionnaire');
    }

    public function update(): void
    {
        $user = $this->findUser();
        if (!$user) {
            Response::redirect('/gestionnaire/login');
            return;
        }

        $errors = [];
        $prenom = trim($_POST['prenom'] ?? '');
        $nom    = trim($_POST['nom'] ?? '');
        $email  = trim($_POST['email'] ?? '');

        if ($prenom === '') $errors['prenom'] = 'Obligatoire.';
        if ($nom === '')    $errors['nom']    = 'Obligatoire.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'E-mail invalide.';

        $db = Database::getInstance()->getConnection();

        // Changement d'e-mail de connexion : mot de passe actuel exigé + unicité
        if (empty($errors['email']) && $email !== $user['email']) {
            $pwd = $_POST['password_confirm'] ?? '';
            if (!password_verify($pwd, $user['password_hash'])) {
                $errors['password_confirm'] = 'Mot de passe actuel requis pour changer l\'e-mail.';
            } else {
                $st = $db->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
                $st->execute([$email, (int)$user['id']]);
                if ($st->fetch()) {
                    $errors['email'] = 'Cet e-mail est déjà utilisé par un autre compte.';
                }
            }
        }

        if (!empty($errors)) {
            Session::flash('form_errors', $errors);
            Session::flash('error', 'Le profil n\'a pas été modifié — vérifiez le formulaire.');
            Response::redirect('/gestionnaire/profil');
            return;
        }

        $db->prepare('UPDATE users SET prenom = ?, nom = ?, email = ?, updated_at = NOW() WHERE id = ?')
           ->execute([$prenom, $nom, $email, (int)$user['id']]);

        Session::set('gestionnaire_name',  trim($prenom . ' ' . $nom));
        Session::set('gestionnaire_email', $email);

        JournalGestionnaire::log('profil.modification', 'user', (int)$user['id'],
            'Profil mis à jour' . ($email !== $user['email'] ? ' (e-mail de connexion modifié)' : ''),
            ['email_change' => $email !== $user['email']]);

        Session::flash('success', 'Profil mis à jour.');
        Response::redirect('/gestionnaire/profil');
    }

    public function updatePassword(): void
    {
        $user = $this->findUser();
        if (!$user) {
            Response::redirect('/gestionnaire/login');
            return;
        }

        $actuel   = $_POST['password_actuel']       ?? '';
        $nouveau  = $_POST['password_nouveau']      ?? '';
        $confirme = $_POST['password_confirmation'] ?? '';

        $errors = [];
        if (!password_verify($actuel, $user['password_hash'])) {
            $errors['password_actuel'] = 'Mot de passe actuel incorrect.';
        }
        if (strlen($nouveau) < 10) {
            $errors['password_nouveau'] = 'Au moins 10 caractères.';
        }
        if ($nouveau !== $confirme) {
            $errors['password_confirmation'] = 'La confirmation ne correspond pas.';
        }

        if (!empty($errors)) {
            Session::flash('form_errors', $errors);
            Session::flash('error', 'Le mot de passe n\'a pas été modifié.');
            Response::redirect('/gestionnaire/profil');
            return;
        }

        Database::getInstance()->getConnection()
            ->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?')
            ->execute([password_hash($nouveau, PASSWORD_BCRYPT), (int)$user['id']]);

        Session::regenerate();

        JournalGestionnaire::log('profil.motdepasse', 'user', (int)$user['id'],
            'Mot de passe du compte gestionnaire modifié');

        Session::flash('success', 'Mot de passe modifié.');
        Response::redirect('/gestionnaire/profil');
    }

    // ─────────────────────────────────────────────────────────────────────

    private function findUser(): ?array
    {
        $id = (int)Session::gestionnaireId();
        if ($id <= 0) {
            return null;
        }
        $st = Database::getInstance()->getConnection()->prepare('
            SELECT id, prenom, nom, email, password_hash, last_login, created_at
            FROM users
            WHERE id = ? AND is_deleted = 0
            LIMIT 1
        ');
        $st->execute([$id]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
