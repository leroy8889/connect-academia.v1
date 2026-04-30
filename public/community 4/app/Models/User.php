<?php
declare(strict_types=1);

namespace Models;

use PDO;

class User extends BaseModel
{
    protected $table = 'users';

    protected $softDeletes = true;

    public function findByEmail(string $email)
    {
        return $this->query(
            "SELECT * FROM users WHERE email = ? AND is_deleted = 0",
            [$email]
        )->fetch(PDO::FETCH_ASSOC);
    }

    public function findByUuid(string $uuid)
    {
        return $this->query(
            "SELECT * FROM users WHERE uuid = ? AND is_deleted = 0",
            [$uuid]
        )->fetch(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $uuid = $this->generateUuid();

        $passwordHash = password_hash(
            $data['password'],
            PASSWORD_BCRYPT,
            ['cost' => 12]
        );

        $emailToken = bin2hex(random_bytes(32));

        $this->query(
            "INSERT INTO users
            (uuid, nom, prenom, email, password_hash, role, classe, email_token)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $uuid,
                $data['nom'],
                $data['prenom'],
                $data['email'],
                $passwordHash,
                $data['role'] ?? 'eleve',
                $data['classe'] ?? null,
                $emailToken
            ]
        );

        return (int) $this->lastInsertId();
    }

    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function updateLastLogin(int $id): void
    {
        $this->query(
            "UPDATE users SET last_login = NOW() WHERE id = ?",
            [$id]
        );
    }

    /**
     * Récupère des suggestions d'utilisateurs à suivre
     * 
     * @param int $userId L'ID de l'utilisateur actuel
     * @param string|null $userClasse La classe de l'utilisateur actuel (optionnel)
     * @param int $limit Nombre de suggestions à retourner
     * @return array Liste des utilisateurs suggérés
     */
    public function getSuggestions(int $userId, ?string $userClasse = null, int $limit = 5): array
    {
        // Construire la requête pour exclure l'utilisateur actuel et ceux déjà suivis
        // Utilisation de LEFT JOIN pour éviter les problèmes avec NOT IN sur des résultats vides
        $sql = "SELECT u.id, u.nom, u.prenom, u.photo_profil, u.role, u.classe, u.matiere, u.followers_count
                FROM users u
                LEFT JOIN follows f ON f.followed_id = u.id AND f.follower_id = ?
                WHERE u.id != ?
                  AND u.is_deleted = 0
                  AND u.is_active = 1
                  AND f.id IS NULL";

        $params = [$userId, $userId];

        // Si l'utilisateur a une classe, prioriser les utilisateurs de la même classe
        if (!empty($userClasse)) {
            $sql .= " ORDER BY 
                        CASE WHEN u.classe = ? THEN 0 ELSE 1 END,
                        u.followers_count DESC,
                        u.created_at DESC";
            $params[] = $userClasse;
        } else {
            $sql .= " ORDER BY u.followers_count DESC, u.created_at DESC";
        }

        $sql .= " LIMIT ?";
        $params[] = $limit;

        return $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Incrémente un compteur numérique pour un utilisateur
     * 
     * @param int $userId L'ID de l'utilisateur
     * @param string $field Le nom du champ à incrémenter (ex: 'posts_count', 'followers_count')
     * @return bool True si la mise à jour a réussi
     */
    public function incrementCount(int $userId, string $field): bool
    {
        // Valider que le champ est un compteur valide
        $allowedFields = ['posts_count', 'followers_count', 'following_count', 'comments_count'];
        if (!in_array($field, $allowedFields, true)) {
            throw new \InvalidArgumentException("Champ non autorisé : {$field}");
        }

        return $this->query(
            "UPDATE users SET {$field} = {$field} + 1, updated_at = NOW() WHERE id = ?",
            [$userId]
        )->rowCount() > 0;
    }

    /**
     * Décrémente un compteur numérique pour un utilisateur
     * 
     * @param int $userId L'ID de l'utilisateur
     * @param string $field Le nom du champ à décrémenter (ex: 'posts_count', 'followers_count')
     * @return bool True si la mise à jour a réussi
     */
    public function decrementCount(int $userId, string $field): bool
    {
        // Valider que le champ est un compteur valide
        $allowedFields = ['posts_count', 'followers_count', 'following_count', 'comments_count'];
        if (!in_array($field, $allowedFields, true)) {
            throw new \InvalidArgumentException("Champ non autorisé : {$field}");
        }

        return $this->query(
            "UPDATE users SET {$field} = GREATEST({$field} - 1, 0), updated_at = NOW() WHERE id = ?",
            [$userId]
        )->rowCount() > 0;
    }

    /**
     * Met à jour le profil d'un utilisateur
     * 
     * @param int $userId L'ID de l'utilisateur
     * @param array $data Les données à mettre à jour
     * @return bool True si la mise à jour a réussi
     */
    public function updateProfile(int $userId, array $data): bool
    {
        // Liste des champs autorisés pour la mise à jour
        $allowedFields = [
            'nom', 'prenom', 'email', 'photo_profil', 'bio', 
            'classe', 'niveau', 'matiere', 'etablissement'
        ];

        // Filtrer les données pour ne garder que les champs autorisés
        $updateData = array_intersect_key($data, array_flip($allowedFields));

        if (empty($updateData)) {
            return false;
        }

        // Construire la requête SQL dynamiquement
        $fields = [];
        $params = [];

        foreach ($updateData as $field => $value) {
            $fields[] = "{$field} = ?";
            $params[] = $value;
        }

        $params[] = $userId;

        $sql = "UPDATE users SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ? AND is_deleted = 0";

        return $this->query($sql, $params)->rowCount() > 0;
    }

    public function search(string $query, int $limit = 20): array
    {
        $like = '%' . $query . '%';
        $users = $this->query(
            "SELECT id, nom, prenom, photo_profil, role, classe, matiere, followers_count
             FROM users
             WHERE is_deleted = 0 AND is_active = 1
               AND (nom LIKE ? OR prenom LIKE ? OR email LIKE ?)
             ORDER BY followers_count DESC
             LIMIT ?",
            [$like, $like, $like, $limit]
        )->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($users as &$user) {
            $user['photo_profil'] = \Models\Post::normalizePhotoPath($user['photo_profil'] ?? null);
        }
        unset($user);

        return $users;
    }

    public function findByEmailToken(string $token): ?array
    {
        $result = $this->query(
            "SELECT id FROM users WHERE email_token = ? AND is_deleted = 0",
            [$token]
        )->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function markEmailVerified(int $userId): void
    {
        $this->query(
            "UPDATE users SET is_verified = 1, email_token = NULL WHERE id = ?",
            [$userId]
        );
    }

    private function generateUuid(): string
    {
        $data = random_bytes(16);

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf(
            '%s%s-%s-%s-%s-%s%s%s',
            str_split(bin2hex($data), 4)
        );
    }
}