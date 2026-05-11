<?php
declare(strict_types=1);

namespace Models;

use PDO;

class IaConversation extends BaseModel
{
    protected string $table = 'ia_conversations';

    public function getHistorique(int $userId, int $ressourceId, int $limit = 10, ?string $conversationId = null): array
    {
        if ($conversationId !== null) {
            return $this->query(
                "SELECT user_message, ia_response, created_at
                 FROM ia_conversations
                 WHERE user_id = ? AND ressource_id = ? AND conversation_id = ?
                 ORDER BY created_at ASC
                 LIMIT ?",
                [$userId, $ressourceId, $conversationId, $limit]
            )->fetchAll(PDO::FETCH_ASSOC);
        }

        return $this->query(
            "SELECT user_message, ia_response, created_at
             FROM ia_conversations
             WHERE user_id = ? AND ressource_id = ?
             ORDER BY created_at ASC
             LIMIT ?",
            [$userId, $ressourceId, $limit]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function save(int $userId, int $ressourceId, string $question, string $reponse, ?string $conversationId = null): void
    {
        $this->query(
            "INSERT INTO ia_conversations (user_id, ressource_id, conversation_id, user_message, ia_response, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())",
            [$userId, $ressourceId, $conversationId, $question, $reponse]
        );
    }

    public function getCurrentConversationId(int $userId, int $ressourceId): ?string
    {
        $result = $this->query(
            "SELECT conversation_id FROM ia_conversations
             WHERE user_id = ? AND ressource_id = ? AND conversation_id IS NOT NULL
             ORDER BY created_at DESC LIMIT 1",
            [$userId, $ressourceId]
        )->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['conversation_id'] : null;
    }

    public static function generateId(): string
    {
        return bin2hex(random_bytes(16));
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

    public function countToday(int $userId): int
    {
        $result = $this->query(
            "SELECT COUNT(*) AS nb FROM ia_conversations
             WHERE user_id = ? AND DATE(created_at) = CURDATE()",
            [$userId]
        )->fetch(PDO::FETCH_ASSOC);
        return (int) ($result['nb'] ?? 0);
    }
}
