<?php
/** Temporary design rows, isolated from persisted templates. */
$search = $search ?? '';
$tipe = $tipe ?? '';
$kategori = $kategori ?? '';
$rows = require VIEW_PATH . '/admin/template-rapor/_preview-data.php';
$rows = array_filter($rows, static fn($row) =>
    ($search === '' || mb_stripos($row['name'], $search) !== false)
    && ($tipe === '' || $tipe === 'system')
    && ($kategori === '' || $kategori === 'rapor_murid')
);
ob_start();
?>
<form method="GET" action="<?= BASE_PATH ?>/kurikulum/manajemen-template" class="list-filter">
    <?= uiField('q', 'Cari template', ['type' => 'search', 'value' => $search, 'placeholder' => 'Cari', 'icon' => 'icon_search', 'iconPosition' => 'left', 'hideLabel' => true, 'id' => 'list-search']) ?>
    <?= uiFilter('tipe', 'Tipe', ['' => 'Semua Tipe', 'system' => 'System', 'custom' => 'Custom'], ['value' => $tipe, 'id' => 'filter-tipe', 'marginVertical' => 0, 'attributes' => ['onchange' => 'this.form.submit()']]) ?>
    <?= uiFilter('kategori', 'Kategori', ['' => 'Semua Kategori', 'rapor_murid' => 'Rapor Murid', 'rapor_sekolah' => 'Rapor Sekolah'], ['value' => $kategori, 'id' => 'filter-kategori', 'marginVertical' => 0, 'attributes' => ['onchange' => 'this.form.submit()']]) ?>
</form>
<?php
$headerActions = ob_get_clean();
require VIEW_PATH . '/layouts/shell-header.php';
?>
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/manajemen-rapor.css?v=<?= filemtime(ROOT_PATH . '/public/assets/css/manajemen-rapor.css') ?>">
<?php require VIEW_PATH . '/admin/template-rapor/_semester-tables.php'; ?>
<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
