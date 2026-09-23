<?php
require __DIR__ . '/report-workflow-regression.php';
define('ROOT_PATH',dirname(__DIR__));define('VIEW_PATH',ROOT_PATH.'/app/views');define('BASE_PATH','');
require ROOT_PATH.'/app/helpers/functions.php';require ROOT_PATH.'/app/helpers/ui.php';
require ROOT_PATH.'/app/models/TahunAjaran.php';
class Controller {
    public array $data=[]; public array $inputs=[]; public ?string $redirected=null;
    protected function middleware(...$args) {}
    protected function input($key,$default=null) { return $this->inputs[$key] ?? $default; }
    protected function view($name,$data) { $this->data=$data; }
    protected function redirect($url) { $this->redirected=$url; }
}
class RoleMiddleware { public function check(...$args): bool { return true; } }
require ROOT_PATH.'/app/controllers/RaporMuridController.php';
require ROOT_PATH.'/app/controllers/PengisianRaporController.php';
$db->exec("ALTER TABLE tahun_ajaran ADD tahun_awal TEXT; ALTER TABLE tahun_ajaran ADD tahun_akhir TEXT; ALTER TABLE tahun_ajaran ADD is_active INTEGER;
UPDATE tahun_ajaran SET tahun_awal='2026',tahun_akhir='2027',is_active=1 WHERE id=1;
ALTER TABLE murid ADD nama_lengkap TEXT; ALTER TABLE murid ADD nisn TEXT; UPDATE murid SET nama_lengkap='Murid Uji';
ALTER TABLE kelas ADD level_kelas TEXT; ALTER TABLE kelas ADD nama_kelas TEXT;
ALTER TABLE rapor ADD disetujui_oleh INTEGER; ALTER TABLE rapor ADD disetujui_at TEXT;
CREATE TABLE roles(id INTEGER,nama TEXT); CREATE TABLE jabatan(id INTEGER,nama TEXT);
CREATE TABLE karyawan(id INTEGER,jabatan_id INTEGER); CREATE TABLE users(id INTEGER,karyawan_id INTEGER,role_id INTEGER,is_active INTEGER);
INSERT INTO roles VALUES(1,'Admin'),(2,'Guru'); INSERT INTO jabatan VALUES(1,'Admin'),(2,'Kepala Sekolah'),(3,'Guru Kelas');
INSERT INTO karyawan VALUES(1,1),(2,2),(3,3); INSERT INTO users VALUES(1,1,1,1),(2,2,1,1),(3,3,2,1);");
foreach([1=>true,2=>true,3=>false] as $userId=>$allowed) { $_SESSION=['user_id'=>$userId]; checkReport(ReportWorkflow::reviewer()===$allowed,'Review access'); }
$_SESSION=['user_id'=>1,'csrf_token'=>'fixture'];
$controller=new RaporMuridController();
$controller->show('1');checkReport($controller->redirected==='/rapor-murid','Draft preview blocked');
$controller->redirected=null;$controller->downloadPdf('1');checkReport($controller->redirected==='/rapor-murid','Draft PDF blocked');
$controller->inputs=['tahun_ajaran_id'=>2];$controller->index();checkReport(count($controller->data['periodeList'])===1,'Year filter isolates periods');
extract($controller->data);
$source=file_get_contents(VIEW_PATH.'/admin/rapor-murid/index.php');
$source=str_replace(["require VIEW_PATH . '/layouts/shell-header.php';","require VIEW_PATH . '/layouts/shell-footer.php';"],'',$source);
ob_start();eval('?>'.$source);$html=ob_get_clean();
checkReport(!str_contains($html,'modal-tambah-sesi') && !preg_match('~href="/rapor-murid/\d+~',$html),'No create button or draft links');
checkReport(str_contains($headerActions,'tahun_ajaran_id'),'Year filter shown');
$db->exec("UPDATE rapor SET status='menunggu_persetujuan' WHERE id=1");
$controller->approve('1');checkReport($db->query('SELECT status FROM rapor WHERE id=1')->fetchColumn()==='disetujui','Approval transition');
$controller->approve('2');checkReport($db->query('SELECT status FROM rapor WHERE id=2')->fetchColumn()==='belum_diisi','Cannot approve draft');
$portal=new PengisianRaporController();$semesterMethod=new ReflectionMethod($portal,'semesterUntukSesi');
foreach($db->query('SELECT s.id,p.semester FROM sesi_pembagian_rapor s JOIN periode_penilaian p ON p.id=s.periode_id')->fetchAll() as $row) {
    checkReport($semesterMethod->invoke($portal,(int)$row['id'])===$row['semester'],'Semester independent of middle/end type');
}
$rapor=['id'=>1,'nama_lengkap'=>'Murid Uji','nisn'=>'123','status'=>'menunggu_persetujuan','periode_tipe'=>'Akhir Semester','tahun_awal'=>'2026','tahun_akhir'=>'2027'];$areas=[];$legenda=[];$canApprove=true;
$source=file_get_contents(VIEW_PATH.'/admin/rapor-murid/show.php');
$source=str_replace(["require VIEW_PATH . '/layouts/shell-header.php';","require VIEW_PATH . '/layouts/shell-footer.php';"],'',$source);
ob_start();eval('?>'.$source);$html=ob_get_clean();
checkReport(str_contains($headerActions,'Setujui') && str_contains($headerActions,'csrf_token'),'Approval UI');
checkReport(str_contains($html,'template-preview') && str_contains($html,'2026/2027') && str_contains($html,'AKHIR SEMESTER'),'Actual document metadata');
echo "PASS: reviewer roles, draft URL/PDF guards, year filter, approval state, semester routing and preview UI.\n";
