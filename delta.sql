-- ═══════════════════════════════════════════════════════════════════════════
-- Connect'Academia — DELTA PROD (consolidation des 22 migrations locales)
-- Généré : 2026-07-29
--
-- OBJET : amener le SCHÉMA d'une base PROD déjà peuplée au niveau du schéma
--         LOCAL, SANS toucher aux données prod. 100 % idempotent : rejouable
--         plusieurs fois sans erreur (chaque ajout est gardé par
--         information_schema ; les CREATE sont IF NOT EXISTS ; les MODIFY et
--         UPDATE de conversion ne matchent que les anciennes valeurs).
--
-- ⚠️  1. BACKUP PROD OBLIGATOIRE AVANT (le DDL MySQL auto-commit, pas de rollback) :
--        mysqldump -h HOST -u USER -p --single-transaction --routines --triggers DB > backup.sql
--     2. EXÉCUTER VIA LE CLIENT CLI (support DELIMITER) — PAS phpMyAdmin SQL box :
--        mysql -h PROD_HOST -u PROD_USER -p PROD_DB < delta.sql
--
-- Ordre = dépendances (tables avant colonnes/FK qui les référencent).
-- ═══════════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ─────────────────────────────────────────────────────────────────────────────
-- HELPERS idempotents : ajoutent colonne / index seulement si absent
-- (MySQL 8 ne supporte pas ADD COLUMN IF NOT EXISTS)
-- ─────────────────────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS ca_add_column;
DROP PROCEDURE IF EXISTS ca_add_index;

DELIMITER $$

CREATE PROCEDURE ca_add_column(IN p_table VARCHAR(64), IN p_col VARCHAR(64), IN p_def TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_col
  ) THEN
    SET @s = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN ', p_def);
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END $$

CREATE PROCEDURE ca_add_index(IN p_table VARCHAR(64), IN p_idx VARCHAR(64), IN p_def TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND INDEX_NAME = p_idx
  ) THEN
    SET @s = CONCAT('ALTER TABLE `', p_table, '` ADD ', p_def);
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END $$

DELIMITER ;

-- ═══════════════════════════════════════════════════════════════════════════
-- 1) MODULE ORIENTATION v1 — 18 tables (CREATE IF NOT EXISTS)
-- ═══════════════════════════════════════════════════════════════════════════
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS etablissements (
  id                    INT PRIMARY KEY AUTO_INCREMENT,
  slug                  VARCHAR(120) UNIQUE NOT NULL,
  nom                   VARCHAR(200) NOT NULL,
  type                  ENUM('universite','grande_ecole','institut') NOT NULL,
  ville                 VARCHAR(100) NOT NULL,
  logo_cle              VARCHAR(255),
  couleur_principale    VARCHAR(7) DEFAULT '#1F4E79',
  statut                ENUM('actif','inactif','brouillon') DEFAULT 'brouillon',
  label_campagne        VARCHAR(160),
  titre_accroche        VARCHAR(255),
  sous_titre            TEXT,
  date_limite_dossier   DATE,
  description           TEXT,
  video_url             VARCHAR(255),
  citation_texte        TEXT,
  citation_auteur       VARCHAR(150),
  citation_fonction     VARCHAR(150),
  adresse               VARCHAR(255),
  latitude              DECIMAL(10,7),
  longitude             DECIMAL(10,7),
  email_contact         VARCHAR(150),
  telephone             VARCHAR(30),
  whatsapp              VARCHAR(30),
  prix_preinscription   INT DEFAULT 0,
  prix_inscription      INT DEFAULT 0,
  frais_dossier         INT DEFAULT 0,
  infos_paiement        JSON,
  preinscription_active TINYINT DEFAULT 1,
  genere_recepisse      TINYINT DEFAULT 1,
  inscription_active    TINYINT DEFAULT 0,
  badges_confiance      JSON,
  atouts                JSON,
  statistiques          JSON,
  images_cles           JSON,
  date_creation         DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_slug (slug),
  INDEX idx_statut (statut),
  INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS formations (
  id                INT PRIMARY KEY AUTO_INCREMENT,
  etablissement_id  INT NOT NULL,
  intitule          VARCHAR(200) NOT NULL,
  niveau            VARCHAR(60),
  icone             VARCHAR(40),
  duree_texte       VARCHAR(80),
  places_texte      VARCHAR(80),
  prix              INT DEFAULT 0,
  prix_unite        VARCHAR(20) DEFAULT 'FCFA/an',
  statut            ENUM('ouverte','bientot','fermee') DEFAULT 'ouverte',
  ordre             INT DEFAULT 0,
  FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE,
  INDEX idx_etab (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS actualites (
  id                INT PRIMARY KEY AUTO_INCREMENT,
  etablissement_id  INT NOT NULL,
  titre             VARCHAR(200) NOT NULL,
  categorie         VARCHAR(60),
  date_publication  DATE,
  image_cle         VARCHAR(255),
  extrait           TEXT,
  contenu           LONGTEXT,
  a_la_une          TINYINT DEFAULT 0,
  statut            ENUM('publie','brouillon') DEFAULT 'brouillon',
  FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE,
  INDEX idx_etab (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dossiers_inscription (
  id                      INT PRIMARY KEY AUTO_INCREMENT,
  eleve_id                INT,
  etablissement_id        INT NOT NULL,
  formation_id            INT,
  filiere_visee           ENUM('socle_l1_l2','licence_l3','master_m1_m2','certification'),
  niveau_entree           VARCHAR(60),
  nom_complet             VARCHAR(200),
  genre                   ENUM('homme','femme','autre'),
  date_naissance          DATE,
  lieu_naissance          VARCHAR(120),
  nationalite             VARCHAR(80),
  email                   VARCHAR(150),
  indicatif_tel           VARCHAR(8) DEFAULT '+241',
  telephone               VARCHAR(30),
  adresse                 VARCHAR(255),
  ville                   VARCHAR(120),
  pays_provenance         VARCHAR(80),
  dernier_etablissement   VARCHAR(200),
  reponses_personnalisees JSON,
  origine                 ENUM('en_ligne','etablissement') DEFAULT 'en_ligne',
  cree_par_user_id        INT,
  statut                  ENUM('brouillon','soumis','paye','accepte','refuse') DEFAULT 'brouillon',
  date_creation           DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (etablissement_id) REFERENCES etablissements(id),
  INDEX idx_etab (etablissement_id),
  INDEX idx_eleve (eleve_id),
  INDEX idx_statut (statut),
  INDEX idx_origine (origine)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pieces_justificatives_requises (
  id                INT PRIMARY KEY AUTO_INCREMENT,
  etablissement_id  INT NOT NULL,
  niveau            VARCHAR(60),
  libelle           VARCHAR(180) NOT NULL,
  obligatoire       TINYINT DEFAULT 1,
  ordre             INT DEFAULT 0,
  FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE,
  INDEX idx_etab (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS champs_formulaire (
  id                INT PRIMARY KEY AUTO_INCREMENT,
  etablissement_id  INT NOT NULL,
  type_formulaire   ENUM('preinscription','inscription') NOT NULL,
  section           VARCHAR(60),
  cle               VARCHAR(60),
  libelle           VARCHAR(120),
  type_champ        ENUM('text','email','tel','date','select','textarea','fichier'),
  options           JSON,
  obligatoire       TINYINT DEFAULT 1,
  actif             TINYINT DEFAULT 1,
  standard          TINYINT DEFAULT 1,
  ordre             INT DEFAULT 0,
  FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE,
  INDEX idx_etab_type (etablissement_id, type_formulaire)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recepisses (
  id                INT PRIMARY KEY AUTO_INCREMENT,
  dossier_id        INT NOT NULL UNIQUE,
  numero_recepisse  VARCHAR(50) UNIQUE,
  cle_pdf           VARCHAR(255),
  date_emission     DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (dossier_id) REFERENCES dossiers_inscription(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS documents_orientation (
  id                  INT PRIMARY KEY AUTO_INCREMENT,
  dossier_id          INT NOT NULL,
  eleve_id            INT NOT NULL,
  piece_requise_id    INT,
  libelle             VARCHAR(180),
  cle_b2              VARCHAR(255) NOT NULL,
  mime_type           VARCHAR(80),
  taille_octets       INT,
  date_depot          DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (dossier_id) REFERENCES dossiers_inscription(id),
  INDEX idx_dossier (dossier_id),
  INDEX idx_eleve (eleve_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS journal_acces_documents (
  id                INT PRIMARY KEY AUTO_INCREMENT,
  document_id       INT NOT NULL,
  user_id           INT NOT NULL,
  action            ENUM('consultation','telechargement') DEFAULT 'consultation',
  ip                VARCHAR(45),
  date_acces        DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_doc (document_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS etablissement_admins (
  id                INT PRIMARY KEY AUTO_INCREMENT,
  etablissement_id  INT NOT NULL,
  user_id           INT NOT NULL,
  role              ENUM('gestionnaire','agent') DEFAULT 'gestionnaire',
  date_creation     DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE,
  UNIQUE KEY uq_etab_user (etablissement_id, user_id),
  INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS preinscriptions (
  id                  INT PRIMARY KEY AUTO_INCREMENT,
  dossier_id          INT NOT NULL UNIQUE,
  date_preinscription DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (dossier_id) REFERENCES dossiers_inscription(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inscriptions (
  id                INT PRIMARY KEY AUTO_INCREMENT,
  dossier_id        INT NOT NULL UNIQUE,
  etablissement_id  INT NOT NULL,
  date_inscription  DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (dossier_id) REFERENCES dossiers_inscription(id),
  INDEX idx_etab (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS paiements_orientation (
  id                      INT PRIMARY KEY AUTO_INCREMENT,
  dossier_id              INT NOT NULL,
  etablissement_id        INT NOT NULL,
  montant                 INT NOT NULL,
  mode                    ENUM('en_ligne','espece','virement','mobile_money') DEFAULT 'en_ligne',
  token_pay               VARCHAR(100),
  statut                  ENUM('initie','en_attente','reussi','echoue') DEFAULT 'initie',
  enregistre_par_user_id  INT,
  date_paiement           DATETIME DEFAULT CURRENT_TIMESTAMP,
  date_confirmation       DATETIME,
  FOREIGN KEY (dossier_id) REFERENCES dossiers_inscription(id),
  INDEX idx_etab (etablissement_id),
  INDEX idx_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recus_orientation (
  id                INT PRIMARY KEY AUTO_INCREMENT,
  paiement_id       INT NOT NULL UNIQUE,
  numero_recu       VARCHAR(50) UNIQUE,
  cle_pdf           VARCHAR(255),
  date_emission     DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (paiement_id) REFERENCES paiements_orientation(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS etablissement_paiement (
  id                      INT PRIMARY KEY AUTO_INCREMENT,
  etablissement_id        INT NOT NULL UNIQUE,
  pays_code               VARCHAR(4) DEFAULT 'ga',
  withdraw_mode           VARCHAR(40),
  numero_mobile_money     VARCHAR(30) NOT NULL,
  titulaire               VARCHAR(150),
  taux_commission         DECIMAL(5,2),
  statut                  ENUM('actif','inactif','en_attente') DEFAULT 'en_attente',
  date_connexion          DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reversements (
  id                    INT PRIMARY KEY AUTO_INCREMENT,
  paiement_id           INT NOT NULL UNIQUE,
  etablissement_id      INT NOT NULL,
  montant_brut          INT NOT NULL,
  commission_plateforme INT NOT NULL DEFAULT 0,
  montant_reverse       INT NOT NULL,
  numero_destination    VARCHAR(30),
  withdraw_mode         VARCHAR(40),
  token_pay             VARCHAR(100),
  statut                ENUM('initie','en_cours','reussi','echoue','a_rejouer') DEFAULT 'initie',
  tentatives            INT DEFAULT 0,
  message_erreur        VARCHAR(255),
  date_initiation       DATETIME DEFAULT CURRENT_TIMESTAMP,
  date_confirmation     DATETIME,
  FOREIGN KEY (paiement_id) REFERENCES paiements_orientation(id),
  INDEX idx_etab (etablissement_id),
  INDEX idx_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS visites_etablissement (
  id                INT PRIMARY KEY AUTO_INCREMENT,
  etablissement_id  INT NOT NULL,
  ip                VARCHAR(45),
  date_visite       DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_etab (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications_orientation (
  id                INT PRIMARY KEY AUTO_INCREMENT,
  user_id           INT NOT NULL,
  etablissement_id  INT,
  type              VARCHAR(60),
  titre             VARCHAR(200),
  message           TEXT,
  lu                TINYINT DEFAULT 0,
  date_creation     DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ═══════════════════════════════════════════════════════════════════════════
-- 2) JOURNAUX SMS / WHATSAPP candidats
-- ═══════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS sms_orientation (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  dossier_id        INT UNSIGNED NOT NULL,
  etablissement_id  INT UNSIGNED NOT NULL,
  envoye_par        INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'users.id du gestionnaire',
  destinataire      VARCHAR(20)  NOT NULL COMMENT 'Numéro E.164',
  message           VARCHAR(500) NOT NULL,
  statut            ENUM('envoye','echec','simule') NOT NULL DEFAULT 'envoye',
  reference_externe VARCHAR(100) NULL COMMENT 'ID retourné par la passerelle',
  erreur            VARCHAR(255) NULL,
  date_envoi        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sms_dossier (dossier_id),
  KEY idx_sms_etab (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_orientation (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  dossier_id        INT UNSIGNED NOT NULL,
  etablissement_id  INT UNSIGNED NOT NULL,
  envoye_par        INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'users.id du gestionnaire',
  destinataire      VARCHAR(20)   NOT NULL COMMENT 'Numéro E.164',
  message           VARCHAR(1000) NOT NULL,
  statut            ENUM('envoye','echec','simule') NOT NULL DEFAULT 'envoye',
  reference_externe VARCHAR(100)  NULL COMMENT 'ID retourné par la passerelle',
  erreur            VARCHAR(255)  NULL,
  date_envoi        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_wa_dossier (dossier_id),
  KEY idx_wa_etab (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════
-- 3) ATTESTATIONS + AVIS
-- ═══════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS attestations_inscription (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  dossier_id          INT          NOT NULL,
  numero_attestation  VARCHAR(50)  NULL,
  cle_pdf             VARCHAR(255) NULL,
  date_emission       DATETIME     NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_attestation_dossier (dossier_id),
  UNIQUE KEY uq_attestation_numero  (numero_attestation),
  CONSTRAINT fk_attestation_dossier
    FOREIGN KEY (dossier_id) REFERENCES dossiers_inscription(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- ═══════════════════════════════════════════════════════════════════════════
-- 4) PHASE 9 — audit_log + index perf + etablissement_paiement.updated_at
-- ═══════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS audit_log (
  id         INT PRIMARY KEY AUTO_INCREMENT,
  action     VARCHAR(100) NOT NULL,
  entity     VARCHAR(60)  NOT NULL,
  entity_id  INT          NOT NULL,
  user_id    INT,
  context    JSON,
  ip         VARCHAR(45),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_entity  (entity, entity_id),
  INDEX idx_action  (action),
  INDEX idx_user    (user_id),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL ca_add_index('etablissements',        'idx_etab_statut_type',      'INDEX idx_etab_statut_type (statut, type)');
CALL ca_add_index('dossiers_inscription',  'idx_dossier_etab_statut',   'INDEX idx_dossier_etab_statut (etablissement_id, statut)');
CALL ca_add_index('paiements_orientation', 'idx_paie_etab_statut_date', 'INDEX idx_paie_etab_statut_date (etablissement_id, statut, date_paiement)');
CALL ca_add_index('reversements',          'idx_rev_statut_date',       'INDEX idx_rev_statut_date (statut, date_initiation)');
CALL ca_add_column('etablissement_paiement', 'updated_at', 'updated_at DATETIME DEFAULT NULL AFTER date_connexion');

-- ═══════════════════════════════════════════════════════════════════════════
-- 5) MÉDIAS & LOCALISATION (etablissements)
-- ═══════════════════════════════════════════════════════════════════════════
CALL ca_add_column('etablissements', 'video_cle',     'video_cle VARCHAR(255) NULL AFTER video_url');
CALL ca_add_column('etablissements', 'map_embed_url', 'map_embed_url TEXT NULL AFTER longitude');

-- ═══════════════════════════════════════════════════════════════════════════
-- 6) COMPTES VISITEURS — index email (le MODIFY du rôle est géré en §12 pour
--    éviter d'écraser la valeur 'gestionnaire' déjà présente en prod)
-- ═══════════════════════════════════════════════════════════════════════════
CALL ca_add_index('dossiers_inscription', 'idx_email', 'INDEX idx_email (email)');

-- ═══════════════════════════════════════════════════════════════════════════
-- 7) FILIÈRES & SPÉCIALITÉS configurables
-- ═══════════════════════════════════════════════════════════════════════════
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
    FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    FOREIGN KEY (filiere_id) REFERENCES filieres_etablissement(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- filiere_visee : ENUM -> VARCHAR (idempotent : re-MODIFY sans effet) + specialite
ALTER TABLE dossiers_inscription MODIFY filiere_visee VARCHAR(190) NULL;
CALL ca_add_column('dossiers_inscription', 'specialite_visee', 'specialite_visee VARCHAR(190) NULL AFTER filiere_visee');

UPDATE dossiers_inscription SET filiere_visee = 'Socle L1-L2'   WHERE filiere_visee = 'socle_l1_l2';
UPDATE dossiers_inscription SET filiere_visee = 'Licence L3'    WHERE filiere_visee = 'licence_l3';
UPDATE dossiers_inscription SET filiere_visee = 'Master M1-M2'  WHERE filiere_visee = 'master_m1_m2';
UPDATE dossiers_inscription SET filiere_visee = 'Certification' WHERE filiere_visee = 'certification';

-- ═══════════════════════════════════════════════════════════════════════════
-- 8) STATUT « inscrit » (idempotent)
-- ═══════════════════════════════════════════════════════════════════════════
ALTER TABLE dossiers_inscription
  MODIFY COLUMN statut
  ENUM('brouillon','soumis','paye','accepte','inscrit','refuse')
  NOT NULL DEFAULT 'brouillon';

-- ═══════════════════════════════════════════════════════════════════════════
-- 9) QR CODE vérification (recepisses + attestations)
-- ═══════════════════════════════════════════════════════════════════════════
CALL ca_add_column('recepisses',               'token_verification', 'token_verification CHAR(64) DEFAULT NULL AFTER date_emission');
CALL ca_add_index ('recepisses',               'uq_recepisse_token', 'UNIQUE KEY uq_recepisse_token (token_verification)');
CALL ca_add_column('attestations_inscription', 'token_verification', 'token_verification CHAR(64) DEFAULT NULL AFTER date_emission');
CALL ca_add_index ('attestations_inscription', 'uq_attestation_token', 'UNIQUE KEY uq_attestation_token (token_verification)');

UPDATE recepisses               SET token_verification = SHA2(CONCAT(UUID(), RAND(), id, 'recepisse'),   256) WHERE token_verification IS NULL;
UPDATE attestations_inscription SET token_verification = SHA2(CONCAT(UUID(), RAND(), id, 'attestation'), 256) WHERE token_verification IS NULL;

-- ═══════════════════════════════════════════════════════════════════════════
-- 10) COLONNES etablissements : attestation / signature / commission / frais
-- ═══════════════════════════════════════════════════════════════════════════
CALL ca_add_column('etablissements', 'genere_attestation',        'genere_attestation TINYINT(1) NOT NULL DEFAULT 1 AFTER genere_recepisse');
CALL ca_add_column('etablissements', 'signature_cle',             'signature_cle VARCHAR(255) NULL DEFAULT NULL COMMENT ''Clé B2 signature/cachet attestations'' AFTER logo_cle');
CALL ca_add_column('etablissements', 'taux_commission_scolarite', 'taux_commission_scolarite DECIMAL(5,2) NOT NULL DEFAULT 10.00 COMMENT ''Commission plateforme en % de la scolarité (0 = aucune)'' AFTER frais_dossier');
CALL ca_add_column('etablissements', 'frais_scolarite',           'frais_scolarite INT NOT NULL DEFAULT 0 COMMENT ''Frais scolarité annuels Licence FCFA (0 = non renseigné)'' AFTER frais_dossier');
CALL ca_add_column('etablissements', 'frais_scolarite_master',    'frais_scolarite_master INT NOT NULL DEFAULT 0 COMMENT ''Frais scolarité annuels Master FCFA (0 = non renseigné)'' AFTER frais_scolarite');

-- ═══════════════════════════════════════════════════════════════════════════
-- 11) TÉLÉPHONE users + COMMISSIONS scolarité (tables + tranches)
-- ═══════════════════════════════════════════════════════════════════════════
CALL ca_add_column('users', 'telephone', 'telephone VARCHAR(20) DEFAULT NULL AFTER email');
CALL ca_add_index ('users', 'idx_users_telephone', 'INDEX idx_users_telephone (telephone)');

CREATE TABLE IF NOT EXISTS commissions_scolarite (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  dossier_id INT NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  etablissement_id INT NOT NULL,
  montant_scolarite DECIMAL(12,2) NOT NULL,
  taux_commission DECIMAL(5,2) NOT NULL DEFAULT 10.00,
  montant_commission DECIMAL(12,2) NOT NULL,
  statut ENUM('en_cours','solde') NOT NULL DEFAULT 'en_cours',
  created_by INT DEFAULT NULL COMMENT 'admins.id ayant créé la commission',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_commission_dossier (dossier_id),
  KEY idx_commission_user (user_id),
  KEY idx_commission_etab (etablissement_id),
  CONSTRAINT fk_commission_dossier FOREIGN KEY (dossier_id) REFERENCES dossiers_inscription(id) ON DELETE CASCADE,
  CONSTRAINT fk_commission_etab    FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commission_tranches (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  commission_id INT UNSIGNED NOT NULL,
  numero TINYINT UNSIGNED NOT NULL COMMENT '1, 2 ou 3',
  pourcentage DECIMAL(5,2) NOT NULL COMMENT '40.00 / 30.00 / 30.00',
  montant DECIMAL(12,2) NOT NULL,
  est_payee TINYINT(1) NOT NULL DEFAULT 0,
  date_paiement DATETIME DEFAULT NULL,
  valide_par INT DEFAULT NULL COMMENT 'admins.id ayant coché le paiement',
  PRIMARY KEY (id),
  UNIQUE KEY uq_tranche (commission_id, numero),
  CONSTRAINT fk_tranche_commission FOREIGN KEY (commission_id) REFERENCES commissions_scolarite(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════
-- 12) RÔLE users : enseignant -> gestionnaire (état FINAL, sûr si déjà migré)
--     Étape 1 : ENUM élargi (garde 'enseignant' ET 'gestionnaire' -> aucune
--     perte que 'gestionnaire' soit déjà présent ou non).
-- ═══════════════════════════════════════════════════════════════════════════
ALTER TABLE users
  MODIFY role ENUM('eleve','enseignant','gestionnaire','admin','visiteur')
  COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'eleve';
UPDATE users SET role = 'gestionnaire' WHERE role = 'enseignant';
ALTER TABLE users
  MODIFY role ENUM('eleve','gestionnaire','admin','visiteur')
  COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'eleve';

-- ═══════════════════════════════════════════════════════════════════════════
-- 13) ALERTES DE SÉCURITÉ (IDS)
-- ═══════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS security_alerts (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  type          VARCHAR(64)  NOT NULL,
  severity      ENUM('faible','moyenne','elevee','critique') NOT NULL DEFAULT 'moyenne',
  ip            VARCHAR(45)  NULL,
  user_agent    VARCHAR(255) NULL,
  method        VARCHAR(10)  NULL,
  uri           VARCHAR(512) NULL,
  message       VARCHAR(255) NOT NULL,
  context       JSON         NULL,
  occurrences   INT UNSIGNED NOT NULL DEFAULT 1,
  statut        ENUM('nouveau','vu','resolu') NOT NULL DEFAULT 'nouveau',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_statut   (statut),
  KEY idx_severity (severity),
  KEY idx_created  (created_at),
  KEY idx_ip       (ip),
  KEY idx_dedupe   (type, ip, statut, last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════
-- 14) MONEYFUSION — modes de retrait aux clés officielles Gabon (data, idempotent)
-- ═══════════════════════════════════════════════════════════════════════════
UPDATE etablissement_paiement SET withdraw_mode = 'airtel-ga' WHERE withdraw_mode = 'airtel_money';
UPDATE etablissement_paiement SET withdraw_mode = 'moov-ga'   WHERE withdraw_mode = 'moov_money';
UPDATE reversements           SET withdraw_mode = 'airtel-ga' WHERE withdraw_mode = 'airtel_money';
UPDATE reversements           SET withdraw_mode = 'moov-ga'   WHERE withdraw_mode = 'moov_money';

-- ═══════════════════════════════════════════════════════════════════════════
-- 15) INTÉGRITÉ RÉFÉRENTIELLE — suppression en cascade (FK rewrite)
--     LE BLOC LE PLUS LOURD : nettoie les orphelins, aligne les types de
--     colonnes sur users.id (INT UNSIGNED), reconstruit toutes les FK en
--     ON DELETE CASCADE / SET NULL. Auto-idempotent (drop FK par colonne
--     avant re-add). Doit rester EN DERNIER (toutes les tables doivent exister).
-- ═══════════════════════════════════════════════════════════════════════════
DROP PROCEDURE IF EXISTS drop_fk_if_exists;

DELIMITER $$
CREATE PROCEDURE drop_fk_if_exists(IN p_table VARCHAR(64), IN p_column VARCHAR(64))
BEGIN
  DECLARE done INT DEFAULT 0;
  DECLARE fk_name VARCHAR(64);
  DECLARE cur CURSOR FOR
    SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table
      AND COLUMN_NAME = p_column AND REFERENCED_TABLE_NAME IS NOT NULL;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
  OPEN cur;
  read_loop: LOOP
    FETCH cur INTO fk_name;
    IF done = 1 THEN LEAVE read_loop; END IF;
    SET @sql = CONCAT('ALTER TABLE `', p_table, '` DROP FOREIGN KEY `', fk_name, '`');
    PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
  END LOOP;
  CLOSE cur;
END$$
DELIMITER ;

-- ② Nettoyage orphelins (sinon l'ajout des FK échoue)
DELETE FROM avis_etablissements       WHERE user_id NOT IN (SELECT id FROM users);
DELETE FROM favoris                   WHERE user_id NOT IN (SELECT id FROM users);
DELETE FROM progressions              WHERE user_id NOT IN (SELECT id FROM users);
DELETE FROM sessions_revision         WHERE user_id NOT IN (SELECT id FROM users);
DELETE FROM etablissement_admins      WHERE user_id NOT IN (SELECT id FROM users);
DELETE FROM notifications_orientation WHERE user_id NOT IN (SELECT id FROM users);
UPDATE dossiers_inscription SET eleve_id = NULL
  WHERE eleve_id IS NOT NULL AND eleve_id NOT IN (SELECT id FROM users);
UPDATE dossiers_inscription SET cree_par_user_id = NULL
  WHERE cree_par_user_id IS NOT NULL AND cree_par_user_id NOT IN (SELECT id FROM users);
UPDATE notifications_orientation SET etablissement_id = NULL
  WHERE etablissement_id IS NOT NULL AND etablissement_id NOT IN (SELECT id FROM etablissements);
DELETE FROM visites_etablissement   WHERE etablissement_id NOT IN (SELECT id FROM etablissements);
DELETE FROM sms_orientation         WHERE dossier_id       NOT IN (SELECT id FROM dossiers_inscription);
DELETE FROM journal_acces_documents WHERE document_id      NOT IN (SELECT id FROM documents_orientation);

-- ③ Alignement des types (FK exige le type EXACT du parent)
CALL drop_fk_if_exists('avis_etablissements', 'user_id');
CALL drop_fk_if_exists('etablissement_admins', 'user_id');
CALL drop_fk_if_exists('notifications_orientation', 'user_id');
CALL drop_fk_if_exists('dossiers_inscription', 'eleve_id');
CALL drop_fk_if_exists('dossiers_inscription', 'cree_par_user_id');
CALL drop_fk_if_exists('journal_acces_documents', 'user_id');
CALL drop_fk_if_exists('sms_orientation', 'dossier_id');
CALL drop_fk_if_exists('sms_orientation', 'etablissement_id');

ALTER TABLE avis_etablissements       MODIFY user_id          INT UNSIGNED NOT NULL;
ALTER TABLE etablissement_admins      MODIFY user_id          INT UNSIGNED NOT NULL;
ALTER TABLE notifications_orientation MODIFY user_id          INT UNSIGNED NOT NULL;
ALTER TABLE dossiers_inscription      MODIFY eleve_id         INT UNSIGNED NULL;
ALTER TABLE dossiers_inscription      MODIFY cree_par_user_id INT UNSIGNED NULL;
ALTER TABLE journal_acces_documents   MODIFY user_id          INT UNSIGNED NOT NULL;
ALTER TABLE sms_orientation           MODIFY dossier_id       INT NOT NULL;
ALTER TABLE sms_orientation           MODIFY etablissement_id INT NOT NULL;

-- ④ users -> contenu communauté / apprentissage
CALL drop_fk_if_exists('favoris', 'user_id');
CALL drop_fk_if_exists('favoris', 'ressource_id');
ALTER TABLE favoris
  ADD CONSTRAINT fk_favoris_user      FOREIGN KEY (user_id)      REFERENCES users(id)      ON DELETE CASCADE,
  ADD CONSTRAINT fk_favoris_ressource FOREIGN KEY (ressource_id) REFERENCES ressources(id) ON DELETE CASCADE;

CALL drop_fk_if_exists('progressions', 'user_id');
CALL drop_fk_if_exists('progressions', 'ressource_id');
ALTER TABLE progressions
  ADD CONSTRAINT fk_progressions_user      FOREIGN KEY (user_id)      REFERENCES users(id)      ON DELETE CASCADE,
  ADD CONSTRAINT fk_progressions_ressource FOREIGN KEY (ressource_id) REFERENCES ressources(id) ON DELETE CASCADE;

CALL drop_fk_if_exists('sessions_revision', 'user_id');
CALL drop_fk_if_exists('sessions_revision', 'ressource_id');
ALTER TABLE sessions_revision
  ADD CONSTRAINT fk_sessrev_user      FOREIGN KEY (user_id)      REFERENCES users(id)      ON DELETE CASCADE,
  ADD CONSTRAINT fk_sessrev_ressource FOREIGN KEY (ressource_id) REFERENCES ressources(id) ON DELETE CASCADE;

-- ⑤ users -> module orientation
ALTER TABLE avis_etablissements
  ADD CONSTRAINT fk_avis_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
CALL drop_fk_if_exists('etablissement_admins', 'user_id');
ALTER TABLE etablissement_admins
  ADD CONSTRAINT fk_etabadmin_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
CALL drop_fk_if_exists('notifications_orientation', 'user_id');
CALL drop_fk_if_exists('notifications_orientation', 'etablissement_id');
ALTER TABLE notifications_orientation
  ADD CONSTRAINT fk_notiforient_user FOREIGN KEY (user_id)          REFERENCES users(id)          ON DELETE CASCADE,
  ADD CONSTRAINT fk_notiforient_etab FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE SET NULL;
CALL drop_fk_if_exists('dossiers_inscription', 'eleve_id');
CALL drop_fk_if_exists('dossiers_inscription', 'cree_par_user_id');
ALTER TABLE dossiers_inscription
  ADD CONSTRAINT fk_dossier_eleve FOREIGN KEY (eleve_id)         REFERENCES users(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_dossier_agent FOREIGN KEY (cree_par_user_id) REFERENCES users(id) ON DELETE SET NULL;
CALL drop_fk_if_exists('journal_acces_documents', 'user_id');
CALL drop_fk_if_exists('journal_acces_documents', 'document_id');
ALTER TABLE journal_acces_documents
  ADD CONSTRAINT fk_journal_user FOREIGN KEY (user_id)     REFERENCES users(id)                 ON DELETE CASCADE,
  ADD CONSTRAINT fk_journal_doc  FOREIGN KEY (document_id) REFERENCES documents_orientation(id) ON DELETE CASCADE;

-- ⑥ etablissements -> tout son contenu
CALL drop_fk_if_exists('dossiers_inscription', 'etablissement_id');
ALTER TABLE dossiers_inscription
  ADD CONSTRAINT fk_dossier_etab FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE;
CALL drop_fk_if_exists('visites_etablissement', 'etablissement_id');
ALTER TABLE visites_etablissement
  ADD CONSTRAINT fk_visite_etab FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE;
CALL drop_fk_if_exists('inscriptions', 'etablissement_id');
ALTER TABLE inscriptions
  ADD CONSTRAINT fk_inscription_etab FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE;
CALL drop_fk_if_exists('reversements', 'etablissement_id');
ALTER TABLE reversements
  ADD CONSTRAINT fk_reversement_etab FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE;
CALL drop_fk_if_exists('sms_orientation', 'etablissement_id');
ALTER TABLE sms_orientation
  ADD CONSTRAINT fk_sms_etab FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE;

-- ⑦ dossiers -> documents, récépissés, inscriptions, paiements, SMS
CALL drop_fk_if_exists('recepisses', 'dossier_id');
ALTER TABLE recepisses
  ADD CONSTRAINT fk_recepisse_dossier FOREIGN KEY (dossier_id) REFERENCES dossiers_inscription(id) ON DELETE CASCADE;
CALL drop_fk_if_exists('documents_orientation', 'dossier_id');
ALTER TABLE documents_orientation
  ADD CONSTRAINT fk_document_dossier FOREIGN KEY (dossier_id) REFERENCES dossiers_inscription(id) ON DELETE CASCADE;
CALL drop_fk_if_exists('preinscriptions', 'dossier_id');
ALTER TABLE preinscriptions
  ADD CONSTRAINT fk_preinscription_dossier FOREIGN KEY (dossier_id) REFERENCES dossiers_inscription(id) ON DELETE CASCADE;
CALL drop_fk_if_exists('inscriptions', 'dossier_id');
ALTER TABLE inscriptions
  ADD CONSTRAINT fk_inscription_dossier FOREIGN KEY (dossier_id) REFERENCES dossiers_inscription(id) ON DELETE CASCADE;
CALL drop_fk_if_exists('paiements_orientation', 'dossier_id');
ALTER TABLE paiements_orientation
  ADD CONSTRAINT fk_paiement_dossier FOREIGN KEY (dossier_id) REFERENCES dossiers_inscription(id) ON DELETE CASCADE;
CALL drop_fk_if_exists('sms_orientation', 'dossier_id');
ALTER TABLE sms_orientation
  ADD CONSTRAINT fk_sms_dossier FOREIGN KEY (dossier_id) REFERENCES dossiers_inscription(id) ON DELETE CASCADE;

-- ⑧ paiements -> reçus, reversements
CALL drop_fk_if_exists('recus_orientation', 'paiement_id');
ALTER TABLE recus_orientation
  ADD CONSTRAINT fk_recu_paiement FOREIGN KEY (paiement_id) REFERENCES paiements_orientation(id) ON DELETE CASCADE;
CALL drop_fk_if_exists('reversements', 'paiement_id');
ALTER TABLE reversements
  ADD CONSTRAINT fk_reversement_paiement FOREIGN KEY (paiement_id) REFERENCES paiements_orientation(id) ON DELETE CASCADE;

-- ⑨ Index perf
CALL ca_add_index('avis_etablissements',       'idx_etab_date', 'INDEX idx_etab_date (etablissement_id, date_modification)');
CALL ca_add_index('dossiers_inscription',      'idx_etab_date', 'INDEX idx_etab_date (etablissement_id, date_creation)');
CALL ca_add_index('notifications_orientation', 'idx_user_lu',   'INDEX idx_user_lu (user_id, lu)');

-- ═══════════════════════════════════════════════════════════════════════════
-- NETTOYAGE des procédures helper
-- ═══════════════════════════════════════════════════════════════════════════
DROP PROCEDURE IF EXISTS drop_fk_if_exists;
DROP PROCEDURE IF EXISTS ca_add_column;
DROP PROCEDURE IF EXISTS ca_add_index;

SELECT 'delta.sql appliqué — schéma prod aligné sur local' AS resultat;
