<?php
declare(strict_types=1);

namespace Models\Orientation;

use Core\Database;
use PDO;

/**
 * Avis 5 étoiles sur les établissements (modèle Google Reviews) :
 * un seul avis par utilisateur et par établissement — re-noter met à jour
 * l'avis existant au lieu d'en créer un second.
 */
class Avis
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /** Crée ou met à jour l'avis de l'utilisateur (upsert sur uq_etab_user). */
    public function upsert(int $etabId, int $userId, int $note, ?string $commentaire): void
    {
        $st = $this->db->prepare('
            INSERT INTO avis_etablissements (etablissement_id, user_id, note, commentaire)
            VALUES (:etab, :user, :note, :commentaire)
            ON DUPLICATE KEY UPDATE
                note = VALUES(note),
                commentaire = VALUES(commentaire),
                date_modification = NOW()
        ');
        $st->execute([
            ':etab'        => $etabId,
            ':user'        => $userId,
            ':note'        => $note,
            ':commentaire' => $commentaire !== null && trim($commentaire) !== '' ? trim($commentaire) : null,
        ]);
    }

    public function deleteByUser(int $etabId, int $userId): bool
    {
        $st = $this->db->prepare(
            'DELETE FROM avis_etablissements WHERE etablissement_id = ? AND user_id = ?'
        );
        $st->execute([$etabId, $userId]);
        return $st->rowCount() > 0;
    }

    /** Avis de l'utilisateur courant sur un établissement (pré-remplir le widget). */
    public function findByUser(int $etabId, int $userId): ?array
    {
        $st = $this->db->prepare('
            SELECT id, note, commentaire, date_creation, date_modification
            FROM avis_etablissements
            WHERE etablissement_id = ? AND user_id = ?
            LIMIT 1
        ');
        $st->execute([$etabId, $userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Moyenne + volume pour un établissement. */
    public function aggregateForEtab(int $etabId): array
    {
        $st = $this->db->prepare('
            SELECT COUNT(*) AS nb_avis, COALESCE(AVG(note), 0) AS note_moyenne
            FROM avis_etablissements
            WHERE etablissement_id = ?
        ');
        $st->execute([$etabId]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: ['nb_avis' => 0, 'note_moyenne' => 0];

        return [
            'nb_avis'      => (int)$row['nb_avis'],
            'note_moyenne' => round((float)$row['note_moyenne'], 1),
        ];
    }

    /**
     * Moyennes + volumes pour un lot d'établissements (cartes marketplace).
     * Retour indexé par etablissement_id.
     *
     * @param int[] $etabIds
     * @return array<int, array{nb_avis:int, note_moyenne:float}>
     */
    public function aggregatesForEtabs(array $etabIds): array
    {
        $etabIds = array_values(array_filter(array_map('intval', $etabIds)));
        if (empty($etabIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($etabIds), '?'));
        $st = $this->db->prepare("
            SELECT etablissement_id, COUNT(*) AS nb_avis, AVG(note) AS note_moyenne
            FROM avis_etablissements
            WHERE etablissement_id IN ({$placeholders})
            GROUP BY etablissement_id
        ");
        $st->execute($etabIds);

        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(int)$row['etablissement_id']] = [
                'nb_avis'      => (int)$row['nb_avis'],
                'note_moyenne' => round((float)$row['note_moyenne'], 1),
            ];
        }
        return $out;
    }

    /** Répartition 5→1 étoiles (barres histogramme, façon fiche Google). */
    public function distribution(int $etabId): array
    {
        $st = $this->db->prepare('
            SELECT note, COUNT(*) AS n
            FROM avis_etablissements
            WHERE etablissement_id = ?
            GROUP BY note
        ');
        $st->execute([$etabId]);
        $rows = $st->fetchAll(PDO::FETCH_KEY_PAIR);

        $dist = [];
        for ($i = 5; $i >= 1; $i--) {
            $dist[$i] = (int)($rows[$i] ?? 0);
        }
        return $dist;
    }

    /** Derniers avis publiés (nom public : prénom + initiale du nom). */
    public function listForEtab(int $etabId, int $limit = 6, int $offset = 0): array
    {
        $limit  = max(1, min(50, $limit));
        $offset = max(0, $offset);

        $st = $this->db->prepare("
            SELECT a.id, a.note, a.commentaire, a.date_creation, a.date_modification,
                   u.prenom, u.nom
            FROM avis_etablissements a
            JOIN users u ON u.id = a.user_id AND u.is_deleted = 0
            WHERE a.etablissement_id = ?
            ORDER BY a.date_modification DESC
            LIMIT {$limit} OFFSET {$offset}
        ");
        $st->execute([$etabId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
