<?php
$headerActions = '';
if (count($sessionOptions) > 1) {
    $choices = array_column($sessionOptions, 'nama', 'id');
    $headerActions = '<form method="GET" action="' . BASE_PATH . '/portal-guru/dashboard" class="list-filter">'
        . uiFilter('sesi_id', 'Periode Rapor', $choices, ['value'=>$selectedSession['id'] ?? '', 'marginVertical'=>0,
            'attributes'=>['onchange'=>'this.form.submit()']]) . '</form>';
}
require VIEW_PATH . '/layouts/shell-header.php';
?>
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/portal-guru.css?v=<?= filemtime(ROOT_PATH . '/public/assets/css/portal-guru.css') ?>">
<?php if (!empty($_SESSION['report_error'])): ?>
<p role="alert"><?= uiText($_SESSION['report_error'], 'body-sm', ['tone'=>'status-inactive']) ?></p>
<?php unset($_SESSION['report_error']); endif; ?>
<div class="dashboard-banner">
    <?= uiText('Halo! ' . ($_SESSION['display_name'] ?? ''), 'body-sm', ['weight'=>'bold','tone'=>'inverse']) ?>
    <?= uiText(date('d/m/Y, H:i'), 'body-sm', ['tone'=>'inverse']) ?>
</div>
<div class="dashboard-stack">
    <?php ob_start(); ?>
        <?= uiText('Agenda sedang berlangsung', 'body-sm', ['tag'=>'p','weight'=>'bold','class'=>'dashboard-agenda-label']) ?>
        <?php if ($agendaBerlangsung): ?>
            <div class="dashboard-agenda-details"><?= uiText($agendaBerlangsung['nama']) ?><span aria-hidden="true">•</span><?= uiText('Sisa ' . $agendaBerlangsung['sisa_hari'] . ' Hari') ?></div>
        <?php else: ?>
            <?= uiText('Belum ada agenda yang sedang berlangsung untuk murid Anda.') ?>
        <?php endif; ?>
    <?php echo uiCard(ob_get_clean(), 'callout', ['class'=>'dashboard-agenda-box']); ?>
    <?php if ($selectedSession && (!$agendaBerlangsung || $selectedSession['id'] !== $agendaBerlangsung['id'])): ?>
        <?= uiText('Periode ditampilkan: ' . $selectedSession['nama'], 'caption-md', ['tone'=>'muted']) ?>
    <?php endif; ?>
    <section class="accordion-item report-period-table is-open">
        <button class="accordion-header" type="button" data-accordion-toggle aria-expanded="true" aria-controls="teacher-student-reports">
            <span class="report-period-heading"><?= uiText('Daftar Murid') ?><?= uiText('Tahun Ajaran ' . $tahunLabel, 'caption-md') ?></span>
            <?= uiText((string)count($daftarMurid)) ?>
            <span class="accordion-chevron" aria-hidden="true"><?= icon('icon_chevron') ?></span>
        </button>
        <div class="accordion-body" id="teacher-student-reports">
            <table class="data-table" aria-label="Daftar rapor murid ampuan">
                <tbody>
                <?php foreach ($daftarMurid as $student): ?>
                    <?php
                    $draft = $student['rapor_status'] === 'belum_diisi';
                    $archived = $draft && !empty($student['rapor_diarsipkan_at']);
                    $canOpen = !empty($student['rapor_id']) && uiCan('Portal Guru','Daftar Murid',$draft ? 'edit' : 'lihat');
                    $href = BASE_PATH . '/portal-guru/rapor/' . (int)$student['rapor_id'] . ($draft ? '' : '/pratinjau');
                    ?>
                    <tr><td>
                        <?php if ($canOpen): ?><a class="report-student-row" href="<?= e($href) ?>"><?php else: ?><div class="report-student-row"><?php endif; ?>
                            <span class="report-student-identity">
                                <?= uiText($student['nama_lengkap'], 'body-sm', ['class'=>'report-student-name']) ?>
                                <?php if ($archived): ?><?= uiBadge('Diarsipkan', 'netral') ?><?php endif; ?>
                            </span>
                            <?php if ($canOpen): ?>
                                <?= uiText($archived ? 'Lanjutkan' : ($draft ? 'Isi Rapor' : 'Lihat Rapor'), 'body-sm', ['tone'=>$draft ? 'brand' : 'success']) ?>
                                <span class="report-student-chevron" aria-hidden="true"><?= icon('icon_chevron') ?></span>
                            <?php else: ?>
                                <?= uiText(empty($student['rapor_id']) ? 'Rapor belum tersedia' : 'Akses dibatasi', 'caption-md', ['tone'=>'muted']) ?>
                            <?php endif; ?>
                        <?php if ($canOpen): ?></a><?php else: ?></div><?php endif; ?>
                    </td></tr>
                <?php endforeach; ?>
                <?php if (!$daftarMurid): ?><tr><td class="data-table-empty">Belum ada murid yang diampu. Hubungi Admin untuk pengaturan kelas.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php ob_start(); ?>
        <?= uiText('Agenda Berikutnya', 'body-sm', ['tag'=>'p','weight'=>'bold','class'=>'dashboard-agenda-label']) ?>
        <?php if ($agendaBerikutnya): ?>
            <div class="dashboard-agenda-details"><?= uiText($agendaBerikutnya['nama']) ?><span aria-hidden="true">•</span><?= uiText(date('d/m/Y',strtotime($agendaBerikutnya['tanggal_mulai'])) . ' - ' . date('d/m/Y',strtotime($agendaBerikutnya['tanggal_selesai']))) ?></div>
        <?php else: ?>
            <?= uiText('Belum ada agenda berikutnya untuk murid Anda.') ?>
        <?php endif; ?>
    <?php echo uiCard(ob_get_clean(), 'outlined', ['class'=>'dashboard-agenda-box dashboard-agenda-box--neutral']); ?>
</div>
<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
