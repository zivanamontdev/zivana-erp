<?php
/**
 * Halaman Daftar Karyawan — cookbook/design-system.md (Human Capital).
 *
 * Variabel dari KaryawanController::index():
 * - $karyawanList, $jabatanOptions, $canEdit (bool)
 * - $search, $jabatanId, $status (filter aktif)
 */
$headerActions = $canEdit
    ? '<button type="button" class="btn btn-primary" data-modal-open="modal-tambah-karyawan">Tambah Karyawan</button>'
    : '';

require VIEW_PATH . '/layouts/shell-header.php';
?>
<div class="list-toolbar">
    <form method="GET" action="<?= BASE_PATH ?>/karyawan" class="list-filter">
        <input type="text" name="q" class="field-input" placeholder="Cari" value="<?= e($search) ?>">
        <select name="jabatan_id" class="field-input" onchange="this.form.submit()">
            <option value="">Semua Jabatan</option>
            <?php foreach ($jabatanOptions as $j): ?>
            <option value="<?= $j['id'] ?>" <?= $jabatanId == $j['id'] ? 'selected' : '' ?>><?= e($j['nama']) ?></option>
            <?php endforeach; ?>
        </select>
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
                <td><span class="badge <?= $k['is_active'] ? 'badge-positif' : 'badge-netral' ?>"><?= $k['is_active'] ? 'Aktif' : 'Nonaktif' ?></span></td>
                <td class="col-action">
                    <?php if ($canEdit): ?>
                    <div class="action-menu" data-action-menu>
                        <button type="button" class="action-menu-toggle" data-action-menu-toggle><?= icon('icon_more_vertical') ?></button>
                        <div class="action-menu-dropdown">
                            <button type="button" data-modal-open="modal-ubah-karyawan-<?= $k['id'] ?>">Ubah</button>
                            <button type="button" class="is-destructive" data-modal-open="modal-hapus-karyawan-<?= $k['id'] ?>">Hapus</button>
                        </div>
                    </div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php foreach ($karyawanList as $k): ?>
<div class="modal-overlay" id="modal-ubah-karyawan-<?= $k['id'] ?>">
    <div class="modal-box modal-sm">
        <h2 class="modal-title">Ubah Informasi Karyawan</h2>
        <form method="POST" action="<?= BASE_PATH ?>/karyawan/<?= $k['id'] ?>" class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
            <div class="field">
                <label class="field-label">Nama Karyawan *</label>
                <input type="text" name="nama" class="field-input" value="<?= e($k['nama']) ?>" required>
            </div>
            <div class="field">
                <label class="field-label">Jabatan *</label>
                <select name="jabatan_id" class="field-input" required>
                    <?php foreach ($jabatanOptions as $j): ?>
                    <option value="<?= $j['id'] ?>" <?= $k['jabatan_id'] == $j['id'] ? 'selected' : '' ?>><?= e($j['nama']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field-label">Email Karyawan *</label>
                <input type="email" name="email" class="field-input" value="<?= e($k['email'] ?? '') ?>" required>
            </div>
            <button type="button" class="btn btn-tertiary" data-modal-open="modal-ganti-password-<?= $k['id'] ?>">Ganti Kata Sandi</button>
            <div class="modal-actions">
                <button type="button" class="btn btn-tertiary" data-modal-close>Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modal-ganti-password-<?= $k['id'] ?>">
    <div class="modal-box modal-sm">
        <h2 class="modal-title">Ganti Kata Sandi</h2>
        <form method="POST" action="<?= BASE_PATH ?>/karyawan/<?= $k['id'] ?>/kata-sandi" class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
            <div class="field">
                <label class="field-label">Kata Sandi Baru *</label>
                <input type="password" name="password" class="field-input" placeholder="Minimal 8 karakter" minlength="8" required>
            </div>
            <div class="field">
                <label class="field-label">Ulangi Kata Sandi Baru *</label>
                <input type="password" name="password_confirmation" class="field-input" minlength="8" required>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-tertiary" data-modal-close>Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Kata Sandi</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modal-hapus-karyawan-<?= $k['id'] ?>">
    <div class="modal-box modal-sm">
        <h2 class="modal-title">Hapus Karyawan?</h2>
        <p class="text-body-sm">Karyawan yang telah dihapus akan menghilang dari data karyawan dan tidak dapat diakses atau digunakan kembali. Pastikan data telah dibackup terlebih dahulu sebelum dihapus.</p>
        <form method="POST" action="<?= BASE_PATH ?>/karyawan/<?= $k['id'] ?>/hapus">
            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
            <div class="modal-actions">
                <button type="button" class="btn btn-tertiary" data-modal-close>Batal</button>
                <button type="submit" class="btn btn-primary">Hapus Karyawan</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<?php if ($canEdit): ?>
<div class="modal-overlay" id="modal-tambah-karyawan">
    <div class="modal-box modal-sm">
        <h2 class="modal-title">Tambah Karyawan</h2>
        <form method="POST" action="<?= BASE_PATH ?>/karyawan" class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
            <div class="field">
                <label class="field-label">Nama Karyawan *</label>
                <input type="text" name="nama" class="field-input" placeholder="Isi nama karyawan" required>
            </div>
            <div class="field">
                <label class="field-label">Jabatan *</label>
                <select name="jabatan_id" class="field-input" required>
                    <option value="">Pilih jabatan karyawan</option>
                    <?php foreach ($jabatanOptions as $j): ?>
                    <option value="<?= $j['id'] ?>"><?= e($j['nama']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field-label">Email Karyawan *</label>
                <input type="email" name="email" class="field-input" placeholder="Isi email karyawan" required>
            </div>
            <div class="field">
                <label class="field-label">Kata Sandi Karyawan *</label>
                <input type="password" name="password" class="field-input" placeholder="Minimal 8 karakter" minlength="8" required>
            </div>
            <div class="field">
                <label class="field-label">Ulangi Kata Sandi Karyawan *</label>
                <input type="password" name="password_confirmation" class="field-input" minlength="8" required>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-tertiary" data-modal-close>Batal</button>
                <button type="submit" class="btn btn-primary">Tambah Karyawan</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
