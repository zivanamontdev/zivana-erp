<?php
require_once ROOT_PATH.'/app/models/EraporStructuredEntry.php';
$structuredFile=ROOT_PATH.'/database/migrations/20260924_erapor_bing_ppi_values.sql';
catalogCheck(EraporMigrationRunner::apply($db,$structuredFile)==='applied','Structured values schema');
catalogCheck(EraporMigrationRunner::apply($db,$structuredFile)==='already_applied','Structured values schema retry');
$structuredSession=(int)$abkSession['id']; $structuredActor=(int)$abkStudent['actor_id'];
$structuredClock=new DateTimeImmutable($middle['akhir_periode'].' 12:00:00',new DateTimeZone('Asia/Makassar'));
$rawText="  Catatan guru: 'baik' & <b>utuh</b>\r\n- Bahasa العربية 😊\n  ";
foreach (['BING'=>4,'PPI'=>5] as $structuredType=>$position) {
    $structuredDoc=(int)$db->query('SELECT dokumen_id FROM erapor_sesi_dokumen WHERE sesi_id='.$structuredSession.' AND urutan='.$position)->fetchColumn();
    $structuredRubric=(int)$db->query('SELECT rubrik_id FROM erapor_dokumen WHERE id='.$structuredDoc)->fetchColumn();
    $structuredChanges=[];
    if ($structuredType==='BING') {
        foreach ($db->query('SELECT id FROM erapor_bing_indikator WHERE rubrik_id='.$structuredRubric.' ORDER BY urutan')->fetchAll(PDO::FETCH_COLUMN) as $i) $structuredChanges[]=['key'=>'nilai:'.$i,'value'=>'EXCELLENT','expected'=>null];
        foreach ($db->query('SELECT id FROM erapor_bing_komentar WHERE rubrik_id='.$structuredRubric.' ORDER BY urutan')->fetchAll(PDO::FETCH_COLUMN) as $i) $structuredChanges[]=['key'=>'komentar:'.$i,'value'=>$rawText,'expected'=>null];
        $textChange=$structuredChanges[5];
        $valueTable='erapor_bing_isian';
    } else {
        foreach ($db->query('SELECT a.id AS aid,c.id AS cid FROM erapor_ppi_aspek a JOIN erapor_ppi_kolom c ON c.rubrik_id=a.rubrik_id WHERE a.rubrik_id='.$structuredRubric.' AND c.diisi_di_sesi=1 ORDER BY a.urutan,c.bagian,c.urutan')->fetchAll(PDO::FETCH_ASSOC) as $r) $structuredChanges[]=['key'=>$r['aid'].':'.$r['cid'],'value'=>$rawText,'expected'=>null];
        $textChange=$structuredChanges[0];
        $valueTable='erapor_ppi_isian';
    }
    $structuredSave=fn($changes)=>EraporStructuredEntry::save($db,$structuredType,$structuredSession,$structuredDoc,$structuredActor,$changes,$structuredClock);
    $structuredBefore=catalogFingerprints($db);
    $db->exec("CREATE TRIGGER fail_structured_audit BEFORE INSERT ON erapor_isian_log FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture structured audit failure'");
    try { catalogReject(fn()=>$structuredSave($structuredChanges),'fixture structured audit failure'); }
    finally { $db->exec('DROP TRIGGER fail_structured_audit'); }
    catalogCheck(catalogFingerprints($db)===$structuredBefore,'Structured audit rollback '.$structuredType);
    catalogCheck($structuredSave($structuredChanges)===['changed'=>count($structuredChanges),'filled'=>count($structuredChanges),'required'=>count($structuredChanges),'complete'=>true],'Complete structured rubric '.$structuredType);
    catalogCheck((int)$db->query('SELECT COUNT(*) FROM '.$valueTable.' WHERE sesi_id='.$structuredSession)->fetchColumn()===($structuredType==='BING'?4:30),'Exact textarea count');
    foreach ($db->query('SELECT isi FROM '.$valueTable.' WHERE sesi_id='.$structuredSession)->fetchAll(PDO::FETCH_COLUMN) as $storedText) catalogCheck($storedText===$rawText,'Raw UTF-8 text preserved');
    $structuredStable=catalogFingerprints($db);
    catalogCheck($structuredSave($structuredChanges)['changed']===0,'Structured retry no-op');
    catalogCheck(catalogFingerprints($db)===$structuredStable,'Structured retry no audit writes');
    catalogReject(fn()=>$structuredSave([['key'=>$textChange['key'],'value'=>'New','expected'=>null]]),'Isian telah berubah');
    catalogReject(fn()=>$structuredSave([['key'=>'invalid:999','value'=>'New','expected'=>null]]),'bukan milik rubrik');
    catalogReject(fn()=>$structuredSave([$textChange,$textChange]),'duplikat');
    catalogReject(fn()=>$structuredSave([['key'=>$textChange['key'],'value'=>str_repeat('a',65536),'expected'=>$rawText]]),'kapasitas');
    catalogReject(fn()=>$structuredSave([['key'=>$textChange['key'],'value'=>chr(255),'expected'=>$rawText]]),'UTF-8');
    catalogReject(fn()=>EraporStructuredEntry::save($db,$structuredType,$structuredSession,$structuredDoc,999999,[$textChange],$structuredClock),'Sesi bukan milik');
    catalogReject(fn()=>EraporStructuredEntry::save($db,$structuredType,$structuredSession,999999,$structuredActor,[$textChange],$structuredClock),'Dokumen tidak tersedia');
    catalogReject(fn()=>EraporStructuredEntry::save($db,$structuredType,$structuredSession,$structuredDoc,$structuredActor,[$textChange],$structuredClock->modify('+1 day')),'Batas waktu');
    $updateText=['key'=>$textChange['key'],'value'=>'Updated','expected'=>$rawText];
    catalogReject(fn()=>$structuredSave([$updateText,['key'=>'invalid:999','value'=>null,'expected'=>null]]),'bukan milik rubrik');
    catalogCheck(catalogFingerprints($db)===$structuredStable,'Later invalid field rolls back earlier write');
    catalogCheck($structuredSave([$updateText])['changed']===1,'Update text');
    $textAudit=$db->query('SELECT nilai_lama,nilai_baru,aktor_id,status_sesi FROM erapor_isian_log ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    catalogCheck(json_decode($textAudit['nilai_lama'],true)===$rawText && json_decode($textAudit['nilai_baru'],true)==='Updated'
        && (int)$textAudit['aktor_id']===$structuredActor && $textAudit['status_sesi']==='BELUM_DIISI','Exact audit old/new');
    $db->exec("UPDATE erapor_sesi SET status='TELAH_DIISI' WHERE id=$structuredSession");
    $clear=['key'=>$textChange['key'],'value'=>" \t\n\u{00A0}\u{3000}",'expected'=>'Updated'];
    $result=$structuredSave([$clear]);
    catalogCheck(!$result['complete'] && $result['filled']===count($structuredChanges)-1,'Unicode whitespace clears mandatory field');
    catalogCheck($db->query('SELECT status FROM erapor_sesi WHERE id='.$structuredSession)->fetchColumn()==='TELAH_DIISI','Clear retains discussion status');
    catalogCheck($structuredSave([$textChange])['complete'],'Refill completes rubric');
    if ($structuredType==='BING') {
        $grade=$structuredChanges[0];
        catalogReject(fn()=>$structuredSave([['key'=>$grade['key'],'value'=>'A+','expected'=>'EXCELLENT']]),'Skala BING');
        catalogCheck($structuredSave([['key'=>$grade['key'],'value'=>'GOOD','expected'=>'EXCELLENT']])['changed']===1,'BING grade update');
        catalogCheck(!$structuredSave([['key'=>$grade['key'],'value'=>null,'expected'=>'GOOD']])['complete'],'BING grade clear');
        catalogCheck($structuredSave([$grade])['complete'],'BING grade refill');
        catalogReject(fn()=>$db->exec("UPDATE erapor_bing_nilai SET pilihan_kode='A+' WHERE sesi_id=$structuredSession"),'foreign key');
    } else {
        $outcome=(int)$db->query('SELECT id FROM erapor_ppi_kolom WHERE rubrik_id='.$structuredRubric.' AND diisi_di_sesi=0 LIMIT 1')->fetchColumn();
        $aspect=explode(':',$textChange['key'])[0];
        catalogReject(fn()=>$structuredSave([['key'=>$aspect.':'.$outcome,'value'=>'Outcome','expected'=>null]]),'Hasil Capaian');
        catalogCheck((int)$db->query('SELECT COUNT(*) FROM erapor_ppi_isian WHERE kolom_id='.$outcome)->fetchColumn()===0,'Outcomes never stored in session');
    }
    $stmt=$other->prepare('SELECT GET_LOCK(?,0)'); $stmt->execute([$lock]);
    try { catalogReject(fn()=>$structuredSave([$textChange]),'Another migration or assessment'); }
    finally { $stmt=$other->prepare('SELECT RELEASE_LOCK(?)'); $stmt->execute([$lock]); }
    foreach (['MENUNGGU_TTD','SELESAI'] as $frozen) {
        $db->prepare('UPDATE erapor_sesi SET status=? WHERE id=?')->execute([$frozen,$structuredSession]);
        $frozenBefore=catalogFingerprints($db);
        catalogReject(fn()=>$structuredSave([$textChange]),'sudah terkunci');
        catalogCheck(catalogFingerprints($db)===$frozenBefore,'Locked structured write no changes');
    }
    $db->exec("UPDATE erapor_sesi SET status='BELUM_DIISI' WHERE id=$structuredSession"); // Fixture only.
}
$nextAbk=EraporSessionFactory::create($db,(int)$abkStudent['murid_id'],(int)$second['id'],$structuredActor);
$nextClock=new DateTimeImmutable($second['akhir_periode'].' 12:00:00',new DateTimeZone('Asia/Makassar'));
foreach (['BING'=>4,'PPI'=>5] as $type=>$position) {
    $nextDoc=(int)$db->query('SELECT dokumen_id FROM erapor_sesi_dokumen WHERE sesi_id='.$nextAbk['id'].' AND urutan='.$position)->fetchColumn();
    $oldDoc=(int)$db->query('SELECT dokumen_id FROM erapor_sesi_dokumen WHERE sesi_id='.$structuredSession.' AND urutan='.$position)->fetchColumn();
    catalogCheck($nextDoc!==$oldDoc,'BING/PPI document identity split by semester');
    $key=$type==='BING'?'komentar:'.$db->query('SELECT id FROM erapor_bing_komentar ORDER BY id LIMIT 1')->fetchColumn():$textChange['key'];
    $out=EraporStructuredEntry::save($db,$type,(int)$nextAbk['id'],$nextDoc,$structuredActor,[['key'=>$key,'value'=>'Semester baru','expected'=>null]],$nextClock);
    catalogCheck($out['filled']===1 && !$out['complete'],'New semester completion is isolated');
    $t=$type==='BING'?'erapor_bing_isian':'erapor_ppi_isian';
    catalogCheck((int)$db->query('SELECT COUNT(*) FROM '.$t.' WHERE sesi_id='.$structuredSession.' AND dokumen_id='.$oldDoc)->fetchColumn()===($type==='BING'?4:30),'Previous semester text remains intact');
    catalogReject(fn()=>EraporStructuredEntry::save($db,$type,(int)$nextAbk['id'],$oldDoc,$structuredActor,[['key'=>$key,'value'=>'Wrong period','expected'=>null]],$nextClock),'Dokumen tidak tersedia');
}
$db->exec("UPDATE erapor_sesi SET status='BELUM_DIISI' WHERE id=$sid"); // Disposable fixture, not a transition API.
$ppiDoc=(int)$db->query('SELECT dokumen_id FROM erapor_sesi_dokumen WHERE sesi_id='.$structuredSession.' AND urutan=5')->fetchColumn();
catalogReject(fn()=>EraporStructuredEntry::save($db,'PPI',$sid,$ppiDoc,(int)$regular['actor_id'],[$textChange],$structuredClock),'PPI hanya tersedia');
$db->exec("UPDATE erapor_sesi SET status='SELESAI' WHERE id=$sid");
catalogReject(fn()=>$db->exec('INSERT INTO erapor_ppi_isian SELECT * FROM erapor_ppi_isian LIMIT 1'),'Duplicate');
catalogReject(fn()=>$db->exec('INSERT INTO erapor_bing_nilai SELECT * FROM erapor_bing_nilai LIMIT 1'),'Duplicate');
