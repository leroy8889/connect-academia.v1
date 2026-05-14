<?php
declare(strict_types=1);

namespace Middleware;

use Core\{Session, Response, Cache};
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

        // Période gratuite : 15 heures à partir de created_at
        $createdAt     = strtotime($user['created_at']);
        $periodeHeures = (int) ($_ENV['PERIODE_GRATUITE_HEURES'] ?? 15);
        $expireAt      = $createdAt + ($periodeHeures * 3600);

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
            Cache::set("abonnement:{$userId}", $abonnement, 3600);
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

    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
