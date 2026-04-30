<?php
declare(strict_types=1);

namespace Models;

use PDO;

class Report extends BaseModel
{
    protected string $table = 'reports';

    public function create(array $data): int
    {
        $existing = $this->query(
            "SELECT id FROM reports
             WHERE reporter_id = ? AND post_id <=> ? AND comment_id <=> ? AND status = 'pending'",
            [$data['reporter_id'], $data['post_id'] ?? null, $data['comment_id'] ?? null]
        )->fetch(PDO::FETCH_ASSOC);

        if ($existing) return (int) $existing['id'];

        $this->query(
            "INSERT INTO reports (reporter_id, post_id, comment_id, reason, description, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())",
            [
                $data['reporter_id'],
                $data['post_id'] ?? null,
                $data['comment_id'] ?? null,
                $data['reason'],
                $data['description'] ?? null,
            ]
        );
        return $this->lastInsertId();
    }

    public function getPending(int $limit = 50): array
    {
        return $this->query(
            "SELECT r.*, u.nom AS reporter_nom, u.prenom AS reporter_prenom
             FROM reports r LEFT JOIN users u ON r.reporter_id = u.id
             WHERE r.status = 'pending'
             ORDER BY r.created_at DESC LIMIT ?",
            [$limit]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateStatus(int $id, string $status, ?string $note = null): void
    {
        $this->query(
            "UPDATE reports SET status = ?, admin_note = ?, updated_at = NOW() WHERE id = ?",
            [$status, $note, $id]
        );
    }

    public function getByStatus(string $status, int $limit = 30): array
    {
        return $this->query(
            "SELECT r.*,
                    u_reporter.nom  AS reporter_nom,  u_reporter.prenom AS reporter_prenom,
                    u_cible.nom     AS cible_nom,     u_cible.prenom    AS cible_prenom,
                    p.contenu       AS post_content
             FROM reports r
             LEFT JOIN users u_reporter ON u_reporter.id = r.reporter_id
             LEFT JOIN posts p          ON p.id = r.post_id
             LEFT JOIN users u_cible    ON u_cible.id = p.user_id
             WHERE r.status = ?
             ORDER BY r.created_at DESC
             LIMIT ?",
            [$status, $limit]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCommunauteStats(): array
    {
        $db = \Core\Database::getInstance()->getConnection();

        $posts    = (int) $db->query("SELECT COUNT(*) FROM posts WHERE is_deleted = 0")->fetchColumn();
        $comments = (int) $db->query("SELECT COUNT(*) FROM comments WHERE is_deleted = 0")->fetchColumn();
        $likes    = (int) $db->query("SELECT COUNT(*) FROM likes")->fetchColumn();
        $reports  = (int) $db->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'")->fetchColumn();
        $postsMois = (int) $db->query(
            "SELECT COUNT(*) FROM posts WHERE is_deleted = 0 AND created_at >= DATE_FORMAT(NOW(),'%Y-%m-01')"
        )->fetchColumn();

        return compact('posts', 'comments', 'likes', 'reports', 'postsMois');
    }
}
