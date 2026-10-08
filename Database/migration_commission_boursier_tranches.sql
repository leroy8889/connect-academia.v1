-- ============================================================================
-- Connect'Academia — Base de commission des BOURSIERS, par établissement
--                    + recouvrement ramené à DEUX tranches (60 % / 40 %)
-- ----------------------------------------------------------------------------
-- IDEMPOTENTE : rejouable sans erreur (procédure de garde `ca_add_col4`).
--
-- ⚠️ À REJOUER EN PRODUCTION :
--   mysql -u <user> -p <base> < Database/migration_commission_boursier_tranches.sql
--
--
-- CE QUI CHANGE, ET POURQUOI
-- ==========================
--
-- 1. LA BASE DE COMMISSION D'UN BOURSIER DEVIENT NÉGOCIABLE
--    ------------------------------------------------------
--    Depuis migration_tarifs_formation.sql, un boursier confirmé était
--    commissionné sur ses FRAIS D'INSCRIPTION, sans exception : sa scolarité
--    étant prise en charge par l'ANBG, la commissionner revenait à facturer
--    l'établissement sur un flux qu'il ne perçoit pas de l'étudiant.
--
--    La règle reste le DÉFAUT, et elle est la bonne dans le cas général. Mais
--    certains accords signés avec des partenaires prévoient l'inverse : la
--    commission y porte sur la scolarité, y compris pour un boursier — parce
--    que l'établissement la perçoit bel et bien, de l'ANBG cette fois. Coder
--    une règle unique obligeait à saisir ces dossiers à la main, donc à faire
--    diverger la facturation de ce que la plateforme affiche.
--
--    `etablissements.base_commission_boursier` porte cet arbitrage, PAR
--    ÉTABLISSEMENT, et il est réservé au super administrateur (fiche
--    établissement → « Commission plateforme »).
--
--    ⚠️ IL NE CONCERNE QUE LES BOURSIERS CONFIRMÉS. Un non-boursier et un
--    dossier ANBG en cours restent commissionnés sur la SCOLARITÉ, quel que
--    soit le réglage : ce sont deux situations où l'étudiant reste redevable
--    de la totalité, et aucun accord ne porte dessus.
--
--    La colonne naît à 'frais_inscription' : le comportement actuel est donc
--    conservé à l'identique tant que le super administrateur ne touche à rien.
--
-- 2. AUCUNE COMMISSION DÉJÀ ÉMISE N'EST RECALCULÉE
--    ----------------------------------------------
--    `commissions_scolarite.base_commission` fige la nature retenue AU MOMENT
--    de la validation à 100 %, et `taux_commission` fige le taux. Modifier le
--    réglage d'un établissement n'affecte donc que les inscriptions suivantes.
--    C'est la même garantie que pour le taux (spec §10.2, §18) : le passé d'une
--    comptabilité ne bouge pas parce qu'un écran de configuration a changé.
--
-- 3. RECOUVREMENT — DEUX TRANCHES AU LIEU DE TROIS
--    ----------------------------------------------
--    Le recouvrement présentiel passe de 40/30/30 à 60/40. Trois passages en
--    établissement pour une commission de quelques dizaines de milliers de
--    francs coûtaient plus en déplacements qu'ils ne sécurisaient d'encaissement.
--
--    La répartition vit dans le code
--    (Services\Orientation\CommissionDossier::REPARTITION_TRANCHES) : cette
--    migration ne fait que rattraper les échéanciers DÉJÀ EN BASE, et
--    seulement ceux sur lesquels rien ne s'est encore passé.
--
--    ⚠️ CE QUI N'EST PAS TOUCHÉ, ET POURQUOI :
--      · toute commission dont UNE tranche est encaissée garde ses trois
--        tranches. La refondre en deux déplacerait le montant déjà perçu d'une
--        ligne à l'autre : un encaissement de 40 % deviendrait un acompte de
--        66 % sur une tranche de 60 %, et le reçu remis à l'établissement ne
--        correspondrait plus à rien ;
--      · toute commission close (réglée, annulée, abandon d'année) est un fait
--        comptable, pas une vue ;
--      · toute tranche fermée par un abandon d'année, pour la même raison.
--
--    Les écrans de suivi (super administrateur et établissement) affichent
--    désormais autant de tranches que la commission en porte : un échéancier
--    hérité à trois lignes reste donc lisible, à côté d'un neuf à deux lignes.
-- ============================================================================

SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS ca_add_col4;
DELIMITER $$
CREATE PROCEDURE ca_add_col4(IN p_table VARCHAR(64), IN p_col VARCHAR(64), IN p_ddl TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_col
  ) THEN
    SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN ', p_ddl);
    PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
  END IF;
END$$
DELIMITER ;


-- ============================================================================
-- 1. ÉTABLISSEMENTS — où prélever la commission d'un boursier
-- ============================================================================
CALL ca_add_col4('etablissements', 'base_commission_boursier',
  "`base_commission_boursier` ENUM('frais_inscription','scolarite')
     NOT NULL DEFAULT 'frais_inscription'
     COMMENT 'Boursiers confirmes uniquement : assiette de la commission plateforme. Les autres situations restent sur la scolarite.'");


-- ============================================================================
-- 2. TRANCHES — commentaires de colonnes remis à jour
-- ----------------------------------------------------------------------------
-- Purement documentaire, mais un commentaire de colonne qui ment est ce qui
-- fait écrire le mauvais code six mois plus tard. Les deux MODIFY sont
-- rejouables sans effet de bord : ils ne changent ni le type ni les données.
-- ============================================================================
ALTER TABLE `commission_tranches`
  MODIFY `numero` TINYINT UNSIGNED NOT NULL
    COMMENT '1 ou 2 (echeanciers herites : 1 a 3)',
  MODIFY `pourcentage` DECIMAL(5,2) NOT NULL
    COMMENT '60.00 / 40.00 (echeanciers herites : 40/30/30)';


-- ============================================================================
-- 3. RATTRAPAGE — échéanciers VIERGES ramenés à 60 / 40
-- ----------------------------------------------------------------------------
-- « Vierge » = aucune tranche encaissée, aucune tranche close par un abandon,
-- et commission encore ouverte. Ces échéanciers n'ont produit aucun document et
-- n'ont fait l'objet d'aucune visite : les refondre ne contredit rien.
-- ============================================================================
DROP TEMPORARY TABLE IF EXISTS ca_tmp_tranches_a_refondre;
CREATE TEMPORARY TABLE ca_tmp_tranches_a_refondre (
  commission_id INT UNSIGNED NOT NULL PRIMARY KEY,
  montant DECIMAL(12,2) NOT NULL
) ENGINE=MEMORY;

INSERT INTO ca_tmp_tranches_a_refondre (commission_id, montant)
SELECT c.id, c.montant_commission
FROM commissions_scolarite c
WHERE c.statut IN ('a_facturer', 'facturee')
  AND c.montant_commission > 0
  -- Au moins une tranche existe (sinon il n'y a rien à refondre)…
  AND EXISTS (
    SELECT 1 FROM commission_tranches t WHERE t.commission_id = c.id
  )
  -- … et aucune ne porte d'histoire.
  AND NOT EXISTS (
    SELECT 1 FROM commission_tranches t
    WHERE t.commission_id = c.id
      AND (t.est_payee = 1 OR t.est_abandonnee = 1)
  )
  -- Déjà au format 60/40 : ne rien réécrire pour rien (idempotence).
  AND (
    SELECT COUNT(*) FROM commission_tranches t WHERE t.commission_id = c.id
  ) <> 2;

DELETE t FROM commission_tranches t
JOIN ca_tmp_tranches_a_refondre r ON r.commission_id = t.commission_id;

INSERT INTO commission_tranches (commission_id, numero, pourcentage, montant)
SELECT commission_id, 1, 60.00, ROUND(montant * 0.60, 2)
FROM ca_tmp_tranches_a_refondre;

-- La DERNIÈRE tranche prend le RESTE, pas 40 % recalculés : deux arrondis
-- successifs peuvent faire diverger la somme de quelques francs, et un
-- établissement solderait sans jamais atteindre le total.
INSERT INTO commission_tranches (commission_id, numero, pourcentage, montant)
SELECT commission_id, 2, 40.00, ROUND(montant - ROUND(montant * 0.60, 2), 2)
FROM ca_tmp_tranches_a_refondre;

DROP TEMPORARY TABLE ca_tmp_tranches_a_refondre;


DROP PROCEDURE IF EXISTS ca_add_col4;

-- ── Vérification manuelle après exécution ───────────────────────────────────
--   SELECT base_commission_boursier, COUNT(*) FROM etablissements GROUP BY 1;
--   SELECT n, COUNT(*) FROM (
--     SELECT commission_id, COUNT(*) AS n FROM commission_tranches GROUP BY commission_id
--   ) x GROUP BY n;
--   -- Attendu : le gros du parc à n = 2 ; les n = 3 restants sont des
--   -- échéanciers entamés ou clos, et c'est voulu.
