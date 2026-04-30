<?php
declare(strict_types=1);

namespace Models;

use PDO;

class Abonnement extends BaseModel
{
    protected string $table = 'abonnements';

    public function getActif(int $userId): array|false
    {
        return $this->query(
            "SELECT * FROM abonnements WHERE user_id = ? AND statut = 'actif' AND fin > NOW() ORDER BY fin DESC LIMIT 1",
            [$userId]
        )->fetch(PDO::FETCH_ASSOC);
    }

    public function getExpire(int $userId): array|false
    {
        return $this->query(
            "SELECT * FROM abonnements WHERE user_id = ? AND (statut = 'expire' OR fin <= NOW()) ORDER BY fin DESC LIMIT 1",
            [$userId]
        )->fetch(PDO::FETCH_ASSOC);
    }

    public function creer(int $userId, string $plan, int $dureeJours): int
    {
        $this->query(
            "INSERT INTO abonnements (user_id, plan, statut, debut, fin)
             VALUES (?, ?, 'actif', NOW(), DATE_ADD(NOW(), INTERVAL ? DAY))",
            [$userId, $plan, $dureeJours]
        );
        return $this->lastInsertId();
    }
}
