<?php
// Isolated database: no production data, accounts or reports are changed.
class Database { public static PDO $db; public static function getInstance(): PDO { return self::$db; } }
require __DIR__ . '/../app/core/Model.php';
foreach (['PeriodePenilaian','SesiPembagianRapor','Rapor','ReportWorkflow','ReportPreview'] as $model) require __DIR__ . '/../app/models/' . $model . '.php';
$db=Database::$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE tahun_ajaran(id INTEGER PRIMARY KEY);
CREATE TABLE periode_penilaian(id INTEGER PRIMARY KEY,tahun_ajaran_id INTEGER,semester TEXT,nama TEXT,tipe TEXT,kategori TEXT,awal_periode TEXT,akhir_periode TEXT,UNIQUE(tahun_ajaran_id,semester,tipe));
CREATE TABLE template_rapor(id INTEGER PRIMARY KEY,nama TEXT);
CREATE TABLE sesi_pembagian_rapor(id INTEGER PRIMARY KEY,periode_id INTEGER,template_id INTEGER,nama TEXT,tanggal_mulai TEXT,tanggal_selesai TEXT);
CREATE TABLE rapor(id INTEGER PRIMARY KEY,murid_id INTEGER,sesi_pembagian_id INTEGER,template_id INTEGER,guru_id INTEGER,status TEXT,UNIQUE(murid_id,sesi_pembagian_id));
CREATE TABLE rapor_nilai(rapor_id INTEGER,semester TEXT);
CREATE TABLE kelas(id INTEGER PRIMARY KEY,tahun_ajaran_id INTEGER);
CREATE TABLE murid(id INTEGER PRIMARY KEY,kelas_id INTEGER,status TEXT);
CREATE TABLE kelas_guru_murid(murid_id INTEGER,guru_id INTEGER);
INSERT INTO tahun_ajaran VALUES(1),(2);
INSERT INTO template_rapor VALUES(1,'Rapor Montessori Tengah Semester'),(2,'Rapor Montessori Akhir Semester');
INSERT INTO kelas VALUES(1,1),(2,2);
INSERT INTO murid VALUES(1,1,'bersekolah'),(2,1,'bersekolah'),(3,2,'bersekolah'),(4,1,'berhenti');
INSERT INTO kelas_guru_murid VALUES(1,7),(2,8);");
function checkReport(bool $ok,string $message): void { if(!$ok) throw new Exception($message); }
$base=['tahun_ajaran_id'=>1,'nama'=>'Periode Uji','kategori'=>'Rapor Murid','awal_periode'=>'2026-01-01','akhir_periode'=>'2026-06-01'];
foreach(['ganjil','genap'] as $semester) foreach(ReportWorkflow::TYPES as $type) ReportWorkflow::savePeriod($base+['semester'=>$semester,'tipe'=>$type]);
checkReport((int)$db->query('SELECT COUNT(*) FROM periode_penilaian')->fetchColumn()===4,'Four slots per school year');
checkReport((int)$db->query('SELECT COUNT(*) FROM rapor')->fetchColumn()===8,'Only active students in matching school year');
checkReport((int)$db->query("SELECT COUNT(*) FROM rapor WHERE guru_id IN (7,8) AND status='belum_diisi'")->fetchColumn()===8,'Teacher ownership and initial status');
try { ReportWorkflow::savePeriod($base+['semester'=>'ganjil','tipe'=>'Tengah Semester']); throw new Exception('Duplicate accepted'); } catch(DomainException $e) {}
try { ReportWorkflow::savePeriod($base+['semester'=>'other','tipe'=>'Tengah Semester']); throw new Exception('Invalid semester accepted'); } catch(DomainException $e) {}
$changed=$base+['semester'=>'ganjil','tipe'=>'Tengah Semester'];$changed['nama']='Updated';
ReportWorkflow::savePeriod($changed,1);
checkReport((int)$db->query('SELECT COUNT(*) FROM rapor')->fetchColumn()===8,'Edit is idempotent');
checkReport($db->query('SELECT nama FROM sesi_pembagian_rapor WHERE periode_id=1')->fetchColumn()==='Updated','Session metadata follows period');
$db->exec("INSERT INTO periode_penilaian VALUES(10,2,NULL,'Legacy','Tengah Semester','Rapor Murid','2026-01-01','2026-06-01')");
ReportWorkflow::syncPeriod(10);
checkReport((int)$db->query('SELECT COUNT(*) FROM sesi_pembagian_rapor WHERE periode_id=10')->fetchColumn()===0,'Unknown legacy semester not inferred');
$next=$changed;$next['tahun_ajaran_id']=2;ReportWorkflow::savePeriod($next,10);
checkReport((int)$db->query('SELECT COUNT(*) FROM rapor WHERE murid_id=3')->fetchColumn()===1,'Classify legacy period generates reports');
$items=[];for($i=1;$i<=120;$i++)$items[]=['nama_tujuan'=>'Tujuan '.$i,'nilai_ganjil'=>'slash','nilai_genap'=>null];
$pages=ReportPreview::pages([['nama_area'=>'Area','subkategori'=>[['label'=>'a','nama'=>'Kategori','item'=>$items]]]]);
$count=0;foreach($pages as $page)foreach($page['columns'] as $column)foreach($column as $section)foreach($section['groups'] as $group)$count+=count($group['items']);
checkReport($count===120 && count($pages)>1,'Pagination retains all real items without duplication');
echo "PASS: four semester/type slots, duplicate guard, correct year/template/teacher, automatic reports, idempotent editing, legacy classification and pagination.\n";
