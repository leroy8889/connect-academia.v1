<?php
declare(strict_types=1);

namespace Core;

class Router
{
    private array $routes = [];

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
        $this->routes[] = compact('method', 'path', 'handler', 'middleware');
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri    = urldecode(preg_replace('#/+#', '/', $uri));

        // Retirer index.php si présent
        $uri = preg_replace('#/index\.php(/|$)#', '/', $uri);

        // Retirer le préfixe BASE_URL
        if (BASE_URL !== '' && str_starts_with($uri, BASE_URL)) {
            $uri = substr($uri, strlen(BASE_URL));
        }

        $uri = rtrim($uri, '/') ?: '/';

        // Support _method (PUT/PATCH/DELETE via formulaire HTML)
        if ($method === 'POST' && !empty($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }

        foreach ($this->routes as $route) {
            $pattern = $this->toRegex($route['path']);
            if ($route['method'] === $method && preg_match($pattern, $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $this->runMiddleware($route['middleware']);
                $this->callHandler($route['handler'], $params);
                return;
            }
        }

        $this->notFound($uri);
    }

    private function toRegex(string $path): string
    {
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    private function runMiddleware(array $list): void
    {
        $map = [
            'auth'    => \Middleware\AuthMiddleware::class,
            'csrf'    => \Middleware\CsrfMiddleware::class,
            'admin'   => \Middleware\AdminMiddleware::class,
            'abonne'  => \Middleware\AbonneMiddleware::class,
        ];

        foreach ($list as $name) {
            if (isset($map[$name])) {
                (new $map[$name]())->handle();
            }
        }
    }

    private function callHandler(string $handler, array $params): void
    {
        [$controllerName, $method] = explode('@', $handler);
        $class = 'Controllers\\' . $controllerName;

        if (!class_exists($class)) {
            http_response_code(503);
            if ($this->isAjax()) {
                Response::json(['success' => false, 'error' => ['code' => 'NOT_IMPLEMENTED', 'message' => 'Module en cours de développement']], 503);
            }
            require BASE_PATH . '/app/Views/errors/503.php';
            exit;
        }

        $controller = new $class();

        if (!method_exists($controller, $method)) {
            throw new \RuntimeException("Méthode introuvable : {$class}@{$method}");
        }

        if (empty($params)) {
            $controller->$method();
            return;
        }

        $ref       = new \ReflectionMethod($controller, $method);
        $firstP    = $ref->getParameters()[0] ?? null;
        $firstType = $firstP?->getType();

        if ($firstType instanceof \ReflectionNamedType && $firstType->getName() === 'array') {
            // Contrôleurs Admin : method(array $params) — passer le tableau associatif nommé
            $controller->$method($params);
        } else {
            // Contrôleurs standard : method(string $id, ...) — dépaqueter les valeurs
            call_user_func_array([$controller, $method], array_values($params));
        }
    }

    private function notFound(string $uri): void
    {
        // Mode debug
        if (($_ENV['APP_DEBUG'] ?? 'false') === 'true' && isset($_GET['debug'])) {
            echo "<h2>404 — Route introuvable</h2><pre>URI: {$uri}\nBASE_URL: " . BASE_URL . "\n\nRoutes enregistrées:\n";
            foreach ($this->routes as $r) {
                echo "{$r['method']} {$r['path']}\n";
            }
            echo "</pre>";
            exit;
        }

        http_response_code(404);
        if ($this->isAjax()) {
            Response::json(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Page non trouvée']], 404);
        }
        require BASE_PATH . '/app/Views/errors/404.php';
    }

    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
