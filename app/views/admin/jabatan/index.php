<?php
/**
 * Halaman Jabatan — cookbook/design-system.md (Human Capital > Karyawan > Jabatan).
 *
 * Variabel dari JabatanController::index():
 * - $jabatanList (array), $canEdit (bool), $search (string), $status (string)
 */
$headerActions = $canEdit
    ? uiButton('Tambah Jabatan', 'primary', ['icon' => 'icon_plus', 'iconPosition' => 'right', 'marginVertical' => 0, 'attributes' => ['data-modal-open' => 'modal-tambah-jabatan']])
    : '';

ob_start();
?>
<div class="list-toolbar">
    <form method="GET" action="<?= BASE_PATH ?>/jabatan" class="list-filter">
        <?= uiField('q', 'Cari', ['type' => 'search', 'value' => $search, 'placeholder' => 'Cari', 'icon' => 'icon_search', 'iconPosition' => 'left', 'hideLabel' => true, 'id' => 'list-search']) ?>
        <?= uiFilter('status', 'Status', ['' => 'Semua Status', 'aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'], ['value' => $status, 'id' => 'filter-status', 'marginVertical' => 0, 'attributes' => ['onchange' => 'this.form.submit()']]) ?>
    </form>
</div>
<?php
$headerActions = ob_get_clean() . $headerActions;
require VIEW_PATH . '/layouts/shell-header.php';
?>

<?php if (!empty($deleteError)): ?>
    <p role="alert"><?= uiText($deleteError, 'body-sm', ['tone' => 'status-inactive']) ?></p>
<?php endif; ?>

<div class="data-table-wrapper">
    <table class="data-table">
        <thead>
            <tr>
                <th>Nama Jabatan</th>
                <th>Role</th>
                <th>Status</th>
                <th class="col-action"></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($jabatanList)): ?>
            <tr><td colspan="4" class="data-table-empty">Belum ada data jabatan. Tambahkan jabatan pertama lewat tombol "Tambah Jabatan".</td></tr>
            <?php endif; ?>
            <?php foreach ($jabatanList as $jabatan): ?>
            <tr>
                <td><?= e($jabatan['nama']) ?></td>
                <td><?= e($jabatan['nama_role'] ?? '-') ?></td>
                <td><?= uiText($jabatan['is_active'] ? 'Aktif' : 'Nonaktif', 'body-sm', ['weight' => 'regular', 'tone' => $jabatan['is_active'] ? 'status-active' : 'status-inactive']) ?></td>
                <td class="col-action">
                    <?php if ($canEdit): ?>
                    <div class="action-menu" data-action-menu>
                        <button type="button" class="action-menu-toggle" data-action-menu-toggle><?= icon('icon_more_vertical') ?></button>
                        <div class="action-menu-dropdown">
                            <button type="button" data-modal-open="modal-ubah-jabatan-<?= $jabatan['id'] ?>">Ubah</button>
                            <button type="button" data-modal-open="modal-status-jabatan-<?= $jabatan['id'] ?>"><?= $jabatan['is_active'] ? 'Nonaktifkan Jabatan' : 'Aktifkan Jabatan' ?></button>
                            <button type="button" class="is-destructive" data-modal-open="modal-hapus-jabatan-<?= $jabatan['id'] ?>">Hapus</button>
                        </div>
                    </div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require VIEW_PATH . '/admin/jabatan/_modals.php'; ?>
<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
