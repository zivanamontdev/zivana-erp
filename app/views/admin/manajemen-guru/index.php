<?php
/**
 * Halaman Manajemen Guru — cookbook/design-system.md (Human Capital).
 * Reuse pattern Guru-Murid Card dan Atur Anak Murid dari Detail Kelas
 * (Fase 5), tapi lintas kelas (satu guru bisa ampu murid di kelas beda).
 *
 * Variabel dari ManajemenGuruController::index():
 * - $guruList (tiap item sudah termasuk 'murid'), $jabatanOptions, $muridOptions
 * - $canEdit, $search, $jabatanId
 */
$headerActions = '';

require VIEW_PATH . '/layouts/shell-header.php';
?>
<div class="list-toolbar">
    <form method="GET" action="<?= BASE_PATH ?>/manajemen-guru" class="list-filter">
        <input type="text" name="q" class="field-input" placeholder="Cari" value="<?= e($search) ?>">
        <select name="jabatan_id" class="field-input" onchange="this.form.submit()">
            <option value="">Semua Jabatan</option>
            <?php foreach ($jabatanOptions as $j): ?>
            <option value="<?= $j['id'] ?>" <?= $jabatanId == $j['id'] ? 'selected' : '' ?>><?= e($j['nama']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-tertiary">Cari</button>
    </form>
</div>

<?php if (empty($guruList)): ?>
<p class="text-body-sm">Belum ada karyawan dengan role Guru. Tambahkan lewat modul Daftar Karyawan (pastikan jabatannya diberi role "Guru").</p>
<?php endif; ?>

<?php foreach ($guruList as $guru): ?>
<div class="guru-murid-card">
    <div class="guru-murid-card-header">
        <div>
            <div class="text-body-sm font-bold"><?= e($guru['nama']) ?></div>
            <div class="text-caption-md"><?= e($guru['nama_jabatan']) ?></div>
        </div>
        <span class="badge badge-netral"><?= count($guru['murid']) ?></span>
        <?php if ($canEdit): ?>
        <div class="action-menu" data-action-menu>
            <button type="button" class="action-menu-toggle" data-action-menu-toggle><?= icon('icon_more_vertical') ?></button>
            <div class="action-menu-dropdown">
                <button type="button" data-modal-open="modal-atur-murid-guru-<?= $guru['id'] ?>">Atur Anak Murid</button>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <div class="guru-murid-card-list">
        <?php foreach ($guru['murid'] as $m): ?>
        <div class="guru-murid-item">
            <?= e($m['nama_lengkap']) ?>
            <span class="text-caption-md">— <?= e(trim(($m['level_kelas'] ?? '') . ' ' . ($m['nama_kelas'] ?? '')) ?: 'Belum ada kelas') ?></span>
        </div>
        <?php endforeach; ?>
        <?php if (empty($guru['murid'])): ?>
        <div class="guru-murid-item text-caption-md">Belum ada murid diampu.</div>
        <?php endif; ?>
    </div>
</div>

<?php if ($canEdit): ?>
<div class="modal-overlay" id="modal-atur-murid-guru-<?= $guru['id'] ?>">
    <div class="modal-box modal-lg">
        <h2 class="modal-title">Atur Anak Murid</h2>
        <div class="assign-guru-context">
            <div class="text-body-sm font-bold"><?= e($guru['nama']) ?></div>
            <div class="text-caption-md"><?= e($guru['nama_jabatan']) ?></div>
        </div>
        <form method="POST" action="<?= BASE_PATH ?>/manajemen-guru/<?= $guru['id'] ?>/murid" class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
            <div class="assign-list-card" data-assign-list>
                <div class="assign-list-header">
                    <span class="text-body-sm font-bold">Daftar Murid</span>
                    <button type="button" class="btn btn-tertiary" data-assign-add>+ Tambah Murid</button>
                </div>
                <div class="assign-list-rows" data-assign-rows>
                    <?php foreach ($guru['murid'] as $m): ?>
                    <div class="assign-list-row" data-assign-row>
                        <select name="murid_ids[]" class="field-input">
                            <?php foreach ($muridOptions as $opt): ?>
                            <option value="<?= $opt['id'] ?>" <?= $opt['id'] == $m['id'] ? 'selected' : '' ?>><?= e($opt['nama_lengkap'] . ' — ' . $opt['level_kelas'] . ' ' . $opt['nama_kelas']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="assign-list-remove" data-assign-remove><?= icon('icon_trash') ?></button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <template data-assign-template>
                    <div class="assign-list-row" data-assign-row>
                        <select name="murid_ids[]" class="field-input">
                            <?php foreach ($muridOptions as $opt): ?>
                            <option value="<?= $opt['id'] ?>"><?= e($opt['nama_lengkap'] . ' — ' . $opt['level_kelas'] . ' ' . $opt['nama_kelas']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="assign-list-remove" data-assign-remove><?= icon('icon_trash') ?></button>
                    </div>
                </template>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-tertiary" data-modal-close>Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php endforeach; ?>

<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
