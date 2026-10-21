<?php
$headerActions=uiBadge('Superadmin · internal','netral');
require VIEW_PATH.'/layouts/shell-header.php';
$hidden=static function(array $extra=[])use($sid,$kind):void {
    foreach(array_merge(['csrf_token'=>getCsrfToken(),'session_id'=>$sid,'kind'=>$kind],$extra) as $k=>$v) if($v!==null) echo '<input type="hidden" name="'.e($k).'" value="'.e((string)$v).'">';
};
$auth=static function(string $id,bool $reason=true):void {
    echo uiField('token','Token revisi',['id'=>'revision-token-'.$id,'type'=>'password','variant'=>'form','inputAttributes'=>['required'=>true,'autocomplete'=>'off','spellcheck'=>'false','minlength'=>'32'],'placeholder'=>'ERAPOR_PDF_REVISION_TOKEN dari .env']);
    if($reason) echo uiField('reason','Alasan / catatan pemeriksaan',['id'=>'revision-reason-'.$id,'type'=>'textarea','value'=>'Pembaruan desain Quicksand; nilai dan bukti pengesahan dipertahankan.','inputAttributes'=>['required'=>true,'minlength'=>'12','maxlength'=>'500']]);
};
$messages=[
    'REVISION_SCHEMA_MISSING'=>'Tabel revisi belum dipasang. Tidak ada file resmi yang diubah.',
    'NO_OFFICIAL_ARTIFACT'=>'Sesi ini belum memiliki PDF resmi. Gunakan alur rapor biasa, bukan revisi historis.',
    'HISTORICAL_SOURCE_HASH_MISMATCH'=>'Paket yang direkonstruksi tidak cocok dengan SHA-256 sumber asli. Ada sumber yang berubah atau format historis belum dapat dipulihkan. Perlu backup sumber asli; jangan mengedit nilai agar pemeriksaan lolos.',
    'HISTORICAL_SIGNERS_MISMATCH'=>'Bukti penandatangan tidak cocok dengan manifest asli. Profil/TTD terkini tidak akan dipakai untuk menggantikannya.',
    'HISTORICAL_DOCUMENTS_MISMATCH'=>'Susunan atau versi rubrik tidak cocok dengan manifest asli.',
    'PPI_SOURCE_NOT_ARCHIVED'=>'PDF PPI terpisah lama hanya menyimpan hash nilai, bukan identitas dan paket lengkap. Revisi diblokir sampai sumber historis lengkap tersedia.',
    'HISTORICAL_RENDERER_UNSUPPORTED'=>'Format sumber renderer historis ini belum didukung. File asli tetap aman.',
    'HISTORICAL_APPROVAL_INCOMPLETE'=>'Jejak persetujuan historis tidak lengkap. Revisi tidak boleh menambahkan atau mengganti bukti pengesahan.',
    'HISTORICAL_MANIFEST_INCOMPLETE'=>'Manifest asli tidak lengkap atau tidak konsisten. Perlu pemeriksaan sumber/backup, bukan penerbitan ulang otomatis.',
    'HISTORICAL_SOURCE_UNVERIFIABLE'=>'Sumber historis belum dapat diverifikasi. Tidak ada revisi yang dibuat.',
    'REVISION_TOKEN_INVALID'=>'Token revisi salah. Tidak ada perubahan dilakukan.',
    'REVISION_COOLDOWN'=>'Tunggu lima detik sebelum menjalankan tindakan berikutnya.',
    'REVISION_DISABLE_API_FOR_MIGRATION'=>'Nonaktifkan ERAPOR_API_ENABLED sementara sebelum memasang tabel revisi.',
    'REVISION_API_DISABLED'=>'Aktifkan kembali ERAPOR_API_ENABLED sebelum memeriksa atau menyiapkan PDF.',
    'REVISION_REVIEW_REQUIRED'=>'Buka file asli dan revisi pada sesi login ini (maksimal 30 menit), periksa keduanya, lalu centang konfirmasi.',
    'REVISION_CHECK_STALE'=>'Sumber atau desain telah berubah. Jalankan pemeriksaan kembali.',
    'REVISION_SELECTION_CHANGED'=>'Pilihan aktif sudah berubah di tab/oleh operator lain. Muat ulang sebelum melanjutkan.',
    'REVISION_REASON_REQUIRED'=>'Catatan pemeriksaan wajib diisi, 12–500 karakter.',
    'REVISION_CONFIRM_REQUIRED'=>'Konfirmasi tindakan wajib dicentang.',
    'REVISION_SESSION_REQUIRED'=>'Masukkan nomor sesi rapor yang valid.',
];
$pdfUrl=BASE_PATH.'/sistem/revisi-pdf/'.$sid.'/'.$kind.'/pdf';
?>
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/erapor-approval.css?v=<?= filemtime(ROOT_PATH.'/public/assets/css/erapor-approval.css') ?>">
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/erapor-signer-profile.css?v=<?= filemtime(ROOT_PATH.'/public/assets/css/erapor-signer-profile.css') ?>">
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/erapor-pdf-revision.css?v=<?= filemtime(ROOT_PATH.'/public/assets/css/erapor-pdf-revision.css') ?>">
<p class="text-body-sm erapor-signer-intro">Perbarui desain PDF lama tanpa menimpa arsip. <strong>Aktivasi hanya mengubah preview internal</strong>, bukan persetujuan, nilai, status rapor, R2, atau tautan unduh orang tua. Ini bukan pengesahan atau penerbitan ulang oleh Kepala Sekolah.</p>
<?php if($notice||$error): ?><div id="revision-status" tabindex="-1">
<?php if($notice): ?><p class="erapor-approval-notice text-body-sm" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if($error): ?><div class="erapor-approval-notice" role="alert"><p class="text-body-sm"><?= e($messages[$error]??'Proses dihentikan agar arsip tetap aman. Simpan kode berikut untuk diperiksa.') ?></p><code><?= e($error) ?></code></div><?php endif; ?>
</div><?php endif; ?>
<?php if($technical): ?><details class="ui-disclosure"><summary class="ui-disclosure-trigger">Rincian galat yang disaring</summary><pre class="erapor-migration-log"><?= e(json_encode($technical,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE)) ?></pre></details><?php endif; ?>
<?php if(!$available): ?>
<?php ob_start(); ?>
<h2 class="text-body-md">Pasang penyimpanan revisi</h2>
<p class="text-body-sm">Migrasi aditif ini hanya membuat empat tabel revisi dan mencatat ledger. Tidak mengubah tabel artefak, nilai atau distribusi lama. Backup database terlebih dahulu.</p>
<?php if($apiEnabled): ?><p class="text-body-sm">Di File Manager, ubah sementara <code>ERAPOR_API_ENABLED=false</code>, lalu muat ulang halaman ini. Setelah migrasi sukses, kembalikan menjadi <code>true</code>.</p>
<?php else: ?>
<form method="POST" action="<?= BASE_PATH ?>/sistem/revisi-pdf" class="erapor-signer-form" autocomplete="off">
<?php $hidden(['action'=>'migrate']);$auth('schema',false); ?>
<?= uiCheckbox('confirm','Saya sudah melakukan backup dan menyetujui pemasangan empat tabel baru.',false,['id'=>'revision-schema-confirm','inputAttributes'=>['required'=>true]]) ?>
<?= uiButton('Pasang tabel revisi','primary',['type'=>'submit','marginVertical'=>0]) ?>
</form>
<?php endif; ?>
<?php echo uiCard(ob_get_clean(),'outlined',['tag'=>'section','class'=>'erapor-signer-editor']); ?>
<?php else: ?>
<?php ob_start(); ?>
<h2 class="text-body-md">1. Periksa satu paket sumber</h2>
<form method="POST" action="<?= BASE_PATH ?>/sistem/revisi-pdf#revision-result" class="erapor-signer-form" autocomplete="off">
<?php $hidden(['action'=>'inspect','session_id'=>null,'kind'=>null]); ?>
<?= uiField('session_id','Nomor sesi rapor',['id'=>'revision-session','type'=>'number','value'=>$sid?:'','inputAttributes'=>['required'=>true,'min'=>'1','step'=>'1']]) ?>
<?= uiSelect('kind','Paket',['RAPOR'=>'Rapor utama (termasuk PPI jika digabung)','PPI'=>'PPI yang diterbitkan terpisah'],['id'=>'revision-kind','value'=>$kind]) ?>
<?php $auth('inspect',false); ?>
<?= uiButton('Verifikasi sumber historis','primary',['type'=>'submit','marginVertical'=>0,'disabled'=>!$apiEnabled]) ?>
</form>
<p class="text-caption-md">Pemeriksaan hanya baca. Paket lama harus cocok persis dengan hash sumber, rubrik dan snapshot TTD asli; bukan sekadar memakai nilai/identitas terkini.</p>
<?php echo uiCard(ob_get_clean(),'outlined',['tag'=>'section','class'=>'erapor-signer-editor']); ?>
<?php if($check!==null): ?>
<?php ob_start(); ?>
<div id="revision-result" tabindex="-1" class="erapor-migration-result-head"><h2 class="text-body-md">Hasil verifikasi · sesi <?= $sid ?> · <?= e($kind) ?></h2><?= uiBadge($check['ready']?'Sumber terverifikasi':'Revisi diblokir',$check['ready']?'positif':'peringatan') ?></div>
<?php if(!$check['ready']): ?>
<p class="text-body-sm"><?= e($messages[$check['code']]??'Paket historis tidak dapat diverifikasi. File asli tidak diubah; sumber lengkap/backup diperlukan sebelum revisi.') ?></p><code><?= e($check['code']) ?></code>
<?php else: ?>
<p class="text-body-sm"><?= $check['method']==='SAAT_PERSETUJUAN'?'Menggunakan paket lengkap yang dibekukan saat persetujuan.':'Rekonstruksi cocok dengan SHA-256 sumber asli dan manifest penandatangan. Paket akan dibekukan sebelum render revisi.' ?> Tidak mengambil TTD dari profil terkini.</p>
<p class="text-caption-md">Renderer <?= e($check['original_renderer']) ?> → <?= e($check['renderer']) ?> · <?= (int)$check['document_count'] ?> dokumen · sumber <code><?= e(substr($check['package_sha256'],0,16)) ?></code></p>
<form method="POST" action="<?= BASE_PATH ?>/sistem/revisi-pdf" class="erapor-signer-form" autocomplete="off">
<?php $hidden(['action'=>'prepare','package_hash'=>$check['package_sha256'],'design_hash'=>$check['design_sha256']]);$auth('prepare'); ?>
<?= uiCheckbox('confirm','Siapkan salinan revisi dengan Quicksand dan desain TTD terbaru. Jangan ganti file asli atau aktifkan otomatis.',false,['id'=>'revision-prepare-confirm','inputAttributes'=>['required'=>true]]) ?>
<?= uiButton('Siapkan preview revisi','primary',['type'=>'submit','marginVertical'=>0]) ?>
</form>
<?php endif; ?>
<?php echo uiCard(ob_get_clean(),'outlined',['tag'=>'section','class'=>'erapor-signer-editor']); ?>
<?php endif; ?>
<?php if($sid>0): ?>
<section class="pdf-revision-section" aria-labelledby="revision-compare-title">
<h2 class="text-body-md" id="revision-compare-title">2. Bandingkan, lalu pilih preview internal</h2>
<p class="text-body-sm">Pilihan aktif: <strong><?= $selection==='asli'?'arsip asli':'revisi #'.e($selection) ?></strong>. Buka kedua PDF di tab baru. Periksa seluruh halaman: nilai, identitas, tanggal pengesahan, TTD/cap dan kelengkapan rubrik. Logo/layout mengikuti desain baru; bukti TTD yang tidak ada dahulu tidak boleh ditambahkan dari profil sekarang.</p>
<?php if($originalHash!==''): ?><a class="ui-button ui-button--outline" href="<?= e($pdfUrl.'?revision=asli') ?>" target="_blank" rel="noopener noreferrer">Buka arsip asli · tidak mengubah hitungan unduh</a><?php endif; ?>
<div class="pdf-revision-grid">
<?php foreach($history as $r): $rid=(int)$r['id'];ob_start(); ?>
<div class="erapor-migration-result-head"><h3 class="text-body-md">Revisi #<?= $rid ?></h3><?= uiBadge($r['aktif']?'Aktif internal':'Belum aktif',$r['aktif']?'positif':'netral') ?></div>
<p class="text-caption-md"><?= e($r['renderer_versi']) ?> · <?= (int)$r['halaman'] ?> halaman · <?= e($r['dibuat_pada']) ?></p>
<a class="ui-button ui-button--outline" href="<?= e($pdfUrl.'?revision='.$rid) ?>" target="_blank" rel="noopener noreferrer">Buka revisi #<?= $rid ?></a>
<?php if(!$r['aktif']): ?>
<form method="POST" action="<?= BASE_PATH ?>/sistem/revisi-pdf" class="erapor-signer-form" autocomplete="off">
<?php $hidden(['action'=>'activate','revision_id'=>$rid,'expected'=>$selection,'pdf_hash'=>$r['pdf_sha256'],'original_hash'=>$originalHash]);$auth('activate-'.$rid); ?>
<?= uiCheckbox('confirm','Saya telah membandingkan seluruh halaman asli dan revisi. Nilai, identitas, tanggal dan bukti TTD sesuai; saya memilih revisi ini untuk preview internal saja.',false,['id'=>'revision-confirm-'.$rid,'inputAttributes'=>['required'=>true]]) ?>
<?= uiButton('Aktifkan untuk preview internal','primary',['type'=>'submit','marginVertical'=>0,'disabled'=>!$apiEnabled]) ?>
</form>
<?php endif; ?>
<?php echo uiCard(ob_get_clean(),'outlined',['tag'=>'article','class'=>'pdf-revision-card']);endforeach; ?>
</div>
<?php if($selection!=='asli'): ?>
<?php ob_start(); ?>
<h3 class="text-body-md">Kembali ke arsip asli</h3>
<form method="POST" action="<?= BASE_PATH ?>/sistem/revisi-pdf" class="erapor-signer-form" autocomplete="off">
<?php $hidden(['action'=>'restore','expected'=>$selection]);$auth('restore'); ?>
<?= uiCheckbox('confirm','Kembalikan preview internal ke file asli. Simpan seluruh revisi dan audit.',false,['id'=>'revision-restore-confirm','inputAttributes'=>['required'=>true]]) ?>
<?= uiButton('Gunakan arsip asli kembali','outline',['type'=>'submit','marginVertical'=>0]) ?>
</form>
<?php echo uiCard(ob_get_clean(),'outlined',['tag'=>'section','class'=>'erapor-signer-editor']); ?>
<?php endif; ?>
</section>
<?php endif; ?>
<?php endif; ?>
<?php if($recent): ?>
<details class="ui-disclosure"><summary class="ui-disclosure-trigger">Referensi nomor sesi dengan PDF resmi (30 terbaru)</summary>
<div class="data-table-wrapper"><table class="data-table"><thead><tr><th scope="col">Sesi</th><th scope="col">Murid</th><th scope="col">Kondisi</th><th scope="col">Periode</th></tr></thead><tbody>
<?php foreach($recent as $s): ?><tr><td><?= (int)$s['id'] ?></td><td><?= e($s['nama_lengkap']) ?></td><td><?= e($s['kondisi']) ?></td><td><?= e($s['periode']) ?></td></tr><?php endforeach; ?>
</tbody></table></div></details>
<?php endif; ?>
<p class="text-caption-md erapor-signer-intro">Satu sesi dan satu paket per tindakan. Tidak ada revisi massal, unggah R2 atau pembagian otomatis. Setelah selesai, hapus <code>ERAPOR_PDF_REVISION_TOKEN</code> dari <code>.env</code>; pilihan preview yang sudah diaktifkan tetap berlaku.</p>
<?php require VIEW_PATH.'/layouts/shell-footer.php'; ?>
