<?php
declare(strict_types=1);

namespace Models\Orientation;

use Core\Database;
use PDO;

class PieceRequise
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function findByEtab(int $etabId): array
    {
        $st = $this->db->prepare(
            'SELECT * FROM pieces_justificatives_requises
             WHERE etablissement_id = ?
             ORDER BY obligatoire DESC, ordre ASC, id ASC'
        );
        $st->execute([$etabId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id, int $etabId): ?array
    {
        $st = $this->db->prepare(
            'SELECT * FROM pieces_justificatives_requises WHERE id = ? AND etablissement_id = ? LIMIT 1'
        );
        $st->execute([$id, $etabId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** NON cloisonné (inclut etablissement_id) — télémétrie TenantGuard sur le chemin de refus. */
    public function findByIdAny(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM pieces_justificatives_requises WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(int $etabId, array $d): int
    {
        $st = $this->db->prepare('
            INSERT INTO pieces_justificatives_requises
              (etablissement_id, niveau, libelle, obligatoire, ordre)
            VALUES
              (:etab, :niveau, :libelle, :obligatoire, :ordre)
        ');
        $st->execute([
            ':etab'       => $etabId,
            ':niveau'     => trim($d['niveau'] ?? '') ?: null,
            ':libelle'    => $d['libelle'],
            ':obligatoire'=> (int)(!empty($d['obligatoire'])),
            ':ordre'      => (int)($d['ordre'] ?? 0),
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, int $etabId, array $d): void
    {
        $this->db->prepare('
            UPDATE pieces_justificatives_requises
            SET niveau = :niveau, libelle = :libelle, obligatoire = :obligatoire, ordre = :ordre
            WHERE id = :id AND etablissement_id = :etab
        ')->execute([
            ':niveau'     => trim($d['niveau'] ?? '') ?: null,
            ':libelle'    => $d['libelle'],
            ':obligatoire'=> (int)(!empty($d['obligatoire'])),
            ':ordre'      => (int)($d['ordre'] ?? 0),
            ':id'         => $id,
            ':etab'       => $etabId,
        ]);
    }

    public function delete(int $id, int $etabId): void
    {
        $this->db->prepare(
            'DELETE FROM pieces_justificatives_requises WHERE id = ? AND etablissement_id = ?'
        )->execute([$id, $etabId]);
    }
}
