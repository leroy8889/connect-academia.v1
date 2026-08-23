<?php
declare(strict_types=1);

namespace Models\Orientation;

use Core\Database;
use PDO;

class NotificationOrientation
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create(array $d): void
    {
        try {
            $this->db->prepare('
                INSERT INTO notifications_orientation
                  (user_id, etablissement_id, type, titre, message, lu, date_creation)
                VALUES
                  (:user_id, :etab_id, :type, :titre, :message, 0, NOW())
            ')->execute([
                ':user_id' => $d['user_id']          ?? null,
                ':etab_id' => $d['etablissement_id'] ?? null,
                ':type'    => $d['type'],
                ':titre'   => $d['titre'],
                ':message' => $d['message']          ?? null,
            ]);
        } catch (\Throwable $e) {
            error_log('[NotifOrientation] Échec insert : ' . $e->getMessage());
        }
    }

    public function findByUser(int $userId, int $limit = 20): array
    {
        $st = $this->db->prepare('
            SELECT n.*, e.nom AS etab_nom
            FROM notifications_orientation n
            LEFT JOIN etablissements e ON e.id = n.etablissement_id
            WHERE n.user_id = ?
            ORDER BY n.date_creation DESC
            LIMIT ?
        ');
        $st->execute([$userId, $limit]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countUnread(int $userId): int
    {
        $st = $this->db->prepare(
            'SELECT COUNT(*) FROM notifications_orientation WHERE user_id = ? AND lu = 0'
        );
        $st->execute([$userId]);
        return (int)$st->fetchColumn();
    }

    public function markRead(int $id, int $userId): void
    {
        $this->db->prepare(
            'UPDATE notifications_orientation SET lu = 1 WHERE id = ? AND user_id = ?'
        )->execute([$id, $userId]);
    }

    public function markAllRead(int $userId): void
    {
        $this->db->prepare(
            'UPDATE notifications_orientation SET lu = 1 WHERE user_id = ? AND lu = 0'
        )->execute([$userId]);
    }

    /**
     * Find gestionnaire user IDs for a given etablissement.
     * Used to push in-app notifications to gestionnaires.
     */
    public function findGestionnaireIds(int $etabId): array
    {
        $st = $this->db->prepare('
            SELECT ea.user_id
            FROM etablissement_admins ea
            JOIN users u ON u.id = ea.user_id AND u.is_active = 1 AND u.is_deleted = 0
            WHERE ea.etablissement_id = ?
        ');
        $st->execute([$etabId]);
        return $st->fetchAll(PDO::FETCH_COLUMN, 0);
    }
}
