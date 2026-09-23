<?php
/**
 * Halaman Manajemen Kelas — cookbook/design-system.md (Murid > Manajemen Kelas).
 *
 * Variabel dari KelasController::index():
 * - $kelasList (array, sudah termasuk jumlah_murid & jumlah_guru), $canEdit (bool)
 */
$canCreate = uiCan('Murid', 'Manajemen Kelas', 'tambah');
$canDelete = uiCan('Murid', 'Manajemen Kelas', 'hapus');
$canStatus = uiCan('Murid', 'Manajemen Kelas', 'status');
$canPassword = uiCan('Murid', 'Manajemen Kelas', 'kata_sandi');
$canManage = $canEdit || $canCreate || $canDelete || $canStatus || $canPassword;
$headerActions = $canCreate
    ? uiButton('Tambah Kelas', 'primary', ['icon' => 'icon_plus', 'iconPosition' => 'right', 'marginVertical' => 0, 'attributes' => ['data-modal-open' => 'modal-tambah-kelas']])
    : '';

require VIEW_PATH . '/layouts/shell-header.php';
?>
<div class="data-table-wrapper class-list-table">
    <table class="data-table">
        <thead>
            <tr>
                <th>Level Kelas</th>
                <th>Nama Kelas</th>
                <th>Jumlah Murid</th>
                <th>Jumlah Guru</th>
                <th class="col-action"></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($kelasList)): ?>
            <tr><td colspan="5" class="data-table-empty">Belum ada data kelas. Tambahkan kelas pertama lewat tombol "Tambah Kelas".</td></tr>
            <?php endif; ?>
            <?php foreach ($kelasList as $kelas): ?>
            <tr>
                <td><?= e($kelas['level_kelas']) ?></td>
                <td><?= e($kelas['nama_kelas']) ?></td>
                <td><?= (int) $kelas['jumlah_murid'] ?></td>
                <td><?= (int) $kelas['jumlah_guru'] ?></td>
                <td class="col-action">
                    <div class="action-menu" data-action-menu>
                        <button type="button" class="action-menu-toggle" data-action-menu-toggle><?= icon('icon_more_vertical') ?></button>
                        <div class="action-menu-dropdown">
                            <a href="<?= BASE_PATH ?>/kelas/<?= (int) $kelas['id'] ?>">Lihat Detail</a>
                            <?php if ($canManage): ?>
<?php if ($canEdit): ?>
                            <button type="button" data-modal-open="modal-ubah-kelas-<?= $kelas['id'] ?>">Ubah</button>
<?php endif; ?>
<?php if ($canDelete): ?>
                            <button type="button" class="is-destructive" data-modal-open="modal-hapus-kelas-<?= $kelas['id'] ?>">Hapus</button>
<?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/_modals.php'; ?>

<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
