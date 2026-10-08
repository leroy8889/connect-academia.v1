<?php
declare(strict_types=1);

namespace Core;

use PDO;
use Throwable;

/**
 * Garde-fou de schéma pour `Database/migration_tarifs_formation.sql`.
 *
 * POURQUOI
 * --------
 * Cette migration touche trois endroits du chemin critique : la grille
 * tarifaire (`tarifs_formation`), les quatre frais d'inscription de la fiche
 * établissement, et la nature de la base de commission. Sur une base où elle
 * n'a pas encore été rejouée, une seule requête mentionnant une colonne absente
 * ferait échouer :
 *
 *   · l'enregistrement d'un établissement par le super administrateur ;
 *   · la validation à 100 % au guichet — donc toute inscription, avec un
 *     candidat qui attend devant l'agent ;
 *   · la génération de la commission qui l'accompagne.
 *
 * Le déploiement de cette plateforme sépare le code (rsync) de la base
 * (migration jouée à la main) : les deux ne sont jamais synchrones. Ce filet
 * répond avant d'écrire à la seule question qui compte — « la colonne
 * existe-t-elle ? » — et permet au code de retomber sur le comportement
 * antérieur, qui reste correct : tarif par cycle sur la fiche établissement,
 * commission sur la scolarité.
 *
 * En LECTURE SEULE, comme Core\DossierFinanceSchema : aucun ALTER déclenché par
 * une requête HTTP sur des tables comptables. La migration reste la voie
 * officielle, et le log dit laquelle rejouer.
 *
 * Résultats mis en cache pour la durée du process : trois requêtes
 * information_schema par cycle PHP au maximum, pas trois par dossier.
 */
final class TarifFormationSchema
{
    private static ?bool $grille          = null;
    private static ?bool $fraisInscription = null;
    private static ?bool $baseCommission   = null;
    private static ?bool $baseBoursier     = null;

    private const RAPPEL = ' Rejouer Database/migration_tarifs_formation.sql.';

    private const RAPPEL_BOURSIER = ' Rejouer Database/migration_commission_boursier_tranches.sql.';

    /**
     * La table `tarifs_formation` est-elle disponible ?
     * Sinon : la grille par formation/niveau est ignorée, la résolution
     * retombe sur les tarifs par cycle de la fiche établissement.
     */
    public static function grilleDisponible(?PDO $db = null): bool
    {
        if (self::$grille !== null) {
            return self::$grille;
        }

        self::$grille = self::table('tarifs_formation', $db);

        if (self::$grille === false) {
            error_log(
                '[TarifFormationSchema] Table tarifs_formation absente — '
                . 'tarification par cycle uniquement.' . self::RAPPEL
            );
        }

        return self::$grille;
    }

    /**
     * Les quatre colonnes `frais_inscription_*` existent-elles ?
     * Sinon : repli sur `prix_inscription` + `frais_dossier`, exactement le
     * comportement d'avant la migration.
     */
    public static function fraisInscriptionDisponible(?PDO $db = null): bool
    {
        if (self::$fraisInscription !== null) {
            return self::$fraisInscription;
        }

        self::$fraisInscription = self::colonne('etablissements', 'frais_inscription_licence', $db);

        if (self::$fraisInscription === false) {
            error_log(
                '[TarifFormationSchema] Colonnes frais_inscription_* absentes — '
                . 'repli sur prix_inscription + frais_dossier.' . self::RAPPEL
            );
        }

        return self::$fraisInscription;
    }

    /**
     * Les colonnes de traçabilité de la base de commission existent-elles
     * (`base_commission`, `statut_bourse_applique`, `revision_requise`…) ?
     *
     * Sinon : la commission est calculée sur la scolarité pour TOUS les
     * statuts de bourse, comme avant. C'est un choix délibéré — écrire un
     * montant sans pouvoir dire d'où il vient produirait des commissions
     * inexplicables, ce qui est pire qu'une règle ancienne mais lisible.
     */
    public static function baseCommissionDisponible(?PDO $db = null): bool
    {
        if (self::$baseCommission !== null) {
            return self::$baseCommission;
        }

        self::$baseCommission = self::colonne('commissions_scolarite', 'base_commission', $db);

        if (self::$baseCommission === false) {
            error_log(
                '[TarifFormationSchema] Colonne commissions_scolarite.base_commission absente — '
                . 'commission calculée sur la scolarité quel que soit le statut de bourse.' . self::RAPPEL
            );
        }

        return self::$baseCommission;
    }

    /**
     * La colonne `etablissements.base_commission_boursier` existe-t-elle ?
     *
     * Elle porte l'arbitrage négocié avec un partenaire : pour un BOURSIER
     * CONFIRMÉ, la commission porte-t-elle sur ses frais d'inscription (défaut
     * historique) ou sur sa scolarité (accord particulier) ?
     *
     * Sinon : tous les boursiers sont commissionnés sur les frais
     * d'inscription, exactement comme avant cette migration. Un établissement
     * sous accord « scolarité » sera donc sous-facturé tant que la migration
     * n'est pas jouée — c'est le sens du défaut retenu : en cas de doute, la
     * plateforme facture moins, jamais plus.
     */
    public static function baseBoursierDisponible(?PDO $db = null): bool
    {
        if (self::$baseBoursier !== null) {
            return self::$baseBoursier;
        }

        self::$baseBoursier = self::colonne('etablissements', 'base_commission_boursier', $db);

        if (self::$baseBoursier === false) {
            error_log(
                '[TarifFormationSchema] Colonne etablissements.base_commission_boursier absente — '
                . 'boursiers commissionnés sur les frais d’inscription pour tous les établissements.'
                . self::RAPPEL_BOURSIER
            );
        }

        return self::$baseBoursier;
    }

    /** Réinitialise le cache — réservé aux tests. */
    public static function oublier(): void
    {
        self::$grille           = null;
        self::$fraisInscription = null;
        self::$baseCommission   = null;
        self::$baseBoursier     = null;
    }

    // ── Interne ──────────────────────────────────────────────────────────────

    private static function table(string $table, ?PDO $db): bool
    {
        return self::compter(
            "SELECT COUNT(*) FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
            [$table],
            $db
        );
    }

    private static function colonne(string $table, string $colonne, ?PDO $db): bool
    {
        return self::compter(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            [$table, $colonne],
            $db
        );
    }

    /**
     * En cas de doute (base injoignable), retourne false : ne pas utiliser une
     * colonne vaut mieux que faire échouer une validation à 100 %.
     */
    private static function compter(string $sql, array $params, ?PDO $db): bool
    {
        try {
            $db ??= Database::getInstance()->getConnection();
            $st   = $db->prepare($sql);
            $st->execute($params);

            return (int)$st->fetchColumn() > 0;
        } catch (Throwable $e) {
            error_log('[TarifFormationSchema] Vérification impossible : ' . $e->getMessage());

            return false;
        }
    }
}
