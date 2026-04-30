-- ============================================================
--  Connect'Academia — Migration 004 — Tables manquantes
--  Suite de 003 — crée toutes les tables absentes
--  CTO : ONA-DAVID LEROY — 2026-04-29
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ── historique_connexions_admin ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS historique_connexions_admin (
  id         INT          AUTO_INCREMENT PRIMARY KEY,
  admin_id   INT          DEFAULT NULL,
  email      VARCHAR(150) NOT NULL,
  ip         VARCHAR(45)  NOT NULL,
  user_agent VARCHAR(500) DEFAULT NULL,
  statut     ENUM('succes','echec','2fa_echec','bloque') NOT NULL,
  created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── settings ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS settings (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_key   VARCHAR(100) NOT NULL,
  setting_value TEXT         DEFAULT NULL,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_settings_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
  ('site_name',                    'Connect''Academia'),
  ('periode_gratuite_jours',       '1'),
  ('prix_mensuel_xaf',             '2000'),
  ('prix_annuel_xaf',              '15000'),
  ('gemini_rate_limit_per_minute', '10'),
  ('enable_chat',                  '1'),
  ('enable_paiement',              '0'),
  ('max_upload_mb',                '50'),
  ('description_publique',         'Plateforme éducative gabonaise pour les élèves de Terminale.'),
  ('email_contact',                'contact@connect-academia.ga'),
  ('enable_suivi',                 '1'),
  ('enable_messagerie',            '0'),
  ('enable_signalements',          '1'),
  ('enable_notif_email',           '1'),
  ('email_inscription',            '1'),
  ('email_signalement',            '1'),
  ('email_ressource',              '0');

-- ── posts ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS posts (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id        INT          NOT NULL,
  type           ENUM('question','ressource','partage','annonce') NOT NULL DEFAULT 'partage',
  contenu        TEXT         NOT NULL,
  image          VARCHAR(500) DEFAULT NULL,
  matiere_tag    VARCHAR(50)  DEFAULT NULL,
  serie_tag      VARCHAR(10)  DEFAULT NULL,
  hashtags       VARCHAR(500) DEFAULT NULL,
  is_resolved    TINYINT(1)   NOT NULL DEFAULT 0,
  is_pinned      TINYINT(1)   NOT NULL DEFAULT 0,
  is_deleted     TINYINT(1)   NOT NULL DEFAULT 0,
  likes_count    INT UNSIGNED NOT NULL DEFAULT 0,
  comments_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_posts_user (user_id),
  INDEX idx_posts_feed (is_deleted, is_pinned, created_at),
  INDEX idx_posts_type (type),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── comments ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS comments (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  post_id        INT UNSIGNED NOT NULL,
  user_id        INT          NOT NULL,
  parent_id      INT UNSIGNED DEFAULT NULL,
  contenu        TEXT         NOT NULL,
  is_best_answer TINYINT(1)   NOT NULL DEFAULT 0,
  is_deleted     TINYINT(1)   NOT NULL DEFAULT 0,
  likes_count    INT UNSIGNED NOT NULL DEFAULT 0,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_comments_post   (post_id, created_at),
  INDEX idx_comments_parent (parent_id),
  FOREIGN KEY (post_id)   REFERENCES posts(id)    ON DELETE CASCADE,
  FOREIGN KEY (user_id)   REFERENCES users(id)    ON DELETE CASCADE,
  FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── likes ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS likes (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT          NOT NULL,
  post_id    INT UNSIGNED DEFAULT NULL,
  comment_id INT UNSIGNED DEFAULT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_likes_user_post    (user_id, post_id),
  UNIQUE KEY uk_likes_user_comment (user_id, comment_id),
  FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
  FOREIGN KEY (post_id)    REFERENCES posts(id)    ON DELETE CASCADE,
  FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── follows ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS follows (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  follower_id INT          NOT NULL,
  followed_id INT          NOT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_follows           (follower_id, followed_id),
  INDEX      idx_follows_followed (followed_id),
  FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (followed_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── bookmarks ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS bookmarks (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT          NOT NULL,
  post_id    INT UNSIGNED NOT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_bookmarks (user_id, post_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── reports ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS reports (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reporter_id INT          NOT NULL,
  post_id     INT UNSIGNED DEFAULT NULL,
  comment_id  INT UNSIGNED DEFAULT NULL,
  reason      ENUM('inappropriate','spam','harassment','other') NOT NULL,
  description TEXT         DEFAULT NULL,
  status      ENUM('pending','reviewed','dismissed') NOT NULL DEFAULT 'pending',
  admin_note  TEXT         DEFAULT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_reports_status (status),
  FOREIGN KEY (reporter_id) REFERENCES users(id)    ON DELETE CASCADE,
  FOREIGN KEY (post_id)     REFERENCES posts(id)    ON DELETE SET NULL,
  FOREIGN KEY (comment_id)  REFERENCES comments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── salons ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS salons (
  id          INT          AUTO_INCREMENT PRIMARY KEY,
  nom         VARCHAR(100) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  serie_tag   VARCHAR(10)  DEFAULT NULL,
  matiere_tag VARCHAR(50)  DEFAULT NULL,
  is_active   TINYINT(1)   DEFAULT 1,
  created_by  INT          DEFAULT NULL,
  created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO salons (nom, description, serie_tag) VALUES
  ('Général',            'Salon ouvert à tous',                 NULL),
  ('Série C',            'Mathématiques et Sciences Physiques', 'C'),
  ('Série D',            'Sciences de la Vie et de la Terre',   'D'),
  ('Série A',            'Lettres et Sciences Humaines',        'A'),
  ('Série B',            'Sciences Économiques',                'B'),
  ('Examens & Concours', 'Préparation aux examens nationaux',   NULL);

-- ── messages_chat ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS messages_chat (
  id         INT          AUTO_INCREMENT PRIMARY KEY,
  salon_id   INT          NOT NULL,
  user_id    INT          NOT NULL,
  contenu    TEXT         NOT NULL,
  is_deleted TINYINT(1)   DEFAULT 0,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_messages_salon (salon_id, created_at),
  FOREIGN KEY (salon_id) REFERENCES salons(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── password_resets ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS password_resets (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email      VARCHAR(255) NOT NULL,
  token      VARCHAR(64)  NOT NULL,
  is_used    TINYINT(1)   NOT NULL DEFAULT 0,
  expires_at DATETIME     NOT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_pwreset_token (token),
  INDEX idx_pwreset_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── abonnements ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS abonnements (
  id          INT          AUTO_INCREMENT PRIMARY KEY,
  user_id     INT          NOT NULL,
  plan        ENUM('gratuit','mensuel','annuel') NOT NULL DEFAULT 'gratuit',
  statut      ENUM('actif','expire','en_attente') NOT NULL DEFAULT 'actif',
  debut       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fin         DATETIME     NOT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_abonnements_user (user_id, statut),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── transactions ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS transactions (
  id               INT          AUTO_INCREMENT PRIMARY KEY,
  user_id          INT          NOT NULL,
  abonnement_id    INT          DEFAULT NULL,
  reference        VARCHAR(100) UNIQUE NOT NULL,
  montant          DECIMAL(10,2) NOT NULL,
  devise           VARCHAR(10)  DEFAULT 'XAF',
  plan             ENUM('mensuel','annuel') NOT NULL,
  statut           ENUM('en_attente','succes','echec','rembourse') DEFAULT 'en_attente',
  methode_paiement VARCHAR(50)  DEFAULT NULL,
  aggregateur_ref  VARCHAR(200) DEFAULT NULL,
  webhook_payload  TEXT         DEFAULT NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── notifications — fix colonnes manquantes ───────────────────────────────
ALTER TABLE notifications RENAME COLUMN `lu` TO `is_read`;

ALTER TABLE notifications
  MODIFY COLUMN `type` ENUM('like','comment','reply','mention','follow','announcement','nouvelle_ressource','abonnement','info','success','warning') NOT NULL DEFAULT 'announcement';

ALTER TABLE notifications ADD COLUMN `actor_id` INT UNSIGNED DEFAULT NULL AFTER `user_id`;
ALTER TABLE notifications ADD COLUMN `link`     VARCHAR(500) DEFAULT NULL AFTER `message`;
ALTER TABLE notifications ADD COLUMN `post_id`  INT UNSIGNED DEFAULT NULL AFTER `link`;

SET FOREIGN_KEY_CHECKS = 1;
