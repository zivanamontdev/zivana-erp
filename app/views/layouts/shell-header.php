<?php
/**
 * Layout shell — bagian header (buka <html>, sidebar, buka page header + content-body).
 * Dipakai di semua halaman KECUALI Login. Lihat cookbook/design-system.md bagian 2.
 * Pasangannya: layouts/shell-footer.php (menutup tag yang dibuka di sini).
 *
 * Variabel WAJIB di-set oleh view sebelum require file ini:
 * - $pageTitle (string)
 *
 * Variabel opsional:
 * - $breadcrumb (string|null) — null = baris breadcrumb tidak ditampilkan (lihat 2.3:
 *   breadcrumb hanya muncul di halaman yang punya parent)
 * - $headerActions (string) — HTML tombol aksi di baris judul, sudah di-render oleh view
 * - $activeNavItem (string) — key item nav yang aktif, lihat daftar $navGroups di bawah
 */
$breadcrumb = $breadcrumb ?? null;
$headerActions = $headerActions ?? '';
$activeNavItem = $activeNavItem ?? '';

// Struktur navigasi persis sesuai cookbook/design-system.md bagian 2.2.
$navGroups = [
    [
        'label' => 'Sekolah',
        'items' => [
            ['key' => 'data-sekolah', 'label' => 'Data Sekolah', 'href' => '/sekolah', 'icon' => 'icon_school'],
        ],
        'submenus' => [
            [
                'key' => 'kurikulum',
                'label' => 'Kurikulum',
                'icon' => 'icon_book_marked',
                'items' => [
                    ['key' => 'manajemen-template', 'label' => 'Manajemen Template', 'href' => '/kurikulum/manajemen-template'],
                    ['key' => 'periode-penilaian', 'label' => 'Periode Penilaian', 'href' => '/kurikulum/periode-penilaian'],
                ],
            ],
        ],
    ],
    [
        'label' => 'Human Capital',
        'items' => [],
        'submenus' => [
            [
                'key' => 'karyawan',
                'label' => 'Karyawan',
                'icon' => 'icon_users',
                'items' => [
                    ['key' => 'daftar-karyawan', 'label' => 'Daftar Karyawan', 'href' => '/karyawan'],
                    ['key' => 'jabatan', 'label' => 'Jabatan', 'href' => '/jabatan'],
                    ['key' => 'manajemen-guru', 'label' => 'Manajemen Guru', 'href' => '/manajemen-guru'],
                ],
            ],
        ],
    ],
    [
        'label' => 'Murid',
        'items' => [
            ['key' => 'manajemen-murid', 'label' => 'Manajemen Murid', 'href' => '/murid', 'icon' => 'icon_graduation_cap'],
            ['key' => 'manajemen-kelas', 'label' => 'Manajemen Kelas', 'href' => '/kelas', 'icon' => 'icon_backpack'],
            ['key' => 'rapor-murid', 'label' => 'Rapor Murid', 'href' => '/rapor-murid', 'icon' => 'icon_book_user'],
        ],
        'submenus' => [],
    ],
    [
        'label' => 'Portal Guru',
        'items' => [
            ['key' => 'portal-dashboard', 'label' => 'Dashboard', 'href' => '/portal-guru/dashboard', 'icon' => 'icon_layout_dashboard'],
            ['key' => 'portal-daftar-murid', 'label' => 'Daftar Murid', 'href' => '/portal-guru/murid', 'icon' => 'icon_backpack'],
        ],
        'submenus' => [],
    ],
    [
        'label' => 'Sistem',
        'items' => [
            ['key' => 'rbac', 'label' => 'RBAC', 'href' => '/rbac', 'icon' => 'icon_user_cog'],
        ],
        'submenus' => [],
    ],
];

// Buka otomatis submenu yang salah satu child-nya sedang aktif.
foreach ($navGroups as $gi => $group) {
    foreach ($group['submenus'] as $si => $submenu) {
        foreach ($submenu['items'] as $item) {
            if ($item['key'] === $activeNavItem) {
                $navGroups[$gi]['submenus'][$si]['is_open'] = true;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<?php require VIEW_PATH . '/layouts/head.php'; ?>
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="<?= BASE_PATH ?>/assets/images/logo-colored.png" alt="<?= e(APP_NAME) ?>">
            <button type="button" class="sidebar-collapse-btn" data-sidebar-toggle aria-label="Ciutkan sidebar">
                <?= icon('icon_minimize') ?>
            </button>
        </div>

        <nav class="nav-menu">
            <?php foreach ($navGroups as $group): ?>
            <div class="nav-group<?= !empty($group['submenus'][0]['is_open'] ?? false) ? ' is-open' : '' ?>">
                <?php foreach ($group['items'] as $item): ?>
                <a href="<?= BASE_PATH . e($item['href']) ?>" class="nav-item<?= $activeNavItem === $item['key'] ? ' is-active' : '' ?>">
                    <?= icon($item['icon']) ?>
                    <span class="nav-label"><?= e($item['label']) ?></span>
                </a>
                <?php endforeach; ?>

                <?php foreach ($group['submenus'] as $submenu): ?>
                <button type="button" class="nav-group-toggle" data-nav-toggle>
                    <?= icon($submenu['icon']) ?>
                    <span class="nav-label"><?= e($submenu['label']) ?></span>
                    <span class="nav-chevron"><?= icon('icon_chevron') ?></span>
                </button>
                <div class="nav-submenu">
                    <?php foreach ($submenu['items'] as $item): ?>
                    <a href="<?= BASE_PATH . e($item['href']) ?>" class="nav-item<?= $activeNavItem === $item['key'] ? ' is-active' : '' ?>">
                        <span class="nav-label"><?= e($item['label']) ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </nav>
    </aside>

    <div class="content">
        <header class="page-header">
            <div class="page-header-row page-header-toolbar">
                <input type="search" class="search-field" placeholder="Cari">
                <div class="page-header-user">
                    <!-- [ASUMSI] Icon aksi toolbar persis belum terdokumentasi di design-system.md 2.3,
                         pakai icon_bolt sementara sebagai placeholder notifikasi. -->
                    <?= icon('icon_bolt') ?>
                    <span><?= e($_SESSION['user_name'] ?? '') ?></span>
                </div>
            </div>

            <?php if ($breadcrumb !== null): ?>
            <div class="page-header-row page-header-breadcrumb"><?= $breadcrumb ?></div>
            <?php endif; ?>

            <div class="page-header-row page-header-title-row">
                <h1><?= e($pageTitle) ?></h1>
                <div class="page-header-actions"><?= $headerActions ?></div>
            </div>
        </header>

        <main class="content-body">
