<?php
require_once ROOT_PATH.'/app/models/EraporCompleteness.php';
require_once ROOT_PATH.'/app/models/EraporConfirmFilled.php';
$cs=(int)$abkSession['id']; $ca=(int)$abkStudent['actor_id'];
$confirm=fn()=>EraporConfirmFilled::confirm($db,$cs,$ca);
$completionBefore=catalogFingerprints($db);
$incomplete=$confirm();
catalogCheck($incomplete['result']==='incomplete','Missing RTS prevents package confirmation');
catalogCheck(catalogFingerprints($db)===$completionBefore,'Incomplete confirmation no writes');
catalogCheck(array_column($incomplete['completion']['documents'],'jenis')===['RTS','AGAMA','UMMI','BING','PPI'],'ABK package all five documents');
$byType=array_column($incomplete['completion']['documents'],null,'jenis');
catalogCheck($byType['RTS']['filled']===0 && count($byType['RTS']['missing'])===175,'Missing indicator details');
catalogCheck($byType['UMMI']['complete'] && $byType['UMMI']['required']===1,'Ummi optional data not required');
catalogCheck($byType['PPI']['required']===30 && $byType['PPI']['complete'],'Outcomes excluded');
catalogCheck($byType['BING']['required']===9 && $byType['BING']['complete'],'BING nine fields');
catalogCheck($byType['AGAMA']['required']===($middle['semester']==='ganjil'?43:42),'Agama semester requirements');
$rtsDoc=$byType['RTS']['dokumen_id'];
$ids=$db->query('SELECT id FROM erapor_rubrik_indikator ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
$clock=new DateTimeImmutable($middle['akhir_periode'].' 12:00:00',new DateTimeZone('Asia/Makassar'));
EraporRtsEntry::save($db,$cs,$rtsDoc,$ca,array_map(fn($id)=>['indikator_id'=>(int)$id,'nilai'=>4,'expected'=>null],$ids),$clock);
$readyBefore=catalogFingerprints($db);
$db->exec("CREATE TRIGGER fail_confirm_audit BEFORE INSERT ON erapor_sesi_log FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture confirmation failure'");
try { catalogReject($confirm,'fixture confirmation failure'); }
finally { $db->exec('DROP TRIGGER fail_confirm_audit'); }
catalogCheck(catalogFingerprints($db)===$readyBefore,'Failed status audit rolls back status');
$stmt=$other->prepare('SELECT GET_LOCK(?,0)'); $stmt->execute([$lock]);
try { catalogReject($confirm,'Another migration or assessment'); }
finally { $stmt=$other->prepare('SELECT RELEASE_LOCK(?)'); $stmt->execute([$lock]); }
catalogReject(fn()=>EraporConfirmFilled::confirm($db,$cs,999999),'Sesi bukan milik');
$confirmed=$confirm();
catalogCheck($confirmed['result']==='confirmed' && $confirmed['status']==='TELAH_DIISI' && $confirmed['completion']['complete'],'Complete package confirms');
catalogCheck($middle['akhir_periode']<date('Y-m-d'),'Fixture deadline is past; confirmation still permitted');
$confirmedBefore=catalogFingerprints($db);
catalogCheck($confirm()['result']==='already_confirmed','Confirmation retry no-op');
catalogCheck(catalogFingerprints($db)===$confirmedBefore,'No duplicate status audit');
$event=$db->query("SELECT aktor_id,status_lama,status_baru FROM erapor_sesi_log WHERE sesi_id=$cs AND aksi='KONFIRMASI_ISI'")->fetch(PDO::FETCH_ASSOC);
catalogCheck((int)$event['aktor_id']===$ca && $event['status_lama']==='BELUM_DIISI' && $event['status_baru']==='TELAH_DIISI','Status audit actor/old/new');
$ummiDoc=$byType['UMMI']['dokumen_id'];
$note=$db->query('SELECT isi FROM erapor_ummi_catatan WHERE sesi_id='.$cs)->fetchColumn();
EraporUmmiEntry::save($db,$cs,$ummiDoc,$ca,[['key'=>'catatan','value'=>null,'expected'=>$note]],$clock);
$cleared=$confirm();
catalogCheck($cleared['result']==='incomplete' && $cleared['status']==='TELAH_DIISI','Completeness can fall without status downgrade');
$missingUmmi=array_column($cleared['completion']['documents'],null,'jenis')['UMMI']['missing'];
catalogCheck($missingUmmi===[['key'=>'catatan','label'=>'Catatan Guru']],'Exact missing note label');
EraporUmmiEntry::save($db,$cs,$ummiDoc,$ca,[['key'=>'catatan','value'=>$note,'expected'=>null]],$clock);
catalogCheck($confirm()['result']==='already_confirmed','Refill does not confirm twice');
foreach (['MENUNGGU_TTD','SELESAI'] as $state) {
    $db->prepare('UPDATE erapor_sesi SET status=? WHERE id=?')->execute([$state,$cs]);
    $frozen=catalogFingerprints($db);
    catalogReject($confirm,'sudah terkunci');
    catalogCheck(catalogFingerprints($db)===$frozen,'Confirmation cannot reopen locked session');
}
$db->exec("UPDATE erapor_sesi SET status='TELAH_DIISI' WHERE id=$cs"); // Fixture only.
catalogReject(fn()=>EraporConfirmFilled::confirm($db,$endSession,$ca),'Komposisi paket wajib tidak lengkap');
$db->exec('UPDATE erapor_sesi_dokumen SET wajib=0 WHERE sesi_id='.$cs.' AND urutan=1');
catalogReject($confirm,'Paket sesi mengalami drift');
$db->exec('UPDATE erapor_sesi_dokumen SET wajib=1 WHERE sesi_id='.$cs.' AND urutan=1');
// Strict missing-value behavior even if invalid whitespace arrived through direct SQL.
$ppiRow=$db->query('SELECT aspek_id,kolom_id,isi FROM erapor_ppi_isian WHERE sesi_id='.$cs.' LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$q=$db->prepare('UPDATE erapor_ppi_isian SET isi=? WHERE sesi_id=? AND aspek_id=? AND kolom_id=?');
$q->execute(["\u{00A0}\n",$cs,$ppiRow['aspek_id'],$ppiRow['kolom_id']]);
catalogCheck(!$confirm()['completion']['complete'],'Whitespace-only persisted text is incomplete');
$q->execute([$ppiRow['isi'],$cs,$ppiRow['aspek_id'],$ppiRow['kolom_id']]);
$db->beginTransaction();
try {
    $regularCtx=$db->query('SELECT * FROM erapor_sesi WHERE id='.$sid.' FOR UPDATE')->fetch(PDO::FETCH_ASSOC);
    $regularCompletion=EraporCompleteness::inspect($db,$regularCtx);
    catalogCheck(array_column($regularCompletion['documents'],'jenis')===['RTS','AGAMA','UMMI','BING'],'Regular package excludes PPI');
    catalogCheck(!$regularCompletion['complete'],'Regular missing values remain incomplete');
} finally { $db->rollBack(); }
