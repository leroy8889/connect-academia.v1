<?php
declare(strict_types=1);

namespace Models\Orientation;

use Core\Database;
use PDO;

/**
 * Filières & spécialités configurées par chaque établissement
 * (tables filieres_etablissement / specialites_filiere).
 * Utilisées par les formulaires de préinscription / inscription
 * pour afficher un menu déroulant Filière → Spécialité en cascade.
 */
class Filiere
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    // ── Filières ──────────────────────────────────────────────────────────────

    /** Toutes les filières d'un établissement, avec leurs spécialités (gestion). */
    public function findByEtab(int $etabId): array
    {
        $st = $this->db->prepare(
            'SELECT * FROM filieres_etablissement WHERE etablissement_id = ? ORDER BY ordre ASC, nom ASC'
        );
        $st->execute([$etabId]);
        $filieres = $st->fetchAll(PDO::FETCH_ASSOC);

        if (!$filieres) {
            return [];
        }

        $ids = array_column($filieres, 'id');
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $st  = $this->db->prepare(
            "SELECT * FROM specialites_filiere WHERE filiere_id IN ({$in}) ORDER BY ordre ASC, nom ASC"
        );
        $st->execute($ids);

        $specByFiliere = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $spec) {
            $specByFiliere[(int)$spec['filiere_id']][] = $spec;
        }

        foreach ($filieres as &$f) {
            $f['specialites'] = $specByFiliere[(int)$f['id']] ?? [];
        }
        unset($f);

        return $filieres;
    }

    /**
     * Filières actives + spécialités actives, pour les formulaires publics.
     * Retourne [['nom' => 'Droit', 'specialites' => ['Conseil juridique', …]], …]
     */
    public function findActiveByEtab(int $etabId): array
    {
        $st = $this->db->prepare(
            'SELECT id, nom FROM filieres_etablissement
             WHERE etablissement_id = ? AND actif = 1
             ORDER BY ordre ASC, nom ASC'
        );
        $st->execute([$etabId]);
        $filieres = $st->fetchAll(PDO::FETCH_ASSOC);

        if (!$filieres) {
            return [];
        }

        $ids = array_column($filieres, 'id');
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $st  = $this->db->prepare(
            "SELECT filiere_id, nom FROM specialites_filiere
             WHERE filiere_id IN ({$in}) AND actif = 1
             ORDER BY ordre ASC, nom ASC"
        );
        $st->execute($ids);

        $specByFiliere = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $spec) {
            $specByFiliere[(int)$spec['filiere_id']][] = $spec['nom'];
        }

        $out = [];
        foreach ($filieres as $f) {
            $out[] = [
                'nom'         => $f['nom'],
                'specialites' => $specByFiliere[(int)$f['id']] ?? [],
            ];
        }
        return $out;
    }

    public function findById(int $id, int $etabId): ?array
    {
        $st = $this->db->prepare(
            'SELECT * FROM filieres_etablissement WHERE id = ? AND etablissement_id = ? LIMIT 1'
        );
        $st->execute([$id, $etabId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Chargement NON cloisonné (inclut etablissement_id) — réservé à la
     * télémétrie TenantGuard sur le chemin de refus, pour distinguer un vrai
     * 404 d'une sonde cross-établissement. Jamais utilisé pour un accès légitime.
     */
    public function findByIdAny(int $id): ?array
    {
        $st = $this->db->prepare(
            'SELECT * FROM filieres_etablissement WHERE id = ? LIMIT 1'
        );
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(int $etabId, array $d): int
    {
        $st = $this->db->prepare(
            'INSERT INTO filieres_etablissement (etablissement_id, nom, frais_inscription, frais_scolarite, ordre, actif)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $etabId,
            $d['nom'],
            (int)($d['frais_inscription'] ?? 0),
            (int)($d['frais_scolarite'] ?? 0),
            (int)($d['ordre'] ?? 0),
            !empty($d['actif']) ? 1 : 0,
        ]);
        return (int)$this->db->lastInsertId();
    }

    /** Filière d'un établissement par nom exact — résolution du tarif pour la facture proforma. */
    public function findByNom(string $nom, int $etabId): ?array
    {
        $st = $this->db->prepare(
            'SELECT * FROM filieres_etablissement WHERE etablissement_id = ? AND nom = ? LIMIT 1'
        );
        $st->execute([$etabId, $nom]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function update(int $id, int $etabId, array $d): void
    {
        $this->db->prepare(
            'UPDATE filieres_etablissement
             SET nom = ?, frais_inscription = ?, frais_scolarite = ?, ordre = ?, actif = ?
             WHERE id = ? AND etablissement_id = ?'
        )->execute([
            $d['nom'],
            (int)($d['frais_inscription'] ?? 0),
            (int)($d['frais_scolarite'] ?? 0),
            (int)($d['ordre'] ?? 0),
            !empty($d['actif']) ? 1 : 0,
            $id,
            $etabId,
        ]);
    }

    /** Supprime la filière et ses spécialités (FK ON DELETE CASCADE). */
    public function delete(int $id, int $etabId): void
    {
        $this->db->prepare(
            'DELETE FROM filieres_etablissement WHERE id = ? AND etablissement_id = ?'
        )->execute([$id, $etabId]);
    }

    // ── Spécialités ───────────────────────────────────────────────────────────

    /** Spécialité verrouillée sur l'établissement (isolation multi-tenant). */
    public function findSpecialite(int $specId, int $etabId): ?array
    {
        $st = $this->db->prepare(
            'SELECT s.* FROM specialites_filiere s
             JOIN filieres_etablissement f ON f.id = s.filiere_id
             WHERE s.id = ? AND f.etablissement_id = ?
             LIMIT 1'
        );
        $st->execute([$specId, $etabId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** NON cloisonné (expose l'etablissement_id de la filière parente) — télémétrie TenantGuard seulement. */
    public function findSpecialiteAny(int $specId): ?array
    {
        $st = $this->db->prepare(
            'SELECT s.*, f.etablissement_id
             FROM specialites_filiere s
             JOIN filieres_etablissement f ON f.id = s.filiere_id
             WHERE s.id = ? LIMIT 1'
        );
        $st->execute([$specId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function createSpecialite(int $filiereId, int $etabId, array $d): ?int
    {
        // Vérifier que la filière appartient bien à l'établissement
        if (!$this->findById($filiereId, $etabId)) {
            return null;
        }
        $st = $this->db->prepare(
            'INSERT INTO specialites_filiere (filiere_id, nom, ordre, actif)
             VALUES (?, ?, ?, ?)'
        );
        $st->execute([
            $filiereId,
            $d['nom'],
            (int)($d['ordre'] ?? 0),
            !empty($d['actif']) ? 1 : 0,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function updateSpecialite(int $specId, int $etabId, array $d): void
    {
        $this->db->prepare(
            'UPDATE specialites_filiere s
             JOIN filieres_etablissement f ON f.id = s.filiere_id
             SET s.nom = ?, s.ordre = ?, s.actif = ?
             WHERE s.id = ? AND f.etablissement_id = ?'
        )->execute([
            $d['nom'],
            (int)($d['ordre'] ?? 0),
            !empty($d['actif']) ? 1 : 0,
            $specId,
            $etabId,
        ]);
    }

    public function deleteSpecialite(int $specId, int $etabId): void
    {
        $this->db->prepare(
            'DELETE s FROM specialites_filiere s
             JOIN filieres_etablissement f ON f.id = s.filiere_id
             WHERE s.id = ? AND f.etablissement_id = ?'
        )->execute([$specId, $etabId]);
    }
}
