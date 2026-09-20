<?php

abstract class Controller
{
    protected function view(string $name, array $data = []): void
    {
        extract($data);
        $viewFile = VIEW_PATH . '/' . str_replace('.', '/', $name) . '.php';
        if (!file_exists($viewFile)) {
            throw new RuntimeException("View tidak ditemukan: {$name}");
        }
        require $viewFile;
    }

    protected function json($data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    protected function redirect(string $path): void
    {
        $base = defined('BASE_PATH') ? BASE_PATH : '';
        header('Location: ' . $base . $path);
        exit;
    }

    protected function middleware(string $class, ...$args)
    {
        $middleware = new $class();
        return $middleware->handle(...$args);
    }

    protected function input(string $key, $default = null)
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    protected function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    protected function isGet(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'GET';
    }
}
