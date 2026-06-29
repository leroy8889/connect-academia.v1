<?php
declare(strict_types=1);

namespace Core;

class AdminPermissions
{
    // Préfixes de routes autorisés pour le rôle admin
    private const ADMIN_PREFIXES = [
        '/admin/utilisateurs',
        '/admin/api/utilisateurs',
        '/admin/series-matieres',
        '/admin/api/series',
        '/admin/contenu',
        '/admin/api/contenu',
        '/admin/api/matieres',
        '/admin/communaute',
        '/admin/api/communaute',
        '/admin/signalements',
        '/admin/api/signalements',
        '/admin/annonces',
        '/admin/api/annonces',
        '/admin/contact',
        '/admin/api/contact',
        '/admin/paiement',
        '/admin/api/paiement',
        '/admin/analytics',
        '/admin/notifications',
        '/admin/api/notifications',
        '/admin/api/stats',
        '/admin/logout',
    ];

    // Préfixes de routes autorisés pour le rôle modérateur
    private const MODERATEUR_PREFIXES = [
        '/admin/series-matieres',
        '/admin/api/series',
        '/admin/contenu',
        '/admin/api/contenu',
        '/admin/api/matieres',
        '/admin/communaute',
        '/admin/api/communaute',
        '/admin/signalements',
        '/admin/api/signalements',
        '/admin/annonces',
        '/admin/api/annonces',
        '/admin/contact',
        '/admin/api/contact',
        '/admin/logout',
    ];

    // Préfixes de routes autorisés pour le rôle Modérateur 1
    // Accès lecture seule sur utilisateurs (écriture bloquée dans UsersController)
    private const MODERATEUR_1_PREFIXES = [
        '/admin/utilisateurs',
        '/admin/api/utilisateurs',
        '/admin/series-matieres',
        '/admin/api/series',
        '/admin/contenu',
        '/admin/api/contenu',
        '/admin/api/matieres',
        '/admin/communaute',
        '/admin/api/communaute',
        '/admin/analytics',
        '/admin/api/stats',
        '/admin/logout',
    ];

    /**
     * Vérifie si un rôle peut accéder à un chemin donné (sans BASE_URL).
     */
    public static function canAccess(string $role, string $path): bool
    {
        if ($role === 'super_admin') {
            return true;
        }

        $prefixes = match ($role) {
            'admin'        => self::ADMIN_PREFIXES,
            'moderateur'   => self::MODERATEUR_PREFIXES,
            'moderateur_1' => self::MODERATEUR_1_PREFIXES,
            default        => [],
        };

        // Dashboard exact match pour admin et moderateur_1
        if (in_array($role, ['admin', 'moderateur_1'], true) && ($path === '/admin' || $path === '/admin/')) {
            return true;
        }

        foreach ($prefixes as $prefix) {
            if ($path === $prefix
                || str_starts_with($path, $prefix . '/')
                || str_starts_with($path, $prefix . '?')
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Page d'accueil par défaut selon le rôle (chemin sans BASE_URL).
     */
    public static function getDefaultPath(string $role): string
    {
        return match ($role) {
            'moderateur' => '/admin/series-matieres',
            default      => '/admin',
        };
    }

    /**
     * Vérifie si un rôle a uniquement le droit de lecture sur les utilisateurs.
     */
    public static function isUsersReadOnly(string $role): bool
    {
        return $role === 'moderateur_1';
    }
}
