<?php
/**
 * API Matières - GET matières par série
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
session_start();

// Permettre l'accès aux élèves et aux admins
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Non authentifié']));
}

$serie_id = intval($_GET['serie_id'] ?? 0);

if ($serie_id < 1) {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'serie_id requis']));
}

try {
    $pdo = getDB();
    $user_id = $_SESSION['user_id'] ?? null;
    
    // Si admin, pas de progression utilisateur
    if ($user_id) {
        $stmt = $pdo->prepare("
            SELECT m.id, m.nom, m.icone, m.ordre,
                   COUNT(DISTINCT r.id) AS nb_ressources,
                   COALESCE(AVG(p.pourcentage), 0) AS progression_moyenne
            FROM matieres m
            LEFT JOIN ressources r ON r.matiere_id = m.id AND r.is_deleted = 0
            LEFT JOIN progressions p ON p.ressource_id = r.id AND p.user_id = ?
            WHERE m.serie_id = ? AND m.is_active = 1
            GROUP BY m.id
            ORDER BY m.ordre ASC
        ");
        $stmt->execute([$user_id, $serie_id]);
    } else {
        // Pour admin, pas de progression
        $stmt = $pdo->prepare("
            SELECT m.id, m.nom, m.icone, m.ordre,
                   COUNT(DISTINCT r.id) AS nb_ressources,
                   0 AS progression_moyenne
            FROM matieres m
            LEFT JOIN ressources r ON r.matiere_id = m.id AND r.is_deleted = 0
            WHERE m.serie_id = ? AND m.is_active = 1
            GROUP BY m.id
            ORDER BY m.ordre ASC
        ");
        $stmt->execute([$serie_id]);
    }
    $matieres = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $matieres
    ]);
} catch (PDOException $e) {
    error_log('Erreur API matieres: ' . $e->getMessage());
    http_response_code(500);
    die(json_encode(['success' => false, 'error' => 'Erreur serveur']));
}

