<?php
$pageTitle = 'Pengisian Rapor';
$kelasLabel = trim(($form['student']['level_kelas'] ?? '').' '.($form['student']['nama_kelas'] ?? ''));
$completionByType = [];
foreach ($form['completion']['documents'] as $completion) $completionByType[$completion['jenis']] = $completion;
$required = array_sum(array_column($form['completion']['documents'],'required'));
$filled = array_sum(array_column($form['completion']['documents'],'filled'));
require VIEW_PATH.'/layouts/focus-header.php';
?>
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/portal-guru.css?v=<?= filemtime(ROOT_PATH.'/public/assets/css/portal-guru.css') ?>">
<section class="pengisian-header-card">
    <div class="pengisian-header-top">
        <div>
            <h1>Pengisian Rapor</h1>
            <p class="pengisian-student-name"><?= e($form['student']['nama_lengkap']) ?></p>
        </div>
        <div class="pengisian-header-actions">
            <?= uiButton('Kembali ke Dashboard','outline',['marginVertical'=>0,'attributes'=>['onclick'=>'window.location.href=\''.BASE_PATH.'/portal-guru/dashboard\'']]) ?>
        </div>
    </div>
    <p class="pengisian-rapor-warning">
        <?= e($form['period']['nama']) ?> · Tahun Ajaran <?= e($form['period']['tahun_label']) ?> ·
        Semester <?= e(ucfirst(strtolower($form['period']['semester']))) ?>
        <?php if ($kelasLabel !== ''): ?> · <?= e($kelasLabel) ?><?php endif; ?>
    </p>
    <div class="pengisian-rapor-progress" aria-label="Progress isian rapor">
        <span class="pengisian-rapor-progress-label">Progress:</span>
        <span class="pengisian-rapor-progress-bar"><span class="pengisian-rapor-progress-bar-fill" style="width:<?= $required ? min(100,round($filled/$required*100)) : 0 ?>%"></span></span>
        <span><?= (int)$filled ?> dari <?= (int)$required ?></span>
    </div>
</section>

<div class="teacher-session-phase-note" role="status">
    <?= uiText('Sesi rapor baru sudah terhubung ke kalender dan paket penilaian. Editor nilai untuk format e-Rapor ini sedang diintegrasikan; halaman ini belum menulis atau mengubah nilai.','body-sm',['tone'=>'muted']) ?>
</div>

<section class="teacher-session-documents" aria-label="Dokumen dalam paket rapor">
    <?php foreach ($form['documents'] as $document): ?>
        <?php $progress = $completionByType[$document['jenis_dokumen']] ?? ['filled'=>0,'required'=>0,'complete'=>false]; ?>
        <article class="teacher-session-document">
            <div class="teacher-session-document-header">
                <h2><?= e($document['nama']) ?></h2>
                <span class="teacher-session-document-progress">
                    <?= (int)$progress['filled'] ?> / <?= (int)$progress['required'] ?> terisi
                </span>
            </div>
            <p><?= uiText($document['jenis_dokumen'],'caption-md',['tone'=>'muted']) ?></p>
        </article>
    <?php endforeach; ?>
</section>
<?php require VIEW_PATH.'/layouts/focus-footer.php'; ?>
