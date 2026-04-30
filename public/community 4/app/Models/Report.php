<?php
declare(strict_types=1);

namespace Models;

use PDO;

class Report extends BaseModel
{
    /** @var string */
    protected $table = 'reports';

    public function create(array $data): int
    {
        $existing = $this->query(
            "SELECT id FROM reports
             WHERE reporter_id = ? AND post_id <=> ? AND comment_id <=> ? AND status = 'pending'",
            [
                $data['reporter_id'],
                $data['post_id'] ?? null,
                $data['comment_id'] ?? null,
            ]
        )->fetch(\PDO::FETCH_ASSOC);

        if ($existing) {
            return (int) $existing['id'];
        }

        $this->query(
            "INSERT INTO reports (reporter_id, post_id, comment_id, reason, description, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())",
            [
                $data['reporter_id'],
                $data['post_id'] ?? null,
                $data['comment_id'] ?? null,
                $data['reason'],
                $data['description'] ?? null,
            ]
        );

        return $this->lastInsertId();
    }
}
