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
/** Password policy for employee creation and password changes. */
function employeePasswordIsValid(string $password): bool
{
    return mb_strlen($password) >= 8
        && preg_match('/[A-Z]/', $password) === 1
        && preg_match('/[0-9]/', $password) === 1
        && preg_match('/[\p{P}\p{S}]/u', $password) === 1;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Ambil seluruh token warna aplikasi. Fallback require membuat helper ini
 * tetap bisa dipakai oleh script CLI kecil yang belum memuat config.php.
 */
function colorTokens(): array
{
    static $tokens = null;

    if ($tokens === null) {
        if (defined('COLOR_TOKENS')) {
            $tokens = COLOR_TOKENS;
        } else {
            $path = defined('CONFIG_PATH')
                ? CONFIG_PATH . '/colors.php'
                : dirname(__DIR__, 2) . '/config/colors.php';
            $tokens = require $path;
        }
    }

    return $tokens;
}

/**
 * Ambil satu token warna berdasarkan nama, mis. neutral-50 atau
 * login-preview-divider.
 */
function colorToken(string $name): string
{
    $tokens = colorTokens();

    if (!array_key_exists($name, $tokens)) {
        throw new InvalidArgumentException("Token warna tidak ditemukan: {$name}");
    }

    return $tokens[$name];
}

/**
 * Render token warna sebagai CSS custom properties. Nilai HEX tetap hanya
 * berada di config/colors.php, bukan di stylesheet komponen.
 */
function colorCssVariables(): string
{
    $lines = [':root {'];

    foreach (colorTokens() as $name => $value) {
        if (!preg_match('/^#[0-9A-Fa-f]{6}([0-9A-Fa-f]{2})?$/', $value)) {
            throw new RuntimeException("Format token warna tidak valid: {$name}");
        }

        $cssName = preg_replace('/[^a-z0-9-]+/i', '-', (string) $name);
        $lines[] = '  --color-' . $cssName . ': ' . $value . ';';
    }

    $lines[] = '}';

    return implode("\n", $lines);
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
function breadcrumb(string|array $parent, string|array $current, string|array ...$descendants): string
{
    // Explicit [label, path] segments override these existing navigation defaults.
    $routes = [
        'Kurikulum' => '/kurikulum/manajemen-template',
        'Manajemen Rapor' => '/kurikulum/manajemen-template',
        'Periode Rapor' => '/kurikulum/periode-penilaian',
        'Karyawan' => '/karyawan', 'Daftar Karyawan' => '/karyawan',
        'Jabatan' => '/jabatan', 'Manajemen Kelas' => '/kelas',
        'Manajemen Murid' => '/murid', 'Rapor Murid' => '/rapor-murid',
    ];
    $segments = array_merge([$parent, $current], $descendants);
    $html = '';
    foreach ($segments as $index => $segment) {
        $label = is_array($segment) ? (string) $segment[0] : $segment;
        $path = is_array($segment) ? ($segment[1] ?? null) : ($routes[$label] ?? null);
        if ($index > 0) $html .= icon('icon_chevron', 'breadcrumb-separator');
        $class = $index === count($segments) - 1 ? 'breadcrumb-current' : 'breadcrumb-parent';
        if ($class === 'breadcrumb-parent' && is_string($path) && str_starts_with($path, '/') && !str_starts_with($path, '//')) {
            $html .= '<a class="' . $class . '" href="' . e((defined('BASE_PATH') ? BASE_PATH : '') . $path) . '">' . e($label) . '</a>';
        } else {
            $html .= '<span class="' . $class . '"' . ($class === 'breadcrumb-current' ? ' aria-current="page"' : '') . '>' . e($label) . '</span>';
        }
    }
    return $html . icon('icon_tooltip', 'breadcrumb-info');
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
 * karakter unicode standar) dari aset SVG bersama. Dipakai di
 * legenda dan sel nilai dokumen rapor. Lihat
 * cookbook/design-system.md bagian 3.5.
 *
 * @param string $kode 'slash'|'triangle-sm'|'triangle-lg'|'triangle-full'
 */
function skalaSimbolSrc(string $kode): string
{
    static $sources = [];
    $files = [
        'slash' => 'penilaian-1-sisi.svg',
        'triangle-sm' => 'penilaian-2-sisi.svg',
        'triangle-lg' => 'penilaian-3-sisi.svg',
        'triangle-full' => 'penilaian-full.svg',
    ];
    if (!isset($files[$kode])) return '';
    // Embed the same source for browser and offline PDF; no duplicate public asset.
    return $sources[$kode] ??= 'data:image/svg+xml;base64,' . base64_encode(file_get_contents(ROOT_PATH . '/assets/' . $files[$kode]));
}

function renderSkalaSimbol(string $kode): string
{
    $source = skalaSimbolSrc($kode);
    return $source === '' ? '' : '<img class="skala-simbol" width="16" height="16" src="' . e($source) . '" alt="" aria-hidden="true">';
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
