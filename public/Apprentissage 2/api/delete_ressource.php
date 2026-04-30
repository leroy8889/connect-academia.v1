<?php
/**
 * API Delete Ressource - Suppression soft d'une ressource
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
    
    // Récupérer l'ID de la ressource
    $data = json_decode(file_get_contents('php://input'), true);
    $ressource_id = intval($data['ressource_id'] ?? 0);
    
    if ($ressource_id < 1) {
        http_response_code(400);
        die(json_encode(['success' => false, 'error' => 'ID ressource invalide']));
    }
    
    // Vérifier que la ressource existe et n'est pas déjà supprimée
    $stmt = $pdo->prepare("SELECT id, titre FROM ressources WHERE id = ? AND is_deleted = 0");
    $stmt->execute([$ressource_id]);
    $ressource = $stmt->fetch();
    
    if (!$ressource) {
        http_response_code(404);
        die(json_encode(['success' => false, 'error' => 'Ressource non trouvée']));
    }
    
    // Soft delete : marquer comme supprimée
    $stmt = $pdo->prepare("UPDATE ressources SET is_deleted = 1, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$ressource_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Ressource supprimée avec succès'
    ]);
} catch (PDOException $e) {
    error_log('Erreur suppression ressource: ' . $e->getMessage());
    http_response_code(500);
    die(json_encode(['success' => false, 'error' => 'Erreur serveur']));
} catch (Exception $e) {
    error_log('Erreur suppression ressource: ' . $e->getMessage());
    http_response_code(500);
    die(json_encode(['success' => false, 'error' => 'Erreur serveur']));
}

