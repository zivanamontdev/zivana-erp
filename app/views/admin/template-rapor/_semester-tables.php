<div class="report-template-sections">
    <?php foreach (['Rapor Akhir Semester', 'Rapor Tengah Semester'] as $sectionIndex => $sectionTitle): ?>
    <section class="report-template-section" aria-labelledby="report-section-<?= $sectionIndex ?>">
        <?= uiText($sectionTitle, 'body-sm', ['tag' => 'h2', 'weight' => 'regular', 'attributes' => ['id' => 'report-section-' . $sectionIndex]]) ?>
        <div class="data-table-wrapper">
            <table class="data-table report-template-table" aria-labelledby="report-section-<?= $sectionIndex ?>">
                <thead><tr>
                    <th scope="col" class="report-stage">Urutan Tahapan</th>
                    <th scope="col">Template</th>
                    <th scope="col" class="report-updated">Terakhir Diperbarui</th>
                    <th scope="col" class="col-action"><span class="ui-visually-hidden">Aksi</span></th>
                </tr></thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= $row['order'] ?></td>
                        <td><?= e($row['name']) ?></td>
                        <td class="report-updated"><?= e($row['updated']) ?></td>
                        <td class="col-action">
                            <a class="report-preview-link" href="<?= BASE_PATH ?>/kurikulum/manajemen-template/pratinjau/<?= $sectionIndex === 0 ? 'akhir' : 'tengah' ?>" aria-label="<?= e('Pratinjau Rapor Montessori ' . ($sectionIndex === 0 ? 'Akhir' : 'Tengah') . ' Semester') ?>"><?= icon('icon_more_vertical') ?></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$rows): ?>
                    <tr><td colspan="4" class="data-table-empty">Tidak ada template yang sesuai pencarian atau filter.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endforeach; ?>
</div>
