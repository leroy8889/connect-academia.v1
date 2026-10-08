<?php
declare(strict_types=1);

namespace Services\Orientation;

use Core\AnneeUniversitaire;
use PDO;

/**
 * Référence définitive du dossier — CA-2026-ESM-000147 (spec §5.5).
 *
 * Le compteur est porté par la table `sequences_dossier`, cloisonnée par
 * établissement ET par année. Il est incrémenté sous verrou de ligne
 * (SELECT … FOR UPDATE) à l'intérieur de la transaction de soumission :
 * deux candidats qui soumettent à la même milliseconde obtiennent deux
 * numéros distincts, et le numéro est libéré si la transaction échoue.
 *
 * MAX(id) + 1 est explicitement proscrit : sous concurrence, deux lectures
 * simultanées renvoient la même valeur.
 */
final class ReferenceDossier
{
    private const PREFIXE          = 'CA';
    private const LONGUEUR_SEQUENCE = 6;

    /** Nombre maximum de numéros sautés pour trouver une référence libre. */
    private const MAX_SAUTS = 1000;

    /**
     * Réserve le prochain numéro et retourne la référence complète.
     *
     * DOIT être appelée à l'intérieur d'une transaction ouverte par
     * l'appelant : sans transaction, le verrou FOR UPDATE est relâché
     * immédiatement et la garantie d'unicité disparaît.
     *
     * @throws \RuntimeException si aucune transaction n'est active.
     */
    public static function reserver(PDO $db, int $etablissementId, ?int $annee = null): string
    {
        if (!$db->inTransaction()) {
            throw new \RuntimeException(
                'ReferenceDossier::reserver() exige une transaction active (verrou FOR UPDATE).'
            );
        }

        $annee   = $annee ?? AnneeUniversitaire::debut();
        $prefixe = self::prefixe($etablissementId, $annee);

        // 1. Garantir l'existence de la ligne de compteur sans écraser la
        //    valeur courante. INSERT IGNORE évite une erreur si une transaction
        //    concurrente vient de la créer.
        //
        //    La ligne n'est PAS initialisée à zéro : les dossiers antérieurs à
        //    la table de séquences portent déjà des références. Repartir de
        //    zéro réémettrait CA-2026-XXX-000001 alors que ce numéro est pris,
        //    et l'index unique `uq_dossier_reference` ferait échouer TOUTE la
        //    soumission. On amorce donc le compteur sur le plus grand numéro
        //    déjà émis pour cet établissement et cette année.
        $db->prepare(
            'INSERT IGNORE INTO sequences_dossier (etablissement_id, annee, dernier_numero)
             SELECT ?, ?, COALESCE(MAX(CAST(SUBSTRING_INDEX(reference, \'-\', -1) AS UNSIGNED)), 0)
               FROM dossiers_inscription
              WHERE etablissement_id = ? AND reference LIKE ?'
        )->execute([$etablissementId, $annee, $etablissementId, $prefixe . '%']);

        // 2. Verrouiller la ligne pour la durée de la transaction.
        $st = $db->prepare(
            'SELECT dernier_numero FROM sequences_dossier
             WHERE etablissement_id = ? AND annee = ?
             FOR UPDATE'
        );
        $st->execute([$etablissementId, $annee]);
        $courant = $st->fetchColumn();

        if ($courant === false) {
            // La ligne a disparu entre l'INSERT IGNORE et le SELECT (suppression
            // de l'établissement en parallèle) : on repart de zéro.
            $courant = 0;
            $db->prepare(
                'INSERT INTO sequences_dossier (etablissement_id, annee, dernier_numero)
                 VALUES (?, ?, 0)'
            )->execute([$etablissementId, $annee]);
        }

        $suivant = (int)$courant + 1;

        // Filet : le compteur peut malgré tout être en retard sur la réalité
        // (reprise de données, référence posée à la main, table de séquences
        // remise à zéro). On avance jusqu'au premier numéro réellement libre
        // plutôt que de heurter l'index unique — une collision annulerait la
        // transaction et perdrait le dépôt du candidat.
        $existe = $db->prepare('SELECT 1 FROM dossiers_inscription WHERE reference = ? LIMIT 1');
        for ($essai = 0; $essai < self::MAX_SAUTS; $essai++) {
            $existe->execute([self::formater($etablissementId, $annee, $suivant)]);
            if ($existe->fetchColumn() === false) {
                break;
            }
            $suivant++;
        }

        $db->prepare(
            'UPDATE sequences_dossier SET dernier_numero = ?
             WHERE etablissement_id = ? AND annee = ?'
        )->execute([$suivant, $etablissementId, $annee]);

        return self::formater($etablissementId, $annee, $suivant);
    }

    /**
     * Compose la référence sans toucher au compteur (affichage, tests).
     */
    public static function formater(int $etablissementId, int $annee, int $sequence): string
    {
        return self::prefixe($etablissementId, $annee)
            . str_pad((string)$sequence, self::LONGUEUR_SEQUENCE, '0', STR_PAD_LEFT);
    }

    /**
     * Préfixe commun à toutes les références d'un établissement pour une
     * année — « CA-2026-HAV- ». Sert à borner la recherche du dernier numéro
     * émis (l'index unique sur `reference` couvre le LIKE préfixé).
     */
    public static function prefixe(int $etablissementId, int $annee): string
    {
        return sprintf(
            '%s-%04d-%s-',
            self::PREFIXE,
            $annee,
            CodeEtablissement::pour($etablissementId)
        );
    }

    /**
     * Une chaîne saisie par un agent est-elle une référence bien formée ?
     * Sert à distinguer une recherche par référence d'une recherche par nom.
     */
    public static function estValide(string $reference): bool
    {
        return preg_match(
            '/^' . self::PREFIXE . '-\d{4}-[A-Z0-9]{3,5}-\d{' . self::LONGUEUR_SEQUENCE . '}$/',
            strtoupper(trim($reference))
        ) === 1;
    }

    /** Normalise une saisie utilisateur avant recherche en base. */
    public static function normaliser(string $saisie): string
    {
        return strtoupper(trim($saisie));
    }
}
