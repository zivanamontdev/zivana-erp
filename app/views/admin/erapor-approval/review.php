<?php
$studentName = (string) $review['student']['nama_lengkap'];
$approval = $review['approval'];
$sessionId = (int) $review['session']['id'];
$approvalId = (int) $approval['id'];
$modalId = 'modal-setujui-erapor-' . $sessionId . '-' . $approvalId;
// Kembali ke antrean lewat breadcrumb.
$headerActions = '';
if (!empty($canPdf)) $headerActions .= '<a class="ui-button ui-button--outline" href="' . BASE_PATH . '/erapor/sesi/' . (int) $sessionId . '/pdf" target="_blank" rel="noopener">Pratinjau PDF</a>';
if ($canApprove) {
    $headerActions .= ' ' . uiButton('Setujui', 'primary', ['marginVertical' => 0, 'attributes' => ['data-modal-open' => $modalId]]);
}
$publishModalId = 'modal-terbitkan-erapor-' . $sessionId;
if (!empty($canPublish)) {
    $headerActions .= ' ' . uiButton('Terbitkan Rapor', 'primary', ['marginVertical' => 0, 'attributes' => ['data-modal-open' => $publishModalId]]);
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
    <?php if ($review['session']['status'] === 'SELESAI'): ?>
        <?= uiBadge('Terbit', 'positif') ?>
        <?= uiText('Rapor sudah diterbitkan. Guru PIC dapat membagikan tautan unduh kepada orang tua.', 'body-sm') ?>
    <?php elseif ($review['all_approved']): ?>
        <?= uiBadge('Menunggu diterbitkan', 'peringatan') ?>
        <?= uiText('Semua tahap persetujuan tercatat. Kepala Sekolah dapat menerbitkan rapor.', 'body-sm') ?>
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

<?php
// Satu tabel nilai; baris 'sub' menjadi subjudul (mis. "a. Perawatan Diri").
$renderValues = static function (array $rows): string {
    $html = '<div class="data-table-wrapper"><table class="data-table erapor-approval-values"><thead><tr><th>Aspek Penilaian</th><th>Isian Guru</th></tr></thead><tbody>';
    $openSub = null;
    foreach ($rows as $row) {
        $sub = (string) ($row['sub'] ?? '');
        if ($sub !== '' && $sub !== $openSub) $html .= '<tr class="erapor-approval-subrow"><th colspan="2">' . e($sub) . '</th></tr>';
        $openSub = $sub;
        $empty = $row['value'] === 'Belum dinilai';
        $html .= '<tr><td>' . e($row['label']) . '</td><td' . ($empty ? ' class="erapor-approval-empty"' : '') . '>' . e($row['value']) . '</td></tr>';
    }
    if (!$rows) $html .= '<tr><td colspan="2" class="data-table-empty">Tidak ada butir penilaian pada dokumen ini.</td></tr>';
    return $html . '</tbody></table></div>';
};
$filledCount = static fn(array $rows): string => count(array_filter($rows, static fn($row) => $row['value'] !== 'Belum dinilai')) . '/' . count($rows);
$chevron = '<span class="ui-disclosure-chevron" aria-hidden="true">' . icon('icon_chevron') . '</span>';
?>
<?php // Tiap rapor dapat dilipat; di dalamnya bagian (Area/Lingkup/Jilid) juga dapat dilipat, seperti halaman pengisian. ?>
<?php foreach ($review['documents'] as $document): ?>
    <?php
    $groups = [];
    foreach ($document['display_rows'] as $row) $groups[(string) ($row['group'] ?? '')][] = $row;
    ob_start();
    ?>
    <details class="ui-disclosure erapor-approval-disclosure">
        <summary class="ui-disclosure-trigger erapor-approval-document-title">
            <?= uiText($document['nama'], 'body-md', ['tag' => 'span', 'weight' => 'bold']) ?>
            <span class="erapor-approval-count"><?= e($filledCount($document['display_rows'])) ?></span>
            <?= $chevron ?>
        </summary>
        <div class="erapor-approval-groups">
            <?php foreach ($groups as $groupName => $rows): ?>
                <?php if ($groupName === ''): ?>
                    <?= $renderValues($rows) ?>
                <?php else: ?>
                    <details class="ui-disclosure erapor-approval-group">
                        <summary class="ui-disclosure-trigger"><span><?= e($groupName) ?></span><span class="erapor-approval-count"><?= e($filledCount($rows)) ?></span><?= $chevron ?></summary>
                        <?= $renderValues($rows) ?>
                    </details>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if (!$groups): ?><?= $renderValues([]) ?><?php endif; ?>
        </div>
    </details>
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

<?php if (!empty($canPublish)): ?>
    <?php ob_start(); ?>
    <form method="POST" action="<?= BASE_PATH ?>/erapor/persetujuan/<?= $sessionId ?>/<?= $approvalId ?>/terbitkan">
        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
        <div class="modal-actions">
            <?= uiButton('Batal', 'outline', ['marginVertical' => 0, 'attributes' => ['data-modal-close' => true]]) ?>
            <?= uiButton('Terbitkan Rapor', 'primary', ['type' => 'submit', 'marginVertical' => 0]) ?>
        </div>
    </form>
    <?php echo uiModal($publishModalId, 'Terbitkan Rapor?', ob_get_clean(), [
        'variant' => 'delete',
        'description' => 'PDF resmi disimpan ke penyimpanan rapor dan tautan unduh untuk orang tua dibuat (berlaku ' . EraporDistribution::LINK_DAYS . ' hari). Rapor berstatus Selesai dan tidak dapat diubah lagi.',
    ]); ?>
<?php endif; ?>

<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
