<?php
// Isolated in-memory database, including the existing assignment regression suite.
declare(strict_types=1);
require __DIR__ . '/student-assignment-regression.php';

$db->exec("INSERT INTO kelas(id,tahun_ajaran_id) VALUES (3,2);
    INSERT INTO periode_penilaian VALUES (1,1,'ganjil'),(2,1,'genap'),(3,2,'ganjil'),(4,1,NULL);
    CREATE TABLE nilai_fixture (rapor_id INTEGER, nilai TEXT);");
$session = $db->prepare('INSERT INTO sesi_pembagian_rapor VALUES (?,?,?,?)');
$session->execute([1,1,1,date('Y-m-d',strtotime('-1 day'))]); // closed
$session->execute([2,1,1,date('Y-m-d')]); // includes the final day
$session->execute([3,2,1,date('Y-m-d',strtotime('+30 days'))]);
$session->execute([4,3,1,date('Y-m-d',strtotime('+30 days'))]); // other school year
$session->execute([5,4,1,date('Y-m-d',strtotime('+30 days'))]); // ambiguous legacy semester
$pupil = (int)$students->create(['nama_lengkap'=>'New pupil','kelas_id'=>1]);
$reports = fn() => $db->query("SELECT * FROM rapor WHERE murid_id=$pupil ORDER BY sesi_pembagian_id")->fetchAll();
verify(array_column($reports(),'sesi_pembagian_id') === [2,3], 'New pupil gets only eligible open/future reports');
verify(array_column($reports(),'guru_id') === [null,null], 'Unassigned drafts do not invent a teacher');
$students->update($pupil,['nama_lengkap'=>'Renamed pupil']);
verify(count($reports()) === 2, 'Repeated synchronization must not duplicate reports');
$assign->replaceForGuru(3,[$pupil]);
verify(array_column($reports(),'guru_id') === [3,3], 'Assignment after period creation owns both drafts');
$draftId = (int)$reports()[0]['id'];
$submittedId = (int)$reports()[1]['id'];
$db->exec("INSERT INTO nilai_fixture VALUES ($draftId,'existing mark');
    UPDATE rapor SET status='menunggu_persetujuan' WHERE id=$submittedId;
    INSERT INTO rapor(murid_id,guru_id,status,sesi_pembagian_id,template_id) VALUES ($pupil,3,'belum_diisi',1,1)");
$assign->removeGuruFromKelas(1,3);
verify(array_column($reports(),'guru_id') === [3,null,3], 'Removal clears only open drafts, preserving closed and submitted ownership');
$assign->replaceForGuru(1,[$pupil]);
verify(array_column($reports(),'guru_id') === [3,1,3], 'Reassignment preserves report history');
$students->update($pupil,['kelas_id'=>3]);
verify(array_column($reports(),'guru_id') === [3,null,3,1], 'Cross-year transfer releases old open draft and creates new-year draft');
verify($db->query("SELECT nilai FROM nilai_fixture WHERE rapor_id=$draftId")->fetchColumn() === 'existing mark', 'Synchronization must preserve existing marks');
$students->update($pupil,['status'=>'berhenti']);
verify(array_column($reports(),'guru_id') === [3,null,3,null], 'Inactive pupil releases open draft access');
$students->update($pupil,['status'=>'bersekolah']);
verify(array_column($reports(),'guru_id') === [3,null,3,1], 'Reactivation restores eligible draft ownership');
(new Kelas())->delete(3);
verify($students->find($pupil)['kelas_id'] === null, 'Class deletion clears pupil class');
verify(array_column($reports(),'guru_id') === [3,null,3,null], 'Class deletion clears open draft ownership without erasing history');

try {
    StudentReportSync::sync($pupil);
    throw new RuntimeException('Expected transaction guard');
} catch (LogicException $e) {}

// A synchronization error must roll back both the pupil and its partially created reports.
$beforeStudents = (int)$db->query('SELECT COUNT(*) FROM murid')->fetchColumn();
$beforeReports = (int)$db->query('SELECT COUNT(*) FROM rapor')->fetchColumn();
$db->exec("CREATE TRIGGER fail_report BEFORE INSERT ON rapor WHEN NEW.sesi_pembagian_id=3 BEGIN SELECT RAISE(ABORT,'test synchronization failure'); END");
try {
    $students->create(['nama_lengkap'=>'Rollback fixture','kelas_id'=>1]);
    throw new RuntimeException('Expected synchronization failure');
} catch (PDOException $e) {}
verify((int)$db->query('SELECT COUNT(*) FROM murid')->fetchColumn() === $beforeStudents, 'Failed sync must roll back pupil creation');
verify((int)$db->query('SELECT COUNT(*) FROM rapor')->fetchColumn() === $beforeReports, 'Failed sync must roll back partial report creation');
echo "PASS: late enrollment, date/year/semester boundaries, idempotency, assignment/release, historical ownership and values, transfers, inactive pupils, class deletion, transaction rollback.\n";
