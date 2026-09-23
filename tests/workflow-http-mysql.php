<?php
/** Run explicitly: php tests/workflow-http-mysql.php --run
 * Creates a random, disposable MySQL database from repository schema (no live data).
 * Uses real HTTP routes, sessions, CSRF, controllers and PDF generation.
 */
if (!in_array('--run', $argv, true)) { echo "SKIP: opt-in HTTP/MySQL test; use --run.\n"; exit; }
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
require CONFIG_PATH . '/config.php';
require ROOT_PATH . '/app/models/PermissionCatalog.php';
session_write_close();
$db = new PDO('mysql:host='.DB_HOST.';port='.DB_PORT.';charset=utf8mb4', DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$database = 'zivana_e2e_' . bin2hex(random_bytes(8));
$created = false; $server = null;
function expectHttp(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
class WorkflowClient
{
    private CurlHandle $curl;
    public string $csrf = '';
    public function __construct(private string $base) {
        $this->curl = curl_init();
        curl_setopt_array($this->curl, [CURLOPT_COOKIEFILE=>'', CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>30]);
    }
    public function request(string $path, ?array $data=null, int $expected=200): array {
        curl_setopt_array($this->curl, [CURLOPT_URL=>$this->base.$path, CURLOPT_POST=>$data!==null, CURLOPT_FOLLOWLOCATION=>false]);
        if ($data!==null) curl_setopt($this->curl, CURLOPT_POSTFIELDS, http_build_query($data + ['csrf_token'=>$this->csrf]));
        $body = curl_exec($this->curl);
        $code = curl_getinfo($this->curl, CURLINFO_RESPONSE_CODE);
        expectHttp($body!==false && $code===$expected, "$path expected $expected, got $code: " . curl_error($this->curl));
        if (preg_match('/name="csrf_token" value="([^"]+)"/', $body, $match)) $this->csrf=$match[1];
        $result=['body'=>$body,'type'=>curl_getinfo($this->curl,CURLINFO_CONTENT_TYPE)];
        $redirect=curl_getinfo($this->curl,CURLINFO_REDIRECT_URL);
        if ($code===302 && $redirect) {
            $target=parse_url($redirect,PHP_URL_PATH);
            $query=parse_url($redirect,PHP_URL_QUERY);
            $this->request($target . ($query ? '?'.$query : ''));
        }
        return $result;
    }
    public function login(string $email): void {
        $this->request('/login');
        $this->request('/login',['email'=>$email,'password'=>'Fixture123$'],302);
    }
}
try {
    $db->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $created = true;
    $db->exec("USE `$database`");
    $db->exec(file_get_contents(ROOT_PATH.'/database/schema.sql'));
    $insert=$db->prepare('INSERT INTO permissions(modul,section,sub_section,aksi) VALUES(?,?,?,?)');
    foreach (PermissionCatalog::EXTRA as $permission) $insert->execute(array_slice($permission,0,4));
    $adminRole=(int)$db->query("SELECT id FROM roles WHERE nama='Admin'")->fetchColumn();
    $teacherRole=(int)$db->query("SELECT id FROM roles WHERE nama='Guru'")->fetchColumn();
    $db->exec("INSERT INTO role_permissions(role_id,permission_id) SELECT $adminRole,id FROM permissions;
        INSERT INTO role_permissions(role_id,permission_id) SELECT $teacherRole,id FROM permissions WHERE modul='Portal Guru';
        INSERT INTO jabatan(id,nama,role_id,is_active) VALUES(1,'Admin',$adminRole,1),(2,'Guru Kelas',$teacherRole,1);
        INSERT INTO karyawan(id,jabatan_id,nama,is_active) VALUES(1,1,'Admin Fixture',1);
        INSERT INTO tahun_ajaran(id,tahun_awal,tahun_akhir,is_active) VALUES(1,2026,2027,1)");
    $db->prepare('INSERT INTO users(karyawan_id,role_id,email,password_hash) VALUES(1,?,?,?)')
        ->execute([$adminRole,'admin@fixture.test',password_hash('Fixture123$',PASSWORD_DEFAULT)]);
    // Bind an available loopback port; production localhost:8000 is never used.
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
    if (!$socket) throw new RuntimeException('Cannot reserve test port');
    $address=stream_socket_get_name($socket,false); fclose($socket);
    $env=getenv(); $env['ZIVANA_E2E_DB']=$database;
    $log=tmpfile();
    $server=proc_open([PHP_BINARY,'-S',$address,'-t',ROOT_PATH.'/public',ROOT_PATH.'/tests/fixtures/workflow-http-router.php'],
        [0=>['pipe','r'],1=>$log,2=>$log],$pipes,ROOT_PATH,$env,['bypass_shell'=>true]);
    if (!is_resource($server)) throw new RuntimeException('Test HTTP server did not start');
    fclose($pipes[0]);
    $ready=false;
    for($attempt=0;$attempt<40;$attempt++) {
        $probe=@stream_socket_client('tcp://'.$address,$errno,$error,0.1);
        if ($probe) { fclose($probe);$ready=true;break; }
        usleep(100000);
    }
    expectHttp($ready,'Test server readiness');
    $base='http://'.$address;
    $admin=new WorkflowClient($base); $admin->login('admin@fixture.test');
    $admin->request('/karyawan');
    foreach (['teacher','other'] as $name) {
        $admin->request('/karyawan',['nama'=>$name,'jabatan_id'=>2,'email'=>"$name@fixture.test",'password'=>'Fixture123$','password_confirmation'=>'Fixture123$'],302);
    }
    $teacherId=(int)$db->query("SELECT karyawan_id FROM users WHERE email='teacher@fixture.test'")->fetchColumn();
    expectHttp($teacherId>0,'Employee creation produced login account');
    $admin->request('/kelas',['level_kelas'=>'Ranting','nama_kelas'=>'Fixture Akasia'],302);
    $classId=(int)$db->query('SELECT id FROM kelas')->fetchColumn();
    $admin->request('/kurikulum/periode-penilaian',[
        'nama'=>'Periode Fixture','semester'=>'ganjil','tipe'=>'Tengah Semester','kategori'=>'Rapor Murid',
        'awal_periode'=>date('Y-m-d'),'akhir_periode'=>date('Y-m-d',strtotime('+20 days'))],302);
    expectHttp((int)$db->query('SELECT COUNT(*) FROM sesi_pembagian_rapor')->fetchColumn()===1,'Period creates session');
    $periodId=(int)$db->query('SELECT id FROM periode_penilaian')->fetchColumn();
    foreach (['Akhir Semester','Tengah Semester'] as $type) {
        $admin->request("/kurikulum/periode-penilaian/$periodId",[
            'nama'=>'Periode Fixture','semester'=>'ganjil','tipe'=>$type,'kategori'=>'Rapor Murid',
            'awal_periode'=>date('Y-m-d'),'akhir_periode'=>date('Y-m-d',strtotime('+20 days'))],302);
        $templateName=$db->query('SELECT t.nama FROM sesi_pembagian_rapor s JOIN template_rapor t ON t.id=s.template_id')->fetchColumn();
        expectHttp($templateName==='Rapor Montessori '.$type,'Empty period change updates real MySQL session template');
    }
    $pupil=['kelas_id'=>$classId,'level_kelas'=>'Ranting','nama_lengkap'=>'Murid Fixture','nama_panggilan'=>'Fixture',
        'agama'=>'Islam','nik'=>'1234567890123456','no_registrasi_akte'=>'12345','jenis_kelamin'=>'P','tempat_lahir'=>'Makassar',
        'tanggal_lahir'=>'2020-01-01','alamat'=>'Alamat Fixture','tanggal_masuk_sekolah'=>'2026-07-01','status_kondisi'=>'Regular',
        'alamat_domisili'=>'Alamat Fixture','jumlah_saudara'=>1,'nama_ayah'=>'Ayah Fixture','pendidikan_ayah'=>'S1',
        'pekerjaan_ayah'=>'Swasta','telp_ayah'=>'0811111111','nama_ibu'=>'Ibu Fixture','pendidikan_ibu'=>'S1','pekerjaan_ibu'=>'Swasta','telp_ibu'=>'0822222222'];
    $admin->request('/murid',$pupil,302);
    $pupilId=(int)$db->query('SELECT id FROM murid')->fetchColumn();
    expectHttp($pupilId>0,'Pupil created after period');
    $admin->request('/manajemen-guru');
    $admin->request("/manajemen-guru/$teacherId/murid",['murid_ids'=>[$pupilId]],302);
    $report=$db->query('SELECT * FROM rapor')->fetch();
    expectHttp($report && (int)$report['guru_id']===$teacherId,'Late assignment owns generated report');
    $reportId=(int)$report['id'];
    $teacher=new WorkflowClient($base);$teacher->login('teacher@fixture.test');
    $teacher->request('/portal-guru/dashboard');
    $teacher->request('/karyawan',null,403);
    $teacher->request("/portal-guru/murid/$pupilId");
    $teacher->request("/portal-guru/rapor/$reportId");
    $other=new WorkflowClient($base);$other->login('other@fixture.test');
    $other->request("/portal-guru/murid/$pupilId",null,404);
    $other->request("/portal-guru/rapor/$reportId",null,404);
    $teacher->request("/portal-guru/rapor/$reportId/simpan",['csrf_token'=>'invalid'],419);
    $teacher->request("/portal-guru/rapor/$reportId");
    $teacher->request("/rapor-murid/$reportId/setujui",[],403);
    $teacher->request("/portal-guru/rapor/$reportId");
    $admin->request("/rapor-murid/$reportId",null,302); // drafts cannot be reviewed
    $items=$db->query('SELECT i.id FROM template_rapor_item i JOIN template_rapor_subkategori s ON s.id=i.subkategori_id JOIN template_rapor_area a ON a.id=s.area_id WHERE a.template_id='.(int)$report['template_id'])->fetchAll(PDO::FETCH_COLUMN);
    expectHttp(count($items)>1,'Fixture has multiple assessment items');
    $option=(int)$db->query('SELECT id FROM skala_nilai_opsi ORDER BY id LIMIT 1')->fetchColumn();
    $teacher->request("/portal-guru/rapor/$reportId/simpan",['nilai'=>[$items[0]=>$option]],302);
    expectHttp((int)$db->query('SELECT COUNT(*) FROM rapor_nilai')->fetchColumn()===1,'Draft persists through real HTTP');
    // Transfer a partially filled draft through the actual assignment form.
    $otherTeacherId=(int)$db->query("SELECT karyawan_id FROM users WHERE email='other@fixture.test'")->fetchColumn();
    $admin->request('/manajemen-guru');
    $admin->request("/manajemen-guru/$teacherId/murid",['murid_ids'=>[]],302);
    $teacher->request("/portal-guru/rapor/$reportId",null,404);
    $admin->request("/manajemen-guru/$otherTeacherId/murid",['murid_ids'=>[$pupilId]],302);
    $other->request("/portal-guru/rapor/$reportId");
    expectHttp((int)$db->query('SELECT guru_id FROM rapor')->fetchColumn()===$otherTeacherId,'Draft owner follows new assignment');
    expectHttp((int)$db->query('SELECT skala_nilai_opsi_id FROM rapor_nilai')->fetchColumn()===$option,'Draft transfer preserves saved mark');
    $admin->request("/manajemen-guru/$otherTeacherId/murid",['murid_ids'=>[]],302);
    $admin->request("/manajemen-guru/$teacherId/murid",['murid_ids'=>[$pupilId]],302);
    $teacher->request("/portal-guru/rapor/$reportId");
    $teacher->request("/portal-guru/rapor/$reportId/selesaikan",['nilai'=>[]],302);
    expectHttp($db->query('SELECT status FROM rapor')->fetchColumn()==='belum_diisi','Incomplete submit rejected');
    $submissionToken=$teacher->csrf;
    $teacher->request("/portal-guru/rapor/$reportId/selesaikan",['nilai'=>array_fill_keys($items,$option)],302);
    expectHttp($db->query('SELECT status FROM rapor')->fetchColumn()==='menunggu_persetujuan','Teacher submission awaits approval');
    $teacher->request("/portal-guru/rapor/$reportId/selesaikan",['csrf_token'=>$submissionToken,'nilai'=>array_fill_keys($items,$option)],419);
    expectHttp((int)$db->query('SELECT COUNT(*) FROM rapor_nilai')->fetchColumn()===count($items),'Double-click submission does not duplicate marks');
    // An Admin job title alone must not bypass its approval permission.
    $adminPermissions=array_filter($db->query('SELECT * FROM permissions')->fetchAll(),fn($p)=>PermissionCatalog::visible($p));
    $noApproval=array_column(array_filter($adminPermissions,fn($p)=>!($p['modul']==='Murid' && $p['section']==='Rapor Murid' && $p['aksi']==='edit')),'id');
    $admin->request('/rbac');
    $admin->request('/rbac',['role_id'=>$adminRole,'permission_ids'=>$noApproval],302);
    $preview=$admin->request("/rapor-murid/$reportId");
    expectHttp(!str_contains($preview['body'],"/rapor-murid/$reportId/setujui"),'Approval action hidden without permission');
    $admin->request("/rapor-murid/$reportId/setujui",[],403);
    expectHttp($db->query('SELECT status FROM rapor')->fetchColumn()==='menunggu_persetujuan','Rejected approval does not alter status');
    $admin->request('/rbac');
    $admin->request('/rbac',['role_id'=>$adminRole,'permission_ids'=>array_column($adminPermissions,'id')],302);
    $preview=$admin->request("/rapor-murid/$reportId");
    expectHttp(str_contains($preview['body'],'Setujui'),'Approver can see approval action');
    $admin->request("/rapor-murid/$reportId/setujui",[],302);
    $approved=$db->query('SELECT * FROM rapor')->fetch();
    expectHttp($approved['status']==='disetujui' && $approved['disetujui_oleh']!==null,'Approval persists reviewer identity');
    $admin->request('/rbac'); // obtain a fresh token for an intentionally repeated request
    $admin->request("/rapor-murid/$reportId/setujui",[],302);
    expectHttp($db->query('SELECT * FROM rapor')->fetch()===$approved,'Repeated approval preserves original approval metadata');
    $teacher->request("/portal-guru/rapor/$reportId/pratinjau");
    $pdf=$teacher->request("/portal-guru/rapor/$reportId/pdf");
    expectHttp(str_contains($pdf['type'],'application/pdf') && str_starts_with($pdf['body'],'%PDF-'),'Teacher receives actual PDF');
    $other->request("/portal-guru/rapor/$reportId/pdf",null,404);
    // Exercise the actual RBAC save form, then logout/login as requested by the product flow.
    $teacherPermissions=array_filter($db->query("SELECT * FROM permissions WHERE modul='Portal Guru'")->fetchAll(),
        fn($p)=>PermissionCatalog::visible($p));
    $readonlyIds=array_column(array_filter($teacherPermissions,fn($p)=>$p['aksi']==='lihat'),'id');
    $admin->request('/rbac');
    $admin->request('/rbac',['role_id'=>$teacherRole,'permission_ids'=>$readonlyIds],302);
    expectHttp((int)$db->query("SELECT COUNT(*) FROM role_permissions WHERE role_id=$teacherRole")->fetchColumn()===count($readonlyIds),'RBAC grants saved');
    $teacher->request('/logout',null,302);$teacher->login('teacher@fixture.test');
    $preview=$teacher->request("/portal-guru/rapor/$reportId/pratinjau");
    expectHttp(!str_contains($preview['body'],'Simpan PDF'),'Revoked PDF action hidden from teacher UI');
    $teacher->request("/portal-guru/rapor/$reportId/pdf",null,403);
    $teacher->request("/portal-guru/rapor/$reportId",null,403);
    $admin->request('/rbac',['role_id'=>$teacherRole,'permission_ids'=>array_column($teacherPermissions,'id')],302);
    $teacher->request('/logout',null,302);$teacher->login('teacher@fixture.test');
    $preview=$teacher->request("/portal-guru/rapor/$reportId/pratinjau");
    expectHttp(str_contains($preview['body'],'Simpan PDF'),'Granted PDF action restored after login');
    $teacher->request("/portal-guru/rapor/$reportId/pdf");
    $admin->request('/karyawan');
    $admin->request("/karyawan/$teacherId/nonaktifkan",[],302);
    $teacher->request('/portal-guru/dashboard',null,302); // active session is rejected
    $teacher->login('teacher@fixture.test'); // denied login returns to login form
    $teacher->request('/portal-guru/dashboard',null,302);
    $admin->request("/karyawan/$teacherId/aktifkan",[],302);
    $teacher->login('teacher@fixture.test');
    $teacher->request('/portal-guru/dashboard');
    expectHttp((int)$db->query('SELECT COUNT(*) FROM rapor_nilai')->fetchColumn()===count($items),'Access tests do not mutate marks');
    echo "PASS: real HTTP/MySQL employee creation/login, period, late pupil/assignment, scoped teacher detail/form, draft/save/submit, approval, PDF, foreign ownership, CSRF, RBAC revoke/regrant after login, employee deactivation/reactivation.\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    // Delete only the exact random database created by this invocation, never DB_NAME.
    if ($created && preg_match('/^zivana_e2e_[a-f0-9]{16}$/D',$database) && $database!==DB_NAME) {
        $db->exec("DROP DATABASE `$database`");
        echo "CLEANUP: disposable fixture database removed; application data untouched.\n";
    }
}
