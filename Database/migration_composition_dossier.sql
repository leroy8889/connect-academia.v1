-- ============================================================================
-- Connect'Academia — Composition du dossier d'inscription, par cycle
-- ----------------------------------------------------------------------------
-- IDEMPOTENTE : rejouable sans erreur (procédure de garde `ca_add_col_comp`).
--
-- ⚠️ À REJOUER EN PRODUCTION :
--   mysql -u <user> -p <base> < Database/migration_composition_dossier.sql
--
--
-- CE QUI CHANGE, ET POURQUOI
-- ==========================
--
-- Un candidat qui vient de se préinscrire ne sait pas ce qu'il devra APPORTER
-- pour transformer sa préinscription en inscription. L'attestation le disait
-- en une phrase creuse — « l'ensemble des pièces justificatives correspondant
-- à votre niveau d'entrée » — qui n'apprend rien à personne et fait revenir le
-- candidat au guichet une deuxième fois, dossier incomplet.
--
-- Le super administrateur saisit désormais cette liste sur la fiche
-- établissement. Elle est ensuite imprimée sur l'attestation de préinscription,
-- sur la facture proforma, et publiée sur le gabarit public de l'établissement.
--
-- UNE COLONNE JSON, PAS UNE TABLE
-- -------------------------------
-- La composition est une LISTE DE LIBELLÉS ordonnée, sans identité propre :
-- rien ne la référence, rien ne la joint, elle n'est jamais interrogée
-- autrement que « donne-moi celle de cet établissement ». C'est exactement le
-- profil des colonnes `atouts`, `badges_confiance` et `statistiques` déjà
-- portées par cette table, et le même choix est fait ici — une table aurait
-- ajouté une jointure et une cascade de suppression pour zéro requête gagnée.
--
-- À ne pas confondre avec `pieces_dossier` (le module d'inscription), qui
-- porte, lui, des pièces RÉELLEMENT DÉPOSÉES par un candidat : fichiers,
-- statuts de validation, historique. La composition décrite ici est une
-- information éditoriale — ce que l'établissement DEMANDE —, pas un document.
--
-- FORME DU JSON
-- -------------
--   {"commun":["…"],"licence":["…"],"master":["…"]}
--
--   · `commun`  : pièces exigées de tous les candidats, quel que soit le cycle ;
--   · `licence` : pièces qui s'ajoutent au commun pour un candidat L1→L3 ;
--   · `master`  : pièces qui s'ajoutent au commun pour un candidat M1→M2.
--
-- Le cycle d'un dossier est déduit de `niveau_entree` par la table de vérité
-- déjà en place (Services\Orientation\Niveau puis TarifCycle::detecter) : un
-- candidat de licence ne voit JAMAIS les pièces du master, et inversement.
--
-- NULL = non renseigné. Aucun bloc n'est alors affiché nulle part, et le texte
-- générique historique de l'attestation reste en place : le comportement est
-- rigoureusement identique à celui d'avant la migration tant que le super
-- administrateur n'a rien saisi.
-- ============================================================================

SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS ca_add_col_comp;
DELIMITER $$
CREATE PROCEDURE ca_add_col_comp(IN p_table VARCHAR(64), IN p_col VARCHAR(64), IN p_ddl TEXT)
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

CALL ca_add_col_comp('etablissements', 'composition_dossier',
  "`composition_dossier` TEXT NULL DEFAULT NULL
     COMMENT 'JSON {commun:[],licence:[],master:[]} — pièces du dossier d''inscription (NULL = non renseigné)'
     AFTER `statistiques`");

DROP PROCEDURE IF EXISTS ca_add_col_comp;


-- ── Vérification ────────────────────────────────────────────────────────────
-- SELECT nom, composition_dossier FROM etablissements WHERE composition_dossier IS NOT NULL;
