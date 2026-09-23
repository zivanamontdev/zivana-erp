<?php
/**
 * Halaman Manajemen Kelas — cookbook/design-system.md (Murid > Manajemen Kelas).
 *
 * Variabel dari KelasController::index():
 * - $kelasList (array, sudah termasuk jumlah_murid & jumlah_guru), $canEdit (bool)
 */
$headerActions = $canEdit
    ? '<button type="button" class="ui-button ui-button--primary" data-modal-open="modal-tambah-kelas">Tambah Kelas</button>'
    : '';

require VIEW_PATH . '/layouts/shell-header.php';
?>
<div class="data-table-wrapper">
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
                <td><a href="<?= BASE_PATH ?>/kelas/<?= $kelas['id'] ?>"><?= e($kelas['level_kelas']) ?></a></td>
                <td><a href="<?= BASE_PATH ?>/kelas/<?= $kelas['id'] ?>"><?= e($kelas['nama_kelas']) ?></a></td>
                <td><?= (int) $kelas['jumlah_murid'] ?></td>
                <td><?= (int) $kelas['jumlah_guru'] ?></td>
                <td class="col-action">
                    <?php if ($canEdit): ?>
                    <div class="action-menu" data-action-menu>
                        <button type="button" class="action-menu-toggle" data-action-menu-toggle><?= icon('icon_more_vertical') ?></button>
                        <div class="action-menu-dropdown">
                            <button type="button" data-modal-open="modal-ubah-kelas-<?= $kelas['id'] ?>">Ubah</button>
                            <button type="button" class="is-destructive" data-modal-open="modal-hapus-kelas-<?= $kelas['id'] ?>">Hapus</button>
                        </div>
                    </div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php foreach ($kelasList as $kelas): ?>
<div class="modal-overlay" id="modal-ubah-kelas-<?= $kelas['id'] ?>">
    <div class="modal-box modal-sm">
        <h2 class="modal-title">Ubah Kelas</h2>
        <form method="POST" action="<?= BASE_PATH ?>/kelas/<?= $kelas['id'] ?>" class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
            <div class="field">
                <label class="field-label">Level Kelas *</label>
                <input type="text" name="level_kelas" class="field-input" value="<?= e($kelas['level_kelas']) ?>" required>
            </div>
            <div class="field">
                <label class="field-label">Nama Kelas *</label>
                <input type="text" name="nama_kelas" class="field-input" value="<?= e($kelas['nama_kelas']) ?>" required>
            </div>
            <div class="modal-actions">
                <button type="button" class="ui-button ui-button--outline" data-modal-close>Batal</button>
                <button type="submit" class="ui-button ui-button--primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modal-hapus-kelas-<?= $kelas['id'] ?>">
    <div class="modal-box modal-sm">
        <h2 class="modal-title">Hapus Kelas?</h2>
        <p class="text-body-sm">Kelas yang telah dihapus akan menghilang dari data kelas dan tidak dapat diakses atau digunakan kembali. Murid yang masih terkait dengan kelas yang dihapus akan mengosongkan kelas murid terkait. Pastikan data telah dibackup terlebih dahulu sebelum dihapus.</p>
        <form method="POST" action="<?= BASE_PATH ?>/kelas/<?= $kelas['id'] ?>/hapus">
            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
            <div class="modal-actions">
                <button type="button" class="ui-button ui-button--outline" data-modal-close>Batal</button>
                <button type="submit" class="ui-button ui-button--primary">Hapus Kelas</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<?php if ($canEdit): ?>
<div class="modal-overlay" id="modal-tambah-kelas">
    <div class="modal-box modal-sm">
        <h2 class="modal-title">Tambah Kelas</h2>
        <form method="POST" action="<?= BASE_PATH ?>/kelas" class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
            <div class="field">
                <label class="field-label">Level Kelas *</label>
                <input type="text" name="level_kelas" class="field-input" placeholder="Pilih level kelas" required>
            </div>
            <div class="field">
                <label class="field-label">Nama Kelas *</label>
                <input type="text" name="nama_kelas" class="field-input" placeholder="Isi nama kelas" required>
            </div>
            <div class="modal-actions">
                <button type="button" class="ui-button ui-button--outline" data-modal-close>Batal</button>
                <button type="submit" class="ui-button ui-button--primary">Tambah Kelas</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
