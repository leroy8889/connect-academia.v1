<?php
declare(strict_types=1);

namespace Core;

use PDO;
use Throwable;

/**
 * Garde-fou de schéma pour `password_resets`.
 *
 * POURQUOI
 * --------
 * Le flux « mot de passe oublié » stocke le token HACHÉ dans `token_hash`.
 * Sur une base restée au schéma d'origine (`token`, `is_used`, `expires_at`
 * NOT NULL), l'INSERT échoue ; l'exception est avalée par le catch
 * anti-énumération et aucun email ne part — l'utilisateur voit « rien ne se
 * passe ». Symptôme observé en production en juillet 2026.
 *
 * Cette classe aligne la table sur le code au premier appel, puis ne fait plus
 * rien (drapeau statique = une seule vérification par requête PHP).
 *
 * SÉCURITÉ
 * --------
 * · Les instructions DDL sont des littéraux, aucune donnée utilisateur n'y
 *   entre : pas de surface d'injection.
 * · La table ne contient que des hachages de tokens éphémères (1 h) : la
 *   migration ne peut pas détruire de donnée métier.
 * · Si l'utilisateur MySQL n'a pas le droit ALTER, l'échec est journalisé et
 *   l'appelant retombe sur son comportement d'erreur normal — on ne casse rien.
 *
 * Le fichier `Database/migration_password_resets_token_hash.sql` reste la voie
 * officielle : cette classe n'est qu'un filet pour les bases non migrées.
 */
final class PasswordResetSchema
{
    private static bool $checked = false;

    /**
     * Garantit la présence de `token_hash` (et l'absence des colonnes héritées
     * NOT NULL qui feraient échouer l'INSERT). Idempotent, silencieux.
     */
    public static function ensure(PDO $db): void
    {
        if (self::$checked) {
            return;
        }
        self::$checked = true;

        try {
            if (self::hasColumn($db, 'token_hash') && !self::hasColumn($db, 'token')) {
                return; // schéma déjà à jour
            }

            // Les demandes en cours sont invalidées : un token au format
            // précédent ne doit pas survivre au changement.
            $db->exec("DELETE FROM `password_resets`");

            if (!self::hasColumn($db, 'token_hash')) {
                $db->exec(
                    "ALTER TABLE `password_resets`
                       ADD COLUMN `token_hash` VARCHAR(64) NOT NULL DEFAULT ''
                       AFTER `email`"
                );
                $db->exec("ALTER TABLE `password_resets` ADD INDEX `idx_pwreset_token_hash` (`token_hash`)");
            }

            foreach (['token', 'is_used', 'expires_at'] as $legacy) {
                if (self::hasColumn($db, $legacy)) {
                    $db->exec("ALTER TABLE `password_resets` DROP COLUMN `{$legacy}`");
                }
            }

            error_log('[PasswordResetSchema] Table password_resets migrée vers token_hash.');
        } catch (Throwable $e) {
            // Droit ALTER absent ou table indisponible : on journalise clairement
            // pour que l'exploitant rejoue la migration SQL à la main.
            error_log(
                '[PasswordResetSchema] Migration automatique impossible — rejouer '
                . 'Database/migration_password_resets_token_hash.sql. Détail : ' . $e->getMessage()
            );
        }
    }

    private static function hasColumn(PDO $db, string $column): bool
    {
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'password_resets'
               AND COLUMN_NAME  = ?"
        );
        $stmt->execute([$column]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
