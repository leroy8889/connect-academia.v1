-- ============================================================
-- Migration : comptes "visiteur" (grand public — module Orientation)
-- Permet aux parents / étudiants / personnes lambda de créer un
-- compte léger pour suivre leurs dossiers d'inscription, sans
-- passer par le formulaire d'inscription élève de terminale.
-- Base : connect_academia1
-- ============================================================

-- ① Nouveau rôle 'visiteur' dans users
ALTER TABLE users
  MODIFY role ENUM('eleve','enseignant','admin','visiteur')
  COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'eleve';

-- ② Index pour le rattachement des dossiers par email
--    (idx_eleve sur eleve_id existe déjà dans le schéma d'origine)
ALTER TABLE dossiers_inscription
  ADD INDEX idx_email (email);
