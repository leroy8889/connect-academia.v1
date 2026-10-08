<?php
declare(strict_types=1);

namespace Core;

use PDO;
use Throwable;

/**
 * Garde-fou de schéma pour `Database/migration_accepte_boursiers.sql`.
 *
 * POURQUOI
 * --------
 * Le déploiement de cette plateforme sépare le code (rsync) de la base
 * (migration jouée à la main) : les deux ne sont jamais synchrones. Nommer
 * `etablissements.accepte_boursiers` dans un INSERT avant que la colonne
 * n'existe ferait échouer la création d'un établissement en entier, et le
 * super administrateur n'aurait aucun moyen de comprendre pourquoi.
 *
 * Colonne absente → le réglage n'est simplement pas écrit, et la lecture
 * retombe sur le défaut « l'établissement accepte les boursiers », qui est
 * exactement le comportement d'avant la migration.
 *
 * En LECTURE SEULE, comme Core\TarifFormationSchema : aucun ALTER déclenché par
 * une requête HTTP. La migration reste la voie officielle, et le log dit
 * laquelle rejouer.
 *
 * Résultat mis en cache pour la durée du process : une requête
 * information_schema par cycle PHP au maximum, pas une par établissement.
 */
final class PolitiqueBoursiersSchema
{
    private static ?bool $colonne = null;

    private const RAPPEL = ' Rejouer Database/migration_accepte_boursiers.sql.';

    /**
     * La colonne `etablissements.accepte_boursiers` existe-t-elle ?
     *
     * Sinon : tous les établissements acceptent les boursiers, comme avant.
     * En cas de doute (base injoignable) on répond false — ne pas utiliser la
     * colonne vaut mieux que faire échouer un enregistrement de fiche.
     */
    public static function disponible(?PDO $db = null): bool
    {
        if (self::$colonne !== null) {
            return self::$colonne;
        }

        try {
            $db ??= Database::getInstance()->getConnection();
            $st   = $db->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME   = ?
                    AND COLUMN_NAME  = ?'
            );
            $st->execute(['etablissements', 'accepte_boursiers']);
            self::$colonne = (int)$st->fetchColumn() > 0;
        } catch (Throwable $e) {
            error_log('[PolitiqueBoursiersSchema] Vérification impossible : ' . $e->getMessage());

            return false;
        }

        if (self::$colonne === false) {
            error_log(
                '[PolitiqueBoursiersSchema] Colonne etablissements.accepte_boursiers absente — '
                . 'tous les établissements acceptent les boursiers.' . self::RAPPEL
            );
        }

        return self::$colonne;
    }

    /** Réinitialise le cache — réservé aux tests. */
    public static function oublier(): void
    {
        self::$colonne = null;
    }
}
