<?php
/**
 * Fonctions utilitaires
 */

/**
 * Échapper les données pour éviter XSS
 */
function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Générer un token CSRF
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Vérifier le token CSRF
 */
function verifyCsrfToken(): void {
    // Si pas de token en session, initialiser
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    // Récupérer le token depuis les headers HTTP, POST ou JSON body
    $token = '';
    
    // 1. Essayer depuis $_POST d'abord (formulaires classiques)
    if (isset($_POST['csrf_token'])) {
        $token = $_POST['csrf_token'];
    }
    // 2. Essayer depuis $_SERVER (plus fiable que getallheaders)
    elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
    }
    // 3. Essayer depuis getallheaders() si disponible
    elseif (function_exists('getallheaders')) {
        $headers = getallheaders();
        if ($headers && is_array($headers)) {
            // Chercher dans différentes variations de casse
            foreach ($headers as $key => $value) {
                if (strtolower($key) === 'x-csrf-token') {
                    $token = $value;
                    break;
                }
            }
        }
    }
    // 4. Si Content-Type est application/json, essayer de lire depuis le body JSON
    if (empty($token) && isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        $input = @json_decode(file_get_contents('php://input'), true);
        if ($input && isset($input['csrf_token'])) {
            $token = $input['csrf_token'];
        }
    }
    
    $session_token = $_SESSION['csrf_token'] ?? '';
    
    // Log de débogage (à désactiver en production si nécessaire)
    if (empty($token)) {
        error_log('CSRF Token: Token reçu vide. Headers: ' . json_encode([
            'HTTP_X_CSRF_TOKEN' => $_SERVER['HTTP_X_CSRF_TOKEN'] ?? 'non défini',
            'CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'] ?? 'non défini',
            'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? 'non défini'
        ]));
    }
    
    if (empty($token) || !hash_equals($session_token, $token)) {
        error_log('CSRF Token validation failed. Session token exists: ' . (!empty($session_token) ? 'yes' : 'no') . ', Received token exists: ' . (!empty($token) ? 'yes' : 'no'));
        http_response_code(403);
        die(json_encode(['success' => false, 'error' => 'Token CSRF invalide.']));
    }
}

/**
 * Formater la taille d'un fichier
 */
function formatFileSize(int $bytes): string {
    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / 1024 / 1024, 1) . ' Mo';
    }
    return number_format($bytes / 1024) . ' Ko';
}

/**
 * Formater une date relative (il y a X jours)
 */
function timeAgo(string $datetime): string {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) return 'Il y a ' . $diff . ' secondes';
    if ($diff < 3600) return 'Il y a ' . floor($diff / 60) . ' minutes';
    if ($diff < 86400) return 'Il y a ' . floor($diff / 3600) . ' heures';
    if ($diff < 2592000) return 'Il y a ' . floor($diff / 86400) . ' jours';
    if ($diff < 31536000) return 'Il y a ' . floor($diff / 2592000) . ' mois';
    return 'Il y a ' . floor($diff / 31536000) . ' ans';
}

/**
 * Formater le temps en heures:minutes
 */
function formatDuration(int $seconds): string {
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    if ($h > 0) {
        return sprintf('%dh %02dm', $h, $m);
    }
    return sprintf('%dm', $m);
}

/**
 * Obtenir le chemin de base de l'application
 */
function getBasePath(): string {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $basePath = dirname($scriptName);
    // Normaliser le chemin (enlever les points et doubles slashes)
    $basePath = str_replace('\\', '/', $basePath);
    $basePath = rtrim($basePath, '/');
    // Si on est à la racine (/), retourner vide pour éviter les doubles slashes
    if ($basePath === '.' || $basePath === '' || $basePath === '/') {
        return '';
    }
    return $basePath;
}

