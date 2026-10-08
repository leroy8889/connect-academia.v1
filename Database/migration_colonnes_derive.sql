-- ============================================================================
-- Connect'Academia — Rattrapage des colonnes ajoutées hors migration
-- ----------------------------------------------------------------------------
-- IDEMPOTENTE : rejouable sans erreur (procédure de garde `ca_add_col_drv`).
--
-- POURQUOI CE FICHIER
-- ===================
-- Neuf colonnes existent sur la base de développement, sont lues et écrites
-- par le code applicatif, et n'ont JAMAIS eu de fichier de migration : elles
-- ont été ajoutées à la main. Si la production ne les a pas, les écrans qui
-- les utilisent tombent en erreur SQL. Si elle les a déjà, ce fichier ne fait
-- rien — la garde ne pose que ce qui manque.
--
-- Le détail, colonne par colonne :
--   dossiers_inscription.serie_bac / annee_bac
--       Saisis au guichet et sur le formulaire public de préinscription
--       (CandidatureController, CandidatureGestionnaireController).
--   etablissements.cachet_cle
--       Cachet de l'établissement imprimé sur le récépissé et la proforma
--       (RecepisseService, FactureProformaService).
--   filieres_etablissement.frais_inscription / frais_scolarite
--       Dernier repli de la résolution tarifaire (TarifCycle) quand ni la
--       grille `tarifs_formation` ni la fiche établissement ne portent de
--       montant. NOT NULL DEFAULT 0 = inactif tant que rien n'est saisi.
--   formations.description / debouches / avantages / image_cle
--       Contenu éditorial des fiches formation, affiché par PublicController.
--
-- ⚠️ À REJOUER EN PRODUCTION :
--   mysql -u <user> -p <base> < Database/migration_colonnes_derive.sql
-- ============================================================================

SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS ca_add_col_drv;
DELIMITER $$
CREATE PROCEDURE ca_add_col_drv(IN p_table VARCHAR(64), IN p_col VARCHAR(64), IN p_ddl TEXT)
BEGIN
  IF EXISTS (
    SELECT 1 FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table
  ) AND NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_col
  ) THEN
    SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN ', p_ddl);
    PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
  END IF;
END$$
DELIMITER ;

-- ── Baccalauréat du candidat ────────────────────────────────────────────────
CALL ca_add_col_drv('dossiers_inscription', 'serie_bac',
  "`serie_bac` VARCHAR(20) NULL DEFAULT NULL
     COMMENT 'Serie du baccalaureat declaree par le candidat'
     AFTER `dernier_etablissement`");

CALL ca_add_col_drv('dossiers_inscription', 'annee_bac',
  "`annee_bac` SMALLINT NULL DEFAULT NULL
     COMMENT 'Annee d''obtention du baccalaureat'
     AFTER `serie_bac`");

-- ── Cachet de l'établissement (récépissé, proforma) ─────────────────────────
CALL ca_add_col_drv('etablissements', 'cachet_cle',
  "`cachet_cle` VARCHAR(255) NULL DEFAULT NULL
     COMMENT 'Cle de stockage du cachet imprime sur les documents'
     AFTER `logo_cle`");

-- ── Tarifs de repli portés par la filière ───────────────────────────────────
CALL ca_add_col_drv('filieres_etablissement', 'frais_inscription',
  "`frais_inscription` INT NOT NULL DEFAULT 0
     COMMENT 'Repli tarifaire par filiere — 0 = inactif'
     AFTER `nom`");

CALL ca_add_col_drv('filieres_etablissement', 'frais_scolarite',
  "`frais_scolarite` INT NOT NULL DEFAULT 0
     COMMENT 'Repli tarifaire par filiere — 0 = inactif'
     AFTER `frais_inscription`");

-- ── Contenu éditorial des fiches formation ──────────────────────────────────
CALL ca_add_col_drv('formations', 'description',
  "`description` TEXT NULL DEFAULT NULL AFTER `icone`");

CALL ca_add_col_drv('formations', 'debouches',
  "`debouches` TEXT NULL DEFAULT NULL AFTER `prix_unite`");

CALL ca_add_col_drv('formations', 'avantages',
  "`avantages` TEXT NULL DEFAULT NULL AFTER `debouches`");

CALL ca_add_col_drv('formations', 'image_cle',
  "`image_cle` VARCHAR(255) NULL DEFAULT NULL AFTER `avantages`");

DROP PROCEDURE IF EXISTS ca_add_col_drv;

-- ============================================================================
-- VÉRIFICATION
--   SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS
--    WHERE TABLE_SCHEMA = DATABASE()
--      AND ((TABLE_NAME='dossiers_inscription'   AND COLUMN_NAME IN ('serie_bac','annee_bac'))
--        OR (TABLE_NAME='etablissements'         AND COLUMN_NAME='cachet_cle')
--        OR (TABLE_NAME='filieres_etablissement' AND COLUMN_NAME IN ('frais_inscription','frais_scolarite'))
--        OR (TABLE_NAME='formations'             AND COLUMN_NAME IN ('description','debouches','avantages','image_cle')));
--   → 9 lignes attendues
-- ============================================================================
