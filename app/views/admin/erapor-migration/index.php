<?php
/**
 * Sistem -> Migrasi eRapor. Variabel dari EraporMigrationController:
 * - $result (null | ['mode','ok','lines'])
 * - $apiEnabled (bool)
 */
$headerActions = '';
require VIEW_PATH . '/layouts/shell-header.php';
?>
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/erapor-approval.css?v=<?= filemtime(ROOT_PATH . '/public/assets/css/erapor-approval.css') ?>">
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/erapor-signer-profile.css?v=<?= filemtime(ROOT_PATH . '/public/assets/css/erapor-signer-profile.css') ?>">

<p class="text-body-sm erapor-signer-intro">Menjalankan migrasi skema dan seed rubrik eRapor pilot (sama dengan <code>database/run-erapor-pilot.php</code>). Backup database terlebih dahulu, jalankan <strong>Periksa</strong>, lalu <strong>Jalankan Migrasi</strong>. Setelah selesai, hapus <code>ERAPOR_MIGRATION_TOKEN</code> dari <code>.env</code> agar halaman ini kembali tidak tersedia.</p>

<?php if ($apiEnabled): ?>
<p class="erapor-approval-notice" role="alert"><?= uiText('ERAPOR_API_ENABLED masih true. Ubah menjadi false di .env sebelum migrasi; installer akan menolak berjalan.', 'body-sm', ['tone' => 'status-inactive']) ?></p>
<?php endif; ?>

<?php if ($result !== null): ?>
<?php ob_start(); ?>
<div class="erapor-migration-result">
    <div class="erapor-migration-result-head">
        <?= uiText($result['mode'] === 'apply' ? 'Hasil Jalankan Migrasi' : 'Hasil Periksa', 'body-md', ['tag' => 'h2', 'weight' => 'bold', 'tone' => 'heading']) ?>
        <?= $result['ok'] ? uiBadge('Berhasil', 'positif') : uiBadge('Berhenti', 'destruktif') ?>
    </div>
    <pre class="erapor-migration-log" data-migration-log><?php foreach ($result['lines'] as $line) echo '[eRapor] ', e($line), "\n"; ?></pre>
</div>
<?php echo uiCard(ob_get_clean(), 'outlined', ['tag' => 'section', 'class' => 'erapor-signer-current']); ?>
<?php endif; ?>

<?php ob_start(); ?>
<form method="POST" action="<?= BASE_PATH ?>/sistem/migrasi-erapor" class="erapor-signer-form" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
    <?= uiField('token', 'Token Migrasi', [
        'type' => 'password', 'variant' => 'form',
        'placeholder' => 'Salin dari ERAPOR_MIGRATION_TOKEN di .env',
        'inputAttributes' => ['required' => 'required', 'autocomplete' => 'off', 'spellcheck' => 'false'],
    ]) ?>
    <div class="erapor-signer-actions">
        <?= uiButton('Periksa', 'outline', ['type' => 'submit', 'marginVertical' => 0, 'attributes' => ['name' => 'mode', 'value' => 'check']]) ?>
        <?= uiButton('Jalankan Migrasi', 'primary', ['type' => 'submit', 'marginVertical' => 0, 'attributes' => ['name' => 'mode', 'value' => 'apply', 'data-migration-apply' => true]]) ?>
    </div>
    <p class="text-caption-md">Jalankan Migrasi bisa memakan waktu beberapa menit. Jangan klik dua kali atau memuat ulang halaman sampai hasil tampil.</p>
</form>
<?php echo uiCard(ob_get_clean(), 'outlined', ['tag' => 'section', 'class' => 'erapor-signer-editor']); ?>

<script>
document.querySelector('.erapor-signer-form').addEventListener('submit', function (event) {
    var buttons = this.querySelectorAll('button[type="submit"]');
    // Tunda disable agar nilai tombol (mode) tetap ikut terkirim.
    setTimeout(function () { buttons.forEach(function (b) { b.disabled = true; }); }, 0);
});
</script>
<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
