<?php

abstract class Controller
{
    /**
     * Enforce CSRF otomatis untuk semua request POST/PUT/DELETE di
     * SEMUA controller — tidak bergantung pada disiplin developer
     * memanggil validasi manual di tiap method. Lihat
     * cookbook/security.md bagian 2.
     */
    public function __construct()
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
            $token = (string) ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

            if (!validateCsrfToken($token)) {
                http_response_code(419);
                die('Token keamanan (CSRF) tidak valid atau kedaluwarsa. Muat ulang halaman dan coba lagi.');
            }
        }
    }

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
