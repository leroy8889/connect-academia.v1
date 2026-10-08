-- ============================================================================
-- Connect'Academia — RETRAIT de la situation de bourse « dossier en cours »
-- ----------------------------------------------------------------------------
-- IDEMPOTENTE : rejouable sans erreur.
--
-- ⚠️ À REJOUER EN PRODUCTION :
--   mysql -u <user> -p <base> < Database/migration_suppression_dossier_en_cours.sql
--
--
-- CE QUI CHANGE, ET POURQUOI
-- ==========================
--
-- Le formulaire d'inscription proposait TROIS situations de bourse :
--
--     · Je ne suis pas boursier                            (non_boursier)
--     · Je suis boursier                                   (boursier)
--     · Mon dossier est en cours de traitement à l'ANBG    (dossier_en_cours)
--
-- Après discussion avec les établissements partenaires, la troisième est
-- retirée. Elle décrivait une ATTENTE, pas une situation : au guichet, personne
-- ne savait quoi en faire — ni tarif propre, ni pièce exigible, ni décision
-- possible —, et le candidat restait indéfiniment dans un entre-deux qu'aucun
-- écran ne savait résoudre.
--
-- La règle est désormais simple : tant que la bourse n'est pas accordée, le
-- candidat est NON BOURSIER. Le jour où l'ANBG répond, il se déclare boursier
-- lui-même depuis son espace — à tout moment, y compris des mois après
-- l'inscription définitive. La bascule est tracée (`historique_statut_bourse`)
-- et la commission est réévaluée automatiquement tant qu'aucune tranche n'a été
-- encaissée (CommissionDossier::reevaluerBase).
--
--
-- POURQUOI LA BASCULE EST FINANCIÈREMENT NEUTRE
-- =============================================
--
-- `dossier_en_cours` n'a JAMAIS eu de traitement financier propre :
--
--   · TarifCycle::baseCommission() ne distingue que le BOURSIER CONFIRMÉ ;
--     toute autre valeur — `dossier_en_cours` comme `non_boursier` — donne la
--     SCOLARITÉ comme assiette de commission ;
--   · TarifCycle::detail() ne pose `est_boursier` que sur le boursier confirmé,
--     donc la grille tarifaire appliquée était déjà la grille NON BOURSIER.
--
-- Basculer ces dossiers en `non_boursier` ne change donc aucun montant, aucune
-- commission déjà émise, aucun document déjà signé. C'est un renommage de la
-- valeur, pas une révision de facturation.
--
--
-- ⚠️ CE QUI N'EST PAS TOUCHÉ, ET POURQUOI
-- =======================================
--
--   · `historique_statut_bourse` n'est PAS réécrit. La table est en ajout seul
--     et ses colonnes sont des VARCHAR : les lignes antérieures gardent la
--     valeur `dossier_en_cours`, et SituationBourse sait encore l'afficher
--     (LIBELLES_RETIRES). Réécrire un historique pour le rendre cohérent avec
--     le présent, c'est effacer ce qui s'est réellement passé.
--
--   · `journal_activite` n'est pas touché non plus, pour la même raison.
--
--   · Les documents `preuve_depot_anbg` DÉJÀ DÉPOSÉS restent en base et restent
--     consultables : ce sont des pièces que le candidat a réellement fournies.
--     Seule la LIGNE DE RÉFÉRENTIEL qui les réclamait disparaît.
--
--   · L'ENUM `dossiers_inscription.statut_bourse` n'est PAS rétréci. Les
--     valeurs héritées sont conservées dans l'ENUM exactement comme l'a fait
--     migration_inscription_boursiers.sql pour les statuts de dossier : plus
--     aucune écriture ne les produit (estValide() les refuse), mais un
--     rétrécissement transformerait en erreur silencieuse toute ligne qu'un
--     import ou une restauration partielle ramènerait.
-- ============================================================================


-- ============================================================================
-- 1. DOSSIERS — bascule des déclarations « dossier en cours » en « non boursier »
-- ----------------------------------------------------------------------------
-- L'historique est écrit AVANT la bascule : il lit encore l'ancienne valeur.
-- `role_modificateur = 'admin'` et `modifie_par = NULL` : la correction vient de
-- la plateforme, pas d'une personne. Le commentaire dit pourquoi, en clair, pour
-- le candidat qui relira son historique dans son espace.
-- ============================================================================
INSERT INTO `historique_statut_bourse`
  (`dossier_id`, `ancien_statut`, `nouveau_statut`, `justificatif_cle`,
   `modifie_par`, `role_modificateur`, `commentaire`, `modifie_le`)
SELECT d.`id`, 'dossier_en_cours', 'non_boursier', NULL,
       NULL, 'admin',
       'Situation « dossier en cours de traitement à l''ANBG » retiree de la plateforme : le dossier devient non boursier. Aucun changement de tarif ni de commission. Declarez-vous boursier depuis votre espace si votre bourse est accordee.',
       NOW()
FROM `dossiers_inscription` d
WHERE d.`statut_bourse` = 'dossier_en_cours';

UPDATE `dossiers_inscription`
   SET `statut_bourse`       = 'non_boursier',
       `date_depot_anbg`     = NULL,
       `bourse_declaree_par` = 'admin'
 WHERE `statut_bourse` = 'dossier_en_cours';

-- `bourse_declaree_le` est VOLONTAIREMENT laissé tel quel : c'est la date à
-- laquelle le candidat a déclaré sa situation, et il l'a bien déclarée ce
-- jour-là. La date de la correction, elle, est dans l'historique ci-dessus.

-- Dossiers qui n'ont jamais porté ce statut mais gardent une date de dépôt
-- orpheline (reprise de données, import partiel) : la colonne n'appartenait
-- qu'à la situation retirée, plus rien ne la lit.
UPDATE `dossiers_inscription`
   SET `date_depot_anbg` = NULL
 WHERE `date_depot_anbg` IS NOT NULL;


-- ============================================================================
-- 2. RÉFÉRENTIEL DES PIÈCES — retrait de « Preuve de dépôt du dossier ANBG »
-- ----------------------------------------------------------------------------
-- Ces lignes ont été semées par migration_inscription_boursiers.sql avec
-- `systeme = 1` : elles appartiennent à la plateforme, aucun établissement ne
-- les a saisies, et le formulaire du gestionnaire ne sait pas les recréer
-- (PieceRequise::create ne renseigne ni `code_interne` ni `condition_bourse`).
--
-- Le DELETE est borné à `systeme = 1` : si un établissement avait, contre toute
-- attente, une pièce à lui portant ce code, elle survivrait.
--
-- `documents_orientation.piece_requise_id` n'est pas contraint par une clé
-- étrangère et les documents portent leur propre `libelle` et `type_document` :
-- les pièces déjà déposées restent affichées correctement.
-- ============================================================================
DELETE FROM `pieces_justificatives_requises`
 WHERE `code_interne` = 'preuve_depot_anbg'
   AND `systeme` = 1;

-- Filet : une pièce conditionnée sur la situation retirée ne serait plus jamais
-- proposée à personne (Dossier::piecesPourBourse filtre sur `condition_bourse`),
-- mais elle continuerait de s'afficher au gestionnaire comme une pièce active
-- rattachée à un statut qui n'existe plus. On la ramène au tronc commun en
-- NON obligatoire : visible, modifiable, jamais bloquante.
UPDATE `pieces_justificatives_requises`
   SET `condition_bourse` = 'tous',
       `obligatoire`      = 0
 WHERE `condition_bourse` = 'dossier_en_cours';


-- ============================================================================
-- 3. RÉGLAGE DEVENU SANS OBJET — seuil de relance des dossiers ANBG
-- ----------------------------------------------------------------------------
-- La tâche planifiée `relance-bourse` relançait les « dossier en cours » de plus
-- de N jours. Elle a disparu avec le statut (TachesPlanifiees,
-- bin/taches-orientation.php) ; son réglage n'a plus de lecteur.
-- ============================================================================
DELETE FROM `settings` WHERE `setting_key` = 'relance_bourse_en_cours_jours';


-- ============================================================================
-- VÉRIFICATION (à lancer après la migration)
-- ----------------------------------------------------------------------------
-- SELECT statut_bourse, COUNT(*) FROM dossiers_inscription GROUP BY statut_bourse;
--   → aucune ligne 'dossier_en_cours'
--
-- SELECT COUNT(*) FROM dossiers_inscription WHERE date_depot_anbg IS NOT NULL;
--   → 0
--
-- SELECT id, etablissement_id, libelle, condition_bourse, obligatoire
--   FROM pieces_justificatives_requises WHERE code_interne = 'preuve_depot_anbg';
--   → aucune ligne (ou, à la rigueur, des pièces non système ramenées à 'tous')
--
-- SELECT COUNT(*) FROM historique_statut_bourse WHERE nouveau_statut = 'non_boursier'
--   AND ancien_statut = 'dossier_en_cours';
--   → autant de lignes que de dossiers basculés
-- ============================================================================
