<?php
require_once ROOT_PATH.'/app/models/EraporUmmiEntry.php';
$ummiValuesFile=ROOT_PATH.'/database/migrations/20260924_erapor_ummi_values.sql';
catalogCheck(EraporMigrationRunner::apply($db,$ummiValuesFile)==='applied','Ummi values migration');
catalogCheck(EraporMigrationRunner::apply($db,$ummiValuesFile)==='already_applied','Ummi migration retry');
$us=(int)$abkSession['id']; $ua=(int)$abkStudent['actor_id'];
$ud=(int)$db->query('SELECT dokumen_id FROM erapor_sesi_dokumen WHERE sesi_id='.$us.' AND urutan=3')->fetchColumn();
$ur=(int)$db->query('SELECT rubrik_id FROM erapor_dokumen WHERE id='.$ud)->fetchColumn();
$uc=new DateTimeImmutable($middle['akhir_periode'].' 12:00:00',new DateTimeZone('Asia/Makassar'));
$saveUmmi=fn($changes)=>EraporUmmiEntry::save($db,$us,$ud,$ua,$changes,$uc);
$initialUmmi=catalogFingerprints($db);
$db->exec("CREATE TRIGGER fail_ummi_audit BEFORE INSERT ON erapor_isian_log FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture ummi audit failure'");
try { catalogReject(fn()=>$saveUmmi([]),'fixture ummi audit failure'); }
finally { $db->exec('DROP TRIGGER fail_ummi_audit'); }
catalogCheck(catalogFingerprints($db)===$initialUmmi,'Initialization audit failure rolls back flag');
$result=$saveUmmi([]);
catalogCheck($result['initialized'] && !$result['complete'] && !$result['mulai_pra_tk'] && $result['required']===1,'Ummi default false and one requirement');
$emptyStable=catalogFingerprints($db);
catalogCheck(!$saveUmmi([])['initialized'],'Initialize only once');
catalogCheck(catalogFingerprints($db)===$emptyStable,'Reinitialize is no-op');
$ummiNote="  Jilid I: baik ﷺ\r\nBaris kedua 😊  ";
$noteChange=['key'=>'catatan','value'=>$ummiNote,'expected'=>null];
catalogCheck($saveUmmi([$noteChange])['complete'],'Note alone completes Ummi');
catalogCheck((int)$db->query('SELECT COUNT(*) FROM erapor_ummi_bacaan')->fetchColumn()===0 && (int)$db->query('SELECT COUNT(*) FROM erapor_ummi_tes')->fetchColumn()===0,'Empty optional blocks allowed');
catalogCheck($db->query('SELECT isi FROM erapor_ummi_catatan WHERE sesi_id='.$us)->fetchColumn()===$ummiNote,'Raw Ummi note retained');
$praMaterial=(int)$db->query('SELECT m.id FROM erapor_ummi_materi m JOIN erapor_ummi_jilid j ON j.id=m.jilid_id WHERE j.hanya_pra_tk=1 LIMIT 1')->fetchColumn();
catalogCheck($saveUmmi([['key'=>'mulai_pra_tk','value'=>true,'expected'=>false],['key'=>'bacaan:'.$praMaterial,'value'=>'A+','expected'=>null]])['mulai_pra_tk'],'Enable PRA and grade');
catalogCheck(!$saveUmmi([['key'=>'mulai_pra_tk','value'=>false,'expected'=>true]])['mulai_pra_tk'],'Disable PRA');
catalogCheck($db->query('SELECT nilai FROM erapor_ummi_bacaan WHERE sesi_id='.$us.' AND materi_id='.$praMaterial)->fetchColumn()==='A+','Disable PRA preserves grade');
catalogCheck($saveUmmi([['key'=>'mulai_pra_tk','value'=>true,'expected'=>false]])['complete'],'Reenable retains completeness');
$ummiTests=[];
for ($n=1;$n<=3;$n++) $ummiTests[]=['key'=>'tes:'.str_pad(dechex($n),32,'0',STR_PAD_LEFT),
    'value'=>['urutan'=>$n,'tanggal_tes'=>$middle['akhir_periode'],'jilid'=>'Jilid '.$n,'nilai'=>'B+'],'expected'=>null];
catalogCheck($saveUmmi($ummiTests)['changed']===3,'More than two optional tests');
$stableUmmi=catalogFingerprints($db);
catalogCheck($saveUmmi($ummiTests)['changed']===0,'Token-based test retry no-op');
catalogCheck(catalogFingerprints($db)===$stableUmmi,'Retry does not duplicate tests/audit');
$editedTest=$ummiTests[0]['value']; $editedTest['nilai']='A-';
catalogCheck($saveUmmi([['key'=>$ummiTests[0]['key'],'value'=>$editedTest,'expected'=>$ummiTests[0]['value']]])['changed']===1,'Edit test event');
catalogReject(fn()=>$saveUmmi([['key'=>$ummiTests[0]['key'],'value'=>$ummiTests[0]['value'],'expected'=>null]]),'Isian telah berubah');
$audit=$db->query('SELECT nilai_lama,nilai_baru FROM erapor_isian_log ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
catalogCheck(json_decode($audit['nilai_lama'],true)==$ummiTests[0]['value'] && json_decode($audit['nilai_baru'],true)==$editedTest,'Test audit complete old/new payload');
catalogCheck($saveUmmi([['key'=>$ummiTests[0]['key'],'value'=>null,'expected'=>$editedTest]])['complete'],'Delete optional test');
catalogCheck((int)$db->query('SELECT COUNT(*) FROM erapor_ummi_tes WHERE sesi_id='.$us)->fetchColumn()===2,'Deleted only targeted event');
foreach ([
    ['key'=>'bacaan:999999','value'=>'A','expected'=>null],
    ['key'=>'hafalan:1','value'=>'A','expected'=>null],
    ['key'=>'tes:bad-token','value'=>null,'expected'=>null],
] as $bad) catalogReject(fn()=>$saveUmmi([$bad]),'bukan milik rubrik Ummi');
catalogReject(fn()=>$saveUmmi([['key'=>'bacaan:'.$praMaterial,'value'=>'EXCELLENT','expected'=>'A+']]),'Skala Ummi');
catalogReject(fn()=>$saveUmmi([['key'=>'mulai_pra_tk','value'=>null,'expected'=>true]]),'boolean');
$badTest=$ummiTests[0]; $badTest['value']['tanggal_tes']='2026-02-30';
catalogReject(fn()=>$saveUmmi([$badTest]),'Tanggal tes');
$badTest=$ummiTests[0]; $badTest['value']['jilid']='  ';
catalogReject(fn()=>$saveUmmi([$badTest]),'Jilid tes');
catalogReject(fn()=>$saveUmmi([['key'=>'catatan','value'=>chr(255),'expected'=>$ummiNote]]),'UTF-8');
catalogReject(fn()=>$saveUmmi([$noteChange,$noteChange]),'duplikat');
$stableUmmi=catalogFingerprints($db);
catalogReject(fn()=>$saveUmmi([['key'=>'catatan','value'=>'Changed','expected'=>$ummiNote],['key'=>'invalid','value'=>null,'expected'=>null]]),'bukan milik rubrik');
catalogCheck(catalogFingerprints($db)===$stableUmmi,'Invalid later field rolls back entire batch');
catalogReject(fn()=>EraporUmmiEntry::save($db,$us,$ud,999999,[$noteChange],$uc),'Sesi bukan milik');
catalogReject(fn()=>EraporUmmiEntry::save($db,$us,999999,$ua,[$noteChange],$uc),'Dokumen tidak tersedia');
catalogReject(fn()=>EraporUmmiEntry::save($db,$us,$ud,$ua,[$noteChange],$uc->modify('+1 day')),'Batas waktu');
$db->exec("UPDATE erapor_sesi SET status='TELAH_DIISI' WHERE id=$us");
catalogCheck(!$saveUmmi([['key'=>'catatan','value'=>"\n\u{00A0}",'expected'=>$ummiNote]])['complete'],'Clear note loses completeness');
catalogCheck($db->query('SELECT status FROM erapor_sesi WHERE id='.$us)->fetchColumn()==='TELAH_DIISI','Clear no status downgrade');
catalogCheck($saveUmmi([$noteChange])['complete'],'Refill note');
foreach (['MENUNGGU_TTD','SELESAI'] as $state) {
    $db->prepare('UPDATE erapor_sesi SET status=? WHERE id=?')->execute([$state,$us]);
    $lockedUmmi=catalogFingerprints($db);
    catalogReject(fn()=>$saveUmmi([]),'sudah terkunci');
    catalogCheck(catalogFingerprints($db)===$lockedUmmi,'Locked initialization no writes');
}
$db->exec("UPDATE erapor_sesi SET status='BELUM_DIISI' WHERE id=$us"); // Test fixture only.
$stmt=$other->prepare('SELECT GET_LOCK(?,0)'); $stmt->execute([$lock]);
try { catalogReject(fn()=>$saveUmmi([]),'Another migration or assessment'); }
finally { $stmt=$other->prepare('SELECT RELEASE_LOCK(?)'); $stmt->execute([$lock]); }
$otherUmmiDoc=(int)$db->query('SELECT dokumen_id FROM erapor_sesi_dokumen WHERE sesi_id='.$nextAbk['id'].' AND urutan=3')->fetchColumn();
$otherClock=new DateTimeImmutable($second['akhir_periode'].' 12:00:00',new DateTimeZone('Asia/Makassar'));
$otherResult=EraporUmmiEntry::save($db,(int)$nextAbk['id'],$otherUmmiDoc,$ua,[],$otherClock);
catalogCheck(!$otherResult['mulai_pra_tk'] && !$otherResult['complete'],'Different semester does not inherit flag/note');

// Synthetic Ummi-only end-period fixture, NOT a valid final-semester package.
// Production factory still blocks AKHIR because no official RAS exists.
$currentSession=$db->query('SELECT * FROM erapor_sesi WHERE id='.$us)->fetch(PDO::FETCH_ASSOC);
$q=$db->prepare("SELECT * FROM periode_penilaian WHERE tahun_ajaran_id=? AND semester=? AND tipe='Akhir Semester'");
$q->execute([$currentSession['tahun_ajaran_id'],strtolower($currentSession['semester'])]); $endPeriod=$q->fetch(PDO::FETCH_ASSOC);
catalogCheck((bool)$endPeriod,'End-period fixture available');
$fixtureHash=hash('sha256',json_encode([[$ud,$ur,$currentSession['semester'],1,1]],JSON_THROW_ON_ERROR));
$q=$db->prepare("INSERT INTO erapor_sesi(murid_id,periode_id,tahun_ajaran_id,semester,jenis,kondisi,kelas_id,guru_user_id,paket_sha256) VALUES(?,?,?,?,'AKHIR',?,?,?,?)");
$q->execute([$currentSession['murid_id'],$endPeriod['id'],$currentSession['tahun_ajaran_id'],$currentSession['semester'],$currentSession['kondisi'],$currentSession['kelas_id'],$ua,$fixtureHash]);
$endSession=(int)$db->lastInsertId();
$db->prepare('INSERT INTO erapor_sesi_dokumen(sesi_id,dokumen_id,murid_id,tahun_ajaran_id,semester,dokumen_semester,urutan,wajib) VALUES(?,?,?,?,?,?,1,1)')
    ->execute([$endSession,$ud,$currentSession['murid_id'],$currentSession['tahun_ajaran_id'],$currentSession['semester'],$currentSession['semester']]);
$endClock=new DateTimeImmutable($endPeriod['akhir_periode'].' 12:00:00',new DateTimeZone('Asia/Makassar'));
$endResult=EraporUmmiEntry::save($db,$endSession,$ud,$ua,[],$endClock);
catalogCheck($endResult['mulai_pra_tk'] && !$endResult['complete'],'End inherits mid flag only, not note');
$saveUmmi([['key'=>'mulai_pra_tk','value'=>false,'expected'=>true]]);
catalogCheck(EraporUmmiEntry::save($db,$endSession,$ud,$ua,[],$endClock)['mulai_pra_tk'],'End flag snapshot does not follow later mid edits');
EraporUmmiEntry::save($db,$endSession,$ud,$ua,[['key'=>'catatan','value'=>'Akhir semester','expected'=>null]],$endClock);
catalogCheck($db->query('SELECT isi FROM erapor_ummi_catatan WHERE sesi_id='.$us)->fetchColumn()===$ummiNote,'Same document notes isolated by period');
$auditFailureSnapshot=catalogFingerprints($db);
$db->exec("CREATE TRIGGER fail_ummi_update BEFORE INSERT ON erapor_isian_log FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture ummi update failure'");
try { catalogReject(fn()=>$saveUmmi([['key'=>'catatan','value'=>'Changed','expected'=>$ummiNote]]),'fixture ummi update failure'); }
finally { $db->exec('DROP TRIGGER fail_ummi_update'); }
catalogCheck(catalogFingerprints($db)===$auditFailureSnapshot,'Failed Ummi audit rolls back existing note update');
$previousGrade='A+';
foreach (['A','A-','B+','B','B-','C+','C','C-','D+','D','D-'] as $grade) {
    catalogCheck($saveUmmi([['key'=>'bacaan:'.$praMaterial,'value'=>$grade,'expected'=>$previousGrade]])['complete'],'All twelve Ummi grades accepted');
    $previousGrade=$grade;
}
catalogCheck($saveUmmi([['key'=>'bacaan:'.$praMaterial,'value'=>null,'expected'=>$previousGrade]])['complete'],'Clearing optional bacaan keeps completion');
catalogCheck((int)$db->query('SELECT COUNT(*) FROM erapor_ummi_bacaan WHERE sesi_id='.$us)->fetchColumn()===0,'Clear bacaan deletes row');
$saveUmmi([['key'=>'bacaan:'.$praMaterial,'value'=>'A','expected'=>null]]);
catalogReject(fn()=>$db->exec('INSERT INTO erapor_ummi_tes SELECT * FROM erapor_ummi_tes LIMIT 1'),'Duplicate');
catalogReject(fn()=>$db->exec("UPDATE erapor_ummi_bacaan SET nilai='EXCELLENT' WHERE sesi_id=$us"),'foreign key');
