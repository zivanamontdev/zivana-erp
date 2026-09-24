<?php
// Included only by disposable restore harness; no application-DB migration.
require_once ROOT_PATH.'/app/models/EraporAgamaSeed.php';
$agamaFile=ROOT_PATH.'/database/migrations/20260924_erapor_agama.sql';
catalogCheck(EraporMigrationRunner::apply($db,$agamaFile)==='applied','Agama schema');
catalogCheck(EraporMigrationRunner::apply($db,$agamaFile)==='already_applied','Agama schema retry');
$agama=EraporAgamaSeed::load(ROOT_PATH.'/eRapor_Zivana_Spesifikasi/rubrik_agama_seed.json');
$initial=catalogFingerprints($db);
$db->exec("CREATE TRIGGER fail_agama_import BEFORE INSERT ON erapor_agama_item_nama FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture seed failure'");
try { catalogReject(fn()=>EraporAgamaSeed::apply($db,$agama),'fixture seed failure'); }
finally { $db->exec('DROP TRIGGER fail_agama_import'); }
catalogCheck(catalogFingerprints($db)===$initial,'Agama full rollback');
$stmt=$other->prepare('SELECT GET_LOCK(?,0)'); $stmt->execute([$lock]);
try { catalogReject(fn()=>EraporAgamaSeed::apply($db,$agama),'Another migration'); }
finally { $stmt=$other->prepare('SELECT RELEASE_LOCK(?)'); $stmt->execute([$lock]); }
catalogCheck(EraporAgamaSeed::apply($db,$agama)==='seeded','Official Agama seeded');
$id=(int)$db->query("SELECT id FROM erapor_rubrik WHERE kode='AGAMA_V1'")->fetchColumn();
foreach (['lingkup'=>6,'sub'=>8,'item'=>73,'item_nama'=>99,'tahapan'=>5,'subtingkat'=>3,'pilihan'=>7] as $suffix=>$count) {
    catalogCheck((int)$db->query('SELECT COUNT(*) FROM erapor_agama_'.$suffix)->fetchColumn()===$count,'Agama '.$suffix.' count');
}
foreach (['GANJIL'=>37,'GENAP'=>36] as $semester=>$count) {
    catalogCheck((int)$db->query('SELECT COUNT(*) FROM erapor_agama_item WHERE semester='.$db->quote($semester))->fetchColumn()===$count,'Agama semester count');
}
catalogCheck((int)$db->query('SELECT COUNT(*) FROM erapor_agama_lingkup WHERE catatan_wajib=1')->fetchColumn()===6,'Six mandatory narrative definitions');
foreach ($agama['ruang_lingkup'] as $scope) foreach ($scope['sub'] as $sub) foreach ($sub['item'] as $item) {
    $q=$db->prepare('SELECT id,teks,semester FROM erapor_agama_item WHERE kode=?'); $q->execute([$item['kode']]); $stored=$q->fetch();
    catalogCheck($stored['teks']===$item['teks'] && $stored['semester']===$item['semester'],'Exact Agama item/semester');
    $q=$db->prepare('SELECT nama FROM erapor_agama_item_nama WHERE item_id=? ORDER BY urutan'); $q->execute([$stored['id']]);
    catalogCheck($q->fetchAll(PDO::FETCH_COLUMN)===($item['nama'] ?? []),'Exact grouped names');
}
foreach ($agama['rubrik']['skala']['pilihan_dropdown'] as $p) {
    $q=$db->prepare('SELECT label,tahapan_kode,subtingkat_kode,kolom_cetak FROM erapor_agama_pilihan WHERE rubrik_id=? AND urutan=?'); $q->execute([$id,$p['urutan']]);
    catalogCheck($q->fetch()===['label'=>$p['label'],'tahapan_kode'=>$p['tahapan'],'subtingkat_kode'=>$p['subtingkat'],'kolom_cetak'=>$p['kolom_cetak']],'Choice print mapping');
}
$snapshot=json_decode($db->query('SELECT seed_json FROM erapor_rubrik_sumber WHERE rubrik_id='.$id)->fetchColumn(),true,512,JSON_THROW_ON_ERROR);
catalogCheck($snapshot==$agama,'Complete Agama source retained');
$stable=catalogFingerprints($db);
catalogCheck(EraporAgamaSeed::apply($db,$agama)==='already_seeded','Agama retry');
catalogCheck(catalogFingerprints($db)===$stable,'Agama retry no writes');
$changed=$agama; $changed['ruang_lingkup'][0]['sub'][0]['item'][0]['teks']='Changed';
catalogReject(fn()=>EraporAgamaSeed::apply($db,$changed),'Seed changed');
catalogCheck(catalogFingerprints($db)===$stable,'Agama changed source no writes');
$db->exec("UPDATE erapor_rubrik SET status='terkunci' WHERE id=$id");
catalogCheck(EraporAgamaSeed::apply($db,$agama)==='already_seeded','Agama locked retry');
catalogReject(fn()=>EraporAgamaSeed::apply($db,$changed),'Seed changed');
$q=$db->prepare('UPDATE erapor_agama_item SET teks=? WHERE kode=?');
$first=$agama['ruang_lingkup'][0]['sub'][0]['item'][0];
$q->execute(['Drift',$first['kode']]);
catalogReject(fn()=>EraporAgamaSeed::apply($db,$agama),'Stored rubric drift');
$q->execute([$first['teks'],$first['kode']]);
catalogReject(fn()=>$db->exec("UPDATE erapor_agama_pilihan SET subtingkat_id=NULL,subtingkat_kode=NULL WHERE urutan=3"),'Check constraint');
$levelId=(int)$db->query("SELECT id FROM erapor_agama_subtingkat WHERE kode='D'")->fetchColumn();
catalogReject(fn()=>$db->exec("UPDATE erapor_agama_pilihan SET subtingkat_id=$levelId,subtingkat_kode='D' WHERE urutan=1"),'Check constraint');
catalogReject(fn()=>$db->exec("UPDATE erapor_agama_pilihan SET subtingkat_kode='J' WHERE urutan=3"),'foreign key');
catalogReject(fn()=>$db->exec('UPDATE erapor_agama_pilihan SET rubrik_id=999999'),'foreign key');
$teladan=(int)$db->query("SELECT id FROM erapor_agama_tahapan WHERE kode='TELADAN'")->fetchColumn();
catalogReject(fn()=>$db->exec("UPDATE erapor_agama_subtingkat SET tahapan_id=$teladan,tahapan_kode='TELADAN' WHERE kode='D'"),'Check constraint');
catalogReject(fn()=>$db->exec("UPDATE erapor_agama_item_nama SET urutan=11 WHERE urutan=1"),'Check constraint');
catalogReject(fn()=>$db->exec("UPDATE erapor_agama_item SET kode=".$db->quote($first['kode'])." WHERE kode='i_aqidah_tauhid__rukun_islam'"),'Duplicate');
catalogCheck(EraporAgamaSeed::apply($db,$agama)==='already_seeded','Agama unchanged after rejected mutations');
catalogCheck((int)$db->query('SELECT COUNT(*) FROM erapor_seed_history')->fetchColumn()===5,'Five official rubrics tracked');
