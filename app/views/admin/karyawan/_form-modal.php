<?php
$editing = $employee !== null;
$suffix = $editing ? 'edit-' . (int) $employee['id'] : 'create';
$modalId = $editing ? 'modal-ubah-karyawan-' . (int) $employee['id'] : 'modal-tambah-karyawan';
$title = $editing ? 'Ubah Karyawan' : 'Tambah Karyawan';
$variant = $editing ? 'outline' : 'primary';
$fieldOptions = ['variant' => 'form', 'font' => 'geist', 'required' => true];
$passwordOptions = array_merge($fieldOptions, ['type' => 'password', 'icon' => 'icon_eye', 'iconToggle' => true, 'autocomplete' => 'new-password', 'inputAttributes' => ['minlength' => 8]]);
ob_start();
?>
<form method="POST" action="<?= BASE_PATH ?>/karyawan<?= $editing ? '/' . (int) $employee['id'] : '' ?>" class="modal-body" data-complete-form data-employee-password-policy>
    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
    <?= uiField('nama', 'Nama Karyawan *', array_merge($fieldOptions, ['id' => 'employee-name-' . $suffix, 'value' => $employee['nama'] ?? '', 'placeholder' => 'Isi nama karyawan'])) ?>
    <?= uiSelect('jabatan_id', 'Jabatan *', ['' => 'Pilih jabatan karyawan'] + array_column($jabatanOptions, 'nama', 'id'), ['id' => 'employee-role-' . $suffix, 'required' => true, 'value' => $employee['jabatan_id'] ?? '']) ?>
    <?= uiField('email', 'Email Karyawan *', array_merge($fieldOptions, ['id' => 'employee-email-' . $suffix, 'type' => 'email', 'value' => $employee['email'] ?? '', 'placeholder' => 'Isi email karyawan'])) ?>
    <?php if (!$editing): ?>
        <?= uiField('password', 'Kata Sandi Karyawan *', array_merge($passwordOptions, ['id' => 'employee-password-' . $suffix, 'placeholder' => 'Minimal 8 karakter'])) ?>
        <?= uiField('password_confirmation', 'Ulangi Kata Sandi Karyawan *', array_merge($passwordOptions, ['id' => 'employee-confirm-' . $suffix, 'placeholder' => 'Ulangi kata sandi'])) ?>
    <?php endif; ?>
    <div class="modal-actions">
        <?= uiButton('Batal', 'outline', ['marginVertical' => 0, 'attributes' => ['data-modal-close' => true]]) ?>
        <?= uiButton($title, $variant, ['type' => 'submit', 'disabled' => true, 'marginVertical' => 0, 'attributes' => ['data-complete-submit' => true, 'data-enabled-variant' => $variant]]) ?>
    </div>
</form>
<?php echo uiModal($modalId, $title, ob_get_clean()); ?>
