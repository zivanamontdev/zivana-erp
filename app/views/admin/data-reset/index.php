<?php
/**
 * Sistem -> Reset & Seed Data. Variabel dari DataResetController:
 * - $result (null | ['mode','ok','lines']), $plan (null | DataResetSeeder::plan()), $confirmation (string)
 */
$headerActions = '';
require VIEW_PATH . '/layouts/shell-header.php';
?>
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/erapor-approval.css?v=<?= filemtime(ROOT_PATH . '/public/assets/css/erapor-approval.css') ?>">
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/erapor-signer-profile.css?v=<?= filemtime(ROOT_PATH . '/public/assets/css/erapor-signer-profile.css') ?>">

<p class="text-body-sm erapor-signer-intro">Mengosongkan data operasional (murid, kelas, karyawan, akun selain Superadmin utama, periode, rapor lama, dan seluruh isian/sesi eRapor), lalu mengisi master data uji coba tanpa tag [DEMO]. RBAC, jabatan, template rapor, serta rubrik dan alur persetujuan eRapor <strong>tidak diubah</strong>. <strong>Backup database terlebih dahulu</strong>, jalankan <strong>Pratinjau</strong>, baru <strong>Jalankan</strong>.</p>

<?php if ($result !== null): ?>
<?php ob_start(); ?>
<div class="erapor-migration-result">
    <div class="erapor-migration-result-head">
        <?= uiText($result['mode'] === 'run' ? 'Hasil Reset & Seed' : 'Hasil Pratinjau', 'body-md', ['tag' => 'h2', 'weight' => 'bold', 'tone' => 'heading']) ?>
        <?= $result['ok'] ? uiBadge('Berhasil', 'positif') : uiBadge('Berhenti', 'destruktif') ?>
    </div>
    <pre class="erapor-migration-log" data-reset-log><?php foreach ($result['lines'] as $line) echo e($line), "\n"; ?></pre>
</div>
<?php echo uiCard(ob_get_clean(), 'outlined', ['tag' => 'section', 'class' => 'erapor-signer-current']); ?>
<?php endif; ?>

<?php if ($plan !== null): ?>
<?php ob_start(); ?>
<div class="erapor-migration-result" data-reset-plan>
    <?= uiText('Yang akan dikosongkan', 'body-md', ['tag' => 'h2', 'weight' => 'bold', 'tone' => 'heading']) ?>
    <pre class="erapor-migration-log"><?php foreach ($plan['delete_counts'] as $table => $count) echo e(str_pad($table, 36)), e((string) $count), " baris\n"; ?></pre>
    <?= uiText('Akun Superadmin yang dipertahankan: ' . ($plan['kept_users'] ? implode(', ', $plan['kept_users']) : '—'), 'body-sm') ?>
    <?= uiText('Yang akan dibuat', 'body-md', ['tag' => 'h2', 'weight' => 'bold', 'tone' => 'heading']) ?>
    <pre class="erapor-migration-log"><?php
        $s = $plan['seed'];
        echo 'Tahun ajaran ', $s['tahun_ajaran'], ' (2025/2026, 2026/2027 aktif) · Periode ', $s['periode'], ' · Kelas ', $s['kelas'], "\n";
        echo 'Murid ', $s['murid'], ' (', $s['murid_regular'], ' Regular, ', $s['murid_abk'], " ABK; termasuk status tanpa keterangan, berhenti, tamat)\n";
        echo 'Rapor contoh terisi penuh (belum dikonfirmasi): ', e(implode(', ', array_column(DataResetSeeder::EXAMPLES, 'murid'))), "\n\n";
        foreach ($plan['staff'] as $staff) echo e($staff), "\n";
    ?></pre>
</div>
<?php echo uiCard(ob_get_clean(), 'outlined', ['tag' => 'section', 'class' => 'erapor-signer-current']); ?>
<?php endif; ?>

<?php ob_start(); ?>
<form method="POST" action="<?= BASE_PATH ?>/sistem/reset-data" class="erapor-signer-form" autocomplete="off" data-reset-form>
    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
    <?= uiField('token', 'Token Reset', [
        'type' => 'password', 'variant' => 'form', 'placeholder' => 'Salin dari DATA_RESET_TOKEN di .env',
        'inputAttributes' => ['required' => 'required', 'autocomplete' => 'off', 'spellcheck' => 'false'],
    ]) ?>
    <?= uiField('password', 'Password Awal Akun Seed', [
        'type' => 'password', 'variant' => 'form', 'placeholder' => 'Min. 8 karakter, huruf kapital, angka, simbol',
        'inputAttributes' => ['autocomplete' => 'new-password'],
    ]) ?>
    <?= uiField('password_confirmation', 'Konfirmasi Password Awal', [
        'type' => 'password', 'variant' => 'form', 'placeholder' => 'Ulangi password awal',
        'inputAttributes' => ['autocomplete' => 'new-password'],
    ]) ?>
    <?= uiField('confirmation', 'Ketik "' . $confirmation . '" untuk Jalankan', [
        'type' => 'text', 'variant' => 'form', 'placeholder' => $confirmation,
        'inputAttributes' => ['autocomplete' => 'off', 'spellcheck' => 'false'],
    ]) ?>
    <div class="erapor-signer-actions">
        <?= uiButton('Pratinjau', 'outline', ['type' => 'submit', 'marginVertical' => 0, 'attributes' => ['name' => 'mode', 'value' => 'preview']]) ?>
        <?= uiButton('Jalankan Reset & Seed', 'primary', ['type' => 'submit', 'marginVertical' => 0, 'attributes' => ['name' => 'mode', 'value' => 'run']]) ?>
    </div>
    <p class="text-caption-md">Password awal dipakai untuk semua akun seed; minta setiap pengguna menggantinya setelah login pertama. Pratinjau hanya membutuhkan token.</p>
</form>
<?php echo uiCard(ob_get_clean(), 'outlined', ['tag' => 'section', 'class' => 'erapor-signer-editor']); ?>

<script>
document.querySelector('[data-reset-form]').addEventListener('submit', function () {
    var buttons = this.querySelectorAll('button[type="submit"]');
    // Tunda disable agar nilai tombol (mode) tetap ikut terkirim.
    setTimeout(function () { buttons.forEach(function (b) { b.disabled = true; }); }, 0);
});
</script>
<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
