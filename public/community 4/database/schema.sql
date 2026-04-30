-- ============================================================
-- StudyLink — Schéma de Base de Données
-- MySQL 8.x / MariaDB 10.x
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS studylink_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE studylink_db;

-- ── UTILISATEURS ──────────────────────────────────────────
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id`                INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `uuid`              CHAR(36)            NOT NULL,
    `nom`               VARCHAR(100)        NOT NULL,
    `prenom`            VARCHAR(100)        NOT NULL,
    `email`             VARCHAR(255)        NOT NULL,
    `password_hash`     VARCHAR(255)        NOT NULL,
    `photo_profil`      VARCHAR(500)        DEFAULT NULL,
    `bio`               VARCHAR(160)        DEFAULT NULL,
    `role`              ENUM('eleve','enseignant','admin') NOT NULL DEFAULT 'eleve',
    `classe`            VARCHAR(50)         DEFAULT NULL,
    `niveau`            VARCHAR(50)         DEFAULT NULL,
    `matiere`           VARCHAR(255)        DEFAULT NULL COMMENT 'Pour les enseignants, séparées par des virgules',
    `etablissement`     VARCHAR(255)        DEFAULT NULL,
    `is_verified`       TINYINT(1)          NOT NULL DEFAULT 0,
    `is_active`         TINYINT(1)          NOT NULL DEFAULT 1,
    `is_deleted`        TINYINT(1)          NOT NULL DEFAULT 0,
    `email_token`       VARCHAR(64)         DEFAULT NULL,
    `last_login`        DATETIME            DEFAULT NULL,
    `posts_count`       INT UNSIGNED        NOT NULL DEFAULT 0,
    `followers_count`   INT UNSIGNED        NOT NULL DEFAULT 0,
    `following_count`   INT UNSIGNED        NOT NULL DEFAULT 0,
    `created_at`        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_users_uuid` (`uuid`),
    UNIQUE KEY `uk_users_email` (`email`),
    INDEX `idx_users_role` (`role`),
    INDEX `idx_users_classe` (`classe`),
    INDEX `idx_users_active` (`is_active`, `is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── PUBLICATIONS ──────────────────────────────────────────
DROP TABLE IF EXISTS `posts`;
CREATE TABLE `posts` (
    `id`                INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `user_id`           INT UNSIGNED        NOT NULL,
    `type`              ENUM('question','ressource','partage','annonce') NOT NULL DEFAULT 'partage',
    `contenu`           TEXT                NOT NULL,
    `image`             VARCHAR(500)        DEFAULT NULL,
    `matiere_tag`       VARCHAR(50)         DEFAULT NULL,
    `classe_tag`        VARCHAR(50)         DEFAULT NULL,
    `hashtags`          VARCHAR(500)        DEFAULT NULL COMMENT 'Tags séparés par des virgules',
    `is_resolved`       TINYINT(1)          NOT NULL DEFAULT 0,
    `is_pinned`         TINYINT(1)          NOT NULL DEFAULT 0,
    `is_deleted`        TINYINT(1)          NOT NULL DEFAULT 0,
    `likes_count`       INT UNSIGNED        NOT NULL DEFAULT 0,
    `comments_count`    INT UNSIGNED        NOT NULL DEFAULT 0,
    `created_at`        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    INDEX `idx_posts_user` (`user_id`),
    INDEX `idx_posts_type` (`type`),
    INDEX `idx_posts_matiere` (`matiere_tag`),
    INDEX `idx_posts_created` (`created_at` DESC),
    INDEX `idx_posts_feed` (`is_deleted`, `is_pinned`, `created_at` DESC),
    INDEX `idx_posts_classe` (`classe_tag`),

    CONSTRAINT `fk_posts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── COMMENTAIRES ──────────────────────────────────────────
DROP TABLE IF EXISTS `comments`;
CREATE TABLE `comments` (
    `id`                INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `post_id`           INT UNSIGNED        NOT NULL,
    `user_id`           INT UNSIGNED        NOT NULL,
    `parent_id`         INT UNSIGNED        DEFAULT NULL COMMENT 'Pour les réponses imbriquées',
    `contenu`           TEXT                NOT NULL,
    `is_best_answer`    TINYINT(1)          NOT NULL DEFAULT 0,
    `is_deleted`        TINYINT(1)          NOT NULL DEFAULT 0,
    `likes_count`       INT UNSIGNED        NOT NULL DEFAULT 0,
    `created_at`        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    INDEX `idx_comments_post` (`post_id`, `created_at`),
    INDEX `idx_comments_user` (`user_id`),
    INDEX `idx_comments_parent` (`parent_id`),

    CONSTRAINT `fk_comments_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_comments_parent` FOREIGN KEY (`parent_id`) REFERENCES `comments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── LIKES ─────────────────────────────────────────────────
DROP TABLE IF EXISTS `likes`;
CREATE TABLE `likes` (
    `id`                INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `user_id`           INT UNSIGNED        NOT NULL,
    `post_id`           INT UNSIGNED        DEFAULT NULL,
    `comment_id`        INT UNSIGNED        DEFAULT NULL,
    `created_at`        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_likes_user_post` (`user_id`, `post_id`),
    UNIQUE KEY `uk_likes_user_comment` (`user_id`, `comment_id`),
    INDEX `idx_likes_post` (`post_id`),
    INDEX `idx_likes_comment` (`comment_id`),

    CONSTRAINT `fk_likes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_likes_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_likes_comment` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── FOLLOWS ───────────────────────────────────────────────
DROP TABLE IF EXISTS `follows`;
CREATE TABLE `follows` (
    `id`                INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `follower_id`       INT UNSIGNED        NOT NULL,
    `followed_id`       INT UNSIGNED        NOT NULL,
    `created_at`        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_follows` (`follower_id`, `followed_id`),
    INDEX `idx_follows_followed` (`followed_id`),

    CONSTRAINT `fk_follows_follower` FOREIGN KEY (`follower_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_follows_followed` FOREIGN KEY (`followed_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── NOTIFICATIONS ─────────────────────────────────────────
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
    `id`                INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `user_id`           INT UNSIGNED        NOT NULL COMMENT 'Destinataire',
    `actor_id`          INT UNSIGNED        DEFAULT NULL COMMENT 'Qui a déclenché',
    `type`              ENUM('like','comment','reply','mention','follow','announcement') NOT NULL,
    `message`           VARCHAR(500)        NOT NULL,
    `link`              VARCHAR(500)        DEFAULT NULL,
    `post_id`           INT UNSIGNED        DEFAULT NULL,
    `is_read`           TINYINT(1)          NOT NULL DEFAULT 0,
    `created_at`        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    INDEX `idx_notif_user` (`user_id`, `is_read`, `created_at` DESC),
    INDEX `idx_notif_actor` (`actor_id`),

    CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_notif_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── SIGNALEMENTS ──────────────────────────────────────────
DROP TABLE IF EXISTS `reports`;
CREATE TABLE `reports` (
    `id`                INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `reporter_id`       INT UNSIGNED        NOT NULL,
    `post_id`           INT UNSIGNED        DEFAULT NULL,
    `comment_id`        INT UNSIGNED        DEFAULT NULL,
    `reason`            ENUM('inappropriate','spam','harassment','other') NOT NULL,
    `description`       TEXT                DEFAULT NULL,
    `status`            ENUM('pending','reviewed','dismissed') NOT NULL DEFAULT 'pending',
    `admin_note`        TEXT                DEFAULT NULL,
    `created_at`        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    INDEX `idx_reports_status` (`status`),
    INDEX `idx_reports_post` (`post_id`),

    CONSTRAINT `fk_reports_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reports_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_reports_comment` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── PASSWORD RESETS ───────────────────────────────────────
DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
    `id`                INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `email`             VARCHAR(255)        NOT NULL,
    `token`             VARCHAR(64)         NOT NULL,
    `is_used`           TINYINT(1)          NOT NULL DEFAULT 0,
    `expires_at`        DATETIME            NOT NULL,
    `created_at`        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    INDEX `idx_pwreset_token` (`token`),
    INDEX `idx_pwreset_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── SETTINGS (Plateforme) ─────────────────────────────────
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
    `id`                INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `setting_key`       VARCHAR(100)        NOT NULL,
    `setting_value`     TEXT                DEFAULT NULL,
    `updated_at`        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_settings_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── BOOKMARKS ─────────────────────────────────────────────
DROP TABLE IF EXISTS `bookmarks`;
CREATE TABLE `bookmarks` (
    `id`                INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `user_id`           INT UNSIGNED        NOT NULL,
    `post_id`           INT UNSIGNED        NOT NULL,
    `created_at`        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_bookmarks` (`user_id`, `post_id`),

    CONSTRAINT `fk_bookmarks_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_bookmarks_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── DONNÉES INITIALES ─────────────────────────────────────

-- Créer un compte admin par défaut (mot de passe : admin123)
INSERT INTO `users` (`uuid`, `nom`, `prenom`, `email`, `password_hash`, `role`, `is_verified`, `is_active`)
VALUES (
    UUID(),
    'Admin',
    'StudyLink',
    'admin@studylink.fr',
    '$2y$12$Fo8mne/tIXsaa7Prd6Xsdu31TsXf7u3XmOGlPGhriwg4SXdqSGe6S',
    'admin',
    1,
    1
);

-- Paramètres par défaut
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('site_name', 'StudyLink'),
('site_description', 'Le réseau social bienveillant pour l''éducation'),
('classes_list', '6ème,5ème,4ème,3ème,Seconde,Première,Terminale'),
('matieres_list', 'Mathématiques,Physique-Chimie,SVT,Français,Histoire-Géographie,Anglais,Espagnol,Philosophie,NSI,EPS,Arts,Musique'),
('enable_messaging', '0'),
('enable_follow', '1'),
('max_upload_mb', '10');

SET FOREIGN_KEY_CHECKS = 1;

