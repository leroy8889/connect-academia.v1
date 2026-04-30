<?php
declare(strict_types=1);

namespace Middleware;

use Core\Response;

class CsrfMiddleware
{
    public function handle(): void
    {
        if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN']
                ?? $_POST['_csrf_token']
                ?? '';

            if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
                if ($this->isAjax()) {
                    Response::json([
                        'success' => false,
                        'error' => [
                            'code'    => 'CSRF_ERROR',
                            'message' => 'Token CSRF invalide. Veuillez rafraîchir la page.',
                        ],
                    ], 403);
                }

                \Core\Session::flash('errors', ['general' => 'Token de sécurité invalide. Veuillez rafraîchir la page et réessayer.']);
                $referer = $_SERVER['HTTP_REFERER'] ?? '/';
                Response::redirect($referer);
            }
        }
    }

    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
