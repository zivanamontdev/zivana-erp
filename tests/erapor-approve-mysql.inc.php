<?php
require_once ROOT_PATH.'/app/models/EraporApprove.php';
require_once ROOT_PATH.'/app/models/EraporApprovalReview.php';
require_once ROOT_PATH.'/app/models/EraporApprovalInbox.php';
require_once ROOT_PATH.'/app/models/EraporTeacherForm.php';
require_once ROOT_PATH.'/app/models/EraporPackagePdfRenderer.php';
require_once ROOT_PATH.'/app/models/EraporPublication.php';
$publicationMigration=ROOT_PATH.'/database/migrations/20260925_erapor_publication_artifact.sql';
$publicationPlan=EraporMigrationRunner::plan($publicationMigration);
catalogCheck(array_keys($publicationPlan['steps'])===['erapor_sesi.tanggal_pengesahan','erapor_publikasi_pdf'],'Publication migration is constrained to one reviewed column and one table');
catalogCheck(EraporMigrationRunner::apply($db,$publicationMigration)==='applied','Publication artifact schema');
catalogCheck(EraporMigrationRunner::apply($db,$publicationMigration)==='already_applied','Publication artifact migration retry');
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
    $beforePdfFailure=catalogFingerprints($db);
    $db->exec("CREATE TRIGGER fail_publication_insert BEFORE INSERT ON erapor_publikasi_pdf FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture publication failure'");
    try { catalogReject(fn()=>$approveFn($approveH),'fixture publication failure'); }
    finally { $db->exec('DROP TRIGGER fail_publication_insert'); }
    catalogCheck(catalogFingerprints($db)===$beforePdfFailure,'Failed PDF preparation rolls back final approval, signer snapshot, date, and audit');
    $approveResult=$approveFn($approveH);
    catalogCheck($approveResult['all_approved'] && $approveResult['status']==='MENUNGGU_TTD','Head approval ready for publication, not finalized');
    catalogCheck($approveResult['publication']['result']==='prepared' && $approveResult['publication']['delivery_status']==='SIAP_DIKIRIM','Final approval atomically prepares a private PDF artifact');
    catalogCheck($db->query('SELECT status FROM erapor_sesi WHERE id='.$approveSid)->fetchColumn()==='MENUNGGU_TTD','No premature SELESAI');
    catalogCheck($db->query('SELECT tanggal_pengesahan FROM erapor_sesi WHERE id='.$approveSid)->fetchColumn()!==null,'Approval snapshot stores the signing date');
    $pdfArtifact=$db->query('SELECT * FROM erapor_publikasi_pdf WHERE sesi_id='.$approveSid)->fetch(PDO::FETCH_ASSOC);
    $pdfBytes=is_resource($pdfArtifact['pdf_bytes'])?stream_get_contents($pdfArtifact['pdf_bytes']):$pdfArtifact['pdf_bytes'];
    $pdfManifest=json_decode($pdfArtifact['manifest'],true,512,JSON_THROW_ON_ERROR);
    catalogCheck(str_starts_with($pdfBytes,'%PDF-') && strlen($pdfBytes)===(int)$pdfArtifact['ukuran_byte']
        && hash_equals($pdfArtifact['pdf_sha256'],hash('sha256',$pdfBytes)),'Stored PDF bytes, length, and hash match');
    catalogCheck(count($pdfManifest['documents'])===5 && count($pdfManifest['signers'])>0
        && hash_equals($pdfArtifact['sumber_sha256'],$pdfManifest['source_sha256'])
        && is_int($pdfManifest['page_count']) && $pdfManifest['page_count']>=5 && $pdfManifest['page_count']<=80,
        'ABK manifest freezes all five documents, signer evidence, and a bounded PDF page count');
    $agamaManifest=array_values(array_filter($pdfManifest['documents'],static fn($d)=>($d['jenis'] ?? '')==='AGAMA'))[0] ?? null;
    catalogCheck(($agamaManifest['narrative_template_version'] ?? null)==='AGAMA_NARASI_V1',
        'Published Agama narrative records an immutable template version');
    catalogCheck($pdfManifest['school']['tempat_pengesahan']==='Makassar','Publication derives signing place from the configured school address');
    // periode_penilaian menyimpan 'ganjil'/'Tengah Semester'; renderer membaca key ternormalisasi (TS_GANJIL, TENGAH_GANJIL, ...).
    $capture=new ReflectionMethod(EraporPublication::class,'capture'); $capture->setAccessible(true);
    $capSession=$db->query('SELECT * FROM erapor_sesi WHERE id='.$approveSid)->fetch(PDO::FETCH_ASSOC);
    $capApprovals=$db->query('SELECT * FROM erapor_sesi_penyetuju WHERE sesi_id='.$approveSid.' ORDER BY urutan,id')->fetchAll(PDO::FETCH_ASSOC);
    $captured=array_column($capture->invoke(null,$db,$capSession,$capApprovals,new DateTimeImmutable())['documents'],null,'jenis_dokumen');
    $capSemester=$capSession['semester'];
    catalogCheck(!empty($captured['RTS']['period_values']['TS_'.$capSemester]) && isset($captured['RTS']['signature_periods'][$capSemester])
        && !empty($captured['AGAMA']['period_values']['TENGAH_'.$capSemester]) && !empty($captured['UMMI']['period_values']['TENGAH'])
        && !empty($captured['BING']['period_values']['CURRENT'])
        && str_contains($captured['AGAMA']['narrative'],'semester '.strtolower($capSemester)),
        'Publication captures filled values under the keys the PDF renderer reads (not blank grade cells)');
    catalogCheck((int)$db->query("SELECT COUNT(*) FROM erapor_sesi_log WHERE sesi_id=$approveSid AND aksi='PDF_DISIAPKAN'")->fetchColumn()===1,'PDF preparation is audited once');
    catalogCheck((int)$db->query("SELECT COUNT(*) FROM erapor_sesi_log WHERE sesi_id=$approveSid AND aksi='SETUJUI'")->fetchColumn()===3,'One audit per approval');
    $manifestTampered=$pdfManifest; $manifestTampered['source_sha256']=str_repeat('0',64);
    $db->prepare('UPDATE erapor_publikasi_pdf SET manifest=? WHERE sesi_id=?')->execute([json_encode($manifestTampered,JSON_THROW_ON_ERROR),$approveSid]);
    try { catalogReject(fn()=>$approveFn($approveH),'integritas'); }
    finally { $db->prepare('UPDATE erapor_publikasi_pdf SET manifest=? WHERE sesi_id=?')->execute([$pdfArtifact['manifest'],$approveSid]); }
    catalogCheck($approveFn($approveH)['publication']['result']==='already_prepared','Head retry reuses the same PDF artifact');
    catalogCheck((int)$db->query('SELECT COUNT(*) FROM erapor_publikasi_pdf WHERE sesi_id='.$approveSid)->fetchColumn()===1,'Approval retry creates no duplicate PDF');
    catalogReject(fn()=>$db->exec('DELETE FROM erapor_sesi_log WHERE id='.$approveSnapshot['log_id']),'foreign key');
} finally { $approveJob->execute([$approveEmployee['jabatan_id'],$approveEmployee['id']]); }
