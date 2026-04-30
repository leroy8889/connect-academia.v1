<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\{Response, Session, Database};
use PDO;

class UsersController
{
    /** @var PDO */
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * GET /admin/users — Liste paginée des utilisateurs
     */
    public function index(): void
    {
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 25;
        $offset  = ($page - 1) * $perPage;

        // Filtres
        $role   = $_GET['role'] ?? '';
        $status = $_GET['status'] ?? '';
        $search = trim($_GET['q'] ?? '');

        $where  = ['u.is_deleted = 0'];
        $params = [];

        if (in_array($role, ['eleve', 'enseignant', 'admin'], true)) {
            $where[]  = 'u.role = ?';
            $params[] = $role;
        }

        if ($status === 'active') {
            $where[] = 'u.is_active = 1';
        } elseif ($status === 'suspended') {
            $where[] = 'u.is_active = 0';
        }

        if ($search !== '') {
            $where[]  = '(u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ?)';
            $like     = "%{$search}%";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $whereSql = implode(' AND ', $where);

        // Total
        $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM users u WHERE {$whereSql}");
        $stmt->execute($params);
        $total      = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        $totalPages = max(1, (int) ceil($total / $perPage));

        // Users
        $stmt = $this->db->prepare(
            "SELECT u.id, u.uuid, u.nom, u.prenom, u.email, u.role, u.classe, u.matiere,
                    u.photo_profil, u.is_active, u.is_verified, u.created_at, u.last_login,
                    u.posts_count, u.followers_count
             FROM users u
             WHERE {$whereSql}
             ORDER BY u.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Compteurs rapides
        $counts = $this->getCounts();

        Response::view('admin/users/index', [
            'pageTitle'   => 'Gestion des Utilisateurs — Admin StudyLink',
            'headerTitle' => 'Users Management',
            'users'       => $users,
            'counts'      => $counts,
            'total'       => $total,
            'page'        => $page,
            'totalPages'  => $totalPages,
            'filters'     => ['role' => $role, 'status' => $status, 'q' => $search],
        ], 'admin');
    }

    /**
     * PATCH /admin/api/users/{id}/toggle — Activer / Désactiver un utilisateur
     */
    public function toggle(string $id): void
    {
        $userId = (int) $id;
        $stmt = $this->db->prepare("SELECT id, is_active FROM users WHERE id = ? AND is_deleted = 0");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            Response::json(['success' => false, 'error' => 'Utilisateur introuvable'], 404);
        }

        $newStatus = $user['is_active'] ? 0 : 1;
        $stmt = $this->db->prepare("UPDATE users SET is_active = ? WHERE id = ?");
        $stmt->execute([$newStatus, $userId]);

        Response::json([
            'success' => true,
            'data'    => ['is_active' => $newStatus],
            'message' => $newStatus ? 'Utilisateur activé' : 'Utilisateur suspendu',
        ]);
    }

    /**
     * DELETE /admin/api/users/{id} — Supprimer un utilisateur (soft delete)
     */
    public function destroy(string $id): void
    {
        $userId = (int) $id;

        // Ne pas se supprimer soi-même
        if ($userId === Session::userId()) {
            Response::json(['success' => false, 'error' => 'Vous ne pouvez pas supprimer votre propre compte'], 403);
        }

        $stmt = $this->db->prepare("UPDATE users SET is_deleted = 1, is_active = 0 WHERE id = ? AND is_deleted = 0");
        $stmt->execute([$userId]);

        if ($stmt->rowCount() === 0) {
            Response::json(['success' => false, 'error' => 'Utilisateur introuvable'], 404);
        }

        Response::json(['success' => true, 'message' => 'Utilisateur supprimé']);
    }

    private function getCounts(): array
    {
        $all       = $this->count("SELECT COUNT(*) as total FROM users WHERE is_deleted = 0");
        $students  = $this->count("SELECT COUNT(*) as total FROM users WHERE is_deleted = 0 AND role = 'eleve'");
        $teachers  = $this->count("SELECT COUNT(*) as total FROM users WHERE is_deleted = 0 AND role = 'enseignant'");
        $admins    = $this->count("SELECT COUNT(*) as total FROM users WHERE is_deleted = 0 AND role = 'admin'");
        $suspended = $this->count("SELECT COUNT(*) as total FROM users WHERE is_deleted = 0 AND is_active = 0");

        return compact('all', 'students', 'teachers', 'admins', 'suspended');
    }

    private function count(string $sql): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
}

