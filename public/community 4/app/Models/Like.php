<?php
declare(strict_types=1);

namespace Models;

use PDO;

class Like extends BaseModel
{
    /** @var string */
    protected $table = 'likes';

    public function togglePostLike(int $userId, int $postId): array
    {
        $existing = $this->query(
            "SELECT id FROM likes WHERE user_id = ? AND post_id = ?",
            [$userId, $postId]
        )->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Unlike
            $this->query("DELETE FROM likes WHERE id = ?", [$existing['id']]);
            $this->query("UPDATE posts SET likes_count = GREATEST(likes_count - 1, 0) WHERE id = ?", [$postId]);
            $liked = false;
        } else {
            // Like
            $this->query(
                "INSERT INTO likes (user_id, post_id, created_at) VALUES (?, ?, NOW())",
                [$userId, $postId]
            );
            $this->query("UPDATE posts SET likes_count = likes_count + 1 WHERE id = ?", [$postId]);
            $liked = true;
        }

        // Récupérer le nouveau compteur
        $post = $this->query("SELECT likes_count FROM posts WHERE id = ?", [$postId])->fetch(PDO::FETCH_ASSOC);

        return [
            'liked'       => $liked,
            'likes_count' => (int) $post['likes_count'],
        ];
    }

    public function toggleCommentLike(int $userId, int $commentId): array
    {
        $existing = $this->query(
            "SELECT id FROM likes WHERE user_id = ? AND comment_id = ?",
            [$userId, $commentId]
        )->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $this->query("DELETE FROM likes WHERE id = ?", [$existing['id']]);
            $this->query("UPDATE comments SET likes_count = GREATEST(likes_count - 1, 0) WHERE id = ?", [$commentId]);
            $liked = false;
        } else {
            $this->query(
                "INSERT INTO likes (user_id, comment_id, created_at) VALUES (?, ?, NOW())",
                [$userId, $commentId]
            );
            $this->query("UPDATE comments SET likes_count = likes_count + 1 WHERE id = ?", [$commentId]);
            $liked = true;
        }

        $comment = $this->query("SELECT likes_count FROM comments WHERE id = ?", [$commentId])->fetch(PDO::FETCH_ASSOC);

        return [
            'liked'       => $liked,
            'likes_count' => (int) $comment['likes_count'],
        ];
    }
}
