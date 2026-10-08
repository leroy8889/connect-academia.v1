-- ============================================================================
-- Connect'Academia — Tranches automatiques, abandon d'année, pièces remplacées
-- ----------------------------------------------------------------------------
-- IDEMPOTENTE : rejouable sans erreur (procédures de garde `ca_add_col2`).
--
-- Trois besoins métier, un seul passage sur le schéma :
--
--  1. TRANCHES AUTOMATIQUES
--     La commission naît désormais toute seule à la validation à 100 %
--     (Services\Orientation\CommissionDossier). Elle doit naître AVEC ses trois
--     tranches 40/30/30, que le super administrateur encaisse une par une.
--     Les colonnes ajoutées ici tracent CHAQUE encaissement : sa date, son
--     auteur, sa référence — sinon « réglée » ne serait qu'une case cochée sans
--     preuve.
--
--  2. ABANDON D'ANNÉE (décision du SUPER ADMINISTRATEUR, jamais de l'établissement)
--     Un étudiant inscrit à 100 % peut abandonner en cours d'année. Les tranches
--     non encaissées ne le seront jamais. L'établissement signale la situation,
--     le super administrateur la constate : c'est lui qui ferme la créance.
--     On n'ajoute PAS d'état à la machine du dossier — l'inscription a bien eu
--     lieu, l'attestation reste valable, le passé ne se réécrit pas. L'abandon
--     est un FAIT DATÉ posé sur le dossier, et une clôture sur la commission.
--
--  3. PIÈCE REFUSÉE → REMPLAÇABLE
--     Une pièce refusée est remplacée par une nouvelle ligne (l'historique du
--     refus doit rester lisible). `remplace_document_id` relie la remplaçante à
--     la refusée : sans ce lien, le back-office verrait deux pièces concurrentes
--     sans savoir laquelle fait foi.
--
-- ⚠️ À REJOUER EN PRODUCTION :
--   mysql -u <user> -p <base> < Database/migration_tranches_abandon.sql
-- ============================================================================

SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS ca_add_col2;
DELIMITER $$
CREATE PROCEDURE ca_add_col2(IN p_table VARCHAR(64), IN p_col VARCHAR(64), IN p_ddl TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_col
  ) THEN
    SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN ', p_ddl);
    PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
  END IF;
END$$
DELIMITER ;

DROP PROCEDURE IF EXISTS ca_add_idx2;
DELIMITER $$
CREATE PROCEDURE ca_add_idx2(IN p_table VARCHAR(64), IN p_idx VARCHAR(64), IN p_ddl TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND INDEX_NAME = p_idx
  ) THEN
    SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD ', p_ddl);
    PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
  END IF;
END$$
DELIMITER ;


-- ============================================================================
-- 1. COMMISSIONS — deux statuts de plus
-- ----------------------------------------------------------------------------
-- `partiellement_reglee` : au moins une tranche encaissée, pas toutes. Sans lui,
--   une commission à 40 % encaissée s'afficherait « facturée », comme si rien
--   n'était rentré.
-- `abandonnee` : l'étudiant a quitté l'année. Distinct d'`annulee`, qui vise une
--   inscription défaite par erreur : ici l'inscription a bien eu lieu, et les
--   tranches déjà encaissées restent dues et acquises.
-- Les valeurs héritées ('en_cours','solde') restent dans l'ENUM : aucune lecture
-- existante ne casse.
-- ============================================================================
ALTER TABLE `commissions_scolarite`
  MODIFY COLUMN `statut` ENUM(
    'a_facturer','facturee','partiellement_reglee','reglee','annulee','abandonnee',
    'en_cours','solde'
  ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'a_facturer';

CALL ca_add_col2('commissions_scolarite', 'abandon_le',
  "`abandon_le` DATETIME NULL DEFAULT NULL COMMENT 'Constat d abandon par le super administrateur'");
CALL ca_add_col2('commissions_scolarite', 'abandon_motif',
  "`abandon_motif` VARCHAR(500) NULL DEFAULT NULL");
CALL ca_add_col2('commissions_scolarite', 'abandon_par',
  "`abandon_par` INT NULL DEFAULT NULL COMMENT 'admins.id'");


-- ============================================================================
-- 2. TRANCHES — traçabilité de l'encaissement et clôture par abandon
-- ============================================================================
CALL ca_add_col2('commission_tranches', 'reference_reglement',
  "`reference_reglement` VARCHAR(80) NULL DEFAULT NULL COMMENT 'N° de reçu / transfert justifiant l encaissement'");
CALL ca_add_col2('commission_tranches', 'est_abandonnee',
  "`est_abandonnee` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = ne sera jamais encaissée (abandon d année)'");
CALL ca_add_col2('commission_tranches', 'abandon_le',
  "`abandon_le` DATETIME NULL DEFAULT NULL");
CALL ca_add_idx2('commission_tranches', 'idx_tranche_etat',
  'KEY `idx_tranche_etat` (`commission_id`,`est_payee`,`est_abandonnee`)');


-- ============================================================================
-- 3. DOSSIERS — abandon de l'année universitaire
-- ----------------------------------------------------------------------------
-- Aucune valeur ajoutée à l'ENUM `statut` : l'inscription a EU LIEU. L'effacer
-- invaliderait rétroactivement une attestation remise à l'étudiant et un reçu
-- de scolarité déjà encaissé. L'abandon se lit sur ces trois colonnes.
-- ============================================================================
CALL ca_add_col2('dossiers_inscription', 'abandon_le',
  "`abandon_le` DATETIME NULL DEFAULT NULL COMMENT 'Abandon d année constaté par le super administrateur'");
CALL ca_add_col2('dossiers_inscription', 'abandon_par',
  "`abandon_par` INT NULL DEFAULT NULL COMMENT 'admins.id'");
CALL ca_add_col2('dossiers_inscription', 'abandon_motif',
  "`abandon_motif` VARCHAR(500) NULL DEFAULT NULL");
CALL ca_add_idx2('dossiers_inscription', 'idx_dossier_abandon',
  'KEY `idx_dossier_abandon` (`etablissement_id`,`abandon_le`)');


-- ============================================================================
-- 4. DOCUMENTS — chaînage d'une pièce remplaçante vers la pièce refusée
-- ============================================================================
CALL ca_add_col2('documents_orientation', 'remplace_document_id',
  "`remplace_document_id` INT NULL DEFAULT NULL COMMENT 'Document refusé que cette pièce remplace'");
CALL ca_add_idx2('documents_orientation', 'idx_doc_remplace',
  'KEY `idx_doc_remplace` (`remplace_document_id`)');


-- ============================================================================
-- 5. REPRISE — échéancier des commissions déjà existantes
-- ----------------------------------------------------------------------------
-- Les commissions nées avant cette migration n'ont pas de tranches : sans
-- reprise, l'écran de suivi afficherait « échéancier absent » et le super
-- administrateur n'aurait rien à encaisser dessus.
--
-- Seules les commissions SANS AUCUNE tranche sont reprises : celles créées à la
-- main depuis « Gestion des utilisateurs » en ont déjà, et les recréer
-- violerait l'unicité (commission_id, numero).
-- ============================================================================
DROP TEMPORARY TABLE IF EXISTS ca_tmp_sans_tranches;
CREATE TEMPORARY TABLE ca_tmp_sans_tranches AS
SELECT c.id AS commission_id, c.montant_commission
FROM commissions_scolarite c
LEFT JOIN commission_tranches t ON t.commission_id = c.id
WHERE t.id IS NULL
  AND c.statut <> 'annulee'
  AND c.montant_commission > 0;

INSERT INTO commission_tranches (commission_id, numero, pourcentage, montant)
SELECT commission_id, 1, 40.00, ROUND(montant_commission * 0.40, 2)
FROM ca_tmp_sans_tranches;

INSERT INTO commission_tranches (commission_id, numero, pourcentage, montant)
SELECT commission_id, 2, 30.00, ROUND(montant_commission * 0.30, 2)
FROM ca_tmp_sans_tranches;

-- La dernière tranche prend le RESTE, pas 30 % recalculés : trois arrondis
-- successifs peuvent faire diverger la somme de quelques francs, et un
-- établissement solderait sans jamais atteindre le total.
INSERT INTO commission_tranches (commission_id, numero, pourcentage, montant)
SELECT commission_id, 3, 30.00,
       ROUND(montant_commission
             - ROUND(montant_commission * 0.40, 2)
             - ROUND(montant_commission * 0.30, 2), 2)
FROM ca_tmp_sans_tranches;

DROP TEMPORARY TABLE ca_tmp_sans_tranches;

-- Une commission déjà réglée doit avoir ses trois tranches encaissées : lui
-- créer un échéancier vierge ferait réapparaître une dette payée.
UPDATE commission_tranches t
JOIN commissions_scolarite c ON c.id = t.commission_id
SET t.est_payee     = 1,
    t.date_paiement = COALESCE(t.date_paiement, c.regle_le, c.updated_at)
WHERE c.statut IN ('reglee', 'solde')
  AND t.est_payee = 0;

-- Vocabulaire de statut unifié : les deux écrans de suivi lisent la même
-- colonne, avec les mêmes valeurs.
UPDATE commissions_scolarite SET statut = 'a_facturer' WHERE statut = 'en_cours';
UPDATE commissions_scolarite SET statut = 'reglee'     WHERE statut = 'solde';

-- Commissions partiellement encaissées (reprise du flux manuel) : le statut
-- doit refléter l'argent réellement rentré.
UPDATE commissions_scolarite c
SET c.statut = 'partiellement_reglee'
WHERE c.statut IN ('a_facturer', 'facturee')
  AND EXISTS (SELECT 1 FROM commission_tranches t WHERE t.commission_id = c.id AND t.est_payee = 1)
  AND EXISTS (SELECT 1 FROM commission_tranches t WHERE t.commission_id = c.id AND t.est_payee = 0);


DROP PROCEDURE IF EXISTS ca_add_col2;
DROP PROCEDURE IF EXISTS ca_add_idx2;
