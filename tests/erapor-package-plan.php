<?php
require __DIR__.'/../app/models/EraporSessionPolicy.php';
require __DIR__.'/../app/models/EraporCalendar.php';
require __DIR__.'/../app/models/EraporPackagePlan.php';
$composition=require __DIR__.'/../config/erapor-package.php';
$checks=0;
function planCheck(bool $ok): void { global $checks; if (!$ok) throw new RuntimeException('Plan assertion failed'); $checks++; }
function planReject(callable $fn): void {
    try { $fn(); } catch (DomainException $e) { planCheck(true); return; }
    throw new RuntimeException('Invalid plan accepted');
}
$catalog=[];
foreach ($composition as $i=>$entry) if ($entry['kode']!==null) $catalog[]=[
    'id'=>$i+1,'kode'=>$entry['kode'],'jenis_dokumen'=>$entry['jenis'],'cakupan'=>$entry['cakupan'],
    'jenis_periode'=>$entry['periode'],'khusus_abk'=>$entry['khusus_abk'],'status'=>'draft'];
$base=['id'=>1,'tahun_ajaran_id'=>8,'semester'=>'ganjil','tipe'=>'Tengah Semester','awal_periode'=>'2026-09-01','akhir_periode'=>'2026-09-30'];
$build=fn($condition,$row,$rows=null)=>EraporPackagePlan::build(7,$condition,$row,$rows ?? $catalog,$composition);
$regular=$build('Regular',$base); $abk=$build('ABK',$base);
planCheck(array_column($regular['dokumen'],'jenis')===['RTS','AGAMA','UMMI','BING']);
planCheck(array_column($abk['dokumen'],'jenis')===['RTS','AGAMA','UMMI','BING','PPI']);
planCheck(array_column($abk['dokumen'],'urutan')===[1,2,3,4,5]);
planCheck($build('Regular',$base)===$regular);
$genap=$base; $genap['id']=3; $genap['semester']='genap';
$next=$build('Regular',$genap);
foreach ([0,1] as $i) planCheck($regular['dokumen'][$i]['identitas']===$next['dokumen'][$i]['identitas']);
foreach ([2,3] as $i) planCheck($regular['dokumen'][$i]['identitas']!==$next['dokumen'][$i]['identitas']);
planCheck($regular['sesi']!==$next['sesi']);
foreach (['ganjil','genap'] as $s) foreach (['Tengah Semester','Akhir Semester'] as $t) {
    $row=$base; $row['semester']=$s; $row['tipe']=$t;
    $result=$build('ABK',$row);
    planCheck($result['periode']['urutan']===($s==='ganjil'?0:2)+($t==='Tengah Semester'?1:2));
    if ($t==='Akhir Semester') {
        planCheck(!$result['dapat_dibentuk'] && $result['rubrik_belum_tersedia']===['RAS'] && $result['dokumen']===[]);
    }
}
$missing=$catalog; array_pop($missing);
planCheck($build('Regular',$base,$missing)['dapat_dibentuk']);
planCheck($build('ABK',$base,$missing)['rubrik_belum_tersedia']===['PPI']);
foreach (['semester'=>null,'tipe'=>'Unknown','awal_periode'=>'2026-02-30','akhir_periode'=>'2026-08-01','id'=>0] as $key=>$value) {
    $row=$base; $row[$key]=$value; planReject(fn()=>$build('Regular',$row));
}
planReject(fn()=>$build('Unknown',$base));
$bad=$catalog; $bad[0]['status']='arsip'; planReject(fn()=>$build('Regular',$base,$bad));
$bad=$catalog; $bad[0]['cakupan']='SEMESTER'; planReject(fn()=>$build('Regular',$base,$bad));
$bad=$catalog; $bad[]=$bad[0]; planReject(fn()=>$build('Regular',$base,$bad));
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE tahun_ajaran(id INTEGER,tahun_awal INTEGER,tahun_akhir INTEGER)');
$db->exec('CREATE TABLE periode_penilaian(id INTEGER,tahun_ajaran_id INTEGER,semester TEXT,tipe TEXT,awal_periode TEXT,akhir_periode TEXT)');
$db->exec('INSERT INTO tahun_ajaran VALUES(8,2026,2027)');
$q=$db->prepare('INSERT INTO periode_penilaian VALUES(?,?,?,?,?,?)');
$q->execute(array_values($base));
$year=EraporCalendar::year($db,8);
planCheck($year['slot_belum_tersedia']===[2,3,4] && count($year['periode'])===1);
$q->execute(array_values($base)); planReject(fn()=>EraporCalendar::year($db,8));
planReject(fn()=>EraporCalendar::year($db,999));
echo "PASS: $checks calendar/package preflight checks.\n";
