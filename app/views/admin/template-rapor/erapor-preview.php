<?php
/**
 * Pratinjau satu template rubrik eRapor. PDF-nya dirender oleh EraporPackagePdfRenderer yang sama dengan rapor terbit.
 * Variabel dari TemplateRaporController::eraporPreview(): $rubric, $semester (GANJIL|GENAP), $canPdf
 */
$slug = strtolower($rubric['jenis_dokumen']);
$base = BASE_PATH . '/kurikulum/manajemen-template/erapor/' . e($slug);
$query = 'semester=' . strtolower($semester);
$headerActions = '<form method="GET" action="' . $base . '" class="list-filter">'
    . uiFilter('semester', 'Semester', ['ganjil' => 'Semester Ganjil', 'genap' => 'Semester Genap'], ['value' => strtolower($semester), 'marginVertical' => 0, 'attributes' => ['onchange' => 'this.form.submit()']])
    . '</form>'
    . ($canPdf ? ' <a href="' . $base . '/pdf?unduh=1&amp;' . $query . '" class="ui-button ui-button--primary">Simpan PDF</a>' : '');
require VIEW_PATH . '/layouts/shell-header.php';
?>
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/manajemen-rapor.css?v=<?= filemtime(ROOT_PATH . '/public/assets/css/manajemen-rapor.css') ?>">

<p class="report-template-note">Identitas ditampilkan sebagai penanda seperti <strong>{Nama Siswa}</strong> dan <strong>{Kelas}</strong>; saat rapor diterbitkan, penanda diganti data murid, nilai guru, dan tanda tangan penyetuju.</p>
<iframe class="report-template-frame" src="<?= $base ?>/pdf?<?= $query ?>" title="Pratinjau PDF <?= e($rubric['nama']) ?>"></iframe>
<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
