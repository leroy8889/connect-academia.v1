<?php
declare(strict_types=1);

namespace Controllers\Apprentissage;

use Core\{Response, Session, Cache};
use Models\{Ressource, IaConversation};

class IaController
{
    private const DAILY_LIMIT = 20;

    private const ALLOWED_IMAGE_MIMES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'image/heic', 'image/heif', 'image/bmp', 'image/tiff',
    ];
    // 5 Mo raw → ~6.7 Mo base64
    private const MAX_IMAGE_B64_LEN = 7_340_032;

    public function question(): void
    {
        $userId = Session::userId();
        $input  = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $ressourceId    = (int) ($input['document_id'] ?? $input['ressource_id'] ?? 0);
        $message        = trim($input['message'] ?? '');
        $imageData      = (string) ($input['image_data'] ?? '');
        $imageMime      = (string) ($input['image_mime'] ?? '');
        $conversationId = trim($input['conversation_id'] ?? '') ?: null;

        // Valider l'image si présente
        $hasImage = false;
        if ($imageData !== '' && $imageMime !== '') {
            if (
                in_array($imageMime, self::ALLOWED_IMAGE_MIMES, true)
                && strlen($imageData) <= self::MAX_IMAGE_B64_LEN
                && base64_decode($imageData, true) !== false
            ) {
                // Si l'image contient l'en-tête data:image/xxx;base64, on l'extrait
                if (strpos($imageData, ',') !== false) {
                    $imageData = explode(',', $imageData)[1];
                }
                $hasImage = true;
            } else {
                Response::json(['success' => false, 'error' => ['message' => "Format d'image invalide ou trop volumineux (max 5 Mo)."]], 400);
                return;
            }
        }

        if ($ressourceId < 1 || (empty($message) && !$hasImage) || mb_strlen($message) > 2000) {
            Response::json(['success' => false, 'error' => ['message' => 'Données invalides']], 400);
            return;
        }

        $iaModel = new IaConversation();

        // Limite quotidienne : 20 requêtes par jour calendaire
        if ($iaModel->countToday($userId) >= self::DAILY_LIMIT) {
            Response::json(['success' => false, 'error' => ['message' => 'Limite quotidienne atteinte. Revenez demain pour de nouvelles questions !']], 429);
            return;
        }

        $ressource = (new Ressource())->findWithProgression($ressourceId, $userId);
        if (!$ressource) {
            Response::json(['success' => false, 'error' => ['message' => 'Ressource introuvable']], 404);
            return;
        }

        // Vérifier que l'élève a accès à cette série
        $userSerieId = (int) Session::get('user_serie_id');
        if ($userSerieId && (int) ($ressource['serie_id'] ?? 0) !== $userSerieId) {
            Response::json(['success' => false, 'error' => ['message' => 'Accès non autorisé']], 403);
            return;
        }

        // Historique de la conversation
        if ($conversationId !== null) {
            $historique = $iaModel->getHistorique($userId, $ressourceId, 10, $conversationId);
        } else {
            $historique     = $iaModel->getHistorique($userId, $ressourceId, 10);
            $conversationId = IaConversation::generateId();
        }

        // 1. Générer le prompt système de base
        $type = $ressource['type'] ?? 'cours';
        $matiere = $ressource['matiere_nom'] ?? 'Général';
        $serie = $ressource['serie_nom'] ?? 'Toutes Séries';
        $titre = $ressource['titre'] ?? 'Document d\'étude';
        $annee = $ressource['annee'] ?? '';

        $systemPrompt = "🎯 IDENTITÉ DE L'ASSISTANT
Tu es Assistant Connect'Academia, un assistant pédagogique intelligent intégré dans la plateforme Connect'Academia — la première plateforme d'entraide scolaire dédiée aux élèves gabonais de Terminale.
Tu es un enseignant expérimenté du système éducatif gabonais, passionné par la réussite des élèves. Tu connais parfaitement les programmes officiels de la Direction Générale des Examens et Concours (DGEC) du Gabon et les attentes de l'Office du Baccalauréat du Gabon (OBG).

🖼️ CAPACITÉ D'ANALYSE D'IMAGES
Tu as la capacité complète de voir et d'analyser les images envoyées par les élèves (exercices, schémas, copies). Si l'élève envoie une image sans message texte, décris son contenu scolaire et pose-lui une question pour l'orienter. Ne dis JAMAIS que tu ne peux pas voir les images.

📚 CONTEXTE DU DOCUMENT ACTIF
L'élève consulte actuellement le document suivant. Toutes tes réponses doivent être fondées en priorité sur ce contenu.
- TYPE DE DOCUMENT : {$type}
- MATIÈRE          : {$matiere}
- SÉRIE            : {$serie}
- TITRE            : {$titre}\n";
        if (!empty($annee)) { $systemPrompt .= "- ANNÉE            : {$annee}\n"; }

        $systemPrompt .= "
🇬🇦 CONTEXTE ÉDUCATIF GABONAIS
- Les programmes suivent le référentiel de la DGEC (Séries A1, A2, B, C, D).
- Quand tu illustres un concept avec un exemple concret, ancre-le dans la réalité gabonaise ou africaine (Libreville, Port-Gentil, Ogooué, secteur pétrolier, manganèse, culture locale, etc.) autant que possible.

📐 RÈGLES PÉDAGOGIQUES STRICTES
- Règle 1 (Priorité) : Fonde toujours ta réponse sur le contenu du document actif en priorité. Si la question est totalement hors sujet (non scolaire), redirige poliment l'élève vers ses révisions.
- Règle 2 (Séries) : Scientifiques (C, D) -> Formules claires, étapes numérotées, exemples numériques. Littéraires (A1, A2) -> Structure claire, méthodologie (ne fais pas le devoir à leur place). Série B -> Notions économiques de la sous-région (CEMAC, développement).
- Règle 3 (Structure) : 1. Reformulation, 2. Réponse simple, 3. Développement, 4. Exemple gabonais, 5. Vérification (question de relance).
- Règle 4 (Ton) : Toujours en français, chaleureux, bienveillant (ex: 'Très bonne question !'). Emojis autorisés : ✅, ⚠️, 💡, 📌, 🎯.
- Règle 5 (Interdits) : Pas de réponse brute sans raisonnement, pas de politique/sujets privés, pas de dénigrement du système éducatif.
- Règle 6 (Format) : Listes à puces ou numérotées, max 1200 mots, pas de code informatique sauf si cours d'informatique.";

// 2. Récupération du contenu textuel de sécurité
        $texteDocument = "";
        if (!empty($ressource['contenu'])) {
            $texteDocument = $ressource['contenu'];
        } elseif (!empty($ressource['description'])) {
            $texteDocument = $ressource['description'];
        } elseif (!empty($ressource['titre'])) {
            $texteDocument = "Sujet : " . $ressource['titre'];
        }

        if (!empty($texteDocument)) {
            $systemPrompt .= "\n\n--- CONTENU DU DOCUMENT ---\n" . $texteDocument . "\n--- FIN ---";
        }

// 3. Construction du Payload pour l'API Google Gemini officiel
        $googleContents = [];        foreach ($historique as $entry) {
            if (!empty(trim($entry['user_message']))) {
                $googleContents[] = ['role' => 'user', 'parts' => [['text' => $entry['user_message']]]];
            }
            if (!empty(trim($entry['ia_response']))) {
                $googleContents[] = ['role' => 'model', 'parts' => [['text' => $entry['ia_response']]]];
            }
        }

        $displayMessage = $message !== '' ? $message : 'Analyse cette image et décris son contenu en détail.';

        if ($hasImage) {
            $googleContents[] = [
                'role' => 'user',
                'parts' => [
                    ['text' => $displayMessage],
                    [
                        'inlineData' => [
                            'mimeType' => $imageMime,
                            'data' => $imageData
                        ]
                    ]
                ]
            ];
        } else {
            $googleContents[] = ['role' => 'user', 'parts' => [['text' => $displayMessage]]];
        }

// On injecte le prompt système sous forme de premier message pour l'API v1
        $finalContents = [];
        $finalContents[] = [
            'role' => 'user',
            'parts' => [['text' => "Consignes système à respecter : " . $systemPrompt]]
        ];
        $finalContents[] = [
            'role' => 'model',
            'parts' => [['text' => "Compris. Je vais suivre ces consignes pour aider l'élève."]]
        ];

        // On ajoute l'historique et le message actuel
        foreach ($googleContents as $content) {
            $finalContents[] = $content;
        }

        $payload = [
            'contents' => $finalContents,
            'generationConfig' => [
                'maxOutputTokens' => 8192,
                'temperature' => 0.7
            ]
        ];

       
// Configuration de la clé API Google
        $apiKey = $_ENV['GEMINI_API_KEY'] ?? env('GEMINI_API_KEY') ?? getenv('GEMINI_API_KEY');
        $apiUrl = 'https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent?key=' . $apiKey;
        $body   = json_encode($payload);
        $response = null;
        $httpCode = 0;

        try {
            for ($attempt = 1; $attempt <= 3; $attempt++) {
                $ch = curl_init($apiUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => $body,
                    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                    CURLOPT_TIMEOUT        => 60,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4 // Force IPv4 pour éviter les blocages de plages IPv6 par Google
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlErr  = curl_error($ch);
                curl_close($ch);
                
                if (!$curlErr && $httpCode === 200) break;
                if ($attempt < 3) sleep(2);
             }

// Si Google renvoie une erreur de Quota (Code 429)
        if ($httpCode === 429) {
            Response::json([
                'success' => true, 
                'response' => "⚠️ **Limite quotidienne atteinte**\n\nBonjour ! L'assistant a atteint son nombre maximal de questions autorisées pour aujourd'hui.\n\nPas d'inquiétude, le quota sera entièrement réinitialisé. Reviens demain pour continuer tes révisions et décortiquer tes exercices ! Courage ! 📚✏️"
            ], 200);
            return;
        }

        // Pour les autres erreurs (500, etc.)
        if ($httpCode !== 200) {
            error_log("Google Gemini API HTTP {$httpCode}: " . (string) $response);
            Response::json([
                'success' => false,
                'error' => ['message' => "L'assistant rencontre une petite perturbation technique. Réessaye dans quelques instants."]
            ], 200);
            return;
        }

            $data = json_decode((string) $response, true);
            if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                error_log("Réponse Google API invalide: " . (string) $response);
                Response::json([
                    'success' => false,
                    'error' => ['message' => "Une erreur de communication est survenue avec l'assistant."]
                ], 200);
                return;
            }

            $iaResponse = $data['candidates'][0]['content']['parts'][0]['text'];

            // Sauvegarde BDD
            $savedMessage = $hasImage ? '[📷 Image] ' . $displayMessage : $displayMessage;
            $iaModel->save($userId, $ressourceId, $savedMessage, $iaResponse, $conversationId);

            $usedToday = $iaModel->countToday($userId);
            $remaining = max(0, self::DAILY_LIMIT - $usedToday);

            if (ob_get_length()) { ob_clean(); }

            Response::json([
                'success'            => true,
                'response'           => $iaResponse,
                'data'               => [
                    'response'        => $iaResponse,
                    'conversation_id' => $conversationId
                ],
                'remaining_requests' => $remaining,
                'conversation_id'    => $conversationId,
            ]);

        } catch (\Throwable $e) {
            error_log("Exception fatale IaController: " . $e->getMessage());
            Response::json([
                'success' => false,
                'error' => ['message' => "Une erreur critique est survenue."]
            ], 200);
        }
    }

    // Le reste des fonctions (historique, nouvelleConversation, etc.) reste identique...
    public function historique(string $id): void
    {
        $userId      = Session::userId();
        $ressourceId = (int) $id;
        $iaModel     = new IaConversation();
        $conversationId = $iaModel->getCurrentConversationId($userId, $ressourceId);

        if ($conversationId !== null) {
            $historique = $iaModel->getHistorique($userId, $ressourceId, 20, $conversationId);
        } else {
            $historique = $iaModel->getHistorique($userId, $ressourceId, 20);
        }

        Response::json(['success' => true, 'data' => [
            'historique'      => $historique,
            'conversation_id' => $conversationId,
        ]]);
    }

    public function nouvelleConversation(): void
    {
        $input       = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $ressourceId = (int) ($input['document_id'] ?? $input['ressource_id'] ?? 0);

        if ($ressourceId < 1) {
            Response::json(['success' => false, 'error' => ['message' => 'Données invalides']], 400);
            return;
        }
        Response::json(['success' => true, 'conversation_id' => IaConversation::generateId()]);
    }

    private function getDocumentInlineData(array $ressource): ?array
    {
        $path = trim($ressource['fichier_path'] ?? '');
        if (empty($path) || !preg_match('#\.pdf$#i', $path)) { return null; }
        $candidate   = BASE_PATH . '/public/' . ltrim($path, '/');
        $absPath     = realpath($candidate);
        $allowedBase = realpath(BASE_PATH . '/public/');
        if (!$absPath || !$allowedBase || !str_starts_with($absPath, $allowedBase . DIRECTORY_SEPARATOR)) { return null; }
        if (!file_exists($absPath)) { return null; }

        $fileSize = filesize($absPath);
        if ($fileSize === false || $fileSize > 15_000_000) { return null; }

        $cacheKey = 'pdf_inline_' . (int) ($ressource['id']);
        $cached   = Cache::get($cacheKey);
        if ($cached !== null) { return $cached; }

        $raw = @file_get_contents($absPath);
        if ($raw === false) { return null; }

        $result = [
            'mimeType' => 'application/pdf',
            'data'     => base64_encode($raw),
        ];
        Cache::set($cacheKey, $result, 7200);
        return $result;
    }
}
