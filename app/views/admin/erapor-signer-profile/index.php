<?php
$headerActions='';
$revokeModalId='modal-cabut-persetujuan-tanda-tangan';
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
require VIEW_PATH.'/layouts/shell-header.php';
?>
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/erapor-signer-profile.css?v=<?= filemtime(ROOT_PATH.'/public/assets/css/erapor-signer-profile.css') ?>">

<?php if ($notice): ?>
<p class="erapor-approval-notice" role="status"><?= uiText($notice['message'],'body-sm',['tone'=>$notice['type']==='success'?'success':'status-inactive']) ?></p>
<?php endif; ?>

<p class="text-body-sm erapor-signer-intro">Kelola NUPTK dan tanda tangan Anda sendiri. File disimpan privat dan disalin hanya ke snapshot penerimaan atau persetujuan yang Anda lakukan; administrator tidak dapat mengunggah atas nama Anda.</p>

<?php ob_start(); ?>
<div class="erapor-signer-status">
    <div>
        <?= uiText('Status tanda tangan','caption-md',['tone'=>'muted']) ?>
        <?php if ($profile['has_signature']): ?>
            <?= uiBadge('Persetujuan aktif','positif') ?>
            <p class="text-caption-md">Disetujui pada <?= e($profile['consented_at']) ?></p>
        <?php else: ?>
            <?= uiBadge('Belum ada tanda tangan','netral') ?>
            <p class="text-caption-md">Rapor berikutnya akan merekam nama tanpa gambar tanda tangan.</p>
        <?php endif; ?>
    </div>
    <div><?= uiDataCard('NUPTK',$profile['nuptk'] ?: 'Belum diisi') ?></div>
</div>
<?php if ($profile['has_signature']): ?>
<figure class="erapor-signer-preview">
    <img src="<?= BASE_PATH ?>/erapor/profil-penandatangan/tanda-tangan" alt="Pratinjau tanda tangan tersimpan pada akun Anda" loading="lazy">
    <figcaption class="text-caption-md">Pratinjau privat. Tidak dapat diakses oleh akun lain.</figcaption>
</figure>
<?php endif; ?>
<?php echo uiCard(ob_get_clean(),'outlined',['tag'=>'section','class'=>'erapor-signer-current']); ?>

<?php if ($canEdit): ?>
<?php ob_start(); ?>
<form method="POST" enctype="multipart/form-data" action="<?= BASE_PATH ?>/erapor/profil-penandatangan" class="erapor-signer-form">
    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
    <?= uiField('nuptk','NUPTK',[
        'type'=>'text','variant'=>'form','value'=>$profile['nuptk'] ?? '',
        'placeholder'=>'Masukkan 16 digit NUPTK','autocomplete'=>'off',
        'inputAttributes'=>['inputmode'=>'numeric','maxlength'=>'16','pattern'=>'[0-9]{16}'],
    ]) ?>
    <?= uiField('signature','File Tanda Tangan PNG',[
        'type'=>'file','variant'=>'form',
        'inputAttributes'=>['accept'=>'image/png','aria-describedby'=>'erapor-signer-file-help'],
    ]) ?>
    <p id="erapor-signer-file-help" class="text-caption-md">PNG maksimal 2 MB, ukuran hingga 4096×2048 piksel. Gambar dinormalisasi untuk menghapus metadata.</p>
    <div class="erapor-signer-consent">
        <?= uiCheckbox('consent','File tanda tangan yang saya pilih adalah milik saya. Saya memberi persetujuan untuk menyimpan salinannya secara privat dan menggunakannya dalam snapshot eRapor.',false,[
            'value'=>'1','textVariant'=>'body-sm','tone'=>'default',
        ]) ?>
    </div>
    <div class="erapor-signer-actions">
        <?= uiButton('Simpan Profil','primary',['type'=>'submit','marginVertical'=>0]) ?>
        <?php if ($profile['has_signature']): ?>
            <?= uiButton('Cabut Persetujuan','outline-danger',['attributes'=>['data-modal-open'=>$revokeModalId],'marginVertical'=>0]) ?>
        <?php endif; ?>
    </div>
</form>
<?php echo uiCard(ob_get_clean(),'outlined',['tag'=>'section','class'=>'erapor-signer-editor']); ?>

<?php if ($profile['has_signature']): ?>
<?php ob_start(); ?>
<form method="POST" action="<?= BASE_PATH ?>/erapor/profil-penandatangan/cabut" class="modal-body">
    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
    <div class="modal-actions">
        <?= uiButton('Batal','outline',['marginVertical'=>0,'attributes'=>['data-modal-close'=>true]]) ?>
        <?= uiButton('Cabut Persetujuan','outline-danger',['type'=>'submit','marginVertical'=>0]) ?>
    </div>
</form>
<?php echo uiModal($revokeModalId,'Cabut Persetujuan Tanda Tangan?',ob_get_clean(),[
    'variant'=>'delete','description'=>'Tanda tangan tidak akan disertakan pada snapshot baru setelah pencabutan. Snapshot penerimaan atau persetujuan yang sudah tercatat tidak diubah.',
]); ?>
<?php endif; ?>
<?php endif; ?>

<?php require VIEW_PATH.'/layouts/shell-footer.php'; ?>
