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
              ?? $_POST['csrf_token']
              ?? '';

        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            if ($this->isAjax()) {
                Response::json([
                    'success' => false,
                    'error'   => ['code' => 'CSRF_ERROR', 'message' => 'Token invalide. Rafraîchissez la page.'],
                ], 403);
            }
            $message = 'Session expirée : votre action n’a pas été enregistrée. Rafraîchissez la page et réessayez.';

            // Deux clés pour un seul message : les formulaires historiques
            // relisent `errors` (tableau par champ), les écrans récents
            // relisent `error` (chaîne). N'en poser qu'une renvoyait
            // l'utilisateur sur une page inchangée, sans un mot d'explication —
            // c'est exactement ce qui se passait au dépôt d'une pièce depuis
            // l'espace candidat, où l'échec était totalement silencieux.
            Session::flash('errors', ['general' => $message]);
            Session::flash('error', $message);
            Response::redirect($_SERVER['HTTP_REFERER'] ?? '/');
        }
    }

    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
