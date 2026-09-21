<?php
/**
 * Halaman Manajemen Template — cookbook/design-system.md 5.2.
 * Read-only (semua template bertipe "System", tidak ada tombol tambah).
 *
 * Variabel dari TemplateRaporController::index(): $templateList
 */
$kategoriLabel = ['rapor_murid' => 'Rapor Murid', 'rapor_sekolah' => 'Rapor Sekolah'];
$headerActions = '';

require VIEW_PATH . '/layouts/shell-header.php';
?>
<div class="list-toolbar">
    <form method="GET" action="<?= BASE_PATH ?>/kurikulum/manajemen-template" class="list-filter">
        <select name="tipe" class="field-input" onchange="this.form.submit()">
            <option value="">Semua Tipe</option>
            <option value="system">System</option>
            <option value="custom">Custom</option>
        </select>
        <select name="kategori" class="field-input" onchange="this.form.submit()">
            <option value="">Semua Kategori</option>
            <option value="rapor_murid">Rapor Murid</option>
            <option value="rapor_sekolah">Rapor Sekolah</option>
        </select>
    </form>
</div>

<div class="data-table-wrapper">
    <table class="data-table">
        <thead>
            <tr>
                <th>Template</th>
                <th>Kategori</th>
                <th>Tipe</th>
                <th>Status</th>
                <th>Terakhir Diperbarui</th>
                <th class="col-action"></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($templateList)): ?>
            <tr><td colspan="6" class="data-table-empty">Belum ada data template.</td></tr>
            <?php endif; ?>
            <?php foreach ($templateList as $t): ?>
            <tr>
                <td><a href="<?= BASE_PATH ?>/kurikulum/manajemen-template/<?= $t['id'] ?>"><?= e($t['nama']) ?></a></td>
                <td><?= e($kategoriLabel[$t['kategori']] ?? $t['kategori']) ?></td>
                <td><?= ucfirst(e($t['tipe'])) ?></td>
                <td><span class="badge <?= $t['is_active'] ? 'badge-positif' : 'badge-netral' ?>"><?= $t['is_active'] ? 'Aktif' : 'Nonaktif' ?></span></td>
                <td><?= date('d/m/Y', strtotime($t['updated_at'])) ?></td>
                <td class="col-action">
                    <a href="<?= BASE_PATH ?>/kurikulum/manajemen-template/<?= $t['id'] ?>"><?= icon('icon_more_vertical') ?></a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
