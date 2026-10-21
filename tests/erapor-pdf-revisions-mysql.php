<?php
/** php tests/erapor-pdf-revisions-mysql.php --run
 * Local disposable clone only. Lineage fixtures are explicit, NOT claims about recovery of production reports.
 */
if(PHP_SAPI!=='cli'||!in_array('--run',$argv,true)){echo "Usage: php tests/erapor-pdf-revisions-mysql.php --run\n";exit(1);}
define('ROOT_PATH',dirname(__DIR__));define('CONFIG_PATH',ROOT_PATH.'/config');define('BASE_PATH','');
require CONFIG_PATH.'/config.php';require ROOT_PATH.'/vendor/autoload.php';require ROOT_PATH.'/app/helpers/functions.php';
spl_autoload_register(static function($c){foreach(['core','models','middleware'] as $d){$p=ROOT_PATH.'/app/'.$d.'/'.$c.'.php';if(is_file($p)){require $p;return;}}});
if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
if(!in_array(DB_HOST,['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Local database only');
$opts=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false];
$dsn='mysql:host='.DB_HOST.';port='.DB_PORT.';charset=utf8mb4';$live=new PDO($dsn.';dbname='.DB_NAME,DB_USER,DB_PASS,$opts);$db=new PDO($dsn,DB_USER,DB_PASS,$opts);
$quote=static fn($s)=>'`'.str_replace('`','``',$s).'`';
$fingerprint=static function(PDO $c,?array $tables=null)use($quote):array{$out=[];foreach($tables??$c->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN) as $t){$rows=[];foreach($c->query('SELECT * FROM '.$quote($t)) as $r)$rows[]=hash('sha256',serialize($r));sort($rows);$out[$t]=[count($rows),hash('sha256',implode('',$rows))];}ksort($out);return $out;};
$before=$fingerprint($live);$name='zivana_revision_test_'.bin2hex(random_bytes(8));$created=false;$checks=0;
$check=static function(bool $ok,string $label)use(&$checks){if(!$ok)throw new RuntimeException($label);$checks++;};
$reject=static function(callable $call,string $label)use($check){try{$call();}catch(DomainException){$check(true,$label);return;}throw new RuntimeException('Expected rejection: '.$label);};
$json=static fn($p)=>json_encode($p,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
try {
 $db->exec('CREATE DATABASE '.$quote($name).' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$created=true;$db->exec('USE '.$quote($name));$db->exec('SET FOREIGN_KEY_CHECKS=0');
 foreach(array_keys($before) as $t)$db->exec($live->query('SHOW CREATE TABLE '.$quote($t))->fetch(PDO::FETCH_NUM)[1]);
 foreach(array_keys($before) as $t)$db->exec('INSERT INTO '.$quote($t).' SELECT * FROM '.$quote(DB_NAME).'.'.$quote($t));$db->exec('SET FOREIGN_KEY_CHECKS=1');
 $check($fingerprint($db)===$before,'Clone initially matches every original table');
 $admin=(int)$db->query("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE u.is_active=1 AND r.nama='Superadmin' LIMIT 1")->fetchColumn();
 $teacher=(int)$db->query("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE u.is_active=1 AND r.nama<>'Superadmin' LIMIT 1")->fetchColumn();
 $sid=(int)$db->query("SELECT p.sesi_id FROM erapor_publikasi_pdf p JOIN erapor_sesi s ON s.id=p.sesi_id WHERE s.kondisi='Regular' AND EXISTS(SELECT 1 FROM erapor_sesi_penyetuju a JOIN erapor_persetujuan_snapshot x ON x.sesi_penyetuju_id=a.id WHERE a.sesi_id=p.sesi_id AND a.kode='KEPALA_SEKOLAH') ORDER BY p.sesi_id LIMIT 1")->fetchColumn();
 if(!$sid)throw new RuntimeException('Requires a local approved regular session');
 $check(!EraporPdfRevision::available($db),'Deploy before migration gracefully has no revision schema');
 $check(EraporPdfRevision::activeArtifact($db,$sid)===null,'Pre-migration preview remains original');
 $check(EraporPdfRevision::readiness($db,$admin,$sid)['code']==='REVISION_SCHEMA_MISSING','Pre-migration readiness blocks without writes');
 $file=ROOT_PATH.'/database/migrations/'.EraporPdfRevision::MIGRATION.'.sql';$plan=EraporMigrationRunner::plan($file);
 $check(count($plan['steps'])===4&&!array_filter($plan['steps'],static fn($s)=>!str_starts_with($s,'CREATE TABLE erapor_pdf_')),'Migration contains only four additive CREATE TABLE statements');
 EraporMigrationRunner::apply($db,$file);$check(EraporMigrationRunner::apply($db,$file)==='already_applied','DDL retry is idempotent');
 $tables=array_values(array_diff(array_keys($before),['erapor_migrations']));$check($fingerprint($db,$tables)===$fingerprint($live,$tables),'Migration preserves every original business table');
 $check(EraporPdfRevision::available($db),'Completed ledger/schema is recognized');
 // Explicit legacy proof fixture, using the real data capture; no original source DB/file is changed.
 $epoch=(int)$db->query('SELECT UNIX_TIMESTAMP(dibuat_pada) FROM erapor_publikasi_pdf WHERE sesi_id='.$sid)->fetchColumn();
 $clock=new DateTimeImmutable('2026-09-17 07:23:45',new DateTimeZone('Asia/Makassar'));
 $p=EraporPublication::historicalPackage($db,$sid,$clock,$epoch);
 $originalClass=$db->query('SELECT level_kelas,nama_kelas FROM kelas WHERE id='.(int)$p['session']['kelas_id'])->fetch();
 $p['student']['level_kelas']=$originalClass['level_kelas'];$p['student']['nama_kelas']=trim($originalClass['level_kelas'].' '.$originalClass['nama_kelas']);
 foreach($p['documents'] as &$d){if(is_array($d['signature_current']))unset($d['signature_current']['signers']['GURU_KELAS']);foreach($d['signature_periods'] as &$b)unset($b['signers']['GURU_KELAS']);unset($b);}unset($d);
 $source=EraporPublication::manifestSource($p);$source['renderer']='erapor-print-v8';unset($source['exclude_ppi']);$sourceHash=hash('sha256',$json($source));
 $row=$db->query('SELECT * FROM erapor_publikasi_pdf WHERE sesi_id='.$sid)->fetch();$m=json_decode($row['manifest'],true);
 $m['renderer']='erapor-print-v8';$m['tanggal_pengesahan']='2026-09-17';$m['school']=$p['school']['snapshot'];$m['signers']=EraporPublication::signerManifest($p);$m['source_sha256']=$sourceHash;
 $m['documents']=array_map(static function($d){$o=['id'=>(int)$d['id'],'rubrik_id'=>(int)$d['rubrik_id'],'kode'=>$d['kode'],'jenis'=>$d['jenis_dokumen'],'versi'=>(int)$d['versi'],'seed_sha256'=>$d['seed_sha256']];if($d['jenis_dokumen']==='AGAMA')$o['narrative_template_version']=$d['narrative_version'];return $o;},$p['documents']);
 $db->prepare('UPDATE erapor_publikasi_pdf SET manifest=?,renderer_versi=?,sumber_sha256=? WHERE sesi_id=?')->execute([$json($m),'erapor-print-v8',$sourceHash,$sid]);
 $businessBefore=$fingerprint($db,$tables);$reason='Pemeriksaan sintetis revisi desain Quicksand.';
 $r=EraporPdfRevision::readiness($db,$admin,$sid);$check($r['ready']&&$r['method']==='HASH_HISTORIS','Legacy source/time recovered by exact original source hash: '.($r['code']??''));
 $check((int)$db->query('SELECT COUNT(*) FROM erapor_pdf_sumber')->fetchColumn()===0,'Readiness never archives/changes anything');
 $check($fingerprint($db,$tables)===$businessBefore,'Legacy recovery is read-only for every original business table');
 $studentId=(int)$p['session']['murid_id'];$classBefore=$db->query('SELECT * FROM murid WHERE id='.$studentId)->fetch();
 $otherClass=(int)$db->query('SELECT id FROM kelas WHERE id<>'.(int)$p['session']['kelas_id'].' LIMIT 1')->fetchColumn();
 $db->prepare('UPDATE murid SET kelas_id=? WHERE id=?')->execute([$otherClass,$studentId]);
 $moved=EraporPdfRevision::readiness($db,$admin,$sid);
 $check($moved['ready']&&$moved['package_sha256']===$r['package_sha256'],'Class transfer recovers the session original class only when the full historical digest matches');
 $keys=array_values(array_intersect(['kelas_id','updated_at'],array_keys($classBefore)));$db->prepare('UPDATE murid SET '.implode(',',array_map(static fn($k)=>$k.'=?',$keys)).' WHERE id=?')->execute([...array_map(static fn($k)=>$classBefore[$k],$keys),$studentId]);
 $check($fingerprint($db,$tables)===$businessBefore,'Class-transfer fixture restores all original fields without changing source business data');
 $reject(fn()=>EraporPdfRevision::prepare($db,$teacher,$sid,'RAPOR',$r['package_sha256'],$r['design_sha256'],$reason),'Non-Superadmin cannot prepare');
 $reject(fn()=>EraporPdfRevision::prepare($db,$admin,$sid,'RAPOR',str_repeat('a',64),$r['design_sha256'],$reason),'Stale/mismatched source proof rejected');
 $reject(fn()=>EraporPdfRevision::prepare($db,$admin,$sid,'RAPOR',$r['package_sha256'],str_repeat('b',64),$reason),'Changed design proof rejected');
 $reject(fn()=>EraporPdfRevision::prepare($db,$admin,$sid,'RAPOR',$r['package_sha256'],$r['design_sha256'],'short'),'Audit reason required');
 $id=EraporPdfRevision::prepare($db,$admin,$sid,'RAPOR',$r['package_sha256'],$r['design_sha256'],$reason);
 $check($id>0&&EraporPdfRevision::selection($db,$sid,'RAPOR')==='asli','Preparing creates immutable revision, not activation');
 $check(EraporPdfRevision::prepare($db,$admin,$sid,'RAPOR',$r['package_sha256'],$r['design_sha256'],$reason)===$id,'Repeated preparation reuses matching revision, not another timestamped PDF');
 $check((int)$db->query('SELECT COUNT(*) FROM erapor_pdf_revisi_log')->fetchColumn()===1,'Preparation retry adds no duplicate audit');
 $rev=EraporPdfRevision::reviewArtifact($db,$admin,$sid,'RAPOR',$id);$check(str_starts_with($rev['bytes'],'%PDF-')&&$rev['pages']>0,'Revised output is a real Dompdf artifact');
 $check($rev['package']['published_at']==='2026-09-17 07:23:45','Original timestamp/date/age recovered, not today');
 $check(EraporPublication::signerManifest($rev['package'])===$m['signers'],'No extra teacher/current-profile evidence added to legacy package');
 $reject(fn()=>EraporPdfRevision::activate($db,$teacher,$sid,'RAPOR',$id,'asli',$rev['sha256'],$reason),'Non-Superadmin cannot activate');
 $reject(fn()=>EraporPdfRevision::activate($db,$admin,$sid,'RAPOR',$id,'9999',$rev['sha256'],$reason),'Stale active selection cannot be overwritten');
 $reject(fn()=>EraporPdfRevision::activate($db,$admin,$sid,'RAPOR',$id,'asli',str_repeat('c',64),$reason),'Wrong preview hash cannot activate');
 $reject(fn()=>EraporPdfRevision::reviewArtifact($db,$admin,$sid+99999,'RAPOR',$id),'Revision cannot cross session boundary');
 $reject(fn()=>EraporPdfRevision::reviewArtifact($db,$admin,$sid,'PPI',$id),'Revision cannot cross package boundary');
 EraporPdfRevision::activate($db,$admin,$sid,'RAPOR',$id,'asli',$rev['sha256'],$reason);
 EraporPdfRevision::activate($db,$admin,$sid,'RAPOR',$id,'asli',$rev['sha256'],$reason);
 $check(EraporPdfAccess::previewArtifact($db,$sid)['bytes']===$rev['bytes'],'Internal preview uses selected revised bytes');
 $check(EraporPdfAccess::officialArtifact($db,$sid)['bytes']===$row['pdf_bytes'],'Distribution original accessor remains original bytes');
 $check((int)$db->query('SELECT COUNT(*) FROM erapor_pdf_revisi_log')->fetchColumn()===2,'Activation retry adds no duplicate audit');
 $db->setAttribute(PDO::ATTR_STRINGIFY_FETCHES,true);
 $check(EraporPdfAccess::previewArtifact($db,$sid)['bytes']===$rev['bytes']&&EraporPdfRevision::readiness($db,$admin,$sid)['package_sha256']===$r['package_sha256'],'Active/frozen source survives string-typed numeric PDO results');
 $db->setAttribute(PDO::ATTR_STRINGIFY_FETCHES,false);
 $db->prepare('UPDATE erapor_publikasi_pdf SET pdf_sha256=? WHERE sesi_id=?')->execute([str_repeat('e',64),$sid]);
 $reject(fn()=>EraporPdfRevision::activeArtifact($db,$sid),'An active revision cannot hide a corrupt original artifact');
 $db->prepare('UPDATE erapor_publikasi_pdf SET pdf_sha256=? WHERE sesi_id=?')->execute([$row['pdf_sha256'],$sid]);
 // Frozen source must survive later edits without reading/substituting current identity/profile fields.
 $student=(int)$p['session']['murid_id'];$oldStudent=$db->query('SELECT * FROM murid WHERE id='.$student)->fetch();$oldName=$oldStudent['nama_lengkap'];
 $restoreStudent=static function()use($db,$oldStudent,$student):void {$keys=array_values(array_intersect(['nama_lengkap','updated_at'],array_keys($oldStudent)));$q=$db->prepare('UPDATE murid SET '.implode(',',array_map(static fn($k)=>$k.'=?',$keys)).' WHERE id=?');$q->execute([...array_map(static fn($k)=>$oldStudent[$k],$keys),$student]);};$db->prepare("UPDATE murid SET nama_lengkap='IDENTITAS BERUBAH SETELAH ARSIP' WHERE id=?")->execute([$student]);
 $check(EraporPdfRevision::readiness($db,$admin,$sid)['package_sha256']===$r['package_sha256'],'Archived full source is independent of changed current identity');
 $check(EraporPdfRevision::activePackage($db,$sid)['student']['nama_lengkap']===$p['student']['nama_lengkap'],'Scoped source remains historical, not current identity');
 $restoreStudent();
 $reject(fn()=>EraporPdfRevision::restore($db,$admin,$sid,'RAPOR','asli',$reason),'Stale restore cannot discard another active revision');
 EraporPdfRevision::restore($db,$admin,$sid,'RAPOR',(string)$id,$reason);EraporPdfRevision::restore($db,$admin,$sid,'RAPOR',(string)$id,$reason);
 $check(EraporPdfAccess::previewArtifact($db,$sid)['bytes']===$row['pdf_bytes'],'Restoring original changes only the pointer');
 $check(count(EraporPdfRevision::history($db,$admin,$sid,'RAPOR'))===1,'Restoring keeps all immutable revision history');
 $check((int)$db->query('SELECT COUNT(*) FROM erapor_pdf_revisi_log')->fetchColumn()===3,'Restore retry adds no duplicate audit');
 $db->exec("UPDATE erapor_pdf_revisi SET pdf_sha256='".str_repeat('d',64)."' WHERE id=$id");
 $reject(fn()=>EraporPdfRevision::reviewArtifact($db,$admin,$sid,'RAPOR',$id),'Tampered revised bytes/hash rejected');
 $db->prepare('UPDATE erapor_pdf_revisi SET pdf_sha256=? WHERE id=?')->execute([$rev['sha256'],$id]);
 $snapshot=$db->query("SELECT paket_json FROM erapor_pdf_sumber WHERE sesi_id=$sid AND jenis='RAPOR'")->fetchColumn();
 $db->prepare("UPDATE erapor_pdf_sumber SET paket_json='{}' WHERE sesi_id=? AND jenis='RAPOR'")->execute([$sid]);
 $check(EraporPdfRevision::readiness($db,$admin,$sid)['code']==='ARCHIVED_SOURCE_INTEGRITY_FAILED','Tampered frozen package rejected instead of recapturing live values');
 $db->prepare("UPDATE erapor_pdf_sumber SET paket_json=? WHERE sesi_id=? AND jenis='RAPOR'")->execute([$snapshot,$sid]);
 $check($fingerprint($db,$tables)===$businessBefore,'All original artifacts, signatures, values, dates, counters, distribution unchanged by revision lifecycle');
 // Remove the constructed source/revision ONLY in this clone, to test failure before any snapshot exists.
 foreach(['erapor_pdf_revisi_log','erapor_pdf_aktif','erapor_pdf_revisi','erapor_pdf_sumber'] as $t)$db->exec('DELETE FROM '.$t.' WHERE sesi_id='.$sid);
 $db->prepare("UPDATE murid SET nama_lengkap='DATA TERKINI BUKAN SUMBER LAMA' WHERE id=?")->execute([$student]);
 $check(EraporPdfRevision::readiness($db,$admin,$sid)['code']==='HISTORICAL_SOURCE_HASH_MISMATCH','Current identity mismatch fails closed before recovery');
 $check((int)$db->query('SELECT COUNT(*) FROM erapor_pdf_sumber')->fetchColumn()===0,'Mismatch never leaves an unverified snapshot');
 $restoreStudent();
 $oldSigners=$m['signers'];$m['signers'][0]['name']='BUKAN PENANDATANGAN ASLI';$db->prepare('UPDATE erapor_publikasi_pdf SET manifest=? WHERE sesi_id=?')->execute([$json($m),$sid]);
 $check(EraporPdfRevision::readiness($db,$admin,$sid)['code']==='HISTORICAL_SIGNERS_MISMATCH','A mismatched signer manifest cannot be replaced by a current profile');
 $m['signers']=$oldSigners;$db->prepare('UPDATE erapor_publikasi_pdf SET manifest=? WHERE sesi_id=?')->execute([$json($m),$sid]);
 // Layout definitions are outside the legacy value projection. Rendering failure must roll back the new snapshot.
 $rid=(int)$p['documents'][0]['rubrik_id'];$defs=$db->query('SELECT * FROM erapor_rubrik_penandatangan WHERE rubrik_id='.$rid)->fetchAll();$db->exec('DELETE FROM erapor_rubrik_penandatangan WHERE rubrik_id='.$rid);
 $bad=EraporPdfRevision::readiness($db,$admin,$sid);
 $check($bad['ready'],'Value/source proof remains separate from actual layout validation');
 $reject(fn()=>EraporPdfRevision::prepare($db,$admin,$sid,'RAPOR',$bad['package_sha256'],$bad['design_sha256'],$reason),'Renderer failure rolls back the just-inserted historical snapshot');
 $check((int)$db->query('SELECT COUNT(*) FROM erapor_pdf_sumber')->fetchColumn()===0&&(int)$db->query('SELECT COUNT(*) FROM erapor_pdf_revisi')->fetchColumn()===0,'Failed render leaves no source/revision/audit or active pointer');
 foreach($defs as $def){$sql='INSERT INTO erapor_rubrik_penandatangan('.implode(',',array_keys($def)).') VALUES('.implode(',',array_fill(0,count($def),'?')).')';$db->prepare($sql)->execute(array_values($def));}
 $check($fingerprint($db,$tables)===$businessBefore,'Negative fixtures restored every business table');
 $db->exec('DROP TABLE erapor_pdf_revisi_log');
 $reject(fn()=>EraporPdfRevision::available($db),'Partial schema fails closed, not silent fallback to another preview');
 $check($fingerprint($live)===$before,'Every original source database table is untouched');
 echo "PASS: $checks disposable MySQL revision checks (source proof, RBAC, immutable revisions, stale checks, activate/restore, no original/R2/distribution changes).\n";
} finally {if($db->inTransaction())$db->rollBack();if($created&&preg_match('/^zivana_revision_test_[a-f0-9]{16}$/D',$name)&&$name!==DB_NAME)$db->exec('DROP DATABASE '.$quote($name));}
