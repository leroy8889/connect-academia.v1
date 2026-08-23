-- Migration : ajoute le statut « inscrit » au cycle de vie d'un dossier.
--
-- Hiérarchie des dossiers d'orientation :
--   brouillon → soumis → (inscription : validée par l'établissement) → inscrit
--                       → (préinscription : aucune validation requise)
--   refuse  = dossier écarté.  accepte = ancien statut de validation (conservé
--   pour compatibilité ascendante avec les dossiers déjà validés).
--
-- « inscrit » n'est atteint qu'après examen du dossier d'inscription par le
-- gestionnaire (pièces reçues) : c'est lui qui déclenche l'attestation.
--
-- Idempotent : rejouable sans risque (MODIFY réécrit simplement l'ENUM).

ALTER TABLE dossiers_inscription
  MODIFY COLUMN statut
  ENUM('brouillon','soumis','paye','accepte','inscrit','refuse')
  NOT NULL DEFAULT 'brouillon';
