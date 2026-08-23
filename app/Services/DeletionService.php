<?php
declare(strict_types=1);

namespace Services;

use Core\{Database, Cache};
use PDO;

/**
 * Suppression définitive d'un utilisateur : purge transactionnelle de tout
 * ce qui le concerne, puis invalidation des caches Redis.
 *
 * Les FKs ON DELETE CASCADE (migration_cascade_suppression.sql) couvrent déjà
 * la propagation côté base ; les DELETE explicites ci-dessous rendent la purge
 * fiable même si la migration n'a pas encore été appliquée sur l'environnement.
 *
 * La session de l'utilisateur meurt à sa prochaine requête : AuthMiddleware
 * recharge le compte en base à chaque hit et détruit la session si absent.
 */
class DeletionService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Purge complète d'un utilisateur. Retourne false si le compte n'existe pas.
     *
     * @param int      $userId  Compte à supprimer
     * @param int|null $adminId Admin à l'origine de l'action (journal d'audit)
     */
    public function purgeUser(int $userId, ?int $adminId = null): bool
    {
        $st = $this->db->prepare('SELECT id, email, prenom, nom FROM users WHERE id = ?');
        $st->execute([$userId]);
        $user = $st->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            return false;
        }

        $this->db->beginTransaction();
        try {
            // Contenu personnel sans FK CASCADE garantie sur tous les environnements
            foreach ([
                'DELETE FROM avis_etablissements       WHERE user_id = ?',
                'DELETE FROM favoris                   WHERE user_id = ?',
                'DELETE FROM progressions              WHERE user_id = ?',
                'DELETE FROM sessions_revision         WHERE user_id = ?',
                'DELETE FROM etablissement_admins      WHERE user_id = ?',
                'DELETE FROM notifications_orientation WHERE user_id = ?',
                'DELETE FROM journal_acces_documents   WHERE user_id = ?',
            ] as $sql) {
                $this->db->prepare($sql)->execute([$userId]);
            }

            // Dossiers d'inscription : archive légale de l'établissement —
            // on détache le compte, le dossier reste consultable côté école.
            $this->db->prepare('UPDATE dossiers_inscription SET eleve_id = NULL WHERE eleve_id = ?')
                ->execute([$userId]);
            $this->db->prepare('UPDATE dossiers_inscription SET cree_par_user_id = NULL WHERE cree_par_user_id = ?')
                ->execute([$userId]);

            // Jetons de réinitialisation liés à l'email
            $this->db->prepare('DELETE FROM password_resets WHERE email = ?')
                ->execute([$user['email']]);

            // Le reste (posts, comments, likes, bookmarks, follows, notifications,
            // messages_chat, abonnements, transactions, ia_conversations, reports)
            // part via les FKs ON DELETE CASCADE du schéma principal.
            $this->db->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);

            $this->audit('user.purge', 'users', $userId, $adminId, [
                'email' => $user['email'],
                'nom'   => trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')),
            ]);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log('[DeletionService] Purge user #' . $userId . ' échouée : ' . $e->getMessage());
            return false;
        }

        $this->bustCachesForUser($userId);
        return true;
    }

    /** Invalide les caches Redis qui peuvent encore référencer l'utilisateur. */
    private function bustCachesForUser(int $userId): void
    {
        Cache::forget("abonnement:{$userId}");
        Cache::forget('admin:user:statuts');
        Cache::flush('admin:dashboard:*');
        Cache::flush('top_questions_*');
        Cache::flush('trending_hashtags_*');
    }

    private function audit(string $action, string $entity, int $entityId, ?int $adminId, array $context): void
    {
        try {
            $this->db->prepare('
                INSERT INTO audit_log (action, entity, entity_id, user_id, context, ip)
                VALUES (?, ?, ?, ?, ?, ?)
            ')->execute([
                $action,
                $entity,
                $entityId,
                $adminId,
                json_encode($context, JSON_UNESCAPED_UNICODE),
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (\Throwable $e) {
            error_log('[DeletionService] Audit log échoué : ' . $e->getMessage());
        }
    }
}
