-- ============================================================
--  Connect'Academia — Seed 005 — Données connect_academia1
--  Admin + Matieres pour tous les series
--  Mot de passe admin : Admin@2026
--  CTO : ONA-DAVID LEROY — 2026-04-29
-- ============================================================

USE connect_academia1;
SET NAMES utf8mb4;

-- ── Admin par défaut ────────────────────────────────────────────────────────
-- Supprimer si existant (pour idempotence) puis insérer
DELETE FROM admins WHERE email = 'admin@connect-academia.ga';

INSERT INTO admins (nom, prenom, email, password_hash, role, is_active, is_locked, totp_enabled)
VALUES ('Admin', 'Connect', 'admin@connect-academia.ga',
        '$2y$12$TJNN0vjNDMZb7FBumZhaSO0inonKn2B8hzThxoPDy6ZzHjCqoeS4G',
        'super_admin', 1, 0, 0);

-- ── Matieres — Série A (id=1) ───────────────────────────────────────────────
INSERT INTO matieres (nom, icone, serie_id, ordre) VALUES
  ('Mathématiques',          'calculator',   1, 1),
  ('Français / Littérature', 'book-open',    1, 2),
  ('Philosophie',            'lightbulb',    1, 3),
  ('Anglais (LV1)',          'globe',        1, 4),
  ('Histoire-Géographie',    'map',          1, 5),
  ('SVT',                    'leaf',         1, 6),
  ('LV2 Espagnol',           'languages',    1, 7);

-- ── Matieres — Série B (id=2) ───────────────────────────────────────────────
INSERT INTO matieres (nom, icone, serie_id, ordre) VALUES
  ('Économie / Gestion',     'trending-up',  2, 1),
  ('Mathématiques',          'calculator',   2, 2),
  ('Philosophie',            'lightbulb',    2, 3),
  ('Français / Littérature', 'book-open',    2, 4),
  ('Anglais (LV1)',          'globe',        2, 5),
  ('Histoire-Géographie',    'map',          2, 6),
  ('Comptabilité',           'receipt',      2, 7),
  ('LV2 Espagnol',           'languages',    2, 8);

-- ── Matieres — Série C (id=3) — série de l'utilisateur ─────────────────────
INSERT INTO matieres (nom, icone, serie_id, ordre) VALUES
  ('Mathématiques',          'calculator',      3, 1),
  ('Physique-Chimie',        'flask-conical',   3, 2),
  ('SVT',                    'leaf',            3, 3),
  ('Philosophie',            'lightbulb',       3, 4),
  ('Français / Littérature', 'book-open',       3, 5),
  ('Anglais (LV1)',          'globe',           3, 6),
  ('Histoire-Géographie',    'map',             3, 7),
  ('LV2 Espagnol',           'languages',       3, 8);

-- ── Matieres — Série D (id=4) ───────────────────────────────────────────────
INSERT INTO matieres (nom, icone, serie_id, ordre) VALUES
  ('SVT',                    'leaf',            4, 1),
  ('Mathématiques',          'calculator',      4, 2),
  ('Physique-Chimie',        'flask-conical',   4, 3),
  ('Philosophie',            'lightbulb',       4, 4),
  ('Français / Littérature', 'book-open',       4, 5),
  ('Anglais (LV1)',          'globe',           4, 6),
  ('Histoire-Géographie',    'map',             4, 7),
  ('LV2 Espagnol',           'languages',       4, 8);

-- ── Matieres — Série F3 (id=5) ──────────────────────────────────────────────
INSERT INTO matieres (nom, icone, serie_id, ordre) VALUES
  ('Mathématiques',               'calculator',     5, 1),
  ('Physique-Chimie',             'flask-conical',  5, 2),
  ('Électronique / Informatique', 'cpu',            5, 3),
  ('Français / Littérature',      'book-open',      5, 4),
  ('Anglais (LV1)',               'globe',          5, 5),
  ('Philosophie',                 'lightbulb',      5, 6);

-- ── Matieres — Série G (id=6) ───────────────────────────────────────────────
INSERT INTO matieres (nom, icone, serie_id, ordre) VALUES
  ('Économie / Gestion',          'trending-up',   6, 1),
  ('Mathématiques',               'calculator',    6, 2),
  ('Comptabilité',                'receipt',       6, 3),
  ('Français / Littérature',      'book-open',     6, 4),
  ('Anglais (LV1)',               'globe',         6, 5),
  ('Histoire-Géographie',         'map',           6, 6),
  ('Philosophie',                 'lightbulb',     6, 7),
  ('Droit',                       'scale',         6, 8);
