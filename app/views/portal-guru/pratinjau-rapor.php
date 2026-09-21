<?php
/**
 * Pratinjau Rapor Murid — versi Guru, sebelum/sesudah submit.
 * Struktur dokumen sama persis dengan Pratinjau Rapor Murid Admin,
 * karena itu partial _document.php dipakai ulang (lihat prinsip
 * "jangan duplikasi kalau tidak perlu" — cuma toolbar/aksi yang beda).
 *
 * Variabel dari PengisianRaporController::pratinjau(): $rapor, $areas, $legenda
 */
$headerActions = $rapor['status'] === 'belum_diisi'
    ? '<a href="' . BASE_PATH . '/portal-guru/rapor/' . (int) $rapor['id'] . '" class="btn btn-tertiary">Kembali Mengisi</a>'
    : '';

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
