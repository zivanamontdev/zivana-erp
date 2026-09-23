<?php
/**
 * Form Murid — dipakai untuk 3 mode: tambah, ubah, detail.
 * cookbook/design-system.md + cookbook/schema.md bagian 5 (Murid).
 * 3 tab: Data Murid, Informasi Pendaftaran, Relasi & Kontak.
 *
 * Variabel dari MuridController: $mode, $muridId, $murid, $old, $errors, $canEdit
 */
$isDetail = $mode === 'detail';
$isTambah = $mode === 'tambah';

$val = function (string $field) use ($murid, $old) {
    return array_key_exists($field, $old) ? $old[$field] : ($murid[$field] ?? '');
};

/** Render satu field text/date/number secara konsisten di 3 mode. */
$field = function (string $name, string $label, string $type = 'text', bool $required = true, bool $fullWidth = false) use ($isDetail, $val, $errors) {
    $value = (string) $val($name);
    $hasError = isset($errors[$name]);
    $class = $fullWidth ? 'field field-full' : 'field';
    ob_start();
    ?>
    <div class="<?= $class ?>">
        <label class="field-label"><?= e($label) ?><?= $required ? ' *' : '' ?></label>
        <?php if ($isDetail): ?>
        <div class="field-input is-viewonly"><?= e($value !== '' ? $value : '-') ?></div>
        <?php else: ?>
        <input type="<?= $type ?>" name="<?= $name ?>" class="field-input<?= $hasError ? ' is-negative' : '' ?>" value="<?= e($value) ?>">
        <?php if ($hasError): ?><span class="field-error"><?= e($errors[$name]) ?></span><?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
};

/** Render satu field dropdown (select) secara konsisten di 3 mode. */
$selectField = function (string $name, string $label, array $options, bool $required = true) use ($isDetail, $val, $errors) {
    $value = (string) $val($name);
    $hasError = isset($errors[$name]);
    ob_start();
    ?>
    <div class="field">
        <label class="field-label"><?= e($label) ?><?= $required ? ' *' : '' ?></label>
        <?php if ($isDetail): ?>
        <div class="field-input is-viewonly"><?= e($options[$value] ?? ($value !== '' ? $value : '-')) ?></div>
        <?php else: ?>
        <select name="<?= $name ?>" class="field-input<?= $hasError ? ' is-negative' : '' ?>">
            <option value="">Pilih <?= e(mb_strtolower($label)) ?></option>
            <?php foreach ($options as $optVal => $optLabel): ?>
            <option value="<?= e($optVal) ?>" <?= $value === $optVal ? 'selected' : '' ?>><?= e($optLabel) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($hasError): ?><span class="field-error"><?= e($errors[$name]) ?></span><?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
};

$agamaOptions = ['Islam' => 'Islam', 'Kristen Protestan' => 'Kristen Protestan', 'Katolik' => 'Katolik', 'Hindu' => 'Hindu', 'Buddha' => 'Buddha', 'Konghucu' => 'Konghucu'];
$jenisKelaminOptions = ['L' => 'Laki-laki', 'P' => 'Perempuan'];
// [ASUMSI] Hanya "Reguler" yang terkonfirmasi dari crawling screenshot,
// opsi lain diturunkan dari keberadaan field "Jenis Kebutuhan" di tab
// yang sama, perlu dikonfirmasi ke user.
$statusKondisiOptions = ['Reguler' => 'Reguler', 'Berkebutuhan Khusus' => 'Berkebutuhan Khusus'];

$formAction = $isTambah ? BASE_PATH . '/murid' : BASE_PATH . '/murid/' . $muridId;

$headerActions = '';
if ($isDetail && $canEdit) {
    $headerActions = '<a href="' . BASE_PATH . '/murid/' . $muridId . '/ubah" class="ui-button ui-button--primary">Ubah Data Murid</a>';
} elseif (!$isDetail) {
    $headerActions = '<button type="submit" form="form-murid" class="ui-button ui-button--primary">'
        . ($isTambah ? 'Simpan Data Murid' : 'Simpan Perubahan') . '</button>';
}

require VIEW_PATH . '/layouts/shell-header.php';
?>
<div class="tabs" data-tabs>
    <div class="tabs-nav">
        <button type="button" class="tabs-tab is-active" data-tab-target="data-murid">Data Murid</button>
        <button type="button" class="tabs-tab" data-tab-target="informasi">Informasi Pendaftaran</button>
        <button type="button" class="tabs-tab" data-tab-target="relasi">Relasi &amp; Kontak</button>
    </div>

    <?php if (!$isDetail): ?><form method="POST" action="<?= $formAction ?>" id="form-murid"><?php endif; ?>
    <?php if (!$isDetail): ?><input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>"><?php endif; ?>

    <div class="tabs-panel is-active" data-tab-panel="data-murid">
        <div class="field-row">
            <?= $field('nama_lengkap', 'Nama Lengkap') ?>
            <?= $field('nama_panggilan', 'Nama Panggilan') ?>
        </div>
        <div class="field-row">
            <?= $field('nisn', 'NISN', 'text', false) ?>
            <?= $selectField('agama', 'Agama', $agamaOptions) ?>
        </div>
        <div class="field-row">
            <?= $field('nik', 'NIK') ?>
            <?= $field('no_registrasi_akte', 'No. Registrasi Akte') ?>
        </div>
        <div class="field-row">
            <?= $selectField('jenis_kelamin', 'Jenis Kelamin', $jenisKelaminOptions) ?>
            <?= $field('tempat_lahir', 'Tempat Lahir') ?>
        </div>
        <div class="field-row">
            <?= $field('tanggal_lahir', 'Tanggal Lahir', 'date') ?>
            <div class="field">
                <label class="field-label">Umur</label>
                <div class="field-input is-viewonly"><?php
                    $tglLahir = $val('tanggal_lahir');
                    if ($tglLahir) {
                        $umur = (new DateTime($tglLahir))->diff(new DateTime())->y;
                        echo $umur . ' tahun';
                    } else {
                        echo '-';
                    }
                ?></div>
            </div>
        </div>
        <div class="field-row">
            <div class="field field-full">
                <label class="field-label">Alamat *</label>
                <?php if ($isDetail): ?>
                <div class="field-textarea is-viewonly"><?= nl2br(e($val('alamat') ?: '-')) ?></div>
                <?php else: ?>
                <textarea name="alamat" class="field-textarea<?= isset($errors['alamat']) ? ' is-negative' : '' ?>" placeholder="Isi alamat"><?= e($val('alamat')) ?></textarea>
                <?php if (isset($errors['alamat'])): ?><span class="field-error"><?= e($errors['alamat']) ?></span><?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="tabs-panel" data-tab-panel="informasi">
        <div class="field-row">
            <?= $field('tanggal_masuk_sekolah', 'Tanggal Masuk Sekolah', 'date') ?>
            <?= $selectField('status_kondisi', 'Status Kondisi', $statusKondisiOptions) ?>
        </div>
        <div class="field-row">
            <?= $field('jenis_kebutuhan', 'Jenis Kebutuhan', 'text', false) ?>
            <?= $field('kelengkapan_berkas', 'Kelengkapan Berkas', 'text', false) ?>
        </div>
        <?php if ($isDetail): ?>
        <div class="field-row">
            <div class="field">
                <label class="field-label">Level Kelas</label>
                <div class="field-input is-viewonly"><?= e($murid['level_kelas'] ?? '-') ?></div>
            </div>
            <div class="field">
                <label class="field-label">Kelas</label>
                <div class="field-input is-viewonly"><?= e($murid['nama_kelas'] ?? '-') ?></div>
            </div>
        </div>
        <p class="text-caption-md">Level Kelas dan Kelas diatur lewat modul Manajemen Kelas, bukan dari form ini.</p>
        <?php endif; ?>
    </div>

    <div class="tabs-panel" data-tab-panel="relasi">
        <div class="field-row">
            <?= $field('alamat_domisili', 'Alamat Domisili') ?>
            <?= $field('anak_ke', 'Anak Ke-', 'number', false) ?>
        </div>
        <div class="field-row">
            <?= $field('jumlah_saudara', 'Jumlah Saudara', 'number') ?>
        </div>

        <h3 class="text-body-sm font-bold">Informasi Ayah</h3>
        <div class="field-row">
            <?= $field('nama_ayah', 'Nama Ayah') ?>
            <?= $field('pendidikan_ayah', 'Pendidikan Terakhir') ?>
        </div>
        <div class="field-row">
            <?= $field('pekerjaan_ayah', 'Pekerjaan') ?>
            <?= $field('telp_ayah', 'No. Telp') ?>
        </div>

        <h3 class="text-body-sm font-bold">Informasi Ibu</h3>
        <div class="field-row">
            <?= $field('nama_ibu', 'Nama Ibu') ?>
            <?= $field('pendidikan_ibu', 'Pendidikan Terakhir') ?>
        </div>
        <div class="field-row">
            <?= $field('pekerjaan_ibu', 'Pekerjaan') ?>
            <?= $field('telp_ibu', 'No. Telp') ?>
        </div>
    </div>

    <?php if (!$isDetail): ?></form><?php endif; ?>
</div>

<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
