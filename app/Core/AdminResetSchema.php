<?php
declare(strict_types=1);

namespace Core;

use PDO;
use Throwable;

/**
 * Garde-fou de schéma pour la réinitialisation du mot de passe administrateur.
 *
 * POURQUOI
 * --------
 * `Models\Admin` stocke le token de reset HACHÉ (SHA-256) et son expiration
 * dans `admins.reset_token` / `admins.reset_expires_at`. Sur une base créée
 * avant l'ajout du flux, ces colonnes n'existent pas : chaque requête lève
 * « Unknown column 'reset_token' » et la page /Administratorconnect/reset-password
 * renvoie un 503 (constaté en local le 4 août 2026).
 *
 * Cette classe aligne la table au premier appel puis ne fait plus rien
 * (drapeau statique = une seule vérification par requête PHP).
 *
 * SÉCURITÉ
 * --------
 * · DDL en littéraux, aucune donnée utilisateur : pas de surface d'injection.
 * · Ajout de colonnes uniquement — aucune donnée métier n'est touchée.
 * · Sans droit ALTER, l'échec est journalisé et l'appelant retombe sur son
 *   comportement d'erreur normal.
 *
 * `Database/migration_admin_reset_token.sql` reste la voie officielle ; cette
 * classe n'est qu'un filet pour les bases non migrées.
 */
final class AdminResetSchema
{
    private static bool $checked = false;

    /** Garantit la présence de `reset_token` et `reset_expires_at`. Idempotent. */
    public static function ensure(PDO $db): void
    {
        if (self::$checked) {
            return;
        }
        self::$checked = true;

        try {
            if (self::hasColumn($db, 'reset_token') && self::hasColumn($db, 'reset_expires_at')) {
                return; // schéma déjà à jour
            }

            if (!self::hasColumn($db, 'reset_token')) {
                $db->exec(
                    "ALTER TABLE `admins`
                       ADD COLUMN `reset_token` VARCHAR(64) NULL DEFAULT NULL
                       AFTER `password_hash`"
                );
                $db->exec("ALTER TABLE `admins` ADD INDEX `idx_admins_reset_token` (`reset_token`)");
            }

            if (!self::hasColumn($db, 'reset_expires_at')) {
                $db->exec(
                    "ALTER TABLE `admins`
                       ADD COLUMN `reset_expires_at` DATETIME NULL DEFAULT NULL
                       AFTER `reset_token`"
                );
            }

            error_log('[AdminResetSchema] Colonnes de reset ajoutées sur la table admins.');
        } catch (Throwable $e) {
            error_log(
                '[AdminResetSchema] Migration automatique impossible — rejouer '
                . 'Database/migration_admin_reset_token.sql. Détail : ' . $e->getMessage()
            );
        }
    }

    private static function hasColumn(PDO $db, string $column): bool
    {
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'admins'
               AND COLUMN_NAME  = ?"
        );
        $stmt->execute([$column]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
