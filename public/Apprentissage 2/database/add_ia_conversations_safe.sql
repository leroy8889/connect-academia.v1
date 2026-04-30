-- Table pour stocker les conversations avec l'IA Gemini
-- Connect'Academia - Assistant Connect'Acadrmia
-- Version sécurisée sans IF NOT EXISTS (pour MySQL plus ancien)

-- Vérifier et supprimer la table si elle existe
DROP TABLE IF EXISTS ia_conversations;

-- Créer la table
CREATE TABLE ia_conversations (
    id            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED     NOT NULL,
    document_id   INT UNSIGNED     NOT NULL,
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

