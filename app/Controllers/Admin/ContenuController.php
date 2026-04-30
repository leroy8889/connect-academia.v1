<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\{Response, Session};
use Core\Database;
use PDO;

class ContenuController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function index(): void
    {
        $series = $this->db->query(
            "SELECT id, nom FROM series WHERE is_active = 1 ORDER BY nom"
        )->fetchAll(PDO::FETCH_ASSOC);

        $matieresBySerie = [];
        $matiStmt = $this->db->query(
            "SELECT id, nom, serie_id FROM matieres WHERE is_active = 1 ORDER BY serie_id, ordre, nom"
        );
        foreach ($matiStmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
            $matieresBySerie[(int)$m['serie_id']][] = ['id' => (int)$m['id'], 'nom' => $m['nom']];
        }

        $where  = ['r.is_deleted = 0'];
        $params = [];

        if (!empty($_GET['serie_id'])) {
            $where[]  = 'm.serie_id = ?';
            $params[] = (int) $_GET['serie_id'];
        }
        if (!empty($_GET['matiere_id'])) {
            $where[]  = 'r.matiere_id = ?';
            $params[] = (int) $_GET['matiere_id'];
        }
        if (!empty($_GET['type'])) {
            $where[]  = 'r.type = ?';
            $params[] = $_GET['type'];
        }

        $stmt = $this->db->prepare(
            "SELECT r.id, r.titre, r.type, r.nb_vues, r.created_at,
                    m.nom AS matiere, s.nom AS serie
             FROM ressources r
             JOIN matieres m ON m.id = r.matiere_id
             JOIN series s ON s.id = m.serie_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY r.created_at DESC
             LIMIT 100"
        );
        $stmt->execute($params);
        $ressources = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalRessources = (int) $this->db->query(
            "SELECT COUNT(*) FROM ressources WHERE is_deleted = 0"
        )->fetchColumn();

        $totalVues = (int) $this->db->query(
            "SELECT COALESCE(SUM(nb_vues), 0) FROM ressources
             WHERE is_deleted = 0 AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')"
        )->fetchColumn();

        Response::view('admin/contenu/index', [
            'pageTitle'         => 'Ressources — Admin',
            'breadcrumbSection' => 'Apprentissage',
            'breadcrumbPage'    => 'Bibliothèque',
            'ressources'        => $ressources,
            'series'            => $series,
            'matieresBySerie'   => $matieresBySerie,
            'totalRessources'   => $totalRessources,
            'totalVues'         => $totalVues,
            'totalPdf'          => $totalRessources,
        ], 'admin');
    }

    public function storeRessource(): void
    {
        $titre     = trim($_POST['titre'] ?? '');
        $matiereId = (int) ($_POST['matiere_id'] ?? 0);
        $type      = $_POST['type'] ?? '';
        $serieId   = (int) ($_POST['serie_id'] ?? 0);
        $annee     = !empty($_POST['annee']) ? (int) $_POST['annee'] : null;
        $desc      = trim($_POST['description'] ?? '');

        if (!$titre || !$matiereId || !in_array($type, ['cours', 'td', 'ancienne_epreuve', 'corrige'])) {
            Response::json(['success' => false, 'message' => 'Champs obligatoires manquants.'], 422);
        }

        $upload = $_FILES['fichier'] ?? null;
        if (!$upload || $upload['error'] !== UPLOAD_ERR_OK) {
            Response::json(['success' => false, 'message' => 'Fichier requis.'], 422);
        }

        $maxMb = (int) ($this->getSetting('max_upload_mb') ?? 50);
        if ($upload['size'] > $maxMb * 1024 * 1024) {
            Response::json(['success' => false, 'message' => "Fichier trop lourd (max {$maxMb} Mo)."], 413);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $upload['tmp_name']);
        finfo_close($finfo);
        if ($mime !== 'application/pdf') {
            Response::json(['success' => false, 'message' => 'Seuls les fichiers PDF sont acceptés.'], 415);
        }

        $uploadDir = BASE_PATH . '/public/uploads/ressources/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = uniqid('res_', true) . '.pdf';
        $destPath = $uploadDir . $filename;

        if (!move_uploaded_file($upload['tmp_name'], $destPath)) {
            Response::json(['success' => false, 'message' => 'Erreur lors du déplacement du fichier.'], 500);
        }

        $filePath    = 'uploads/ressources/' . $filename;
        $fileNom     = $upload['name'] ?? $filename;
        $tailleOctet = $upload['size'] ?? 0;

        // Récupérer le serie_id depuis la matière
        $matiereRow = $this->db->prepare("SELECT serie_id FROM matieres WHERE id = ?");
        $matiereRow->execute([$matiereId]);
        $serieId = (int) ($matiereRow->fetchColumn() ?: ($serieId ?? 0));

        $this->db->prepare(
            "INSERT INTO ressources (titre, description, type, matiere_id, serie_id, annee,
                                     fichier_path, fichier_nom, taille_fichier, admin_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([$titre, $desc, $type, $matiereId, $serieId, $annee,
                    $filePath, $fileNom, $tailleOctet, Session::adminId()]);

        $id = (int) $this->db->lastInsertId();

        Response::json([
            'success' => true,
            'message' => "Ressource « {$titre} » publiée avec succès.",
            'id'      => $id,
        ]);
    }

    public function deleteRessource(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);

        $res = $this->db->prepare(
            "SELECT fichier_path FROM ressources WHERE id = ? AND is_deleted = 0"
        );
        $res->execute([$id]);
        $row = $res->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            Response::json(['success' => false, 'message' => 'Ressource introuvable.'], 404);
        }

        $this->db->prepare(
            "UPDATE ressources SET is_deleted = 1, updated_at = NOW() WHERE id = ?"
        )->execute([$id]);

        Response::json(['success' => true, 'message' => 'Ressource supprimée.']);
    }

    // ── PATCH /admin/api/contenu/ressource/{id} ───────────────────────────
    public function updateRessource(array $params): void
    {
        $id    = (int) ($params['id'] ?? 0);
        $titre = trim($_POST['titre'] ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $type  = $_POST['type'] ?? '';
        $annee = !empty($_POST['annee']) ? (int) $_POST['annee'] : null;

        if (!$id || !$titre) {
            Response::json(['success' => false, 'message' => 'Titre obligatoire.'], 422);
        }

        if (!in_array($type, ['cours', 'td', 'ancienne_epreuve', 'corrige'], true)) {
            Response::json(['success' => false, 'message' => 'Type invalide.'], 422);
        }

        $exists = $this->db->prepare("SELECT id FROM ressources WHERE id = ? AND is_deleted = 0");
        $exists->execute([$id]);
        if (!$exists->fetch()) {
            Response::json(['success' => false, 'message' => 'Ressource introuvable.'], 404);
        }

        $this->db->prepare(
            "UPDATE ressources SET titre=?, description=?, type=?, annee=?, updated_at=NOW() WHERE id=?"
        )->execute([$titre, $desc ?: null, $type, $annee, $id]);

        Response::json(['success' => true, 'message' => 'Ressource mise à jour.']);
    }

    // ── GET /admin/api/matieres?serie_id=X ───────────────────────────────
    public function getMatieres(): void
    {
        $serieId = (int) ($_GET['serie_id'] ?? 0);

        if (!$serieId) {
            Response::json(['success' => false, 'message' => 'serie_id requis.'], 422);
        }

        $matieres = $this->db->prepare(
            "SELECT id, nom FROM matieres WHERE serie_id = ? AND is_active = 1 ORDER BY ordre, nom"
        );
        $matieres->execute([$serieId]);

        Response::json(['success' => true, 'matieres' => $matieres->fetchAll(PDO::FETCH_ASSOC)]);
    }

    private function getSetting(string $key): ?string
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT setting_value FROM settings WHERE setting_key = ?"
            );
            $stmt->execute([$key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row['setting_value'] : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
