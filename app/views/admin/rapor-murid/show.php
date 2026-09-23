<?php
/**
 * Pratinjau Rapor Murid (Admin) — sama struktur dengan Pratinjau
 * Template tapi placeholder sudah diganti data murid sungguhan +
 * nilai yang sudah diisi guru (kalau ada).
 *
 * Variabel dari RaporMuridController::show(): $rapor, $areas, $legenda
 */
$headerActions = uiButton('Muat ulang', 'outline', ['icon'=>'icon_refresh', 'iconOnly'=>true, 'marginVertical'=>0, 'attributes'=>['onclick'=>'location.reload()']])
    . (uiCan('Murid', 'Rapor Murid', 'pdf') ? '<a href="' . BASE_PATH . '/rapor-murid/' . (int) $rapor['id'] . '/pdf" class="ui-button ui-button--outline">Simpan PDF</a>' : '');
if ($canApprove && $rapor['status'] === 'menunggu_persetujuan') {
    $headerActions .= '<form method="POST" action="' . BASE_PATH . '/rapor-murid/' . (int) $rapor['id'] . '/setujui"><input type="hidden" name="csrf_token" value="' . e(getCsrfToken()) . '">'
        . uiButton('Setujui', 'primary', ['type'=>'submit','marginVertical'=>0]) . '</form>';
}

require VIEW_PATH . '/layouts/shell-header.php';
?>
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/template-preview.css?v=<?= filemtime(ROOT_PATH . '/public/assets/css/template-preview.css') ?>">

<?php
$previewPages = ReportPreview::pages($areas);
require VIEW_PATH . '/admin/template-rapor/_preview-pages.php';
require VIEW_PATH . '/layouts/shell-footer.php';
?>
