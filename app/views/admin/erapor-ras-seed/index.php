<?php
/** Sistem -> Seed Rubrik RAS (sementara). Variabel: $result (null | ['mode','ok','lines']). */
$headerActions = '';
require VIEW_PATH . '/layouts/shell-header.php';
?>
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/erapor-approval.css?v=<?= filemtime(ROOT_PATH . '/public/assets/css/erapor-approval.css') ?>">
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/erapor-signer-profile.css?v=<?= filemtime(ROOT_PATH . '/public/assets/css/erapor-signer-profile.css') ?>">

<p class="text-body-sm erapor-signer-intro">Memasukkan rubrik Rapor Akhir Semester (<code>RAS_MONTESSORI_V1</code>). Jalankan migrasi <code>20261023_erapor_ras.sql</code> di phpMyAdmin lebih dulu, lalu <strong>Periksa</strong> dan <strong>Jalankan Seed</strong>. Aman diulang. Setelah selesai, hapus <code>ERAPOR_RAS_SEED_TOKEN</code> dari <code>.env</code> agar halaman ini kembali 404.</p>

<?php if ($result !== null): ?>
<?php ob_start(); ?>
<div class="erapor-migration-result">
    <div class="erapor-migration-result-head">
        <?= uiText($result['mode'] === 'apply' ? 'Hasil Seed' : 'Hasil Periksa', 'body-md', ['tag' => 'h2', 'weight' => 'bold', 'tone' => 'heading']) ?>
        <?= $result['ok'] ? uiBadge('Berhasil', 'positif') : uiBadge('Berhenti', 'destruktif') ?>
    </div>
    <pre class="erapor-migration-log"><?php foreach ($result['lines'] as $line) echo '[RAS] ', e($line), "\n"; ?></pre>
</div>
<?php echo uiCard(ob_get_clean(), 'outlined', ['tag' => 'section', 'class' => 'erapor-signer-current']); ?>
<?php endif; ?>

<?php ob_start(); ?>
<form method="POST" action="<?= BASE_PATH ?>/sistem/seed-ras" class="erapor-signer-form" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
    <?= uiField('token', 'Token Seed RAS', [
        'type' => 'password', 'variant' => 'form',
        'placeholder' => 'Salin dari ERAPOR_RAS_SEED_TOKEN di .env',
        'inputAttributes' => ['required' => 'required', 'autocomplete' => 'off', 'spellcheck' => 'false'],
    ]) ?>
    <div class="erapor-signer-actions">
        <?= uiButton('Periksa', 'outline', ['type' => 'submit', 'marginVertical' => 0, 'attributes' => ['name' => 'mode', 'value' => 'check']]) ?>
        <?= uiButton('Jalankan Seed', 'primary', ['type' => 'submit', 'marginVertical' => 0, 'attributes' => ['name' => 'mode', 'value' => 'apply']]) ?>
    </div>
</form>
<?php echo uiCard(ob_get_clean(), 'outlined', ['tag' => 'section', 'class' => 'erapor-signer-editor']); ?>

<script>
document.querySelector('.erapor-signer-form').addEventListener('submit', function () {
    var buttons = this.querySelectorAll('button[type="submit"]');
    setTimeout(function () { buttons.forEach(function (b) { b.disabled = true; }); }, 0);
});
</script>
<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
