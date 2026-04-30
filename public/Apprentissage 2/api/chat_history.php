<?php
/**
 * API Chat History - Récupérer l'historique des conversations
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
session_start();

header('Content-Type: application/json; charset=utf-8');

// Vérifier l'authentification
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Authentification requise']));
}

$user_id = $_SESSION['user_id'];

// Vérifier la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    die(json_encode(['success' => false, 'error' => 'Méthode non autorisée']));
}

try {
    $pdo = getDB();
    
    $document_id = intval($_GET['document_id'] ?? 0);
    
    if ($document_id < 1) {
        http_response_code(400);
        die(json_encode(['success' => false, 'error' => 'ID document invalide']));
    }
    
    // Récupérer l'historique
    $stmt = $pdo->prepare("
        SELECT user_message, ia_response, created_at
        FROM ia_conversations
        WHERE user_id = ? AND document_id = ?
        ORDER BY created_at ASC
        LIMIT 20
    ");
    $stmt->execute([$user_id, $document_id]);
    $history = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'history' => $history
    ]);
    
} catch (Exception $e) {
    error_log('Erreur API Chat History: ' . $e->getMessage());
    http_response_code(500);
    die(json_encode(['success' => false, 'error' => 'Erreur serveur']));
}

