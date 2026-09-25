<?php
/**
 * Rapor Murid saat eRapor aktif: daftar hanya-baca seluruh sesi per periode (Superadmin).
 * Variabel dari RaporMuridController::eraporIndex(): $tahunOptions, $tahunId, $periodeList (EraporOverview::forYear)
 */
$yearChoices = [];
foreach ($tahunOptions as $year) $yearChoices[$year['id']] = $year['tahun_awal'] . '/' . $year['tahun_akhir'];
$headerActions = '<form method="GET" action="' . BASE_PATH . '/rapor-murid" class="list-filter">'
    . uiFilter('tahun_ajaran_id', 'Tahun Ajaran', $yearChoices, ['value' => $tahunId, 'marginVertical' => 0, 'attributes' => ['onchange' => 'this.form.submit()']])
    . '</form>';
require VIEW_PATH . '/layouts/shell-header.php';
?>
<p class="text-body-sm">Ringkasan hanya-baca. Guru mengisi lewat Portal Guru, penyetuju menyetujui lewat Persetujuan eRapor.</p>
<?php if (!$periodeList): ?>
<p class="text-body-sm">Belum ada periode rapor pada tahun ajaran ini.</p>
<?php endif; ?>
<?php foreach ($periodeList as $index => $periode): $bodyId = 'erapor-period-' . (int) $periode['id']; $open = $periode['opened'] > 0 || ($index === 0 && !array_filter(array_column($periodeList, 'opened'))); ?>
<section class="accordion-item report-period-table<?= $open ? ' is-open' : '' ?>">
    <button type="button" class="accordion-header" data-accordion-toggle aria-expanded="<?= $open ? 'true' : 'false' ?>" aria-controls="<?= $bodyId ?>">
        <span class="report-period-heading">
            <?= uiText($periode['nama'], 'body-sm') ?>
            <?= uiText(date('d/m/Y', strtotime($periode['awal_periode'])) . ' - ' . date('d/m/Y', strtotime($periode['akhir_periode'])) . ' · ' . $periode['opened'] . ' dari ' . count($periode['rows']) . ' rapor dibuka', 'caption-md') ?>
        </span>
        <?= uiText((string) count($periode['rows']), 'body-sm', ['tone' => 'preview']) ?>
        <span class="accordion-chevron" aria-hidden="true"><?= icon('icon_chevron') ?></span>
    </button>
    <div class="accordion-body" id="<?= $bodyId ?>">
        <div class="data-table-wrapper">
        <table class="data-table erapor-overview-table" aria-label="<?= e('Rapor ' . $periode['nama']) ?>">
            <thead><tr><th scope="col">Murid</th><th scope="col">Kelas</th><th scope="col">Kondisi</th><th scope="col">Guru</th><th scope="col">Progres</th><th scope="col">Persetujuan</th><th scope="col">Status</th><th scope="col" class="col-action"><span class="ui-visually-hidden">PDF</span></th></tr></thead>
            <tbody>
            <?php foreach ($periode['rows'] as $row): [$label, $tone] = EraporOverview::STATUS[$row['status']] ?? [$row['status'], 'netral']; ?>
                <tr data-erapor-row>
                    <td><?= e($row['nama_lengkap']) ?></td>
                    <td><?= e(trim($row['level_kelas'] . ' ' . $row['nama_kelas'])) ?></td>
                    <td><?= e(EraporPackagePlan::normalizeCondition((string) $row['status_kondisi']) ?? '—') ?></td>
                    <td><?= e($row['guru_sesi'] ?? $row['guru'] ?? '—') ?></td>
                    <td><?= $row['required'] ? (int) $row['filled'] . '/' . (int) $row['required'] : '—' ?></td>
                    <td><?= e($row['tahap']) ?></td>
                    <td><?= uiBadge($label, $tone) ?></td>
                    <td class="col-action">
                        <?php if ($row['sesi_id']): ?>
                        <a class="ui-button ui-button--outline" href="<?= BASE_PATH ?>/erapor/sesi/<?= (int) $row['sesi_id'] ?>/pdf" target="_blank" rel="noopener"><?= $row['status'] === 'TERBIT' ? 'PDF Resmi' : 'Pratinjau PDF' ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$periode['rows']): ?><tr><td colspan="8" class="data-table-empty">Belum ada murid aktif di tahun ajaran ini.</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</section>
<?php endforeach; ?>
<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
