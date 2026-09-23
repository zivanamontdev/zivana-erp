<?php
/**
 * Halaman Detail Kelas — cookbook/design-system.md (Murid > Manajemen Kelas > Detail).
 * Pattern "Atur Anak Murid" di sini reusable, dipakai lagi di Manajemen Guru (Fase 4).
 *
 * Variabel dari KelasController::show():
 * - $kelas, $canEdit, $guruMuridGroups, $guruTersedia, $muridDiKelasIni, $assignedElsewhere
 */
$headerActions = $canEdit
    ? '<button type="button" class="ui-button ui-button--primary" data-modal-open="modal-tambah-guru">+ Tambah Guru</button>'
    : '';

require VIEW_PATH . '/layouts/shell-header.php';
?>
<div class="field-row">
    <div class="field">
        <label class="field-label">Level Kelas</label>
        <div class="field-input is-viewonly"><?= e($kelas['level_kelas']) ?></div>
    </div>
    <div class="field">
        <label class="field-label">Nama Kelas</label>
        <div class="field-input is-viewonly"><?= e($kelas['nama_kelas']) ?></div>
    </div>
</div>

<h2 class="text-body-sm font-bold">Daftar Guru &amp; Murid</h2>

<?php if (empty($guruMuridGroups)): ?>
<p class="text-body-sm">Belum ada guru yang ditugaskan di kelas ini. Tambahkan lewat tombol "+ Tambah Guru".</p>
<?php endif; ?>

<?php foreach ($guruMuridGroups as $group): ?>
<div class="guru-murid-card">
    <div class="guru-murid-card-header">
        <div>
            <div class="text-body-sm font-bold"><?= e($group['nama_guru']) ?></div>
            <div class="text-caption-md"><?= e($group['nama_jabatan']) ?></div>
        </div>
        <span class="badge badge-netral"><?= count($group['murid']) ?></span>
        <?php if ($canEdit): ?>
        <div class="action-menu" data-action-menu>
            <button type="button" class="action-menu-toggle" data-action-menu-toggle><?= icon('icon_more_vertical') ?></button>
            <div class="action-menu-dropdown">
                <button type="button" data-modal-open="modal-atur-murid-<?= $group['guru_id'] ?>">Atur Anak Murid</button>
                <form method="POST" action="<?= BASE_PATH ?>/kelas/<?= $kelas['id'] ?>/guru/<?= $group['guru_id'] ?>/hapus">
                    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                    <button type="submit" class="is-destructive">Hapus dari Kelas</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <div class="guru-murid-card-list">
        <?php foreach ($group['murid'] as $m): ?>
        <div class="guru-murid-item"><?= e($m['nama_lengkap']) ?></div>
        <?php endforeach; ?>
        <?php if (empty($group['murid'])): ?>
        <div class="guru-murid-item text-caption-md">Belum ada murid diampu.</div>
        <?php endif; ?>
    </div>
</div>

<?php if ($canEdit): ?>
<div class="modal-overlay" id="modal-atur-murid-<?= $group['guru_id'] ?>">
    <div class="modal-box modal-lg">
        <h2 class="modal-title">Atur Anak Murid</h2>
        <div class="assign-guru-context">
            <div class="text-body-sm font-bold"><?= e($group['nama_guru']) ?></div>
            <div class="text-caption-md"><?= e($group['nama_jabatan']) ?></div>
        </div>
        <form method="POST" action="<?= BASE_PATH ?>/kelas/<?= $kelas['id'] ?>/guru-murid" class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
            <input type="hidden" name="guru_id" value="<?= $group['guru_id'] ?>">
            <div class="assign-list-card" data-assign-list>
                <div class="assign-list-header">
                    <span class="text-body-sm font-bold">Daftar Murid</span>
                    <button type="button" class="ui-button ui-button--outline" data-assign-add>+ Tambah Murid</button>
                </div>
                <div class="assign-list-rows" data-assign-rows>
                    <?php foreach ($group['murid'] as $m): ?>
                    <div class="assign-list-row" data-assign-row>
                        <select name="murid_ids[]" class="field-input">
                            <?php foreach ($muridDiKelasIni as $opt): ?>
                            <?php if (isset($assignedElsewhere[$opt['id']]) && $assignedElsewhere[$opt['id']] != $group['guru_id']) { continue; } ?>
                            <option value="<?= $opt['id'] ?>" <?= $opt['id'] == $m['id'] ? 'selected' : '' ?>><?= e($opt['nama_lengkap']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="assign-list-remove" data-assign-remove><?= icon('icon_trash') ?></button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <template data-assign-template>
                    <div class="assign-list-row" data-assign-row>
                        <select name="murid_ids[]" class="field-input">
                            <?php foreach ($muridDiKelasIni as $opt): ?>
                            <?php if (isset($assignedElsewhere[$opt['id']]) && $assignedElsewhere[$opt['id']] != $group['guru_id']) { continue; } ?>
                            <option value="<?= $opt['id'] ?>"><?= e($opt['nama_lengkap']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="assign-list-remove" data-assign-remove><?= icon('icon_trash') ?></button>
                    </div>
                </template>
            </div>
            <div class="modal-actions">
                <button type="button" class="ui-button ui-button--outline" data-modal-close>Batal</button>
                <button type="submit" class="ui-button ui-button--primary">Simpan</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php endforeach; ?>

<?php if ($canEdit): ?>
<div class="modal-overlay" id="modal-tambah-guru">
    <div class="modal-box modal-lg">
        <h2 class="modal-title">Tambah Guru</h2>
        <form method="POST" action="<?= BASE_PATH ?>/kelas/<?= $kelas['id'] ?>/guru-murid" class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
            <div class="field">
                <label class="field-label">Guru *</label>
                <select name="guru_id" class="field-input" required>
                    <option value="">Pilih guru</option>
                    <?php foreach ($guruTersedia as $g): ?>
                    <option value="<?= $g['id'] ?>"><?= e($g['nama']) ?> (<?= e($g['nama_jabatan']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="assign-list-card" data-assign-list>
                <div class="assign-list-header">
                    <span class="text-body-sm font-bold">Daftar Murid</span>
                    <button type="button" class="ui-button ui-button--outline" data-assign-add>+ Tambah Murid</button>
                </div>
                <div class="assign-list-rows" data-assign-rows></div>
                <template data-assign-template>
                    <div class="assign-list-row" data-assign-row>
                        <select name="murid_ids[]" class="field-input">
                            <?php foreach ($muridDiKelasIni as $opt): ?>
                            <?php if (isset($assignedElsewhere[$opt['id']])) { continue; } ?>
                            <option value="<?= $opt['id'] ?>"><?= e($opt['nama_lengkap']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="assign-list-remove" data-assign-remove><?= icon('icon_trash') ?></button>
                    </div>
                </template>
            </div>
            <div class="modal-actions">
                <button type="button" class="ui-button ui-button--outline" data-modal-close>Batal</button>
                <button type="submit" class="ui-button ui-button--primary">Simpan</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
