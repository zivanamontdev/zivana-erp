<?php
/** php tests/erapor-ppi-workflow-mysql.php --run
 * Local disposable clone only. Real services/Dompdf; in-memory R2 substitute, no network/WhatsApp writes.
 */
if(PHP_SAPI!=='cli'||!in_array('--run',$argv,true)){echo "Usage: php tests/erapor-ppi-workflow-mysql.php --run\n";exit(1);}
define('ROOT_PATH',dirname(__DIR__));define('CONFIG_PATH',ROOT_PATH.'/config');define('BASE_PATH','');
require CONFIG_PATH.'/config.php';require ROOT_PATH.'/vendor/autoload.php';require ROOT_PATH.'/app/helpers/functions.php';
spl_autoload_register(static function($class){foreach(['core','models','middleware'] as $folder){$p=ROOT_PATH.'/app/'.$folder.'/'.$class.'.php';if(is_file($p)){require $p;return;}}});
// Same public storage contract; deliberately incapable of contacting a real bucket.
final class R2Storage {
    public array $objects=[];public bool $fail=false;
    public function put(string $key,string $bytes,string $type,string $disposition=''):void {if($this->fail)throw new RuntimeException('Simulated upload failure');$this->objects[$key]=$bytes;}
    public function head(string $key):?array {return isset($this->objects[$key])?['size'=>strlen($this->objects[$key])]:null;}
    public function delete(string $key):void {unset($this->objects[$key]);}
    public function presignedGetUrl(string $key,int $ttl=300,string $name=''):string{return 'http://127.0.0.1/ppi-test/'.rawurlencode($key).'?name='.rawurlencode($name);}
}
if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
if(!in_array(DB_HOST,['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Local database only');
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false];
$dsn='mysql:host='.DB_HOST.';port='.DB_PORT.';charset=utf8mb4';$live=new PDO($dsn.';dbname='.DB_NAME,DB_USER,DB_PASS,$options);$db=new PDO($dsn,DB_USER,DB_PASS,$options);
$quote=static fn($s)=>'`'.str_replace('`','``',$s).'`';$migration='20261018_erapor_ppi_terpisah';
$fingerprint=static function(PDO $c,?array $columns=null,bool $ignoreNewLedger=false)use($quote,$migration):array {
    $out=[];$tables=$columns?array_keys($columns):$c->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    foreach($tables as $t){$rows=[];$select=$columns?implode(',',array_map($quote,$columns[$t])):'*';
        foreach($c->query('SELECT '.$select.' FROM '.$quote($t).($ignoreNewLedger&&$t==='erapor_migrations'?" WHERE id<>".$c->quote($migration):'')) as $r)$rows[]=hash('sha256',serialize($r));
        sort($rows);$out[$t]=[count($rows),hash('sha256',implode('',$rows))];}ksort($out);return $out;
};
$before=$fingerprint($live);$columns=[];foreach(array_keys($before) as $t)$columns[$t]=$live->query('SHOW COLUMNS FROM '.$quote($t))->fetchAll(PDO::FETCH_COLUMN);
$name='zivana_ppi_test_'.bin2hex(random_bytes(8));$created=false;$checks=0;
$check=static function(bool $ok,string $label)use(&$checks){if(!$ok)throw new RuntimeException($label);$checks++;};
$reject=static function(callable $call,string $label)use($check){try{$call();}catch(DomainException){$check(true,$label);return;}throw new RuntimeException('Expected rejection: '.$label);};
try{
    $db->exec('CREATE DATABASE '.$quote($name).' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$created=true;$db->exec('USE '.$quote($name));$db->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach(array_keys($before) as $t)$db->exec($live->query('SHOW CREATE TABLE '.$quote($t))->fetch(PDO::FETCH_NUM)[1]);
    foreach(array_keys($before) as $t)$db->exec('INSERT INTO '.$quote($t).' SELECT * FROM '.$quote(DB_NAME).'.'.$quote($t));$db->exec('SET FOREIGN_KEY_CHECKS=1');
    $check($fingerprint($db)===$before,'Clone exactly matches source');
    if(!EraporPpiWorkflow::available($db)){
        $old=$db->query("SELECT * FROM erapor_sesi WHERE kondisi='ABK' AND jenis='TENGAH' ORDER BY id LIMIT 1")->fetch();
        $fallback=EraporTeacherDashboard::read($db,(int)$old['guru_user_id'],(int)$old['periode_id']);
        $check(!array_filter($fallback['students'],static fn($r)=>$r['ppi_pending_submission']),'Dashboard remains compatible before the additive PPI migration, without false reminders');
        $overview=EraporOverview::forYear($db,(int)$old['tahun_ajaran_id']);$pending=[];foreach($overview as $p)foreach($p['rows'] as $r)if($r['ppi_pending_submission'])$pending[]=$r;
        $check($pending===[]&&$fingerprint($db)===$before,'Pre-migration overview badge fallback is read-only and does not access missing PPI columns/tables');
    }
    $legacy=$fingerprint($db,$columns,true);$file=ROOT_PATH.'/database/migrations/'.$migration.'.sql';
    $check(count(EraporMigrationRunner::plan($file)['steps'])===6,'Exactly two additive flags and four independent tables');
    EraporMigrationRunner::apply($db,$file);$check(EraporMigrationRunner::apply($db,$file)==='already_applied','Migration retry is idempotent');
    $check($fingerprint($db,$columns,true)===$legacy,'Migration preserves all legacy fields, signature snapshots and official PDF bytes');
    $s=$db->query("SELECT * FROM erapor_sesi WHERE kondisi='ABK' AND jenis='TENGAH' ORDER BY (status='MENUNGGU_TTD') DESC,id LIMIT 1")->fetch();
    if(!$s)throw new RuntimeException('Requires one local ABK session to clone');
    $sid=(int)$s['id'];$pic=(int)$s['guru_user_id'];
    $clock=new DateTimeImmutable($db->query('SELECT akhir_periode FROM periode_penilaian WHERE id='.(int)$s['periode_id'])->fetchColumn().' 10:00:00',new DateTimeZone('Asia/Makassar'));
    $db->prepare("UPDATE murid SET nama_lengkap='Murid Uji PPI',nama_panggilan='Uji',telp_ayah='081234567890',telp_ibu='081234567891' WHERE id=?")->execute([$s['murid_id']]);
    $reset=static function()use($db,$sid){
        foreach(['erapor_ppi_pdf','erapor_ppi_persetujuan','erapor_ppi_log','erapor_ppi_pengajuan','erapor_publikasi_distribusi','erapor_publikasi_pdf','erapor_persetujuan_snapshot',
            'erapor_sesi_penyetuju_dokumen','erapor_sesi_penyetuju','erapor_sesi_penerimaan','erapor_sesi_log'] as $t)$db->exec('DELETE FROM '.$t.' WHERE sesi_id='.$sid);
        $db->exec("UPDATE erapor_sesi SET status='BELUM_DIISI',tanggal_pengesahan=NULL,ppi_disertakan=1,ppi_terpisah=0 WHERE id=$sid");
    };
    $session=static fn()=>$db->query('SELECT * FROM erapor_sesi WHERE id='.$sid)->fetch();
    $reset();$form=EraporTeacherForm::read($db,$sid,$pic,$clock);$docs=array_column($form['documents'],null,'jenis_dokumen');$pd=$docs['PPI'];$did=(int)$pd['id'];
    // Complete missing main entries through real optimistic-concurrency save services.
    foreach($form['completion']['documents'] as $d){if(!in_array($d['jenis'],['RTS','AGAMA'],true))continue;$batch=[];
        foreach($d['missing'] as $m){$key=$m['key'];$batch[]=$d['jenis']==='RTS'?['indikator_id'=>(int)substr($key,6),'nilai'=>0,'expected'=>null]
            :['key'=>$key,'value'=>str_starts_with($key,'nilai:')?'-':'Narasi uji PPI.','expected'=>null];}
        foreach(array_chunk($batch,30) as $chunk){if($d['jenis']==='RTS')EraporRtsEntry::save($db,$sid,(int)$docs['RTS']['id'],$pic,$chunk,$clock);else EraporAgamaEntry::save($db,$sid,(int)$docs['AGAMA']['id'],$pic,$chunk,$clock);}
    }
    $ppiValues=[];foreach($pd['form']['definitions']['aspects'] as $a)foreach($pd['form']['definitions']['columns'] as $c)if($c['diisi_di_sesi'])$ppiValues[$a['id'].':'.$c['id']]='Nilai PPI uji '.$a['id'].':'.$c['id'];
    $savePpi=static function()use($db,$sid,$did,$pic,$clock,$ppiValues){$p=EraporTeacherForm::read($db,$sid,$pic,$clock);$doc=array_column($p['documents'],null,'jenis_dokumen')['PPI'];$changes=[];
        foreach($ppiValues as $key=>$v)$changes[]=['key'=>$key,'value'=>$v,'expected'=>$doc['form']['values'][$key]??null];EraporStructuredEntry::save($db,'PPI',$sid,$did,$pic,$changes,$clock);};
    $savePpi();$check(EraporPpiWorkflow::completion($db,$session())['complete'],'PPI starts filled');
    $reject(fn()=>EraporPpiWorkflow::choose($db,$sid,$pic+100000,false),'Non-owner cannot change PPI inclusion');
    EraporPpiWorkflow::choose($db,$sid,$pic,false);$state=EraporTeacherForm::read($db,$sid,$pic,$clock);
    $check($state['completion']['complete']&&$state['ppi']['separate']&&$state['ppi']['progress']['filled']===count($ppiValues),'Filled PPI excluded without deleting values');
    $beforeFinalRow=null;foreach(EraporTeacherDashboard::read($db,$pic,(int)$s['periode_id'],$clock)['students'] as $row)if((int)$row['sesi_id']===$sid)$beforeFinalRow=$row;
    $check($beforeFinalRow&&!$beforeFinalRow['ppi_pending_submission'],'Choosing separate PPI before main Finalisasi does not yet create an actionable pending-submission reminder');
    EraporPpiWorkflow::choose($db,$sid,$pic,true);$check(!EraporTeacherForm::read($db,$sid,$pic,$clock)['ppi']['separate'],'PIC can restore inclusion before finalization');
    EraporPpiWorkflow::choose($db,$sid,$pic,false);
    $key=array_key_first($ppiValues);EraporStructuredEntry::save($db,'PPI',$sid,$did,$pic,[['key'=>$key,'value'=>null,'expected'=>$ppiValues[$key]]],$clock);
    $check(EraporConfirmFilled::confirm($db,$sid,$pic)['result']==='confirmed','Incomplete excluded PPI does not block main completion');
    $check(EraporConfirmReception::confirm($db,$sid,$pic,false)['result']==='confirmed','Main finalizes without PPI');
    $tasks=array_column($db->query('SELECT * FROM erapor_sesi_penyetuju WHERE sesi_id='.$sid)->fetchAll(),null,'kode');$actors=[];
    foreach($tasks as $code=>$t)if($code!=='WALI_KELAS')$actors[$code]=(int)$db->query('SELECT user_id FROM erapor_penyetuju_user WHERE aktif=1 AND penyetuju_id='.(int)$t['penyetuju_id'].' LIMIT 1')->fetchColumn();
    $wali=$actors['KOORDINATOR_BING'];WaliKelas::set($db,(int)$s['kelas_id'],$wali,$wali);$head=$actors['KEPALA_SEKOLAH'];$actors['WALI_KELAS']=$wali;
    $scope=(int)$db->query('SELECT COUNT(*) FROM erapor_sesi_penyetuju_dokumen WHERE sesi_id='.$sid.' AND dokumen_id='.$did)->fetchColumn();$check($scope===0,'PIC-excluded PPI is outside the main approval scope');
    $reject(fn()=>EraporPpiWorkflow::submit($db,$sid,$pic),'Incomplete standalone PPI cannot submit');
    $check(EraporTeacherForm::read($db,$sid,$pic,$clock)['capabilities']['can_edit_ppi'],'Only detached PPI stays editable after main Finalisasi');
    $expired=EraporTeacherForm::read($db,$sid,$pic,$clock->modify('+1 day'));
    $check($expired['capabilities']['read_only_reason']==='STATUS_TERKUNCI'&&$expired['capabilities']['ppi_read_only_reason']==='TENGGAT_BERAKHIR'
        &&!$expired['capabilities']['can_edit_ppi']&&!$expired['ppi']['can_submit'],'Expired incomplete PPI exposes its own deadline reason independently of the main lock');
    $rts=$docs['RTS'];$first=(int)$rts['form']['definitions']['items'][0]['id'];$reject(fn()=>EraporRtsEntry::save($db,$sid,(int)$rts['id'],$pic,[['indikator_id'=>$first,'nilai'=>1,'expected'=>0]],$clock),'Main RTS remains locked');
    $savePpi();
    $expiredComplete=EraporTeacherForm::read($db,$sid,$pic,$clock->modify('+1 day'));
    $check(!$expiredComplete['capabilities']['can_edit_ppi']&&$expiredComplete['capabilities']['ppi_read_only_reason']==='TENGGAT_BERAKHIR'
        &&$expiredComplete['ppi']['can_submit'],'Complete retained PPI can still submit after deadline without granting edit permission');
    $mainApprove=static function(?bool $include=null)use($db,$sid,$actors,&$tasks){$result=null;uasort($tasks,static fn($a,$b)=>$a['urutan']<=>$b['urutan']);foreach($tasks as $code=>$t)$result=EraporApprove::approve($db,$sid,(int)$t['id'],$actors[$code],$code==='KEPALA_SEKOLAH'?$include:null);return $result;};
    $mainApprove();$main=$db->query('SELECT pdf_sha256,pdf_bytes,manifest FROM erapor_publikasi_pdf WHERE sesi_id='.$sid)->fetch();
    $check(!in_array('PPI',array_column(json_decode($main['manifest'],true)['documents'],'jenis'),true),'Main official artifact contains no PPI');
    // Regression from production: signed PDF preparation must not hide the remaining publication task.
    $taskFor=static fn(array $tasks)=>array_values(array_filter($tasks,static fn($t)=>$t['session_id']===$sid));
    $overviewFor=static function()use($db,$s,$sid){foreach(EraporOverview::forYear($db,(int)$s['tahun_ajaran_id']) as $p)foreach($p['rows'] as $r)if($r['sesi_id']===$sid)return $r;throw new RuntimeException('Overview session missing');};
    $dashboardFor=static function(?PDO $connection=null)use($db,$s,$sid,$pic,$clock){
        foreach(EraporTeacherDashboard::read($connection??$db,$pic,(int)$s['periode_id'],$clock)['students'] as $row)if((int)$row['sesi_id']===$sid)return $row;
        throw new RuntimeException('Dashboard session missing');
    };
    $readBefore=$fingerprint($db);
    $headPending=$taskFor(EraporApprovalInbox::read($db,$head)['tasks']);
    $check(count($headPending)===1&&$headPending[0]['ready_to_publish']&&!$headPending[0]['can_approve'],'Approved main head task stays in pending queue until publication');
    $headHistory=$taskFor(EraporApprovalInbox::read($db,$head,false,true)['tasks']);
    $check(count($headHistory)===1&&$headHistory[0]['ready_to_publish'],'Approval history keeps an explicit unpublished state');
    $monitorPending=$taskFor(EraporApprovalInbox::read($db,1,true)['tasks']);
    $check(count($monitorPending)===1&&$monitorPending[0]['ready_to_publish']&&!$monitorPending[0]['can_approve'],'Superadmin can monitor pending publication without approval authority');
    $check($taskFor(EraporApprovalInbox::read($db,$wali)['tasks'])===[],'Approved coordinator/Wali tasks do not reappear as publish actions');
    $readyOverview=$overviewFor();$check($readyOverview['status']==='SIAP_TERBIT'&&$readyOverview['has_official_pdf'],'Official PDF readiness is distinguished from publication in Superadmin overview');
    $check($readyOverview['ppi_pending_submission']&&$dashboardFor()['ppi_pending_submission'],'Teacher and Superadmin lists flag detached PPI awaiting explicit submission while main approval is complete');
    $strings=new PDO($dsn.';dbname='.$name,DB_USER,DB_PASS,$options+[PDO::ATTR_STRINGIFY_FETCHES=>true]);
    $stringTasks=$taskFor(EraporApprovalInbox::read($strings,$head)['tasks']);
    $check(count($stringTasks)===1&&$stringTasks[0]['ready_to_publish'],'Pending publication also works with string-typed numeric PDO results, as on MariaDB hosting');
    $stringOverview=null;foreach(EraporOverview::forYear($strings,(int)$s['tahun_ajaran_id']) as $p)foreach($p['rows'] as $r)if($r['sesi_id']===$sid)$stringOverview=$r;
    $check($stringOverview&&$stringOverview['status']==='SIAP_TERBIT','Overview ready/published distinction is independent of PDO numeric fetch types');
    $check($stringOverview['ppi_pending_submission']&&$dashboardFor($strings)['ppi_pending_submission'],'Pending PPI badge projection also supports string-typed numeric PDO fetches');$strings=null;
    $check($fingerprint($db)===$readBefore,'Publication queue/history/overview projections do not write data');
    $db->prepare('UPDATE erapor_penyetuju_user SET aktif=0 WHERE user_id=? AND penyetuju_id=?')->execute([$head,$tasks['KEPALA_SEKOLAH']['penyetuju_id']]);
    $check($taskFor(EraporApprovalInbox::read($db,$head)['tasks'])===[],'Revoked head assignment loses pending publication access');
    $db->prepare('UPDATE erapor_penyetuju_user SET aktif=1 WHERE user_id=? AND penyetuju_id=?')->execute([$head,$tasks['KEPALA_SEKOLAH']['penyetuju_id']]);
    $mainStorage=new R2Storage();EraporDistribution::publish($db,$sid,(int)$tasks['KEPALA_SEKOLAH']['id'],$head,$mainStorage);
    $check($taskFor(EraporApprovalInbox::read($db,$head)['tasks'])===[],'Published report leaves the head pending queue');
    $publishedOverview=$overviewFor();$check($publishedOverview['status']==='TERBIT'&&$publishedOverview['has_official_pdf'],'Overview marks a report published only after the publication transition');
    $check($publishedOverview['ppi_pending_submission']&&$dashboardFor()['ppi_pending_submission'],'Publishing the main report does not hide an unsubmitted detached PPI');
    EraporDistribution::markShared($db,$sid,$pic,'AYAH');
    $mainDistribution=$db->query('SELECT * FROM erapor_publikasi_distribusi WHERE sesi_id='.$sid)->fetch();
    EraporStructuredEntry::save($db,'PPI',$sid,$did,$pic,[['key'=>$key,'value'=>'Nilai PPI setelah rapor utama terbit','expected'=>$ppiValues[$key]]],$clock);
    $check($session()['status']==='SELESAI'&&EraporTeacherForm::read($db,$sid,$pic,$clock)['capabilities']['can_edit_ppi'],'Detached PPI can still be completed after the main report has been published and shared');
    $reject(fn()=>EraporPpiWorkflow::choose($db,$sid,$pic,true),'Main inclusion cannot change after Finalisasi');
    $reject(fn()=>EraporApprove::approve($db,$sid,(int)$tasks['KEPALA_SEKOLAH']['id'],$head,true),'Head cannot append PPI after official artifact exists');
    $check(!EraporPpiWorkflow::canView($db,$sid,$actors['KOORDINATOR_QURAN']),'Religion coordinator alone cannot review standalone PPI');
    $check(!EraporPpiWorkflow::canView($db,$sid,$wali)&&!EraporPpiWorkflow::canView($db,$sid,$head)
        &&EraporPpiWorkflow::canView($db,$sid,$pic),'Unsubmitted separate PPI is visible only to its PIC or Superadmin, not future approvers');
    $check(EraporPpiWorkflow::submit($db,$sid,$pic)['result']==='submitted','PIC explicitly submits retained PPI');
    $check(EraporPpiWorkflow::submit($db,$sid,$pic)['result']==='already_submitted','Submission replay is idempotent');
    $check(!$overviewFor()['ppi_pending_submission']&&!$dashboardFor()['ppi_pending_submission'],'Pending-submission badge clears immediately after PIC submission, not only after publication');
    $reject(fn()=>EraporStructuredEntry::save($db,'PPI',$sid,$did,$pic,[['key'=>$key,'value'=>'Changed','expected'=>$ppiValues[$key]]],$clock),'PPI values lock after independent submission');
    $reject(fn()=>EraporPpiWorkflow::approve($db,$sid,$head,'KEPALA_SEKOLAH'),'Head cannot skip Wali approval');
    $reject(fn()=>EraporPpiWorkflow::approve($db,$sid,$pic,'WALI_KELAS'),'Unmapped PIC cannot sign as Wali');
    $db->prepare('UPDATE erapor_wali_kelas SET user_id=? WHERE kelas_id=?')->execute([$pic,$s['kelas_id']]);
    $reject(fn()=>EraporPpiWorkflow::approve($db,$sid,$wali,'WALI_KELAS'),'Revoked Wali assignment cannot reuse its earlier main approval authority');
    $check(!EraporPpiWorkflow::canView($db,$sid,$wali),'Coordinator loses separate PPI access when its Wali mapping is revoked');
    $db->prepare('UPDATE erapor_wali_kelas SET user_id=? WHERE kelas_id=?')->execute([$wali,$s['kelas_id']]);
    EraporPpiWorkflow::approve($db,$sid,$wali,'WALI_KELAS');$check(EraporPpiWorkflow::approve($db,$sid,$wali,'WALI_KELAS')['result']==='already_approved','Wali replay records no duplicate approval');
    // A renderer/definition failure must roll back the head snapshot and workflow status together.
    $signers=$db->query("SELECT * FROM erapor_rubrik_penandatangan WHERE rubrik_id=".(int)$pd['rubrik_id'])->fetchAll();
    $db->exec('DELETE FROM erapor_rubrik_penandatangan WHERE rubrik_id='.(int)$pd['rubrik_id']);
    $reject(fn()=>EraporPpiWorkflow::approve($db,$sid,$head,'KEPALA_SEKOLAH'),'Rendering failure rolls back final child approval');
    $check($db->query('SELECT status FROM erapor_ppi_pengajuan WHERE sesi_id='.$sid)->fetchColumn()==='MENUNGGU_KEPALA'
        &&(int)$db->query("SELECT COUNT(*) FROM erapor_ppi_persetujuan WHERE sesi_id=$sid AND kode='KEPALA_SEKOLAH'")->fetchColumn()===0,'No partial head approval survives');
    foreach($signers as $r){$q=$db->prepare('INSERT INTO erapor_rubrik_penandatangan('.implode(',',array_keys($r)).') VALUES('.implode(',',array_fill(0,count($r),'?')).')');$q->execute(array_values($r));}
    EraporPpiWorkflow::approve($db,$sid,$head,'KEPALA_SEKOLAH');$artifact=EraporPpiWorkflow::artifact($db,$sid);
    $check($artifact&&str_starts_with($artifact['bytes'],'%PDF-')&&$artifact['filename']==='PPI-Murid-Uji-PPI-TS-'.ucfirst(strtolower($s['semester'])).'-'.str_replace('/','-',$form['period']['tahun_label']).'.pdf','Separate immutable child PDF and child-specific filename');
    $savedManifest=$db->query('SELECT manifest FROM erapor_ppi_pdf WHERE sesi_id='.$sid)->fetchColumn();
    $db->exec("UPDATE erapor_ppi_pdf SET manifest='{}' WHERE sesi_id=$sid");$reject(fn()=>EraporPpiWorkflow::artifact($db,$sid),'Corrupt child manifest fails closed instead of silently serving or rerendering an official file');
    $db->prepare('UPDATE erapor_ppi_pdf SET manifest=? WHERE sesi_id=?')->execute([$savedManifest,$sid]);
    $package=EraporPpiWorkflow::package($db,$sid);$check(array_column($package['documents'],'jenis_dokumen')===['PPI']&&EraporPackagePdfRenderer::watermarkEnabled($package),'Standalone package contains only PPI with finalization watermark');
    $check(isset($package['documents'][0]['signature_current']['signers']['GURU_KELAS'],$package['documents'][0]['signature_current']['signers']['KEPALA_SEKOLAH'])
        &&count($package['documents'][0]['signature_current']['signers'])===2,'Child signatures come from its own Wali and head approvals only');
    $reject(fn()=>EraporPpiWorkflow::publish($db,$sid,$wali,new R2Storage()),'Wali cannot publish');
    $storage=new R2Storage();$storage->fail=true;try{EraporPpiWorkflow::publish($db,$sid,$head,$storage);throw new LogicException('Upload failure expected');}catch(RuntimeException){$check(true,'Failed upload leaves PPI unpublished');}
    $storage->fail=false;EraporPpiWorkflow::publish($db,$sid,$head,$storage);EraporPpiWorkflow::publish($db,$sid,$head,$storage);
    $check(count($storage->objects)===1,'Publication replay does not upload or overwrite another artifact');
    $token=$db->query('SELECT unduh_token FROM erapor_ppi_pdf WHERE sesi_id='.$sid)->fetchColumn();$check((bool)EraporPpiWorkflow::downloadUrl($db,$token,false,$storage),'Standalone anonymous download token works');
    $check((int)$db->query('SELECT jumlah_unduh FROM erapor_ppi_pdf WHERE sesi_id='.$sid)->fetchColumn()===0,'Messaging preview does not count as parent download');
    EraporPpiWorkflow::downloadUrl($db,$token,true,$storage);$check((int)$db->query('SELECT jumlah_unduh FROM erapor_ppi_pdf WHERE sesi_id='.$sid)->fetchColumn()===1,'Parent download counted separately');
    $check(EraporPpiWorkflow::downloadUrl($db,str_repeat('0',64),true,$storage)===null,'Unknown PPI tokens fail closed');
    $check(EraporPpiWorkflow::downloadUrl($db,$mainDistribution['unduh_token'],false,$storage)===null&&EraporDistribution::downloadUrl($db,$token,false,$mainStorage)===null,'Main and PPI download tokens cannot be interchanged');
    $expiry=$db->query('SELECT unduh_kedaluwarsa FROM erapor_ppi_pdf WHERE sesi_id='.$sid)->fetchColumn();$db->exec('UPDATE erapor_ppi_pdf SET unduh_kedaluwarsa=DATE_SUB(NOW(),INTERVAL 1 DAY) WHERE sesi_id='.$sid);
    $check(EraporPpiWorkflow::downloadUrl($db,$token,false,$storage)===null,'Expired child download token fails closed');$db->prepare('UPDATE erapor_ppi_pdf SET unduh_kedaluwarsa=? WHERE sesi_id=?')->execute([$expiry,$sid]);
    $share=EraporPpiWorkflow::shareState($db,$sid);$check(count($share['contacts'])===2&&str_contains($share['contacts']['AYAH']['wa_url'],'PPI')&&str_contains(rawurldecode($share['contacts']['AYAH']['wa_url']),'/rapor/ppi/unduh/'),'PPI sharing uses its own token and explicit PPI message');
    $reject(fn()=>EraporPpiWorkflow::markShared($db,$sid,$wali,'AYAH'),'Only active PIC can record sharing');EraporPpiWorkflow::markShared($db,$sid,$pic,'AYAH');
    $check(EraporPpiWorkflow::shareState($db,$sid)['shared_to']==='AYAH','PPI sharing audit is independent');
    $check($db->query('SELECT pdf_sha256 FROM erapor_publikasi_pdf WHERE sesi_id='.$sid)->fetchColumn()===$main['pdf_sha256']
        &&hash('sha256',$db->query('SELECT pdf_bytes FROM erapor_publikasi_pdf WHERE sesi_id='.$sid)->fetchColumn())===$main['pdf_sha256'],'Main official bytes remain unchanged through child edits, submission, approval, publication, download and sharing');

    $check($db->query('SELECT * FROM erapor_publikasi_distribusi WHERE sesi_id='.$sid)->fetch()===$mainDistribution,'Main publication URL, sharing audit and counters remain entirely unchanged');

    // Included path, then the distinct late exclusion path preserve the original main approval scope.
    $reset();$savePpi();EraporConfirmFilled::confirm($db,$sid,$pic);EraporConfirmReception::confirm($db,$sid,$pic,true);
    $tasks=array_column($db->query('SELECT * FROM erapor_sesi_penyetuju WHERE sesi_id='.$sid)->fetchAll(),null,'kode');
    $mainApprove();$manifest=json_decode($db->query('SELECT manifest FROM erapor_publikasi_pdf WHERE sesi_id='.$sid)->fetchColumn(),true);
    $check(in_array('PPI',array_column($manifest['documents'],'jenis'),true)&&!$session()['ppi_terpisah'],'Normal included PPI remains in the main official PDF');
    $reject(fn()=>EraporPpiWorkflow::submit($db,$sid,$pic),'Included PPI cannot be separately submitted');
    $check(!$overviewFor()['ppi_pending_submission']&&!$dashboardFor()['ppi_pending_submission'],'Included PPI never receives a misleading standalone submission warning');
    $reset();$savePpi();EraporConfirmFilled::confirm($db,$sid,$pic);EraporConfirmReception::confirm($db,$sid,$pic,true);
    $tasks=array_column($db->query('SELECT * FROM erapor_sesi_penyetuju WHERE sesi_id='.$sid)->fetchAll(),null,'kode');$mainApprove(false);
    $manifest=json_decode($db->query('SELECT manifest FROM erapor_publikasi_pdf WHERE sesi_id='.$sid)->fetchColumn(),true);
    $check($session()['ppi_disertakan']===1&&$session()['ppi_terpisah']===1&&!in_array('PPI',array_column($manifest['documents'],'jenis'),true),'Head late exclusion changes publication only, not frozen original approval scope');
    $check((int)$db->query('SELECT COUNT(*) FROM erapor_sesi_penyetuju_dokumen WHERE sesi_id='.$sid.' AND dokumen_id='.$did)->fetchColumn()>0,'Historical included PPI approval evidence remains intact');
    $check($overviewFor()['ppi_pending_submission']&&$dashboardFor()['ppi_pending_submission'],'Head late exclusion also creates a visible pending PPI submission reminder');
    $check(EraporTeacherForm::read($db,$sid,$pic,$clock)['ppi']['progress']['filled']===count($ppiValues)&&EraporPpiWorkflow::submit($db,$sid,$pic)['result']==='submitted','Late-excluded PPI retains values and requires explicit fresh PIC submission');
    $check($fingerprint($live)===$before,'Every original source table and artifact remains unchanged');
    echo "PASS: $checks disposable MySQL checks (migration, included/excluded/late-excluded PPI, locks, access, rollback, immutable artifacts, storage failure/retry, downloads and sharing).\n";
}finally{
    if($db->inTransaction())$db->rollBack();
    if($created&&preg_match('/^zivana_ppi_test_[a-f0-9]{16}$/D',$name)&&$name!==DB_NAME)$db->exec('DROP DATABASE '.$quote($name));
}
