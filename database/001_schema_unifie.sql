-- ============================================================
--  Connect'Academia — Schéma unifié v2.0
--  CTO : ONA-DAVID LEROY — Avril 2026
-- ============================================================
-- DATABASE: connect_academia
-- Exécuter depuis phpMyAdmin ou : mysql -u root -p connect_academia < 001_schema_unifie.sql

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ── SÉRIES (Apprentissage) ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS series (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  nom         VARCHAR(10) NOT NULL,
  description TEXT DEFAULT NULL,
  couleur     VARCHAR(7) DEFAULT '#8B52FA',
  is_active   TINYINT(1) DEFAULT 1,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ADMINS ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS admins (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  nom            VARCHAR(100) NOT NULL,
  prenom         VARCHAR(100) NOT NULL,
  email          VARCHAR(150) UNIQUE NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  role           ENUM('super_admin','admin','moderateur') DEFAULT 'admin',
  totp_secret    VARCHAR(64) DEFAULT NULL,
  totp_enabled   TINYINT(1) DEFAULT 0,
  is_active      TINYINT(1) DEFAULT 1,
  is_locked      TINYINT(1) DEFAULT 0,
  created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── HISTORIQUE CONNEXIONS ADMIN ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS historique_connexions_admin (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  admin_id   INT DEFAULT NULL,
  email      VARCHAR(150) NOT NULL,
  ip         VARCHAR(45) NOT NULL,
  user_agent VARCHAR(500) DEFAULT NULL,
  statut     ENUM('succes','echec','2fa_echec','bloque') NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── UTILISATEURS (table unifiée) ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid            CHAR(36) NOT NULL,
  nom             VARCHAR(100) NOT NULL,
  prenom          VARCHAR(100) NOT NULL,
  email           VARCHAR(255) NOT NULL,
  password_hash   VARCHAR(255) NOT NULL,
  serie_id        INT DEFAULT NULL,
  role            ENUM('eleve','enseignant','admin') NOT NULL DEFAULT 'eleve',
  photo_profil    VARCHAR(500) DEFAULT NULL,
  bio             VARCHAR(160) DEFAULT NULL,
  etablissement   VARCHAR(255) DEFAULT NULL,
  is_verified     TINYINT(1) NOT NULL DEFAULT 0,
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  is_deleted      TINYINT(1) NOT NULL DEFAULT 0,
  email_token     VARCHAR(64) DEFAULT NULL,
  posts_count     INT UNSIGNED NOT NULL DEFAULT 0,
  followers_count INT UNSIGNED NOT NULL DEFAULT 0,
  following_count INT UNSIGNED NOT NULL DEFAULT 0,
  last_login      DATETIME DEFAULT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_uuid (uuid),
  UNIQUE KEY uk_email (email),
  INDEX idx_role (role),
  INDEX idx_active (is_active, is_deleted),
  FOREIGN KEY (serie_id) REFERENCES series(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ABONNEMENTS ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS abonnements (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  plan        ENUM('gratuit','mensuel','annuel') NOT NULL DEFAULT 'gratuit',
  statut      ENUM('actif','expire','en_attente') NOT NULL DEFAULT 'actif',
  debut       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fin         DATETIME NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_abonnements_user (user_id, statut),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── TRANSACTIONS PAIEMENT ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS transactions (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  user_id           INT UNSIGNED NOT NULL,
  abonnement_id     INT DEFAULT NULL,
  reference         VARCHAR(100) UNIQUE NOT NULL,
  montant           DECIMAL(10,2) NOT NULL,
  devise            VARCHAR(10) DEFAULT 'XAF',
  plan              ENUM('mensuel','annuel') NOT NULL,
  statut            ENUM('en_attente','succes','echec','rembourse') DEFAULT 'en_attente',
  methode_paiement  VARCHAR(50) DEFAULT NULL,
  aggregateur_ref   VARCHAR(200) DEFAULT NULL,
  webhook_payload   TEXT DEFAULT NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── MATIERES ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS matieres (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  nom         VARCHAR(100) NOT NULL,
  description TEXT DEFAULT NULL,
  coef        TINYINT UNSIGNED DEFAULT 2,
  icone       VARCHAR(50) DEFAULT 'book',
  serie_id    INT NOT NULL,
  ordre       INT DEFAULT 0,
  is_active   TINYINT(1) DEFAULT 1,
  FOREIGN KEY (serie_id) REFERENCES series(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── CHAPITRES ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS chapitres (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  titre      VARCHAR(200) NOT NULL,
  matiere_id INT NOT NULL,
  ordre      INT DEFAULT 0,
  is_active  TINYINT(1) DEFAULT 1,
  FOREIGN KEY (matiere_id) REFERENCES matieres(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── RESSOURCES ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ressources (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  titre          VARCHAR(255) NOT NULL,
  description    TEXT DEFAULT NULL,
  type           ENUM('cours','td','ancienne_epreuve','corrige') NOT NULL,
  fichier_path   VARCHAR(500) NOT NULL,
  fichier_nom    VARCHAR(255) NOT NULL,
  taille_fichier INT DEFAULT 0,
  matiere_id     INT NOT NULL,
  chapitre_id    INT DEFAULT NULL,
  serie_id       INT NOT NULL,
  annee          YEAR DEFAULT NULL,
  admin_id       INT NOT NULL,
  nb_vues        INT DEFAULT 0,
  is_deleted     TINYINT(1) DEFAULT 0,
  created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_ressources_serie (serie_id),
  INDEX idx_ressources_matiere (matiere_id),
  INDEX idx_ressources_type (type),
  FOREIGN KEY (matiere_id)  REFERENCES matieres(id),
  FOREIGN KEY (chapitre_id) REFERENCES chapitres(id),
  FOREIGN KEY (serie_id)    REFERENCES series(id),
  FOREIGN KEY (admin_id)    REFERENCES admins(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── PROGRESSIONS ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS progressions (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  ressource_id  INT NOT NULL,
  statut        ENUM('non_commence','en_cours','termine') DEFAULT 'non_commence',
  pourcentage   INT DEFAULT 0,
  temps_passe   INT DEFAULT 0,
  derniere_page INT DEFAULT 1,
  started_at    DATETIME DEFAULT NULL,
  completed_at  DATETIME DEFAULT NULL,
  updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_user_ressource (user_id, ressource_id),
  INDEX idx_progressions_user (user_id),
  FOREIGN KEY (user_id)      REFERENCES users(id),
  FOREIGN KEY (ressource_id) REFERENCES ressources(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── SESSIONS RÉVISION ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS sessions_revision (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  user_id        INT UNSIGNED NOT NULL,
  ressource_id   INT NOT NULL,
  debut          DATETIME NOT NULL,
  fin            DATETIME DEFAULT NULL,
  duree_secondes INT DEFAULT 0,
  INDEX idx_sessions_user (user_id),
  FOREIGN KEY (user_id)      REFERENCES users(id),
  FOREIGN KEY (ressource_id) REFERENCES ressources(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── FAVORIS ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS favoris (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL,
  ressource_id INT NOT NULL,
  created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_favori (user_id, ressource_id),
  FOREIGN KEY (user_id)      REFERENCES users(id),
  FOREIGN KEY (ressource_id) REFERENCES ressources(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── CONVERSATIONS IA ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ia_conversations (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       INT UNSIGNED NOT NULL,
  ressource_id  INT NOT NULL,
  user_message  TEXT NOT NULL,
  ia_response   TEXT NOT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_ia_user (user_id),
  INDEX idx_ia_ressource (ressource_id),
  FOREIGN KEY (user_id)      REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (ressource_id) REFERENCES ressources(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── POSTS (Communauté) ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS posts (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id        INT UNSIGNED NOT NULL,
  type           ENUM('question','ressource','partage','annonce') NOT NULL DEFAULT 'partage',
  contenu        TEXT NOT NULL,
  image          VARCHAR(500) DEFAULT NULL,
  matiere_tag    VARCHAR(50) DEFAULT NULL,
  serie_tag      VARCHAR(10) DEFAULT NULL,
  hashtags       VARCHAR(500) DEFAULT NULL,
  is_resolved    TINYINT(1) NOT NULL DEFAULT 0,
  is_pinned      TINYINT(1) NOT NULL DEFAULT 0,
  is_deleted     TINYINT(1) NOT NULL DEFAULT 0,
  likes_count    INT UNSIGNED NOT NULL DEFAULT 0,
  comments_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_posts_user (user_id),
  INDEX idx_posts_feed (is_deleted, is_pinned, created_at),
  INDEX idx_posts_type (type),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── COMMENTAIRES ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS comments (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  post_id        INT UNSIGNED NOT NULL,
  user_id        INT UNSIGNED NOT NULL,
  parent_id      INT UNSIGNED DEFAULT NULL,
  contenu        TEXT NOT NULL,
  is_best_answer TINYINT(1) NOT NULL DEFAULT 0,
  is_deleted     TINYINT(1) NOT NULL DEFAULT 0,
  likes_count    INT UNSIGNED NOT NULL DEFAULT 0,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_comments_post (post_id, created_at),
  INDEX idx_comments_parent (parent_id),
  FOREIGN KEY (post_id)   REFERENCES posts(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)   REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── LIKES ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS likes (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  post_id    INT UNSIGNED DEFAULT NULL,
  comment_id INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_likes_user_post (user_id, post_id),
  UNIQUE KEY uk_likes_user_comment (user_id, comment_id),
  FOREIGN KEY (user_id)    REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (post_id)    REFERENCES posts(id) ON DELETE CASCADE,
  FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── FOLLOWS ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS follows (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  follower_id INT UNSIGNED NOT NULL,
  followed_id INT UNSIGNED NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_follows (follower_id, followed_id),
  INDEX idx_follows_followed (followed_id),
  FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (followed_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── BOOKMARKS ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS bookmarks (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  post_id    INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_bookmarks (user_id, post_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── SALONS DE CHAT ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS salons (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  nom         VARCHAR(100) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  serie_tag   VARCHAR(10) DEFAULT NULL,
  matiere_tag VARCHAR(50) DEFAULT NULL,
  is_active   TINYINT(1) DEFAULT 1,
  created_by  INT DEFAULT NULL,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── MESSAGES CHAT ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS messages_chat (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  salon_id   INT NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  contenu    TEXT NOT NULL,
  is_deleted TINYINT(1) DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_messages_salon (salon_id, created_at),
  FOREIGN KEY (salon_id) REFERENCES salons(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── NOTIFICATIONS ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS notifications (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  actor_id   INT UNSIGNED DEFAULT NULL,
  type       ENUM('like','comment','reply','mention','follow','announcement','nouvelle_ressource','abonnement') NOT NULL,
  message    VARCHAR(500) NOT NULL,
  link       VARCHAR(500) DEFAULT NULL,
  post_id    INT UNSIGNED DEFAULT NULL,
  is_read    TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_notif_user (user_id, is_read, created_at),
  FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── SIGNALEMENTS ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS reports (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reporter_id INT UNSIGNED NOT NULL,
  post_id     INT UNSIGNED DEFAULT NULL,
  comment_id  INT UNSIGNED DEFAULT NULL,
  reason      ENUM('inappropriate','spam','harassment','other') NOT NULL,
  description TEXT DEFAULT NULL,
  status      ENUM('pending','reviewed','dismissed') NOT NULL DEFAULT 'pending',
  admin_note  TEXT DEFAULT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_reports_status (status),
  FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (post_id)     REFERENCES posts(id) ON DELETE SET NULL,
  FOREIGN KEY (comment_id)  REFERENCES comments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── PASSWORD RESETS ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS password_resets (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email      VARCHAR(255) NOT NULL,
  token      VARCHAR(64) NOT NULL,
  is_used    TINYINT(1) NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_pwreset_token (token),
  INDEX idx_pwreset_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── SETTINGS ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS settings (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_key   VARCHAR(100) NOT NULL,
  setting_value TEXT DEFAULT NULL,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_settings_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ── DONNÉES INITIALES ─────────────────────────────────────────────────────
INSERT IGNORE INTO series (nom, description, couleur) VALUES
('A',  'Lettres et Sciences Humaines',            '#FF6B6B'),
('B',  'Sciences Économiques',                    '#4ECDC4'),
('C',  'Mathématiques et Sciences Physiques',     '#8B52FA'),
('D',  'Sciences de la Vie et de la Terre',       '#45B7D1'),
('F3', 'Génie Électronique',                      '#96CEB4'),
('G',  'Techniques de Gestion',                   '#FFEAA7');

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('site_name',                    'Connect''Academia'),
('periode_gratuite_jours',       '1'),
('prix_mensuel_xaf',             '2000'),
('prix_annuel_xaf',              '15000'),
('gemini_rate_limit_per_minute', '10'),
('enable_chat',                  '1'),
('enable_paiement',              '0'),
('max_upload_mb',                '50');

INSERT IGNORE INTO salons (nom, description, serie_tag) VALUES
('Général',         'Salon ouvert à tous',                  NULL),
('Série C',         'Mathématiques et Sciences Physiques',  'C'),
('Série D',         'Sciences de la Vie et de la Terre',    'D'),
('Série A',         'Lettres et Sciences Humaines',         'A'),
('Série B',         'Sciences Économiques',                 'B'),
('Examens & Concours', 'Préparation aux examens nationaux', NULL);
