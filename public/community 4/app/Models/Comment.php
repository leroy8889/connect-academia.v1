<?php
declare(strict_types=1);

namespace Models;

use PDO;

class Comment extends BaseModel
{
    /** @var string */
    protected $table = 'comments';

    /** @var bool */
    protected $softDeletes = true;

    public function getByPost(int $postId, int $userId): array
    {
        $comments = $this->query(
            "SELECT c.*, u.nom, u.prenom, u.photo_profil, u.role AS user_role,
                    EXISTS(SELECT 1 FROM likes WHERE comment_id = c.id AND user_id = ?) AS is_liked_by_me
             FROM comments c
             INNER JOIN users u ON c.user_id = u.id
             WHERE c.post_id = ? AND c.parent_id IS NULL AND c.is_deleted = 0
             ORDER BY c.is_best_answer DESC, c.created_at ASC",
            [$userId, $postId]
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($comments as &$comment) {
            $comment['photo_profil'] = Post::normalizePhotoPath($comment['photo_profil'] ?? null);
            $comment['replies'] = [];
        }
        unset($comment);

        if (empty($comments)) {
            return $comments;
        }

        // Récupère toutes les réponses en une seule requête (évite le N+1)
        $commentIds = array_column($comments, 'id');
        $placeholders = implode(',', array_fill(0, count($commentIds), '?'));

        $replies = $this->query(
            "SELECT c.*, u.nom, u.prenom, u.photo_profil, u.role AS user_role,
                    EXISTS(SELECT 1 FROM likes WHERE comment_id = c.id AND user_id = ?) AS is_liked_by_me
             FROM comments c
             INNER JOIN users u ON c.user_id = u.id
             WHERE c.parent_id IN ({$placeholders}) AND c.is_deleted = 0
             ORDER BY c.created_at ASC",
            array_merge([$userId], $commentIds)
        )->fetchAll(PDO::FETCH_ASSOC);

        $repliesByParent = [];
        foreach ($replies as &$reply) {
            $reply['photo_profil'] = Post::normalizePhotoPath($reply['photo_profil'] ?? null);
            $repliesByParent[$reply['parent_id']][] = $reply;
        }
        unset($reply);

        foreach ($comments as &$comment) {
            $comment['replies'] = $repliesByParent[$comment['id']] ?? [];
        }
        unset($comment);

        return $comments;
    }

    public function create(array $data): int
    {
        $this->query(
            "INSERT INTO comments (post_id, user_id, parent_id, contenu, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            [
                $data['post_id'],
                $data['user_id'],
                $data['parent_id'] ?? null,
                $data['contenu'],
            ]
        );

        $commentId = $this->lastInsertId();

        // Incrémenter le compteur de commentaires du post
        $this->query(
            "UPDATE posts SET comments_count = comments_count + 1 WHERE id = ?",
            [$data['post_id']]
        );

        return $commentId;
    }

    public function markBestAnswer(int $commentId, int $postId): bool
    {
        // Retirer l'ancien best answer
        $this->query(
            "UPDATE comments SET is_best_answer = 0 WHERE post_id = ? AND is_best_answer = 1",
            [$postId]
        );

        // Marquer le nouveau
        $result = $this->query(
            "UPDATE comments SET is_best_answer = 1 WHERE id = ? AND post_id = ?",
            [$commentId, $postId]
        )->rowCount() > 0;

        // Marquer le post comme résolu
        if ($result) {
            (new Post())->markResolved($postId);
        }

        return $result;
    }

    public function getReplies(int $parentId, int $userId): array
    {
        $replies = $this->query(
            "SELECT c.*, u.nom, u.prenom, u.photo_profil, u.role AS user_role,
                    EXISTS(SELECT 1 FROM likes WHERE comment_id = c.id AND user_id = ?) AS is_liked_by_me
             FROM comments c
             INNER JOIN users u ON c.user_id = u.id
             WHERE c.parent_id = ? AND c.is_deleted = 0
             ORDER BY c.created_at ASC",
            [$userId, $parentId]
        )->fetchAll(PDO::FETCH_ASSOC);
        
        // Normaliser les photos de profil
        foreach ($replies as &$reply) {
            $reply['photo_profil'] = Post::normalizePhotoPath($reply['photo_profil'] ?? null);
        }
        unset($reply);
        
        return $replies;
    }

    /**
     * Récupère un commentaire avec les infos utilisateur (avatar, nom, etc.)
     */
    public function findByIdWithUser(int $commentId, int $currentUserId): ?array
    {
        $result = $this->query(
            "SELECT c.*, u.nom, u.prenom, u.photo_profil, u.role AS user_role,
                    EXISTS(SELECT 1 FROM likes WHERE comment_id = c.id AND user_id = ?) AS is_liked_by_me
             FROM comments c
             INNER JOIN users u ON c.user_id = u.id
             WHERE c.id = ? AND c.is_deleted = 0",
            [$currentUserId, $commentId]
        )->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            // Normaliser la photo de profil
            $result['photo_profil'] = Post::normalizePhotoPath($result['photo_profil'] ?? null);
        }

        return $result ?: null;
    }

    public function getByUser(int $userId, int $limit = 20): array
    {
        return $this->query(
            "SELECT c.*, p.contenu AS post_contenu, p.id AS post_id
             FROM comments c
             INNER JOIN posts p ON c.post_id = p.id
             WHERE c.user_id = ? AND c.is_deleted = 0
             ORDER BY c.created_at DESC
             LIMIT ?",
            [$userId, $limit]
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}
