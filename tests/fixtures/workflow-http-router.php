<?php
// Test-only front controller. Never served by the application's public document root.
if (PHP_SAPI !== 'cli-server' || !preg_match('/^zivana_(?:e2e|erapor_test)_[a-f0-9]{16}$/D', getenv('ZIVANA_E2E_DB') ?: '')) {
    http_response_code(404);
    exit;
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($path, '/assets/')) return false;
class Database
{
    private static ?PDO $db = null;
    public static function getInstance(): PDO
    {
        return self::$db ??= new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . getenv('ZIVANA_E2E_DB') . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]
        );
    }
}
require dirname(__DIR__, 2) . '/public/index.php';
