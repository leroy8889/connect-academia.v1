<?php
declare(strict_types=1);

namespace Controllers\Apprentissage;

use Core\{Response, Session};
use Models\{Ressource, IaConversation};

class IaController
{
    private const RATE_LIMIT = 10;

    public function question(): void
    {
        $userId = Session::userId();
        $input  = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $ressourceId = (int) ($input['document_id'] ?? $input['ressource_id'] ?? 0);
        $message     = trim($input['message'] ?? '');

        if ($ressourceId < 1 || empty($message) || mb_strlen($message) > 2000) {
            Response::json(['success' => false, 'error' => ['message' => 'Données invalides']], 400);
        }

        $iaModel = new IaConversation();

        // Rate limiting via BDD
        if ($iaModel->countRecent($userId, 1) >= self::RATE_LIMIT) {
            Response::json(['success' => false, 'error' => ['message' => 'Trop de requêtes. Patientez un instant.']], 429);
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

        $historique = $iaModel->getHistorique($userId, $ressourceId, 10);
        $systemPrompt = $this->buildSystemPrompt($ressource);

        $contents = [];
        foreach ($historique as $entry) {
            $contents[] = ['role' => 'user',  'parts' => [['text' => $entry['user_message']]]];
            $contents[] = ['role' => 'model', 'parts' => [['text' => $entry['ia_response']]]];
        }
        $contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];

        $payload = [
            'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents'          => $contents,
            'generationConfig'  => [
                'temperature' => 0.7, 'topK' => 40, 'topP' => 0.95, 'maxOutputTokens' => 1024,
            ],
            'safetySettings' => [
                ['category' => 'HARM_CATEGORY_HARASSMENT',        'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
                ['category' => 'HARM_CATEGORY_HATE_SPEECH',       'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
                ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
                ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
            ],
        ];

        $apiUrl  = ($_ENV['GEMINI_API_URL'] ?? 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent') . '?key=' . urlencode($apiKey);
        $body    = json_encode($payload);
        $response = null;
        $httpCode = 0;

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT        => 30,
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

        $iaResponse = $data['candidates'][0]['content']['parts'][0]['text'];
        $iaModel->save($userId, $ressourceId, $message, $iaResponse);

        Response::json(['success' => true, 'response' => $iaResponse]);
    }

    public function historique(string $id): void
    {
        $userId      = Session::userId();
        $ressourceId = (int) $id;

        $historique = (new IaConversation())->getHistorique($userId, $ressourceId, 20);
        Response::json(['success' => true, 'data' => ['historique' => $historique]]);
    }

    private function buildSystemPrompt(array $ressource): string
    {
        $types = ['cours' => 'cours', 'td' => 'travail dirigé (TD)', 'ancienne_epreuve' => 'annale du baccalauréat'];
        $type  = $types[$ressource['type']] ?? $ressource['type'];
        $annee = !empty($ressource['annee']) ? "Session {$ressource['annee']}" : '';

        $promptFile = BASE_PATH . '/public/Apprentissage 2/BACY_Prompt_Gemini.md';
        if (file_exists($promptFile)) {
            $tpl = file_get_contents($promptFile);
            $tpl = str_replace(['{{TYPE}}', '{{MATIERE}}', '{{SERIE}}', '{{TITRE}}', '{{ANNEE}}', '{{CONTENU_DOCUMENT}}'],
                               [$type, $ressource['matiere'], $ressource['serie'], $ressource['titre'], $annee, ''],
                               $tpl);
            return $tpl;
        }

        return "Tu es BACY, l'assistant pédagogique de Connect'Academia pour les élèves gabonais de Terminale.
Document : {$ressource['titre']} ({$type})
Matière : {$ressource['matiere']} — Série : {$ressource['serie']} {$annee}
Réponds aux questions de l'élève de manière pédagogique, bienveillante et adaptée au programme gabonais.";
    }
}
