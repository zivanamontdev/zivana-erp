<?php
/** Existing accordion + data-table components, flattened to one period. */
$rows = [];
foreach ($periode['sesi'] as $session) foreach ($session['rapor'] as $report) $rows[] = $report;
usort($rows, static fn($a, $b) => strcasecmp($a['nama_lengkap'], $b['nama_lengkap']));
$bodyId = 'report-period-' . (int) $periode['id'];
$open = $index === 0;
?>
<section class="accordion-item report-period-table<?= $open ? ' is-open' : '' ?>">
    <button type="button" class="accordion-header" data-accordion-toggle aria-expanded="<?= $open ? 'true' : 'false' ?>" aria-controls="<?= $bodyId ?>">
        <span class="report-period-heading">
            <?= uiText($periode['nama'], 'body-sm') ?>
            <?= uiText(date('d/m/Y', strtotime($periode['awal_periode'])) . ' - ' . date('d/m/Y', strtotime($periode['akhir_periode'])), 'caption-md') ?>
        </span>
        <?= uiText((string) count($rows), 'body-sm', ['tone'=>'preview']) ?>
        <span class="accordion-chevron" aria-hidden="true"><?= icon('icon_chevron') ?></span>
    </button>
    <div class="accordion-body" id="<?= $bodyId ?>">
        <table class="data-table" aria-label="<?= e('Daftar rapor ' . $periode['nama']) ?>">
            <tbody>
            <?php foreach ($rows as $report): ?>
                <?php $available = in_array($report['status'], ['menunggu_persetujuan', 'disetujui'], true); ?>
                <tr><td>
                <?php if ($available): ?>
                    <a class="report-student-row" href="<?= BASE_PATH ?>/rapor-murid/<?= (int) $report['id'] ?>" aria-label="<?= e('Pratinjau rapor ' . $report['nama_lengkap']) ?>">
                <?php else: ?>
                    <div class="report-student-row is-disabled" aria-disabled="true">
                <?php endif; ?>
                    <?= uiText($report['nama_lengkap'], 'body-sm', ['class'=>'report-student-name']) ?>
                    <?php if ($report['status'] === 'belum_diisi'): ?>
                        <?= uiText('Belum diisi', 'body-sm', ['tone'=>'status-inactive']) ?>
                    <?php elseif ($report['status'] === 'menunggu_persetujuan'): ?>
                        <?= uiText('Menunggu persetujuan', 'body-sm', ['class'=>'report-status-pending']) ?>
                    <?php else: ?>
                        <span class="ui-visually-hidden">Disetujui</span>
                    <?php endif; ?>
                    <span class="report-student-chevron" aria-hidden="true"><?= icon('icon_chevron') ?></span>
                <?php if ($available): ?></a><?php else: ?></div><?php endif; ?>
                </td></tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td class="data-table-empty">Belum ada murid aktif pada periode ini.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
