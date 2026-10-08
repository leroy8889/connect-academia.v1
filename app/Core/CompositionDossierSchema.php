<?php
declare(strict_types=1);

namespace Core;

use PDO;
use Throwable;

/**
 * Garde-fou de schéma pour `Database/migration_composition_dossier.sql`.
 *
 * POURQUOI
 * --------
 * Le déploiement de cette plateforme sépare le code (rsync) de la base
 * (migration jouée à la main) : les deux ne sont jamais synchrones. Nommer
 * `etablissements.composition_dossier` dans l'INSERT avant que la colonne
 * n'existe ferait échouer la CRÉATION D'UN ÉTABLISSEMENT en entier — pour une
 * information purement éditoriale, ce qui serait absurde.
 *
 * Absente, la composition n'est ni écrite ni lue : les documents retombent sur
 * leur texte générique et le gabarit public n'affiche pas la section. C'est
 * exactement le comportement d'avant la migration.
 *
 * En LECTURE SEULE, comme Core\TarifFormationSchema : aucun ALTER déclenché
 * par une requête HTTP. La migration reste la voie officielle, et le log dit
 * laquelle rejouer.
 *
 * Résultat mémorisé pour la durée du process : une requête information_schema
 * par cycle PHP au maximum.
 */
final class CompositionDossierSchema
{
    private static ?bool $disponible = null;

    /**
     * La colonne `etablissements.composition_dossier` existe-t-elle ?
     *
     * En cas de doute (base injoignable) : false. Ne pas utiliser une colonne
     * vaut mieux que faire échouer l'enregistrement d'une fiche.
     */
    public static function disponible(?PDO $db = null): bool
    {
        if (self::$disponible !== null) {
            return self::$disponible;
        }

        try {
            $db ??= Database::getInstance()->getConnection();
            $st   = $db->prepare(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME   = 'etablissements'
                    AND COLUMN_NAME  = 'composition_dossier'"
            );
            $st->execute();
            self::$disponible = (int)$st->fetchColumn() > 0;
        } catch (Throwable $e) {
            error_log('[CompositionDossierSchema] Vérification impossible : ' . $e->getMessage());
            self::$disponible = false;
        }

        if (self::$disponible === false) {
            error_log(
                '[CompositionDossierSchema] Colonne etablissements.composition_dossier absente — '
                . 'composition du dossier d’inscription désactivée. '
                . 'Rejouer Database/migration_composition_dossier.sql.'
            );
        }

        return self::$disponible;
    }

    /** Réinitialise le cache — réservé aux tests. */
    public static function oublier(): void
    {
        self::$disponible = null;
    }
}
