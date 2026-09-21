<?php
/**
 * Halaman Periode Penilaian — cookbook/design-system.md (Sekolah > Kurikulum).
 *
 * Variabel dari PeriodePenilaianController::index():
 * - $periodeList, $tipeOptions, $kategoriOptions, $canEdit, $tipe, $kategori
 */
$headerActions = $canEdit
    ? '<button type="button" class="btn btn-primary" data-modal-open="modal-tambah-periode">Tambah Periode Penilaian</button>'
    : '';

require VIEW_PATH . '/layouts/shell-header.php';
?>
<div class="list-toolbar">
    <form method="GET" action="<?= BASE_PATH ?>/kurikulum/periode-penilaian" class="list-filter">
        <select name="tipe" class="field-input" onchange="this.form.submit()">
            <option value="">Semua Tipe</option>
            <?php foreach ($tipeOptions as $val => $label): ?>
            <option value="<?= e($val) ?>" <?= $tipe === $val ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="kategori" class="field-input" onchange="this.form.submit()">
            <option value="">Semua Kategori</option>
            <?php foreach ($kategoriOptions as $val => $label): ?>
            <option value="<?= e($val) ?>" <?= $kategori === $val ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<div class="data-table-wrapper">
    <table class="data-table">
        <thead>
            <tr>
                <th>Nama Periode</th>
                <th>Awal Periode</th>
                <th>Akhir Periode</th>
                <th class="col-action"></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($periodeList)): ?>
            <tr><td colspan="4" class="data-table-empty">Belum ada data periode penilaian. Tambahkan lewat tombol "Tambah Periode Penilaian".</td></tr>
            <?php endif; ?>
            <?php foreach ($periodeList as $p): ?>
            <tr>
                <td><?= e($p['nama']) ?></td>
                <td><?= date('d/m/Y', strtotime($p['awal_periode'])) ?></td>
                <td><?= date('d/m/Y', strtotime($p['akhir_periode'])) ?></td>
                <td class="col-action">
                    <?php if ($canEdit): ?>
                    <div class="action-menu" data-action-menu>
                        <button type="button" class="action-menu-toggle" data-action-menu-toggle><?= icon('icon_more_vertical') ?></button>
                        <div class="action-menu-dropdown">
                            <button type="button" data-modal-open="modal-ubah-periode-<?= $p['id'] ?>">Ubah</button>
                            <button type="button" class="is-destructive" data-modal-open="modal-hapus-periode-<?= $p['id'] ?>">Hapus</button>
                        </div>
                    </div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php foreach ($periodeList as $p): ?>
<div class="modal-overlay" id="modal-ubah-periode-<?= $p['id'] ?>">
    <div class="modal-box modal-sm">
        <h2 class="modal-title">Ubah Periode Penilaian</h2>
        <form method="POST" action="<?= BASE_PATH ?>/kurikulum/periode-penilaian/<?= $p['id'] ?>" class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
            <div class="field">
                <label class="field-label">Nama Periode Penilaian *</label>
                <input type="text" name="nama" class="field-input" value="<?= e($p['nama']) ?>" required>
            </div>
            <div class="field">
                <label class="field-label">Tipe *</label>
                <select name="tipe" class="field-input" required>
                    <?php foreach ($tipeOptions as $val => $label): ?>
                    <option value="<?= e($val) ?>" <?= $p['tipe'] === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field-label">Kategori *</label>
                <select name="kategori" class="field-input" required>
                    <?php foreach ($kategoriOptions as $val => $label): ?>
                    <option value="<?= e($val) ?>" <?= $p['kategori'] === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field-row">
                <div class="field">
                    <label class="field-label">Awal Periode *</label>
                    <input type="date" name="awal_periode" class="field-input" value="<?= e($p['awal_periode']) ?>" required>
                </div>
                <div class="field">
                    <label class="field-label">Akhir Periode *</label>
                    <input type="date" name="akhir_periode" class="field-input" value="<?= e($p['akhir_periode']) ?>" required>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-tertiary" data-modal-close>Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modal-hapus-periode-<?= $p['id'] ?>">
    <div class="modal-box modal-sm">
        <h2 class="modal-title">Hapus Periode?</h2>
        <p class="text-body-sm">Periode yang telah dihapus akan menghilang dari data periode penilaian dan tidak dapat diakses atau digunakan kembali.</p>
        <form method="POST" action="<?= BASE_PATH ?>/kurikulum/periode-penilaian/<?= $p['id'] ?>/hapus">
            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
            <div class="modal-actions">
                <button type="button" class="btn btn-tertiary" data-modal-close>Batal</button>
                <button type="submit" class="btn btn-primary">Hapus Periode</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<?php if ($canEdit): ?>
<div class="modal-overlay" id="modal-tambah-periode">
    <div class="modal-box modal-sm">
        <h2 class="modal-title">Tambah Periode Penilaian</h2>
        <form method="POST" action="<?= BASE_PATH ?>/kurikulum/periode-penilaian" class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
            <div class="field">
                <label class="field-label">Nama Periode Penilaian *</label>
                <input type="text" name="nama" class="field-input" placeholder="Isi nama periode penilaian" required>
            </div>
            <div class="field">
                <label class="field-label">Tipe *</label>
                <select name="tipe" class="field-input" required>
                    <option value="">Pilih tipe</option>
                    <?php foreach ($tipeOptions as $val => $label): ?>
                    <option value="<?= e($val) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field-label">Kategori *</label>
                <select name="kategori" class="field-input" required>
                    <option value="">Pilih kategori</option>
                    <?php foreach ($kategoriOptions as $val => $label): ?>
                    <option value="<?= e($val) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field-row">
                <div class="field">
                    <label class="field-label">Awal Periode *</label>
                    <input type="date" name="awal_periode" class="field-input" required>
                </div>
                <div class="field">
                    <label class="field-label">Akhir Periode *</label>
                    <input type="date" name="akhir_periode" class="field-input" required>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-tertiary" data-modal-close>Batal</button>
                <button type="submit" class="btn btn-primary">Tambah Periode Penilaian</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
