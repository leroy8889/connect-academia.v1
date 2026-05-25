<?php
declare(strict_types=1);

namespace Middleware;

use Core\{Session, Response, Redis, AdminPermissions};

class AdminMiddleware
{
    // Clé Redis : 'admin:activity:{session_id}'
    private const ACTIVITY_NS = 'admin:activity:';

    public function handle(): void
    {
        if (!Session::isAdmin()) {
            if ($this->isAjax()) {
                Response::json(['success' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Accès réservé aux administrateurs']], 401);
            }
            Response::redirect('/Administratorconnect/login');
        }

        $this->checkInactivity();
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

    /**
     * Détecte l'inactivité admin via Redis.
     * La clé Redis expire au bout de ADMIN_INACTIVITY_TIMEOUT secondes.
     * Si la clé est absente alors que l'admin est connecté → timeout.
     */
    private function checkInactivity(): void
    {
        $redis = Redis::getInstance();
        if (!$redis->isAvailable()) {
            // Redis indisponible : on ne bloque pas l'admin
            return;
        }

        $timeout    = (int)($_ENV['ADMIN_INACTIVITY_TIMEOUT'] ?? 1800);
        $activityKey = self::ACTIVITY_NS . session_id();

        if (!$redis->exists($activityKey)) {
            // Clé expirée = inactivité détectée
            Session::destroy();

            if ($this->isAjax()) {
                Response::json([
                    'success' => false,
                    'error'   => ['code' => 'SESSION_TIMEOUT', 'message' => 'Session expirée par inactivité'],
                ], 401);
            }
            Response::redirect('/Administratorconnect/login?reason=timeout');
        }

        // Rafraîchir le TTL à chaque requête active
        $redis->expire($activityKey, $timeout);
    }

    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
