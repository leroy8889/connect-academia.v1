-- ============================================================================
-- Connect'Academia — Frais d'inscription distincts des frais de scolarité
-- ----------------------------------------------------------------------------
-- IDEMPOTENTE : rejouable sans erreur (procédure de garde `ca_add_col`).
--
-- POURQUOI
-- --------
-- La validation à 100 % ne captait qu'UN seul montant encaissé
-- (`montant_encaisse_initial`), présenté comme un acompte sur la scolarité.
-- Or l'agent de guichet encaisse deux choses le même jour, de nature
-- différente :
--
--   · les FRAIS D'INSCRIPTION   — dus une fois, non remboursables, acquis à
--                                 l'établissement, sans rapport avec la
--                                 scolarité annuelle ;
--   · un VERSEMENT SUR SCOLARITÉ — acompte imputé sur le montant annuel.
--
-- Tout mettre dans la même colonne produisait deux erreurs visibles sur le
-- reçu remis au candidat :
--   1. le « reste à régler » de la scolarité était minoré du montant des frais
--      d'inscription, qui n'en font pas partie ;
--   2. le candidat n'avait aucune trace écrite du règlement de ses frais
--      d'inscription.
--
-- La commission de la plateforme reste calculée sur la SEULE scolarité
-- annuelle (`montant_scolarite_annuelle`) : elle n'est pas touchée par cette
-- migration, et les commissions déjà émises gardent leur base de calcul.
--
-- REPRISE DE DONNÉES — volontairement ABSENTE
-- -------------------------------------------
-- La colonne est laissée à NULL sur les dossiers existants. Impossible de
-- deviner rétroactivement quelle part d'un `montant_encaisse_initial` déjà
-- saisi correspondait aux frais d'inscription : inventer une répartition
-- fausserait des reçus déjà remis. NULL se lit « non renseigné », et le reçu
-- masque alors la ligne au lieu d'afficher un zéro trompeur.
-- ============================================================================

SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS ca_add_col;
DELIMITER $$
CREATE PROCEDURE ca_add_col(IN p_table VARCHAR(64), IN p_col VARCHAR(64), IN p_ddl TEXT)
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

CALL ca_add_col(
  'dossiers_inscription',
  'montant_frais_inscription',
  "`montant_frais_inscription` DECIMAL(12,2) NULL DEFAULT NULL
     COMMENT 'Frais d''inscription encaissés au guichet — distincts de la scolarité, hors base de commission'
     AFTER `montant_encaisse_initial`"
);

DROP PROCEDURE IF EXISTS ca_add_col;

-- ── Vérification ────────────────────────────────────────────────────────────
-- SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE
--   FROM information_schema.COLUMNS
--  WHERE TABLE_SCHEMA = DATABASE()
--    AND TABLE_NAME = 'dossiers_inscription'
--    AND COLUMN_NAME = 'montant_frais_inscription';
