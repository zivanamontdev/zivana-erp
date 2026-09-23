<?php
/** Static illustration: sample names and dates are design copy, not account data. */
$activeAgenda = uiText('Agenda sedang berlangsung', 'preview-callout', ['tag' => 'p', 'weight' => 'bold'])
    . uiInlineMeta('Pembagian Rapor Tengah Semester', 'Sisa 2 Hari', ['class' => 'text-preview-callout']);
ob_start();
echo uiCard($activeAgenda, 'callout', ['class' => 'login-active-agenda']);
?>
<div class="login-preview-table">
    <div class="login-preview-row login-preview-header">
        <div class="login-preview-name">
            <?= uiText('Daftar Murid', 'preview', ['tag' => 'p']) ?>
            <?= uiText('Tahun Ajaran 2025/2026', 'preview', ['tag' => 'p']) ?>
        </div>
        <span class="login-preview-action"><?= uiText('3', 'preview') ?><?= icon('icon_chevron') ?></span>
    </div>
    <?php foreach (['Eira Salsabila', 'Citra Novalina', 'Daffa Arkan Pratama'] as $index => $name): ?>
        <div class="login-preview-row">
            <?= uiText($name, 'preview', ['class' => 'login-preview-name']) ?>
            <span class="login-preview-action <?= $index === 0 ? 'ui-text-tone-success' : 'ui-text-tone-brand' ?>">
                <?= uiText($index === 0 ? 'Lihat Rapor' : 'Isi Rapor', 'preview') ?>
                <?= icon('icon_chevron', 'login-chevron-right') ?>
            </span>
        </div>
    <?php endforeach; ?>
</div>
<?php
$nextAgenda = uiText('Agenda Berikutnya', 'preview', ['tag' => 'p', 'weight' => 'bold'])
    . uiInlineMeta('Pembagian Rapor Akhir Semester', '12/12/2026 - 24/12/2026', ['class' => 'text-preview']);
echo uiCard($nextAgenda, 'outlined', ['class' => 'login-next-agenda']);
$previewContent = ob_get_clean();
?>
<div class="login-preview" role="img" aria-label="Contoh dashboard: agenda tengah semester tersisa 2 hari, tiga murid, dan agenda akhir semester berikutnya.">
    <?= uiCard($previewContent, 'preview', ['class' => 'login-preview-card']) ?>
</div>
