<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\{Response, Session, Database};
use Models\Admin;
use PDO;

class UsersController
{
    private Admin $model;
    private PDO   $db;

    public function __construct()
    {
        $this->model = new Admin();
        $this->db    = Database::getInstance()->getConnection();
    }

    public function index(): void
    {
        $filters = [
            'q'      => trim($_GET['q'] ?? ''),
            'role'   => $_GET['role'] ?? '',
            'status' => $_GET['status'] ?? '',
        ];

        if (($_GET['export'] ?? '') === 'csv') {
            $this->exportCsv($filters);
            return;
        }

        $page       = max(1, (int) ($_GET['page'] ?? 1));
        $perPage    = 20;
        $offset     = ($page - 1) * $perPage;
        $total      = $this->model->countUsers($filters);
        $totalPages = (int) ceil($total / $perPage) ?: 1;
        $users      = $this->model->getAllUsers($filters, $perPage, $offset);
        $counts     = $this->model->getUserCountsByRole();
        $series     = $this->db->query(
            "SELECT id, nom FROM series WHERE is_active = 1 ORDER BY nom"
        )->fetchAll(PDO::FETCH_ASSOC);

        Response::view('admin/users/index', [
            'pageTitle'         => 'Utilisateurs — Admin',
            'breadcrumbSection' => 'Apprentissage',
            'breadcrumbPage'    => 'Utilisateurs',
            'users'             => $users,
            'counts'            => $counts,
            'filters'           => $filters,
            'page'              => $page,
            'totalPages'        => $totalPages,
            'total'             => $total,
            'series'            => $series,
        ], 'admin');
    }

    // ── GET /admin/api/utilisateurs/{id} ─────────────────────────────────
    public function show(array $params): void
    {
        $id   = (int) ($params['id'] ?? 0);
        $stmt = $this->db->prepare(
            "SELECT u.id, u.nom, u.prenom, u.email, u.role, u.serie_id, u.is_active,
                    u.bio, u.etablissement, u.photo_profil, u.created_at, u.last_login,
                    s.nom AS serie_nom
             FROM users u
             LEFT JOIN series s ON s.id = u.serie_id
             WHERE u.id = ? AND u.is_deleted = 0"
        );
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            Response::json(['success' => false, 'message' => 'Utilisateur introuvable.'], 404);
        }

        Response::json(['success' => true, 'user' => $user]);
    }

    // ── POST /admin/api/utilisateurs ─────────────────────────────────────
    public function store(): void
    {
        $prenom  = trim($_POST['prenom'] ?? '');
        $nom     = trim($_POST['nom'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $role    = $_POST['role'] ?? 'eleve';
        $serieId = !empty($_POST['serie_id']) ? (int) $_POST['serie_id'] : null;
        $mdp     = $_POST['password'] ?? '';

        if (!$prenom || !$nom || !$email) {
            Response::json(['success' => false, 'message' => 'Prénom, nom et email sont obligatoires.'], 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::json(['success' => false, 'message' => 'Email invalide.'], 422);
        }

        if (!in_array($role, ['eleve', 'enseignant'], true)) {
            Response::json(['success' => false, 'message' => 'Rôle invalide.'], 422);
        }

        // Vérifier unicité email
        $exists = $this->db->prepare("SELECT id FROM users WHERE email = ? AND is_deleted = 0");
        $exists->execute([$email]);
        if ($exists->fetch()) {
            Response::json(['success' => false, 'message' => 'Cet email est déjà utilisé.'], 409);
        }

        // Mot de passe : fourni ou généré aléatoirement
        if (empty($mdp)) {
            $mdp = bin2hex(random_bytes(8)); // 16 chars hex
        }
        if (strlen($mdp) < 8) {
            Response::json(['success' => false, 'message' => 'Le mot de passe doit contenir au moins 8 caractères.'], 422);
        }

        $hash = password_hash($mdp, PASSWORD_BCRYPT, ['cost' => 12]);
        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        $this->db->prepare(
            "INSERT INTO users (uuid, nom, prenom, email, password_hash, serie_id, role, is_active, is_verified)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1)"
        )->execute([$uuid, $nom, $prenom, $email, $hash, $serieId, $role]);

        $id = (int) $this->db->lastInsertId();

        Response::json([
            'success' => true,
            'message' => "Utilisateur {$prenom} {$nom} créé avec succès.",
            'id'      => $id,
            'tmp_password' => $mdp,
        ]);
    }

    // ── PATCH /admin/api/utilisateurs/{id} ───────────────────────────────
    public function update(array $params): void
    {
        $id      = (int) ($params['id'] ?? 0);
        $prenom  = trim($_POST['prenom'] ?? '');
        $nom     = trim($_POST['nom'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $role    = $_POST['role'] ?? '';
        $serieId = !empty($_POST['serie_id']) ? (int) $_POST['serie_id'] : null;
        $bio     = trim($_POST['bio'] ?? '');
        $etab    = trim($_POST['etablissement'] ?? '');

        if (!$id || !$prenom || !$nom || !$email) {
            Response::json(['success' => false, 'message' => 'Champs requis manquants.'], 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::json(['success' => false, 'message' => 'Email invalide.'], 422);
        }

        if (!in_array($role, ['eleve', 'enseignant'], true)) {
            Response::json(['success' => false, 'message' => 'Rôle invalide.'], 422);
        }

        // Unicité email (hors cet user)
        $exists = $this->db->prepare("SELECT id FROM users WHERE email = ? AND id != ? AND is_deleted = 0");
        $exists->execute([$email, $id]);
        if ($exists->fetch()) {
            Response::json(['success' => false, 'message' => 'Cet email est déjà utilisé.'], 409);
        }

        $this->db->prepare(
            "UPDATE users SET nom=?, prenom=?, email=?, role=?, serie_id=?, bio=?, etablissement=?, updated_at=NOW()
             WHERE id=? AND is_deleted=0"
        )->execute([$nom, $prenom, $email, $role, $serieId, $bio ?: null, $etab ?: null, $id]);

        Response::json(['success' => true, 'message' => "Utilisateur mis à jour."]);
    }

    // ── PATCH /admin/api/utilisateurs/{id}/toggle ────────────────────────
    public function toggle(array $params): void
    {
        $id     = (int) ($params['id'] ?? 0);
        $active = $this->model->toggleUser($id);

        Response::json([
            'success' => true,
            'active'  => $active,
            'label'   => $active ? 'Actif' : 'Suspendu',
            'message' => $active ? 'Utilisateur activé.' : 'Utilisateur suspendu.',
        ]);
    }

    // ── DELETE /admin/api/utilisateurs/{id} ──────────────────────────────
    public function delete(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);

        $user = $this->db->prepare("SELECT id, prenom, nom FROM users WHERE id = ? AND is_deleted = 0");
        $user->execute([$id]);
        $row = $user->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            Response::json(['success' => false, 'message' => 'Utilisateur introuvable.'], 404);
        }

        $this->db->prepare(
            "UPDATE users SET is_deleted = 1, is_active = 0, updated_at = NOW() WHERE id = ?"
        )->execute([$id]);

        Response::json(['success' => true, 'message' => "Utilisateur {$row['prenom']} {$row['nom']} supprimé."]);
    }

    // ── CSV export ────────────────────────────────────────────────────────
    private function exportCsv(array $filters): void
    {
        $users = $this->model->getAllUsers($filters, 10000, 0);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="utilisateurs_' . date('Y-m-d') . '.csv"');
        header('Cache-Control: no-cache');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, ['ID', 'Prénom', 'Nom', 'Email', 'Rôle', 'Série', 'Statut', 'Inscrit le', 'Dernier login']);

        foreach ($users as $u) {
            fputcsv($out, [
                $u['id'],
                $u['prenom'],
                $u['nom'],
                $u['email'],
                ucfirst($u['role']),
                $u['serie'] ?? '',
                $u['is_active'] ? 'Actif' : 'Suspendu',
                $u['created_at'] ? date('d/m/Y', strtotime($u['created_at'])) : '',
                $u['last_login'] ? date('d/m/Y H:i', strtotime($u['last_login'])) : '',
            ]);
        }
        fclose($out);
        exit;
    }
}
