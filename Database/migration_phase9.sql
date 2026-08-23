-- ============================================================
-- Connect'Academia — Migration Phase 9 (Finitions & Sécurité)
-- Module Orientation
-- ============================================================
-- À exécuter sur connect_academia1 APRÈS migration_orientation_v1.sql
-- Compatible MySQL 8.0 (pas de IF NOT EXISTS sur index/colonnes) :
-- idempotence assurée via information_schema + PREPARE/EXECUTE.

-- ① Journal d'audit des actions sensibles
CREATE TABLE IF NOT EXISTS audit_log (
  id         INT PRIMARY KEY AUTO_INCREMENT,
  action     VARCHAR(100) NOT NULL,
  entity     VARCHAR(60)  NOT NULL,
  entity_id  INT          NOT NULL,
  user_id    INT,
  context    JSON,
  ip         VARCHAR(45),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_entity  (entity, entity_id),
  INDEX idx_action  (action),
  INDEX idx_user    (user_id),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ② Index composites pour requêtes marketplace (EXPLAIN vérifié)
SET @idx := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = 'etablissements'
               AND index_name = 'idx_etab_statut_type');
SET @sql := IF(@idx = 0,
  'CREATE INDEX idx_etab_statut_type ON etablissements(statut, type)',
  'SELECT ''idx_etab_statut_type déjà présent''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = 'dossiers_inscription'
               AND index_name = 'idx_dossier_etab_statut');
SET @sql := IF(@idx = 0,
  'CREATE INDEX idx_dossier_etab_statut ON dossiers_inscription(etablissement_id, statut)',
  'SELECT ''idx_dossier_etab_statut déjà présent''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = 'paiements_orientation'
               AND index_name = 'idx_paie_etab_statut_date');
SET @sql := IF(@idx = 0,
  'CREATE INDEX idx_paie_etab_statut_date ON paiements_orientation(etablissement_id, statut, date_paiement)',
  'SELECT ''idx_paie_etab_statut_date déjà présent''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = 'reversements'
               AND index_name = 'idx_rev_statut_date');
SET @sql := IF(@idx = 0,
  'CREATE INDEX idx_rev_statut_date ON reversements(statut, date_initiation)',
  'SELECT ''idx_rev_statut_date déjà présent''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ③ Colonne de contexte pour audit reversement (si absente)
SET @col := (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'etablissement_paiement'
               AND column_name = 'updated_at');
SET @sql := IF(@col = 0,
  'ALTER TABLE etablissement_paiement ADD COLUMN updated_at DATETIME DEFAULT NULL AFTER date_connexion',
  'SELECT ''updated_at déjà présente''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
