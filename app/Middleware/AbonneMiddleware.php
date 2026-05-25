<?php
declare(strict_types=1);

namespace Middleware;

use Core\{Session, Response, Cache, Settings};
use Models\User;

class AbonneMiddleware
{
    public function handle(): void
    {
        $userId = Session::userId();
        if (!$userId) {
            Response::redirect('/auth/connexion');
        }

        $user = (new User())->findById($userId);
        if (!$user) {
            Response::redirect('/auth/connexion');
        }

        // Période gratuite : source primaire = DB (periode_gratuite_jours en jours), fallback .env
        $createdAt = strtotime($user['created_at']);
        $expireAt  = $createdAt + Settings::getPeriodeGratuiteSecondes();

        if (time() <= $expireAt) {
            return; // Encore dans la période gratuite
        }

        // Vérifier cache Redis avant la BDD
        $cached = Cache::get("abonnement:{$userId}");
        if ($cached !== null) {
            return; // Abonnement actif en cache
        }

        // Vérifier abonnement actif en BDD
        $abonnement = (new \Models\Abonnement())->getActif($userId);
        if ($abonnement) {
            // TTL = secondes restantes jusqu'à fin d'abonnement (max 1h)
            // Garantit que le cache expire au même moment que l'abonnement
            $remaining = max(60, strtotime($abonnement['fin']) - time());
            Cache::set("abonnement:{$userId}", $abonnement, min(3600, $remaining));
            return;
        }

        // Vérifier si abonnement expiré (pour rediriger vers renouveler)
        $expired = (new \Models\Abonnement())->getExpire($userId);
        if ($expired) {
            if ($this->isAjax()) {
                Response::json(['success' => false, 'error' => ['code' => 'SUBSCRIPTION_EXPIRED', 'message' => 'Abonnement expiré']], 402);
            }
            Response::redirect('/abonnement/renouveler');
        }

        if ($this->isAjax()) {
            Response::json(['success' => false, 'error' => ['code' => 'SUBSCRIPTION_REQUIRED', 'message' => 'Abonnement requis']], 402);
        }
        Response::redirect('/abonnement/choisir');
    }

    /**
     * Vérifie si un utilisateur a accès aux fonctionnalités premium.
     * Utilisé par le layout pour conditionner la navbar.
     */
    public static function hasAccess(?int $userId): bool
    {
        if (!$userId) {
            return false;
        }

        $user = (new User())->findById($userId);
        if (!$user) {
            return false;
        }

        $expireAt = strtotime($user['created_at']) + Settings::getPeriodeGratuiteSecondes();

        if (time() <= $expireAt) {
            return true;
        }

        $cached = Cache::get("abonnement:{$userId}");
        if ($cached !== null) {
            return true;
        }

        $abonnement = (new \Models\Abonnement())->getActif($userId);
        if ($abonnement) {
            $remaining = max(60, strtotime($abonnement['fin']) - time());
            Cache::set("abonnement:{$userId}", $abonnement, min(3600, $remaining));
            return true;
        }

        return false;
    }

    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
