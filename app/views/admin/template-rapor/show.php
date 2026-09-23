<?php
/**
 * Pratinjau Template — cookbook/design-system.md bagian 5.2.
 * Render dokumen rapor dengan placeholder variable (belum data murid
 * sungguhan — itu di Fase 7, Pratinjau Rapor Murid).
 *
 * Variabel dari TemplateRaporController::show():
 * - $template, $areas (nested: area->subkategori->item), $legenda
 *
 * [ASUMSI] Hanya halaman 1 dari 4 yang terkonfirmasi dari crawling
 * Figma (lihat cookbook/design-system.md 5.2) — struktur halaman 2-4
 * (kemungkinan area lain, Bacaan Jilid, PAI, catatan guru) BELUM
 * dibangun, menunggu konfirmasi user/designer.
 */
$headerActions = '<button type="button" class="ui-button ui-button--outline ui-button--icon-only" aria-label="Muat ulang" onclick="location.reload()">' . icon('icon_refresh') . '</button> '
    . '<a href="' . BASE_PATH . '/kurikulum/manajemen-template/' . $template['id'] . '/pdf" class="ui-button ui-button--primary">Simpan PDF</a>';

require VIEW_PATH . '/layouts/shell-header.php';
?>
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/rapor-document.css?v=<?= filemtime(ROOT_PATH . '/public/assets/css/rapor-document.css') ?>">

<div class="rapor-toolbar">
    <span class="rapor-pagination">Halaman 1 dari 4</span>
</div>

<?php require VIEW_PATH . '/admin/template-rapor/_document.php'; ?>

<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
