<?php // Included only when the additive PPI migration is available. ?>
<?php
if (!$ppiState['separate'] && !($canEdit && in_array($session['status'],['BELUM_DIISI','TELAH_DIISI'],true))) return;
ob_start();
?>
<?php if ($canEdit && in_array($session['status'],['BELUM_DIISI','TELAH_DIISI'],true)): ?>
<?= uiCheckbox('ppi_include','Sertakan PPI dalam rapor utama',(bool)$ppiState['included'],['inputAttributes'=>['data-erapor-ppi-choice'=>true]]) ?>
<p class="text-caption-md">Jika tidak disertakan, seluruh nilai tetap tersimpan. Setelah Finalisasi rapor utama, ajukan hanya PPI tanpa mengisi ulang.</p>
<?php endif; ?>
<?php if ($ppiState['separate']): ?>
<div class="erapor-approval-state" role="status"><?= uiBadge('PPI Terpisah · '.$ppiState['label'],$ppiState['status']==='TERBIT'?'positif':'peringatan') ?>
<span class="text-caption-md" data-erapor-ppi-progress><?= (int)$ppiState['progress']['filled'] ?> / <?= (int)$ppiState['progress']['required'] ?> isian tersimpan</span></div>
<p class="text-body-sm">PPI tidak masuk PDF rapor utama. Pengajuan dan tanda tangan PPI dilakukan tersendiri melalui Wali Kelas → Kepala Sekolah.</p>
<div class="erapor-save-actions">
<a class="ui-button ui-button--outline" href="<?= BASE_PATH ?>/erapor/ppi/<?= (int)$session['id'] ?>/pdf" target="_blank" rel="noopener">PDF PPI</a>
<?php if ($canSend && $ppiState['status']==='BELUM_DIAJUKAN' && in_array($session['status'],['MENUNGGU_TTD','SELESAI'],true)): ?>
<?= uiButton('Ajukan Rapor PPI','primary',['marginVertical'=>0,'disabled'=>!$ppiState['can_submit'],'attributes'=>['data-erapor-submit-ppi'=>true]]) ?>
<?php elseif ($ppiState['status']!=='BELUM_DIAJUKAN'): ?>
<a class="ui-button ui-button--outline" href="<?= BASE_PATH ?>/erapor/ppi/<?= (int)$session['id'] ?>">Lihat Pengajuan PPI</a>
<?php endif; ?>
<?php if ($ppiState['status']==='TERBIT' && !empty($ppiShare['contacts'])): ?>
<?php $ppiContactCount=count($ppiShare['contacts']); ?>
<?php if ($ppiContactCount===1): $target=array_key_first($ppiShare['contacts']);$contact=$ppiShare['contacts'][$target]; ?>
<a class="ui-button ui-button--primary" target="_blank" rel="noopener" data-erapor-share-ppi="<?= e($target) ?>" href="<?= e($contact['wa_url']) ?>">Bagikan PPI ke <?= e($contact['label']) ?></a>
<?php else: ?>
<div class="action-menu erapor-share-menu" data-action-menu-root>
<?= uiButton('Bagikan PPI','primary',['marginVertical'=>0,'attributes'=>['data-action-menu-toggle'=>true,'aria-expanded'=>'false']]) ?>
<div class="action-menu-dropdown erapor-share-menu-dropdown" hidden><?php foreach ($ppiShare['contacts'] as $target=>$contact): ?>
<a class="action-menu-item" target="_blank" rel="noopener" data-erapor-share-ppi="<?= e($target) ?>" href="<?= e($contact['wa_url']) ?>">Ke <?= e($contact['label']) ?></a>
<?php endforeach; ?></div></div>
<?php endif; ?>
<?php endif; ?>
</div>
<?php if ($ppiState['status']==='BELUM_DIAJUKAN' && !in_array($session['status'],['MENUNGGU_TTD','SELESAI'],true)): ?>
<p class="text-caption-md">Finalisasi rapor utama terlebih dahulu untuk mengajukan PPI terpisah.</p>
<?php endif; ?>
<?php if ($ppiState['status']==='TERBIT'): ?>
<p class="text-caption-md" data-erapor-ppi-share-status><?= !empty($ppiShare['shared_at'])?'PPI dibagikan ke '.e(strtolower((string)$ppiShare['shared_to'])).' · '.date('d/m/Y H:i',strtotime($ppiShare['shared_at'])):'PPI belum dibagikan.' ?></p>
<?php endif; ?>
<?php endif; ?>
<?php echo uiCard(ob_get_clean(),'outlined',['tag'=>'section','class'=>'erapor-section erapor-session-notice']); ?>
