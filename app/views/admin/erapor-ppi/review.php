<?php
$sid=(int)$review['session']['id'];$ppi=$review['ppi'];$code=$review['approval_code'];
$headerActions='<a class="ui-button ui-button--outline" target="_blank" rel="noopener" href="'.BASE_PATH.'/erapor/ppi/'.$sid.'/pdf">Pratinjau PDF PPI</a>';
if ($review['can_approve']) $headerActions.=' '.uiButton('Setujui PPI','primary',['marginVertical'=>0,'attributes'=>['data-modal-open'=>'ppi-approve']]);
if ($review['can_publish']) $headerActions.=' '.uiButton('Terbitkan PPI','primary',['marginVertical'=>0,'attributes'=>['data-modal-open'=>'ppi-publish']]);
require VIEW_PATH.'/layouts/shell-header.php';
?>
<div class="field-row erapor-approval-summary">
<?= uiDataCard('Murid',$review['student']['nama_lengkap']) ?>
<?= uiDataCard('Kelas',trim($review['student']['level_kelas'].' '.$review['student']['nama_kelas'])) ?>
<?= uiDataCard('Periode',$review['period']['nama']) ?>
<?= uiDataCard('Pengajuan','PPI terpisah dari rapor utama') ?>
</div>
<div class="erapor-approval-state" role="status"><?= uiBadge($ppi['label'],$ppi['status']==='TERBIT'?'positif':'peringatan') ?>
<?= uiText($ppi['progress']['filled'].' / '.$ppi['progress']['required'].' isian tersimpan','body-sm') ?></div>
<?php if ($review['approvals']): ?>
<ul class="text-body-sm"><?php foreach ($review['approvals'] as $approval): ?>
<li><?= e(($approval['kode']==='WALI_KELAS'?'Wali Kelas':'Kepala Sekolah').' · '.$approval['nama'].' · '.date('d/m/Y H:i',strtotime($approval['disetujui_pada']))) ?></li>
<?php endforeach; ?></ul>
<?php endif; ?>
<?php foreach ($review['form']['definitions']['aspects'] as $aspect): ?>
<?php ob_start(); ?><details class="ui-disclosure erapor-approval-group">
<summary class="ui-disclosure-trigger"><span><?= e($aspect['nama']) ?></span><span class="ui-disclosure-chevron" aria-hidden="true"><?= icon('icon_chevron') ?></span></summary>
<div class="data-table-wrapper"><table class="data-table erapor-approval-values"><thead><tr><th>Aspek Penilaian</th><th>Isian Guru</th></tr></thead><tbody>
<?php foreach ($review['form']['definitions']['columns'] as $column): ?>
<tr><td><?= e($column['label_cetak']) ?></td><td><?= nl2br(e($review['form']['values'][$aspect['id'].':'.$column['id']] ?? 'Belum dinilai')) ?></td></tr>
<?php endforeach; ?></tbody></table></div></details>
<?php echo uiCard(ob_get_clean(),'outlined',['tag'=>'section','class'=>'erapor-approval-document']); ?>
<?php endforeach; ?>
<?php if ($review['can_approve']): ?>
<?php ob_start(); ?><form method="POST" action="<?= BASE_PATH ?>/erapor/ppi/<?= $sid ?>/setujui/<?= $code==='WALI_KELAS'?'wali':'kepala' ?>">
<input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>"><div class="modal-actions">
<?= uiButton('Batal','outline',['marginVertical'=>0,'attributes'=>['data-modal-close'=>true]]) ?>
<?= uiButton('Setujui PPI','primary',['type'=>'submit','marginVertical'=>0]) ?>
</div></form><?php echo uiModal('ppi-approve','Setujui PPI?',ob_get_clean(),['variant'=>'delete','description'=>'Persetujuan dan tanda tangan ini hanya untuk PPI. Rapor utama tidak diubah.']); ?>
<?php endif; ?>
<?php if ($review['can_publish']): ?>
<?php ob_start(); ?><form method="POST" action="<?= BASE_PATH ?>/erapor/ppi/<?= $sid ?>/terbitkan">
<input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>"><div class="modal-actions">
<?= uiButton('Batal','outline',['marginVertical'=>0,'attributes'=>['data-modal-close'=>true]]) ?>
<?= uiButton('Terbitkan PPI','primary',['type'=>'submit','marginVertical'=>0]) ?>
</div></form><?php echo uiModal('ppi-publish','Terbitkan PPI?',ob_get_clean(),['variant'=>'delete','description'=>'PDF PPI disimpan sebagai file tersendiri. PIC dapat membagikannya tanpa mengganti PDF rapor utama.']); ?>
<?php endif; ?>
<?php require VIEW_PATH.'/layouts/shell-footer.php'; ?>
