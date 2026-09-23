<?php if ($canEdit): ?>
<?php foreach ($karyawanList as $employee): ?>
    <?php require VIEW_PATH . '/admin/karyawan/_form-modal.php'; ?>
    <?php require VIEW_PATH . '/admin/karyawan/_password-modal.php'; ?>
    <?php ob_start(); ?>
    <form method="POST" action="<?= BASE_PATH ?>/karyawan/<?= (int) $employee['id'] ?>/hapus">
        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
        <div class="modal-actions">
            <?= uiButton('Batal', 'outline', ['marginVertical' => 0, 'attributes' => ['data-modal-close' => true]]) ?>
            <?= uiButton('Hapus Karyawan', 'outline-danger', ['type' => 'submit', 'marginVertical' => 0]) ?>
        </div>
    </form>
    <?php echo uiModal('modal-hapus-karyawan-' . (int) $employee['id'], 'Hapus Karyawan?', ob_get_clean(), [
        'variant' => 'delete', 'description' => 'Karyawan yang telah dihapus akan menghilang dari data karyawan dan tidak dapat diakses atau digunakan kembali. Pastikan data telah dibackup terlebih dahulu sebelum dihapus.',
    ]); ?>
        <?php
        $statusAction = $employee['is_active'] ? 'nonaktifkan' : 'aktifkan';
        $statusLabel = $employee['is_active'] ? 'Nonaktifkan Karyawan' : 'Aktifkan Karyawan';
        ob_start();
        ?>
        <form method="POST" action="<?= BASE_PATH ?>/karyawan/<?= (int) $employee['id'] ?>/<?= $statusAction ?>">
            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
            <div class="modal-actions">
                <?= uiButton('Batal', 'outline', ['marginVertical' => 0, 'attributes' => ['data-modal-close' => true]]) ?>
                <?= uiButton($statusLabel, $employee['is_active'] ? 'outline-danger' : 'primary', ['type' => 'submit', 'marginVertical' => 0]) ?>
            </div>
        </form>
        <?php echo uiModal('modal-status-karyawan-' . (int) $employee['id'], $statusLabel . '?', ob_get_clean(), [
            'variant' => 'delete',
            'description' => $employee['is_active']
                ? 'Karyawan akan berstatus Nonaktif dan tidak dapat mengakses aplikasi. Data karyawan tetap tersimpan.'
                : 'Karyawan akan berstatus Aktif dan dapat kembali mengakses aplikasi sesuai hak aksesnya. Data karyawan tetap tersimpan.',
        ]); ?>
<?php endforeach; ?>
<?php $employee = null; require VIEW_PATH . '/admin/karyawan/_form-modal.php'; ?>
<?php endif; ?>
