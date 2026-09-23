<?php
/**
 * Halaman Daftar Karyawan — cookbook/design-system.md (Human Capital).
 *
 * Variabel dari KaryawanController::index():
 * - $karyawanList, $jabatanOptions, $canEdit (bool)
 * - $search, $jabatanId, $status (filter aktif)
 */
$canCreate = uiCan('Human Capital', 'Daftar Karyawan', 'tambah');
$canDelete = uiCan('Human Capital', 'Daftar Karyawan', 'hapus');
$canStatus = uiCan('Human Capital', 'Daftar Karyawan', 'status');
$canPassword = uiCan('Human Capital', 'Daftar Karyawan', 'kata_sandi');
$canManage = $canEdit || $canCreate || $canDelete || $canStatus || $canPassword;
$headerActions = $canCreate
    ? uiButton('Tambah Karyawan', 'primary', ['icon' => 'icon_plus', 'iconPosition' => 'right', 'marginVertical' => 0, 'attributes' => ['data-modal-open' => 'modal-tambah-karyawan']])
    : '';

ob_start();
?>
<div class="list-toolbar">
    <form method="GET" action="<?= BASE_PATH ?>/karyawan" class="list-filter">
        <?= uiField('q', 'Cari', ['type' => 'search', 'value' => $search, 'placeholder' => 'Cari', 'icon' => 'icon_search', 'iconPosition' => 'left', 'hideLabel' => true, 'id' => 'list-search']) ?>
        <?= uiFilter('jabatan_id', 'Jabatan', ['' => 'Semua Jabatan'] + array_column($jabatanOptions, 'nama', 'id'), ['value' => $jabatanId, 'id' => 'filter-jabatan_id', 'marginVertical' => 0, 'attributes' => ['onchange' => 'this.form.submit()']]) ?>
        <?= uiFilter('status', 'Status', ['' => 'Semua Status', 'aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'], ['value' => $status, 'id' => 'filter-status', 'marginVertical' => 0, 'attributes' => ['onchange' => 'this.form.submit()']]) ?>
    </form>
</div>
<?php
$headerActions = ob_get_clean() . $headerActions;
require VIEW_PATH . '/layouts/shell-header.php';
?>
<?php if (!empty($_SESSION['employee_error'])): ?>
<p role="alert"><?= uiText($_SESSION['employee_error'], 'body-sm', ['tone'=>'status-inactive']) ?></p>
<?php unset($_SESSION['employee_error']); endif; ?>

<div class="data-table-wrapper">
    <table class="data-table">
        <thead>
            <tr>
                <th>Nama Karyawan</th>
                <th>Jabatan</th>
                <th>Status</th>
                <th class="col-action"></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($karyawanList)): ?>
            <tr><td colspan="4" class="data-table-empty">Belum ada data karyawan. Tambahkan karyawan pertama lewat tombol "Tambah Karyawan".</td></tr>
            <?php endif; ?>
            <?php foreach ($karyawanList as $k): ?>
            <tr>
                <td><?= e($k['nama']) ?></td>
                <td><?= e($k['nama_jabatan']) ?></td>
                <td><?= uiText($k['is_active'] ? 'Aktif' : 'Nonaktif', 'body-sm', ['weight' => 'regular', 'tone' => $k['is_active'] ? 'status-active' : 'status-inactive']) ?></td>
                <td class="col-action">
                    <?php if ($canManage): ?>
                    <div class="action-menu" data-action-menu>
                        <button type="button" class="action-menu-toggle" data-action-menu-toggle><?= icon('icon_more_vertical') ?></button>
                        <div class="action-menu-dropdown">
<?php if ($canEdit): ?>
                            <button type="button" data-modal-open="modal-ubah-karyawan-<?= $k['id'] ?>">Ubah</button>
<?php endif; ?>
<?php if ($canPassword): ?>
                            <button type="button" data-modal-open="modal-kata-sandi-karyawan-<?= $k['id'] ?>">Ubah Kata Sandi</button>
<?php endif; ?>
<?php if ($canStatus): ?>
                            <button type="button" data-modal-open="modal-status-karyawan-<?= $k['id'] ?>"><?= $k['is_active'] ? 'Nonaktifkan Karyawan' : 'Aktifkan Karyawan' ?></button>
<?php endif; ?>
<?php if ($canDelete): ?>
                            <button type="button" class="is-destructive" data-modal-open="modal-hapus-karyawan-<?= $k['id'] ?>">Hapus</button>
<?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require VIEW_PATH . '/admin/karyawan/_modals.php'; ?>
<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
