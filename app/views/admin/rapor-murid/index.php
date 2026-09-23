<?php
/** One shared table per period; sessions are data sources, not nested cards. */
$yearChoices = [];
foreach ($tahunOptions as $year) $yearChoices[$year['id']] = $year['tahun_awal'] . '/' . $year['tahun_akhir'];
$headerActions = '<form method="GET" action="' . BASE_PATH . '/rapor-murid" class="list-filter">'
    . uiFilter('tahun_ajaran_id', 'Tahun Ajaran', $yearChoices, ['value'=>$tahunId, 'marginVertical'=>0, 'attributes'=>['onchange'=>'this.form.submit()']])
    . '</form>';
require VIEW_PATH . '/layouts/shell-header.php';
?>
<?php if (empty($periodeList)): ?>
<p class="text-body-sm">Belum ada periode rapor pada tahun ajaran ini.</p>
<?php endif; ?>
<?php foreach ($periodeList as $index => $periode): ?>
    <?php require VIEW_PATH . '/components/report-period-table.php'; ?>
<?php endforeach; ?>
<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
