-- ============================================================================
-- Migration : frais de scolarité par cycle (licence / master)
--
-- La colonne existante `frais_scolarite` devient explicitement la scolarité
-- du CYCLE LICENCE (aucune donnée n'est déplacée — le montant déjà saisi est
-- bien celui de la licence). On ajoute `frais_scolarite_master` pour le
-- cycle master.
--
-- · frais_scolarite         → scolarité annuelle cycle Licence (FCFA)
-- · frais_scolarite_master  → scolarité annuelle cycle Master  (FCFA)
--
-- 0 = non renseigné → le montant n'est pas affiché sur la page publique.
--
-- La commission plateforme (taux_commission_scolarite) reste calculée sur
-- `frais_scolarite` (licence) — comportement inchangé.
--
-- ⚠️ À REJOUER EN PRODUCTION (comme les autres migrations du dossier).
-- L'ALTER TABLE échoue proprement si la colonne existe déjà
-- (« Duplicate column ») — ignorer dans ce cas.
-- ============================================================================

ALTER TABLE `etablissements`
  ADD COLUMN `frais_scolarite_master` INT NOT NULL DEFAULT 0
    COMMENT 'Frais de scolarité annuels cycle Master en FCFA (0 = non renseigné)'
    AFTER `frais_scolarite`;
