<?php
// Only disposable backup-restore harness invokes this test.
require_once ROOT_PATH.'/app/models/EraporSessionFactory.php';
$sessionFile=ROOT_PATH.'/database/migrations/20260924_erapor_sessions.sql';
catalogCheck(EraporMigrationRunner::apply($db,$sessionFile)==='applied','Session identity schema');
catalogCheck(EraporMigrationRunner::apply($db,$sessionFile)==='already_applied','Session schema retry');
$pairs=$db->query("SELECT m.id AS murid_id,u.id AS actor_id,m.status_kondisi
    FROM murid m JOIN kelas_guru_murid a ON a.murid_id=m.id AND a.kelas_id=m.kelas_id
    JOIN karyawan k ON k.id=a.guru_id JOIN jabatan j ON j.id=k.jabatan_id JOIN users u ON u.karyawan_id=k.id
    WHERE u.is_active=1 AND k.is_active=1 AND j.nama IN ('Guru Kelas','Guru Shadow')
    ORDER BY m.id")->fetchAll(PDO::FETCH_ASSOC);
$testStudents=[];
foreach ($pairs as $pair) $testStudents[$pair['status_kondisi']] ??= $pair;
catalogCheck(isset($testStudents['Regular'],$testStudents['ABK']),'Assigned Regular and ABK fixtures available');
$midPeriods=$db->query("SELECT * FROM periode_penilaian WHERE tipe='Tengah Semester' ORDER BY tahun_ajaran_id,semester")->fetchAll(PDO::FETCH_ASSOC);
catalogCheck(count($midPeriods)>=2,'Two middle periods available');
$middle=$midPeriods[0]; $second=null;
foreach ($midPeriods as $p) if ($p['tahun_ajaran_id']===$middle['tahun_ajaran_id'] && $p['semester']!==$middle['semester']) { $second=$p; break; }
catalogCheck($second!==null,'Two semesters in same year');
$regular=$testStudents['Regular']; $abkStudent=$testStudents['ABK'];
$createRegular=fn()=>EraporSessionFactory::create($db,(int)$regular['murid_id'],(int)$middle['id'],(int)$regular['actor_id']);
$initialSessions=catalogFingerprints($db);
$db->exec("CREATE TRIGGER fail_session_import BEFORE INSERT ON erapor_sesi_log FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture session failure'");
try { catalogReject($createRegular,'fixture session failure'); }
finally { $db->exec('DROP TRIGGER fail_session_import'); }
catalogCheck(catalogFingerprints($db)===$initialSessions,'Rollback includes documents/session/links/rubric status/audit');
$stmt=$other->prepare('SELECT GET_LOCK(?,0)'); $stmt->execute([$lock]);
try { catalogReject($createRegular,'Another migration or session'); }
finally { $stmt=$other->prepare('SELECT RELEASE_LOCK(?)'); $stmt->execute([$lock]); }
$createdSession=$createRegular();
catalogCheck($createdSession['result']==='created','Regular session created');
$sid=$createdSession['id'];
$sessionStable=catalogFingerprints($db);
catalogCheck($createRegular()===['id'=>$sid,'result'=>'already_created'],'Creation retry returns same identity');
catalogCheck(catalogFingerprints($db)===$sessionStable,'Retry no writes including logs/timestamps');
catalogCheck((int)$db->query('SELECT COUNT(*) FROM erapor_sesi_dokumen WHERE sesi_id='.$sid)->fetchColumn()===4,'Regular session four documents');
catalogCheck((int)$db->query('SELECT COUNT(*) FROM erapor_sesi_log WHERE sesi_id='.$sid)->fetchColumn()===1,'Single creation audit event');
$abkSession=EraporSessionFactory::create($db,(int)$abkStudent['murid_id'],(int)$middle['id'],(int)$abkStudent['actor_id']);
catalogCheck((int)$db->query('SELECT COUNT(*) FROM erapor_sesi_dokumen WHERE sesi_id='.$abkSession['id'])->fetchColumn()===5,'ABK session five documents');
$otherSemester=EraporSessionFactory::create($db,(int)$regular['murid_id'],(int)$second['id'],(int)$regular['actor_id']);
catalogCheck($otherSemester['id']!==$sid,'Concrete periods have separate sessions');
catalogCheck((int)$db->query('SELECT COUNT(*) FROM erapor_dokumen WHERE murid_id='.(int)$regular['murid_id'])->fetchColumn()===6,'Reuse two annual documents, split two semester documents');
catalogReject(fn()=>EraporSessionFactory::create($db,(int)$regular['murid_id'],(int)$middle['id'],999999),'Guru aktif tidak ditugaskan');
$last=$db->query("SELECT id FROM periode_penilaian WHERE tipe='Akhir Semester' ORDER BY id LIMIT 1")->fetchColumn();
$stableBlocked=catalogFingerprints($db);
catalogReject(fn()=>EraporSessionFactory::create($db,(int)$regular['murid_id'],(int)$last,(int)$regular['actor_id']),'Rubrik belum tersedia: RAS');
catalogCheck(catalogFingerprints($db)===$stableBlocked,'Missing RAS leaves no partial package');
catalogReject(fn()=>$db->exec('INSERT INTO erapor_dokumen(murid_id,tahun_ajaran_id,rubrik_id,semester) SELECT murid_id,tahun_ajaran_id,rubrik_id,semester FROM erapor_dokumen LIMIT 1'),'Duplicate');
catalogReject(fn()=>$db->exec('INSERT INTO erapor_sesi(murid_id,periode_id,tahun_ajaran_id,semester,jenis,kondisi,kelas_id,guru_user_id,paket_sha256) SELECT murid_id,periode_id,tahun_ajaran_id,semester,jenis,kondisi,kelas_id,guru_user_id,paket_sha256 FROM erapor_sesi LIMIT 1'),'Duplicate');
$foreignDoc=(int)$db->query('SELECT dokumen_id FROM erapor_sesi_dokumen WHERE sesi_id='.$abkSession['id'].' LIMIT 1')->fetchColumn();
catalogReject(fn()=>$db->exec('UPDATE erapor_sesi_dokumen SET dokumen_id='.$foreignDoc.' WHERE sesi_id='.$sid.' AND urutan=1'),'foreign key');
$db->exec('UPDATE erapor_sesi_dokumen SET wajib=0 WHERE sesi_id='.$sid.' AND urutan=1');
catalogReject($createRegular,'Paket sesi mengalami drift');
$db->exec('UPDATE erapor_sesi_dokumen SET wajib=1 WHERE sesi_id='.$sid.' AND urutan=1');
catalogCheck($createRegular()['result']==='already_created','Restored package unchanged');
$differentTeacher=$db->query('SELECT id FROM users WHERE id<>'.(int)$regular['actor_id'].' AND is_active=1 ORDER BY id LIMIT 1')->fetchColumn();
catalogReject(fn()=>EraporSessionFactory::create($db,(int)$regular['murid_id'],(int)$middle['id'],(int)$differentTeacher),'Guru aktif tidak ditugaskan');
$db->exec("UPDATE erapor_sesi SET status='SELESAI' WHERE id=$sid");
$finalSnapshot=catalogFingerprints($db);
catalogCheck($createRegular()['result']==='already_created','Retry does not reopen completed session');
catalogCheck(catalogFingerprints($db)===$finalSnapshot,'Completed session retry no changes');
