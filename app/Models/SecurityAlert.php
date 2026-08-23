<?php
declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * Accès à la table `security_alerts` (cf. migration_security_alerts.sql).
 * Méthodes statiques : le moniteur comme le back-office y accèdent sans état.
 */
class SecurityAlert
{
    private static function db(): PDO
    {
        return Database::getInstance()->getConnection();
    }

    /**
     * Enregistre une alerte, ou regroupe avec une alerte identique récente
     * (même type + IP, encore « nouveau », dans la fenêtre de déduplication).
     *
     * @return bool  true si une NOUVELLE ligne a été insérée, false si une
     *               alerte existante a simplement été incrémentée.
     */
    public static function recordOrTouch(array $d, int $dedupeMinutes = 30): bool
    {
        $db = self::db();

        // La fenêtre est un entier casté (jamais une donnée utilisateur) : on
        // l'inline dans INTERVAL, car les prepared natifs (EMULATE_PREPARES=false)
        // refusent un placeholder à cet emplacement.
        $mins = max(1, (int) $dedupeMinutes);
        $existing = $db->prepare(
            "SELECT id FROM security_alerts
             WHERE type = :type AND ip <=> :ip AND statut = 'nouveau'
               AND last_seen_at >= (NOW() - INTERVAL {$mins} MINUTE)
             ORDER BY id DESC LIMIT 1"
        );
        $existing->bindValue(':type', $d['type']);
        $existing->bindValue(':ip', $d['ip']);
        $existing->execute();
        $row = $existing->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $upd = $db->prepare(
                'UPDATE security_alerts
                    SET occurrences = occurrences + 1, last_seen_at = NOW()
                  WHERE id = ?'
            );
            $upd->execute([(int) $row['id']]);
            return false;
        }

        $ins = $db->prepare(
            'INSERT INTO security_alerts
                (type, severity, ip, user_agent, method, uri, message, context, created_at, last_seen_at)
             VALUES
                (:type, :severity, :ip, :ua, :method, :uri, :message, :context, NOW(), NOW())'
        );
        $ins->execute([
            ':type'     => $d['type'],
            ':severity' => $d['severity'] ?? 'moyenne',
            ':ip'       => $d['ip'] ?? null,
            ':ua'       => $d['user_agent'] ?? null,
            ':method'   => $d['method'] ?? null,
            ':uri'      => $d['uri'] ?? null,
            ':message'  => $d['message'] ?? '',
            ':context'  => !empty($d['context']) ? json_encode($d['context'], JSON_UNESCAPED_UNICODE) : null,
        ]);
        return true;
    }

    public static function countByStatut(string $statut): int
    {
        $st = self::db()->prepare('SELECT COUNT(*) FROM security_alerts WHERE statut = ?');
        $st->execute([$statut]);
        return (int) $st->fetchColumn();
    }

    /**
     * Liste paginée filtrable.
     *
     * @param array{severity?:string,statut?:string,type?:string} $filters
     */
    public static function paginate(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where  = [];
        $params = [];
        foreach (['severity', 'statut', 'type'] as $col) {
            if (!empty($filters[$col])) {
                $where[]        = "$col = :$col";
                $params[":$col"] = $filters[$col];
            }
        }
        $sql = 'SELECT * FROM security_alerts';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY last_seen_at DESC, id DESC LIMIT :limit OFFSET :offset';

        $st = self::db()->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
        $st->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Statistiques d'en-tête (compteurs). */
    public static function stats(): array
    {
        $db = self::db();
        $bySev = $db->query(
            'SELECT severity, COUNT(*) c FROM security_alerts GROUP BY severity'
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        $nouveau = self::countByStatut('nouveau');
        $last24  = (int) $db->query(
            'SELECT COUNT(*) FROM security_alerts WHERE created_at >= (NOW() - INTERVAL 24 HOUR)'
        )->fetchColumn();
        $total = (int) $db->query('SELECT COUNT(*) FROM security_alerts')->fetchColumn();

        return [
            'nouveau'  => $nouveau,
            'last24'   => $last24,
            'total'    => $total,
            'critique' => (int) ($bySev['critique'] ?? 0),
            'elevee'   => (int) ($bySev['elevee'] ?? 0),
            'moyenne'  => (int) ($bySev['moyenne'] ?? 0),
            'faible'   => (int) ($bySev['faible'] ?? 0),
        ];
    }

    public static function updateStatut(int $id, string $statut): bool
    {
        if (!in_array($statut, ['nouveau', 'vu', 'resolu'], true)) {
            return false;
        }
        $st = self::db()->prepare('UPDATE security_alerts SET statut = ? WHERE id = ?');
        return $st->execute([$statut, $id]);
    }

    /** Passe toutes les alertes « nouveau » à « vu ». */
    public static function markAllSeen(): int
    {
        $st = self::db()->prepare('UPDATE security_alerts SET statut = "vu" WHERE statut = "nouveau"');
        $st->execute();
        return $st->rowCount();
    }

    /** Purge les alertes résolues. */
    public static function clearResolved(): int
    {
        $st = self::db()->prepare('DELETE FROM security_alerts WHERE statut = "resolu"');
        $st->execute();
        return $st->rowCount();
    }
}
