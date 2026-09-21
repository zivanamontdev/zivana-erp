<?php
/**
 * Daftar Murid — Portal Guru (read-only, versi guru).
 * cookbook/design-system.md: kolom lebih sedikit dari versi Admin,
 * tanpa Status, tanpa aksi CRUD.
 *
 * Variabel dari PortalGuruController::daftarMurid(): $muridList
 */
$headerActions = '';
require VIEW_PATH . '/layouts/shell-header.php';
?>
<div class="data-table-wrapper">
    <table class="data-table">
        <thead>
            <tr>
                <th>NISN</th>
                <th>Nama Lengkap</th>
                <th>Level Kelas</th>
                <th>Kelas</th>
                <th>Jenis Kelamin</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($muridList)): ?>
            <tr><td colspan="5" class="data-table-empty">Belum ada murid yang diampu.</td></tr>
            <?php endif; ?>
            <?php foreach ($muridList as $m): ?>
            <tr>
                <td><?= e($m['nisn'] ?: '-') ?></td>
                <td><?= e($m['nama_lengkap']) ?></td>
                <td><?= e($m['level_kelas'] ?? '-') ?></td>
                <td><?= e($m['nama_kelas'] ?? '-') ?></td>
                <td><?= $m['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
