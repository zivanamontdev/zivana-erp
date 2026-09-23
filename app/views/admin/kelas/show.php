<?php
/**
 * Halaman Detail Kelas — cookbook/design-system.md (Murid > Manajemen Kelas > Detail).
 * Pattern "Atur Anak Murid" di sini reusable, dipakai lagi di Manajemen Guru (Fase 4).
 *
 * Variabel dari KelasController::show():
 * - $kelas, $canEdit, $guruMuridGroups, $guruTersedia, $muridDiKelasIni, $assignedElsewhere
 */
$headerActions = $canEdit
    ? uiButton('Ubah Data Kelas', 'outline', ['marginVertical' => 0, 'attributes' => ['data-modal-open' => 'modal-ubah-kelas-' . (int) $kelas['id']]])
    : '';

require VIEW_PATH . '/layouts/shell-header.php';
?>
<?php if (!empty($_SESSION['assignment_error'])): ?>
    <p role="alert"><?= uiText($_SESSION['assignment_error'], 'body-sm', ['tone' => 'status-inactive']) ?></p>
    <?php unset($_SESSION['assignment_error']); ?>
<?php endif; ?>
<div class="field-row class-detail-summary">
    <?= uiDataCard('Level Kelas', $kelas['level_kelas']) ?>
    <?= uiDataCard('Nama Kelas', $kelas['nama_kelas']) ?>
</div>

<?= uiText('Daftar Guru & Murid', 'body-sm', ['tag' => 'h2', 'weight' => 'bold', 'class' => 'class-detail-heading']) ?>

<?php if (empty($guruMuridGroups)): ?>
<p class="text-body-sm">Belum ada guru yang memiliki murid di kelas ini. Atur penugasan murid melalui Manajemen Guru.</p>
<?php endif; ?>

<?php foreach ($guruMuridGroups as $group): ?>
<div class="guru-murid-card teacher-management-card class-detail-teacher">
    <div class="guru-murid-card-header">
        <div>
            <?= uiText($group['nama_guru'], 'body-sm', ['tag' => 'div']) ?>
            <?= uiText($group['nama_jabatan'], 'caption-md', ['tag' => 'div']) ?>
        </div>
        <?= uiText((string) count($group['murid']), 'body-sm', ['font' => 'geist', 'tone' => 'preview']) ?>
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
        <a class="guru-murid-item class-student-link" href="<?= BASE_PATH ?>/murid/<?= (int) $m['id'] ?>">
            <?= uiText($m['nama_lengkap'], 'body-sm', ['class' => 'teacher-student-name']) ?>
            <?= icon('icon_chevron', 'teacher-student-chevron') ?>
        </a>
        <?php endforeach; ?>
        <?php if (empty($group['murid'])): ?>
        <div class="guru-murid-item text-caption-md">Belum ada murid diampu.</div>
        <?php endif; ?>
    </div>
</div>

<?php if ($canEdit) require __DIR__ . '/_assignment-modal.php'; ?>
<?php endforeach; ?>

<?php if ($canEdit): ?>
<?php $classRecord = $kelas; $returnToDetail = true; require __DIR__ . '/_form-modal.php'; ?>
<?php endif; ?>

<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
