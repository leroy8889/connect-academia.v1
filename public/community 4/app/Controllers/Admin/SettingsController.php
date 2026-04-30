<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\{Response, Session, Database};
use PDO;

class SettingsController
{
    /** @var PDO */
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * GET /admin/settings — Page des paramètres
     */
    public function index(): void
    {
        $settings = $this->getAllSettings();

        Response::view('admin/settings/index', [
            'pageTitle'   => 'Paramètres — Admin StudyLink',
            'headerTitle' => 'Platform Settings',
            'settings'    => $settings,
        ], 'admin');
    }

    /**
     * POST /admin/api/settings — Mettre à jour les paramètres
     */
    public function update(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

        $allowedKeys = [
            'site_name', 'site_description', 'classes_list', 'matieres_list',
            'enable_messaging', 'enable_follow', 'max_upload_mb',
        ];

        $updated = 0;
        foreach ($allowedKeys as $key) {
            if (array_key_exists($key, $input)) {
                $value = trim((string) $input[$key]);
                $stmt = $this->db->prepare(
                    "INSERT INTO settings (setting_key, setting_value, updated_at)
                     VALUES (?, ?, NOW())
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()"
                );
                $stmt->execute([$key, $value]);
                $updated++;
            }
        }

        if ($this->isAjax()) {
            Response::json(['success' => true, 'message' => "{$updated} paramètre(s) mis à jour"]);
        } else {
            Session::flash('success', 'Paramètres enregistrés avec succès');
            Response::redirect('/admin/settings');
        }
    }

    private function getAllSettings(): array
    {
        $stmt = $this->db->prepare("SELECT setting_key, setting_value FROM settings");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        return $settings;
    }

    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}

