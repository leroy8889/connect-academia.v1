<?php
declare(strict_types=1);

namespace Models;

use PDO;

class Notification extends BaseModel
{
    protected string $table = 'notifications';

    public function getForUser(int $userId, int $limit = 30): array
    {
        $notifs = $this->query(
            "SELECT n.*, a.nom AS actor_nom, a.prenom AS actor_prenom, a.photo_profil AS actor_photo
             FROM notifications n
             LEFT JOIN users a ON n.actor_id = a.id
             WHERE n.user_id = ?
             ORDER BY n.created_at DESC
             LIMIT ?",
            [$userId, $limit]
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($notifs as &$notif) {
            $notif['actor_photo'] = User::normalizePhotoPath($notif['actor_photo'] ?? null);
        }
        unset($notif);
        return $notifs;
    }

    public function getUnreadCount(int $userId): int
    {
        $result = $this->query(
            "SELECT COUNT(*) AS count FROM notifications WHERE user_id = ? AND is_read = 0",
            [$userId]
        )->fetch(PDO::FETCH_ASSOC);
        return (int) ($result['count'] ?? 0);
    }

    public function create(array $data): int
    {
        if (($data['user_id'] ?? 0) === ($data['actor_id'] ?? 0)) return 0;

        $this->query(
            "INSERT INTO notifications (user_id, actor_id, type, message, link, post_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [
                $data['user_id'],
                $data['actor_id'] ?? null,
                $data['type'],
                $data['message'],
                $data['link'] ?? null,
                $data['post_id'] ?? null,
            ]
        );
        return $this->lastInsertId();
    }

    public function markAsRead(int $id, int $userId): void
    {
        $this->query(
            "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );
    }

    public function markAllAsRead(int $userId): void
    {
        $this->query("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0", [$userId]);
    }
}
