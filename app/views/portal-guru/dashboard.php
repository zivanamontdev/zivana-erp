<?php
/**
 * Dashboard Portal Guru — cookbook/design-system.md (Portal Guru > Dashboard).
 * [FIX] Dibangun ulang dari grid 3 kartu jadi stack vertikal 1 kolom
 * sesuai assets/ss/Portal Guru - menu_dashboard.svg (lihat catatan di
 * portal-guru.css).
 *
 * Variabel dari PortalGuruController::dashboard():
 * - $agendaBerlangsung, $agendaBerikutnya (array|null), $daftarMurid
 */
$headerActions = '';
require VIEW_PATH . '/layouts/shell-header.php';
?>
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/portal-guru.css?v=<?= filemtime(ROOT_PATH . '/public/assets/css/portal-guru.css') ?>">

<div class="dashboard-banner">
    <span class="text-headline-sm font-bold">Halo! <?= e($_SESSION['display_name'] ?? '') ?></span>
    <span class="text-caption-md" data-live-datetime><?= strtoupper(date('d F Y, H:i')) ?></span>
</div>

<div class="dashboard-stack">
    <div class="dashboard-agenda-box">
        <p class="dashboard-agenda-label">Agenda sedang berlangsung</p>
        <?php if ($agendaBerlangsung): ?>
        <span class="text-body-sm"><?= e($agendaBerlangsung['nama']) ?> &bull; Sisa <?= (int) $agendaBerlangsung['sisa_hari'] ?> Hari</span>
        <?php else: ?>
        <span class="text-body-sm">Belum ada agenda yang sedang berlangsung.</span>
        <?php endif; ?>
    </div>

    <div class="dashboard-murid-card">
        <div class="dashboard-murid-header">
            <span>Daftar Murid</span>
            <span class="text-caption-md">Tahun Ajaran <?= date('Y') ?>/<?= date('Y') + 1 ?></span>
            <span class="badge badge-netral"><?= count($daftarMurid) ?></span>
        </div>
        <?php if (empty($daftarMurid)): ?>
        <div class="dashboard-murid-row">
            <span class="text-body-sm">Belum ada murid yang diampu. Hubungi Admin untuk pengaturan kelas.</span>
        </div>
        <?php endif; ?>
        <?php foreach ($daftarMurid as $m): ?>
        <div class="dashboard-murid-row">
            <span><?= e($m['nama_lengkap']) ?></span>
            <?php if (!empty($m['rapor_id'])): ?>
            <div class="dashboard-murid-row-actions">
                <?php if ($m['rapor_status'] === 'disetujui'): ?>
                <a href="<?= BASE_PATH ?>/portal-guru/rapor/<?= $m['rapor_id'] ?>/pratinjau" class="dashboard-murid-link is-done">Lihat Rapor</a>
                <?php else: ?>
                <a href="<?= BASE_PATH ?>/portal-guru/rapor/<?= $m['rapor_id'] ?>" class="dashboard-murid-link is-pending">Isi Rapor</a>
                <?php endif; ?>
                <?= icon('icon_chevron') ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="dashboard-agenda-box dashboard-agenda-box--neutral">
        <p class="dashboard-agenda-label">Agenda Berikutnya</p>
        <?php if ($agendaBerikutnya): ?>
        <span class="text-body-sm"><?= e($agendaBerikutnya['nama']) ?></span><br>
        <span class="text-caption-md"><?= date('d/m/Y', strtotime($agendaBerikutnya['tanggal_mulai'])) ?> - <?= date('d/m/Y', strtotime($agendaBerikutnya['tanggal_selesai'])) ?></span>
        <?php else: ?>
        <span class="text-body-sm" style="font-style:italic;">Belum ada agenda berikutnya yang ditambahkan oleh admin</span>
        <?php endif; ?>
    </div>
</div>

<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
