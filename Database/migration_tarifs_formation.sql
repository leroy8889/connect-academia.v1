-- ============================================================================
-- Connect'Academia — Tarification par formation, niveau et situation de bourse
-- ----------------------------------------------------------------------------
-- IDEMPOTENTE : rejouable sans erreur (procédures de garde `ca_add_col3`).
--
-- ⚠️ À REJOUER EN PRODUCTION :
--   mysql -u <user> -p <base> < Database/migration_tarifs_formation.sql
--
--
-- CE QUI CHANGE, ET POURQUOI
-- ==========================
--
-- 1. FRAIS D'INSCRIPTION — quatre tarifs au lieu d'un
--    -----------------------------------------------
--    `etablissements.prix_inscription` portait UN seul montant. Or les
--    partenaires facturent l'inscription différemment selon le cycle ET selon
--    que l'étudiant est boursier : un boursier de licence et un non-boursier
--    de master ne règlent pas la même chose. Un montant unique obligeait le
--    guichet à corriger à la main, donc à faire diverger la vitrine du reçu.
--
--    `prix_inscription` et `frais_dossier` sont CONSERVÉS : ils restent le
--    repli des fiches non encore mises à jour, et l'affichage public actuel
--    continue de les lire. Les quatre colonnes naissent à 0, donc INACTIVES :
--    tant que le super administrateur ne les a pas renseignées, la résolution
--    retombe sur eux et rien ne change (cf. §4).
--
-- 2. SCOLARITÉ — une grille par formation, niveau et bourse
--    -----------------------------------------------------
--    La scolarité vivait sur la fiche établissement, par cycle (quatre
--    colonnes `frais_scolarite*`). Deux réalités du terrain ne rentraient pas
--    dans ce moule :
--
--      · l'établissement A tarife L1, L2 et L3 séparément (non-boursiers),
--        et applique un tarif unique à tous ses boursiers de licence ;
--      · l'établissement B applique un tarif unique par cycle, boursier
--        comme non-boursier.
--
--    `tarifs_formation` porte les deux cas sans en privilégier aucun : une
--    ligne par (formation, cycle, niveau), et le niveau VIDE signifie « tout
--    le cycle ». L'établissement A remplit ses lignes L1/L2/L3 ; le B remplit
--    la seule ligne de cycle. La résolution (Services\Orientation\TarifCycle)
--    cherche le niveau exact, puis le cycle, puis retombe sur l'ancien
--    dispositif — aucune fiche existante ne perd son tarif.
--
--    Les colonnes `frais_scolarite*` de `etablissements` et
--    `filieres_etablissement.frais_scolarite` NE SONT PAS SUPPRIMÉES : elles
--    deviennent le dernier repli. Supprimer aurait cassé la page publique de
--    chaque établissement le temps que la grille soit saisie.
--
-- 3. COMMISSIONS — la base dépend désormais de la situation de bourse
--    ---------------------------------------------------------------
--    Jusqu'ici la commission portait TOUJOURS sur la scolarité annuelle.
--    La règle métier devient :
--
--      · boursier déclaré      → 10 % des FRAIS D'INSCRIPTION
--                                (sa scolarité est prise en charge par l'ANBG,
--                                 la commissionner reviendrait à facturer un
--                                 flux que l'établissement ne perçoit pas de
--                                 l'étudiant) ;
--      · dossier ANBG en cours → 10 % de la SCOLARITÉ ;
--      · non boursier          → 10 % de la SCOLARITÉ (inchangé).
--
--    `montant_scolarite` garde son nom mais devient LA BASE RETENUE, quelle
--    qu'elle soit. Le renommer aurait cassé les six écrans de suivi qui la
--    lisent, pour un gain purement cosmétique. `base_commission` dit de quelle
--    nature elle est, et `statut_bourse_applique` fige la situation qui l'a
--    justifiée : sans ces deux colonnes, une commission de 15 000 F ne pourrait
--    plus être expliquée six mois plus tard.
--
--    ⚠️ CONSÉQUENCE ASSUMÉE — le statut de bourse a désormais un effet
--    financier, ce que Services\Orientation\SituationBourse interdisait par
--    principe (anti-fraude). Les garde-fous qui remplacent ce principe :
--      · toute déclaration reste tracée dans `historique_statut_bourse`
--        (auteur, horodatage serveur, justificatif) ;
--      · la révision d'une commission déjà partiellement encaissée est
--        REFUSÉE au profit d'un signalement au super administrateur
--        (`revision_requise`), seul habilité à arbitrer.
--
-- 4. AUCUNE DONNÉE N'EST DÉPLACÉE NI DEVINÉE
--    ---------------------------------------
--    Les commissions déjà émises conservent leur montant et leur base : elles
--    sont marquées `base_commission = 'scolarite'`, ce qu'elles étaient
--    réellement. Rien n'est recalculé rétroactivement — une commission facturée
--    ou réglée est un fait comptable, pas une vue.
-- ============================================================================

SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS ca_add_col3;
DELIMITER $$
CREATE PROCEDURE ca_add_col3(IN p_table VARCHAR(64), IN p_col VARCHAR(64), IN p_ddl TEXT)
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

DROP PROCEDURE IF EXISTS ca_add_idx3;
DELIMITER $$
CREATE PROCEDURE ca_add_idx3(IN p_table VARCHAR(64), IN p_idx VARCHAR(64), IN p_ddl TEXT)
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
-- 1. ÉTABLISSEMENTS — frais d'inscription par cycle et par situation de bourse
-- ----------------------------------------------------------------------------
-- 0 = non renseigné → la résolution retombe sur `prix_inscription` +
-- `frais_dossier`, c'est-à-dire sur le comportement d'avant cette migration.
-- ============================================================================
CALL ca_add_col3('etablissements', 'frais_inscription_licence',
  "`frais_inscription_licence` INT NOT NULL DEFAULT 0
     COMMENT 'Frais d''inscription cycle Licence — NON boursier (FCFA, 0 = non renseigné)'
     AFTER `frais_dossier`");

CALL ca_add_col3('etablissements', 'frais_inscription_licence_boursier',
  "`frais_inscription_licence_boursier` INT NOT NULL DEFAULT 0
     COMMENT 'Frais d''inscription cycle Licence — BOURSIER (FCFA, 0 = non renseigné)'
     AFTER `frais_inscription_licence`");

CALL ca_add_col3('etablissements', 'frais_inscription_master',
  "`frais_inscription_master` INT NOT NULL DEFAULT 0
     COMMENT 'Frais d''inscription cycle Master — NON boursier (FCFA, 0 = non renseigné)'
     AFTER `frais_inscription_licence_boursier`");

CALL ca_add_col3('etablissements', 'frais_inscription_master_boursier',
  "`frais_inscription_master_boursier` INT NOT NULL DEFAULT 0
     COMMENT 'Frais d''inscription cycle Master — BOURSIER (FCFA, 0 = non renseigné)'
     AFTER `frais_inscription_master`");


-- ============================================================================
-- 2. TARIFS_FORMATION — grille de scolarité par formation / niveau / bourse
-- ----------------------------------------------------------------------------
-- `niveau` VIDE ('') = tarif de TOUT LE CYCLE. Ce n'est pas un NULL : MySQL
-- n'applique pas l'unicité aux NULL, et deux lignes « tout le cycle »
-- concurrentes pour une même formation rendraient le tarif indéterminé.
--
-- Les deux montants cohabitent sur la même ligne parce qu'ils décrivent la
-- même chose vue de deux situations. Les séparer en deux lignes aurait
-- doublé la grille de saisie sans rien apporter.
--
-- 0 = non renseigné → la résolution descend d'un cran (niveau → cycle →
-- fiche filière → fiche établissement). C'est ce qui permet à un
-- établissement de ne remplir QUE la ligne de cycle pour ses boursiers,
-- tout en détaillant L1/L2/L3 pour ses non-boursiers.
-- ============================================================================
CREATE TABLE IF NOT EXISTS `tarifs_formation` (
  `id`                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `filiere_id`             INT NOT NULL COMMENT 'filieres_etablissement.id',
  `cycle`                  ENUM('licence','master') NOT NULL,
  `niveau`                 VARCHAR(8) NOT NULL DEFAULT ''
                             COMMENT 'L1, L2, L3, M1, M2 — vide = tout le cycle',
  `scolarite_non_boursier` INT NOT NULL DEFAULT 0 COMMENT 'FCFA/an (0 = non renseigné)',
  `scolarite_boursier`     INT NOT NULL DEFAULT 0 COMMENT 'FCFA/an (0 = non renseigné)',
  `maj_le`                 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tarif_formation` (`filiere_id`, `cycle`, `niveau`),
  KEY `idx_tarif_filiere_cycle` (`filiere_id`, `cycle`),
  CONSTRAINT `fk_tarif_filiere` FOREIGN KEY (`filiere_id`)
    REFERENCES `filieres_etablissement`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- 3. COMMISSIONS_SCOLARITE — nature de la base et signalement de révision
-- ============================================================================

-- Nature de `montant_scolarite`. Les lignes existantes portaient toutes la
-- scolarité : le DEFAULT dit donc la vérité sur le passé, sans UPDATE.
CALL ca_add_col3('commissions_scolarite', 'base_commission',
  "`base_commission` ENUM('scolarite','frais_inscription') NOT NULL DEFAULT 'scolarite'
     COMMENT 'Nature du montant porté par montant_scolarite'
     AFTER `montant_scolarite`");

-- Situation de bourse GELÉE au moment du calcul. Le dossier peut changer de
-- statut demain ; la commission doit rester explicable telle qu'elle a été
-- émise. NULL sur les lignes antérieures : « non tracé », et non « non boursier ».
CALL ca_add_col3('commissions_scolarite', 'statut_bourse_applique',
  "`statut_bourse_applique` VARCHAR(20) NULL DEFAULT NULL
     COMMENT 'statut_bourse du dossier au moment du calcul (gelé)'
     AFTER `base_commission`");

-- Signalement au super administrateur : la situation de bourse a changé alors
-- que la commission n'était plus révisable automatiquement (tranche encaissée
-- ou commission close). On ne touche pas à l'argent, on lève la main.
CALL ca_add_col3('commissions_scolarite', 'revision_requise',
  "`revision_requise` TINYINT(1) NOT NULL DEFAULT 0
     COMMENT '1 = base à réexaminer par le super administrateur'");

CALL ca_add_col3('commissions_scolarite', 'revision_motif',
  "`revision_motif` VARCHAR(255) NULL DEFAULT NULL");

CALL ca_add_col3('commissions_scolarite', 'revision_le',
  "`revision_le` DATETIME NULL DEFAULT NULL COMMENT 'Horodatage du signalement'");

CALL ca_add_idx3('commissions_scolarite', 'idx_commission_revision',
  'KEY `idx_commission_revision` (`revision_requise`,`etablissement_id`)');


-- ============================================================================
-- 4. AUCUN SEMIS — et c'est un choix, pas un oubli
-- ----------------------------------------------------------------------------
-- Il aurait été tentant de recopier les tarifs existants dans les nouvelles
-- colonnes et dans la grille, pour que le super administrateur les retrouve
-- pré-remplis. C'est exactement ce qu'il ne faut pas faire.
--
-- La résolution retient le niveau le PLUS PRÉCIS qui soit renseigné. Recopier
-- `etablissements.frais_scolarite` dans une ligne « tout le cycle » de la
-- grille reviendrait donc à FIGER ce montant : la grille l'emporterait
-- désormais, et le super administrateur pourrait corriger le champ de la fiche
-- établissement autant qu'il veut sans que plus aucun dossier n'en tienne
-- compte. Un champ qui n'a plus d'effet mais qui reste modifiable est le pire
-- des deux mondes.
--
-- Même raisonnement pour `prix_inscription` + `frais_dossier` : les quatre
-- nouvelles colonnes restent à 0, la résolution retombe donc sur eux, et le
-- comportement est RIGOUREUSEMENT identique à celui d'avant la migration tant
-- que personne n'a rien saisi.
--
-- Conséquence voulue :
--   · le jour du déploiement, aucun tarif ne bouge, aucun dossier ne change
--     de base de commission ;
--   · les nouveaux champs sont OPT-IN — ils ne prennent le dessus qu'une fois
--     délibérément renseignés ;
--   · le formulaire affiche la valeur héritée en indication, pour que
--     l'écart entre « vide » et « repris de la fiche » reste lisible.
--
-- Les commissions déjà émises ne sont pas davantage touchées : elles restent
-- marquées `base_commission = 'scolarite'`, ce qu'elles étaient réellement.
-- Une commission facturée est un fait comptable, pas une vue recalculable.
-- ============================================================================


-- ── Nettoyage des gardes ────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS ca_add_col3;
DROP PROCEDURE IF EXISTS ca_add_idx3;


-- ── Vérification ────────────────────────────────────────────────────────────
-- SELECT nom, frais_inscription_licence, frais_inscription_licence_boursier,
--        frais_inscription_master, frais_inscription_master_boursier
--   FROM etablissements;
-- SELECT f.nom, t.cycle, t.niveau, t.scolarite_non_boursier, t.scolarite_boursier
--   FROM tarifs_formation t JOIN filieres_etablissement f ON f.id = t.filiere_id
--  ORDER BY f.nom, t.cycle, t.niveau;
