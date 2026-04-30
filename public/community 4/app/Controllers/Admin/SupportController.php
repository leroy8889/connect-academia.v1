<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\{Response, Database};
use PDO;

class SupportController
{
    /** @var PDO */
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * GET /admin/support — Page de support / informations système
     */
    public function index(): void
    {
        // Informations système
        $systemInfo = [
            'php_version'   => PHP_VERSION,
            'server'        => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'database'      => $this->getDbVersion(),
            'platform'      => $this->getSetting('site_name') ?: 'StudyLink',
            'app_env'       => $_ENV['APP_ENV'] ?? 'local',
        ];

        // Statistiques base de données
        $dbStats = [
            'users'         => $this->count("SELECT COUNT(*) as total FROM users WHERE is_deleted = 0"),
            'posts'         => $this->count("SELECT COUNT(*) as total FROM posts WHERE is_deleted = 0"),
            'comments'      => $this->count("SELECT COUNT(*) as total FROM comments WHERE is_deleted = 0"),
            'reports'       => $this->count("SELECT COUNT(*) as total FROM reports"),
            'pending_reports' => $this->count("SELECT COUNT(*) as total FROM reports WHERE status = 'pending'"),
        ];

        // Dernières connexions admin
        $recentLogins = [];
        try {
            $stmt = $this->db->prepare(
                "SELECT al.ip_address, al.status, al.created_at, u.prenom, u.nom, u.email
                 FROM admin_logins al
                 LEFT JOIN users u ON al.user_id = u.id
                 ORDER BY al.created_at DESC
                 LIMIT 10"
            );
            $stmt->execute();
            $recentLogins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            // Table may not exist
        }

        Response::view('admin/support/index', [
            'pageTitle'    => 'Support — Admin StudyLink',
            'headerTitle'  => 'Support & System Info',
            'systemInfo'   => $systemInfo,
            'dbStats'      => $dbStats,
            'recentLogins' => $recentLogins,
        ], 'admin');
    }

    private function getDbVersion(): string
    {
        try {
            $stmt = $this->db->query("SELECT VERSION() as v");
            return $stmt->fetch(PDO::FETCH_ASSOC)['v'] ?? 'Unknown';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    private function getSetting(string $key): ?string
    {
        $stmt = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['setting_value'] : null;
    }

    private function count(string $sql): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
}

