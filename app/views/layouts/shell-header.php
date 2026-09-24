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
 * - $pageTitleHtml (string) — HTML mentah pengganti $pageTitle untuk H1 (mis. nama
 *   murid + dropdown switcher di Pratinjau Rapor Murid) — view yang set ini WAJIB
 *   escape sendiri bagian data dinamisnya. Kalau tidak di-set, $pageTitle dipakai
 *   apa adanya (di-escape otomatis).
 */
$breadcrumb = $breadcrumb ?? null;
$headerActions = $headerActions ?? '';
$activeNavItem = $activeNavItem ?? '';
$pageTitleHtml = $pageTitleHtml ?? null;

// Struktur navigasi dikonfirmasi ULANG langsung dari assets/ss/sidebar.svg,
// sidebar_submenu.svg dan sidebar_submenu2.svg (bukan dari deskripsi teks
// design-system.md 2.2 yang ternyata salah urutan/nesting-nya di grup
// Human Capital — lihat catatan [FIX] di bawah). Tiap grup punya daftar
// 'entries' berurutan persis seperti tampilan SVG; entry bertipe 'item'
// (link langsung) atau 'submenu' (dropdown dengan children).
// 'perm' => [modul, subSection] dipakai untuk filter entry yang tidak
// bisa diakses role saat ini — server-side tetap dilindungi
// RoleMiddleware, ini murni soal UX supaya tidak menampilkan menu yang
// toh akan di-403 kalau diklik.
$navGroups = [
    [
        'label' => 'Sekolah',
        'entries' => [
            ['type' => 'item', 'key' => 'data-sekolah', 'label' => 'Data Sekolah', 'href' => '/sekolah', 'icon' => 'icon_school', 'perm' => ['Sekolah', 'Data Sekolah']],
            [
                'type' => 'submenu',
                'key' => 'kurikulum',
                'label' => 'Kurikulum',
                'icon' => 'icon_book_marked',
                'items' => [
                    ['key' => 'manajemen-template', 'label' => 'Manajemen Rapor', 'href' => '/kurikulum/manajemen-template', 'perm' => ['Sekolah', 'Manajemen Template']],
                    ['key' => 'periode-penilaian', 'label' => 'Periode Rapor', 'href' => '/kurikulum/periode-penilaian', 'perm' => ['Sekolah', 'Periode Penilaian']],
                ],
            ],
        ],
    ],
    [
        'label' => 'Human Capital',
        'entries' => [
            [
                'type' => 'submenu',
                'key' => 'karyawan',
                'label' => 'Karyawan',
                'icon' => 'icon_users',
                'items' => [
                    ['key' => 'daftar-karyawan', 'label' => 'Daftar Karyawan', 'href' => '/karyawan', 'perm' => ['Human Capital', 'Daftar Karyawan']],
                    ['key' => 'jabatan', 'label' => 'Jabatan', 'href' => '/jabatan', 'perm' => ['Human Capital', 'Jabatan']],
                ],
            ],
            // [FIX] Dikonfirmasi dari assets/ss/sidebar_submenu2.svg (Karyawan
            // di-expand): "Manajemen Guru" BUKAN child dari submenu Karyawan —
            // itu item top-level tersendiri dengan ikon sendiri, muncul
            // SETELAH header "Karyawan" (bukan di dalamnya). Sebelumnya
            // salah dinested sebagai child submenu Karyawan.
            ['type' => 'item', 'key' => 'manajemen-guru', 'label' => 'Manajemen Guru', 'href' => '/manajemen-guru', 'icon' => 'icon_user_round_cog', 'perm' => ['Human Capital', 'Manajemen Guru']],
        ],
    ],
    [
        'label' => 'Murid',
        'entries' => [
            ['type' => 'item', 'key' => 'manajemen-murid', 'label' => 'Manajemen Murid', 'href' => '/murid', 'icon' => 'icon_graduation_cap', 'perm' => ['Murid', 'Manajemen Murid']],
            ['type' => 'item', 'key' => 'manajemen-kelas', 'label' => 'Manajemen Kelas', 'href' => '/kelas', 'icon' => 'icon_backpack', 'perm' => ['Murid', 'Manajemen Kelas']],
            ['type' => 'item', 'key' => 'rapor-murid', 'label' => 'Rapor Murid', 'href' => '/rapor-murid', 'icon' => 'icon_book_user', 'perm' => ['Murid', 'Rapor Murid']],
            ['type' => 'item', 'key' => 'erapor-approval', 'label' => 'Persetujuan eRapor', 'href' => '/erapor/persetujuan', 'icon' => 'icon_book_user', 'perm' => ['eRapor', 'Persetujuan']],
        ],
    ],
    [
        'label' => 'Portal Guru',
        'entries' => [
            ['type' => 'item', 'key' => 'portal-dashboard', 'label' => 'Dashboard', 'href' => '/portal-guru/dashboard', 'icon' => 'icon_layout_dashboard', 'perm' => ['Portal Guru', 'Dashboard']],
            ['type' => 'item', 'key' => 'portal-daftar-murid', 'label' => 'Daftar Murid', 'href' => '/portal-guru/murid', 'icon' => 'icon_backpack', 'perm' => ['Portal Guru', 'Daftar Murid']],
        ],
    ],
    [
        'label' => 'Sistem',
        'entries' => [
            ['type' => 'item', 'key' => 'rbac', 'label' => 'RBAC', 'href' => '/rbac', 'icon' => 'icon_user_cog', 'perm' => ['Sistem', 'RBAC']],
            ['type' => 'item', 'key' => 'erapor-assignments', 'label' => 'Penugasan Penyetuju', 'href' => '/erapor/persetujuan/penugasan', 'icon' => 'icon_users', 'perm' => ['eRapor', 'Penugasan Penyetuju']],
        ],
    ],
];

// Filter entry/child yang tidak bisa diakses role saat ini (aksi 'lihat').
// Ini murni UX — proteksi sungguhan tetap RoleMiddleware server-side.
$roleChecker = new RoleMiddleware();
$canAccessNav = fn(array $item) => !isset($item['perm']) || $roleChecker->check($item['perm'][0], $item['perm'][1], 'lihat');

foreach ($navGroups as $gi => $group) {
    foreach ($group['entries'] as $ei => $entry) {
        if ($entry['type'] === 'submenu') {
            $navGroups[$gi]['entries'][$ei]['items'] = array_values(array_filter($entry['items'], $canAccessNav));
        }
    }

    // Buang entry item yang tidak diizinkan, dan submenu yang jadi kosong.
    $navGroups[$gi]['entries'] = array_values(array_filter(
        $navGroups[$gi]['entries'],
        fn($entry) => $entry['type'] === 'submenu' ? !empty($entry['items']) : $canAccessNav($entry)
    ));
}

// Buang grup yang jadi kosong total setelah difilter.
$navGroups = array_values(array_filter($navGroups, fn($group) => !empty($group['entries'])));

// Buka otomatis submenu yang salah satu child-nya sedang aktif.
foreach ($navGroups as $gi => $group) {
    foreach ($group['entries'] as $ei => $entry) {
        if ($entry['type'] !== 'submenu') {
            continue;
        }
        foreach ($entry['items'] as $item) {
            if ($item['key'] === $activeNavItem) {
                $navGroups[$gi]['entries'][$ei]['is_open'] = true;
                $navGroups[$gi]['entries'][$ei]['is_active'] = true;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<script>
// Restore before styles/body are parsed to avoid an expanded-sidebar flash.
try {
    document.documentElement.classList.toggle('sidebar-collapsed', localStorage.getItem('zivana-erp-sidebar-collapsed') === '1');
} catch (_) {}
</script>
<?php require VIEW_PATH . '/layouts/head.php'; ?>
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="<?= BASE_PATH ?>/assets/images/logo-colored.png" class="sidebar-brand-logo" alt="<?= e(APP_NAME) ?>">
            <button type="button" class="sidebar-collapse-btn" data-sidebar-toggle aria-label="Ciutkan sidebar">
                <?= icon('icon_minimize') ?>
                <img src="data:image/png;base64,<?= base64_encode(file_get_contents(ROOT_PATH . '/public/assets/images/logo-icon.png')) ?>" class="sidebar-expand-logo" width="24" height="24" loading="eager" decoding="sync" alt="" aria-hidden="true">
            </button>
        </div>

        <nav class="nav-menu" id="main-navigation" aria-label="Navigasi utama">
            <?php foreach ($navGroups as $group): ?>
            <?php $groupHasOpenSubmenu = !empty(array_filter($group['entries'], fn($e) => $e['type'] === 'submenu' && !empty($e['is_open']))); ?>
            <div class="nav-group<?= $groupHasOpenSubmenu ? ' is-open' : '' ?>">
                <?= uiText($group['label'], 'body-sm', [
                    'tag' => 'p', 'weight' => 'regular', 'tone' => 'muted', 'class' => 'nav-group-label',
                ]) ?>
                <?php foreach ($group['entries'] as $entry): ?>
                    <?php if ($entry['type'] === 'item'): ?>
                    <a href="<?= BASE_PATH . e($entry['href']) ?>" class="nav-item<?= $activeNavItem === $entry['key'] ? ' is-active' : '' ?>">
                        <?= icon($entry['icon']) ?>
                        <span class="nav-label"><?= e($entry['label']) ?></span>
                    </a>
                    <?php else: ?>
                    <button type="button" class="nav-group-toggle<?= !empty($entry['is_active']) ? ' is-active' : '' ?>" data-nav-toggle>
                        <?= icon($entry['icon']) ?>
                        <span class="nav-label"><?= e($entry['label']) ?></span>
                        <span class="nav-chevron"><?= icon('icon_chevron') ?></span>
                    </button>
                    <div class="nav-submenu">
                        <?php foreach ($entry['items'] as $item): ?>
                        <a href="<?= BASE_PATH . e($item['href']) ?>" class="nav-item<?= $activeNavItem === $item['key'] ? ' is-active' : '' ?>">
                            <span class="nav-submenu-connector" aria-hidden="true"><?= icon('curved-submenu') ?></span>
                            <span class="nav-label"><?= e($item['label']) ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </nav>
    </aside>

    <div class="content">
        <header class="page-header">
            <div class="page-header-row page-header-toolbar">
                <?= uiField('header_search', 'Cari', [
                    'type' => 'search', 'placeholder' => 'Cari', 'hideLabel' => true,
                    'icon' => 'icon_search', 'iconPosition' => 'left', 'class' => 'search-field',
                ]) ?>
                <!--
                    [FIX] Widget profil user (nama + role + avatar inisial +
                    chevron) — sebelumnya salah diimplementasikan sebagai
                    ikon notifikasi + email mentah, padahal semua SVG di
                    assets/ss/ yang menampilkan header konsisten menunjukkan
                    widget profil (mis. "Nur Sahayana" / "Admin" + avatar
                    "NS"), bukan notifikasi. design-system.md 2.3 tidak
                    mendokumentasikan komponen ini sama sekali (gap crawl
                    sebelumnya). Dropdown isinya belum ada contoh visual di
                    SVG manapun (semua capture dalam kondisi tertutup) —
                    diisi minimal dengan "Keluar" karena sebelum ini TIDAK
                    ADA cara logout lewat UI sama sekali di halaman manapun.
                -->
                <div class="action-menu page-header-user" data-action-menu>
                    <button type="button" class="page-header-user-toggle" data-action-menu-toggle>
                        <div class="page-header-user-info">
                            <span class="page-header-user-name"><?= e($_SESSION['display_name'] ?? '') ?></span>
                            <span class="page-header-user-role"><?= e($_SESSION['role_name'] ?? '') ?></span>
                        </div>
                        <div class="page-header-avatar"><?= e(initials($_SESSION['display_name'] ?? '?')) ?></div>
                        <?= icon('icon_chevron') ?>
                    </button>
                    <div class="action-menu-dropdown">
                        <a href="<?= BASE_PATH ?>/logout">Keluar</a>
                    </div>
                </div>
            </div>

            <?php if ($breadcrumb !== null): ?>
            <div class="page-header-row page-header-breadcrumb"><?= $breadcrumb ?></div>
            <?php endif; ?>

            <div class="page-header-row page-header-title-row">
                <h1><?= $pageTitleHtml ?? e($pageTitle) ?></h1>
                <div class="page-header-actions"><?= $headerActions ?></div>
            </div>
        </header>

        <main class="content-body">
