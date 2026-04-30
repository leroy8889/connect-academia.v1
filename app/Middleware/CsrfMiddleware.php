<?php
declare(strict_types=1);

namespace Middleware;

use Core\{Session, Response};

class CsrfMiddleware
{
    public function handle(): void
    {
        if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        $token = $_SERVER['HTTP_X_CSRF_TOKEN']
              ?? $_POST['_csrf_token']
              ?? '';

        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            if ($this->isAjax()) {
                Response::json([
                    'success' => false,
                    'error'   => ['code' => 'CSRF_ERROR', 'message' => 'Token invalide. Rafraîchissez la page.'],
                ], 403);
            }
            Session::flash('errors', ['general' => 'Token de sécurité invalide. Rafraîchissez la page.']);
            Response::redirect($_SERVER['HTTP_REFERER'] ?? '/');
        }
    }

    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
