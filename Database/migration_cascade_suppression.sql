-- ═══════════════════════════════════════════════════════════════════════════
-- Migration — Intégrité référentielle & suppression en cascade
-- Date : 2026-07-07
--
-- Objectif : supprimer un utilisateur / établissement / dossier dans la base
-- doit propager la suppression à TOUT ce qui le concerne (plus d'orphelins,
-- plus de DELETE bloqué par une contrainte RESTRICT).
--
-- Politique appliquée :
--   users            → contenu personnel (avis, favoris, progressions, sessions
--                      de révision, accès gestionnaire, notifications) : CASCADE
--                    → dossiers d'inscription (archive légale de l'école) :
--                      SET NULL (le dossier reste, le lien au compte disparaît)
--   etablissements   → tout le contenu du site école (dossiers, visites,
--                      inscriptions, SMS, reversements…) : CASCADE
--   dossiers         → récépissés, documents, préinscriptions, inscriptions,
--                      paiements, SMS : CASCADE
--   paiements        → reçus, reversements : CASCADE
--
-- Appliquer : mysql -u root -p connect_academia1 < Database/migration_cascade_suppression.sql
-- ═══════════════════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────────────────────────────────────
-- ① Helper : DROP FOREIGN KEY par (table, colonne) sans connaître son nom
--    (les FKs créées inline reçoivent des noms auto `xxx_ibfk_N` qui varient)
-- ─────────────────────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS drop_fk_if_exists;

DELIMITER $$
CREATE PROCEDURE drop_fk_if_exists(IN p_table VARCHAR(64), IN p_column VARCHAR(64))
BEGIN
  DECLARE done INT DEFAULT 0;
  DECLARE fk_name VARCHAR(64);
  DECLARE cur CURSOR FOR
    SELECT CONSTRAINT_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table
      AND COLUMN_NAME = p_column
      AND REFERENCED_TABLE_NAME IS NOT NULL;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

  OPEN cur;
  read_loop: LOOP
    FETCH cur INTO fk_name;
    IF done = 1 THEN LEAVE read_loop; END IF;
    SET @sql = CONCAT('ALTER TABLE `', p_table, '` DROP FOREIGN KEY `', fk_name, '`');
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END LOOP;
  CLOSE cur;
END$$
DELIMITER ;

-- ─────────────────────────────────────────────────────────────────────────────
-- ② Nettoyage des orphelins existants (sinon l'ajout des FKs échoue)
-- ─────────────────────────────────────────────────────────────────────────────
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

DELETE FROM visites_etablissement WHERE etablissement_id NOT IN (SELECT id FROM etablissements);
DELETE FROM sms_orientation       WHERE dossier_id       NOT IN (SELECT id FROM dossiers_inscription);
DELETE FROM journal_acces_documents WHERE document_id    NOT IN (SELECT id FROM documents_orientation);

-- ─────────────────────────────────────────────────────────────────────────────
-- ③ Alignement des types de colonnes (FK exige le type EXACT du parent)
--    users.id = INT UNSIGNED ; les tables orientation utilisaient INT signé.
--    dossiers_inscription.id = INT signé ; sms_orientation utilisait UNSIGNED.
-- ─────────────────────────────────────────────────────────────────────────────
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

-- ─────────────────────────────────────────────────────────────────────────────
-- ④ users → contenu communauté / apprentissage (RESTRICT → CASCADE)
-- ─────────────────────────────────────────────────────────────────────────────
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

-- ─────────────────────────────────────────────────────────────────────────────
-- ⑤ users → module orientation
-- ─────────────────────────────────────────────────────────────────────────────
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

-- ─────────────────────────────────────────────────────────────────────────────
-- ⑥ etablissements → tout son contenu (RESTRICT / absent → CASCADE)
-- ─────────────────────────────────────────────────────────────────────────────
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

-- ─────────────────────────────────────────────────────────────────────────────
-- ⑦ dossiers_inscription → documents, récépissés, inscriptions, paiements, SMS
-- ─────────────────────────────────────────────────────────────────────────────
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

-- ─────────────────────────────────────────────────────────────────────────────
-- ⑧ paiements_orientation → reçus, reversements
-- ─────────────────────────────────────────────────────────────────────────────
CALL drop_fk_if_exists('recus_orientation', 'paiement_id');
ALTER TABLE recus_orientation
  ADD CONSTRAINT fk_recu_paiement FOREIGN KEY (paiement_id) REFERENCES paiements_orientation(id) ON DELETE CASCADE;

CALL drop_fk_if_exists('reversements', 'paiement_id');
ALTER TABLE reversements
  ADD CONSTRAINT fk_reversement_paiement FOREIGN KEY (paiement_id) REFERENCES paiements_orientation(id) ON DELETE CASCADE;

-- ─────────────────────────────────────────────────────────────────────────────
-- ⑨ Index de performance (montée en charge 15 000+ utilisateurs)
--    Liste des avis triée par date : index composite au lieu du tri filesort.
-- ─────────────────────────────────────────────────────────────────────────────
SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'avis_etablissements' AND INDEX_NAME = 'idx_etab_date');
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE avis_etablissements ADD INDEX idx_etab_date (etablissement_id, date_modification)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers_inscription' AND INDEX_NAME = 'idx_etab_date');
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE dossiers_inscription ADD INDEX idx_etab_date (etablissement_id, date_creation)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications_orientation' AND INDEX_NAME = 'idx_user_lu');
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE notifications_orientation ADD INDEX idx_user_lu (user_id, lu)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ─────────────────────────────────────────────────────────────────────────────
-- ⑩ Nettoyage
-- ─────────────────────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS drop_fk_if_exists;
