<?php
/**
 * Dashboard Portal Guru — cookbook/design-system.md (Portal Guru > Dashboard).
 * 3 blok: Agenda Sedang Berlangsung, Daftar Murid, Agenda Berikutnya.
 *
 * Variabel dari PortalGuruController::dashboard():
 * - $agendaBerlangsung, $agendaBerikutnya (array|null), $daftarMurid
 */
$headerActions = '';
require VIEW_PATH . '/layouts/shell-header.php';
?>
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/portal-guru.css">

<div class="dashboard-banner">
    <span class="text-headline-sm font-bold">Halo! <?= e($_SESSION['user_name'] ?? '') ?></span>
    <span class="text-caption-md" data-live-datetime><?= strtoupper(date('d F Y, H:i')) ?></span>
</div>

<div class="dashboard-grid">
    <div class="dashboard-card">
        <h2 class="text-body-sm font-bold">Agenda Sedang Berlangsung</h2>
        <?php if ($agendaBerlangsung): ?>
        <p class="text-body-md font-bold"><?= e($agendaBerlangsung['nama']) ?></p>
        <span class="badge badge-peringatan">Sisa <?= (int) $agendaBerlangsung['sisa_hari'] ?> Hari</span>
        <?php else: ?>
        <p class="text-body-sm">Belum ada agenda yang sedang berlangsung.</p>
        <?php endif; ?>
    </div>

    <div class="dashboard-card">
        <div class="dashboard-card-header">
            <h2 class="text-body-sm font-bold">Daftar Murid</h2>
            <span class="text-caption-md">Tahun Ajaran <?= date('Y') ?>/<?= date('Y') + 1 ?></span>
            <span class="badge badge-netral"><?= count($daftarMurid) ?></span>
        </div>
        <?php if (empty($daftarMurid)): ?>
        <p class="text-body-sm">Belum ada murid yang diampu. Hubungi Admin untuk pengaturan kelas.</p>
        <?php endif; ?>
        <?php foreach ($daftarMurid as $m): ?>
        <div class="dashboard-murid-row">
            <span><?= e($m['nama_lengkap']) ?></span>
            <?php if (!empty($m['rapor_id'])): ?>
                <?php if ($m['rapor_status'] === 'disetujui'): ?>
                <a href="<?= BASE_PATH ?>/portal-guru/rapor/<?= $m['rapor_id'] ?>/pratinjau" class="dashboard-murid-link is-done">Lihat Rapor</a>
                <?php else: ?>
                <a href="<?= BASE_PATH ?>/portal-guru/rapor/<?= $m['rapor_id'] ?>" class="dashboard-murid-link is-pending">Isi Rapor</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="dashboard-card">
        <h2 class="text-body-sm font-bold">Agenda Berikutnya</h2>
        <?php if ($agendaBerikutnya): ?>
        <p class="text-body-md font-bold"><?= e($agendaBerikutnya['nama']) ?></p>
        <span class="text-caption-md"><?= date('d/m/Y', strtotime($agendaBerikutnya['tanggal_mulai'])) ?> - <?= date('d/m/Y', strtotime($agendaBerikutnya['tanggal_selesai'])) ?></span>
        <?php else: ?>
        <p class="text-body-sm" style="font-style:italic;">Belum ada agenda berikutnya yang ditambahkan oleh admin</p>
        <?php endif; ?>
    </div>
</div>

<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
