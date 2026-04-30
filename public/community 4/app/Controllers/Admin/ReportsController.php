<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\{Response, Session, Database};
use PDO;

class ReportsController
{
    /** @var PDO */
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * GET /admin/reports — Liste des signalements
     */
    public function index(): void
    {
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 25;
        $offset  = ($page - 1) * $perPage;
        $status  = $_GET['status'] ?? '';

        $where  = [];
        $params = [];

        if (in_array($status, ['pending', 'reviewed', 'dismissed'], true)) {
            $where[]  = 'r.status = ?';
            $params[] = $status;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Total
        $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM reports r {$whereSql}");
        $stmt->execute($params);
        $total      = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        $totalPages = max(1, (int) ceil($total / $perPage));

        // Reports
        $stmt = $this->db->prepare(
            "SELECT r.id, r.reason, r.description, r.status, r.admin_note, r.created_at, r.updated_at,
                    r.post_id, r.comment_id,
                    reporter.id AS reporter_id, reporter.prenom AS reporter_prenom, reporter.nom AS reporter_nom,
                    reporter.photo_profil AS reporter_photo, reporter.role AS reporter_role,
                    p.contenu AS post_contenu, p.type AS post_type,
                    post_author.prenom AS author_prenom, post_author.nom AS author_nom
             FROM reports r
             LEFT JOIN users reporter ON r.reporter_id = reporter.id
             LEFT JOIN posts p ON r.post_id = p.id
             LEFT JOIN users post_author ON p.user_id = post_author.id
             LEFT JOIN comments c ON r.comment_id = c.id
             {$whereSql}
             ORDER BY FIELD(r.status, 'pending', 'reviewed', 'dismissed'), r.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Compteurs
        $counts = [
            'all'       => $this->count("SELECT COUNT(*) as total FROM reports"),
            'pending'   => $this->count("SELECT COUNT(*) as total FROM reports WHERE status = 'pending'"),
            'reviewed'  => $this->count("SELECT COUNT(*) as total FROM reports WHERE status = 'reviewed'"),
            'dismissed' => $this->count("SELECT COUNT(*) as total FROM reports WHERE status = 'dismissed'"),
        ];

        Response::view('admin/reports/index', [
            'pageTitle'   => 'Gestion des Signalements — Admin StudyLink',
            'headerTitle' => 'Reports & Moderation',
            'reports'     => $reports,
            'counts'      => $counts,
            'total'       => $total,
            'page'        => $page,
            'totalPages'  => $totalPages,
            'filterStatus'=> $status,
        ], 'admin');
    }

    /**
     * PATCH /admin/api/reports/{id} — Mettre à jour le statut d'un signalement
     */
    public function update(string $id): void
    {
        $reportId = (int) $id;
        $input    = json_decode(file_get_contents('php://input'), true) ?: [];
        $newStatus = $input['status'] ?? '';
        $adminNote = trim($input['admin_note'] ?? '');

        if (!in_array($newStatus, ['pending', 'reviewed', 'dismissed'], true)) {
            Response::json(['success' => false, 'error' => 'Statut invalide'], 400);
        }

        $stmt = $this->db->prepare("SELECT id FROM reports WHERE id = ?");
        $stmt->execute([$reportId]);
        if (!$stmt->fetch()) {
            Response::json(['success' => false, 'error' => 'Signalement introuvable'], 404);
        }

        $stmt = $this->db->prepare("UPDATE reports SET status = ?, admin_note = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newStatus, $adminNote ?: null, $reportId]);

        Response::json(['success' => true, 'message' => 'Signalement mis à jour']);
    }

    /**
     * DELETE /admin/api/reports/{id}/content — Supprimer le contenu signalé
     */
    public function deleteContent(string $id): void
    {
        $reportId = (int) $id;
        $stmt = $this->db->prepare("SELECT id, post_id, comment_id FROM reports WHERE id = ?");
        $stmt->execute([$reportId]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$report) {
            Response::json(['success' => false, 'error' => 'Signalement introuvable'], 404);
        }

        // Supprimer le contenu (soft delete)
        if ($report['post_id']) {
            $this->db->prepare("UPDATE posts SET is_deleted = 1 WHERE id = ?")->execute([$report['post_id']]);
        }
        if ($report['comment_id']) {
            $this->db->prepare("UPDATE comments SET is_deleted = 1 WHERE id = ?")->execute([$report['comment_id']]);
        }

        // Marquer le rapport comme traité
        $this->db->prepare("UPDATE reports SET status = 'reviewed', admin_note = CONCAT(IFNULL(admin_note,''), ' [Content deleted by admin]'), updated_at = NOW() WHERE id = ?")
            ->execute([$reportId]);

        Response::json(['success' => true, 'message' => 'Contenu supprimé et signalement traité']);
    }

    private function count(string $sql): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
}

