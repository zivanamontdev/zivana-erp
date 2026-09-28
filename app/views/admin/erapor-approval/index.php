<?php
// Filter sejajar judul: Butuh Persetujuan (antrean) dan Selesai (riwayat yang sudah disetujui akun ini).
$filterLink = static fn(string $label, string $query, bool $active): string => '<a class="ui-button ui-button--' . ($active ? 'tabular-active' : 'tabular-inactive') . ' erapor-approval-filter"'
    . ($active ? ' aria-current="page"' : '') . ' href="' . BASE_PATH . '/erapor/persetujuan' . $query . '">' . e($label) . '</a>';
$headerActions = '<div class="erapor-approval-filters" role="group" aria-label="Filter persetujuan">'
    . $filterLink('Butuh Persetujuan', '', empty($done)) . $filterLink('Selesai', '?status=selesai', !empty($done)) . '</div>';
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
require VIEW_PATH . '/layouts/shell-header.php';
?>

<?php if ($notice): ?>
<p class="erapor-approval-notice" role="status"><?= uiText($notice['message'], 'body-sm', ['tone' => $notice['type'] === 'success' ? 'success' : 'status-inactive']) ?></p>
<?php endif; ?>

<p class="text-body-sm erapor-approval-intro"><?= !empty($done)
    ? (!empty($monitor) ? 'Mode pantau: seluruh persetujuan yang sudah diberikan.' : 'Riwayat rapor yang sudah Anda setujui. Buka Tinjau untuk melihat isi dan Pratinjau PDF beserta tanda tangannya.')
    : (!empty($monitor)
        ? 'Mode pantau: seluruh rapor yang menunggu persetujuan. Hanya dapat dilihat; persetujuan dilakukan oleh penyetuju yang ditugaskan.'
        : 'Tinjau hanya rapor yang ditugaskan kepada akun Anda. Persetujuan mengikuti urutan dan cakupan dokumen yang tersimpan pada sesi rapor.') ?></p>

<div class="data-table-wrapper erapor-approval-table">
    <table class="data-table">
        <thead>
            <tr>
                <th>Murid</th>
                <th>Periode</th>
                <th>Persetujuan</th>
                <th>Dokumen dalam cakupan</th>
                <th>Status</th>
                <th class="col-action"></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($tasks)): ?>
            <tr><td colspan="6" class="data-table-empty"><?= !empty($done) ? 'Belum ada rapor yang Anda setujui.' : 'Tidak ada rapor yang menunggu persetujuan dari Anda.' ?></td></tr>
            <?php endif; ?>
            <?php foreach ($tasks as $task): ?>
            <tr>
                <td><?= e($task['student']) ?></td>
                <td><?= e($task['period']) ?></td>
                <td><?= e($task['label']) ?></td>
                <td><?= e($task['integrity_error'] ? 'Cakupan tidak dapat diverifikasi' : implode(', ', $task['documents'])) ?></td>
                <td>
                    <?php if ($task['integrity_error']): ?>
                        <?= uiBadge('Perlu diperiksa', 'destruktif') ?>
                    <?php elseif (!empty($done)): ?>
                        <?= uiBadge('Disetujui' . (!empty($task['approved_at']) ? ' ' . date('d/m/Y', strtotime($task['approved_at'])) : ''), 'positif') ?>
                        <?php if (empty($task['all_approved'])): ?><?= uiText('Menunggu penyetuju lain', 'caption-md', ['tone' => 'muted', 'tag' => 'p']) ?><?php endif; ?>
                    <?php elseif ($task['can_approve'] || (!empty($monitor) && empty($task['waiting_for']))): ?>
                        <?= uiBadge('Siap ditinjau', 'peringatan') ?>
                    <?php else: ?>
                        <?= uiBadge('Menunggu tahap sebelumnya', 'netral') ?>
                    <?php endif; ?>
                </td>
                <td class="col-action">
                    <?php if ($task['integrity_error']): ?>
                        <?= uiText('Tidak dapat ditinjau', 'caption-md', ['tone' => 'status-inactive']) ?>
                    <?php else: ?>
                    <a class="ui-button ui-button--outline erapor-approval-review-link" href="<?= BASE_PATH ?>/erapor/persetujuan/<?= (int) $task['session_id'] ?>/<?= (int) $task['approval_id'] ?>">Tinjau</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
