<?php
$previewPages = $previewPages ?? require __DIR__ . '/_preview-pages-data.php';
$semesterLabel = $rapor['periode_tipe'] ?? (str_contains(mb_strtolower($template['nama'] ?? ''), 'akhir') ? 'Akhir Semester' : 'Tengah Semester');
$documentTitle = 'Laporan Perkembangan ' . $semesterLabel;
$yearLabel = isset($rapor['tahun_awal']) ? $rapor['tahun_awal'] . '/' . $rapor['tahun_akhir'] : '2024/2025';
$previewLegend = [
    'slash' => 'Baru dikenalkan', 'triangle-sm' => 'Mulai Berkembang',
    'triangle-lg' => 'Berkembang Sesuai Harapan', 'triangle-full' => 'Berkembang Sangat Baik',
];
?>
<section class="template-preview" tabindex="0" role="region" aria-label="Pratinjau rapor. Geser horizontal untuk melihat halaman berikutnya.">
    <?php foreach ($previewPages as $pageIndex => $previewPage): ?>
    <section class="template-preview-item" aria-labelledby="preview-page-<?= $pageIndex + 1 ?>">
        <div class="template-preview-label" id="preview-page-<?= $pageIndex + 1 ?>">Halaman <?= $pageIndex + 1 ?> dari <?= count($previewPages) ?></div>
        <article class="template-paper">
            <img class="template-paper-watermark" src="<?= e(assetSrc('images/logo-icon.png')) ?>" alt="">
            <div class="template-paper-heading">
                <header class="template-paper-brand">
                    <div><h2><?= e(mb_strtoupper($documentTitle)) ?></h2><p>T.A <?= e($yearLabel) ?></p></div>
                    <img src="<?= e(assetSrc('images/logo-colored.png')) ?>" alt="Zivana Montessori">
                </header>
                <div class="template-paper-identity">
                    <div><span>Nama Siswa</span><b>:</b><strong><?= e($rapor['nama_lengkap'] ?? '{Nama Siswa}') ?></strong></div>
                    <div><span>Kelas</span><b>:</b><strong><?= e(isset($rapor) ? (trim(($rapor['level_kelas'] ?? '') . ' ' . ($rapor['nama_kelas'] ?? '')) ?: '-') : '{Kelas Siswa}') ?></strong></div>
                    <div><span>NISN</span><b>:</b><strong><?= e(isset($rapor) ? ($rapor['nisn'] ?: '-') : '{NISN Siswa}') ?></strong></div>
                </div>
                <?php if ($previewPage['legend']): ?>
                <div class="template-paper-legend"><b>Keterangan :</b>
                    <div class="template-paper-symbols">
                        <?php foreach ($previewLegend as $symbol => $label): ?>
                        <div>
                            <?php if ($symbol === 'triangle-sm'): ?>
                                <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><polyline points="9,1 1,15 16,15" fill="none" stroke="currentColor" stroke-width=".7"/></svg>
                            <?php else: ?>
                                <?= renderSkalaSimbol($symbol) ?>
                            <?php endif; ?>
                            <span><?= e($label) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div class="template-paper-columns <?= count($previewPage['columns']) > 1 ? 'template-paper-columns--two' : '' ?>">
                <?php foreach ($previewPage['columns'] as $column): ?>
                <div>
                    <?php foreach ($column as $section): $hasApparatus = $section['apparatus'] ?? false; ?>
                    <table class="template-paper-table <?= $hasApparatus ? 'template-paper-table--apparatus' : '' ?>">
                        <colgroup><col><?php if ($hasApparatus): ?><col><?php endif; ?><col class="template-paper-score"><col class="template-paper-score"></colgroup>
                        <thead>
                            <tr><th colspan="<?= $hasApparatus ? 4 : 3 ?>" class="template-paper-area"><?= e($section['title']) ?></th></tr>
                            <tr><th scope="col">Tujuan</th><?php if ($hasApparatus): ?><th scope="col">Aparatus/Media Pendukung</th><?php endif; ?><th scope="col">TS Ganjil</th><th scope="col">TS Genap</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($section['groups'] as $group): ?>
                            <?php if ($group['name'] !== ''): ?><tr><th colspan="<?= $hasApparatus ? 4 : 3 ?>" scope="rowgroup"><?= e($group['name']) ?></th></tr><?php endif; ?>
                            <?php foreach ($group['items'] as $item): ?>
                            <tr><td><?= e(is_array($item) ? $item['nama_tujuan'] : $item) ?></td><?php if ($hasApparatus): ?><td><?= e(is_array($item) ? $item['nama_tujuan'] : $item) ?></td><?php endif; ?><td><?= is_array($item) && !empty($item['nilai_ganjil']) ? renderSkalaSimbol($item['nilai_ganjil']) : '' ?></td><td><?= is_array($item) && !empty($item['nilai_genap']) ? renderSkalaSimbol($item['nilai_genap']) : '' ?></td></tr>
                            <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <footer class="template-paper-footer"><?= e($documentTitle) ?> T.P <?= e($yearLabel) ?></footer>
        </article>
    </section>
    <?php endforeach; ?>
</section>
