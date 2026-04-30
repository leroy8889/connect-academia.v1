<?php
/**
 * API Ressources - GET ressources filtrées
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Non authentifié']));
}

try {
    $pdo = getDB();
    $user_id = $_SESSION['user_id'];
    
    // Paramètres de filtrage
    $matiere_id = isset($_GET['matiere_id']) ? intval($_GET['matiere_id']) : null;
    $serie_id = isset($_GET['serie_id']) ? intval($_GET['serie_id']) : null;
    $type = $_GET['type'] ?? null;
    $search = $_GET['search'] ?? '';
    $limit = intval($_GET['limit'] ?? 20);
    $page = intval($_GET['page'] ?? 1);
    $offset = ($page - 1) * $limit;
    
    // Construire la requête
    $sql = "
        SELECT r.id, r.titre, r.type, r.description, r.nb_vues, r.created_at,
               m.nom as matiere, s.nom as serie,
               ch.titre as chapitre,
               p.statut, p.pourcentage,
               (SELECT COUNT(*) FROM favoris f WHERE f.user_id = ? AND f.ressource_id = r.id) as est_favori
        FROM ressources r
        JOIN matieres m ON m.id = r.matiere_id
        JOIN series s ON s.id = r.serie_id
        LEFT JOIN chapitres ch ON ch.id = r.chapitre_id
        LEFT JOIN progressions p ON p.ressource_id = r.id AND p.user_id = ?
        WHERE r.is_deleted = 0
    ";
    
    $params = [$user_id, $user_id];
    
    if ($matiere_id) {
        $sql .= " AND r.matiere_id = ?";
        $params[] = $matiere_id;
    }
    
    if ($serie_id) {
        $sql .= " AND r.serie_id = ?";
        $params[] = $serie_id;
    }
    
    if ($type) {
        $sql .= " AND r.type = ?";
        $params[] = $type;
    }
    
    if ($search) {
        $sql .= " AND (r.titre LIKE ? OR m.nom LIKE ?)";
        $searchParam = '%' . $search . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    // Compter le total
    $countSql = "SELECT COUNT(*) FROM (" . $sql . ") as subquery";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();
    
    // Récupérer les ressources
    $sql .= " ORDER BY r.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ressources = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'ressources' => $ressources,
            'total' => $total,
            'page' => $page,
            'pages_total' => ceil($total / $limit)
        ]
    ]);
} catch (PDOException $e) {
    error_log('Erreur API ressources: ' . $e->getMessage());
    http_response_code(500);
    die(json_encode(['success' => false, 'error' => 'Erreur serveur']));
}

