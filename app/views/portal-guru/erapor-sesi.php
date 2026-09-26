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
$readonlyMessages = [
    'STATUS_TERKUNCI' => 'Sesi ini sudah terkunci. Nilai dapat dilihat, tetapi tidak dapat diubah.',
    'TENGGAT_BERAKHIR' => 'Tenggat pengisian periode ini telah berakhir. Nilai dapat dilihat, tetapi tidak dapat diubah.',
];

$renderSelect = static function (array $document, string $type, string $key, string $label, array $choices, mixed $value, array $images = [], bool $required = true, array $extraAttributes = []) use ($canEdit): string {
    $attributes = [
        'data-erapor-entry' => $type,
        'data-erapor-document-id' => (int)$document['id'],
        'data-erapor-key' => $key,
        'data-saved-value' => $value === null ? '' : (string)$value,
        'aria-required' => $required ? 'true' : 'false',
    ];
    if ($type === 'RTS') $attributes['data-erapor-indicator-id'] = substr($key, strlen('nilai:'));
    $attributes = array_merge($attributes, $extraAttributes);
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
// Simbol skala RTS; "-" (Belum Dikenalkan) tidak punya gambar dan ditampilkan sebagai teks.
$scaleIcon = static function (array $scale): string {
    $source = $scale['simbol'] === '-' ? '' : skalaSimbolSrc($scale['simbol']);
    return '<span class="erapor-scale-icon" aria-hidden="true">' . ($source !== '' ? '<img src="' . e($source) . '" alt="">' : '<span class="erapor-scale-dash"></span>') . '</span>';
};
// RTS memakai radio ikon (satu klik) alih-alih dropdown; label lengkap ada di keterangan halaman dan dibacakan pembaca layar.
$renderScale = static function (array $document, array $item, array $scales, ?int $value) use ($canEdit, $scaleIcon): string {
    $name = 'erapor_' . (int)$document['id'] . '_nilai_' . (int)$item['id'];
    $html = '<div class="erapor-scale-options" role="radiogroup" ' . uiAttrs([
        'aria-label' => $item['tujuan'],
        'aria-required' => 'true',
        'data-erapor-entry' => 'RTS',
        'data-erapor-radio' => 'true',
        'data-erapor-document-id' => (int)$document['id'],
        'data-erapor-key' => 'nilai:' . $item['id'],
        'data-erapor-indicator-id' => (int)$item['id'],
        'data-saved-value' => $value === null ? '' : (string)$value,
    ]) . '>';
    foreach ($scales as $scale) {
        $grade = (int)$scale['nilai'];
        $html .= '<label class="erapor-scale-option" title="' . e($scale['label']) . '">'
            . '<input type="radio" name="' . e($name) . '" value="' . $grade . '"' . ($value === $grade ? ' checked' : '') . (!$canEdit ? ' disabled' : '') . '>'
            . $scaleIcon($scale) . '<span class="ui-visually-hidden">' . e($scale['label']) . '</span></label>';
    }
    return $html . '</div>';
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
                <?php if ($canSend && $session['status'] === 'BELUM_DIISI'): ?>
                    <?php // Tetap aktif walau belum lengkap: klik menandai isian wajib yang kosong dan membuka bagiannya. ?>
                    <?= uiButton('Selesaikan Rapor', 'primary', ['marginVertical'=>0, 'attributes'=>['data-erapor-confirm'=>true]]) ?>
                <?php elseif ($canSend && $session['status'] === 'TELAH_DIISI'): ?>
                    <?= uiButton('Konfirmasi Penerimaan', 'primary', ['marginVertical'=>0, 'attributes'=>['data-erapor-confirm-reception'=>true]]) ?>
                <?php elseif (in_array($session['status'], ['MENUNGGU_TTD', 'SELESAI'], true)): ?>
                    <span class="teacher-report-action teacher-report-action--pending"><?= $session['status'] === 'SELESAI' ? 'Rapor telah disetujui' : 'Menunggu proses persetujuan' ?></span>
                <?php endif; ?>
                <a class="ui-button ui-button--outline" href="<?= BASE_PATH ?>/erapor/sesi/<?= (int)$session['id'] ?>/pdf" target="_blank" rel="noopener" data-erapor-pdf><?= !empty($hasOfficialPdf) ? 'Lihat PDF Resmi' : 'Pratinjau PDF' ?></a>
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

    <?php $pageTotal = count($form['documents']); ?>
    <nav class="erapor-steps" aria-label="Bagian rapor" data-erapor-steps>
        <?php foreach ($form['documents'] as $index => $document): ?>
            <?php $stepProgress = $completionByType[$document['jenis_dokumen']] ?? ['filled'=>0, 'required'=>0]; ?>
            <button type="button" class="erapor-step" data-erapor-step="<?= $index ?>"<?= $index === 0 ? ' aria-current="step"' : '' ?>>
                <span class="erapor-step-index" aria-hidden="true"><?= $index + 1 ?></span>
                <span class="erapor-step-text">
                    <span class="erapor-step-name"><?= e($document['nama']) ?></span>
                    <small data-erapor-step-progress="<?= e($document['jenis_dokumen']) ?>"><?= (int)$stepProgress['required'] ? (int)$stepProgress['filled'] . ' / ' . (int)$stepProgress['required'] : 'Opsional' ?></small>
                </span>
            </button>
        <?php endforeach; ?>
    </nav>

    <?php foreach ($form['documents'] as $index => $document): ?>
        <?php
        $type = $document['jenis_dokumen'];
        $rubric = $document['form'];
        $definitions = $rubric['definitions'];
        $values = $rubric['values'];
        $progress = $completionByType[$type] ?? ['filled'=>0, 'required'=>0, 'complete'=>false];
        $optional = (int)$progress['required'] === 0;
        ?>
        <section class="erapor-page" id="bagian-<?= $index + 1 ?>" data-erapor-page="<?= $index ?>" data-erapor-document="<?= (int)$document['id'] ?>" data-erapor-type="<?= e($type) ?>" data-erapor-optional="<?= $optional ? 'true' : 'false' ?>" data-erapor-required="<?= (int)$progress['required'] ?>" data-erapor-filled="<?= (int)$progress['filled'] ?>"<?= $index === 0 ? '' : ' hidden' ?>>
            <header class="erapor-page-intro">
                <div class="erapor-page-intro-top">
                    <div>
                        <?= uiText('Bagian ' . ($index + 1) . ' dari ' . $pageTotal . ($optional ? ' · Opsional' : ''), 'caption-md', ['tone'=>'muted']) ?>
                        <h2><?= e($document['nama']) ?></h2>
                    </div>
                    <span class="teacher-session-document-progress" data-erapor-document-progress><?= $optional ? 'Opsional' : (int)$progress['filled'] . ' / ' . (int)$progress['required'] . ' terisi' ?></span>
                </div>

            <?php if ($type === 'RTS'): ?>
                <?php
                $subareasByArea = [];
                foreach ($definitions['subareas'] as $subarea) $subareasByArea[(int)$subarea['area_id']][] = $subarea;
                $groups = array_column($definitions['groups'], null, 'id');
                $scaleChoices = [];
                foreach ($definitions['scale'] as $scale) $scaleChoices[(string)(int)$scale['nilai']] = $scale;
                $reference = $document['reference'] ?? null;
                ?>
                <p class="erapor-page-help">Pilih satu simbol capaian untuk setiap tujuan. Semua tujuan wajib diisi; pilih <strong>-</strong> bila tujuan belum dikenalkan kepada murid.</p>
                <ul class="erapor-scale-legend" aria-label="Keterangan simbol capaian">
                    <?php foreach ($definitions['scale'] as $scale): ?>
                        <li><?= $scaleIcon($scale) ?><span><?= e($scale['label']) ?></span></li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($reference): ?>
                    <p class="erapor-reference-note"><?= e($reference['label']) ?> ditampilkan di bawah setiap tujuan sebagai pembanding dan tidak dapat diubah.</p>
                <?php endif; ?>
            </header>
                <?php foreach ($definitions['areas'] as $area): ?>
                    <h3 class="pengisian-kategori-header erapor-page-heading"><?= e(mb_convert_case($area['nama'], MB_CASE_TITLE, 'UTF-8')) ?></h3>
                    <?php foreach ($subareasByArea[(int)$area['id']] ?? [] as $subarea): ?>
                        <?php $subItems = array_values(array_filter($definitions['items'], fn($item)=>(int)$item['sub_area_id']===(int)$subarea['id'])); ?>
                        <?php if (!$subarea['implisit']): ?><h4 class="pengisian-subkategori-header erapor-page-heading"><?= e(($subarea['huruf'] ? $subarea['huruf'] . '. ' : '') . $subarea['nama']) ?></h4><?php endif; ?>
                        <?php $lastGroupId = null; ?>
                        <?php foreach ($subItems as $item): ?>
                            <?php
                            $value = $values['nilai:' . $item['id']] ?? null;
                            $groupId = $item['grup_id'] === null ? null : (int)$item['grup_id'];
                            if ($groupId !== null && $groupId !== $lastGroupId && isset($groups[$groupId])):
                            ?>
                                <p class="erapor-subheading"><?= e($groups[$groupId]['nama']) ?></p>
                            <?php endif; $lastGroupId = $groupId; ?>
                            <div class="erapor-question erapor-question--scale" data-erapor-question>
                                <div class="erapor-question-text">
                                    <span><?= e($item['tujuan']) ?></span>
                                    <?php if ($reference): ?>
                                        <?php $refGrade = $reference['values']['nilai:' . $item['id']] ?? null; $refScale = $refGrade === null ? null : ($scaleChoices[(string)$refGrade] ?? null); ?>
                                        <small class="erapor-reference" data-erapor-reference><?= e($reference['label']) ?>:
                                            <?php if ($refScale): ?><?= $scaleIcon($refScale) ?> <?= e($refScale['label']) ?><?php else: ?>—<?php endif; ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                                <?= $renderScale($document, $item, $definitions['scale'], $value === null ? null : (int)$value) ?>
                                <p class="erapor-question-error" data-erapor-question-error hidden>Pilih salah satu capaian.</p>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endforeach; ?>

            <?php elseif ($type === 'BING'): ?>
                <?php
                $bingChoices = [];
                foreach ($definitions['scale'] as $grade) $bingChoices[$grade['kode']] = $grade['label'];
                $groupedIndicators = [];
                foreach ($definitions['items'] as $item) $groupedIndicators[$item['grup'] ?: ''][] = $item;
                ?>
                <p class="erapor-page-help">Pilih capaian untuk setiap indikator, lalu lengkapi catatan kemampuan di akhir halaman.</p>
            </header>
                <?php foreach ($groupedIndicators as $groupName => $items): ?>
                    <?php if ($groupName !== ''): ?><h3 class="pengisian-subkategori-header erapor-page-heading"><?= e($groupName) ?></h3><?php endif; ?>
                    <?php foreach ($items as $item): ?>
                        <div class="erapor-question erapor-question--select" data-erapor-question>
                            <span class="erapor-question-text"><?= e($item['penanda_cetak'] ? $item['penanda_cetak'] . ' ' : '') . e($item['label_cetak']) ?></span>
                            <?= $renderSelect($document, 'BING', 'nilai:' . $item['id'], $item['label_cetak'], $bingChoices, $values['nilai:' . $item['id']] ?? null, [], (bool)$item['wajib']) ?>
                            <p class="erapor-question-error" data-erapor-question-error hidden>Pilih salah satu capaian.</p>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                <h3 class="pengisian-subkategori-header erapor-page-heading">Catatan kemampuan</h3>
                <?php foreach ($definitions['comments'] as $comment): ?>
                    <div class="erapor-question" data-erapor-question>
                        <?= $renderText($document, 'BING', 'komentar:' . $comment['id'], $comment['label_cetak'], $values['komentar:' . $comment['id']] ?? null, false, (bool)$comment['wajib']) ?>
                        <p class="erapor-question-error" data-erapor-question-error hidden>Catatan ini wajib diisi.</p>
                    </div>
                <?php endforeach; ?>

            <?php elseif ($type === 'PPI'): ?>
                <?php
                $columnsByBagian = [];
                foreach ($definitions['columns'] as $column) $columnsByBagian[$column['bagian']][] = $column;
                ?>
                <p class="erapor-page-help">Isi setiap kolom Program Pembelajaran Individual untuk tiap aspek perkembangan.</p>
            </header>
                <?php foreach ($definitions['aspects'] as $aspect): ?>
                    <div class="erapor-question erapor-question--group" data-erapor-question>
                        <h3 class="erapor-question-title"><?= e($aspect['nama']) ?></h3>
                        <div class="erapor-ppi-fields">
                            <?php foreach ($columnsByBagian as $columns): foreach ($columns as $column): ?>
                                <?php $key = $aspect['id'] . ':' . $column['id']; ?>
                                <?= $renderText($document, 'PPI', $key, $column['label_cetak'], $values[$key] ?? null, false, (bool)$column['wajib']) ?>
                            <?php endforeach; endforeach; ?>
                        </div>
                        <p class="erapor-question-error" data-erapor-question-error hidden>Lengkapi semua kolom wajib pada aspek ini.</p>
                    </div>
                <?php endforeach; ?>

            <?php elseif ($type === 'AGAMA'): ?>
                <?php
                $subscopesByScope = [];
                foreach ($definitions['subscopes'] as $subscope) $subscopesByScope[(int)$subscope['lingkup_id']][] = $subscope;
                $agamaChoices = [];
                foreach ($definitions['scale'] as $grade) $agamaChoices[$grade['kolom_cetak']] = $grade['label'];
                ?>
                <p class="erapor-page-help">Pilih tahapan capaian untuk setiap butir. Semua butir dan catatan lingkup wajib diisi.</p>
            </header>
                <?php foreach ($definitions['scopes'] as $scope): ?>
                    <?php
                    $scopeSubscopes = $subscopesByScope[(int)$scope['id']] ?? [];
                    $scopeItems = [];
                    foreach ($definitions['items'] as $item) {
                        foreach ($scopeSubscopes as $subscope) if ((int)$item['sub_id'] === (int)$subscope['id']) $scopeItems[] = $item;
                    }
                    ?>
                    <h3 class="pengisian-kategori-header erapor-page-heading"><?= e($scope['nomor_romawi'] . '. ' . $scope['nama']) ?></h3>
                    <?php foreach ($scopeSubscopes as $subscope): ?>
                        <?php $items = array_values(array_filter($scopeItems, fn($item)=>(int)$item['sub_id']===(int)$subscope['id'])); ?>
                        <?php if (!$subscope['implisit']): ?><h4 class="pengisian-subkategori-header erapor-page-heading"><?= e(($subscope['huruf'] ? $subscope['huruf'] . '. ' : '') . $subscope['nama']) ?></h4><?php endif; ?>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $itemNames = array_values(array_filter($definitions['names'], fn($name)=>(int)$name['item_id']===(int)$item['id']));
                            $label = ($item['nomor'] ? $item['nomor'] . '. ' : '') . $item['teks'];
                            ?>
                            <div class="erapor-question erapor-question--select" data-erapor-question>
                                <span class="erapor-question-text"><?= e($label) ?><?php if ($itemNames): ?><small><?= e(implode(' · ', array_column($itemNames, 'nama'))) ?></small><?php endif; ?></span>
                                <?= $renderSelect($document, 'AGAMA', 'nilai:' . $item['id'], $label, $agamaChoices, $values['nilai:' . $item['id']] ?? null) ?>
                                <p class="erapor-question-error" data-erapor-question-error hidden>Pilih salah satu tahapan.</p>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    <?php if (!empty($scope['catatan_wajib'])): ?>
                        <div class="erapor-question" data-erapor-question>
                            <?= $renderText($document, 'AGAMA', 'catatan:' . $scope['id'], 'Catatan ' . $scope['nama'], $values['catatan:' . $scope['id']] ?? null, false, (bool)$scope['catatan_wajib']) ?>
                            <p class="erapor-question-error" data-erapor-question-error hidden>Catatan ini wajib diisi.</p>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>

            <?php elseif ($type === 'UMMI'): ?>
                <p class="erapor-page-help">Rapor Ummi bersifat <strong>opsional</strong>. Rapor tetap dapat diselesaikan walaupun bagian ini dikosongkan.</p>
            </header>
                <?php if (!empty($definitions['initialization_required'])): ?>
                    <div class="erapor-question erapor-ummi-init" role="status">
                        <h3 class="erapor-question-title">Mulai pengisian Rapor Ummi</h3>
                        <p>Halaman ini belum memiliki setelan periode. Menekan tombol mulai akan menyimpan setelan PRA TK awal untuk periode ini. Membuka halaman saja tidak mengubah data.</p>
                        <?php if ($canEdit): ?>
                            <?= uiButton('Mulai pengisian Ummi', 'primary', ['marginVertical'=>0, 'attributes'=>['data-erapor-ummi-init'=>true, 'data-erapor-document-id'=>(int)$document['id']]]) ?>
                        <?php else: ?>
                            <p>Setelan periode belum dibuat dan sesi ini hanya dapat dilihat.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php
                    $ummiChoices = [];
                    foreach ($definitions['scale'] as $grade) $ummiChoices[$grade['kode']] = $grade['label'];
                    $itemsByVolume = [];
                    foreach ($definitions['items'] as $item) $itemsByVolume[(int)$item['jilid_id']][] = $item;
                    $existingTests = [];
                    foreach ($values as $valueKey => $value) if (str_starts_with($valueKey, 'tes:') && is_array($value)) $existingTests[$valueKey] = $value;
                    $filledReadings = 0;
                    foreach ($definitions['items'] as $item) if (!empty($values['bacaan:' . $item['id']])) $filledReadings++;
                    $praId = null;
                    foreach ($definitions['volumes'] as $volume) if (!empty($volume['hanya_pra_tk'])) $praId = (int)$volume['id'];
                    ?>
                    <section class="erapor-question erapor-field-group erapor-ummi-controls">
                        <?= uiCheckbox('erapor_ummi_pra_' . (int)$document['id'], 'Murid memulai dari PRA TK', (bool)$values['mulai_pra_tk'], [
                            'disabled'=>!$canEdit,
                            'inputAttributes'=>[
                                'data-erapor-entry'=>'UMMI',
                                'data-erapor-document-id'=>(int)$document['id'],
                                'data-erapor-key'=>'mulai_pra_tk',
                                'data-saved-value'=>$values['mulai_pra_tk'] ? 'true' : 'false',
                                'data-erapor-pra-toggle'=>'true',
                            ],
                        ]) ?>
                        <p class="erapor-ummi-note">Bagian A bersifat informasi; materi yang belum dinilai boleh tetap kosong.</p>
                        <p class="erapor-ummi-reading-count" data-ummi-reading-count data-filled="<?= (int)$filledReadings ?>" data-total="<?= count($definitions['items']) ?>">Terisi <?= (int)$filledReadings ?> dari <?= count($definitions['items']) ?> materi</p>
                    </section>

                    <section class="erapor-ummi-volumes" aria-label="Bacaan jilid">
                        <?php foreach ($definitions['volumes'] as $volume): ?>
                            <?php $isPra = !empty($volume['hanya_pra_tk']); ?>
                            <?php if ($isPra && $praId === null) continue; ?>
                            <details class="erapor-ummi-volume ui-disclosure" data-ummi-volume="<?= (int)$volume['id'] ?>" data-ummi-pra-tk="<?= $isPra ? 'true' : 'false' ?>"<?= $isPra && !$values['mulai_pra_tk'] ? ' hidden' : '' ?>>
                                <summary class="pengisian-subkategori-header ui-disclosure-trigger"><span>Jilid <?= e($volume['nama']) ?></span><span class="ui-disclosure-chevron" aria-hidden="true"><?= icon('icon_chevron') ?></span></summary>
                                <div class="erapor-ummi-volume-items">
                                    <?php foreach ($itemsByVolume[(int)$volume['id']] ?? [] as $item): ?>
                                        <?= $renderSelect($document, 'UMMI', 'bacaan:' . $item['id'], $item['teks'], $ummiChoices, $values['bacaan:' . $item['id']] ?? null, [], false, ['data-ummi-reading'=>'true']) ?>
                                    <?php endforeach; ?>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    </section>

                    <section class="erapor-question erapor-field-group erapor-ummi-tests" data-ummi-tests>
                        <div class="erapor-ummi-tests-heading">
                            <div>
                                <h3>Nilai Tes Kenaikan Jilid</h3>
                                <p>Tes bersifat opsional. Tambahkan baris hanya jika ada tes yang perlu dicatat.</p>
                            </div>
                            <?php if ($canEdit): ?>
                                <?= uiButton('Tambah Tes', 'outline', ['marginVertical'=>0, 'attributes'=>['data-erapor-add-test'=>(int)$document['id']]]) ?>
                            <?php endif; ?>
                        </div>
                        <div class="erapor-ummi-test-list" data-ummi-test-list>
                            <?php foreach ($existingTests as $key => $test): ?>
                                <?php $testToken = substr($key, 4); $savedTest = json_encode($test, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>
                                <div class="erapor-ummi-test-row" data-erapor-entry="UMMI_TEST" data-erapor-document-id="<?= (int)$document['id'] ?>" data-erapor-key="<?= e($key) ?>" data-saved-value="<?= e($savedTest) ?>">
                                    <?= uiField('erapor_ummi_test_order_' . $testToken, 'Urutan', ['variant'=>'form','font'=>'base','type'=>'number','value'=>(string)$test['urutan'],'inputAttributes'=>['min'=>1,'max'=>2147483647,'step'=>1,'data-ummi-test-field'=>'urutan']]) ?>
                                    <?= uiField('erapor_ummi_test_date_' . $testToken, 'Tanggal tes', ['variant'=>'form','font'=>'base','icon'=>'icon_calendar','iconCalendar'=>true,'type'=>'date','value'=>$test['tanggal_tes'],'inputAttributes'=>['min'=>'1000-01-01','data-ummi-test-field'=>'tanggal_tes']]) ?>
                                    <?= uiField('erapor_ummi_test_volume_' . $testToken, 'Jilid yang diteskan', ['variant'=>'form','font'=>'base','value'=>$test['jilid'],'placeholder'=>'Contoh: Jilid I','inputAttributes'=>['maxlength'=>150,'data-ummi-test-field'=>'jilid']]) ?>
                                    <?= uiSelect('erapor_ummi_test_grade_' . $testToken, 'Nilai tes', [''=>'Pilih nilai'] + $ummiChoices, ['font'=>'base','value'=>$test['nilai'],'attributes'=>['data-ummi-test-field'=>'nilai']]) ?>
                                    <span class="erapor-ummi-test-status" data-ummi-test-status aria-live="polite"></span>
                                    <?php if ($canEdit): ?><?= uiButton('Hapus', 'outline-danger', ['marginVertical'=>0,'attributes'=>['data-erapor-remove-test'=>true]]) ?><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (!$existingTests && !$canEdit): ?><p>Tidak ada tes yang dicatat pada periode ini.</p><?php endif; ?>
                    </section>

                    <template data-ummi-test-template data-ummi-document-id="<?= (int)$document['id'] ?>">
                        <div class="erapor-ummi-test-row" data-erapor-entry="UMMI_TEST" data-erapor-document-id="<?= (int)$document['id'] ?>" data-erapor-key="" data-saved-value="" data-erapor-incomplete="true">
                            <?= uiField('erapor_ummi_test_order___TOKEN__', 'Urutan', ['variant'=>'form','font'=>'base','type'=>'number','placeholder'=>'1','inputAttributes'=>['min'=>1,'max'=>2147483647,'step'=>1,'data-ummi-test-field'=>'urutan']]) ?>
                            <?= uiField('erapor_ummi_test_date___TOKEN__', 'Tanggal tes', ['variant'=>'form','font'=>'base','icon'=>'icon_calendar','iconCalendar'=>true,'type'=>'date','inputAttributes'=>['min'=>'1000-01-01','data-ummi-test-field'=>'tanggal_tes']]) ?>
                            <?= uiField('erapor_ummi_test_volume___TOKEN__', 'Jilid yang diteskan', ['variant'=>'form','font'=>'base','placeholder'=>'Contoh: Jilid I','inputAttributes'=>['maxlength'=>150,'data-ummi-test-field'=>'jilid']]) ?>
                            <?= uiSelect('erapor_ummi_test_grade___TOKEN__', 'Nilai tes', [''=>'Pilih nilai'] + $ummiChoices, ['font'=>'base','id'=>'erapor_ummi_test_grade___TOKEN__','attributes'=>['data-ummi-test-field'=>'nilai']]) ?>
                            <span class="erapor-ummi-test-status" data-ummi-test-status aria-live="polite">Lengkapi semua kolom untuk menyimpan.</span>
                            <?= uiButton('Hapus', 'outline-danger', ['marginVertical'=>0,'attributes'=>['data-erapor-remove-test'=>true]]) ?>
                        </div>
                    </template>

                    <section class="erapor-question erapor-field-group erapor-ummi-teacher-note">
                        <h3>Catatan Guru</h3>
                        <?= $renderText($document, 'UMMI', 'catatan', 'Catatan Guru', $values['catatan'] ?? null, false, false) ?>
                    </section>
                <?php endif; ?>
            <?php else: ?>
            </header>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>

    <nav class="erapor-pager" aria-label="Navigasi bagian rapor" data-erapor-pager>
        <?= uiButton('Sebelumnya', 'outline', ['marginVertical'=>0, 'attributes'=>['data-erapor-prev'=>true]]) ?>
        <span class="erapor-pager-label" data-erapor-pager-label>Bagian 1 dari <?= $pageTotal ?></span>
        <?= uiButton('Berikutnya', 'primary', ['marginVertical'=>0, 'attributes'=>['data-erapor-next'=>true]]) ?>
        <?php if ($canSend && $session['status'] === 'BELUM_DIISI'): ?>
            <?= uiButton('Selesaikan Rapor', 'primary', ['marginVertical'=>0, 'attributes'=>['data-erapor-confirm'=>true, 'data-erapor-pager-submit'=>true]]) ?>
        <?php elseif ($canSend && $session['status'] === 'TELAH_DIISI'): ?>
            <?= uiButton('Konfirmasi Penerimaan', 'primary', ['marginVertical'=>0, 'attributes'=>['data-erapor-confirm-reception'=>true, 'data-erapor-pager-submit'=>true]]) ?>
        <?php endif; ?>
    </nav>

    <?php // Konfirmasi memakai modal aplikasi (sama dengan halaman persetujuan), bukan window.confirm() bawaan browser. ?>
    <?= uiModal('erapor-confirm-modal', 'Konfirmasi', '<p class="ui-modal-description" data-erapor-confirm-message></p><div class="modal-body"><div class="modal-actions">'
        . uiButton('Batal', 'outline', ['marginVertical'=>0, 'attributes'=>['data-erapor-confirm-cancel'=>true]])
        . uiButton('Lanjutkan', 'primary', ['marginVertical'=>0, 'attributes'=>['data-erapor-confirm-accept'=>true]])
        . '</div></div>') ?>

    <?php // Harus di dalam [data-erapor-editor]: erapor-session.js mencarinya lewat root.querySelector(). ?>
    <script type="application/json" data-erapor-initial-state><?= json_encode([
        'completion' => $form['completion'],
        'capabilities' => $form['capabilities'],
        'session' => $session,
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?></script>
</main>

<script src="<?= BASE_PATH ?>/assets/js/ui-select.js?v=<?= filemtime(ROOT_PATH . '/public/assets/js/ui-select.js') ?>"></script>
<script src="<?= BASE_PATH ?>/assets/js/ui-datepicker.js?v=<?= filemtime(ROOT_PATH . '/public/assets/js/ui-datepicker.js') ?>"></script>
<script src="<?= BASE_PATH ?>/assets/js/erapor-session.js?v=<?= filemtime(ROOT_PATH . '/public/assets/js/erapor-session.js') ?>"></script>
<?php require VIEW_PATH . '/layouts/focus-footer.php'; ?>
