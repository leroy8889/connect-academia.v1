-- ─────────────────────────────────────────────────────────────────────────────
-- Journal des messages WhatsApp envoyés aux candidats depuis l'espace gestionnaire
-- (« Dossiers reçus » → message compte + WhatsApp du numéro de compte candidat)
-- Appliquer : mysql -u root -p connect_academia < Database/migration_whatsapp_orientation.sql
-- ─────────────────────────────────────────────────────────────────────────────

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
