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

ob_start();
?>
<div class="list-toolbar">
    <form method="GET" action="<?= BASE_PATH ?>/manajemen-guru" class="list-filter">
        <?= uiField('q', 'Cari', ['type' => 'search', 'value' => $search, 'placeholder' => 'Cari', 'icon' => 'icon_search', 'iconPosition' => 'left', 'hideLabel' => true, 'id' => 'list-search']) ?>
        <?= uiFilter('jabatan_id', 'Jabatan', ['' => 'Semua Jabatan'] + array_column($jabatanOptions, 'nama', 'id'), ['value' => $jabatanId, 'id' => 'filter-jabatan_id', 'marginVertical' => 0, 'attributes' => ['onchange' => 'this.form.submit()']]) ?>
    </form>
</div>
<?php
$headerActions = ob_get_clean() . $headerActions;
require VIEW_PATH . '/layouts/shell-header.php';
?>
<?php if (!empty($_SESSION['assignment_error'])): ?>
    <p role="alert"><?= uiText($_SESSION['assignment_error'], 'body-sm', ['tone' => 'status-inactive']) ?></p>
    <?php unset($_SESSION['assignment_error']); ?>
<?php endif; ?>

<?php if (empty($guruList)): ?>
<p class="text-body-sm">Belum ada karyawan aktif dengan jabatan Guru Kelas atau Guru Shadow yang sesuai pencarian. Tambahkan atau atur jabatannya melalui Daftar Karyawan.</p>
<?php endif; ?>

<?php foreach ($guruList as $guru): ?>
<div class="guru-murid-card teacher-management-card">
    <div class="guru-murid-card-header">
        <div>
            <div class="text-body-sm font-bold"><?= e($guru['nama']) ?></div>
            <div class="text-caption-md"><?= e($guru['nama_jabatan']) ?></div>
        </div>
        <?= uiText((string) count($guru['murid']), 'body-sm', ['font' => 'geist', 'tone' => 'preview']) ?>
        <?php if ($canEdit): ?>
        <button type="button" class="action-menu-toggle" data-modal-open="modal-atur-murid-guru-<?= (int) $guru['id'] ?>" aria-label="<?= e('Atur Anak Murid untuk ' . $guru['nama']) ?>" aria-haspopup="dialog"><?= icon('icon_more_vertical') ?></button>
        <?php endif; ?>
    </div>
    <div class="guru-murid-card-list">
        <?php foreach ($guru['murid'] as $m): ?>
        <div class="guru-murid-item">
            <?= uiText($m['nama_lengkap'], 'body-sm', ['class' => 'teacher-student-name']) ?>
            <div class="teacher-student-class">
                <?= uiText(trim(($m['level_kelas'] ?? '') . ' ' . ($m['nama_kelas'] ?? '')) ?: 'Belum ada kelas', 'body-sm') ?>
                <?php if (uiCan('Murid', 'Manajemen Murid')): ?>
                <a class="class-student-link" href="<?= BASE_PATH ?>/murid/<?= (int) $m['id'] ?>" aria-label="<?= e('Lihat detail ' . $m['nama_lengkap']) ?>"><?= icon('icon_chevron', 'teacher-student-chevron') ?></a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($guru['murid'])): ?>
        <div class="guru-murid-item text-caption-md">Belum ada murid diampu.</div>
        <?php endif; ?>
    </div>
</div>

<?php if ($canEdit) require __DIR__ . '/_assignment-modal.php'; ?>
<?php endforeach; ?>

<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
