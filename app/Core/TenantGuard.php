<?php
declare(strict_types=1);

namespace Core;

/**
 * Garde applicatif de cloisonnement multi-tenant — l'équivalent d'un
 * « Row-Level Security » pour cette application MySQL (qui n'a pas de RLS
 * natif, contrairement à PostgreSQL).
 *
 * L'isolation réelle est déjà assurée au niveau des requêtes (les Models
 * filtrent systématiquement par `etablissement_id` pour le gestionnaire et par
 * `eleve_id`/`email` pour l'élève). Cette classe ajoute une couche de
 * DÉFENSE-EN-PROFONDEUR : elle centralise la détection d'une tentative d'accès
 * cross-tenant et la journalise dans le moniteur de sécurité (SecurityMonitor
 * → table security_alerts → alerte super-admin).
 *
 * Elle ne modifie JAMAIS le flux : le contrôleur conserve son 404/redirect
 * existant. La garde ne fait qu'observer et signaler.
 */
final class TenantGuard
{
    /**
     * Vérifie qu'une ligne appartient bien à l'établissement attendu.
     *
     * @param array|null $row      Ligne chargée (ou null si introuvable).
     * @param int        $etabId   Établissement de la session gestionnaire.
     * @param string     $resource Étiquette de la ressource (ex. "gestionnaire.dossier").
     * @param int|string|null $ref Référence facultative (id de la ressource).
     * @return bool  true si la ligne existe ET appartient à l'établissement.
     *
     * Ne journalise QUE le cas « ligne existante mais mauvais établissement »
     * (sonde IDOR réelle) — jamais un simple 404 (ressource inexistante), qui
     * n'est pas un signal d'attaque.
     */
    public static function ownsEtab(?array $row, int $etabId, string $resource, int|string|null $ref = null): bool
    {
        if (!is_array($row) || !isset($row['etablissement_id'])) {
            return false; // introuvable : le contrôleur renvoie 404, rien à signaler
        }

        if ((int) $row['etablissement_id'] === $etabId) {
            return true; // accès légitime
        }

        // Ligne réelle appartenant à un AUTRE établissement → tentative cross-tenant.
        SecurityMonitor::accessViolation('Accès cross-établissement refusé', [
            'ressource'       => $resource,
            'ref'             => $ref,
            'etab_session'    => $etabId,
            'etab_ressource'  => (int) $row['etablissement_id'],
            'gestionnaire_id' => Session::gestionnaireId(),
        ]);
        return false;
    }

    /**
     * Variante « effet de bord seul » : à appeler à l'intérieur d'un bloc de
     * refus existant (`if (!$row || $row['etablissement_id'] !== $etabId)`).
     * Journalise uniquement si $row est une ligne réelle d'un autre
     * établissement ; sinon ne fait rien. Ne retourne rien, ne change pas le flux.
     */
    public static function denyEtab(?array $row, int $etabId, string $resource, int|string|null $ref = null): void
    {
        self::ownsEtab($row, $etabId, $resource, $ref);
    }

    /**
     * Journalise une tentative d'accès d'un élève/visiteur à un dossier qui ne
     * lui appartient pas (ownership). À appeler quand un identifiant décodé
     * pointe vers une ressource réelle mais que la requête scellée par
     * propriétaire (eleve_id/email) n'a rien renvoyé.
     */
    public static function denyOwnership(string $resource, int|string|null $ref = null): void
    {
        SecurityMonitor::accessViolation('Accès à un dossier non détenu (ownership)', [
            'ressource' => $resource,
            'ref'       => $ref,
            'user_id'   => Session::userId(),
        ]);
    }
}
