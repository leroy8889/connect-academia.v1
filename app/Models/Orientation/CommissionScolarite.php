<?php
declare(strict_types=1);

namespace Models\Orientation;

use Core\Database;
use PDO;

/**
 * Commission plateforme sur la scolarité des élèves apportés aux
 * établissements, recouvrée en présentiel en tranches successives
 * (60 % / 40 % de la commission depuis la révision du recouvrement — la
 * répartition vit dans REPARTITION_TRANCHES, jamais recopiée ici).
 *
 * Le taux est propre à chaque établissement
 * (etablissements.taux_commission_scolarite, défini par le super admin
 * à la création/modification de l'établissement) ; 10 % par défaut.
 *
 * Une commission est adossée à un dossier d'inscription au statut
 * « inscrit » (validé par l'établissement). Le super admin la crée en
 * saisissant le montant de la scolarité — le taux de l'établissement
 * s'applique automatiquement — puis coche chaque tranche encaissée ;
 * l'établissement consulte l'état des tranches sur son dashboard
 * gestionnaire.
 */
class CommissionScolarite
{
    public const TAUX_DEFAUT = 10.0;

    /**
     * Répartition du recouvrement. Alias de la constante du service, qui reste
     * la source : deux tableaux littéraux finiraient par diverger le jour où
     * la répartition change.
     */
    public const REPARTITION_TRANCHES = \Services\Orientation\CommissionDossier::REPARTITION_TRANCHES;

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Dossiers « inscrit » d'un utilisateur, avec l'éventuelle commission
     * déjà créée (commission_id NULL = éligible à la création).
     */
    public function dossiersInscritsForUser(int $userId): array
    {
        $st = $this->db->prepare("
            SELECT d.id AS dossier_id, d.filiere_visee, d.niveau_entree, d.date_creation,
                   e.id AS etablissement_id, e.nom AS etablissement_nom,
                   c.id AS commission_id
            FROM dossiers_inscription d
            JOIN etablissements e ON e.id = d.etablissement_id
            LEFT JOIN commissions_scolarite c ON c.dossier_id = d.id
            WHERE d.eleve_id = ? AND d.statut IN ('valide_100', 'inscrit')
            ORDER BY d.date_creation DESC
        ");
        $st->execute([$userId]);
        $dossiers = $st->fetchAll(PDO::FETCH_ASSOC);

        foreach ($dossiers as &$d) {
            $d['taux_commission'] = $this->tauxEtablissement((int) $d['etablissement_id']);
        }

        return $dossiers;
    }

    /** Commissions d'un utilisateur, tranches incluses. */
    public function findByUserWithTranches(int $userId): array
    {
        $st = $this->db->prepare("
            SELECT c.*, e.nom AS etablissement_nom, d.filiere_visee
            FROM commissions_scolarite c
            JOIN etablissements e ON e.id = c.etablissement_id
            JOIN dossiers_inscription d ON d.id = c.dossier_id
            WHERE c.user_id = ?
            ORDER BY c.created_at DESC
        ");
        $st->execute([$userId]);
        $commissions = $st->fetchAll(PDO::FETCH_ASSOC);

        return $this->attachTranches($commissions);
    }

    /** Commissions d'un établissement, tranches incluses (dashboard gestionnaire). */
    public function findByEtabWithTranches(int $etabId): array
    {
        $st = $this->db->prepare("
            SELECT c.*, d.nom_complet AS eleve_nom, d.filiere_visee, d.niveau_entree,
                   d.reference, d.abandon_le, d.abandon_motif,
                   u.prenom AS user_prenom, u.nom AS user_nom
            FROM commissions_scolarite c
            JOIN dossiers_inscription d ON d.id = c.dossier_id
            LEFT JOIN users u ON u.id = c.user_id
            WHERE c.etablissement_id = ?
            ORDER BY c.created_at DESC
        ");
        $st->execute([$etabId]);
        $commissions = $st->fetchAll(PDO::FETCH_ASSOC);

        return $this->attachTranches($commissions);
    }

    /**
     * Crée la commission et son échéancier (REPARTITION_TRANCHES), au taux
     * configuré sur l'établissement (etablissements.taux_commission_scolarite,
     * 10 % par défaut). La dernière tranche absorbe les arrondis pour que la
     * somme des tranches égale exactement la commission.
     *
     * @throws \InvalidArgumentException dossier introuvable / non inscrit /
     *         sans élève rattaché / commission déjà existante / montant invalide
     */
    public function createForDossier(int $dossierId, float $montantScolarite, ?int $adminId): int
    {
        if ($montantScolarite <= 0) {
            throw new \InvalidArgumentException('Le montant de la scolarité doit être supérieur à 0.');
        }

        $st = $this->db->prepare(
            "SELECT id, eleve_id, etablissement_id, statut FROM dossiers_inscription WHERE id = ? LIMIT 1"
        );
        $st->execute([$dossierId]);
        $dossier = $st->fetch(PDO::FETCH_ASSOC);

        if (!$dossier) {
            throw new \InvalidArgumentException('Dossier introuvable.');
        }
        // 'valide_100' est le statut écrit par la machine à états depuis la
        // refonte ; 'inscrit' reste accepté pour les lignes non migrées.
        if (!in_array($dossier['statut'], ['valide_100', 'inscrit'], true)) {
            throw new \InvalidArgumentException('Le dossier doit être inscrit à 100 % (validé par l\'établissement).');
        }
        if (empty($dossier['eleve_id'])) {
            throw new \InvalidArgumentException('Ce dossier n\'est rattaché à aucun compte utilisateur.');
        }

        $exists = $this->db->prepare('SELECT id FROM commissions_scolarite WHERE dossier_id = ? LIMIT 1');
        $exists->execute([$dossierId]);
        if ($exists->fetch()) {
            throw new \InvalidArgumentException('Une commission existe déjà pour ce dossier.');
        }

        $taux = $this->tauxEtablissement((int) $dossier['etablissement_id']);
        if ($taux <= 0) {
            throw new \InvalidArgumentException(
                'Cet établissement est configuré sans commission (taux 0 %) — modifiez son taux dans « Orientation → Modifier » avant de créer une commission.'
            );
        }
        $commission = round($montantScolarite * $taux / 100, 2);

        $this->db->beginTransaction();
        try {
            // Vocabulaire de statut aligné sur le cycle de facturation actuel
            // ('a_facturer'), et non plus sur l'ancien 'en_cours' : les deux
            // écrans de suivi lisent la même colonne.
            $this->db->prepare("
                INSERT INTO commissions_scolarite
                  (dossier_id, user_id, etablissement_id, montant_scolarite,
                   taux_commission, montant_commission, statut, periode_facturation, created_by)
                VALUES (?, ?, ?, ?, ?, ?, 'a_facturer', ?, ?)
            ")->execute([
                $dossierId,
                (int) $dossier['eleve_id'],
                (int) $dossier['etablissement_id'],
                $montantScolarite,
                $taux,
                $commission,
                date('Y-m'),
                $adminId,
            ]);
            $commissionId = (int) $this->db->lastInsertId();

            // Échéancier construit par le service : une seule règle d'arrondi
            // dans tout le code, donc une seule somme possible.
            \Services\Orientation\CommissionDossier::creerTranches($commissionId, $commission, $this->db);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $commissionId;
    }

    /**
     * Coche/décoche une tranche encaissée en présentiel.
     * Ordre imposé : impossible de payer la tranche N avant la N-1,
     * ni d'annuler la N si la N+1 est déjà payée.
     *
     * @return array la tranche mise à jour + statut de la commission
     * @throws \InvalidArgumentException tranche introuvable / ordre violé
     */
    public function setTranchePayee(int $trancheId, bool $payee, ?int $adminId): array
    {
        // Délégation : l'encaissement d'une tranche est écrit à UN SEUL endroit
        // (Services\Orientation\CommissionDossier). Deux implémentations, c'est
        // deux vocabulaires de statut — « solde » ici, « reglee » là — et un
        // tableau de bord qui contredit l'autre.
        $resultat = \Services\Orientation\CommissionDossier::payerTranche(
            $trancheId,
            $payee,
            $adminId,
            null
        );

        if (!$resultat['ok']) {
            throw new \InvalidArgumentException($resultat['message']);
        }

        $st = $this->db->prepare(
            'SELECT commission_id, numero, est_payee, date_paiement
             FROM commission_tranches WHERE id = ? LIMIT 1'
        );
        $st->execute([$trancheId]);
        $tranche = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'tranche_id'        => $trancheId,
            'numero'            => (int) ($tranche['numero'] ?? 0),
            'est_payee'         => $payee,
            'date_paiement'     => $tranche['date_paiement'] ?? null,
            'commission_id'     => (int) ($tranche['commission_id'] ?? 0),
            'commission_statut' => $resultat['commission_statut'],
        ];
    }

    /**
     * Taux de commission configuré sur l'établissement (%).
     * Repli sur TAUX_DEFAUT si la colonne n'existe pas encore
     * (migration_taux_commission_etablissement.sql non jouée) — la
     * création de commissions continue de fonctionner comme avant.
     */
    public function tauxEtablissement(int $etabId): float
    {
        try {
            $st = $this->db->prepare(
                'SELECT taux_commission_scolarite FROM etablissements WHERE id = ? LIMIT 1'
            );
            $st->execute([$etabId]);
            $taux = $st->fetchColumn();

            return $taux !== false && $taux !== null ? (float) $taux : self::TAUX_DEFAUT;
        } catch (\PDOException $e) {
            error_log('[CommissionScolarite] taux_commission_scolarite indisponible (migration non jouée ?) : ' . $e->getMessage());
            return self::TAUX_DEFAUT;
        }
    }

    /** @param array $commissions lignes commissions_scolarite */
    private function attachTranches(array $commissions): array
    {
        if (empty($commissions)) {
            return [];
        }

        $ids          = array_column($commissions, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->db->prepare("
            SELECT * FROM commission_tranches
            WHERE commission_id IN ({$placeholders})
            ORDER BY commission_id, numero
        ");
        $st->execute($ids);

        $byCommission = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $t) {
            $byCommission[(int) $t['commission_id']][] = $t;
        }

        foreach ($commissions as &$c) {
            $c['tranches'] = $byCommission[(int) $c['id']] ?? [];
        }

        return $commissions;
    }
}
