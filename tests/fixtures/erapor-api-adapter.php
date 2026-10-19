<?php
// CLI-only isolated controller contract test. Backend services are explicit spies;
// database/business correctness is covered separately by the restored MySQL harness.
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
ob_start();
define('ROOT_PATH',dirname(__DIR__,2));
define('ERAPOR_API_ENABLED',($argv[1] ?? '')!=='disabled');
$mode=$argv[1] ?? 'show';
class Database { public static PDO $db; public static function getInstance(): PDO { return self::$db; } }
spl_autoload_register(function($class) {
    foreach (['core','models','controllers','middleware'] as $folder) {
        $path=ROOT_PATH.'/app/'.$folder.'/'.$class.'.php';
        if (is_file($path)) { require $path; return; }
    }
});
require ROOT_PATH.'/app/helpers/functions.php';
class EraporTeacherForm {
    public static function read($db,$id,$actor): array {
        if ($id===99) throw new DomainException('secret student identity');
        if ($id===98) throw new RuntimeException('secret database credentials');
        return ['actor'=>$actor,'ppi'=>['included'=>EraporPpiWorkflow::$choice],'documents'=>[['id'=>2,'jenis_dokumen'=>'UMMI']],
            'completion'=>['complete'=>false,'documents'=>[]],
            'capabilities'=>['can_confirm_filled'=>false],
            'session'=>['id'=>$id,'status'=>'BELUM_DIISI']];
    }
}
class EraporUmmiEntry { public static function save($db,$sid,$did,$actor,$changes): array { return ['actor'=>$actor,'session'=>$sid,'document'=>$did,'changes'=>$changes]; } }
class EraporConfirmFilled { public static function confirm($db,$sid,$actor): array { return ['result'=>'confirmed','actor'=>$actor]; } }
class EraporConfirmReception { public static function confirm($db,$sid,$actor,$include=null): array { return ['result'=>'confirmed','actor'=>$actor,'include_ppi'=>$include]; } }
class EraporPpiWorkflow {
    public static ?bool $choice=null;
    public static function choose($db,$sid,$actor,bool $include): array {self::$choice=$include;return ['actor'=>$actor];}
    public static function submit($db,$sid,$actor): array {return ['result'=>'submitted','actor'=>$actor];}
}
class ApiHarness extends EraporTeacherApiController { protected function rawBody(): string { return stream_get_contents(STDIN); } }
$db=Database::$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE users(id INTEGER PRIMARY KEY,karyawan_id INTEGER,role_id INTEGER,is_active INTEGER);
 CREATE TABLE karyawan(id INTEGER PRIMARY KEY,jabatan_id INTEGER,is_active INTEGER);
 CREATE TABLE jabatan(id INTEGER PRIMARY KEY,nama TEXT);
 CREATE TABLE permissions(id INTEGER PRIMARY KEY,modul TEXT,section TEXT,sub_section TEXT,aksi TEXT);
 CREATE TABLE role_permissions(role_id INTEGER,permission_id INTEGER);
 INSERT INTO users VALUES(1,1,3,1); INSERT INTO karyawan VALUES(1,1,1); INSERT INTO jabatan VALUES(1,'Guru Kelas');
 INSERT INTO permissions VALUES(1,'Portal Guru','Daftar Murid',NULL,'lihat'),(2,'Portal Guru','Daftar Murid',NULL,'edit'),(3,'Portal Guru','Daftar Murid',NULL,'kirim');
 INSERT INTO role_permissions VALUES(3,1),(3,2),(3,3);");
$_SESSION=['user_id'=>1,'role_id'=>3,'role_name'=>'Guru'];
$_SERVER['REQUEST_METHOD']=in_array($mode,['show','guest','inactive','denied','disabled','wrong-id','foreign','failure'])?'GET':'POST';
$_SERVER['CONTENT_TYPE']=$mode==='content-type'?'text/plain':'application/json';
$_SERVER['HTTP_X_CSRF_TOKEN']=getCsrfToken();
if ($mode==='guest') $_SESSION=[];
if ($mode==='inactive') $db->exec('UPDATE users SET is_active=0');
if ($mode==='denied') $db->exec('DELETE FROM role_permissions');
if (in_array($mode,['no-edit','ppi-choice-no-edit'],true)) $db->exec('DELETE FROM role_permissions WHERE permission_id=2');
if (in_array($mode,['no-send','ppi-submit-no-send'],true)) $db->exec('DELETE FROM role_permissions WHERE permission_id=3');
if ($mode==='csrf') $_SERVER['HTTP_X_CSRF_TOKEN']='invalid';
register_shutdown_function(function() {
    $body=ob_get_clean(); echo json_encode(['status'=>http_response_code() ?: 200,'body'=>json_decode($body,true)]);
});
$api=new ApiHarness();
if ($_SERVER['REQUEST_METHOD']==='GET') $api->show(match($mode) {'wrong-id'=>'1oops','foreign'=>'99','failure'=>'98',default=>'1'});
elseif (in_array($mode,['filled','no-send'])) $api->confirmFilled('1');
elseif ($mode==='reception') $api->confirmReception('1');
elseif (str_starts_with($mode,'ppi-choice')) $api->choosePpi('1');
elseif (str_starts_with($mode,'ppi-submit')) $api->submitPpi('1');
else $api->save('1',$mode==='foreign-doc'?'3':'2');
