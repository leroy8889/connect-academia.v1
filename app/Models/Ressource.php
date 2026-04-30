<?php
declare(strict_types=1);

namespace Models;

use PDO;

class Ressource extends BaseModel
{
    protected string $table = 'ressources';
    protected bool $softDeletes = true;

    public function getByMatiere(int $matiereId, int $userId, ?string $type = null): array
    {
        $sql = "
            SELECT r.id, r.titre, r.type, r.description, r.nb_vues, r.created_at,
                   ch.titre AS chapitre,
                   p.statut, p.pourcentage, p.derniere_page,
                   (SELECT COUNT(*) FROM favoris f WHERE f.user_id = ? AND f.ressource_id = r.id) AS est_favori
            FROM ressources r
            LEFT JOIN chapitres ch ON ch.id = r.chapitre_id
            LEFT JOIN progressions p ON p.ressource_id = r.id AND p.user_id = ?
            WHERE r.matiere_id = ? AND r.is_deleted = 0
        ";
        $params = [$userId, $userId, $matiereId];

        if ($type !== null && $type !== 'tous') {
            $sql .= " AND r.type = ?";
            $params[] = $type;
        }

        $sql .= " ORDER BY r.created_at DESC";

        return $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findWithProgression(int $id, ?int $userId): ?array
    {
        if ($userId) {
            $sql = "
                SELECT r.id, r.titre, r.type, r.description, r.fichier_path, r.nb_vues, r.serie_id,
                       m.nom AS matiere, s.nom AS serie,
                       p.statut, p.pourcentage, p.derniere_page, p.temps_passe
                FROM ressources r
                JOIN matieres m ON m.id = r.matiere_id
                JOIN series s ON s.id = r.serie_id
                LEFT JOIN progressions p ON p.ressource_id = r.id AND p.user_id = ?
                WHERE r.id = ? AND r.is_deleted = 0
            ";
            $result = $this->query($sql, [$userId, $id])->fetch(PDO::FETCH_ASSOC);
        } else {
            $sql = "
                SELECT r.id, r.titre, r.type, r.description, r.fichier_path, r.nb_vues, r.serie_id,
                       m.nom AS matiere, s.nom AS serie,
                       NULL AS statut, NULL AS pourcentage, NULL AS derniere_page, NULL AS temps_passe
                FROM ressources r
                JOIN matieres m ON m.id = r.matiere_id
                JOIN series s ON s.id = r.serie_id
                WHERE r.id = ? AND r.is_deleted = 0
            ";
            $result = $this->query($sql, [$id])->fetch(PDO::FETCH_ASSOC);
        }
        return $result ?: null;
    }

    public function getAutres(int $id, int $limit = 5): array
    {
        return $this->query(
            "SELECT r.id, r.titre, r.type
             FROM ressources r
             WHERE r.matiere_id = (SELECT matiere_id FROM ressources WHERE id = ?)
               AND r.id != ? AND r.is_deleted = 0
             ORDER BY r.created_at DESC
             LIMIT ?",
            [$id, $id, $limit]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function incrementVues(int $id): void
    {
        $this->query("UPDATE ressources SET nb_vues = nb_vues + 1 WHERE id = ?", [$id]);
    }

    public function getRecentes(int $limit = 6): array
    {
        return $this->query(
            "SELECT r.id, r.titre, r.type, r.description, m.nom AS matiere, s.nom AS serie, r.nb_vues, r.created_at
             FROM ressources r
             JOIN matieres m ON m.id = r.matiere_id
             JOIN series s ON s.id = r.serie_id
             WHERE r.is_deleted = 0
             ORDER BY r.created_at DESC
             LIMIT ?",
            [$limit]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getListeApi(int $userId, ?int $matiereId, ?string $type, int $limit = 20): array
    {
        $params = [$userId, $userId];
        $where  = ['r.is_deleted = 0'];

        if ($matiereId) {
            $where[]  = 'r.matiere_id = ?';
            $params[] = $matiereId;
        }
        if ($type && $type !== 'tous') {
            $where[]  = 'r.type = ?';
            $params[] = $type;
        }

        $params[] = $limit;

        $sql = "
            SELECT r.id, r.titre, r.type, r.description, r.nb_vues, r.created_at,
                   m.nom AS matiere, s.nom AS serie, ch.titre AS chapitre,
                   p.statut, p.pourcentage,
                   (SELECT COUNT(*) FROM favoris f WHERE f.user_id = ? AND f.ressource_id = r.id) AS est_favori
            FROM ressources r
            JOIN matieres m ON m.id = r.matiere_id
            JOIN series s ON s.id = r.serie_id
            LEFT JOIN chapitres ch ON ch.id = r.chapitre_id
            LEFT JOIN progressions p ON p.ressource_id = r.id AND p.user_id = ?
            WHERE " . implode(' AND ', $where) . "
            ORDER BY r.created_at DESC
            LIMIT ?
        ";

        return $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }
}
