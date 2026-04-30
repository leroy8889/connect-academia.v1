-- Connect'Academia - Schéma de base de données
-- MySQL 8.x

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS ia_conversations;
DROP TABLE IF EXISTS favoris;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS sessions_revision;
DROP TABLE IF EXISTS progressions;
DROP TABLE IF EXISTS ressources;
DROP TABLE IF EXISTS chapitres;
DROP TABLE IF EXISTS matieres;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS admins;
DROP TABLE IF EXISTS series;
SET FOREIGN_KEY_CHECKS = 1;

-- Table series
CREATE TABLE series (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(10) NOT NULL,
    description TEXT DEFAULT NULL,
    couleur     VARCHAR(7) DEFAULT '#8B52FA',
    is_active   TINYINT(1) DEFAULT 1,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table users (Élèves)
CREATE TABLE users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nom        VARCHAR(100) NOT NULL,
    prenom     VARCHAR(100) NOT NULL,
    email      VARCHAR(150) UNIQUE NOT NULL,
    password   VARCHAR(255) NOT NULL,
    serie_id   INT NOT NULL,
    avatar     VARCHAR(255) DEFAULT NULL,
    is_active  TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME DEFAULT NULL,
    FOREIGN KEY (serie_id) REFERENCES series(id)
);

-- Table admins
CREATE TABLE admins (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nom        VARCHAR(100) NOT NULL,
    prenom     VARCHAR(100) NOT NULL,
    email      VARCHAR(150) UNIQUE NOT NULL,
    password   VARCHAR(255) NOT NULL,
    role       ENUM('super_admin', 'admin') DEFAULT 'admin',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table matieres
CREATE TABLE matieres (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    nom       VARCHAR(100) NOT NULL,
    icone     VARCHAR(50) DEFAULT 'book',
    serie_id  INT NOT NULL,
    ordre     INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (serie_id) REFERENCES series(id)
);

-- Table chapitres
CREATE TABLE chapitres (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    titre      VARCHAR(200) NOT NULL,
    matiere_id INT NOT NULL,
    ordre      INT DEFAULT 0,
    is_active  TINYINT(1) DEFAULT 1,
    FOREIGN KEY (matiere_id) REFERENCES matieres(id)
);

-- Table ressources
CREATE TABLE ressources (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    titre          VARCHAR(255) NOT NULL,
    description    TEXT DEFAULT NULL,
    type           ENUM('cours', 'td', 'ancienne_epreuve') NOT NULL,
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
    FOREIGN KEY (matiere_id)  REFERENCES matieres(id),
    FOREIGN KEY (chapitre_id) REFERENCES chapitres(id),
    FOREIGN KEY (serie_id)    REFERENCES series(id),
    FOREIGN KEY (admin_id)    REFERENCES admins(id)
);

-- Table progressions
CREATE TABLE progressions (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT NOT NULL,
    ressource_id  INT NOT NULL,
    statut        ENUM('non_commence', 'en_cours', 'termine') DEFAULT 'non_commence',
    pourcentage   INT DEFAULT 0,
    temps_passe   INT DEFAULT 0,
    derniere_page INT DEFAULT 1,
    started_at    DATETIME DEFAULT NULL,
    completed_at  DATETIME DEFAULT NULL,
    updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_ressource (user_id, ressource_id),
    FOREIGN KEY (user_id)      REFERENCES users(id),
    FOREIGN KEY (ressource_id) REFERENCES ressources(id)
);

-- Table sessions_revision
CREATE TABLE sessions_revision (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    ressource_id    INT NOT NULL,
    debut           DATETIME NOT NULL,
    fin             DATETIME DEFAULT NULL,
    duree_secondes  INT DEFAULT 0,
    FOREIGN KEY (user_id)      REFERENCES users(id),
    FOREIGN KEY (ressource_id) REFERENCES ressources(id)
);

-- Table favoris
CREATE TABLE favoris (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL,
    ressource_id INT NOT NULL,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_favori (user_id, ressource_id),
    FOREIGN KEY (user_id)      REFERENCES users(id),
    FOREIGN KEY (ressource_id) REFERENCES ressources(id)
);

-- Table notifications
CREATE TABLE notifications (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT DEFAULT NULL,
    titre      VARCHAR(200) NOT NULL,
    message    TEXT NOT NULL,
    type       ENUM('info', 'success', 'warning', 'nouvelle_ressource') DEFAULT 'info',
    lu         TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Créer la table
CREATE TABLE IF NOT EXISTS ia_conversations (
    id            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id       INT              NOT NULL,
    document_id   INT              NOT NULL,
    document_type ENUM('cours','td','ancienne_epreuve') NOT NULL,
    user_message  TEXT             NOT NULL,
    ia_response   TEXT             NOT NULL,
    created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user_id    (user_id),
    KEY idx_document   (document_id, document_type),
    KEY idx_created_at (created_at),
    FOREIGN KEY (user_id)      REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id)  REFERENCES ressources(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Index de performance
CREATE INDEX idx_ressources_serie     ON ressources(serie_id);
CREATE INDEX idx_ressources_matiere   ON ressources(matiere_id);
CREATE INDEX idx_ressources_type      ON ressources(type);
CREATE INDEX idx_progressions_user    ON progressions(user_id);
CREATE INDEX idx_sessions_user        ON sessions_revision(user_id);
CREATE INDEX idx_notifications_user   ON notifications(user_id, lu);
CREATE INDEX idx_matieres_serie       ON matieres(serie_id);
CREATE INDEX idx_chapitres_matiere    ON chapitres(matiere_id);


