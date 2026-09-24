<?php
// Configuration/snapshot fixtures only, not a production reception transition.
require_once ROOT_PATH.'/app/models/EraporApprovalPlan.php';
$approvalFile=ROOT_PATH.'/database/migrations/20260924_erapor_approval_flow.sql';
catalogCheck(EraporMigrationRunner::apply($db,$approvalFile)==='applied','Approval schema');
catalogCheck(EraporMigrationRunner::apply($db,$approvalFile)==='already_applied','Approval schema retry');
catalogCheck((int)$db->query('SELECT COUNT(*) FROM erapor_penyetuju_user')->fetchColumn()===0,'No automatic approver assignment');
$db->exec("INSERT INTO erapor_alur_penyetuju(kode,label,urutan,cakupan) VALUES
 ('KOORDINATOR_QURAN','Koordinator Quran',1,'TERBATAS'),
 ('KOORDINATOR_BING','Koordinator Bahasa Inggris',1,'TERBATAS'),
 ('KEPALA_SEKOLAH','Kepala Sekolah',2,'SEMUA')");
$approvalFlows=$db->query('SELECT * FROM erapor_alur_penyetuju ORDER BY id')->fetchAll();
$approvalDocQuery=$db->prepare('SELECT d.id,d.rubrik_id,r.jenis_dokumen FROM erapor_sesi_dokumen sd JOIN erapor_dokumen d ON d.id=sd.dokumen_id JOIN erapor_rubrik r ON r.id=d.rubrik_id WHERE sd.sesi_id=? ORDER BY sd.urutan');
$approvalDocQuery->execute([$abkSession['id']]); $approvalDocs=$approvalDocQuery->fetchAll();
$approvalScopeInsert=$db->prepare('INSERT INTO erapor_alur_dokumen(penyetuju_id,rubrik_id) VALUES(?,?)');
foreach ($approvalDocs as $approvalDoc) {
    if ($approvalDoc['jenis_dokumen']==='UMMI') $approvalScopeInsert->execute([$approvalFlows[0]['id'],$approvalDoc['rubrik_id']]);
    if ($approvalDoc['jenis_dokumen']==='BING') $approvalScopeInsert->execute([$approvalFlows[1]['id'],$approvalDoc['rubrik_id']]);
}
$approvalScopes=$db->query('SELECT * FROM erapor_alur_dokumen')->fetchAll();
$approvalProjection=EraporApprovalPlan::build($approvalDocs,$approvalFlows,$approvalScopes);
catalogCheck(array_column($approvalProjection,'urutan')===[1,1,2],'Parallel coordinators before head');
catalogCheck(count($approvalProjection[2]['dokumen_ids'])===5,'Head includes ABK PPI');
$approvalDocQuery->execute([$sid]);
$approvalRegular=EraporApprovalPlan::build($approvalDocQuery->fetchAll(),$approvalFlows,$approvalScopes);
catalogCheck(count($approvalRegular[2]['dokumen_ids'])===4,'Regular head scope');
$approvalInsert=$db->prepare('INSERT INTO erapor_sesi_penyetuju(sesi_id,penyetuju_id,kode,label,urutan,cakupan,status) VALUES(?,?,?,?,?,?,?)');
$approvalLink=$db->prepare('INSERT INTO erapor_sesi_penyetuju_dokumen(sesi_penyetuju_id,sesi_id,dokumen_id) VALUES(?,?,?)');
foreach ($approvalProjection as $approvalRow) {
    $approvalInsert->execute([$abkSession['id'],$approvalRow['penyetuju_id'],$approvalRow['kode'],$approvalRow['label'],$approvalRow['urutan'],$approvalRow['cakupan'],$approvalRow['status']]);
    $approvalSnapshotId=(int)$db->lastInsertId();
    foreach ($approvalRow['dokumen_ids'] as $approvalDocumentId) $approvalLink->execute([$approvalSnapshotId,$abkSession['id'],$approvalDocumentId]);
}
$approvalSnapshotBefore=catalogFingerprints($db,['erapor_sesi_penyetuju','erapor_sesi_penyetuju_dokumen']);
$db->exec("UPDATE erapor_alur_penyetuju SET label='Changed configuration',urutan=3,aktif=0");
$db->exec('DELETE FROM erapor_alur_dokumen');
catalogCheck(catalogFingerprints($db,['erapor_sesi_penyetuju','erapor_sesi_penyetuju_dokumen'])===$approvalSnapshotBefore,'Session scope independent of later configuration');
$approvalDocQuery->execute([$sid]); $approvalForeignDocs=$approvalDocQuery->fetchAll();
catalogReject(fn()=>$approvalLink->execute([$approvalSnapshotId,$sid,$approvalForeignDocs[0]['id']]),'foreign key');
catalogReject(fn()=>$approvalLink->execute([$approvalSnapshotId,$abkSession['id'],$approvalForeignDocs[0]['id']]),'foreign key');
catalogReject(fn()=>$approvalLink->execute([$approvalSnapshotId,$abkSession['id'],$approvalDocumentId]),'Duplicate');
catalogReject(fn()=>$approvalInsert->execute([$abkSession['id'],$approvalRow['penyetuju_id'],$approvalRow['kode'],$approvalRow['label'],2,'SEMUA','MENUNGGU']),'Duplicate');
$approvalAssignment=$db->prepare('INSERT INTO erapor_penyetuju_user(penyetuju_id,user_id) VALUES(?,?)');
$approvalAssignment->execute([$approvalFlows[0]['id'],$abkStudent['actor_id']]); // Explicit test fixture, not role inference.
catalogReject(fn()=>$approvalAssignment->execute([$approvalFlows[0]['id'],$abkStudent['actor_id']]),'Duplicate');
catalogReject(fn()=>$approvalAssignment->execute([$approvalFlows[0]['id'],2147483647]),'foreign key');
catalogReject(fn()=>$db->exec('DELETE FROM erapor_alur_penyetuju'),'foreign key');
