-- ============================================================================
-- Migration : frais de scolarité de l'établissement
--
-- etablissements.frais_scolarite — montant annuel de la scolarité (FCFA)
-- renseigné par le super admin dans le formulaire de création / modification
-- d'établissement. Sert de référence affichée (fiche admin) et de valeur par
-- défaut pour le calcul des commissions de scolarité
-- (taux_commission_scolarite s'applique à ce montant).
--
-- 0 = non renseigné : le comportement existant (montant saisi à la main lors
-- de la création d'une commission) reste inchangé.
--
-- ⚠️ À REJOUER EN PRODUCTION (comme les autres migrations du dossier).
-- L'ALTER TABLE échoue proprement si la colonne existe déjà
-- (« Duplicate column ») — ignorer dans ce cas.
-- ============================================================================

ALTER TABLE `etablissements`
  ADD COLUMN `frais_scolarite` INT NOT NULL DEFAULT 0
    COMMENT 'Frais de scolarité annuels en FCFA (0 = non renseigné)'
    AFTER `frais_dossier`;
