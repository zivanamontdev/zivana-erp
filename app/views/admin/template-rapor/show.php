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
$headerActions = '<button type="button" class="btn btn-tertiary" data-pdf-refresh>' . icon('icon_refresh') . '</button> '
    . '<button type="button" class="btn btn-primary" disabled title="[ASUMSI] Library PDF belum diintegrasikan, lihat todo.md Fase 6 item terakhir">Simpan PDF</button>';

require VIEW_PATH . '/layouts/shell-header.php';
?>
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/rapor-document.css">

<div class="rapor-toolbar">
    <span class="rapor-pagination">Halaman 1 dari 4</span>
</div>

<div class="rapor-page">
    <div class="rapor-watermark">
        <img src="<?= BASE_PATH ?>/assets/images/logo-colored.png" alt="">
    </div>

    <div class="rapor-header">
        <div>
            <h1>LAPORAN PERKEMBANGAN TENGAH SEMESTER</h1>
            <p>T.P {Tahun Ajaran}</p>
        </div>
        <img src="<?= BASE_PATH ?>/assets/images/logo-colored.png" alt="<?= e(APP_NAME) ?>">
    </div>

    <div class="rapor-identitas">
        <div>Nama Siswa : {Nama Siswa}</div>
        <div>Kelas : {Kelas Siswa}</div>
        <div>NISN : {NISN Siswa}</div>
    </div>

    <div class="rapor-legenda">
        <span class="text-caption-md font-bold">Keterangan:</span>
        <?php foreach ($legenda as $opsi): ?>
        <div class="rapor-legenda-item">
            <?= renderSkalaSimbol($opsi['simbol']) ?>
            <span><?= e($opsi['label']) ?></span>
        </div>
        <?php endforeach; ?>
    </div>

    <?php foreach ($areas as $area): ?>
    <div class="rapor-area-title"><?= e($area['nama_area']) ?></div>
    <table class="rapor-table">
        <thead>
            <tr>
                <th style="text-align:left">Tujuan</th>
                <th class="col-nilai">TS Ganjil</th>
                <th class="col-nilai">TS Genap</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($area['subkategori'] as $sub): ?>
            <tr class="rapor-subkategori-row">
                <td colspan="3"><?= e($sub['label']) ?>. <?= e($sub['nama']) ?></td>
            </tr>
            <?php foreach ($sub['item'] as $item): ?>
            <tr>
                <td><?= e($item['nama_tujuan']) ?></td>
                <td class="col-nilai">&nbsp;</td>
                <td class="col-nilai">&nbsp;</td>
            </tr>
            <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endforeach; ?>

    <div class="rapor-footer">Laporan Perkembangan Tengah Semester T.P {Tahun Ajaran}</div>
</div>

<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
