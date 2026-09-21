<?php
/**
 * Pengisian Rapor — Portal Guru. cookbook/design-system.md 5.4.
 * Struktur berjenjang Kategori (band merah) -> Sub-kategori (band
 * oranye) -> item dengan satu dropdown skala nilai per baris, diakhiri
 * textarea "Catatan Guru" per kategori.
 *
 * [ASUMSI] Crawl menempatkan tombol aksi di toolbar atas pada desktop
 * dan pindah ke sticky bar bawah hanya di mobile. Di sini disederhanakan
 * jadi sticky bar bawah di semua ukuran layar — perilaku sama-sama
 * jelas dan lebih sedikit kode, tidak mengubah alur fungsional.
 *
 * Variabel dari PengisianRaporController::show(): $rapor, $areas,
 * $semester, $catatanList, $daftarMuridLain, $totalItem, $terisiItem
 */
$kelasLabel = trim(($rapor['level_kelas'] ?? '') . ' ' . ($rapor['nama_kelas'] ?? '')) ?: '-';
$headerActions = '';
require VIEW_PATH . '/layouts/shell-header.php';
?>
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/portal-guru.css">

<div class="field" style="max-width:320px;">
    <label class="field-label">Nama Murid</label>
    <select class="field-input" onchange="if (this.value) window.location.href = this.value;">
        <option value="<?= BASE_PATH ?>/portal-guru/rapor/<?= (int) $rapor['id'] ?>" selected>
            <?= e($rapor['nama_lengkap']) ?> — <?= e($kelasLabel) ?>
        </option>
        <?php foreach ($daftarMuridLain as $lain): ?>
        <option value="<?= BASE_PATH ?>/portal-guru/rapor/<?= (int) $lain['id'] ?>"><?= e($lain['nama_lengkap']) ?></option>
        <?php endforeach; ?>
    </select>
</div>

<div class="pengisian-rapor-warning">
    Pastikan tiap penilaian sudah benar sebelum diselesaikan. Penilaian rapor yang telah selesai dan diapprove oleh Kepala Sekolah tidak dapat diubah kembali.
    <br>Mengisi untuk: <strong><?= $semester === 'genap' ? 'TS Genap' : 'TS Ganjil' ?></strong>
</div>

<div class="pengisian-rapor-progress">
    <span class="text-caption-md"><?= $terisiItem ?>/<?= $totalItem ?> terisi</span>
    <div class="pengisian-rapor-progress-bar">
        <div class="pengisian-rapor-progress-bar-fill" style="width:<?= $totalItem > 0 ? round($terisiItem / $totalItem * 100) : 0 ?>%"></div>
    </div>
</div>

<form method="POST" action="<?= BASE_PATH ?>/portal-guru/rapor/<?= (int) $rapor['id'] ?>/simpan">
    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">

    <?php foreach ($areas as $area): ?>
    <div class="pengisian-kategori">
        <div class="pengisian-kategori-header"><?= e($area['nama_area']) ?></div>

        <?php foreach ($area['subkategori'] as $sub): ?>
        <div class="pengisian-subkategori-header"><?= e($sub['label']) ?>. <?= e($sub['nama']) ?></div>

        <?php foreach ($sub['item'] as $item): ?>
        <div class="pengisian-item-row">
            <span><?= e($item['nama_tujuan']) ?></span>
            <select name="nilai[<?= (int) $item['id'] ?>]" class="field-input">
                <option value="">Pilih penilaian</option>
                <?php foreach ($item['opsi'] as $opsi): ?>
                <option value="<?= (int) $opsi['id'] ?>" <?= (int) $item['nilai_opsi_id'] === (int) $opsi['id'] ? 'selected' : '' ?>><?= e($opsi['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endforeach; ?>
        <?php endforeach; ?>

        <div class="pengisian-catatan-guru">
            <label class="field-label">Catatan Guru</label>
            <textarea name="catatan[<?= (int) $area['id'] ?>]" class="field-input" rows="3"><?= e($catatanList[$area['id']] ?? '') ?></textarea>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="pengisian-actions">
        <button type="submit" class="btn btn-tertiary">Arsip Rapor</button>
        <button type="submit" formaction="<?= BASE_PATH ?>/portal-guru/rapor/<?= (int) $rapor['id'] ?>/selesaikan" class="btn btn-primary">Selesaikan Rapor</button>
    </div>
</form>

<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
