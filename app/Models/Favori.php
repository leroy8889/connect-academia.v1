<?php
declare(strict_types=1);

namespace Models;

use PDO;

class Favori extends BaseModel
{
    protected string $table = 'favoris';

    public function getByUser(int $userId): array
    {
        return $this->query(
            "SELECT r.id, r.titre, r.type, r.description, r.nb_vues,
                    m.nom AS matiere, s.nom AS serie,
                    p.statut, p.pourcentage
             FROM favoris f
             JOIN ressources r ON r.id = f.ressource_id
             JOIN matieres m ON m.id = r.matiere_id
             JOIN series s ON s.id = r.serie_id
             LEFT JOIN progressions p ON p.ressource_id = r.id AND p.user_id = ?
             WHERE f.user_id = ? AND r.is_deleted = 0
             ORDER BY f.created_at DESC",
            [$userId, $userId]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function toggle(int $userId, int $ressourceId): array
    {
        $existing = $this->query(
            "SELECT id FROM favoris WHERE user_id = ? AND ressource_id = ?",
            [$userId, $ressourceId]
        )->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $this->query("DELETE FROM favoris WHERE id = ?", [$existing['id']]);
            return ['favori' => false];
        }

        $this->query(
            "INSERT INTO favoris (user_id, ressource_id, created_at) VALUES (?, ?, NOW())",
            [$userId, $ressourceId]
        );
        return ['favori' => true];
    }

    public function isFavori(int $userId, int $ressourceId): bool
    {
        return (bool) $this->query(
            "SELECT 1 FROM favoris WHERE user_id = ? AND ressource_id = ?",
            [$userId, $ressourceId]
        )->fetch(PDO::FETCH_ASSOC);
    }
}
