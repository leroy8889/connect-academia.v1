<?php
declare(strict_types=1);

namespace Middleware;

use Core\{Session, Response, AdminPermissions};

class AdminMiddleware
{
    public function handle(): void
    {
        if (!Session::isAdmin()) {
            if ($this->isAjax()) {
                Response::json(['success' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Accès réservé aux administrateurs']], 401);
            }
            Response::redirect('/Administratorconnect/login');
        }

        // Pas de timeout d'inactivité : l'admin reste connecté tant que sa
        // session PHP est vivante (déconnexion uniquement via le bouton logout).
        $this->checkRoleAccess();
    }

    private function checkRoleAccess(): void
    {
        $role = Session::adminRole() ?? 'admin';

        // super_admin : accès total sans vérification
        if ($role === 'super_admin') {
            return;
        }

        // Reconstruire l'URI nettoyée (identique à Router::dispatch)
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $uri = urldecode(preg_replace('#/+#', '/', (string)$uri));
        $uri = preg_replace('#/index\.php(/|$)#', '/', $uri);
        if (defined('BASE_URL') && BASE_URL !== '' && str_starts_with($uri, BASE_URL)) {
            $uri = substr($uri, strlen(BASE_URL));
        }
        $uri = rtrim($uri, '/') ?: '/';

        if (!AdminPermissions::canAccess($role, $uri)) {
            if ($this->isAjax()) {
                Response::json([
                    'success' => false,
                    'error'   => ['code' => 'FORBIDDEN', 'message' => 'Accès non autorisé pour votre rôle'],
                ], 403);
            }
            Response::redirect(AdminPermissions::getDefaultPath($role));
        }
    }

    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
