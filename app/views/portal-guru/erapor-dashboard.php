<?php
$choices = [];
$canProvisionErapor = uiCan('Portal Guru','Daftar Murid','edit');
$canOpenErapor = uiCan('Portal Guru','Daftar Murid','lihat');
foreach ($periodOptions as $periodOption) $choices[$periodOption['id']] = $periodOption['label'];
$headerActions = '';
if ($choices !== []) {
    $headerActions = '<form method="GET" action="'.BASE_PATH.'/portal-guru/dashboard" class="list-filter">'
        .uiFilter('periode_id','Periode Rapor',$choices,[
            'value'=>$selectedPeriod['id'] ?? '', 'marginVertical'=>0,
            'attributes'=>['onchange'=>'this.form.submit()'],
        ]).'</form>';
}
require VIEW_PATH.'/layouts/shell-header.php';
?>
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/portal-guru.css?v=<?= filemtime(ROOT_PATH.'/public/assets/css/portal-guru.css') ?>">
<?php if (!empty($_SESSION['report_error'])): ?>
    <p role="alert" class="teacher-report-alert"><?= e($_SESSION['report_error']) ?></p>
    <?php unset($_SESSION['report_error']); ?>
<?php endif; ?>

<div class="dashboard-banner">
    <?= uiText('Halo! '.($_SESSION['display_name'] ?? ''),'body-sm',['weight'=>'bold','tone'=>'inverse']) ?>
    <?= uiText(date('d/m/Y, H:i'),'body-sm',['tone'=>'inverse']) ?>
</div>

<div class="dashboard-stack">
    <?php ob_start(); ?>
        <?= uiText('Agenda sedang berlangsung','body-sm',['tag'=>'p','weight'=>'bold','class'=>'dashboard-agenda-label']) ?>
        <?php if ($agendaCurrent): ?>
            <div class="dashboard-agenda-details">
                <?= uiText($agendaCurrent['nama']) ?><span aria-hidden="true">&bull;</span>
                <?= uiText('Sisa '.max(0,(int)$agendaCurrent['sisa_hari']).' Hari') ?>
            </div>
        <?php else: ?>
            <?= uiText('Belum ada agenda pengisian rapor yang sedang berlangsung.') ?>
        <?php endif; ?>
    <?php echo uiCard(ob_get_clean(),'callout',['class'=>'dashboard-agenda-box']); ?>

    <?php if ($selectedPeriod): ?>
        <section class="accordion-item report-period-table is-open">
            <button class="accordion-header" type="button" data-accordion-toggle aria-expanded="true" aria-controls="erapor-student-list">
                <span class="report-period-heading">
                    <?= uiText('Daftar Murid') ?>
                    <?= uiText('Tahun Ajaran '.$selectedPeriod['tahun_label'],'caption-md') ?>
                    <?= uiText($selectedPeriod['nama'],'caption-md',['tone'=>'muted']) ?>
                </span>
                <?= uiText((string)count($students)) ?>
                <span class="accordion-chevron" aria-hidden="true"><?= icon('icon_chevron') ?></span>
            </button>
            <div class="accordion-body" id="erapor-student-list">
                <table class="data-table" aria-label="Daftar rapor murid ampuan">
                    <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr><td>
                            <div class="report-student-row">
                                <span class="report-student-name">
                                    <?= uiText($student['nama_lengkap'],'body-sm') ?>
                                    <?php if (!empty($student['level_kelas']) || !empty($student['nama_kelas'])): ?>
                                        <small><?= e(trim(($student['level_kelas'] ?? '').' '.($student['nama_kelas'] ?? ''))) ?></small>
                                    <?php endif; ?>
                                </span>
                                <?php if ($student['action']==='create' && $canProvisionErapor): ?>
                                    <form method="POST" action="<?= BASE_PATH ?>/portal-guru/sesi/siapkan" class="teacher-report-action-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                        <input type="hidden" name="murid_id" value="<?= (int)$student['id'] ?>">
                                        <input type="hidden" name="periode_id" value="<?= (int)$selectedPeriod['id'] ?>">
                                        <button class="teacher-report-action teacher-report-action--brand" type="submit">Isi Rapor</button>
                                        <span class="report-student-chevron" aria-hidden="true"><?= icon('icon_chevron') ?></span>
                                    </form>
                                <?php elseif ($student['action']==='open' && $canOpenErapor
                                    && (!in_array($student['sesi_status'],['BELUM_DIISI','TELAH_DIISI'],true) || $canProvisionErapor)): ?>
                                    <a class="teacher-report-open" href="<?= BASE_PATH ?>/portal-guru/sesi/<?= (int)$student['sesi_id'] ?>">
                                        <span class="teacher-report-action <?= $student['sesi_status']==='SELESAI' ? 'teacher-report-action--success' : ($student['sesi_status']==='MENUNGGU_TTD' ? 'teacher-report-action--pending' : 'teacher-report-action--brand') ?>">
                                            <?= e($student['action_label']) ?>
                                        </span>
                                        <span class="report-student-chevron" aria-hidden="true"><?= icon('icon_chevron') ?></span>
                                    </a>
                                <?php elseif (in_array($student['action'],['create','open'],true)): ?>
                                    <span class="teacher-report-action teacher-report-action--muted">Akses dibatasi</span>
                                <?php else: ?>
                                    <span class="teacher-report-action teacher-report-action--muted" title="Status paket atau penugasan memerlukan pemeriksaan.">
                                        <?= e($student['action_label']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td></tr>
                    <?php endforeach; ?>
                    <?php if (!$students): ?><tr><td class="data-table-empty">Belum ada murid yang ditugaskan kepada Anda.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php else: ?>
        <?= uiCard(uiText('Periode rapor belum tersedia. Hubungi Admin untuk memeriksa kalender penilaian.','body-sm',['tone'=>'muted']),'outlined') ?>
    <?php endif; ?>

    <?php ob_start(); ?>
        <?= uiText('Agenda Berikutnya','body-sm',['tag'=>'p','weight'=>'bold','class'=>'dashboard-agenda-label']) ?>
        <?php if ($agendaNext): ?>
            <div class="dashboard-agenda-details">
                <?= uiText($agendaNext['nama']) ?><span aria-hidden="true">&bull;</span>
                <?= uiText(date('d/m/Y',strtotime($agendaNext['awal_periode'])).' - '.date('d/m/Y',strtotime($agendaNext['akhir_periode']))) ?>
            </div>
        <?php else: ?>
            <?= uiText('Belum ada agenda berikutnya.') ?>
        <?php endif; ?>
    <?php echo uiCard(ob_get_clean(),'outlined',['class'=>'dashboard-agenda-box dashboard-agenda-box--neutral']); ?>
</div>
<?php require VIEW_PATH.'/layouts/shell-footer.php'; ?>
