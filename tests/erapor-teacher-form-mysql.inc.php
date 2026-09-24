<?php
require_once ROOT_PATH.'/app/models/EraporTeacherForm.php';
$formSid=(int)$abkSession['id']; $formActor=(int)$abkStudent['actor_id'];
$formClock=new DateTimeImmutable($middle['akhir_periode'].' 12:00:00',new DateTimeZone('Asia/Makassar'));
$formBefore=catalogFingerprints($db);
$formRead=EraporTeacherForm::read($db,$formSid,$formActor,$formClock);
catalogCheck(catalogFingerprints($db)===$formBefore,'Read does not create/update rows or timestamps');
catalogCheck(!$db->inTransaction(),'Read closes transaction');
catalogCheck($formRead['capabilities']['read_only_reason']==='STATUS_TERKUNCI','Signed session form readonly');
catalogCheck(array_column($formRead['documents'],'jenis_dokumen')===['RTS','AGAMA','UMMI','BING','PPI'],'ABK form order');
$formDocs=array_column($formRead['documents'],null,'jenis_dokumen');
catalogCheck(count($formDocs['RTS']['form']['definitions']['items'])===175,'All RTS definitions');
catalogCheck(count($formDocs['RTS']['form']['values'])===175,'RTS stored grades');
catalogCheck(count($formDocs['BING']['form']['definitions']['items'])===5 && count($formDocs['BING']['form']['definitions']['comments'])===4,'BING 5 grades 4 notes');
catalogCheck(count($formDocs['BING']['form']['definitions']['scale'])===4,'BING scales');
catalogCheck(count($formDocs['PPI']['form']['definitions']['aspects'])===5 && count($formDocs['PPI']['form']['definitions']['columns'])===6,'PPI excludes outcomes');
catalogCheck(count($formDocs['PPI']['form']['values'])===30,'PPI values');
catalogCheck(count($formDocs['AGAMA']['form']['definitions']['scale'])===7,'Agama seven options');
catalogCheck(count($formDocs['AGAMA']['form']['definitions']['items'])===($formRead['period']['semester']==='GANJIL'?37:36),'Agama semester-specific items');
foreach ($formDocs['AGAMA']['form']['definitions']['items'] as $formItem) catalogCheck($formItem['semester']===$formRead['period']['semester'],'No other semester definition');
catalogCheck(count($formDocs['UMMI']['form']['definitions']['scale'])===12,'Ummi 12 grades');
catalogCheck(count($formDocs['UMMI']['form']['definitions']['volumes'])===7 && count($formDocs['UMMI']['form']['definitions']['items'])===27,'Ummi form returns seven named volumes and 27 reading materials');
catalogCheck($formDocs['UMMI']['form']['definitions']['items'][0]['jilid_nama']==='PRA TK'
    && (bool)$formDocs['UMMI']['form']['definitions']['items'][0]['hanya_pra_tk'],'Ummi materials carry their volume and PRA visibility metadata');
$formSavedNote=$db->query('SELECT isi FROM erapor_ummi_catatan WHERE sesi_id='.$formSid)->fetchColumn();
catalogCheck($formDocs['UMMI']['form']['values']['catatan']===$formSavedNote,'Raw note retained exactly');
catalogCheck(!str_contains(json_encode($formRead,JSON_THROW_ON_ERROR),'ttd_png'),'No signatures in teacher form');
$formRegular=EraporTeacherForm::read($db,(int)$sid,(int)$regular['actor_id'],$formClock);
catalogCheck(count($formRegular['documents'])===4 && !in_array('PPI',array_column($formRegular['documents'],'jenis_dokumen'),true),'Regular form excludes PPI');
catalogReject(fn()=>EraporTeacherForm::read($db,$formSid,2147483647),'Sesi bukan milik');
catalogReject(fn()=>EraporTeacherForm::read($db,2147483647,$formActor),'Sesi bukan milik');
$db->exec('UPDATE users SET is_active=0,updated_at=updated_at WHERE id='.$formActor);
try { catalogReject(fn()=>EraporTeacherForm::read($db,$formSid,$formActor),'Penugasan guru tidak aktif'); }
finally { $db->exec('UPDATE users SET is_active=1,updated_at=updated_at WHERE id='.$formActor); }
catalogCheck(!$db->inTransaction(),'Failed read rolls back transaction');
$formNext=(int)$nextAbk['id'];
$formNextPeriod=$db->query('SELECT p.* FROM periode_penilaian p JOIN erapor_sesi s ON s.periode_id=p.id WHERE s.id='.$formNext)->fetch();
$formNextClock=new DateTimeImmutable($formNextPeriod['akhir_periode'].' 12:00:00',new DateTimeZone('Asia/Makassar'));
$formNextRead=EraporTeacherForm::read($db,$formNext,$formActor,$formNextClock);
catalogCheck($formNextRead['capabilities']['can_edit'],'Within deadline editable');
catalogCheck(EraporTeacherForm::read($db,$formNext,$formActor,$formNextClock->modify('+1 day'))['capabilities']['read_only_reason']==='TENGGAT_BERAKHIR','After deadline readonly but readable');
$formNextDocs=array_column($formNextRead['documents'],null,'jenis_dokumen');
catalogCheck(count($formNextDocs['RTS']['form']['values'])<175,'Shared annual document does not leak other-session grades');
$formNextUmmi=(int)$formNextDocs['UMMI']['id'];
$formFlag=$db->query('SELECT * FROM erapor_ummi_periode WHERE sesi_id='.$formNext)->fetch();
$db->exec('DELETE FROM erapor_ummi_periode WHERE sesi_id='.$formNext);
try {
    $formNoInitBefore=catalogFingerprints($db);
    $formNoInit=array_column(EraporTeacherForm::read($db,$formNext,$formActor,$formNextClock)['documents'],null,'jenis_dokumen')['UMMI']['form'];
    catalogCheck($formNoInit['definitions']['initialization_required'] && $formNoInit['values']['mulai_pra_tk']===null,'Uninitialized is distinct from false');
    catalogCheck(catalogFingerprints($db)===$formNoInitBefore,'GET never initializes flag');
} finally {
    if ($formFlag) $db->prepare('INSERT INTO erapor_ummi_periode(sesi_id,dokumen_id,rubrik_id,mulai_pra_tk,diisi_oleh,diisi_pada) VALUES(?,?,?,?,?,?)')->execute(array_values($formFlag));
}
// Values returned by the reader are compatible with expected-value autosave.
$formText=$formNextDocs['UMMI']['form']['values']['catatan'];
$formRoundtripBefore=catalogFingerprints($db);
catalogCheck(EraporUmmiEntry::save($db,$formNext,$formNextUmmi,$formActor,[['key'=>'catatan','value'=>$formText,'expected'=>$formText]],$formNextClock)['changed']===0,'Read/save exact-value roundtrip');
catalogCheck(catalogFingerprints($db)===$formRoundtripBefore,'Roundtrip does not generate audit');
$db->exec('UPDATE erapor_sesi_dokumen SET wajib=0 WHERE sesi_id='.$formSid.' AND urutan=1');
try { catalogReject(fn()=>EraporTeacherForm::read($db,$formSid,$formActor),'Paket sesi mengalami drift'); }
finally { $db->exec('UPDATE erapor_sesi_dokumen SET wajib=1 WHERE sesi_id='.$formSid.' AND urutan=1'); }
