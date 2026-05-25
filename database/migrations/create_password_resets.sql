-- ============================================================
-- Migration : table password_resets
-- Connect'Academia — Réinitialisation de mot de passe
--
-- Exécuter une seule fois :
--   mysql -u root -p connect_academia1 < create_password_resets.sql
-- ============================================================

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `email`      VARCHAR(255)  NOT NULL                    COMMENT 'Email du compte concerné',
  `token_hash` VARCHAR(64)   NOT NULL                    COMMENT 'SHA-256 du token brut envoyé par email',
  `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE  KEY `uk_email`      (`email`),
  KEY          `idx_token`    (`token_hash`),
  KEY          `idx_created`  (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tokens de réinitialisation de mot de passe (usage unique, TTL 1h)';
