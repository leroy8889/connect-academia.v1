-- ─────────────────────────────────────────────────────────────────────────────
-- Migration : attestations d'inscription (module Orientation)
--
-- L'attestation d'inscription est délivrée APRÈS validation du dossier par
-- l'établissement (statut "accepte") — à ne pas confondre avec le récépissé
-- de préinscription (table `recepisses`), délivré immédiatement au dépôt.
--
-- Numérotation : ATT-AAAA-XXXX, séquence par établissement et par année.
-- Fichier HTML autonome stocké sur B2 : attestations/{dossier_id}/{numero}.html
--
-- Exécution :
--   mysql --socket=/Applications/MAMP/tmp/mysql/mysql.sock -uroot -proot \
--     connect_academia1 < Database/migration_attestations.sql
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS attestations_inscription (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    dossier_id          INT          NOT NULL,
    numero_attestation  VARCHAR(50)  NULL,
    cle_pdf             VARCHAR(255) NULL,
    date_emission       DATETIME     NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_attestation_dossier (dossier_id),
    UNIQUE KEY uq_attestation_numero  (numero_attestation),
    CONSTRAINT fk_attestation_dossier
        FOREIGN KEY (dossier_id) REFERENCES dossiers_inscription(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
