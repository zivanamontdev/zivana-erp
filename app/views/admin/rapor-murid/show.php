<?php
/**
 * Pratinjau Rapor Murid (Admin) — sama struktur dengan Pratinjau
 * Template tapi placeholder sudah diganti data murid sungguhan +
 * nilai yang sudah diisi guru (kalau ada).
 *
 * Variabel dari RaporMuridController::show(): $rapor, $areas, $legenda
 */
$headerActions = '<button type="button" class="btn btn-tertiary" onclick="location.reload()">' . icon('icon_refresh') . '</button> '
    . '<a href="' . BASE_PATH . '/rapor-murid/' . $rapor['id'] . '/pdf" class="btn btn-primary">Simpan PDF</a>';

require VIEW_PATH . '/layouts/shell-header.php';
?>
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/rapor-document.css">

<div class="rapor-toolbar">
    <span class="rapor-pagination">Halaman 1 dari 4</span>
</div>

<?php
$forPdf = false;
require VIEW_PATH . '/admin/rapor-murid/_document.php';
require VIEW_PATH . '/layouts/shell-footer.php';
?>
