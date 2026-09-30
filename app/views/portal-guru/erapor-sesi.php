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
    // Nilai huruf Ummi (maks. 2 karakter) memakai pilihan kecil dengan placeholder "-".
    $placeholder = $type === 'UMMI' ? '-' : 'Pilih jawaban Anda';
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
        ['' => $placeholder] + $choices, [
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
$renderText = static function (array $document, string $type, string $key, string $label, ?string $value, bool $disabled = false, bool $required = true, bool $hideLabel = false) use ($canEdit): string {
    return uiField('erapor_' . (int)$document['id'] . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $key), $label, [
        'type' => 'textarea', 'variant' => 'form',
        'id' => 'erapor-field-' . (int)$document['id'] . '-' . substr(hash('sha256', $key), 0, 12),
        'value' => $value ?? '', 'placeholder' => 'Tulis ' . mb_strtolower($label, 'UTF-8'),
        'disabled' => $disabled || !$canEdit,
        'hideLabel' => $hideLabel,
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
    <?php
    // Bagikan ke Orang Tua: satu kontak = tombol langsung membuka WhatsApp ke nomor itu; dua kontak = dropdown Ayah/Ibu;
    // tanpa kontak = nonaktif. Klik dicatat (Dibagikan ke Orang Tua) oleh erapor-session.js.
    $renderShare = static function (bool $iconOnly) use ($share, $session): string {
        if (empty($share)) return '';
        $label = 'Bagikan ke Orang Tua';
        $content = '<span class="ui-button-icon" aria-hidden="true">' . icon('icon_share') . '</span>' . ($iconOnly ? '' : '<span class="ui-button-label">' . e($label) . '</span>');
        $class = 'ui-button ui-button--primary' . ($iconOnly ? ' ui-button--icon-only' : '');
        $contacts = $share['contacts'];
        if (!$contacts) {
            return '<span class="erapor-share-disabled" title="Kontak orang tua belum tersedia di data murid">'
                . uiButton($label, 'disabled', ['icon'=>'icon_share', 'iconOnly'=>$iconOnly, 'marginVertical'=>0, 'disabled'=>true, 'attributes'=>['aria-describedby'=>'erapor-share-hint']]) . '</span>';
        }
        if (count($contacts) === 1) {
            $code = array_key_first($contacts); $contact = $contacts[$code];
            return '<a class="' . $class . '" href="' . e($contact['wa_url']) . '" target="_blank" rel="noopener" data-erapor-share="' . e($code) . '"'
                . ' title="' . e($label . ' (' . $contact['label'] . ')') . '" aria-label="' . e($label . ' (' . $contact['label'] . ')') . '">' . $content . '</a>';
        }
        $items = '';
        foreach ($contacts as $code => $contact) {
            $items .= '<a href="' . e($contact['wa_url']) . '" target="_blank" rel="noopener" data-erapor-share="' . e($code) . '">'
                . '<strong>' . e($contact['label']) . '</strong><small>+' . e($contact['phone']) . '</small></a>';
        }
        return '<div class="action-menu erapor-share-menu" data-action-menu>'
            . '<button type="button" class="' . $class . '" data-action-menu-toggle aria-haspopup="menu" title="' . e($label) . '" aria-label="' . e($label) . '">' . $content
            . ($iconOnly ? '' : '<span class="ui-button-icon erapor-share-caret" aria-hidden="true">' . icon('icon_chevron') . '</span>') . '</button>'
            . '<div class="action-menu-dropdown erapor-share-dropdown" role="menu">' . $items . '</div></div>';
    };
    ?>
    <?php // Tombol kembali: di luar kartu judul pada desktop, di dalam kartu pada mobile. ?>
    <a class="ui-button ui-button--outline ui-button--icon-only pengisian-back pengisian-back--outside" href="<?= BASE_PATH ?>/portal-guru/dashboard" aria-label="Kembali ke Dashboard" title="Kembali ke Dashboard"><span class="ui-button-icon" aria-hidden="true"><?= icon('icon_chevron') ?></span></a>
    <section class="pengisian-header-card">
        <?php
        $pdfUrl = BASE_PATH . '/erapor/sesi/' . (int)$session['id'] . '/pdf';
        $pdfLabel = !empty($hasOfficialPdf) ? 'Lihat PDF Resmi' : 'Pratinjau PDF';
        ?>
        <a class="ui-button ui-button--outline ui-button--icon-only pengisian-back pengisian-back--inside" href="<?= BASE_PATH ?>/portal-guru/dashboard" aria-label="Kembali ke Dashboard" title="Kembali ke Dashboard"><span class="ui-button-icon" aria-hidden="true"><?= icon('icon_chevron') ?></span></a>
        <div class="pengisian-header-top">
            <div class="pengisian-title">
                <h1>Pengisian Rapor</h1>
                <p class="pengisian-student-name"><?= e($student['nama_lengkap']) ?></p>
                <?php if ($session['status'] === 'BELUM_DIISI'): ?>
                    <?php // Status draft: diperbarui erapor-session.js setelah Simpan Draft. ?>
                    <p class="pengisian-approval-status" data-erapor-draft-status<?= empty($draftAt) ? ' hidden' : '' ?>><?= uiBadge('Draft', 'netral') ?> <span class="erapor-draft-time" data-erapor-draft-time><?= !empty($draftAt) ? e('Tersimpan ' . date('d/m/Y H:i', strtotime($draftAt))) : '' ?></span></p>
                <?php endif; ?>
                <?php if (in_array($session['status'], ['MENUNGGU_TTD', 'SELESAI'], true)): ?>
                    <?php $allApproved = !empty($form['approvals']) && !array_filter($form['approvals'], fn($a) => $a['status'] !== 'DISETUJUI'); ?>
                    <p class="pengisian-approval-status" data-erapor-share-status><?= !empty($share['shared_at']) ? uiBadge('Dibagikan ke Orang Tua', 'positif') : ($session['status'] === 'SELESAI' ? uiBadge('Rapor telah terbit', 'positif') : ($allApproved ? uiBadge('Rapor telah disetujui', 'positif') : uiBadge('Menunggu proses persetujuan', 'peringatan'))) ?></p>
                    <?php if (!empty($share['shared_at'])): ?><p class="erapor-share-hint">Dibagikan ke <?= e($share['shared_to'] === 'AYAH' ? 'Ayah' : 'Ibu') ?> · <?= e(date('d/m/Y H:i', strtotime($share['shared_at']))) ?> · diunduh <?= (int)$share['downloads'] ?> kali · tautan berlaku sampai <?= e(date('d/m/Y', strtotime($share['expires_at']))) ?></p><?php endif; ?>
                    <?php if (!empty($share) && !$share['contacts']): ?><p class="erapor-share-hint" id="erapor-share-hint">Kontak orang tua belum tersedia. Lengkapi nomor HP ayah/ibu di data murid untuk membagikan rapor.</p><?php endif; ?>
                <?php endif; ?>
            </div>
            <?php // Mobile: aksi header diringkas jadi tombol ikon; navigasi & Selesaikan ada di bar bawah (pager). ?>
            <div class="pengisian-header-icons">
                <?= uiButton($pdfLabel, 'outline', ['icon'=>'icon_file_text', 'iconOnly'=>true, 'marginVertical'=>0, 'attributes'=>['title'=>$pdfLabel, 'data-erapor-open-url'=>$pdfUrl]]) ?>
                <?php if (!empty($form['approvals'])): ?><?= uiButton('Alur Persetujuan', 'outline', ['icon'=>'icon_clipboard_check', 'iconOnly'=>true, 'marginVertical'=>0, 'attributes'=>['title'=>'Alur Persetujuan', 'data-modal-open'=>'erapor-approval-modal']]) ?><?php endif; ?>
                <?php if ($canEdit && $session['status'] === 'BELUM_DIISI'): ?><?= uiButton('Simpan Draft', 'outline', ['icon'=>'icon_save', 'iconOnly'=>true, 'marginVertical'=>0, 'attributes'=>['title'=>'Simpan Draft', 'data-erapor-draft'=>true]]) ?><?php endif; ?>
                <?= $renderShare(true) ?>
            </div>
            <div class="pengisian-header-actions">
                <a class="ui-button ui-button--outline" href="<?= e($pdfUrl) ?>" target="_blank" rel="noopener" data-erapor-pdf><?= e($pdfLabel) ?></a>
                <?php if (!empty($form['approvals'])): ?><?= uiButton('Alur Persetujuan', 'outline', ['icon'=>'icon_clipboard_check', 'iconOnly'=>true, 'marginVertical'=>0, 'attributes'=>['title'=>'Alur Persetujuan', 'data-modal-open'=>'erapor-approval-modal']]) ?><?php endif; ?>
                <?= $renderShare(false) ?>
                <?php if ($canEdit && $session['status'] === 'BELUM_DIISI'): ?>
                    <?= uiButton('Simpan Draft', 'outline', ['icon'=>'icon_save', 'marginVertical'=>0, 'attributes'=>['data-erapor-draft'=>true]]) ?>
                <?php endif; ?>
                <?php if ($canSend && $session['status'] === 'BELUM_DIISI'): ?>
                    <?php // Aktif setelah semua rapor wajib lengkap (diatur erapor-session.js dari data server). ?>
                    <?= uiButton('Selesaikan Rapor', 'primary', ['marginVertical'=>0, 'attributes'=>['data-erapor-confirm'=>true]]) ?>
                <?php elseif ($canSend && $session['status'] === 'TELAH_DIISI'): ?>
                    <?= uiButton('Konfirmasi Penerimaan', 'primary', ['marginVertical'=>0, 'attributes'=>['data-erapor-confirm-reception'=>true]]) ?>
                <?php endif; ?>
            </div>
        </div>
        <p class="pengisian-rapor-warning">
            <?= e($period['nama']) ?>
            <?php if ($kelasLabel !== ''): ?> · <?= e($kelasLabel) ?><?php endif; ?>
            · Status <?= e(str_replace('_', ' ', $session['status'])) ?>
        </p>
        <?php if (!$canEdit): ?>
            <p class="erapor-session-notice" role="status">
                <?= e($readonlyMessages[$form['capabilities']['read_only_reason'] ?? ''] ?? 'Sesi ini hanya dapat dilihat.') ?>
            </p>
        <?php endif; ?>
        <div class="pengisian-rapor-progress" aria-label="Progress isian rapor">
            <?= uiProgress($filled, $required, ['label'=>'Progress isian rapor', 'fillAttributes'=>['data-erapor-overall-bar'=>true]]) ?>
            <span data-erapor-overall-count><?= (int)$filled ?> dari <?= (int)$required ?></span>
        </div>
        <p class="erapor-save-status" data-erapor-status role="status" aria-live="polite">Semua perubahan tersimpan.</p>
        <div class="erapor-save-actions" data-erapor-recovery hidden>
            <?= uiButton('Coba simpan lagi', 'outline', ['marginVertical'=>0, 'attributes'=>['data-erapor-retry'=>true]]) ?>
            <?= uiButton('Muat ulang sesi', 'outline', ['marginVertical'=>0, 'attributes'=>['data-erapor-reload'=>true]]) ?>
        </div>
    </section>

    <?php $pageTotal = count($form['documents']); ?>
    <div class="erapor-filter" role="group" aria-label="Filter isian">
        <span class="erapor-filter-label">Tampilkan:</span>
        <?= uiButton('Semua', 'tabular-active', ['marginVertical'=>0, 'attributes'=>['data-erapor-filter'=>'all']]) ?>
        <?= uiButton('Belum Diisi', 'tabular-inactive', ['marginVertical'=>0, 'attributes'=>['data-erapor-filter'=>'empty']]) ?>
    </div>
    <nav class="erapor-steps" aria-label="Bagian rapor" data-erapor-steps>
        <?php foreach ($form['documents'] as $index => $document): ?>
            <?php $stepProgress = $completionByType[$document['jenis_dokumen']] ?? ['filled'=>0, 'required'=>0]; ?>
            <button type="button" class="erapor-step" data-erapor-step="<?= $index ?>"<?= $index === 0 ? ' aria-current="step"' : '' ?>>
                <span class="erapor-step-index" aria-hidden="true"><?= $index + 1 ?></span>
                <span class="erapor-step-text">
                    <span class="erapor-step-name"><?= e(['RTS' => 'RTS', 'RAS' => 'RAS', 'AGAMA' => 'Rapor PAI', 'UMMI' => 'Rapor Ummi', 'BING' => 'Rapor BING', 'PPI' => 'Rapor PPI'][$document['jenis_dokumen']] ?? $document['nama']) ?></span>
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
                    <details class="ui-disclosure erapor-section"><summary class="pengisian-kategori-header erapor-page-heading ui-disclosure-trigger"><span><?= e(mb_convert_case($area['nama'], MB_CASE_TITLE, 'UTF-8')) ?></span><span class="ui-disclosure-chevron" aria-hidden="true"><?= icon('icon_chevron') ?></span></summary><div class="erapor-section-body">
                    <?php foreach ($subareasByArea[(int)$area['id']] ?? [] as $subarea): ?>
                        <?php $subItems = array_values(array_filter($definitions['items'], fn($item)=>(int)$item['sub_area_id']===(int)$subarea['id'])); ?>
                        <?php if (!$subarea['implisit']): ?><details class="ui-disclosure erapor-section"><summary class="pengisian-subkategori-header erapor-page-heading ui-disclosure-trigger"><span><?= e(($subarea['huruf'] ? $subarea['huruf'] . '. ' : '') . $subarea['nama']) ?></span><span class="ui-disclosure-chevron" aria-hidden="true"><?= icon('icon_chevron') ?></span></summary><div class="erapor-section-body"><?php endif; ?>
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
                        <?php if (!$subarea['implisit']): ?></div></details><?php endif; ?>
                    <?php endforeach; ?>
                    </div></details>
                <?php endforeach; ?>

            <?php elseif ($type === 'BING'): ?>
                <?php
                $bingChoices = [];
                foreach ($definitions['scale'] as $grade) $bingChoices[$grade['kode']] = $grade['label'];
                $groupedIndicators = [];
                foreach ($definitions['items'] as $item) $groupedIndicators[$item['grup'] ?: ''][] = $item;
                ?>
                <?php
                // Opsional seperti Ummi: form baru tampil setelah guru menekan "Mulai pengisian" atau bila sudah ada isian.
                $bingStarted = false;
                foreach ($values as $valueKey => $value) if (preg_match('/^(nilai|komentar):/', (string)$valueKey) && $value !== null && trim((string)$value) !== '') $bingStarted = true;
                ?>
                <p class="erapor-page-help">Rapor Bahasa Inggris bersifat <strong>opsional</strong> dan tidak dicetak bila tidak diisi. Bila diisi, pilih capaian untuk setiap indikator lalu lengkapi catatan kemampuan.</p>
            </header>
                <?php if (!$bingStarted): ?>
                    <div class="erapor-question erapor-ummi-init" data-erapor-bing-intro>
                        <h3 class="erapor-question-title">Mulai pengisian Rapor Bahasa Inggris</h3>
                        <?php if ($canEdit): ?>
                            <p>Rapor ini opsional. Tekan tombol di bawah bila murid mengikuti kelas Bahasa Inggris pada periode ini.</p>
                            <?= uiButton('Mulai pengisian Bahasa Inggris', 'primary', ['marginVertical'=>0, 'attributes'=>['data-erapor-bing-start'=>true]]) ?>
                        <?php else: ?>
                            <p>Rapor Bahasa Inggris tidak diisi pada periode ini.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="erapor-page-group" data-erapor-bing-fields<?= $bingStarted ? '' : ' hidden' ?>>
                <?php foreach ($groupedIndicators as $groupName => $items): ?>
                    <?php if ($groupName !== ''): ?><details class="ui-disclosure erapor-section"><summary class="pengisian-subkategori-header erapor-page-heading ui-disclosure-trigger"><span><?= e($groupName) ?></span><span class="ui-disclosure-chevron" aria-hidden="true"><?= icon('icon_chevron') ?></span></summary><div class="erapor-section-body"><?php endif; ?>
                    <?php foreach ($items as $item): ?>
                        <div class="erapor-question erapor-question--select" data-erapor-question>
                            <span class="erapor-question-text"><?= e($item['penanda_cetak'] ? $item['penanda_cetak'] . ' ' : '') . e($item['label_cetak']) ?></span>
                            <?= $renderSelect($document, 'BING', 'nilai:' . $item['id'], $item['label_cetak'], $bingChoices, $values['nilai:' . $item['id']] ?? null, [], (bool)$item['wajib']) ?>
                            <p class="erapor-question-error" data-erapor-question-error hidden>Pilih salah satu capaian.</p>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($groupName !== ''): ?></div></details><?php endif; ?>
                <?php endforeach; ?>
                <details class="ui-disclosure erapor-section"><summary class="pengisian-subkategori-header erapor-page-heading ui-disclosure-trigger"><span>Catatan kemampuan</span><span class="ui-disclosure-chevron" aria-hidden="true"><?= icon('icon_chevron') ?></span></summary><div class="erapor-section-body">
                <?php foreach ($definitions['comments'] as $comment): ?>
                    <div class="erapor-question" data-erapor-question>
                        <?= $renderText($document, 'BING', 'komentar:' . $comment['id'], $comment['label_cetak'], $values['komentar:' . $comment['id']] ?? null, false, (bool)$comment['wajib']) ?>
                        <p class="erapor-question-error" data-erapor-question-error hidden>Catatan ini wajib diisi.</p>
                    </div>
                <?php endforeach; ?>
                </div></details>
                </div>

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
                <p class="erapor-page-help">Pilih tahapan capaian untuk setiap butir, lalu tulis Laporan Perkembangan Agama di akhir halaman. Semuanya wajib diisi.</p>
            </header>
                <?php foreach ($definitions['scopes'] as $scope): ?>
                    <?php
                    $scopeSubscopes = $subscopesByScope[(int)$scope['id']] ?? [];
                    $scopeItems = [];
                    foreach ($definitions['items'] as $item) {
                        foreach ($scopeSubscopes as $subscope) if ((int)$item['sub_id'] === (int)$subscope['id']) $scopeItems[] = $item;
                    }
                    ?>
                    <details class="ui-disclosure erapor-section"><summary class="pengisian-kategori-header erapor-page-heading ui-disclosure-trigger"><span><?= e($scope['nomor_romawi'] . '. ' . $scope['nama']) ?></span><span class="ui-disclosure-chevron" aria-hidden="true"><?= icon('icon_chevron') ?></span></summary><div class="erapor-section-body">
                    <?php foreach ($scopeSubscopes as $subscope): ?>
                        <?php $items = array_values(array_filter($scopeItems, fn($item)=>(int)$item['sub_id']===(int)$subscope['id'])); ?>
                        <?php if (!$subscope['implisit']): ?><details class="ui-disclosure erapor-section"><summary class="pengisian-subkategori-header erapor-page-heading ui-disclosure-trigger"><span><?= e(($subscope['huruf'] ? $subscope['huruf'] . '. ' : '') . $subscope['nama']) ?></span><span class="ui-disclosure-chevron" aria-hidden="true"><?= icon('icon_chevron') ?></span></summary><div class="erapor-section-body"><?php endif; ?>
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
                        <?php if (!$subscope['implisit']): ?></div></details><?php endif; ?>
                    <?php endforeach; ?>
                    </div></details>
                <?php endforeach; ?>
                <?php
                // Satu narasi untuk seluruh lingkup; disimpan pada catatan lingkup wajib pertama (lihat EraporCompleteness).
                $narrativeScope = null;
                foreach ($definitions['scopes'] as $scope) if (!empty($scope['catatan_wajib'])) { $narrativeScope = $scope; break; }
                ?>
                <?php if ($narrativeScope): ?>
                    <details class="ui-disclosure erapor-section"><summary class="pengisian-subkategori-header erapor-page-heading ui-disclosure-trigger"><span>Laporan Perkembangan Agama</span><span class="ui-disclosure-chevron" aria-hidden="true"><?= icon('icon_chevron') ?></span></summary><div class="erapor-section-body">
                        <div class="erapor-question" data-erapor-question>
                            <?= $renderText($document, 'AGAMA', 'catatan:' . $narrativeScope['id'], 'Laporan Perkembangan Agama', $values['catatan:' . $narrativeScope['id']] ?? null, false, true) ?>
                            <p class="erapor-question-error" data-erapor-question-error hidden>Laporan Perkembangan Agama wajib diisi.</p>
                        </div>
                    </div></details>
                <?php endif; ?>

            <?php elseif ($type === 'UMMI'): ?>
                <p class="erapor-page-help">Rapor Ummi bersifat <strong>opsional</strong> dan tidak dicetak bila tidak diisi. Rapor tetap dapat diselesaikan walaupun bagian ini dikosongkan.</p>
                <?php $ummiScale = $definitions['scale'] ?? []; ?>
                <?php if ($ummiScale): ?>
                    <div class="erapor-grade-legend">
                        <p class="erapor-page-help">Keterangan nilai bacaan dan tes: huruf <strong><?= e($ummiScale[0]['kode']) ?></strong> adalah capaian tertinggi, turun berurutan sampai <strong><?= e($ummiScale[count($ummiScale) - 1]['kode']) ?></strong> sebagai capaian terendah.</p>
                        <ul class="erapor-scale-legend" aria-label="Urutan nilai Ummi">
                            <?php foreach ($ummiScale as $i => $grade): ?>
                                <li><strong><?= e($grade['kode']) ?></strong><?php if ($i === 0): ?><span>tertinggi</span><?php elseif ($i === count($ummiScale) - 1): ?><span>terendah</span><?php endif; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
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
                    uasort($existingTests, static fn(array $x, array $y): int => (int)$x['urutan'] <=> (int)$y['urutan']);
                    // Pilihan jilid tes diambil dari judul jilid rubrik; nilai lama di luar daftar tetap dipertahankan.
                    $volumeChoices = [];
                    foreach ($definitions['volumes'] as $volume) $volumeChoices['Jilid ' . $volume['nama']] = 'Jilid ' . $volume['nama'];
                    // Satu baris tes: No. otomatis (teks tebal; urutan tersimpan di input tersembunyi), tanggal, jilid, nilai, hapus.
                    $renderTestRow = static function (string $token, array $test, array $rowAttributes, bool $editable = false) use ($document, $ummiChoices, $volumeChoices, $canEdit): string {
                        $volume = (string)($test['jilid'] ?? '');
                        $choices = ['' => 'Pilih jilid'] + $volumeChoices + ($volume !== '' && !isset($volumeChoices[$volume]) ? [$volume => $volume] : []);
                        return '<div class="erapor-ummi-test-row" data-erapor-entry="UMMI_TEST" data-erapor-document-id="' . (int)$document['id'] . '"' . uiAttrs($rowAttributes) . '>'
                            . '<div class="erapor-ummi-test-no"><span class="field-label font-base">No.</span><strong data-ummi-test-no>' . e((string)($test['no'] ?? '')) . '</strong>'
                            . '<input type="hidden" name="erapor_ummi_test_order_' . e($token) . '" value="' . e((string)($test['urutan'] ?? '')) . '" data-ummi-test-field="urutan"></div>'
                            . uiField('erapor_ummi_test_date_' . $token, 'Tanggal tes', ['variant'=>'form','font'=>'base','icon'=>'icon_calendar','iconCalendar'=>true,'type'=>'date','value'=>(string)($test['tanggal_tes'] ?? ''),'inputAttributes'=>['min'=>'1000-01-01','data-ummi-test-field'=>'tanggal_tes']])
                            . uiSelect('erapor_ummi_test_volume_' . $token, 'Jilid yang diteskan', $choices, ['font'=>'base','id'=>'erapor_ummi_test_volume_' . $token,'value'=>$volume,'attributes'=>['data-ummi-test-field'=>'jilid']])
                            . uiSelect('erapor_ummi_test_grade_' . $token, 'Nilai tes', ['' => 'Pilih nilai'] + $ummiChoices, ['font'=>'base','id'=>'erapor_ummi_test_grade_' . $token,'value'=>(string)($test['nilai'] ?? ''),'attributes'=>['data-ummi-test-field'=>'nilai']])
                            . '<span class="erapor-ummi-test-status" data-ummi-test-status aria-live="polite"></span>'
                            . ($canEdit || $editable ? uiButton('Hapus tes', 'outline-danger', ['icon'=>'icon_trash','iconOnly'=>true,'marginVertical'=>0,'attributes'=>['title'=>'Hapus tes','data-erapor-remove-test'=>true]]) : '')
                            . '</div>';
                    };
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

                    <?php // Seragam dengan RTS: judul jilid (oranye, bisa dilipat) lalu satu kartu per materi: teks di kiri, pilihan nilai kecil di kanan. ?>
                    <?php foreach ($definitions['volumes'] as $volume): ?>
                        <?php $isPra = !empty($volume['hanya_pra_tk']); ?>
                        <?php if ($isPra && $praId === null) continue; ?>
                        <details class="ui-disclosure erapor-section erapor-ummi-volume" data-ummi-volume="<?= (int)$volume['id'] ?>" data-ummi-pra-tk="<?= $isPra ? 'true' : 'false' ?>"<?= $isPra && !$values['mulai_pra_tk'] ? ' hidden' : '' ?>>
                            <summary class="pengisian-subkategori-header erapor-page-heading ui-disclosure-trigger"><span>Jilid <?= e($volume['nama']) ?></span><span class="ui-disclosure-chevron" aria-hidden="true"><?= icon('icon_chevron') ?></span></summary>
                            <div class="erapor-section-body">
                                <?php foreach ($itemsByVolume[(int)$volume['id']] ?? [] as $item): ?>
                                    <div class="erapor-question erapor-question--grade" data-erapor-question>
                                        <span class="erapor-question-text"><?= e($item['teks']) ?></span>
                                        <?= $renderSelect($document, 'UMMI', 'bacaan:' . $item['id'], $item['teks'], $ummiChoices, $values['bacaan:' . $item['id']] ?? null, [], false, ['data-ummi-reading'=>'true']) ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </details>
                    <?php endforeach; ?>

                    <?php // Setara jilid: judul oranye lebih gelap, satu kartu per tes, tombol Tambah Tes di kartu paling bawah. ?>
                    <details class="ui-disclosure erapor-section erapor-ummi-tests" data-ummi-tests>
                        <summary class="pengisian-subkategori-header erapor-page-heading ui-disclosure-trigger erapor-ummi-tests-heading"><span class="erapor-ummi-tests-title">Nilai Tes Kenaikan Jilid <?= uiTooltip('Tes bersifat opsional. Tambahkan baris hanya jika ada tes yang perlu dicatat.') ?></span><span class="ui-disclosure-chevron" aria-hidden="true"><?= icon('icon_chevron') ?></span></summary>
                        <div class="erapor-section-body">
                        <div class="erapor-ummi-test-list" data-ummi-test-list>
                            <?php $testNo = 0; foreach ($existingTests as $key => $test): ?>
                                <?php $savedTest = json_encode($test, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>
                                <?= $renderTestRow(substr($key, 4), $test + ['no' => ++$testNo], ['data-erapor-key' => $key, 'data-saved-value' => $savedTest]) ?>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($canEdit): ?>
                            <button type="button" class="erapor-ummi-test-add" data-erapor-add-test="<?= (int)$document['id'] ?>"><span aria-hidden="true"><?= icon('icon_plus') ?></span>Tambah Tes</button>
                        <?php elseif (!$existingTests): ?>
                            <p class="erapor-question erapor-ummi-test-empty">Tidak ada tes yang dicatat pada periode ini.</p>
                        <?php endif; ?>
                        </div>
                    </details>

                    <template data-ummi-test-template data-ummi-document-id="<?= (int)$document['id'] ?>">
                        <?= $renderTestRow('__TOKEN__', [], ['data-erapor-key' => '', 'data-saved-value' => '', 'data-erapor-incomplete' => 'true'], true) ?>
                    </template>

                    <section class="erapor-question erapor-field-group erapor-ummi-teacher-note" data-erapor-question>
                        <h3>Catatan Guru</h3>
                        <?= $renderText($document, 'UMMI', 'catatan', 'Catatan Guru', $values['catatan'] ?? null, false, false, true) ?>
                    </section>
                <?php endif; ?>
            <?php else: ?>
            </header>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>

    <nav class="erapor-pager" aria-label="Navigasi bagian rapor" data-erapor-pager>
        <p class="erapor-pager-hint" data-erapor-pager-hint hidden></p>
        <?= uiButton('Sebelumnya', 'outline', ['marginVertical'=>0, 'attributes'=>['data-erapor-prev'=>true]]) ?>
        <span class="erapor-pager-label" data-erapor-pager-label>Bagian 1 dari <?= $pageTotal ?></span>
        <?= uiButton('Selanjutnya', 'primary', ['marginVertical'=>0, 'attributes'=>['data-erapor-next'=>true]]) ?>
        <?php if ($canSend && $session['status'] === 'BELUM_DIISI'): ?>
            <?= uiButton('Selesaikan Rapor', 'primary', ['marginVertical'=>0, 'attributes'=>['data-erapor-confirm'=>true, 'data-erapor-pager-submit'=>true]]) ?>
        <?php elseif ($canSend && $session['status'] === 'TELAH_DIISI'): ?>
            <?= uiButton('Konfirmasi Penerimaan', 'primary', ['marginVertical'=>0, 'attributes'=>['data-erapor-confirm-reception'=>true, 'data-erapor-pager-submit'=>true]]) ?>
        <?php endif; ?>
    </nav>

    <?php // Konfirmasi memakai modal aplikasi (sama dengan halaman persetujuan), bukan window.confirm() bawaan browser. ?>
    <?php // Varian konfirmasi standar aplikasi (judul, deskripsi, tombol) dengan jarak antarbagian dari ui-modal--delete. ?>
    <?= uiModal('erapor-confirm-modal', 'Konfirmasi', '<p class="ui-modal-description" data-erapor-confirm-message></p><div class="modal-actions">'
        . uiButton('Batal', 'outline', ['marginVertical'=>0, 'attributes'=>['data-erapor-confirm-cancel'=>true]])
        . uiButton('Lanjutkan', 'primary', ['marginVertical'=>0, 'attributes'=>['data-erapor-confirm-accept'=>true]])
        . '</div>', ['variant'=>'delete']) ?>

    <?php // Harus di dalam [data-erapor-editor]: erapor-session.js mencarinya lewat root.querySelector(). ?>
    <script type="application/json" data-erapor-initial-state><?= json_encode([
        'completion' => $form['completion'],
        'capabilities' => $form['capabilities'],
        'session' => $session,
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?></script>
</main>

<?php if (!empty($form['approvals'])): ?>
    <?php // Alur persetujuan (siapa sudah/belum menyetujui) di modal kecil, dibuka dari tombol ikon di header. ?>
    <?php ob_start(); ?>
            <?php
            $pendingRank = null;
            foreach ($form['approvals'] as $approval) if ($approval['status'] !== 'DISETUJUI') { $pendingRank = $pendingRank === null ? (int)$approval['urutan'] : min($pendingRank, (int)$approval['urutan']); }
            ?>
            <ol class="erapor-approval-track" aria-label="Posisi rapor dalam alur persetujuan">
                <?php foreach ($form['approvals'] as $approval): ?>
                    <?php $state = $approval['status'] === 'DISETUJUI' ? 'done' : ((int)$approval['urutan'] === $pendingRank ? 'current' : 'next'); ?>
                    <li class="erapor-approval-step is-<?= $state ?>">
                        <span class="erapor-approval-dot" aria-hidden="true"><?= $state === 'done' ? '&#10003;' : (int)$approval['urutan'] ?></span>
                        <span>
                            <strong><?= e($approval['label']) ?></strong>
                            <small><?= $state === 'done' ? e('Disetujui ' . ($approval['nama'] ?? '') . ($approval['disetujui_pada'] ? ' · ' . date('d/m/Y', strtotime($approval['disetujui_pada'])) : '')) : ($state === 'current' ? 'Sedang ditinjau' : 'Menunggu tahap sebelumnya') ?></small>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ol>
    <div class="modal-actions"><?= uiButton('Tutup', 'outline', ['marginVertical'=>0, 'attributes'=>['data-modal-close'=>true]]) ?></div>
    <?= uiModal('erapor-approval-modal', 'Alur Persetujuan', ob_get_clean(), ['variant'=>'delete']) ?>
<?php endif; ?>
<?= uiToastRegion() ?>
<script src="<?= BASE_PATH ?>/assets/js/ui-toast.js?v=<?= filemtime(ROOT_PATH . '/public/assets/js/ui-toast.js') ?>"></script>
<script src="<?= BASE_PATH ?>/assets/js/action-menu.js?v=<?= filemtime(ROOT_PATH . '/public/assets/js/action-menu.js') ?>"></script>
<script src="<?= BASE_PATH ?>/assets/js/modal.js?v=<?= filemtime(ROOT_PATH . '/public/assets/js/modal.js') ?>"></script>

<script src="<?= BASE_PATH ?>/assets/js/ui-select.js?v=<?= filemtime(ROOT_PATH . '/public/assets/js/ui-select.js') ?>"></script>
<script src="<?= BASE_PATH ?>/assets/js/ui-datepicker.js?v=<?= filemtime(ROOT_PATH . '/public/assets/js/ui-datepicker.js') ?>"></script>
<script src="<?= BASE_PATH ?>/assets/js/erapor-session.js?v=<?= filemtime(ROOT_PATH . '/public/assets/js/erapor-session.js') ?>"></script>
<?php require VIEW_PATH . '/layouts/focus-footer.php'; ?>
