-- ============================================================================
-- Migration : Filières & Spécialités configurables par établissement
-- Date      : 2026-07-11
-- À REJOUER EN PRODUCTION (comme migration_cascade_suppression.sql)
--
-- 1. Chaque établissement configure ses propres filières (ex. "Droit")
--    et leurs spécialités (ex. "Conseil juridique") depuis son espace
--    gestionnaire (/gestionnaire/filieres).
-- 2. Les formulaires de préinscription / inscription affichent alors un
--    menu déroulant Filière → Spécialité en cascade.
-- 3. dossiers_inscription.filiere_visee passe d'ENUM à VARCHAR pour
--    accepter les filières dynamiques ; ajout de specialite_visee.
-- 4. Les anciennes valeurs ENUM sont converties en libellés lisibles
--    (les vues/services gardent un fallback sur la valeur brute).
-- ============================================================================

-- ① Filières par établissement
CREATE TABLE IF NOT EXISTS filieres_etablissement (
  id                INT PRIMARY KEY AUTO_INCREMENT,
  etablissement_id  INT NOT NULL,
  nom               VARCHAR(190) NOT NULL,
  ordre             INT NOT NULL DEFAULT 0,
  actif             TINYINT(1) NOT NULL DEFAULT 1,
  created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_filiere_etab (etablissement_id, nom),
  INDEX idx_etab_actif (etablissement_id, actif),
  CONSTRAINT fk_filiere_etab
    FOREIGN KEY (etablissement_id) REFERENCES etablissements(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ② Spécialités d'une filière
CREATE TABLE IF NOT EXISTS specialites_filiere (
  id          INT PRIMARY KEY AUTO_INCREMENT,
  filiere_id  INT NOT NULL,
  nom         VARCHAR(190) NOT NULL,
  ordre       INT NOT NULL DEFAULT 0,
  actif       TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_spec_filiere (filiere_id, nom),
  INDEX idx_filiere_actif (filiere_id, actif),
  CONSTRAINT fk_spec_filiere
    FOREIGN KEY (filiere_id) REFERENCES filieres_etablissement(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ③ dossiers_inscription : filière libre + spécialité
ALTER TABLE dossiers_inscription
  MODIFY filiere_visee VARCHAR(190) NULL;

ALTER TABLE dossiers_inscription
  ADD COLUMN specialite_visee VARCHAR(190) NULL AFTER filiere_visee;

-- ④ Conversion des anciennes clés ENUM en libellés lisibles
UPDATE dossiers_inscription SET filiere_visee = 'Socle L1-L2'   WHERE filiere_visee = 'socle_l1_l2';
UPDATE dossiers_inscription SET filiere_visee = 'Licence L3'    WHERE filiere_visee = 'licence_l3';
UPDATE dossiers_inscription SET filiere_visee = 'Master M1-M2'  WHERE filiere_visee = 'master_m1_m2';
UPDATE dossiers_inscription SET filiere_visee = 'Certification' WHERE filiere_visee = 'certification';
