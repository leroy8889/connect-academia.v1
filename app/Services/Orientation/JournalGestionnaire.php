<?php
declare(strict_types=1);

namespace Services\Orientation;

use Core\Database;
use Core\Session;
use PDO;

/**
 * Historique des actions des gestionnaires d'établissement.
 *
 * Journalise dans la table `audit_log` existante (migration_phase9.sql) —
 * aucune table supplémentaire. Chaque entrée porte l'etablissement_id et une
 * description lisible dans le contexte JSON : l'etablissement_id est la clé
 * d'isolation multi-tenant utilisée par la page /gestionnaire/historique.
 *
 * Convention de nommage des actions : gestionnaire.<categorie>.<verbe>
 * (ex. gestionnaire.candidature.statut, gestionnaire.formation.suppression).
 */
class JournalGestionnaire
{
    /** Catégories proposées en filtre sur la page historique. */
    public const CATEGORIES = [
        'session'     => 'Connexion',
        'candidature' => 'Candidatures',
        'formation'   => 'Formations',
        'actualite'   => 'Actualités',
        'filiere'     => 'Filières & spécialités',
        'piece'       => 'Pièces requises',
        'formulaire'  => 'Formulaires',
        'parametres'  => 'Paramètres',
        'profil'      => 'Profil',
        'bilan'       => 'Bilans statistiques',
    ];

    /** Libellés lisibles par action (fallback : nom brut de l'action). */
    public const LIBELLES = [
        'gestionnaire.session.connexion'          => 'Connexion',
        'gestionnaire.session.deconnexion'        => 'Déconnexion',
        'gestionnaire.login'                      => 'Connexion',
        'gestionnaire.candidature.creation'       => 'Création de dossier',
        'gestionnaire.candidature.modification'   => 'Modification de dossier',
        'gestionnaire.candidature.statut'         => 'Changement de statut',
        'gestionnaire.candidature.piece.consultation' => 'Pièce justificative consultée',
        'gestionnaire.candidature.sms'            => 'SMS envoyé',
        'gestionnaire.candidature.message'        => 'Message envoyé',
        'gestionnaire.formation.creation'         => 'Formation ajoutée',
        'gestionnaire.formation.modification'     => 'Formation modifiée',
        'gestionnaire.formation.suppression'      => 'Formation supprimée',
        'gestionnaire.actualite.creation'         => 'Actualité créée',
        'gestionnaire.actualite.modification'     => 'Actualité modifiée',
        'gestionnaire.actualite.suppression'      => 'Actualité supprimée',
        'gestionnaire.filiere.creation'           => 'Filière ajoutée',
        'gestionnaire.filiere.modification'       => 'Filière modifiée',
        'gestionnaire.filiere.suppression'        => 'Filière supprimée',
        'gestionnaire.filiere.specialite.creation'     => 'Spécialité ajoutée',
        'gestionnaire.filiere.specialite.modification' => 'Spécialité modifiée',
        'gestionnaire.filiere.specialite.suppression'  => 'Spécialité supprimée',
        'gestionnaire.piece.creation'             => 'Pièce ajoutée',
        'gestionnaire.piece.modification'         => 'Pièce modifiée',
        'gestionnaire.piece.suppression'          => 'Pièce supprimée',
        'gestionnaire.formulaire.creation'        => 'Champ de formulaire ajouté',
        'gestionnaire.formulaire.bascule'         => 'Champ activé/désactivé',
        'gestionnaire.formulaire.suppression'     => 'Champ de formulaire supprimé',
        'gestionnaire.parametres.modification'    => 'Paramètres modifiés',
        'gestionnaire.parametres.update'          => 'Paramètres modifiés',
        'gestionnaire.profil.modification'        => 'Profil modifié',
        'gestionnaire.profil.update'              => 'Profil modifié',
        'gestionnaire.profil.motdepasse'          => 'Mot de passe changé',
        'gestionnaire.password.update'            => 'Mot de passe changé',
        'gestionnaire.bilan.export'               => 'Bilan PDF exporté',
    ];

    /**
     * Journalise une action du gestionnaire connecté.
     * Ne lève jamais d'exception : l'échec du journal ne casse pas l'action métier.
     */
    public static function log(string $action, string $entity, int $entityId, string $description, array $context = []): void
    {
        $context['etablissement_id'] = (int) Session::get('gestionnaire_etab_id');
        $context['description']      = mb_substr($description, 0, 400);

        AuditService::log('gestionnaire.' . $action, $entity, $entityId, Session::gestionnaireId(), $context);
    }

    /** Historique paginé d'un établissement (plus récent d'abord). */
    public static function listByEtab(int $etabId, array $filters = [], int $limit = 30, int $offset = 0): array
    {
        [$where, $params] = self::buildWhere($etabId, $filters);
        $limit  = max(1, min(100, $limit));
        $offset = max(0, $offset);

        try {
            $st = Database::getInstance()->getConnection()->prepare("
                SELECT a.id, a.action, a.entity, a.entity_id, a.user_id, a.context, a.ip, a.created_at,
                       TRIM(CONCAT(COALESCE(u.prenom, ''), ' ', COALESCE(u.nom, ''))) AS gestionnaire_nom
                FROM audit_log a
                LEFT JOIN users u ON u.id = a.user_id
                {$where}
                ORDER BY a.created_at DESC, a.id DESC
                LIMIT {$limit} OFFSET {$offset}
            ");
            $st->execute($params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[JournalGestionnaire] Lecture échouée : ' . $e->getMessage());
            return [];
        }

        foreach ($rows as &$r) {
            $ctx              = json_decode((string) ($r['context'] ?? ''), true) ?: [];
            $r['description'] = (string) ($ctx['description'] ?? '');
            $r['libelle']     = self::LIBELLES[$r['action']] ?? $r['action'];
            $r['categorie']   = explode('.', $r['action'])[1] ?? '';
        }
        unset($r);

        return $rows;
    }

    /** Nombre total d'entrées pour la pagination. */
    public static function countByEtab(int $etabId, array $filters = []): int
    {
        [$where, $params] = self::buildWhere($etabId, $filters);

        try {
            $st = Database::getInstance()->getConnection()->prepare(
                "SELECT COUNT(*) FROM audit_log a {$where}"
            );
            $st->execute($params);
            return (int) $st->fetchColumn();
        } catch (\Throwable $e) {
            error_log('[JournalGestionnaire] Comptage échoué : ' . $e->getMessage());
            return 0;
        }
    }

    private static function buildWhere(int $etabId, array $filters): array
    {
        $where  = "WHERE a.action LIKE 'gestionnaire.%'
                   AND CAST(JSON_EXTRACT(a.context, '$.etablissement_id') AS UNSIGNED) = :etab";
        $params = [':etab' => $etabId];

        $categorie = (string) ($filters['categorie'] ?? '');
        if ($categorie !== '' && isset(self::CATEGORIES[$categorie])) {
            $where .= ' AND a.action LIKE :cat';
            $params[':cat'] = 'gestionnaire.' . $categorie . '.%';
        }

        return [$where, $params];
    }
}
