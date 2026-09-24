<?php
require_once ROOT_PATH.'/app/models/EraporRtsEntry.php';
$valuesFile=ROOT_PATH.'/database/migrations/20260924_erapor_rts_values.sql';
catalogCheck(EraporMigrationRunner::apply($db,$valuesFile)==='applied','RTS values schema');
catalogCheck(EraporMigrationRunner::apply($db,$valuesFile)==='already_applied','RTS values schema retry');
$entrySession=(int)$otherSemester['id'];
$entryActor=(int)$regular['actor_id'];
$entryDoc=(int)$db->query('SELECT dokumen_id FROM erapor_sesi_dokumen WHERE sesi_id='.$entrySession.' AND urutan=1')->fetchColumn();
$entryIndicator=(int)$db->query('SELECT id FROM erapor_rubrik_indikator ORDER BY id LIMIT 1')->fetchColumn();
$entryClock=new DateTimeImmutable($second['akhir_periode'].' 12:00:00',new DateTimeZone('Asia/Makassar'));
$entrySave=fn($changes)=>EraporRtsEntry::save($db,$entrySession,$entryDoc,$entryActor,$changes,$entryClock);
$change=['indikator_id'=>$entryIndicator,'nilai'=>2,'expected'=>null];
catalogCheck($entrySave([$change])===['changed'=>1,'filled'=>1,'required'=>175,'complete'=>false],'Save one RTS value');
$entryStable=catalogFingerprints($db);
catalogCheck($entrySave([$change])['changed']===0,'Retry value is no-op');
catalogCheck(catalogFingerprints($db)===$entryStable,'Retry no audit/timestamp writes');
$db->exec('UPDATE erapor_sesi_dokumen SET wajib=0 WHERE sesi_id='.$entrySession.' AND urutan=1');
catalogReject(fn()=>$entrySave([$change]),'Paket sesi mengalami drift');
$db->exec('UPDATE erapor_sesi_dokumen SET wajib=1 WHERE sesi_id='.$entrySession.' AND urutan=1');
$stale=$change; $stale['nilai']=3;
catalogReject(fn()=>$entrySave([$stale]),'Nilai telah berubah');
catalogReject(fn()=>$entrySave([['indikator_id'=>$entryIndicator,'nilai'=>5,'expected'=>2]]),'Nilai/skala tidak valid');
catalogReject(fn()=>$entrySave([$change,$change]),'Indikator duplikat');
catalogReject(fn()=>$entrySave([['indikator_id'=>999999,'nilai'=>1,'expected'=>null]]),'bukan milik rubrik');
catalogReject(fn()=>EraporRtsEntry::save($db,$entrySession,$entryDoc,(int)$differentTeacher,[$change],$entryClock),'Sesi bukan milik');
$foreignRts=(int)$db->query('SELECT dokumen_id FROM erapor_sesi_dokumen WHERE sesi_id='.$abkSession['id'].' AND urutan=1')->fetchColumn();
catalogReject(fn()=>EraporRtsEntry::save($db,$entrySession,$foreignRts,$entryActor,[$change],$entryClock),'Dokumen RTS tidak tersedia');
catalogReject(fn()=>EraporRtsEntry::save($db,$entrySession,$entryDoc,$entryActor,[$change],$entryClock->modify('+1 day')),'Batas waktu');
$stmt=$other->prepare('SELECT GET_LOCK(?,0)'); $stmt->execute([$lock]);
try { catalogReject(fn()=>$entrySave([$change]),'Another migration or assessment'); }
finally { $stmt=$other->prepare('SELECT RELEASE_LOCK(?)'); $stmt->execute([$lock]); }
$entryStable=catalogFingerprints($db);
$db->exec("CREATE TRIGGER fail_rts_audit BEFORE INSERT ON erapor_isian_log FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture audit failure'");
$update=['indikator_id'=>$entryIndicator,'nilai'=>3,'expected'=>2];
try { catalogReject(fn()=>$entrySave([$update]),'fixture audit failure'); }
finally { $db->exec('DROP TRIGGER fail_rts_audit'); }
catalogCheck(catalogFingerprints($db)===$entryStable,'Failed audit rolls back grade update');
catalogReject(fn()=>$entrySave([$update,['indikator_id'=>999999,'nilai'=>1,'expected'=>null]]),'bukan milik rubrik');
catalogCheck(catalogFingerprints($db)===$entryStable,'Failed later item rolls back entire batch');
catalogCheck($entrySave([$update])['changed']===1,'Update grade');
$auditRow=$db->query("SELECT nilai_lama,nilai_baru,aktor_id,status_sesi FROM erapor_isian_log ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
catalogCheck((int)$auditRow['nilai_lama']===2 && (int)$auditRow['nilai_baru']===3 && (int)$auditRow['aktor_id']===$entryActor && $auditRow['status_sesi']==='BELUM_DIISI','Audit old/new/actor/status');
$db->exec("UPDATE erapor_sesi SET status='TELAH_DIISI' WHERE id=$entrySession");
catalogCheck($entrySave([['indikator_id'=>$entryIndicator,'nilai'=>null,'expected'=>3]])['filled']===0,'Clear deletes grade during discussion');
catalogCheck($db->query('SELECT status FROM erapor_sesi WHERE id='.$entrySession)->fetchColumn()==='TELAH_DIISI','Clear does not downgrade session');
$allIds=$db->query('SELECT id FROM erapor_rubrik_indikator WHERE aktif=1 ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
$allChanges=array_map(fn($i)=>['indikator_id'=>(int)$i,'nilai'=>4,'expected'=>null],$allIds);
catalogCheck($entrySave($allChanges)===['changed'=>175,'filled'=>175,'required'=>175,'complete'=>true],'All 175 mandatory values complete');
catalogCheck((int)$db->query('SELECT COUNT(*) FROM erapor_rts_nilai WHERE sesi_id='.$sid)->fetchColumn()===0,'Same annual document other semester untouched');
foreach (['MENUNGGU_TTD','SELESAI'] as $lockedStatus) {
    $db->prepare('UPDATE erapor_sesi SET status=? WHERE id=?')->execute([$lockedStatus,$entrySession]);
    $lockedSnapshot=catalogFingerprints($db);
    catalogReject(fn()=>$entrySave([['indikator_id'=>$entryIndicator,'nilai'=>1,'expected'=>4]]),'sudah terkunci');
    catalogCheck(catalogFingerprints($db)===$lockedSnapshot,'Locked write no changes');
}
catalogReject(fn()=>$db->exec('INSERT INTO erapor_rts_nilai SELECT * FROM erapor_rts_nilai LIMIT 1'),'Duplicate');
catalogReject(fn()=>$db->exec('UPDATE erapor_rts_nilai SET dokumen_id='.$foreignRts.' WHERE sesi_id='.$entrySession),'foreign key');
