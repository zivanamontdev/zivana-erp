<?php
// Included only from the disposable-DB harness after catalog + RTS migrations.
require_once ROOT_PATH.'/app/models/EraporUmmiPpiSeed.php';
$specialFile=ROOT_PATH.'/database/migrations/20260924_erapor_ummi_ppi.sql';
catalogCheck(EraporMigrationRunner::apply($db,$specialFile)==='applied','Ummi/PPI schema');
catalogCheck(EraporMigrationRunner::apply($db,$specialFile)==='already_applied','Ummi/PPI schema retry');
foreach (['ummi'=>'erapor_ummi_materi','ppi'=>'erapor_ppi_kolom'] as $kind=>$failureTable) {
    $special=EraporUmmiPpiSeed::load(ROOT_PATH.'/eRapor_Zivana_Spesifikasi/rubrik_'.$kind.'_seed.json');
    $initial=catalogFingerprints($db);
    $db->exec("CREATE TRIGGER fail_special_import BEFORE INSERT ON $failureTable FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture seed failure'");
    try { catalogReject(fn()=>EraporUmmiPpiSeed::apply($db,$special),'fixture seed failure'); }
    finally { $db->exec('DROP TRIGGER fail_special_import'); }
    catalogCheck(catalogFingerprints($db)===$initial,'Full seed rollback '.$kind);
    $stmt=$other->prepare('SELECT GET_LOCK(?,0)'); $stmt->execute([$lock]);
    try { catalogReject(fn()=>EraporUmmiPpiSeed::apply($db,$special),'Another migration'); }
    finally { $stmt=$other->prepare('SELECT RELEASE_LOCK(?)'); $stmt->execute([$lock]); }
    catalogCheck(EraporUmmiPpiSeed::apply($db,$special)==='seeded','Official '.$kind.' seeded');
    $stable=catalogFingerprints($db);
    catalogCheck(EraporUmmiPpiSeed::apply($db,$special)==='already_seeded','Retry '.$kind);
    catalogCheck(catalogFingerprints($db)===$stable,'Retry changes nothing '.$kind);
    $changed=$special; $changed['rubrik']['nama']='Changed';
    catalogReject(fn()=>EraporUmmiPpiSeed::apply($db,$changed),'Seed changed');
    catalogCheck(catalogFingerprints($db)===$stable,'Source change rejected '.$kind);
    $id=(int)$db->query('SELECT id FROM erapor_rubrik WHERE kode='.$db->quote($special['rubrik']['kode']))->fetchColumn();
    $stored=json_decode($db->query('SELECT seed_json FROM erapor_rubrik_sumber WHERE rubrik_id='.$id)->fetchColumn(),true,512,JSON_THROW_ON_ERROR);
    catalogCheck($stored==$special,'All print metadata/source text retained '.$kind);
    $q=$db->prepare('UPDATE erapor_rubrik_sumber SET seed_json=? WHERE rubrik_id=?');
    $q->execute(['{}',$id]);
    catalogReject(fn()=>EraporUmmiPpiSeed::apply($db,$special),'Stored rubric drift');
    $q->execute([json_encode($special,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$id]);
    $db->exec("UPDATE erapor_rubrik SET status='terkunci' WHERE id=$id");
    catalogCheck(EraporUmmiPpiSeed::apply($db,$special)==='already_seeded','Locked no-op '.$kind);
    catalogReject(fn()=>EraporUmmiPpiSeed::apply($db,$changed),'Seed changed');
    if ($kind==='ummi') {
        foreach (['erapor_ummi_jilid'=>7,'erapor_ummi_materi'=>27,'erapor_skala_huruf'=>12] as $table=>$n) {
            catalogCheck((int)$db->query('SELECT COUNT(*) FROM '.$table)->fetchColumn()===$n,$table.' count');
        }
        catalogCheck((int)$db->query('SELECT COUNT(*) FROM erapor_rubrik_bagian WHERE rubrik_id='.$id.' AND wajib=1')->fetchColumn()===1,'Only Ummi note mandatory');
        foreach ($special['jilid'] as $j) foreach ($j['materi'] as $m) {
            $q=$db->prepare('SELECT teks FROM erapor_ummi_materi WHERE kode=?'); $q->execute([$m['kode']]);
            catalogCheck($q->fetchColumn()===$m['teks'],'Exact Ummi text');
        }
        catalogReject(fn()=>$db->exec('UPDATE erapor_skala_huruf SET peringkat=13 WHERE rubrik_id='.$id),'Check constraint');
        $q=$db->prepare('UPDATE erapor_ummi_materi SET teks=? WHERE kode=?');
        $m=$special['jilid'][0]['materi'][0]; $q->execute(['Drift',$m['kode']]);
        catalogReject(fn()=>EraporUmmiPpiSeed::apply($db,$special),'Stored rubric drift');
        $q->execute([$m['teks'],$m['kode']]);
    } else {
        catalogCheck((int)$db->query('SELECT COUNT(*) FROM erapor_ppi_aspek')->fetchColumn()===5,'Five PPI aspects');
        catalogCheck((int)$db->query('SELECT COUNT(*) FROM erapor_ppi_kolom')->fetchColumn()===8,'Eight printed PPI columns');
        catalogCheck((int)$db->query('SELECT COUNT(*) FROM erapor_ppi_aspek a JOIN erapor_ppi_kolom c ON c.rubrik_id=a.rubrik_id WHERE c.wajib=1 AND c.diisi_di_sesi=1')->fetchColumn()===30,'Thirty mandatory PPI textareas');
        catalogCheck((int)$db->query('SELECT COUNT(*) FROM erapor_ppi_kolom WHERE wajib=0 AND diisi_di_sesi=0')->fetchColumn()===2,'Outcomes excluded from session');
        foreach ($special['kolom'] as $c) {
            $q=$db->prepare('SELECT label_cetak,grup_cetak,lebar_cetak_cm FROM erapor_ppi_kolom WHERE kode=?'); $q->execute([$c['kode']]); $row=$q->fetch();
            catalogCheck($row['label_cetak']===$c['label_cetak'] && $row['grup_cetak']===$c['grup_cetak']
                && (float)$row['lebar_cetak_cm']===(float)$c['lebar_cetak_cm'],'Exact PPI label/group/width');
        }
        catalogReject(fn()=>$db->exec("UPDATE erapor_ppi_kolom SET wajib=1 WHERE kode='hasil_capaian_guru'"),'Check constraint');
        catalogReject(fn()=>$db->exec("UPDATE erapor_ppi_kolom SET bagian='UNKNOWN' WHERE kode='media'"),'foreign key');
        $db->exec("UPDATE erapor_ppi_kolom SET label_cetak='Drift' WHERE kode='media'");
        catalogReject(fn()=>EraporUmmiPpiSeed::apply($db,$special),'Stored rubric drift');
        $db->exec("UPDATE erapor_ppi_kolom SET label_cetak='Media' WHERE kode='media'");
    }
    catalogCheck(EraporUmmiPpiSeed::apply($db,$special)==='already_seeded','Drift restored '.$kind);
}
catalogCheck((int)$db->query('SELECT COUNT(*) FROM erapor_seed_history')->fetchColumn()===3,'Three official rubrics tracked');
