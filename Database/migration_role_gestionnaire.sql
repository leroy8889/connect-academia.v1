-- ════════════════════════════════════════════════════════════════════
-- Migration : renommage du rôle « enseignant » → « gestionnaire »
-- ────────────────────────────────────────────────────────────────────
-- Les comptes admins d'établissement (module Orientation) étaient stockés
-- dans users.role = 'enseignant', ce qui créait une confusion dans le
-- dashboard super-admin : ce ne sont pas des enseignants mais bien les
-- gestionnaires des établissements. On corrige la valeur ET l'ENUM.
--
-- À REJOUER EN PRODUCTION (idempotent : sûr à exécuter plusieurs fois).
-- ════════════════════════════════════════════════════════════════════

-- ① On élargit l'ENUM pour accueillir la nouvelle valeur sans perdre l'ancienne
ALTER TABLE `users`
  MODIFY `role` ENUM('eleve','enseignant','gestionnaire','admin','visiteur')
  COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'eleve';

-- ② On migre les lignes existantes
UPDATE `users` SET `role` = 'gestionnaire' WHERE `role` = 'enseignant';

-- ③ On retire définitivement l'ancienne valeur de l'ENUM
ALTER TABLE `users`
  MODIFY `role` ENUM('eleve','gestionnaire','admin','visiteur')
  COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'eleve';
