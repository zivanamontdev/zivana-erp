<?php
require_once ROOT_PATH.'/app/models/EraporSignerSnapshot.php';
require_once ROOT_PATH.'/app/models/EraporConfirmReception.php';
$receptionFile=ROOT_PATH.'/database/migrations/20260924_erapor_reception.sql';
catalogCheck(EraporMigrationRunner::apply($db,$receptionFile)==='applied','Reception schema');
catalogCheck(EraporMigrationRunner::apply($db,$receptionFile)==='already_applied','Reception schema retry');
// Remove synthetic projection from preceding fixture; service now creates real rows.
$db->exec('DELETE FROM erapor_sesi_penyetuju_dokumen');
$db->exec('DELETE FROM erapor_sesi_penyetuju');
$receptionRestore=$db->prepare('UPDATE erapor_alur_penyetuju SET label=?,urutan=?,aktif=1 WHERE id=?');
foreach ($approvalFlows as $receptionFlow) $receptionRestore->execute([$receptionFlow['label'],$receptionFlow['urutan'],$receptionFlow['id']]);
foreach ($approvalScopes as $receptionScope) $approvalScopeInsert->execute([$receptionScope['penyetuju_id'],$receptionScope['rubrik_id']]);
$receptionId=(int)$abkSession['id']; $receptionActor=(int)$abkStudent['actor_id'];
$receive=fn()=>EraporConfirmReception::confirm($db,$receptionId,$receptionActor);
catalogReject(fn()=>EraporConfirmReception::confirm($db,$receptionId,2147483647),'Sesi bukan milik');
catalogReject(fn()=>EraporConfirmReception::confirm($db,(int)$nextAbk['id'],$receptionActor),'Status tidak mengizinkan');
catalogReject(fn()=>EraporConfirmReception::confirm($db,$sid,(int)$regular['actor_id']),'Status tidak mengizinkan');
$receptionNote=$db->query('SELECT isi FROM erapor_ummi_catatan WHERE sesi_id='.$receptionId)->fetchColumn();
$db->exec("UPDATE erapor_ummi_catatan SET isi='' WHERE sesi_id=".$receptionId);
$receptionBefore=catalogFingerprints($db);
catalogCheck($receive()['result']==='incomplete','Reception needs complete package');
catalogCheck(catalogFingerprints($db)===$receptionBefore,'Incomplete is read only');
$db->prepare('UPDATE erapor_ummi_catatan SET isi=? WHERE sesi_id=?')->execute([$receptionNote,$receptionId]);
$db->exec("UPDATE erapor_alur_penyetuju SET aktif=0 WHERE kode='KOORDINATOR_QURAN'");
catalogReject($receive,'Alur wajib belum aktif');
$db->exec("UPDATE erapor_alur_penyetuju SET aktif=1 WHERE kode='KOORDINATOR_QURAN'");
$receptionBefore=catalogFingerprints($db);
$db->exec("CREATE TRIGGER fail_reception BEFORE INSERT ON erapor_sesi_log FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture reception failure'");
try { catalogReject($receive,'fixture reception failure'); }
finally { $db->exec('DROP TRIGGER fail_reception'); }
catalogCheck(catalogFingerprints($db)===$receptionBefore,'Rollback snapshot, approvals, scopes and status on audit failure');
$stmt=$other->prepare('SELECT GET_LOCK(?,0)'); $stmt->execute([$lock]);
try { catalogReject($receive,'Another migration or assessment'); }
finally { $stmt=$other->prepare('SELECT RELEASE_LOCK(?)'); $stmt->execute([$lock]); }
// No image/NUPTK is valid; inspect inside trigger, then roll back deliberately.
$db->exec("CREATE TRIGGER inspect_optional_signature AFTER INSERT ON erapor_sesi_penerimaan FOR EACH ROW BEGIN
 IF NEW.guru_ttd_png IS NULL AND NEW.guru_nuptk IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='optional signature accepted'; END IF; END");
try { catalogReject($receive,'optional signature accepted'); }
finally { $db->exec('DROP TRIGGER inspect_optional_signature'); }
catalogCheck(catalogFingerprints($db)===$receptionBefore,'Optional snapshot test rolled back');
$receptionPng=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
$db->prepare('INSERT INTO erapor_profil_penandatangan(user_id,nuptk,ttd_png,ttd_disetujui_pada) VALUES(?,?,?,?)')
    ->execute([$receptionActor,'0012345678901234',$receptionPng,'2026-09-24 10:00:00']);
$db->exec("UPDATE erapor_profil_penandatangan SET nuptk='invalid'");
$receptionInvalid=catalogFingerprints($db);
catalogReject($receive,'NUPTK harus 16 digit');
catalogCheck(catalogFingerprints($db)===$receptionInvalid,'Invalid profile does not partially receive');
$db->exec("UPDATE erapor_profil_penandatangan SET nuptk='0012345678901234'");
$receptionResult=$receive();
catalogCheck($receptionResult['result']==='confirmed' && $receptionResult['status']==='MENUNGGU_TTD','Reception advances complete session after deadline');
$receptionSnapshot=$db->query('SELECT * FROM erapor_sesi_penerimaan WHERE sesi_id='.$receptionId)->fetch(PDO::FETCH_ASSOC);
catalogCheck($receptionSnapshot['guru_nuptk']==='0012345678901234' && $receptionSnapshot['guru_ttd_png']===$receptionPng && $receptionSnapshot['guru_ttd_sha256']===hash('sha256',$receptionPng),'Exact signature and leading zero NUPTK copied');
catalogCheck((int)$db->query('SELECT COUNT(*) FROM erapor_sesi_penyetuju WHERE sesi_id='.$receptionId)->fetchColumn()===3,'Three approval rows');
catalogCheck((int)$db->query('SELECT COUNT(*) FROM erapor_sesi_penyetuju_dokumen WHERE sesi_id='.$receptionId)->fetchColumn()===7,'Two limited documents plus five head documents');
$receptionLog=$db->query("SELECT aktor_id,status_lama,status_baru FROM erapor_sesi_log WHERE sesi_id=$receptionId AND aksi='KONFIRMASI_PENERIMAAN'")->fetch(PDO::FETCH_ASSOC);
catalogCheck((int)$receptionLog['aktor_id']===$receptionActor && $receptionLog['status_lama']==='TELAH_DIISI' && $receptionLog['status_baru']==='MENUNGGU_TTD','Reception audit');
$db->exec("UPDATE erapor_profil_penandatangan SET nuptk=NULL,ttd_png=NULL,ttd_disetujui_pada=NULL");
$db->exec('UPDATE erapor_alur_penyetuju SET aktif=0');
$receptionFrozen=catalogFingerprints($db);
catalogCheck($receive()['result']==='already_confirmed','Retry uses historical snapshot not new profile/config');
catalogCheck(catalogFingerprints($db)===$receptionFrozen,'Retry changes no rows');
catalogCheck($db->query('SELECT * FROM erapor_sesi_penerimaan WHERE sesi_id='.$receptionId)->fetch(PDO::FETCH_ASSOC)===$receptionSnapshot,'Profile update cannot change captured signature');
$receptionEmployee=$db->query('SELECT k.id,k.nama FROM karyawan k JOIN users u ON u.karyawan_id=k.id WHERE u.id='.$receptionActor)->fetch(PDO::FETCH_ASSOC);
$receptionRename=$db->prepare('UPDATE karyawan SET nama=?,updated_at=updated_at WHERE id=?');
$receptionRename->execute(['Changed fixture name',$receptionEmployee['id']]);
try {
    catalogCheck($receive()['result']==='already_confirmed','Renamed teacher can retry without resnapshot');
    catalogCheck($db->query('SELECT guru_nama FROM erapor_sesi_penerimaan WHERE sesi_id='.$receptionId)->fetchColumn()===$receptionEmployee['nama'],'Teacher name remains historical');
} finally { $receptionRename->execute([$receptionEmployee['nama'],$receptionEmployee['id']]); }
$db->exec('UPDATE users SET is_active=0,updated_at=updated_at WHERE id='.$receptionActor);
try { catalogReject($receive,'Penugasan guru tidak aktif'); }
finally { $db->exec('UPDATE users SET is_active=1,updated_at=updated_at WHERE id='.$receptionActor); }
catalogReject(fn()=>EraporUmmiEntry::save($db,$receptionId,(int)$ummiDoc,$receptionActor,[['key'=>'catatan','value'=>'changed','expected'=>$receptionNote]],$clock),'terkunci');
catalogCheck(catalogFingerprints($db)===$receptionFrozen,'Reception locks further assessment edits');
catalogReject(fn()=>$db->exec('DELETE FROM erapor_sesi WHERE id='.$receptionId),'foreign key');
