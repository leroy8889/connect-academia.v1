-- ============================================================================
-- Migration : téléphone utilisateur (Gabon) + commissions scolarité 10 %
--
-- 1. users.telephone — numéro gabonais au format national 9 chiffres
--    (0XXXXXXXX : 01 fixe, 06/07 mobile). Validé/normalisé côté PHP
--    par Core\Phone::normalizeGabon().
--
-- 2. commissions_scolarite — contrat plateforme ↔ établissement :
--    Connect'Academia perçoit 10 % de la scolarité de chaque élève apporté
--    (rien sur les frais d'inscription). Une commission est créée par le
--    super admin sur un dossier au statut « inscrit » (validé par
--    l'établissement). Le recouvrement se fait en présentiel, en 3 tranches :
--      T1 = 40 % de la commission, T2 = 30 %, T3 = 30 %.
--    Chaque tranche encaissée est cochée dans l'admin (gestion des
--    utilisateurs) ; l'établissement voit l'état des tranches sur son
-- dashboard gestionnaire.
--
-- ⚠️ À REJOUER EN PRODUCTION (comme les autres migrations du dossier).
-- Les CREATE TABLE sont idempotents (IF NOT EXISTS) ; l'ALTER TABLE échoue
-- proprement si la colonne existe déjà (« Duplicate column ») — ignorer.
-- ============================================================================

ALTER TABLE `users`
  ADD COLUMN `telephone` VARCHAR(20) DEFAULT NULL AFTER `email`,
  ADD KEY `idx_users_telephone` (`telephone`);

-- ── Commission (1 ligne = 1 dossier inscrit = 1 élève dans 1 établissement) ──
CREATE TABLE IF NOT EXISTS `commissions_scolarite` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dossier_id` INT NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `etablissement_id` INT NOT NULL,
  `montant_scolarite` DECIMAL(12,2) NOT NULL,
  `taux_commission` DECIMAL(5,2) NOT NULL DEFAULT 10.00,
  `montant_commission` DECIMAL(12,2) NOT NULL,
  `statut` ENUM('en_cours','solde') NOT NULL DEFAULT 'en_cours',
  `created_by` INT DEFAULT NULL COMMENT 'admins.id ayant créé la commission',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_commission_dossier` (`dossier_id`),
  KEY `idx_commission_user` (`user_id`),
  KEY `idx_commission_etab` (`etablissement_id`),
  CONSTRAINT `fk_commission_dossier` FOREIGN KEY (`dossier_id`)
    REFERENCES `dossiers_inscription` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_commission_etab` FOREIGN KEY (`etablissement_id`)
    REFERENCES `etablissements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Tranches de recouvrement (3 par commission : 40 / 30 / 30) ───────────────
CREATE TABLE IF NOT EXISTS `commission_tranches` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `commission_id` INT UNSIGNED NOT NULL,
  `numero` TINYINT UNSIGNED NOT NULL COMMENT '1, 2 ou 3',
  `pourcentage` DECIMAL(5,2) NOT NULL COMMENT '40.00 / 30.00 / 30.00',
  `montant` DECIMAL(12,2) NOT NULL,
  `est_payee` TINYINT(1) NOT NULL DEFAULT 0,
  `date_paiement` DATETIME DEFAULT NULL,
  `valide_par` INT DEFAULT NULL COMMENT 'admins.id ayant coché le paiement',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tranche` (`commission_id`, `numero`),
  CONSTRAINT `fk_tranche_commission` FOREIGN KEY (`commission_id`)
    REFERENCES `commissions_scolarite` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
