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
$kelasOptions = $kelasOptions ?? [];

$val = function (string $field) use ($murid, $old) {
    return array_key_exists($field, $old) ? $old[$field] : ($murid[$field] ?? '');
};

/** Shared primitives for create/edit; data cards for every detail value. */
$field = function (string $name, string $label, string $type = 'text', bool $required = true, bool $fullWidth = false) use ($isDetail, $val, $errors) {
    $value = (string) $val($name);
    if ($isDetail) return uiDataCard($label, $value, ['class' => $fullWidth ? 'field-full' : '']);
    $options = ['variant' => 'form', 'font' => 'geist', 'type' => $type,
        'value' => $value, 'required' => $required, 'error' => $errors[$name] ?? '',
        'class' => $fullWidth ? 'field-full' : '', 'placeholder' => 'Isi ' . mb_strtolower($label)];
    if ($type === 'date') $options += ['icon' => 'icon_calendar', 'iconCalendar' => true];
    return uiField($name, $label . ($required ? ' *' : ''), $options);
};
$selectField = function (string $name, string $label, array $options, bool $required = true) use ($isDetail, $val, $errors) {
    $value = (string) $val($name);
    if ($isDetail) return uiDataCard($label, $options[$value] ?? $value);
    return uiSelect($name, $label . ($required ? ' *' : ''),
        ['' => 'Pilih ' . mb_strtolower($label)] + $options,
        ['value' => $value, 'required' => $required, 'error' => $errors[$name] ?? '']);
};

$agamaOptions = ['Islam' => 'Islam', 'Kristen Protestan' => 'Kristen Protestan', 'Katolik' => 'Katolik', 'Hindu' => 'Hindu', 'Buddha' => 'Buddha', 'Konghucu' => 'Konghucu'];
$jenisKelaminOptions = ['L' => 'Laki-laki', 'P' => 'Perempuan'];
// Keep stored values compatible with existing student records.
$statusKondisiOptions = ['Reguler' => 'Regular', 'Berkebutuhan Khusus' => 'ABK (Anak Berkebutuhan Khusus)'];
$selectedClassId = (string) $val('kelas_id');
$selectedLevel = '';
$classChoices = ['' => 'Pilih kelas'];
$classLevels = [];
foreach ($kelasOptions as $classOption) {
    $classChoices[$classOption['id']] = $classOption['nama_kelas'];
    $classLevels[$classOption['id']] = $classOption['level_kelas'];
    if ((string) $classOption['id'] === $selectedClassId) $selectedLevel = $classOption['level_kelas'];
}

$formAction = $isTambah ? BASE_PATH . '/murid' : BASE_PATH . '/murid/' . $muridId;

$headerActions = '';
if ($isDetail && $canEdit) {
    $headerActions = '<a href="' . BASE_PATH . '/murid/' . $muridId . '/ubah" class="ui-button ui-button--primary">Ubah Data Murid</a>';
} elseif (!$isDetail) {
    $headerActions = uiButton($isTambah ? 'Simpan Data Murid' : 'Simpan Perubahan', 'primary', ['type' => 'submit', 'marginVertical' => 0, 'attributes' => ['form' => 'form-murid']]);
}

require VIEW_PATH . '/layouts/shell-header.php';
?>
<div class="tabs student-record-tabs" data-tabs>
    <div class="tabs-nav">
        <?= uiButton('Data Murid', 'tabular-active', ['marginVertical' => 0, 'class' => 'is-active', 'attributes' => ['data-tab-target' => 'data-murid']]) ?>
        <?= uiButton('Informasi Pendaftaran', 'tabular-inactive', ['marginVertical' => 0, 'class' => '', 'attributes' => ['data-tab-target' => 'informasi']]) ?>
        <?= uiButton('Relasi & Kontak', 'tabular-inactive', ['marginVertical' => 0, 'class' => '', 'attributes' => ['data-tab-target' => 'relasi']]) ?>
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
            <?php
            $age = '';
            $birthDateValue = (string) $val('tanggal_lahir');
            $birthDate = DateTimeImmutable::createFromFormat('!Y-m-d', $birthDateValue);
            $today = new DateTimeImmutable('today');
            if ($birthDate && $birthDate->format('Y-m-d') === $birthDateValue && $birthDate <= $today) {
                $age = (string) $birthDate->diff($today)->y;
            }
            echo $isDetail ? uiDataCard('Umur', $age)
                : uiField('umur', 'Umur', ['variant' => 'form', 'font' => 'geist', 'disabled' => true, 'value' => $age]);
            ?>
        </div>
        <div class="field-row">
            <?= $field('alamat', 'Alamat', 'textarea', true, true) ?>
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
            <?= uiDataCard('Level Kelas', $murid['level_kelas'] ?? null) ?>
            <?= uiDataCard('Kelas', $murid['nama_kelas'] ?? null) ?>
        </div>
        <?php else: ?>
        <div class="field-row" data-student-class data-class-levels="<?= e(json_encode($classLevels)) ?>">
            <?= uiSelect('kelas_id', 'Kelas *', $classChoices, ['value' => $selectedClassId, 'required' => true, 'error' => $errors['kelas_id'] ?? '']) ?>
            <?= uiSelect('level_kelas', 'Level Kelas *', ['' => 'Pilih kelas terlebih dahulu'] + array_combine(Kelas::LEVELS, Kelas::LEVELS), ['value' => $selectedLevel, 'required' => true]) ?>
        </div>
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
            <?= $field('telp_ayah', 'No. Telp', 'tel') ?>
        </div>

        <h3 class="text-body-sm font-bold">Informasi Ibu</h3>
        <div class="field-row">
            <?= $field('nama_ibu', 'Nama Ibu') ?>
            <?= $field('pendidikan_ibu', 'Pendidikan Terakhir') ?>
        </div>
        <div class="field-row">
            <?= $field('pekerjaan_ibu', 'Pekerjaan') ?>
            <?= $field('telp_ibu', 'No. Telp', 'tel') ?>
        </div>
    </div>

    <?php if (!$isDetail): ?></form><?php endif; ?>
</div>

<?php if (!$isDetail): ?>
<script src="<?= BASE_PATH ?>/assets/js/murid-age.js?v=<?= filemtime(ROOT_PATH . '/public/assets/js/murid-age.js') ?>"></script>
<script src="<?= BASE_PATH ?>/assets/js/murid-class.js?v=<?= filemtime(ROOT_PATH . '/public/assets/js/murid-class.js') ?>"></script>
<?php endif; ?>
<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
