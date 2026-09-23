<?php
/**
 * Halaman Data Sekolah — cookbook/design-system.md bagian 5.
 * Singleton (1 baris data), 2 tab: Informasi Umum, Kontak & Media.
 *
 * Variabel dari SekolahController::index():
 * - $sekolah (array|null), $media (array), $tahunAjaranAktif (array|null)
 * - $canEdit (bool), $mode ('lihat'|'ubah')
 * - $errors (array field=>pesan), $old (array field=>value)
 */
$isEdit = $mode === 'ubah';

$val = function (string $field) use ($sekolah, $old) {
    return array_key_exists($field, $old) ? $old[$field] : ($sekolah[$field] ?? '');
};

$tahunLabel = $tahunAjaranAktif ? $tahunAjaranAktif['tahun_awal'] . '/' . $tahunAjaranAktif['tahun_akhir'] : '-';
$headerActions = '<div class="action-menu" data-action-menu data-dropdown-match-trigger>'
    . uiButton('Tahun Ajaran berjalan: ' . $tahunLabel, 'outline', [
        'icon' => 'icon_chevron', 'iconPosition' => 'right', 'marginVertical' => 0,
        'attributes' => ['data-action-menu-toggle' => true, 'aria-expanded' => 'false', 'aria-controls' => 'school-year-dropdown'],
    ])
    . '<div class="action-menu-dropdown" id="school-year-dropdown">'
    . '<span class="school-year-current">Tahun Ajaran berjalan: ' . e($tahunLabel) . '</span>';
if ($canEdit) {
    $headerActions .= '<button type="button" data-modal-open="modal-tahun-ajaran">Perbarui Tahun Ajaran</button>';
}
$headerActions .= '</div></div>';
if ($canEdit) {
    $headerActions .= $isEdit
        ? uiButton('Simpan', 'primary', ['type' => 'submit', 'attributes' => ['form' => 'form-sekolah']])
        : '<a href="' . BASE_PATH . '/sekolah?mode=ubah" class="ui-button ui-button--outline"><span class="ui-button-label">Ubah Data Sekolah</span></a>';
}

require VIEW_PATH . '/layouts/shell-header.php';
?>
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/sekolah.css?v=<?= filemtime(ROOT_PATH . '/public/assets/css/sekolah.css') ?>">


<div class="tabs sekolah-tabs" data-tabs>
    <div class="sekolah-tabs-nav">
        <?= uiButton('Informasi Umum', 'tabular-active', ['class' => 'is-active', 'marginVertical' => 0, 'attributes' => ['data-tab-target' => 'informasi']]) ?>
        <?= uiButton('Kontak & Media', 'tabular-inactive', ['marginVertical' => 0, 'attributes' => ['data-tab-target' => 'kontak']]) ?>
    </div>

    <?php if (!$isEdit): ?>
        <?php require VIEW_PATH . '/admin/sekolah/_details.php'; ?>
    <?php else: ?>
        <?php require VIEW_PATH . '/admin/sekolah/_edit.php'; ?>
    <?php endif; ?>
</div>

<div class="modal-overlay" id="modal-tahun-ajaran">
    <div class="modal-box modal-sm">
        <h2 class="modal-title">Perbarui Tahun Ajaran</h2>
        <form method="POST" action="<?= BASE_PATH ?>/sekolah/tahun-ajaran" class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">

            <?= uiDataCard('Tahun Ajaran Berjalan', $tahunLabel) ?>

            <p class="text-body-sm font-bold" style="margin: 0;">Perbarui ke:</p>

            <div class="field-row">
                <?php
                $currentYear = (int) date('Y');
                $startYears = range($currentYear - 1, $currentYear + 3);
                $endYears = range($currentYear - 1, $currentYear + 4);
                echo uiSelect('tahun_awal', 'Tahun Awal Ajaran *', array_combine($startYears, $startYears), ['value' => $currentYear - 1, 'required' => true]);
                echo uiSelect('tahun_akhir', 'Tahun Akhir Ajaran *', array_combine($endYears, $endYears), ['value' => $currentYear + 1, 'required' => true]);
                ?>
            </div>

            <div class="modal-actions">
                <button type="button" class="ui-button ui-button--outline" data-modal-close>Batalkan</button>
                <button type="submit" class="ui-button ui-button--primary">Perbarui Tahun Ajaran</button>
            </div>
        </form>
    </div>
</div>

<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
