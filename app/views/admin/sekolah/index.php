<?php
/**
 * Halaman Data Sekolah — cookbook/design-system.md bagian 5.
 * Singleton (1 baris data), 2 tab: Informasi Umum, Kontak & Media.
 *
 * Variabel dari SekolahController::index():
 * - $sekolah (array|null), $media (array), $tahunAjaranAktif (array|null)
 * - $canEdit (bool), $mode ('lihat'|'ubah')
 * - $errors (array field=>pesan), $old (array field=>value)
 */
$isEdit = $mode === 'ubah';

$val = function (string $field) use ($sekolah, $old) {
    return array_key_exists($field, $old) ? $old[$field] : ($sekolah[$field] ?? '');
};

$headerActions = '';
if ($canEdit) {
    $headerActions = $isEdit
        ? '<button type="submit" form="form-sekolah" class="btn btn-primary">Simpan</button>'
        : '<a href="' . BASE_PATH . '/sekolah?mode=ubah" class="btn btn-primary">Ubah Data Sekolah</a>';
}

require VIEW_PATH . '/layouts/shell-header.php';
?>
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/sekolah.css">

<?php if ($tahunAjaranAktif || $canEdit): ?>
<div class="sekolah-tahun-ajaran">
    <span class="text-caption-md">Tahun Ajaran berjalan:
        <strong><?= $tahunAjaranAktif ? e($tahunAjaranAktif['tahun_awal'] . '/' . $tahunAjaranAktif['tahun_akhir']) : '-' ?></strong>
    </span>
    <?php if ($canEdit): ?>
    <button type="button" class="btn btn-tertiary" data-modal-open="modal-tahun-ajaran">Perbarui Tahun Ajaran</button>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="tabs" data-tabs>
    <div class="tabs-nav">
        <button type="button" class="tabs-tab is-active" data-tab-target="informasi">Informasi Umum</button>
        <button type="button" class="tabs-tab" data-tab-target="kontak">Kontak &amp; Media</button>
    </div>

    <form method="POST" action="<?= BASE_PATH ?>/sekolah" id="form-sekolah">
        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">

        <div class="tabs-panel is-active" data-tab-panel="informasi">
            <div class="field-row">
                <div class="field">
                    <label class="field-label">Nama Legal Sekolah *</label>
                    <?php if ($isEdit): ?>
                    <input type="text" name="nama_legal" class="field-input<?= isset($errors['nama_legal']) ? ' is-negative' : '' ?>" value="<?= e($val('nama_legal')) ?>" placeholder="Isi nama legal sekolah">
                    <?php if (isset($errors['nama_legal'])): ?><span class="field-error"><?= e($errors['nama_legal']) ?></span><?php endif; ?>
                    <?php else: ?>
                    <div class="field-input is-viewonly"><?= e($sekolah['nama_legal'] ?? '-') ?></div>
                    <?php endif; ?>
                </div>
                <div class="field">
                    <label class="field-label">Nama Komersial Sekolah *</label>
                    <?php if ($isEdit): ?>
                    <input type="text" name="nama_komersial" class="field-input<?= isset($errors['nama_komersial']) ? ' is-negative' : '' ?>" value="<?= e($val('nama_komersial')) ?>" placeholder="Isi nama komersial sekolah">
                    <?php if (isset($errors['nama_komersial'])): ?><span class="field-error"><?= e($errors['nama_komersial']) ?></span><?php endif; ?>
                    <?php else: ?>
                    <div class="field-input is-viewonly"><?= e($sekolah['nama_komersial'] ?? '-') ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="field-row">
                <div class="field">
                    <label class="field-label">Bentuk Pendidikan *</label>
                    <?php if ($isEdit): ?>
                    <!-- [ASUMSI] Opsi bentuk pendidikan tidak eksplisit lengkap di screenshot,
                         hanya contoh "TK" yang terlihat. Daftar berikut menyesuaikan konteks
                         Montessori, perlu dikonfirmasi ke user kalau ada opsi lain. -->
                    <select name="bentuk_pendidikan" class="field-input<?= isset($errors['bentuk_pendidikan']) ? ' is-negative' : '' ?>">
                        <option value="">Pilih bentuk pendidikan</option>
                        <?php foreach (['KB' => 'Kelompok Bermain (KB)', 'TK' => 'Taman Kanak-Kanak (TK)', 'TPA' => 'Tempat Penitipan Anak (TPA)'] as $optVal => $optLabel): ?>
                        <option value="<?= e($optVal) ?>" <?= $val('bentuk_pendidikan') === $optVal ? 'selected' : '' ?>><?= e($optLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['bentuk_pendidikan'])): ?><span class="field-error"><?= e($errors['bentuk_pendidikan']) ?></span><?php endif; ?>
                    <?php else: ?>
                    <div class="field-input is-viewonly"><?= e($sekolah['bentuk_pendidikan'] ?? '-') ?></div>
                    <?php endif; ?>
                </div>
                <div class="field">
                    <label class="field-label">NPSN *</label>
                    <?php if ($isEdit): ?>
                    <input type="text" name="npsn" class="field-input<?= isset($errors['npsn']) ? ' is-negative' : '' ?>" value="<?= e($val('npsn')) ?>" placeholder="Isi NPSN">
                    <?php if (isset($errors['npsn'])): ?><span class="field-error"><?= e($errors['npsn']) ?></span><?php endif; ?>
                    <?php else: ?>
                    <div class="field-input is-viewonly"><?= e($sekolah['npsn'] ?? '-') ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="field-row">
                <div class="field field-full">
                    <label class="field-label">Alamat Sekolah *</label>
                    <?php if ($isEdit): ?>
                    <textarea name="alamat" class="field-textarea<?= isset($errors['alamat']) ? ' is-negative' : '' ?>" placeholder="Isi alamat sekolah (cth: Jalan, Kota, Provinsi, Kode Pos)"><?= e($val('alamat')) ?></textarea>
                    <?php if (isset($errors['alamat'])): ?><span class="field-error"><?= e($errors['alamat']) ?></span><?php endif; ?>
                    <?php else: ?>
                    <div class="field-textarea is-viewonly"><?= nl2br(e($sekolah['alamat'] ?? '-')) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="tabs-panel" data-tab-panel="kontak">
            <div class="field-row">
                <div class="field">
                    <label class="field-label">No. Telepon Sekolah *</label>
                    <?php if ($isEdit): ?>
                    <input type="text" name="no_telepon" class="field-input<?= isset($errors['no_telepon']) ? ' is-negative' : '' ?>" value="<?= e($val('no_telepon')) ?>" placeholder="Isi nomor telepon sekolah">
                    <?php if (isset($errors['no_telepon'])): ?><span class="field-error"><?= e($errors['no_telepon']) ?></span><?php endif; ?>
                    <?php else: ?>
                    <div class="field-input is-viewonly"><?= e($sekolah['no_telepon'] ?? '-') ?></div>
                    <?php endif; ?>
                </div>
                <div class="field">
                    <label class="field-label">Email Sekolah *</label>
                    <?php if ($isEdit): ?>
                    <input type="email" name="email" class="field-input<?= isset($errors['email']) ? ' is-negative' : '' ?>" value="<?= e($val('email')) ?>" placeholder="Isi alamat email sekolah">
                    <?php if (isset($errors['email'])): ?><span class="field-error"><?= e($errors['email']) ?></span><?php endif; ?>
                    <?php else: ?>
                    <div class="field-input is-viewonly"><?= e($sekolah['email'] ?? '-') ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="sekolah-media" data-assign-list>
                <div class="assign-list-header">
                    <span class="text-body-sm font-bold">Daftar Media</span>
                    <?php if ($isEdit): ?>
                    <button type="button" class="btn btn-tertiary" data-assign-add>+ Tambah Media</button>
                    <?php endif; ?>
                </div>
                <div class="assign-list-rows" data-assign-rows>
                    <?php foreach ($media as $item): ?>
                    <div class="sekolah-media-row" data-assign-row>
                        <select name="media_jenis[]" class="field-input" <?= $isEdit ? '' : 'disabled' ?>>
                            <?php foreach (['Instagram', 'Facebook', 'TikTok', 'YouTube', 'Website'] as $jenis): ?>
                            <option value="<?= e($jenis) ?>" <?= $item['jenis_media'] === $jenis ? 'selected' : '' ?>><?= e($jenis) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="media_nama[]" class="field-input" value="<?= e($item['nama_akun']) ?>" placeholder="Isi ID/nama akun" <?= $isEdit ? '' : 'readonly' ?>>
                        <input type="text" name="media_url[]" class="field-input" value="<?= e($item['url']) ?>" placeholder="Isi URL/link media" <?= $isEdit ? '' : 'readonly' ?>>
                        <?php if ($isEdit): ?>
                        <button type="button" class="assign-list-remove" data-assign-remove><?= icon('icon_trash') ?></button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($isEdit): ?>
                <template data-assign-template>
                    <div class="sekolah-media-row" data-assign-row>
                        <select name="media_jenis[]" class="field-input">
                            <?php foreach (['Instagram', 'Facebook', 'TikTok', 'YouTube', 'Website'] as $jenis): ?>
                            <option value="<?= e($jenis) ?>"><?= e($jenis) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="media_nama[]" class="field-input" placeholder="Isi ID/nama akun">
                        <input type="text" name="media_url[]" class="field-input" placeholder="Isi URL/link media">
                        <button type="button" class="assign-list-remove" data-assign-remove><?= icon('icon_trash') ?></button>
                    </div>
                </template>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<div class="modal-overlay" id="modal-tahun-ajaran">
    <div class="modal-box modal-sm">
        <h2 class="modal-title">Perbarui Tahun Ajaran</h2>
        <form method="POST" action="<?= BASE_PATH ?>/sekolah/tahun-ajaran" class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">

            <div class="field">
                <label class="field-label">Tahun Ajaran Berjalan</label>
                <div class="field-input is-viewonly"><?= $tahunAjaranAktif ? e($tahunAjaranAktif['tahun_awal'] . '/' . $tahunAjaranAktif['tahun_akhir']) : '-' ?></div>
            </div>

            <p class="text-body-sm font-bold" style="margin: 0;">Perbarui ke:</p>

            <div class="field-row">
                <?php $currentYear = (int) date('Y'); ?>
                <div class="field">
                    <label class="field-label">Tahun Awal Ajaran *</label>
                    <select name="tahun_awal" class="field-input" required>
                        <?php for ($y = $currentYear - 1; $y <= $currentYear + 3; $y++): ?>
                        <option value="<?= $y ?>"><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field-label">Tahun Akhir Ajaran *</label>
                    <select name="tahun_akhir" class="field-input" required>
                        <?php for ($y = $currentYear - 1; $y <= $currentYear + 4; $y++): ?>
                        <option value="<?= $y ?>" <?= $y === $currentYear + 1 ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-tertiary" data-modal-close>Batalkan</button>
                <button type="submit" class="btn btn-primary">Perbarui Tahun Ajaran</button>
            </div>
        </form>
    </div>
</div>

<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
