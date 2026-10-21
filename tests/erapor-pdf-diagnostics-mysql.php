<?php
/** php tests/erapor-pdf-diagnostics-mysql.php --run : isolated clone, no R2/network or source changes. */
if(PHP_SAPI!=='cli'||!in_array('--run',$argv,true)){echo "Usage: php tests/erapor-pdf-diagnostics-mysql.php --run\n";exit(1);}
define('ROOT_PATH',dirname(__DIR__));define('CONFIG_PATH',ROOT_PATH.'/config');define('BASE_PATH','');
require CONFIG_PATH.'/config.php';require ROOT_PATH.'/vendor/autoload.php';require ROOT_PATH.'/app/helpers/functions.php';
spl_autoload_register(static function($c){foreach(['core','models','middleware'] as $d){$p=ROOT_PATH.'/app/'.$d.'/'.$c.'.php';if(is_file($p)){require $p;return;}}});
if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
if(!in_array(DB_HOST,['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Local database only');
$opts=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false];
$dsn='mysql:host='.DB_HOST.';port='.DB_PORT.';charset=utf8mb4';$live=new PDO($dsn.';dbname='.DB_NAME,DB_USER,DB_PASS,$opts);$db=new PDO($dsn,DB_USER,DB_PASS,$opts);
$q=static fn($s)=>'`'.str_replace('`','``',$s).'`';
$fingerprint=static function(PDO $db)use($q):array{$out=[];foreach($db->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN) as $t){$rows=[];foreach($db->query('SELECT * FROM '.$q($t)) as $r)$rows[]=hash('sha256',serialize($r));sort($rows);$out[$t]=[count($rows),hash('sha256',implode('',$rows))];}ksort($out);return $out;};
$before=$fingerprint($live);$name='zivana_diag_test_'.bin2hex(random_bytes(8));$created=false;$checks=0;
$check=static function(bool $ok,string $label)use(&$checks){if(!$ok)throw new RuntimeException($label);$checks++;};
try{
 $db->exec('CREATE DATABASE '.$q($name).' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$created=true;$db->exec('USE '.$q($name));$db->exec('SET FOREIGN_KEY_CHECKS=0');
 foreach(array_keys($before) as $t)$db->exec($live->query('SHOW CREATE TABLE '.$q($t))->fetch(PDO::FETCH_NUM)[1]);
 foreach(array_keys($before) as $t)$db->exec('INSERT INTO '.$q($t).' SELECT * FROM '.$q(DB_NAME).'.'.$q($t));$db->exec('SET FOREIGN_KEY_CHECKS=1');
 $check($fingerprint($db)===$before,'Clone matches source before diagnosis');
 $admin=(int)$db->query("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE r.nama='Superadmin' AND u.is_active=1 LIMIT 1")->fetchColumn();
 $teacher=(int)$db->query("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE r.nama<>'Superadmin' AND u.is_active=1 LIMIT 1")->fetchColumn();
 // Establish missing stored-artifact fixtures ONLY in this disposable clone; no workflow/production claims.
 foreach(['Regular','ABK'] as $condition){
  $exists=$db->query("SELECT 1 FROM erapor_publikasi_pdf p JOIN erapor_sesi s ON s.id=p.sesi_id WHERE s.kondisi=".$db->quote($condition)." LIMIT 1")->fetchColumn();
  if($exists)continue;
  $fixtureSid=(int)$db->query('SELECT id FROM erapor_sesi WHERE kondisi='.$db->quote($condition).' ORDER BY id LIMIT 1')->fetchColumn();
  if(!$fixtureSid)throw new RuntimeException('Requires local '.$condition.' session');
  $pdf=EraporPackagePdfRenderer::render(EraporPublication::draftPackage($db,$fixtureSid));$sha=hash('sha256',$pdf);
  $db->prepare("INSERT INTO erapor_publikasi_pdf(sesi_id,disiapkan_oleh,renderer_versi,filename,ukuran_byte,pdf_sha256,sumber_sha256,manifest,pdf_bytes) VALUES(?,?,?,?,?,?,?,?,?)")
   ->execute([$fixtureSid,$admin,EraporPackagePdfRenderer::version(),'synthetic-diagnostic-test.pdf',strlen($pdf),$sha,str_repeat('a',64),'{}',$pdf]);
 }
 // Explicit scope fixtures for draft ABK data that has no approval rows yet. Clone only.
 foreach(['Regular','ABK'] as $condition){
  $fixtureSid=(int)$db->query('SELECT s.id FROM erapor_sesi s JOIN erapor_publikasi_pdf p ON p.sesi_id=s.id WHERE s.kondisi='.$db->quote($condition).' ORDER BY s.id LIMIT 1')->fetchColumn();
  $db->prepare("INSERT INTO erapor_sesi_penyetuju(sesi_id,penyetuju_id,kode,label,urutan,cakupan) SELECT ?,a.id,a.kode,a.label,a.urutan,a.cakupan FROM erapor_alur_penyetuju a WHERE a.cakupan='TERBATAS' AND a.aktif=1 AND NOT EXISTS(SELECT 1 FROM erapor_sesi_penyetuju sp WHERE sp.sesi_id=? AND sp.penyetuju_id=a.id)")->execute([$fixtureSid,$fixtureSid]);
  $db->prepare('INSERT INTO erapor_sesi_penyetuju_dokumen(sesi_penyetuju_id,sesi_id,dokumen_id) SELECT sp.id,sp.sesi_id,sd.dokumen_id FROM erapor_sesi_penyetuju sp JOIN erapor_sesi_dokumen sd ON sd.sesi_id=sp.sesi_id JOIN erapor_dokumen d ON d.id=sd.dokumen_id JOIN erapor_alur_dokumen a ON a.penyetuju_id=sp.penyetuju_id AND a.rubrik_id=d.rubrik_id WHERE sp.sesi_id=? AND NOT EXISTS(SELECT 1 FROM erapor_sesi_penyetuju_dokumen x WHERE x.sesi_penyetuju_id=sp.id AND x.dokumen_id=sd.dokumen_id)')->execute([$fixtureSid]);
 }
 $cloneBefore=$fingerprint($db);
 foreach([false,true] as $stringify){
  $db->setAttribute(PDO::ATTR_STRINGIFY_FETCHES,$stringify);
  foreach(['Regular','ABK'] as $condition){
   $s=$db->query("SELECT s.id,m.nama_lengkap FROM erapor_sesi s JOIN murid m ON m.id=s.murid_id WHERE s.kondisi=".$db->quote($condition)." AND EXISTS(SELECT 1 FROM erapor_publikasi_pdf p WHERE p.sesi_id=s.id) ORDER BY s.id LIMIT 1")->fetch();
   if(!$s)throw new RuntimeException('Requires existing local official '.$condition.' fixture');$sid=(int)$s['id'];
   $r=EraporPdfDiagnostics::run($db,$admin,['mode'=>'actor','session_id'=>$sid,'actor_id'=>$admin]);
   $check($r['ok']&&$r['path']==='stored_official','Full-access path uses stored official PDF: '.$condition);
   $check(!isset($r['pdf']['pages'])&&!in_array('renderer',$r['steps'],true),'Stored path does not invoke renderer');
   $r=EraporPdfDiagnostics::run($db,$admin,['mode'=>'render','session_id'=>$sid]);
   $check($r['ok']&&$r['pdf']['pages']>0&&in_array('render_layout',$r['steps'],true),'Fresh render traces capture and layout: '.$condition);
   $check(!str_contains(json_encode($r),$s['nama_lengkap']),'No student identity in exported report');
   $r=EraporPdfDiagnostics::run($db,$admin,['mode'=>'readiness','session_id'=>$sid]);
   $check($r['ok']&&!$r['revision']['ready']&&$r['revision']['code']==='HISTORICAL_SOURCE_NOT_ARCHIVED','Legacy revision fails closed');
   $r=EraporPdfDiagnostics::run($db,$admin,['mode'=>'actor','session_id'=>$sid,'actor_id'=>2147483647]);
   $check(!$r['ok']&&$r['error']['stage']==='actor_access','Missing actor cannot bypass access scope');
  }
 }
 $scopedFixtures=[];
 foreach(['Regular','ABK'] as $condition){
  $found=false;
  $candidates=$db->query("SELECT DISTINCT s.id,a.user_id FROM erapor_sesi s JOIN erapor_sesi_penyetuju sp ON sp.sesi_id=s.id AND sp.cakupan='TERBATAS' JOIN erapor_penyetuju_user a ON a.penyetuju_id=sp.penyetuju_id AND a.aktif=1 WHERE s.kondisi=".$db->quote($condition))->fetchAll();
  foreach($candidates as $c){if(EraporPdfAccess::canView($db,(int)$c['id'],(int)$c['user_id']))continue;
   $r=EraporPdfDiagnostics::run($db,$admin,['mode'=>'actor','session_id'=>(int)$c['id'],'actor_id'=>(int)$c['user_id']]);
   $check($r['ok']&&$r['path']==='fresh_scoped'&&!in_array('RTS',$r['documents'],true),'Actual coordinator path renders only scoped documents: '.$condition);$scopedFixtures[$condition]=$c;$found=true;break;
  }
  $check($found,'Scoped coordinator fixture exists for '.$condition);
 }
 $corruptSid=(int)$scopedFixtures['Regular']['id'];
 $originalHash=$db->query('SELECT pdf_sha256 FROM erapor_publikasi_pdf WHERE sesi_id='.$corruptSid)->fetchColumn();
 if(!$originalHash)throw new RuntimeException('Need stored regular artifact for corruption test');
 $db->prepare('UPDATE erapor_publikasi_pdf SET pdf_sha256=? WHERE sesi_id=?')->execute([str_repeat('f',64),$corruptSid]);
 try {
  $r=EraporPdfDiagnostics::run($db,$admin,['mode'=>'actor','session_id'=>$corruptSid,'actor_id'=>$admin]);
  $check(!$r['ok']&&$r['error']['stage']==='official_artifact','Full-access diagnostic validates stored bytes like actual endpoint');
  $r=EraporPdfDiagnostics::run($db,$admin,['mode'=>'actor','session_id'=>$corruptSid,'actor_id'=>(int)$scopedFixtures['Regular']['user_id']]);
  $check($r['ok']&&$r['path']==='fresh_scoped'&&!$r['official']['integrity_checked'],'Scoped route does not gain an unrelated stored-artifact validation step');
  $r=EraporPdfDiagnostics::run($db,$admin,['mode'=>'render','session_id'=>$corruptSid]);
  $check($r['ok']&&$r['path']==='fresh_full','Forced fresh render can investigate independently from a corrupt stored file');
 } finally {$db->prepare('UPDATE erapor_publikasi_pdf SET pdf_sha256=? WHERE sesi_id=?')->execute([$originalHash,$corruptSid]);}
 foreach(['RTS','AGAMA','UMMI','BING','PPI'] as $type){
  $r=EraporPdfDiagnostics::run($db,$admin,['mode'=>'template','document_type'=>$type,'semester'=>'GENAP']);
  $check($r['ok']&&$r['documents']===[$type]&&!$r['watermark'],'Template comparison works without report mutation: '.$type);
 }
 $sid=(int)$db->query('SELECT sesi_id FROM erapor_publikasi_pdf ORDER BY sesi_id LIMIT 1')->fetchColumn();
 $r=EraporPdfDiagnostics::run($db,$admin,['mode'=>'render','session_id'=>$sid],static function($p,$observe){$observe('render_layout');trigger_error('Private unquoted student note for failure test',E_USER_WARNING);throw new RuntimeException('Private unquoted student note for failure test');});
 $check(!$r['ok']&&$r['error']['stage']==='render_layout'&&count($r['warnings'])===1,'Failure records exact stage and warning');
 $check(!str_contains(json_encode($r),'Private unquoted student note'),'Failure/warning export cannot disclose unquoted child values');
 $r=EraporPdfDiagnostics::run($db,$admin,['mode'=>'render','session_id'=>$sid],static function()use($db,$sid){$db->exec('UPDATE erapor_sesi SET status=status WHERE id='.$sid);return [];});
 $check(!$r['ok']&&$r['error']['class']==='PDOException','Database rejects even an injected write in diagnostic READ ONLY transaction');
 $check(!$db->inTransaction(),'Failure always closes its own transaction');
 try{EraporPdfDiagnostics::run($db,$teacher,['mode'=>'render','session_id'=>$sid]);throw new RuntimeException('Non-superadmin accepted');}catch(DomainException){$check(true,'Model also enforces Superadmin');}
 try{EraporPdfDiagnostics::run($db,$admin,['mode'=>['bad'],'session_id'=>$sid]);throw new RuntimeException('Malformed input accepted');}catch(DomainException){$check(true,'Malformed mode rejected before work');}
 $db->setAttribute(PDO::ATTR_STRINGIFY_FETCHES,false);
 $check($fingerprint($db)===$cloneBefore,'Every clone table including artifacts/values/approvals unchanged after fixture setup');
 $check($fingerprint($live)===$before,'Every original table unchanged');
 echo "PASS: $checks isolated MySQL diagnostic checks.\n";
}finally{if($db->inTransaction())$db->rollBack();if($created&&preg_match('/^zivana_diag_test_[a-f0-9]{16}$/D',$name)&&$name!==DB_NAME)$db->exec('DROP DATABASE '.$q($name));}
