<?php
/**
 * Halaman Manajemen Murid — cookbook/design-system.md (Murid > Manajemen Murid).
 *
 * Variabel dari MuridController::index():
 * - $muridList, $kelasOptions, $canEdit, $search, $kelasId, $status
 */
$statusLabel = [
    'bersekolah' => ['Bersekolah', 'badge-positif'],
    'tanpa_keterangan' => ['Tanpa Keterangan', 'badge-peringatan'],
    'tamat' => ['Tamat', 'badge-netral'],
    'berhenti' => ['Berhenti', 'badge-netral'],
];

$headerActions = '';
if ($canEdit) {
    $headerActions = '<button type="button" class="btn btn-tertiary" disabled title="[ASUMSI] Fitur Import belum dikonfirmasi user (format file/mapping kolom), lihat cookbook/prd.md poin asumsi #6">Import</button> '
        . '<a href="' . BASE_PATH . '/murid/tambah" class="btn btn-primary">Tambah Murid</a>';
}

require VIEW_PATH . '/layouts/shell-header.php';
?>
<div class="list-toolbar">
    <form method="GET" action="<?= BASE_PATH ?>/murid" class="list-filter">
        <input type="text" name="q" class="field-input" placeholder="Cari" value="<?= e($search) ?>">
        <select name="kelas_id" class="field-input" onchange="this.form.submit()">
            <option value="">Semua Kelas</option>
            <?php foreach ($kelasOptions as $k): ?>
            <option value="<?= $k['id'] ?>" <?= $kelasId == $k['id'] ? 'selected' : '' ?>><?= e($k['level_kelas'] . ' - ' . $k['nama_kelas']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status" class="field-input" onchange="this.form.submit()">
            <option value="">Semua Status</option>
            <?php foreach ($statusLabel as $val => [$label, ]): ?>
            <option value="<?= $val ?>" <?= $status === $val ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-tertiary">Cari</button>
    </form>
</div>

<div class="data-table-wrapper">
    <table class="data-table">
        <thead>
            <tr>
                <th>Nama Lengkap</th>
                <th>NISN</th>
                <th>Kelas</th>
                <th>Jenis Kelamin</th>
                <th>Guru Kelas</th>
                <th>Status</th>
                <th class="col-action"></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($muridList)): ?>
            <tr><td colspan="7" class="data-table-empty">Belum ada data murid. Tambahkan murid pertama lewat tombol "Tambah Murid".</td></tr>
            <?php endif; ?>
            <?php foreach ($muridList as $m): ?>
            <tr>
                <td><a href="<?= BASE_PATH ?>/murid/<?= $m['id'] ?>"><?= e($m['nama_lengkap']) ?></a></td>
                <td><?= e($m['nisn'] ?: '-') ?></td>
                <td><?= e($m['nama_kelas'] ?? '-') ?></td>
                <td><?= $m['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></td>
                <td><?= e($m['nama_guru_kelas'] ?? '-') ?></td>
                <td><span class="badge <?= $statusLabel[$m['status']][1] ?>"><?= $statusLabel[$m['status']][0] ?></span></td>
                <td class="col-action">
                    <?php if ($canEdit): ?>
                    <div class="action-menu" data-action-menu>
                        <button type="button" class="action-menu-toggle" data-action-menu-toggle><?= icon('icon_more_vertical') ?></button>
                        <div class="action-menu-dropdown">
                            <button type="button" onclick="window.location='<?= BASE_PATH ?>/murid/<?= $m['id'] ?>/ubah'">Ubah</button>
                        </div>
                    </div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
