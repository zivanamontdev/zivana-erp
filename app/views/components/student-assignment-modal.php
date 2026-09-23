<?php
ob_start();
?>
<form method="POST" action="<?= e($assignmentAction) ?>" class="modal-body">
    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
    <input type="hidden" name="guru_id" value="<?= (int) $assignmentGuru['id'] ?>">
    <div class="assign-guru-context">
        <?= uiText($assignmentGuru['nama'], 'body-sm', ['tag' => 'div', 'weight' => 'bold']) ?>
        <?= uiText($assignmentGuru['nama_jabatan'], 'caption-md', ['tag' => 'div']) ?>
    </div>
    <div data-assign-list>
        <div class="assign-list-header">
            <?= uiText('Daftar Murid', 'body-sm') ?>
            <?= uiButton('Tambah Murid', 'outline', ['marginVertical' => 0, 'attributes' => ['data-assign-add' => true]]) ?>
        </div>
        <div class="assign-list-rows" data-assign-rows>
            <?php foreach ($assignmentGuru['murid'] ?: [['id' => '']] as $rowIndex => $student): ?>
                <?php
                $studentFieldId = $assignmentId . '-student-' . $rowIndex;
                $studentValue = $student['id'];
                require __DIR__ . '/student-assignment-row.php';
                ?>
            <?php endforeach; ?>
        </div>
        <template data-assign-template>
            <?php
            $studentFieldId = $assignmentId . '-student-template';
            $studentValue = '';
            require __DIR__ . '/student-assignment-row.php';
            ?>
        </template>
    </div>
    <div class="modal-actions">
        <?= uiButton('Batal', 'outline', ['marginVertical' => 0, 'attributes' => ['data-modal-close' => true]]) ?>
        <?= uiButton('Simpan', 'primary', ['type' => 'submit', 'marginVertical' => 0]) ?>
    </div>
</form>
<?php echo uiModal($assignmentId, 'Atur Anak Murid', ob_get_clean(), ['variant' => 'assignment']); ?>
