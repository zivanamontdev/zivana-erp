<?php
$headerActions=uiBadge('Hanya baca','netral');
require VIEW_PATH.'/layouts/shell-header.php';
?>
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/erapor-approval.css?v=<?= filemtime(ROOT_PATH.'/public/assets/css/erapor-approval.css') ?>">
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/erapor-signer-profile.css?v=<?= filemtime(ROOT_PATH.'/public/assets/css/erapor-signer-profile.css') ?>">
<p class="text-body-sm erapor-signer-intro">Periksa satu jalur PDF tanpa menyimpan nilai, mengganti file resmi, atau menerbitkan rapor. Setelah selesai, hapus <code>ERAPOR_PDF_DIAGNOSTIC_TOKEN</code> dari <code>.env</code>.</p>
<?php if($notice): ?><p role="alert" class="erapor-approval-notice"><?= uiText($notice,'body-sm',['tone'=>'status-inactive']) ?></p><?php endif; ?>
<?php if($report!==null): ?>
<?php ob_start(); ?>
<div class="erapor-migration-result" data-pdf-diagnostic-result>
    <div class="erapor-migration-result-head">
        <?= uiText('Hasil pemeriksaan','body-md',['tag'=>'h2','weight'=>'bold','tone'=>'heading']) ?>
        <?= isset($report['revision'])?uiBadge('Revisi diblokir','peringatan'):(!empty($report['ok'])?uiBadge('Berhasil','positif'):uiBadge('Perlu diperiksa','destruktif')) ?>
    </div>
    <?php if(isset($report['revision'])): ?>
        <p class="text-body-sm"><?= e($report['revision']['message']) ?></p>
        <p class="text-caption-md">Tidak ada migrasi atau penerbitan ulang otomatis. Nilai dan PDF asli tidak diubah.</p>
    <?php elseif(isset($report['error'])): ?>
        <p class="text-body-sm">Berhenti pada <strong><?= e($report['error']['stage']) ?></strong>. Unduh hasil diagnosis untuk diperiksa; jangan mengaktifkan debug publik.</p>
        <pre class="erapor-migration-log"><?= e(($report['error']['class']??'Error')."\n".mb_strimwidth((string)($report['error']['message']??''),0,220,'…').(!empty($report['error']['file'])?"\n".$report['error']['file'].':'.(int)($report['error']['line']??0):'')) ?></pre>
    <?php else: ?>
        <p class="text-body-sm"><?= ($report['path']??'')==='stored_official'?'Akun ini mengambil PDF resmi tersimpan. Renderer baru tidak dijalankan.':'Pemeriksaan berhasil. PDF uji hanya dibuat di memori; tidak disimpan atau diterbitkan.' ?></p>
        <?php if(!empty($report['pdf']['pages'])): ?><?= uiText($report['pdf']['pages'].' halaman · '.number_format($report['pdf']['bytes']).' byte','caption-md') ?><?php endif; ?>
    <?php endif; ?>
    <details class="ui-disclosure"><summary class="ui-disclosure-trigger">Rincian teknis yang telah disaring</summary><pre class="erapor-migration-log"><?= e(json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE)) ?></pre></details>
    <form method="POST" action="<?= BASE_PATH ?>/sistem/diagnosis-pdf/unduh" class="erapor-signer-actions" data-diagnostic-download>
        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
        <input type="hidden" name="report_id" value="<?= e($report['id']) ?>">
        <?= uiButton('Unduh diagnosis','outline',['type'=>'submit','marginVertical'=>0]) ?>
    </form>
    <p class="text-body-sm" role="status" aria-live="polite" data-diagnostic-download-status hidden></p>
    <p class="text-caption-md">Laporan tidak berisi password, token, isian rapor atau gambar TTD. Tautan unduh berlaku 15 menit pada sesi login ini. Tanpa JavaScript, muat ulang setelah mengunduh.</p>
</div>
<?php echo uiCard(ob_get_clean(),'outlined',['tag'=>'section','class'=>'erapor-signer-current']); ?>
<?php endif; ?>
<?php ob_start(); ?>
<form method="POST" action="<?= BASE_PATH ?>/sistem/diagnosis-pdf" class="erapor-signer-form" autocomplete="off" data-pdf-diagnostic-form>
    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
    <?= uiField('token','Token diagnosis',['type'=>'password','variant'=>'form','inputAttributes'=>['required'=>true,'autocomplete'=>'off','spellcheck'=>'false'],'placeholder'=>'ERAPOR_PDF_DIAGNOSTIC_TOKEN dari .env']) ?>
    <?= uiSelect('mode','Pemeriksaan',[
        'actor'=>'Jalur akun yang mengalami masalah',
        'render'=>'Render ulang uji · tanpa menyimpan',
        'template'=>'Template pembanding',
        'readiness'=>'Kesiapan revisi PDF lama',
    ],['value'=>'actor','id'=>'pdf-diagnostic-mode']) ?>
    <div data-diagnostic-fields="session">
        <?= uiField('session_id','Nomor sesi rapor',['type'=>'number','variant'=>'form','placeholder'=>'Contoh: 14','inputAttributes'=>['min'=>'1','step'=>'1']]) ?>
    </div>
    <div data-diagnostic-fields="actor">
        <?= uiSelect('actor_id','Akun yang diperiksa',$actors,['id'=>'pdf-diagnostic-actor']) ?>
        <p class="text-caption-md">Contoh: pilih Yusti untuk membandingkan sesi 14 dengan 16. Tidak perlu login sebagai akun tersebut.</p>
    </div>
    <div data-diagnostic-fields="template" hidden>
        <?= uiSelect('document_type','Jenis template',['RTS'=>'RTS','AGAMA'=>'Agama','UMMI'=>'Ummi','BING'=>'Bahasa Inggris','PPI'=>'PPI']) ?>
        <?= uiSelect('semester','Semester',['GANJIL'=>'Ganjil','GENAP'=>'Genap']) ?>
    </div>
    <div class="erapor-signer-actions"><?= uiButton('Jalankan pemeriksaan','primary',['type'=>'submit','marginVertical'=>0]) ?></div>
    <p class="text-caption-md">Satu pemeriksaan per request. Tunggu hasil sebelum mencoba yang lain; tidak ada tombol simpan atau terbitkan PDF.</p>
</form>
<?php echo uiCard(ob_get_clean(),'outlined',['tag'=>'section','class'=>'erapor-signer-editor']); ?>
<script>
(function () {
    var form=document.querySelector('[data-pdf-diagnostic-form]');
    var mode=form.querySelector('[name=mode]');
    function refresh() {
        form.querySelectorAll('[data-diagnostic-fields]').forEach(function(group) {
            var kind=group.dataset.diagnosticFields;
            var visible=kind==='session'?mode.value!=='template':mode.value===kind;
            group.hidden=!visible;
            group.querySelectorAll('input,select').forEach(function(field) {
                field.disabled=!visible;
                field.required=visible&&(field.name==='session_id'||field.name==='actor_id');
            });
        });
    }
    mode.addEventListener('change',refresh);refresh();
    form.addEventListener('submit',function(){setTimeout(function(){form.querySelector('button[type=submit]').disabled=true;},0);});
    var download=document.querySelector('[data-diagnostic-download]');
    if(download) download.addEventListener('submit',async function(event) {
        event.preventDefault();
        var button=download.querySelector('button'),status=document.querySelector('[data-diagnostic-download-status]');
        button.disabled=true;status.hidden=true;
        try {
            var response=await fetch(download.action,{method:'POST',body:new FormData(download),credentials:'same-origin'});
            var csrf=response.headers.get('X-CSRF-Token');
            if(csrf) document.querySelectorAll('input[name=csrf_token]').forEach(function(input){input.value=csrf;});
            if(!response.ok) throw new Error('Unduhan gagal atau kedaluwarsa. Muat ulang halaman.');
            var url=URL.createObjectURL(await response.blob()),link=document.createElement('a');
            link.href=url;link.download='diagnosis-pdf.json';document.body.appendChild(link);link.click();link.remove();
            setTimeout(function(){URL.revokeObjectURL(url);},1000);
            status.textContent='Diagnosis telah diunduh.';status.hidden=false;
        } catch(error) {
            status.textContent='Unduhan gagal atau kedaluwarsa. Muat ulang halaman.';status.hidden=false;
        } finally {button.disabled=false;}
    });
}());
</script>
<?php require VIEW_PATH.'/layouts/shell-footer.php'; ?>
