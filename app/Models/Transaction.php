<?php
declare(strict_types=1);

namespace Models;

use PDO;

class Transaction extends BaseModel
{
    protected string $table = 'transactions';

    public function creer(int $userId, string $plan, float $montant, string $reference, ?string $mfToken = null): int
    {
        $this->query(
            "INSERT INTO transactions (user_id, plan, montant, devise, reference, statut, aggregateur_ref, created_at, updated_at)
             VALUES (?, ?, ?, 'XAF', ?, 'en_attente', ?, NOW(), NOW())",
            [$userId, $plan, $montant, $reference, $mfToken]
        );
        return $this->lastInsertId();
    }

    public function findById(int $id): array|false
    {
        return $this->query(
            "SELECT * FROM transactions WHERE id = ? LIMIT 1",
            [$id]
        )->fetch(PDO::FETCH_ASSOC);
    }

    public function findByReference(string $reference): array|false
    {
        return $this->query(
            "SELECT * FROM transactions WHERE reference = ? LIMIT 1",
            [$reference]
        )->fetch(PDO::FETCH_ASSOC);
    }

    public function findByAgregateurRef(string $ref): array|false
    {
        return $this->query(
            "SELECT * FROM transactions WHERE aggregateur_ref = ? LIMIT 1",
            [$ref]
        )->fetch(PDO::FETCH_ASSOC);
    }

    public function findEnAttente(int $userId): array|false
    {
        return $this->query(
            "SELECT * FROM transactions WHERE user_id = ? AND statut = 'en_attente' ORDER BY created_at DESC LIMIT 1",
            [$userId]
        )->fetch(PDO::FETCH_ASSOC);
    }

    public function getAllEnAttente(int $maxAgeMinutes = 120): array
    {
        return $this->query(
            "SELECT * FROM transactions WHERE statut = 'en_attente' AND aggregateur_ref IS NOT NULL AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE) ORDER BY created_at DESC",
            [$maxAgeMinutes]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function mettreAJour(int $id, string $statut, ?string $agregRef = null, ?string $webhookPayload = null, ?string $methodePaiement = null): void
    {
        $this->query(
            "UPDATE transactions SET statut = ?, aggregateur_ref = ?, webhook_payload = ?, methode_paiement = ?, updated_at = NOW() WHERE id = ?",
            [$statut, $agregRef, $webhookPayload, $methodePaiement, $id]
        );
    }

    public function getByUser(int $userId, int $limit = 20): array
    {
        return $this->query(
            "SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT ?",
            [$userId, $limit]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAll(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['statut'])) {
            $where[]  = 't.statut = ?';
            $params[] = $filters['statut'];
        }
        if (!empty($filters['plan'])) {
            $where[]  = 't.plan = ?';
            $params[] = $filters['plan'];
        }
        if (!empty($filters['date_debut'])) {
            $where[]  = 'DATE(t.created_at) >= ?';
            $params[] = $filters['date_debut'];
        }
        if (!empty($filters['date_fin'])) {
            $where[]  = 'DATE(t.created_at) <= ?';
            $params[] = $filters['date_fin'];
        }
        if (!empty($filters['q'])) {
            $like     = '%' . $filters['q'] . '%';
            $where[]  = '(u.email LIKE ? OR u.nom LIKE ? OR u.prenom LIKE ? OR t.reference LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $params[] = $limit;
        $params[] = $offset;

        return $this->query(
            "SELECT t.*, u.nom, u.prenom, u.email
             FROM transactions t
             JOIN users u ON u.id = t.user_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY t.created_at DESC
             LIMIT ? OFFSET ?",
            $params
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countAll(array $filters = []): int
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['statut'])) {
            $where[]  = 't.statut = ?';
            $params[] = $filters['statut'];
        }
        if (!empty($filters['plan'])) {
            $where[]  = 't.plan = ?';
            $params[] = $filters['plan'];
        }
        if (!empty($filters['date_debut'])) {
            $where[]  = 'DATE(t.created_at) >= ?';
            $params[] = $filters['date_debut'];
        }
        if (!empty($filters['date_fin'])) {
            $where[]  = 'DATE(t.created_at) <= ?';
            $params[] = $filters['date_fin'];
        }
        if (!empty($filters['q'])) {
            $like     = '%' . $filters['q'] . '%';
            $where[]  = '(u.email LIKE ? OR u.nom LIKE ? OR u.prenom LIKE ? OR t.reference LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        return (int) $this->query(
            "SELECT COUNT(*) FROM transactions t JOIN users u ON u.id = t.user_id WHERE " . implode(' AND ', $where),
            $params
        )->fetchColumn();
    }

    public function totalCA(?string $periode = null): float
    {
        $where  = ["statut = 'succes'"];
        $params = [];

        if ($periode === '7j') {
            $where[] = 'created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
        } elseif ($periode === '30j') {
            $where[] = 'created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
        }

        $result = $this->query(
            "SELECT COALESCE(SUM(montant), 0) FROM transactions WHERE " . implode(' AND ', $where),
            $params
        )->fetchColumn();

        return (float) $result;
    }

    public function countByStatut(): array
    {
        $rows = $this->query(
            "SELECT statut, COUNT(*) AS nb FROM transactions GROUP BY statut"
        )->fetchAll(PDO::FETCH_ASSOC);

        $counts = ['en_attente' => 0, 'succes' => 0, 'echec' => 0, 'rembourse' => 0, 'total' => 0];
        foreach ($rows as $row) {
            $counts[$row['statut']] = (int) $row['nb'];
            $counts['total'] += (int) $row['nb'];
        }
        return $counts;
    }
}
