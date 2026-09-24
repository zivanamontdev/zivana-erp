<?php
require_once ROOT_PATH.'/app/models/EraporApprovalAssignment.php';
$assignmentMigration=ROOT_PATH.'/database/migrations/20260925_erapor_approval_assignment_audit.sql';
catalogCheck(EraporMigrationRunner::apply($db,$assignmentMigration)==='applied','Approver assignment audit schema');
catalogCheck(EraporMigrationRunner::apply($db,$assignmentMigration)==='already_applied','Approver assignment audit migration retry');

// Restore a valid fixed flow only inside this disposable test database.
$flowFixture=[
    'KOORDINATOR_QURAN'=>['Koordinator Quran',1,'TERBATAS'],
    'KOORDINATOR_BING'=>['Koordinator Bahasa Inggris',1,'TERBATAS'],
    'KEPALA_SEKOLAH'=>['Kepala Sekolah',2,'SEMUA'],
];
foreach($flowFixture as $code=>[$label,$rank,$scope]) {
    $q=$db->prepare('INSERT INTO erapor_alur_penyetuju(kode,label,urutan,cakupan,aktif) VALUES(?,?,?,?,1)
        ON DUPLICATE KEY UPDATE label=VALUES(label),urutan=VALUES(urutan),cakupan=VALUES(cakupan),aktif=1');
    $q->execute([$code,$label,$rank,$scope]);
}
$db->exec('DELETE FROM erapor_alur_dokumen');
$db->exec("INSERT INTO erapor_alur_dokumen(penyetuju_id,rubrik_id)
    SELECT f.id,r.id FROM erapor_alur_penyetuju f JOIN erapor_rubrik r
      ON (f.kode='KOORDINATOR_QURAN' AND r.jenis_dokumen='UMMI')
      OR (f.kode='KOORDINATOR_BING' AND r.jenis_dokumen='BING')");
$db->exec('DELETE FROM erapor_penyetuju_user');

// Apply deployment seed and assert retry/non-overwrite semantics.
$seedFile=ROOT_PATH.'/database/seeds/20260925_erapor_approval_setup.sql';
$permissionIdsBefore=array_fill_keys(array_map('intval',$db->query('SELECT id FROM permissions')->fetchAll(PDO::FETCH_COLUMN)),true);
$seedSql=preg_replace('/^--[^\n]*$/m','',file_get_contents($seedFile));
$runSeed=static function()use($db,$seedSql):void { foreach(explode(';',$seedSql) as $statement) if(trim($statement)!=='') $db->exec($statement); };
$beforeLabels=$db->query('SELECT kode,label FROM erapor_alur_penyetuju ORDER BY kode')->fetchAll(PDO::FETCH_KEY_PAIR);
$runSeed(); $seedPermissionCount=(int)$db->query("SELECT COUNT(*) FROM permissions WHERE modul='eRapor'")->fetchColumn();
$scopeCount=(int)$db->query('SELECT COUNT(*) FROM erapor_alur_dokumen')->fetchColumn();
$runSeed();
catalogCheck($seedPermissionCount===(int)$db->query("SELECT COUNT(*) FROM permissions WHERE modul='eRapor'")->fetchColumn(),'Setup seed permissions are idempotent');
catalogCheck($scopeCount===(int)$db->query('SELECT COUNT(*) FROM erapor_alur_dokumen')->fetchColumn(),'Setup seed document scope is idempotent');
catalogCheck($beforeLabels===$db->query('SELECT kode,label FROM erapor_alur_penyetuju ORDER BY kode')->fetchAll(PDO::FETCH_KEY_PAIR),'Setup seed never overwrites existing flow labels');

require_once ROOT_PATH.'/app/models/EraporApprovalAssignment.php';
$suffix=bin2hex(random_bytes(4));
$newRole=static function(string $name)use($db,$suffix):int {
    $q=$db->prepare('INSERT INTO roles(nama) VALUES(?)'); $q->execute([$name.' '.$suffix]); return (int)$db->lastInsertId();
};
$setupRole=$newRole('Erapor Setup Fixture');
$approverRole=$newRole('Erapor Approver Fixture');
$permissions=[];
$permissionQuery=$db->prepare("SELECT id FROM permissions WHERE modul='eRapor' AND section=? AND aksi=? AND sub_section IS NULL");
foreach(['Persetujuan'=>['lihat','edit'],'Penugasan Penyetuju'=>['lihat','edit']] as $section=>$actions) foreach($actions as $action) {
    $permissionQuery->execute([$section,$action]); $id=$permissionQuery->fetchColumn();
    if(!$id) throw new RuntimeException('Approval setup permissions were not seeded.');
    $permissions[$section][$action]=(int)$id;
}
$grant=static function(int $roleId,array $permissionIds)use($db):void {
    $q=$db->prepare('INSERT INTO role_permissions(role_id,permission_id) VALUES(?,?)');
    foreach($permissionIds as $permissionId) $q->execute([$roleId,$permissionId]);
};
$grant($setupRole,array_values($permissions['Penugasan Penyetuju']));
$grant($approverRole,array_values($permissions['Persetujuan']));
$positionQuery=$db->prepare('SELECT id FROM jabatan WHERE nama=? ORDER BY id LIMIT 1');
$positionIds=[];
foreach(['Admin','Kepala Sekolah','Guru Kelas'] as $positionName) {
    $positionQuery->execute([$positionName]); $positionId=$positionQuery->fetchColumn();
    if(!$positionId) throw new RuntimeException('Required fixture position missing: '.$positionName);
    $positionIds[$positionName]=(int)$positionId;
}
$makeUser=static function(int $roleId,string $position,string $suffix,string $label)use($db,$positionIds):int {
    $employee=$db->prepare('INSERT INTO karyawan(jabatan_id,nama,is_active) VALUES(?,?,1)');
    $employee->execute([$positionIds[$position],$label]); $employeeId=(int)$db->lastInsertId();
    $user=$db->prepare('INSERT INTO users(karyawan_id,role_id,email,password_hash,is_active) VALUES(?,?,?,?,1)');
    $user->execute([$employeeId,$roleId,'erapor-'.$label.'-'.$suffix.'@example.test','fixture-hash']);
    return (int)$db->lastInsertId();
};
$actor=$makeUser($setupRole,'Admin',$suffix,'Setup Actor');
$coordinatorA=$makeUser($approverRole,'Admin',$suffix,'Coordinator A');
$coordinatorB=$makeUser($approverRole,'Admin',$suffix,'Coordinator B');
$head=$makeUser($approverRole,'Kepala Sekolah',$suffix,'Head Approver');
$read=EraporApprovalAssignment::read($db);
catalogCheck($read['ready'] && count($read['flows'])===3,'Approval setup reads the immutable three-stage flow');
catalogCheck((int)$db->query('SELECT COUNT(*) FROM erapor_penyetuju_user WHERE aktif=1')->fetchColumn()===0,'Provisioning never infers or auto-assigns an approver');
catalogCheck((int)$db->query('SELECT COUNT(*) FROM erapor_penyetuju_user WHERE aktif=1')->fetchColumn()===0,'Provisioning never infers or auto-assigns an approver');
$initial=['KOORDINATOR_QURAN'=>[$coordinatorA],'KOORDINATOR_BING'=>[$coordinatorA],'KEPALA_SEKOLAH'=>[$head]];
catalogReject(fn()=>EraporApprovalAssignment::save($db,$actor,$initial,'singkat'),'10–500 karakter');
catalogReject(fn()=>EraporApprovalAssignment::save($db,$actor,$initial+['UNKNOWN'=>[]],'Alasan test valid'),'Tahap persetujuan tidak dikenal');
$saved=EraporApprovalAssignment::save($db,$actor,$initial,'Penetapan penyetuju awal yang disepakati.');
catalogCheck($saved['changed']===3 && (int)$db->query('SELECT COUNT(*) FROM erapor_penyetuju_user WHERE aktif=1')->fetchColumn()===3,'Explicit assignment writes all required active approvers');
catalogCheck((int)$db->query('SELECT COUNT(*) FROM erapor_penugasan_penyetuju_audit')->fetchColumn()===3,'Every activation has an audit row');
$noop=EraporApprovalAssignment::save($db,$actor,$initial,'Penetapan penyetuju awal yang disepakati.');
catalogCheck($noop['changed']===0 && (int)$db->query('SELECT COUNT(*) FROM erapor_penugasan_penyetuju_audit')->fetchColumn()===3,'No-op save does not create audit noise');
$replacement=['KOORDINATOR_QURAN'=>[$coordinatorB],'KOORDINATOR_BING'=>[$coordinatorA],'KEPALA_SEKOLAH'=>[$head]];
$switched=EraporApprovalAssignment::save($db,$actor,$replacement,'Rotasi koordinator Quran yang telah disetujui.');
catalogCheck($switched['changed']===2,'Replacement is recorded as one deactivation and one activation');
$audit=$db->query('SELECT aktif_sebelum,aktif_sesudah,actor_id,alasan FROM erapor_penugasan_penyetuju_audit ORDER BY id DESC LIMIT 2')->fetchAll();
catalogCheck((int)$audit[0]['actor_id']===$actor && $audit[0]['alasan']==='Rotasi koordinator Quran yang telah disetujui.'
    && (int)$audit[0]['aktif_sebelum']===0 && (int)$audit[0]['aktif_sesudah']===1
    && (int)$audit[1]['aktif_sebelum']===1 && (int)$audit[1]['aktif_sesudah']===0,'Audit contains actor, before/after state, and reason');
catalogReject(fn()=>EraporApprovalAssignment::save($db,$actor,['KOORDINATOR_QURAN'=>[$coordinatorB],'KOORDINATOR_BING'=>[$coordinatorA],'KEPALA_SEKOLAH'=>[$coordinatorA]],'Alasan penugasan kepala sekolah tidak sesuai.'),'tidak memenuhi syarat tahap KEPALA_SEKOLAH');
catalogReject(fn()=>EraporApprovalAssignment::save($db,$actor,['KOORDINATOR_QURAN'=>[$coordinatorB],'KOORDINATOR_BING'=>[],'KEPALA_SEKOLAH'=>[$head]],'Alasan pengujian valid'),'minimal satu akun');
catalogReject(fn()=>EraporApprovalAssignment::save($db,$coordinatorA,$replacement,'Aktor bukan pengelola penugasan.'),'tidak berwenang');
$db->prepare('DELETE FROM role_permissions WHERE role_id=? AND permission_id=?')->execute([$approverRole,$permissions['Persetujuan']['edit']]);
catalogReject(fn()=>EraporApprovalAssignment::save($db,$actor,$replacement,'Akun approver kehilangan izin edit.'),'tidak memenuhi syarat tahap KOORDINATOR_QURAN');
$db->prepare('INSERT INTO role_permissions(role_id,permission_id) VALUES(?,?)')->execute([$approverRole,$permissions['Persetujuan']['edit']]);
$db->prepare('DELETE FROM role_permissions WHERE role_id=? AND permission_id=?')->execute([$approverRole,$permissions['Persetujuan']['edit']]);
catalogReject(fn()=>EraporApprovalAssignment::save($db,$actor,$replacement,'Akun approver kehilangan izin edit.'),'tidak memenuhi syarat tahap KOORDINATOR_QURAN');
$db->prepare('INSERT INTO role_permissions(role_id,permission_id) VALUES(?,?)')->execute([$approverRole,$permissions['Persetujuan']['edit']]);

// An audit failure must roll back both assignment state changes.
$auditCount=(int)$db->query('SELECT COUNT(*) FROM erapor_penugasan_penyetuju_audit')->fetchColumn();
$db->exec("CREATE TRIGGER fail_erapor_assignment_audit BEFORE INSERT ON erapor_penugasan_penyetuju_audit FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture assignment audit failure'");
try {
    catalogReject(fn()=>EraporApprovalAssignment::save($db,$actor,$initial,'Perubahan yang sengaja gagal audit.'),'fixture assignment audit failure');
} finally { $db->exec('DROP TRIGGER fail_erapor_assignment_audit'); }
$activeQuran=$db->query("SELECT u.user_id FROM erapor_penyetuju_user u JOIN erapor_alur_penyetuju f ON f.id=u.penyetuju_id WHERE f.kode='KOORDINATOR_QURAN' AND u.aktif=1")->fetchColumn();
catalogCheck((int)$activeQuran===$coordinatorB && (int)$db->query('SELECT COUNT(*) FROM erapor_penugasan_penyetuju_audit')->fetchColumn()===$auditCount,'Audit failure rolls back assignment changes and audit entries');

// Keep the disposable harness' legacy-table checksum exactly equal to the source backup.
$fixtureUsers=[$actor,$coordinatorA,$coordinatorB,$head];
$placeholders=implode(',',array_fill(0,count($fixtureUsers),'?'));
$employeeQuery=$db->prepare("SELECT karyawan_id FROM users WHERE id IN ($placeholders)"); $employeeQuery->execute($fixtureUsers);
$fixtureEmployees=array_map('intval',$employeeQuery->fetchAll(PDO::FETCH_COLUMN));
$db->prepare("DELETE FROM erapor_penugasan_penyetuju_audit WHERE actor_id IN ($placeholders) OR user_id IN ($placeholders)")->execute([...$fixtureUsers,...$fixtureUsers]);
$db->prepare("DELETE FROM erapor_penyetuju_user WHERE user_id IN ($placeholders)")->execute($fixtureUsers);
$db->prepare("DELETE FROM users WHERE id IN ($placeholders)")->execute($fixtureUsers);
$db->prepare('DELETE FROM karyawan WHERE id IN ('.implode(',',array_fill(0,count($fixtureEmployees),'?')).')')->execute($fixtureEmployees);
$db->prepare('DELETE FROM roles WHERE id IN (?,?)')->execute([$setupRole,$approverRole]);
$newPermissionIds=[];
foreach($db->query('SELECT id FROM permissions') as $permissionRow) if(!isset($permissionIdsBefore[(int)$permissionRow['id']])) $newPermissionIds[]=(int)$permissionRow['id'];
if($newPermissionIds) {
    $newPermissionPlaceholders=implode(',',array_fill(0,count($newPermissionIds),'?'));
    $db->prepare("DELETE FROM role_permissions WHERE permission_id IN ($newPermissionPlaceholders)")->execute($newPermissionIds);
    $db->prepare("DELETE FROM permissions WHERE id IN ($newPermissionPlaceholders)")->execute($newPermissionIds);
}
