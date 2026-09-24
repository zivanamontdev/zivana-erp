<?php
require_once ROOT_PATH.'/app/models/EraporApprove.php';
require_once ROOT_PATH.'/app/models/EraporApprovalReview.php';
require_once ROOT_PATH.'/app/models/EraporApprovalInbox.php';
require_once ROOT_PATH.'/app/models/EraporTeacherForm.php';
$approveFile=ROOT_PATH.'/database/migrations/20260924_erapor_approval_actions.sql';
catalogCheck(EraporMigrationRunner::apply($db,$approveFile)==='applied','Approval actions schema');
catalogCheck(EraporMigrationRunner::apply($db,$approveFile)==='already_applied','Approval actions retry');
$approveSid=$receptionId; $approveActor=$receptionActor;
$approveRows=array_column($db->query('SELECT * FROM erapor_sesi_penyetuju WHERE sesi_id='.$approveSid)->fetchAll(),null,'kode');
$approveQ=(int)$approveRows['KOORDINATOR_QURAN']['id'];
$approveB=(int)$approveRows['KOORDINATOR_BING']['id'];
$approveH=(int)$approveRows['KEPALA_SEKOLAH']['id'];
$approveFn=fn($id)=>EraporApprove::approve($db,$approveSid,$id,$approveActor);
catalogReject(fn()=>$approveFn($approveB),'tidak berwenang');
catalogReject(fn()=>EraporApprove::approve($db,$approveSid,$approveB,2147483647),'tidak berwenang');
catalogReject(fn()=>$approveFn(2147483647),'bukan bagian sesi');
catalogReject(fn()=>EraporApprove::approve($db,(int)$nextAbk['id'],$approveQ,$approveActor),'belum menunggu');
$approveAssign=$db->prepare('INSERT INTO erapor_penyetuju_user(penyetuju_id,user_id) VALUES(?,?)');
foreach (['KOORDINATOR_BING','KEPALA_SEKOLAH'] as $approveCode) $approveAssign->execute([$approveRows[$approveCode]['penyetuju_id'],$approveActor]);
$approvalReadBefore=catalogFingerprints($db);
$approvalReview=EraporApprovalReview::read($db,$approveSid,$approveB,$approveActor);
catalogCheck(array_values(array_unique(array_column($approvalReview['documents'],'jenis_dokumen')))==['BING'], 'Review projection exposes only the explicitly assigned BING scope');
catalogCheck($approvalReview['can_approve'] && !$approvalReview['all_approved'], 'Parallel coordinator approval is actionable before head stage');
$approvalInbox=EraporApprovalInbox::read($db,$approveActor);
$approvalBingTask=array_values(array_filter($approvalInbox['tasks'],fn($task)=>$task['approval_id']===$approveB))[0]??null;
catalogCheck($approvalBingTask && $approvalBingTask['can_approve'] && !$approvalBingTask['integrity_error'], 'Inbox projects assigned pending approval and readiness');
catalogCheck(catalogFingerprints($db)===$approvalReadBefore, 'Approval inbox and review are strictly read-only');
catalogReject(fn()=>$approveFn($approveH),'tidak berwenang'); // Explicit assignment alone cannot make a teacher head.
$approveEmployee=$db->query('SELECT k.id,k.jabatan_id FROM karyawan k JOIN users u ON u.karyawan_id=k.id WHERE u.id='.$approveActor)->fetch();
$approveHeadJob=(int)$db->query("SELECT id FROM jabatan WHERE nama='Kepala Sekolah' AND is_active=1 LIMIT 1")->fetchColumn();
catalogCheck($approveHeadJob>0,'Head job fixture available');
$approveJob=$db->prepare('UPDATE karyawan SET jabatan_id=?,updated_at=updated_at WHERE id=?');
$approveJob->execute([$approveHeadJob,$approveEmployee['id']]); // Disposable explicit fixture only.
try {
    catalogReject(fn()=>$approveFn($approveH),'tahap sebelumnya');
    $stmt=$other->prepare('SELECT GET_LOCK(?,0)'); $stmt->execute([$lock]);
    try { catalogReject(fn()=>$approveFn($approveB),'Another migration or assessment'); }
    finally { $stmt=$other->prepare('SELECT RELEASE_LOCK(?)'); $stmt->execute([$lock]); }
    $approveBefore=catalogFingerprints($db);
    $db->exec("CREATE TRIGGER fail_approval_snapshot BEFORE INSERT ON erapor_persetujuan_snapshot FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture approval failure'");
    try { catalogReject(fn()=>$approveFn($approveB),'fixture approval failure'); }
    finally { $db->exec('DROP TRIGGER fail_approval_snapshot'); }
    catalogCheck(catalogFingerprints($db)===$approveBefore,'Failed snapshot rolls back approval audit/status');
    // Head scope must include every document; incomplete snapshot rejected.
    $approveLink=$db->query('SELECT * FROM erapor_sesi_penyetuju_dokumen WHERE sesi_penyetuju_id='.$approveH.' LIMIT 1')->fetch();
    $db->exec('DELETE FROM erapor_sesi_penyetuju_dokumen WHERE sesi_penyetuju_id='.$approveH.' AND dokumen_id='.$approveLink['dokumen_id']);
    catalogReject(fn()=>$approveFn($approveB),'Cakupan persetujuan sesi tidak lengkap');
    $db->prepare('INSERT INTO erapor_sesi_penyetuju_dokumen(sesi_penyetuju_id,sesi_id,dokumen_id) VALUES(?,?,?)')->execute([$approveH,$approveSid,$approveLink['dokumen_id']]);
    catalogCheck($approveFn($approveB)['result']==='approved','BING can approve before Quran; current flow config inactive does not replace snapshot');
    catalogCheck($db->query('SELECT ttd_png FROM erapor_persetujuan_snapshot WHERE sesi_penyetuju_id='.$approveB)->fetchColumn()===null,'No signature image allowed');
    catalogReject(fn()=>$approveFn($approveH),'tahap sebelumnya');
    $approveFrozen=catalogFingerprints($db);
    catalogCheck($approveFn($approveB)['result']==='already_approved','Same actor retry is idempotent');
    catalogCheck(catalogFingerprints($db)===$approveFrozen,'No duplicate evidence/log');
    $db->exec('UPDATE erapor_penyetuju_user SET aktif=0 WHERE user_id='.$approveActor.' AND penyetuju_id='.$approveRows['KOORDINATOR_BING']['penyetuju_id']);
    catalogReject(fn()=>$approveFn($approveB),'tidak berwenang');
    $db->exec('UPDATE erapor_penyetuju_user SET aktif=1 WHERE user_id='.$approveActor);
    $db->exec('UPDATE users SET is_active=0,updated_at=updated_at WHERE id='.$approveActor);
    try { catalogReject(fn()=>$approveFn($approveQ),'tidak berwenang'); }
    finally { $db->exec('UPDATE users SET is_active=1,updated_at=updated_at WHERE id='.$approveActor); }
    $db->prepare('UPDATE erapor_profil_penandatangan SET nuptk=?,ttd_png=?,ttd_disetujui_pada=? WHERE user_id=?')->execute(['0012345678901234',$receptionPng,'2026-09-24 10:00:00',$approveActor]);
    catalogCheck($approveFn($approveQ)['all_approved']===false,'Two coordinators still need head');
    $approveSnapshot=$db->query('SELECT * FROM erapor_persetujuan_snapshot WHERE sesi_penyetuju_id='.$approveQ)->fetch();
    catalogCheck($approveSnapshot['ttd_png']===$receptionPng && $approveSnapshot['ttd_sha256']===hash('sha256',$receptionPng),'Approver image/hash copied');
    $db->exec('UPDATE erapor_profil_penandatangan SET nuptk=NULL,ttd_png=NULL,ttd_disetujui_pada=NULL WHERE user_id='.$approveActor);
    catalogCheck($approveFn($approveQ)['result']==='already_approved','Profile replacement does not re-sign');
    catalogCheck($db->query('SELECT * FROM erapor_persetujuan_snapshot WHERE sesi_penyetuju_id='.$approveQ)->fetch()===$approveSnapshot,'Historical signature retained');
    $approveResult=$approveFn($approveH);
    catalogCheck($approveResult['all_approved'] && $approveResult['status']==='MENUNGGU_TTD','Head approval ready for publication, not finalized');
    catalogCheck($db->query('SELECT status FROM erapor_sesi WHERE id='.$approveSid)->fetchColumn()==='MENUNGGU_TTD','No premature SELESAI');
    catalogCheck((int)$db->query("SELECT COUNT(*) FROM erapor_sesi_log WHERE sesi_id=$approveSid AND aksi='SETUJUI'")->fetchColumn()===3,'One audit per approval');
    catalogCheck($approveFn($approveH)['all_approved'],'Head retry retains completed approvals');
    catalogReject(fn()=>$db->exec('DELETE FROM erapor_sesi_log WHERE id='.$approveSnapshot['log_id']),'foreign key');
} finally { $approveJob->execute([$approveEmployee['jabatan_id'],$approveEmployee['id']]); }
