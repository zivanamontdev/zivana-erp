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

// [ASUMSI] Native <select><option> tidak bisa merender bentuk SVG
// (renderSkalaSimbol dipakai di dokumen/pratinjau). Dikonfirmasi dari
// SVG, dropdown di form ini menampilkan karakter simbol + label sekaligus
// (mis. "/ (Baru dikenalkan)") — didekati pakai karakter unicode yang
// bentuknya paling mendekati tiap simbol.
$simbolChar = [
    'slash' => '/',
    'triangle-sm' => '▵',
    'triangle-lg' => '△',
    'triangle-full' => '▲',
];

require VIEW_PATH . '/layouts/focus-header.php';
?>
<?php if (!empty($_SESSION['report_error'])): ?>
<p role="alert"><?= uiText($_SESSION['report_error'], 'body-sm', ['tone'=>'status-inactive']) ?></p>
<?php unset($_SESSION['report_error']); endif; ?>
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/portal-guru.css?v=<?= filemtime(ROOT_PATH . '/public/assets/css/portal-guru.css') ?>">

<form method="POST" action="<?= BASE_PATH ?>/portal-guru/rapor/<?= (int) $rapor['id'] ?>/simpan">
    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">

    <div class="pengisian-header-card">
        <h1>Pengisian Rapor</h1>

        <div class="field">
            <label class="field-label" for="rapor-murid-switcher">Nama Murid</label>
            <?php
            $currentMuridUrl = BASE_PATH . '/portal-guru/rapor/' . (int) $rapor['id'];
            $muridChoices = [$currentMuridUrl => $rapor['nama_lengkap'] . ' — ' . $kelasLabel];
            foreach ($daftarMuridLain as $lain) {
                $muridChoices[BASE_PATH . '/portal-guru/rapor/' . (int) $lain['id']] = $lain['nama_lengkap'];
            }
            echo uiFilter('murid_switcher', 'Nama Murid', $muridChoices, [
                'id' => 'rapor-murid-switcher', 'value' => $currentMuridUrl, 'marginVertical' => 0,
                'attributes' => ['onchange' => 'if (this.value) window.location.href = this.value;'],
            ]);
            ?>
        </div>

        <p class="pengisian-rapor-warning">
            Pastikan tiap penilaian sudah benar sebelum diselesaikan. Penilaian rapor yang telah selesai dan diapprove oleh Kepala Sekolah tidak dapat diubah kembali.
            Mengisi untuk: <strong><?= $semester === 'genap' ? 'TS Genap' : 'TS Ganjil' ?></strong>
        </p>

        <div class="pengisian-rapor-progress">
            <span class="pengisian-rapor-progress-label">Progress:</span>
            <div class="pengisian-rapor-progress-bar">
                <div class="pengisian-rapor-progress-bar-fill" style="width:<?= $totalItem > 0 ? round($terisiItem / $totalItem * 100) : 0 ?>%"></div>
            </div>
            <span class="pengisian-rapor-progress-label"><?= $terisiItem ?> dari <?= $totalItem ?></span>
        </div>

        <div class="pengisian-header-actions">
            <button type="submit" class="ui-button ui-button--outline">Arsip Rapor</button>
            <button type="submit" formaction="<?= BASE_PATH ?>/portal-guru/rapor/<?= (int) $rapor['id'] ?>/selesaikan" class="ui-button ui-button--primary">Selesaikan Rapor</button>
        </div>
    </div>

    <?php foreach ($areas as $area): ?>
    <div class="pengisian-kategori">
        <?php // [FIX] nama_area disimpan ALL CAPS di DB (sesuai konvensi dokumen
        // cetak — lihat rapor-document.css), tapi form Pengisian Rapor
        // menampilkannya Title Case sesuai SVG. Transform tampilan saja,
        // nilai tersimpan tidak diubah. ?>
        <div class="pengisian-kategori-header"><?= e(mb_convert_case($area['nama_area'], MB_CASE_TITLE, 'UTF-8')) ?></div>

        <?php foreach ($area['subkategori'] as $sub): ?>
        <div class="pengisian-subkategori-header">Tujuan – <?= e($sub['nama']) ?></div>

        <?php foreach ($sub['item'] as $item): ?>
        <div class="pengisian-item-row">
            <span><?= e($item['nama_tujuan']) ?></span>
            <select name="nilai[<?= (int) $item['id'] ?>]" class="field-input">
                <option value="">Pilih jawaban anda</option>
                <?php foreach ($item['opsi'] as $opsi): ?>
                <option value="<?= (int) $opsi['id'] ?>" <?= (int) $item['nilai_opsi_id'] === (int) $opsi['id'] ? 'selected' : '' ?>><?= e(($simbolChar[$opsi['simbol']] ?? '') . ' (' . $opsi['label'] . ')') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endforeach; ?>
        <?php endforeach; ?>

        <div class="pengisian-catatan-guru">
            <label class="field-label">Catatan Guru</label>
            <textarea name="catatan[<?= (int) $area['id'] ?>]" class="field-input" rows="3" placeholder="Masukkan jawaban anda"><?= e($catatanList[$area['id']] ?? '') ?></textarea>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="pengisian-actions">
        <button type="submit" class="ui-button ui-button--outline">Arsip Rapor</button>
        <button type="submit" formaction="<?= BASE_PATH ?>/portal-guru/rapor/<?= (int) $rapor['id'] ?>/selesaikan" class="ui-button ui-button--primary">Selesaikan Rapor</button>
    </div>
</form>

<?php require VIEW_PATH . '/layouts/focus-footer.php'; ?>
