<?php
declare(strict_types=1);

namespace Models;

use PDO;

class IaConversation extends BaseModel
{
    protected string $table = 'ia_conversations';

    public function getHistorique(int $userId, int $ressourceId, int $limit = 10): array
    {
        return $this->query(
            "SELECT user_message, ia_response, created_at
             FROM ia_conversations
             WHERE user_id = ? AND ressource_id = ?
             ORDER BY created_at ASC
             LIMIT ?",
            [$userId, $ressourceId, $limit]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function save(int $userId, int $ressourceId, string $question, string $reponse): void
    {
        $this->query(
            "INSERT INTO ia_conversations (user_id, ressource_id, user_message, ia_response, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            [$userId, $ressourceId, $question, $reponse]
        );
    }

    public function countRecent(int $userId, int $minutes = 1): int
    {
        $result = $this->query(
            "SELECT COUNT(*) AS nb FROM ia_conversations
             WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)",
            [$userId, $minutes]
        )->fetch(PDO::FETCH_ASSOC);
        return (int) ($result['nb'] ?? 0);
    }
}
