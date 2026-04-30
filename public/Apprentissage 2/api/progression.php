<?php
/**
 * API Progression - Tracking progression PDF
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
$action = $data['action'] ?? '';
$ressource_id = intval($data['ressource_id'] ?? 0);

if ($ressource_id < 1) {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'ID ressource invalide']));
}

try {
    $pdo = getDB();
    $user_id = $_SESSION['user_id'];
    
    switch ($action) {
        case 'start':
            // Créer ou récupérer la progression
            $stmt = $pdo->prepare("
                INSERT INTO progressions (user_id, ressource_id, statut, started_at)
                VALUES (?, ?, 'en_cours', NOW())
                ON DUPLICATE KEY UPDATE statut = 'en_cours', started_at = COALESCE(started_at, NOW())
            ");
            $stmt->execute([$user_id, $ressource_id]);
            
            // Créer une session de révision
            $stmt = $pdo->prepare("
                INSERT INTO sessions_revision (user_id, ressource_id, debut) VALUES (?, ?, NOW())
            ");
            $stmt->execute([$user_id, $ressource_id]);
            $session_id = $pdo->lastInsertId();
            
            // Incrémenter les vues de la ressource au démarrage
            $pdo->prepare("UPDATE ressources SET nb_vues = nb_vues + 1 WHERE id = ?")
                ->execute([$ressource_id]);
            
            echo json_encode(['success' => true, 'session_id' => $session_id]);
            break;
            
        case 'heartbeat':
        case 'end':
            $temps = intval($data['temps'] ?? 0);
            $page_actuelle = intval($data['page_actuelle'] ?? 1);
            $total_pages = intval($data['total_pages'] ?? 1);
            
            $pourcentage = min(100, round(($page_actuelle / $total_pages) * 100));
            
            // Vérifier si la progression existe, sinon la créer
            $stmt = $pdo->prepare("SELECT id FROM progressions WHERE user_id = ? AND ressource_id = ?");
            $stmt->execute([$user_id, $ressource_id]);
            $progression_exists = $stmt->fetch();
            
            if (!$progression_exists) {
                // Créer la progression si elle n'existe pas
                $stmt = $pdo->prepare("
                    INSERT INTO progressions (user_id, ressource_id, statut, started_at, temps_passe, derniere_page, pourcentage)
                    VALUES (?, ?, 'en_cours', NOW(), 0, 1, 0)
                ");
                $stmt->execute([$user_id, $ressource_id]);
            }
            
            // Mettre à jour la progression
            $stmt = $pdo->prepare("
                UPDATE progressions
                SET temps_passe = COALESCE(temps_passe, 0) + ?,
                    derniere_page = ?,
                    pourcentage = ?,
                    statut = IF(? >= 100, 'termine', 'en_cours'),
                    completed_at = IF(? >= 100, NOW(), completed_at)
                WHERE user_id = ? AND ressource_id = ?
            ");
            $stmt->execute([$temps, $page_actuelle, $pourcentage, $pourcentage, $pourcentage, $user_id, $ressource_id]);
            
            // Mettre à jour la session en cours (si elle existe)
            $stmt = $pdo->prepare("
                UPDATE sessions_revision
                SET duree_secondes = COALESCE(duree_secondes, 0) + ?,
                    fin = IF(?, NOW(), NULL)
                WHERE user_id = ? AND ressource_id = ? AND fin IS NULL
                ORDER BY debut DESC
                LIMIT 1
            ");
            $stmt->execute([$temps, (int)($action === 'end'), $user_id, $ressource_id]);
            
            echo json_encode(['success' => true, 'pourcentage' => $pourcentage]);
            break;
            
        case 'complete':
            $stmt = $pdo->prepare("
                UPDATE progressions
                SET statut = 'termine', pourcentage = 100, completed_at = NOW()
                WHERE user_id = ? AND ressource_id = ?
            ");
            $stmt->execute([$user_id, $ressource_id]);
            
            echo json_encode(['success' => true]);
            break;
            
        default:
            http_response_code(400);
            die(json_encode(['success' => false, 'error' => 'Action invalide']));
    }
} catch (PDOException $e) {
    error_log('Erreur progression: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    http_response_code(500);
    die(json_encode(['success' => false, 'error' => 'Erreur serveur: ' . $e->getMessage()]));
} catch (Exception $e) {
    error_log('Erreur progression (générale): ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    http_response_code(500);
    die(json_encode(['success' => false, 'error' => 'Erreur serveur']));
}

