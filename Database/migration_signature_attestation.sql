-- ============================================================================
-- Migration : signature / cachet de l'établissement sur les attestations
--
-- etablissements.signature_cle — clé B2 (stockage privé) de l'image de la
-- signature/cachet uploadée par le gestionnaire de l'établissement depuis
-- /gestionnaire/parametres. Embarquée en data URI (LogoDataUri) sur
-- l'attestation d'inscription, à l'emplacement du bloc « La Direction
-- Académique » (là où figurait le logo), pour authentifier le document.
--
-- Vide/NULL par défaut : le gabarit bascule alors sur le logo (comportement
-- actuel), jamais de régression.
--
-- ⚠️ À REJOUER EN PRODUCTION (comme les autres migrations du dossier).
-- L'ALTER TABLE échoue proprement si la colonne existe déjà
-- (« Duplicate column ») — ignorer dans ce cas.
-- ============================================================================

ALTER TABLE `etablissements`
  ADD COLUMN `signature_cle` VARCHAR(255) NULL DEFAULT NULL
    COMMENT 'Clé B2 de la signature/cachet imprimé sur les attestations'
    AFTER `logo_cle`;
