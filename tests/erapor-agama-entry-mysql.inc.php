<?php
require_once ROOT_PATH.'/app/models/EraporAgamaEntry.php';
$agamaValuesFile=ROOT_PATH.'/database/migrations/20260924_erapor_agama_values.sql';
catalogCheck(EraporMigrationRunner::apply($db,$agamaValuesFile)==='applied','Agama value schema');
catalogCheck(EraporMigrationRunner::apply($db,$agamaValuesFile)==='already_applied','Agama value schema retry');
$agamaActor=(int)$abkStudent['actor_id']; $annualDoc=null;
$agamaText="  Aqidah: baik ﷺ\r\n- Catatan & <teks> tetap 😊  ";
foreach ([(int)$abkSession['id'],(int)$nextAbk['id']] as $agamaSession) {
    $agamaCtx=$db->query('SELECT s.semester,p.akhir_periode FROM erapor_sesi s JOIN periode_penilaian p ON p.id=s.periode_id WHERE s.id='.$agamaSession)->fetch(PDO::FETCH_ASSOC);
    $agamaClock=new DateTimeImmutable($agamaCtx['akhir_periode'].' 12:00:00',new DateTimeZone('Asia/Makassar'));
    $agamaDoc=(int)$db->query('SELECT dokumen_id FROM erapor_sesi_dokumen WHERE sesi_id='.$agamaSession.' AND urutan=2')->fetchColumn();
    if ($annualDoc!==null) catalogCheck($annualDoc===$agamaDoc,'Annual Agama identity reused across semesters');
    $annualDoc=$agamaDoc;
    $agamaItems=$db->query('SELECT id FROM erapor_agama_item WHERE semester='.$db->quote($agamaCtx['semester']).' ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    $agamaNotes=$db->query('SELECT id FROM erapor_agama_lingkup ORDER BY urutan')->fetchAll(PDO::FETCH_COLUMN);
    $agamaChanges=[];
    $choices=['TELADAN','TALQIN','TAHFIZH/D','TAHFIZH/J','TAHFIZH/M','TAFHIM','TADIB'];
    foreach ($agamaItems as $n=>$i) $agamaChanges[]=['key'=>'nilai:'.$i,'value'=>$choices[$n%7],'expected'=>null];
    foreach ($agamaNotes as $i) $agamaChanges[]=['key'=>'catatan:'.$i,'value'=>$agamaText,'expected'=>null];
    $agamaSave=fn($changes)=>EraporAgamaEntry::save($db,$agamaSession,$agamaDoc,$agamaActor,$changes,$agamaClock);
    $agamaBefore=catalogFingerprints($db);
    $db->exec("CREATE TRIGGER fail_agama_audit BEFORE INSERT ON erapor_isian_log FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture agama audit failure'");
    try { catalogReject(fn()=>$agamaSave($agamaChanges),'fixture agama audit failure'); }
    finally { $db->exec('DROP TRIGGER fail_agama_audit'); }
    catalogCheck(catalogFingerprints($db)===$agamaBefore,'Agama values rollback when audit fails');
    $needed=$agamaCtx['semester']==='GANJIL'?43:42;
    catalogCheck($agamaSave($agamaChanges)===['changed'=>$needed,'filled'=>$needed,'required'=>$needed,'complete'=>true],'Correct semester completion');
    $agamaStable=catalogFingerprints($db);
    catalogCheck($agamaSave($agamaChanges)['changed']===0,'Agama retry no-op');
    catalogCheck(catalogFingerprints($db)===$agamaStable,'Agama retry audit/timestamps unchanged');
    foreach ($db->query('SELECT isi FROM erapor_agama_catatan WHERE sesi_id='.$agamaSession)->fetchAll(PDO::FETCH_COLUMN) as $text) catalogCheck($text===$agamaText,'Raw Agama note preserved');
    foreach (array_slice($agamaChanges,0,7) as $choice) {
        $itemId=(int)substr($choice['key'],6);
        $stored=$db->query('SELECT tahapan_kode,subtingkat_kode FROM erapor_agama_nilai WHERE sesi_id='.$agamaSession.' AND item_id='.$itemId)->fetch(PDO::FETCH_ASSOC);
        $split=explode('/',$choice['value']);
        catalogCheck($stored===['tahapan_kode'=>$split[0],'subtingkat_kode'=>$split[1] ?? null],'Stage/sublevel stored separately');
    }
    $wrongSemester=(int)$db->query('SELECT id FROM erapor_agama_item WHERE semester<>'.$db->quote($agamaCtx['semester']).' LIMIT 1')->fetchColumn();
    catalogReject(fn()=>$agamaSave([['key'=>'nilai:'.$wrongSemester,'value'=>'TELADAN','expected'=>null]]),'bukan milik rubrik/semester');
    $firstGrade=$agamaChanges[0]; $note=$agamaChanges[count($agamaItems)];
    foreach (['TAHFIZH','TAFHIM/D','TAHFIZH/X','EXCELLENT'] as $badGrade) {
        catalogReject(fn()=>$agamaSave([['key'=>$firstGrade['key'],'value'=>$badGrade,'expected'=>'TELADAN']]),'Tahapan/subtingkat');
    }
    catalogReject(fn()=>$agamaSave([['key'=>$firstGrade['key'],'value'=>'TALQIN','expected'=>null]]),'Isian telah berubah');
    catalogReject(fn()=>$agamaSave([$firstGrade,$firstGrade]),'duplikat');
    catalogReject(fn()=>$agamaSave([['key'=>$note['key'],'value'=>chr(255),'expected'=>$agamaText]]),'UTF-8');
    catalogReject(fn()=>$agamaSave([['key'=>$note['key'],'value'=>str_repeat('a',65536),'expected'=>$agamaText]]),'kapasitas');
    catalogReject(fn()=>EraporAgamaEntry::save($db,$agamaSession,$agamaDoc,999999,[$note],$agamaClock),'Sesi bukan milik');
    catalogReject(fn()=>EraporAgamaEntry::save($db,$agamaSession,999999,$agamaActor,[$note],$agamaClock),'Dokumen tidak tersedia');
    catalogReject(fn()=>EraporAgamaEntry::save($db,$agamaSession,$agamaDoc,$agamaActor,[$note],$agamaClock->modify('+1 day')),'Batas waktu');
    $update=['key'=>$firstGrade['key'],'value'=>'TAHFIZH/D','expected'=>'TELADAN'];
    catalogReject(fn()=>$agamaSave([$update,['key'=>'nilai:999999','value'=>'TELADAN','expected'=>null]]),'bukan milik rubrik/semester');
    catalogCheck(catalogFingerprints($db)===$agamaStable,'Batch invalid later field rolls back');
    catalogCheck($agamaSave([$update])['changed']===1,'Change from stage to Tahfizh');
    $audit=$db->query('SELECT nilai_lama,nilai_baru,aktor_id,status_sesi FROM erapor_isian_log ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    catalogCheck(json_decode($audit['nilai_lama'],true)==='TELADAN' && json_decode($audit['nilai_baru'],true)==='TAHFIZH/D'
        && (int)$audit['aktor_id']===$agamaActor && $audit['status_sesi']==='BELUM_DIISI','Agama audit old/new/status/actor');
    catalogCheck($agamaSave([['key'=>$firstGrade['key'],'value'=>'TAFHIM','expected'=>'TAHFIZH/D']])['changed']===1,'Leaving Tahfizh clears sublevel');
    $db->exec("UPDATE erapor_sesi SET status='TELAH_DIISI' WHERE id=$agamaSession");
    catalogCheck(!$agamaSave([['key'=>$note['key'],'value'=>"\n\u{00A0}\u{3000}",'expected'=>$agamaText]])['complete'],'Whitespace removes mandatory note');
    catalogCheck($db->query('SELECT status FROM erapor_sesi WHERE id='.$agamaSession)->fetchColumn()==='TELAH_DIISI','No status downgrade on clear');
    catalogCheck($agamaSave([$note])['complete'],'Note refill');
    catalogCheck(!$agamaSave([['key'=>$firstGrade['key'],'value'=>null,'expected'=>'TAFHIM']])['complete'],'Clear grade deletes row');
    catalogCheck($agamaSave([$firstGrade])['complete'],'Grade refill');
    catalogReject(fn()=>$db->exec("UPDATE erapor_agama_nilai SET subtingkat_id=NULL,subtingkat_kode=NULL WHERE sesi_id=$agamaSession AND tahapan_kode='TAHFIZH'"),'Check constraint');
    catalogReject(fn()=>$db->exec("UPDATE erapor_agama_nilai SET subtingkat_kode='D' WHERE sesi_id=$agamaSession AND tahapan_kode='TELADAN'"),'Check constraint');
    catalogReject(fn()=>$db->exec("UPDATE erapor_agama_nilai SET subtingkat_kode='M' WHERE sesi_id=$agamaSession AND subtingkat_kode='D'"),'foreign key');
    $stmt=$other->prepare('SELECT GET_LOCK(?,0)'); $stmt->execute([$lock]);
    try { catalogReject(fn()=>$agamaSave([$note]),'Another migration or assessment'); }
    finally { $stmt=$other->prepare('SELECT RELEASE_LOCK(?)'); $stmt->execute([$lock]); }
    foreach (['MENUNGGU_TTD','SELESAI'] as $state) {
        $db->prepare('UPDATE erapor_sesi SET status=? WHERE id=?')->execute([$state,$agamaSession]);
        $locked=catalogFingerprints($db);
        catalogReject(fn()=>$agamaSave([$note]),'sudah terkunci');
        catalogCheck(catalogFingerprints($db)===$locked,'Locked Agama write no changes');
    }
    $db->exec("UPDATE erapor_sesi SET status='BELUM_DIISI' WHERE id=$agamaSession"); // Disposable fixture only.
}
catalogCheck((int)$db->query('SELECT COUNT(*) FROM erapor_agama_nilai')->fetchColumn()===73,'Both semester values retained in annual document');
catalogCheck((int)$db->query('SELECT COUNT(*) FROM erapor_agama_catatan')->fetchColumn()===12,'Six independent notes per session');
catalogReject(fn()=>$db->exec('INSERT INTO erapor_agama_nilai SELECT * FROM erapor_agama_nilai LIMIT 1'),'Duplicate');
catalogReject(fn()=>$db->exec('INSERT INTO erapor_agama_catatan SELECT * FROM erapor_agama_catatan LIMIT 1'),'Duplicate');
