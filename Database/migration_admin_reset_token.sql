-- ---------------------------------------------------------------------------
-- Réinitialisation du mot de passe administrateur
-- ---------------------------------------------------------------------------
-- Le flux « mot de passe oublié » du back-office (Models\Admin::setResetToken /
-- findByResetToken / updatePassword) stocke le token HACHÉ (SHA-256) et sa date
-- d'expiration directement sur la ligne admin. Ces deux colonnes manquaient sur
-- les bases créées avant l'ajout du flux : chaque appel levait
-- « Unknown column 'reset_token' » et la page de reset renvoyait un 503.
--
-- Idempotent : à rejouer sans risque (les ALTER échouent si déjà appliqués,
-- exécuter alors uniquement les instructions manquantes).
-- ---------------------------------------------------------------------------

ALTER TABLE `admins`
    ADD COLUMN `reset_token`      VARCHAR(64) NULL DEFAULT NULL AFTER `password_hash`,
    ADD COLUMN `reset_expires_at` DATETIME    NULL DEFAULT NULL AFTER `reset_token`;

ALTER TABLE `admins`
    ADD INDEX `idx_admins_reset_token` (`reset_token`);
