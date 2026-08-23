<?php
declare(strict_types=1);

namespace Models\Orientation;

use Core\Database;
use PDO;

class Formation
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function findByEtab(int $etabId): array
    {
        $st = $this->db->prepare(
            'SELECT * FROM formations WHERE etablissement_id = ? ORDER BY ordre ASC, id ASC'
        );
        $st->execute([$etabId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id, int $etabId): ?array
    {
        $st = $this->db->prepare(
            'SELECT * FROM formations WHERE id = ? AND etablissement_id = ? LIMIT 1'
        );
        $st->execute([$id, $etabId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** NON cloisonné (inclut etablissement_id) — télémétrie TenantGuard sur le chemin de refus. */
    public function findByIdAny(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM formations WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(int $etabId, array $d): int
    {
        $st = $this->db->prepare('
            INSERT INTO formations
              (etablissement_id, intitule, niveau, icone, description, duree_texte, places_texte, prix, prix_unite, debouches, avantages, image_cle, statut, ordre)
            VALUES
              (:etab, :intitule, :niveau, :icone, :description, :duree, :places, :prix, :unite, :debouches, :avantages, :image_cle, :statut, :ordre)
        ');
        $st->execute([
            ':etab'    => $etabId,
            ':intitule'=> $d['intitule'],
            ':niveau'  => $d['niveau'] ?? null,
            ':icone'   => $d['icone'] ?? null,
            ':description' => $d['description'] ?? null,
            ':duree'   => $d['duree_texte'] ?? null,
            ':places'  => $d['places_texte'] ?? null,
            ':prix'    => (int)($d['prix'] ?? 0),
            ':unite'   => $d['prix_unite'] ?? 'FCFA/an',
            ':debouches' => $d['debouches'] ?? null,
            ':avantages' => $d['avantages'] ?? null,
            ':image_cle' => $d['image_cle'] ?? null,
            ':statut'  => in_array($d['statut'] ?? '', ['ouverte','bientot','fermee'], true)
                            ? $d['statut'] : 'ouverte',
            ':ordre'   => (int)($d['ordre'] ?? 0),
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, int $etabId, array $d): void
    {
        $sql = 'UPDATE formations
            SET intitule = :intitule, niveau = :niveau, icone = :icone, description = :description,
                duree_texte = :duree, places_texte = :places,
                prix = :prix, prix_unite = :unite, debouches = :debouches, avantages = :avantages,
                statut = :statut, ordre = :ordre';
        $params = [
            ':intitule'=> $d['intitule'],
            ':niveau'  => $d['niveau'] ?? null,
            ':icone'   => $d['icone'] ?? null,
            ':description' => $d['description'] ?? null,
            ':duree'   => $d['duree_texte'] ?? null,
            ':places'  => $d['places_texte'] ?? null,
            ':prix'    => (int)($d['prix'] ?? 0),
            ':unite'   => $d['prix_unite'] ?? 'FCFA/an',
            ':debouches' => $d['debouches'] ?? null,
            ':avantages' => $d['avantages'] ?? null,
            ':statut'  => in_array($d['statut'] ?? '', ['ouverte','bientot','fermee'], true)
                            ? $d['statut'] : 'ouverte',
            ':ordre'   => (int)($d['ordre'] ?? 0),
            ':id'      => $id,
            ':etab'    => $etabId,
        ];
        // image_cle : mise à jour seulement si une nouvelle image est fournie (upload optionnel)
        if (array_key_exists('image_cle', $d) && $d['image_cle'] !== null) {
            $sql .= ', image_cle = :image_cle';
            $params[':image_cle'] = $d['image_cle'];
        }
        $sql .= ' WHERE id = :id AND etablissement_id = :etab';
        $this->db->prepare($sql)->execute($params);
    }

    public function delete(int $id, int $etabId): void
    {
        $this->db->prepare(
            'DELETE FROM formations WHERE id = ? AND etablissement_id = ?'
        )->execute([$id, $etabId]);
    }
}
