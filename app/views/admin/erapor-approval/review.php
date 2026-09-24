<?php
$studentName = (string) $review['student']['nama_lengkap'];
$approval = $review['approval'];
$sessionId = (int) $review['session']['id'];
$approvalId = (int) $approval['id'];
$modalId = 'modal-setujui-erapor-' . $sessionId . '-' . $approvalId;
$headerActions = '<a class="ui-button ui-button--outline" href="' . BASE_PATH . '/erapor/persetujuan">Kembali ke Antrean</a>';
if ($canApprove) {
    $headerActions .= ' ' . uiButton('Setujui', 'primary', ['marginVertical' => 0, 'attributes' => ['data-modal-open' => $modalId]]);
}
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
require VIEW_PATH . '/layouts/shell-header.php';
?>

<?php if ($notice): ?>
<p class="erapor-approval-notice" role="status"><?= uiText($notice['message'], 'body-sm', ['tone' => $notice['type'] === 'success' ? 'success' : 'status-inactive']) ?></p>
<?php endif; ?>

<div class="field-row erapor-approval-summary">
    <?= uiDataCard('Nama Murid', $studentName) ?>
    <?= uiDataCard('NISN', $review['student']['nisn'] ?? null) ?>
    <?= uiDataCard('Kelas', trim(($review['student']['level_kelas'] ?? '') . ' ' . ($review['student']['nama_kelas'] ?? '')) ?: null) ?>
    <?= uiDataCard('Periode Rapor', $review['period']['nama'] . ' · ' . $review['period']['tahun_label']) ?>
</div>

<div class="erapor-approval-state" role="status">
    <?php if ($review['all_approved']): ?>
        <?= uiBadge('Menunggu publikasi', 'peringatan') ?>
        <?= uiText('Semua tahap persetujuan tercatat. Rapor belum diterbitkan dan belum berstatus selesai.', 'body-sm') ?>
    <?php elseif ($approval['status'] === 'DISETUJUI'): ?>
        <?= uiBadge('Persetujuan tercatat', 'positif') ?>
        <?= uiText('Persetujuan ini sudah tercatat dan tidak dapat diulang.', 'body-sm') ?>
    <?php elseif ($review['waiting_for']): ?>
        <?= uiBadge('Menunggu tahap sebelumnya', 'netral') ?>
        <?= uiText('Dapat ditinjau setelah: ' . implode(', ', $review['waiting_for']), 'body-sm') ?>
    <?php else: ?>
        <?= uiBadge('Siap ditinjau', 'peringatan') ?>
        <?= uiText('Dokumen yang ditampilkan dibatasi pada cakupan persetujuan yang ditugaskan kepada Anda.', 'body-sm') ?>
    <?php endif; ?>
</div>

<?php foreach ($review['documents'] as $document): ?>
    <?php ob_start(); ?>
    <h2 class="erapor-approval-document-title"><?= uiText($document['nama'], 'headline-sm', ['tag' => 'span']) ?></h2>
    <div class="data-table-wrapper">
        <table class="data-table erapor-approval-values">
            <thead><tr><th>Aspek Penilaian</th><th>Isian Guru</th></tr></thead>
            <tbody>
                <?php foreach ($document['display_rows'] as $row): ?>
                <tr><td><?= e($row['label']) ?></td><td><?= e($row['value']) ?></td></tr>
                <?php endforeach; ?>
                <?php if (empty($document['display_rows'])): ?>
                <tr><td colspan="2" class="data-table-empty">Tidak ada butir penilaian pada dokumen ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php echo uiCard(ob_get_clean(), 'outlined', ['tag' => 'section', 'class' => 'erapor-approval-document']); ?>
<?php endforeach; ?>

<?php if ($canApprove): ?>
    <?php ob_start(); ?>
    <form method="POST" action="<?= BASE_PATH ?>/erapor/persetujuan/<?= $sessionId ?>/<?= $approvalId ?>/setujui" class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
        <div class="modal-actions">
            <?= uiButton('Batal', 'outline', ['marginVertical' => 0, 'attributes' => ['data-modal-close' => true]]) ?>
            <?= uiButton('Setujui Persetujuan', 'primary', ['type' => 'submit', 'marginVertical' => 0]) ?>
        </div>
    </form>
    <?php echo uiModal($modalId, 'Setujui Persetujuan?', ob_get_clean(), [
        'variant' => 'delete',
        'description' => 'Persetujuan ini akan dicatat sebagai bukti resmi dan tidak dapat dibatalkan. Pastikan seluruh dokumen dalam cakupan Anda sudah ditinjau.',
    ]); ?>
<?php endif; ?>

<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
