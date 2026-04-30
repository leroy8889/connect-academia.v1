<?php
/**
 * API PDF - Servir les fichiers PDF de manière sécurisée
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
session_start();

// Vérifier l'authentification (élèves ou admins)
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    http_response_code(401);
    die('Accès refusé');
}

$ressource_id = intval($_GET['id'] ?? 0);

if ($ressource_id < 1) {
    http_response_code(400);
    die('ID ressource invalide');
}

try {
    $pdo = getDB();
    
    // Récupérer la ressource et vérifier les droits d'accès
    $stmt = $pdo->prepare("
        SELECT r.id, r.titre, r.fichier_path, r.fichier_nom, r.taille_fichier
        FROM ressources r
        WHERE r.id = ? AND r.is_deleted = 0
    ");
    $stmt->execute([$ressource_id]);
    $ressource = $stmt->fetch();
    
    if (!$ressource) {
        http_response_code(404);
        die('Ressource non trouvée');
    }
    
    // Construire le chemin complet du fichier
    $fichier_path = __DIR__ . '/../' . $ressource['fichier_path'];
    
    // Sécurité : vérifier que le chemin ne sort pas du répertoire uploads
    $realPath = realpath($fichier_path);
    $uploadsPath = realpath(__DIR__ . '/../uploads/ressources/');
    
    if (!$realPath || strpos($realPath, $uploadsPath) !== 0) {
        http_response_code(403);
        die('Accès non autorisé');
    }
    
    // Vérifier que le fichier existe
    if (!file_exists($realPath) || !is_readable($realPath)) {
        http_response_code(404);
        die('Fichier non trouvé');
    }
    
    // Incrémenter le compteur de vues
    $pdo->prepare("UPDATE ressources SET nb_vues = nb_vues + 1 WHERE id = ?")->execute([$ressource_id]);
    
    // Déterminer le nom du fichier pour le téléchargement
    $nom_fichier = $ressource['fichier_nom'] ?: basename($realPath);
    
    // En-têtes HTTP pour servir le PDF (compatible PDF.js)
    header('Content-Type: application/pdf');
    header('Content-Length: ' . filesize($realPath));
    header('Content-Disposition: inline; filename="' . addslashes($nom_fichier) . '"');
    header('Accept-Ranges: bytes');
    header('Cache-Control: private, max-age=3600');
    header('Pragma: cache');
    
    // Support du Range request pour PDF.js (streaming)
    $fileSize = filesize($realPath);
    $range = $_SERVER['HTTP_RANGE'] ?? null;
    
    if ($range && preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
        // Extraire la plage demandée
        $start = intval($matches[1]);
        $end = isset($matches[2]) && $matches[2] !== '' ? intval($matches[2]) : $fileSize - 1;
        
        // Valider la plage
        if ($start < 0 || $start >= $fileSize || $end < $start || $end >= $fileSize) {
            http_response_code(416);
            header('Content-Range: bytes */' . $fileSize);
            exit;
        }
        
        $length = $end - $start + 1;
        http_response_code(206);
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
        header('Content-Length: ' . $length);
        
        $fp = fopen($realPath, 'rb');
        if ($fp) {
            fseek($fp, $start);
            $remaining = $length;
            $chunkSize = 8192; // 8KB chunks
            
            while ($remaining > 0 && !feof($fp)) {
                $read = min($remaining, $chunkSize);
                echo fread($fp, $read);
                $remaining -= $read;
            }
            fclose($fp);
        }
    } else {
        // Servir le fichier complet
        header('Content-Length: ' . $fileSize);
        readfile($realPath);
    }
    exit;
    
} catch (Exception $e) {
    error_log('Erreur API PDF: ' . $e->getMessage());
    http_response_code(500);
    die('Erreur serveur');
}

