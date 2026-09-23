<?php
// Runs real controllers, password hashes and RBAC SQL against isolated SQLite.
// Only redirects/rendering are intercepted; no local application accounts are modified.
ob_start();
session_start();
define('ROOT_PATH', dirname(__DIR__));
define('VIEW_PATH', ROOT_PATH . '/app/views');
define('BASE_PATH', '');
define('CSRF_TOKEN_NAME', 'csrf_token');
class Database { public static PDO $db; public static function getInstance(): PDO { return self::$db; } }
spl_autoload_register(function ($class) {
    foreach (['core','models','controllers','middleware'] as $folder) {
        $file = ROOT_PATH . '/app/' . $folder . '/' . $class . '.php';
        if (is_file($file)) { require $file; return; }
    }
});
require ROOT_PATH . '/app/helpers/functions.php';
require ROOT_PATH . '/app/helpers/ui.php';
$db = Database::$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("PRAGMA foreign_keys=ON;
CREATE TABLE roles(id INTEGER PRIMARY KEY,nama TEXT);
CREATE TABLE jabatan(id INTEGER PRIMARY KEY,nama TEXT,role_id INTEGER,is_active INTEGER);
CREATE TABLE karyawan(id INTEGER PRIMARY KEY,jabatan_id INTEGER,nama TEXT,is_active INTEGER);
CREATE TABLE users(id INTEGER PRIMARY KEY,karyawan_id INTEGER,role_id INTEGER,email TEXT UNIQUE COLLATE NOCASE,password_hash TEXT,is_active INTEGER,remember_token TEXT);
CREATE TABLE permissions(id INTEGER PRIMARY KEY,modul TEXT,section TEXT,sub_section TEXT,aksi TEXT,display_order INTEGER);
CREATE TABLE role_permissions(id INTEGER PRIMARY KEY,role_id INTEGER,permission_id INTEGER,UNIQUE(role_id,permission_id));
INSERT INTO roles VALUES(1,'Superadmin'),(2,'Admin'),(3,'Guru'),(4,'Terbatas');
INSERT INTO jabatan VALUES(1,'Kepala Sekolah',2,1),(2,'Admin',2,1),(3,'Guru Kelas',3,1),(4,'Guru Shadow',3,1);
INSERT INTO users VALUES(1,NULL,1,'fixture-admin@example.test','',1,NULL);");
$nodes=[['Sekolah','Data Sekolah'],['Sekolah','Manajemen Template'],['Sekolah','Periode Penilaian'],['Human Capital','Daftar Karyawan'],['Human Capital','Jabatan'],['Human Capital','Manajemen Guru'],['Murid','Manajemen Murid'],['Murid','Manajemen Kelas'],['Murid','Rapor Murid'],['Portal Guru','Dashboard'],['Portal Guru','Daftar Murid'],['Sistem','RBAC']];
foreach ($nodes as [$module,$section]) foreach (['lihat','edit'] as $action) {
    $db->prepare('INSERT INTO permissions(modul,section,aksi,display_order) VALUES(?,?,?,0)')->execute([$module,$section,$action]);
}
foreach(PermissionCatalog::EXTRA as [$module,$section,$sub,$action]) {
    $db->prepare('INSERT INTO permissions(modul,section,sub_section,aksi,display_order) VALUES(?,?,?,?,100)')->execute([$module,$section,$sub,$action]);
}
$db->exec('INSERT INTO role_permissions(role_id,permission_id) SELECT 1,id FROM permissions');
$db->exec('INSERT INTO role_permissions(role_id,permission_id) SELECT 2,id FROM permissions');
// Even accidental admin grants must not expose admin modules to teachers.
$db->exec('INSERT INTO role_permissions(role_id,permission_id) SELECT 3,id FROM permissions');
function expectAccount(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); }
class TestRedirect extends RuntimeException {}
trait CaptureAccountResponse {
    public array $viewData=[];
    protected function redirect(string $path): void { throw new TestRedirect($path); }
    protected function view(string $name,array $data=[]): void { $this->viewData=$data; }
}
class EmployeeHarness extends KaryawanController { use CaptureAccountResponse; }
class LoginHarness extends AuthController { use CaptureAccountResponse; }
class PositionHarness extends JabatanController { use CaptureAccountResponse; }
class RbacHarness extends RbacController { use CaptureAccountResponse; }
function requestAccount(string $class,string $method,array $input=[],array $args=[]): string {
    $_SERVER['REQUEST_METHOD']='POST'; $_POST=$input+['csrf_token'=>getCsrfToken()]; $_GET=[];
    try { (new $class())->$method(...$args); } catch (TestRedirect $redirect) { return $redirect->getMessage(); }
    return '';
}
function adminAccount(): void { $_SESSION=['user_id'=>1,'role_id'=>1,'role_name'=>'Superadmin','karyawan_id'=>null]; }
adminAccount();
// Isolated subprocess modes exercise actual exit-based rejection paths.
$mode=$argv[1] ?? '';
if ($mode==='route') {
    $_SESSION['role_id']=4;
    $_SERVER['REQUEST_METHOD']=$argv[2]; $_SERVER['REQUEST_URI']=$argv[3];
    $_POST=['csrf_token'=>getCsrfToken()];
    register_shutdown_function(function()use($db){
        ob_end_clean();
        echo json_encode(['status'=>http_response_code(), 'employees'=>(int)$db->query('SELECT COUNT(*) FROM karyawan')->fetchColumn()]);
    });
    $router=new Router(); require ROOT_PATH.'/routes/web.php'; $router->dispatch(); exit;
}
if ($mode==='deny') {
    $_SESSION['role_id']=4;
    register_shutdown_function(function()use($db){ echo '\nRESULT '.http_response_code().' employees='.$db->query('SELECT COUNT(*) FROM karyawan')->fetchColumn(); });
    requestAccount(EmployeeHarness::class,'store',['nama'=>'Blocked']); exit;
}
if ($mode==='csrf') {
    $_SERVER['REQUEST_METHOD']='POST'; $_POST=['csrf_token'=>'invalid'];
    register_shutdown_function(function()use($db){ echo '\nRESULT '.http_response_code().' employees='.$db->query('SELECT COUNT(*) FROM karyawan')->fetchColumn(); });
    new EmployeeHarness(); exit;
}
$password='Password123$'; $created=[];
foreach ([1,2,3,4] as $position) {
    adminAccount();
    $email="fixture-$position@example.test";
    requestAccount(EmployeeHarness::class,'store',['nama'=>"Fixture $position",'jabatan_id'=>$position,'email'=>$email,'password'=>$password,'password_confirmation'=>$password]);
    $user=(new User())->whereFirst('email',$email); $created[$position]=$user;
    expectAccount($user && password_verify($password,$user['password_hash']) && $user['password_hash']!==$password,'Employee account and hashed password');
    $_SESSION=[];
    $target=requestAccount(LoginHarness::class,'login',['email'=>$email,'password'=>$password]);
    expectAccount($target===($position>=3?'/portal-guru/dashboard':'/sekolah'),'Login landing by allowed modules');
    expectAccount($_SESSION['karyawan_id']==$user['karyawan_id'],'Employee identity in session');
    if($position>=3) foreach($nodes as [$module,$section]) expectAccount((new RoleMiddleware())->check($module,$section)===($module==='Portal Guru'),'Teacher restricted to portal even with extra grants');
}
adminAccount();
$count=$db->query('SELECT COUNT(*) FROM karyawan')->fetchColumn();
requestAccount(EmployeeHarness::class,'store',['nama'=>'Duplicate','jabatan_id'=>2,'email'=>'FIXTURE-2@example.test','password'=>$password,'password_confirmation'=>$password]);
expectAccount($db->query('SELECT COUNT(*) FROM karyawan')->fetchColumn()===$count && !empty($_SESSION['employee_error']),'Duplicate email creates no orphan employee');
foreach (['password123$', 'Password$', 'Password123', 'Short1$'] as $weak) {
    requestAccount(EmployeeHarness::class,'store',['nama'=>'Invalid','jabatan_id'=>2,'email'=>'weak@example.test','password'=>$weak,'password_confirmation'=>$weak]);
    expectAccount($db->query('SELECT COUNT(*) FROM karyawan')->fetchColumn()===$count,'Weak passwords rejected');
}
$teacher=$created[3]; $employee=(string)$teacher['karyawan_id'];
requestAccount(EmployeeHarness::class,'updatePassword',['password'=>'NewPassword123$','password_confirmation'=>'NewPassword123$'],[$employee]);
$_SESSION=[];
expectAccount(requestAccount(LoginHarness::class,'login',['email'=>$teacher['email'],'password'=>$password])==='/login','Old password rejected');
expectAccount(requestAccount(LoginHarness::class,'login',['email'=>$teacher['email'],'password'=>'NewPassword123$'])==='/portal-guru/dashboard','New password accepted');
adminAccount(); requestAccount(EmployeeHarness::class,'deactivate',[],[$employee]);
$_SESSION=[];
expectAccount(requestAccount(LoginHarness::class,'login',['email'=>$teacher['email'],'password'=>'NewPassword123$'])==='/login','Inactive employee cannot log in');
adminAccount(); requestAccount(EmployeeHarness::class,'activate',[],[$employee]);
expectAccount(AccountAccess::active((new User())->find($teacher['id'])),'Reactivation restores login eligibility');
requestAccount(PositionHarness::class,'update',['nama'=>'Guru Kelas','role_id'=>4],['3']);
expectAccount((int)(new User())->find($teacher['id'])['role_id']===4,'Changing position role updates employee account');
$_SESSION=[];
expectAccount(requestAccount(LoginHarness::class,'login',['email'=>$teacher['email'],'password'=>'NewPassword123$'])==='/akses-terbatas','Role without permissions has safe landing');
adminAccount();
$before=$db->query('SELECT COUNT(*) FROM role_permissions WHERE role_id=2')->fetchColumn();
requestAccount(RbacHarness::class,'update',['role_id'=>2,'permission_ids'=>[999999]]);
expectAccount($db->query('SELECT COUNT(*) FROM role_permissions WHERE role_id=2')->fetchColumn()===$before,'Unknown permission does not erase grants');
$rbacView=(int)$db->query("SELECT id FROM permissions WHERE section='RBAC' AND aksi='lihat'")->fetchColumn();
requestAccount(RbacHarness::class,'update',['role_id'=>2,'permission_ids'=>[$rbacView]]);
$_SESSION=['user_id'=>$created[2]['id'],'role_id'=>2,'role_name'=>'Admin'];
$_SERVER['REQUEST_METHOD']='GET'; $_POST=[];
$page=new RbacHarness(); $page->index(); expectAccount(!$page->viewData['canEdit'],'Read-only RBAC has no editing permission');
extract($page->viewData);
$source=file_get_contents(VIEW_PATH.'/admin/rbac/index.php');
$source=str_replace(["require VIEW_PATH . '/layouts/shell-header.php';","require VIEW_PATH . '/layouts/shell-footer.php';"],'',$source);
ob_start(); eval('?>'.$source); $html=ob_get_clean();
expectAccount(str_contains($html,'class="rbac-permissions" disabled') && !str_contains($html,'type="submit"'),'Read-only matrix disabled, save button absent');
adminAccount();
$editOnly=(int)$db->query("SELECT id FROM permissions WHERE section='Daftar Karyawan' AND aksi='edit'")->fetchColumn();
requestAccount(RbacHarness::class,'update',['role_id'=>4,'permission_ids'=>[$editOnly]]);
$_SESSION['role_id']=4;
expectAccount(!(new RoleMiddleware())->check('Human Capital','Daftar Karyawan','edit'),'Write permission requires visibility permission');
adminAccount(); requestAccount(EmployeeHarness::class,'destroy',[],[$employee]);
expectAccount(!(new User())->find($teacher['id']) && !(new Karyawan())->find($employee),'Delete removes account and employee');
$_SESSION=[];
expectAccount(requestAccount(LoginHarness::class,'login',['email'=>$teacher['email'],'password'=>'NewPassword123$'])==='/login','Deleted account cannot log in');
$employeeView=(int)$db->query("SELECT id FROM permissions WHERE COALESCE(sub_section,section)='Daftar Karyawan' AND aksi='lihat'")->fetchColumn();
foreach (['tambah','edit','hapus','status','kata_sandi'] as $action) {
    adminAccount();
    $feature=(int)$db->query("SELECT id FROM permissions WHERE COALESCE(sub_section,section)='Daftar Karyawan' AND aksi='$action'")->fetchColumn();
    requestAccount(RbacHarness::class,'update',['role_id'=>4,'permission_ids'=>[$employeeView,$feature]]);
    $_SESSION=['user_id'=>$created[2]['id'],'role_id'=>4,'role_name'=>'Terbatas'];
    $_SERVER['REQUEST_METHOD']='GET'; $_POST=[];
    $list=new EmployeeHarness(); $list->index(); extract($list->viewData);
    $source=file_get_contents(VIEW_PATH.'/admin/karyawan/index.php');
    $source=str_replace(["require VIEW_PATH . '/layouts/shell-header.php';","require VIEW_PATH . '/layouts/shell-footer.php';"],'',$source);
    ob_start(); eval('?>'.$source); $html=ob_get_clean();
    foreach(['tambah'=>'modal-tambah-karyawan','edit'=>'modal-ubah-karyawan','hapus'=>'modal-hapus-karyawan','status'=>'modal-status-karyawan','kata_sandi'=>'modal-kata-sandi-karyawan'] as $key=>$modal) {
        expectAccount(str_contains($html,$modal)===($action===$key), 'Granular employee button/modal '.$key.' for '.$action);
        expectAccount((new RoleMiddleware())->check('Human Capital','Daftar Karyawan',$key)===($action===$key),'Independent backend feature '.$key);
    }
}
function renderAccessPage(string $folder, array $data): string {
    extract($data);
    $directory=VIEW_PATH.'/admin/'.$folder;
    $source=file_get_contents($directory.'/index.php');
    $source=str_replace(["require VIEW_PATH . '/layouts/shell-header.php';","require VIEW_PATH . '/layouts/shell-footer.php';"],'',$source);
    $source=str_replace('__DIR__',var_export($directory,true),$source);
    ob_start(); eval('?>'.$source); return ob_get_clean();
}
$fixtures = [
    ['jabatan','Human Capital','Jabatan','jabatan',['jabatanList'=>[['id'=>2,'nama'=>'Admin','nama_role'=>'Admin','role_id'=>2,'is_active'=>1]],'roleOptions'=>[['id'=>2,'nama'=>'Admin']], 'search'=>'','status'=>'']],
    ['kelas','Murid','Manajemen Kelas','kelas',['kelasList'=>[['id'=>1,'level_kelas'=>'Ranting','nama_kelas'=>'Akasia','jumlah_murid'=>1,'jumlah_guru'=>1]]]],
    ['periode-penilaian','Sekolah','Periode Penilaian','periode',['periodeList'=>[['id'=>1,'nama'=>'Periode Uji','semester'=>'ganjil','tipe'=>'Tengah Semester','kategori'=>'Rapor Murid','awal_periode'=>'2026-09-01','akhir_periode'=>'2026-09-30']], 'tipeOptions'=>['Tengah Semester'=>'Tengah Semester'],'kategoriOptions'=>['Rapor Murid'=>'Rapor Murid'],'tipe'=>'','kategori'=>'']],
];
foreach ($fixtures as [$folder,$module,$section,$slug,$data]) {
    $actions=['tambah'=>'tambah','edit'=>'ubah','hapus'=>'hapus'];
    if($folder==='jabatan') $actions['status']='status';
    foreach($actions as $action=>$prefix) {
        adminAccount();
        $stmt=$db->prepare('SELECT id FROM permissions WHERE modul=? AND COALESCE(sub_section,section)=? AND aksi IN (?,?)');
        $stmt->execute([$module,$section,'lihat',$action]);
        requestAccount(RbacHarness::class,'update',['role_id'=>4,'permission_ids'=>$stmt->fetchAll(PDO::FETCH_COLUMN)]);
        $_SESSION=['user_id'=>$created[2]['id'],'role_id'=>4,'role_name'=>'Terbatas'];
        $html=renderAccessPage($folder,$data+['canEdit'=>uiCan($module,$section,'edit')]);
        foreach($actions as $key=>$modalPrefix) expectAccount(str_contains($html,'id="modal-'.$modalPrefix.'-'.$slug)===($action===$key),"$folder modal $key with only $action permission");
    }
}
ob_end_clean();
echo "PASS: four employee accounts/login, role landing, teacher scope, password change/validation, duplicate rollback, activation/deletion, position-role synchronization, RBAC save validation/read-only UI and view-before-write.\n";
