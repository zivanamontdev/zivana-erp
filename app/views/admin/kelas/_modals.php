<?php if ($canManage): ?>
    <?php foreach ($kelasList as $classRecord): ?>
        <?php if ($canEdit) require __DIR__ . '/_form-modal.php'; ?>
        <?php if ($canDelete): ?>
    <?php ob_start(); ?>
        <form method="POST" action="<?= BASE_PATH ?>/kelas/<?= (int) $classRecord['id'] ?>/hapus">
            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
            <div class="modal-actions">
                <?= uiButton('Batal', 'outline', ['marginVertical' => 0, 'attributes' => ['data-modal-close' => true]]) ?>
                <?= uiButton('Hapus Kelas', 'outline-danger', ['type' => 'submit', 'marginVertical' => 0]) ?>
            </div>
        </form>
        <?php echo uiModal('modal-hapus-kelas-' . (int) $classRecord['id'], 'Hapus Kelas?', ob_get_clean(), [
            'variant' => 'delete',
            'description' => 'Kelas yang telah dihapus akan menghilang dari data  kelas dan tidak dapat diakses atau digunakan kembali. Murid yang masih terkait dengan kelas yang dihapus akan mengosongkan kelas murid terkait. Pastikan data telah dibackup terlebih dahulu sebelum dihapus.',
        ]); ?>
<?php endif; ?>
    <?php endforeach; ?>
    <?php $classRecord = null; if ($canCreate) require __DIR__ . '/_form-modal.php'; ?>
<?php endif; ?>
