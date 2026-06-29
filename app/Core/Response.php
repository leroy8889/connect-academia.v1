<?php
declare(strict_types=1);

namespace Core;

class Response
{
    public static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function redirect(string $path, int $status = 302): void
    {
        // Ajouter BASE_URL pour les chemins internes
        if (defined('BASE_URL') && BASE_URL !== '' && str_starts_with($path, '/') && !str_starts_with($path, '//')) {
            $path = BASE_URL . $path;
        }
        http_response_code($status);
        header("Location: {$path}");
        exit;
    }

    public static function view(string $viewPath, array $data = [], string $layout = 'main'): void
    {
        extract($data);

        ob_start();
        $viewFile = BASE_PATH . '/app/Views/' . $viewPath . '.php';
        if (!file_exists($viewFile)) {
            throw new \RuntimeException("Vue introuvable : {$viewFile}");
        }
        require $viewFile;
        $content = ob_get_clean();

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
