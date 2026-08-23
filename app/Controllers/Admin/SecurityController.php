<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\{Response, Session, Database, Settings};
use Models\SecurityAlert;
use PDO;

/**
 * Tableau de bord sécurité (super-admin uniquement).
 * Consultation des alertes du moniteur d'intrusion, traitement et réglages.
 */
class SecurityController
{
    public function __construct()
    {
        // Double barrière : la route est réservée aux rôles super_admin et
        // admin via AdminPermissions ; on re-vérifie ici par défense en
        // profondeur (les modérateurs restent exclus).
        if (!in_array(Session::adminRole(), ['super_admin', 'admin'], true)) {
            if ($this->isAjax()) {
                Response::json(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Réservé au super-admin.']], 403);
            }
            Response::redirect('/admin');
        }
    }

    public function index(): void
    {
        $filters = [
            'severity' => $_GET['severity'] ?? '',
            'statut'   => $_GET['statut']   ?? '',
            'type'     => $_GET['type']     ?? '',
        ];

        $alerts = SecurityAlert::paginate($filters, 100, 0);
        $stats  = SecurityAlert::stats();

        Response::view('admin/securite/index', [
            'pageTitle'         => 'Sécurité — Admin',
            'breadcrumbSection' => 'Système',
            'breadcrumbPage'    => 'Sécurité',
            'alerts'            => $alerts,
            'stats'             => $stats,
            'filters'           => $filters,
            'blockingEnabled'   => (string) Settings::get('security_block_attacks', '0') === '1',
            'flash'             => Session::getFlash('success'),
            'flashError'        => Session::getFlash('error'),
        ], 'admin');
    }

    /** Compteur d'alertes non lues (badge de navigation, poll léger). */
    public function count(): void
    {
        Response::json(['success' => true, 'count' => SecurityAlert::countByStatut('nouveau')]);
    }

    /** Change le statut d'une alerte (nouveau / vu / resolu). */
    public function updateStatut(int $id): void
    {
        $statut = (string) ($_POST['statut'] ?? '');
        $ok     = SecurityAlert::updateStatut($id, $statut);
        if ($this->isAjax()) {
            Response::json(['success' => $ok]);
        }
        Session::flash($ok ? 'success' : 'error', $ok ? 'Alerte mise à jour.' : 'Statut invalide.');
        Response::redirect('/admin/securite');
    }

    public function markAllSeen(): void
    {
        $n = SecurityAlert::markAllSeen();
        Session::flash('success', "{$n} alerte(s) marquée(s) comme vue(s).");
        Response::redirect('/admin/securite');
    }

    public function clearResolved(): void
    {
        $n = SecurityAlert::clearResolved();
        Session::flash('success', "{$n} alerte(s) résolue(s) supprimée(s).");
        Response::redirect('/admin/securite');
    }

    /** Active/désactive le blocage automatique des attaques évidentes (403). */
    public function toggleBlocking(): void
    {
        $val = (($_POST['security_block_attacks'] ?? '0') === '1') ? '1' : '0';
        Database::getInstance()->getConnection()->prepare(
            'INSERT INTO settings (setting_key, setting_value)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()'
        )->execute(['security_block_attacks', $val]);
        Settings::forgetAll();
        Session::flash('success', $val === '1'
            ? 'Blocage automatique des attaques activé.'
            : 'Blocage automatique désactivé (mode alerte seule).');
        Response::redirect('/admin/securite');
    }

    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
