-- ============================================================================
-- Connect'Academia — Refonte du parcours d'inscription (boursiers + présentiel)
-- Spécification : Connect-Academia_Spec_Inscription_Boursiers.md — Lot 0
-- ----------------------------------------------------------------------------
-- IDEMPOTENTE : rejouable sans erreur (procédures de garde `ca_add_col`).
--
-- NOTE D'ARCHITECTURE — écart assumé avec la spec §12
-- La spec propose un schéma « terrain vierge » (tables `filieres`,
-- `utilisateurs`, `notifications`, `parametres`, `documents_dossier`,
-- `commissions`…). Ces tables n'existent pas ici : la plateforme possède déjà
-- `filieres_etablissement`, `users`, `notifications_orientation`, `settings`,
-- `documents_orientation`, `commissions_scolarite`. Créer les tables de la spec
-- aurait dupliqué la donnée et cassé le back-office existant.
-- On ÉTEND donc l'existant, en conservant strictement la sémantique de la spec.
--
-- Correspondances :
--   spec.dossiers_inscription   → dossiers_inscription (étendue)
--   spec.documents_dossier      → documents_orientation (étendue)
--   spec.commissions            → commissions_scolarite (étendue)
--   spec.notifications          → notifications_orientation (existante)
--   spec.parametres             → settings (existante, Core\Settings)
--   spec.documents_generes      → recepisses + attestations_inscription (étendues)
--   spec.etablissements.taux_commission → etablissements.taux_commission_scolarite
--   spec.utilisateurs.derniere_connexion_le → users.last_login
-- ============================================================================

SET NAMES utf8mb4;

-- ── Garde : ajout de colonne idempotent ─────────────────────────────────────
DROP PROCEDURE IF EXISTS ca_add_col;
DELIMITER $$
CREATE PROCEDURE ca_add_col(IN p_table VARCHAR(64), IN p_col VARCHAR(64), IN p_ddl TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_col
  ) THEN
    SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN ', p_ddl);
    PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
  END IF;
END$$
DELIMITER ;

DROP PROCEDURE IF EXISTS ca_add_idx;
DELIMITER $$
CREATE PROCEDURE ca_add_idx(IN p_table VARCHAR(64), IN p_idx VARCHAR(64), IN p_ddl TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND INDEX_NAME = p_idx
  ) THEN
    SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD ', p_ddl);
    PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
  END IF;
END$$
DELIMITER ;


-- ============================================================================
-- 1. ÉTABLISSEMENTS — code court, mode de paiement, délai d'expiration
-- ============================================================================
-- `code_court` est NULL-able (les lignes existantes n'en ont pas encore) et
-- rempli soit par bin/backfill-codes-etablissement.php, soit à la volée par
-- Services\Orientation\CodeEtablissement. L'unicité reste garantie par l'index.
CALL ca_add_col('etablissements', 'code_court',
  "`code_court` VARCHAR(5) NULL DEFAULT NULL COMMENT 'Code 3-5 lettres pour la référence CA-AAAA-XXX-NNNNNN' AFTER `nom`");
CALL ca_add_idx('etablissements', 'uq_etab_code_court',
  'UNIQUE KEY `uq_etab_code_court` (`code_court`)');

-- Canal de règlement des frais d'inscription/scolarité.
-- 'presentiel' (défaut, cf. spec §1) : l'étudiant paie sur place, le wizard
-- s'arrête sur l'écran de confirmation. 'en_ligne'/'mixte' réactivent
-- MoneyFusion sans redéveloppement (spec §17).
CALL ca_add_col('etablissements', 'mode_paiement_inscription',
  "`mode_paiement_inscription` ENUM('presentiel','en_ligne','mixte') NOT NULL DEFAULT 'presentiel'");

CALL ca_add_col('etablissements', 'delai_expiration_dossier_jours',
  "`delai_expiration_dossier_jours` SMALLINT UNSIGNED NOT NULL DEFAULT 30");

-- `taux_commission_scolarite` existe déjà (migration_taux_commission_etablissement).
-- Filet si la migration précédente n'a pas été rejouée sur cet environnement :
CALL ca_add_col('etablissements', 'taux_commission_scolarite',
  "`taux_commission_scolarite` DECIMAL(5,2) NOT NULL DEFAULT 10.00");


-- ============================================================================
-- 2. USERS — statut de parcours (visiteur / candidat / inscrit)
-- ============================================================================
-- `derniere_connexion_le` de la spec = `users.last_login`, déjà présent.
CALL ca_add_col('users', 'statut_parcours',
  "`statut_parcours` ENUM('visiteur','candidat','inscrit') NOT NULL DEFAULT 'visiteur' AFTER `role`");
CALL ca_add_idx('users', 'idx_statut_parcours',
  'KEY `idx_statut_parcours` (`statut_parcours`)');


-- ============================================================================
-- 3. DOSSIERS_INSCRIPTION — machine à états, bourse, finances
-- ============================================================================

-- 3.1 Identification
CALL ca_add_col('dossiers_inscription', 'reference',
  "`reference` VARCHAR(32) NULL DEFAULT NULL COMMENT 'CA-AAAA-CODE-NNNNNN, posée à la soumission' AFTER `id`");
CALL ca_add_idx('dossiers_inscription', 'uq_dossier_reference',
  'UNIQUE KEY `uq_dossier_reference` (`reference`)');

CALL ca_add_col('dossiers_inscription', 'annee_academique',
  "`annee_academique` VARCHAR(9) NULL DEFAULT NULL COMMENT '2026-2027'");
CALL ca_add_idx('dossiers_inscription', 'idx_dossier_annee',
  'KEY `idx_dossier_annee` (`annee_academique`)');

-- 3.2 Progression affichée (0 / 50 / 100) — dérivée du statut, jamais saisie
CALL ca_add_col('dossiers_inscription', 'progression',
  "`progression` TINYINT UNSIGNED NOT NULL DEFAULT 0");

-- 3.3 Situation de bourse (déclarée par l'étudiant — spec §5.2)
CALL ca_add_col('dossiers_inscription', 'statut_bourse',
  "`statut_bourse` ENUM('non_boursier','boursier','dossier_en_cours') NULL DEFAULT NULL");
CALL ca_add_col('dossiers_inscription', 'numero_attestation_anbg',
  "`numero_attestation_anbg` VARCHAR(50) NULL DEFAULT NULL");
CALL ca_add_col('dossiers_inscription', 'date_depot_anbg',
  "`date_depot_anbg` DATE NULL DEFAULT NULL");
CALL ca_add_col('dossiers_inscription', 'bourse_declaree_le',
  "`bourse_declaree_le` DATETIME NULL DEFAULT NULL COMMENT 'Horodatage serveur, jamais transmis par le formulaire'");
CALL ca_add_col('dossiers_inscription', 'bourse_declaree_par',
  "`bourse_declaree_par` ENUM('etudiant','etablissement','admin') NULL DEFAULT NULL");
CALL ca_add_idx('dossiers_inscription', 'idx_dossier_bourse',
  'KEY `idx_dossier_bourse` (`etablissement_id`,`statut_bourse`)');

-- 3.4 Jalons du cycle de vie
CALL ca_add_col('dossiers_inscription', 'soumis_le',      "`soumis_le` DATETIME NULL DEFAULT NULL");
CALL ca_add_col('dossiers_inscription', 'valide_50_le',   "`valide_50_le` DATETIME NULL DEFAULT NULL");
CALL ca_add_col('dossiers_inscription', 'valide_50_par',  "`valide_50_par` INT UNSIGNED NULL DEFAULT NULL COMMENT 'users.id de l agent'");
CALL ca_add_col('dossiers_inscription', 'valide_100_le',  "`valide_100_le` DATETIME NULL DEFAULT NULL");
CALL ca_add_col('dossiers_inscription', 'valide_100_par', "`valide_100_par` INT UNSIGNED NULL DEFAULT NULL");
CALL ca_add_col('dossiers_inscription', 'expire_le',      "`expire_le` DATETIME NULL DEFAULT NULL");
CALL ca_add_col('dossiers_inscription', 'motif_rejet',    "`motif_rejet` TEXT NULL DEFAULT NULL");
CALL ca_add_col('dossiers_inscription', 'pieces_attendues',
  "`pieces_attendues` TEXT NULL DEFAULT NULL COMMENT 'Libellés demandés lors du passage en piece_manquante'");

-- 3.5 Données financières captées à la validation 100 % (spec §8.4)
CALL ca_add_col('dossiers_inscription', 'montant_scolarite_annuelle',
  "`montant_scolarite_annuelle` DECIMAL(12,2) NULL DEFAULT NULL");
CALL ca_add_col('dossiers_inscription', 'montant_encaisse_initial',
  "`montant_encaisse_initial` DECIMAL(12,2) NULL DEFAULT NULL");
CALL ca_add_col('dossiers_inscription', 'mode_paiement',
  "`mode_paiement` ENUM('especes','mobile_money','virement','cheque','autre') NULL DEFAULT NULL");
CALL ca_add_col('dossiers_inscription', 'reference_paiement',
  "`reference_paiement` VARCHAR(80) NULL DEFAULT NULL");
CALL ca_add_col('dossiers_inscription', 'date_paiement',
  "`date_paiement` DATE NULL DEFAULT NULL");
CALL ca_add_col('dossiers_inscription', 'taux_commission_applique',
  "`taux_commission_applique` DECIMAL(5,2) NULL DEFAULT NULL COMMENT 'Taux GELÉ au moment des 100 % — ne change plus jamais'");

CALL ca_add_col('dossiers_inscription', 'maj_le',
  "`maj_le` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

-- 3.6 Machine à états — extension de l'ENUM
-- Les valeurs héritées ('paye','accepte','inscrit','refuse') sont CONSERVÉES
-- dans l'ENUM pour ne casser aucune lecture existante, mais plus aucune écriture
-- ne les produit : Dossier::transition() n'émet que les états de la spec §4.1.
ALTER TABLE `dossiers_inscription`
  MODIFY COLUMN `statut` ENUM(
    'brouillon','soumis','piece_manquante','valide_50','rejete','valide_100','expire','annule',
    'paye','accepte','inscrit','refuse'
  ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'brouillon';

CALL ca_add_idx('dossiers_inscription', 'idx_dossier_statut_expire',
  'KEY `idx_dossier_statut_expire` (`statut`,`expire_le`)');


-- ============================================================================
-- 4. DOCUMENTS_ORIENTATION — pièces justificatives enrichies (spec §5.3 / 12.3)
-- ============================================================================
CALL ca_add_col('documents_orientation', 'type_document',
  "`type_document` VARCHAR(50) NULL DEFAULT NULL COMMENT 'acte_naissance, attestation_bourse, …'");
CALL ca_add_col('documents_orientation', 'nom_original',
  "`nom_original` VARCHAR(255) NULL DEFAULT NULL");
CALL ca_add_col('documents_orientation', 'hash_sha256',
  "`hash_sha256` CHAR(64) NULL DEFAULT NULL COMMENT 'Preuve d intégrité + détection de doublon'");
CALL ca_add_col('documents_orientation', 'statut',
  "`statut` ENUM('en_attente','accepte','refuse') NOT NULL DEFAULT 'en_attente'");
CALL ca_add_col('documents_orientation', 'motif_refus',  "`motif_refus` TEXT NULL DEFAULT NULL");
CALL ca_add_col('documents_orientation', 'verifie_le',   "`verifie_le` DATETIME NULL DEFAULT NULL");
CALL ca_add_col('documents_orientation', 'verifie_par',  "`verifie_par` INT UNSIGNED NULL DEFAULT NULL");
CALL ca_add_idx('documents_orientation', 'idx_doc_hash', 'KEY `idx_doc_hash` (`hash_sha256`)');

-- `eleve_id` devient NULL-able : un dossier peut être ouvert au guichet avant
-- que le candidat n'ait un compte. La colonne était NOT NULL, ce qui obligeait
-- à y écrire 0 — un identifiant d'utilisateur qui n'existe pas. NULL dit la
-- vérité : « pas encore de compte rattaché ».
ALTER TABLE `documents_orientation` MODIFY COLUMN `eleve_id` INT UNSIGNED NULL DEFAULT NULL;
UPDATE `documents_orientation` SET `eleve_id` = NULL WHERE `eleve_id` = 0;
CALL ca_add_idx('documents_orientation', 'idx_doc_type', 'KEY `idx_doc_type` (`dossier_id`,`type_document`)');


-- ============================================================================
-- 5. PIECES_JUSTIFICATIVES_REQUISES — liste dynamique selon la bourse (§5.3)
-- ============================================================================
-- La liste des pièces reste pilotée par l'établissement (table existante).
-- On y ajoute deux notions : un code interne stable, et la condition de bourse
-- qui fait apparaître ou non la pièce à l'étape 2.
CALL ca_add_col('pieces_justificatives_requises', 'code_interne',
  "`code_interne` VARCHAR(50) NULL DEFAULT NULL COMMENT 'acte_naissance, attestation_bourse, …'");
CALL ca_add_col('pieces_justificatives_requises', 'condition_bourse',
  "`condition_bourse` ENUM('tous','boursier','dossier_en_cours','non_boursier') NOT NULL DEFAULT 'tous'");
CALL ca_add_col('pieces_justificatives_requises', 'systeme',
  "`systeme` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = pièce gérée par la plateforme, non supprimable par l établissement'");
CALL ca_add_idx('pieces_justificatives_requises', 'idx_piece_condition',
  'KEY `idx_piece_condition` (`etablissement_id`,`condition_bourse`)');

-- 5.1 Semis des deux pièces conditionnelles pour chaque établissement existant.
--     `niveau` NULL = applicable à tous les niveaux (cf. getPiecesGroupedByNiveau).
INSERT INTO pieces_justificatives_requises
  (etablissement_id, niveau, libelle, obligatoire, ordre, code_interne, condition_bourse, systeme)
SELECT e.id, NULL, 'Attestation de bourse ANBG', 1, 900, 'attestation_bourse', 'boursier', 1
FROM etablissements e
WHERE NOT EXISTS (
  SELECT 1 FROM pieces_justificatives_requises p
  WHERE p.etablissement_id = e.id AND p.code_interne = 'attestation_bourse'
);

INSERT INTO pieces_justificatives_requises
  (etablissement_id, niveau, libelle, obligatoire, ordre, code_interne, condition_bourse, systeme)
SELECT e.id, NULL, 'Preuve de dépôt du dossier ANBG', 0, 901, 'preuve_depot_anbg', 'dossier_en_cours', 1
FROM etablissements e
WHERE NOT EXISTS (
  SELECT 1 FROM pieces_justificatives_requises p
  WHERE p.etablissement_id = e.id AND p.code_interne = 'preuve_depot_anbg'
);


-- ============================================================================
-- 6. COMMISSIONS_SCOLARITE — cycle de facturation (spec §10.3)
-- ============================================================================
ALTER TABLE `commissions_scolarite`
  MODIFY COLUMN `statut` ENUM('a_facturer','facturee','reglee','annulee','en_cours','solde')
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'a_facturer';

CALL ca_add_col('commissions_scolarite', 'periode_facturation',
  "`periode_facturation` CHAR(7) NULL DEFAULT NULL COMMENT 'AAAA-MM'");
CALL ca_add_col('commissions_scolarite', 'regle_le',
  "`regle_le` DATETIME NULL DEFAULT NULL");
CALL ca_add_col('commissions_scolarite', 'reference_reglement',
  "`reference_reglement` VARCHAR(80) NULL DEFAULT NULL");
CALL ca_add_idx('commissions_scolarite', 'idx_commission_periode',
  'KEY `idx_commission_periode` (`periode_facturation`)');
CALL ca_add_idx('commissions_scolarite', 'idx_commission_etab_statut',
  'KEY `idx_commission_etab_statut` (`etablissement_id`,`statut`)');


-- ============================================================================
-- 7. DOCUMENTS GÉNÉRÉS — empreinte et compteur de téléchargements
-- ============================================================================
CALL ca_add_col('recepisses', 'hash_sha256',        "`hash_sha256` CHAR(64) NULL DEFAULT NULL");
CALL ca_add_col('recepisses', 'nb_telechargements', "`nb_telechargements` INT UNSIGNED NOT NULL DEFAULT 0");
CALL ca_add_col('recepisses', 'version',            "`version` SMALLINT UNSIGNED NOT NULL DEFAULT 1");
CALL ca_add_col('attestations_inscription', 'hash_sha256',        "`hash_sha256` CHAR(64) NULL DEFAULT NULL");
CALL ca_add_col('attestations_inscription', 'nb_telechargements', "`nb_telechargements` INT UNSIGNED NOT NULL DEFAULT 0");
CALL ca_add_col('attestations_inscription', 'version',            "`version` SMALLINT UNSIGNED NOT NULL DEFAULT 1");


-- ============================================================================
-- 8. SÉQUENCES DE RÉFÉRENCE (spec §5.5)
-- ============================================================================
-- Compteur par établissement ET par année. Incrémenté sous SELECT … FOR UPDATE
-- à l'intérieur de la transaction de soumission : jamais de MAX(id)+1.
CREATE TABLE IF NOT EXISTS `sequences_dossier` (
  `etablissement_id` INT NOT NULL,
  `annee`            SMALLINT UNSIGNED NOT NULL,
  `dernier_numero`   INT UNSIGNED NOT NULL DEFAULT 0,
  `maj_le`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`etablissement_id`, `annee`),
  CONSTRAINT `fk_sequence_etab` FOREIGN KEY (`etablissement_id`)
    REFERENCES `etablissements`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- 9. JETONS DE VÉRIFICATION ET SCANS (spec §7 / §12.4)
-- ============================================================================
-- Le jeton porte le DOSSIER, pas le document : le récépissé imprimé à 0 %
-- reste exact à 50 % et à 100 %. Les tokens documentaires historiques
-- (recepisses/attestations, migration_qrcode_verification) restent en place.
CREATE TABLE IF NOT EXISTS `jetons_verification` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dossier_id`  INT NOT NULL,
  `token`       CHAR(48) NOT NULL,
  `code_court`  CHAR(8)  NOT NULL,
  `actif`       TINYINT(1) NOT NULL DEFAULT 1,
  `cree_le`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `revoque_le`  DATETIME NULL DEFAULT NULL,
  `revoque_par` INT NULL DEFAULT NULL COMMENT 'admins.id',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_jeton_token` (`token`),
  UNIQUE KEY `uq_jeton_code`  (`code_court`),
  KEY `idx_jeton_dossier` (`dossier_id`,`actif`),
  CONSTRAINT `fk_jeton_dossier` FOREIGN KEY (`dossier_id`)
    REFERENCES `dossiers_inscription`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `scans_qr` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `jeton_id`         INT UNSIGNED NULL DEFAULT NULL,
  `dossier_id`       INT NULL DEFAULT NULL,
  `token_tente`      VARCHAR(64) NULL DEFAULT NULL,
  `scanne_par`       INT UNSIGNED NULL DEFAULT NULL COMMENT 'users.id ou admins.id selon acteur_role',
  `acteur_role`      VARCHAR(30) NULL DEFAULT NULL,
  `etablissement_id` INT NULL DEFAULT NULL,
  `resultat` ENUM('valide','non_valide_50','etablissement_incorrect',
                  'jeton_revoque','introuvable','deja_inscrit','non_authentifie') NOT NULL,
  `adresse_ip`       VARCHAR(45)  NULL DEFAULT NULL,
  `user_agent`       VARCHAR(255) NULL DEFAULT NULL,
  `scanne_le`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_scan_dossier` (`dossier_id`),
  KEY `idx_scan_date` (`scanne_le`),
  KEY `idx_scan_ip_date` (`adresse_ip`,`scanne_le`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- 10. HISTORIQUES (spec §12.7)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `historique_statut_dossier` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dossier_id`     INT NOT NULL,
  `ancien_statut`  VARCHAR(20) NULL DEFAULT NULL,
  `nouveau_statut` VARCHAR(20) NOT NULL,
  `acteur_id`      INT UNSIGNED NULL DEFAULT NULL,
  `acteur_role`    VARCHAR(30) NULL DEFAULT NULL,
  `commentaire`    TEXT NULL DEFAULT NULL,
  `cree_le`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hsd_dossier` (`dossier_id`,`cree_le`),
  CONSTRAINT `fk_hsd_dossier` FOREIGN KEY (`dossier_id`)
    REFERENCES `dossiers_inscription`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `historique_statut_bourse` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dossier_id`        INT NOT NULL,
  `ancien_statut`     VARCHAR(20) NULL DEFAULT NULL,
  `nouveau_statut`    VARCHAR(20) NOT NULL,
  `justificatif_cle`  VARCHAR(500) NULL DEFAULT NULL,
  `modifie_par`       INT UNSIGNED NULL DEFAULT NULL,
  `role_modificateur` ENUM('etudiant','etablissement','admin') NOT NULL,
  `commentaire`       TEXT NULL DEFAULT NULL,
  `modifie_le`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hsb_dossier` (`dossier_id`,`modifie_le`),
  CONSTRAINT `fk_hsb_dossier` FOREIGN KEY (`dossier_id`)
    REFERENCES `dossiers_inscription`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- 11. JOURNAL D'ACTIVITÉ — EN AJOUT SEUL (spec §11.3)
-- ============================================================================
-- Aucune route applicative ne doit UPDATE/DELETE cette table.
-- En production, restreindre les droits du compte MySQL applicatif :
--   GRANT INSERT, SELECT ON connect_academia.journal_activite TO 'app'@'%';
--   REVOKE UPDATE, DELETE ON connect_academia.journal_activite FROM 'app'@'%';
-- Pas de clé étrangère : le journal doit survivre à la suppression de l'objet
-- qu'il décrit (une trace effaçable ne prouve rien).
CREATE TABLE IF NOT EXISTS `journal_activite` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `utilisateur_id`   INT UNSIGNED NULL DEFAULT NULL COMMENT 'Sujet de l événement',
  `acteur_id`        INT UNSIGNED NULL DEFAULT NULL COMMENT 'Auteur de l action',
  `acteur_role`      VARCHAR(30)  NULL DEFAULT NULL,
  `type_evenement`   VARCHAR(60)  NOT NULL,
  `objet_type`       VARCHAR(40)  NULL DEFAULT NULL,
  `objet_id`         BIGINT UNSIGNED NULL DEFAULT NULL,
  `etablissement_id` INT NULL DEFAULT NULL,
  `donnees`          JSON NULL DEFAULT NULL,
  `adresse_ip`       VARCHAR(45) NULL DEFAULT NULL,
  `cree_le`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ja_utilisateur_date` (`utilisateur_id`,`cree_le`),
  KEY `idx_ja_type_date` (`type_evenement`,`cree_le`),
  KEY `idx_ja_objet` (`objet_type`,`objet_id`),
  KEY `idx_ja_etab_date` (`etablissement_id`,`cree_le`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- 12. PARAMÈTRES GLOBAUX (spec §12.8 → table `settings` existante)
-- ============================================================================
INSERT INTO `settings` (`setting_key`, `setting_value`)
SELECT * FROM (SELECT 'taux_commission_defaut' AS k, '10.00' AS v) t
WHERE NOT EXISTS (SELECT 1 FROM settings s WHERE s.setting_key = 'taux_commission_defaut');

INSERT INTO `settings` (`setting_key`, `setting_value`)
SELECT * FROM (SELECT 'delai_expiration_defaut_jours' AS k, '30' AS v) t
WHERE NOT EXISTS (SELECT 1 FROM settings s WHERE s.setting_key = 'delai_expiration_defaut_jours');

INSERT INTO `settings` (`setting_key`, `setting_value`)
SELECT * FROM (SELECT 'relance_bourse_en_cours_jours' AS k, '90' AS v) t
WHERE NOT EXISTS (SELECT 1 FROM settings s WHERE s.setting_key = 'relance_bourse_en_cours_jours');

INSERT INTO `settings` (`setting_key`, `setting_value`)
SELECT * FROM (SELECT 'relance_dossier_dormant_jours' AS k, '7' AS v) t
WHERE NOT EXISTS (SELECT 1 FROM settings s WHERE s.setting_key = 'relance_dossier_dormant_jours');


-- ============================================================================
-- 13. REPRISE DES DONNÉES EXISTANTES
-- ============================================================================
-- Correspondance des statuts hérités vers la machine à états de la spec §4.1.
--   brouillon → brouillon        (identique)
--   soumis    → soumis           (identique)
--   paye      → soumis           (frais réglés en ligne, pièces non encore analysées)
--   accepte   → valide_50        (dossier recevable, présentiel à venir)
--   inscrit   → valide_100       (inscription définitive)
--   refuse    → rejete
UPDATE `dossiers_inscription` SET `statut` = 'soumis'     WHERE `statut` = 'paye';
UPDATE `dossiers_inscription` SET `statut` = 'valide_50'  WHERE `statut` = 'accepte';
UPDATE `dossiers_inscription` SET `statut` = 'valide_100' WHERE `statut` = 'inscrit';
UPDATE `dossiers_inscription` SET `statut` = 'rejete'     WHERE `statut` = 'refuse';

-- Progression cohérente avec le statut
UPDATE `dossiers_inscription`
SET `progression` = CASE `statut`
    WHEN 'valide_100' THEN 100
    WHEN 'valide_50'  THEN 50
    ELSE 0
END;

-- Jalons rétro-remplis au mieux (date_creation faute de mieux) pour que les
-- écrans de suivi n'affichent pas de trous sur l'historique.
UPDATE `dossiers_inscription`
SET `soumis_le` = COALESCE(`soumis_le`, `date_creation`)
WHERE `statut` IN ('soumis','piece_manquante','valide_50','valide_100','rejete','expire');

-- Année académique par défaut sur les dossiers existants (année civile de dépôt).
UPDATE `dossiers_inscription`
SET `annee_academique` = CONCAT(YEAR(`date_creation`), '-', YEAR(`date_creation`) + 1)
WHERE `annee_academique` IS NULL AND `date_creation` IS NOT NULL;

-- Taux gelé sur les dossiers déjà à 100 % : on reprend le taux courant de
-- l'établissement, faute de trace historique. À partir de cette migration, le
-- gel est fait au moment exact de la validation.
UPDATE `dossiers_inscription` d
JOIN `etablissements` e ON e.id = d.etablissement_id
SET d.`taux_commission_applique` = e.`taux_commission_scolarite`
WHERE d.`statut` = 'valide_100' AND d.`taux_commission_applique` IS NULL;

-- Statut de parcours des utilisateurs, déduit de leurs dossiers.
UPDATE `users` u
SET u.`statut_parcours` = 'candidat'
WHERE u.`statut_parcours` = 'visiteur'
  AND EXISTS (
    SELECT 1 FROM `dossiers_inscription` d
    WHERE d.eleve_id = u.id AND d.statut <> 'brouillon'
  );

UPDATE `users` u
SET u.`statut_parcours` = 'inscrit'
WHERE EXISTS (
    SELECT 1 FROM `dossiers_inscription` d
    WHERE d.eleve_id = u.id AND d.statut = 'valide_100'
  );

-- Commissions existantes : reprise du vocabulaire de facturation.
UPDATE `commissions_scolarite`
SET `statut` = 'a_facturer' WHERE `statut` = 'en_cours';
UPDATE `commissions_scolarite`
SET `statut` = 'reglee', `regle_le` = COALESCE(`regle_le`, `updated_at`)
WHERE `statut` = 'solde';
UPDATE `commissions_scolarite`
SET `periode_facturation` = DATE_FORMAT(`created_at`, '%Y-%m')
WHERE `periode_facturation` IS NULL;


-- ── Nettoyage des gardes ────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS ca_add_col;
DROP PROCEDURE IF EXISTS ca_add_idx;
