<?php
// Only included by the isolated restore harness; never apply to application DB.
require_once ROOT_PATH.'/app/models/EraporBingSeed.php';
$bingFile=ROOT_PATH.'/database/migrations/20260924_erapor_bing.sql';
catalogCheck(EraporMigrationRunner::apply($db,$bingFile)==='applied','BING migration');
catalogCheck(EraporMigrationRunner::apply($db,$bingFile)==='already_applied','BING migration retry');
$bing=EraporBingSeed::load(ROOT_PATH.'/eRapor_Zivana_Spesifikasi/rubrik_bing_seed.json');
$initial=catalogFingerprints($db);
$db->exec("CREATE TRIGGER fail_bing_import BEFORE INSERT ON erapor_bing_komentar FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture seed failure'");
try { catalogReject(fn()=>EraporBingSeed::apply($db,$bing),'fixture seed failure'); }
finally { $db->exec('DROP TRIGGER fail_bing_import'); }
catalogCheck(catalogFingerprints($db)===$initial,'BING all rows rolled back');
$stmt=$other->prepare('SELECT GET_LOCK(?,0)'); $stmt->execute([$lock]);
try { catalogReject(fn()=>EraporBingSeed::apply($db,$bing),'Another migration'); }
finally { $stmt=$other->prepare('SELECT RELEASE_LOCK(?)'); $stmt->execute([$lock]); }
catalogCheck(EraporBingSeed::apply($db,$bing)==='seeded','BING seeded');
$id=(int)$db->query("SELECT id FROM erapor_rubrik WHERE kode='BING_V1'")->fetchColumn();
foreach (['erapor_bing_skala'=>4,'erapor_bing_indikator'=>5,'erapor_bing_komentar'=>4] as $table=>$count) {
    catalogCheck((int)$db->query('SELECT COUNT(*) FROM '.$table)->fetchColumn()===$count,$table.' count');
}
foreach (['skala'=>['erapor_bing_skala',['label','definisi']], 'indikator'=>['erapor_bing_indikator',['label_cetak','grup']],
    'komentar'=>['erapor_bing_komentar',['label_cetak','penanda_cetak']]] as $key=>[$table,$columns]) {
    foreach ($bing[$key] as $row) {
        $q=$db->prepare('SELECT '.implode(',',$columns).' FROM '.$table.' WHERE rubrik_id=? AND kode=?');
        $q->execute([$id,$row['kode']]);
        $expected=[];
        foreach ($columns as $column) $expected[$column]=$row[$column];
        catalogCheck($q->fetch()===$expected,'BING exact definition text');
    }
}
$snapshot=json_decode($db->query('SELECT seed_json FROM erapor_rubrik_sumber WHERE rubrik_id='.$id)->fetchColumn(),true,512,JSON_THROW_ON_ERROR);
catalogCheck($snapshot==$bing,'BING source/print metadata retained');
catalogCheck((int)$db->query("SELECT COUNT(*) FROM erapor_bing_indikator WHERE label_cetak='Speaking Test Result'")->fetchColumn()===0,'Group not counted as indicator');
catalogCheck((int)$db->query('SELECT COUNT(*) FROM erapor_bing_indikator WHERE wajib=1')->fetchColumn()+
    (int)$db->query('SELECT COUNT(*) FROM erapor_bing_komentar WHERE wajib=1')->fetchColumn()===9,'Nine required definitions');
$stable=catalogFingerprints($db);
catalogCheck(EraporBingSeed::apply($db,$bing)==='already_seeded','BING retry');
catalogCheck(catalogFingerprints($db)===$stable,'BING retry no writes');
$changed=$bing; $changed['indikator'][3]['label_cetak']='Pronunciation';
catalogReject(fn()=>EraporBingSeed::apply($db,$changed),'Seed changed');
catalogCheck(catalogFingerprints($db)===$stable,'Changed source cannot overwrite BING');
$db->exec("UPDATE erapor_rubrik SET status='terkunci' WHERE id=$id");
catalogCheck(EraporBingSeed::apply($db,$bing)==='already_seeded','BING locked retry');
catalogReject(fn()=>EraporBingSeed::apply($db,$changed),'Seed changed');
$db->exec("UPDATE erapor_bing_indikator SET label_cetak='Drift' WHERE kode='attendance'");
catalogReject(fn()=>EraporBingSeed::apply($db,$bing),'Stored rubric drift');
$q=$db->prepare("UPDATE erapor_bing_indikator SET label_cetak=? WHERE kode='attendance'");
$q->execute([$bing['indikator'][0]['label_cetak']]);
catalogCheck(EraporBingSeed::apply($db,$bing)==='already_seeded','BING restored');
catalogReject(fn()=>$db->exec('UPDATE erapor_bing_skala SET peringkat=5 WHERE rubrik_id='.$id),'Check constraint');
catalogReject(fn()=>$db->exec("UPDATE erapor_bing_indikator SET kode='attendance' WHERE kode='written_test'"),'Duplicate');
catalogReject(fn()=>$db->exec('UPDATE erapor_bing_komentar SET rubrik_id=999999'),'foreign key');
catalogCheck((int)$db->query('SELECT COUNT(*) FROM erapor_seed_history')->fetchColumn()===4,'Four official rubrics tracked');
