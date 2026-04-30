<?php
declare(strict_types=1);

namespace Models;

use PDO;

class Post extends BaseModel
{
    protected string $table = 'posts';
    protected bool $softDeletes = true;

    public static function normalizeImagePath(?string $path): ?string
    {
        if (empty($path)) return null;
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) return $path;
        if (!str_starts_with($path, '/public/uploads/posts') && !str_starts_with($path, 'public/uploads/posts')) {
            $path = '/public/uploads/posts/' . ltrim($path, '/');
        }
        if (!str_starts_with($path, '/')) $path = '/' . $path;
        return (defined('BASE_URL') && BASE_URL !== '') ? BASE_URL . $path : $path;
    }

    public function getFeed(int $userId, ?int $serieId, array $filters = [], ?int $beforeId = null, int $limit = 10): array
    {
        $params     = [];
        $conditions = ['p.is_deleted = 0'];

        if (!empty($filters['type'])) {
            $conditions[] = 'p.type = ?';
            $params[]     = $filters['type'];
        }
        if (!empty($filters['matiere'])) {
            $conditions[] = 'p.matiere_tag = ?';
            $params[]     = $filters['matiere'];
        }
        if ($beforeId) {
            $conditions[] = 'p.id < ?';
            $params[]     = $beforeId;
        }

        $where = implode(' AND ', $conditions);

        $sql = "
            SELECT p.*,
                   u.nom, u.prenom, u.photo_profil, u.role AS user_role, u.serie_id AS user_serie_id,
                   EXISTS(SELECT 1 FROM likes WHERE post_id = p.id AND user_id = ?) AS is_liked_by_me,
                   EXISTS(SELECT 1 FROM bookmarks WHERE post_id = p.id AND user_id = ?) AS is_bookmarked_by_me,
                   (
                       CAST(p.likes_count AS SIGNED) * 2 +
                       CAST(p.comments_count AS SIGNED) * 3 +
                       CAST((SELECT COUNT(*) FROM follows WHERE follower_id = ? AND followed_id = p.user_id) AS SIGNED) * 10 +
                       IF(u.serie_id = ?, 5, 0) +
                       IF(p.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY), -20, 0)
                   ) AS relevance_score
            FROM posts p
            INNER JOIN users u ON p.user_id = u.id
            WHERE {$where}
            ORDER BY p.is_pinned DESC, p.created_at DESC, relevance_score DESC
            LIMIT ?
        ";

        array_unshift($params, $userId, $userId, $userId, $serieId ?? 0);
        $params[] = $limit + 1;

        $posts   = $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
        $hasMore = count($posts) > $limit;
        if ($hasMore) array_pop($posts);

        foreach ($posts as &$post) {
            $post['image']       = self::normalizeImagePath($post['image'] ?? null);
            $post['photo_profil'] = User::normalizePhotoPath($post['photo_profil'] ?? null);
        }
        unset($post);

        return ['posts' => $posts, 'has_more' => $hasMore];
    }

    public function getNewPosts(int $afterId, int $limit = 10): array
    {
        $posts = $this->query(
            "SELECT p.*, u.nom, u.prenom, u.photo_profil, u.role AS user_role, u.serie_id AS user_serie_id
             FROM posts p
             INNER JOIN users u ON p.user_id = u.id
             WHERE p.id > ? AND p.is_deleted = 0
             ORDER BY p.created_at DESC
             LIMIT ?",
            [$afterId, $limit]
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($posts as &$post) {
            $post['image']       = self::normalizeImagePath($post['image'] ?? null);
            $post['photo_profil'] = User::normalizePhotoPath($post['photo_profil'] ?? null);
        }
        unset($post);
        return $posts;
    }

    public function findByIdWithUser(int $postId, int $currentUserId): ?array
    {
        $sql = "
            SELECT p.*,
                   u.nom, u.prenom, u.photo_profil, u.role AS user_role, u.serie_id AS user_serie_id,
                   EXISTS(SELECT 1 FROM likes WHERE post_id = p.id AND user_id = ?) AS is_liked_by_me,
                   EXISTS(SELECT 1 FROM bookmarks WHERE post_id = p.id AND user_id = ?) AS is_bookmarked_by_me
            FROM posts p
            INNER JOIN users u ON p.user_id = u.id
            WHERE p.id = ? AND p.is_deleted = 0
        ";
        $result = $this->query($sql, [$currentUserId, $currentUserId, $postId])->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $result['image']       = self::normalizeImagePath($result['image'] ?? null);
            $result['photo_profil'] = User::normalizePhotoPath($result['photo_profil'] ?? null);
        }
        return $result ?: null;
    }

    public function create(array $data): int
    {
        $this->query(
            "INSERT INTO posts (user_id, type, contenu, image, matiere_tag, hashtags, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [
                $data['user_id'],
                $data['type'] ?? 'partage',
                $data['contenu'],
                $data['image'] ?? null,
                $data['matiere_tag'] ?? null,
                $data['hashtags'] ?? null,
            ]
        );

        $postId = $this->lastInsertId();
        (new User())->incrementCount((int) $data['user_id'], 'posts_count');
        return $postId;
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [];

        foreach (['contenu', 'image', 'matiere_tag', 'hashtags', 'type'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }
        if (empty($fields)) return false;

        $params[] = $id;
        return $this->query(
            "UPDATE posts SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ? AND is_deleted = 0",
            $params
        )->rowCount() > 0;
    }

    public function getByUser(int $userId, int $limit = 20, int $offset = 0): array
    {
        $posts = $this->query(
            "SELECT p.*, u.nom, u.prenom, u.photo_profil, u.role AS user_role
             FROM posts p
             INNER JOIN users u ON p.user_id = u.id
             WHERE p.user_id = ? AND p.is_deleted = 0
             ORDER BY p.created_at DESC
             LIMIT ? OFFSET ?",
            [$userId, $limit, $offset]
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($posts as &$post) {
            $post['image']       = self::normalizeImagePath($post['image'] ?? null);
            $post['photo_profil'] = User::normalizePhotoPath($post['photo_profil'] ?? null);
        }
        unset($post);
        return $posts;
    }

    public function getTopQuestions(int $limit = 3): array
    {
        return $this->query(
            "SELECT p.id, p.contenu, p.likes_count, p.comments_count
             FROM posts p
             WHERE p.type = 'question' AND p.is_deleted = 0
               AND p.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
             ORDER BY (p.likes_count * 2 + p.comments_count * 3) DESC
             LIMIT ?",
            [$limit]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTrendingHashtags(int $limit = 8): array
    {
        $rows = $this->query(
            "SELECT hashtags FROM posts
             WHERE is_deleted = 0 AND hashtags IS NOT NULL AND hashtags != ''
               AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
             ORDER BY created_at DESC LIMIT 100"
        )->fetchAll(PDO::FETCH_ASSOC);

        $counts = [];
        foreach ($rows as $row) {
            foreach (array_map('trim', explode(',', $row['hashtags'])) as $tag) {
                $tag = ltrim($tag, '#');
                if (!empty($tag)) $counts[$tag] = ($counts[$tag] ?? 0) + 1;
            }
        }
        arsort($counts);
        $result = [];
        foreach (array_slice($counts, 0, $limit, true) as $tag => $count) {
            $result[] = ['tag' => $tag, 'count' => $count];
        }
        return $result;
    }

    public function markResolved(int $id): void
    {
        $this->query("UPDATE posts SET is_resolved = 1, updated_at = NOW() WHERE id = ?", [$id]);
    }

    public function search(string $q, int $limit = 20): array
    {
        $like  = "%{$q}%";
        $posts = $this->query(
            "SELECT p.*, u.nom, u.prenom, u.photo_profil, u.role AS user_role
             FROM posts p INNER JOIN users u ON p.user_id = u.id
             WHERE p.is_deleted = 0 AND (p.contenu LIKE ? OR p.hashtags LIKE ? OR p.matiere_tag LIKE ?)
             ORDER BY p.created_at DESC LIMIT ?",
            [$like, $like, $like, $limit]
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($posts as &$post) {
            $post['image']       = self::normalizeImagePath($post['image'] ?? null);
            $post['photo_profil'] = User::normalizePhotoPath($post['photo_profil'] ?? null);
        }
        unset($post);
        return $posts;
    }
}
