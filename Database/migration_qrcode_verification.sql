-- ─────────────────────────────────────────────────────────────────────────────
-- Migration : QR code de vérification d'authenticité des documents
-- (récépissés de préinscription + attestations d'inscription)
--
-- Chaque document reçoit un token de vérification unique (64 hex = 256 bits
-- aléatoires, imprévisible). Le QR code imprimé sur le document encode l'URL
-- publique /verifier/{token} qui affiche les informations officielles du
-- dossier — toute falsification est ainsi détectable par simple scan.
--
-- ⚠ À REJOUER EN PRODUCTION (comme les autres migrations du dossier Database/).
-- MySQL 8.0 : pas de ADD COLUMN IF NOT EXISTS — en cas de rejeu, l'erreur
-- "Duplicate column name" signifie que la migration est déjà appliquée.
--
-- Exécution :
--   mysql --socket=/Applications/MAMP/tmp/mysql/mysql.sock -uroot -proot \
--     connect_academia1 < Database/migration_qrcode_verification.sql
-- ─────────────────────────────────────────────────────────────────────────────

ALTER TABLE recepisses
  ADD COLUMN token_verification CHAR(64) DEFAULT NULL AFTER date_emission,
  ADD UNIQUE KEY uq_recepisse_token (token_verification);

ALTER TABLE attestations_inscription
  ADD COLUMN token_verification CHAR(64) DEFAULT NULL AFTER date_emission,
  ADD UNIQUE KEY uq_attestation_token (token_verification);

-- Backfill des documents existants : token aléatoire dérivé d'UUID + aléa SQL.
-- (Les nouveaux documents reçoivent un token PHP random_bytes(32) à la création.)
UPDATE recepisses
   SET token_verification = SHA2(CONCAT(UUID(), RAND(), id, 'recepisse'), 256)
 WHERE token_verification IS NULL;

UPDATE attestations_inscription
   SET token_verification = SHA2(CONCAT(UUID(), RAND(), id, 'attestation'), 256)
 WHERE token_verification IS NULL;
