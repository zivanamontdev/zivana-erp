<?php
$editing = $position !== null;
$suffix = $editing ? 'edit-' . (int) $position['id'] : 'create';
$modalId = $editing ? 'modal-ubah-jabatan-' . (int) $position['id'] : 'modal-tambah-jabatan';
$title = $editing ? 'Ubah Jabatan' : 'Tambah Jabatan';
$variant = $editing ? 'outline' : 'primary';
ob_start();
?>
<form method="POST" action="<?= BASE_PATH ?>/jabatan<?= $editing ? '/' . (int) $position['id'] : '' ?>" class="modal-body" data-complete-form>
    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
    <?= uiSelect('nama', 'Nama Jabatan *', ['' => 'Pilih jabatan'] + array_combine(Jabatan::NAMES, Jabatan::NAMES), ['id' => 'position-name-' . $suffix, 'value' => $position['nama'] ?? '', 'required' => true]) ?>
    <?= uiSelect('role_id', 'Role Sistem *', ['' => 'Pilih role sistem'] + array_column($roleOptions, 'nama', 'id'), ['id' => 'position-role-' . $suffix, 'value' => $position['role_id'] ?? '', 'required' => true]) ?>
    <div class="modal-actions">
        <?= uiButton('Batal', 'outline', ['marginVertical' => 0, 'attributes' => ['data-modal-close' => true]]) ?>
        <?= uiButton($title, $variant, ['type' => 'submit', 'disabled' => true, 'marginVertical' => 0, 'attributes' => ['data-complete-submit' => true, 'data-enabled-variant' => $variant]]) ?>
    </div>
</form>
<?php echo uiModal($modalId, $title, ob_get_clean()); ?>
