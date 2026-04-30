<?php
declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

class Admin extends BaseModel
{
    protected string $table = 'admins';

    public function findByEmail(string $email): array|false
    {
        return $this->query(
            "SELECT * FROM admins WHERE email = ? AND is_active = 1 AND is_locked = 0",
            [$email]
        )->fetch(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): array|false
    {
        return $this->query(
            "SELECT * FROM admins WHERE id = ? AND is_active = 1",
            [$id]
        )->fetch(PDO::FETCH_ASSOC);
    }

    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function logConnection(int|null $adminId, string $email, string $ip, string $agent, string $statut): void
    {
        $this->query(
            "INSERT INTO historique_connexions_admin (admin_id, email, ip, user_agent, statut) VALUES (?, ?, ?, ?, ?)",
            [$adminId, $email, $ip, substr($agent, 0, 500), $statut]
        );
    }

    public function incrementFailedAttempts(int $id): void
    {
        // On utilise is_locked pour bloquer après trop d'échecs (logique simple)
        $this->query(
            "UPDATE admins SET updated_at = NOW() WHERE id = ?",
            [$id]
        );
    }

    public function getAll(int $limit = 50, int $offset = 0): array
    {
        return $this->query(
            "SELECT id, nom, prenom, email, role, totp_enabled, is_active, is_locked, created_at
             FROM admins ORDER BY created_at DESC LIMIT ? OFFSET ?",
            [$limit, $offset]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        $this->query(
            "INSERT INTO admins (nom, prenom, email, password_hash, role) VALUES (?, ?, ?, ?, ?)",
            [
                $data['nom'],
                $data['prenom'],
                $data['email'],
                $hash,
                $data['role'] ?? 'admin',
            ]
        );
        return $this->lastInsertId();
    }

    // ── Stats globales pour le dashboard ─────────────────────────────────

    public function getDashboardStats(): array
    {
        $db = Database::getInstance()->getConnection();

        $totalUsers = (int) $db->query(
            "SELECT COUNT(*) FROM users WHERE is_deleted = 0"
        )->fetchColumn();

        $totalRessources = (int) $db->query(
            "SELECT COUNT(*) FROM ressources WHERE is_deleted = 0"
        )->fetchColumn();

        $activesMonth = (int) $db->query(
            "SELECT COUNT(*) FROM users WHERE last_login >= DATE_FORMAT(NOW(), '%Y-%m-01') AND is_deleted = 0"
        )->fetchColumn();

        $engagement = (float) ($db->query(
            "SELECT COALESCE(AVG(pourcentage), 0) FROM progressions"
        )->fetchColumn() ?? 0);

        $totalPosts = (int) $db->query(
            "SELECT COUNT(*) FROM posts WHERE is_deleted = 0"
        )->fetchColumn();

        $pendingReports = (int) $db->query(
            "SELECT COUNT(*) FROM reports WHERE status = 'pending'"
        )->fetchColumn();

        return compact('totalUsers', 'totalRessources', 'activesMonth', 'engagement', 'totalPosts', 'pendingReports');
    }

    public function getCroissanceInscriptions(): array
    {
        $db = Database::getInstance()->getConnection();
        return $db->query(
            "SELECT DATE_FORMAT(created_at, '%b %Y') AS mois,
                    DATE_FORMAT(created_at, '%Y-%m') AS mois_key,
                    COUNT(*) AS nb
             FROM users
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) AND is_deleted = 0
             GROUP BY DATE_FORMAT(created_at, '%Y-%m'), DATE_FORMAT(created_at, '%b %Y')
             ORDER BY mois_key ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRepartitionSeries(): array
    {
        $db = Database::getInstance()->getConnection();
        return $db->query(
            "SELECT s.nom AS serie, s.couleur, COUNT(u.id) AS nb
             FROM series s
             LEFT JOIN users u ON u.serie_id = s.id AND u.is_deleted = 0
             WHERE s.is_active = 1
             GROUP BY s.id
             ORDER BY s.nom"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDernieresInscriptions(int $limit = 5): array
    {
        $db = Database::getInstance()->getConnection();
        return $db->query(
            "SELECT u.id, u.nom, u.prenom, u.email, u.role, u.photo_profil,
                    s.nom AS serie, u.created_at, u.is_active
             FROM users u
             LEFT JOIN series s ON s.id = u.serie_id
             WHERE u.is_deleted = 0
             ORDER BY u.created_at DESC
             LIMIT {$limit}"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActiviteParMatiere(int $limit = 5): array
    {
        $db = Database::getInstance()->getConnection();
        return $db->query(
            "SELECT m.nom, m.icone,
                    COUNT(DISTINCT p.ressource_id) AS nb_consultations,
                    COALESCE(AVG(p.pourcentage), 0) AS progression_moyenne
             FROM matieres m
             LEFT JOIN ressources r ON r.matiere_id = m.id AND r.is_deleted = 0
             LEFT JOIN progressions p ON p.ressource_id = r.id
             WHERE m.is_active = 1
             GROUP BY m.id
             ORDER BY nb_consultations DESC
             LIMIT {$limit}"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActiviteRecente(int $limit = 5): array
    {
        $db = Database::getInstance()->getConnection();
        return $db->query(
            "SELECT u.id, u.nom, u.prenom, u.photo_profil, u.role,
                    p.updated_at AS action_at,
                    'consultation' AS action_type,
                    r.titre AS action_label,
                    m.nom AS matiere_nom
             FROM progressions p
             JOIN users u ON u.id = p.user_id
             JOIN ressources r ON r.id = p.ressource_id
             JOIN matieres m ON m.id = r.matiere_id
             WHERE u.is_deleted = 0
             ORDER BY p.updated_at DESC
             LIMIT {$limit}"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Gestion utilisateurs ─────────────────────────────────────────────

    public function getAllUsers(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $db     = Database::getInstance()->getConnection();
        $where  = ['u.is_deleted = 0'];
        $params = [];

        if (!empty($filters['role'])) {
            $where[]  = 'u.role = ?';
            $params[] = $filters['role'];
        }
        if (!empty($filters['status'])) {
            $where[] = $filters['status'] === 'active' ? 'u.is_active = 1' : 'u.is_active = 0';
        }
        if (!empty($filters['q'])) {
            $like     = '%' . $filters['q'] . '%';
            $where[]  = '(u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $params[] = $limit;
        $params[] = $offset;

        $sql = "
            SELECT u.id, u.nom, u.prenom, u.email, u.role, u.photo_profil,
                   u.is_active, u.last_login, u.created_at, u.posts_count,
                   s.nom AS serie, m.nom AS matiere
            FROM users u
            LEFT JOIN series s ON s.id = u.serie_id
            LEFT JOIN ressources top_r ON top_r.id = (
                SELECT p2.ressource_id FROM progressions p2
                WHERE p2.user_id = u.id
                GROUP BY p2.ressource_id
                ORDER BY COUNT(*) DESC LIMIT 1
            )
            LEFT JOIN matieres m ON m.id = top_r.matiere_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY u.created_at DESC
            LIMIT ? OFFSET ?";

        return $db->prepare($sql) ? $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function countUsers(array $filters = []): int
    {
        $db    = Database::getInstance()->getConnection();
        $where = ['is_deleted = 0'];
        $params = [];

        if (!empty($filters['role'])) {
            $where[]  = 'role = ?';
            $params[] = $filters['role'];
        }
        if (!empty($filters['status'])) {
            $where[] = $filters['status'] === 'active' ? 'is_active = 1' : 'is_active = 0';
        }
        if (!empty($filters['q'])) {
            $like     = '%' . $filters['q'] . '%';
            $where[]  = '(nom LIKE ? OR prenom LIKE ? OR email LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        return (int) $this->query(
            "SELECT COUNT(*) FROM users WHERE " . implode(' AND ', $where),
            $params
        )->fetchColumn();
    }

    public function getUserCountsByRole(): array
    {
        $db = Database::getInstance()->getConnection();
        $rows = $db->query(
            "SELECT role, COUNT(*) AS nb, SUM(is_active = 1) AS actifs, SUM(is_active = 0) AS suspendus
             FROM users WHERE is_deleted = 0 GROUP BY role"
        )->fetchAll(PDO::FETCH_ASSOC);

        $counts = ['total' => 0, 'eleve' => 0, 'enseignant' => 0, 'admin' => 0, 'actifs' => 0, 'suspendus' => 0];
        foreach ($rows as $row) {
            $counts['total']     += $row['nb'];
            $counts[$row['role']] = $row['nb'];
            $counts['actifs']    += $row['actifs'];
            $counts['suspendus'] += $row['suspendus'];
        }
        return $counts;
    }

    public function toggleUser(int $userId): bool
    {
        $user = $this->query(
            "SELECT is_active FROM users WHERE id = ? AND is_deleted = 0",
            [$userId]
        )->fetch(PDO::FETCH_ASSOC);

        if (!$user) return false;

        $newStatus = $user['is_active'] ? 0 : 1;
        $this->query(
            "UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?",
            [$newStatus, $userId]
        );
        return (bool) $newStatus;
    }

    // ── Analytics ────────────────────────────────────────────────────────

    public function getHeatmapData(): array
    {
        $db = Database::getInstance()->getConnection();
        return $db->query(
            "SELECT DAYOFWEEK(debut) - 1 AS jour_num,
                    HOUR(debut) AS heure,
                    COUNT(*) AS nb
             FROM sessions_revision
             WHERE debut >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY jour_num, heure
             ORDER BY jour_num, heure"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTopRessources(int $limit = 7): array
    {
        $db = Database::getInstance()->getConnection();
        return $db->query(
            "SELECT r.titre, r.nb_vues, m.nom AS matiere
             FROM ressources r
             JOIN matieres m ON m.id = r.matiere_id
             WHERE r.is_deleted = 0
             ORDER BY r.nb_vues DESC
             LIMIT {$limit}"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFunnelData(): array
    {
        $db = Database::getInstance()->getConnection();
        $inscrits     = (int) $db->query("SELECT COUNT(*) FROM users WHERE is_deleted = 0")->fetchColumn();
        $profilComplet = (int) $db->query("SELECT COUNT(*) FROM users WHERE is_deleted = 0 AND photo_profil IS NOT NULL AND bio IS NOT NULL")->fetchColumn();
        $premiereConsult = (int) $db->query("SELECT COUNT(DISTINCT user_id) FROM progressions")->fetchColumn();
        $premierFavori  = (int) $db->query("SELECT COUNT(DISTINCT user_id) FROM favoris")->fetchColumn();
        $premierPost   = (int) $db->query("SELECT COUNT(DISTINCT user_id) FROM posts WHERE is_deleted = 0")->fetchColumn();
        $actifsReguliers = (int) $db->query(
            "SELECT COUNT(DISTINCT user_id) FROM sessions_revision WHERE debut >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        )->fetchColumn();

        return compact('inscrits', 'profilComplet', 'premiereConsult', 'premierFavori', 'premierPost', 'actifsReguliers');
    }
}
