<?php
/**
 * API Favoris - Toggle favori
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Non authentifié']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'error' => 'Méthode non autorisée']));
}

$data = json_decode(file_get_contents('php://input'), true);
$ressource_id = intval($data['ressource_id'] ?? 0);

if ($ressource_id < 1) {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'ID ressource invalide']));
}

try {
    $pdo = getDB();
    $user_id = $_SESSION['user_id'];
    
    // Vérifier si le favori existe
    $stmt = $pdo->prepare("SELECT id FROM favoris WHERE user_id = ? AND ressource_id = ?");
    $stmt->execute([$user_id, $ressource_id]);
    $exists = $stmt->fetch();
    
    if ($exists) {
        // Supprimer
        $pdo->prepare("DELETE FROM favoris WHERE user_id = ? AND ressource_id = ?")
            ->execute([$user_id, $ressource_id]);
        echo json_encode(['success' => true, 'action' => 'removed', 'est_favori' => false]);
    } else {
        // Ajouter
        $pdo->prepare("INSERT INTO favoris (user_id, ressource_id) VALUES (?, ?)")
            ->execute([$user_id, $ressource_id]);
        echo json_encode(['success' => true, 'action' => 'added', 'est_favori' => true]);
    }
} catch (PDOException $e) {
    error_log('Erreur favoris: ' . $e->getMessage());
    http_response_code(500);
    die(json_encode(['success' => false, 'error' => 'Erreur serveur']));
}

