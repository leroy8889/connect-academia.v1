-- ============================================================
--  Connect'Academia — Migration 006
--  Restructuration des séries : A1, A2, B, C, D
--  Suppression des anciennes séries : F3, G, F4, STMG
--  Réinitialisation complète des matières
--  CTO : ONA-DAVID LEROY — 2026-05-07
-- ============================================================

USE connect_academia1;
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── 1. Nettoyage des tables dépendantes des matières ─────────
DELETE FROM progressions;
DELETE FROM sessions_revision;
DELETE FROM favoris;
DELETE FROM ia_conversations;
DELETE FROM ressources;
DELETE FROM chapitres;
DELETE FROM matieres;

-- ── 2. Suppression des séries obsolètes (G=6, F4=7, STMG=8) ─
DELETE FROM series WHERE id IN (6, 7, 8);

-- ── 3. Mise à jour des séries conservées ─────────────────────

-- id=1 : A → A1 (Lettres et Langues)
UPDATE series SET
    nom         = 'A1',
    description = 'Terminale A1 — Lettres et Langues',
    couleur     = '#FF6B6B'
WHERE id = 1;

-- id=2 : B (description mise à jour)
UPDATE series SET
    nom         = 'B',
    description = 'Terminale B — Sciences Économiques',
    couleur     = '#4ECDC4'
WHERE id = 2;

-- id=3 : C (description mise à jour)
UPDATE series SET
    nom         = 'C',
    description = 'Terminale C — Mathématiques et Physique',
    couleur     = '#8B52FA'
WHERE id = 3;

-- id=4 : D (description mise à jour)
UPDATE series SET
    nom         = 'D',
    description = 'Terminale D — Sciences de la Vie et de la Terre',
    couleur     = '#45B7D1'
WHERE id = 4;

-- id=5 : F3 → A2 (Lettres et Sciences Humaines)
UPDATE series SET
    nom         = 'A2',
    description = 'Terminale A2 — Lettres et Sciences Humaines',
    couleur     = '#FF8E53'
WHERE id = 5;

-- ── 4. Matières — Série A1 (id=1) ────────────────────────────
INSERT INTO matieres (nom, icone, serie_id, ordre) VALUES
  ('Mathématiques',          'calculator',   1, 1),
  ('Français / Littérature', 'book-open',    1, 2),
  ('Philosophie',            'lightbulb',    1, 3),
  ('Anglais (LV1)',          'globe',        1, 4),
  ('Histoire-Géographie',    'map',          1, 5),
  ('LV2 Espagnol',           'languages',    1, 6);

-- ── 5. Matières — Série A2 (id=5) ────────────────────────────
INSERT INTO matieres (nom, icone, serie_id, ordre) VALUES
  ('Mathématiques',          'calculator',   5, 1),
  ('Français / Littérature', 'book-open',    5, 2),
  ('Philosophie',            'lightbulb',    5, 3),
  ('Anglais (LV1)',          'globe',        5, 4),
  ('Histoire-Géographie',    'map',          5, 5),
  ('LV2 Espagnol',           'languages',    5, 6);

-- ── 6. Matières — Série B (id=2) ─────────────────────────────
INSERT INTO matieres (nom, icone, serie_id, ordre) VALUES
  ('Économie / Gestion',     'trending-up',  2, 1),
  ('Mathématiques',          'calculator',   2, 2),
  ('Philosophie',            'lightbulb',    2, 3),
  ('Français / Littérature', 'book-open',    2, 4),
  ('Anglais (LV1)',          'globe',        2, 5),
  ('Histoire-Géographie',    'map',          2, 6),
  ('LV2 Espagnol',           'languages',    2, 7);

-- ── 7. Matières — Série C (id=3) ─────────────────────────────
INSERT INTO matieres (nom, icone, serie_id, ordre) VALUES
  ('Mathématiques',          'calculator',    3, 1),
  ('Physique-Chimie',        'flask-conical', 3, 2),
  ('SVT',                    'leaf',          3, 3),
  ('Philosophie',            'lightbulb',     3, 4),
  ('Français / Littérature', 'book-open',     3, 5),
  ('Anglais (LV1)',          'globe',         3, 6),
  ('Histoire-Géographie',    'map',           3, 7);

-- ── 8. Matières — Série D (id=4) ─────────────────────────────
INSERT INTO matieres (nom, icone, serie_id, ordre) VALUES
  ('Mathématiques',          'calculator',    4, 1),
  ('Physique-Chimie',        'flask-conical', 4, 2),
  ('SVT',                    'leaf',          4, 3),
  ('Philosophie',            'lightbulb',     4, 4),
  ('Français / Littérature', 'book-open',     4, 5),
  ('Anglais (LV1)',          'globe',         4, 6),
  ('Histoire-Géographie',    'map',           4, 7);

-- ── 9. Mise à jour des salons ─────────────────────────────────

-- Supprimer salons des séries supprimées
DELETE FROM salons WHERE serie_tag IN ('F3', 'G', 'F4', 'STMG');

-- Renommer Série A → Série A1
UPDATE salons SET
    nom         = 'Série A1',
    description = 'Terminale A1 — Lettres et Langues',
    serie_tag   = 'A1'
WHERE serie_tag = 'A';

-- Mettre à jour descriptions des autres salons
UPDATE salons SET description = 'Terminale B — Sciences Économiques'           WHERE serie_tag = 'B';
UPDATE salons SET description = 'Terminale C — Mathématiques et Physique'      WHERE serie_tag = 'C';
UPDATE salons SET description = 'Terminale D — Sciences de la Vie et de la Terre' WHERE serie_tag = 'D';

-- Ajouter salon Série A2
INSERT IGNORE INTO salons (nom, description, serie_tag) VALUES
  ('Série A2', 'Terminale A2 — Lettres et Sciences Humaines', 'A2');

SET FOREIGN_KEY_CHECKS = 1;

-- ── Vérification ──────────────────────────────────────────────
SELECT 'SERIES:' AS '';
SELECT id, nom, description, couleur FROM series ORDER BY nom;
SELECT 'MATIERES:' AS '';
SELECT m.id, m.nom, m.icone, s.nom AS serie FROM matieres m JOIN series s ON s.id = m.serie_id ORDER BY s.nom, m.ordre;
SELECT 'SALONS:' AS '';
SELECT id, nom, serie_tag FROM salons ORDER BY id;
