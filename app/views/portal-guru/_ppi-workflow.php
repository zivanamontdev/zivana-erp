<?php // Included only when the additive PPI migration is available. ?>
<?php
if (!$ppiState['separate'] && !($canEdit && in_array($session['status'],['BELUM_DIISI','TELAH_DIISI'],true))) return;
$ppiDeadlineExpired=$ppiState['status']==='BELUM_DIAJUKAN' && ($form['capabilities']['ppi_read_only_reason'] ?? null)==='TENGGAT_BERAKHIR';
ob_start();
?>
<?php if ($canEdit && in_array($session['status'],['BELUM_DIISI','TELAH_DIISI'],true)): ?>
<?= uiCheckbox('ppi_include','Sertakan PPI dalam rapor utama',(bool)$ppiState['included'],['inputAttributes'=>['data-erapor-ppi-choice'=>true]]) ?>
<p class="text-caption-md">Nilai tetap tersimpan <?= uiTooltip('Jika PPI tidak disertakan, ajukan PPI tersendiri setelah Finalisasi rapor utama tanpa mengisi ulang.') ?></p>
<?php endif; ?>
<?php if ($ppiState['separate']): ?>
<div class="erapor-approval-state" role="status"><?= uiBadge($ppiState['label'],$ppiState['status']==='TERBIT'?'positif':'peringatan') ?></div>
<p class="text-caption-md">Wali Kelas → Kepala Sekolah <?= uiTooltip('PPI tidak masuk PDF rapor utama. Pengajuan dan tanda tangan PPI dilakukan tersendiri.') ?></p>
<?php if ($ppiDeadlineExpired): ?>
<p class="text-caption-md" id="erapor-ppi-deadline-hint" role="status">
<?php if (!empty($ppiState['progress']['complete'])): ?>Isian PPI sudah lengkap <?= uiTooltip('Tenggat pengisian telah berakhir. Isian lengkap tetap dapat diajukan setelah Finalisasi rapor utama.') ?>
<?php else: ?>Tenggat PPI berakhir <?= uiTooltip('Hubungi admin untuk memperpanjang periode pengisian. Nilai yang sudah diisi tetap tersimpan.') ?><?php endif; ?></p>
<?php endif; ?>
<?php if ($ppiState['status']==='TERBIT' && !empty($ppiShare) && empty($ppiShare['contacts'])): ?>
<p class="text-caption-md" id="erapor-ppi-share-hint" role="status">Kontak belum tersedia <?= uiTooltip('Minta admin melengkapi nomor HP ayah atau ibu di data murid agar PPI dapat dibagikan.') ?></p>
<?php endif; ?>
<?php if ($ppiState['status']==='BELUM_DIAJUKAN' && !in_array($session['status'],['MENUNGGU_TTD','SELESAI'],true)): ?>
<p class="text-caption-md">Finalisasi rapor utama terlebih dahulu untuk mengajukan PPI terpisah.</p>
<?php endif; ?>
<?php if ($ppiState['status']==='TERBIT'): ?>
<p class="text-caption-md" data-erapor-ppi-share-status><?= !empty($ppiShare['shared_at'])?'PPI dibagikan ke '.e(strtolower((string)$ppiShare['shared_to'])).' · '.date('d/m/Y H:i',strtotime($ppiShare['shared_at'])):'PPI belum dibagikan.' ?></p>
<?php endif; ?>
<?php endif; ?>
<?php $ppiWorkflowDetails=ob_get_clean();ob_start(); ?>
<?php if ($ppiState['separate']): ?>
<div class="erapor-save-actions">
<a class="ui-button ui-button--outline" href="<?= BASE_PATH ?>/erapor/ppi/<?= (int)$session['id'] ?>/pdf" target="_blank" rel="noopener">PDF PPI</a>
<?php if ($canSend && $ppiState['status']==='BELUM_DIAJUKAN' && in_array($session['status'],['MENUNGGU_TTD','SELESAI'],true)): ?>
<?= uiButton('Ajukan Rapor PPI','primary',['marginVertical'=>0,'disabled'=>!$ppiState['can_submit'],'attributes'=>['data-erapor-submit-ppi'=>true,'aria-describedby'=>$ppiDeadlineExpired?'erapor-ppi-deadline-hint':null]]) ?>
<?php elseif ($ppiState['status']!=='BELUM_DIAJUKAN'): ?>
<a class="ui-button ui-button--outline" href="<?= BASE_PATH ?>/erapor/ppi/<?= (int)$session['id'] ?>">Lihat Pengajuan PPI</a>
<?php endif; ?>
<?php if ($ppiState['status']==='TERBIT' && !empty($ppiShare['contacts'])): ?>
<?php $ppiContactCount=count($ppiShare['contacts']); ?>
<?php if ($ppiContactCount===1): $target=array_key_first($ppiShare['contacts']);$contact=$ppiShare['contacts'][$target]; ?>
<a class="ui-button ui-button--primary" target="_blank" rel="noopener" data-erapor-share-ppi="<?= e($target) ?>" href="<?= e($contact['wa_url']) ?>">Bagikan PPI ke <?= e($contact['label']) ?></a>
<?php else: ?>
<div class="action-menu erapor-share-menu" data-action-menu>
<?= uiButton('Bagikan PPI','primary',['marginVertical'=>0,'attributes'=>['data-action-menu-toggle'=>true,'aria-expanded'=>'false']]) ?>
<div class="action-menu-dropdown erapor-share-menu-dropdown"><?php foreach ($ppiShare['contacts'] as $target=>$contact): ?>
<a class="action-menu-item" target="_blank" rel="noopener" data-erapor-share-ppi="<?= e($target) ?>" href="<?= e($contact['wa_url']) ?>">Ke <?= e($contact['label']) ?></a>
<?php endforeach; ?></div></div>
<?php endif; ?>
<?php elseif ($ppiState['status']==='TERBIT' && !empty($ppiShare)): ?>
<span class="erapor-share-disabled" title="Kontak orang tua belum tersedia di data murid">
<?= uiButton('Bagikan PPI','disabled',['icon'=>'icon_share','marginVertical'=>0,'disabled'=>true,'attributes'=>['aria-describedby'=>'erapor-ppi-share-hint']]) ?>
</span>
<?php endif; ?>
</div>
<?php endif; ?>
<?php $ppiWorkflowActions=ob_get_clean(); ?>
<div class="erapor-section erapor-ppi-workflow">
<?= $ppiWorkflowActions ?>
<div class="erapor-section erapor-ppi-details"><?= $ppiWorkflowDetails ?></div>
</div>
