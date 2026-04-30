<?php
/**
 * API Gemini - Assistant Connect'Acadrmia
 * Endpoint pour communiquer avec l'IA Gemini selon le prompt BACY
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
session_start();

header('Content-Type: application/json; charset=utf-8');

// Vérifier l'authentification (uniquement pour les élèves)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Authentification requise']));
}

$user_id = $_SESSION['user_id'];

// Vérifier la méthode HTTP
$request_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($request_method !== 'POST') {
    // Log pour débogage
    error_log('API Gemini: Méthode HTTP incorrecte. Méthode reçue: ' . $request_method . ', Attendu: POST');
    error_log('API Gemini: Headers: ' . json_encode([
        'REQUEST_METHOD' => $request_method,
        'HTTP_X_CSRF_TOKEN' => $_SERVER['HTTP_X_CSRF_TOKEN'] ?? 'non défini',
        'CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'] ?? 'non défini',
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'non défini'
    ]));
    http_response_code(405);
    die(json_encode([
        'success' => false, 
        'error' => 'Méthode non autorisée',
        'debug' => [
            'method_received' => $request_method,
            'method_expected' => 'POST'
        ]
    ]));
}

// Vérifier le token CSRF
verifyCsrfToken();

// Vérifier que la clé API Gemini est configurée
if (empty(GEMINI_API_KEY)) {
    http_response_code(500);
    die(json_encode(['success' => false, 'error' => 'Configuration IA non disponible']));
}

// Rate limiting simple (basé sur la session)
if (!isset($_SESSION['gemini_requests'])) {
    $_SESSION['gemini_requests'] = [];
}
$now = time();
$_SESSION['gemini_requests'] = array_filter($_SESSION['gemini_requests'], function($timestamp) use ($now) {
    return ($now - $timestamp) < 60; // Garder seulement les requêtes de la dernière minute
});

if (count($_SESSION['gemini_requests']) >= GEMINI_RATE_LIMIT) {
    http_response_code(429);
    die(json_encode(['success' => false, 'error' => 'Trop de requêtes. Veuillez patienter un instant.']));
}

// Ajouter cette requête au compteur
$_SESSION['gemini_requests'][] = $now;

try {
    $pdo = getDB();
    
    // Récupérer et valider les données POST
    $raw_input = file_get_contents('php://input');
    $input = null;
    
    if (!empty($raw_input)) {
        $input = json_decode($raw_input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Erreur parsing JSON input: " . json_last_error_msg());
            $input = null;
        }
    }
    
    // Fallback sur $_POST si JSON invalide ou vide
    if (!$input || !is_array($input)) {
        $input = $_POST;
    }
    
    $document_id = intval($input['document_id'] ?? 0);
    $message = trim($input['message'] ?? '');
    
    // Validation
    if ($document_id < 1) {
        http_response_code(400);
        die(json_encode(['success' => false, 'error' => 'ID document invalide']));
    }
    
    if (empty($message) || strlen($message) > 2000) {
        http_response_code(400);
        die(json_encode(['success' => false, 'error' => 'Message invalide (max 2000 caractères)']));
    }
    
    // Récupérer les informations du document avec la série ID
    $stmt = $pdo->prepare("
        SELECT r.id, r.titre, r.type, r.description, r.fichier_path, r.annee, r.serie_id,
               m.nom as matiere, s.nom as serie
        FROM ressources r
        JOIN matieres m ON m.id = r.matiere_id
        JOIN series s ON s.id = r.serie_id
        WHERE r.id = ? AND r.is_deleted = 0
    ");
    $stmt->execute([$document_id]);
    $document = $stmt->fetch();
    
    if (!$document) {
        http_response_code(404);
        die(json_encode(['success' => false, 'error' => 'Document non trouvé']));
    }
    
    // Vérifier que l'élève a accès à ce document (même série)
    $stmt = $pdo->prepare("SELECT serie_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_serie_id = $stmt->fetchColumn();
    
    if ($user_serie_id != $document['serie_id']) {
        http_response_code(403);
        die(json_encode(['success' => false, 'error' => 'Accès non autorisé à ce document']));
    }
    
    // Ne pas extraire le contenu du PDF - utiliser une description basique
    // L'utilisateur veut que le PDF s'affiche tel quel, sans extraction
    $document_content = "Document PDF disponible. L'assistant peut répondre aux questions générales sur le sujet basé sur le titre et les métadonnées du document.";
    
    // Tronquer le contenu si trop long
    if (mb_strlen($document_content) > GEMINI_MAX_DOCUMENT_LENGTH) {
        $document_content = mb_substr($document_content, 0, GEMINI_MAX_DOCUMENT_LENGTH) . '...';
    }
    
    // Construire le prompt système selon BACY_Prompt_Gemini.md
    $document_type_map = [
        'cours' => 'cours',
        'td' => 'travail dirigé (TD)',
        'ancienne_epreuve' => 'annale du baccalauréat'
    ];
    
    $type_doc = $document_type_map[$document['type']] ?? $document['type'];
    $annee = $document['annee'] ? "Session {$document['annee']}" : '';
    
    $system_prompt = buildSystemPrompt($type_doc, $document['matiere'], $document['serie'], $document['titre'], $annee, $document_content);
    
    // Récupérer l'historique de la conversation pour ce document
    $stmt = $pdo->prepare("
        SELECT user_message, ia_response
        FROM ia_conversations
        WHERE user_id = ? AND document_id = ? AND document_type = ?
        ORDER BY created_at ASC
        LIMIT 10
    ");
    $stmt->execute([$user_id, $document_id, $document['type']]);
    $history = $stmt->fetchAll();
    
    // Construire le contenu pour Gemini
    $contents = [];
    
    // Ajouter l'historique
    foreach ($history as $entry) {
        $contents[] = ['role' => 'user', 'parts' => [['text' => $entry['user_message']]]];
        $contents[] = ['role' => 'model', 'parts' => [['text' => $entry['ia_response']]]];
    }
    
    // Ajouter le nouveau message
    $contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];
    
    // Préparer la requête à l'API Gemini
    $gemini_request = [
        'systemInstruction' => [
            'parts' => [
                ['text' => $system_prompt]
            ]
        ],
        'contents' => $contents,
        'generationConfig' => [
            'temperature' => GEMINI_TEMPERATURE,
            'topK' => 40,
            'topP' => 0.95,
            'maxOutputTokens' => GEMINI_MAX_TOKENS
        ],
        'safetySettings' => [
            ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
            ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
            ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
            ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE']
        ]
    ];
    
    // Appel à l'API Gemini (avec retry automatique sur 503)
    $api_url = GEMINI_API_URL . '?key=' . urlencode(GEMINI_API_KEY);
    $request_body = json_encode($gemini_request);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Erreur encodage JSON pour Gemini: " . json_last_error_msg());
        http_response_code(500);
        die(json_encode(['success' => false, 'error' => 'Erreur de préparation de la requête']));
    }

    $max_retries = 3;
    $response = null;
    $http_code = 0;
    $curl_error = '';

    for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
        $ch = curl_init($api_url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $request_body,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3
        ]);

        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        // Succès ou erreur non-récupérable : on sort de la boucle
        if ($http_code === 200 || $curl_error || ($http_code !== 503 && $http_code !== 529)) {
            break;
        }

        // 503/529 = surcharge temporaire Gemini, on attend et on réessaie
        if ($attempt < $max_retries) {
            error_log("Gemini 503 (tentative $attempt/$max_retries), retry dans 2s...");
            sleep(2);
        }
    }

    if ($curl_error) {
        error_log("Erreur cURL Gemini: $curl_error");
        http_response_code(500);
        die(json_encode(['success' => false, 'error' => 'Erreur de connexion à l\'IA']));
    }

    if ($http_code !== 200) {
        $error_details = '';
        if ($response) {
            $error_data = @json_decode($response, true);
            if ($error_data && isset($error_data['error']['message'])) {
                $error_details = $error_data['error']['message'];
            } else {
                $error_details = substr($response, 0, 200);
            }
        }
        error_log("Erreur API Gemini HTTP $http_code: $error_details");

        $user_message = ($http_code === 503 || $http_code === 529)
            ? 'L\'assistant est temporairement surchargé. Veuillez réessayer dans quelques secondes.'
            : 'Erreur de l\'IA. Veuillez réessayer.';

        http_response_code(500);
        die(json_encode(['success' => false, 'error' => $user_message]));
    }
    
    if (empty($response)) {
        error_log("Réponse Gemini vide");
        http_response_code(500);
        die(json_encode(['success' => false, 'error' => 'Réponse vide de l\'IA']));
    }
    
    $gemini_data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Erreur décodage JSON Gemini: " . json_last_error_msg() . " - Réponse: " . substr($response, 0, 500));
        http_response_code(500);
        die(json_encode(['success' => false, 'error' => 'Réponse invalide de l\'IA']));
    }
    
    if (!isset($gemini_data['candidates']) || !is_array($gemini_data['candidates']) || empty($gemini_data['candidates'])) {
        error_log("Réponse Gemini sans candidates: " . substr($response, 0, 500));
        http_response_code(500);
        die(json_encode(['success' => false, 'error' => 'Réponse invalide de l\'IA']));
    }
    
    if (!isset($gemini_data['candidates'][0]['content']['parts'][0]['text'])) {
        $error_info = isset($gemini_data['candidates'][0]['finishReason']) ? $gemini_data['candidates'][0]['finishReason'] : 'unknown';
        error_log("Réponse Gemini invalide - finishReason: $error_info - Réponse: " . substr($response, 0, 500));
        http_response_code(500);
        die(json_encode(['success' => false, 'error' => 'Réponse invalide de l\'IA']));
    }
    
    $ia_response = $gemini_data['candidates'][0]['content']['parts'][0]['text'];
    
    // Sauvegarder la conversation dans la base de données
    $stmt = $pdo->prepare("
        INSERT INTO ia_conversations (user_id, document_id, document_type, user_message, ia_response)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $user_id,
        $document_id,
        $document['type'],
        $message,
        $ia_response
    ]);
    
    // Retourner la réponse
    echo json_encode([
        'success' => true,
        'response' => $ia_response
    ]);
    
} catch (PDOException $e) {
    error_log('Erreur BDD API Gemini: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    http_response_code(500);
    die(json_encode(['success' => false, 'error' => 'Erreur de base de données. Veuillez réessayer.']));
} catch (Exception $e) {
    error_log('Erreur API Gemini: ' . $e->getMessage());
    error_log('Fichier: ' . $e->getFile() . ' Ligne: ' . $e->getLine());
    error_log('Stack trace: ' . $e->getTraceAsString());
    http_response_code(500);
    die(json_encode(['success' => false, 'error' => 'Erreur de l\'IA. Veuillez réessayer.']));
}

/**
 * Construire le prompt système selon BACY_Prompt_Gemini.md
 */
function buildSystemPrompt($type, $matiere, $serie, $titre, $annee, $contenu): string {
    $prompt_file = __DIR__ . '/../BACY_Prompt_Gemini.md';
    
    if (!file_exists($prompt_file)) {
        error_log("Fichier prompt non trouvé: $prompt_file");
        // Retourner un prompt par défaut si le fichier n'existe pas
        return "Tu es Assistant Connect'Acadrmia, un assistant pédagogique pour les élèves gabonais de Terminale. 
Type de document: $type
Matière: $matiere
Série: $serie
Titre: $titre
Année: $annee

Contenu: $contenu

Réponds aux questions de l'élève de manière pédagogique et bienveillante.";
    }
    
    $prompt_template = @file_get_contents($prompt_file);
    
    if ($prompt_template === false) {
        error_log("Impossible de lire le fichier prompt: $prompt_file");
        // Retourner un prompt par défaut
        return "Tu es Assistant Connect'Acadrmia, un assistant pédagogique pour les élèves gabonais de Terminale. 
Type de document: $type
Matière: $matiere
Série: $serie
Titre: $titre
Année: $annee

Contenu: $contenu

Réponds aux questions de l'élève de manière pédagogique et bienveillante.";
    }
    
    // Remplacer les variables
    $prompt = str_replace('{{TYPE}}', $type, $prompt_template);
    $prompt = str_replace('{{MATIERE}}', $matiere, $prompt);
    $prompt = str_replace('{{SERIE}}', $serie, $prompt);
    $prompt = str_replace('{{TITRE}}', $titre, $prompt);
    $prompt = str_replace('{{ANNEE}}', $annee, $prompt);
    $prompt = str_replace('{{CONTENU_DOCUMENT}}', $contenu, $prompt);
    
    return $prompt;
}

/**
 * Extraire le texte d'un PDF
 * Note: Cette fonction nécessite une bibliothèque PHP pour extraire le texte des PDFs
 * Pour l'instant, on retourne une description basique si l'extraction échoue
 */
function extractPdfContent($fichier_path): string {
    $full_path = __DIR__ . '/../' . $fichier_path;
    
    if (!file_exists($full_path)) {
        return "Document PDF disponible mais contenu non extrait.";
    }
    
    // Essayer d'utiliser smalot/pdfparser si disponible
    if (class_exists('\Smalot\PdfParser\Parser')) {
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($full_path);
            $text = $pdf->getText();
            return $text ?: "Contenu du PDF non extractible.";
        } catch (Exception $e) {
            error_log("Erreur extraction PDF: " . $e->getMessage());
        }
    }
    
    // Fallback: utiliser pdftotext si disponible (commande système)
    // Vérifier si pdftotext est disponible
    $pdftotext_path = '';
    if (function_exists('shell_exec')) {
        // Essayer différentes commandes selon l'OS
        $commands = ['pdftotext', '/usr/bin/pdftotext', '/usr/local/bin/pdftotext'];
        foreach ($commands as $cmd) {
            $which = @shell_exec("which $cmd 2>/dev/null");
            if (!empty($which)) {
                $pdftotext_path = trim($which);
                break;
            }
        }
    }
    
    if (!empty($pdftotext_path)) {
        $escaped_path = escapeshellarg($full_path);
        $output = @shell_exec("$pdftotext_path $escaped_path - 2>/dev/null");
        if ($output && strlen(trim($output)) > 50) { // Au moins 50 caractères pour être valide
            return trim($output);
        }
    }
    
    // Dernier recours: retourner une description basique
    return "Document PDF disponible. Le contenu textuel n'a pas pu être extrait automatiquement. L'assistant peut répondre aux questions générales sur le sujet.";
}

