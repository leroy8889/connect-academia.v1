-- Table pour stocker les conversations avec l'IA Gemini
-- Connect'Academia - Assistant Connect'Acadrmia
-- Version sans clés étrangères (si les tables référencées n'existent pas encore)

-- Supprimer la table si elle existe déjà
DROP TABLE IF EXISTS ia_conversations;

-- Créer la table sans contraintes de clés étrangères
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
    KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ajouter les clés étrangères après (si les tables existent)
-- ALTER TABLE ia_conversations 
--     ADD CONSTRAINT fk_ia_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
-- 
-- ALTER TABLE ia_conversations 
--     ADD CONSTRAINT fk_ia_document FOREIGN KEY (document_id) REFERENCES ressources(id) ON DELETE CASCADE;

