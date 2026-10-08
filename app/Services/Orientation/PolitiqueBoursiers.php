<?php
declare(strict_types=1);

namespace Services\Orientation;

use Core\Database;
use Core\PolitiqueBoursiersSchema;
use PDO;

/**
 * « Cet établissement accepte-t-il les candidatures de boursiers ? »
 *
 * Un seul endroit répond à cette question, pour que le front-office, la
 * validation serveur et les grilles tarifaires ne puissent pas diverger.
 *
 * TROIS PRINCIPES
 * ---------------
 *
 *  1. LE DÉFAUT EST « OUI ». Colonne absente (migration non rejouée), fiche
 *     chargée depuis un cache antérieur à la migration, tableau incomplet :
 *     dans tous les cas la réponse est « accepte ». Fermer une porte par
 *     accident, parce qu'une donnée manque, serait le pire des deux échecs —
 *     des candidats verraient une plateforme qui leur refuse silencieusement
 *     ce à quoi ils ont droit.
 *
 *  2. LE MASQUAGE N'EST PAS LA SÉCURITÉ. Retirer le bloc du formulaire est un
 *     confort d'écran ; ce qui compte est `normaliserPost()`, appliqué avant
 *     toute validation, qui ramène à `non_boursier` une déclaration envoyée
 *     par un POST forgé. Les deux sont posés, jamais l'un sans l'autre.
 *
 *  3. LE PASSÉ NE BOUGE PAS. Fermer les boursiers ne réécrit AUCUN dossier
 *     déjà déposé : leur `statut_bourse`, leur historique et leur commission
 *     restent tels quels (cf. l'en-tête de la migration). Le réglage ne
 *     s'applique qu'aux déclarations à venir.
 */
final class PolitiqueBoursiers
{
    /** Colonne portant le réglage sur la fiche établissement. */
    public const CHAMP = 'accepte_boursiers';

    /** Message unique affiché quand une déclaration de bourse est refusée. */
    public const MESSAGE_REFUS =
        'Cet établissement n’accepte pas les candidatures de boursiers : '
        . 'votre dossier reste enregistré comme non boursier.';

    /** Réponses déjà résolues dans ce process : id établissement → bool. */
    private static array $cache = [];

    /**
     * Réponse lue sur une fiche déjà chargée (findBySlug, findById, bundle
     * Redis de la page publique…). Aucune requête.
     *
     * @param array<string,mixed>|null $etab
     */
    public static function accepte(?array $etab): bool
    {
        if (!is_array($etab) || !array_key_exists(self::CHAMP, $etab)) {
            return true; // Principe 1 : en l'absence d'information, on accepte.
        }

        return (int)$etab[self::CHAMP] === 1;
    }

    /**
     * Réponse pour un établissement dont on n'a que l'identifiant (espace
     * candidat : le dossier ne transporte pas la fiche complète).
     */
    public static function accepteId(int $etablissementId, ?PDO $db = null): bool
    {
        if ($etablissementId <= 0) {
            return true;
        }
        if (isset(self::$cache[$etablissementId])) {
            return self::$cache[$etablissementId];
        }

        $valeur = true;

        if (PolitiqueBoursiersSchema::disponible($db)) {
            try {
                $db ??= Database::getInstance()->getConnection();
                $st   = $db->prepare(
                    'SELECT ' . self::CHAMP . ' FROM etablissements WHERE id = ? LIMIT 1'
                );
                $st->execute([$etablissementId]);
                $ligne = $st->fetchColumn();

                // Établissement introuvable → false du fetch : on garde le
                // défaut plutôt que de le lire comme un refus.
                if ($ligne !== false) {
                    $valeur = (int)$ligne === 1;
                }
            } catch (\Throwable $e) {
                error_log('[PolitiqueBoursiers] Lecture du réglage échouée #' . $etablissementId . ' : ' . $e->getMessage());
            }
        }

        return self::$cache[$etablissementId] = $valeur;
    }

    /**
     * Options du bloc « situation de bourse » réellement proposables.
     *
     * Établissement fermé aux boursiers : seule reste « Je ne suis pas
     * boursier ». La liste n'est jamais vide — une question sans réponse
     * possible bloquerait le formulaire.
     *
     * @param array<string,mixed>|null $etab
     * @return list<array{valeur:string,libelle:string,aide:string,piece:?string}>
     */
    public static function options(?array $etab): array
    {
        $toutes = SituationBourse::options();

        if (self::accepte($etab)) {
            return $toutes;
        }

        return array_values(array_filter(
            $toutes,
            static fn(array $o): bool => $o['valeur'] === SituationBourse::NON_BOURSIER
        ));
    }

    /**
     * Mêmes options, à partir du seul identifiant d'établissement (espace
     * candidat : le dossier ne transporte pas la fiche complète).
     *
     * @return list<array{valeur:string,libelle:string,aide:string,piece:?string}>
     */
    public static function optionsId(int $etablissementId): array
    {
        return self::options([self::CHAMP => self::accepteId($etablissementId) ? 1 : 0]);
    }

    /** Ce statut est-il déclarable dans cet établissement ? */
    public static function statutAutorise(?array $etab, ?string $statut): bool
    {
        if (self::accepte($etab)) {
            return true;
        }

        return (string)$statut === SituationBourse::NON_BOURSIER;
    }

    /** Même question, à partir du seul identifiant d'établissement. */
    public static function statutAutoriseId(int $etablissementId, ?string $statut): bool
    {
        if (self::accepteId($etablissementId)) {
            return true;
        }

        return (string)$statut === SituationBourse::NON_BOURSIER;
    }

    /**
     * Neutralise toute déclaration de bourse d'un POST destiné à un
     * établissement fermé aux boursiers, AVANT validation.
     *
     * On ne renvoie pas d'erreur : le candidat n'a rien fait de mal, il n'a
     * même pas vu la question. Le champ est simplement ramené à
     * `non_boursier`, et les champs conditionnels (numéro d'attestation, date
     * de dépôt ANBG) sont vidés pour qu'aucune donnée orpheline ne soit
     * enregistrée.
     *
     * @param array<string,mixed>|null $etab
     * @param array<string,mixed>      $post
     * @return array<string,mixed>
     */
    public static function normaliserPost(?array $etab, array $post): array
    {
        if (self::accepte($etab)) {
            return $post;
        }

        $post['statut_bourse']           = SituationBourse::NON_BOURSIER;
        $post['numero_attestation_anbg'] = '';
        $post['date_depot_anbg']         = '';

        return $post;
    }

    /** Réinitialise le cache — réservé aux tests et aux scripts longs. */
    public static function oublier(): void
    {
        self::$cache = [];
    }
}
