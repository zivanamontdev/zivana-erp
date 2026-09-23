<?php
$passwordSuffix = 'change-' . (int) $employee['id'];
$options = ['variant' => 'form', 'font' => 'geist', 'required' => true, 'type' => 'password', 'icon' => 'icon_eye', 'iconToggle' => true, 'autocomplete' => 'new-password', 'inputAttributes' => ['minlength' => 8]];
ob_start();
?>
<form method="POST" action="<?= BASE_PATH ?>/karyawan/<?= (int) $employee['id'] ?>/kata-sandi" class="modal-body" data-complete-form data-employee-password-policy>
    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
    <?= uiField('password', 'Kata Sandi Karyawan *', array_merge($options, ['id' => 'password-' . $passwordSuffix, 'placeholder' => 'Minimal 8 karakter'])) ?>
    <?= uiField('password_confirmation', 'Ulangi Kata Sandi Karyawan *', array_merge($options, ['id' => 'confirmation-' . $passwordSuffix, 'placeholder' => 'Ulangi kata sandi'])) ?>
    <div class="modal-actions">
        <?= uiButton('Batal', 'outline', ['marginVertical' => 0, 'attributes' => ['data-modal-close' => true]]) ?>
        <?= uiButton('Ubah Kata Sandi', 'outline', ['type' => 'submit', 'disabled' => true, 'marginVertical' => 0, 'attributes' => ['data-complete-submit' => true, 'data-enabled-variant' => 'outline']]) ?>
    </div>
</form>
<?php echo uiModal('modal-kata-sandi-karyawan-' . (int) $employee['id'], 'Ubah Kata Sandi', ob_get_clean()); ?>
