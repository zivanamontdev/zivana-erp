<?php
/**
 * Halaman Jabatan — cookbook/design-system.md (Human Capital > Karyawan > Jabatan).
 *
 * Variabel dari JabatanController::index():
 * - $jabatanList (array), $canEdit (bool), $search (string), $status (string)
 */
$headerActions = $canEdit
    ? '<button type="button" class="btn btn-primary" data-modal-open="modal-tambah-jabatan">Tambah Jabatan</button>'
    : '';

require VIEW_PATH . '/layouts/shell-header.php';
?>
<div class="list-toolbar">
    <form method="GET" action="<?= BASE_PATH ?>/jabatan" class="list-filter">
        <input type="text" name="q" class="field-input" placeholder="Cari" value="<?= e($search) ?>">
        <select name="status" class="field-input" onchange="this.form.submit()">
            <option value="">Semua Status</option>
            <option value="aktif" <?= $status === 'aktif' ? 'selected' : '' ?>>Aktif</option>
            <option value="nonaktif" <?= $status === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
        </select>
        <button type="submit" class="btn btn-tertiary">Cari</button>
    </form>
</div>

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
                <td><span class="badge <?= $jabatan['is_active'] ? 'badge-positif' : 'badge-netral' ?>"><?= $jabatan['is_active'] ? 'Aktif' : 'Nonaktif' ?></span></td>
                <td class="col-action">
                    <?php if ($canEdit): ?>
                    <div class="action-menu" data-action-menu>
                        <button type="button" class="action-menu-toggle" data-action-menu-toggle><?= icon('icon_more_vertical') ?></button>
                        <div class="action-menu-dropdown">
                            <button type="button" data-modal-open="modal-ubah-jabatan-<?= $jabatan['id'] ?>">Ubah</button>
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

<?php foreach ($jabatanList as $jabatan): ?>
<div class="modal-overlay" id="modal-ubah-jabatan-<?= $jabatan['id'] ?>">
    <div class="modal-box modal-sm">
        <h2 class="modal-title">Ubah Informasi Jabatan</h2>
        <form method="POST" action="<?= BASE_PATH ?>/jabatan/<?= $jabatan['id'] ?>" class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
            <div class="field">
                <label class="field-label">Nama Jabatan *</label>
                <input type="text" name="nama" class="field-input" value="<?= e($jabatan['nama']) ?>" required>
            </div>
            <div class="field">
                <label class="field-label">Role Sistem *</label>
                <select name="role_id" class="field-input" required>
                    <option value="">Pilih role sistem</option>
                    <?php foreach ($roleOptions as $role): ?>
                    <option value="<?= $role['id'] ?>" <?= $jabatan['role_id'] == $role['id'] ? 'selected' : '' ?>><?= e($role['nama']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-tertiary" data-modal-close>Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modal-hapus-jabatan-<?= $jabatan['id'] ?>">
    <div class="modal-box modal-sm">
        <h2 class="modal-title">Hapus Jabatan?</h2>
        <p class="text-body-sm">Jabatan yang telah dihapus akan menghilang dari data jabatan dan tidak dapat diakses atau digunakan kembali.</p>
        <form method="POST" action="<?= BASE_PATH ?>/jabatan/<?= $jabatan['id'] ?>/hapus">
            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
            <div class="modal-actions">
                <button type="button" class="btn btn-tertiary" data-modal-close>Batal</button>
                <button type="submit" class="btn btn-primary">Hapus Jabatan</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<?php if ($canEdit): ?>
<div class="modal-overlay" id="modal-tambah-jabatan">
    <div class="modal-box modal-sm">
        <h2 class="modal-title">Tambah Jabatan</h2>
        <form method="POST" action="<?= BASE_PATH ?>/jabatan" class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
            <div class="field">
                <label class="field-label">Nama Jabatan *</label>
                <input type="text" name="nama" class="field-input" placeholder="Isi nama jabatan" required>
            </div>
            <div class="field">
                <label class="field-label">Role Sistem *</label>
                <select name="role_id" class="field-input" required>
                    <option value="">Pilih role sistem</option>
                    <?php foreach ($roleOptions as $role): ?>
                    <option value="<?= $role['id'] ?>"><?= e($role['nama']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-tertiary" data-modal-close>Batal</button>
                <button type="submit" class="btn btn-primary">Tambah Jabatan</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
