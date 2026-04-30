<?php
declare(strict_types=1);

namespace Middleware;

use Core\Session;
use Core\Response;
use Models\User;

class AdminMiddleware
{
    public function handle(): void
    {
        // Vérifier l'authentification (sans rediriger vers /login classique)
        if (!Session::isLoggedIn()) {
            if ($this->isAjax()) {
                Response::json(['success' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Non authentifié']], 401);
            }
            Response::redirect('/admin/login');
        }

        // Vérifier que le compte est toujours actif
        $user = (new User())->findById(Session::userId());
        if (!$user || !$user['is_active'] || $user['is_deleted']) {
            Session::destroy();
            if ($this->isAjax()) {
                Response::json(['success' => false, 'error' => ['code' => 'SUSPENDED', 'message' => 'Compte suspendu']], 403);
            }
            Response::redirect('/admin/login?reason=suspended');
        }

        // Vérifier le rôle admin
        if (Session::userRole() !== 'admin') {
            if ($this->isAjax()) {
                Response::json(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Accès réservé aux administrateurs']], 403);
            }
            Response::redirect('/admin/login');
        }
    }

    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
