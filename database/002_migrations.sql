-- ─────────────────────────────────────────────────────────────────────────
-- Migration 002 — Connect'Academia Phase 7 — 2026-04-27
-- À exécuter une fois sur la base existante (pas idempotent sur l'ENUM)
-- ─────────────────────────────────────────────────────────────────────────

-- 1. Ajout du type "corrigé" dans la bibliothèque de ressources
ALTER TABLE ressources
  MODIFY COLUMN type ENUM('cours','td','ancienne_epreuve','corrige') NOT NULL;

-- 2. Ajout des nouvelles clés de paramètres (si la table settings existe)
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
  ('description_publique', 'Plateforme éducative gabonaise pour les élèves de Terminale.'),
  ('email_contact',        'contact@connect-academia.ga'),
  ('enable_suivi',         '1'),
  ('enable_messagerie',    '0'),
  ('enable_signalements',  '1'),
  ('enable_notif_email',   '1'),
  ('email_inscription',    '1'),
  ('email_signalement',    '1'),
  ('email_ressource',      '0');

-- 3. Index de performance sur posts (pour le fil communauté)
ALTER TABLE posts
  ADD INDEX idx_posts_deleted_pinned_date (is_deleted, is_pinned, created_at);

-- 4. Index sur ressources pour le filtre matière
ALTER TABLE ressources
  ADD INDEX idx_ressources_matiere_deleted (matiere_id, is_deleted);
