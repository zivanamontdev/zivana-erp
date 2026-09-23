<?php
$editing = $period !== null;
$suffix = $editing ? 'edit-' . (int) $period['id'] : 'create';
$modalId = $editing ? 'modal-ubah-periode-' . (int) $period['id'] : 'modal-tambah-periode';
$title = $editing ? 'Ubah Periode Rapor' : 'Tambah Periode Rapor';
$formUrl = BASE_PATH . '/kurikulum/periode-penilaian' . ($editing ? '/' . (int) $period['id'] : '');
$dateOptions = ['type' => 'date', 'variant' => 'form', 'font' => 'geist', 'required' => true, 'icon' => 'icon_calendar', 'iconCalendar' => true];
?>
<?php ob_start(); ?>
        <form method="POST" action="<?= e($formUrl) ?>" class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
            <input type="hidden" name="kategori" value="<?= e($period['kategori'] ?? 'Rapor Murid') ?>">
            <?= uiField('nama', 'Nama Periode Penilaian', ['id' => 'period-name-' . $suffix, 'variant' => 'form', 'font' => 'geist', 'value' => $period['nama'] ?? '', 'placeholder' => 'Isi nama periode penilaian', 'required' => true]) ?>
            <?= uiSelect('tipe', 'Tipe Periode', ['' => 'Pilih tipe periode'] + $tipeOptions, ['id' => 'period-type-' . $suffix, 'value' => $period['tipe'] ?? '', 'required' => true]) ?>
            <?= uiField('awal_periode', 'Awal Periode', array_merge($dateOptions, ['id' => 'period-start-' . $suffix, 'value' => $period['awal_periode'] ?? ''])) ?>
            <?= uiField('akhir_periode', 'Akhir Periode', array_merge($dateOptions, ['id' => 'period-end-' . $suffix, 'value' => $period['akhir_periode'] ?? ''])) ?>
            <div class="modal-actions">
                <?= uiButton('Batal', 'outline', ['marginVertical' => 0, 'attributes' => ['data-modal-close' => true]]) ?>
                <?= uiButton($editing ? 'Simpan Perubahan' : 'Tambah Periode', $editing ? 'outline' : 'primary', ['type' => 'submit', 'marginVertical' => 0]) ?>
            </div>
        </form>
<?php echo uiModal($modalId, $title, ob_get_clean()); ?>
