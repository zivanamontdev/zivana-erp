<?php
require_once ROOT_PATH.'/app/models/EraporSignerProfile.php';
$signerAuditMigration=ROOT_PATH.'/database/migrations/20260925_erapor_signer_profile_audit.sql';
catalogCheck(EraporMigrationRunner::apply($db,$signerAuditMigration)==='applied','Signer profile audit schema');
catalogCheck(EraporMigrationRunner::apply($db,$signerAuditMigration)==='already_applied','Signer profile audit retry');
$signerSuffix=bin2hex(random_bytes(4));
$signerRoleQuery=$db->prepare('INSERT INTO roles(nama) VALUES(?)'); $signerRoleQuery->execute(['Signer Fixture '.$signerSuffix]);
$signerRole=(int)$db->lastInsertId();
$signerPositionQuery=$db->prepare('SELECT id FROM jabatan WHERE nama=? ORDER BY id LIMIT 1');
$signerPositionQuery->execute(['Guru Kelas']); $signerPosition=(int)$signerPositionQuery->fetchColumn();
$signerEmployee=$db->prepare('INSERT INTO karyawan(jabatan_id,nama,is_active) VALUES(?,?,1)');
$signerEmployee->execute([$signerPosition,'Signer Fixture']); $signerEmployeeId=(int)$db->lastInsertId();
$signerUser=$db->prepare('INSERT INTO users(karyawan_id,role_id,email,password_hash,is_active) VALUES(?,?,?,?,1)');
$signerUser->execute([$signerEmployeeId,$signerRole,'signer-'.$signerSuffix.'@example.test','fixture-hash']); $signerUserId=(int)$db->lastInsertId();
$signerOtherEmployee=$db->prepare('INSERT INTO karyawan(jabatan_id,nama,is_active) VALUES(?,?,1)');
$signerOtherEmployee->execute([$signerPosition,'Other Signer Fixture']); $signerOtherEmployeeId=(int)$db->lastInsertId();
$signerOtherUser=$db->prepare('INSERT INTO users(karyawan_id,role_id,email,password_hash,is_active) VALUES(?,?,?,?,1)');
$signerOtherUser->execute([$signerOtherEmployeeId,$signerRole,'other-signer-'.$signerSuffix.'@example.test','fixture-hash']); $signerOtherUserId=(int)$db->lastInsertId();
$signerInitial=EraporSignerProfile::read($db,$signerUserId);
catalogCheck($signerInitial===['nuptk'=>null,'has_signature'=>false,'consented_at'=>null],'New owner profile starts empty without implicit consent');
catalogReject(fn()=>EraporSignerProfile::save($db,$signerUserId,'123456789012345','',true),'NUPTK harus 16 digit');
catalogReject(fn()=>EraporSignerProfile::save($db,$signerUserId,'1234567890123456','not PNG',true),'PNG tanda tangan');
$signerImage=imagecreatetruecolor(64,24); imagealphablending($signerImage,false); imagesavealpha($signerImage,true);
$signerTransparent=imagecolorallocatealpha($signerImage,255,255,255,127); imagefill($signerImage,0,0,$signerTransparent);
$signerInk=imagecolorallocatealpha($signerImage,20,20,20,0); imageline($signerImage,2,18,60,4,$signerInk);
ob_start(); imagepng($signerImage); $signerPng=ob_get_clean(); imagedestroy($signerImage);
catalogReject(fn()=>EraporSignerProfile::save($db,$signerUserId,'1234567890123456',$signerPng,false),'Persetujuan pemilik wajib');
$signerSaved=EraporSignerProfile::save($db,$signerUserId,'1234567890123456',$signerPng,true);
$signerProfile=EraporSignerProfile::read($db,$signerUserId); $signerStoredPng=EraporSignerProfile::signature($db,$signerUserId);
catalogCheck($signerSaved['changed'] && $signerProfile['has_signature'] && $signerProfile['nuptk']==='1234567890123456'
    && $signerProfile['consented_at']!==null && is_string($signerStoredPng) && getimagesizefromstring($signerStoredPng)[2]===IMAGETYPE_PNG,
    'Owner can save a normalized private PNG with explicit NUPTK consent');
catalogCheck((int)$db->query("SELECT COUNT(*) FROM erapor_profil_penandatangan_audit WHERE user_id=$signerUserId AND aksi='SETUJUI_TANDA_TANGAN' AND versi_persetujuan='signature-consent-v1'")->fetchColumn()===1,
    'Signature consent audit stores version and owner identity');
$signerNoop=EraporSignerProfile::save($db,$signerUserId,'1234567890123456',null,false);
catalogCheck(!$signerNoop['changed'] && (int)$db->query('SELECT COUNT(*) FROM erapor_profil_penandatangan_audit WHERE user_id='.$signerUserId)->fetchColumn()===1,
    'Unchanged owner profile does not create audit noise');
$signerNuptk=EraporSignerProfile::save($db,$signerUserId,'0000000000000001',null,false);
catalogCheck($signerNuptk['has_signature'] && EraporSignerProfile::read($db,$signerUserId)['consented_at']===$signerProfile['consented_at'],
    'NUPTK-only update preserves existing signature consent');
$signerOther=EraporSignerProfile::read($db,$signerOtherUserId);
catalogCheck(!$signerOther['has_signature'] && EraporSignerProfile::signature($db,$signerOtherUserId)===null,
    'Another account receives only its own empty signer profile');
$signerAuditBefore=(int)$db->query('SELECT COUNT(*) FROM erapor_profil_penandatangan_audit WHERE user_id='.$signerUserId)->fetchColumn();
$db->exec("CREATE TRIGGER fail_signer_profile_audit BEFORE INSERT ON erapor_profil_penandatangan_audit FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture signer audit failure'");
try {
    catalogReject(fn()=>EraporSignerProfile::save($db,$signerUserId,'0000000000000002',null,false),'fixture signer audit failure');
} finally { $db->exec('DROP TRIGGER fail_signer_profile_audit'); }
catalogCheck(EraporSignerProfile::read($db,$signerUserId)['nuptk']==='0000000000000001'
    && (int)$db->query('SELECT COUNT(*) FROM erapor_profil_penandatangan_audit WHERE user_id='.$signerUserId)->fetchColumn()===$signerAuditBefore,
    'Profile mutation rolls back if its audit insert fails');
$signerRevoked=EraporSignerProfile::revoke($db,$signerUserId);
$signerAfterRevoke=EraporSignerProfile::read($db,$signerUserId);
catalogCheck($signerRevoked['changed'] && !$signerAfterRevoke['has_signature'] && $signerAfterRevoke['consented_at']===null
    && $signerAfterRevoke['nuptk']==='0000000000000001' && EraporSignerProfile::signature($db,$signerUserId)===null,
    'Owner can revoke future signature use while retaining NUPTK');
catalogCheck((int)$db->query("SELECT COUNT(*) FROM erapor_profil_penandatangan_audit WHERE user_id=$signerUserId AND aksi='CABUT_TANDA_TANGAN' AND ttd_sha256 IS NOT NULL")->fetchColumn()===1,
    'Revocation audit retains a hash of the revoked signature');
$signerRevokeAgain=EraporSignerProfile::revoke($db,$signerUserId);
catalogCheck(!$signerRevokeAgain['changed'],'Repeated revocation is an idempotent no-op');
$db->prepare('DELETE FROM erapor_profil_penandatangan_audit WHERE user_id IN (?,?)')->execute([$signerUserId,$signerOtherUserId]);
$db->prepare('DELETE FROM erapor_profil_penandatangan WHERE user_id IN (?,?)')->execute([$signerUserId,$signerOtherUserId]);
$db->prepare('DELETE FROM users WHERE id IN (?,?)')->execute([$signerUserId,$signerOtherUserId]);
$db->prepare('DELETE FROM karyawan WHERE id IN (?,?)')->execute([$signerEmployeeId,$signerOtherEmployeeId]);
$db->prepare('DELETE FROM roles WHERE id=?')->execute([$signerRole]);
