<?php
$headerActions='<div class="erapor-approval-filters"><a class="ui-button ui-button--outline" href="'.BASE_PATH.'/erapor/persetujuan">Rapor Utama</a>'
    .'<a class="ui-button ui-button--'.(!$done?'tabular-active':'tabular-inactive').'" href="'.BASE_PATH.'/erapor/ppi">Butuh Persetujuan</a>'
    .'<a class="ui-button ui-button--'.($done?'tabular-active':'tabular-inactive').'" href="'.BASE_PATH.'/erapor/ppi?status=selesai">Selesai</a></div>';
require VIEW_PATH.'/layouts/shell-header.php';
?>
<p class="text-body-sm erapor-approval-intro">PPI diajukan terpisah dari rapor utama: Wali Kelas → Kepala Sekolah. Nilai dan PDF rapor utama tidak berubah.</p>
<div class="data-table-wrapper erapor-approval-table"><table class="data-table">
<thead><tr><th>Murid</th><th>Kelas</th><th>Status PPI</th><th class="col-action"></th></tr></thead><tbody>
<?php if (!$tasks): ?><tr><td colspan="4" class="data-table-empty">Tidak ada pengajuan PPI pada daftar ini.</td></tr><?php endif; ?>
<?php foreach ($tasks as $task): ?><tr>
<td><?= e($task['student']) ?></td><td><?= e($task['class']) ?></td><td><?= uiBadge($task['label'],$task['status']==='TERBIT'?'positif':'peringatan') ?></td>
<td><a class="ui-button ui-button--outline" href="<?= BASE_PATH ?>/erapor/ppi/<?= (int)$task['id'] ?>">Tinjau PPI</a></td>
</tr><?php endforeach; ?>
</tbody></table></div>
<?php require VIEW_PATH.'/layouts/shell-footer.php'; ?>
