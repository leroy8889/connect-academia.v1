<?php
declare(strict_types=1);

namespace Models;

use PDO;

class Follow extends BaseModel
{
    protected string $table = 'follows';

    public function toggle(int $followerId, int $followedId): array
    {
        if ($followerId === $followedId) {
            throw new \RuntimeException("Impossible de se suivre soi-même");
        }

        $existing = $this->query(
            "SELECT id FROM follows WHERE follower_id = ? AND followed_id = ?",
            [$followerId, $followedId]
        )->fetch(PDO::FETCH_ASSOC);

        $userModel = new User();

        if ($existing) {
            $this->query("DELETE FROM follows WHERE id = ?", [$existing['id']]);
            $userModel->decrementCount($followerId, 'following_count');
            $userModel->decrementCount($followedId, 'followers_count');
            return ['following' => false];
        }

        $this->query(
            "INSERT INTO follows (follower_id, followed_id, created_at) VALUES (?, ?, NOW())",
            [$followerId, $followedId]
        );
        $userModel->incrementCount($followerId, 'following_count');
        $userModel->incrementCount($followedId, 'followers_count');
        return ['following' => true];
    }

    public function isFollowing(int $followerId, int $followedId): bool
    {
        return (bool) $this->query(
            "SELECT 1 FROM follows WHERE follower_id = ? AND followed_id = ?",
            [$followerId, $followedId]
        )->fetch(PDO::FETCH_ASSOC);
    }

    public function getFollowers(int $userId, int $limit = 50): array
    {
        return $this->query(
            "SELECT u.id, u.nom, u.prenom, u.photo_profil, u.role
             FROM follows f INNER JOIN users u ON f.follower_id = u.id
             WHERE f.followed_id = ? AND u.is_deleted = 0
             ORDER BY f.created_at DESC LIMIT ?",
            [$userId, $limit]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFollowing(int $userId, int $limit = 50): array
    {
        return $this->query(
            "SELECT u.id, u.nom, u.prenom, u.photo_profil, u.role
             FROM follows f INNER JOIN users u ON f.followed_id = u.id
             WHERE f.follower_id = ? AND u.is_deleted = 0
             ORDER BY f.created_at DESC LIMIT ?",
            [$userId, $limit]
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}
