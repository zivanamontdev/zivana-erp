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
        $responseHeaders=[];
        $options=[CURLOPT_URL=>$this->base.$path,CURLOPT_HTTPGET=>true,CURLOPT_HTTPHEADER=>$headers,
            CURLOPT_HEADERFUNCTION=>function($curl,string $line)use(&$responseHeaders):int {
                $parts=explode(':',$line,2);
                if (count($parts)===2) $responseHeaders[strtolower(trim($parts[0]))]=trim($parts[1]);
                return strlen($line);
            }];
        if ($body!==null) { $options[CURLOPT_POST]=true; $options[CURLOPT_POSTFIELDS]=$body; }
        curl_setopt_array($this->curl,$options);
        $raw=curl_exec($this->curl); $status=curl_getinfo($this->curl,CURLINFO_RESPONSE_CODE);
        if ($raw===false) throw new RuntimeException('Loopback HTTP request failed: '.curl_error($this->curl));
        return ['status'=>$status,'body'=>$raw,'json'=>json_decode($raw,true),'type'=>curl_getinfo($this->curl,CURLINFO_CONTENT_TYPE),'headers'=>$responseHeaders];
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
    AND NOT EXISTS (SELECT 1 FROM kelas_guru_murid a WHERE a.murid_id=? AND a.guru_id=k.id)
    AND NOT EXISTS (SELECT 1 FROM erapor_penyetuju_user au WHERE au.user_id=u.id AND au.aktif=1) ORDER BY u.id LIMIT 1");
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
$httpApprovalPermissionIds=[];
$httpAddedApprovalPermissionIds=[];
foreach (['lihat','edit'] as $httpApprovalAction) {
    $httpPermission=$db->prepare('SELECT id FROM permissions WHERE modul=? AND section=? AND sub_section IS NULL AND aksi=? LIMIT 1');
    $httpPermission->execute(['eRapor','Persetujuan',$httpApprovalAction]);
    $httpPermissionId=$httpPermission->fetchColumn();
    if (!$httpPermissionId) {
        $db->prepare('INSERT INTO permissions(modul,section,sub_section,aksi,display_order) VALUES(?,?,NULL,?,100)')
            ->execute(['eRapor','Persetujuan',$httpApprovalAction]);
        $httpPermissionId=$db->lastInsertId();
        $httpAddedApprovalPermissionIds[]=(int)$httpPermissionId;
    }
    $httpApprovalPermissionIds[$httpApprovalAction]=(int)$httpPermissionId;
}
$httpSetupPermission=$db->prepare("SELECT id FROM permissions WHERE modul='eRapor' AND section='Penugasan Penyetuju' AND sub_section IS NULL AND aksi='lihat' LIMIT 1");
$httpSetupPermission->execute(); $httpSetupPermissionId=$httpSetupPermission->fetchColumn();
if (!$httpSetupPermissionId) {
    $db->prepare('INSERT INTO permissions(modul,section,sub_section,aksi,display_order) VALUES(?,?,NULL,?,102)')
        ->execute(['eRapor','Penugasan Penyetuju','lihat']);
    $httpSetupPermissionId=(int)$db->lastInsertId();
    $httpAddedApprovalPermissionIds[]=$httpSetupPermissionId;
} else $httpSetupPermissionId=(int)$httpSetupPermissionId;
$httpSignerPermissionIds=[];
foreach (['lihat','edit'] as $httpSignerAction) {
    $httpSignerPermission=$db->prepare("SELECT id FROM permissions WHERE modul='eRapor' AND section='Profil Penandatangan' AND sub_section IS NULL AND aksi=? LIMIT 1");
    $httpSignerPermission->execute([$httpSignerAction]); $httpSignerPermissionId=$httpSignerPermission->fetchColumn();
    if (!$httpSignerPermissionId) {
        $db->prepare('INSERT INTO permissions(modul,section,sub_section,aksi,display_order) VALUES(?,?,NULL,?,?)')
            ->execute(['eRapor','Profil Penandatangan',$httpSignerAction,$httpSignerAction==='lihat'?104:105]);
        $httpSignerPermissionId=(int)$db->lastInsertId(); $httpAddedApprovalPermissionIds[]=$httpSignerPermissionId;
    }
    $httpSignerPermissionIds[$httpSignerAction]=(int)$httpSignerPermissionId;
}
$httpRoles=array_values(array_unique([(int)$httpUser['role_id'],(int)$httpOtherUser['role_id']])); $httpPrior=[];
$httpPriorPermissions=$db->prepare('SELECT id,permission_id,created_at FROM role_permissions WHERE role_id=? ORDER BY id');
foreach ($httpRoles as $httpRole) { $httpPriorPermissions->execute([$httpRole]); $httpPrior[$httpRole]=$httpPriorPermissions->fetchAll(PDO::FETCH_ASSOC); }
$httpGrant=$db->prepare('INSERT IGNORE INTO role_permissions(role_id,permission_id) VALUES(?,?)');
foreach ($httpRoles as $httpRole) foreach ($httpPermissionIds as $httpPermissionId) $httpGrant->execute([$httpRole,$httpPermissionId]);
foreach ($httpRoles as $httpRole) foreach ($httpApprovalPermissionIds as $httpApprovalPermissionId) $httpGrant->execute([$httpRole,$httpApprovalPermissionId]);
foreach ($httpRoles as $httpRole) $httpGrant->execute([$httpRole,$httpSetupPermissionId]); // Deliberate accidental grant to test hard teacher denial.
foreach ($httpRoles as $httpRole) foreach ($httpSignerPermissionIds as $httpSignerPermissionId) $httpGrant->execute([$httpRole,$httpSignerPermissionId]);
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
    $httpTeacherSetup=$httpTeacher->raw('/erapor/persetujuan/penugasan');
    catalogCheck($httpTeacherSetup['status']===403 && !str_contains($httpTeacherSetup['body'],'Simpan Penugasan'),
        'Teacher is denied approver setup even when the role permission is accidentally granted');
    $httpSignerPage=$httpTeacher->raw('/erapor/profil-penandatangan');
    catalogCheck($httpSignerPage['status']===200 && str_contains($httpSignerPage['body'],'Profil Penandatangan')
        && str_contains($httpSignerPage['body'],'enctype="multipart/form-data"'),
        'Active teacher opens only their own self-service signer profile over HTTP');
    $httpApprovalIndex=$httpTeacher->raw('/erapor/persetujuan');
    catalogCheck($httpApprovalIndex['status']===200 && str_contains($httpApprovalIndex['body'],'Antrean Persetujuan')
        && str_contains($httpApprovalIndex['body'],'data-table'), 'Assigned teacher with RBAC can open the approval inbox over HTTP');
    $httpApprovalTarget=(int)$approveB;
    $httpApprovalReview=$httpTeacher->raw('/erapor/persetujuan/'.(int)$approveSid.'/'.$httpApprovalTarget);
    $httpScopedCount=$db->prepare('SELECT COUNT(*) FROM erapor_sesi_penyetuju_dokumen WHERE sesi_penyetuju_id=?');
    $httpScopedCount->execute([$httpApprovalTarget]);
    catalogCheck($httpApprovalReview['status']===200 && str_contains($httpApprovalReview['body'],'Rapor Bahasa Inggris')
        && substr_count($httpApprovalReview['body'],'class="data-table erapor-approval-values"')===(int)$httpScopedCount->fetchColumn(),
        'Reviewer HTTP page renders only documents assigned to this approval stage');
    catalogCheck(str_contains(strtolower($httpApprovalReview['headers']['cache-control'] ?? ''),'no-store')
        && str_contains(strtolower($httpApprovalReview['headers']['x-robots-tag'] ?? ''),'noindex'),
        'Reviewer page disables private-data caching and indexing');
    $httpApprovalDbBefore=catalogFingerprints($db);
    $httpTeacher->api('GET',"/api/erapor/sesi/$httpSid"); // Rotates a valid session CSRF token for the HTML POST.
    $httpApprovalPost=$httpTeacher->raw('/erapor/persetujuan/'.(int)$approveSid.'/'.$httpApprovalTarget.'/setujui',
        http_build_query(['csrf_token'=>$httpTeacher->csrf]),['Content-Type: application/x-www-form-urlencoded']);
    catalogCheck($httpApprovalPost['status']===302 && catalogFingerprints($db)===$httpApprovalDbBefore,
        'Authorized approval route accepts CSRF and safely retries already-recorded approval without duplicate writes');
    $httpApprovalAfter=$httpTeacher->raw('/erapor/persetujuan/'.(int)$approveSid.'/'.$httpApprovalTarget);
    catalogCheck($httpApprovalAfter['status']===200 && str_contains($httpApprovalAfter['body'],'Seluruh persetujuan tercatat'),
        'Approval route reports completed signature chain while leaving publication separate');
    $httpApprovalSnapshot=$db->prepare('SELECT log_id FROM erapor_persetujuan_snapshot WHERE sesi_penyetuju_id=?');
    $httpApprovalSnapshot->execute([$httpApprovalTarget]);
    $httpApprovalLog=(int)$httpApprovalSnapshot->fetchColumn();
    $db->prepare('DELETE FROM erapor_persetujuan_snapshot WHERE sesi_penyetuju_id=?')->execute([$httpApprovalTarget]);
    $db->prepare('DELETE FROM erapor_sesi_log WHERE id=?')->execute([$httpApprovalLog]);
    $db->prepare("UPDATE erapor_sesi_penyetuju SET status='MENUNGGU' WHERE id=?")->execute([$httpApprovalTarget]);
    $db->prepare('DELETE FROM role_permissions WHERE role_id=? AND permission_id=?')
        ->execute([(int)$httpUser['role_id'],$httpApprovalPermissionIds['edit']]);
    $httpReadOnlyApproval=$httpTeacher->raw('/erapor/persetujuan/'.(int)$approveSid.'/'.$httpApprovalTarget);
    catalogCheck($httpReadOnlyApproval['status']===200 && str_contains($httpReadOnlyApproval['body'],'Siap ditinjau')
        && !str_contains($httpReadOnlyApproval['body'],'data-modal-open="modal-setujui-erapor-'),
        'View-only RBAC can inspect an actionable assignment but has no approval control');
    $httpTeacher->api('GET',"/api/erapor/sesi/$httpSid");
    $httpNoApprovalEdit=$httpTeacher->raw('/erapor/persetujuan/'.(int)$approveSid.'/'.$httpApprovalTarget.'/setujui',
        http_build_query(['csrf_token'=>$httpTeacher->csrf]),['Content-Type: application/x-www-form-urlencoded']);
    catalogCheck($httpNoApprovalEdit['status']===403, 'Approval endpoint enforces the distinct eRapor edit permission');
    $httpOther->login($httpOtherUser['email']);
    $httpUnassignedApproval=$httpOther->raw('/erapor/persetujuan');
    catalogCheck($httpUnassignedApproval['status']===403 && !str_contains($httpUnassignedApproval['body'],'Rapor Bahasa Inggris'),
        'Role permission without any explicit approver assignment cannot access the inbox');
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
    $httpCreatePeriod=$db->query('SELECT * FROM periode_penilaian WHERE id='.(int)$httpCreateCandidate['periode_id'])->fetch(PDO::FETCH_ASSOC);
    if ((int)$httpCreatePeriod['id']!==(int)$httpPeriod['id']) $httpRestorePeriod->execute([$httpNewDeadline,'2000-01-01 00:00:00',$httpCreatePeriod['id']]);
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
        && str_contains($httpSessionPage['body'],'data-erapor-ummi-init'),'New session ID opens its protected editor with explicit Ummi initialization control');

    $httpNewSid=(int)$httpNewSession['id'];
    $httpNewRead=$httpTeacher->api('GET',"/api/erapor/sesi/$httpNewSid");
    catalogCheck($httpNewRead['status']===200 && $httpNewRead['json']['ok'],'New session read includes Ummi form');
    $httpNewDocs=array_column($httpNewRead['json']['data']['documents'],null,'jenis_dokumen');
    $httpUmmiDoc=$httpNewDocs['UMMI'];
    $httpUmmiForm=$httpUmmiDoc['form'];
    catalogCheck($httpUmmiForm['definitions']['initialization_required'] && $httpUmmiForm['values']['mulai_pra_tk']===null,
        'HTTP GET keeps Ummi period uninitialized');
    $httpInitBefore=$db->prepare('SELECT COUNT(*) FROM erapor_ummi_periode WHERE sesi_id=? AND dokumen_id=?');
    $httpInitBefore->execute([$httpNewSid,$httpUmmiDoc['id']]);
    catalogCheck((int)$httpInitBefore->fetchColumn()===0,'Opening Ummi editor does not create its period row');
    $httpInitUmmi=$httpTeacher->api('POST',"/api/erapor/sesi/$httpNewSid/dokumen/".$httpUmmiDoc['id'].'/simpan',['changes'=>[]]);
    catalogCheck($httpInitUmmi['status']===200 && $httpInitUmmi['json']['data']['initialized'],'Explicit Ummi start initializes and audits the period');
    $httpUmmiAfterInit=$httpTeacher->api('GET',"/api/erapor/sesi/$httpNewSid");
    $httpAfterInitDocs=array_column($httpUmmiAfterInit['json']['data']['documents'],null,'jenis_dokumen');
    $httpUmmiForm=$httpAfterInitDocs['UMMI']['form'];
    catalogCheck(!$httpUmmiForm['definitions']['initialization_required'] && $httpUmmiForm['values']['mulai_pra_tk']===false,
        'Initialized Ummi form starts with PRA TK off');
    $httpUmmiItem=$httpUmmiForm['definitions']['items'][0];
    $httpUmmiGrade=$httpUmmiForm['definitions']['scale'][0]['kode'];
    $httpUmmiVolume='I';
    $httpTestToken='abcdef0123456789abcdef0123456789';
    $httpTestValue=['urutan'=>1,'tanggal_tes'=>$httpNow,'jilid'=>$httpUmmiVolume,'nilai'=>$httpUmmiGrade];
    $httpUmmiSave=$httpTeacher->api('POST',"/api/erapor/sesi/$httpNewSid/dokumen/".$httpUmmiDoc['id'].'/simpan',['changes'=>[
        ['key'=>'mulai_pra_tk','value'=>true,'expected'=>false],
        ['key'=>'bacaan:'.$httpUmmiItem['id'],'value'=>$httpUmmiGrade,'expected'=>null],
        ['key'=>'tes:'.$httpTestToken,'value'=>$httpTestValue,'expected'=>null],
        ['key'=>'catatan','value'=>'Catatan Ummi tersimpan lewat HTTP.','expected'=>null],
    ]]);
    catalogCheck($httpUmmiSave['status']===200 && $httpUmmiSave['json']['data']['changed']===4,
        'HTTP Ummi autosave persists flag, reading grade, dynamic test, and teacher note');
    $httpUmmiCompletion=array_values(array_filter($httpUmmiSave['json']['data']['completion']['documents'],fn($row)=>$row['jenis']==='UMMI'))[0] ?? null;
    catalogCheck($httpUmmiCompletion && $httpUmmiCompletion['complete'] && $httpUmmiCompletion['filled']===1,
        'Ummi completion is driven only by its required period note');
    $httpDeleteTest=$httpTeacher->api('POST',"/api/erapor/sesi/$httpNewSid/dokumen/".$httpUmmiDoc['id'].'/simpan',['changes'=>[
        ['key'=>'tes:'.$httpTestToken,'value'=>null,'expected'=>$httpTestValue],
    ]]);
    catalogCheck($httpDeleteTest['status']===200 && $httpDeleteTest['json']['data']['changed']===1
        && (int)$db->query('SELECT COUNT(*) FROM erapor_ummi_tes WHERE sesi_id='.$httpNewSid)->fetchColumn()===0,
        'HTTP Ummi dynamic test deletion uses the saved expected value');

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
    $httpReceptionRetry=$httpTeacher->api('POST',"/api/erapor/sesi/".(int)$abkSession['id']."/konfirmasi-penerimaan",new stdClass());
    catalogCheck($httpReceptionRetry['status']===200 && ($httpReceptionRetry['json']['data']['result'] ?? '')==='already_confirmed',
        'Authenticated teacher can reach the distinct reception API action; exact retry is idempotent');
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
    if (isset($httpCreatePeriod) && (int)$httpCreatePeriod['id']!==(int)$httpPeriod['id'])
        $httpRestorePeriod->execute([$httpCreatePeriod['akhir_periode'],$httpCreatePeriod['updated_at'],$httpCreatePeriod['id']]);
    $httpRestoreUser->execute([$httpOriginalAccount['password_hash'],$httpOriginalAccount['updated_at'],$httpActor]);
    $httpRestoreUser->execute([$httpOtherOriginalAccount['password_hash'],$httpOtherOriginalAccount['updated_at'],$httpOtherUser['id']]);
    foreach ($httpRoles as $httpRole) {
        $db->prepare('DELETE FROM role_permissions WHERE role_id=?')->execute([$httpRole]);
        foreach ($httpPrior[$httpRole] as $httpGrantRow) $db->prepare('INSERT INTO role_permissions(id,role_id,permission_id,created_at) VALUES(?,?,?,?)')->execute([$httpGrantRow['id'],$httpRole,$httpGrantRow['permission_id'],$httpGrantRow['created_at']]);
    }
    foreach ($httpAddedApprovalPermissionIds as $httpPermissionId)
        $db->prepare('DELETE FROM permissions WHERE id=?')->execute([$httpPermissionId]);
}
