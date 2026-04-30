<?php
declare(strict_types=1);

namespace Models;

use PDO;

class Post extends BaseModel
{
    /** @var string */
    protected $table = 'posts';

    /** @var bool */
    protected $softDeletes = true;

    /**
     * Normalise le chemin de l'image pour qu'il soit accessible depuis le frontend
     * Prend en compte BASE_URL si l'application est dans un sous-répertoire
     * 
     * @param string|null $imagePath Le chemin de l'image depuis la base de données
     * @return string|null Le chemin normalisé avec BASE_URL ou null si pas d'image
     */
    public static function normalizeImagePath(?string $imagePath): ?string
    {
        if (empty($imagePath)) {
            return null;
        }

        // Si le chemin commence déjà par http:// ou https://, on le retourne tel quel
        if (str_starts_with($imagePath, 'http://') || str_starts_with($imagePath, 'https://')) {
            return $imagePath;
        }

        // Si le chemin ne commence pas par /public/uploads/posts/, 
        // c'est probablement juste le nom du fichier, on complète le chemin
        if (!str_starts_with($imagePath, '/public/uploads/posts') && !str_starts_with($imagePath, 'public/uploads/posts')) {
            $imagePath = '/public/uploads/posts/' . ltrim($imagePath, '/');
        }

        // S'assurer que le chemin commence par /
        if (!str_starts_with($imagePath, '/')) {
            $imagePath = '/' . $imagePath;
        }

        // Ajouter BASE_URL si défini et non vide (pour les sous-répertoires)
        if (defined('BASE_URL') && BASE_URL !== '') {
            return BASE_URL . $imagePath;
        }

        return $imagePath;
    }

    /**
     * Normalise le chemin de la photo de profil pour qu'il soit accessible depuis le frontend
     * Prend en compte BASE_URL si l'application est dans un sous-répertoire
     * 
     * @param string|null $photoPath Le chemin de la photo de profil depuis la base de données
     * @return string|null Le chemin normalisé avec BASE_URL ou null si pas de photo
     */
    public static function normalizePhotoPath(?string $photoPath): ?string
    {
        if (empty($photoPath)) {
            return null;
        }

        // Si le chemin commence déjà par http:// ou https://, on le retourne tel quel
        if (str_starts_with($photoPath, 'http://') || str_starts_with($photoPath, 'https://')) {
            return $photoPath;
        }

        // Si le chemin ne commence pas par /public/uploads/avatars/, 
        // c'est probablement juste le nom du fichier, on complète le chemin
        if (!str_starts_with($photoPath, '/public/uploads/avatars') && !str_starts_with($photoPath, 'public/uploads/avatars')) {
            $photoPath = '/public/uploads/avatars/' . ltrim($photoPath, '/');
        }

        // S'assurer que le chemin commence par /
        if (!str_starts_with($photoPath, '/')) {
            $photoPath = '/' . $photoPath;
        }

        // Ajouter BASE_URL si défini et non vide (pour les sous-répertoires)
        if (defined('BASE_URL') && BASE_URL !== '') {
            return BASE_URL . $photoPath;
        }

        return $photoPath;
    }

    /**
     * @param int $userId
     * @param string|null $userClasse
     * @param array $filters
     * @param int|null $beforeId
     * @param int|null $afterId
     * @param int $limit
     * @return array
     */
    public function getFeed(int $userId, $userClasse, array $filters = [], $beforeId = null, $afterId = null, int $limit = 10): array
    {
        $params = [];
        $conditions = ['p.is_deleted = 0'];

        // Filtre par type
        if (!empty($filters['type'])) {
            $conditions[] = 'p.type = ?';
            $params[] = $filters['type'];
        }

        // Filtre par matière
        if (!empty($filters['matiere'])) {
            $conditions[] = 'p.matiere_tag = ?';
            $params[] = $filters['matiere'];
        }

        // Filtre par classe
        if (!empty($filters['classe'])) {
            $conditions[] = 'p.classe_tag = ?';
            $params[] = $filters['classe'];
        }

        // Pagination cursor-based
        if ($beforeId) {
            $conditions[] = 'p.id < ?';
            $params[] = $beforeId;
        }
        if ($afterId) {
            $conditions[] = 'p.id > ?';
            $params[] = $afterId;
        }

        $where = implode(' AND ', $conditions);

        // Requête du feed avec score de pertinence
        $sql = "
            SELECT
                p.*,
                u.nom, u.prenom, u.photo_profil, u.role AS user_role, u.classe AS user_classe, u.matiere AS user_matiere,
                EXISTS(
                    SELECT 1 FROM likes WHERE post_id = p.id AND user_id = ?
                ) AS is_liked_by_me,
                EXISTS(
                    SELECT 1 FROM bookmarks WHERE post_id = p.id AND user_id = ?
                ) AS is_bookmarked_by_me,
                (
                    CAST(p.likes_count AS SIGNED) * 2 +
                    CAST(p.comments_count AS SIGNED) * 3 +
                    CAST((SELECT COUNT(*) FROM follows WHERE follower_id = ? AND followed_id = p.user_id) AS SIGNED) * 10 +
                    IF(u.classe = ?, 5, 0) +
                    IF(p.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY), -20, 0)
                ) AS relevance_score
            FROM posts p
            INNER JOIN users u ON p.user_id = u.id
            WHERE {$where}
            ORDER BY p.is_pinned DESC, p.created_at DESC, relevance_score DESC
            LIMIT ?
        ";

        array_unshift($params, $userId, $userId, $userId, $userClasse ?? '');
        $params[] = $limit + 1;

        $posts = $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

        $hasMore = count($posts) > $limit;
        if ($hasMore) {
            array_pop($posts);
        }

        // Normaliser les chemins d'images et photos de profil pour tous les posts
        foreach ($posts as &$post) {
            $post['image'] = self::normalizeImagePath($post['image'] ?? null);
            $post['photo_profil'] = self::normalizePhotoPath($post['photo_profil'] ?? null);
        }
        unset($post);

        return [
            'posts'    => $posts,
            'has_more' => $hasMore,
        ];
    }

    public function getNewPostsCount(int $afterId): int
    {
        $stmt = $this->query(
            "SELECT COUNT(*) as count FROM posts WHERE id > ? AND is_deleted = 0",
            [$afterId]
        );
        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }

    public function getNewPosts(int $afterId, int $limit = 10): array
    {
        $posts = $this->query(
            "SELECT p.*, u.nom, u.prenom, u.photo_profil, u.role AS user_role, u.classe AS user_classe
             FROM posts p
             INNER JOIN users u ON p.user_id = u.id
             WHERE p.id > ? AND p.is_deleted = 0
             ORDER BY p.created_at DESC
             LIMIT ?",
            [$afterId, $limit]
        )->fetchAll(PDO::FETCH_ASSOC);

        // Normaliser les chemins d'images et photos de profil pour tous les posts
        foreach ($posts as &$post) {
            $post['image'] = self::normalizeImagePath($post['image'] ?? null);
            $post['photo_profil'] = self::normalizePhotoPath($post['photo_profil'] ?? null);
        }
        unset($post);

        return $posts;
    }

    /**
     * Récupère un post avec les infos utilisateur (pour l'affichage immédiat après création)
     */
    public function findByIdWithUser(int $postId, int $currentUserId): ?array
    {
        $sql = "
            SELECT
                p.*,
                u.nom, u.prenom, u.photo_profil, u.role AS user_role, u.classe AS user_classe, u.matiere AS user_matiere,
                EXISTS(
                    SELECT 1 FROM likes WHERE post_id = p.id AND user_id = ?
                ) AS is_liked_by_me,
                EXISTS(
                    SELECT 1 FROM bookmarks WHERE post_id = p.id AND user_id = ?
                ) AS is_bookmarked_by_me
            FROM posts p
            INNER JOIN users u ON p.user_id = u.id
            WHERE p.id = ? AND p.is_deleted = 0
        ";

        $result = $this->query($sql, [$currentUserId, $currentUserId, $postId])->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            // Normaliser le chemin de l'image et de la photo de profil
            $result['image'] = self::normalizeImagePath($result['image'] ?? null);
            $result['photo_profil'] = self::normalizePhotoPath($result['photo_profil'] ?? null);
        }
        return $result ?: null;
    }

    public function create(array $data): int
    {
        $this->query(
            "INSERT INTO posts (user_id, type, contenu, image, matiere_tag, classe_tag, hashtags, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $data['user_id'],
                $data['type'] ?? 'partage',
                $data['contenu'],
                $data['image'] ?? null,
                $data['matiere_tag'] ?? null,
                $data['classe_tag'] ?? null,
                $data['hashtags'] ?? null,
            ]
        );

        $postId = $this->lastInsertId();

        // Incrémenter le compteur de posts de l'utilisateur
        (new User())->incrementCount((int) $data['user_id'], 'posts_count');

        return $postId;
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [];

        $allowedFields = ['contenu', 'image', 'matiere_tag', 'classe_tag', 'hashtags', 'type'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

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

        // Normaliser les chemins d'images et photos de profil pour tous les posts
        foreach ($posts as &$post) {
            $post['image'] = self::normalizeImagePath($post['image'] ?? null);
            $post['photo_profil'] = self::normalizePhotoPath($post['photo_profil'] ?? null);
        }
        unset($post);

        return $posts;
    }

    public function getTopQuestions(int $limit = 5): array
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
        return $this->query(
            "SELECT hashtags FROM posts
             WHERE is_deleted = 0 AND hashtags IS NOT NULL AND hashtags != ''
               AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
             ORDER BY created_at DESC
             LIMIT 100"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function search(string $query, int $limit = 20): array
    {
        $searchTerm = "%{$query}%";
        $posts = $this->query(
            "SELECT p.*, u.nom, u.prenom, u.photo_profil, u.role AS user_role
             FROM posts p
             INNER JOIN users u ON p.user_id = u.id
             WHERE p.is_deleted = 0
               AND (p.contenu LIKE ? OR p.hashtags LIKE ? OR p.matiere_tag LIKE ?)
             ORDER BY p.created_at DESC
             LIMIT ?",
            [$searchTerm, $searchTerm, $searchTerm, $limit]
        )->fetchAll(PDO::FETCH_ASSOC);

        // Normaliser les chemins d'images et photos de profil pour tous les posts
        foreach ($posts as &$post) {
            $post['image'] = self::normalizeImagePath($post['image'] ?? null);
            $post['photo_profil'] = self::normalizePhotoPath($post['photo_profil'] ?? null);
        }
        unset($post);

        return $posts;
    }

    public function markResolved(int $id): bool
    {
        return $this->query(
            "UPDATE posts SET is_resolved = 1, updated_at = NOW() WHERE id = ?",
            [$id]
        )->rowCount() > 0;
    }
}
