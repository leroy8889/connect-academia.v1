<?php
declare(strict_types=1);

namespace Models;

use PDO;

class Bookmark extends BaseModel
{
    protected string $table = 'bookmarks';

    public function toggle(int $userId, int $postId): array
    {
        $existing = $this->query(
            "SELECT id FROM bookmarks WHERE user_id = ? AND post_id = ?",
            [$userId, $postId]
        )->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $this->query("DELETE FROM bookmarks WHERE id = ?", [$existing['id']]);
            return ['bookmarked' => false];
        }

        $this->query("INSERT INTO bookmarks (user_id, post_id, created_at) VALUES (?, ?, NOW())", [$userId, $postId]);
        return ['bookmarked' => true];
    }

    public function getByUser(int $userId, int $limit = 20): array
    {
        $posts = $this->query(
            "SELECT p.*, u.nom, u.prenom, u.photo_profil, u.role AS user_role
             FROM bookmarks b
             INNER JOIN posts p ON b.post_id = p.id
             INNER JOIN users u ON p.user_id = u.id
             WHERE b.user_id = ? AND p.is_deleted = 0
             ORDER BY b.created_at DESC LIMIT ?",
            [$userId, $limit]
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($posts as &$post) {
            $post['image']       = Post::normalizeImagePath($post['image'] ?? null);
            $post['photo_profil'] = User::normalizePhotoPath($post['photo_profil'] ?? null);
        }
        unset($post);
        return $posts;
    }
}
