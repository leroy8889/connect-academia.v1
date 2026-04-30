<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\{Response, Session, Database, Totp};
use Models\Admin;
use PDO;

class ParametresController
{
    private PDO   $db;
    private Admin $model;

    public function __construct()
    {
        $this->db    = Database::getInstance()->getConnection();
        $this->model = new Admin();
    }

    public function index(): void
    {
        $adminId   = Session::adminId();
        $admin     = $this->model->findById($adminId);
        $settings  = $this->getAllSettings();
        $activeTab = $_GET['tab'] ?? 'general';

        // Génère (ou récupère depuis session) le secret TOTP temporaire
        $totpSecret = null;
        $totpQrUrl  = null;
        if (!($admin['totp_enabled'] ?? false)) {
            if (!Session::get('admin_totp_setup')) {
                Session::set('admin_totp_setup', Totp::generateSecret());
            }
            $totpSecret = Session::get('admin_totp_setup');
            $totpQrUrl  = Totp::getQrCodeImageUrl($admin['email'] ?? 'admin', $totpSecret);
        }

        Response::view('admin/parametres/index', [
            'pageTitle'         => 'Paramètres — Admin',
            'breadcrumbSection' => 'Système',
            'breadcrumbPage'    => 'Paramètres',
            'settings'          => $settings,
            'admin'             => $admin,
            'activeTab'         => $activeTab,
            'totpSecret'        => $totpSecret,
            'totpQrUrl'         => $totpQrUrl,
        ], 'admin');
    }

    public function save(): void
    {
        $allowed = [
            'site_name', 'description_publique', 'email_contact', 'max_upload_mb',
            'enable_suivi', 'enable_messagerie', 'enable_signalements', 'enable_notif_email',
            'enable_chat', 'enable_paiement', 'gemini_rate_limit_per_minute',
            'periode_gratuite_jours', 'prix_mensuel_xaf', 'prix_annuel_xaf',
            'email_inscription', 'email_signalement', 'email_ressource',
        ];

        $boolKeys = [
            'enable_suivi', 'enable_messagerie', 'enable_signalements', 'enable_notif_email',
            'enable_chat', 'enable_paiement',
            'email_inscription', 'email_signalement', 'email_ressource',
        ];

        foreach ($allowed as $key) {
            if (in_array($key, $boolKeys, true)) {
                $val = isset($_POST[$key]) ? '1' : '0';
            } else {
                if (!isset($_POST[$key])) continue;
                $val = trim((string) $_POST[$key]);
            }
            $this->upsertSetting($key, $val);
        }

        Session::flash('success', 'Paramètres enregistrés avec succès.');
        Response::redirect('/admin/parametres?tab=' . ($_POST['_tab'] ?? 'general'));
    }

    public function enable2fa(): void
    {
        $adminId   = Session::adminId();
        $code      = preg_replace('/\D/', '', (string) ($_POST['totp_code_confirm'] ?? ''));
        $secret    = Session::get('admin_totp_setup');

        if (!$secret || strlen($code) !== 6) {
            Session::flash('error', 'Code invalide ou session expirée.');
            Response::redirect('/admin/parametres?tab=securite');
        }

        if (!Totp::verify($secret, $code)) {
            Session::flash('error', 'Code OTP incorrect. Réessayez.');
            Response::redirect('/admin/parametres?tab=securite');
        }

        $this->db->prepare(
            "UPDATE admins SET totp_secret = ?, totp_enabled = 1, updated_at = NOW() WHERE id = ?"
        )->execute([$secret, $adminId]);

        Session::remove('admin_totp_setup');
        Session::flash('success', '2FA activée avec succès ! Elle sera demandée à votre prochaine connexion.');
        Response::redirect('/admin/parametres?tab=securite');
    }

    public function disable2fa(): void
    {
        $adminId = Session::adminId();

        $this->db->prepare(
            "UPDATE admins SET totp_secret = NULL, totp_enabled = 0, updated_at = NOW() WHERE id = ?"
        )->execute([$adminId]);

        Session::flash('success', '2FA désactivée.');
        Response::redirect('/admin/parametres?tab=securite');
    }

    public function changePassword(): void
    {
        $adminId  = Session::adminId();
        $current  = $_POST['current_password'] ?? '';
        $new      = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if (!$current || !$new || !$confirm) {
            Session::flash('error', 'Tous les champs sont requis.');
            Response::redirect('/admin/parametres?tab=securite');
        }

        if ($new !== $confirm) {
            Session::flash('error', 'Les mots de passe ne correspondent pas.');
            Response::redirect('/admin/parametres?tab=securite');
        }

        if (strlen($new) < 10) {
            Session::flash('error', 'Le mot de passe doit contenir au moins 10 caractères.');
            Response::redirect('/admin/parametres?tab=securite');
        }

        $admin = $this->model->findById($adminId);
        if (!$admin || !$this->model->verifyPassword($current, $admin['password_hash'])) {
            Session::flash('error', 'Mot de passe actuel incorrect.');
            Response::redirect('/admin/parametres?tab=securite');
        }

        $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
        $this->db->prepare(
            "UPDATE admins SET password_hash = ?, updated_at = NOW() WHERE id = ?"
        )->execute([$hash, $adminId]);

        Session::flash('success', 'Mot de passe changé avec succès.');
        Response::redirect('/admin/parametres?tab=securite');
    }

    // ── POST /admin/api/admins ────────────────────────────────────────────
    public function storeAdmin(): void
    {
        $prenom = trim($_POST['prenom'] ?? '');
        $nom    = trim($_POST['nom'] ?? '');
        $email  = trim($_POST['email'] ?? '');
        $role   = $_POST['role'] ?? 'admin';
        $mdp    = $_POST['password'] ?? '';

        if (!$prenom || !$nom || !$email || !$mdp) {
            Response::json(['success' => false, 'message' => 'Tous les champs sont obligatoires.'], 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::json(['success' => false, 'message' => 'Email invalide.'], 422);
        }

        if (!in_array($role, ['super_admin', 'admin', 'moderateur'], true)) {
            Response::json(['success' => false, 'message' => 'Rôle invalide.'], 422);
        }

        if (strlen($mdp) < 10) {
            Response::json(['success' => false, 'message' => 'Mot de passe min. 10 caractères.'], 422);
        }

        $exists = $this->db->prepare("SELECT id FROM admins WHERE email = ?");
        $exists->execute([$email]);
        if ($exists->fetch()) {
            Response::json(['success' => false, 'message' => 'Cet email est déjà utilisé.'], 409);
        }

        $hash = password_hash($mdp, PASSWORD_BCRYPT, ['cost' => 12]);
        $this->db->prepare(
            "INSERT INTO admins (nom, prenom, email, password_hash, role) VALUES (?, ?, ?, ?, ?)"
        )->execute([$nom, $prenom, $email, $hash, $role]);

        $id = (int) $this->db->lastInsertId();

        Response::json([
            'success' => true,
            'message' => "Admin {$prenom} {$nom} créé avec succès.",
            'id'      => $id,
        ]);
    }

    // ── DELETE /admin/api/admins/{id} ─────────────────────────────────────
    public function deleteAdmin(array $params): void
    {
        $id      = (int) ($params['id'] ?? 0);
        $meId    = (int) Session::adminId();

        if ($id === $meId) {
            Response::json(['success' => false, 'message' => 'Vous ne pouvez pas supprimer votre propre compte.'], 403);
        }

        $stmt = $this->db->prepare("SELECT id, prenom, nom FROM admins WHERE id = ? AND is_active = 1");
        $stmt->execute([$id]);
        $admin = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$admin) {
            Response::json(['success' => false, 'message' => 'Administrateur introuvable.'], 404);
        }

        $this->db->prepare(
            "UPDATE admins SET is_active = 0, updated_at = NOW() WHERE id = ?"
        )->execute([$id]);

        Response::json(['success' => true, 'message' => "Admin {$admin['prenom']} {$admin['nom']} désactivé."]);
    }

    public function clearCache(): void
    {
        // Logique future : vider le cache Redis/OPcache
        $cleared = 0;
        if (function_exists('opcache_reset')) {
            opcache_reset();
            $cleared++;
        }
        Response::json(['success' => true, 'message' => "Cache vidé ({$cleared} couche(s))."]);
    }

    // ── Private helpers ────────────────────────────────────────────────────

    private function getAllSettings(): array
    {
        $rows = $this->db->query(
            "SELECT setting_key, setting_value FROM settings"
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        return $rows ?: [];
    }

    private function upsertSetting(string $key, string $value): void
    {
        $this->db->prepare(
            "INSERT INTO settings (setting_key, setting_value)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()"
        )->execute([$key, $value]);
    }
}
