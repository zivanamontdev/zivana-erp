<?php if ($canManage): ?>
<?php foreach ($periodeList as $period): ?>
    <?php if ($canEdit) require VIEW_PATH . '/admin/periode-penilaian/_form-modal.php'; ?>
    <?php if ($canDelete): ?>
    <?php ob_start(); ?>
            <form method="POST" action="<?= BASE_PATH ?>/kurikulum/periode-penilaian/<?= (int) $period['id'] ?>/hapus">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                <div class="modal-actions">
                    <?= uiButton('Batal', 'outline', ['marginVertical' => 0, 'attributes' => ['data-modal-close' => true]]) ?>
                    <?= uiButton('Hapus Periode', 'outline-danger', ['type' => 'submit', 'marginVertical' => 0]) ?>
                </div>
            </form>
    <?php echo uiModal('modal-hapus-periode-' . (int) $period['id'], 'Hapus Periode?', ob_get_clean(), [
        'variant' => 'delete', 'description' => 'Periode yang telah dihapus akan menghilang dari data periode penilaian dan tidak dapat diakses atau digunakan kembali.',
    ]); ?>
<?php endif; ?>
<?php endforeach; ?>
<?php $period = null; if ($canCreate) require VIEW_PATH . '/admin/periode-penilaian/_form-modal.php'; ?>
<?php endif; ?>
