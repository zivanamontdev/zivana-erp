<?php
$educationLabels = ['KB' => 'Kelompok Bermain (KB)', 'TK' => 'Taman Kanak-Kanak (TK)', 'TPA' => 'Tempat Penitipan Anak (TPA)'];
$education = (string) ($sekolah['bentuk_pendidikan'] ?? '');
?>
<div class="tabs-panel is-active" data-tab-panel="informasi">
    <div class="sekolah-detail-grid">
        <?= uiDataCard('Nama Legal Sekolah', $sekolah['nama_legal'] ?? null) ?>
        <?= uiDataCard('Nama Komersial Sekolah', $sekolah['nama_komersial'] ?? null) ?>
        <?= uiDataCard('Bentuk Pendidikan', $educationLabels[$education] ?? $education) ?>
        <?= uiDataCard('NPSN', $sekolah['npsn'] ?? null) ?>
        <?= uiDataCard('Alamat Sekolah', $sekolah['alamat'] ?? null, ['class' => 'sekolah-detail-full']) ?>
    </div>
</div>
<div class="tabs-panel" data-tab-panel="kontak">
    <div class="sekolah-contact-details">
        <div class="sekolah-detail-grid">
            <?= uiDataCard('No. Telepon Sekolah', $sekolah['no_telepon'] ?? null) ?>
            <?= uiDataCard('Email Sekolah', $sekolah['email'] ?? null) ?>
        </div>
        <?php foreach ($media as $item): ?>
        <div class="sekolah-media-details">
            <div class="sekolah-media-identity">
                <?= uiDataCard('Jenis Media', $item['jenis_media'] ?? null) ?>
                <?= uiDataCard('Nama Akun', $item['nama_akun'] ?? null) ?>
            </div>
            <?= uiDataCard('URL / Link Media', $item['url'] ?? null, ['externalLink' => true]) ?>
        </div>
        <?php endforeach; ?>
        <?php if (empty($media)): ?>
            <?= uiText('Belum ada media sekolah.', 'body-sm', ['tag' => 'p', 'tone' => 'muted']) ?>
        <?php endif; ?>
    </div>
</div>
