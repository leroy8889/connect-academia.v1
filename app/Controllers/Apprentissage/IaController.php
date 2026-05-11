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
    // 5 Mo raw → ~6.7 Mo base64 → marge confortable pour Gemini (limite 20 Mo/requête)
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
                $hasImage = true;
            } else {
                Response::json(['success' => false, 'error' => ['message' => "Format d'image invalide ou trop volumineux (max 5 Mo)."]], 400);
            }
        }

        if ($ressourceId < 1 || (empty($message) && !$hasImage) || mb_strlen($message) > 2000) {
            Response::json(['success' => false, 'error' => ['message' => 'Données invalides']], 400);
        }

        $iaModel = new IaConversation();

        // Limite quotidienne : 20 requêtes par jour calendaire
        if ($iaModel->countToday($userId) >= self::DAILY_LIMIT) {
            Response::json(['success' => false, 'error' => ['message' => 'Limite quotidienne atteinte. Revenez demain pour de nouvelles questions !']], 429);
        }

        $apiKey = $_ENV['GEMINI_API_KEY'] ?? '';
        if (empty($apiKey)) {
            Response::json(['success' => false, 'error' => ['message' => 'Assistant IA non configuré']], 503);
        }

        $ressource = (new Ressource())->findWithProgression($ressourceId, $userId);
        if (!$ressource) {
            Response::json(['success' => false, 'error' => ['message' => 'Ressource introuvable']], 404);
        }

        // Vérifier que l'élève a accès à cette série
        $userSerieId = (int) Session::get('user_serie_id');
        if ($userSerieId && (int) ($ressource['serie_id'] ?? 0) !== $userSerieId) {
            Response::json(['success' => false, 'error' => ['message' => 'Accès non autorisé']], 403);
        }

        // Historique : si conversation_id fourni → contexte de cette conversation seulement
        // Sinon → historique global (rétrocompat) + génération d'un nouvel ID
        if ($conversationId !== null) {
            $historique = $iaModel->getHistorique($userId, $ressourceId, 10, $conversationId);
        } else {
            $historique     = $iaModel->getHistorique($userId, $ressourceId, 10);
            $conversationId = IaConversation::generateId();
        }

        // Charger le PDF en mémoire (cache Redis) pour injection inline dans Gemini
        $docInlineData = $this->getDocumentInlineData($ressource);
        $systemPrompt  = $this->buildSystemPrompt($ressource, $docInlineData !== null);

        $contents = [];

        // Préfixer la conversation avec le document complet (Gemini le "voit" comme un humain)
        if ($docInlineData !== null) {
            $contents[] = [
                'role'  => 'user',
                'parts' => [
                    ['inlineData' => $docInlineData],
                    ['text'       => "Voici le document que l'élève étudie actuellement. Utilise son contenu intégralement comme référence principale pour répondre à toutes ses questions."],
                ],
            ];
            $contents[] = [
                'role'  => 'model',
                'parts' => [['text' => "Document reçu et analysé intégralement. Je suis prêt à répondre aux questions de l'élève en me basant sur l'ensemble de son contenu."]],
            ];
        }

        foreach ($historique as $entry) {
            $contents[] = ['role' => 'user',  'parts' => [['text' => $entry['user_message']]]];
            $contents[] = ['role' => 'model', 'parts' => [['text' => $entry['ia_response']]]];
        }

        // Construire les parts du message utilisateur (texte + image optionnelle)
        $userParts = [];
        if ($hasImage) {
            $userParts[] = ['inlineData' => ['mimeType' => $imageMime, 'data' => $imageData]];
        }
        $displayMessage = $message !== '' ? $message : 'Analyse cette image et décris son contenu en détail.';
        $userParts[] = ['text' => $displayMessage];
        $contents[]  = ['role' => 'user', 'parts' => $userParts];

        $payload = [
            'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents'          => $contents,
            'generationConfig'  => [
                'temperature' => 0.7, 'topK' => 40, 'topP' => 0.95, 'maxOutputTokens' => 2048,
            ],
            'safetySettings' => [
                ['category' => 'HARM_CATEGORY_HARASSMENT',        'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
                ['category' => 'HARM_CATEGORY_HATE_SPEECH',       'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
                ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
                ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
            ],
        ];

        $apiUrl   = ($_ENV['GEMINI_API_URL'] ?? 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent') . '?key=' . urlencode($apiKey);
        $body     = json_encode($payload);
        $response = null;
        $httpCode = 0;

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT        => 60,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($curlErr || ($httpCode !== 503 && $httpCode !== 529)) break;
            if ($attempt < 3) sleep(2);
        }

        if ($httpCode !== 200) {
            $errMsg = ($httpCode === 503 || $httpCode === 529)
                ? "L'assistant est temporairement surchargé. Réessayez dans quelques secondes."
                : "Erreur de l'IA. Veuillez réessayer.";
            error_log("Gemini HTTP {$httpCode}: " . substr((string) $response, 0, 300));
            Response::json(['success' => false, 'error' => ['message' => $errMsg]], 500);
        }

        $data = json_decode((string) $response, true);
        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            error_log("Réponse Gemini invalide: " . substr((string) $response, 0, 300));
            Response::json(['success' => false, 'error' => ['message' => "Réponse invalide de l'IA"]], 500);
        }

        $iaResponse   = $data['candidates'][0]['content']['parts'][0]['text'];
        $savedMessage = $hasImage ? '[📷 Image] ' . $displayMessage : $displayMessage;
        $iaModel->save($userId, $ressourceId, $savedMessage, $iaResponse, $conversationId);

        $usedToday = $iaModel->countToday($userId);
        $remaining = max(0, self::DAILY_LIMIT - $usedToday);

        Response::json([
            'success'            => true,
            'response'           => $iaResponse,
            'remaining_requests' => $remaining,
            'conversation_id'    => $conversationId,
        ]);
    }

    public function historique(string $id): void
    {
        $userId      = Session::userId();
        $ressourceId = (int) $id;
        $iaModel     = new IaConversation();

        $conversationId = $iaModel->getCurrentConversationId($userId, $ressourceId);

        // Si conversation_id trouvé → charger uniquement cette conversation
        // Sinon → rétrocompat : charger tout l'historique (anciens enregistrements sans ID)
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

    private function buildSystemPrompt(array $ressource, bool $hasInlinePdf = false): string
    {
        $types = ['cours' => 'cours', 'td' => 'travail dirigé (TD)', 'ancienne_epreuve' => 'annale du baccalauréat'];
        $type  = $types[$ressource['type']] ?? $ressource['type'];
        $annee = !empty($ressource['annee']) ? "Session {$ressource['annee']}" : '';

        $contenuNote = $hasInlinePdf
            ? '(Le document complet est fourni en pièce jointe dans la conversation — utilise-le comme référence principale et absolue pour toutes tes réponses. Tu peux voir chaque page, chaque formule, chaque schéma.)'
            : '';

        $promptFile = BASE_PATH . '/docs/AssistantConnect.md';
        if (file_exists($promptFile)) {
            $tpl = file_get_contents($promptFile);
            $tpl = str_replace(
                ['{{TYPE}}', '{{MATIERE}}', '{{SERIE}}', '{{TITRE}}', '{{ANNEE}}', '{{CONTENU_DOCUMENT}}'],
                [$type, $ressource['matiere'], $ressource['serie'], $ressource['titre'], $annee, $contenuNote],
                $tpl
            );
            return $tpl;
        }

        $pdfNote = $hasInlinePdf ? "\nLe document complet est fourni en pièce jointe — base-toi sur son contenu intégral." : '';
        return "Tu es BACY, l'assistant pédagogique de Connect'Academia pour les élèves gabonais de Terminale.
Document : {$ressource['titre']} ({$type})
Matière : {$ressource['matiere']} — Série : {$ressource['serie']} {$annee}{$pdfNote}
Réponds aux questions de l'élève de manière pédagogique, bienveillante et adaptée au programme gabonais.";
    }

    /**
     * Lit le fichier PDF de la ressource, l'encode en base64 et le met en cache Redis.
     * Retourne null si : non-PDF, fichier manquant, trop volumineux (>15 Mo) ou erreur lecture.
     *
     * @return array{mimeType: string, data: string}|null
     */
    private function getDocumentInlineData(array $ressource): ?array
    {
        $path = trim($ressource['fichier_path'] ?? '');
        if (empty($path) || !preg_match('#\.pdf$#i', $path)) {
            return null;
        }

        $absPath = BASE_PATH . '/public/' . ltrim($path, '/');
        if (!file_exists($absPath)) {
            return null;
        }

        $fileSize = filesize($absPath);
        // Limite Gemini inline data ~20 Mo raw ; on reste à 15 Mo avec marge
        if ($fileSize === false || $fileSize > 15_000_000) {
            return null;
        }

        $cacheKey = 'pdf_inline_' . (int) ($ressource['id']);
        $cached   = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $raw = @file_get_contents($absPath);
        if ($raw === false) {
            return null;
        }

        $result = [
            'mimeType' => 'application/pdf',
            'data'     => base64_encode($raw),
        ];

        // Cache 2 h — un PDF ne change pas souvent
        Cache::set($cacheKey, $result, 7200);

        return $result;
    }
}
