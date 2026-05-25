<?php
declare(strict_types=1);

namespace Models;

use PDO;

class ContactMessage extends BaseModel
{
    protected string $table = 'contact_messages';

    public function create(array $data): int
    {
        $this->query(
            "INSERT INTO contact_messages (nom, prenom, email, telephone, message, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                trim($data['nom']),
                trim($data['prenom']),
                trim($data['email']),
                trim($data['telephone']),
                trim($data['message']),
                $data['ip_address'] ?? null,
            ]
        );
        return $this->lastInsertId();
    }

    public function getAll(int $limit = 50, int $offset = 0): array
    {
        return $this->query(
            "SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT ? OFFSET ?",
            [$limit, $offset]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countTotal(): int
    {
        return $this->count();
    }

    public function countUnread(): int
    {
        return $this->count('is_read = 0');
    }

    public function markRead(int $id): void
    {
        $this->query(
            "UPDATE contact_messages SET is_read = 1, updated_at = NOW() WHERE id = ?",
            [$id]
        );
    }

    public function markAllRead(): void
    {
        $this->query("UPDATE contact_messages SET is_read = 1, updated_at = NOW() WHERE is_read = 0");
    }

    public function delete(int $id): bool
    {
        return $this->query(
            "DELETE FROM contact_messages WHERE id = ?",
            [$id]
        )->rowCount() > 0;
    }
}
