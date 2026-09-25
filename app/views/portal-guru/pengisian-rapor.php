<?php
/**
 * Pengisian Rapor — Portal Guru. cookbook/design-system.md 5.4.
 *
 * [FIX TOTAL] Dibangun ulang setelah re-audit langsung assets/ss/Portal
 * Guru - halaman_pengisian_rapor(desktop_mode + mobile_mode).svg —
 * versi sebelumnya salah pakai layout app-shell biasa (dengan sidebar),
 * padahal halaman ini TIDAK punya sidebar/topbar sama sekali (mode
 * fokus). Warna kategori/subkategori juga sebelumnya salah shade.
 * Lihat layouts/focus-header.php dan portal-guru.css untuk detail.
 *
 * Variabel dari PengisianRaporController::show(): $rapor, $areas,
 * $semester, $catatanList, $daftarMuridLain, $totalItem, $terisiItem
 */
$kelasLabel = trim(($rapor['level_kelas'] ?? '') . ' ' . ($rapor['nama_kelas'] ?? '')) ?: '-';
$pageTitle = 'Pengisian Rapor';

// Custom select displays the shared SVG assets; native fallback keeps readable labels.

require VIEW_PATH . '/layouts/focus-header.php';
?>
<?php if (!empty($_SESSION['report_error'])): ?>
<p role="alert"><?= uiText($_SESSION['report_error'], 'body-sm', ['tone'=>'status-inactive']) ?></p>
<?php unset($_SESSION['report_error']); endif; ?>
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/portal-guru.css?v=<?= filemtime(ROOT_PATH . '/public/assets/css/portal-guru.css') ?>">

<a class="ui-button ui-button--outline teacher-report-back" href="<?= BASE_PATH ?>/portal-guru/dashboard?sesi_id=<?= (int)$rapor['sesi_pembagian_id'] ?>">
    <span class="ui-button-label">Kembali ke Dashboard</span>
</a>

<form method="POST" action="<?= BASE_PATH ?>/portal-guru/rapor/<?= (int) $rapor['id'] ?>/simpan" data-report-entry>
    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">

    <div class="pengisian-header-card">
        <div class="pengisian-header-top">
            <h1>Pengisian Rapor</h1>
            <div class="pengisian-header-actions">
<?php if (uiCan('Portal Guru', 'Daftar Murid', 'edit')): ?>
                <?= uiButton('Arsip Rapor', 'outline', ['type'=>'submit','marginVertical'=>0,'attributes'=>['formaction'=>BASE_PATH . '/portal-guru/rapor/' . (int)$rapor['id'] . '/arsipkan']]) ?>
<?php endif; ?>
<?php if (uiCan('Portal Guru', 'Daftar Murid', 'kirim')): ?>
                <?= uiButton('Selesaikan Rapor', 'primary', ['type'=>'submit','marginVertical'=>0,'disabled'=>$totalItem===0 || $terisiItem!==$totalItem,'attributes'=>['data-report-submit'=>true,'formaction'=>BASE_PATH . '/portal-guru/rapor/' . (int)$rapor['id'] . '/selesaikan']]) ?>
<?php endif; ?>
            </div>
        </div>
        <p class="pengisian-student-name"><?= e($rapor['nama_lengkap']) ?></p>

        <p class="pengisian-rapor-warning">
            Pastikan tiap penilaian sudah benar sebelum diselesaikan. Rapor akan terkunci setelah dikirim untuk persetujuan Kepala Sekolah/Admin.
            Mengisi untuk: <strong><?= $semester === 'genap' ? 'TS Genap' : 'TS Ganjil' ?></strong>
        </p>

        <div class="pengisian-rapor-progress">
            <span class="pengisian-rapor-progress-label">Progress:</span>
            <div class="pengisian-rapor-progress-bar">
                <div class="pengisian-rapor-progress-bar-fill" data-report-progress style="width:<?= $totalItem > 0 ? round($terisiItem / $totalItem * 100) : 0 ?>%"></div>
            </div>
            <span class="pengisian-rapor-progress-label" data-report-count><?= $terisiItem ?> dari <?= $totalItem ?></span>
        </div>

    </div>

    <?php foreach ($areas as $area): ?>
    <details class="pengisian-kategori ui-disclosure">
        <?php // [FIX] nama_area disimpan ALL CAPS di DB (sesuai konvensi dokumen
        // cetak — lihat rapor-document.css), tapi form Pengisian Rapor
        // menampilkannya Title Case sesuai SVG. Transform tampilan saja,
        // nilai tersimpan tidak diubah. ?>
        <summary class="pengisian-kategori-header ui-disclosure-trigger"><span><?= e(mb_convert_case($area['nama_area'], MB_CASE_TITLE, 'UTF-8')) ?></span><span class="ui-disclosure-chevron" aria-hidden="true"><?= icon('icon_chevron') ?></span></summary>
        <div class="pengisian-disclosure-content">

        <?php foreach ($area['subkategori'] as $sub): ?>
        <details class="pengisian-subkategori ui-disclosure">
        <summary class="pengisian-subkategori-header ui-disclosure-trigger"><span>Tujuan – <?= e($sub['nama']) ?></span><span class="ui-disclosure-chevron" aria-hidden="true"><?= icon('icon_chevron') ?></span></summary>
        <div class="pengisian-disclosure-content">

        <?php foreach ($sub['item'] as $item): ?>
        <div class="pengisian-item-row">
            <span><?= e($item['nama_tujuan']) ?></span>
            <?php
            $choices = ['' => 'Pilih jawaban anda'];
            $optionImages = [];
            foreach ($item['opsi'] as $opsi) {
                $choices[$opsi['id']] = $opsi['label'];
                $optionImages[$opsi['id']] = skalaSimbolSrc($opsi['simbol']);
            }
            echo uiSelect('nilai[' . (int)$item['id'] . ']', $item['nama_tujuan'], $choices, [
                'id'=>'report-value-' . (int)$item['id'], 'value'=>$item['nilai_opsi_id'] ?? '',
                'hideLabel'=>true, 'attributes'=>['data-report-value'=>true],
                'optionImages'=>$optionImages,
            ]);
            ?>
        </div>
        <?php endforeach; ?>
        </div>
        </details>
        <?php endforeach; ?>

        <div class="pengisian-catatan-guru">
            <?= uiField('catatan[' . (int)$area['id'] . ']', 'Catatan Guru', ['type'=>'textarea','id'=>'report-note-' . (int)$area['id'],'value'=>$catatanList[$area['id']] ?? '', 'placeholder'=>'Masukkan catatan guru', 'attributes'=>['rows'=>3]]) ?>
        </div>
        </div>
    </details>
    <?php endforeach; ?>

    <?php if ($totalItem === 0): ?>
    <p role="status"><?= uiText('Template rapor ini belum memiliki item penilaian. Hubungi Admin untuk melengkapi template; rapor belum dapat dikirim.', 'body-sm', ['tone'=>'muted']) ?></p>
    <?php endif; ?>

</form>

<script src="<?= BASE_PATH ?>/assets/js/ui-select.js?v=<?= filemtime(ROOT_PATH . '/public/assets/js/ui-select.js') ?>"></script>
<script src="<?= BASE_PATH ?>/assets/js/report-entry.js?v=<?= filemtime(ROOT_PATH . '/public/assets/js/report-entry.js') ?>"></script>
<?php require VIEW_PATH . '/layouts/focus-footer.php'; ?>
