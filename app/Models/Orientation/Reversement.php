<?php
declare(strict_types=1);

namespace Models\Orientation;

use Core\Database;
use PDO;

class Reversement
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function exists(int $paiementId): bool
    {
        $st = $this->db->prepare('SELECT id FROM reversements WHERE paiement_id = ? LIMIT 1');
        $st->execute([$paiementId]);
        return (bool)$st->fetch();
    }

    public function create(array $d): int
    {
        $st = $this->db->prepare('
            INSERT INTO reversements
              (paiement_id, etablissement_id, montant_brut, commission_plateforme, montant_reverse,
               numero_destination, withdraw_mode, statut)
            VALUES
              (:paiement_id, :etablissement_id, :montant_brut, :commission, :montant_reverse,
               :numero, :mode, :statut)
        ');
        $st->execute([
            ':paiement_id'     => $d['paiement_id'],
            ':etablissement_id'=> $d['etablissement_id'],
            ':montant_brut'    => $d['montant_brut'],
            ':commission'      => $d['commission_plateforme'],
            ':montant_reverse' => $d['montant_reverse'],
            ':numero'          => $d['numero_destination'],
            ':mode'            => $d['withdraw_mode'],
            ':statut'          => $d['statut'] ?? 'initie',
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $st = $this->db->prepare('
            SELECT r.*, e.nom AS etab_nom
            FROM reversements r
            JOIN etablissements e ON e.id = r.etablissement_id
            WHERE r.id = ? LIMIT 1
        ');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByToken(string $token): ?array
    {
        $st = $this->db->prepare('SELECT * FROM reversements WHERE token_pay = ? LIMIT 1');
        $st->execute([$token]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateStatut(int $id, string $statut, ?string $token, ?string $erreur): void
    {
        $sql    = 'UPDATE reversements SET statut = ?, message_erreur = ?';
        $params = [$statut, $erreur];
        if ($token !== null) {
            $sql .= ', token_pay = ?';
            $params[] = $token;
        }
        if ($statut === 'reussi') {
            $sql .= ', date_confirmation = NOW()';
        }
        $sql .= ' WHERE id = ?';
        $params[] = $id;
        $this->db->prepare($sql)->execute($params);
    }

    public function incrementTentatives(int $id): void
    {
        $this->db->prepare('UPDATE reversements SET tentatives = tentatives + 1 WHERE id = ?')
            ->execute([$id]);
    }

    public function getARejouer(): array
    {
        return $this->db->query("
            SELECT r.*, e.nom AS etab_nom
            FROM reversements r
            JOIN etablissements e ON e.id = r.etablissement_id
            WHERE r.statut IN ('echoue','a_rejouer')
            ORDER BY r.date_initiation DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAll(int $limit = 100): array
    {
        $st = $this->db->prepare("
            SELECT r.*, e.nom AS etab_nom
            FROM reversements r
            JOIN etablissements e ON e.id = r.etablissement_id
            ORDER BY r.date_initiation DESC
            LIMIT ?
        ");
        $st->execute([$limit]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCompteEtab(int $etabId): ?array
    {
        $st = $this->db->prepare('SELECT * FROM etablissement_paiement WHERE etablissement_id = ? LIMIT 1');
        $st->execute([$etabId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function upsertCompte(int $etabId, array $d): void
    {
        $this->db->prepare('
            INSERT INTO etablissement_paiement
              (etablissement_id, pays_code, withdraw_mode, numero_mobile_money, titulaire, taux_commission, statut)
            VALUES (:etab_id, :pays, :mode, :numero, :titulaire, :taux, :statut)
            ON DUPLICATE KEY UPDATE
              withdraw_mode        = VALUES(withdraw_mode),
              numero_mobile_money  = VALUES(numero_mobile_money),
              titulaire            = VALUES(titulaire),
              taux_commission      = VALUES(taux_commission),
              statut               = VALUES(statut)
        ')->execute([
            ':etab_id'   => $etabId,
            ':pays'      => 'ga',
            ':mode'      => $d['withdraw_mode'],
            ':numero'    => $d['numero_mobile_money'],
            ':titulaire' => $d['titulaire'] ?? null,
            ':taux'      => (float)($d['taux_commission'] ?? $_ENV['COMMISSION_PLATEFORME_PERCENT'] ?? 5),
            ':statut'    => $d['statut'] ?? 'en_attente',
        ]);
    }

    public function activerCompte(int $etabId): void
    {
        $this->db->prepare(
            "UPDATE etablissement_paiement SET statut = 'actif' WHERE etablissement_id = ?"
        )->execute([$etabId]);
    }

    public function countARejouer(): int
    {
        $st = $this->db->query("SELECT COUNT(*) FROM reversements WHERE statut IN ('echoue','a_rejouer')");
        return (int)$st->fetchColumn();
    }
}
