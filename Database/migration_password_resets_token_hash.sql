-- ============================================================================
-- Migration : password_resets — passage au token HACHÉ (SHA-256)
--
-- CONTEXTE DU BUG (front-office, prod, juil. 2026)
-- ------------------------------------------------------------------
-- Le code sécurité (`AuthController::forgot`) stocke le token de
-- réinitialisation HACHÉ dans `password_resets.token_hash` et n'écrit plus les
-- colonnes historiques `token` / `is_used` / `expires_at`.
--
-- Sur une base restée au schéma d'origine :
--   · `token_hash` n'existe pas          → colonne inconnue
--   · `token` et `expires_at` sont NOT NULL sans valeur par défaut → violation
-- L'INSERT échouait donc, l'exception était avalée par le catch anti-énumération
-- (réponse volontairement identique qu'un compte existe ou non) et AUCUN email
-- n'était envoyé : côté utilisateur, « je valide et il ne se passe rien ».
--
-- Cette migration aligne la table sur le code. Elle est IDEMPOTENTE : elle peut
-- être rejouée sans erreur, y compris sur une base déjà migrée.
--
--   mysql -u <user> -p <base> < Database/migration_password_resets_token_hash.sql
--
-- Les tokens en cours sont invalidés (ils sont de toute façon valables 1 h) :
-- c'est volontaire, un token en clair ne doit pas survivre à la migration.
-- ============================================================================

-- Aucune demande en cours ne doit survivre au changement de format.
DELETE FROM `password_resets`;

-- 1. Ajout de `token_hash` s'il manque -------------------------------------
SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `password_resets`
       ADD COLUMN `token_hash` VARCHAR(64) NOT NULL DEFAULT ''''
         COMMENT ''SHA-256 du token de réinitialisation (jamais le token en clair)''
         AFTER `email`',
    'DO 0'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'password_resets'
    AND COLUMN_NAME  = 'token_hash'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. Index de recherche sur le hash ----------------------------------------
SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `password_resets` ADD INDEX `idx_pwreset_token_hash` (`token_hash`)',
    'DO 0'
  )
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'password_resets'
    AND INDEX_NAME   = 'idx_pwreset_token_hash'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. Suppression des colonnes héritées -------------------------------------
--    (`token` en clair, `is_used` et `expires_at` remplacés par la fenêtre
--     glissante d'1 h calculée sur `created_at`)
SET @sql := (
  SELECT IF(
    COUNT(*) > 0,
    'ALTER TABLE `password_resets` DROP INDEX `idx_pwreset_token`',
    'DO 0'
  )
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'password_resets'
    AND INDEX_NAME   = 'idx_pwreset_token'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) > 0,
    CONCAT('ALTER TABLE `password_resets` ',
           GROUP_CONCAT(CONCAT('DROP COLUMN `', COLUMN_NAME, '`') SEPARATOR ', ')),
    'DO 0'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'password_resets'
    AND COLUMN_NAME IN ('token', 'is_used', 'expires_at')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
