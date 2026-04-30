<?php
/**
 * API Séries - GET toutes les séries actives
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT id, nom, description, couleur FROM series WHERE is_active = 1 ORDER BY nom ASC");
    $series = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $series
    ]);
} catch (PDOException $e) {
    error_log('Erreur API series: ' . $e->getMessage());
    http_response_code(500);
    die(json_encode(['success' => false, 'error' => 'Erreur serveur']));
}

