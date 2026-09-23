<?php
/**
 * Pratinjau Template — cookbook/design-system.md bagian 5.2.
 * Render dokumen rapor dengan placeholder variable (belum data murid
 * sungguhan — itu di Fase 7, Pratinjau Rapor Murid).
 *
 * Variabel dari TemplateRaporController::show():
 * - $template, $areas (nested: area->subkategori->item), $legenda
 *
 * Browser preview uses the supplied design fixtures; pages 3–4 repeat 1–2.
 * PDF export retains the database-backed document separately.
 */
$headerActions = '<button type="button" class="ui-button ui-button--outline ui-button--icon-only" aria-label="Muat ulang" onclick="location.reload()">' . icon('icon_refresh') . '</button> '
    . (uiCan('Sekolah', 'Manajemen Template', 'pdf') ? '<a href="' . BASE_PATH . '/kurikulum/manajemen-template/' . $template['id'] . '/pdf" class="ui-button ui-button--primary">Simpan PDF</a>' : '');

require VIEW_PATH . '/layouts/shell-header.php';
?>
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/template-preview.css?v=<?= filemtime(ROOT_PATH . '/public/assets/css/template-preview.css') ?>">

<?php require VIEW_PATH . '/admin/template-rapor/_preview-pages.php'; ?>

<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
