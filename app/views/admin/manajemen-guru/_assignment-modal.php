<?php
$studentChoices = ['' => 'Nama murid'];
foreach ($muridOptions as $option) {
    if (!empty($option['assigned_guru_id']) && (int) $option['assigned_guru_id'] !== (int) $guru['id']) continue;
    $studentChoices[$option['id']] = $option['nama_lengkap'] . ' — ' . trim(($option['level_kelas'] ?? '') . ' ' . ($option['nama_kelas'] ?? ''));
}
$assignmentGuru = $guru;
$assignmentAction = BASE_PATH . '/manajemen-guru/' . (int) $guru['id'] . '/murid';
$assignmentId = 'modal-atur-murid-guru-' . (int) $guru['id'];
require VIEW_PATH . '/components/student-assignment-modal.php';
