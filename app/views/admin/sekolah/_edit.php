<?php
$schoolField = static function (string $name, string $label, array $options = []) use ($val, $errors): string {
    return uiField($name, $label, array_merge([
        'variant' => 'form', 'font' => 'geist', 'value' => (string) $val($name),
        'error' => $errors[$name] ?? '',
    ], $options));
};
?>
<form method="POST" action="<?= BASE_PATH ?>/sekolah" id="form-sekolah">
    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
    <div class="tabs-panel is-active" data-tab-panel="informasi">
        <div class="sekolah-detail-grid">
            <?= $schoolField('nama_legal', 'Nama Legal Sekolah *', ['placeholder' => 'Isi nama legal sekolah']) ?>
            <?= $schoolField('nama_komersial', 'Nama Komersial Sekolah *', ['placeholder' => 'Isi nama komersial sekolah']) ?>
            <?= uiSelect('bentuk_pendidikan', 'Bentuk Pendidikan *', ['' => 'Pilih bentuk pendidikan', 'KB' => 'Kelompok Bermain (KB)', 'TK' => 'Taman Kanak-Kanak (TK)', 'TPA' => 'Tempat Penitipan Anak (TPA)'], ['value' => $val('bentuk_pendidikan'), 'error' => $errors['bentuk_pendidikan'] ?? '']) ?>
            <?= $schoolField('npsn', 'NPSN *', ['placeholder' => 'Isi NPSN']) ?>
            <?= $schoolField('alamat', 'Alamat Sekolah *', ['type' => 'textarea', 'class' => 'sekolah-detail-full', 'placeholder' => 'Isi alamat sekolah']) ?>
        </div>
    </div>
    <div class="tabs-panel" data-tab-panel="kontak">
        <div class="sekolah-contact-details">
            <div class="sekolah-detail-grid">
                <?= $schoolField('no_telepon', 'No. Telepon Sekolah *', ['placeholder' => 'Isi nomor telepon sekolah']) ?>
                <?= $schoolField('email', 'Email Sekolah *', ['type' => 'email', 'placeholder' => 'Isi alamat email sekolah']) ?>
            </div>
            <div class="sekolah-media" data-assign-list>
                <div class="assign-list-header">
                    <?= uiText('Daftar Media', 'body-sm', ['weight' => 'bold']) ?>
                    <?= uiButton('Tambah Media', 'outline', ['marginVertical' => 0, 'attributes' => ['data-assign-add' => true]]) ?>
                </div>
                <div class="assign-list-rows" data-assign-rows>
                    <?php foreach ($media as $item): ?>
                        <?php require VIEW_PATH . '/admin/sekolah/_media-edit-row.php'; ?>
                    <?php endforeach; ?>
                </div>
                <template data-assign-template>
                    <?php $item = []; require VIEW_PATH . '/admin/sekolah/_media-edit-row.php'; ?>
                </template>
            </div>
        </div>
    </div>
</form>
