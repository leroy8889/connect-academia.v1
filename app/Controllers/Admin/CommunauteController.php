<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\{Response, Database};
use Models\Report;
use PDO;

class CommunauteController
{
    private PDO    $db;
    private Report $report;

    public function __construct()
    {
        $this->db     = Database::getInstance()->getConnection();
        $this->report = new Report();
    }

    public function index(): void
    {
        $stats = $this->report->getCommunauteStats();

        // Posts récents avec infos auteur + matière (tag texte) + série
        $posts = $this->db->query(
            "SELECT p.id, p.contenu, p.likes_count, p.comments_count, p.is_pinned, p.created_at,
                    u.nom, u.prenom, u.role, u.photo_profil,
                    p.matiere_tag AS matiere_nom,
                    s.nom         AS serie_nom
             FROM posts p
             JOIN users u ON u.id = p.user_id
             LEFT JOIN series s ON s.id = u.serie_id
             WHERE p.is_deleted = 0
             ORDER BY p.is_pinned DESC, p.created_at DESC
             LIMIT 20"
        )->fetchAll(PDO::FETCH_ASSOC);

        // Top contributeurs (30 derniers jours) avec série
        $topContributeurs = $this->db->query(
            "SELECT u.id, u.nom, u.prenom, u.role, u.photo_profil,
                    s.nom AS serie_nom,
                    COUNT(p.id) AS posts_count
             FROM posts p
             JOIN users u ON u.id = p.user_id
             LEFT JOIN series s ON s.id = u.serie_id
             WHERE p.is_deleted = 0
               AND p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY u.id
             ORDER BY posts_count DESC
             LIMIT 5"
        )->fetchAll(PDO::FETCH_ASSOC);

        Response::view('admin/communaute/index', [
            'pageTitle'          => 'Communauté — Admin',
            'breadcrumbSection'  => 'Communauté',
            'breadcrumbPage'     => 'Publications',
            'stats'              => [
                'posts'      => $stats['posts'],
                'comments'   => $stats['comments'],
                'likes'      => $stats['likes'],
                'reports'    => $stats['reports'],
                'posts_mois' => $stats['postsMois'],
            ],
            'posts'              => $posts,
            'topContributeurs'   => $topContributeurs,
        ], 'admin');
    }

    // ── DELETE /admin/api/communaute/posts/{id} ───────────────────────────
    public function deletePost(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);

        $stmt = $this->db->prepare("SELECT id FROM posts WHERE id = ? AND is_deleted = 0");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            Response::json(['success' => false, 'message' => 'Publication introuvable.'], 404);
        }

        $this->db->prepare(
            "UPDATE posts SET is_deleted = 1, updated_at = NOW() WHERE id = ?"
        )->execute([$id]);

        Response::json(['success' => true, 'message' => 'Publication supprimée.']);
    }

    // ── PATCH /admin/api/communaute/posts/{id}/pin ────────────────────────
    public function pinPost(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);

        $stmt = $this->db->prepare("SELECT id, is_pinned FROM posts WHERE id = ? AND is_deleted = 0");
        $stmt->execute([$id]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$post) {
            Response::json(['success' => false, 'message' => 'Publication introuvable.'], 404);
        }

        $newPin = $post['is_pinned'] ? 0 : 1;
        $this->db->prepare(
            "UPDATE posts SET is_pinned = ?, updated_at = NOW() WHERE id = ?"
        )->execute([$newPin, $id]);

        Response::json([
            'success'  => true,
            'pinned'   => (bool) $newPin,
            'message'  => $newPin ? 'Publication épinglée.' : 'Publication désépinglée.',
        ]);
    }

    public function traiterReport(array $params): void
    {
        $id     = (int) ($params['id'] ?? 0);
        $action = $_POST['action'] ?? $this->jsonInput('action');

        if (!in_array($action, ['reviewed', 'rejected'])) {
            Response::json(['success' => false, 'message' => 'Action invalide.'], 422);
        }

        $exists = $this->db->prepare("SELECT id FROM reports WHERE id = ?");
        $exists->execute([$id]);
        if (!$exists->fetch()) {
            Response::json(['success' => false, 'message' => 'Signalement introuvable.'], 404);
        }

        $this->report->updateStatus($id, $action);

        Response::json([
            'success' => true,
            'message' => $action === 'reviewed' ? 'Signalement marqué comme examiné.' : 'Signalement rejeté.',
        ]);
    }

    private function jsonInput(string $key): string
    {
        static $payload = null;
        if ($payload === null) {
            $payload = (array) (json_decode(file_get_contents('php://input'), true) ?? []);
        }
        return (string) ($payload[$key] ?? '');
    }
}
