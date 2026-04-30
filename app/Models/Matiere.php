<?php
declare(strict_types=1);

namespace Models;

use PDO;

class Matiere extends BaseModel
{
    protected string $table = 'matieres';

    public function getBySerie(int $serieId, int $userId): array
    {
        return $this->query(
            "SELECT m.id, m.nom, m.icone, m.ordre,
                    COUNT(DISTINCT r.id) AS nb_ressources,
                    COALESCE(AVG(p.pourcentage), 0) AS progression_moyenne
             FROM matieres m
             LEFT JOIN ressources r ON r.matiere_id = m.id AND r.is_deleted = 0
             LEFT JOIN progressions p ON p.ressource_id = r.id AND p.user_id = ?
             WHERE m.serie_id = ? AND m.is_active = 1
             GROUP BY m.id
             ORDER BY m.ordre ASC",
            [$userId, $serieId]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findActive(int $id): ?array
    {
        $result = $this->query(
            "SELECT m.id, m.nom, m.icone, s.nom AS serie
             FROM matieres m
             JOIN series s ON s.id = m.serie_id
             WHERE m.id = ? AND m.is_active = 1",
            [$id]
        )->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getAll(): array
    {
        return $this->query(
            "SELECT m.id, m.nom, m.icone, s.nom AS serie
             FROM matieres m
             JOIN series s ON s.id = m.serie_id
             WHERE m.is_active = 1
             ORDER BY s.nom, m.ordre"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSeries(): array
    {
        return $this->query(
            "SELECT id, nom, description, couleur FROM series WHERE is_active = 1 ORDER BY nom"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findSerie(int $id): ?array
    {
        $result = $this->query(
            "SELECT id, nom, description, couleur FROM series WHERE id = ?",
            [$id]
        )->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
}
