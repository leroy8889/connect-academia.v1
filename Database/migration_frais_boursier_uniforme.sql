-- ============================================================================
-- Migration : tarifs boursiers / non-boursiers + frais d'uniforme
--
-- Certains établissements partenaires pratiquent DEUX tarifs par cycle :
-- un tarif boursier (État / fondation) et un tarif non boursier. Les colonnes
-- existantes deviennent explicitement le tarif NON BOURSIER — c'est bien ce
-- qui a été saisi jusqu'ici, aucune donnée n'est déplacée :
--
--   · frais_scolarite                 → Licence, NON boursier (existant)
--   · frais_scolarite_boursier        → Licence, boursier          (NOUVEAU)
--   · frais_scolarite_master          → Master,  NON boursier (existant)
--   · frais_scolarite_master_boursier → Master,  boursier          (NOUVEAU)
--   · frais_uniforme                  → tenue / uniforme annuel    (NOUVEAU)
--
-- 0 = non renseigné → le montant n'apparaît pas sur le gabarit public.
-- Un établissement à tarif unique laisse simplement les champs boursiers à 0 :
-- l'affichage reste identique à aujourd'hui.
--
-- La commission plateforme (taux_commission_scolarite) continue de porter sur
-- les tarifs NON boursiers — comportement inchangé.
--
-- ⚠️ À REJOUER EN PRODUCTION. Migration IDEMPOTENTE : rejouable sans erreur.
--   mysql -u <user> -p <base> < Database/migration_frais_boursier_uniforme.sql
-- ============================================================================

SET @sql := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `etablissements`
       ADD COLUMN `frais_scolarite_boursier` INT NOT NULL DEFAULT 0
         COMMENT ''Scolarité annuelle Licence — tarif boursier (FCFA, 0 = non renseigné)''
         AFTER `frais_scolarite`',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'etablissements'
    AND COLUMN_NAME  = 'frais_scolarite_boursier'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `etablissements`
       ADD COLUMN `frais_scolarite_master_boursier` INT NOT NULL DEFAULT 0
         COMMENT ''Scolarité annuelle Master — tarif boursier (FCFA, 0 = non renseigné)''
         AFTER `frais_scolarite_master`',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'etablissements'
    AND COLUMN_NAME  = 'frais_scolarite_master_boursier'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `etablissements`
       ADD COLUMN `frais_uniforme` INT NOT NULL DEFAULT 0
         COMMENT ''Frais d''''uniforme / tenue (FCFA, 0 = non renseigné)''
         AFTER `frais_scolarite_master_boursier`',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'etablissements'
    AND COLUMN_NAME  = 'frais_uniforme'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
