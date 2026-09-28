<?php
/**
 * Portal Guru > Profil (baca-saja). Variabel dari PortalGuruController::profil():
 * - $profile (null bila akun tidak terhubung ke data pegawai), $canManageSignature (bool)
 */
$headerActions = !empty($canManageSignature)
    ? '<a class="ui-button ui-button--outline" href="' . BASE_PATH . '/erapor/profil-penandatangan">Kelola Tanda Tangan</a>'
    : '';
header('Cache-Control: private, no-store, max-age=0');
require VIEW_PATH . '/layouts/shell-header.php';
?>
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/portal-guru.css?v=<?= filemtime(ROOT_PATH . '/public/assets/css/portal-guru.css') ?>">

<?php if ($profile === null): ?>
    <?= uiCard(uiText('Profil hanya tersedia untuk akun yang terhubung dengan data pegawai.', 'body-sm'), 'callout', ['tag' => 'section', 'class' => 'teacher-profile-card']) ?>
<?php else: ?>
    <?php ob_start(); ?>
    <div class="teacher-profile-heading">
        <?= uiText('Data Pegawai', 'body-md', ['tag' => 'h2', 'weight' => 'bold', 'tone' => 'heading']) ?>
        <?= $profile['is_active'] && $profile['karyawan_active'] ? uiBadge('Aktif', 'positif') : uiBadge('Nonaktif', 'netral') ?>
    </div>
    <div class="field-row teacher-profile-grid">
        <?= uiDataCard('Nama Lengkap', $profile['nama']) ?>
        <?= uiDataCard('Jabatan', $profile['jabatan']) ?>
        <?= uiDataCard('Email', $profile['email']) ?>
        <?= uiDataCard('NUPTK', $profile['nuptk'] ?: 'Belum diisi') ?>
        <?= uiDataCard('Wali Kelas', $profile['wali_kelas'] ? implode(', ', $profile['wali_kelas']) : 'Tidak ada') ?>
        <?= uiDataCard('Tugas Persetujuan eRapor', $profile['tahap_persetujuan'] ? implode(', ', $profile['tahap_persetujuan']) : 'Tidak ada') ?>
        <?= uiDataCard('Murid Diampu', $profile['murid_diampu']
            ? implode(', ', array_map(fn($row) => $row['kelas'] . ' (' . (int) $row['jumlah'] . ' murid)', $profile['murid_diampu']))
            : 'Tidak ada') ?>
    </div>
    <?php echo uiCard(ob_get_clean(), 'outlined', ['tag' => 'section', 'class' => 'teacher-profile-card']); ?>

    <?php ob_start(); ?>
    <div class="teacher-profile-heading">
        <?= uiText('Tanda Tangan', 'body-md', ['tag' => 'h2', 'weight' => 'bold', 'tone' => 'heading']) ?>
        <?= $profile['has_signature'] ? uiBadge('Tersimpan', 'positif') : uiBadge('Belum ada', 'netral') ?>
    </div>
    <?php if ($profile['has_signature'] && !empty($canManageSignature)): ?>
        <figure class="teacher-profile-signature">
            <img src="<?= BASE_PATH ?>/erapor/profil-penandatangan/tanda-tangan" alt="Tanda tangan <?= e($profile['nama']) ?>" loading="lazy">
            <figcaption><?= uiText('Tanda tangan ini ditempel pada rapor yang Anda setujui atau terima.' . ($profile['consented_at'] ? ' Disetujui pemilik pada ' . date('d/m/Y', strtotime($profile['consented_at'])) . '.' : ''), 'caption-md', ['tone' => 'muted']) ?></figcaption>
        </figure>
    <?php else: ?>
        <?= uiText('Belum ada tanda tangan tersimpan. Rapor akan mencetak nama Anda tanpa gambar tanda tangan.' . (!empty($canManageSignature) ? ' Unggah lewat tombol Kelola Tanda Tangan.' : ''), 'body-sm', ['tone' => 'muted']) ?>
    <?php endif; ?>
    <?php echo uiCard(ob_get_clean(), 'outlined', ['tag' => 'section', 'class' => 'teacher-profile-card']); ?>
<?php endif; ?>
<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
