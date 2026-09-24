<?php
$pageTitle = 'Pengisian Rapor';
$session = $form['session'];
$student = $form['student'];
$period = $form['period'];
$completionByType = [];
foreach ($form['completion']['documents'] as $row) $completionByType[$row['jenis']] = $row;
$required = array_sum(array_column($form['completion']['documents'], 'required'));
$filled = array_sum(array_column($form['completion']['documents'], 'filled'));
$kelasLabel = trim(($student['level_kelas'] ?? '') . ' ' . ($student['nama_kelas'] ?? ''));
$canEdit = uiCan('Portal Guru', 'Daftar Murid', 'edit') && !empty($form['capabilities']['can_edit']);
$canSend = uiCan('Portal Guru', 'Daftar Murid', 'kirim');
$canSubmit = $canSend && !empty($form['capabilities']['can_confirm_filled']);
$readonlyMessages = [
    'STATUS_TERKUNCI' => 'Sesi ini sudah terkunci. Nilai dapat dilihat, tetapi tidak dapat diubah.',
    'TENGGAT_BERAKHIR' => 'Tenggat pengisian periode ini telah berakhir. Nilai dapat dilihat, tetapi tidak dapat diubah.',
];

$renderSelect = static function (array $document, string $type, string $key, string $label, array $choices, mixed $value, array $images = [], bool $required = true) use ($canEdit): string {
    $attributes = [
        'data-erapor-entry' => $type,
        'data-erapor-document-id' => (int)$document['id'],
        'data-erapor-key' => $key,
        'data-saved-value' => $value === null ? '' : (string)$value,
        'aria-required' => $required ? 'true' : 'false',
    ];
    if ($type === 'RTS') $attributes['data-erapor-indicator-id'] = substr($key, strlen('nilai:'));
    return uiSelect('erapor_' . (int)$document['id'] . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $key), $label,
        ['' => 'Pilih jawaban Anda'] + $choices, [
            'id' => 'erapor-field-' . (int)$document['id'] . '-' . substr(hash('sha256', $key), 0, 12),
            'value' => $value === null ? '' : (string)$value,
            'font' => 'base',
            'hideLabel' => true,
            'disabled' => !$canEdit,
            'attributes' => $attributes,
            'optionImages' => $images,
        ]);
};
$renderText = static function (array $document, string $type, string $key, string $label, ?string $value, bool $disabled = false, bool $required = true) use ($canEdit): string {
    return uiField('erapor_' . (int)$document['id'] . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $key), $label, [
        'type' => 'textarea', 'variant' => 'form',
        'id' => 'erapor-field-' . (int)$document['id'] . '-' . substr(hash('sha256', $key), 0, 12),
        'value' => $value ?? '', 'placeholder' => 'Tulis ' . mb_strtolower($label, 'UTF-8'),
        'disabled' => $disabled || !$canEdit,
        'inputAttributes' => [
            'rows' => 3,
            'data-erapor-entry' => $type,
            'data-erapor-document-id' => (int)$document['id'],
            'data-erapor-key' => $key,
            'data-saved-value' => $value ?? '',
            'aria-required' => $required ? 'true' : 'false',
        ],
    ]);
};

require VIEW_PATH . '/layouts/focus-header.php';
?>
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/portal-guru.css?v=<?= filemtime(ROOT_PATH . '/public/assets/css/portal-guru.css') ?>">

<main class="erapor-session" data-erapor-editor
      data-session-id="<?= (int)$session['id'] ?>"
      data-api-url="<?= e(BASE_PATH . '/api/erapor/sesi/' . (int)$session['id']) ?>"
      data-login-url="<?= e(BASE_PATH . '/login') ?>"
      data-csrf-token="<?= e(getCsrfToken()) ?>"
      data-can-submit="<?= $canSend ? 'true' : 'false' ?>">
    <section class="pengisian-header-card">
        <div class="pengisian-header-top">
            <div>
                <h1>Pengisian Rapor</h1>
                <p class="pengisian-student-name"><?= e($student['nama_lengkap']) ?></p>
            </div>
            <div class="pengisian-header-actions">
                <?php if ($canSend): ?>
                    <?= uiButton('Selesaikan Rapor', $canSubmit ? 'primary' : 'disabled', ['marginVertical'=>0, 'disabled'=>!$canSubmit, 'attributes'=>['data-erapor-confirm'=>true]]) ?>
                <?php elseif ($session['status'] === 'TELAH_DIISI'): ?>
                    <span class="teacher-report-action teacher-report-action--pending">Menunggu proses persetujuan</span>
                <?php endif; ?>
                <?= uiButton('Kembali ke Dashboard', 'outline', ['marginVertical'=>0, 'attributes'=>['onclick'=>'window.location.href=\''.BASE_PATH.'/portal-guru/dashboard\'']]) ?>
            </div>
        </div>
        <p class="pengisian-rapor-warning">
            <?= e($period['nama']) ?> · Tahun Ajaran <?= e($period['tahun_label']) ?> ·
            Semester <?= e(ucfirst(strtolower($period['semester']))) ?>
            <?php if ($kelasLabel !== ''): ?> · <?= e($kelasLabel) ?><?php endif; ?>
            · Status <?= e(str_replace('_', ' ', $session['status'])) ?>
        </p>
        <?php if (!$canEdit): ?>
            <p class="erapor-session-notice" role="status">
                <?= e($readonlyMessages[$form['capabilities']['read_only_reason'] ?? ''] ?? 'Sesi ini hanya dapat dilihat.') ?>
            </p>
        <?php endif; ?>
        <div class="pengisian-rapor-progress" aria-label="Progress isian rapor">
            <span class="pengisian-rapor-progress-label">Progress:</span>
            <span class="pengisian-rapor-progress-bar"><span class="pengisian-rapor-progress-bar-fill" data-erapor-overall-bar style="width:<?= $required ? min(100, round($filled / $required * 100)) : 0 ?>%"></span></span>
            <span data-erapor-overall-count><?= (int)$filled ?> dari <?= (int)$required ?></span>
        </div>
        <p class="erapor-save-status" data-erapor-status role="status" aria-live="polite">Semua perubahan tersimpan.</p>
        <div class="erapor-save-actions" data-erapor-recovery hidden>
            <?= uiButton('Coba simpan lagi', 'outline', ['marginVertical'=>0, 'attributes'=>['data-erapor-retry'=>true]]) ?>
            <?= uiButton('Muat ulang sesi', 'outline', ['marginVertical'=>0, 'attributes'=>['data-erapor-reload'=>true]]) ?>
        </div>
    </section>

    <?php foreach ($form['documents'] as $document): ?>
        <?php
        $type = $document['jenis_dokumen'];
        $rubric = $document['form'];
        $definitions = $rubric['definitions'];
        $values = $rubric['values'];
        $progress = $completionByType[$type] ?? ['filled'=>0, 'required'=>0, 'complete'=>false];
        ?>
        <section class="teacher-session-document erapor-document" data-erapor-document="<?= (int)$document['id'] ?>" data-erapor-type="<?= e($type) ?>" data-erapor-required="<?= (int)$progress['required'] ?>" data-erapor-filled="<?= (int)$progress['filled'] ?>">
            <div class="teacher-session-document-header">
                <div>
                    <h2><?= e($document['nama']) ?></h2>
                    <p><?= uiText($type, 'caption-md', ['tone'=>'muted']) ?></p>
                </div>
                <span class="teacher-session-document-progress" data-erapor-document-progress><?= (int)$progress['filled'] ?> / <?= (int)$progress['required'] ?> terisi</span>
            </div>

            <?php if ($type === 'RTS'): ?>
                <?php
                $subareasByArea = [];
                foreach ($definitions['subareas'] as $subarea) $subareasByArea[(int)$subarea['area_id']][] = $subarea;
                $groups = array_column($definitions['groups'], null, 'id');
                $scaleChoices = [];
                $scaleImages = [];
                foreach ($definitions['scale'] as $scale) {
                    $grade = (string)(int)$scale['nilai'];
                    $scaleChoices[$grade] = $scale['label'];
                    $scaleImages[$grade] = skalaSimbolSrc($scale['simbol']);
                }
                ?>
                <?php foreach ($definitions['areas'] as $area): ?>
                    <details class="pengisian-kategori ui-disclosure erapor-area">
                        <summary class="pengisian-kategori-header ui-disclosure-trigger"><span><?= e(mb_convert_case($area['nama'], MB_CASE_TITLE, 'UTF-8')) ?></span><span class="ui-disclosure-chevron" aria-hidden="true"><?= icon('icon_chevron') ?></span></summary>
                        <div class="pengisian-disclosure-content">
                            <?php foreach ($subareasByArea[(int)$area['id']] ?? [] as $subarea): ?>
                                <?php $subItems = array_values(array_filter($definitions['items'], fn($item)=>(int)$item['sub_area_id']===(int)$subarea['id'])); ?>
                                <details class="pengisian-subkategori ui-disclosure">
                                    <summary class="pengisian-subkategori-header ui-disclosure-trigger"><span><?= $subarea['implisit'] ? e(mb_convert_case($area['nama'], MB_CASE_TITLE, 'UTF-8')) : e(($subarea['huruf'] ? $subarea['huruf'] . '. ' : '') . $subarea['nama']) ?></span><span class="ui-disclosure-chevron" aria-hidden="true"><?= icon('icon_chevron') ?></span></summary>
                                    <div class="pengisian-disclosure-content">
                                        <?php $lastGroupId = null; ?>
                                        <?php foreach ($subItems as $item): ?>
                                            <?php
                                            $value = $values['nilai:' . $item['id']] ?? null;
                                            $groupId = $item['grup_id'] === null ? null : (int)$item['grup_id'];
                                            if ($groupId !== null && $groupId !== $lastGroupId && isset($groups[$groupId])):
                                            ?>
                                                <h3 class="erapor-subheading"><?= e($groups[$groupId]['nama']) ?></h3>
                                            <?php endif; $lastGroupId = $groupId; ?>
                                            <div class="pengisian-item-row">
                                                <span><?= e($item['tujuan']) ?></span>
                                                <?= $renderSelect($document, 'RTS', 'nilai:' . $item['id'], $item['tujuan'], $scaleChoices, $value === null ? null : (string)$value, $scaleImages) ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </details>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endforeach; ?>

            <?php elseif ($type === 'BING'): ?>
                <?php
                $bingChoices = [];
                foreach ($definitions['scale'] as $grade) $bingChoices[$grade['kode']] = $grade['label'];
                $groupedIndicators = [];
                foreach ($definitions['items'] as $item) $groupedIndicators[$item['grup'] ?: ''][] = $item;
                ?>
                <div class="erapor-fields-grid">
                    <?php foreach ($groupedIndicators as $groupName => $items): ?>
                        <section class="erapor-field-group">
                            <?php if ($groupName !== ''): ?><h3><?= e($groupName) ?></h3><?php endif; ?>
                            <?php foreach ($items as $item): ?>
                                <div class="pengisian-item-row">
                                    <span><?= e($item['penanda_cetak'] ? $item['penanda_cetak'] . ' ' : '') . e($item['label_cetak']) ?></span>
                                    <?= $renderSelect($document, 'BING', 'nilai:' . $item['id'], $item['label_cetak'], $bingChoices, $values['nilai:' . $item['id']] ?? null, [], (bool)$item['wajib']) ?>
                                </div>
                            <?php endforeach; ?>
                        </section>
                    <?php endforeach; ?>
                </div>
                <section class="erapor-field-group">
                    <h3>Catatan kemampuan</h3>
                    <div class="erapor-text-grid">
                        <?php foreach ($definitions['comments'] as $comment): ?>
                            <?= $renderText($document, 'BING', 'komentar:' . $comment['id'], $comment['label_cetak'], $values['komentar:' . $comment['id']] ?? null, false, (bool)$comment['wajib']) ?>
                        <?php endforeach; ?>
                    </div>
                </section>

            <?php elseif ($type === 'PPI'): ?>
                <?php
                $columnsByBagian = [];
                foreach ($definitions['columns'] as $column) $columnsByBagian[$column['bagian']][] = $column;
                ?>
                <?php foreach ($definitions['aspects'] as $aspect): ?>
                    <details class="erapor-ppi-aspect ui-disclosure">
                        <summary class="pengisian-subkategori-header ui-disclosure-trigger"><span><?= e($aspect['nama']) ?></span><span class="ui-disclosure-chevron" aria-hidden="true"><?= icon('icon_chevron') ?></span></summary>
                        <div class="erapor-ppi-fields">
                            <?php foreach ($columnsByBagian as $columns): foreach ($columns as $column): ?>
                                <?php $key = $aspect['id'] . ':' . $column['id']; ?>
                                <?= $renderText($document, 'PPI', $key, $column['label_cetak'], $values[$key] ?? null, false, (bool)$column['wajib']) ?>
                            <?php endforeach; endforeach; ?>
                        </div>
                    </details>
                <?php endforeach; ?>

            <?php elseif ($type === 'AGAMA'): ?>
                <?php
                $subscopesByScope = [];
                foreach ($definitions['subscopes'] as $subscope) $subscopesByScope[(int)$subscope['lingkup_id']][] = $subscope;
                $agamaChoices = [];
                foreach ($definitions['scale'] as $grade) $agamaChoices[$grade['kolom_cetak']] = $grade['label'];
                ?>
                <?php foreach ($definitions['scopes'] as $scope): ?>
                    <?php
                    $scopeSubscopes = $subscopesByScope[(int)$scope['id']] ?? [];
                    $scopeItems = [];
                    foreach ($definitions['items'] as $item) {
                        foreach ($scopeSubscopes as $subscope) if ((int)$item['sub_id'] === (int)$subscope['id']) $scopeItems[] = $item;
                    }
                    ?>
                    <details class="pengisian-kategori ui-disclosure erapor-area">
                        <summary class="pengisian-kategori-header ui-disclosure-trigger"><span><?= e($scope['nomor_romawi'] . '. ' . $scope['nama']) ?></span><span class="ui-disclosure-chevron" aria-hidden="true"><?= icon('icon_chevron') ?></span></summary>
                        <div class="pengisian-disclosure-content">
                            <?php foreach ($scopeSubscopes as $subscope): ?>
                                <?php $items = array_values(array_filter($scopeItems, fn($item)=>(int)$item['sub_id']===(int)$subscope['id'])); ?>
                                <?php if (!$subscope['implisit']): ?><h3 class="erapor-subheading"><?= e(($subscope['huruf'] ? $subscope['huruf'] . '. ' : '') . $subscope['nama']) ?></h3><?php endif; ?>
                                <?php foreach ($items as $item): ?>
                                    <?php
                                    $itemNames = array_values(array_filter($definitions['names'], fn($name)=>(int)$name['item_id']===(int)$item['id']));
                                    $label = ($item['nomor'] ? $item['nomor'] . '. ' : '') . $item['teks'];
                                    ?>
                                    <div class="pengisian-item-row">
                                        <span><?= e($label) ?><?php if ($itemNames): ?><small><?= e(implode(' · ', array_column($itemNames, 'nama'))) ?></small><?php endif; ?></span>
                                        <?= $renderSelect($document, 'AGAMA', 'nilai:' . $item['id'], $label, $agamaChoices, $values['nilai:' . $item['id']] ?? null) ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                            <?php if (!empty($scope['catatan_wajib'])): ?>
                                <?= $renderText($document, 'AGAMA', 'catatan:' . $scope['id'], 'Catatan ' . $scope['nama'], $values['catatan:' . $scope['id']] ?? null, false, (bool)$scope['catatan_wajib']) ?>
                            <?php endif; ?>
                        </div>
                    </details>
                <?php endforeach; ?>

            <?php else: ?>
                <div class="erapor-session-notice" role="status">
                    <h3>Form <?= e($document['nama']) ?> belum tersedia di editor ini.</h3>
                    <p>Dokumen Ummi memerlukan alur inisialisasi periode dan pencatatan tes khusus. Belum ada perubahan yang dilakukan pada dokumen ini.</p>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</main>

<script type="application/json" data-erapor-initial-state><?= json_encode([
    'completion' => $form['completion'],
    'capabilities' => $form['capabilities'],
    'session' => $session,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?></script>

<script src="<?= BASE_PATH ?>/assets/js/ui-select.js?v=<?= filemtime(ROOT_PATH . '/public/assets/js/ui-select.js') ?>"></script>
<script src="<?= BASE_PATH ?>/assets/js/erapor-session.js?v=<?= filemtime(ROOT_PATH . '/public/assets/js/erapor-session.js') ?>"></script>
<?php require VIEW_PATH . '/layouts/focus-footer.php'; ?>
