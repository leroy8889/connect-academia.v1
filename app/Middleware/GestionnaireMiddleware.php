<?php
declare(strict_types=1);

namespace Middleware;

use Core\{Session, Response, Database};

/**
 * Protège l'espace /gestionnaire.
 * Session gestionnaire DÉDIÉE (gestionnaire_id) — indépendante de la session
 * utilisateur classique et de la session super-admin.
 */
class GestionnaireMiddleware
{
    public function handle(): void
    {
        if (!Session::isGestionnaire()) {
            if ($this->isAjax()) {
                Response::json(['success' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Non authentifié']], 401);
            }
            Response::redirect('/gestionnaire/login?next=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
        }

        $userId = Session::gestionnaireId();

        // Re-vérifier à chaque requête : lien toujours valide + compte non suspendu
        $db = Database::getInstance()->getConnection();
        $st = $db->prepare('
            SELECT ea.etablissement_id, ea.role
            FROM etablissement_admins ea
            JOIN etablissements e ON e.id = ea.etablissement_id
            JOIN users u ON u.id = ea.user_id AND u.is_active = 1 AND u.is_deleted = 0
            WHERE ea.user_id = ? AND ea.role = \'gestionnaire\'
            LIMIT 1
        ');
        $st->execute([$userId]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            Session::remove('gestionnaire_id');
            Session::remove('gestionnaire_name');
            Session::remove('gestionnaire_email');
            Session::remove('gestionnaire_etab_id');
            Session::remove('gestionnaire_etab_nom');
            Session::remove('gestionnaire_role');
            if ($this->isAjax()) {
                Response::json(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Accès réservé aux gestionnaires']], 403);
            }
            Session::flash('error', 'Ton accès gestionnaire a été suspendu ou retiré.');
            Response::redirect('/gestionnaire/login');
            return;
        }

        Session::set('gestionnaire_etab_id', (int)$row['etablissement_id']);
        Session::set('gestionnaire_role', $row['role']);
    }

    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
