<?php
/**
 * Halaman Rapor Murid (Admin) — cookbook/design-system.md.
 * Accordion 3 level: Periode Penilaian -> Sesi Pembagian Rapor -> per-murid.
 *
 * Variabel dari RaporMuridController::index():
 * - $periodeList (nested: periode->sesi->rapor), $periodeOptions, $templateOptions, $canEdit
 */
$statusLabel = [
    'belum_diisi' => ['Belum diisi', 'badge-destruktif'],
    'menunggu_persetujuan' => ['Menunggu persetujuan', 'badge-peringatan'],
    'disetujui' => ['Disetujui', 'badge-positif'],
];

$headerActions = $canEdit
    ? '<button type="button" class="ui-button ui-button--primary" data-modal-open="modal-tambah-sesi">+ Tambah Sesi Pembagian</button>'
    : '';

require VIEW_PATH . '/layouts/shell-header.php';
?>
<?php if (empty($periodeList)): ?>
<p class="text-body-sm">Belum ada periode penilaian. Buat dulu lewat menu Kurikulum &gt; Periode Penilaian.</p>
<?php endif; ?>

<?php foreach ($periodeList as $periode): ?>
<div class="accordion-item">
    <button type="button" class="accordion-header" data-accordion-toggle>
        <span class="text-body-sm font-bold"><?= e($periode['nama']) ?></span>
        <span class="badge badge-netral"><?= $periode['jumlah_murid'] ?> murid</span>
        <span class="accordion-chevron"><?= icon('icon_chevron') ?></span>
    </button>
    <div class="accordion-body">
        <?php if (empty($periode['sesi'])): ?>
        <p class="text-body-sm">Belum ada sesi pembagian rapor untuk periode ini.</p>
        <?php endif; ?>
        <?php foreach ($periode['sesi'] as $sesi): ?>
        <div class="accordion-item">
            <button type="button" class="accordion-header" data-accordion-toggle>
                <span class="text-body-sm"><?= e($sesi['nama']) ?></span>
                <span class="text-caption-md"><?= date('d/m/Y', strtotime($sesi['tanggal_mulai'])) ?> - <?= date('d/m/Y', strtotime($sesi['tanggal_selesai'])) ?></span>
                <span class="badge badge-netral"><?= count($sesi['rapor']) ?></span>
                <span class="accordion-chevron"><?= icon('icon_chevron') ?></span>
            </button>
            <div class="accordion-body">
                <?php foreach ($sesi['rapor'] as $r): ?>
                <div class="guru-murid-item" style="display:flex; align-items:center; justify-content:space-between;">
                    <a href="<?= BASE_PATH ?>/rapor-murid/<?= $r['id'] ?>"><?= e($r['nama_lengkap']) ?></a>
                    <div style="display:flex; align-items:center; gap: var(--space-3);">
                        <span class="badge <?= $statusLabel[$r['status']][1] ?>"><?= $statusLabel[$r['status']][0] ?></span>
                        <?php if ($canEdit && $r['status'] === 'menunggu_persetujuan'): ?>
                        <div class="action-menu" data-action-menu>
                            <button type="button" class="action-menu-toggle" data-action-menu-toggle><?= icon('icon_more_vertical') ?></button>
                            <div class="action-menu-dropdown">
                                <form method="POST" action="<?= BASE_PATH ?>/rapor-murid/<?= $r['id'] ?>/setujui">
                                    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                    <button type="submit">Setujui</button>
                                </form>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($sesi['rapor'])): ?>
                <div class="guru-murid-item text-caption-md">Belum ada murid aktif di sesi ini.</div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<?php if ($canEdit): ?>
<div class="modal-overlay" id="modal-tambah-sesi">
    <div class="modal-box modal-sm">
        <h2 class="modal-title">Tambah Sesi Pembagian Rapor</h2>
        <form method="POST" action="<?= BASE_PATH ?>/rapor-murid/sesi" class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
            <div class="field">
                <label class="field-label">Periode Penilaian *</label>
                <select name="periode_id" class="field-input" required>
                    <option value="">Pilih periode</option>
                    <?php foreach ($periodeOptions as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= e($p['nama']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field-label">Template Rapor *</label>
                <select name="template_id" class="field-input" required>
                    <option value="">Pilih template</option>
                    <?php foreach ($templateOptions as $t): ?>
                    <option value="<?= $t['id'] ?>"><?= e($t['nama']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field-label">Nama Sesi *</label>
                <input type="text" name="nama" class="field-input" placeholder="mis. Pembagian Rapor Tengah Semester 25/26" required>
            </div>
            <div class="field-row">
                <div class="field">
                    <label class="field-label">Tanggal Mulai *</label>
                    <input type="date" name="tanggal_mulai" class="field-input" required>
                </div>
                <div class="field">
                    <label class="field-label">Tanggal Selesai *</label>
                    <input type="date" name="tanggal_selesai" class="field-input" required>
                </div>
            </div>
            <p class="text-caption-md">Membuat sesi akan otomatis membuat baris rapor kosong untuk semua murid berstatus "Bersekolah", ter-assign ke guru masing-masing (dari data Manajemen Guru/Kelas).</p>
            <div class="modal-actions">
                <button type="button" class="ui-button ui-button--outline" data-modal-close>Batal</button>
                <button type="submit" class="ui-button ui-button--primary">Buat Sesi</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
