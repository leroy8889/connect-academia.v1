<?php
declare(strict_types=1);

namespace Core;

/**
 * Mode maintenance global.
 *
 * Quand `settings.maintenance_mode` vaut '1', toute la plateforme publique
 * (élèves, communauté, orientation, gestionnaires d'établissement…) est
 * remplacée par la page de maintenance.
 *
 * Reste TOUJOURS accessible : le back-office (/admin/*) et la connexion admin
 * (/Administratorconnect/*), afin qu'un super-admin puisse se connecter et
 * désactiver le mode. Le reste de la plateforme (y compris pour un super-admin
 * connecté qui navigue sur le site public) affiche la page de maintenance —
 * c'est voulu : ainsi l'activation est vérifiable en direct.
 */
class Maintenance
{
    /** Préfixes d'URI jamais bloqués (back-office). */
    private const EXEMPT_PREFIXES = ['/admin', '/Administratorconnect'];

    public static function isEnabled(): bool
    {
        return (string) Settings::get('maintenance_mode', '0') === '1';
    }

    /**
     * À appeler une fois, avant le dispatch du routeur.
     * Rend la page de maintenance et termine la requête si nécessaire.
     */
    public static function guard(): void
    {
        if (!self::isEnabled() || self::isExempt()) {
            return;
        }
        self::render();
    }

    private static function isExempt(): bool
    {
        // Seul le back-office reste joignable (pour désactiver le mode).
        // Aucune exemption « personne » : même un super-admin voit la page
        // de maintenance sur le site public, ce qui rend l'activation vérifiable.
        $uri = self::currentUri();
        foreach (self::EXEMPT_PREFIXES as $prefix) {
            if ($uri === $prefix || str_starts_with($uri, $prefix . '/')) {
                return true;
            }
        }
        return false;
    }

    /** URI nettoyée, identique à Router::dispatch(). */
    private static function currentUri(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $uri = urldecode(preg_replace('#/+#', '/', (string) $uri));
        $uri = preg_replace('#/index\.php(/|$)#', '/', $uri);
        if (defined('BASE_URL') && BASE_URL !== '' && str_starts_with($uri, BASE_URL)) {
            $uri = substr($uri, strlen(BASE_URL));
        }
        return rtrim($uri, '/') ?: '/';
    }

    private static function render(): void
    {
        if (!headers_sent()) {
            http_response_code(503);
            header('Retry-After: 3600');
        }

        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error'   => ['code' => 'MAINTENANCE', 'message' => 'Plateforme en maintenance.'],
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Message personnalisable depuis le back-office (facultatif).
        $maintenanceMessage = trim((string) Settings::get('maintenance_message', ''));
        require BASE_PATH . '/app/Views/errors/maintenance.php';
        exit;
    }
}
