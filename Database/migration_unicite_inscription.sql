-- ============================================================================
-- Connect'Academia — UNE SEULE INSCRIPTION par école et par année académique
-- ----------------------------------------------------------------------------
-- IDEMPOTENTE : rejouable sans erreur (procédure de garde `ca_add_idx7`).
--
-- ⚠️ À REJOUER EN PRODUCTION :
--   mysql -u <user> -p <base> < Database/migration_unicite_inscription.sql
--
--
-- CE QUI CHANGE, ET POURQUOI
-- ==========================
--
-- Un même candidat pouvait ouvrir autant de dossiers d'INSCRIPTION qu'il le
-- voulait dans le même établissement pour la même année : un retour arrière du
-- navigateur, un second onglet, une hésitation sur la filière, et le guichet
-- recevait trois dossiers pour une seule personne — chacun avec sa référence,
-- sa proforma, ses pièces à instruire, et sa commission à la validation.
--
-- La règle est désormais : UNE inscription par (candidat, établissement, année
-- académique). Elle est portée par Services\Orientation\UniciteInscription et
-- appliquée aux deux seuls chemins qui créent une inscription — le parcours
-- public (étape 1) et le guichet.
--
--
-- POURQUOI PAS UN INDEX UNIQUE
-- ============================
--
-- Parce que la contrainte est PARTIELLE, et que MySQL ne sait pas l'exprimer :
--
--   · elle ne vise que les INSCRIPTIONS, et « être une inscription » se lit
--     entre autres dans la table `inscriptions` (Dossier::SQL_EST_INSCRIPTION) —
--     une sous-requête, donc interdite dans une colonne générée ;
--   · elle ne vise que les dossiers VIVANTS : un dossier rejeté, expiré ou
--     annulé ne doit bloquer personne, sans quoi un refus dû à une pièce
--     illisible enfermerait le candidat pour l'année entière ;
--   · l'identité du candidat est « le compte OU l'e-mail », parce qu'un dossier
--     ouvert au guichet n'a pas toujours de compte rattaché.
--
-- Cette migration pose donc ce qui MANQUE pour que la règle applicative soit
-- exacte et rapide : l'année académique sur les dossiers qui n'en avaient pas,
-- et l'index qui sert la recherche.
--
--
-- ⚠️ LES DOUBLONS EXISTANTS NE SONT PAS SUPPRIMÉS
-- ===============================================
-- Aucun dossier n'est effacé ni fusionné par cette migration : supprimer un
-- dossier d'inscription détruit des pièces déposées, une référence peut-être
-- déjà imprimée sur un récépissé, et possiblement une commission. Le tri
-- relève de l'établissement, dossier par dossier. La requête de VÉRIFICATION en
-- fin de fichier les liste ; la règle applicative empêche, elle, d'en créer de
-- nouveaux.
-- ============================================================================

DROP PROCEDURE IF EXISTS ca_add_idx7;
DELIMITER $$
CREATE PROCEDURE ca_add_idx7(IN p_table VARCHAR(64), IN p_idx VARCHAR(64), IN p_ddl TEXT)
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
-- 1. ANNÉE ACADÉMIQUE — comblement des dossiers qui n'en portaient pas
-- ----------------------------------------------------------------------------
-- Le guichet (Dossier::createGuichet) ne renseignait pas la colonne : tous les
-- dossiers saisis au comptoir sont à NULL. Sans année, ils n'appartiennent à
-- aucune campagne — ils échappent aux bilans, aux filtres par année, et à la
-- règle d'unicité, qui compare précisément là-dessus.
--
-- L'année est déduite de la date de dépôt selon la MÊME règle que
-- Core\AnneeUniversitaire : bascule en JUIN (mois >= 6 → année N/N+1), parce
-- que les campagnes ouvrent avant la rentrée de septembre.
--
-- ⚠️ Si ANNEE_UNIVERSITAIRE est forcée dans le .env, elle prime dans le code
--    mais PAS ici : ce comblement reconstitue l'année RÉELLE du dépôt, ce qui
--    est le comportement voulu pour de l'historique.
-- ============================================================================
UPDATE `dossiers_inscription`
   SET `annee_academique` = CASE
         WHEN MONTH(COALESCE(`soumis_le`, `date_creation`, NOW())) >= 6
           THEN CONCAT(YEAR(COALESCE(`soumis_le`, `date_creation`, NOW())), '-',
                       YEAR(COALESCE(`soumis_le`, `date_creation`, NOW())) + 1)
         ELSE CONCAT(YEAR(COALESCE(`soumis_le`, `date_creation`, NOW())) - 1, '-',
                     YEAR(COALESCE(`soumis_le`, `date_creation`, NOW())))
       END
 WHERE `annee_academique` IS NULL
    OR TRIM(`annee_academique`) = '';


-- ============================================================================
-- 2. INDEX — la recherche d'unicité, sur le chemin de chaque création
-- ----------------------------------------------------------------------------
-- UniciteInscription::existante() interroge (etablissement_id, annee_academique)
-- puis filtre sur le statut et l'identité. L'index couvre le préfixe sélectif ;
-- `eleve_id` en troisième colonne évite le retour à la table dans le cas le
-- plus fréquent — un candidat connecté sur le parcours public.
--
-- Ce n'est PAS un index unique : voir l'en-tête, la contrainte est partielle.
-- ============================================================================
CALL ca_add_idx7('dossiers_inscription', 'idx_dossier_unicite_inscription',
  'KEY `idx_dossier_unicite_inscription` (`etablissement_id`,`annee_academique`,`eleve_id`)');

-- L'e-mail est la seconde clé d'identité (dossier ouvert au guichet, compte
-- créé après coup). Colonne bornée à 100 caractères dans l'index : suffisant
-- pour discriminer une adresse, et compatible avec la limite de longueur de clé.
CALL ca_add_idx7('dossiers_inscription', 'idx_dossier_etab_email',
  'KEY `idx_dossier_etab_email` (`etablissement_id`,`email`(100))');


DROP PROCEDURE IF EXISTS ca_add_idx7;


-- ============================================================================
-- VÉRIFICATION (à lancer après la migration)
-- ----------------------------------------------------------------------------
-- 1. Plus aucun dossier sans année :
--
--    SELECT COUNT(*) FROM dossiers_inscription
--     WHERE annee_academique IS NULL OR TRIM(annee_academique) = '';
--    → 0
--
-- 2. Doublons d'inscription HÉRITÉS, à trier à la main par l'établissement.
--    Mêmes critères que UniciteInscription : inscriptions seulement, statuts
--    vivants seulement, identité = e-mail (le plus large des deux).
--
--    SELECT d.etablissement_id, e.nom AS etablissement, d.annee_academique,
--           LOWER(TRIM(d.email)) AS candidat, COUNT(*) AS nb,
--           GROUP_CONCAT(d.id ORDER BY d.id) AS dossiers
--      FROM dossiers_inscription d
--      JOIN etablissements e ON e.id = d.etablissement_id
--     WHERE d.statut IN ('brouillon','soumis','piece_manquante','valide_50',
--                        'valide_100','paye','accepte','inscrit')
--       AND (EXISTS (SELECT 1 FROM inscriptions i WHERE i.dossier_id = d.id)
--            OR d.reference IS NOT NULL
--            OR d.statut = 'brouillon')
--       AND TRIM(COALESCE(d.email, '')) <> ''
--     GROUP BY d.etablissement_id, d.annee_academique, LOWER(TRIM(d.email))
--    HAVING nb > 1
--     ORDER BY nb DESC, d.etablissement_id;
-- ============================================================================
