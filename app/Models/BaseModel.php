<?php
declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;
use PDOStatement;

class BaseModel
{
    protected PDO $db;
    protected string $table = '';
    protected bool $softDeletes = false;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    protected function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function findById(int $id): array|false
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = ?";
        if ($this->softDeletes) {
            $sql .= " AND is_deleted = 0";
        }
        return $this->query($sql, [$id])->fetch(PDO::FETCH_ASSOC);
    }

    public function findAll(int $limit = 50, int $offset = 0): array
    {
        $where = $this->softDeletes ? 'is_deleted = 0' : '1=1';
        return $this->query(
            "SELECT * FROM {$this->table} WHERE {$where} ORDER BY created_at DESC LIMIT ? OFFSET ?",
            [$limit, $offset]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(string $where = '1=1', array $params = []): int
    {
        return (int) $this->query(
            "SELECT COUNT(*) as total FROM {$this->table} WHERE {$where}",
            $params
        )->fetch(PDO::FETCH_ASSOC)['total'];
    }

    public function softDelete(int $id): bool
    {
        return $this->query(
            "UPDATE {$this->table} SET is_deleted = 1, updated_at = NOW() WHERE id = ?",
            [$id]
        )->rowCount() > 0;
    }

    protected function lastInsertId(): int
    {
        return (int) $this->db->lastInsertId();
    }
}
