<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\{Response, Session, Database, AdminPermissions, Cache};
use Models\Admin;
use Models\User;
use Models\Notification;
use Models\Orientation\NotificationOrientation;
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

        // --- TRAITEMENT DES PHOTOS DE PROFIL ---
        foreach ($users as &$user) {
            $user['photo_profil_url'] = User::normalizePhotoPath($user['photo_profil'] ?? null);
        }

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

    // ── GET /admin/api/utilisateurs/statuts ──────────────────────────────
    public function statuts(): void
    {
        $now     = time();
        $statuts = Cache::get('admin:user:statuts');

        if ($statuts === null) {
            $threshold = 300; // 5 minutes = en ligne
            $rows = $this->db->query(
                "SELECT id, UNIX_TIMESTAMP(last_activity) AS last_activity_ts FROM users WHERE is_deleted = 0"
            )->fetchAll(PDO::FETCH_ASSOC);

            $statuts = [];
            foreach ($rows as $row) {
                $ts = (int) $row['last_activity_ts'];
                $statuts[(int) $row['id']] = [
                    'is_online' => $ts > 0 && ($now - $ts) < $threshold,
                ];
            }
            // Cache 60s — scan complet de la table, inutile de le refaire à chaque appel admin
            Cache::set('admin:user:statuts', $statuts, 60);
        }

        Response::json(['success' => true, 'statuts' => $statuts, 'ts' => $now]);
    }

    // ── GET /admin/api/utilisateurs/{id} ─────────────────────────────────
    public function show(array $params): void
    {
        $id   = (int) ($params['id'] ?? 0);
        $stmt = $this->db->prepare(
            "SELECT u.id, u.nom, u.prenom, u.email, u.telephone, u.role, u.serie_id, u.is_active,
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
        if (AdminPermissions::isUsersReadOnly(Session::adminRole() ?? '')) {
            Response::json(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Accès en lecture seule pour votre rôle.']], 403);
        }

        $prenom  = trim($_POST['prenom'] ?? '');
        $nom     = trim($_POST['nom'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $telRaw  = trim($_POST['telephone'] ?? '');
        $role    = $_POST['role'] ?? 'eleve';
        $serieId = !empty($_POST['serie_id']) ? (int) $_POST['serie_id'] : null;
        $mdp     = $_POST['password'] ?? '';

        if (!$prenom || !$nom || !$email) {
            Response::json(['success' => false, 'message' => 'Prénom, nom et email sont obligatoires.'], 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::json(['success' => false, 'message' => 'Email invalide.'], 422);
        }

        $telephone = null;
        if ($telRaw !== '') {
            $telephone = \Core\Phone::normalizeGabon($telRaw);
            if ($telephone === null) {
                Response::json(['success' => false, 'message' => 'Numéro gabonais invalide — ex : 074 12 34 56 (01, 06 ou 07).'], 422);
            }
        }

        if (!in_array($role, ['eleve', 'gestionnaire'], true)) {
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
            "INSERT INTO users (uuid, nom, prenom, email, telephone, password_hash, serie_id, role, is_active, is_verified)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1)"
        )->execute([$uuid, $nom, $prenom, $email, $telephone, $hash, $serieId, $role]);

        $id = (int) $this->db->lastInsertId();

        // Sécurité (WEB-03) — le mot de passe n'est JAMAIS renvoyé au client
        // (risque de journalisation/cache). L'admin le transmet via le champ
        // du formulaire s'il l'a saisi ; un mot de passe auto-généré doit être
        // communiqué à l'utilisateur par un canal dédié (email).
        Response::json([
            'success' => true,
            'message' => "Utilisateur {$prenom} {$nom} créé avec succès.",
            'id'      => $id,
        ]);
    }

    // ── PATCH /admin/api/utilisateurs/{id} ───────────────────────────────
    public function update(array $params): void
    {
        if (AdminPermissions::isUsersReadOnly(Session::adminRole() ?? '')) {
            Response::json(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Accès en lecture seule pour votre rôle.']], 403);
        }

        $id      = (int) ($params['id'] ?? 0);
        $prenom  = trim($_POST['prenom'] ?? '');
        $nom     = trim($_POST['nom'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $telRaw  = trim($_POST['telephone'] ?? '');
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

        $telephone = null;
        if ($telRaw !== '') {
            $telephone = \Core\Phone::normalizeGabon($telRaw);
            if ($telephone === null) {
                Response::json(['success' => false, 'message' => 'Numéro gabonais invalide — ex : 074 12 34 56 (01, 06 ou 07).'], 422);
            }
        }

        if (!in_array($role, ['eleve', 'gestionnaire'], true)) {
            Response::json(['success' => false, 'message' => 'Rôle invalide.'], 422);
        }

        // Unicité email (hors cet user)
        $exists = $this->db->prepare("SELECT id FROM users WHERE email = ? AND id != ? AND is_deleted = 0");
        $exists->execute([$email, $id]);
        if ($exists->fetch()) {
            Response::json(['success' => false, 'message' => 'Cet email est déjà utilisé.'], 409);
        }

        $this->db->prepare(
            "UPDATE users SET nom=?, prenom=?, email=?, telephone=?, role=?, serie_id=?, bio=?, etablissement=?, updated_at=NOW()
             WHERE id=? AND is_deleted=0"
        )->execute([$nom, $prenom, $email, $telephone, $role, $serieId, $bio ?: null, $etab ?: null, $id]);

        Response::json(['success' => true, 'message' => "Utilisateur mis à jour."]);
    }

    // ── PATCH /admin/api/utilisateurs/{id}/toggle ────────────────────────
    public function toggle(array $params): void
    {
        if (AdminPermissions::isUsersReadOnly(Session::adminRole() ?? '')) {
            Response::json(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Accès en lecture seule pour votre rôle.']], 403);
        }

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
        if (AdminPermissions::isUsersReadOnly(Session::adminRole() ?? '')) {
            Response::json(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Accès en lecture seule pour votre rôle.']], 403);
        }

        $id = (int) ($params['id'] ?? 0);

        $user = $this->db->prepare("SELECT id, prenom, nom FROM users WHERE id = ? AND is_deleted = 0");
        $user->execute([$id]);
        $row = $user->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            Response::json(['success' => false, 'message' => 'Utilisateur introuvable.'], 404);
        }

        // Purge définitive : le compte ET tout ce qui le concerne (posts,
        // commentaires, likes, avis, favoris, notifications, accès gestionnaire…)
        // partent en cascade ; ses dossiers d'inscription restent archivés
        // côté établissements. Sa session meurt à sa prochaine requête.
        $ok = (new \Services\DeletionService())->purgeUser($id, Session::adminId());

        if (!$ok) {
            Response::json(['success' => false, 'message' => 'La suppression a échoué — consultez les logs.'], 500);
        }

        Response::json(['success' => true, 'message' => "Utilisateur {$row['prenom']} {$row['nom']} supprimé définitivement, ainsi que tout son contenu."]);
    }

    // ── POST /admin/api/utilisateurs/{id}/message ─────────────────────────
    // Envoie un message de l'administration à un utilisateur : il arrive
    // comme notification in-app (cloche navbar + espace élève orientation).
    public function sendMessage(array $params): void
    {
        if (AdminPermissions::isUsersReadOnly(Session::adminRole() ?? '')) {
            Response::json(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Accès en lecture seule pour votre rôle.']], 403);
        }

        $id      = (int) ($params['id'] ?? 0);
        $message = trim($_POST['message'] ?? '');

        if ($message === '') {
            Response::json(['success' => false, 'message' => 'Le message est vide.'], 422);
        }
        if (mb_strlen($message) > 500) {
            Response::json(['success' => false, 'message' => 'Message trop long (500 caractères max).'], 422);
        }

        $stmt = $this->db->prepare("SELECT id, prenom, nom FROM users WHERE id = ? AND is_deleted = 0");
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            Response::json(['success' => false, 'message' => 'Utilisateur introuvable.'], 404);
        }

        // Cloche navbar (compte communauté / hub) — actor_id NULL = message officiel
        (new Notification())->create([
            'user_id' => $id,
            'type'    => 'announcement',
            'message' => $message,
        ]);

        // Espace élève orientation (page Notifications) — même message
        (new NotificationOrientation())->create([
            'user_id' => $id,
            'type'    => 'message_admin',
            'titre'   => 'Message de l\'administration',
            'message' => $message,
        ]);

        Response::json(['success' => true, 'message' => "Message envoyé à {$user['prenom']} {$user['nom']}."]);
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
        fputcsv($out, ['ID', 'Prénom', 'Nom', 'Email', 'Téléphone', 'Rôle', 'Série', 'Établissement (inscrit)', 'Statut', 'Inscrit le', 'Dernier login']);

        foreach ($users as $u) {
            fputcsv($out, [
                $u['id'],
                $u['prenom'],
                $u['nom'],
                $u['email'],
                \Core\Phone::formatGabon($u['telephone'] ?? null),
                ucfirst($u['role']),
                $u['serie'] ?? '',
                $u['etabs_inscrit'] ?? '',
                $u['is_active'] ? 'Actif' : 'Suspendu',
                $u['created_at'] ? date('d/m/Y', strtotime($u['created_at'])) : '',
                $u['last_login'] ? date('d/m/Y H:i', strtotime($u['last_login'])) : '',
            ]);
        }
        fclose($out);
        exit;
    }
}