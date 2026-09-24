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
    $httpDashboardBefore=catalogFingerprints($db);
    $httpDashboard=$httpTeacher->raw('/portal-guru/dashboard?periode_id='.(int)$middle['id']);
    catalogCheck($httpDashboard['status']===200 && str_contains($httpDashboard['body'],'Daftar Murid')
        && str_contains($httpDashboard['body'],'Tahun Ajaran'),'New teacher dashboard renders selected calendar period');
    catalogCheck(catalogFingerprints($db)===$httpDashboardBefore,'Dashboard GET does not provision sessions or write assessment data');
    if (!preg_match('/name="csrf_token" value="([^"]+)"/',$httpDashboard['body'],$httpDashboardCsrf)) throw new RuntimeException('Teacher dashboard CSRF token missing.');
    $httpCreateCandidate=$db->query("SELECT m.id AS murid_id,p.id AS periode_id FROM kelas_guru_murid a
        JOIN murid m ON m.id=a.murid_id AND m.kelas_id=a.kelas_id
        JOIN karyawan k ON k.id=a.guru_id JOIN users u ON u.karyawan_id=k.id
        JOIN jabatan j ON j.id=k.jabatan_id
        JOIN periode_penilaian p ON p.tipe='Tengah Semester' AND p.semester IN ('ganjil','genap')
        WHERE u.id=".(int)$httpActor." AND u.is_active=1 AND k.is_active=1 AND j.is_active=1
        AND NOT EXISTS (SELECT 1 FROM erapor_sesi s WHERE s.murid_id=m.id AND s.periode_id=p.id)
        ORDER BY p.id,m.id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$httpCreateCandidate) throw new RuntimeException('An assigned pupil with an uninitialized middle-period session is required for HTTP provisioning test.');
    $httpProvision=$httpTeacher->raw('/portal-guru/sesi/siapkan',http_build_query([
        'csrf_token'=>$httpDashboardCsrf[1], 'murid_id'=>$httpCreateCandidate['murid_id'], 'periode_id'=>$httpCreateCandidate['periode_id'],
    ]),['Content-Type: application/x-www-form-urlencoded']);
    catalogCheck($httpProvision['status']===302,'Session provisioning is an explicit CSRF-protected POST');
    $httpNewSessionQuery=$db->prepare('SELECT id,guru_user_id,status FROM erapor_sesi WHERE murid_id=? AND periode_id=?');
    $httpNewSessionQuery->execute([(int)$httpCreateCandidate['murid_id'],(int)$httpCreateCandidate['periode_id']]);
    $httpNewSession=$httpNewSessionQuery->fetch(PDO::FETCH_ASSOC);
    catalogCheck($httpNewSession && (int)$httpNewSession['guru_user_id']===$httpActor && $httpNewSession['status']==='BELUM_DIISI','Provisioned session has authenticated teacher snapshot and initial state');
    $httpSessionPage=$httpTeacher->raw('/portal-guru/sesi/'.(int)$httpNewSession['id']);
    catalogCheck($httpSessionPage['status']===200 && str_contains($httpSessionPage['body'],'data-erapor-editor')
        && str_contains($httpSessionPage['body'],'erapor-session.js')
        && str_contains($httpSessionPage['body'],'belum tersedia di editor ini'),'New session ID opens its own protected e-Rapor editor and identifies unsupported Ummi');
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
    catalogCheck($httpSave['status']===200 && $httpSave['json']['ok'] && $httpSave['json']['data']['changed']===1,'Real HTTP BING autosave persists via service');
    catalogCheck(isset($httpSave['json']['data']['completion']['documents'])
        && array_key_exists('can_confirm_filled',$httpSave['json']['data']['capabilities'] ?? []),
        'Autosave response includes authoritative package completion and submission capability');
    $httpStored=$db->prepare('SELECT isi FROM erapor_bing_isian WHERE sesi_id=? AND dokumen_id=? AND komentar_id=?');
    $httpStored->execute([$httpSid,$httpDocs['BING']['id'],$httpComment['id']]);
    catalogCheck($httpStored->fetchColumn()==='Penilaian fixture melalui HTTP.','Autosave persisted exact text');

    $httpRtsItem=$httpDocs['RTS']['form']['definitions']['items'][0];
    $httpRtsKey='nilai:'.$httpRtsItem['id'];
    $httpRtsExpected=$httpDocs['RTS']['form']['values'][$httpRtsKey] ?? null;
    $httpRtsValue=$httpRtsExpected===1?2:1;
    $httpRtsSave=$httpTeacher->api('POST',"/api/erapor/sesi/$httpSid/dokumen/".$httpDocs['RTS']['id'].'/simpan',[
        'changes'=>[['indikator_id'=>(int)$httpRtsItem['id'],'nilai'=>$httpRtsValue,'expected'=>$httpRtsExpected]],
    ]);
    catalogCheck($httpRtsSave['status']===200 && $httpRtsSave['json']['data']['changed']===1,'Real HTTP RTS image-scale selection autosaves');

    $httpAgamaItem=$httpDocs['AGAMA']['form']['definitions']['items'][0];
    $httpAgamaKey='nilai:'.$httpAgamaItem['id'];
    $httpAgamaExpected=$httpDocs['AGAMA']['form']['values'][$httpAgamaKey] ?? null;
    $httpAgamaChoice=null;
    foreach ($httpDocs['AGAMA']['form']['definitions']['scale'] as $choice) {
        if ($choice['kolom_cetak']!==$httpAgamaExpected) { $httpAgamaChoice=$choice['kolom_cetak']; break; }
    }
    if ($httpAgamaChoice===null) throw new RuntimeException('Agama grade fixture is unavailable.');
    $httpAgamaSave=$httpTeacher->api('POST',"/api/erapor/sesi/$httpSid/dokumen/".$httpDocs['AGAMA']['id'].'/simpan',[
        'changes'=>[['key'=>$httpAgamaKey,'value'=>$httpAgamaChoice,'expected'=>$httpAgamaExpected]],
    ]);
    catalogCheck($httpAgamaSave['status']===200 && $httpAgamaSave['json']['data']['changed']===1,'Real HTTP Agama semester choice autosaves');

    $httpPpiAspect=$httpDocs['PPI']['form']['definitions']['aspects'][0];
    $httpPpiColumn=$httpDocs['PPI']['form']['definitions']['columns'][0];
    $httpPpiKey=$httpPpiAspect['id'].':'.$httpPpiColumn['id'];
    $httpPpiExpected=$httpDocs['PPI']['form']['values'][$httpPpiKey] ?? null;
    $httpPpiSave=$httpTeacher->api('POST',"/api/erapor/sesi/$httpSid/dokumen/".$httpDocs['PPI']['id'].'/simpan',[
        'changes'=>[['key'=>$httpPpiKey,'value'=>'PPI fixture melalui HTTP.','expected'=>$httpPpiExpected]],
    ]);
    catalogCheck($httpPpiSave['status']===200 && $httpPpiSave['json']['data']['changed']===1,'Real HTTP PPI session text autosaves');
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
    $httpDashboardNoEdit=$httpTeacher->raw('/portal-guru/dashboard?periode_id='.(int)$httpCreateCandidate['periode_id']);
    catalogCheck($httpDashboardNoEdit['status']===200 && !str_contains($httpDashboardNoEdit['body'],'/portal-guru/sesi/'.(int)$httpNewSession['id'])
        && str_contains($httpDashboardNoEdit['body'],'Akses dibatasi'),'Dashboard hides editable session action when RBAC edit is revoked');
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
