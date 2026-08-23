<?php
declare(strict_types=1);

namespace Services\Orientation;

use Core\Database;

class AuditService
{
    public static function log(
        string $action,
        string $entity,
        int    $entityId,
        ?int   $userId  = null,
        array  $context = []
    ): void {
        try {
            $db = Database::getInstance()->getConnection();
            $db->prepare('
                INSERT INTO audit_log
                  (action, entity, entity_id, user_id, context, ip, created_at)
                VALUES
                  (:action, :entity, :entity_id, :user_id, :context, :ip, NOW())
            ')->execute([
                ':action'    => $action,
                ':entity'    => $entity,
                ':entity_id' => $entityId,
                ':user_id'   => $userId,
                ':context'   => $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : null,
                ':ip'        => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (\Throwable $e) {
            error_log('[AuditService] Échec log : ' . $e->getMessage());
        }
    }
}
