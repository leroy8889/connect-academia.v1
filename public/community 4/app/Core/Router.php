<?php
declare(strict_types=1);

namespace Core;

class Router
{
    /** @var array */
    private $routes = [];

    public function get(string $path, string $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, string $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function put(string $path, string $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function patch(string $path, string $handler, array $middleware = []): void
    {
        $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, string $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    private function addRoute(string $method, string $path, string $handler, array $middleware): void
    {
        $this->routes[] = [
            'method'     => $method,
            'path'       => $path,
            'handler'    => $handler,
            'middleware'  => $middleware,
        ];
    }

    public function dispatch(): void
    {
        $requestMethod = $_SERVER['REQUEST_METHOD'];
        $requestUri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Normaliser l'URI (supprimer les doubles slashes)
        $requestUri = preg_replace('#/+#', '/', $requestUri);
        
        // Décoder les espaces et autres caractères encodés
        $requestUri = urldecode($requestUri);
        
        // Supprimer index.php de l'URI si présent (cas où .htaccess ne fonctionne pas)
        $requestUri = preg_replace('#/index\.php(/|$)#', '/', $requestUri);

        // Supprimer le préfixe du sous-répertoire (BASE_URL) de l'URI
        if (defined('BASE_URL') && BASE_URL !== '') {
            // Essayer différentes variantes de BASE_URL (avec/sans encodage)
            $baseUrlVariants = [
                BASE_URL,
                urldecode(BASE_URL),
                urlencode(BASE_URL),
                str_replace(' ', '%20', BASE_URL),
                str_replace('%20', ' ', BASE_URL),
            ];
            
            foreach ($baseUrlVariants as $baseUrl) {
                if ($baseUrl !== '' && strpos($requestUri, $baseUrl) === 0) {
                    $requestUri = substr($requestUri, strlen($baseUrl));
                    break;
                }
            }
        }

        $requestUri = rtrim($requestUri, '/') ?: '/';

        // Support pour PUT/PATCH/DELETE via _method
        if ($requestMethod === 'POST' && isset($_POST['_method'])) {
            $requestMethod = strtoupper($_POST['_method']);
        }

        foreach ($this->routes as $route) {
            $pattern = $this->convertPathToRegex($route['path']);

            if ($route['method'] === $requestMethod && preg_match($pattern, $requestUri, $matches)) {
                // Extraire les paramètres nommés
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                // Exécuter les middlewares
                $this->runMiddleware($route['middleware']);

                // Appeler le controller
                $this->callHandler($route['handler'], $params);
                return;
            }
        }

        // 404 Not Found
        // Mode debug temporaire pour diagnostiquer les problèmes de routing
        $appEnv = $_ENV['APP_ENV'] ?? 'local';
        if ($appEnv === 'local' && isset($_GET['debug'])) {
            echo "<h1>Debug Router</h1>";
            echo "<p><strong>Request Method:</strong> {$requestMethod}</p>";
            echo "<p><strong>Request URI:</strong> {$_SERVER['REQUEST_URI']}</p>";
            echo "<p><strong>Parsed URI:</strong> {$requestUri}</p>";
            echo "<p><strong>BASE_URL:</strong> " . (defined('BASE_URL') ? BASE_URL : 'non défini') . "</p>";
            echo "<p><strong>SCRIPT_NAME:</strong> {$_SERVER['SCRIPT_NAME']}</p>";
            echo "<h2>Routes disponibles:</h2>";
            echo "<ul>";
            foreach ($this->routes as $route) {
                echo "<li>{$route['method']} {$route['path']} → {$route['handler']}</li>";
            }
            echo "</ul>";
            exit;
        }
        
        http_response_code(404);
        if ($this->isAjax()) {
            Response::json(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Page non trouvée']], 404);
        } else {
            require BASE_PATH . '/app/Views/errors/404.php';
        }
    }

    private function convertPathToRegex(string $path): string
    {
        // Convertir {param} en groupes nommés regex
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    private function runMiddleware(array $middlewareList): void
    {
        $middlewareMap = [
            'auth'  => \Middleware\AuthMiddleware::class,
            'csrf'  => \Middleware\CsrfMiddleware::class,
            'admin' => \Middleware\AdminMiddleware::class,
        ];

        foreach ($middlewareList as $name) {
            if (isset($middlewareMap[$name])) {
                $middleware = new $middlewareMap[$name]();
                $middleware->handle();
            }
        }
    }

    private function callHandler(string $handler, array $params): void
    {
        [$controllerName, $method] = explode('@', $handler);

        $controllerClass = 'Controllers\\' . $controllerName;

        if (!class_exists($controllerClass)) {
            throw new \RuntimeException("Controller introuvable : {$controllerClass}");
        }

        $controller = new $controllerClass();

        if (!method_exists($controller, $method)) {
            throw new \RuntimeException("Méthode introuvable : {$controllerClass}@{$method}");
        }

        // Appeler la méthode avec les paramètres de l'URL
        call_user_func_array([$controller, $method], $params);
    }

    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
