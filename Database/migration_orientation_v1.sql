-- ============================================================
-- Connect'Academia — Module Orientation — Migration BDD v1
-- Phase 1 : 18 tables
-- ============================================================
SET FOREIGN_KEY_CHECKS = 0;

-- ① Établissements (cœur multi-tenant)
CREATE TABLE IF NOT EXISTS etablissements (
  id                    INT PRIMARY KEY AUTO_INCREMENT,
  slug                  VARCHAR(120) UNIQUE NOT NULL,
  nom                   VARCHAR(200) NOT NULL,
  type                  ENUM('universite','grande_ecole','institut') NOT NULL,
  ville                 VARCHAR(100) NOT NULL,
  logo_cle              VARCHAR(255),
  couleur_principale    VARCHAR(7) DEFAULT '#1F4E79',
  statut                ENUM('actif','inactif','brouillon') DEFAULT 'brouillon',
  -- Hero
  label_campagne        VARCHAR(160),
  titre_accroche        VARCHAR(255),
  sous_titre            TEXT,
  date_limite_dossier   DATE,
  -- Présentation
  description           TEXT,
  video_url             VARCHAR(255),
  citation_texte        TEXT,
  citation_auteur       VARCHAR(150),
  citation_fonction     VARCHAR(150),
  -- Contact
  adresse               VARCHAR(255),
  latitude              DECIMAL(10,7),
  longitude             DECIMAL(10,7),
  email_contact         VARCHAR(150),
  telephone             VARCHAR(30),
  whatsapp              VARCHAR(30),
  -- Tarifs
  prix_preinscription   INT DEFAULT 0,
  prix_inscription      INT DEFAULT 0,
  frais_dossier         INT DEFAULT 0,
  infos_paiement        JSON,
  -- Flags candidature
  preinscription_active TINYINT DEFAULT 1,
  genere_recepisse      TINYINT DEFAULT 1,
  inscription_active    TINYINT DEFAULT 0,
  -- Listes flexibles JSON
  badges_confiance      JSON,
  atouts                JSON,
  statistiques          JSON,
  images_cles           JSON,
  date_creation         DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_slug (slug),
  INDEX idx_statut (statut),
  INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ② Formations
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

-- ③ Actualités / Blog
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

-- ④ Dossiers d'inscription
CREATE TABLE IF NOT EXISTS dossiers_inscription (
  id                      INT PRIMARY KEY AUTO_INCREMENT,
  eleve_id                INT,
  etablissement_id        INT NOT NULL,
  formation_id            INT,
  filiere_visee           ENUM('socle_l1_l2','licence_l3','master_m1_m2','certification'),
  niveau_entree           VARCHAR(60),
  -- Identité
  nom_complet             VARCHAR(200),
  genre                   ENUM('homme','femme','autre'),
  date_naissance          DATE,
  lieu_naissance          VARCHAR(120),
  nationalite             VARCHAR(80),
  -- Contact
  email                   VARCHAR(150),
  indicatif_tel           VARCHAR(8) DEFAULT '+241',
  telephone               VARCHAR(30),
  adresse                 VARCHAR(255),
  ville                   VARCHAR(120),
  pays_provenance         VARCHAR(80),
  dernier_etablissement   VARCHAR(200),
  -- Champs personnalisés école
  reponses_personnalisees JSON,
  -- Traçabilité
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

-- ⑤ Pièces justificatives requises
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

-- ⑥ Champs de formulaire configurables
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

-- ⑦ Récépissés de préinscription
CREATE TABLE IF NOT EXISTS recepisses (
  id                INT PRIMARY KEY AUTO_INCREMENT,
  dossier_id        INT NOT NULL UNIQUE,
  numero_recepisse  VARCHAR(50) UNIQUE,
  cle_pdf           VARCHAR(255),
  date_emission     DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (dossier_id) REFERENCES dossiers_inscription(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ⑧ Documents déposés
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

-- ⑨ Journal d'accès documents
CREATE TABLE IF NOT EXISTS journal_acces_documents (
  id                INT PRIMARY KEY AUTO_INCREMENT,
  document_id       INT NOT NULL,
  user_id           INT NOT NULL,
  action            ENUM('consultation','telechargement') DEFAULT 'consultation',
  ip                VARCHAR(45),
  date_acces        DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_doc (document_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ⑩ Admins établissements
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

-- ⑪ Pré-inscriptions
CREATE TABLE IF NOT EXISTS preinscriptions (
  id                  INT PRIMARY KEY AUTO_INCREMENT,
  dossier_id          INT NOT NULL UNIQUE,
  date_preinscription DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (dossier_id) REFERENCES dossiers_inscription(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ⑫ Inscriptions confirmées
CREATE TABLE IF NOT EXISTS inscriptions (
  id                INT PRIMARY KEY AUTO_INCREMENT,
  dossier_id        INT NOT NULL UNIQUE,
  etablissement_id  INT NOT NULL,
  date_inscription  DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (dossier_id) REFERENCES dossiers_inscription(id),
  INDEX idx_etab (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ⑬ Paiements orientation
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

-- ⑭ Reçus PDF
CREATE TABLE IF NOT EXISTS recus_orientation (
  id                INT PRIMARY KEY AUTO_INCREMENT,
  paiement_id       INT NOT NULL UNIQUE,
  numero_recu       VARCHAR(50) UNIQUE,
  cle_pdf           VARCHAR(255),
  date_emission     DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (paiement_id) REFERENCES paiements_orientation(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ⑮ Destination de reversement
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

-- ⑯ Reversements automatiques
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

-- ⑰ Visites par établissement
CREATE TABLE IF NOT EXISTS visites_etablissement (
  id                INT PRIMARY KEY AUTO_INCREMENT,
  etablissement_id  INT NOT NULL,
  ip                VARCHAR(45),
  date_visite       DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_etab (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ⑱ Notifications orientation
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

-- Vérification
SELECT COUNT(*) AS tables_orientation FROM information_schema.tables
WHERE table_schema = 'connect_academia1'
  AND table_name IN (
    'etablissements','formations','actualites','dossiers_inscription',
    'pieces_justificatives_requises','champs_formulaire','recepisses',
    'documents_orientation','journal_acces_documents','etablissement_admins',
    'preinscriptions','inscriptions','paiements_orientation','recus_orientation',
    'etablissement_paiement','reversements','visites_etablissement','notifications_orientation'
  );
