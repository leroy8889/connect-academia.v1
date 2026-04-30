<?php
/**
 * API Upload - Upload PDF ressource
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
session_start();

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Accès refusé']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'error' => 'Méthode non autorisée']));
}

try {
    $pdo = getDB();
    
    // Validation des champs
    $titre = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $type = $_POST['type'] ?? '';
    $serie_id = intval($_POST['serie_id'] ?? 0);
    $matiere_id = intval($_POST['matiere_id'] ?? 0);
    $chapitre = trim($_POST['chapitre'] ?? '');
    
    if (empty($titre) || empty($type) || $serie_id < 1 || $matiere_id < 1) {
        http_response_code(400);
        die(json_encode(['success' => false, 'error' => 'Champs requis manquants']));
    }
    
    if (!isset($_FILES['fichier']) || $_FILES['fichier']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        die(json_encode(['success' => false, 'error' => 'Fichier manquant ou erreur upload']));
    }
    
    // Validation MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $_FILES['fichier']['tmp_name']);
    
    if ($mimeType !== 'application/pdf') {
        http_response_code(400);
        die(json_encode(['success' => false, 'error' => 'Seuls les fichiers PDF sont acceptés']));
    }
    
    // Validation taille
    if ($_FILES['fichier']['size'] > UPLOAD_MAX_SIZE) {
        http_response_code(400);
        die(json_encode(['success' => false, 'error' => 'Fichier trop volumineux (max 50 Mo)']));
    }
    
    // Créer le répertoire si nécessaire
    $serie_nom = $pdo->prepare("SELECT nom FROM series WHERE id = ?");
    $serie_nom->execute([$serie_id]);
    $serie_nom = $serie_nom->fetchColumn();
    
    $matiere_nom = $pdo->prepare("SELECT nom FROM matieres WHERE id = ?");
    $matiere_nom->execute([$matiere_id]);
    $matiere_nom = $matiere_nom->fetchColumn();
    
    $uploadDir = UPLOAD_PATH . $serie_nom . '/' . strtolower(str_replace(' ', '_', $matiere_nom)) . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Renommer le fichier
    $newName = uniqid('ressource_', true) . '.pdf';
    $uploadPath = $uploadDir . $newName;
    
    if (!move_uploaded_file($_FILES['fichier']['tmp_name'], $uploadPath)) {
        http_response_code(500);
        die(json_encode(['success' => false, 'error' => 'Erreur lors du déplacement du fichier']));
    }
    
    // Récupérer le chapitre si fourni
    $chapitre_id = null;
    if (!empty($chapitre)) {
        $stmt = $pdo->prepare("SELECT id FROM chapitres WHERE titre = ? AND matiere_id = ? LIMIT 1");
        $stmt->execute([$chapitre, $matiere_id]);
        $chapitre_id = $stmt->fetchColumn();
        
        if (!$chapitre_id) {
            // Créer le chapitre
            $stmt = $pdo->prepare("INSERT INTO chapitres (titre, matiere_id) VALUES (?, ?)");
            $stmt->execute([$chapitre, $matiere_id]);
            $chapitre_id = $pdo->lastInsertId();
        }
    }
    
    // Insérer en BDD
    $stmt = $pdo->prepare("
        INSERT INTO ressources (titre, description, type, fichier_path, fichier_nom, taille_fichier, 
                               matiere_id, chapitre_id, serie_id, admin_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $fichier_path = 'uploads/ressources/' . $serie_nom . '/' . strtolower(str_replace(' ', '_', $matiere_nom)) . '/' . $newName;
    $taille_fichier = $_FILES['fichier']['size'];
    
    $stmt->execute([
        $titre,
        $description ?: null,
        $type,
        $fichier_path,
        $_FILES['fichier']['name'],
        $taille_fichier,
        $matiere_id,
        $chapitre_id,
        $serie_id,
        $_SESSION['admin_id']
    ]);
    
    echo json_encode([
        'success' => true,
        'data' => ['id' => $pdo->lastInsertId()]
    ]);
} catch (PDOException $e) {
    error_log('Erreur upload: ' . $e->getMessage());
    http_response_code(500);
    die(json_encode(['success' => false, 'error' => 'Erreur serveur']));
} catch (Exception $e) {
    error_log('Erreur upload: ' . $e->getMessage());
    http_response_code(500);
    die(json_encode(['success' => false, 'error' => 'Erreur serveur']));
}

