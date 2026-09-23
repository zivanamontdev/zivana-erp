<?php
$studentChoices = ['' => 'Nama murid'];
foreach ($muridDiKelasIni as $option) {
    if (isset($assignedElsewhere[$option['id']]) && (int) $assignedElsewhere[$option['id']] !== (int) $group['guru_id']) continue;
    $studentChoices[$option['id']] = $option['nama_lengkap'];
}
$assignmentGuru = ['id' => $group['guru_id'], 'nama' => $group['nama_guru'], 'nama_jabatan' => $group['nama_jabatan'], 'murid' => $group['murid']];
$assignmentAction = BASE_PATH . '/kelas/' . (int) $kelas['id'] . '/guru-murid';
$assignmentId = 'modal-atur-murid-' . (int) $group['guru_id'];
require VIEW_PATH . '/components/student-assignment-modal.php';
