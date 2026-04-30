<?php
declare(strict_types=1);

namespace Models;

use PDO;

class User extends BaseModel
{
    protected string $table = 'users';
    protected bool $softDeletes = true;

    public function findByEmail(string $email): array|false
    {
        return $this->query(
            "SELECT * FROM users WHERE email = ? AND is_deleted = 0",
            [$email]
        )->fetch(PDO::FETCH_ASSOC);
    }

    public function findByUuid(string $uuid): array|false
    {
        return $this->query(
            "SELECT * FROM users WHERE uuid = ? AND is_deleted = 0",
            [$uuid]
        )->fetch(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $uuid         = $this->generateUuid();
        $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        $emailToken   = bin2hex(random_bytes(32));

        $this->query(
            "INSERT INTO users (uuid, nom, prenom, email, password_hash, serie_id, role, etablissement, email_token)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $uuid,
                $data['nom'],
                $data['prenom'],
                $data['email'],
                $passwordHash,
                $data['serie_id'] ?? null,
                $data['role'] ?? 'eleve',
                $data['etablissement'] ?? null,
                $emailToken,
            ]
        );

        return $this->lastInsertId();
    }

    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function updateLastLogin(int $id): void
    {
        $this->query("UPDATE users SET last_login = NOW() WHERE id = ?", [$id]);
    }

    public static function normalizePhotoPath(?string $path): ?string
    {
        if (empty($path)) return null;
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) return $path;
        if (!str_starts_with($path, '/public/uploads/avatars') && !str_starts_with($path, 'public/uploads/avatars')) {
            $path = '/public/uploads/avatars/' . ltrim($path, '/');
        }
        if (!str_starts_with($path, '/')) $path = '/' . $path;
        return (defined('BASE_URL') && BASE_URL !== '') ? BASE_URL . $path : $path;
    }

    public function updateProfile(int $userId, array $data): bool
    {
        $allowed = ['nom', 'prenom', 'photo_profil', 'bio', 'serie_id', 'etablissement', 'matiere'];
        $fields  = [];
        $params  = [];

        foreach (array_intersect_key($data, array_flip($allowed)) as $field => $value) {
            $fields[] = "{$field} = ?";
            $params[] = $value;
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $userId;
        return $this->query(
            "UPDATE users SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ? AND is_deleted = 0",
            $params
        )->rowCount() > 0;
    }

    public function getSuggestions(int $userId, ?int $serieId = null, int $limit = 5): array
    {
        $params = [$userId, $userId];
        $order  = "u.followers_count DESC, u.created_at DESC";

        if ($serieId) {
            $order  = "CASE WHEN u.serie_id = ? THEN 0 ELSE 1 END, {$order}";
            $params[] = $serieId;
        }

        $params[] = $limit;

        return $this->query(
            "SELECT u.id, u.nom, u.prenom, u.photo_profil, u.role, u.serie_id, u.followers_count
             FROM users u
             LEFT JOIN follows f ON f.followed_id = u.id AND f.follower_id = ?
             WHERE u.id != ? AND u.is_deleted = 0 AND u.is_active = 1 AND f.id IS NULL
             ORDER BY {$order}
             LIMIT ?",
            $params
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function incrementCount(int $userId, string $field): void
    {
        $allowed = ['posts_count', 'followers_count', 'following_count'];
        if (!in_array($field, $allowed, true)) {
            throw new \InvalidArgumentException("Champ non autorisé : {$field}");
        }
        $this->query("UPDATE users SET {$field} = {$field} + 1, updated_at = NOW() WHERE id = ?", [$userId]);
    }

    public function decrementCount(int $userId, string $field): void
    {
        $allowed = ['posts_count', 'followers_count', 'following_count'];
        if (!in_array($field, $allowed, true)) {
            throw new \InvalidArgumentException("Champ non autorisé : {$field}");
        }
        $this->query("UPDATE users SET {$field} = GREATEST({$field} - 1, 0), updated_at = NOW() WHERE id = ?", [$userId]);
    }

    public function search(string $q, int $limit = 20): array
    {
        $like = '%' . $q . '%';
        return $this->query(
            "SELECT id, nom, prenom, photo_profil, role, followers_count
             FROM users WHERE is_deleted = 0 AND is_active = 1
               AND (nom LIKE ? OR prenom LIKE ? OR email LIKE ?)
             ORDER BY followers_count DESC LIMIT ?",
            [$like, $like, $like, $limit]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByEmailToken(string $token): array|false
    {
        $result = $this->query(
            "SELECT id FROM users WHERE email_token = ? AND is_deleted = 0 LIMIT 1",
            [$token]
        )->fetch(PDO::FETCH_ASSOC);
        return $result ?: false;
    }

    public function markEmailVerified(int $userId): void
    {
        $this->query("UPDATE users SET is_verified = 1, email_token = NULL WHERE id = ?", [$userId]);
    }

    private function generateUuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
