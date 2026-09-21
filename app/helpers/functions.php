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
 * Inisial 2 huruf dari nama untuk avatar bulat di page-header (lihat
 * assets/ss/*.svg mana pun yang menunjukkan header — widget "NS" untuk
 * "Nur Sahayana"). Ambil huruf pertama dari 2 kata pertama saja,
 * kata ke-3+ diabaikan.
 */
function initials(string $name): string
{
    $words = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);
    $letters = array_map(fn($w) => mb_strtoupper(mb_substr($w, 0, 1)), array_slice($words, 0, 2));

    return implode('', $letters) ?: '?';
}

/**
 * HTML baris breadcrumb ("Parent > Halaman ⓘ") untuk dilempar sebagai
 * $breadcrumb ke layouts/shell-header.php. [FIX] Sebelumnya TIDAK ADA
 * satupun view yang mengisi breadcrumb meski design-system.md 2.3
 * eksplisit mewajibkannya di halaman 2-level (submenu, detail, form).
 * Dikonfirmasi ulang langsung dari assets/ss/*.svg (bukan cuma daftar
 * contoh di design-system.md yang ternyata tidak lengkap) — pola yang
 * benar: SETIAP halaman yang diakses lewat submenu ATAU halaman
 * detail/form turunan dari sebuah daftar, dapat breadcrumb ke
 * parent-nya; hanya daftar top-level (diakses langsung dari item
 * sidebar biasa) yang tidak.
 *
 * [ASUMSI] Ikon "ⓘ" di sebelah kanan selalu ada di semua SVG breadcrumb
 * tapi tidak ada tooltip/konten yang ter-crawl untuk isinya — dirender
 * statis/dekoratif, tidak ada perilaku klik/hover khusus.
 */
function breadcrumb(string $parent, string $current): string
{
    return '<span class="breadcrumb-parent">' . e($parent) . '</span>'
        . icon('icon_chevron', 'breadcrumb-separator')
        . '<span class="breadcrumb-current">' . e($current) . '</span>'
        . icon('icon_tooltip', 'breadcrumb-info');
}

/**
 * HTML untuk $pageTitleHtml di Pratinjau Rapor Murid (admin & guru).
 * [FIX] Sebelumnya H1 di halaman ini teks statis "Pratinjau Rapor
 * Murid" — dikonfirmasi dari assets/ss/Murid - menu_rapor_murid -
 * halaman_pratinjau_rapor.svg dan Portal Guru - menu_dashboard -
 * halaman_pratinjau_rapor_murid.svg, H1 seharusnya NAMA MURID dengan
 * dropdown chevron untuk pindah ke murid lain di sesi yang sama —
 * dipakai ulang di RaporMuridController (admin) dan
 * PengisianRaporController::pratinjau() (guru).
 *
 * @param array{id:int,nama_lengkap:string} $current
 * @param array<array{id:int,nama_lengkap:string}> $siblings
 * @param string $urlTemplate URL dengan placeholder "{id}", mis. "/rapor-murid/{id}"
 */
function muridSwitcherTitle(array $current, array $siblings, string $urlTemplate): string
{
    $base = defined('BASE_PATH') ? BASE_PATH : '';
    $html = '<div class="action-menu page-title-switcher" data-action-menu>'
        . '<button type="button" class="page-title-switcher-toggle" data-action-menu-toggle>'
        . e($current['nama_lengkap']) . icon('icon_chevron') . '</button>';

    if (!empty($siblings)) {
        $html .= '<div class="action-menu-dropdown">';
        foreach ($siblings as $sibling) {
            $url = $base . str_replace('{id}', (string) $sibling['id'], $urlTemplate);
            $html .= '<a href="' . e($url) . '">' . e($sibling['nama_lengkap']) . '</a>';
        }
        $html .= '</div>';
    }

    $html .= '</div>';

    return $html;
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

/**
 * URL/data-URI untuk asset gambar. Dipakai di view yang bisa dirender
 * baik sebagai halaman web biasa (path relatif normal) MAUPUN sebagai
 * PDF via Dompdf ($inline=true) — Dompdf tidak selalu bisa resolve
 * path relatif "/assets/..." dengan benar, jadi untuk PDF gambar
 * di-inline sebagai base64 data URI supaya pasti tampil.
 */
function assetSrc(string $relativePath, bool $inline = false): string
{
    $relativePath = ltrim($relativePath, '/');

    if (!$inline) {
        return (defined('BASE_PATH') ? BASE_PATH : '') . '/assets/' . $relativePath;
    }

    $fullPath = ROOT_PATH . '/public/assets/' . $relativePath;

    if (!file_exists($fullPath)) {
        return '';
    }

    $mime = match (strtolower(pathinfo($fullPath, PATHINFO_EXTENSION))) {
        'svg' => 'image/svg+xml',
        'jpg', 'jpeg' => 'image/jpeg',
        default => 'image/png',
    };

    return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($fullPath));
}
