-- ═══════════════════════════════════════════════════════════════════════════
-- Nettoyage : lignes `preinscriptions` posées sur des dossiers d'INSCRIPTION
--
-- POURQUOI
-- Une ligne dans `preinscriptions` atteste d'une préinscription : c'est sur
-- elle que comptent le bilan de l'établissement, la courbe du tableau de bord
-- et la fiche candidat de la console d'administration (nb_preinscriptions /
-- nb_dossiers). Or elle était écrite par Dossier::reserveRecepisse(), appelée
-- aussi pour le récépissé de dépôt d'un dossier d'inscription : chaque
-- inscription comptait donc comme une préinscription de plus, et disparaissait
-- du compte des inscriptions.
--
-- Le code ne produit plus ces lignes (la nature du dossier décide désormais).
-- Ce script répare l'historique. Il ne touche NI aux dossiers, NI aux
-- récépissés, NI aux attestations : uniquement la table de comptage.
--
-- À JOUER UNE FOIS, en production, après sauvegarde.
-- Vérifier d'abord ce qui sera supprimé (SELECT), puis exécuter le DELETE.
-- ═══════════════════════════════════════════════════════════════════════════

-- 1) Contrôle : combien de lignes, et lesquelles ?
SELECT p.id, p.dossier_id, d.reference, d.statut, d.date_creation
  FROM preinscriptions p
  JOIN dossiers_inscription d ON d.id = p.dossier_id
 WHERE (EXISTS (SELECT 1 FROM inscriptions i WHERE i.dossier_id = d.id)
        OR d.reference IS NOT NULL
        OR d.statut = 'brouillon');

-- 2) Suppression (même critère, mot pour mot : Dossier::SQL_EST_INSCRIPTION)
DELETE p
  FROM preinscriptions p
  JOIN dossiers_inscription d ON d.id = p.dossier_id
 WHERE (EXISTS (SELECT 1 FROM inscriptions i WHERE i.dossier_id = d.id)
        OR d.reference IS NOT NULL
        OR d.statut = 'brouillon');
