<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\{Response, Session};
use Core\Database;
use PDO;
use Exception;

class ContenuController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Affiche la bibliothèque des ressources
     */
    public function index(): void
    {
        // Récupération des séries pour les filtres
        $series = $this->db->query(
            "SELECT id, nom FROM series WHERE is_active = 1 ORDER BY nom"
        )->fetchAll(PDO::FETCH_ASSOC);

        // Récupération des matières groupées par série
        $matieresBySerie = [];
        $matiStmt = $this->db->query(
            "SELECT id, nom, serie_id FROM matieres WHERE is_active = 1 ORDER BY serie_id, ordre, nom"
        );
        foreach ($matiStmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
            $matieresBySerie[(int)$m['serie_id']][] = ['id' => (int)$m['id'], 'nom' => $m['nom']];
        }

        // Construction des filtres de recherche
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

        // Requête principale avec récupération du chemin du fichier
        $stmt = $this->db->prepare(
            "SELECT r.id, r.titre, r.type, r.nb_vues, r.created_at, r.fichier_path,
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

        // --- NORMALISATION DES URLS POUR LA VISUALISATION ---
        foreach ($ressources as &$res) {
            $path = $res['fichier_path'] ?? '';
            if (!empty($path)) {
                // Nettoyage du chemin pour pointer vers le dossier public
                if (!str_starts_with($path, '/public/') && !str_starts_with($path, 'public/')) {
                    $path = '/public/' . ltrim($path, '/');
                }
                if (!str_starts_with($path, '/')) $path = '/' . $path;
                $res['file_url'] = (defined('BASE_URL') && BASE_URL !== '') ? BASE_URL . $path : $path;
            } else {
                $res['file_url'] = '#';
            }
        }

        // Statistiques du mois
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

    /**
     * Enregistre une nouvelle ressource (Upload PDF)
     */
    public function storeRessource(): void
    {
        try {
            $titre     = trim($_POST['titre'] ?? '');
            $matiereId = (int) ($_POST['matiere_id'] ?? 0);
            $type      = $_POST['type'] ?? 'cours';
            $serieId   = (int) ($_POST['serie_id'] ?? 0);
            $annee     = !empty($_POST['annee']) ? (int) $_POST['annee'] : null;
            $desc      = trim($_POST['description'] ?? '');

            // 1. Validation des champs
            if (!$titre || !$matiereId) {
                Response::json(['success' => false, 'message' => 'Titre et matière requis.'], 422);
                return;
            }

            // 2. Gestion de l'upload
            $upload = $_FILES['fichier'] ?? null;
            if (!$upload || $upload['error'] !== UPLOAD_ERR_OK) {
                Response::json(['success' => false, 'message' => 'Erreur lors du téléchargement du fichier.'], 422);
                return;
            }

            // Vérification du type MIME (PDF uniquement)
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $upload['tmp_name']);
            finfo_close($finfo);
            if ($mime !== 'application/pdf') {
                Response::json(['success' => false, 'message' => 'Seuls les fichiers PDF sont acceptés.'], 415);
                return;
            }

            // 3. Préparation du dossier de destination
            $uploadDir = BASE_PATH . '/public/uploads/ressources/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $filename = uniqid('res_', true) . '.pdf';
            $destPath = $uploadDir . $filename;

            // 4. Déplacement du fichier et insertion en base
            if (move_uploaded_file($upload['tmp_name'], $destPath)) {
                $filePath    = 'uploads/ressources/' . $filename;
                $fileNom     = $upload['name'] ?? $filename;
                $tailleOctet = $upload['size'] ?? 0;

                // Récupération automatique du serie_id si manquant
                if ($serieId === 0) {
                    $mStmt = $this->db->prepare("SELECT serie_id FROM matieres WHERE id = ?");
                    $mStmt->execute([$matiereId]);
                    $serieId = (int) ($mStmt->fetchColumn() ?: 0);
                }

                $stmt = $this->db->prepare(
                    "INSERT INTO ressources (titre, description, type, matiere_id, serie_id, annee,
                                             fichier_path, fichier_nom, taille_fichier, admin_id, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
                );

                $stmt->execute([
                    $titre, $desc, $type, $matiereId, $serieId, $annee,
                    $filePath, $fileNom, $tailleOctet, Session::adminId()
                ]);

                Response::json([
                    'success' => true,
                    'message' => "La ressource « {$titre} » a été publiée avec succès.",
                    'id'      => (int) $this->db->lastInsertId()
                ]);
            } else {
                Response::json(['success' => false, 'message' => 'Échec du déplacement du fichier sur le serveur.'], 500);
            }

        } catch (Exception $e) {
            // Capture l'erreur réelle pour éviter le message "Réponse invalide" générique
            Response::json([
                'success' => false,
                'message' => 'Erreur base de données : ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprime une ressource (Soft delete)
     */
    public function deleteRessource(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $this->db->prepare("UPDATE ressources SET is_deleted = 1, updated_at = NOW() WHERE id = ?")->execute([$id]);
        Response::json(['success' => true, 'message' => 'Ressource supprimée avec succès.']);
    }

    /**
     * Met à jour les informations d'une ressource
     */
    public function updateRessource(array $params): void
    {
        $id    = (int) ($params['id'] ?? 0);
        $titre = trim($_POST['titre'] ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $type  = $_POST['type'] ?? 'cours';
        $annee = !empty($_POST['annee']) ? (int) $_POST['annee'] : null;

        if (!$id || !$titre) {
            Response::json(['success' => false, 'message' => 'Le titre est obligatoire.'], 422);
            return;
        }

        $this->db->prepare(
            "UPDATE ressources SET titre=?, description=?, type=?, annee=?, updated_at=NOW() WHERE id=?"
        )->execute([$titre, $desc, $type, $annee, $id]);

        Response::json(['success' => true, 'message' => 'Ressource mise à jour.']);
    }

    /**
     * API pour charger les matières dynamiquement selon la série
     */
    public function getMatieres(): void
    {
        $serieId = (int) ($_GET['serie_id'] ?? 0);
        $matieres = $this->db->prepare(
            "SELECT id, nom FROM matieres WHERE serie_id = ? AND is_active = 1 ORDER BY ordre, nom"
        );
        $matieres->execute([$serieId]);
        Response::json(['success' => true, 'matieres' => $matieres->fetchAll(PDO::FETCH_ASSOC)]);
    }

    /**
     * Récupère un réglage système
     */
    private function getSetting(string $key): ?string
    {
        $stmt = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (string)$val : null;
    }
}