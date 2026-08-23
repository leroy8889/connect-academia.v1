-- ════════════════════════════════════════════════════════════════════
-- Migration : option « génération d'attestation d'inscription »
-- ────────────────────────────────────────────────────────────────────
-- Symétrique de genere_recepisse. Un établissement peut désactiver la
-- délivrance de l'attestation d'inscription depuis ses paramètres. Par
-- défaut activé (1) : le système permet d'en générer autant que voulu.
--
-- Idempotente et compatible MySQL 8 (qui NE supporte PAS
-- « ADD COLUMN IF NOT EXISTS ») : on teste information_schema puis on
-- exécute l'ALTER seulement si la colonne manque.
-- À REJOUER EN PRODUCTION.
-- ════════════════════════════════════════════════════════════════════

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'etablissements'
    AND COLUMN_NAME  = 'genere_attestation'
);

SET @ddl := IF(@col_exists = 0,
  'ALTER TABLE `etablissements` ADD COLUMN `genere_attestation` TINYINT(1) NOT NULL DEFAULT 1 AFTER `genere_recepisse`',
  'DO 0'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
