<?php

class Router
{
    private array $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => [],
    ];

    public function get(string $path, array $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, array $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function put(string $path, array $handler): void
    {
        $this->routes['PUT'][$path] = $handler;
    }

    public function delete(string $path, array $handler): void
    {
        $this->routes['DELETE'][$path] = $handler;
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }

        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if (defined('BASE_PATH') && BASE_PATH !== '' && str_starts_with($uri, BASE_PATH)) {
            $uri = substr($uri, strlen(BASE_PATH));
        }
        $uri = '/' . trim($uri, '/');

        $routes = $this->routes[$method] ?? [];

        foreach ($routes as $path => $handler) {
            $pattern = preg_replace('#\{[a-zA-Z_]+\}#', '([^/]+)', $path);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches);
                [$controllerClass, $method2] = $handler;
                $controller = new $controllerClass();
                call_user_func_array([$controller, $method2], $matches);
                return;
            }
        }

        $this->notFound();
    }

    private function notFound(): void
    {
        http_response_code(404);
        $errorView = VIEW_PATH . '/errors/404.php';
        if (file_exists($errorView)) {
            require $errorView;
        } else {
            echo '404 Not Found';
        }
    }
}
