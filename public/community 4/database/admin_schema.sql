-- ============================================================
-- StudyLink — Schéma Admin Additionnel
-- À exécuter APRÈS schema.sql
-- ============================================================

SET NAMES utf8mb4;

USE studylink_db;

-- ── JOURNAL DES CONNEXIONS ADMIN ────────────────────────────
CREATE TABLE IF NOT EXISTS `admin_logins` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `user_id`       INT UNSIGNED    NOT NULL,
    `ip_address`    VARCHAR(45)     NOT NULL,
    `user_agent`    VARCHAR(500)    DEFAULT NULL,
    `status`        ENUM('success','failed') NOT NULL DEFAULT 'success',
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    INDEX `idx_admin_logins_user` (`user_id`),
    INDEX `idx_admin_logins_date` (`created_at` DESC),

    CONSTRAINT `fk_admin_logins_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

