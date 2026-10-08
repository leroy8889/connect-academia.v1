<?php
declare(strict_types=1);

namespace Core;

use PDO;
use Throwable;

/**
 * Garde-fou de schéma pour la colonne `dossiers_inscription.montant_frais_inscription`.
 *
 * POURQUOI
 * --------
 * Cette colonne sépare les frais d'inscription du versement sur la scolarité
 * (cf. `Database/migration_frais_inscription_dossier.sql`). Elle est écrite au
 * moment le plus sensible du système : la validation à 100 %, qui pose aussi la
 * commission. Sur une base où la migration n'a pas encore été rejouée, un
 * UPDATE mentionnant une colonne absente fait échouer toute la transition —
 * autrement dit, plus aucune inscription ne peut être validée au guichet.
 *
 * Ce filet répond à la seule question qui compte avant d'écrire : « la colonne
 * existe-t-elle ? ». Si elle manque, le code se rabat sur le comportement
 * antérieur (frais non enregistrés) et journalise l'anomalie, plutôt que de
 * bloquer un agent avec un candidat devant lui.
 *
 * Volontairement en LECTURE SEULE, contrairement à Core\PasswordResetSchema :
 * `dossiers_inscription` est la table centrale du métier et porte des données
 * comptables. Un ALTER automatique déclenché par une requête HTTP n'y a pas sa
 * place — la migration reste la voie officielle.
 *
 * Le résultat est mis en cache pour la durée du process : une requête
 * information_schema par cycle PHP, pas une par dossier.
 */
final class DossierFinanceSchema
{
    private static ?bool $fraisInscription = null;

    /**
     * La colonne `montant_frais_inscription` est-elle disponible ?
     * En cas de doute (base injoignable), retourne false : ne pas écrire vaut
     * mieux que faire échouer une validation à 100 %.
     */
    public static function fraisInscriptionDisponible(?PDO $db = null): bool
    {
        if (self::$fraisInscription !== null) {
            return self::$fraisInscription;
        }

        try {
            $db ??= Database::getInstance()->getConnection();

            $st = $db->prepare(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME   = 'dossiers_inscription'
                    AND COLUMN_NAME  = 'montant_frais_inscription'"
            );
            $st->execute();
            self::$fraisInscription = (int)$st->fetchColumn() > 0;

            if (self::$fraisInscription === false) {
                error_log(
                    '[DossierFinanceSchema] Colonne montant_frais_inscription absente — '
                    . 'les frais d’inscription ne seront pas enregistrés. '
                    . 'Rejouer Database/migration_frais_inscription_dossier.sql.'
                );
            }
        } catch (Throwable $e) {
            error_log('[DossierFinanceSchema] Vérification impossible : ' . $e->getMessage());
            self::$fraisInscription = false;
        }

        return self::$fraisInscription;
    }

    /** Réinitialise le cache — réservé aux tests. */
    public static function oublier(): void
    {
        self::$fraisInscription = null;
    }
}
