<?php
$headerActions='';
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
require VIEW_PATH.'/layouts/shell-header.php';
?>
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/erapor-approval.css?v=<?= filemtime(ROOT_PATH.'/public/assets/css/erapor-approval.css') ?>">

<?php if ($notice): ?>
<p class="erapor-approval-notice" role="status"><?= uiText($notice['message'],'body-sm',['tone'=>$notice['type']==='success'?'success':'status-inactive']) ?></p>
<?php endif; ?>

<p class="text-body-sm erapor-assignment-intro">Tetapkan secara eksplisit akun untuk tiap tahap. Akun harus aktif dan memiliki izin Persetujuan; tahap Kepala Sekolah hanya dapat ditugaskan kepada pegawai dengan jabatan Kepala Sekolah.</p>

<?php if ($configurationIssue): ?>
    <?= uiCard(uiText($configurationIssue,'body-sm',['tone'=>'status-inactive']), 'callout', ['tag'=>'section','class'=>'erapor-assignment-warning']) ?>
<?php elseif (empty($state['ready'])): ?>
    <?= uiCard(uiText('Konfigurasi alur persetujuan belum valid. Penugasan tidak dapat diubah. Periksa seed dan mapping katalog sebelum melanjutkan.','body-sm',['tone'=>'status-inactive']), 'callout', ['tag'=>'section','class'=>'erapor-assignment-warning']) ?>
<?php else: ?>
<form method="POST" action="<?= BASE_PATH ?>/erapor/persetujuan/penugasan" class="erapor-assignment-form">
    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
    <div class="erapor-assignment-grid">
        <?php foreach ($state['flows'] as $flow): ?>
            <?php ob_start(); ?>
            <div class="erapor-assignment-heading">
                <?= uiText($flow['label'],'body-md',['tag'=>'h2','weight'=>'bold','tone'=>'heading']) ?>
                <?= uiBadge($flow['pending'].' rapor menunggu',$flow['pending']>0?'peringatan':'netral') ?>
            </div>
            <p class="text-caption-md erapor-assignment-scope">
                <?= $flow['code']==='KEPALA_SEKOLAH'?'Tahap 2 · seluruh dokumen setelah kedua koordinator.':($flow['code']==='KOORDINATOR_QURAN'?'Tahap 1 · dokumen Ummi/Quran.':'Tahap 1 · dokumen Bahasa Inggris.') ?>
            </p>
            <div class="erapor-assignment-options">
                <?php if (!$flow['users']): ?>
                    <?= uiText('Belum ada akun eligible. Aktifkan akun pegawai dan berikan izin eRapor > Persetujuan melalui RBAC.','body-sm',['tone'=>'muted']) ?>
                <?php endif; ?>
                <?php foreach ($flow['users'] as $user): ?>
                    <?php
                    $label=trim((string)$user['nama']).' · '.(string)$user['email'];
                    if ($user['ineligible_reason']!=='') $label.=' · Tidak eligible: '.$user['ineligible_reason'];
                    $checkboxName='assignments['.$flow['code'].'][]';
                    $checkboxId='assignment-'.$flow['code'].'-'.(int)$user['id'];
                    ?>
                    <?= uiCheckbox($checkboxName,$label,(bool)$user['assigned'],[
                        'id'=>$checkboxId,'value'=>(string)(int)$user['id'],'textVariant'=>'body-sm','tone'=>$user['eligible']?'default':'muted',
                    ]) ?>
                <?php endforeach; ?>
            </div>
            <?php echo uiCard(ob_get_clean(),'outlined',['tag'=>'section','class'=>'erapor-assignment-flow']); ?>
        <?php endforeach; ?>
    </div>
    <p class="text-caption-md erapor-assignment-impact">Perubahan penugasan berlaku juga untuk rapor yang sedang menunggu. Persetujuan yang sudah tercatat tidak dihapus; perubahan assignment hanya menentukan siapa yang masih dapat mengakses tugas pending.</p>
    <?= uiField('reason','Alasan perubahan',[
        'type'=>'textarea','variant'=>'form','required'=>true,'placeholder'=>'Jelaskan alasan perubahan (10–500 karakter).',
        'inputAttributes'=>['maxlength'=>'2000','minlength'=>'10'],
    ]) ?>
    <div class="erapor-assignment-actions">
        <?= uiButton('Simpan Penugasan','primary',['type'=>'submit','marginVertical'=>0]) ?>
    </div>
</form>
<?php endif; ?>

<?php require VIEW_PATH.'/layouts/shell-footer.php'; ?>
