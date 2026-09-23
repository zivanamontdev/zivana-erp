<?php
// Isolated fixtures; never connects to or modifies application data.
define('ROOT_PATH',dirname(__DIR__)); define('VIEW_PATH',ROOT_PATH.'/app/views'); define('BASE_PATH','');
class Database { public static PDO $db; public static function getInstance():PDO{return self::$db;} }
class Controller {
    public array $data=[]; public array $inputs=[]; public ?string $redirected=null;
    protected function middleware(...$args){}
    protected function input($key,$default=null){return $this->inputs[$key]??$default;}
    protected function view($name,$data){$this->data=$data;}
    protected function redirect($path){$this->redirected=$path;}
}
class RoleMiddleware {public function check(...$args){return true;}}
spl_autoload_register(function($class){foreach(['models','controllers'] as $folder){$file=ROOT_PATH.'/app/'.$folder.'/'.$class.'.php';if(is_file($file)){require $file;return;}}});
require ROOT_PATH.'/app/core/Model.php'; require ROOT_PATH.'/app/helpers/functions.php'; require ROOT_PATH.'/app/helpers/ui.php';
$db=Database::$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE tahun_ajaran(id INTEGER PRIMARY KEY,tahun_awal TEXT,tahun_akhir TEXT,is_active INTEGER);
CREATE TABLE periode_penilaian(id INTEGER PRIMARY KEY,semester TEXT,tipe TEXT,tahun_ajaran_id INTEGER);
CREATE TABLE sesi_pembagian_rapor(id INTEGER PRIMARY KEY,periode_id INTEGER,nama TEXT,tanggal_mulai TEXT,tanggal_selesai TEXT);
CREATE TABLE kelas(id INTEGER PRIMARY KEY,nama_kelas TEXT,level_kelas TEXT);
CREATE TABLE murid(id INTEGER PRIMARY KEY,nama_lengkap TEXT,nisn TEXT,kelas_id INTEGER);
CREATE TABLE kelas_guru_murid(murid_id INTEGER,guru_id INTEGER);
CREATE TABLE rapor(id INTEGER PRIMARY KEY,murid_id INTEGER,guru_id INTEGER,sesi_pembagian_id INTEGER,template_id INTEGER,status TEXT);
CREATE TABLE template_rapor_area(id INTEGER PRIMARY KEY,template_id INTEGER,nama_area TEXT,display_order INTEGER);
CREATE TABLE template_rapor_subkategori(id INTEGER PRIMARY KEY,area_id INTEGER,label TEXT,nama TEXT,display_order INTEGER);
CREATE TABLE template_rapor_item(id INTEGER PRIMARY KEY,subkategori_id INTEGER,nama_tujuan TEXT,skala_nilai_id INTEGER,display_order INTEGER);
CREATE TABLE skala_nilai_opsi(id INTEGER PRIMARY KEY,skala_id INTEGER,simbol TEXT,label TEXT,display_order INTEGER);
CREATE TABLE rapor_nilai(id INTEGER PRIMARY KEY,rapor_id INTEGER,item_id INTEGER,semester TEXT,skala_nilai_opsi_id INTEGER,UNIQUE(rapor_id,item_id,semester));
CREATE TABLE rapor_catatan_guru(id INTEGER PRIMARY KEY,rapor_id INTEGER,area_id INTEGER,catatan TEXT,UNIQUE(rapor_id,area_id));
INSERT INTO tahun_ajaran VALUES(1,'2026','2027',1);
INSERT INTO periode_penilaian VALUES(1,'ganjil','Tengah Semester',1),(2,'genap','Tengah Semester',1),(3,'ganjil','Akhir Semester',1),(4,'genap','Akhir Semester',1);
INSERT INTO sesi_pembagian_rapor VALUES(1,1,'Current','2026-09-01','2026-09-30'),(2,2,'Past','2026-03-01','2026-03-31'),(3,3,'Next','2026-12-01','2026-12-20'),(4,4,'Other teacher','2026-09-20','2026-09-29');
INSERT INTO kelas VALUES(1,'Akasia','Ranting');
INSERT INTO murid VALUES(1,'Murid A','123',1),(2,'Murid B','456',1);
INSERT INTO kelas_guru_murid VALUES(1,7),(2,8);
INSERT INTO rapor VALUES(1,1,7,1,1,'belum_diisi'),(2,1,7,2,1,'disetujui'),(3,1,7,3,1,'belum_diisi'),(4,2,8,4,1,'belum_diisi');
INSERT INTO template_rapor_area VALUES(1,1,'Area',1),(2,2,'Other template',1);
INSERT INTO template_rapor_subkategori VALUES(1,1,'a','Perawatan Diri',1),(2,2,'a','Other',1);
INSERT INTO template_rapor_item VALUES(1,1,'Tujuan satu',1,1),(2,1,'Tujuan dua',1,2),(3,2,'Other',2,1);
INSERT INTO skala_nilai_opsi VALUES(1,1,'slash','Baru dikenalkan',1),(2,1,'triangle-lg','Berkembang',2),(3,2,'triangle-full','Other scale',1);");
function portalCheck(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function invalidEntry(callable $call):void{try{$call();throw new RuntimeException('Invalid report entry accepted');}catch(DomainException $expected){}}
$dashboard=TeacherPortal::dashboard(7,null,'2026-09-23');
portalCheck($dashboard['agendaBerlangsung']['id']===1 && $dashboard['agendaBerlangsung']['sisa_hari']===7,'Current agenda/countdown belongs to teacher');
portalCheck($dashboard['agendaBerikutnya']['id']===3 && count($dashboard['sessionOptions'])===3,'Upcoming and historical scoped periods');
portalCheck(TeacherPortal::dashboard(7,4,'2026-09-23')['selectedSession']['id']===1,'Foreign period id cannot leak pupils');
portalCheck(TeacherPortal::dashboard(7,2,'2026-09-23')['daftarMurid'][0]['rapor_id']===2,'Historical period selection');
portalCheck(TeacherPortal::dashboard(99,null,'2026-09-23')['daftarMurid']===[],'Empty teacher state');
portalCheck(TeacherPortal::student(7,1)!==null && TeacherPortal::student(7,2)===null,'Student detail ownership');
invalidEntry(fn()=>ReportEntry::save(4,7,[1=>1],[]));
invalidEntry(fn()=>ReportEntry::save(1,7,[3=>3],[]));
invalidEntry(fn()=>ReportEntry::save(1,7,[1=>3],[]));
invalidEntry(fn()=>ReportEntry::save(1,7,[1=>1],[2=>'Foreign area']));
portalCheck((int)$db->query('SELECT COUNT(*) FROM rapor_nilai')->fetchColumn()===0,'Rejected writes leave no marks');
ReportEntry::save(1,7,[1=>1],[1=>'Catatan guru']);
portalCheck($db->query('SELECT semester FROM rapor_nilai')->fetchColumn()==='ganjil','Period semester used');
invalidEntry(fn()=>ReportEntry::save(1,7,[1=>2],[],true));
portalCheck((int)$db->query('SELECT skala_nilai_opsi_id FROM rapor_nilai')->fetchColumn()===1,'Incomplete submit rolls back value changes');
ReportEntry::save(1,7,[1=>''],[]);
portalCheck((int)$db->query('SELECT COUNT(*) FROM rapor_nilai')->fetchColumn()===0,'Clearing draft selection removes previous mark');
ReportEntry::save(1,7,[1=>2,2=>1],[1=>'Final'],true);
portalCheck($db->query('SELECT status FROM rapor WHERE id=1')->fetchColumn()==='menunggu_persetujuan','Complete report submitted');
invalidEntry(fn()=>ReportEntry::save(1,7,[1=>1],[],true));
portalCheck((int)$db->query('SELECT skala_nilai_opsi_id FROM rapor_nilai WHERE item_id=1')->fetchColumn()===2,'Repeated submission cannot overwrite');
foreach([1=>'ganjil',2=>'genap',3=>'ganjil',4=>'genap'] as $period=>$semester){
    $id=10+$period;$db->exec("INSERT INTO rapor VALUES($id,1,7,$period,1,'belum_diisi')");
    ReportEntry::save($id,7,[1=>1],[]);
    portalCheck($db->query("SELECT semester FROM rapor_nilai WHERE rapor_id=$id")->fetchColumn()===$semester,'All four semester/type combinations');
}
$db->exec("INSERT INTO rapor VALUES(20,1,7,1,99,'belum_diisi')");
invalidEntry(fn()=>ReportEntry::save(20,7,[],[],true));
$_SESSION=['karyawan_id'=>7,'display_name'=>'Guru Uji','csrf_token'=>'fixture'];
$controller=new PortalGuruController();$controller->showMurid('1');
portalCheck($controller->data['mode']==='detail' && !$controller->data['canEdit'] && str_contains($controller->data['breadcrumb'],'Daftar Murid Guru'),'Portal detail read-only breadcrumb');
$portal=new PengisianRaporController();$own=new ReflectionMethod($portal,'findOwnRapor');
portalCheck($own->invoke($portal,4)===null,'Foreign preview/PDF lookup blocked');
extract($dashboard);
$source=file_get_contents(VIEW_PATH.'/portal-guru/dashboard.php');
$source=str_replace(["require VIEW_PATH . '/layouts/shell-header.php';","require VIEW_PATH . '/layouts/shell-footer.php';"],'',$source);
ob_start();eval('?>'.$source);$html=ob_get_clean();
portalCheck(str_contains($html,'report-period-table') && str_contains($html,'/portal-guru/rapor/1') && !str_contains($html,'/portal-guru/rapor/4'),'Shared dashboard table, scoped links');
$portal->show('11'); extract($portal->data);
$source=file_get_contents(VIEW_PATH.'/portal-guru/pengisian-rapor.php');
$source=str_replace(["require VIEW_PATH . '/layouts/focus-header.php';","require VIEW_PATH . '/layouts/focus-footer.php';"],'',$source);
ob_start();eval('?>'.$source);$html=ob_get_clean();
portalCheck(str_contains($html,'data-ui-select') && str_contains($html,'data-report-value') && str_contains($html,'report-entry.js'),'Custom form components and progress behavior');
$db->exec('DELETE FROM kelas_guru_murid WHERE murid_id=1');
portalCheck($own->invoke($portal,11)===null && $own->invoke($portal,1)!==null,'Removed assignment blocks drafts but preserves submitted history');
invalidEntry(fn()=>ReportEntry::save(11,7,[1=>1],[]));
echo "PASS: teacher-scoped current/past/upcoming/empty dashboard, detail access, four semesters, draft saves/clears, template validation, atomic submit/rollback, duplicate lock, assignment removal and component rendering.\n";
