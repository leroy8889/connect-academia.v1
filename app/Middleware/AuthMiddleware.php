<?php
declare(strict_types=1);

namespace Middleware;

use Core\{Session, Response};
use Models\User;

class AuthMiddleware
{
    public function handle(): void
    {
        if (!Session::isLoggedIn()) {
            if ($this->isAjax()) {
                Response::json(['success' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Non authentifié']], 401);
            }
            Response::redirect('/auth/connexion');
        }

        $userModel = new User();
        $user = $userModel->findById(Session::userId());
        if (!$user || !$user['is_active'] || $user['is_deleted']) {
            Session::destroy();
            if ($this->isAjax()) {
                Response::json(['success' => false, 'error' => ['code' => 'SUSPENDED', 'message' => 'Compte suspendu']], 403);
            }
            Response::redirect('/auth/connexion?reason=suspended');
        }

        // Cloison des espaces : une session « élève » ouverte avec un compte
        // d'établissement (avant le verrou posé sur /auth/connexion, ou par un
        // autre chemin) est fermée ici.
        // On ne détruit que les clés utilisateur : une éventuelle session
        // gestionnaire ou admin ouverte en parallèle reste intacte.
        // Sortie identique à une session expirée : ni message ni redirection
        // ne révèlent l'existence d'un autre espace ou son adresse.
        if ($userModel->isEtablissementAccount((int) Session::userId())) {
            $this->clearUserSession();
            if ($this->isAjax()) {
                Response::json(['success' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Non authentifié']], 401);
            }
            Response::redirect('/auth/connexion');
        }

        $userModel->updateLastActivity((int) Session::userId());
    }

    /**
     * Ferme la session utilisateur sans toucher aux sessions gestionnaire ou
     * admin ouvertes en parallèle. La liste des clés vit dans Core\Session :
     * une seconde copie ici finirait par diverger et laisser une clé derrière.
     */
    private function clearUserSession(): void
    {
        Session::clearUtilisateur();
    }

    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
