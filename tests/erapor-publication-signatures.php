<?php
// Isolated immutable approval evidence; no application database or user profiles are touched.
require __DIR__.'/../app/models/EraporPublication.php';
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE erapor_sesi_penyetuju(id INTEGER,sesi_id INTEGER,kode TEXT,status TEXT);
CREATE TABLE erapor_persetujuan_snapshot(sesi_penyetuju_id INTEGER,sesi_id INTEGER,nama TEXT,nuptk TEXT,ttd_png BLOB,ttd_sha256 TEXT,ttd_disetujui_pada TEXT);
CREATE TABLE erapor_sesi_penerimaan(sesi_id INTEGER,guru_nama TEXT,guru_nuptk TEXT,guru_ttd_png BLOB,guru_ttd_sha256 TEXT,guru_ttd_disetujui_pada TEXT)');
$db->exec("INSERT INTO erapor_sesi_penyetuju VALUES(1,1,'KOORDINATOR_BING','DISETUJUI'),(2,1,'WALI_KELAS','MENUNGGU'),(3,2,'WALI_KELAS','DISETUJUI');
INSERT INTO erapor_sesi_penerimaan(sesi_id,guru_nama) VALUES(1,'PIC bukan wali'),(9,'Guru snapshot lama')");
$png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
$insert=$db->prepare('INSERT INTO erapor_persetujuan_snapshot VALUES(?,?,?,?,?,?,?)');
$insert->execute([1,1,'Guru Dua Peran',null,$png,hash('sha256',$png),'2026-10-01 08:00:00']);
$insert->execute([3,2,'Wali Kelas Lain',null,$png,hash('sha256',$png),'2026-10-01 08:00:00']);
$method=new ReflectionMethod(EraporPublication::class,'signatureBlock');
$read=static fn($sid)=>$method->invoke(null,$db,['id'=>$sid],[],null,'Makassar');
$checks=0;$check=static function(bool $ok)use(&$checks){if(!$ok)throw new RuntimeException('Publication signer assertion failed');$checks++;};
$before=$read(1);
$check(isset($before['signers']['KOORDINATOR_BING'])&&!isset($before['signers']['GURU_KELAS']));
$check(!str_contains(json_encode($before),'PIC bukan wali')&&!str_contains(json_encode($before),'Wali Kelas Lain'));
$insert->execute([2,1,'Guru Dua Peran',null,$png,hash('sha256',$png),'2026-10-01 09:00:00']);
$db->exec("UPDATE erapor_sesi_penyetuju SET status='DISETUJUI' WHERE id=2");
$after=$read(1);
$check($after['signers']['GURU_KELAS']['nama']==='Guru Dua Peran');
$check($after['signers']['KOORDINATOR_BING']['nama']===$after['signers']['GURU_KELAS']['nama']);
$check($after['signers']['GURU_KELAS']['consent_at']!==$after['signers']['KOORDINATOR_BING']['consent_at']);
$check($read(2)['signers']['GURU_KELAS']['nama']==='Wali Kelas Lain');
$check($read(9)['signers']['GURU_KELAS']['nama']==='Guru snapshot lama');
echo "PASS: $checks publication signer checks (separate role evidence, class/session isolation, historical fallback).\n";
