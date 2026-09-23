<?php if ($canManage): ?>
<?php foreach ($jabatanList as $position): ?>
    <?php if ($canEdit) require VIEW_PATH . '/admin/jabatan/_form-modal.php'; ?>
    <?php
    $statusAction = $position['is_active'] ? 'nonaktifkan' : 'aktifkan';
    $statusLabel = $position['is_active'] ? 'Nonaktifkan Jabatan' : 'Aktifkan Jabatan';
    foreach ([$statusAction, 'hapus'] as $action):
        $deleting = $action === 'hapus';
        if ($deleting ? !$canDelete : !$canStatus) continue;
        $label = $deleting ? 'Hapus Jabatan' : $statusLabel;
        $id = ($deleting ? 'modal-hapus-jabatan-' : 'modal-status-jabatan-') . (int) $position['id'];
        $description = $action === 'aktifkan'
            ? 'Jabatan akan berstatus Aktif dan dapat dipilih kembali saat menambahkan karyawan.'
            : 'Jabatan akan berstatus Nonaktif dan tidak tersedia untuk penugasan karyawan baru. Data dan penugasan karyawan yang sudah ada tetap tersimpan.';
        if ($deleting) {
            $description = 'Jabatan yang telah dihapus akan hilang secara permanen dan tidak dapat digunakan kembali. Jabatan yang masih digunakan oleh karyawan tidak dapat dihapus. Pastikan data telah dibackup terlebih dahulu sebelum dihapus.';
        }
        ob_start();
    ?>
    <form method="POST" action="<?= BASE_PATH ?>/jabatan/<?= (int) $position['id'] ?>/<?= $action ?>">
        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
        <div class="modal-actions">
            <?= uiButton('Batal', 'outline', ['marginVertical' => 0, 'attributes' => ['data-modal-close' => true]]) ?>
            <?= uiButton($label, $action === 'aktifkan' ? 'primary' : 'outline-danger', ['type' => 'submit', 'marginVertical' => 0]) ?>
        </div>
    </form>
    <?php echo uiModal($id, $label . '?', ob_get_clean(), ['variant' => 'delete', 'description' => $description]); ?>
    <?php endforeach; ?>
<?php endforeach; ?>
<?php $position = null; if ($canCreate) require VIEW_PATH . '/admin/jabatan/_form-modal.php'; ?>
<?php endif; ?>
