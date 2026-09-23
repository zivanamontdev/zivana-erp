<?php
$headerActions = uiButton('Muat ulang', 'outline', ['icon'=>'icon_refresh','iconOnly'=>true,'marginVertical'=>0,'attributes'=>['onclick'=>'location.reload()']]);
if (uiCan('Portal Guru','Daftar Murid','pdf')) {
    $headerActions .= '<a href="' . BASE_PATH . '/portal-guru/rapor/' . (int)$rapor['id'] . '/pdf" class="ui-button ui-button--primary">Simpan PDF</a>';
}
if ($rapor['status'] === 'belum_diisi' && uiCan('Portal Guru','Daftar Murid','edit')) {
    $headerActions .= '<a href="' . BASE_PATH . '/portal-guru/rapor/' . (int)$rapor['id'] . '" class="ui-button ui-button--outline">Kembali Mengisi</a>';
}
require VIEW_PATH . '/layouts/shell-header.php';
?>
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/template-preview.css?v=<?= filemtime(ROOT_PATH . '/public/assets/css/template-preview.css') ?>">
<?php
$previewPages = ReportPreview::pages($areas);
require VIEW_PATH . '/admin/template-rapor/_preview-pages.php';
require VIEW_PATH . '/layouts/shell-footer.php';
?>
