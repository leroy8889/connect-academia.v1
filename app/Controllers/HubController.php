<?php
declare(strict_types=1);

namespace Controllers;

use Core\{Response, Session};
use Models\{User, Abonnement};

class HubController
{
    public function root(): void
    {
        if (Session::isLoggedIn()) {
            Response::redirect('/hub');
        }
        Response::redirect('/auth/connexion');
    }

    public function index(): void
    {
        $userId    = Session::userId();
        $userModel = new User();
        $user      = $userModel->findById($userId);

        if (!$user) {
            Session::destroy();
            Response::redirect('/auth/connexion');
        }

        // Statut abonnement
        $abonnement   = (new Abonnement())->getActif($userId);
        $periodeJours = (int) ($_ENV['PERIODE_GRATUITE_JOURS'] ?? 1);
        $createdAt    = strtotime($user['created_at']);
        $expireGratuit = $createdAt + ($periodeJours * 86400);
        $resteGratuit  = max(0, $expireGratuit - time());

        $acces = $resteGratuit > 0 || $abonnement !== false;

        // Stats rapides + cours en cours (uniquement si accès)
        $stats   = $this->getStats($userId);
        $enCours = $acces ? $this->getEnCours($userId) : [];

        Response::view('hub/index', [
            'pageTitle'     => "Hub — Connect'Academia",
            'user'          => $user,
            'abonnement'    => $abonnement,
            'acces'         => $acces,
            'resteGratuit'  => $resteGratuit,
            'stats'         => $stats,
            'enCours'       => $enCours,
            'extraCss'      => ['hub.css'],
        ]);
    }

    public function stats(): void
    {
        $userId = Session::userId();
        Response::json(['success' => true, 'data' => $this->getStats($userId)]);
    }

    private function getStats(int $userId): array
    {
        try {
            $db = \Core\Database::getInstance()->getConnection();

            $stmtProg = $db->prepare(
                "SELECT COUNT(*) AS total,
                        SUM(CASE WHEN statut = 'termine' THEN 1 ELSE 0 END) AS termines,
                        COALESCE(ROUND(AVG(pourcentage)), 0) AS progression_globale
                 FROM progressions WHERE user_id = ?"
            );
            $stmtProg->execute([$userId]);
            $prog = $stmtProg->fetch(\PDO::FETCH_ASSOC);

            $stmtSocial = $db->prepare(
                "SELECT posts_count, followers_count FROM users WHERE id = ?"
            );
            $stmtSocial->execute([$userId]);
            $social = $stmtSocial->fetch(\PDO::FETCH_ASSOC);

            return [
                'cours_consultes'    => (int) ($prog['total'] ?? 0),
                'cours_termines'     => (int) ($prog['termines'] ?? 0),
                'progression_globale'=> (int) ($prog['progression_globale'] ?? 0),
                'posts'              => (int) ($social['posts_count'] ?? 0),
                'abonnes'            => (int) ($social['followers_count'] ?? 0),
            ];
        } catch (\Exception $e) {
            return [
                'cours_consultes'     => 0,
                'cours_termines'      => 0,
                'progression_globale' => 0,
                'posts'               => 0,
                'abonnes'             => 0,
            ];
        }
    }

    private function getEnCours(int $userId): array
    {
        try {
            return (new \Models\Progression())->getEnCours($userId, 3);
        } catch (\Exception $e) {
            return [];
        }
    }
}
