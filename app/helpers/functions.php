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
