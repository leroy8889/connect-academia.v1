-- ============================================================================
-- Connect'Academia — L'établissement décide s'il accepte les BOURSIERS
-- ----------------------------------------------------------------------------
-- IDEMPOTENTE : rejouable sans erreur (procédure de garde `ca_add_col5`).
--
-- ⚠️ À REJOUER EN PRODUCTION :
--   mysql -u <user> -p <base> < Database/migration_accepte_boursiers.sql
--
--
-- CE QUI CHANGE, ET POURQUOI
-- ==========================
--
-- Plusieurs établissements partenaires ne veulent pas recevoir de candidatures
-- de boursiers : conventions ANBG absentes, quotas déjà pourvus, filières hors
-- périmètre de prise en charge. Jusqu'ici la plateforme posait la question de
-- la bourse à TOUS les candidats, puis l'établissement refusait le dossier à la
-- main — un refus tardif, coûteux pour le candidat comme pour le guichet.
--
-- `etablissements.accepte_boursiers` porte cet arbitrage, PAR ÉTABLISSEMENT, et
-- il est réservé au super administrateur (fiche établissement → « Identité »).
--
--   1 (défaut) → comportement actuel : le bloc « situation de bourse » est posé
--                au candidat, les pièces ANBG sont demandées, les tarifs
--                boursiers sont affichés.
--   0          → tout ce qui concerne la bourse DISPARAÎT du front-office :
--                bloc de déclaration du formulaire d'inscription, colonne
--                « Boursier » des grilles tarifaires, bloc « Ma situation de
--                bourse » de l'espace candidat. Les dossiers créés sont
--                enregistrés en `non_boursier`, y compris si un POST forgé
--                tente autre chose (PolitiqueBoursiers::normaliserPost).
--
-- La colonne naît à 1 : tant que le super administrateur ne touche à rien, le
-- comportement est identique à celui d'avant cette migration.
--
-- ⚠️ CE QUI N'EST PAS TOUCHÉ, ET POURQUOI :
--   · Les dossiers DÉJÀ déposés avec une situation de bourse déclarée gardent
--     leur `statut_bourse`, leur historique et leur commission. Fermer une
--     porte ne réécrit pas ce qui est entré avant : la comptabilité et les
--     documents officiels déjà émis resteraient sinon inexplicables.
--   · Les tarifs boursiers saisis sur la fiche restent en base. Le réglage est
--     réversible, et effacer une grille de prix parce qu'une case a été
--     décochée serait une perte de saisie irrattrapable.
--   · Le back-office (guichet, traçabilité, commissions) continue d'afficher la
--     situation de bourse des dossiers qui en portent une.
-- ============================================================================

DROP PROCEDURE IF EXISTS ca_add_col5;
DELIMITER $$
CREATE PROCEDURE ca_add_col5(IN p_table VARCHAR(64), IN p_col VARCHAR(64), IN p_ddl TEXT)
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


-- ============================================================================
-- ÉTABLISSEMENTS — accepte, ou non, les candidatures de boursiers
-- ============================================================================
CALL ca_add_col5('etablissements', 'accepte_boursiers',
  "`accepte_boursiers` TINYINT(1) NOT NULL DEFAULT 1
     COMMENT '0 = les candidatures de boursiers sont fermees : le front-office masque tout ce qui concerne la bourse et force non_boursier.'");


DROP PROCEDURE IF EXISTS ca_add_col5;

-- ============================================================================
-- VÉRIFICATION (à lancer après la migration)
-- ----------------------------------------------------------------------------
-- SELECT id, nom, accepte_boursiers FROM etablissements ORDER BY nom;
-- ============================================================================
