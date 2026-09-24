<?php
// Real loopback HTTP/controller/session/RBAC/CSRF against this harness's disposable DB.
final class EraporHttpClient
{
    private CurlHandle $curl;
    public string $csrf='';
    public function __construct(private string $base)
    {
        $this->curl=curl_init();
        curl_setopt_array($this->curl,[CURLOPT_COOKIEFILE=>'',CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_FOLLOWLOCATION=>false]);
    }
    public function raw(string $path,?string $body=null,array $headers=[]): array
    {
        $options=[CURLOPT_URL=>$this->base.$path,CURLOPT_HTTPGET=>true,CURLOPT_HTTPHEADER=>$headers];
        if ($body!==null) { $options[CURLOPT_POST]=true; $options[CURLOPT_POSTFIELDS]=$body; }
        curl_setopt_array($this->curl,$options);
        $raw=curl_exec($this->curl); $status=curl_getinfo($this->curl,CURLINFO_RESPONSE_CODE);
        if ($raw===false) throw new RuntimeException('Loopback HTTP request failed: '.curl_error($this->curl));
        return ['status'=>$status,'body'=>$raw,'json'=>json_decode($raw,true),'type'=>curl_getinfo($this->curl,CURLINFO_CONTENT_TYPE)];
    }
    public function login(string $email): void
    {
        $form=$this->raw('/login');
        if ($form['status']!==200 || !preg_match('/name="csrf_token" value="([^"]+)"/',$form['body'],$m)) throw new RuntimeException('Login form/CSRF unavailable over HTTP.');
        $post=$this->raw('/login',http_build_query(['csrf_token'=>$m[1],'email'=>$email,'password'=>'EraporFixture123$']),['Content-Type: application/x-www-form-urlencoded']);
        if ($post['status']!==302) throw new RuntimeException('Fixture teacher login failed.');
    }
    public function api(string $method,string $path,mixed $body=null,?string $token=null): array
    {
        $headers=['Accept: application/json'];
        if ($method==='POST') {
            $headers[]='Content-Type: application/json';
            $headers[]='X-CSRF-Token: '.($token ?? $this->csrf);
        }
        $result=$this->raw($path,$method==='POST'?json_encode($body,JSON_THROW_ON_ERROR):null,$headers);
        if (is_array($result['json']) && is_string($result['json']['csrf_token'] ?? null)) $this->csrf=$result['json']['csrf_token'];
        return $result;
    }
    public function close(): void { curl_close($this->curl); }
}
$httpSid=(int)$nextAbk['id']; $httpActor=(int)$abkStudent['actor_id'];
$httpUser=$db->query('SELECT email,role_id FROM users WHERE id='.$httpActor)->fetch(PDO::FETCH_ASSOC);
$httpOtherUserQuery=$db->prepare("SELECT u.id,u.email,u.role_id FROM users u JOIN karyawan k ON k.id=u.karyawan_id JOIN jabatan j ON j.id=k.jabatan_id
    WHERE u.id<>? AND u.is_active=1 AND k.is_active=1 AND j.is_active=1 AND j.nama IN ('Guru Kelas','Guru Shadow')
    AND NOT EXISTS (SELECT 1 FROM kelas_guru_murid a WHERE a.murid_id=? AND a.guru_id=k.id) ORDER BY u.id LIMIT 1");
$httpOtherUserQuery->execute([$httpActor,$nextAbk['murid_id'] ?? 0]);
$httpOtherUser=$httpOtherUserQuery->fetch(PDO::FETCH_ASSOC);
if (!$httpOtherUser) throw new RuntimeException('A second active, unassigned teacher fixture is required for HTTP ownership testing.');
$httpPeriod=$db->query('SELECT p.* FROM periode_penilaian p JOIN erapor_sesi s ON s.periode_id=p.id WHERE s.id='.$httpSid)->fetch(PDO::FETCH_ASSOC);
$httpNow=(new DateTimeImmutable('now',new DateTimeZone('Asia/Makassar')))->format('Y-m-d');
$httpNewDeadline=(new DateTimeImmutable($httpNow,new DateTimeZone('Asia/Makassar')))->modify('+14 days')->format('Y-m-d');
$httpRestoreUser=$db->prepare('UPDATE users SET password_hash=?,updated_at=? WHERE id=?');
$httpRestorePeriod=$db->prepare('UPDATE periode_penilaian SET akhir_periode=?,updated_at=? WHERE id=?');
$httpOriginalAccount=$db->query('SELECT password_hash,updated_at FROM users WHERE id='.$httpActor)->fetch(PDO::FETCH_ASSOC);
$httpPermissionIds=$db->query("SELECT aksi,id FROM permissions WHERE modul='Portal Guru' AND section='Daftar Murid' AND aksi IN ('lihat','edit','kirim')")->fetchAll(PDO::FETCH_KEY_PAIR);
$httpRoles=array_values(array_unique([(int)$httpUser['role_id'],(int)$httpOtherUser['role_id']])); $httpPrior=[];
$httpPriorPermissions=$db->prepare('SELECT id,permission_id,created_at FROM role_permissions WHERE role_id=? ORDER BY id');
foreach ($httpRoles as $httpRole) { $httpPriorPermissions->execute([$httpRole]); $httpPrior[$httpRole]=$httpPriorPermissions->fetchAll(PDO::FETCH_ASSOC); }
$httpGrant=$db->prepare('INSERT IGNORE INTO role_permissions(role_id,permission_id) VALUES(?,?)');
foreach ($httpRoles as $httpRole) foreach ($httpPermissionIds as $httpPermissionId) $httpGrant->execute([$httpRole,$httpPermissionId]);
$httpRestoreUser->execute([password_hash('EraporFixture123$',PASSWORD_DEFAULT),'2000-01-01 00:00:00',$httpActor]);
$httpOtherOriginalAccount=$db->query('SELECT password_hash,updated_at FROM users WHERE id='.(int)$httpOtherUser['id'])->fetch(PDO::FETCH_ASSOC);
$httpRestoreUser->execute([password_hash('EraporFixture123$',PASSWORD_DEFAULT),'2000-01-01 00:00:00',$httpOtherUser['id']]);
$httpRestorePeriod->execute([$httpNewDeadline,'2000-01-01 00:00:00',$httpPeriod['id']]);
$httpSocket=stream_socket_server('tcp://127.0.0.1:0',$httpErr,$httpErrText);
if (!$httpSocket) throw new RuntimeException('Cannot reserve API E2E port.');
$httpAddress=stream_socket_get_name($httpSocket,false); fclose($httpSocket);
$httpEnv=getenv(); $httpEnv['ZIVANA_E2E_DB']=$database; $httpEnv['ERAPOR_API_ENABLED']='true';
$httpLog=tmpfile();
$httpServer=proc_open([PHP_BINARY,'-S',$httpAddress,'-t',ROOT_PATH.'/public',ROOT_PATH.'/tests/fixtures/workflow-http-router.php'],
    [0=>['pipe','r'],1=>$httpLog,2=>$httpLog],$httpPipes,ROOT_PATH,$httpEnv,['bypass_shell'=>true]);
if (!is_resource($httpServer)) throw new RuntimeException('Could not launch isolated API server.');
fclose($httpPipes[0]);
$httpReady=false;
for ($i=0;$i<40;$i++) { $probe=@stream_socket_client('tcp://'.$httpAddress,$httpErr,$httpErrText,0.1); if ($probe) { fclose($probe);$httpReady=true;break; } usleep(100000); }
if (!$httpReady) { proc_terminate($httpServer); proc_close($httpServer); throw new RuntimeException('Isolated API server did not start.'); }
$httpTeacher=new EraporHttpClient('http://'.$httpAddress);
$httpOther=new EraporHttpClient('http://'.$httpAddress);
try {
    $httpGuest=(new EraporHttpClient('http://'.$httpAddress));
    $httpGuestResult=$httpGuest->api('GET',"/api/erapor/sesi/$httpSid");
    if ($httpGuestResult['status']!==401 || ($httpGuestResult['json']['error']['code'] ?? null)!=='AUTH_REQUIRED') {
        rewind($httpLog); $httpDebug=stream_get_contents($httpLog);
        throw new RuntimeException('Unauthenticated API denied: '.($httpGuestResult['status']).' login='.$httpLoginProbe['status'].' '.($httpGuestResult['json']['error']['code'] ?? substr(strip_tags($httpGuestResult['body']),0,120)).' SERVER_LOG='.substr((string)$httpDebug,-1200));
    }
    $httpGuest->close();
    $httpTeacher->login($httpUser['email']);
    $httpShow=$httpTeacher->api('GET',"/api/erapor/sesi/$httpSid");
    catalogCheck($httpShow['status']===200 && $httpShow['json']['ok'],'Authenticated HTTP session read');
    catalogCheck((int)$httpShow['json']['data']['session']['id']===$httpSid && count($httpShow['json']['data']['documents'])===5,'Actual session ID and ABK package returned');
    catalogCheck($httpShow['type']==='application/json' && str_contains($httpShow['body'],'csrf_token'),'JSON response provides next single-use token');
    $httpDocs=array_column($httpShow['json']['data']['documents'],null,'jenis_dokumen');
    $httpComment=null;
    foreach ($httpDocs['BING']['form']['definitions']['comments'] as $comment) if ((bool)$comment['wajib']) { $httpComment=$comment; break; }
    if (!$httpComment) throw new RuntimeException('Required BING comment fixture is unavailable.');
    $httpKey='komentar:'.$httpComment['id']; $httpExpected=$httpDocs['BING']['form']['values'][$httpKey] ?? null;
    $httpChanges=[['key'=>$httpKey,'value'=>'Penilaian fixture melalui HTTP.','expected'=>$httpExpected]];
    $httpOldToken=$httpTeacher->csrf;
    $httpSave=$httpTeacher->api('POST',"/api/erapor/sesi/$httpSid/dokumen/".$httpDocs['BING']['id'].'/simpan',['changes'=>$httpChanges]);
    catalogCheck($httpSave['status']===200 && $httpSave['json']['ok'] && $httpSave['json']['data']['changed']===1,'Real HTTP autosave persists via service');
    $httpStored=$db->prepare('SELECT isi FROM erapor_bing_isian WHERE sesi_id=? AND dokumen_id=? AND komentar_id=?');
    $httpStored->execute([$httpSid,$httpDocs['BING']['id'],$httpComment['id']]);
    catalogCheck($httpStored->fetchColumn()==='Penilaian fixture melalui HTTP.','Autosave persisted exact text');
    $httpReplay=$httpTeacher->api('POST',"/api/erapor/sesi/$httpSid/dokumen/".$httpDocs['BING']['id'].'/simpan',['changes'=>$httpChanges],$httpOldToken);
    catalogCheck($httpReplay['status']===419 && ($httpReplay['json']['error']['code'] ?? '')==='CSRF_INVALID','Single-use CSRF token rejected on replay');
    $httpBadDoc=$httpTeacher->api('POST',"/api/erapor/sesi/$httpSid/dokumen/2147483647/simpan",['changes'=>$httpChanges]);
    catalogCheck($httpBadDoc['status']===409,'Document outside session rejected: '.$httpBadDoc['status'].' '.($httpBadDoc['json']['error']['code'] ?? 'non-json'));
    $httpMalformed=$httpTeacher->api('POST',"/api/erapor/sesi/$httpSid/dokumen/".$httpDocs['BING']['id'].'/simpan',['changes'=>[['key'=>'komentar:1','value'=>'x','expected'=>null,'surprise'=>true]]]);
    catalogCheck($httpMalformed['status']===422,'Malformed change rejected');
    $httpReceive=$httpTeacher->api('POST',"/api/erapor/sesi/$httpSid/konfirmasi-penerimaan",new stdClass());
    catalogCheck($httpReceive['status']===409,'Wrong reception transition rejected');
    $httpOther->login($httpOtherUser['email']);
    $httpForeign=$httpOther->api('GET',"/api/erapor/sesi/$httpSid");
    catalogCheck($httpForeign['status']===409 && !str_contains($httpForeign['body'],'nama_lengkap'),'Foreign session receives generic denial');
    $httpOther->close();
    $db->prepare('DELETE FROM role_permissions WHERE role_id=? AND permission_id=?')->execute([$httpUser['role_id'],$httpPermissionIds['edit']]);
    $httpNoEdit=$httpTeacher->api('POST',"/api/erapor/sesi/$httpSid/dokumen/".$httpDocs['BING']['id'].'/simpan',['changes'=>$httpChanges]);
    catalogCheck($httpNoEdit['status']===403,'Actual API enforces RBAC on write');
    catalogCheck(!$db->inTransaction(),'HTTP API requests leave connection clean');
} finally {
    $httpTeacher->close(); if (isset($httpOther)) $httpOther->close();
    proc_terminate($httpServer); proc_close($httpServer);
    $httpRestorePeriod->execute([$httpPeriod['akhir_periode'],$httpPeriod['updated_at'],$httpPeriod['id']]);
    $httpRestoreUser->execute([$httpOriginalAccount['password_hash'],$httpOriginalAccount['updated_at'],$httpActor]);
    $httpRestoreUser->execute([$httpOtherOriginalAccount['password_hash'],$httpOtherOriginalAccount['updated_at'],$httpOtherUser['id']]);
    foreach ($httpRoles as $httpRole) {
        $db->prepare('DELETE FROM role_permissions WHERE role_id=?')->execute([$httpRole]);
        foreach ($httpPrior[$httpRole] as $httpGrantRow) $db->prepare('INSERT INTO role_permissions(id,role_id,permission_id,created_at) VALUES(?,?,?,?)')->execute([$httpGrantRow['id'],$httpRole,$httpGrantRow['permission_id'],$httpGrantRow['created_at']]);
    }
}
