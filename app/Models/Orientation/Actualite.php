<?php
declare(strict_types=1);

namespace Models\Orientation;

use Core\Database;
use PDO;

class Actualite
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function findByEtab(int $etabId, int $limit = 20): array
    {
        $st = $this->db->prepare(
            'SELECT * FROM actualites WHERE etablissement_id = ?
             ORDER BY date_publication DESC, id DESC LIMIT ?'
        );
        $st->execute([$etabId, $limit]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id, int $etabId): ?array
    {
        $st = $this->db->prepare(
            'SELECT * FROM actualites WHERE id = ? AND etablissement_id = ? LIMIT 1'
        );
        $st->execute([$id, $etabId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** NON cloisonné (inclut etablissement_id) — télémétrie TenantGuard sur le chemin de refus. */
    public function findByIdAny(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM actualites WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(int $etabId, array $d): int
    {
        $st = $this->db->prepare('
            INSERT INTO actualites
              (etablissement_id, titre, categorie, date_publication, image_cle, extrait, contenu, a_la_une, statut)
            VALUES
              (:etab, :titre, :cat, :date, :img, :extrait, :contenu, :une, :statut)
        ');
        $st->execute([
            ':etab'    => $etabId,
            ':titre'   => $d['titre'],
            ':cat'     => $d['categorie'] ?? null,
            ':date'    => $d['date_publication'] ?: date('Y-m-d'),
            ':img'     => $d['image_cle'] ?? null,
            ':extrait' => $d['extrait'] ?? null,
            ':contenu' => $d['contenu'] ?? null,
            ':une'     => (int)(!empty($d['a_la_une'])),
            ':statut'  => ($d['statut'] ?? '') === 'publie' ? 'publie' : 'brouillon',
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, int $etabId, array $d): void
    {
        $this->db->prepare('
            UPDATE actualites
            SET titre = :titre, categorie = :cat, date_publication = :date,
                image_cle = :img, extrait = :extrait, contenu = :contenu,
                a_la_une = :une, statut = :statut
            WHERE id = :id AND etablissement_id = :etab
        ')->execute([
            ':titre'   => $d['titre'],
            ':cat'     => $d['categorie'] ?? null,
            ':date'    => $d['date_publication'] ?: date('Y-m-d'),
            ':img'     => $d['image_cle'] ?? null,
            ':extrait' => $d['extrait'] ?? null,
            ':contenu' => $d['contenu'] ?? null,
            ':une'     => (int)(!empty($d['a_la_une'])),
            ':statut'  => ($d['statut'] ?? '') === 'publie' ? 'publie' : 'brouillon',
            ':id'      => $id,
            ':etab'    => $etabId,
        ]);
    }

    public function delete(int $id, int $etabId): void
    {
        $this->db->prepare(
            'DELETE FROM actualites WHERE id = ? AND etablissement_id = ?'
        )->execute([$id, $etabId]);
    }
}
