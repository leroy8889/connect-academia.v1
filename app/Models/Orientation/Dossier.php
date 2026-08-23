<?php
declare(strict_types=1);

namespace Models\Orientation;

use Core\Database;
use PDO;

class Dossier
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create(array $d): int
    {
        $st = $this->db->prepare('
            INSERT INTO dossiers_inscription
              (etablissement_id, eleve_id, filiere_visee, specialite_visee, niveau_entree,
               nom_complet, genre, date_naissance, lieu_naissance, nationalite,
               email, indicatif_tel, telephone, adresse, ville, pays_provenance,
               dernier_etablissement, serie_bac, annee_bac, origine, statut)
            VALUES
              (:etablissement_id, :eleve_id, :filiere_visee, :specialite_visee, :niveau_entree,
               :nom_complet, :genre, :date_naissance, :lieu_naissance, :nationalite,
               :email, :indicatif_tel, :telephone, :adresse, :ville, :pays_provenance,
               :dernier_etablissement, :serie_bac, :annee_bac, "en_ligne", :statut)
        ');

        $st->execute([
            ':etablissement_id'      => $d['etablissement_id'],
            ':eleve_id'              => !empty($d['eleve_id']) ? (int)$d['eleve_id'] : null,
            ':filiere_visee'         => $d['filiere_visee'] ?? null,
            ':specialite_visee'      => $d['specialite_visee'] ?? null,
            ':niveau_entree'         => $d['niveau_entree'] ?? null,
            ':nom_complet'           => $d['nom_complet'],
            ':genre'                 => $d['genre'],
            ':date_naissance'        => $d['date_naissance'],
            ':lieu_naissance'        => $d['lieu_naissance'],
            ':nationalite'           => $d['nationalite'],
            ':email'                 => $d['email'],
            ':indicatif_tel'         => $d['indicatif_tel'] ?? '+241',
            ':telephone'             => $d['telephone'],
            ':adresse'               => $d['adresse'] ?? null,
            ':ville'                 => $d['ville'] ?? null,
            ':pays_provenance'       => $d['pays_provenance'] ?? null,
            ':dernier_etablissement' => $d['dernier_etablissement'] ?? null,
            ':serie_bac'             => $d['serie_bac'] ?? null,
            ':annee_bac'             => !empty($d['annee_bac']) ? (int)$d['annee_bac'] : null,
            ':statut'                => $d['statut'] ?? 'brouillon',
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $st = $this->db->prepare('
            SELECT d.*,
                   e.nom               AS etab_nom,
                   e.slug              AS etab_slug,
                   e.couleur_principale,
                   e.ville             AS etab_ville,
                   e.genere_recepisse,
                   e.genere_attestation,
                   e.preinscription_active,
                   e.inscription_active,
                   TRIM(CONCAT(COALESCE(cu.prenom, ""), " ", COALESCE(cu.nom, ""))) AS cree_par_nom
            FROM dossiers_inscription d
            JOIN etablissements e ON e.id = d.etablissement_id
            LEFT JOIN users cu ON cu.id = d.cree_par_user_id
            WHERE d.id = ?
            LIMIT 1
        ');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateFiliere(int $id, string $filiere, string $niveau, ?string $specialite = null): void
    {
        $this->db->prepare(
            'UPDATE dossiers_inscription SET filiere_visee = ?, specialite_visee = ?, niveau_entree = ? WHERE id = ?'
        )->execute([$filiere, $specialite, $niveau, $id]);
    }

    /**
     * Met à jour les informations du candidat — verrouillé sur l'établissement
     * du gestionnaire (isolation multi-tenant). Retourne false si le dossier
     * n'appartient pas à cet établissement.
     */
    public function updateInfosByEtab(int $id, int $etabId, array $d): bool
    {
        $st = $this->db->prepare('
            UPDATE dossiers_inscription SET
              nom_complet           = :nom_complet,
              genre                 = :genre,
              date_naissance        = :date_naissance,
              lieu_naissance        = :lieu_naissance,
              nationalite           = :nationalite,
              email                 = :email,
              indicatif_tel         = :indicatif_tel,
              telephone             = :telephone,
              adresse               = :adresse,
              ville                 = :ville,
              pays_provenance       = :pays_provenance,
              dernier_etablissement = :dernier_etablissement,
              filiere_visee         = :filiere_visee,
              specialite_visee      = :specialite_visee,
              niveau_entree         = :niveau_entree
            WHERE id = :id AND etablissement_id = :etab
        ');
        $st->execute([
            ':nom_complet'           => $d['nom_complet'],
            ':genre'                 => $d['genre'],
            ':date_naissance'        => $d['date_naissance'],
            ':lieu_naissance'        => $d['lieu_naissance'],
            ':nationalite'           => $d['nationalite'],
            ':email'                 => $d['email'],
            ':indicatif_tel'         => $d['indicatif_tel'] ?? '+241',
            ':telephone'             => $d['telephone'],
            ':adresse'               => $d['adresse'] ?? null,
            ':ville'                 => $d['ville'] ?? null,
            ':pays_provenance'       => $d['pays_provenance'] ?? null,
            ':dernier_etablissement' => $d['dernier_etablissement'] ?? null,
            ':filiere_visee'         => $d['filiere_visee'] ?? null,
            ':specialite_visee'      => $d['specialite_visee'] ?? null,
            ':niveau_entree'         => $d['niveau_entree'] ?? null,
            ':id'                    => $id,
            ':etab'                  => $etabId,
        ]);
        return $st->rowCount() > 0 || $this->belongsToEtab($id, $etabId);
    }

    private function belongsToEtab(int $id, int $etabId): bool
    {
        $st = $this->db->prepare(
            'SELECT COUNT(*) FROM dossiers_inscription WHERE id = ? AND etablissement_id = ?'
        );
        $st->execute([$id, $etabId]);
        return (int)$st->fetchColumn() > 0;
    }

    /**
     * Cherche un dossier portant déjà cet e-mail DANS le même établissement.
     * Volontairement borné à l'établissement : un candidat a le droit de
     * postuler dans plusieurs écoles avec le même e-mail, et un gestionnaire ne
     * doit rien apprendre des dossiers d'un autre établissement (multi-tenant).
     *
     * La comparaison est insensible à la casse et aux espaces de bord — les
     * anciens dossiers ont pu être saisis « Jean@Mail.com » au guichet.
     *
     * @param int|null $excludeId Dossier à ignorer (cas de la modification).
     * @return array|null ['id','nom_complet','statut'] du dossier en conflit.
     */
    public function findByEmailInEtab(string $email, int $etabId, ?int $excludeId = null): ?array
    {
        $email = strtolower(trim($email));
        if ($email === '' || $etabId <= 0) {
            return null;
        }

        $sql = 'SELECT id, nom_complet, statut
                FROM dossiers_inscription
                WHERE etablissement_id = :etab
                  AND LOWER(TRIM(email)) = :email';
        $params = [':etab' => $etabId, ':email' => $email];

        if ($excludeId !== null && $excludeId > 0) {
            $sql .= ' AND id <> :exclude';
            $params[':exclude'] = $excludeId;
        }
        $sql .= ' ORDER BY id ASC LIMIT 1';

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateStatut(int $id, string $statut): void
    {
        $this->db->prepare(
            'UPDATE dossiers_inscription SET statut = ? WHERE id = ?'
        )->execute([$statut, $id]);
    }

    /**
     * Réserve un numéro de récépissé unique et crée la ligne (cle_pdf vide,
     * complétée ensuite par updateRecepisseCle). Numérotation atomique et
     * anti-collision : la contrainte numero_recepisse est unique sur toute la
     * table — on part du plus grand numéro de l'année, puis on réessaie en
     * incrémentant tant que l'INSERT bute sur un doublon (course concurrente ou
     * numéro déjà pris par un autre établissement). Idempotent : renvoie le
     * numéro existant si le dossier possède déjà un récépissé.
     */
    public function reserveRecepisse(int $dossierId): string
    {
        $existing = $this->getRecepisse($dossierId);
        if ($existing && !empty($existing['numero_recepisse'])) {
            return (string)$existing['numero_recepisse'];
        }

        $year = date('Y');
        $base = $this->nextSequence('recepisses', 'numero_recepisse', 'PRE', $year);

        for ($i = 0; $i < 50; $i++) {
            $numero = sprintf('PRE-%s-%04d', $year, $base + $i);
            try {
                $this->db->prepare('
                    INSERT INTO recepisses (dossier_id, numero_recepisse, cle_pdf, date_emission, token_verification)
                    VALUES (?, ?, "", NOW(), ?)
                ')->execute([$dossierId, $numero, bin2hex(random_bytes(32))]);

                try {
                    $this->db->prepare('
                        INSERT INTO preinscriptions (dossier_id, date_preinscription)
                        VALUES (?, NOW())
                    ')->execute([$dossierId]);
                } catch (\Throwable) {
                    // Doublon éventuel (rejeu) — ignoré
                }
                return $numero;
            } catch (\PDOException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
                // Collision : dossier déjà pourvu (course) → on renvoie l'existant,
                // sinon numéro pris ailleurs → on réessaie avec +1.
                $existing = $this->getRecepisse($dossierId);
                if ($existing && !empty($existing['numero_recepisse'])) {
                    return (string)$existing['numero_recepisse'];
                }
            }
        }
        throw new \RuntimeException('Récépissé : impossible de réserver un numéro unique.');
    }

    public function updateRecepisseCle(int $dossierId, string $cle): void
    {
        $this->db->prepare(
            'UPDATE recepisses SET cle_pdf = ? WHERE dossier_id = ?'
        )->execute([$cle, $dossierId]);
    }

    /**
     * Prochain numéro séquentiel (global, par année) pour une colonne au format
     * "PREFIX-AAAA-NNNN". Renvoie MAX(NNNN)+1 sur l'année, ou 1 si aucun.
     * $table/$col sont des identifiants internes fixes (jamais des entrées
     * utilisateur) — interpolation sûre.
     */
    private function nextSequence(string $table, string $col, string $prefix, string $year): int
    {
        $st = $this->db->prepare(
            "SELECT MAX(CAST(SUBSTRING_INDEX($col, '-', -1) AS UNSIGNED))
             FROM $table
             WHERE $col LIKE :like"
        );
        $st->execute([':like' => $prefix . '-' . $year . '-%']);
        return (int)$st->fetchColumn() + 1;
    }

    public function getRecepisse(int $dossierId): ?array
    {
        $st = $this->db->prepare('SELECT * FROM recepisses WHERE dossier_id = ? LIMIT 1');
        $st->execute([$dossierId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getPiecesObligatoires(int $etablissementId): array
    {
        $st = $this->db->prepare(
            'SELECT id FROM pieces_justificatives_requises
             WHERE etablissement_id = ? AND obligatoire = 1 AND id > 0'
        );
        $st->execute([$etablissementId]);
        return $st->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    public function getPiecesGroupedByNiveau(int $etablissementId): array
    {
        $st = $this->db->prepare('
            SELECT * FROM pieces_justificatives_requises
            WHERE etablissement_id = ?
            ORDER BY obligatoire DESC, ordre ASC, id ASC
        ');
        $st->execute([$etablissementId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($rows as $row) {
            $key = $row['niveau'] ?? '__tous';
            $grouped[$key][] = $row;
        }
        return $grouped;
    }

    public function addDocument(array $d): int
    {
        $st = $this->db->prepare('
            INSERT INTO documents_orientation
              (dossier_id, eleve_id, piece_requise_id, libelle, cle_b2, mime_type, taille_octets)
            VALUES
              (:dossier_id, :eleve_id, :piece_requise_id, :libelle, :cle_b2, :mime_type, :taille)
        ');
        $st->execute([
            ':dossier_id'       => $d['dossier_id'],
            ':eleve_id'         => $d['eleve_id'] ?? 0,
            ':piece_requise_id' => $d['piece_requise_id'] ?? null,
            ':libelle'          => $d['libelle'],
            ':cle_b2'           => $d['cle_b2'],
            ':mime_type'        => $d['mime_type'],
            ':taille'           => $d['taille_octets'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    // ── Attestation d'inscription (délivrée après validation) ────────────────

    /**
     * Réserve un numéro d'attestation unique et crée la ligne (cle_pdf vide).
     * Même stratégie atomique anti-collision que reserveRecepisse (contrainte
     * uq_attestation_numero unique sur toute la table). Idempotent.
     */
    public function reserveAttestation(int $dossierId): string
    {
        $existing = $this->getAttestation($dossierId);
        if ($existing && !empty($existing['numero_attestation'])) {
            return (string)$existing['numero_attestation'];
        }

        $year = date('Y');
        $base = $this->nextSequence('attestations_inscription', 'numero_attestation', 'ATT', $year);

        for ($i = 0; $i < 50; $i++) {
            $numero = sprintf('ATT-%s-%04d', $year, $base + $i);
            try {
                $this->db->prepare('
                    INSERT INTO attestations_inscription (dossier_id, numero_attestation, cle_pdf, date_emission, token_verification)
                    VALUES (?, ?, "", NOW(), ?)
                ')->execute([$dossierId, $numero, bin2hex(random_bytes(32))]);
                return $numero;
            } catch (\PDOException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
                $existing = $this->getAttestation($dossierId);
                if ($existing && !empty($existing['numero_attestation'])) {
                    return (string)$existing['numero_attestation'];
                }
            }
        }
        throw new \RuntimeException('Attestation : impossible de réserver un numéro unique.');
    }

    public function updateAttestationCle(int $dossierId, string $cle): void
    {
        $this->db->prepare(
            'UPDATE attestations_inscription SET cle_pdf = ? WHERE dossier_id = ?'
        )->execute([$cle, $dossierId]);
    }

    public function getAttestation(int $dossierId): ?array
    {
        $st = $this->db->prepare('SELECT * FROM attestations_inscription WHERE dossier_id = ? LIMIT 1');
        $st->execute([$dossierId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ── Vérification d'authenticité (QR code) ────────────────────────────────

    /**
     * Token de vérification du récépissé (64 hex, 256 bits aléatoires),
     * encodé dans le QR code imprimé sur le document. Backfill paresseux :
     * les lignes créées avant la migration reçoivent leur token au premier
     * accès. Renvoie null si le dossier n'a pas encore de récépissé.
     */
    public function tokenVerificationRecepisse(int $dossierId): ?string
    {
        return $this->tokenVerification('recepisses', $dossierId);
    }

    /** Idem pour l'attestation d'inscription. */
    public function tokenVerificationAttestation(int $dossierId): ?string
    {
        return $this->tokenVerification('attestations_inscription', $dossierId);
    }

    /** $table est un identifiant interne fixe (jamais une entrée utilisateur). */
    private function tokenVerification(string $table, int $dossierId): ?string
    {
        $st = $this->db->prepare("SELECT id, token_verification FROM $table WHERE dossier_id = ? LIMIT 1");
        $st->execute([$dossierId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        if (!empty($row['token_verification'])) {
            return (string)$row['token_verification'];
        }

        $token = bin2hex(random_bytes(32));
        $this->db->prepare("UPDATE $table SET token_verification = ? WHERE id = ?")
                 ->execute([$token, (int)$row['id']]);
        return $token;
    }

    /**
     * Recherche un récépissé par son token de vérification (scan du QR code).
     * Renvoie les données officielles à afficher publiquement : document,
     * dossier, établissement et compte élève lié — ou null si token inconnu.
     */
    public function findRecepisseByToken(string $token): ?array
    {
        $st = $this->db->prepare('
            SELECT r.numero_recepisse AS numero, r.date_emission, r.token_verification,
                   d.id AS dossier_id, d.nom_complet, d.genre, d.date_naissance, d.lieu_naissance,
                   d.nationalite, d.pays_provenance, d.email, d.indicatif_tel, d.telephone,
                   d.filiere_visee, d.specialite_visee, d.niveau_entree, d.dernier_etablissement,
                   d.statut, d.origine, d.date_creation, d.eleve_id,
                   e.nom AS etab_nom, e.ville AS etab_ville, e.slug AS etab_slug,
                   e.couleur_principale, e.logo_cle,
                   u.nom AS compte_nom, u.prenom AS compte_prenom, u.email AS compte_email,
                   u.created_at AS compte_cree_le, u.is_verified AS compte_verifie
            FROM recepisses r
            JOIN dossiers_inscription d ON d.id = r.dossier_id
            JOIN etablissements e ON e.id = d.etablissement_id
            LEFT JOIN users u ON u.id = d.eleve_id
            WHERE r.token_verification = ?
            LIMIT 1
        ');
        $st->execute([$token]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Idem pour l'attestation d'inscription. */
    public function findAttestationByToken(string $token): ?array
    {
        $st = $this->db->prepare('
            SELECT a.numero_attestation AS numero, a.date_emission, a.token_verification,
                   d.id AS dossier_id, d.nom_complet, d.genre, d.date_naissance, d.lieu_naissance,
                   d.nationalite, d.pays_provenance, d.email, d.indicatif_tel, d.telephone,
                   d.adresse, d.ville,
                   d.filiere_visee, d.specialite_visee, d.niveau_entree, d.dernier_etablissement,
                   d.statut, d.origine, d.date_creation, d.eleve_id,
                   e.nom AS etab_nom, e.ville AS etab_ville, e.slug AS etab_slug,
                   e.couleur_principale, e.logo_cle,
                   u.nom AS compte_nom, u.prenom AS compte_prenom, u.email AS compte_email,
                   u.created_at AS compte_cree_le, u.is_verified AS compte_verifie
            FROM attestations_inscription a
            JOIN dossiers_inscription d ON d.id = a.dossier_id
            JOIN etablissements e ON e.id = d.etablissement_id
            LEFT JOIN users u ON u.id = d.eleve_id
            WHERE a.token_verification = ?
            LIMIT 1
        ');
        $st->execute([$token]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ── Paiement ─────────────────────────────────────────────────────────────

    public function createPaiement(array $d): int
    {
        $st = $this->db->prepare('
            INSERT INTO paiements_orientation
              (dossier_id, etablissement_id, montant, mode, token_pay, statut)
            VALUES
              (:dossier_id, :etablissement_id, :montant, :mode, :token_pay, :statut)
        ');
        $st->execute([
            ':dossier_id'       => $d['dossier_id'],
            ':etablissement_id' => $d['etablissement_id'],
            ':montant'          => $d['montant'],
            ':mode'             => $d['mode'] ?? 'en_ligne',
            ':token_pay'        => $d['token_pay'] ?? null,
            ':statut'           => $d['statut'] ?? 'initie',
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function findPaiementById(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM paiements_orientation WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findPaiementByToken(string $token): ?array
    {
        $st = $this->db->prepare('SELECT * FROM paiements_orientation WHERE token_pay = ? LIMIT 1');
        $st->execute([$token]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findLastPaiementByDossier(int $dossierId): ?array
    {
        $st = $this->db->prepare(
            'SELECT * FROM paiements_orientation WHERE dossier_id = ? ORDER BY id DESC LIMIT 1'
        );
        $st->execute([$dossierId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Paiements d'un établissement, joints au dossier, au reçu et au
     * reversement — pour la page Paiements de l'espace gestionnaire.
     */
    public function findPaiementsByEtab(int $etabId, int $limit = 300): array
    {
        $st = $this->db->prepare('
            SELECT p.*,
                   d.nom_complet, d.email, d.filiere_visee, d.specialite_visee,
                   d.niveau_entree, d.statut AS dossier_statut,
                   rec.numero_recu, rec.cle_pdf AS recu_cle, rec.date_emission AS recu_date,
                   rv.statut AS reversement_statut, rv.montant_reverse,
                   rv.commission_plateforme, rv.date_confirmation AS reversement_date
            FROM paiements_orientation p
            JOIN dossiers_inscription d ON d.id = p.dossier_id
            LEFT JOIN recus_orientation rec ON rec.paiement_id = p.id
            LEFT JOIN reversements rv ON rv.paiement_id = p.id
            WHERE p.etablissement_id = :etab
            ORDER BY p.date_paiement DESC
            LIMIT ' . max(1, $limit)
        );
        $st->execute([':etab' => $etabId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Totaux paiements d'un établissement (paiements réussis uniquement).
     */
    public function statsPaiementsEtab(int $etabId): array
    {
        $st = $this->db->prepare("
            SELECT COUNT(*)                                        AS nb_reussis,
                   COALESCE(SUM(p.montant), 0)                     AS montant_total,
                   COALESCE(SUM(rv.montant_reverse), 0)            AS montant_reverse,
                   COALESCE(SUM(rv.commission_plateforme), 0)      AS commission_totale,
                   COALESCE(SUM(rv.statut = 'reussi'), 0)          AS reversements_reussis
            FROM paiements_orientation p
            LEFT JOIN reversements rv ON rv.paiement_id = p.id
            WHERE p.etablissement_id = ? AND p.statut = 'reussi'
        ");
        $st->execute([$etabId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Tous les paiements de la plateforme (vue super admin), filtrables par
     * établissement et statut.
     */
    public function findAllPaiements(?int $etabId = null, ?string $statut = null, int $limit = 500): array
    {
        $where  = [];
        $params = [];
        if ($etabId) {
            $where[]         = 'p.etablissement_id = :etab';
            $params[':etab'] = $etabId;
        }
        if ($statut !== null && $statut !== '') {
            $where[]           = 'p.statut = :statut';
            $params[':statut'] = $statut;
        }
        $sql = '
            SELECT p.*,
                   d.nom_complet, d.email, d.filiere_visee, d.specialite_visee,
                   e.nom AS etab_nom, e.slug AS etab_slug, e.couleur_principale,
                   rec.numero_recu, rec.cle_pdf AS recu_cle,
                   rv.statut AS reversement_statut, rv.montant_reverse,
                   rv.commission_plateforme, rv.numero_destination
            FROM paiements_orientation p
            JOIN dossiers_inscription d ON d.id = p.dossier_id
            JOIN etablissements e ON e.id = p.etablissement_id
            LEFT JOIN recus_orientation rec ON rec.paiement_id = p.id
            LEFT JOIN reversements rv ON rv.paiement_id = p.id
            ' . (!empty($where) ? 'WHERE ' . implode(' AND ', $where) : '') . '
            ORDER BY p.date_paiement DESC
            LIMIT ' . max(1, $limit);
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Totaux plateforme (vue super admin) — paiements réussis uniquement.
     */
    public function statsPaiementsGlobal(): array
    {
        $st = $this->db->query("
            SELECT COUNT(*)                                        AS nb_reussis,
                   COALESCE(SUM(p.montant), 0)                     AS montant_total,
                   COALESCE(SUM(rv.montant_reverse), 0)            AS montant_reverse,
                   COALESCE(SUM(rv.commission_plateforme), 0)      AS commission_totale,
                   COALESCE(SUM(rv.statut = 'reussi'), 0)          AS reversements_reussis,
                   COALESCE(SUM(rv.statut IN ('a_rejouer','echoue')), 0) AS reversements_en_echec
            FROM paiements_orientation p
            LEFT JOIN reversements rv ON rv.paiement_id = p.id
            WHERE p.statut = 'reussi'
        ");
        return $st->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function updatePaiementStatut(int $id, string $statut): void
    {
        $sql    = 'UPDATE paiements_orientation SET statut = ?';
        $params = [$statut];
        if ($statut === 'reussi') {
            $sql .= ', date_confirmation = NOW()';
        }
        $sql .= ' WHERE id = ?';
        $params[] = $id;
        $this->db->prepare($sql)->execute($params);
    }

    public function updatePaiementToken(int $id, string $token): void
    {
        $this->db->prepare('UPDATE paiements_orientation SET token_pay = ? WHERE id = ?')
            ->execute([$token, $id]);
    }

    // ── Inscription ───────────────────────────────────────────────────────────

    public function hasInscription(int $dossierId): bool
    {
        $st = $this->db->prepare('SELECT COUNT(*) FROM inscriptions WHERE dossier_id = ?');
        $st->execute([$dossierId]);
        return (int)$st->fetchColumn() > 0;
    }

    public function createInscription(int $dossierId, int $etabId): void
    {
        try {
            $this->db->prepare('
                INSERT INTO inscriptions (dossier_id, etablissement_id)
                VALUES (?, ?)
            ')->execute([$dossierId, $etabId]);
        } catch (\Throwable) {
            // Ignore duplicate (idempotence)
        }
    }

    // ── Reçu ─────────────────────────────────────────────────────────────────

    /**
     * Réserve un numéro de reçu unique et crée la ligne (cle_pdf vide).
     * Même stratégie atomique anti-collision (numero_recu unique sur toute la
     * table). Idempotent par paiement. Renvoie le numéro attribué.
     */
    public function reserveRecu(int $paiementId): string
    {
        $existing = $this->getRecu($paiementId);
        if ($existing && !empty($existing['numero_recu'])) {
            return (string)$existing['numero_recu'];
        }

        $year = date('Y');
        $base = $this->nextSequence('recus_orientation', 'numero_recu', 'REC', $year);

        for ($i = 0; $i < 50; $i++) {
            $numero = sprintf('REC-%s-%04d', $year, $base + $i);
            try {
                $this->db->prepare('
                    INSERT INTO recus_orientation (paiement_id, numero_recu, cle_pdf, date_emission)
                    VALUES (?, ?, "", NOW())
                ')->execute([$paiementId, $numero]);
                return $numero;
            } catch (\PDOException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
                $existing = $this->getRecu($paiementId);
                if ($existing && !empty($existing['numero_recu'])) {
                    return (string)$existing['numero_recu'];
                }
            }
        }
        throw new \RuntimeException('Reçu : impossible de réserver un numéro unique.');
    }

    public function updateRecuCle(int $paiementId, string $cle): void
    {
        $this->db->prepare(
            'UPDATE recus_orientation SET cle_pdf = ? WHERE paiement_id = ?'
        )->execute([$cle, $paiementId]);
    }

    public function getRecu(int $paiementId): ?array
    {
        $st = $this->db->prepare('SELECT * FROM recus_orientation WHERE paiement_id = ? LIMIT 1');
        $st->execute([$paiementId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ── Gestionnaire ─────────────────────────────────────────────────────────

    public function findByEtab(int $etabId, array $filters = []): array
    {
        $where  = ['d.etablissement_id = :etab'];
        $params = [':etab' => $etabId];

        if (!empty($filters['statut'])) {
            $where[]           = 'd.statut = :statut';
            $params[':statut'] = $filters['statut'];
        }
        if (!empty($filters['origine'])) {
            $where[]            = 'd.origine = :origine';
            $params[':origine'] = $filters['origine'];
        }
        if (!empty($filters['q'])) {
            // ATTR_EMULATE_PREPARES=false interdit de réutiliser le même placeholder
            $where[]       = '(d.nom_complet LIKE :q1 OR d.email LIKE :q2)';
            $params[':q1'] = '%' . $filters['q'] . '%';
            $params[':q2'] = '%' . $filters['q'] . '%';
        }

        $sql = 'SELECT d.*,
                       r.numero_recepisse,
                       i.id AS inscription_id,
                       p.statut AS paiement_statut, p.montant AS paiement_montant
                FROM dossiers_inscription d
                LEFT JOIN recepisses r ON r.dossier_id = d.id
                LEFT JOIN inscriptions i ON i.dossier_id = d.id
                LEFT JOIN paiements_orientation p ON p.dossier_id = d.id AND p.statut = "reussi"
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY d.date_creation DESC
                LIMIT 200';

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Bilan statistique d'une période : candidats ayant effectué une
     * préinscription (récépissé délivré) ou une inscription validée entre
     * deux dates incluses. Alimente l'export PDF du tableau de bord.
     *
     * @param string $type 'preinscription' | 'inscription'
     * @param string $du   Date de début (Y-m-d, incluse)
     * @param string $au   Date de fin   (Y-m-d, incluse)
     * @return array<int, array<string, mixed>> nom_complet, email,
     *         indicatif_tel, telephone, filiere_visee, niveau_entree, ville,
     *         origine, date_evenement — triés chronologiquement
     */
    public function bilanPeriode(int $etabId, string $type, string $du, string $au): array
    {
        if ($type === 'inscription') {
            $sql = 'SELECT d.nom_complet, d.email, d.indicatif_tel, d.telephone,
                           d.filiere_visee, d.niveau_entree, d.ville, d.origine,
                           i.date_inscription AS date_evenement
                    FROM inscriptions i
                    JOIN dossiers_inscription d ON d.id = i.dossier_id
                    WHERE i.etablissement_id = :etab
                      AND i.date_inscription >= :du
                      AND i.date_inscription < DATE_ADD(:au, INTERVAL 1 DAY)
                    ORDER BY i.date_inscription ASC
                    LIMIT 5000';
        } else {
            $sql = 'SELECT d.nom_complet, d.email, d.indicatif_tel, d.telephone,
                           d.filiere_visee, d.niveau_entree, d.ville, d.origine,
                           p.date_preinscription AS date_evenement
                    FROM preinscriptions p
                    JOIN dossiers_inscription d ON d.id = p.dossier_id
                    WHERE d.etablissement_id = :etab
                      AND p.date_preinscription >= :du
                      AND p.date_preinscription < DATE_ADD(:au, INTERVAL 1 DAY)
                    ORDER BY p.date_preinscription ASC
                    LIMIT 5000';
        }

        $st = $this->db->prepare($sql);
        $st->execute([':etab' => $etabId, ':du' => $du, ':au' => $au]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function statsForEtab(int $etabId): array
    {
        $st = $this->db->prepare('
            SELECT
              COUNT(*) AS nb_total,
              SUM(CASE WHEN statut = "soumis" THEN 1 ELSE 0 END) AS nb_preinscriptions,
              SUM(CASE WHEN statut = "paye" THEN 1 ELSE 0 END)   AS nb_payes,
              SUM(CASE WHEN statut != "brouillon" AND origine = "etablissement" THEN 1 ELSE 0 END) AS nb_guichet,
              SUM(CASE WHEN statut != "brouillon" AND origine = "en_ligne" THEN 1 ELSE 0 END) AS nb_enligne
            FROM dossiers_inscription
            WHERE etablissement_id = ?
        ');
        $st->execute([$etabId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        $st2 = $this->db->prepare('
            SELECT COALESCE(SUM(po.montant),0)
            FROM paiements_orientation po
            WHERE po.etablissement_id = ? AND po.statut = "reussi"
        ');
        $st2->execute([$etabId]);
        $row['revenus'] = (int)$st2->fetchColumn();

        $st3 = $this->db->prepare(
            'SELECT COUNT(*) FROM inscriptions WHERE etablissement_id = ?'
        );
        $st3->execute([$etabId]);
        $row['nb_inscriptions'] = (int)$st3->fetchColumn();

        $st4 = $this->db->prepare(
            'SELECT COUNT(*) FROM visites_etablissement WHERE etablissement_id = ?'
        );
        $st4->execute([$etabId]);
        $row['nb_visites'] = (int)$st4->fetchColumn();

        return $row;
    }

    /**
     * Candidatures déposées (statut ≠ brouillon) groupées par ville du candidat.
     * $etabId = null → toute la plateforme (dashboard super-admin),
     * sinon périmètre d'un établissement (dashboard gestionnaire).
     *
     * @return array<int, array{ville:string, n:int}> triées par volume décroissant
     */
    public function statsVilles(?int $etabId = null): array
    {
        $sql = '
            SELECT COALESCE(NULLIF(TRIM(ville), ""), "Non renseignée") AS ville,
                   COUNT(*) AS n
            FROM dossiers_inscription
            WHERE statut != "brouillon"';
        $params = [];
        if ($etabId !== null) {
            $sql     .= ' AND etablissement_id = ?';
            $params[] = $etabId;
        }
        $sql .= ' GROUP BY COALESCE(NULLIF(TRIM(ville), ""), "Non renseignée")
                  ORDER BY n DESC, ville ASC';

        $st = $this->db->prepare($sql);
        $st->execute($params);

        return array_map(
            static fn(array $r): array => ['ville' => (string)$r['ville'], 'n' => (int)$r['n']],
            $st->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /**
     * Croissance mensuelle : préinscriptions et inscriptions de l'établissement
     * sur les $mois derniers mois (bornes incluses), pour le graphique à deux
     * courbes du tableau de bord. Les mois sans activité sont remplis à 0 côté
     * PHP afin d'obtenir une série continue.
     *
     * @return array<int, array{mois:string, label:string, preinscriptions:int, inscriptions:int}>
     */
    public function croissanceMensuelle(int $etabId, int $mois = 12): array
    {
        $mois = max(2, min(24, $mois));

        // Squelette : un seau par mois, du plus ancien au mois courant.
        $moisFr = ['', 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin',
                   'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
        $buckets = [];
        $cursor  = new \DateTime('first day of this month 00:00:00');
        $cursor->modify('-' . ($mois - 1) . ' months');
        for ($i = 0; $i < $mois; $i++) {
            $key = $cursor->format('Y-m');
            $buckets[$key] = [
                'mois'            => $key,
                'label'          => $moisFr[(int)$cursor->format('n')] . ' ' . $cursor->format('y'),
                'preinscriptions' => 0,
                'inscriptions'    => 0,
            ];
            $cursor->modify('+1 month');
        }
        $depuis = array_key_first($buckets) . '-01';

        // Préinscriptions par mois
        $stPre = $this->db->prepare('
            SELECT DATE_FORMAT(p.date_preinscription, "%Y-%m") AS ym, COUNT(*) AS n
            FROM preinscriptions p
            JOIN dossiers_inscription d ON d.id = p.dossier_id
            WHERE d.etablissement_id = :etab
              AND p.date_preinscription >= :depuis
            GROUP BY ym
        ');
        $stPre->execute([':etab' => $etabId, ':depuis' => $depuis]);
        foreach ($stPre->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if (isset($buckets[$r['ym']])) $buckets[$r['ym']]['preinscriptions'] = (int)$r['n'];
        }

        // Inscriptions par mois
        $stIns = $this->db->prepare('
            SELECT DATE_FORMAT(date_inscription, "%Y-%m") AS ym, COUNT(*) AS n
            FROM inscriptions
            WHERE etablissement_id = :etab
              AND date_inscription >= :depuis
            GROUP BY ym
        ');
        $stIns->execute([':etab' => $etabId, ':depuis' => $depuis]);
        foreach ($stIns->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if (isset($buckets[$r['ym']])) $buckets[$r['ym']]['inscriptions'] = (int)$r['n'];
        }

        return array_values($buckets);
    }

    public function getDocumentsByDossier(int $dossierId): array
    {
        $st = $this->db->prepare('
            SELECT doc.*, pj.libelle AS piece_libelle, pj.obligatoire
            FROM documents_orientation doc
            LEFT JOIN pieces_justificatives_requises pj ON pj.id = doc.piece_requise_id
            WHERE doc.dossier_id = ?
            ORDER BY doc.id ASC
        ');
        $st->execute([$dossierId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Une pièce justificative par son identifiant, avec le dossier auquel elle
     * appartient — l'appelant vérifie ensuite la propriété du dossier avant de
     * servir le fichier (jamais l'inverse).
     */
    public function findDocumentById(int $docId): ?array
    {
        $st = $this->db->prepare('
            SELECT doc.*, d.etablissement_id, d.eleve_id, d.email AS dossier_email
            FROM documents_orientation doc
            JOIN dossiers_inscription d ON d.id = doc.dossier_id
            WHERE doc.id = ?
            LIMIT 1
        ');
        $st->execute([$docId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateStatutByEtab(int $id, int $etabId, string $statut): bool
    {
        $allowed = ['soumis','paye','accepte','inscrit','refuse'];
        if (!in_array($statut, $allowed, true)) return false;
        $this->db->prepare(
            'UPDATE dossiers_inscription SET statut = ? WHERE id = ? AND etablissement_id = ?'
        )->execute([$statut, $id, $etabId]);
        return true;
    }

    public function createGuichet(array $d, int $etabId, int $agentId): int
    {
        $st = $this->db->prepare('
            INSERT INTO dossiers_inscription
              (etablissement_id, filiere_visee, specialite_visee, niveau_entree,
               nom_complet, genre, date_naissance, lieu_naissance, nationalite,
               email, indicatif_tel, telephone, adresse, ville, pays_provenance,
               dernier_etablissement, origine, cree_par_user_id, statut)
            VALUES
              (:etab, :filiere, :specialite, :niveau,
               :nom, :genre, :naissance, :lieu, :nationalite,
               :email, :indicatif, :tel, :adresse, :ville, :pays,
               :dernier, "etablissement", :agent, :statut)
        ');
        $st->execute([
            ':etab'        => $etabId,
            ':filiere'     => $d['filiere_visee'] ?? null,
            ':specialite'  => $d['specialite_visee'] ?? null,
            ':niveau'      => $d['niveau_entree'] ?? null,
            ':nom'         => $d['nom_complet'],
            ':genre'       => $d['genre'],
            ':naissance'   => $d['date_naissance'],
            ':lieu'        => $d['lieu_naissance'],
            ':nationalite' => $d['nationalite'],
            ':email'       => $d['email'],
            ':indicatif'   => $d['indicatif_tel'] ?? '+241',
            ':tel'         => $d['telephone'],
            ':adresse'     => $d['adresse'] ?? null,
            ':ville'       => $d['ville'] ?? null,
            ':pays'        => $d['pays_provenance'] ?? null,
            ':dernier'     => $d['dernier_etablissement'] ?? null,
            ':agent'       => $agentId,
            ':statut'      => 'soumis',
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function createPaiementHorsligne(int $dossierId, int $etabId, int $montant, string $mode, int $agentId): int
    {
        $st = $this->db->prepare('
            INSERT INTO paiements_orientation
              (dossier_id, etablissement_id, montant, mode, statut, enregistre_par_user_id, date_confirmation)
            VALUES
              (:dossier, :etab, :montant, :mode, "reussi", :agent, NOW())
        ');
        $st->execute([
            ':dossier'  => $dossierId,
            ':etab'     => $etabId,
            ':montant'  => $montant,
            ':mode'     => $mode,
            ':agent'    => $agentId,
        ]);
        return (int)$this->db->lastInsertId();
    }

    // ── Espace élève ──────────────────────────────────────────────────────────

    /**
     * Rattache au compte $userId tous les dossiers créés avec son email
     * avant l'existence du compte (préinscriptions anonymes).
     * Idempotent — appelé à la création de compte et à chaque connexion.
     */
    public function claimByEmail(int $userId, string $email): int
    {
        if ($userId <= 0 || $email === '') {
            return 0;
        }
        $st = $this->db->prepare('
            UPDATE dossiers_inscription
            SET eleve_id = :uid
            WHERE email = :email AND (eleve_id IS NULL OR eleve_id = 0)
        ');
        $st->execute([':uid' => $userId, ':email' => $email]);
        return $st->rowCount();
    }

    public function countByUserContext(int $userId, string $email): array
    {
        $st = $this->db->prepare('
            SELECT
              COUNT(*) AS total,
              SUM(CASE WHEN statut = "soumis" THEN 1 ELSE 0 END)            AS en_attente,
              SUM(CASE WHEN statut IN ("paye","accepte") THEN 1 ELSE 0 END) AS confirmes,
              SUM(CASE WHEN statut = "refuse" THEN 1 ELSE 0 END)            AS refuses
            FROM dossiers_inscription
            WHERE (eleve_id = :uid OR email = :email) AND statut != "brouillon"
        ');
        $st->execute([':uid' => $userId, ':email' => $email]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'total'      => (int)($row['total'] ?? 0),
            'en_attente' => (int)($row['en_attente'] ?? 0),
            'confirmes'  => (int)($row['confirmes'] ?? 0),
            'refuses'    => (int)($row['refuses'] ?? 0),
        ];
    }

    public function findByUserContext(int $userId, string $email): array
    {
        $st = $this->db->prepare('
            SELECT d.*,
                   e.nom AS etab_nom, e.slug AS etab_slug, e.couleur_principale, e.logo_cle,
                   r.numero_recepisse, r.cle_pdf AS recepisse_cle, r.date_emission AS recepisse_date,
                   rec.numero_recu, rec.cle_pdf AS recu_cle,
                   i.id AS inscription_id,
                   a.numero_attestation, a.cle_pdf AS attestation_cle, a.date_emission AS attestation_date,
                   p.statut AS paiement_statut, p.montant AS paiement_montant, p.date_confirmation AS paiement_date
            FROM dossiers_inscription d
            JOIN etablissements e ON e.id = d.etablissement_id
            LEFT JOIN recepisses r ON r.dossier_id = d.id
            LEFT JOIN inscriptions i ON i.dossier_id = d.id
            LEFT JOIN attestations_inscription a ON a.dossier_id = d.id
            LEFT JOIN paiements_orientation p ON p.dossier_id = d.id AND p.statut = "reussi"
            LEFT JOIN recus_orientation rec ON rec.paiement_id = p.id
            WHERE (d.eleve_id = :uid OR d.email = :email)
            ORDER BY d.date_creation DESC
        ');
        $st->execute([':uid' => $userId, ':email' => $email]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Tous les paiements (tous statuts) des dossiers de l'utilisateur,
     * avec le reçu éventuel — pour les onglets Paiements / Reçus de l'espace élève.
     */
    public function findPaiementsByUserContext(int $userId, string $email): array
    {
        $st = $this->db->prepare('
            SELECT p.*,
                   d.id AS dossier_id, d.filiere_visee, d.niveau_entree, d.statut AS dossier_statut,
                   e.nom AS etab_nom, e.slug AS etab_slug, e.couleur_principale,
                   i.id AS inscription_id,
                   rec.numero_recu, rec.cle_pdf AS recu_cle, rec.date_emission AS recu_date
            FROM paiements_orientation p
            JOIN dossiers_inscription d ON d.id = p.dossier_id
            JOIN etablissements e ON e.id = d.etablissement_id
            LEFT JOIN inscriptions i ON i.dossier_id = d.id
            LEFT JOIN recus_orientation rec ON rec.paiement_id = p.id
            WHERE (d.eleve_id = :uid OR d.email = :email)
            ORDER BY p.date_paiement DESC
        ');
        $st->execute([':uid' => $userId, ':email' => $email]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Toutes les pièces déposées sur les dossiers de l'utilisateur,
     * groupables par dossier — pour l'onglet Documents de l'espace élève.
     */
    public function findDocumentsByUserContext(int $userId, string $email): array
    {
        $st = $this->db->prepare('
            SELECT doc.*,
                   pj.libelle AS piece_libelle, pj.obligatoire,
                   d.id AS dossier_id, d.statut AS dossier_statut,
                   e.nom AS etab_nom, e.couleur_principale
            FROM documents_orientation doc
            JOIN dossiers_inscription d ON d.id = doc.dossier_id
            JOIN etablissements e ON e.id = d.etablissement_id
            LEFT JOIN pieces_justificatives_requises pj ON pj.id = doc.piece_requise_id
            WHERE (d.eleve_id = :uid OR d.email = :email)
            ORDER BY doc.date_depot DESC, doc.id DESC
        ');
        $st->execute([':uid' => $userId, ':email' => $email]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByIdWithOwnership(int $id, int $userId, string $email): ?array
    {
        $st = $this->db->prepare('
            SELECT d.*,
                   e.nom AS etab_nom, e.slug AS etab_slug, e.couleur_principale, e.logo_cle,
                   r.numero_recepisse, r.cle_pdf AS recepisse_cle,
                   rec.numero_recu, rec.cle_pdf AS recu_cle,
                   i.id AS inscription_id,
                   a.numero_attestation, a.cle_pdf AS attestation_cle,
                   p.statut AS paiement_statut, p.montant AS paiement_montant,
                   f.intitule AS formation_intitule
            FROM dossiers_inscription d
            JOIN etablissements e ON e.id = d.etablissement_id
            LEFT JOIN recepisses r ON r.dossier_id = d.id
            LEFT JOIN inscriptions i ON i.dossier_id = d.id
            LEFT JOIN attestations_inscription a ON a.dossier_id = d.id
            LEFT JOIN paiements_orientation p ON p.dossier_id = d.id AND p.statut = "reussi"
            LEFT JOIN recus_orientation rec ON rec.paiement_id = p.id
            LEFT JOIN formations f ON f.id = d.formation_id
            WHERE d.id = :id AND (d.eleve_id = :uid OR d.email = :email)
            LIMIT 1
        ');
        $st->execute([':id' => $id, ':uid' => $userId, ':email' => $email]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
