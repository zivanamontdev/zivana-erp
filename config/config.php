<?php

/**
 * Load .env dan definisikan konstanta konfigurasi aplikasi.
 *
 * PENTING (lihat cookbook/security.md bagian 7): TIDAK ADA fallback
 * kredensial hardcoded di file ini. Kalau .env gagal dibaca, aplikasi
 * WAJIB berhenti dengan error jelas, bukan diam-diam jalan dengan
 * kredensial yang menempel di source code.
 */

function loadEnvFile(string $path): array
{
    if (!file_exists($path)) {
        http_response_code(500);
        die('Konfigurasi .env tidak ditemukan. Salin .env.example ke .env lalu isi sesuai environment.');
    }

    $env = @parse_ini_file($path, false, INI_SCANNER_RAW);

    if ($env === false) {
        // Fallback parser manual — beberapa environment shared hosting
        // menonaktifkan parse_ini_file untuk file bernama ".env".
        $env = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim(trim($value), "\"'");
            $env[$key] = $value;
        }
    }

    if (empty($env)) {
        http_response_code(500);
        die('File .env kosong atau gagal di-parse. Periksa formatnya.');
    }

    return $env;
}

function envValue(array $env, string $key, $default = null)
{
    return array_key_exists($key, $env) && $env[$key] !== '' ? $env[$key] : $default;
}

$env = loadEnvFile(ROOT_PATH . '/.env');

// Palet warna dipisahkan dari konfigurasi environment supaya semua nilai
// warna punya satu sumber kebenaran dan dapat dipakai ulang oleh PHP maupun
// CSS variable yang dirender di layouts/head.php.
$colorTokens = require CONFIG_PATH . '/colors.php';
define('COLOR_TOKENS', $colorTokens);

// --- Database ---
define('DB_HOST', envValue($env, 'DB_HOST'));
define('DB_PORT', envValue($env, 'DB_PORT', '3306'));
define('DB_NAME', envValue($env, 'DB_NAME'));
define('DB_USER', envValue($env, 'DB_USER'));
define('DB_PASS', envValue($env, 'DB_PASS'));

// --- Aplikasi ---
define('APP_NAME', envValue($env, 'APP_NAME', 'Zivana ERP'));
define('APP_URL', envValue($env, 'APP_URL', ''));
define('APP_ENV', envValue($env, 'APP_ENV', 'production'));
define('APP_DEBUG', filter_var(envValue($env, 'APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN));
define('SESSION_LIFETIME', (int) envValue($env, 'SESSION_LIFETIME', 120));

// --- Upload ---
define('MAX_UPLOAD_SIZE', (int) envValue($env, 'MAX_UPLOAD_SIZE', 2097152));
define('ALLOWED_IMAGE_TYPES', explode(',', (string) envValue($env, 'ALLOWED_IMAGE_TYPES', 'jpg,jpeg,png')));

// --- Keamanan ---
define('CSRF_TOKEN_NAME', envValue($env, 'CSRF_TOKEN_NAME', 'csrf_token'));

// --- Email (fitur reset password, lihat Fase 1) ---
define('SMTP_HOST', envValue($env, 'SMTP_HOST'));
define('SMTP_PORT', (int) envValue($env, 'SMTP_PORT', 587));
define('SMTP_USER', envValue($env, 'SMTP_USER'));
define('SMTP_PASS', envValue($env, 'SMTP_PASS'));
define('SMTP_FROM_EMAIL', envValue($env, 'SMTP_FROM_EMAIL'));

// --- Error reporting sesuai APP_DEBUG ---
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// --- Session ---
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME * 60,
        'httponly' => true,
        'samesite' => 'Lax',
        // Otomatis aktif kalau request datang lewat HTTPS (produksi) —
        // tidak di-hardcode true supaya tetap jalan di HTTP lokal (dev).
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}
