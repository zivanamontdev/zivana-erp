<?php
/**
 * Partial isi dokumen rapor MURID SUNGGUHAN (data asli, bukan
 * placeholder) — dipakai bersama show.php (preview web) dan
 * RaporMuridController::downloadPdf().
 *
 * Variabel yang harus di-set: $rapor, $areas, $legenda
 * Variabel opsional: $forPdf (bool, default false)
 */
$forPdf = $forPdf ?? false;
$logoSrc = assetSrc('images/logo-colored.png', $forPdf);
$kelasLabel = trim(($rapor['level_kelas'] ?? '') . ' ' . ($rapor['nama_kelas'] ?? '')) ?: '-';
?>
<div class="rapor-page">
    <div class="rapor-watermark">
        <img src="<?= $logoSrc ?>" alt="">
    </div>

    <div class="rapor-header">
        <table><tr>
            <td style="border:none; padding:0;">
                <h1>LAPORAN PERKEMBANGAN TENGAH SEMESTER</h1>
                <p>T.P {Tahun Ajaran}</p>
            </td>
            <td style="border:none; padding:0; text-align:right; width:80px;">
                <img src="<?= $logoSrc ?>" alt="<?= e(APP_NAME) ?>">
            </td>
        </tr></table>
    </div>

    <div class="rapor-identitas">
        <div>Nama Siswa : <?= e($rapor['nama_lengkap']) ?></div>
        <div>Kelas : <?= e($kelasLabel) ?></div>
        <div>NISN : <?= e($rapor['nisn'] ?: '-') ?></div>
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
                <td class="col-nilai"><?= $item['nilai_ganjil'] ? renderSkalaSimbol($item['nilai_ganjil']) : '&nbsp;' ?></td>
                <td class="col-nilai"><?= $item['nilai_genap'] ? renderSkalaSimbol($item['nilai_genap']) : '&nbsp;' ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endforeach; ?>

    <div class="rapor-footer">Laporan Perkembangan Tengah Semester T.P {Tahun Ajaran}</div>
</div>
