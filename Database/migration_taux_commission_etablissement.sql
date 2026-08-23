-- ============================================================================
-- Migration : taux de commission scolarité configurable par établissement
--
-- etablissements.taux_commission_scolarite — pourcentage de la scolarité
-- perçu par Connect'Academia pour chaque élève apporté à l'établissement.
-- Défini par le super admin à la création (ou modification) de
-- l'établissement ; appliqué automatiquement à chaque commission créée
-- sur un dossier « inscrit » (plus de taux fixe 10 % codé en dur).
--
-- ⚠️ Nom volontairement distinct de etablissement_paiement.taux_commission
-- (commission sur les paiements en ligne / reversements) : findById fait un
-- LEFT JOIN sur cette table et un nom identique écraserait la valeur.
--
-- ⚠️ À REJOUER EN PRODUCTION (comme les autres migrations du dossier).
-- L'ALTER TABLE échoue proprement si la colonne existe déjà
-- (« Duplicate column ») — ignorer dans ce cas.
-- ============================================================================

ALTER TABLE `etablissements`
  ADD COLUMN `taux_commission_scolarite` DECIMAL(5,2) NOT NULL DEFAULT 10.00
    COMMENT 'Commission plateforme en % de la scolarité (0 = aucune)'
    AFTER `frais_dossier`;
