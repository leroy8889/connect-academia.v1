<?php
declare(strict_types=1);

namespace Core;

class Response
{
    public static function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function redirect(string $url, int $statusCode = 302): void
    {
        // Ajouter le préfixe BASE_URL pour les chemins relatifs internes
        if (defined('BASE_URL') && BASE_URL !== '' && strpos($url, '/') === 0 && strpos($url, '//') !== 0) {
            $url = BASE_URL . $url;
        }
        http_response_code($statusCode);
        header("Location: {$url}");
        exit;
    }

    public static function view(string $viewPath, array $data = [], string $layout = 'main'): void
    {
        // Rendre les données accessibles dans la vue
        extract($data);

        // Capturer le contenu de la vue
        ob_start();
        $viewFile = BASE_PATH . '/app/Views/' . $viewPath . '.php';
        if (!file_exists($viewFile)) {
            throw new \RuntimeException("Vue introuvable : {$viewFile}");
        }
        require $viewFile;
        $content = ob_get_clean();

        // Si un layout est défini, l'envelopper
        if ($layout) {
            $layoutFile = BASE_PATH . '/app/Views/layouts/' . $layout . '.php';
            if (!file_exists($layoutFile)) {
                throw new \RuntimeException("Layout introuvable : {$layoutFile}");
            }
            require $layoutFile;
        } else {
            echo $content;
        }
    }
}

