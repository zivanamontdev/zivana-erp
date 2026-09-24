<?php
require_once ROOT_PATH.'/app/models/EraporExtendPeriod.php';
$extensionFile=ROOT_PATH.'/database/migrations/20260924_erapor_extensions.sql';
catalogCheck(EraporMigrationRunner::apply($db,$extensionFile)==='applied','Extension schema');
catalogCheck(EraporMigrationRunner::apply($db,$extensionFile)==='already_applied','Extension schema retry');
$extensionSid=(int)$nextAbk['id']; $extensionActor=(int)$abkStudent['actor_id'];
$extensionSession=$db->query('SELECT * FROM erapor_sesi WHERE id='.$extensionSid)->fetch();
$extensionPeriod=$db->query('SELECT * FROM periode_penilaian WHERE id='.$extensionSession['periode_id'])->fetch();
$extensionPid=(int)$extensionPeriod['id']; $extensionOld=$extensionPeriod['akhir_periode'];
$extensionClock=new DateTimeImmutable($extensionOld.' 12:00:00',new DateTimeZone('Asia/Makassar'));
$extensionClock=$extensionClock->modify('+5 days');
$extensionNew=$extensionClock->modify('+7 days')->format('Y-m-d');
$extensionReason="  Diskusi orang tua belum selesai.\nPerpanjangan bersama.  ";
$extend=fn()=>EraporExtendPeriod::extend($db,$extensionPid,$extensionActor,$extensionOld,$extensionNew,$extensionReason,$extensionClock);
catalogReject($extend,'Hanya kepala sekolah');
catalogReject(fn()=>EraporExtendPeriod::preview($db,$extensionPid,$extensionActor),'Hanya kepala sekolah');
$extensionEmployee=$db->query('SELECT k.id,k.jabatan_id FROM karyawan k JOIN users u ON u.karyawan_id=k.id WHERE u.id='.$extensionActor)->fetch();
$extensionHead=(int)$db->query("SELECT id FROM jabatan WHERE nama='Kepala Sekolah' AND is_active=1 LIMIT 1")->fetchColumn();
$extensionJob=$db->prepare('UPDATE karyawan SET jabatan_id=?,updated_at=updated_at WHERE id=?');
$extensionRestore=$db->prepare('UPDATE periode_penilaian SET akhir_periode=?,updated_at=? WHERE id=?');
$extensionJob->execute([$extensionHead,$extensionEmployee['id']]);
try {
    $extensionBefore=catalogFingerprints($db);
    $extensionPreview=EraporExtendPeriod::preview($db,$extensionPid,$extensionActor);
    catalogCheck(catalogFingerprints($db)===$extensionBefore,'Impact preview read only');
    catalogCheck($extensionPreview['impact']['dapat_diisi']>=1,'Preview counts editable sessions');
    foreach (['',"\u{00A0}\n",str_repeat('a',65536)] as $extensionBadReason) {
        catalogReject(fn()=>EraporExtendPeriod::extend($db,$extensionPid,$extensionActor,$extensionOld,$extensionNew,$extensionBadReason,$extensionClock),'Alasan wajib');
    }
    foreach ([$extensionOld,$extensionClock->format('Y-m-d'),$extensionClock->modify('-1 day')->format('Y-m-d')] as $extensionBadDate) {
        catalogReject(fn()=>EraporExtendPeriod::extend($db,$extensionPid,$extensionActor,$extensionOld,$extensionBadDate,'Alasan',$extensionClock),'Perpanjangan memerlukan');
    }
    catalogReject(fn()=>EraporExtendPeriod::extend($db,$extensionPid,$extensionActor,$extensionOld,'2026-02-30','Alasan',$extensionClock),'Tanggal tidak valid');
    catalogReject(fn()=>EraporExtendPeriod::extend($db,$extensionPid,$extensionActor,'2000-01-01',$extensionNew,'Alasan',$extensionClock),'Tenggat telah berubah');
    $stmt=$other->prepare('SELECT GET_LOCK(?,0)'); $stmt->execute([$lock]);
    try { catalogReject($extend,'Another migration or assessment'); }
    finally { $stmt=$other->prepare('SELECT RELEASE_LOCK(?)'); $stmt->execute([$lock]); }
    $db->exec("CREATE TRIGGER fail_extension BEFORE INSERT ON erapor_periode_perpanjangan FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture extension failure'");
    try { catalogReject($extend,'fixture extension failure'); }
    finally { $db->exec('DROP TRIGGER fail_extension'); }
    catalogCheck(catalogFingerprints($db)===$extensionBefore,'Audit failure rolls back deadline');
    $db->exec('UPDATE users SET is_active=0,updated_at=updated_at WHERE id='.$extensionActor);
    try { catalogReject($extend,'Hanya kepala sekolah'); }
    finally { $db->exec('UPDATE users SET is_active=1,updated_at=updated_at WHERE id='.$extensionActor); }
    $extensionSessions=catalogFingerprints($db,['erapor_sesi']);
    catalogCheck($extend()['result']==='extended','Head extends period');
    catalogCheck(catalogFingerprints($db,['erapor_sesi'])===$extensionSessions,'Extension never changes session status');
    $extensionLog=$db->query('SELECT * FROM erapor_periode_perpanjangan WHERE periode_id='.$extensionPid)->fetch();
    catalogCheck($extensionLog['alasan']===$extensionReason && $extensionLog['tanggal_akhir_lama']===$extensionOld && $extensionLog['tanggal_akhir_baru']===$extensionNew && (int)$extensionLog['diperpanjang_oleh']===$extensionActor,'Exact reason dates and actor audited');
    $extensionFrozen=catalogFingerprints($db);
    catalogCheck($extend()['result']==='already_extended','Exact retry no duplicate');
    catalogCheck(catalogFingerprints($db)===$extensionFrozen,'Retry no rows/timestamps changed');
    catalogReject(fn()=>EraporExtendPeriod::extend($db,$extensionPid,$extensionActor,$extensionOld,$extensionNew,'Different reason',$extensionClock),'Tenggat telah berubah');
    $extensionDoc=(int)$db->query('SELECT dokumen_id FROM erapor_sesi_dokumen WHERE sesi_id='.$extensionSid.' AND urutan=3')->fetchColumn();
    $extensionJob->execute([$extensionEmployee['jabatan_id'],$extensionEmployee['id']]);
    foreach (['BELUM_DIISI','TELAH_DIISI'] as $extensionState) {
        $db->prepare('UPDATE erapor_sesi SET status=? WHERE id=?')->execute([$extensionState,$extensionSid]);
        $extensionCurrent=$db->query('SELECT isi FROM erapor_ummi_catatan WHERE sesi_id='.$extensionSid)->fetchColumn();
        $extensionCurrent=$extensionCurrent===false?null:$extensionCurrent;
        $extensionSave=EraporUmmiEntry::save($db,$extensionSid,$extensionDoc,$extensionActor,[['key'=>'catatan','value'=>'Extended '.$extensionState,'expected'=>$extensionCurrent]],$extensionClock);
        catalogCheck($extensionSave['complete'],'Extension enables assessment in '.$extensionState);
    }
    foreach (['MENUNGGU_TTD','SELESAI'] as $extensionState) {
        $db->prepare('UPDATE erapor_sesi SET status=? WHERE id=?')->execute([$extensionState,$extensionSid]);
        $extensionFrozen=catalogFingerprints($db);
        catalogReject(fn()=>EraporUmmiEntry::save($db,$extensionSid,$extensionDoc,$extensionActor,[],$extensionClock),'terkunci');
        catalogCheck(catalogFingerprints($db)===$extensionFrozen,'Extension cannot reopen '.$extensionState);
    }
} finally {
    $extensionJob->execute([$extensionEmployee['jabatan_id'],$extensionEmployee['id']]);
    $extensionRestore->execute([$extensionOld,$extensionPeriod['updated_at'],$extensionPid]); // Restore disposable legacy fixture, never live DB.
    $db->prepare('UPDATE erapor_sesi SET status=? WHERE id=?')->execute([$extensionSession['status'],$extensionSid]);
}
