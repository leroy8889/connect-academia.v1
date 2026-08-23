<?php
declare(strict_types=1);

namespace Models;

use PDO;

/**
 * Bilans d'activité — requêtes de reporting pour les documents PDF admin.
 *
 * Trois jeux de données, tous bornés par une plage de dates [debut, fin] :
 *   • préinscriptions  → dossiers_inscription déposés (statut ≠ brouillon)
 *   • inscriptions     → dossiers_inscription validés (statut inscrit/accepte)
 *   • utilisateurs     → comptes de la plateforme (table users, non supprimés)
 *
 * Les rapports d'établissement acceptent un etablissement_id null → agrégat
 * « tous établissements confondus ».
 */
class Bilan extends BaseModel
{
    /** Filière visée (enum dossier) → libellé lisible. */
    public const FILIERES = [
        'socle_l1_l2'   => 'Socle L1-L2',
        'licence_l3'    => 'Licence L3',
        'master_m1_m2'  => 'Master M1-M2',
        'certification' => 'Certification',
    ];

    /** Statut dossier → libellé lisible. */
    public const STATUTS = [
        'brouillon' => 'Brouillon',
        'soumis'    => 'Soumis',
        'paye'      => 'Payé',
        'accepte'   => 'Accepté',
        'inscrit'   => 'Inscrit',
        'refuse'    => 'Refusé',
    ];

    /**
     * Liste des établissements pour le sélecteur (id, nom, ville).
     * @return array<int,array{id:int,nom:string,ville:string}>
     */
    public function etablissementsSelect(): array
    {
        return $this->query(
            "SELECT id, nom, ville FROM etablissements ORDER BY nom ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Un établissement (nom, ville, couleur) ou null. */
    public function etablissement(int $id): ?array
    {
        $row = $this->query(
            "SELECT id, nom, ville, couleur_principale FROM etablissements WHERE id = ?",
            [$id]
        )->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Préinscriptions : tout dossier réellement déposé (statut ≠ brouillon)
     * sur la période. Si $etabId est null → tous établissements.
     */
    public function preinscriptions(?int $etabId, string $debut, string $fin): array
    {
        return $this->dossiers($etabId, $debut, $fin, ['soumis', 'paye', 'accepte', 'inscrit', 'refuse']);
    }

    /**
     * Inscriptions validées (statut inscrit + accepte legacy) sur la période.
     */
    public function inscriptions(?int $etabId, string $debut, string $fin): array
    {
        return $this->dossiers($etabId, $debut, $fin, ['inscrit', 'accepte']);
    }

    /**
     * @param string[] $statuts Liste blanche de statuts (littéraux internes, jamais utilisateur).
     */
    private function dossiers(?int $etabId, string $debut, string $fin, array $statuts): array
    {
        $in     = implode(',', array_fill(0, count($statuts), '?'));
        $params = $statuts;

        $sql = "SELECT d.id, d.nom_complet, d.genre, d.email, d.indicatif_tel, d.telephone,
                       d.filiere_visee, d.specialite_visee, d.niveau_entree, d.ville,
                       d.origine, d.statut, d.date_creation,
                       e.nom AS etab_nom, e.ville AS etab_ville
                FROM dossiers_inscription d
                JOIN etablissements e ON e.id = d.etablissement_id
                WHERE d.statut IN ($in)
                  AND d.date_creation BETWEEN ? AND ?";
        $params[] = $debut;
        $params[] = $fin;

        if ($etabId !== null) {
            $sql .= " AND d.etablissement_id = ?";
            $params[] = $etabId;
        }

        $sql .= " ORDER BY d.date_creation DESC";

        return $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Utilisateurs de la plateforme inscrits sur la période (comptes vivants).
     */
    public function utilisateurs(string $debut, string $fin): array
    {
        return $this->query(
            "SELECT u.id, u.nom, u.prenom, u.email, u.role, u.etablissement,
                    u.is_verified, u.is_active, u.last_login, u.created_at,
                    s.nom AS serie_nom
             FROM users u
             LEFT JOIN series s ON s.id = u.serie_id
             WHERE u.is_deleted = 0
               AND u.created_at BETWEEN ? AND ?
             ORDER BY u.created_at DESC",
            [$debut, $fin]
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}
