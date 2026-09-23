<?php
/**
 * Daftar Murid — Portal Guru (read-only, versi guru).
 * cookbook/design-system.md: kolom lebih sedikit dari versi Admin,
 * tanpa Status, tanpa aksi CRUD.
 *
 * Variabel dari PortalGuruController::daftarMurid(): $muridList
 */
$headerActions = '<form method="GET" action="' . BASE_PATH . '/portal-guru/murid" class="list-filter">'
    . uiField('q', 'Cari murid', ['type'=>'search','value'=>$search ?? '', 'placeholder'=>'Cari',
        'icon'=>'icon_search','iconPosition'=>'left','hideLabel'=>true,'id'=>'list-search']) . '</form>';
require VIEW_PATH . '/layouts/shell-header.php';
?>
<div class="data-table-wrapper student-list-table">
    <table class="data-table">
        <thead>
            <tr>
                <th>NISN</th>
                <th>Nama Lengkap</th>
                <th>Level Kelas</th>
                <th>Kelas</th>
                <th>Jenis Kelamin</th>
                <th class="col-action"><span class="ui-visually-hidden">Detail Murid</span></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($muridList)): ?>
            <tr><td colspan="6" class="data-table-empty">Belum ada murid yang diampu.</td></tr>
            <?php endif; ?>
            <?php foreach ($muridList as $m): ?>
            <tr>
                <td><?= e($m['nisn'] ?: '-') ?></td>
                <td><?= e($m['nama_lengkap']) ?></td>
                <td><?= e($m['level_kelas'] ?? '-') ?></td>
                <td><?= e($m['nama_kelas'] ?? '-') ?></td>
                <td><?= $m['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></td>
                <td class="col-action"><a class="action-menu-toggle" href="<?= BASE_PATH ?>/portal-guru/murid/<?= (int)$m['id'] ?>" aria-label="<?= e('Lihat detail ' . $m['nama_lengkap']) ?>"><?= icon('icon_more_vertical') ?></a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
