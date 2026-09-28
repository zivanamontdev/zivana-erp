<?php
/**
 * Portal Guru > Profil. Variabel dari ProfileController::index():
 * - $profile (null = akun tanpa data pegawai), $canViewSignature, $canEditSignature (hasil aksi tampil sebagai toast)
 */
// Simpan di header (sejajar judul) mengirim form Informasi Pribadi lewat atribut form.
$headerActions = $profile !== null ? uiButton('Simpan', 'primary', ['type' => 'submit', 'marginVertical' => 0, 'attributes' => ['form' => 'form-profil']]) : '';
require VIEW_PATH . '/layouts/shell-header.php';
$csrf = e(getCsrfToken());
?>
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/portal-guru.css?v=<?= filemtime(ROOT_PATH . '/public/assets/css/portal-guru.css') ?>">

<?php if ($profile === null): ?>
<div class="profile-layout">
    <?php ob_start(); ?>
    <div class="profile-avatar-wrap"><div class="profile-avatar profile-avatar--initials" aria-hidden="true"><?= e(initials($account['nama'])) ?></div></div>
    <div class="profile-identity">
        <?= uiText($account['nama'], 'body-lg', ['tag' => 'h2', 'weight' => 'bold', 'tone' => 'heading', 'align' => 'center']) ?>
        <?= uiText($account['role'], 'body-sm', ['tone' => 'muted', 'align' => 'center']) ?>
    </div>
    <div class="profile-badges"><?= $account['is_active'] ? uiBadge('Aktif', 'positif') : uiBadge('Nonaktif', 'netral') ?><?= uiBadge('Akun sistem', 'netral') ?></div>
    <?php echo uiCard(ob_get_clean(), 'outlined', ['tag' => 'aside', 'class' => 'profile-card profile-summary profile-area-summary']); ?>
    <?php ob_start(); ?>
    <?= uiText('Informasi Akun', 'body-md', ['tag' => 'h2', 'weight' => 'bold', 'tone' => 'heading']) ?>
    <div class="field-row profile-fields">
        <?= uiDataCard('Email', $account['email']) ?>
        <?= uiDataCard('Peran', $account['role']) ?>
    </div>
    <?= uiText('Akun ini tidak terhubung ke data pegawai, jadi tidak memiliki NUPTK, tanda tangan, atau penugasan.', 'caption-md', ['tone' => 'muted']) ?>
    <?php echo uiCard(ob_get_clean(), 'outlined', ['tag' => 'section', 'class' => 'profile-card profile-area-info']); ?>
</div>
<?php else: ?>
<?php
$roles = $profile['tahap_persetujuan'];
$assignments = [
    'Wali Kelas' => $profile['wali_kelas'] ? implode(', ', $profile['wali_kelas']) : '-',
    'Persetujuan eRapor' => $roles ? implode(', ', $roles) : '-',
    'Murid Diampu' => $profile['murid_diampu'] ? implode(', ', array_map(fn($r) => $r['kelas'] . ' (' . (int) $r['jumlah'] . ')', $profile['murid_diampu'])) : '-',
];
?>
<div class="profile-layout">
        <?php ob_start(); ?>
        <div class="profile-avatar-wrap">
            <div class="profile-avatar profile-avatar--initials" aria-hidden="true"><?= e(initials($profile['nama'])) ?></div>
        </div>
        <div class="profile-identity">
            <?= uiText($profile['nama'], 'body-lg', ['tag' => 'h2', 'weight' => 'bold', 'tone' => 'heading', 'align' => 'center']) ?>
            <?= uiText($profile['jabatan'], 'body-sm', ['tone' => 'muted', 'align' => 'center']) ?>
        </div>
        <div class="profile-badges">
            <?= $profile['is_active'] && $profile['karyawan_active'] ? uiBadge('Aktif', 'positif') : uiBadge('Nonaktif', 'netral') ?>
            <?php foreach ($roles as $role): ?><?= uiBadge($role, 'netral') ?><?php endforeach; ?>
        </div>
        <?php echo uiCard(ob_get_clean(), 'outlined', ['tag' => 'aside', 'class' => 'profile-card profile-summary profile-area-summary']); ?>
        <?php if ($canViewSignature): ?>
            <?php ob_start(); ?>
            <div class="profile-card-heading">
                <div class="profile-card-title">
                    <?= uiText('Tanda Tangan', 'body-md', ['tag' => 'h2', 'weight' => 'bold', 'tone' => 'heading']) ?>
                    <?= $profile['has_signature'] ? uiBadge('Tersimpan', 'positif') : uiBadge('Belum ada', 'netral') ?>
                </div>
                <?php if ($canEditSignature): ?>
                    <div class="profile-icon-actions">
                        <?= uiButton($profile['has_signature'] ? 'Ganti tanda tangan' : 'Unggah tanda tangan', 'outline', ['icon' => $profile['has_signature'] ? 'icon_edit' : 'icon_plus', 'iconOnly' => true, 'marginVertical' => 0, 'attributes' => ['title' => $profile['has_signature'] ? 'Ganti tanda tangan' : 'Unggah tanda tangan', 'data-modal-open' => 'modal-profil-ttd']]) ?>
                        <?php if ($profile['has_signature']): ?>
                            <?= uiButton('Hapus tanda tangan', 'outline-danger', ['icon' => 'icon_trash', 'iconOnly' => true, 'marginVertical' => 0, 'attributes' => ['title' => 'Hapus tanda tangan', 'data-modal-open' => 'modal-profil-ttd-cabut']]) ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="profile-signature-box">
                <?php if ($profile['has_signature']): ?>
                    <img src="<?= BASE_PATH ?>/erapor/profil-penandatangan/tanda-tangan" alt="Tanda tangan <?= e($profile['nama']) ?>">
                <?php else: ?>
                    <?= uiText('Belum ada tanda tangan', 'caption-md', ['tone' => 'muted']) ?>
                <?php endif; ?>
            </div>
            <?php echo uiCard(ob_get_clean(), 'outlined', ['tag' => 'section', 'class' => 'profile-card profile-area-signature']); ?>
        <?php endif; ?>

        <?php ob_start(); ?>
        <?= uiText('Informasi Pribadi', 'body-md', ['tag' => 'h2', 'weight' => 'bold', 'tone' => 'heading']) ?>
        <form method="POST" action="<?= BASE_PATH ?>/portal-guru/profil" class="profile-form" id="form-profil" novalidate data-toast-validate>
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div class="field-row profile-fields">
                <?= uiField('nama', 'Nama Lengkap', ['variant' => 'form', 'value' => $profile['nama'], 'required' => true, 'inputAttributes' => ['maxlength' => 150]]) ?>
                <?php if ($canEditSignature): ?>
                    <?= uiField('nuptk', 'NUPTK', ['variant' => 'form', 'value' => $profile['nuptk'] ?? '', 'placeholder' => '16 digit', 'autocomplete' => 'off',
                        'inputAttributes' => ['inputmode' => 'numeric', 'maxlength' => '16', 'pattern' => '[0-9]{16}']]) ?>
                <?php else: ?>
                    <?= uiField('nuptk_view', 'NUPTK', ['variant' => 'form', 'value' => $profile['nuptk'] ?? '-', 'state' => 'viewonly']) ?>
                <?php endif; ?>
                <?= uiDataCard('Email', $profile['email']) ?>
                <?= uiDataCard('Jabatan', $profile['jabatan']) ?>
            </div>

        </form>
        <?php echo uiCard(ob_get_clean(), 'outlined', ['tag' => 'section', 'class' => 'profile-card profile-area-info']); ?>

        <?php ob_start(); ?>
        <?= uiText('Penugasan', 'body-md', ['tag' => 'h2', 'weight' => 'bold', 'tone' => 'heading']) ?>
        <div class="profile-assignments">
            <?php foreach ($assignments as $label => $value): ?><?= uiDataCard($label, $value) ?><?php endforeach; ?>
        </div>
        <?php echo uiCard(ob_get_clean(), 'outlined', ['tag' => 'section', 'class' => 'profile-card profile-area-assignments']); ?>
</div>


<?php if ($canEditSignature): ?>
<?php ob_start(); ?>
<form method="POST" action="<?= BASE_PATH ?>/erapor/profil-penandatangan" enctype="multipart/form-data" class="modal-body" novalidate data-toast-validate>
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
    <input type="hidden" name="return_to" value="profil">
    <input type="hidden" name="nuptk" value="<?= e($profile['nuptk'] ?? '') ?>">
    <?= uiField('signature', 'File Tanda Tangan', ['type' => 'file', 'variant' => 'form', 'inputAttributes' => ['accept' => 'image/png', 'required' => 'required']]) ?>
    <?= uiText('PNG latar transparan, maks. 2 MB.', 'caption-md', ['tone' => 'muted']) ?>
    <?= uiCheckbox('consent', 'Tanda tangan ini milik saya dan boleh dipakai di rapor.', false, ['value' => '1', 'textVariant' => 'body-sm', 'tone' => 'default', 'inputAttributes' => ['required' => 'required']]) ?>
    <div class="modal-actions">
        <?= uiButton('Batal', 'outline', ['marginVertical' => 0, 'attributes' => ['data-modal-close' => true]]) ?>
        <?= uiButton('Simpan', 'primary', ['type' => 'submit', 'marginVertical' => 0]) ?>
    </div>
</form>
<?php echo uiModal('modal-profil-ttd', $profile['has_signature'] ? 'Ganti Tanda Tangan' : 'Unggah Tanda Tangan', ob_get_clean()); ?>

<?php if ($profile['has_signature']): ?>
<?php ob_start(); ?>
<form method="POST" action="<?= BASE_PATH ?>/erapor/profil-penandatangan/cabut" class="modal-body">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
    <input type="hidden" name="return_to" value="profil">
    <div class="modal-actions">
        <?= uiButton('Batal', 'outline', ['marginVertical' => 0, 'attributes' => ['data-modal-close' => true]]) ?>
        <?= uiButton('Hapus', 'outline-danger', ['type' => 'submit', 'marginVertical' => 0]) ?>
    </div>
</form>
<?php echo uiModal('modal-profil-ttd-cabut', 'Hapus Tanda Tangan?', ob_get_clean(), [
    'variant' => 'delete', 'description' => 'Rapor berikutnya tidak memakai tanda tangan ini. Rapor yang sudah disetujui tidak berubah.',
]); ?>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>
<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
