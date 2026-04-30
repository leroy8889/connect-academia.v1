<?php
declare(strict_types=1);

namespace Middleware;

use Core\Session;
use Core\Response;
use Models\User;

class AuthMiddleware
{
    public function handle(): void
    {
        if (!Session::isLoggedIn()) {
            if ($this->isAjax()) {
                Response::json(['success' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Non authentifié']], 401);
            }
            Response::redirect('/login');
        }

        // Vérifier que le compte est toujours actif
        $user = (new User())->findById(Session::userId());
        if (!$user || !$user['is_active'] || $user['is_deleted']) {
            Session::destroy();
            if ($this->isAjax()) {
                Response::json(['success' => false, 'error' => ['code' => 'SUSPENDED', 'message' => 'Compte suspendu']], 403);
            }
            Response::redirect('/login?reason=suspended');
        }
    }

    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}

