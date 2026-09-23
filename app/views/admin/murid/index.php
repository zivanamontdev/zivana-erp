<?php
/**
 * Halaman Manajemen Murid — cookbook/design-system.md (Murid > Manajemen Murid).
 *
 * Variabel dari MuridController::index():
 * - $muridList, $kelasOptions, $canEdit, $search, $kelasId, $status
 */
$statusLabel = [
    'bersekolah' => ['Bersekolah', 'positif'],
    'tanpa_keterangan' => ['Tanpa Keterangan', 'peringatan'],
    'tamat' => ['Tamat', 'netral'],
    'berhenti' => ['Berhenti', 'destruktif'],
];

$headerActions = '';
if (uiCan('Murid', 'Manajemen Murid', 'tambah')) {
    $headerActions = '<button type="button" class="ui-button ui-button--outline" disabled title="[ASUMSI] Fitur Import belum dikonfirmasi user (format file/mapping kolom), lihat cookbook/prd.md poin asumsi #6">Import</button> '
        . '<a href="' . BASE_PATH . '/murid/tambah" class="ui-button ui-button--primary"><span class="ui-button-label">Tambah Murid</span><span class="ui-button-icon" aria-hidden="true">' . icon('icon_plus') . '</span></a>';
}

ob_start();
?>
<div class="list-toolbar">
    <form method="GET" action="<?= BASE_PATH ?>/murid" class="list-filter">
        <?= uiField('q', 'Cari', ['type' => 'search', 'value' => $search, 'placeholder' => 'Cari', 'icon' => 'icon_search', 'iconPosition' => 'left', 'hideLabel' => true, 'id' => 'list-search']) ?>
        <?= uiFilter('kelas_id', 'Kelas', ['' => 'Semua Kelas'] + array_combine(array_column($kelasOptions, 'id'), array_map(static fn($kelas) => $kelas['level_kelas'] . ' - ' . $kelas['nama_kelas'], $kelasOptions)), ['value' => $kelasId, 'id' => 'filter-kelas_id', 'marginVertical' => 0, 'attributes' => ['onchange' => 'this.form.submit()']]) ?>
        <?= uiFilter('guru_id', 'Guru', ['' => 'Semua Guru'] + array_column($guruOptions, 'nama', 'id'), ['value' => $guruId, 'id' => 'filter-guru_id', 'marginVertical' => 0, 'attributes' => ['onchange' => 'this.form.submit()']]) ?>
        <?= uiFilter('status', 'Status', ['' => 'Semua Status'] + array_map(static fn($item) => $item[0], $statusLabel), ['value' => $status, 'id' => 'filter-status', 'marginVertical' => 0, 'attributes' => ['onchange' => 'this.form.submit()']]) ?>
    </form>
</div>
<?php
$headerActions = ob_get_clean() . $headerActions;
require VIEW_PATH . '/layouts/shell-header.php';
?>

<div class="data-table-wrapper student-list-table">
    <table class="data-table">
        <thead>
            <tr>
                <th>Nama Lengkap</th>
                <th>NISN</th>
                <th>Kelas</th>
                <th>Jenis Kelamin</th>
                <th>Kondisi</th>
                <th>Guru Kelas</th>
                <th>Status</th>
                <th class="col-action"></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($muridList)): ?>
            <tr><td colspan="8" class="data-table-empty">Tidak ada murid yang sesuai. Tambahkan murid atau sesuaikan pencarian dan filter.</td></tr>
            <?php endif; ?>
            <?php foreach ($muridList as $m): ?>
            <tr>
                <td><?= e($m['nama_lengkap']) ?></td>
                <td><?= e($m['nisn'] ?: '-') ?></td>
                <td><?= e($m['nama_kelas'] ?? '-') ?></td>
                <td><?= $m['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></td>
                <td><?= e(match (mb_strtolower(trim((string) ($m['status_kondisi'] ?? '')))) {
                    'reguler', 'regular' => 'Regular',
                    'berkebutuhan khusus', 'abk', 'anak berkebutuhan khusus', 'abk (anak berkebutuhan khusus)' => 'ABK',
                    default => '-',
                }) ?></td>
                <td><?= e($m['nama_guru_kelas'] ?? '-') ?></td>
                <td><?= uiBadge($statusLabel[$m['status']][0], $statusLabel[$m['status']][1]) ?></td>
                <td class="col-action">
                    <div class="action-menu" data-action-menu>
                        <button type="button" class="action-menu-toggle" data-action-menu-toggle><?= icon('icon_more_vertical') ?></button>
                        <div class="action-menu-dropdown">
                            <a href="<?= BASE_PATH ?>/murid/<?= (int) $m['id'] ?>">Lihat Detail</a>
                            <?php if ($canEdit): ?>
                            <button type="button" onclick="window.location='<?= BASE_PATH ?>/murid/<?= $m['id'] ?>/ubah'">Ubah</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
