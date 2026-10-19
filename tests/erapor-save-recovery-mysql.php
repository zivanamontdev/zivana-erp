<?php
/** Opt-in targeted integration against a disposable clone of the LOCAL database.
 * php tests/erapor-save-recovery-mysql.php --run
 * No source rows, deadlines, assignments, artifacts, R2 objects, or .env are changed.
 */
if(PHP_SAPI!=='cli'||!in_array('--run',$argv,true)){echo "Usage: php tests/erapor-save-recovery-mysql.php --run\n";exit(1);}
define('ROOT_PATH',dirname(__DIR__));define('CONFIG_PATH',ROOT_PATH.'/config');define('BASE_PATH','');
require ROOT_PATH.'/config/config.php';require ROOT_PATH.'/vendor/autoload.php';require ROOT_PATH.'/app/helpers/functions.php';
spl_autoload_register(static function($class){foreach(['core','models','middleware'] as $folder){$p=ROOT_PATH.'/app/'.$folder.'/'.$class.'.php';if(is_file($p)){require $p;return;}}});
if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
if(!in_array(DB_HOST,['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Local database only');
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false];
$dsn='mysql:host='.DB_HOST.';port='.DB_PORT.';charset=utf8mb4';
$live=new PDO($dsn.';dbname='.DB_NAME,DB_USER,DB_PASS,$options);$db=new PDO($dsn,DB_USER,DB_PASS,$options);
$quote=static fn($s)=>'`'.str_replace('`','``',$s).'`';
$fingerprint=static function(PDO $connection)use($quote):array{
    $out=[];foreach($connection->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN) as $table){
        $rows=[];foreach($connection->query('SELECT * FROM '.$quote($table)) as $row)$rows[]=hash('sha256',serialize($row));
        sort($rows);$out[$table]=[count($rows),hash('sha256',implode('',$rows))];
    }ksort($out);return $out;
};
$before=$fingerprint($live);$name='zivana_fix_test_'.bin2hex(random_bytes(8));$created=false;$checks=0;
$check=static function(bool $ok,string $label)use(&$checks){if(!$ok)throw new RuntimeException($label);$checks++;};
try{
    $db->exec('CREATE DATABASE '.$quote($name).' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$created=true;$db->exec('USE '.$quote($name));
    $db->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach(array_keys($before) as $table){$schema=$live->query('SHOW CREATE TABLE '.$quote($table))->fetch(PDO::FETCH_NUM)[1];$db->exec($schema);}
    foreach(array_keys($before) as $table)$db->exec('INSERT INTO '.$quote($table).' SELECT * FROM '.$quote(DB_NAME).'.'.$quote($table));
    $db->exec('SET FOREIGN_KEY_CHECKS=1');
    $check($fingerprint($db)===$before,'Disposable clone exactly matches local source');
    $session=$db->query("SELECT * FROM erapor_sesi WHERE status='BELUM_DIISI' ORDER BY id LIMIT 1")->fetch();
    if(!$session)throw new RuntimeException('Requires one local draft session to clone');
    $sid=(int)$session['id'];$actor=(int)$session['guru_user_id'];
    $deadline=$db->query('SELECT akhir_periode FROM periode_penilaian WHERE id='.(int)$session['periode_id'])->fetchColumn();
    $clock=new DateTimeImmutable($deadline.' 10:00:00',new DateTimeZone('Asia/Makassar'));
    $form=EraporTeacherForm::read($db,$sid,$actor,$clock);$doc=array_values(array_filter($form['documents'],static fn($d)=>$d['jenis_dokumen']==='RTS'))[0];
    $a=(int)$doc['form']['definitions']['items'][0]['id'];$b=(int)$doc['form']['definitions']['items'][1]['id'];$did=(int)$doc['id'];
    $oldA=$doc['form']['values']['nilai:'.$a]??null;$oldB=$doc['form']['values']['nilai:'.$b]??null;
    $value=$oldA===2?1:2;$next=$value===3?4:3;
    $batch=[['indikator_id'=>$a,'nilai'=>$value,'expected'=>$oldA]];
    $first=EraporRtsEntry::save($db,$sid,$did,$actor,$batch,$clock);
    $retry=EraporRtsEntry::save($db,$sid,$did,$actor,$batch,$clock);
    $check($first['changed']===1&&$retry['changed']===0,'Lost response retry is idempotent before applying newer edits');
    EraporRtsEntry::save($db,$sid,$did,$actor,[['indikator_id'=>$a,'nilai'=>$next,'expected'=>$value],['indikator_id'=>$b,'nilai'=>4,'expected'=>$oldB]],$clock);
    $saved=EraporTeacherForm::read($db,$sid,$actor,$clock);$values=array_values(array_filter($saved['documents'],static fn($d)=>$d['jenis_dokumen']==='RTS'))[0]['form']['values'];
    $check($values['nilai:'.$a]===$next&&$values['nilai:'.$b]===4,'Revisited and following answers persist after a fresh DB read');
    try{EraporRtsEntry::save($db,$sid,$did,$actor,[['indikator_id'=>$a,'nilai'=>0,'expected'=>$oldA],['indikator_id'=>$b,'nilai'=>1,'expected'=>4]],$clock);throw new RuntimeException('Stale conflict was accepted');}catch(DomainException){$check(true,'Real concurrent conflict rejected');}
    $saved=EraporTeacherForm::read($db,$sid,$actor,$clock);$values=array_values(array_filter($saved['documents'],static fn($d)=>$d['jenis_dokumen']==='RTS'))[0]['form']['values'];
    $check($values['nilai:'.$b]===4,'A genuinely conflicted batch is rolled back, not partially applied');

    // Existing finalized waiting session supplies real, complete cloned rubric data.
    $waiting=$db->query("SELECT s.* FROM erapor_sesi s WHERE s.status='MENUNGGU_TTD' AND NOT EXISTS(SELECT 1 FROM erapor_publikasi_pdf p WHERE p.sesi_id=s.id) ORDER BY s.id LIMIT 1")->fetch();
    if(!$waiting)throw new RuntimeException('Requires one local waiting-approval session to clone');
    $wsid=(int)$waiting['id'];$tasks=array_column($db->query('SELECT * FROM erapor_sesi_penyetuju WHERE sesi_id='.$wsid)->fetchAll(),null,'kode');
    $coordinator=(int)$db->query('SELECT user_id FROM erapor_penyetuju_user WHERE aktif=1 AND penyetuju_id='.(int)$tasks['KOORDINATOR_BING']['penyetuju_id'].' LIMIT 1')->fetchColumn();
    WaliKelas::set($db,(int)$waiting['kelas_id'],$coordinator,$coordinator);
    EraporApprove::approve($db,$wsid,(int)$tasks['KOORDINATOR_BING']['id'],$coordinator);
    $beforeWali=EraporPublication::draftPackage($db,$wsid);$bing=array_values(array_filter($beforeWali['documents'],static fn($d)=>$d['jenis_dokumen']==='BING'))[0];
    $check(isset($bing['signature_current']['signers']['KOORDINATOR_BING'])&&!isset($bing['signature_current']['signers']['GURU_KELAS']),'Coordinator approval alone does not pre-sign the wali slot');
    try{EraporApprove::approve($db,$wsid,(int)$tasks['WALI_KELAS']['id'],(int)$waiting['guru_user_id']);throw new RuntimeException('Unmapped PIC signed as wali');}catch(DomainException){$check(true,'PIC without the class mapping cannot approve as wali');}
    EraporApprove::approve($db,$wsid,(int)$tasks['WALI_KELAS']['id'],$coordinator);
    $package=EraporPublication::draftPackage($db,$wsid);$bing=array_values(array_filter($package['documents'],static fn($d)=>$d['jenis_dokumen']==='BING'))[0];
    $signers=$bing['signature_current']['signers'];
    $check($signers['GURU_KELAS']['nama']===$signers['KOORDINATOR_BING']['nama']&&$signers['GURU_KELAS']['ttd_sha256']===$signers['KOORDINATOR_BING']['ttd_sha256'],'Same class-mapped person has two separate approved signature roles');
    $check(EraporPackagePdfRenderer::watermarkEnabled($package),'Watermark already enabled while waiting for head approval');
    $head=(int)$db->query('SELECT user_id FROM erapor_penyetuju_user WHERE aktif=1 AND penyetuju_id='.(int)$tasks['KEPALA_SEKOLAH']['penyetuju_id'].' LIMIT 1')->fetchColumn();
    $result=EraporApprove::approve($db,$wsid,(int)$tasks['KEPALA_SEKOLAH']['id'],$head);
    $check($result['all_approved']&&$result['publication']['result']==='prepared','Full cloned approval flow produces an official artifact');
    $artifact=$db->query('SELECT filename,renderer_versi,pdf_bytes,pdf_sha256 FROM erapor_publikasi_pdf WHERE sesi_id='.$wsid)->fetch();
    $check($artifact['filename']===EraporPdfFilename::forSession($db,$wsid)&&$artifact['renderer_versi']===EraporPackagePdfRenderer::version(),'Official filename and renderer version are consistent');
    $check(hash_equals($artifact['pdf_sha256'],hash('sha256',$artifact['pdf_bytes'])),'Official artifact hash verifies');
    $check($fingerprint($live)===$before,'All source table fingerprints, official artifacts, and user data remain unchanged');
    echo "PASS: $checks disposable MySQL checks (lost-ACK retry, repeat edits, rollback, dual-role approval signatures, final PDF, source unchanged).\n";
}finally{
    if($db->inTransaction())$db->rollBack();
    if($created&&preg_match('/^zivana_fix_test_[a-f0-9]{16}$/D',$name)&&$name!==DB_NAME)$db->exec('DROP DATABASE '.$quote($name));
}
