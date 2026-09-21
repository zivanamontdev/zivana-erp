<?php

/**
 * Kumpulan helper function global. Bukan class, jadi tidak lewat
 * spl_autoload_register — di-require langsung di public/index.php.
 */

/**
 * Render SVG icon inline supaya warnanya bisa dikontrol lewat CSS
 * `color` (stroke sudah dinormalisasi ke `currentColor` saat file
 * di-copy ke public/assets/icons/ — lihat cookbook/design-system.md 3.4).
 * Icon via <img src="..."> TIDAK bisa di-recolor lewat CSS, makanya
 * di-inline langsung ke HTML.
 *
 * @param string $name  nama file tanpa ekstensi, mis. "icon_school"
 * @param string $class class CSS tambahan untuk elemen <svg>
 */
function icon(string $name, string $class = ''): string
{
    static $cache = [];

    if (!isset($cache[$name])) {
        $path = ROOT_PATH . '/public/assets/icons/' . $name . '.svg';
        $cache[$name] = file_exists($path) ? file_get_contents($path) : '';
    }

    $svg = $cache[$name];

    if ($svg === '' || $class === '') {
        return $svg;
    }

    return preg_replace('/<svg /', '<svg class="' . htmlspecialchars($class) . '" ', $svg, 1);
}

/**
 * Escape output HTML singkat.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Ambil (atau buat baru kalau belum ada/kedaluwarsa) token CSRF untuk
 * request saat ini. Validasi penuh (validateCsrfToken) ditambahkan di
 * Controller base class pada task CSRF khusus — lihat cookbook/todo.md
 * Fase 1 dan cookbook/security.md bagian 2.
 */
function getCsrfToken(): string
{
    $expired = empty($_SESSION['csrf_token_expires']) || time() > $_SESSION['csrf_token_expires'];

    if (empty($_SESSION['csrf_token']) || $expired) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        $_SESSION['csrf_token_expires'] = time() + 3600; // 1 jam
    }

    return $_SESSION['csrf_token'];
}

/**
 * Validasi token CSRF. Single-use — token dihapus dari session setelah
 * divalidasi (baik valid maupun tidak), jadi form yang gagal submit
 * (misal validasi input gagal) perlu redirect balik ke halaman GET
 * supaya token baru dibuat lagi. Lihat cookbook/security.md bagian 2.
 */
function validateCsrfToken(string $token): bool
{
    if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_token_expires'])) {
        return false;
    }

    if (time() > $_SESSION['csrf_token_expires']) {
        unset($_SESSION['csrf_token'], $_SESSION['csrf_token_expires']);
        return false;
    }

    $valid = hash_equals($_SESSION['csrf_token'], $token);

    unset($_SESSION['csrf_token'], $_SESSION['csrf_token_expires']);

    return $valid;
}

/**
 * Render simbol skala penilaian Montessori (bentuk custom, bukan
 * karakter unicode standar) sebagai inline SVG kecil. Dipakai di
 * legenda dan sel nilai dokumen rapor. Lihat
 * cookbook/design-system.md bagian 3.5.
 *
 * @param string $kode 'slash'|'triangle-sm'|'triangle-lg'|'triangle-full'
 */
function renderSkalaSimbol(string $kode): string
{
    $shapes = [
        'slash' => '<line x1="4" y1="16" x2="12" y2="2" stroke="currentColor" stroke-width="1.5"/>',
        'triangle-sm' => '<polygon points="8,4 13,14 3,14" fill="none" stroke="currentColor" stroke-width="1.2"/>',
        'triangle-lg' => '<polygon points="8,1 15,15 1,15" fill="none" stroke="currentColor" stroke-width="1.2"/>',
        'triangle-full' => '<polygon points="8,1 15,15 1,15" fill="currentColor"/>',
    ];

    $shape = $shapes[$kode] ?? '';

    return '<svg class="skala-simbol" width="16" height="16" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg">' . $shape . '</svg>';
}
