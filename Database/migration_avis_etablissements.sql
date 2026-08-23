-- ============================================================
-- Connect'Academia — Module Orientation — Avis établissements
-- Système de notation 5 étoiles (inspiré Google Reviews) :
--   · 1 avis par utilisateur et par établissement (upsert)
--   · note obligatoire 1..5, commentaire facultatif
--   · l'agrégat (moyenne + volume) est calculé à la volée
-- Base : connect_academia1
-- ============================================================

CREATE TABLE IF NOT EXISTS avis_etablissements (
  id                INT PRIMARY KEY AUTO_INCREMENT,
  etablissement_id  INT NOT NULL,
  user_id           INT NOT NULL,
  note              TINYINT UNSIGNED NOT NULL,
  commentaire       TEXT,
  date_creation     DATETIME DEFAULT CURRENT_TIMESTAMP,
  date_modification DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_etab_user (etablissement_id, user_id),
  FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE,
  INDEX idx_etab (etablissement_id),
  INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
