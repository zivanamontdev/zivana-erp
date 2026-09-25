<?php
/**
 * Manajemen Rapor: lima rubrik eRapor terkunci hasil seed spesifikasi (eRapor_Zivana_Spesifikasi).
 * Variabel dari TemplateRaporController::index(): $rubrics, $canPdf
 */
$headerActions = '';
$months = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$formatDate = static function (?string $value) use ($months): string {
    if (!$value) return '—';
    $d = new DateTimeImmutable($value);
    return $d->format('j') . ' ' . $months[(int) $d->format('n')] . ' ' . $d->format('Y, H:i');
};
$scope = ['RTS' => 'Semua murid', 'AGAMA' => 'Semua murid', 'UMMI' => 'Semua murid', 'BING' => 'Semua murid', 'PPI' => 'Khusus murid ABK'];
require VIEW_PATH . '/layouts/shell-header.php';
?>
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/manajemen-rapor.css?v=<?= filemtime(ROOT_PATH . '/public/assets/css/manajemen-rapor.css') ?>">

<div class="report-template-sections">
    <section class="report-template-section" aria-labelledby="report-section-tengah">
        <?= uiText('Rapor Tengah Semester', 'body-sm', ['tag' => 'h2', 'attributes' => ['id' => 'report-section-tengah']]) ?>
        <div class="data-table-wrapper">
            <table class="data-table report-template-table" aria-labelledby="report-section-tengah">
                <thead><tr>
                    <th scope="col" class="report-stage">Urutan Tahapan</th>
                    <th scope="col">Template</th>
                    <th scope="col">Berlaku untuk</th>
                    <th scope="col" class="report-updated">Terakhir Diperbarui</th>
                    <th scope="col" class="col-action"><span class="ui-visually-hidden">Aksi</span></th>
                </tr></thead>
                <tbody>
                    <?php foreach ($rubrics as $index => $rubric): $slug = strtolower($rubric['jenis_dokumen']); ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= e($rubric['nama']) ?> <span class="report-template-code"><?= e($rubric['kode']) ?></span></td>
                        <td><?= e($scope[$rubric['jenis_dokumen']] ?? '—') ?></td>
                        <td class="report-updated"><?= e($formatDate($rubric['seeded_at'])) ?></td>
                        <td class="col-action report-template-actions">
                            <a class="ui-button ui-button--outline" href="<?= BASE_PATH ?>/kurikulum/manajemen-template/erapor/<?= e($slug) ?>">Pratinjau</a>
                            <?php if ($canPdf): ?>
                            <a class="ui-button ui-button--outline" href="<?= BASE_PATH ?>/kurikulum/manajemen-template/erapor/<?= e($slug) ?>/pdf?unduh=1">Simpan PDF</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <section class="report-template-section" aria-labelledby="report-section-akhir">
        <?= uiText('Rapor Akhir Semester', 'body-sm', ['tag' => 'h2', 'attributes' => ['id' => 'report-section-akhir']]) ?>
        <p class="report-template-note">Rapor Akhir Semester (RAS) menunggu spesifikasi rubrik dari sekolah. Agama, Ummi, dan Bahasa Inggris memakai template yang sama dengan Tengah Semester pada kolom Akhir Semester.</p>
    </section>
</div>
<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
