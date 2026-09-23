<?php
$editing = $classRecord !== null;
$suffix = $editing ? 'edit-' . (int) $classRecord['id'] : 'create';
$title = $editing ? 'Ubah Kelas' : 'Tambah Kelas';
$variant = $editing ? 'outline' : 'primary';
$modalId = $editing ? 'modal-ubah-kelas-' . (int) $classRecord['id'] : 'modal-tambah-kelas';
ob_start();
?>
<form method="POST" action="<?= BASE_PATH ?>/kelas<?= $editing ? '/' . (int) $classRecord['id'] : '' ?>" class="modal-body" data-complete-form>
    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
    <?php if (!empty($returnToDetail)): ?><input type="hidden" name="return_to" value="detail"><?php endif; ?>
    <?= uiSelect('level_kelas', 'Level Kelas *', ['' => 'Pilih level kelas'] + array_combine(Kelas::LEVELS, Kelas::LEVELS), ['id' => 'class-level-' . $suffix, 'value' => $classRecord['level_kelas'] ?? '', 'required' => true]) ?>
    <?= uiField('nama_kelas', 'Nama Kelas *', ['variant' => 'form', 'font' => 'geist', 'id' => 'class-name-' . $suffix, 'value' => $classRecord['nama_kelas'] ?? '', 'placeholder' => 'Isi nama kelas', 'required' => true]) ?>
    <div class="modal-actions">
        <?= uiButton('Batal', 'outline', ['marginVertical' => 0, 'attributes' => ['data-modal-close' => true]]) ?>
        <?= uiButton($editing ? 'Simpan Perubahan' : 'Tambah Kelas', $variant, ['type' => 'submit', 'marginVertical' => 0, 'disabled' => true, 'attributes' => ['data-complete-submit' => true, 'data-enabled-variant' => $variant]]) ?>
    </div>
</form>
<?php echo uiModal($modalId, $title, ob_get_clean()); ?>
