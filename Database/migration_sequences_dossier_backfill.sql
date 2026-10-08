-- ============================================================================
-- Connect'Academia — Amorçage des compteurs de référence de dossier
-- ============================================================================
-- migration_inscription_boursiers.sql a créé `sequences_dossier` mais l'a
-- laissée VIDE. Les dossiers antérieurs portaient déjà des références
-- (CA-2026-HAV-000001…), si bien que le compteur repartait de 0 et réémettait
-- un numéro déjà pris : l'index unique `uq_dossier_reference` faisait alors
-- échouer toute la transaction de soumission — le candidat restait bloqué à
-- l'étape 2 avec « Votre dossier n'a pas pu être enregistré ».
--
-- On amorce ici chaque couple (établissement, année) sur le plus grand numéro
-- déjà émis. Idempotent : relançable sans risque, le compteur ne recule jamais.
-- ============================================================================

INSERT INTO sequences_dossier (etablissement_id, annee, dernier_numero)
SELECT
    d.etablissement_id,
    CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(d.reference, '-', 2), '-', -1) AS UNSIGNED) AS annee,
    MAX(CAST(SUBSTRING_INDEX(d.reference, '-', -1) AS UNSIGNED))                     AS dernier_numero
FROM dossiers_inscription d
WHERE d.reference REGEXP '^CA-[0-9]{4}-[A-Z0-9]{3,5}-[0-9]{6}$'
GROUP BY d.etablissement_id, annee
ON DUPLICATE KEY UPDATE
    dernier_numero = GREATEST(sequences_dossier.dernier_numero, VALUES(dernier_numero));
