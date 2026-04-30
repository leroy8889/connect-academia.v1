<?php
declare(strict_types=1);

namespace Models;

use PDO;

class Progression extends BaseModel
{
    protected string $table = 'progressions';

    public function getGlobale(int $userId): int
    {
        $result = $this->query(
            "SELECT AVG(pourcentage) AS moyenne FROM progressions WHERE user_id = ?",
            [$userId]
        )->fetch(PDO::FETCH_ASSOC);
        return (int) round((float)($result['moyenne'] ?? 0));
    }

    public function getTempsHebdo(int $userId): int
    {
        $result = $this->query(
            "SELECT COALESCE(SUM(duree_secondes), 0) AS temps
             FROM sessions_revision
             WHERE user_id = ? AND debut >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            [$userId]
        )->fetch(PDO::FETCH_ASSOC);
        return (int) ($result['temps'] ?? 0);
    }

    public function getCoursStats(int $userId, int $serieId): array
    {
        $result = $this->query(
            "SELECT COUNT(DISTINCT p.ressource_id) AS consultes,
                    COUNT(DISTINCT r.id) AS total
             FROM progressions p
             RIGHT JOIN ressources r ON r.id = p.ressource_id AND (p.user_id = ? OR p.user_id IS NULL)
             WHERE r.serie_id = ? AND r.is_deleted = 0",
            [$userId, $serieId]
        )->fetch(PDO::FETCH_ASSOC);
        return $result ?: ['consultes' => 0, 'total' => 0];
    }

    public function getTerminees(int $userId): int
    {
        $result = $this->query(
            "SELECT COUNT(*) AS total FROM progressions WHERE user_id = ? AND statut = 'termine'",
            [$userId]
        )->fetch(PDO::FETCH_ASSOC);
        return (int) ($result['total'] ?? 0);
    }

    public function getMatiereFavorite(int $userId): ?array
    {
        $result = $this->query(
            "SELECT m.nom, COUNT(*) AS nb
             FROM progressions p
             JOIN ressources r ON r.id = p.ressource_id
             JOIN matieres m ON m.id = r.matiere_id
             WHERE p.user_id = ? AND p.temps_passe > 0
             GROUP BY m.id
             ORDER BY nb DESC
             LIMIT 1",
            [$userId]
        )->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getEnCours(int $userId, int $limit = 3): array
    {
        return $this->query(
            "SELECT r.id, r.titre, r.type, m.nom AS matiere, p.pourcentage, p.derniere_page
             FROM progressions p
             JOIN ressources r ON r.id = p.ressource_id
             JOIN matieres m ON m.id = r.matiere_id
             WHERE p.user_id = ? AND p.statut = 'en_cours'
             ORDER BY p.updated_at DESC
             LIMIT ?",
            [$userId, $limit]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getParMatiere(int $userId): array
    {
        return $this->query(
            "SELECT m.id, m.nom, m.icone,
                    COUNT(DISTINCT r.id) AS total_ressources,
                    COUNT(DISTINCT CASE WHEN p.statut = 'termine' THEN r.id END) AS terminees,
                    COUNT(DISTINCT CASE WHEN p.statut = 'en_cours' THEN r.id END) AS en_cours,
                    AVG(p.pourcentage) AS progression_moyenne
             FROM matieres m
             JOIN ressources r ON r.matiere_id = m.id AND r.is_deleted = 0
             LEFT JOIN progressions p ON p.ressource_id = r.id AND p.user_id = ?
             WHERE m.serie_id = (SELECT serie_id FROM users WHERE id = ?)
             GROUP BY m.id
             ORDER BY m.ordre ASC",
            [$userId, $userId]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function upsert(int $userId, int $ressourceId, array $data): void
    {
        $existing = $this->query(
            "SELECT id, statut FROM progressions WHERE user_id = ? AND ressource_id = ?",
            [$userId, $ressourceId]
        )->fetch(PDO::FETCH_ASSOC);

        $pourcentage  = max(0, min(100, (int) ($data['pourcentage'] ?? 0)));
        $dernierePage = max(1, (int) ($data['derniere_page'] ?? 1));
        $statut       = $pourcentage >= 100 ? 'termine' : ($pourcentage > 0 ? 'en_cours' : 'non_commence');

        if ($existing) {
            if ($existing['statut'] === 'termine') return;
            $this->query(
                "UPDATE progressions
                 SET pourcentage = GREATEST(pourcentage, ?), statut = ?, derniere_page = ?, updated_at = NOW()
                 WHERE user_id = ? AND ressource_id = ?",
                [$pourcentage, $statut, $dernierePage, $userId, $ressourceId]
            );
        } else {
            $this->query(
                "INSERT INTO progressions (user_id, ressource_id, pourcentage, statut, derniere_page, started_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
                [$userId, $ressourceId, $pourcentage, $statut, $dernierePage]
            );
        }

        if ($statut === 'termine') {
            $this->query(
                "UPDATE progressions SET completed_at = NOW() WHERE user_id = ? AND ressource_id = ? AND completed_at IS NULL",
                [$userId, $ressourceId]
            );
        }
    }

    public function marquerTermine(int $userId, int $ressourceId): void
    {
        $this->upsert($userId, $ressourceId, ['pourcentage' => 100, 'derniere_page' => 1]);
    }

    public function logSession(int $userId, int $ressourceId, int $dureeSecondes): void
    {
        if ($dureeSecondes < 5) return;
        $this->query(
            "INSERT INTO sessions_revision (user_id, ressource_id, debut, fin, duree_secondes)
             VALUES (?, ?, DATE_SUB(NOW(), INTERVAL ? SECOND), NOW(), ?)",
            [$userId, $ressourceId, $dureeSecondes, $dureeSecondes]
        );
        $this->query(
            "UPDATE progressions SET temps_passe = temps_passe + ? WHERE user_id = ? AND ressource_id = ?",
            [$dureeSecondes, $userId, $ressourceId]
        );
    }
}
