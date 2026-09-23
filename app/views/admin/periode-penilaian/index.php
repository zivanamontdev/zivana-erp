<?php
/**
 * Halaman Periode Penilaian — cookbook/design-system.md (Sekolah > Kurikulum).
 *
 * Variabel dari PeriodePenilaianController::index():
 * - $periodeList, $tipeOptions, $kategoriOptions, $canEdit, $tipe, $kategori
 */
$headerActions = $canEdit
    ? uiButton('Tambah Periode', 'primary', ['marginVertical' => 0, 'attributes' => ['data-modal-open' => 'modal-tambah-periode']])
    : '';

ob_start();
?>
<div class="list-toolbar">
    <form method="GET" action="<?= BASE_PATH ?>/kurikulum/periode-penilaian" class="list-filter">
        <?= uiFilter('tipe', 'Tipe', ['' => 'Semua Tipe'] + $tipeOptions, ['value' => $tipe, 'id' => 'filter-tipe', 'marginVertical' => 0, 'attributes' => ['onchange' => 'this.form.submit()']]) ?>
        <?= uiFilter('kategori', 'Kategori', ['' => 'Semua Kategori'] + $kategoriOptions, ['value' => $kategori, 'id' => 'filter-kategori', 'marginVertical' => 0, 'attributes' => ['onchange' => 'this.form.submit()']]) ?>
    </form>
</div>
<?php
$headerActions = ob_get_clean() . $headerActions;
require VIEW_PATH . '/layouts/shell-header.php';
?>

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

<?php require VIEW_PATH . '/admin/periode-penilaian/_modals.php'; ?>
<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
